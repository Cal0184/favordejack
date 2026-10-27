<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Frame-Options: DENY');

// La administración usa la misma sesión que el resto de la web: entra quien
// tenga una cuenta con rol «admin».
$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'tiendas/emporio/admin.php'), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin · Emporio</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0907;color:#eadcc0;font-family:Georgia,serif}main{width:min(430px,92vw);padding:28px;border:1px solid #72512e;border-radius:12px;background:#17100c}h1{color:#e8c785}a{display:inline-block;padding:10px 15px;border:1px solid #8b6737;border-radius:7px;background:#2b1b10;color:#efd09a;text-decoration:none}</style>
    <?php favor_head('Admin del Emporio · Favor de Jack'); ?>
</head><body>
<?php favor_site_bar(); ?><main><h1>Administración del Emporio</h1><p>Solo una cuenta de administrador puede editar el catálogo de Ecix's.</p><a href="index.php">Volver a la tienda</a></main></body></html><?php
    exit;
}

$view = (string)($_GET['vista'] ?? 'cartas');
if (!in_array($view, ['cartas', 'pedidos', 'limpieza'], true)) $view = 'cartas';
$error = '';
$notice = '';

/** Mueve un archivo a la carpeta de retirados (crea subcarpetas). */
function emporio_retire_file(string $from, string $dir, string $relative): bool
{
    if (!is_file($from)) return false;
    $to = $dir . '/' . $relative;
    if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0775, true)) return false;
    return @rename($from, $to);
}

