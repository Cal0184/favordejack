<?php
declare(strict_types=1);

/*
 * Imágenes WebP de las cartas del Emporio:
 *   Cartas/mini/<id>-240.webp  cuadrícula en móvil
 *   Cartas/mini/<id>.webp      cuadrícula (480 px)
 *   Cartas/webp/<id>.webp      detalle a tamaño completo (~120 KB en vez de ~1 MB)
 * El PNG original queda como fuente y ya no se descarga. Si falta una copia,
 * la tienda usa la carta original sin romperse.
 */

const MINI_DIR = __DIR__ . '/Cartas/mini';
const LARGE_DIR = __DIR__ . '/Cartas/webp';
const MINI_WIDTH = 480;
const MINI_SIZES = [480, 240];

function emporio_mini_path(string $id, int $width = MINI_WIDTH): string
{
    return MINI_DIR . '/' . $id . ($width === MINI_WIDTH ? '' : '-' . $width) . '.webp';
}

function emporio_large_path(string $id): string
{
    return LARGE_DIR . '/' . $id . '.webp';
}

/** URL relativa de una copia WebP si existe y está al día; si no, null. */
function emporio_webp_url(string $path, string $file): ?string
{
    if (!is_file($path)) return null;
    $original = __DIR__ . '/Cartas/' . $file;
    if (is_file($original) && filemtime($original) > filemtime($path)) return null;
    $relative = substr($path, strlen(__DIR__) + 1);
    return implode('/', array_map('rawurlencode', explode('/', $relative))) . '?v=' . filemtime($path);
}

function emporio_mini_url(string $id, string $file, int $width = MINI_WIDTH): ?string
{
    return emporio_webp_url(emporio_mini_path($id, $width), $file);
}

function emporio_large_url(string $id, string $file): ?string
{
    return emporio_webp_url(emporio_large_path($id), $file);
}

function emporio_write_webp(GdImage $src, string $target, int $width, int $quality): bool
{
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min($width, $w);
    $nh = (int)round($h * $nw / max(1, $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($dst);
        return false;
    }
    $tmp = $target . '.tmp';
    $ok = @imagewebp($dst, $tmp, $quality);
    imagedestroy($dst);
    return $ok && @rename($tmp, $target);
}

/** Crea las tres copias con GD. Devuelve false si el servidor no puede (sin GD/WebP o sin permisos). */
function emporio_make_mini(string $id, string $file): bool
{
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) return false;
    $raw = @file_get_contents(__DIR__ . '/Cartas/' . $file);
    if ($raw === false) return false;
    $src = @imagecreatefromstring($raw);
    if (!$src) return false;
    $ok = emporio_write_webp($src, emporio_large_path($id), 2000, 82);
    foreach (MINI_SIZES as $width) {
        $ok = $ok && emporio_write_webp($src, emporio_mini_path($id, $width), $width, 80);
    }
    imagedestroy($src);
    return $ok;
}

/** Genera las copias que falten, con un tope por visita para no agotar el tiempo de PHP. */
function emporio_make_missing_minis(array $cards, int $limit = 15): int
{
    $made = 0;
    foreach ($cards as $card) {
        if ($made >= $limit) break;
        $id = (string)$card['id'];
        $file = (string)$card['file'];
        if (emporio_mini_url($id, $file) !== null && emporio_mini_url($id, $file, 240) !== null && emporio_large_url($id, $file) !== null) continue;
        if (emporio_make_mini($id, $file)) $made++;
        else break;
    }
    return $made;
}
