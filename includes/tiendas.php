<?php
declare(strict_types=1);

require_once __DIR__ . '/compras.php';
require_once __DIR__ . '/descuentos.php';
require_once __DIR__ . '/discord.php';

/*
 * Compras al instante en las tiendas temáticas (Balas y Sangre, La Dama de
 * las Llamas, La Poción Picante y La Varita Económica).
 *
 * Al pulsar «Comprar» se cobra en la Banca y el objeto entra al Inventario del
 * personaje (includes/compras.php). Cada compra queda en tiendas/compras.json
 * para que el DM la pueda anular desde su panel: se devuelve el oro, se quita
 * del Inventario y, si usó el descuento de la tienda, lo recupera.
 */

const FAVOR_TIENDAS = [
    'balas-y-sangre' => ['dir' => 'balas-y-sangre', 'name' => 'Balas y Sangre'],
    'dama-de-las-llamas' => ['dir' => 'ladamadelasllamas', 'name' => 'La Dama de las Llamas'],
    'pocion-picante' => ['dir' => 'lapocionpicante', 'name' => 'La Poción Picante'],
    'varita-economica' => ['dir' => 'lavaritaeconomica', 'name' => 'La Varita Económica'],
];
const FAVOR_COMPRAS_FILE = __DIR__ . '/../tiendas/compras.json';
const FAVOR_COMPRAS_MAX = 2000;

/**
 * «50 Oro», «25 Po», «6 Plata / 2 Oro», «20 gp X Frasco», «1 de oro» → cobre.
 * Se toma el primer precio. null si no hay número.
 */
function favor_price_copper(string $price): ?int
{
    if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(?:de\s+)?(oro|po|gp|g|plata|pl|sp|cobre|pc|cp)?/iu', $price, $m)) return null;
    $number = str_replace(',', '.', preg_replace('/\.(?=\d{3}\b)/', '', $m[1]));
    $unit = mb_strtolower($m[2] ?? '', 'UTF-8');
    $factor = match (true) {
        in_array($unit, ['plata', 'pl', 'sp'], true) => 10,
        in_array($unit, ['cobre', 'pc', 'cp'], true) => 1,
        default => 100,
    };
    $copper = (int)round((float)$number * $factor);
    return $copper > 0 ? $copper : null;
}

function favor_tienda_products(string $shop): array
{
    $dir = FAVOR_TIENDAS[$shop]['dir'] ?? null;
    if ($dir === null) throw new RuntimeException('Tienda desconocida.');
    $raw = json_decode((string)@file_get_contents(__DIR__ . '/../tiendas/' . $dir . '/productos.json'), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $p) {
        if (is_array($p) && !empty($p['id'])) $out[(string)$p['id']] = $p;
    }
    return $out;
}

/** Personajes con los que la persona puede comprar (los suyos; el admin, todos) y su oro. */
function favor_tienda_buyers(?array $user, string $shop): array
{
    if (!$user) return [];
    $isAdmin = favor_discount_is_admin($user);
    $chars = favor_discount_characters($isAdmin ? null : (string)$user['playerId']);
    if (!$chars) return [];
    $data = favor_discounts_load();
    $db = favor_db();
    try {
        $gold = [];
        foreach ($db->query('SELECT ID, Platino, Oro, Plata, Cobre FROM Personaje WHERE Activo = 1')->fetch_all(MYSQLI_ASSOC) as $r) {
            $gold[(string)$r['ID']] = (int)$r['Platino'] * 1000 + (int)$r['Oro'] * 100 + (int)$r['Plata'] * 10 + (int)$r['Cobre'];
        }
    } finally {
        $db->close();
    }
    $out = [];
    foreach ($chars as $c) {
        $out[] = [
            'id' => $c['id'],
            'name' => $c['name'],
            'playerName' => $c['playerName'],
            'copper' => $gold[$c['id']] ?? 0,
            'discount' => !isset($data['used'][$shop][$c['id']]),
        ];
    }
    return $out;
}

