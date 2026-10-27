<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Descuento del gremio marcado por personaje.
 *
 * Se guarda en tiendas/descuentos.json (el navegador no puede leerlo; viaja en el
 * respaldo). La primera vez se llena con las «X» de la planilla «Descuento Hecho»
 * (tiendas/lapocionpicante/registro-descuentos.json), buscando cada nombre entre
 * los personajes de la Banca.
 */

const FAVOR_DISCOUNTS_FILE = __DIR__ . '/../tiendas/descuentos.json';
const FAVOR_DISCOUNTS_SEED = __DIR__ . '/../tiendas/lapocionpicante/registro-descuentos.json';
const FAVOR_DISCOUNTS_DEFAULT_PERCENT = 10;

function favor_discount_name_key(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if (is_string($ascii) && $ascii !== '') $name = $ascii;
    return preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
}

/** Personajes activos de jugadores activos: [id => [id, name, playerId, playerName]]. */
function favor_discount_characters(?string $onlyPlayer = null): array
{
    $db = favor_db();
    try {
        $sql = 'SELECT p.ID, p.Nombre, p.Jugador, j.Nombre AS JugadorNombre FROM Personaje p '
            . 'INNER JOIN Jugador j ON j.ID = p.Jugador WHERE p.Activo = 1 AND j.Activo = 1';
        if ($onlyPlayer !== null) $sql .= ' AND p.Jugador = ?';
        $sql .= ' ORDER BY j.Nombre, p.Orden, p.Nombre';
        $stmt = $db->prepare($sql);
        if ($onlyPlayer !== null) $stmt->bind_param('s', $onlyPlayer);
        $stmt->execute();
        $result = $stmt->get_result();
        $out = [];
        while ($row = $result->fetch_assoc()) {
            $out[(string)$row['ID']] = [
                'id' => (string)$row['ID'],
                'name' => (string)$row['Nombre'],
                'playerId' => (string)$row['Jugador'],
                'playerName' => (string)$row['JugadorNombre'],
            ];
        }
        $stmt->close();
        return $out;
    } finally {
        $db->close();
    }
}

/** Primera carga: marca los personajes cuyo nombre tenía «X» en la planilla. */
function favor_discounts_seed(array $characters): array
{
    $data = ['percent' => FAVOR_DISCOUNTS_DEFAULT_PERCENT, 'characters' => [], 'unmatched' => []];
    $raw = is_file(FAVOR_DISCOUNTS_SEED) ? json_decode((string)file_get_contents(FAVOR_DISCOUNTS_SEED), true) : null;
    $byName = [];
    foreach ($characters as $c) $byName[favor_discount_name_key($c['name'])][] = $c['id'];
    foreach ((array)($raw['entries'] ?? []) as $entry) {
        if (empty($entry['marked'])) continue;
        $key = favor_discount_name_key((string)($entry['name'] ?? ''));
        // «Agni Vaelthorne» en la planilla es «Agni» en la Banca: prueba también el primer nombre.
        $ids = $byName[$key] ?? $byName[explode(' ', trim($key))[0]] ?? [];
        if (!$ids) $data['unmatched'][] = (string)($entry['name'] ?? '');
        foreach ($ids as $id) $data['characters'][$id] = true;
    }
    return $data;
}

function favor_discounts_load(): array
{
    if (is_file(FAVOR_DISCOUNTS_FILE)) {
        $data = json_decode((string)file_get_contents(FAVOR_DISCOUNTS_FILE), true);
        if (is_array($data)) {
            return [
                'percent' => max(0, min(100, (int)($data['percent'] ?? 0))),
                'characters' => array_filter((array)($data['characters'] ?? []), static fn($v) => $v === true),
                'unmatched' => array_values(array_map('strval', (array)($data['unmatched'] ?? []))),
            ];
        }
    }
    return favor_discounts_seed(favor_discount_characters());
}

function favor_discounts_save(array $data, string $author): void
{
    $data['updatedBy'] = $author;
    $data['updatedAt'] = gmdate('c');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = FAVOR_DISCOUNTS_FILE . '.tmp';
    if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, FAVOR_DISCOUNTS_FILE)) {
        throw new RuntimeException('No pude guardar tiendas/descuentos.json. Revisa los permisos de la carpeta tiendas/.');
    }
}

/**
 * Datos para una tienda: el porcentaje y los personajes que la persona puede
 * elegir al comprar (los suyos; un admin ve todos), cada uno con su marca.
 */
function favor_discount_shop_data(?array $user): array
{
    if (!$user) return ['percent' => 0, 'characters' => [], 'admin' => false];
    try {
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $characters = favor_discount_characters($isAdmin ? null : (string)$user['playerId']);
        $data = favor_discounts_load();
    } catch (Throwable $e) {
        return ['percent' => 0, 'characters' => [], 'admin' => false];
    }
    $list = [];
    foreach ($characters as $c) {
        $list[] = $c + ['discount' => !empty($data['characters'][$c['id']])];
    }
    return ['percent' => (int)$data['percent'], 'characters' => $list, 'admin' => $isAdmin];
}
