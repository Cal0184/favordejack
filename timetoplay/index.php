<?php
declare(strict_types=1);

/*
 * La wiki contiene notas privadas del DM: solo la ve un administrador.
 * El contenido está en contenido.php, que no muestra nada si se abre directo.
 * (Antes era index.html: en Hestia, Nginx entregaba los .html sin mirar el
 * .htaccess, así que cualquiera podía leerlo.)
 */
require_once dirname(__DIR__) . '/includes/auth.php';

favor_session_start();
$user = favor_current_user(true);
favor_session_close();

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'timetoplay/'), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="robots" content="noindex">'
        . '<title>Acceso restringido</title><p>Esta sección es solo para el DM.</p>'
        . '<p><a href="' . favor_base_path() . '">Volver a Favor de Jack</a></p></html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
define('FAVOR_TIMETOPLAY', true);
require __DIR__ . '/contenido.php';
