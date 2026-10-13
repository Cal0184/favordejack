<?php
declare(strict_types=1);

/*
 * La wiki contiene notas privadas del DM: solo la ve un administrador.
 * El contenido sigue en index.html, que el .htaccess bloquea por URL.
 */
require_once dirname(__DIR__) . '/includes/auth.php';

favor_session_start();
$user = favor_current_user(true);
favor_session_close();

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

if (!$user) {
    header('Location: /favordejack/login.php?next=' . rawurlencode('/favordejack/timetoplay/'), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex">'
        . '<title>Acceso restringido</title><p>Esta sección es solo para el DM.</p>'
        . '<p><a href="/favordejack/">Volver a Favor de Jack</a></p></html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/index.html');
