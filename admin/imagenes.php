<?php
declare(strict_types=1);

/*
 * Admin · Ordenar imágenes (fase 2).
 *
 * Deja todas las imágenes de jugadores y personajes en media/:
 *  1. Copia a media/ lo que solo estaba en la copia antigua bancadigital/icons
 *     y bancadigital/characters, y repara las rutas de la base de datos que no
 *     encontraban su archivo por mayúsculas, «liz/lis» o tildes rotas (#U00f1).
 *  2. Reduce iconos y retratos al tamaño que se muestra (el original se guarda
 *     en respaldos/imagenes-originales-<fecha>/).
 *  3. Mueve lo que sobra (la copia de bancadigital/, la carpeta media/icons/liz,
 *     copias repetidas, el Emporio antiguo y las imágenes que ahora viven en un
 *     solo sitio, ver IMG_REPLACED) a respaldos/imagenes-retiradas-<fecha>/.
 *  4. Busca imágenes idénticas en toda la web y las lista.
 * Nada se borra: todo queda en respaldos/, que el navegador no puede abrir.
 * Trabaja por tandas para no pasar el tiempo máximo de PHP; se puede repetir.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/imagenes.php';

favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_url('login.php?next=' . rawurlencode(favor_url('admin/imagenes.php'))), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Esta página es solo para administradores.');
}
$csrf = favor_csrf();
favor_session_close();

const IMG_ROOT = __DIR__ . '/..';
const IMG_EXT = ['webp', 'png', 'jpg', 'jpeg', 'gif'];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mb_label(int $bytes): string { return number_format($bytes / 1048576, 1, ',', '.') . ' MB'; }

/** «Se#U00f1or» → «Señor». */
function img_fix_name(string $v): string
{
    $v = preg_replace_callback('/#U([0-9a-fA-F]{4})/', static fn(array $m): string => mb_chr(hexdec($m[1]), 'UTF-8'), $v) ?? $v;
    return class_exists('Normalizer') ? (Normalizer::normalize($v, Normalizer::FORM_C) ?: $v) : $v;
}

/** Clave para comparar nombres sin mayúsculas, tildes rotas, «liz/lis», espacios ni «.webp.webp». */
function img_key(string $relative): string
{
    $k = mb_strtolower(img_fix_name($relative), 'UTF-8');
    $k = preg_replace('#^(media/(?:icons|characters)/)liz/#', '$1lis/', $k) ?? $k;
    $k = preg_replace('#(\.(?:webp|png|jpe?g|gif))+$#', '', $k) ?? $k;
    $dir = dirname($k);
    return $dir . '/' . (preg_replace('/[\s_\-]+/u', '', basename($k)) ?? basename($k));
}

/** Archivos de imagen bajo una carpeta, como rutas relativas a la web. */
function img_list(string $dir): array
{
    $abs = IMG_ROOT . '/' . $dir;
    if (!is_dir($abs)) return [];
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), IMG_EXT, true)) {
            $out[] = $dir . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($abs) + 1));
        }
    }
    sort($out);
    return $out;
}

/** Rutas de imagen guardadas en la base de datos (media/…). */
function img_db_refs(): array
{
    $refs = [];
    $db = favor_db();
    try {
        foreach (['SELECT IconUrl AS u FROM Jugador', 'SELECT IconUrl AS u FROM Personaje', 'SELECT PortraitUrl AS u FROM Personaje'] as $sql) {
            $res = $db->query($sql);
            while ($row = $res->fetch_assoc()) {
                $path = favor_asset_db_path((string)($row['u'] ?? ''));
                if ($path !== '' && str_starts_with($path, 'media/')) $refs[rawurldecode($path)] = true;
            }
        }
    } finally {
        $db->close();
    }
    return array_keys($refs);
}

function img_move(string $relative, string $toDir): bool
{
    $from = IMG_ROOT . '/' . $relative;
    $to = IMG_ROOT . '/' . $toDir . '/' . $relative;
    if (!is_file($from)) return false;
    if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0775, true)) return false;
    if (is_file($to)) $to .= '.' . bin2hex(random_bytes(2));
    return @rename($from, $to);
}

