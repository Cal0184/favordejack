<?php
declare(strict_types=1);

/*
 * El Emporio de Ecix's · lógica común de la tienda, el admin y los pedidos.
 *
 * La tienda se arma con las imágenes de Cartas/ cruzadas con data/catalog.json.
 * Antes este código estaba copiado en index.php y admin.php y cada arreglo había
 * que hacerlo dos veces.
 *
 * Duplicados: una carta se oculta si otra ya usa el mismo nombre o la misma
 * ilustración (huella de la imagen). El admin decide si un par se muestra igual
 * y puede retirar los archivos sobrantes a data/retirados/ (no se borran).
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once __DIR__ . '/miniaturas.php';

const EMPORIO_CARTAS_DIR = __DIR__ . '/Cartas';
const EMPORIO_DATA_DIR = __DIR__ . '/data';
const EMPORIO_CATALOG_FILE = EMPORIO_DATA_DIR . '/catalog.json';
const EMPORIO_SERVICES_FILE = EMPORIO_DATA_DIR . '/services.json';
const EMPORIO_HASHES_FILE = EMPORIO_DATA_DIR . '/huellas.json';
const EMPORIO_DUPES_FILE = EMPORIO_DATA_DIR . '/duplicados.json';
const EMPORIO_ORDERS_FILE = EMPORIO_DATA_DIR . '/pedidos.json';
const EMPORIO_RETIRED_DIR = EMPORIO_DATA_DIR . '/retirados';

/** Diferencia media de gris (0-255) por debajo de la cual dos ilustraciones son la misma. */
const EMPORIO_SAME_ART = 1.0;

const EMPORIO_ORDER_STATES = [
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'entregado' => 'Entregado',
    'rechazado' => 'Rechazado',
    'cancelado' => 'Cancelado',
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** «Protecci#U00f3n» (nombre roto al descomprimir un zip) → «Protección». */
function emporio_fix_name(string $value): string
{
    $value = preg_replace_callback('/#U([0-9a-fA-F]{4})/', static fn(array $m): string => mb_chr(hexdec($m[1]), 'UTF-8'), $value) ?? $value;
    if (class_exists('Normalizer')) $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
    return $value;
}

function emporio_ascii(string $value): string
{
    $value = mb_strtolower(emporio_fix_name($value), 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $out = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        if (is_string($out) && $out !== '') return $out;
    }
    if (class_exists('Normalizer')) {
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($decomposed)) return preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value;
    }
    // Último recurso: depende del locale del servidor y puede perder letras.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return is_string($ascii) && $ascii !== '' ? $ascii : $value;
}

function slugify(string $value): string
{
    $value = emporio_ascii(pathinfo($value, PATHINFO_FILENAME));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
    return trim(strtolower($value), '-');
}

/** Nombres de archivo con erratas → ID del catálogo. */
function catalog_aliases(): array
{
    return [
        'abrochar-de-la-profundidad' => 'antorcha-de-la-profundidad',
        'aceite-de-resbaladicida' => 'aceite-de-resbaladicidad',
        'cuerda-escalada' => 'cuerda-de-escalada',
        'cuarda-de-escalada' => 'cuerda-de-escalada',
        'guantes-termanles' => 'guantes-termales',
        'anillo-catapulta' => 'anillo-de-catapulta',
        'bronntanas' => 'bronntanas-blade',
        'cinturon-de-fuerza-de-gigante' => 'cinturon-de-fuerza-de-gigante-fuerza-21',
        'tunica-del-horador' => 'tunica-del-orador',
        'guijarro-de-1-tonelada' => 'piedra-de-una-tonelada',
    ];
}

function canonical_slug(string $value): string
{
    $slug = slugify($value);
    return catalog_aliases()[$slug] ?? $slug;
}

function ignored_card_slugs(): array
{
    return ['guantes-de-poder-del-ogro', 'guantes-de-fuerza-de-ogro', 'guantes-de-poder-de-ogro'];
}

