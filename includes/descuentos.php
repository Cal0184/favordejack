<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Descuentos de las tiendas, definidos por el DM.
 *
 * Cada tienda puede tener un descuento (porcentaje + regla, p. ej. «50 % en la
 * primera poción»). Cada personaje lo tiene disponible una vez por tienda: al
 * usarlo queda gastado hasta que un admin lo reactive.
 *
 * Se guarda en tiendas/descuentos.json (el navegador no puede leerlo; viaja en el
 * respaldo):
 *   shops: { <tienda>: { percent, rule, active } }
 *   used:  { <tienda>: { <personaje>: { at, by, item, price, paid, order? } } }
 *   log:   últimos movimientos (usado, reactivado, marcado a mano)
 *
 * La primera vez, los personajes con «X» en la planilla «Descuento Hecho»
 * (tiendas/lapocionpicante/registro-descuentos.json) quedan con el descuento de
 * La Poción Picante ya usado.
 */

const FAVOR_DISCOUNTS_FILE = __DIR__ . '/../tiendas/descuentos.json';
const FAVOR_DISCOUNTS_SEED = __DIR__ . '/../tiendas/lapocionpicante/registro-descuentos.json';
const FAVOR_DISCOUNTS_SEED_SHOP = 'pocion-picante';
const FAVOR_SHOPS_FILE = __DIR__ . '/../mapa/tiendas.json';
const FAVOR_DISCOUNT_LOG_MAX = 400;

/** Descuentos que el DM definió de entrada (se pueden cambiar en el panel). */
const FAVOR_DISCOUNT_DEFAULTS = [
    'pocion-picante' => ['percent' => 50, 'rule' => 'en la primera poción', 'active' => true],
    'nuevo-emporio' => ['percent' => 25, 'rule' => 'en el primer objeto mágico', 'active' => true],
];

/** Tiendas donde el jugador usa el descuento al comprar; en el resto lo marca el admin. */
const FAVOR_DISCOUNT_IN_SHOP = ['pocion-picante', 'nuevo-emporio'];

/** «Jugadores» de la Banca que no son personas (no tienen descuentos). */
const FAVOR_DISCOUNT_SKIP_PLAYERS = ['npc', 'npcs', 'muertos', 'personajes fuera', 'retirados'];

function favor_discount_name_key(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if (is_string($ascii) && $ascii !== '') $name = $ascii;
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name);
}

function favor_discount_is_admin(?array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

/**
 * Personajes que pueden tener descuento, en el orden de la Banca:
 * [id => [id, name, playerId, playerName]]. Con $onlyPlayer, solo los de ese jugador.
 * Con $includeInactive también los archivados (para cruzar la planilla).
 */
function favor_discount_characters(?string $onlyPlayer = null, bool $includeInactive = false): array
{
    $db = favor_db();
    try {
        $sql = 'SELECT p.ID, p.Nombre, p.Jugador, j.Nombre AS JugadorNombre, p.Activo AND j.Activo AS Vivo FROM Personaje p '
            . 'INNER JOIN Jugador j ON j.ID = p.Jugador';
        $where = [];
        if (!$includeInactive) $where[] = 'p.Activo = 1 AND j.Activo = 1';
        if ($onlyPlayer !== null) $where[] = 'p.Jugador = ?';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY j.Orden, j.Nombre, p.Orden, p.Nombre';
        $stmt = $db->prepare($sql);
        if ($onlyPlayer !== null) $stmt->bind_param('s', $onlyPlayer);
        $stmt->execute();
        $result = $stmt->get_result();
        $out = [];
        while ($row = $result->fetch_assoc()) {
            if (in_array(favor_discount_name_key((string)$row['JugadorNombre']), FAVOR_DISCOUNT_SKIP_PLAYERS, true)) continue;
            $out[(string)$row['ID']] = [
                'id' => (string)$row['ID'],
                'name' => (string)$row['Nombre'],
                'playerId' => (string)$row['Jugador'],
                'playerName' => (string)$row['JugadorNombre'],
                'active' => (bool)$row['Vivo'],
            ];
        }
        $stmt->close();
        return $out;
    } finally {
        $db->close();
    }
}

/** Tiendas del mapa: [id => [id, name, url, image, open]]. */
function favor_discount_shop_list(): array
{
    $raw = is_file(FAVOR_SHOPS_FILE) ? json_decode((string)file_get_contents(FAVOR_SHOPS_FILE), true) : [];
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $shop) {
        if (!is_array($shop) || empty($shop['id'])) continue;
        $image = (string)($shop['sellers'][0]['image'] ?? '');
        $out[(string)$shop['id']] = [
            'id' => (string)$shop['id'],
            'name' => (string)($shop['name'] ?? $shop['id']),
            'url' => (string)($shop['url'] ?? ''),
            // Las rutas del mapa son relativas a mapa/.
            'image' => str_starts_with($image, '../') ? substr($image, 3) : ($image !== '' ? 'mapa/' . $image : ''),
            'open' => !empty($shop['enabled']) && !empty($shop['url']),
        ];
    }
    return $out;
}

