<?php
declare(strict_types=1);

/*
 * Favor de Jack · integridad de la base de datos.
 *
 * Inventario, Relaciones y Fichas guardan el ID del personaje sin una clave
 * foránea: la base no impedía apuntar a un personaje que no existe. Aquí se
 * cuentan las referencias huérfanas y, solo si no hay ninguna, se añaden las
 * claves (ON UPDATE CASCADE para seguir al personaje si cambia de ID; borrar
 * un personaje con objetos, relaciones o ficha queda bloqueado, nunca en
 * cascada). Lo lanza el admin a mano desde admin/respaldo.php: nada de esto
 * corre en las páginas normales. No borra ni cambia datos.
 */

/** Claves que debería tener la base: nombre => [tabla, columna]. */
const FAVOR_INTEGRITY_KEYS = [
    'fk_inventario_personaje' => ['InventarioItem', 'PersonajeID'],
    'fk_relacion_personaje_a' => ['PersonajeRelacion', 'PersonajeA'],
    'fk_relacion_personaje_b' => ['PersonajeRelacion', 'PersonajeB'],
    'fk_ficha_personaje' => ['PersonajeFicha', 'PersonajeID'],
];

function favor_integrity_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

/** Estado de cada clave: [nombre => [tabla, columna, existe, huérfanas]]. */
function favor_integrity_report(mysqli $db): array
{
    $present = [];
    $result = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    while ($row = $result->fetch_row()) $present[(string)$row[0]] = true;
    $result->free();

    $report = [];
    foreach (FAVOR_INTEGRITY_KEYS as $name => [$table, $column]) {
        if (!favor_integrity_table_exists($db, $table)) continue;
        $orphans = (int)$db->query(
            "SELECT COUNT(*) FROM `$table` t LEFT JOIN Personaje p ON p.ID = t.`$column` WHERE p.ID IS NULL"
        )->fetch_row()[0];
        $report[$name] = ['table' => $table, 'column' => $column, 'exists' => isset($present[$name]), 'orphans' => $orphans];
    }
    return $report;
}

/** Añade las claves que faltan y no tienen huérfanas. Devuelve [añadidas, pendientes por huérfanas]. */
function favor_integrity_apply(mysqli $db): array
{
    $added = [];
    $blocked = [];
    foreach (favor_integrity_report($db) as $name => $r) {
        if ($r['exists']) continue;
        if ($r['orphans'] > 0) {
            $blocked[] = $r['table'] . '.' . $r['column'] . ' (' . $r['orphans'] . ')';
            continue;
        }
        $db->query("ALTER TABLE `{$r['table']}` ADD CONSTRAINT `$name` FOREIGN KEY (`{$r['column']}`) REFERENCES Personaje (ID) ON UPDATE CASCADE");
        $added[] = $r['table'] . '.' . $r['column'];
    }
    return [$added, $blocked];
}
