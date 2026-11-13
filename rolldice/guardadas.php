<?php
declare(strict_types=1);

/*
 * Tiradas guardadas por cuenta: así salen en cualquier navegador o móvil.
 * GET devuelve la lista; POST la reemplaza entera (máx. 24). Solo con sesión.
 */
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function guardadas_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

favor_session_start();
try {
    $user = favor_current_user();
} catch (Throwable $e) {
    guardadas_out(['ok' => false, 'error' => 'No se pudo comprobar tu cuenta. Inténtalo otra vez.'], 503);
}
if (!$user) guardadas_out(['ok' => false, 'error' => 'Inicia sesión para guardar tiradas en tu cuenta.'], 401);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    } catch (Throwable $e) {
        guardadas_out(['ok' => false, 'error' => $e->getMessage()], 403);
    }
}
favor_session_close();

/** Solo campos conocidos y con valores sensatos. */
function guardadas_limpiar(mixed $list): array
{
    $out = [];
    foreach (array_slice(is_array($list) ? $list : [], 0, 24) as $r) {
        if (!is_array($r)) continue;
        $name = mb_substr(trim((string)($r['name'] ?? '')), 0, 36, 'UTF-8');
        $id = preg_replace('/[^A-Za-z0-9-]/', '', (string)($r['id'] ?? '')) ?: bin2hex(random_bytes(6));
        if ($name === '') continue;
        $mode = in_array($r['mode'] ?? '', ['advantage', 'disadvantage'], true) ? $r['mode'] : 'normal';
        $expression = mb_substr(trim((string)($r['expression'] ?? '')), 0, 120, 'UTF-8');
        if (($r['type'] ?? '') === 'expression' || $expression !== '') {
            if ($expression === '') continue;
            $out[] = ['id' => substr($id, 0, 40), 'type' => 'expression', 'name' => $name, 'expression' => $expression, 'mode' => $mode];
            continue;
        }
        $out[] = [
            'id' => substr($id, 0, 40),
            'type' => 'basic',
            'name' => $name,
            'amount' => max(1, min(100, (int)($r['amount'] ?? 1))),
            'sides' => max(2, min(1000, (int)($r['sides'] ?? 20))),
            'modifier' => max(-1000, min(1000, (int)($r['modifier'] ?? 0))),
            'mode' => $mode,
        ];
    }
    return $out;
}

try {
    $db = favor_db();
    $db->query('CREATE TABLE IF NOT EXISTS TiradaGuardada (
        Usuario int unsigned NOT NULL PRIMARY KEY,
        Datos text NOT NULL,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $uid = (int)$user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $rolls = guardadas_limpiar($input['rolls'] ?? []);
        $json = json_encode($rolls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $db->prepare('INSERT INTO TiradaGuardada (Usuario, Datos) VALUES (?, ?) ON DUPLICATE KEY UPDATE Datos = VALUES(Datos)');
        $st->bind_param('is', $uid, $json);
        $st->execute();
        $st->close();
        guardadas_out(['ok' => true, 'rolls' => $rolls]);
    }

    $st = $db->prepare('SELECT Datos FROM TiradaGuardada WHERE Usuario = ? LIMIT 1');
    $st->bind_param('i', $uid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    guardadas_out(['ok' => true, 'rolls' => $row ? guardadas_limpiar(json_decode((string)$row['Datos'], true)) : [], 'saved' => (bool)$row]);
} catch (Throwable $e) {
    error_log('Tiradas guardadas: ' . $e->getMessage());
    guardadas_out(['ok' => false, 'error' => 'No se pudieron leer las tiradas guardadas.'], 500);
}
