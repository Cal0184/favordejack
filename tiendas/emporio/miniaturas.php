<?php
declare(strict_types=1);

/*
 * Miniaturas del Emporio: copias WebP de 480 px de ancho para la cuadrícula.
 * La carta original (PNG de ~1 MB) solo se descarga al abrir el detalle.
 * Si falta una miniatura, la tienda usa la carta original sin romperse.
 */

const MINI_DIR = __DIR__ . '/Cartas/mini';
const MINI_WIDTH = 480;

function emporio_mini_path(string $id): string {
    return MINI_DIR . '/' . $id . '.webp';
}

/** URL relativa de la miniatura si existe y está al día; si no, null. */
function emporio_mini_url(string $id, string $file): ?string {
    $mini = emporio_mini_path($id);
    $original = __DIR__ . '/Cartas/' . $file;
    if (!is_file($mini)) return null;
    if (is_file($original) && filemtime($original) > filemtime($mini)) return null;
    return 'Cartas/mini/' . rawurlencode($id) . '.webp?v=' . filemtime($mini);
}

/** Crea la miniatura con GD. Devuelve false si el servidor no puede (sin GD/WebP o sin permisos). */
function emporio_make_mini(string $id, string $file): bool {
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) return false;
    $original = __DIR__ . '/Cartas/' . $file;
    $raw = @file_get_contents($original);
    if ($raw === false) return false;
    $src = @imagecreatefromstring($raw);
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min(MINI_WIDTH, $w);
    $nh = (int)round($h * $nw / max(1, $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    if (!is_dir(MINI_DIR) && !@mkdir(MINI_DIR, 0775, true) && !is_dir(MINI_DIR)) {
        imagedestroy($dst);
        return false;
    }
    $tmp = emporio_mini_path($id) . '.tmp';
    $ok = @imagewebp($dst, $tmp, 80);
    imagedestroy($dst);
    return $ok && @rename($tmp, emporio_mini_path($id));
}

/** Genera las miniaturas que falten, con un tope por visita para no agotar el tiempo de PHP. */
function emporio_make_missing_minis(array $cards, int $limit = 15): int {
    $made = 0;
    foreach ($cards as $card) {
        if ($made >= $limit) break;
        if (emporio_mini_url((string)$card['id'], (string)$card['file']) !== null) continue;
        if (emporio_make_mini((string)$card['id'], (string)$card['file'])) $made++;
        else break;
    }
    return $made;
}
