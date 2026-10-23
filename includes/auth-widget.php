<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site.php';

// Toma la identidad mientras todavía se pueden enviar cabeceras. Las páginas
// pueden liberar la sesión inmediatamente después y la barra no la reabre al
// final del HTML (eso volvería a bloquear requests y puede fallar si ya hubo output).
if (!array_key_exists('__favor_auth_widget_user', $GLOBALS)) {
    $widgetUser = favor_current_user();
    // Sesiones abiertas antes de esta versión no traen el icono: se lee una vez.
    if ($widgetUser && !array_key_exists('iconUrl', $widgetUser)) {
        try { $widgetUser = favor_current_user(true); } catch (Throwable $e) {}
    }
    $GLOBALS['__favor_auth_widget_user'] = $widgetUser;
    // Token para el botón «Cerrar sesión» (se envía por POST).
    if ($GLOBALS['__favor_auth_widget_user']) $GLOBALS['__favor_csrf_token'] = favor_csrf();
}

/** Antes era el recuadro flotante de sesión; ahora es la barra común de la web. */
function favor_auth_widget(): void
{
    favor_site_bar();
}