/** Categorías casi iguales que partían el filtro en dos. */
function emporio_category(string $category): string
{
    $category = trim($category);
    $same = [
        'Movimiento y viaje' => 'Movimiento y transporte',
        'Percepción y sentidos' => 'Percepción e información',
        'Defensa y protección' => 'Armaduras, escudos y defensa',
    ];
    return $same[$category] ?? ($category !== '' ? $category : 'Sin categoría');
}

/** Precio en piezas de oro para ordenar y comparar con el saldo; null si no se puede leer. */
function emporio_price_po(string $price): ?int
{
    if (!preg_match('/\d[\d.]*/', $price, $m)) return null;
    $digits = preg_match('/^\d{1,3}(\.\d{3})+$/', $m[0]) ? str_replace('.', '', $m[0]) : explode('.', $m[0])[0];
    return (int)$digits;
}

/** «1800 po» y «1.800 po» se ven igual: 1.800 po. */
function emporio_price_label(string $price): string
{
    $price = trim($price);
    if ($price === '' || $price === '?') return 'Precio a consultar';
    return preg_replace_callback('/\d{4,}|\d{1,3}(?:\.\d{3})+/', static function (array $m): string {
        return number_format((int)str_replace('.', '', $m[0]), 0, ',', '.');
    }, $price) ?? $price;
}