function favor_discount_settings(array $data, string $shop): array
{
    $s = (array)($data['shops'][$shop] ?? FAVOR_DISCOUNT_DEFAULTS[$shop] ?? []);
    return [
        'percent' => max(0, min(100, (int)($s['percent'] ?? 0))),
        'rule' => trim((string)($s['rule'] ?? '')),
        'active' => !empty($s['active']) && (int)($s['percent'] ?? 0) > 0,
    ];
}

function favor_discount_describe(array $settings): string
{
    if (!$settings['active']) return 'Sin descuento';
    return $settings['percent'] . ' %' . ($settings['rule'] !== '' ? ' ' . $settings['rule'] : '');
}

/** Primera carga: los personajes con «X» en la planilla ya usaron el descuento de La Poción Picante. */
function favor_discounts_seed(): array
{
    $data = ['version' => 2, 'shops' => FAVOR_DISCOUNT_DEFAULTS, 'used' => [], 'log' => [], 'unmatched' => []];
    $raw = is_file(FAVOR_DISCOUNTS_SEED) ? json_decode((string)file_get_contents(FAVOR_DISCOUNTS_SEED), true) : null;
    if (!is_array($raw)) return $data;
    try {
        $characters = favor_discount_characters(null, true);
    } catch (Throwable) {
        return $data;
    }
    $byName = [];
    foreach ($characters as $c) $byName[favor_discount_name_key($c['name'])][] = $c['id'];
    foreach ((array)($raw['entries'] ?? []) as $entry) {
        if (empty($entry['marked'])) continue;
        $name = (string)($entry['name'] ?? '');
        $key = favor_discount_name_key($name);
        // «Agni Vaelthorne» en la planilla es «Agni» en la Banca: prueba también el primer nombre.
        $ids = $byName[$key] ?? $byName[explode(' ', $key)[0]] ?? [];
        if (!$ids) $data['unmatched'][] = $name;
        foreach ($ids as $id) {
            $data['used'][FAVOR_DISCOUNTS_SEED_SHOP][$id] = ['at' => '', 'by' => 'planilla', 'item' => 'Marcado en la planilla «Descuento Hecho»'];
        }
    }
    return $data;
}

function favor_discounts_normalize(array $data): array
{
    if ((int)($data['version'] ?? 1) < 2) {
        // Versión anterior (un solo descuento marcado por personaje): se reemplaza
        // por la planilla, que es la lista de quienes ya lo usaron.
        $fresh = favor_discounts_seed();
        $fresh['legacy'] = $data;
        return $fresh;
    }
    $used = [];
    foreach ((array)($data['used'] ?? []) as $shop => $list) {
        foreach ((array)$list as $id => $record) {
            if (is_array($record)) $used[(string)$shop][(string)$id] = $record;
        }
    }
    return [
        'version' => 2,
        'shops' => array_filter((array)($data['shops'] ?? []), 'is_array'),
        'used' => $used,
        'log' => array_values(array_filter((array)($data['log'] ?? []), 'is_array')),
        'unmatched' => array_values(array_map('strval', (array)($data['unmatched'] ?? []))),
    ] + array_intersect_key($data, ['legacy' => 1, 'updatedAt' => 1, 'updatedBy' => 1]);
}

function favor_discounts_load(): array
{
    if (is_file(FAVOR_DISCOUNTS_FILE)) {
        $data = json_decode((string)file_get_contents(FAVOR_DISCOUNTS_FILE), true);
        if (is_array($data)) return favor_discounts_normalize($data);
    }
    return favor_discounts_seed();
}

/**
 * Cambia el archivo con bloqueo (dos compras a la vez no pisan una a la otra).
 * $change recibe los datos y un &$result; devuelve los datos nuevos.
 */
