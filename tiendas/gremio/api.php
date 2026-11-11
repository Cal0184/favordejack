<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/gremio.php';

/*
 * Tienda del Gremio · peticiones de la página (JSON, siempre POST con el token
 * CSRF en X-CSRF-Token). Precios, existencias y dueños de los objetos se
 * comprueban aquí, nunca se toman del navegador. Ver includes/gremio.php.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function gremio_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') gremio_out(['ok' => false, 'error' => 'Método no permitido.'], 405);
try {
    // Cuenta releída de la base: un rol retirado no actúa con los permisos viejos.
    $user = favor_current_user(true);
} catch (Throwable) {
    gremio_out(['ok' => false, 'error' => 'No se pudo comprobar tu cuenta. Inténtalo otra vez.'], 503);
}
if (!$user) gremio_out(['ok' => false, 'error' => 'Inicia sesión para usar la Tienda del Gremio.'], 401);
try {
    favor_verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
} catch (Throwable $e) {
    gremio_out(['ok' => false, 'error' => $e->getMessage()], 403);
}
favor_session_close();

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) gremio_out(['ok' => false, 'error' => 'Datos no válidos.'], 400);
$action = (string)($_GET['action'] ?? $in['action'] ?? '');
$char = (string)($in['character'] ?? '');

try {
    switch ($action) {
        case 'buy':
            $r = favor_gremio_buy($user, (string)($in['item'] ?? ''), (int)($in['qty'] ?? 1), $char, !empty($in['discount']));
            gremio_out([
                'ok' => true, 'id' => $r['id'], 'character' => $r['characterName'], 'item' => $r['item'], 'qty' => $r['qty'],
                'paid' => favor_copper_label($r['copper']), 'discount' => $r['discount']['percent'] ?? 0,
                'product' => (string)($in['item'] ?? ''), 'stock' => $r['stock'],
                'buyers' => favor_tienda_buyers($user, FAVOR_GREMIO_SHOP), 'inventory' => favor_url('inventario/?character=' . rawurlencode($char)),
            ]);
        case 'sellables':
            gremio_out(['ok' => true] + favor_gremio_sellables($user, $char));
        case 'request':
            $r = favor_gremio_request($user, $char, (int)($in['inventory'] ?? 0), (string)($in['note'] ?? ''));
            gremio_out(['ok' => true, 'message' => 'Lady Gena revisará «' . $r['name'] . '» y te dirá cuánto ofrece el gremio.'] + favor_gremio_sellables($user, $char));
        case 'sell':
            $offer = isset($in['offer']) && $in['offer'] !== null && $in['offer'] !== '' ? (int)$in['offer'] : null;
            $r = favor_gremio_sell($user, $char, (int)($in['inventory'] ?? 0), $offer, (int)($in['expected'] ?? -1), !empty($in['unbound']));
            gremio_out(['ok' => true, 'message' => $r['character'] . ' vendió «' . $r['name'] . '» al gremio por ' . $r['paid'] . '. El oro ya está en su Banca.', 'buyers' => favor_tienda_buyers($user, FAVOR_GREMIO_SHOP)] + favor_gremio_sellables($user, $char));
        case 'cancel':
            favor_gremio_cancel($user, $char, (int)($in['offer'] ?? 0));
            gremio_out(['ok' => true, 'message' => 'Solicitud cerrada. El objeto sigue en tu Inventario.'] + favor_gremio_sellables($user, $char));

        // ---- Almacén: solo el DM ----
        case 'storage':
            gremio_out(['ok' => true] + favor_gremio_storage($user));
        case 'save_item':
            favor_gremio_save_item($user, $in);
            gremio_out(['ok' => true, 'message' => 'Objeto guardado.'] + favor_gremio_storage($user));
        case 'add_item':
            favor_gremio_add_item($user, $in);
            gremio_out(['ok' => true, 'message' => 'Objeto añadido al Almacén.'] + favor_gremio_storage($user));
        case 'value':
            favor_gremio_value($user, (int)($in['offer'] ?? 0), (string)($in['price'] ?? ''), (string)($in['note'] ?? ''), !empty($in['remember']), !empty($in['reject']));
            gremio_out(['ok' => true, 'message' => !empty($in['reject']) ? 'Solicitud rechazada.' : 'Oferta enviada al jugador.'] + favor_gremio_storage($user));
        case 'rate':
            favor_gremio_save_rate($user, (string)($in['name'] ?? ''), (string)($in['price'] ?? ''), (string)($in['note'] ?? ''), !empty($in['delete']));
            gremio_out(['ok' => true, 'message' => !empty($in['delete']) ? 'Precio de compra quitado.' : 'Precio de compra guardado.'] + favor_gremio_storage($user));
        case 'undo':
            $note = favor_gremio_undo($user, (int)($in['log'] ?? 0));
            gremio_out(['ok' => true, 'message' => 'Compra anulada. ' . $note] + favor_gremio_storage($user));
        default:
            gremio_out(['ok' => false, 'error' => 'Acción desconocida.'], 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('Tienda del Gremio: ' . $e->getMessage());
    gremio_out(['ok' => false, 'error' => 'Error de la base de datos. No se movió nada; inténtalo otra vez.'], 500);
} catch (RuntimeException $e) {
    gremio_out(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Tienda del Gremio: ' . $e->getMessage());
    gremio_out(['ok' => false, 'error' => 'Error de la base de datos. No se movió nada; inténtalo otra vez.'], 500);
}