function pretty_name(string $file): string
{
    $name = emporio_fix_name(pathinfo($file, PATHINFO_FILENAME));
    $name = preg_replace('/[_\-]+/u', ' ', $name) ?? $name;
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    if ($name !== '' && $name === mb_strtolower($name, 'UTF-8')) {
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
    return $name !== '' ? $name : 'Objeto sin nombre';
}

function load_json_file(string $file, array $fallback = []): array
{
    if (!is_file($file)) return $fallback;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $fallback;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

/** Escribe JSON de forma atómica (archivo temporal único + rename). */
function emporio_save_json(string $file, array $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json) === false) return false;
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Lee, cambia y guarda un JSON con un candado, para que dos pedidos o dos
 * guardados a la vez no se pisen. $change recibe los datos y los devuelve.
 */
function emporio_update_json(string $file, callable $change): mixed
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No pude crear la carpeta data/ del Emporio.');
    $lock = @fopen($file . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude abrir el candado de ' . basename($file) . '. Revisa los permisos de data/.');
    try {
        flock($lock, LOCK_EX);
        $data = load_json_file($file);
        $result = null;
        $data = $change($data, $result);
        if (!emporio_save_json($file, $data)) throw new RuntimeException('No pude guardar ' . basename($file) . '. Revisa los permisos de data/.');
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Acepta el catálogo antiguo en forma de lista ([{"id":…}]) y el nuevo
 * indexado por ID ({"id": {…}}).
 */
function normalize_catalog(array $raw): array
{
    $out = [];
    foreach ($raw as $key => $row) {
        if (!is_array($row)) continue;
        $rawId = trim((string)($row['id'] ?? (is_string($key) ? $key : '')));
        if ($rawId === '') continue;
        $id = canonical_slug($rawId);
        if ($id === '') continue;
        $row['id'] = $id;
        $out[$id] = isset($out[$id]) && is_array($out[$id]) ? array_replace($out[$id], $row) : $row;
    }
    return $out;
}

function emporio_catalog(): array
{
    return normalize_catalog(load_json_file(EMPORIO_CATALOG_FILE));
}

function card_url(string $file): string
{
    return 'Cartas/' . rawurlencode($file);
}

function saved_value(array $saved, string $field, mixed $fallback): mixed
{
    if (array_key_exists($field, $saved) && $saved[$field] !== '' && $saved[$field] !== null) return $saved[$field];
    return $fallback;
}

/** Archivos de imagen de Cartas/, uno por ID (si hay PNG y WebP gana el WebP). */
function emporio_card_files(): array
{
    if (!is_dir(EMPORIO_CARTAS_DIR)) return [];
    $priority = ['webp' => 5, 'png' => 4, 'jpg' => 3, 'jpeg' => 2, 'gif' => 1];
    $files = [];
    foreach (scandir(EMPORIO_CARTAS_DIR) ?: [] as $file) {
        if ($file[0] === '.' || !is_file(EMPORIO_CARTAS_DIR . '/' . $file)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset($priority[$ext])) continue;
        $slug = slugify($file);
        if ($slug === '' || in_array($slug, ignored_card_slugs(), true)) continue;
        $id = canonical_slug($slug);
        $p = $priority[$ext];
        // A igual formato gana el nombre bien escrito (sin «#U00f3»).
        if (isset($files[$id])) {
            $old = $files[$id];
            if ($old['priority'] > $p) continue;
            if ($old['priority'] === $p && !str_contains($file, '#U') && str_contains($old['file'], '#U')) {
                // el nuevo está bien escrito: lo reemplaza
            } elseif ($old['priority'] === $p) {
                $files[$id]['twins'][] = $file;
                continue;
            }
            $twins = array_merge($old['twins'], [$old['file']]);
            $files[$id] = ['file' => $file, 'priority' => $p, 'twins' => $twins];
            continue;
        }
        $files[$id] = ['file' => $file, 'priority' => $p, 'twins' => []];
    }
    return $files;
}

/** Todas las cartas con imagen, sin quitar duplicados. */
function emporio_all_cards(?array $catalog = null): array
{
    $catalog ??= emporio_catalog();
    $cards = [];
    foreach (emporio_card_files() as $id => $meta) {
        $file = (string)$meta['file'];
        $pretty = pretty_name($file);
        $saved = $catalog[$id] ?? $catalog[canonical_slug($pretty)] ?? [];
        if (!is_array($saved)) $saved = [];

        $name = trim((string)saved_value($saved, 'name', $pretty)) ?: $pretty;
        $price = trim((string)saved_value($saved, 'price', '?')) ?: '?';
        $stock = max(0, (int)saved_value($saved, 'stock', 1));
        $available = (bool)saved_value($saved, 'available', true);
        $description = trim((string)saved_value($saved, 'description', ''));

        $cards[$id] = [
            'id' => $id,
            'file' => $file,
            'twins' => $meta['twins'],
            'name' => $name,
            'category' => emporio_category((string)saved_value($saved, 'category', 'Sin categoría')),
            'price' => $price,
            'priceLabel' => emporio_price_label($price),
            'pricePo' => emporio_price_po($price),
            'stock' => $stock,
            'available' => $available,
            'soldOut' => !$available || $stock <= 0,
            'description' => $description,
            'described' => $description !== '',
            'inCatalog' => $saved !== [],
            'url' => emporio_large_url($id, $file) ?? card_url($file),
            'original' => card_url($file),
            'thumb' => emporio_mini_url($id, $file) ?? card_url($file),
            'thumbSmall' => emporio_mini_url($id, $file, 240),
        ];
    }
    uasort($cards, static fn(array $a, array $b): int => strnatcasecmp(emporio_ascii($a['name']), emporio_ascii($b['name'])));
    return $cards;
}

/* ------------------------------------------------------------------ */
/* Huella de la ilustración (para encontrar la misma imagen repetida)  */
/* ------------------------------------------------------------------ */

/**
 * Huella de la zona del dibujo (sin el marco ni los textos de la carta): la
 * ilustración reducida a 16×16 tonos de gris, en 512 caracteres hex.
 */
function emporio_art_hash(string $path): ?string
{
    if (!function_exists('imagecreatefromstring')) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $src = @imagecreatefromstring($raw);
    if (!$src) return null;
    $w = imagesx($src);
    $h = imagesy($src);
    // En dos pasos: reducir de golpe a 16 px promedia mal en GD.
    $mid = imagecreatetruecolor(128, 128);
    imagecopyresampled($mid, $src, 0, 0, (int)round($w * .12), (int)round($h * .20), 128, 128, (int)round($w * .76), (int)round($h * .33));
    imagedestroy($src);
    $small = imagecreatetruecolor(16, 16);
    imagecopyresampled($small, $mid, 0, 0, 0, 0, 16, 16, 128, 128);
    imagedestroy($mid);

    $out = '';
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $gray = intdiv((($rgb >> 16) & 255) * 299 + (($rgb >> 8) & 255) * 587 + ($rgb & 255) * 114, 1000);
            $out .= sprintf('%02x', $gray);
        }
    }
    imagedestroy($small);
    return $out;
}

/** Diferencia media de gris (0-255) entre dos huellas. La misma ilustración da menos de 1. */
function emporio_hash_distance(string $a, string $b): float
{
    if (strlen($a) !== 512 || strlen($b) !== 512) return 255.0;
    $x = hex2bin($a);
    $y = hex2bin($b);
    $d = 0;
    for ($i = 0; $i < 256; $i++) $d += abs(ord($x[$i]) - ord($y[$i]));
    return $d / 256;
}

/**
 * Huellas de todas las cartas, guardadas en data/huellas.json por nombre y
 * tamaño de archivo. Calcula las que falten dentro de un tiempo máximo para no
 * hacer esperar a quien abre la tienda; las que queden se calculan en la
 * siguiente visita.
 */
function emporio_hashes(array $cards, float $budgetSeconds = 1.5): array
{
    $cache = load_json_file(EMPORIO_HASHES_FILE);
    $start = microtime(true);
    $changed = false;
    $out = [];
    foreach ($cards as $id => $card) {
        $path = EMPORIO_CARTAS_DIR . '/' . $card['file'];
        $size = (int)@filesize($path);
        $key = $card['file'];
        if (isset($cache[$key]['hash'], $cache[$key]['size']) && (int)$cache[$key]['size'] === $size && strlen((string)$cache[$key]['hash']) === 512) {
            $out[$id] = (string)$cache[$key]['hash'];
            continue;
        }
        if (microtime(true) - $start > $budgetSeconds) continue;
        // La miniatura es mucho más rápida de leer y da la misma huella.
        $mini = emporio_mini_path($id);
        $hash = emporio_art_hash(is_file($mini) && filemtime($mini) >= filemtime($path) ? $mini : $path);
        if ($hash === null) continue;
        $cache[$key] = ['size' => $size, 'hash' => $hash];
        $out[$id] = $hash;
        $changed = true;
    }
    if ($changed) emporio_save_json(EMPORIO_HASHES_FILE, $cache);
    return $out;
}

/** Pares de cartas con la misma ilustración. El resultado se guarda hasta que cambie una huella. */
function emporio_same_art_pairs(array $hashes): array
{
    ksort($hashes);
    $key = md5(implode(',', array_keys($hashes)) . implode('', $hashes));
    $cacheFile = EMPORIO_DATA_DIR . '/huellas-pares.json';
    $cache = load_json_file($cacheFile);
    if (($cache['key'] ?? '') === $key && is_array($cache['pairs'] ?? null)) return $cache['pairs'];

    $ids = array_keys($hashes);
    $bins = array_map('hex2bin', $hashes);
    $pairs = [];
    for ($i = 0, $n = count($ids); $i < $n; $i++) {
        $x = $bins[$ids[$i]];
        for ($j = $i + 1; $j < $n; $j++) {
            $y = $bins[$ids[$j]];
            $d = 0;
            for ($k = 0; $k < 256; $k++) $d += abs(ord($x[$k]) - ord($y[$k]));
            if ($d / 256 <= EMPORIO_SAME_ART) $pairs[] = [$ids[$i], $ids[$j]];
        }
    }
    emporio_save_json($cacheFile, ['key' => $key, 'pairs' => $pairs]);
    return $pairs;
}

/* ------------------------------------------------------------------ */
/* Duplicados                                                          */
/* ------------------------------------------------------------------ */

function emporio_name_key(string $name): string
{
    $key = preg_replace('/[^a-z0-9]+/', ' ', emporio_ascii($name)) ?? $name;
    $key = preg_replace('/\b(de|del|la|el|los|las|y)\b/', ' ', $key) ?? $key;
    return trim(preg_replace('/\s+/', ' ', $key) ?? $key);
}

/** Decisiones del admin: pares que deben verse los dos, y cuál conservar. */
function emporio_dupe_settings(): array
{
    $data = load_json_file(EMPORIO_DUPES_FILE);
    return [
        'keepBoth' => array_values(array_filter((array)($data['keepBoth'] ?? []), 'is_string')),
        'prefer' => array_filter((array)($data['prefer'] ?? []), 'is_string'),
    ];
}

function emporio_pair_key(string $a, string $b): string
{
    return strcmp($a, $b) < 0 ? "$a|$b" : "$b|$a";
}

/** Puntaje para elegir qué carta se queda: la que tiene ficha completa. */
function emporio_card_score(array $card): int
{
    return ($card['inCatalog'] ? 8 : 0) + ($card['described'] ? 4 : 0) + ($card['pricePo'] !== null ? 2 : 0) + ($card['soldOut'] ? 0 : 1);
}

/**
 * Agrupa las cartas repetidas por nombre o por ilustración.
 * Devuelve [['keep' => id, 'hide' => [id…], 'reason' => 'nombre'|'imagen', 'pairs' => …], …].
 */
function emporio_find_duplicates(array $cards, array $hashes, ?array $settings = null): array
{
    $settings ??= emporio_dupe_settings();
    $keepBoth = array_flip($settings['keepBoth']);
    $ids = array_keys($cards);

    // Unión de grupos: cada arista es un par repetido.
    $parent = array_combine($ids, $ids);
    $find = static function (string $x) use (&$parent, &$find): string {
        return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
    };
    $reasons = [];
    $link = static function (string $a, string $b, string $why) use (&$parent, $find, &$reasons, $keepBoth): void {
        if (isset($keepBoth[emporio_pair_key($a, $b)])) return;
        $ra = $find($a);
        $rb = $find($b);
        if ($ra !== $rb) $parent[$ra] = $rb;
        $reasons[emporio_pair_key($a, $b)] = $why;
    };

    $byName = [];
    foreach ($cards as $id => $card) $byName[emporio_name_key($card['name'])][] = $id;
    foreach ($byName as $group) {
        for ($i = 1, $n = count($group); $i < $n; $i++) $link($group[0], $group[$i], 'nombre');
    }

    foreach (emporio_same_art_pairs($hashes) as [$a, $b]) {
        if (isset($cards[$a], $cards[$b])) $link($a, $b, 'imagen');
    }

    $groups = [];
    foreach ($ids as $id) $groups[$find($id)][] = $id;
    $out = [];
    foreach ($groups as $members) {
        if (count($members) < 2) continue;
        $keep = null;
        foreach ($members as $id) {
            if (isset($settings['prefer'][$id])) $keep = $id;
        }
        if ($keep === null) {
            usort($members, static fn(string $a, string $b): int => emporio_card_score($cards[$b]) <=> emporio_card_score($cards[$a]) ?: strnatcasecmp($a, $b));
            $keep = $members[0];
        }
        $why = [];
        foreach ($members as $x) {
            foreach ($members as $y) {
                if ($x < $y && isset($reasons[emporio_pair_key($x, $y)])) $why[] = $reasons[emporio_pair_key($x, $y)];
            }
        }
        $out[] = [
            'keep' => $keep,
            'hide' => array_values(array_diff($members, [$keep])),
            'members' => $members,
            'reason' => in_array('imagen', $why, true) ? (in_array('nombre', $why, true) ? 'nombre e imagen' : 'imagen') : 'nombre',
        ];
    }
    return $out;
}

/** Cartas para la tienda: sin duplicados. Devuelve [cartas, grupos de duplicados]. */
function emporio_shop_cards(?array $catalog = null, float $hashBudget = 1.5): array
{
    $all = emporio_all_cards($catalog);
    $dupes = emporio_find_duplicates($all, emporio_hashes($all, $hashBudget));
    foreach ($dupes as $group) {
        foreach ($group['hide'] as $id) unset($all[$id]);
    }
    return [$all, $dupes];
}

/* ------------------------------------------------------------------ */
/* Personajes de la Banca (saldo para «¿Me alcanza?» y pedidos)        */
/* ------------------------------------------------------------------ */

/** Personajes activos del usuario (todos si es admin) con su oro en po. */
function emporio_characters(?array $user): array
{
    if (!$user) return [];
    $isAdmin = ($user['role'] ?? '') === 'admin';
    try {
        $db = favor_db();
    } catch (Throwable) {
        return [];
    }
    try {
        $sql = 'SELECT p.ID, p.Nombre, p.Jugador, j.Nombre AS JugadorNombre, p.Platino, p.Oro, p.Plata, p.Cobre '
            . 'FROM Personaje p INNER JOIN Jugador j ON j.ID = p.Jugador WHERE p.Activo = 1 AND j.Activo = 1';
        if (!$isAdmin) $sql .= ' AND p.Jugador = ?';
        $sql .= ' ORDER BY j.Orden, j.Nombre, p.Orden, p.Nombre';
        $stmt = $db->prepare($sql);
        if (!$isAdmin) {
            $player = (string)($user['playerId'] ?? '');
            $stmt->bind_param('s', $player);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $out = [];
        while ($row = $result->fetch_assoc()) {
            $copper = (int)$row['Platino'] * 1000 + (int)$row['Oro'] * 100 + (int)$row['Plata'] * 10 + (int)$row['Cobre'];
            $out[] = [
                'id' => (string)$row['ID'],
                'name' => (string)$row['Nombre'],
                'playerId' => (string)$row['Jugador'],
                'playerName' => (string)$row['JugadorNombre'],
                'gold' => intdiv($copper, 100),
                'goldExact' => round($copper / 100, 2),
            ];
        }
        $stmt->close();
        return $out;
    } catch (Throwable) {
        return [];
    } finally {
        $db->close();
    }
}

/* ------------------------------------------------------------------ */
/* Pedidos a Ecix's                                                    */
/* ------------------------------------------------------------------ */

function emporio_orders(): array
{
    $orders = load_json_file(EMPORIO_ORDERS_FILE);
    return array_values(array_filter($orders, 'is_array'));
}

/** Pedidos visibles para el usuario: los suyos (o todos si es admin), recientes primero. */
function emporio_orders_for(?array $user, int $limit = 50): array
{
    if (!$user) return [];
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $orders = array_filter(emporio_orders(), static fn(array $o): bool => $isAdmin || (int)($o['userId'] ?? 0) === (int)$user['id']);
    usort($orders, static fn(array $a, array $b): int => strcmp((string)($b['created'] ?? ''), (string)($a['created'] ?? '')));
    return array_slice(array_values($orders), 0, $limit);
}

/** Lo que el navegador necesita de un pedido (sin datos internos). */
function emporio_order_public(array $o): array
{
    return [
        'id' => (string)($o['id'] ?? ''),
        'created' => (string)($o['created'] ?? ''),
        'updated' => (string)($o['updated'] ?? ''),
        'state' => (string)($o['state'] ?? 'pendiente'),
        'stateLabel' => EMPORIO_ORDER_STATES[(string)($o['state'] ?? 'pendiente')] ?? 'Pendiente',
        'player' => (string)($o['playerName'] ?? ''),
        'character' => (string)($o['characterName'] ?? ''),
        'items' => array_values((array)($o['items'] ?? [])),
        'total' => $o['total'] ?? null,
        'note' => (string)($o['note'] ?? ''),
        'reply' => (string)($o['reply'] ?? ''),
    ];
}
