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

/**
 * «?v=fecha» para CSS, JS e imágenes propias: el navegador las guarda en caché
 * mucho tiempo (ver .htaccess) y las vuelve a pedir solo cuando cambia el archivo.
 * Uso: href="style.css<?= favor_v(__DIR__ . '/style.css') ?>"
 */
function favor_v(string $absolutePath): string
{
    $time = @filemtime($absolutePath);
    return '?v=' . ($time !== false ? $time : '1');
}

/** URL de un archivo del sitio (relativa a la raíz) con su ?v= para la caché. */
function favor_static(string $relative): string
{
    $relative = ltrim($relative, '/');
    return favor_base_path() . $relative . favor_v(dirname(__DIR__) . '/' . $relative);
}

/** El logo del gremio: un solo archivo para todas las páginas. */
const FAVOR_LOGO = 'assets/logo-favor-de-jack.webp';

/**
 * Tiendas: quita la foto de producto si el archivo no existe, para que se use
 * directamente la imagen de respaldo en vez de pedir cientos de fotos que dan 404.
 * Recorre listas y categorías anidadas de productos.json.
 */
function favor_shop_drop_missing_images(array $products, string $shopDir): array
{
    foreach ($products as $key => $value) {
        if (!is_array($value)) continue;
        if (isset($value['image']) && is_string($value['image']) && $value['image'] !== ''
            && !preg_match('#^(?:https?:)?//#i', $value['image'])
            && !is_file($shopDir . '/' . ltrim(rawurldecode($value['image']), '/'))) {
            $value['image'] = '';
        }
        $products[$key] = favor_shop_drop_missing_images($value, $shopDir);
    }
    return $products;
}
