<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
 * Ruta base de la web («/favordejack/», «/» u otra carpeta).
 *
 * Se calcula sola comparando la carpeta de la web con la raíz del servidor,
 * así funciona igual en una subcarpeta, en la raíz o en otro dominio.
 * Si un hosting raro lo calcula mal, se fija con 'base_path' en config.local.php.
 */
function favor_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $configured = trim((string)(favor_config()['base_path'] ?? ''));
    if ($configured !== '') {
        return $base = '/' . trim($configured, '/') . ($configured === '/' ? '' : '/');
    }

    $siteRoot = realpath(dirname(__DIR__));
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($siteRoot !== false && $docRoot !== false && str_starts_with($siteRoot . '/', rtrim($docRoot, '/') . '/')) {
        $relative = trim(substr($siteRoot, strlen(rtrim($docRoot, '/'))), '/');
        $segments = $relative === '' ? [] : array_map('rawurlencode', explode('/', $relative));
        return $base = '/' . implode('/', $segments) . ($segments ? '/' : '');
    }

    return $base = '/favordejack/';
}

/** URL absoluta dentro de la web: favor_url('bancadigital/') → /favordejack/bancadigital/ */
function favor_url(string $path = ''): string
{
    return favor_base_path() . ltrim($path, '/');
}

/** Igual que favor_url() pero escapada para HTML. */
function favor_url_html(string $path = ''): string
{
    return htmlspecialchars(favor_url($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
