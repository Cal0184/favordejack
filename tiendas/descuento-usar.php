<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/descuentos.php';

/*
 * Usar el descuento de La Poción Picante al comprar una poción (JSON).
 * El Emporio lo usa dentro del pedido (tiendas/emporio/pedidos.php).
 * Precio y nombre salen del servidor, nunca de lo que mande el navegador.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'Método no permitido.'], 405);
$user = favor_current_user();
if (!$user) out(['ok' => false, 'error' => 'Inicia sesión para usar un descuento.'], 401);
try {
    favor_verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 403);
}
favor_session_close();

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) out(['ok' => false, 'error' => 'Datos no válidos.'], 400);

try {
    $shop = (string)($input['shop'] ?? '');
    if ($shop !== 'pocion-picante') throw new RuntimeException('En esta tienda el descuento se usa de otra forma.');
    $character = favor_discount_buyer($user, (string)($input['character'] ?? ''));

    $products = json_decode((string)@file_get_contents(__DIR__ . '/lapocionpicante/productos.json'), true);
    $product = null;
    foreach (is_array($products) ? $products : [] as $p) {
        if (is_array($p) && ($p['id'] ?? '') === (string)($input['item'] ?? '')) $product = $p;
    }
    if (!$product) throw new RuntimeException('No encontré esa poción.');

    $data = favor_discounts_load();
    $percent = favor_discount_settings($data, $shop)['percent'];
    $copper = favor_discount_price_copper((string)($product['price'] ?? ''));
    $paid = $copper === null ? '' : favor_discount_coins((int)round($copper * (100 - $percent) / 100));

    $record = favor_discount_use($shop, $character, [
        'item' => (string)($product['name'] ?? 'Poción'),
        'price' => (string)($product['price'] ?? ''),
        'paid' => $paid,
    ], (string)$user['username']);
    out(['ok' => true, 'character' => $character['name'], 'item' => $record['item'], 'price' => $record['price'], 'paid' => $record['paid'], 'percent' => $record['percent']]);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 400);
}
