<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

/*
 * Pedidos a Ecix's (JSON). Crear y cancelar: cualquier jugador con sesión.
 * Cambiar el estado y responder: solo admin (desde admin.php).
 * Los pedidos viven en data/pedidos.json. Al aprobarlos se cobra en la Banca
 * y los objetos entran al Inventario (includes/compras.php).
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
if (!$user) out(['ok' => false, 'error' => 'Inicia sesión para hacer pedidos.'], 401);
try {
    favor_verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 403);
}
favor_session_close();

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) out(['ok' => false, 'error' => 'Datos no válidos.'], 400);
$action = (string)($input['action'] ?? '');
$isAdmin = ($user['role'] ?? '') === 'admin';

try {
    if ($action === 'create') {
        $characterId = (string)($input['character'] ?? '');
        $character = null;
        foreach (emporio_characters($user) as $c) {
            if ($c['id'] === $characterId) $character = $c;
        }
        if (!$character) throw new RuntimeException('Elige uno de tus personajes.');

        // Precios y nombres salen del servidor, nunca de lo que mande el navegador.
        [$cards] = emporio_shop_cards(null, 0.0);
        $services = [];
        foreach (load_json_file(EMPORIO_SERVICES_FILE) as $s) {
            if (is_array($s)) $services['servicio:' . slugify((string)($s['id'] ?? $s['name'] ?? ''))] = $s;
        }
        $items = [];
        $total = 0;
        $unknown = false;
        foreach (array_slice((array)($input['items'] ?? []), 0, 40) as $line) {
            if (!is_array($line)) continue;
            $id = (string)($line['id'] ?? '');
            $qty = max(1, min(20, (int)($line['qty'] ?? 1)));
            if (isset($cards[$id])) {
                $card = $cards[$id];
                if ($card['soldOut']) throw new RuntimeException($card['name'] . ' está agotado.');
                $item = ['id' => $id, 'name' => $card['name'], 'price' => $card['priceLabel'], 'pricePo' => $card['pricePo'], 'qty' => $qty];
            } elseif (isset($services[$id])) {
                $price = (string)($services[$id]['price'] ?? '');
                $item = ['id' => $id, 'name' => (string)($services[$id]['name'] ?? 'Servicio'), 'price' => emporio_price_label($price), 'pricePo' => emporio_price_po($price), 'qty' => $qty];
            } else {
                continue;
            }
            if ($item['pricePo'] === null) $unknown = true;
            else $total += $item['pricePo'] * $qty;
            $items[] = $item;
        }
        if (!$items) throw new RuntimeException('El pedido está vacío.');

        $now = date('c');
        $orderId = 'p' . date('ymd') . '-' . bin2hex(random_bytes(3));

        // Descuento de la tienda en un objeto mágico (carta) del pedido.
        $discount = null;
        $discountItem = (string)($input['discountItem'] ?? '');
        if ($discountItem !== '') {
            $line = null;
            foreach ($items as $it) if ($it['id'] === $discountItem && isset($cards[$it['id']]) && $it['pricePo'] !== null) $line = $it;
            if (!$line) throw new RuntimeException('El descuento solo se aplica a un objeto mágico con precio.');
            $settings = favor_discount_settings(favor_discounts_load(), EMPORIO_DISCOUNT_SHOP);
            $amount = round($line['pricePo'] * $settings['percent'] / 100, 2);
            $paidCopper = (int)round(($line['pricePo'] - $amount) * 100);
            $discount = [
                'percent' => $settings['percent'],
                'itemId' => $line['id'],
                'item' => $line['name'],
                'price' => $line['price'],
                'amount' => $amount,
                'paid' => favor_discount_coins($paidCopper),
            ];
            $buyer = favor_discount_buyer($user, $character['id']);
            favor_discount_use(EMPORIO_DISCOUNT_SHOP, $buyer, ['item' => $line['name'], 'price' => $line['price'], 'paid' => $discount['paid'], 'order' => $orderId], (string)$user['username']);
            $total -= $amount;
        }
        $order = [
            'id' => $orderId,
            'created' => $now,
            'updated' => $now,
            'state' => 'pendiente',
            'userId' => (int)$user['id'],
            'username' => (string)$user['username'],
            'playerName' => (string)$user['displayName'],
            'characterId' => $character['id'],
            'characterName' => $character['name'],
            'characterGold' => $character['goldExact'],
            'items' => $items,
            'total' => $unknown && $total === 0 ? null : $total,
            'note' => mb_substr(trim((string)($input['note'] ?? '')), 0, 500, 'UTF-8'),
            'reply' => '',
        ] + ($discount ? ['discount' => $discount] : []);

        try {
            emporio_update_json(EMPORIO_ORDERS_FILE, static function (array $orders) use ($order, $user): array {
                $orders = array_values(array_filter($orders, 'is_array'));
                $open = array_filter($orders, static fn(array $o): bool => (int)($o['userId'] ?? 0) === (int)$user['id'] && ($o['state'] ?? '') === 'pendiente');
                if (count($open) >= 10) throw new RuntimeException('Tienes 10 pedidos pendientes. Espera a que Ecix\'s responda alguno.');
                $orders[] = $order;
                return $orders;
            });
        } catch (Throwable $e) {
            // El pedido no se guardó: el descuento no se gastó.
            if ($discount) favor_discount_restore(EMPORIO_DISCOUNT_SHOP, $buyer, (string)$user['username'], $orderId, 'pedido cancelado');
            throw $e;
        }
        out(['ok' => true, 'order' => emporio_order_public($order)]);
    }

    if ($action === 'cancel' || $action === 'state') {
        $id = (string)($input['id'] ?? '');
        $state = $action === 'cancel' ? 'cancelado' : (string)($input['state'] ?? '');
        if (!isset(EMPORIO_ORDER_STATES[$state])) throw new RuntimeException('Estado no válido.');
        if ($action === 'state' && !$isAdmin) throw new RuntimeException('Solo el admin cambia el estado de un pedido.');
        $reply = $action === 'state' ? mb_substr(trim((string)($input['reply'] ?? '')), 0, 500, 'UTF-8') : null;

        $oldState = '';
        $chargeNote = '';
        $updated = emporio_update_json(EMPORIO_ORDERS_FILE, static function (array $orders, &$result) use ($id, $state, $reply, $user, $isAdmin, $action, &$oldState, &$chargeNote): array {
            foreach ($orders as &$o) {
                if (!is_array($o) || ($o['id'] ?? '') !== $id) continue;
                $oldState = (string)($o['state'] ?? '');
                if ($action === 'cancel') {
                    if ((int)($o['userId'] ?? 0) !== (int)$user['id'] && !$isAdmin) throw new RuntimeException('Ese pedido no es tuyo.');
                    if (($o['state'] ?? '') !== 'pendiente') throw new RuntimeException('Solo se cancela un pedido pendiente.');
                }
                $o['state'] = $state;
                if ($reply !== null) $o['reply'] = $reply;
                $o['updated'] = date('c');
                $chargeNote = emporio_order_charge_sync($o, null, (string)($user['displayName'] ?? $user['username']));
                $result = $o;
            }
            unset($o);
            if ($result === null) throw new RuntimeException('No encontré ese pedido.');
            return $orders;
        });
        $note = emporio_order_discount_sync($updated, $oldState, (string)$user['username']);
        out(['ok' => true, 'order' => emporio_order_public($updated), 'notice' => trim($chargeNote . ' ' . $note)]);
    }

    out(['ok' => false, 'error' => 'Acción desconocida.'], 400);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 400);
}