function favor_discounts_update(callable $change, string $author): mixed
{
    $dir = dirname(FAVOR_DISCOUNTS_FILE);
    $lock = @fopen(FAVOR_DISCOUNTS_FILE . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude guardar tiendas/descuentos.json. Revisa los permisos de la carpeta tiendas/.');
    try {
        flock($lock, LOCK_EX);
        $data = favor_discounts_load();
        $result = null;
        $data = $change($data, $result);
        $data['updatedBy'] = $author;
        $data['updatedAt'] = gmdate('c');
        $data['log'] = array_slice($data['log'], -FAVOR_DISCOUNT_LOG_MAX);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $dir . '/descuentos.json.' . bin2hex(random_bytes(3)) . '.tmp';
        if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, FAVOR_DISCOUNTS_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('No pude guardar tiendas/descuentos.json. Revisa los permisos de la carpeta tiendas/.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function favor_discount_log(array &$data, string $action, string $shop, array $character, string $by, string $detail = ''): void
{
    $data['log'][] = [
        'at' => date('c'),
        'action' => $action,
        'shop' => $shop,
        'characterId' => (string)$character['id'],
        'character' => (string)$character['name'],
        'player' => (string)($character['playerName'] ?? ''),
        'by' => $by,
        'detail' => $detail,
    ];
}

/** El personaje que $user puede usar para comprar (los suyos; un admin, cualquiera). */
function favor_discount_buyer(?array $user, string $characterId): array
{
    if (!$user) throw new RuntimeException('Inicia sesión para usar un descuento.');
    $characters = favor_discount_characters(favor_discount_is_admin($user) ? null : (string)$user['playerId']);
    if (!isset($characters[$characterId])) throw new RuntimeException('Elige uno de tus personajes.');
    return $characters[$characterId];
}

/**
 * Gasta el descuento de $shop para un personaje. $purchase: item, price, paid y
 * opcionalmente order. Devuelve el registro guardado; falla si ya estaba usado.
 */
function favor_discount_use(string $shop, array $character, array $purchase, string $by): array
{
    return favor_discounts_update(static function (array $data, &$result) use ($shop, $character, $purchase, $by): array {
        $settings = favor_discount_settings($data, $shop);
        if (!$settings['active']) throw new RuntimeException('Esta tienda no tiene descuento ahora.');
        if (isset($data['used'][$shop][$character['id']])) throw new RuntimeException($character['name'] . ' ya usó su descuento en esta tienda.');
        $result = [
            'at' => date('c'),
            'by' => $by,
            'item' => mb_substr((string)($purchase['item'] ?? ''), 0, 160, 'UTF-8'),
            'price' => (string)($purchase['price'] ?? ''),
            'paid' => (string)($purchase['paid'] ?? ''),
            'percent' => $settings['percent'],
        ] + (isset($purchase['order']) ? ['order' => (string)$purchase['order']] : []);
        $data['used'][$shop][$character['id']] = $result;
        favor_discount_log($data, 'usado', $shop, $character, $by, trim($result['item'] . ($result['paid'] !== '' ? ' · ' . $result['paid'] : '')));
        return $data;
    }, $by);
}

/** Devuelve el descuento (solo si lo gastó ese pedido, cuando se indica). */
function favor_discount_restore(string $shop, array $character, string $by, ?string $order = null, string $why = 'reactivado'): bool
{
    return (bool)favor_discounts_update(static function (array $data, &$result) use ($shop, $character, $by, $order, $why): array {
        $record = $data['used'][$shop][$character['id']] ?? null;
        $result = $record !== null && ($order === null || ($record['order'] ?? '') === $order);
        if ($result) {
            unset($data['used'][$shop][$character['id']]);
            favor_discount_log($data, $why, $shop, $character, $by, (string)($record['item'] ?? ''));
        }
        return $data;
    }, $by);
}

/** Precio «25 Po» → cobre (null si no es un precio simple en Po). */
function favor_discount_price_copper(string $price): ?int
{
    if (!preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*po\s*$/i', $price, $m)) return null;
    return (int)round((float)str_replace(',', '.', $m[1]) * 100);
}

function favor_discount_coins(int $copper): string
{
    $po = intdiv($copper, 100);
    $pl = intdiv($copper % 100, 10);
    $pc = $copper % 10;
    $parts = [];
    if ($po) $parts[] = number_format($po, 0, ',', '.') . ' Po';
    if ($pl) $parts[] = $pl . ' Pl';
    if ($pc) $parts[] = $pc . ' Pc';
    return $parts ? implode(' ', $parts) : '0 Po';
}

/**
 * Datos para el navegador de una tienda: el descuento y los personajes que la
 * persona puede elegir al comprar (los suyos; un admin ve todos), con si aún lo
 * tienen disponible.
 */
function favor_discount_shop_data(?array $user, string $shop): array
{
    $empty = ['shop' => $shop, 'percent' => 0, 'rule' => '', 'active' => false, 'characters' => [], 'admin' => false, 'manageUrl' => ''];
    if (!$user) return $empty;
    $isAdmin = favor_discount_is_admin($user);
    try {
        $characters = favor_discount_characters($isAdmin ? null : (string)$user['playerId']);
        $data = favor_discounts_load();
    } catch (Throwable) {
        return $empty;
    }
    $settings = favor_discount_settings($data, $shop);
    $list = [];
    foreach ($characters as $c) {
        $used = $data['used'][$shop][$c['id']] ?? null;
        unset($c['active']);
        $list[] = $c + ['available' => $used === null, 'usedItem' => (string)($used['item'] ?? ''), 'usedAt' => (string)($used['at'] ?? '')];
    }
    return [
        'shop' => $shop,
        'percent' => $settings['percent'],
        'rule' => $settings['rule'],
        'active' => $settings['active'],
        'characters' => $list,
        'admin' => $isAdmin,
        'manageUrl' => $isAdmin ? favor_base_path() . 'tiendas/descuentos.php?tienda=' . rawurlencode($shop) : '',
        'csrf' => favor_csrf(),
    ];
}
