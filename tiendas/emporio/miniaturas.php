<?php
declare(strict_types=1);

/*
 * Imágenes de las cartas del Emporio. Cada carta tiene UNA imagen:
 *   Cartas/<Nombre>.webp   la carta (se ve en el detalle)
 *   Cartas/mini/<id>.webp  su miniatura de 480 px para la cuadrícula; se puede
 *                          borrar cuando quieras, la tienda la vuelve a crear
 * Si alguien sube una carta en PNG o JPG, el admin crea su WebP y el PNG queda
 * como «copia sobrante» para retirar (Admin → Duplicados y limpieza).
 */

const MINI_DIR = __DIR__ . '/Cartas/mini';
const MINI_WIDTH = 480;
const EMPORIO_MASTER_QUALITY = 90;
/** Copias de versiones anteriores que ya no se usan (se retiran en la limpieza). */
const EMPORIO_OLD_COPY_GLOBS = ['Cartas/webp/*.webp', 'Cartas/mini/*-240.webp'];

function emporio_mini_path(string $id): string
{
    return MINI_DIR . '/' . $id . '.webp';
}

/** URL relativa de la miniatura si existe y está al día; si no, null. */
function emporio_mini_url(string $id, string $file): ?string
{
    $path = emporio_mini_path($id);
    if (!is_file($path)) return null;
    $original = EMPORIO_CARTAS_DIR . '/' . $file;
    if (is_file($original) && filemtime($original) > filemtime($path)) return null;
    return 'Cartas/mini/' . rawurlencode($id) . '.webp?v=' . filemtime($path);
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
    if (!$ok || !@rename($tmp, $target)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function emporio_gd_ready(): bool
{
    return function_exists('imagewebp') && function_exists('imagecreatefromstring');
}

/**
 * Carta en PNG/JPG → su WebP en Cartas/ con el nombre bien escrito (sin «#U00f3»).
 * Devuelve el nombre del WebP, o null si no se pudo.
 */
function emporio_make_master(string $file): ?string
{
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'webp') return $file;
    if (!emporio_gd_ready()) return null;
    $target = emporio_fix_name(pathinfo($file, PATHINFO_FILENAME)) . '.webp';
    if (is_file(EMPORIO_CARTAS_DIR . '/' . $target)) return $target;
    $raw = @file_get_contents(EMPORIO_CARTAS_DIR . '/' . $file);
    $src = $raw === false ? false : @imagecreatefromstring($raw);
    if (!$src) return null;
    $ok = emporio_write_webp($src, EMPORIO_CARTAS_DIR . '/' . $target, imagesx($src), EMPORIO_MASTER_QUALITY);
    imagedestroy($src);
    if (!$ok) return null;
    @touch(EMPORIO_CARTAS_DIR . '/' . $target, (int)filemtime(EMPORIO_CARTAS_DIR . '/' . $file));
    return $target;
}

/** Crea la miniatura. Devuelve false si el servidor no puede (sin GD/WebP o sin permisos). */
function emporio_make_mini(string $id, string $file): bool
{
    if (!emporio_gd_ready()) return false;
    $raw = @file_get_contents(EMPORIO_CARTAS_DIR . '/' . $file);
    $src = $raw === false ? false : @imagecreatefromstring($raw);
    if (!$src) return false;
    $ok = emporio_write_webp($src, emporio_mini_path($id), MINI_WIDTH, 80);
    imagedestroy($src);
    return $ok;
}

/**
 * Pasa a WebP las cartas que siguen en PNG/JPG y crea las miniaturas que falten,
 * con un tope por visita para no agotar el tiempo de PHP. Devuelve cuántas hizo.
 */
function emporio_make_missing_minis(array $cards, int $limit = 15): int
{
    $made = 0;
    foreach ($cards as $card) {
        if ($made >= $limit) break;
        $id = (string)$card['id'];
        $file = (string)$card['file'];
        $isWebp = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'webp';
        if ($isWebp && emporio_mini_url($id, $file) !== null) continue;
        $master = emporio_make_master($file);
        if ($master === null || !emporio_make_mini($id, $master)) break;
        $made++;
    }
    return $made;
}

/** Cartas que todavía no son WebP o no tienen miniatura. */
function emporio_cards_pending_webp(array $cards): int
{
    return count(array_filter($cards, static fn(array $c): bool =>
        strtolower(pathinfo((string)$c['file'], PATHINFO_EXTENSION)) !== 'webp'
        || emporio_mini_url((string)$c['id'], (string)$c['file']) === null));
}

/** Archivos de versiones anteriores (Cartas/webp/, miniaturas de 240 px). */
function emporio_old_copies(): array
{
    $out = [];
    foreach (EMPORIO_OLD_COPY_GLOBS as $pattern) {
        foreach (glob(__DIR__ . '/' . $pattern) ?: [] as $path) $out[] = $path;
    }
    return $out;
}
