<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/descuentos.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'tiendas/descuentos.php'), true, 303);
    exit;
}
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$isAdmin = ($user['role'] ?? '') === 'admin';
$error = '';
$notice = '';
$characters = [];
$data = ['percent' => 0, 'characters' => [], 'unmatched' => []];

try {
    $characters = favor_discount_characters($isAdmin ? null : (string)$user['playerId']);
    $data = favor_discounts_load();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isAdmin) throw new RuntimeException('Solo un administrador puede marcar descuentos.');
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $percent = (int)($_POST['percent'] ?? 0);
        if ($percent < 0 || $percent > 100) throw new RuntimeException('El porcentaje debe estar entre 0 y 100.');
        $marked = [];
        foreach ((array)($_POST['discount'] ?? []) as $id) {
            $id = (string)$id;
            if (isset($characters[$id])) $marked[$id] = true;
        }
        // Conserva las marcas de personajes que hoy están inactivos (no aparecen en la lista).
        foreach ($data['characters'] as $id => $on) {
            if (!isset($characters[$id])) $marked[$id] = true;
        }
        $data = ['percent' => $percent, 'characters' => $marked, 'unmatched' => $data['unmatched']];
        favor_discounts_save($data, (string)$user['username']);
        $notice = 'Descuentos guardados.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$groups = [];
foreach ($characters as $c) $groups[$c['playerName']][] = $c;
$csrf = favor_csrf();
favor_session_close();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Descuento del gremio · Favor de Jack</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:24px 16px 60px;color:#eadcc0;font:17px Georgia,serif;background:radial-gradient(circle at top,#3e2818,#0d0907 50%)}
.wrap{width:min(900px,100%);margin:0 auto}
.top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.top a{color:#ebcb91;text-decoration:none}
h1{margin:0 0 6px;color:#e5c37e;font-weight:500}
.help{margin:0 0 20px;color:#a99880;line-height:1.5}
.notice,.error{padding:11px 13px;margin:0 0 16px;border-radius:7px}
.notice{border:1px solid #61734e;background:#142013;color:#b9d4a6}
.error{border:1px solid #864d48;background:#271311;color:#efb8b0}
.panel{padding:18px;border:1px solid #483522;border-radius:10px;background:#15100c}
.percent{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.percent input{width:90px;padding:9px;border:1px solid #5a4128;border-radius:6px;background:#090604;color:#fff;font:inherit}
.search{width:100%;padding:10px;margin-bottom:14px;border:1px solid #5a4128;border-radius:7px;background:#090604;color:#fff;font:inherit}
.player{margin:0 0 14px}
.player h2{margin:0 0 6px;color:#c89f63;font:700 .78rem system-ui,sans-serif;letter-spacing:.12em;text-transform:uppercase}
.chars{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px}
.char{display:flex;align-items:center;gap:9px;padding:8px 10px;border:1px solid #3a2a1b;border-radius:7px;background:#0f0b08;cursor:pointer}
.char input{width:18px;height:18px;accent-color:#c0934d}
.char.on{border-color:#8b6737;background:#22170e}
.badge{margin-left:auto;color:#9eb27c;font:700 .7rem system-ui,sans-serif}
.save{position:sticky;bottom:12px;margin-top:16px;padding:12px 18px;border:1px solid #8b6737;border-radius:8px;background:#2b1b10;color:#efd09a;font:inherit;cursor:pointer}
.note{margin-top:14px;color:#8c7b65;font-size:.85rem}
[hidden]{display:none!important}
</style>
</head>
<body>
<div class="wrap">
    <div class="top"><a href="<?= favor_base_path() ?>mapa/">← Mapa de Waterdeep</a><a href="<?= favor_base_path() ?>tiendas/lapocionpicante/">La Poción Picante →</a></div>
    <h1>Descuento del gremio</h1>
    <p class="help"><?= $isAdmin
        ? 'Marca qué personajes tienen descuento. Al elegir uno de ellos en una tienda («Comprar como»), los precios se muestran rebajados.'
        : 'Estos son tus personajes y si tienen descuento en las tiendas. Solo el DM puede cambiar las marcas.' ?></p>
    <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <form class="panel" method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <label class="percent">Descuento:
            <?php if ($isAdmin): ?><input type="number" name="percent" min="0" max="100" step="1" value="<?= (int)$data['percent'] ?>"> %
            <?php else: ?><strong><?= (int)$data['percent'] ?> %</strong><?php endif; ?>
        </label>
        <?php if ($isAdmin && count($characters) > 12): ?><input class="search" id="search" type="search" placeholder="Buscar personaje o jugador…"><?php endif; ?>
        <?php foreach ($groups as $playerName => $list): ?>
            <section class="player" data-player="<?= h(mb_strtolower($playerName, 'UTF-8')) ?>">
                <h2><?= h($playerName) ?></h2>
                <div class="chars">
                    <?php foreach ($list as $c): $on = !empty($data['characters'][$c['id']]); ?>
                        <label class="char<?= $on ? ' on' : '' ?>" data-name="<?= h(mb_strtolower($c['name'], 'UTF-8')) ?>">
                            <input type="checkbox" name="discount[]" value="<?= h($c['id']) ?>"<?= $on ? ' checked' : '' ?><?= $isAdmin ? '' : ' disabled' ?>>
                            <span><?= h($c['name']) ?></span>
                            <?php if ($on): ?><span class="badge">Con descuento</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if (!$characters): ?><p>No hay personajes activos.</p><?php endif; ?>
        <?php if ($isAdmin): ?><button class="save" type="submit">Guardar descuentos</button><?php endif; ?>
        <?php if ($isAdmin && $data['unmatched']): ?>
            <p class="note">De la planilla «Descuento Hecho» no encontré entre los personajes activos a: <?= h(implode(', ', $data['unmatched'])) ?>. Márcalos a mano si tienen otro nombre.</p>
        <?php endif; ?>
    </form>
</div>
<script>
document.querySelectorAll('.char input').forEach(i=>i.addEventListener('change',()=>i.closest('.char').classList.toggle('on',i.checked)));
const q=document.getElementById('search');
if(q){
    const norm=v=>String(v||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().trim();
    q.addEventListener('input',()=>{
        const s=norm(q.value);
        document.querySelectorAll('.player').forEach(p=>{
            const byPlayer=!s||norm(p.dataset.player).includes(s);
            let any=false;
            p.querySelectorAll('.char').forEach(c=>{const show=byPlayer||norm(c.dataset.name).includes(s);c.hidden=!show;any=any||show});
            p.hidden=!any;
        });
    });
}
</script>
</body>
</html>