/** Miniatura que acompaña a una carta. */
function emporio_derived_files(string $id): array
{
    return [emporio_mini_path($id)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));

        if ($action === 'save') {
            $id = canonical_slug(trim((string)($_POST['id'] ?? '')));
            if ($id === '') throw new RuntimeException('Objeto inválido.');
            emporio_update_json(EMPORIO_CATALOG_FILE, static function (array $raw) use ($id): array {
                $catalog = normalize_catalog($raw);
                $catalog[$id] = [
                    'id' => $id,
                    'name' => trim((string)($_POST['name'] ?? '')),
                    'category' => trim((string)($_POST['category'] ?? '')) ?: 'Sin categoría',
                    'price' => trim((string)($_POST['price'] ?? '?')) ?: '?',
                    'stock' => max(0, (int)($_POST['stock'] ?? 0)),
                    'description' => trim((string)($_POST['description'] ?? '')),
                    'available' => isset($_POST['available']),
                ];
                return $catalog;
            });
            $notice = 'Cambios guardados.';
        } elseif ($action === 'order_state') {
            $id = (string)($_POST['id'] ?? '');
            $state = (string)($_POST['state'] ?? '');
            if (!isset(EMPORIO_ORDER_STATES[$state])) throw new RuntimeException('Estado no válido.');
            $reply = mb_substr(trim((string)($_POST['reply'] ?? '')), 0, 500, 'UTF-8');
            [, $note] = emporio_order_set_state($id, $state, $reply, emporio_final_price($_POST['final'] ?? ''), $user);
            $notice = trim('Pedido actualizado. ' . $note);
        } elseif ($action === 'keep_both' || $action === 'prefer' || $action === 'reset_dupes') {
            emporio_update_json(EMPORIO_DUPES_FILE, static function (array $data) use ($action): array {
                $data['keepBoth'] = array_values(array_filter((array)($data['keepBoth'] ?? []), 'is_string'));
                $data['prefer'] = array_filter((array)($data['prefer'] ?? []), 'is_string');
                if ($action === 'reset_dupes') return ['keepBoth' => [], 'prefer' => []];
                $members = array_values(array_filter(explode(',', (string)($_POST['members'] ?? '')), 'strlen'));
                if ($action === 'keep_both') {
                    // Todos los pares del grupo se muestran por separado.
                    foreach ($members as $i => $a) {
                        foreach (array_slice($members, $i + 1) as $b) $data['keepBoth'][] = emporio_pair_key($a, $b);
                    }
                    $data['keepBoth'] = array_values(array_unique($data['keepBoth']));
                } else {
                    $keep = (string)($_POST['keep'] ?? '');
                    foreach ($members as $m) unset($data['prefer'][$m]);
                    if ($keep !== '') $data['prefer'][$keep] = $keep;
                }
                return $data;
            });
            $notice = $action === 'keep_both' ? 'Ese grupo ya no se considera duplicado: se muestran todas las cartas.' : 'Preferencia guardada.';
        } elseif ($action === 'retire') {
            // Mueve a data/retirados/<fecha>/ las cartas duplicadas ocultas, las copias
            // sobrantes del mismo objeto y renombra los archivos con tildes rotas.
            $stamp = date('Y-m-d_His');
            $dir = EMPORIO_RETIRED_DIR . '/' . $stamp;
            $all = emporio_all_cards();
            $dupes = emporio_find_duplicates($all, emporio_hashes($all, 20.0));
            $moved = 0;
            $renamed = 0;
            $retiredCatalog = [];
            foreach ($dupes as $group) {
                foreach ($group['hide'] as $id) {
                    $card = $all[$id];
                    foreach (array_merge([$card['file']], $card['twins']) as $f) {
                        if (emporio_retire_file(EMPORIO_CARTAS_DIR . '/' . $f, $dir, 'Cartas/' . $f)) $moved++;
                    }
                    foreach (emporio_derived_files($id) as $p) emporio_retire_file($p, $dir, substr($p, strlen(__DIR__) + 1));
                    $retiredCatalog[$id] = $id;
                    unset($all[$id]);
                }
            }
            foreach ($all as $card) {
                foreach ($card['twins'] as $f) {
                    if (emporio_retire_file(EMPORIO_CARTAS_DIR . '/' . $f, $dir, 'Cartas/' . $f)) $moved++;
                }
                $fixed = emporio_fix_name($card['file']);
                if ($fixed !== $card['file'] && !is_file(EMPORIO_CARTAS_DIR . '/' . $fixed)
                    && @copy(EMPORIO_CARTAS_DIR . '/' . $card['file'], EMPORIO_CARTAS_DIR . '/' . $fixed)) {
                    // Copia con el nombre bueno y el original con «#U» va a retirados.
                    @touch(EMPORIO_CARTAS_DIR . '/' . $fixed, (int)filemtime(EMPORIO_CARTAS_DIR . '/' . $card['file']));
                    emporio_retire_file(EMPORIO_CARTAS_DIR . '/' . $card['file'], $dir, 'Cartas/' . $card['file']);
                    $renamed++;
                }
            }
            // Copias de versiones anteriores (Cartas/webp/, miniaturas de 240 px).
            foreach (emporio_old_copies() as $p) {
                if (emporio_retire_file($p, $dir, substr($p, strlen(__DIR__) + 1))) $moved++;
            }
            foreach (['Cartas/webp'] as $empty) @rmdir(__DIR__ . '/' . $empty);
            if ($retiredCatalog) {
                emporio_update_json(EMPORIO_CATALOG_FILE, static function (array $raw) use ($retiredCatalog, $dir): array {
                    $catalog = normalize_catalog($raw);
                    $out = array_intersect_key($catalog, $retiredCatalog);
                    if ($out) emporio_save_json($dir . '/catalog-retirado.json', $out);
                    return array_diff_key($catalog, $retiredCatalog);
                });
            }
            $notice = $moved || $renamed
                ? "Listo: $moved archivo(s) movidos a data/retirados/$stamp/ y $renamed nombre(s) con tildes arreglados. Nada se borró."
                : 'No había duplicados ni nombres rotos que retirar.';
        } elseif ($action === 'restore') {
            $stamp = basename((string)($_POST['stamp'] ?? ''));
            $dir = EMPORIO_RETIRED_DIR . '/' . $stamp;
            if ($stamp === '' || !is_dir($dir)) throw new RuntimeException('No encontré ese retiro.');
            $restored = 0;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $relative = substr($file->getPathname(), strlen($dir) + 1);
                if ($relative === 'catalog-retirado.json') continue;
                $target = __DIR__ . '/' . $relative;
                if (!is_dir(dirname($target))) @mkdir(dirname($target), 0775, true);
                if (!is_file($target) && @rename($file->getPathname(), $target)) $restored++;
            }
            $back = load_json_file($dir . '/catalog-retirado.json');
            if ($back) {
                emporio_update_json(EMPORIO_CATALOG_FILE, static fn(array $raw): array => normalize_catalog($raw) + normalize_catalog($back));
                @unlink($dir . '/catalog-retirado.json');
            }
            // Quita las carpetas que quedaron vacías.
            $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($dirs as $d) if ($d->isDir()) @rmdir($d->getPathname());
            @rmdir($dir);
            $notice = "Devolví $restored archivo(s) a la tienda. Si siguen siendo duplicados, la tienda los vuelve a ocultar hasta que marques «Son objetos distintos».";
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$catalog = emporio_catalog();
$all = emporio_all_cards($catalog);
// Las cartas nuevas reciben sus copias WebP al abrir esta página (unas pocas por visita).
$madeMinis = emporio_make_missing_minis($all);
if ($madeMinis > 0) $all = emporio_all_cards($catalog);
$hashes = emporio_hashes($all, 8.0);
$dupes = emporio_find_duplicates($all, $hashes);
$hidden = [];
foreach ($dupes as $g) foreach ($g['hide'] as $id) $hidden[$id] = $g['keep'];
$missingWebp = emporio_cards_pending_webp($all);
$oldCopies = emporio_old_copies();
$brokenNames = array_filter($all, static fn(array $c): bool => emporio_fix_name($c['file']) !== $c['file']);
$twinFiles = array_sum(array_map(static fn(array $c): int => count($c['twins']), $all));
$orphans = array_diff_key($catalog, $all);
$orders = emporio_orders_for($user, 200);
$pendingOrders = count(array_filter($orders, static fn(array $o): bool => ($o['state'] ?? '') === 'pendiente'));
$retiredRuns = is_dir(EMPORIO_RETIRED_DIR) ? array_reverse(array_values(array_filter(scandir(EMPORIO_RETIRED_DIR) ?: [], static fn(string $d): bool => $d[0] !== '.' && is_dir(EMPORIO_RETIRED_DIR . '/' . $d)))) : [];
$csrf = favor_csrf();
favor_session_close();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Admin · Emporio</title>
<style>[hidden]{display:none!important}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#3e2818,#0d0907 45%);color:#eadcc0;font-family:Georgia,serif}header{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;padding:14px 4vw;border-bottom:1px solid #5a4128;background:#100b08ee;backdrop-filter:blur(10px)}header h1{margin:0;color:#e5c37e;font-size:1.4rem}nav{display:flex;gap:8px;flex-wrap:wrap}a,button{padding:9px 13px;border:1px solid #76562f;border-radius:7px;background:#21150e;color:#ebcb91;text-decoration:none;font:inherit;cursor:pointer}nav a.active{background:#4a3018;border-color:#c49251}.badge{display:inline-block;min-width:20px;margin-left:4px;padding:0 6px;border-radius:99px;background:#a3452f;color:#fff;font:700 .72rem/20px system-ui,sans-serif;text-align:center}
main{width:min(1350px,94vw);margin:26px auto 70px}.toolbar{position:sticky;top:70px;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;padding:10px;border:1px solid #493724;border-radius:9px;background:#100b08ee;backdrop-filter:blur(10px)}.toolbar input{width:min(460px,100%);padding:11px;border:1px solid #5a4128;border-radius:7px;background:#0b0806;color:#fff}
.discount-line{color:#b9d4a6}.notice,.error,.info{padding:11px 13px;margin:10px 0;border-radius:7px;line-height:1.45}.notice{border:1px solid #61734e;background:#142013;color:#b9d4a6}.error{border:1px solid #864d48;background:#271311;color:#efb8b0}.info{border:1px solid #5a4128;background:#16100c;color:#cdb68d}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:16px}.card{display:grid;grid-template-columns:120px 1fr;gap:14px;padding:13px;border:1px solid #483522;border-radius:10px;background:#15100c;box-shadow:0 15px 35px #0005}.card.is-hidden{opacity:.6;border-style:dashed}.card img{width:120px;height:168px;object-fit:contain;background:#090604;border:1px solid #33251a}.card h2{margin:0 0 8px;color:#e4c388;font-size:1.05rem}
label{display:grid;gap:4px;margin:7px 0;color:#a99880;font-size:.8rem}input,textarea,select{width:100%;padding:9px;border:1px solid #4b3825;border-radius:6px;background:#090604;color:#f4e5ca;font:inherit}textarea{min-height:120px;resize:vertical;line-height:1.45}.row{display:grid;grid-template-columns:1fr 110px;gap:8px}.check{display:flex;align-items:center;gap:8px}.check input{width:auto}.filename{color:#776b5c;word-break:break-all;font-size:.72rem}.id-note{margin-top:3px;color:#665b4d;font:600 .65rem system-ui,sans-serif}.warn{color:#e5a69d;font:600 .75rem system-ui,sans-serif}.save{width:100%;margin-top:8px}.empty{padding:30px;border:1px dashed #5a4128;text-align:center;color:#8c7b65}
section.box{margin:0 0 22px;padding:18px;border:1px solid #483522;border-radius:10px;background:#130e0a}section.box h2{margin:0 0 10px;color:#e5c37e}.dupe{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;padding:12px 0;border-top:1px solid #2f2218}.dupe figure{margin:0;width:130px;text-align:center;font-size:.78rem}.dupe figure img{width:120px;height:168px;object-fit:contain;background:#090604;border:2px solid #33251a}.dupe figure.keep img{border-color:#6f8f4d}.dupe figure figcaption b{display:block;color:#e4c388}.dupe form{display:flex;flex-direction:column;gap:6px;min-width:220px}
.orders{display:grid;gap:12px}.order{display:grid;grid-template-columns:1fr minmax(260px,340px);gap:14px;padding:14px;border:1px solid #483522;border-radius:10px;background:#15100c}.order h3{margin:0 0 4px;color:#e4c388}.order ul{margin:6px 0;padding-left:18px;line-height:1.5}.muted{color:#8c7b65;font:600 .76rem system-ui,sans-serif}.state{display:inline-block;padding:3px 9px;border-radius:99px;border:1px solid #5d4228;font:800 .65rem system-ui,sans-serif;text-transform:uppercase;letter-spacing:.08em}.state-pendiente{border-color:#c49251;color:#f0cd85}.state-aprobado{border-color:#5a7a3d;color:#b8d39a}.state-entregado{border-color:#4f6f8f;color:#a9c6e3}.state-rechazado,.state-cancelado{border-color:#7a3d38;color:#e5a69d}.charged-line{color:#b8d39a;font-weight:700}
table{width:100%;border-collapse:collapse;font-size:.85rem}td,th{padding:6px 8px;border-bottom:1px solid #2f2218;text-align:left}
@media(max-width:700px){.toolbar{position:relative;top:auto;flex-direction:column;align-items:stretch}.card{grid-template-columns:1fr}.card img{width:100%;height:260px}.order{grid-template-columns:1fr}}
</style><?php favor_head('Admin del Emporio · Favor de Jack'); ?>
</head><body>
<?php favor_site_bar(); ?>
<header><h1>Admin · El Emporio de Ecix's</h1>
<nav aria-label="Secciones del admin">
    <a href="?vista=cartas" class="<?= $view === 'cartas' ? 'active' : '' ?>">Cartas</a>
    <a href="?vista=pedidos" class="<?= $view === 'pedidos' ? 'active' : '' ?>">Pedidos<?php if ($pendingOrders): ?><span class="badge"><?= $pendingOrders ?></span><?php endif; ?></a>
    <a href="?vista=limpieza" class="<?= $view === 'limpieza' ? 'active' : '' ?>">Duplicados y limpieza<?php if ($dupes): ?><span class="badge"><?= count($dupes) ?></span><?php endif; ?></a>
    <a href="../descuentos.php?tienda=nuevo-emporio">Descuentos</a>
    <a href="index.php">Ver tienda</a>
</nav></header>
<main>
<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<?php if ($view === 'cartas'): ?>
<div class="toolbar"><div><strong><?= count($all) ?> cartas · <?= count($all) - count($hidden) ?> visibles en la tienda</strong><div class="filename">Las cartas marcadas «oculta» son duplicadas; se gestionan en «Duplicados y limpieza».</div></div><input id="search" type="search" placeholder="Buscar por nombre, categoría o ID..."></div>
<section class="grid">
<?php foreach ($all as $card): ?>
<form class="card<?= isset($hidden[$card['id']]) ? ' is-hidden' : '' ?>" method="post" action="?vista=cartas" data-search="<?= e(emporio_ascii($card['name'] . ' ' . $card['category'] . ' ' . $card['id'])) ?>">
<img loading="lazy" src="<?= e($card['thumb']) ?>" alt="<?= e($card['name']) ?>">
<div>
<input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($card['id']) ?>">
<h2><?= e($card['name']) ?></h2><div class="filename"><?= e($card['file']) ?></div><div class="id-note">ID interno: <?= e($card['id']) ?></div>
<?php if (isset($hidden[$card['id']])): ?><p class="warn">Oculta: duplicado de «<?= e($all[$hidden[$card['id']]]['name']) ?>».</p><?php endif; ?>
<label>Nombre visible<input name="name" value="<?= e($card['name']) ?>" required></label>
<label>Categoría<input name="category" value="<?= e($card['category']) ?>" list="categorias"></label>
<div class="row"><label>Precio<input name="price" value="<?= e($card['price']) ?>"></label><label>Stock<input name="stock" type="number" min="0" value="<?= (int)$card['stock'] ?>"></label></div>
<label>Descripción<textarea name="description" placeholder="Describe qué hace, sus límites, usos y requisitos."><?= e($card['description']) ?></textarea></label>
<label class="check"><input type="checkbox" name="available" <?= $card['available'] ? 'checked' : '' ?>> Disponible</label>
<button class="save" type="submit">Guardar cambios</button>
</div>
</form>
<?php endforeach; ?>
<?php if (!$all): ?><div class="empty">No se encontraron cartas en la carpeta Cartas/.</div><?php endif; ?>
</section>
<datalist id="categorias"><?php foreach (array_unique(array_column($all, 'category')) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>

<?php elseif ($view === 'pedidos'): ?>
<div class="info">Los jugadores arman su pedido desde la tienda y eligen qué personaje compra. Aquí lo apruebas, rechazas o marcas como entregado, con un mensaje opcional que el jugador ve en «Mis pedidos». Al pasarlo a <b>Aprobado</b> o <b>Entregado</b> se cobra solo en la Banca y los objetos entran al Inventario del personaje. Si después lo rechazas o cancelas, el oro vuelve y esos objetos se quitan.</div>
<div class="orders">
<?php foreach ($orders as $o): $p = emporio_order_public($o); ?>
    <article class="order">
        <div>
            <h3><?= e($p['character']) ?> <span class="muted">· <?= e($p['player']) ?></span></h3>
            <p class="muted"><?= e(date('d-m-Y H:i', strtotime($p['created']) ?: time())) ?> · <span class="state state-<?= e($p['state']) ?>"><?= e($p['stateLabel']) ?></span>
            <?php if (isset($o['characterGold'])): ?> · tenía <?= e(number_format((float)$o['characterGold'], 0, ',', '.')) ?> po al pedir<?php endif; ?></p>
            <ul><?php foreach ($p['items'] as $i): ?><li><?= (int)($i['qty'] ?? 1) > 1 ? (int)$i['qty'] . ' × ' : '' ?><?= e((string)($i['name'] ?? '')) ?> <span class="muted"><?= e((string)($i['price'] ?? '')) ?></span></li><?php endforeach; ?></ul>
            <?php if ($p['discount']): ?><p class="discount-line">✦ Descuento <?= (int)$p['discount']['percent'] ?> % en <?= e((string)$p['discount']['item']) ?>: −<?= e(number_format((float)$p['discount']['amount'], fmod((float)$p['discount']['amount'], 1) ? 2 : 0, ',', '.')) ?> po (paga <?= e((string)$p['discount']['paid']) ?>)<?= in_array($p['state'], EMPORIO_ORDER_CLOSED, true) ? ' · devuelto al personaje' : '' ?></p><?php endif; ?>
            <?php if ($p['total'] !== null): ?><p><b>Total estimado: <?= e(number_format((float)$p['total'], fmod((float)$p['total'], 1) ? 2 : 0, ',', '.')) ?> po</b></p><?php endif; ?>
            <?php if ($p['charged'] !== null): ?><p class="charged-line">✔ Cobrado en la Banca: <?= e(number_format((float)$p['charged'], fmod((float)$p['charged'], 1) ? 2 : 0, ',', '.')) ?> po</p><?php endif; ?>
            <?php if ($p['note'] !== ''): ?><p class="muted">Mensaje: <?= e($p['note']) ?></p><?php endif; ?>
        </div>
        <form method="post" action="?vista=pedidos">
            <input type="hidden" name="action" value="order_state"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
            <label>Estado<select name="state"><?php foreach (EMPORIO_ORDER_STATES as $k => $label): ?><option value="<?= e($k) ?>" <?= $k === $p['state'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <?php if ($p['charged'] === null): ?><label>Precio final (po)<input name="final" inputmode="decimal" placeholder="<?= $p['total'] !== null ? e((string)$p['total']) : 'Obligatorio: hay objetos sin precio' ?>"></label><?php endif; ?>
            <label>Respuesta de Ecix's<textarea name="reply" maxlength="500" style="min-height:70px"><?= e($p['reply']) ?></textarea></label>
            <button type="submit">Guardar</button>
        </form>
    </article>
<?php endforeach; ?>
<?php if (!$orders): ?><div class="empty">Todavía no hay pedidos.</div><?php endif; ?>
</div>

<?php else: ?>
<section class="box">
    <h2>Duplicados (<?= count($dupes) ?>)</h2>
    <p class="info">La tienda ya oculta sola las cartas repetidas, por <b>nombre</b> o por <b>ilustración</b> (aunque el nombre sea distinto). Aquí eliges cuál se queda, o marcas «Son objetos distintos» si deben verse las dos.</p>
    <?php foreach ($dupes as $g): ?>
        <div class="dupe">
            <?php foreach ($g['members'] as $id): $c = $all[$id]; ?>
                <figure class="<?= $id === $g['keep'] ? 'keep' : '' ?>"><img loading="lazy" src="<?= e($c['thumb']) ?>" alt=""><figcaption><b><?= e($c['name']) ?></b><?= e($c['priceLabel']) ?><br><?= $id === $g['keep'] ? '✓ se muestra' : 'oculta' ?></figcaption></figure>
            <?php endforeach; ?>
            <div>
                <p class="muted">Repetidas por <?= e($g['reason']) ?>.</p>
                <form method="post" action="?vista=limpieza">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="members" value="<?= e(implode(',', $g['members'])) ?>">
                    <label>Mostrar solo<select name="keep"><?php foreach ($g['members'] as $id): ?><option value="<?= e($id) ?>" <?= $id === $g['keep'] ? 'selected' : '' ?>><?= e($all[$id]['name']) ?></option><?php endforeach; ?></select></label>
                    <button name="action" value="prefer" type="submit">Guardar elección</button>
                    <button name="action" value="keep_both" type="submit">Son objetos distintos: mostrar todas</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$dupes): ?><p>No hay cartas repetidas.</p><?php endif; ?>
</section>

<section class="box">
    <h2>Retirar archivos sobrantes</h2>
    <p>Hay <b><?= count($hidden) ?></b> carta(s) duplicada(s) oculta(s), <b><?= $twinFiles ?></b> copia(s) extra del mismo objeto (por ejemplo el PNG de una carta que ya está en WebP), <b><?= count($oldCopies) ?></b> copia(s) de versiones anteriores y <b><?= count($brokenNames) ?></b> nombre(s) con tildes rotas («#U00f3n»).</p>
    <p class="muted">Al pulsar el botón, los archivos sobrantes se <b>mueven</b> a <code>tiendas/emporio/data/retirados/&lt;fecha&gt;/</code> (no se borran) y su ficha del catálogo se guarda ahí mismo. Los nombres rotos se copian con el nombre correcto. Se puede deshacer abajo. Haz antes un respaldo desde «Mi cuenta → Respaldo de la web».</p>
    <form method="post" action="?vista=limpieza" onsubmit="return confirm('¿Mover los archivos sobrantes a data/retirados/? Se puede deshacer desde esta misma página.')">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button name="action" value="retire" type="submit" <?= ($hidden || $twinFiles || $brokenNames || $oldCopies) ? '' : 'disabled' ?>>Retirar sobrantes y arreglar nombres</button>
    </form>
    <?php if ($retiredRuns): ?>
        <h3>Retiros anteriores</h3>
        <?php foreach ($retiredRuns as $run): ?>
            <form method="post" action="?vista=limpieza" style="display:flex;gap:10px;align-items:center;margin:6px 0" onsubmit="return confirm('¿Devolver estos archivos a la tienda?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="stamp" value="<?= e($run) ?>">
                <code><?= e($run) ?></code><button name="action" value="restore" type="submit">Deshacer este retiro</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="box">
    <h2>Imágenes WebP</h2>
    <p><?= $missingWebp ? "Faltan <b>$missingWebp</b> carta(s) por pasar a WebP o sin miniatura. Se hacen solas, unas 15 cada vez que abres esta página: recárgala hasta que diga 0 y después pulsa «Retirar sobrantes» para apartar los PNG." : '✓ Todas las cartas están en WebP y tienen su miniatura.' ?><?= $madeMinis ? " En esta visita se crearon $madeMinis." : '' ?></p>
</section>

<section class="box">
    <h2>Fichas del catálogo sin carta (<?= count($orphans) ?>)</h2>
    <p class="muted">Estas fichas existen en <code>data/catalog.json</code> pero no tienen imagen en <code>Cartas/</code>, así que no aparecen en la tienda. Para mostrarlas, sube su imagen a <code>Cartas/</code> con el mismo nombre.</p>
    <?php if ($orphans): ?><table><tr><th>Nombre</th><th>Precio</th><th>ID</th></tr><?php foreach ($orphans as $id => $o): ?><tr><td><?= e((string)($o['name'] ?? $id)) ?></td><td><?= e(emporio_price_label((string)($o['price'] ?? ''))) ?></td><td class="muted"><?= e($id) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
    <form method="post" action="?vista=limpieza" style="margin-top:14px"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button name="action" value="reset_dupes" type="submit">Olvidar mis elecciones de duplicados</button></form>
</section>
<?php endif; ?>
</main>
<?php if ($view === 'cartas'): ?>
<script>
const q=document.querySelector('#search'),cards=[...document.querySelectorAll('.card')];
const normalize=v=>String(v??'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLocaleLowerCase('es').trim();
q.oninput=()=>{const s=normalize(q.value);cards.forEach(c=>c.hidden=!!s&&!normalize(c.dataset.search).includes(s))};
</script>
<?php endif; ?>
</body></html>
