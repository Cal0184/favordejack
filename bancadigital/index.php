<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
// Identidad y token ya leídos: la página se dibuja sin retener la sesión.
favor_session_close();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require __DIR__ . '/app.php';