function img_copy(string $from, string $to): bool
{
    $abs = IMG_ROOT . '/' . $to;
    if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0775, true)) return false;
    return @copy(IMG_ROOT . '/' . $from, $abs);
}

/**
 * Archivos que la web ya no usa porque su imagen ahora vive en otro sitio
 * (antigua ruta => la que la reemplaza). Solo se apartan si el reemplazo existe.
 */
const IMG_REPLACED = [
    'emporio' => 'tiendas/emporio/index.php',
    'bancadigital/iconhand.webp' => 'assets/logo-favor-de-jack.webp',
    'rolldice/images/jack-hand.webp' => 'assets/logo-favor-de-jack.webp',
    'mapa/assets/Mapa_Waterdeep.png' => 'mapa/assets/mapa-waterdeep.webp',
    'mapa/assets/vendedores' => 'media/shops/lavaritaeconomica/blanco.webp',
    'tiendas/balas-y-sangre/assets/madeline-ironslinger.png' => 'media/shops/balas-y-sangre/madeline-ironslinger.webp',
    'tiendas/ladamadelasllamas/assets/vendedores' => 'media/shops/ladamadelasllamas/gaston.webp',
    'tiendas/lapocionpicante/assets/vendedores' => 'media/shops/lapocionpicante/yamo-yamo.webp',
    'tiendas/lavaritaeconomica/assets/vendedores' => 'media/shops/lavaritaeconomica/blanco.webp',
    'tiendas/lavaritaeconomica/assets/cartas/ira-del-barbaro.png' => 'tiendas/lavaritaeconomica/assets/cartas/ira-del-barbaro.webp',
    'tiendas/lavaritaeconomica/assets/cartas/mentas-del-bardo.png' => 'tiendas/lavaritaeconomica/assets/cartas/mentas-del-bardo.webp',
    'tiendas/lavaritaeconomica/assets/cartas/acelerador.png' => 'tiendas/lavaritaeconomica/assets/cartas/acelerador.webp',
];

/** Carpetas que no se revisan al buscar duplicados (copias de seguridad, git). */
const IMG_SCAN_SKIP = ['respaldos', '.git', 'tiendas/emporio/data/retirados', 'tiendas/emporio/Cartas/mini'];

function img_tree_bytes(string $relative): int
{
    $abs = IMG_ROOT . '/' . $relative;
    if (is_file($abs)) return (int)filesize($abs);
    $bytes = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)) as $f) $bytes += $f->getSize();
    return $bytes;
}

/** Archivos o carpetas reemplazados que siguen en el servidor: [ruta => bytes]. */
function img_replaced(): array
{
    $out = [];
    foreach (IMG_REPLACED as $old => $new) {
        if (file_exists(IMG_ROOT . '/' . $old) && is_file(IMG_ROOT . '/' . $new)) $out[$old] = img_tree_bytes($old);
    }
    return $out;
}

/**
 * Imágenes idénticas (mismo contenido byte a byte) en toda la web.
 * Devuelve grupos de rutas, el más pesado primero.
 */
function img_identical_groups(): array
{
    $root = realpath(IMG_ROOT) ?: IMG_ROOT;
    $bySize = [];
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $f) use ($root): bool {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if ($f->isDir()) return !in_array($rel, IMG_SCAN_SKIP, true);
            return in_array(strtolower($f->getExtension()), IMG_EXT, true);
        }
    );
    foreach (new RecursiveIteratorIterator($filter) as $f) {
        $bySize[$f->getSize()][] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    }
    $groups = [];
    foreach ($bySize as $size => $paths) {
        if (count($paths) < 2) continue;
        $byHash = [];
        foreach ($paths as $p) $byHash[(string)@md5_file($root . '/' . $p)][] = $p;
        foreach ($byHash as $same) if (count($same) > 1) $groups[] = ['bytes' => (int)$size, 'paths' => $same];
    }
    usort($groups, static fn(array $a, array $b): int => $b['bytes'] * count($b['paths']) <=> $a['bytes'] * count($a['paths']));
    return $groups;
}

