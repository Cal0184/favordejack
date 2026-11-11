<?php
declare(strict_types=1);

require_once __DIR__ . '/migrations.php';

/*
 * Biblioteca del Archivo de Misiones (Archivos del Gremio).
 *
 * Cada misión «Completada» de la tabla Mision tiene un libro en la tabla
 * auxiliar MisionLibro. La misión sigue siendo el único registro de datos
 * (nombre, participantes, recompensas...): el libro solo guarda lo que no se
 * puede deducir de ella y debe quedar fijo para siempre:
 *
 *   MisionID  la misión (clave primaria: una misión nunca tiene dos libros)
 *   Modelo    1–4, elegido al azar una sola vez, distinto del libro anterior
 *   Orden     posición en el archivo (única); los libros nuevos van al final
 *
 * La estantería se calcula: posición del libro entre los visibles dividida por
 * FAVOR_BIBLIOTECA_CAPACIDAD. No depende del tamaño de pantalla.
 *
 * Si una misión deja de estar «Completada», su libro se conserva (oculto) con
 * su modelo y su orden: si vuelve a completarse reaparece igual y en su sitio.
 * Si la misión se borra, su libro se borra con ella (clave foránea).
 *
 * Los libros se crean en el servidor cada vez que alguien abre el archivo o un
 * admin guarda una misión, a partir de los datos guardados: no hace falta que
 * un admin abra la página.
 */

/** Libros por estantería: 3 baldas de 12. Fijo en todas las pantallas. */
const FAVOR_BIBLIOTECA_CAPACIDAD = 36;
const FAVOR_BIBLIOTECA_MODELOS = 4;
const FAVOR_BIBLIOTECA_ESTADO = 'Completada';

