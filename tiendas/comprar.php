<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/tiendas.php';

/*
 * Comprar al instante en una tienda temática (JSON). Cobra en la Banca y mete
 * el objeto al Inventario. Precio y nombre salen del servidor, nunca de lo que
 * mande el navegador.
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
try {
    // Cuenta releída de la base de datos: una cuenta desactivada o un rol
    // retirado no compran ni gestionan con los permisos viejos.
    $user = favor_current_user(true);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => 'No se pudo comprobar tu cuenta. Inténtalo otra vez.'], 503);
}
if (!$user) out(['ok' => false, 'error' => 'Inicia sesión para comprar.'], 401);
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
    $record = favor_tienda_buy($user, $shop, (string)($input['item'] ?? ''), (int)($input['qty'] ?? 1), (string)($input['character'] ?? ''), !empty($input['discount']));
    out([
        'ok' => true,
        'id' => $record['id'],
        'character' => $record['characterName'],
        'item' => $record['item'],
        'qty' => $record['qty'],
        'paid' => favor_copper_label($record['copper']),
        'discount' => $record['discount']['percent'] ?? 0,
        'buyers' => favor_tienda_buyers($user, $shop),
        'inventory' => favor_url('inventario/'),
    ]);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 400);
}