/** Calcula el plan sin tocar nada. */
function img_plan(): array
{
    $media = array_merge(img_list('media/icons'), img_list('media/characters'), img_list('media/profiles'));
    $legacy = array_merge(img_list('bancadigital/icons'), img_list('bancadigital/characters'));
    $refs = img_db_refs();
    $mediaSet = array_flip($media);

    $byKey = [];
    foreach (array_merge($media, $legacy) as $f) {
        $as = str_starts_with($f, 'bancadigital/') ? 'media/' . substr($f, strlen('bancadigital/')) : $f;
        $byKey[img_key($as)][] = $f;
    }

    $copies = [];      // [desde, hacia]
    $missing = [];
    foreach ($refs as $ref) {
        if (isset($mediaSet[$ref])) continue;
        $legacyExact = 'bancadigital/' . substr($ref, strlen('media/'));
        $source = is_file(IMG_ROOT . '/' . $legacyExact) ? $legacyExact : ($byKey[img_key($ref)][0] ?? null);
        if ($source === null) $missing[] = $ref;
        else $copies[$ref] = [$source, $ref];
    }
    // Lo que solo existe en bancadigital/ también pasa a media/ (puede usarlo la ruta automática de la Banca).
    foreach ($legacy as $f) {
        $to = 'media/' . substr($f, strlen('bancadigital/'));
        $to = preg_replace('#^(media/(?:icons|characters)/)liz/#', '$1lis/', $to) ?? $to;
        if (!isset($mediaSet[$to]) && !isset($copies[$to])) $copies[$to] = [$f, $to];
    }

    $refSet = array_flip($refs);
    $final = array_flip(array_merge($media, array_keys($copies)));
    $retire = $legacy;
    foreach ($media as $f) {
        if (isset($refSet[$f])) continue;
        // Carpeta «liz» (el jugador es «Lis») y copias con otro nombre de un archivo que sí se usa.
        $fixed = preg_replace('#^(media/(?:icons|characters)/)liz/#', '$1lis/', $f) ?? $f;
        $twinUsed = false;
        foreach ($byKey[img_key($f)] ?? [] as $other) {
            if ($other !== $f && str_starts_with($other, 'media/') && isset($refSet[$other])) $twinUsed = true;
        }
        foreach ($copies as $to => $_) {
            if ($to !== $f && img_key($to) === img_key($f) && isset($refSet[$to])) $twinUsed = true;
        }
        if ($twinUsed || ($fixed !== $f && isset($final[$fixed]))) $retire[] = $f;
    }

    $big = [];
    foreach (array_keys($final) as $f) {
        if (in_array($f, $retire, true)) continue;
        $limits = favor_image_limits_for($f);
        $abs = IMG_ROOT . '/' . ($copies[$f][0] ?? $f);
        $size = @getimagesize($abs);
        if ($limits && $size && ($size[0] > $limits[0] || $size[1] > $limits[1])) $big[] = $f;
    }

    $bytes = 0;
    foreach (array_merge($media, $legacy) as $f) $bytes += (int)@filesize(IMG_ROOT . '/' . $f);

    return ['refs' => $refs, 'copies' => array_values($copies), 'missing' => $missing, 'big' => $big, 'retire' => array_values(array_unique($retire)), 'replaced' => img_replaced(), 'bytes' => $bytes];
}

