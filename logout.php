<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-widget.php';
header('Cache-Control: no-store');

// Cerrar sesión va por POST con token: un enlace o una imagen de otra web no
// pueden sacarte de la cuenta. Un enlace antiguo (GET) muestra un botón.
$next = favor_safe_next($_POST['next'] ?? $_GET['next'] ?? favor_base_path());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        favor_logout();
        header('Location: ' . $next, true, 303);
        exit;
    } catch (Throwable $e) {
        // Token vencido: se pide confirmar con el botón de abajo.
    }
}
if (!favor_session_user()) {
    header('Location: ' . $next, true, 303);
    exit;
}
$token = favor_csrf();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cerrar sesión · Favor de Jack</title><?php favor_head('Cerrar sesión · Favor de Jack'); ?></head>
<body class="favor-page"><?php favor_site_bar(); ?>
<main class="favor-card favor-narrow"><h1>¿Cerrar sesión?</h1><p>Saldrás de tu cuenta en este navegador.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= favor_h($token) ?>"><input type="hidden" name="next" value="<?= favor_h($next) ?>">
<button class="favor-btn favor-btn-primary" autofocus>Cerrar sesión</button> <a class="favor-btn" href="<?= favor_h($next) ?>">Cancelar</a></form></main>
</body></html>
