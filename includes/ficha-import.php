<?php
declare(strict_types=1);

/**
 * Importación de fichas PDF: compara la ficha con lo que hay en la web
 * y aplica los cambios elegidos en Archivo e Inventario.
 * El dinero de la ficha no se toca: cada jugador lo lleva en la Banca Digital.
 */

require_once __DIR__ . '/ficha-pdf.php';

const FICHA_MAX_BYTES = 15 * 1024 * 1024;

function ficha_ensure_schema(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS PersonajeFicha (
        PersonajeID varchar(100) NOT NULL PRIMARY KEY,
        Datos longtext NOT NULL,
        Archivo varchar(255) NULL,
        ImportadoPor varchar(100) NULL,
        Importado datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Carpeta privada donde se guardan los PDF (el .htaccess impide abrirlos desde el navegador). */
function ficha_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/archivosdelgremio/fichas';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('El servidor no pudo crear la carpeta archivosdelgremio/fichas/.');
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('La carpeta archivosdelgremio/fichas/ no tiene permiso de escritura para PHP.');
    }
    return $dir;
}

function ficha_pdf_path(string $characterId): string
{
    return ficha_storage_dir() . '/' . preg_replace('~[^A-Za-z0-9_-]~', '_', $characterId) . '.pdf';
}

/** Borra las subidas pendientes que nadie confirmó en 24 h. */
function ficha_cleanup_pending(): void
{
    foreach (glob(ficha_storage_dir() . '/tmp-*.pdf') ?: [] as $file) {
        if (@filemtime($file) < time() - 86400) @unlink($file);
    }
}

function ficha_can_edit(array $user, array $character): bool
{
    return ($user['role'] ?? '') === 'admin' || (string)($user['playerId'] ?? '') === (string)($character['Jugador'] ?? '');
}