$notice = '';
$error = '';
$continue = false;
$log = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        if (!favor_image_gd_ready()) throw new RuntimeException('Este servidor no tiene GD con WebP: no puedo reducir imágenes.');
        @set_time_limit(60);
        $start = microtime(true);
        $budget = 18.0;
        $stamp = date('Y-m-d');
        $plan = img_plan();

        $copied = 0;
        $failed = 0;
        foreach ($plan['copies'] as [$from, $to]) {
            if (img_copy($from, $to)) { $copied++; $log[] = "Copiado $from → $to"; }
            else { $failed++; $log[] = "No pude copiar $from → $to"; }
        }
        $done = 0;
        foreach ($plan['big'] as $f) {
            if (microtime(true) - $start > $budget) { $continue = true; break; }
            $abs = IMG_ROOT . '/' . $f;
            [$maxW, $maxH] = favor_image_limits_for($f);
            $img = favor_image_open($abs);
            if (!$img) { $failed++; $log[] = "No pude abrir $f"; continue; }
            if (!img_copy($f, "respaldos/imagenes-originales-$stamp/$f")) { imagedestroy($img); $failed++; $log[] = "No pude respaldar $f"; continue; }
            $img = favor_image_fit($img, $maxW, $maxH);
            if (favor_image_save($img, $abs, 82)) $done++;
            else { $failed++; $log[] = "No pude guardar $f reducida"; }
            imagedestroy($img);
        }
        $retired = 0;
        if (!$continue) {
            foreach ($plan['retire'] as $f) {
                if (img_move($f, "respaldos/imagenes-retiradas-$stamp")) $retired++;
                else { $failed++; $log[] = "No pude apartar $f"; }
            }
            foreach ($plan['replaced'] as $f => $_) {
                $to = IMG_ROOT . "/respaldos/imagenes-retiradas-$stamp/$f";
                if (file_exists($to)) $to .= '-' . date('His');
                if ((is_dir(dirname($to)) || @mkdir(dirname($to), 0775, true)) && @rename(IMG_ROOT . '/' . $f, $to)) { $retired++; $log[] = "Apartado $f (ya no se usa)"; }
                else { $failed++; $log[] = "No pude apartar $f"; }
            }
            // Carpetas que quedaron vacías (por ejemplo media/icons/liz).
            $dirs = array_unique(array_map('dirname', $plan['retire']));
            usort($dirs, static fn(string $a, string $b): int => substr_count($b, '/') <=> substr_count($a, '/'));
            foreach ($dirs as $d) {
                if (preg_match('#^(media|bancadigital)/(icons|characters)/.+#', $d)) @rmdir(IMG_ROOT . '/' . $d);
            }
        }
        $notice = $continue
            ? "Tanda lista: $done imagen(es) reducidas. Quedan más; la página sigue sola."
            : "Listo: $copied copiadas a media/, $done reducidas y $retired movidas a respaldos/imagenes-retiradas-$stamp/.";
        if ($failed) $error = "$failed archivo(s) no se pudieron tocar: revisa en el File Manager que las carpetas media/ y bancadigital/ tengan permiso de escritura (755) y vuelve a pulsar el botón. Mira «Detalle».";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

