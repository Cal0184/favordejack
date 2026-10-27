<?php
declare(strict_types=1);

/*
 * Aviso a Discord de una tirada crítica (20 o 1 natural en un d20).
 * Lo llama jack-features.js; solo con sesión iniciada y una vez cada 15 s por
 * persona, para que no se pueda llenar el canal.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/discord.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function aviso_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') aviso_out(['ok' => false], 405);
favor_session_start();
$user = favor_current_user();
if (!$user) aviso_out(['ok' => false, 'error' => 'Sin sesión.'], 401);
try {
    favor_verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
} catch (Throwable $e) {
    aviso_out(['ok' => false, 'error' => $e->getMessage()], 403);
}
$last = (int)($_SESSION['favor_dice_crit'] ?? 0);
if (time() - $last < 15) aviso_out(['ok' => false, 'error' => 'Espera un momento.'], 429);
$_SESSION['favor_dice_crit'] = time();
favor_session_close();

$input = json_decode((string)file_get_contents('php://input'), true);
$die = (int)($input['die'] ?? 0);
if ($die !== 20 && $die !== 1) aviso_out(['ok' => false, 'error' => 'Solo se avisan los críticos.'], 400);
$total = (int)($input['total'] ?? $die);
$label = mb_substr(trim((string)($input['label'] ?? '1d20')), 0, 40, 'UTF-8');
$mode = (string)($input['mode'] ?? 'normal');
$modes = ['advantage' => 'con ventaja', 'disadvantage' => 'con desventaja'];
$who = (string)($user['displayName'] ?? $user['username']);

$fail = favor_discord_send(
    'tiradas',
    $die === 20 ? '🎲 ¡20 natural de ' . $who . '!' : '💀 1 natural de ' . $who . '…',
    $die === 20 ? 'Los dados sonríen.' : 'Jack se ríe a lo lejos.',
    [['Tirada', $label . (isset($modes[$mode]) ? ' ' . $modes[$mode] : '')], ['Total', (string)$total]]
);
aviso_out(['ok' => $fail === '']);
