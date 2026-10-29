<?php
declare(strict_types=1);

/*
 * Favor de Jack · Crónicas (línea de tiempo de la campaña).
 *
 * Los eventos, las líneas paralelas y la lista de música viven en
 * data/cronicas.json. Las imágenes de fondo y las canciones subidas van a
 * media/cronicas/ (viajan en «Exportar todo» junto con el resto de media/).
 * Los personajes no se guardan aquí: se toman de la Banca y Archivos.
 */

require_once dirname(__DIR__) . '/includes/paths.php';

const CR_FILE = __DIR__ . '/data/cronicas.json';
const CR_MEDIA = 'media/cronicas';
const CR_DEFAULT_TRACK = ['id' => 'tema', 'name' => 'Tema de las Crónicas', 'file' => 'cronicas/musica/tema.mp3'];
const CR_IMAGE_MAX = 1920;
const CR_MUSIC_TYPES = ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'webm' => 'audio/webm'];

function cr_root(): string
{
    return dirname(__DIR__);
}

function cr_default(): array
{
    return [
        'version' => 1,
        'config' => ['campaignName' => 'Crónicas'],
        'timelines' => [['id' => 'main', 'name' => 'Historia Principal', 'color' => '#d4a843', 'startYear' => null, 'endYear' => null]],
        'events' => [],
        'music' => [CR_DEFAULT_TRACK],
    ];
}

function cr_load(): array
{
    $data = is_file(CR_FILE) ? json_decode((string)file_get_contents(CR_FILE), true) : null;
    $def = cr_default();
    if (!is_array($data)) return $def;
    foreach (['timelines', 'events', 'music'] as $key) {
        $data[$key] = array_values(array_filter(is_array($data[$key] ?? null) ? $data[$key] : [], 'is_array'));
    }
    $data['config'] = (is_array($data['config'] ?? null) ? $data['config'] : []) + $def['config'];
    // La línea principal siempre existe.
    if (!in_array('main', array_column($data['timelines'], 'id'), true)) array_unshift($data['timelines'], $def['timelines'][0]);
    return $data + $def;
}