/** Lo que necesita tiendas/compra.js en la página de la tienda. */
function favor_tienda_client(?array $user, string $shop): array
{
    $settings = favor_discount_settings(favor_discounts_load(), $shop);
    try {
        $buyers = favor_tienda_buyers($user, $shop);
    } catch (Throwable) {
        $buyers = [];
    }
    return [
        'shop' => $shop,
        'url' => favor_url('tiendas/comprar.php'),
        'login' => favor_url('login.php?next=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? ''))),
        'csrf' => $user ? favor_csrf() : '',
        'user' => (bool)$user,
        'buyers' => $buyers,
        'discount' => $settings['active'] ? ['percent' => $settings['percent'], 'rule' => $settings['rule']] : null,
    ];
}

/** Lee, cambia y guarda tiendas/compras.json con candado. */
function favor_compras_update(callable $change): mixed
{
    $lock = @fopen(FAVOR_COMPRAS_FILE . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude abrir el candado de tiendas/compras.json. Revisa los permisos de tiendas/.');
    try {
        flock($lock, LOCK_EX);
        $list = is_file(FAVOR_COMPRAS_FILE) ? json_decode((string)file_get_contents(FAVOR_COMPRAS_FILE), true) : [];
        $list = is_array($list) ? $list : [];
        $result = null;
        $list = array_slice(array_values($change($list, $result)), -FAVOR_COMPRAS_MAX);
        $tmp = FAVOR_COMPRAS_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, FAVOR_COMPRAS_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('No pude guardar tiendas/compras.json. Revisa los permisos de tiendas/.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function favor_compras_all(): array
{
    $list = is_file(FAVOR_COMPRAS_FILE) ? json_decode((string)file_get_contents(FAVOR_COMPRAS_FILE), true) : [];
    return array_values(array_filter(is_array($list) ? $list : [], 'is_array'));
}

/**
 * Compra al instante. Devuelve el registro de la compra. Lanza si no le
 * alcanza, si el objeto no tiene precio o si el descuento ya estaba usado.
 */
function favor_tienda_buy(array $user, string $shop, string $itemId, int $qty, string $characterId, bool $useDiscount): array
{
    $shopInfo = FAVOR_TIENDAS[$shop] ?? null;
    if (!$shopInfo) throw new RuntimeException('Tienda desconocida.');
    $character = favor_discount_buyer($user, $characterId);
    $product = favor_tienda_products($shop)[$itemId] ?? null;
    if (!$product) throw new RuntimeException('Ese objeto ya no está en la tienda.');
    if (array_key_exists('available', $product) && $product['available'] === false) throw new RuntimeException('Ese objeto no está disponible ahora.');
    $unit = favor_price_copper((string)($product['price'] ?? ''));
    if ($unit === null) throw new RuntimeException('Este objeto no tiene precio: pregúntale al DM.');
    $qty = max(1, min(50, $qty));
    $name = (string)($product['name'] ?? 'Objeto');
    $by = (string)($user['displayName'] ?? $user['username']);
    $id = 'c' . date('ymd') . '-' . bin2hex(random_bytes(3));

    $copper = $unit * $qty;
    $discount = null;
    if ($useDiscount) {
        // El descuento rebaja una sola unidad y se gasta para siempre (hasta que el DM lo devuelva).
        $settings = favor_discount_settings(favor_discounts_load(), $shop);
        $off = (int)round($unit * $settings['percent'] / 100);
        favor_discount_use($shop, $character, ['item' => $name, 'price' => (string)$product['price'], 'paid' => favor_discount_coins($unit - $off), 'order' => $id], (string)$user['username']);
        $discount = ['percent' => $settings['percent'], 'copper' => $off];
        $copper -= $off;
    }

    $dir = 'tiendas/' . $shopInfo['dir'] . '/';
    // La foto del producto o, si no está en el servidor, su dibujo de reserva.
    $image = '';
    foreach ([(string)($product['image'] ?? ''), (string)($product['fallback'] ?? '')] as $candidate) {
        if ($candidate !== '' && !str_contains($candidate, '..') && is_file(__DIR__ . '/../' . $dir . $candidate)) {
            $image = $dir . $candidate;
            break;
        }
    }
    $description = trim(implode("\n", array_filter([
        (string)($product['description'] ?? $product['effect'] ?? ''),
        !empty($product['damage']) ? 'Daño: ' . $product['damage'] : '',
        !empty($product['range']) ? 'Alcance: ' . $product['range'] : '',
        !empty($product['properties']) && is_array($product['properties']) ? 'Propiedades: ' . implode(', ', $product['properties']) : '',
        !empty($product['duration']) ? 'Duración: ' . $product['duration'] : '',
    ])));
    try {
        $charge = favor_purchase_charge(
            $character['id'],
            $copper,
            [[
                'name' => $name,
                'category' => (string)($product['category'] ?? $product['majorCategory'] ?? 'Objeto'),
                'qty' => $qty,
                'description' => $description,
                'image' => $image,
            ]],
            'Compra en ' . $shopInfo['name'] . ': ' . ($qty > 1 ? $qty . ' × ' : '') . $name,
            $by,
            'Comprado en ' . $shopInfo['name'] . ' el ' . date('d-m-Y') . '.'
        );
    } catch (Throwable $e) {
        if ($discount) favor_discount_restore($shop, $character, (string)$user['username'], $id, 'compra fallida');
        throw $e;
    }

    $record = [
        'id' => $id,
        'at' => date('c'),
        'shop' => $shop,
        'shopName' => $shopInfo['name'],
        'userId' => (int)$user['id'],
        'username' => (string)$user['username'],
        'playerName' => $character['playerName'],
        'characterId' => $character['id'],
        'characterName' => $character['name'],
        'itemId' => $itemId,
        'item' => $name,
        'qty' => $qty,
        'price' => (string)$product['price'],
        'copper' => $copper,
        'state' => 'hecha',
        'charge' => $charge,
    ] + ($discount ? ['discount' => $discount] : []);
    try {
        favor_compras_update(static function (array $list) use ($record): array {
            $list[] = $record;
            return $list;
        });
    } catch (Throwable $e) {
        // Sin registro no se podría anular: se deshace la compra.
        favor_purchase_refund($character['id'], $charge, 'Compra deshecha (no se pudo registrar)', $by);
        if ($discount) favor_discount_restore($shop, $character, (string)$user['username'], $id, 'compra fallida');
        throw $e;
    }

    favor_discord_send(
        'compras',
        '🛒 ' . $character['name'] . ' compró en ' . $shopInfo['name'],
        '• ' . ($qty > 1 ? $qty . ' × ' : '') . $name,
        [['Jugador', $character['playerName']], ['Pagó', favor_copper_label($copper)], ['Descuento', $discount ? $discount['percent'] . ' %' : '']]
    );
    return $record;
}

/** El DM anula una compra de las tiendas: devuelve oro, quita el objeto y el descuento vuelve. */
function favor_tienda_undo(string $id, array $user): string
{
    $by = (string)($user['displayName'] ?? $user['username']);
    $note = '';
    favor_compras_update(static function (array $list) use ($id, $by, $user, &$note): array {
        foreach ($list as &$c) {
            if (!is_array($c) || ($c['id'] ?? '') !== $id) continue;
            if (($c['state'] ?? '') !== 'hecha') throw new RuntimeException('Esa compra ya estaba anulada.');
            $removed = favor_purchase_refund((string)$c['characterId'], (array)$c['charge'], 'Compra anulada por el DM: ' . $c['item'] . ' (' . $c['shopName'] . ')', $by);
            $c['state'] = 'anulada';
            $c['undoneAt'] = date('c');
            $c['undoneBy'] = $by;
            $note = 'Devuelto ' . favor_copper_label((int)$c['copper']) . ' a ' . $c['characterName'] . ($removed ? ' y quitado del Inventario.' : '. El objeto ya no estaba en su Inventario.');
            if (!empty($c['discount'])) {
                $char = ['id' => (string)$c['characterId'], 'name' => (string)$c['characterName'], 'playerName' => (string)$c['playerName']];
                if (favor_discount_restore((string)$c['shop'], $char, (string)$user['username'], $id, 'compra anulada')) $note .= ' Recupera su descuento.';
            }
            return $list;
        }
        throw new RuntimeException('No encontré esa compra.');
    });
    return $note;
}