function ficha_q(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) $types .= is_int($p) ? 'i' : 's';
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** Personajes que este usuario puede actualizar con una ficha. */
function ficha_editable_characters(mysqli $db, array $user): array
{
    $sql = 'SELECT p.ID,p.Jugador,p.Nombre,p.Activo,j.Nombre AS JugadorNombre,pp.NombreCompleto '
        . 'FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID ';
    $params = [];
    if (($user['role'] ?? '') !== 'admin') {
        $sql .= 'WHERE p.Jugador=? ';
        $params[] = (string)$user['playerId'];
    }
    $sql .= 'ORDER BY COALESCE(j.Orden,9999), j.Nombre, p.Nombre';
    $stmt = ficha_q($db, $sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ficha_players(mysqli $db, array $user): array
{
    if (($user['role'] ?? '') === 'admin') {
        $res = $db->query('SELECT ID,Nombre,Activo FROM Jugador ORDER BY Activo DESC, COALESCE(Orden,9999), Nombre');
    } else {
        $res = ficha_q($db, 'SELECT ID,Nombre,Activo FROM Jugador WHERE ID=?', [(string)$user['playerId']])->get_result();
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

/** El personaje que mejor coincide con el nombre de la ficha (o null). */
function ficha_guess_character(array $characters, string $name): ?string
{
    $target = ficha_fold($name);
    if ($target === '') return null;
    foreach ($characters as $c) {
        if (ficha_fold((string)$c['Nombre']) === $target || ficha_fold((string)($c['NombreCompleto'] ?? '')) === $target) return (string)$c['ID'];
    }
    foreach ($characters as $c) {
        $short = ficha_fold((string)$c['Nombre']);
        if ($short !== '' && (str_starts_with($target, $short . ' ') || str_starts_with($short, $target . ' '))) return (string)$c['ID'];
    }
    return null;
}

function ficha_profile_labels(): array
{
    return [
        'NombreCompleto' => 'Nombre completo', 'Raza' => 'Raza', 'Clase' => 'Clase', 'Nivel' => 'Nivel',
        'Edad' => 'Edad', 'Altura' => 'Altura (m)',
        'Fuerza' => 'Fuerza', 'Destreza' => 'Destreza', 'Constitucion' => 'Constitución',
        'Inteligencia' => 'Inteligencia', 'Sabiduria' => 'Sabiduría', 'Carisma' => 'Carisma',
        'Biografia' => 'Historia / biografía',
    ];
}

/**
 * Calcula qué cambiaría al aplicar la ficha a un personaje (o a uno nuevo si $characterId es null).
 */
function ficha_plan(mysqli $db, array $parsed, ?string $characterId): array
{
    $current = null;
    $profile = null;
    $existingItems = [];
    if ($characterId !== null) {
        $stmt = ficha_q($db, 'SELECT p.*, j.Nombre AS JugadorNombre FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador WHERE p.ID=?', [$characterId]);
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$current) throw new RuntimeException('Ese personaje ya no existe.');
        $stmt = ficha_q($db, 'SELECT * FROM PersonajePerfil WHERE ID=?', [$characterId]);
        $profile = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        $existingItems = ficha_existing_items($db, $characterId);
    }

    $fields = [];
    foreach (ficha_profile_labels() as $col => $label) {
        $new = $parsed['profile'][$col] ?? null;
        if ($new === null || $new === '') continue;
        $old = $profile[$col] ?? null;
        $oldText = $old === null ? '' : (string)$old;
        $newText = (string)$new;
        if ($col === 'Altura' && $old !== null) $oldText = rtrim(rtrim(number_format((float)$old, 2, '.', ''), '0'), '.');
        if ($col === 'Altura') $newText = rtrim(rtrim(number_format((float)$new, 2, '.', ''), '0'), '.');
        if ($oldText === $newText) continue;
        $fields[] = [
            'col' => $col, 'label' => $label, 'old' => $oldText, 'new' => $newText,
            'isNew' => trim($oldText) === '',
            // Por defecto se queda lo que ya está escrito cuando es más completo:
            // una biografía existente, o un nombre como "Agni Vaelthorne" frente a "Agni".
            'useSheet' => ficha_default_use_sheet($col, $oldText, $newText),
        ];
    }

    $items = [];
    foreach ($parsed['items'] as $i => $item) {
        $existing = $existingItems[ficha_fold($item['name'])] ?? null;
        $diffs = $existing ? ficha_item_diffs($existing, $item) : [];
        $kind = $item['kind'] ?? 'item';
        $items[] = $item + [
            'index' => $i,
            // nuevo = se añade; igual = ya está tal cual; distinto = ya existe con diferencias (se pregunta);
            // conjuro / dinero = no es un objeto: no se propone salvo que se marque a mano.
            'status' => $existing !== null ? ($diffs ? 'distinto' : 'igual') : ($kind === 'spell' ? 'conjuro' : ($kind === 'money' ? 'dinero' : 'nuevo')),
            'diffs' => $diffs,
        ];
    }

    return [
        'character' => $current,
        'hasProfile' => $profile !== null,
        'fields' => $fields,
        'items' => $items,
    ];
}

function ficha_default_use_sheet(string $col, string $old, string $new): bool
{
    $old = trim($old);
    if ($old === '') return true;
    if ($col === 'Biografia') return false;
    if ($col === 'NombreCompleto' && mb_strlen($old) > mb_strlen($new) && str_starts_with(ficha_fold($old), ficha_fold($new))) return false;
    return true;
}

/** Objetos actuales del personaje, por nombre normalizado. */
function ficha_existing_items(mysqli $db, string $characterId): array
{
    $stmt = ficha_q($db, 'SELECT ID,Nombre,Categoria,Cantidad,Equipado,Descripcion FROM InventarioItem WHERE PersonajeID=? ORDER BY ID', [$characterId]);
    $out = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $key = ficha_fold((string)$row['Nombre']);
        if (!isset($out[$key])) $out[$key] = $row;
    }
    $stmt->close();
    return $out;
}

/** Diferencias importantes entre un objeto del inventario y el de la ficha. */
function ficha_item_diffs(array $existing, array $item): array
{
    $diffs = [];
    if ((int)$existing['Cantidad'] !== (int)$item['quantity']) {
        $diffs['quantity'] = ['label' => 'Cantidad', 'old' => (string)$existing['Cantidad'], 'new' => (string)$item['quantity']];
    }
    if ((bool)$existing['Equipado'] !== (bool)$item['equipped'] && $item['equipped']) {
        $diffs['equipped'] = ['label' => 'Equipado', 'old' => 'no', 'new' => 'sí'];
    }
    $desc = trim((string)$item['description']);
    if ($desc !== '' && trim((string)$existing['Descripcion']) !== $desc) {
        $diffs['description'] = ['label' => 'Descripción', 'old' => trim((string)$existing['Descripcion']), 'new' => $desc];
    }
    return $diffs;
}

/**
 * Aplica la ficha. $choice: target ('existing'|'new'), characterId, playerId,
 * fields (lista de columnas), items (índices nuevos a añadir),
 * itemActions (índice => keep|update|add para objetos que ya existen).
 * Devuelve el ID del personaje y un resumen.
 */
function ficha_apply(mysqli $db, array $user, array $parsed, array $choice, string $pdfTmpPath): array
{
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $author = mb_substr((string)($user['displayName'] ?? $user['username'] ?? ''), 0, 100, 'UTF-8');
    $summary = [];

    $db->begin_transaction();
    try {
        if ($choice['target'] === 'new') {
            $playerId = (string)$choice['playerId'];
            if (!$isAdmin && $playerId !== (string)$user['playerId']) throw new RuntimeException('Solo puedes crear personajes para tu propio jugador.');
            $stmt = ficha_q($db, 'SELECT ID FROM Jugador WHERE ID=?', [$playerId]);
            $player = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$player) throw new RuntimeException('Selecciona el jugador del personaje.');

            $shortName = mb_substr(trim((string)$parsed['name']), 0, 100, 'UTF-8');
            if ($shortName === '') throw new RuntimeException('La ficha no tiene nombre de personaje.');
            $stmt = ficha_q($db, 'SELECT ID FROM Personaje WHERE Jugador=? AND LOWER(TRIM(Nombre))=LOWER(TRIM(?)) LIMIT 1', [$playerId, $shortName]);
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($dup) throw new RuntimeException('Ese jugador ya tiene un personaje llamado "' . $shortName . '". Elige "Actualizar un personaje existente".');

            do {
                $characterId = 'arch-' . bin2hex(random_bytes(14));
                $stmt = ficha_q($db, 'SELECT ID FROM Personaje WHERE ID=?', [$characterId]);
                $taken = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
            } while ($taken);

            $stmt = ficha_q($db, 'SELECT COALESCE(MAX(Orden),-1)+1 AS n FROM Personaje WHERE Jugador=?', [$playerId]);
            $order = (int)$stmt->get_result()->fetch_assoc()['n'];
            $stmt->close();
            ficha_q($db, 'INSERT INTO Personaje (ID,Jugador,Nombre,Platino,Oro,Plata,Cobre,Descripcion,Notas,Orden,Activo) VALUES (?,?,?,0,0,0,0,\'\',\'\',?,1)',
                [$characterId, $playerId, $shortName, $order])->close();
            $summary[] = 'Personaje creado en la Banca.';
            $choice['fields'] = array_keys(ficha_profile_labels());
        } else {
            $characterId = (string)$choice['characterId'];
            $stmt = ficha_q($db, 'SELECT * FROM Personaje WHERE ID=? FOR UPDATE', [$characterId]);
            $character = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$character) throw new RuntimeException('Ese personaje ya no existe.');
            if (!ficha_can_edit($user, $character)) throw new RuntimeException('No puedes modificar ese personaje.');
        }

        // Archivo (PersonajePerfil)
        $stmt = ficha_q($db, 'SELECT ID FROM PersonajePerfil WHERE ID=?', [$characterId]);
        $hasProfile = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$hasProfile) {
            ficha_q($db, "INSERT INTO PersonajePerfil (ID,NombreCompleto,Estado,Categoria) VALUES (?,?,'Activo','Personaje')",
                [$characterId, mb_substr((string)$parsed['name'], 0, 150, 'UTF-8')])->close();
            $summary[] = 'Expediente creado en el Archivo.';
        }
        $allowed = ficha_profile_labels();
        $sets = [];
        $params = [];
        foreach (array_unique($choice['fields']) as $col) {
            if (!isset($allowed[$col])) continue;
            $value = $parsed['profile'][$col] ?? null;
            if ($value === null || $value === '') continue;
            $sets[] = "`$col`=?";
            $params[] = is_float($value) ? number_format($value, 2, '.', '') : (string)$value;
        }
        if ($sets) {
            $params[] = $characterId;
            ficha_q($db, 'UPDATE PersonajePerfil SET ' . implode(',', $sets) . ' WHERE ID=?', $params)->close();
            $summary[] = count($sets) . ' dato(s) del Archivo actualizados.';
        }

        // Inventario: añade lo nuevo; con los que ya existen hace lo que se eligió en la vista previa.
        $existing = ficha_existing_items($db, $characterId);
        $added = 0;
        $updated = 0;
        $selected = array_flip(array_map('intval', $choice['items']));
        foreach ($parsed['items'] as $i => $item) {
            $key = ficha_fold($item['name']);
            $current = $existing[$key] ?? null;
            $mode = $current === null ? (isset($selected[$i]) ? 'add' : 'skip') : (string)($choice['itemActions'][$i] ?? 'keep');
            if ($mode === 'update' && $current !== null) {
                $diffs = ficha_item_diffs($current, $item);
                if (!$diffs) continue;
                ficha_q($db, 'UPDATE InventarioItem SET Cantidad=?,Equipado=?,Descripcion=? WHERE ID=? AND PersonajeID=?', [
                    isset($diffs['quantity']) ? (int)$item['quantity'] : (int)$current['Cantidad'],
                    isset($diffs['equipped']) ? 1 : (int)$current['Equipado'],
                    isset($diffs['description']) ? mb_substr((string)$item['description'], 0, 10000, 'UTF-8') : (string)$current['Descripcion'],
                    (int)$current['ID'], $characterId,
                ])->close();
                $updated++;
                continue;
            }
            if ($mode !== 'add') continue;
            ficha_q($db, 'INSERT INTO InventarioItem (PersonajeID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,CreadoPor) VALUES (?,?,?,?,?,?,\'\',\'\',?)', [
                $characterId, $item['name'], $item['category'], (int)$item['quantity'], $item['equipped'] ? 1 : 0,
                mb_substr((string)$item['description'], 0, 10000, 'UTF-8'), mb_substr((string)($user['username'] ?? ''), 0, 50, 'UTF-8'),
            ])->close();
            if ($current === null) $existing[$key] = ['ID' => 0, 'Cantidad' => $item['quantity'], 'Equipado' => $item['equipped'], 'Descripcion' => $item['description']];
            $added++;
        }
        if ($added) $summary[] = $added . ' objeto(s) añadidos al Inventario.';
        if ($updated) $summary[] = $updated . ' objeto(s) del Inventario actualizados.';

        // Hoja completa + copia del PDF
        $data = json_encode([
            'nombre' => $parsed['name'],
            'perfil' => $parsed['profile'],
            'hoja' => $parsed['sheet'],
            'objetos' => $parsed['items'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $target = ficha_pdf_path($characterId);
        ficha_q($db, 'INSERT INTO PersonajeFicha (PersonajeID,Datos,Archivo,ImportadoPor,Importado) VALUES (?,?,?,?,UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE Datos=VALUES(Datos),Archivo=VALUES(Archivo),ImportadoPor=VALUES(ImportadoPor),Importado=VALUES(Importado)',
            [$characterId, $data, basename($target), $author])->close();
        if (!@copy($pdfTmpPath, $target)) throw new RuntimeException('No se pudo guardar la copia del PDF.');
        $summary[] = 'Hoja completa guardada en el Archivo.';

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    @unlink($pdfTmpPath);
    return ['id' => $characterId, 'summary' => $summary];
}