/** Cambia el archivo bajo candado y lo escribe entero o nada. */
function cr_update(callable $change): mixed
{
    $dir = dirname(CR_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No pude crear la carpeta cronicas/data/.');
    $lock = @fopen(CR_FILE . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude abrir el candado de las crónicas. Revisa los permisos de cronicas/data/.');
    try {
        flock($lock, LOCK_EX);
        $data = cr_load();
        $result = null;
        $data = $change($data, $result);
        $data['updated'] = date('Y-m-d H:i');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tmp = CR_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, CR_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('No pude guardar las crónicas. Revisa los permisos de cronicas/data/.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function cr_id(): string
{
    return bin2hex(random_bytes(8));
}

function cr_str(array $in, string $key, int $max): string
{
    $value = trim(str_replace("\r\n", "\n", (string)($in[$key] ?? '')));
    return mb_substr($value, 0, $max, 'UTF-8');
}

function cr_year($value): ?int
{
    if ($value === null || $value === '' || !is_numeric($value)) return null;
    return max(-1000000, min(1000000, (int)round((float)$value)));
}

function cr_color(string $value, string $fallback = '#d4a843'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}

/** Ruta guardada de una imagen o canción: solo se aceptan las de media/cronicas/. */
function cr_media_path(string $value, string $sub = ''): string
{
    $value = trim($value);
    $prefix = CR_MEDIA . '/' . ($sub !== '' ? $sub . '/' : '');
    if (!str_starts_with($value, $prefix) || !preg_match('#^[a-z0-9/_.-]+$#', $value) || str_contains($value, '..')) return '';
    return is_file(cr_root() . '/' . $value) ? $value : '';
}

/** Personajes que se pueden poner en un evento: [id => [nombre, retrato, ¿tiene hoja?]]. */
function cr_characters(mysqli $db): array
{
    require_once dirname(__DIR__) . '/includes/imagenes.php';
    $out = [];
    $sql = "SELECT p.ID, COALESCE(NULLIF(pp.NombreCompleto,''), p.Nombre) AS Nombre, p.PortraitUrl, p.IconUrl, pp.ID AS Perfil
            FROM Personaje p LEFT JOIN PersonajePerfil pp ON pp.ID = p.ID
            ORDER BY COALESCE(NULLIF(pp.NombreCompleto,''), p.Nombre)";
    $res = $db->query($sql);
    while ($r = $res->fetch_assoc()) {
        $img = (string)($r['PortraitUrl'] ?: $r['IconUrl'] ?: '');
        $out[(string)$r['ID']] = [
            'name' => (string)$r['Nombre'],
            'img' => $img !== '' ? favor_thumb_url($img) : '',
            'sheet' => $r['Perfil'] !== null,
        ];
    }
    $res->free();
    // Un personaje de Muertos o Personajes Fuera con su ficha «gemela» en Archivos sale una vez.
    $byName = [];
    foreach ($out as $id => $c) {
        $key = mb_strtolower($c['name'], 'UTF-8');
        if (!isset($byName[$key])) { $byName[$key] = $id; continue; }
        $other = $byName[$key];
        if ($c['sheet'] && !$out[$other]['sheet']) { $out[$other]['dup'] = true; $byName[$key] = $id; }
        else $out[$id]['dup'] = true;
    }
    return $out;
}

/** Limpia la lista de personajes de un evento: {id} de la web o {name} libre. */
function cr_clean_characters($list, array $known): array
{
    $out = [];
    $seen = [];
    foreach (is_array($list) ? $list : [] as $c) {
        if (!is_array($c)) continue;
        $id = (string)($c['id'] ?? '');
        if ($id !== '') {
            if (!isset($known[$id]) || isset($seen['id:' . $id])) continue;
            $seen['id:' . $id] = true;
            $out[] = ['id' => $id];
            continue;
        }
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($c['name'] ?? '')) ?? ''), 0, 80, 'UTF-8');
        $key = 'name:' . mb_strtolower($name, 'UTF-8');
        if ($name === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = ['name' => $name];
        if (count($out) >= 60) break;
    }
    return $out;
}

/** Guarda una imagen subida como WebP (máx. 1920 px) en media/cronicas/. */
function cr_save_image(array $file): string
{
    cr_upload_ok($file);
    $info = @getimagesize((string)$file['tmp_name']);
    $type = $info[2] ?? 0;
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg((string)$file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng((string)$file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp((string)$file['tmp_name']),
        IMAGETYPE_GIF => @imagecreatefromgif((string)$file['tmp_name']),
        default => false,
    };
    if (!$img) throw new RuntimeException('La imagen debe ser JPG, PNG, WebP o GIF.');
    [$w, $h] = [imagesx($img), imagesy($img)];
    $scale = min(1, CR_IMAGE_MAX / max($w, $h));
    if ($scale < 1) {
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    $rel = CR_MEDIA . '/' . cr_id() . '.webp';
    cr_mkdir(dirname(cr_root() . '/' . $rel));
    $ok = imagewebp($img, cr_root() . '/' . $rel, 82);
    imagedestroy($img);
    if (!$ok) throw new RuntimeException('No pude guardar la imagen. Revisa los permisos de media/.');
    return $rel;
}

/** Guarda una canción subida en media/cronicas/musica/. Devuelve la ruta. */
function cr_save_music(array $file): string
{
    cr_upload_ok($file);
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset(CR_MUSIC_TYPES[$ext])) throw new RuntimeException('La canción debe ser MP3, OGG, M4A, WAV o WebM.');
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type((string)$file['tmp_name']) : '';
    if ($mime !== '' && !str_starts_with($mime, 'audio/') && !str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
        throw new RuntimeException('Ese archivo no parece una canción.');
    }
    $rel = CR_MEDIA . '/musica/' . cr_id() . '.' . $ext;
    cr_mkdir(dirname(cr_root() . '/' . $rel));
    if (!@move_uploaded_file((string)$file['tmp_name'], cr_root() . '/' . $rel)) throw new RuntimeException('No pude guardar la canción. Revisa los permisos de media/.');
    @chmod(cr_root() . '/' . $rel, 0644);
    return $rel;
}

function cr_upload_ok(array $file): void
{
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('El archivo pesa demasiado para el servidor (máximo ' . ini_get('upload_max_filesize') . ').');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new RuntimeException('No llegó el archivo. Inténtalo otra vez.');
}

function cr_mkdir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No pude crear la carpeta ' . basename($dir) . '. Revisa los permisos de media/.');
}

/** Borra un archivo de media/cronicas/ si ningún evento ni canción lo usa ya. */
function cr_delete_unused(array $data, string $path): void
{
    if ($path === '' || $path === CR_DEFAULT_TRACK['file'] || !str_starts_with($path, CR_MEDIA . '/')) return;
    foreach ($data['events'] as $e) if (($e['image'] ?? '') === $path) return;
    foreach ($data['music'] as $m) if (($m['file'] ?? '') === $path) return;
    @unlink(cr_root() . '/' . $path);
}