try {
    $plan = img_plan();
    $identical = img_identical_groups();
} catch (Throwable $e) {
    $plan = null;
    $identical = [];
    $error = $error ?: $e->getMessage();
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Ordenar imágenes · Favor de Jack</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:26px;color:#eadcb9;font:17px Georgia,serif;background:radial-gradient(circle at top,#4b2d1a,#17100c 48%,#080605)}.wrap{width:min(860px,100%);margin:auto}.top{display:flex;justify-content:space-between;gap:12px;margin-bottom:16px}.top a{color:#edce91;text-decoration:none}.panel{padding:26px;border:1px solid #98703a;border-radius:12px;background:#e8d7ad;color:#312217;box-shadow:0 20px 55px #0008}h1{margin:0 0 8px;color:#522d22}ul{line-height:1.6}.btn{padding:11px 16px;border:1px solid #76501e;background:#795324;color:#fff0c8;font:inherit;font-weight:600;cursor:pointer}.notice,.error{padding:10px 12px;margin:0 0 16px}.notice{border:1px solid #658052;background:#dae7c7;color:#3e5836}.error{border:1px solid #a95d5a;background:#eed0bd;color:#762c30}.help{font-size:.88rem;color:#786145}details{margin-top:14px}code{font-size:.85rem}</style></head><body><div class="wrap">
<div class="top"><a href="<?= h(favor_url('cuenta.php')) ?>">← Mi cuenta</a><a href="<?= h(favor_url('admin/respaldo.php')) ?>">Respaldo de la web</a></div>
<main class="panel">
<h1>Ordenar imágenes</h1>
<p class="help">Junta en <code>media/</code> los iconos y retratos que estaban repartidos entre <code>media/</code> y <code>bancadigital/</code>, los reduce al tamaño que se ve en la web y aparta lo repetido. <b>No borra nada</b>: los originales y lo apartado quedan en <code>respaldos/</code>. Haz antes un respaldo desde «Respaldo de la web».</p>
<?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<?php if ($plan): ?>
<ul>
    <li><b><?= count($plan['refs']) ?></b> imágenes usadas por la Banca y los Archivos.</li>
    <li><b><?= count($plan['copies']) ?></b> por copiar a <code>media/</code> (estaban solo en <code>bancadigital/</code> o con otro nombre).</li>
    <li><b><?= count($plan['big']) ?></b> más grandes de lo necesario (iconos a 256 px, retratos a 900×1400 px).</li>
    <li><b><?= count($plan['retire']) ?></b> archivos sobrantes para apartar.</li>
    <li><b><?= count($plan['replaced']) ?></b> archivos o carpetas que la web ya no usa (su imagen ahora está en un solo sitio)<?= $plan['replaced'] ? ': <b>' . mb_label(array_sum($plan['replaced'])) . '</b>' : '' ?>.</li>
    <li>Peso actual de iconos y retratos: <b><?= mb_label($plan['bytes']) ?></b>.</li>
</ul>
<?php if ($plan['missing']): ?>
<details><summary><b><?= count($plan['missing']) ?></b> rutas de la base de datos sin archivo en ninguna carpeta</summary>
<p class="help">Estos personajes o jugadores apuntan a una imagen que no existe. Súbela con ese nombre o cambia la ruta en la Banca («Editar»).</p>
<ul><?php foreach ($plan['missing'] as $m): ?><li><code><?= h($m) ?></code></li><?php endforeach; ?></ul></details>
<?php endif; ?>
<?php if ($plan['replaced']): ?>
<details><summary>Lo que ya no se usa</summary><ul><?php foreach ($plan['replaced'] as $f => $bytes): ?><li><code><?= h($f) ?></code> (<?= mb_label($bytes) ?>)</li><?php endforeach; ?></ul></details>
<?php endif; ?>
<?php if ($plan['copies'] || $plan['big'] || $plan['retire'] || $plan['replaced']): ?>
<form method="post" id="run"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Ordenar imágenes ahora</button></form>
<?php else: ?>
<p><b>✓ Todo en orden.</b> No queda nada por copiar, reducir ni apartar.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($plan): $extra = array_sum(array_map(static fn(array $g): int => count($g['paths']) - 1, $identical)); ?>
<h2>Imágenes repetidas en toda la web</h2>
<?php if ($identical): ?>
<p>Hay <b><?= count($identical) ?></b> imagen(es) guardadas más de una vez: <b><?= $extra ?></b> copia(s) de sobra, <b><?= mb_label(array_sum(array_map(static fn(array $g): int => $g['bytes'] * (count($g['paths']) - 1), $identical))) ?></b>. Las de <code>bancadigital/</code> y las carpetas antiguas se apartan con el botón de arriba; si queda alguna, dime cuál y la junto.</p>
<details><summary>Ver la lista</summary><ul><?php foreach (array_slice($identical, 0, 200) as $g): ?><li><?= implode(' = ', array_map(static fn(string $p): string => '<code>' . h($p) . '</code>', $g['paths'])) ?></li><?php endforeach; ?></ul></details>
<?php else: ?>
<p><b>✓ Ninguna imagen está guardada dos veces.</b></p>
<?php endif; ?>
<p class="help">Las cartas del Emporio se revisan aparte (también por ilustración): <a href="<?= h(favor_url('tiendas/emporio/admin.php?vista=limpieza')) ?>">Admin del Emporio → Duplicados y limpieza</a>.</p>
<?php endif; ?>
<?php if ($log): ?><details><summary>Detalle</summary><ul><?php foreach (array_slice($log, 0, 300) as $l): ?><li><code><?= h($l) ?></code></li><?php endforeach; ?></ul></details><?php endif; ?>
</main></div>
<?php if ($continue): ?><script>setTimeout(()=>document.getElementById('run')?.submit(),800)</script><?php endif; ?>
</body></html>
