<?php
declare(strict_types=1);

/*
 * Imágenes de jugadores y personajes (fase 2).
 *
 * - Las fotos subidas se giran según la orientación del móvil y se reducen al
 *   tamaño que de verdad se muestra.
 * - admin/imagenes.php usa lo mismo para ordenar media/ (ver ese archivo).
 */

require_once __DIR__ . '/assets.php';

/** Tamaños máximos por carpeta de media/ (ancho, alto). */
const FAVOR_IMAGE_LIMITS = [
    'icons' => [256, 256],
    'characters' => [900, 1400],
    'profiles' => [512, 512],
];

function favor_image_gd_ready(): bool
{
    return function_exists('imagecreatefromstring') && function_exists('imagewebp');
}

/**
 * Abre una imagen y la endereza si es una foto de móvil (EXIF «Orientation»).
 * Devuelve null si GD no puede leerla.
 */
function favor_image_open(string $path): ?GdImage
{
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $img = @imagecreatefromstring($raw);
    if (!$img) return null;
    if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($img);

    $orientation = 1;
    if (str_starts_with($raw, "\xFF\xD8") && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
    }
    $rotated = match ($orientation) {
        3 => imagerotate($img, 180, 0),
        6 => imagerotate($img, -90, 0),
        8 => imagerotate($img, 90, 0),
        default => null,
    };
    if ($rotated instanceof GdImage) {
        imagedestroy($img);
        $img = $rotated;
    }
    imagealphablending($img, true);
    imagesavealpha($img, true);
    return $img;
}

/** Reduce (nunca agranda) para que quepa en $maxW × $maxH. */
function favor_image_fit(GdImage $img, int $maxW, int $maxH): GdImage
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxW / max(1, $w), $maxH / max(1, $h));
    if ($scale >= 1) return $img;
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $out;
}

/** Guarda en el formato que indique la extensión de $target (webp, png o jpg). */
function favor_image_save(GdImage $img, string $target, int $quality = 82): bool
{
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $tmp = $target . '.' . bin2hex(random_bytes(3)) . '.tmp';
    $ok = match ($ext) {
        'png' => @imagepng($img, $tmp, 8),
        'jpg', 'jpeg' => @imagejpeg($img, $tmp, $quality),
        default => @imagewebp($img, $tmp, $quality),
    };
    if (!$ok || !@rename($tmp, $target)) {
        @unlink($tmp);
        return false;
    }
    @chmod($target, 0644);
    return true;
}

/** Límites para una ruta dentro de media/ («media/icons/…» → icons). */
function favor_image_limits_for(string $relative): ?array
{
    if (!preg_match('#^media/(icons|characters|profiles)/#', $relative, $m)) return null;
    return FAVOR_IMAGE_LIMITS[$m[1]];
}

/*
 * Miniaturas de retratos e iconos para listas y círculos (Archivos, Relaciones,
 * Inventario, tablón). Viven en media/mini/ con la misma ruta que el original:
 *   media/characters/lis/Arella.webp → media/mini/characters/lis/Arella.webp
 * Se crean solas la primera vez que se piden y se rehacen si cambia el original.
 * Se pueden borrar cuando quieras: la web las vuelve a crear.
 */
const FAVOR_THUMB_W = 320;
const FAVOR_THUMB_H = 400;
/** Segundos por visita que se pueden gastar creando miniaturas (el resto, en la siguiente). */
const FAVOR_THUMB_BUDGET = 2.5;

/** Ruta (de base de datos) de la miniatura de $dbPath; si no hay, el original. */
function favor_thumb(string $dbPath): string
{
    static $spent = 0.0;
    if (!preg_match('#^media/((?:characters|icons|profiles)/.+)\.(?:webp|png|jpe?g)$#i', $dbPath, $m)) return $dbPath;
    $root = dirname(__DIR__);
    $orig = $root . '/' . $dbPath;
    if (!is_file($orig)) return $dbPath;
    $mini = 'media/mini/' . $m[1] . '.webp';
    $abs = $root . '/' . $mini;
    if (is_file($abs) && filemtime($abs) >= filemtime($orig)) return $mini;

    // Si el original ya es pequeño no hace falta miniatura.
    $size = @getimagesize($orig);
    if ($size && $size[0] <= FAVOR_THUMB_W && $size[1] <= FAVOR_THUMB_H && filesize($orig) < 60000) return $dbPath;
    if ($spent > FAVOR_THUMB_BUDGET || !favor_image_gd_ready()) return $dbPath;

    $start = microtime(true);
    $ok = false;
    $img = favor_image_open($orig);
    if ($img) {
        $img = favor_image_fit($img, FAVOR_THUMB_W, FAVOR_THUMB_H);
        $dir = dirname($abs);
        if (is_dir($dir) || @mkdir($dir, 0775, true)) $ok = favor_image_save($img, $abs, 78);
        imagedestroy($img);
    }
    $spent += microtime(true) - $start;
    return $ok ? $mini : $dbPath;
}

/** URL lista para <img> de la miniatura de una ruta guardada en la base de datos. */
function favor_thumb_url(?string $value): string
{
    $path = favor_asset_db_path($value);
    if ($path === '' || preg_match('#^(?:https?://|data:)#i', $path)) return favor_asset_url($path);
    return favor_asset_url(favor_thumb(rawurldecode($path)));
}