/** Crea la tabla MisionLibro (una vez; queda marcada en FavorMeta). */
function favor_biblioteca_schema(mysqli $db): void
{
    static $done = false;
    if ($done) return;
    favor_ensure_schema($db);
    $flag = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='biblioteca_v1' LIMIT 1");
    $ok = $flag && $flag->fetch_assoc();
    if ($flag) $flag->free();
    if ($ok) { $done = true; return; }

    $db->query(
        'CREATE TABLE IF NOT EXISTS MisionLibro ('
        . ' MisionID int(10) unsigned NOT NULL,'
        . ' Modelo tinyint unsigned NOT NULL,'
        . ' Orden int unsigned NOT NULL,'
        . ' Creado datetime NOT NULL DEFAULT current_timestamp(),'
        . ' PRIMARY KEY (MisionID),'
        . ' UNIQUE KEY uq_misionlibro_orden (Orden),'
        . ' CONSTRAINT fk_misionlibro_mision FOREIGN KEY (MisionID) REFERENCES Mision (ID) ON DELETE CASCADE ON UPDATE CASCADE'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('biblioteca_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
    $done = true;
}

/** Modelo al azar (1–4) que no esté en $evitar. */
function favor_biblioteca_modelo(array $evitar): int
{
    $libres = array_values(array_diff(range(1, FAVOR_BIBLIOTECA_MODELOS), array_map('intval', $evitar)));
    if (!$libres) $libres = range(1, FAVOR_BIBLIOTECA_MODELOS);
    return $libres[random_int(0, count($libres) - 1)];
}

/**
 * Crea los libros que falten y devuelve los visibles en orden:
 * [MisionID => ['model' => 1–4, 'order' => n, 'position' => 1.., 'shelf' => 1..]].
 *
 * Es idempotente: llamarla muchas veces no crea libros de más ni cambia nada.
 */
function favor_biblioteca_sync(mysqli $db): array
{
    favor_biblioteca_schema($db);
    $estado = $db->real_escape_string(FAVOR_BIBLIOTECA_ESTADO);

    // Un solo proceso a la vez escribe libros (dos visitas simultáneas no
    // pueden repetir modelo ni orden). Si el candado tarda, solo se lee.
    $lock = $db->query("SELECT GET_LOCK('favor_biblioteca', 3)");
    $locked = $lock && (int)($lock->fetch_row()[0] ?? 0) === 1;
    if ($lock) $lock->free();

    try {
        if ($locked) {
            $nuevos = [];
            $res = $db->query(
                'SELECT m.ID FROM Mision m LEFT JOIN MisionLibro b ON b.MisionID = m.ID '
                . "WHERE m.Estado = '$estado' AND b.MisionID IS NULL "
                . 'ORDER BY COALESCE(m.Fecha, DATE(m.Creado)) ASC, m.ID ASC'
            );
            while ($row = $res->fetch_row()) $nuevos[] = (int)$row[0];
            $res->free();

            if ($nuevos) {
                // El libro anterior es el último del archivo entre los visibles.
                $prev = 0;
                $orden = 0;
                $res = $db->query(
                    'SELECT b.Modelo FROM MisionLibro b JOIN Mision m ON m.ID = b.MisionID '
                    . "WHERE m.Estado = '$estado' ORDER BY b.Orden DESC LIMIT 1"
                );
                if ($row = $res->fetch_row()) $prev = (int)$row[0];
                $res->free();
                $res = $db->query('SELECT COALESCE(MAX(Orden), 0) FROM MisionLibro');
                $orden = (int)$res->fetch_row()[0];
                $res->free();

                $stmt = $db->prepare('INSERT IGNORE INTO MisionLibro (MisionID, Modelo, Orden) VALUES (?, ?, ?)');
                foreach ($nuevos as $id) {
                    $modelo = favor_biblioteca_modelo([$prev]);
                    $orden++;
                    $stmt->bind_param('iii', $id, $modelo, $orden);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) $prev = $modelo;
                }
                $stmt->close();
            }
        }

        // Libros visibles en orden.
        $libros = [];
        $res = $db->query(
            'SELECT b.MisionID, b.Modelo, b.Orden FROM MisionLibro b JOIN Mision m ON m.ID = b.MisionID '
            . "WHERE m.Estado = '$estado' ORDER BY b.Orden ASC"
        );
        while ($row = $res->fetch_row()) {
            $libros[] = ['id' => (int)$row[0], 'model' => (int)$row[1], 'order' => (int)$row[2]];
        }
        $res->free();

        // Si al borrar u ocultar un libro quedan juntos dos del mismo modelo,
        // el segundo cambia una sola vez a uno distinto de sus dos vecinos y
        // se guarda. Es lo único que puede cambiar la apariencia de un libro.
        if ($locked) {
            $fix = null;
            for ($i = 1, $n = count($libros); $i < $n; $i++) {
                if ($libros[$i]['model'] !== $libros[$i - 1]['model']) continue;
                $evitar = [$libros[$i - 1]['model']];
                if (isset($libros[$i + 1])) $evitar[] = $libros[$i + 1]['model'];
                $libros[$i]['model'] = favor_biblioteca_modelo($evitar);
                $fix ??= $db->prepare('UPDATE MisionLibro SET Modelo = ? WHERE MisionID = ?');
                $fix->bind_param('ii', $libros[$i]['model'], $libros[$i]['id']);
                $fix->execute();
            }
            if ($fix) $fix->close();
        }
    } finally {
        if ($locked) $db->query("SELECT RELEASE_LOCK('favor_biblioteca')");
    }

    $out = [];
    foreach ($libros as $i => $libro) {
        $out[$libro['id']] = [
            'model' => $libro['model'],
            'order' => $libro['order'],
            'position' => $i + 1,
            'shelf' => intdiv($i, FAVOR_BIBLIOTECA_CAPACIDAD) + 1,
        ];
    }
    return $out;
}

/** Versión que nunca rompe la página: si falla, el archivo se ve sin libros nuevos. */
function favor_biblioteca_sync_seguro(mysqli $db): array
{
    try {
        return favor_biblioteca_sync($db);
    } catch (Throwable $e) {
        error_log('biblioteca: ' . $e->getMessage());
        return [];
    }
}
