<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/descuentos.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

/*
 * Panel de descuentos (solo administradores).
 *   descuentos.php               todas las tiendas
 *   descuentos.php?tienda=<id>   descuento de esa tienda y personajes por jugador
 */

$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'tiendas/descuentos.php'), true, 303);
    exit;
}
if (!favor_discount_is_admin($user)) {
    http_response_code(403);
    favor_session_close();
    exit('<!doctype html><meta charset="utf-8"><title>Descuentos</title><p style="font:18px Georgia,serif;padding:30px">Los descuentos los administra el DM. Los tuyos aparecen al elegir tu personaje en cada tienda. <a href="' . favor_base_path() . 'mapa/">Volver al mapa</a></p>');
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function when(string $iso): string
{
    if ($iso === '') return '';
    $t = strtotime($iso);
    if (!$t) return '';
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . (date('Y', $t) !== date('Y') ? ' ' . date('Y', $t) : '');
}

$shops = favor_discount_shop_list();
$shopId = (string)($_GET['tienda'] ?? '');
if ($shopId !== '' && !isset($shops[$shopId])) $shopId = '';
$error = '';
$notice = '';
$characters = [];
$data = ['shops' => [], 'used' => [], 'log' => [], 'unmatched' => []];
$by = (string)$user['username'];

try {
    $characters = favor_discount_characters();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        if ($shopId === '') throw new RuntimeException('Elige una tienda.');
        $action = (string)($_POST['action'] ?? '');
        $character = $characters[(string)($_POST['character'] ?? '')] ?? null;

        if ($action === 'settings') {
            $percent = (int)($_POST['percent'] ?? 0);
            if ($percent < 0 || $percent > 100) throw new RuntimeException('El porcentaje debe estar entre 0 y 100.');
            $rule = mb_substr(trim((string)($_POST['rule'] ?? '')), 0, 120, 'UTF-8');
            $active = isset($_POST['active']) && $percent > 0;
            favor_discounts_update(static function (array $d) use ($shopId, $percent, $rule, $active): array {
                $d['shops'][$shopId] = ['percent' => $percent, 'rule' => $rule, 'active' => $active];
                return $d;
            }, $by);
            $notice = $active ? 'Descuento guardado.' : 'Guardado: esta tienda queda sin descuento.';
        } elseif ($action === 'reactivate' && $character) {
            favor_discount_restore($shopId, $character, $by);
            $notice = $character['name'] . ' vuelve a tener su descuento.';
        } elseif ($action === 'mark_used' && $character) {
            favor_discounts_update(static function (array $d, &$r) use ($shopId, $character, $by): array {
                $item = mb_substr(trim((string)($_POST['item'] ?? '')), 0, 160, 'UTF-8') ?: 'Marcado por el DM';
                $d['used'][$shopId][$character['id']] = ['at' => date('c'), 'by' => $by, 'item' => $item];
                favor_discount_log($d, 'marcado', $shopId, $character, $by, $item);
                return $d;
            }, $by);
            $notice = $character['name'] . ' queda con el descuento usado.';
        } elseif ($action === 'reactivate_all') {
            $count = favor_discounts_update(static function (array $d, &$r) use ($shopId, $characters, $by): array {
                $r = 0;
                foreach (array_keys($d['used'][$shopId] ?? []) as $id) {
                    if (!isset($characters[$id])) continue;
                    unset($d['used'][$shopId][$id]);
                    $r++;
                }
                $d['log'][] = ['at' => date('c'), 'action' => 'reactivado', 'shop' => $shopId, 'characterId' => '', 'character' => "Todos ($r)", 'player' => '', 'by' => $by, 'detail' => ''];
                return $d;
            }, $by);
            $notice = "Reactivé el descuento de $count personaje(s).";
        } else {
            throw new RuntimeException('Acción no válida.');
        }
    }
    $data = favor_discounts_load();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$csrf = favor_csrf();
favor_session_close();
$base = favor_base_path();
$shopNames = array_map(static fn(array $s): string => $s['name'], $shops);
$logLabels = ['usado' => 'usó', 'marcado' => 'marcado como usado', 'reactivado' => 'reactivado', 'pedido cancelado' => 'devuelto (pedido cancelado)', 'pedido rechazado' => 'devuelto (pedido rechazado)'];

$groups = [];
foreach ($characters as $c) $groups[$c['playerName']][] = $c;
$recent = array_reverse(array_values(array_filter($data['log'], static fn(array $l): bool => $shopId === '' || ($l['shop'] ?? '') === $shopId)));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $shopId ? h($shops[$shopId]['name']) . ' · ' : '' ?>Descuentos · Favor de Jack</title>
<link rel="icon" type="image/webp" href="<?= h(favor_static(FAVOR_LOGO)) ?>">
<style>
:root{--bg:#0d0907;--panel:#15100c;--line:#3a2a1b;--line2:#5a4128;--gold:#e5c37e;--text:#eadcc0;--muted:#a99880;--ok:#9fcf86;--okbg:#14200f;--used:#e3a38f;--usedbg:#2a1511}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:24px 16px 70px;color:var(--text);font:17px Georgia,serif;background:radial-gradient(circle at top,#3e2818,var(--bg) 50%)}
a{color:#ebcb91}
.wrap{width:min(1100px,100%);margin:0 auto}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.top a{text-decoration:none}
h1{margin:0 0 4px;color:var(--gold);font-weight:500;font-size:clamp(1.7rem,4vw,2.3rem)}
.help{margin:0 0 20px;color:var(--muted);line-height:1.5;max-width:70ch}
.notice,.error{padding:11px 13px;margin:0 0 16px;border-radius:8px}
.notice{border:1px solid #61734e;background:var(--okbg);color:#b9d4a6}
.error{border:1px solid #864d48;background:#271311;color:#efb8b0}
.shops{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.shop{position:relative;display:grid;grid-template-columns:72px 1fr;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--panel);color:inherit;text-decoration:none;transition:border-color .15s,transform .15s}
.shop:hover,.shop:focus-visible{border-color:#8b6737;transform:translateY(-2px)}
.shop.closed{opacity:.6}
.shop img,.shop .ph{width:72px;height:72px;border-radius:50%;object-fit:cover;object-position:top;background:#090604;border:1px solid var(--line2)}
.shop .ph{display:grid;place-items:center;font-size:1.8rem;color:var(--gold)}
.shop h2{margin:0 0 4px;font:600 1.05rem Georgia,serif;color:var(--gold)}
.shop .deal{margin:0 0 6px;font-size:.95rem}
.shop .deal.none{color:var(--muted);font-style:italic}
.counts{display:flex;gap:6px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:99px;font:600 .72rem system-ui,sans-serif;letter-spacing:.02em;white-space:nowrap}
.pill.ok{background:var(--okbg);color:var(--ok);border:1px solid #3c5a2c}
.pill.used{background:var(--usedbg);color:var(--used);border:1px solid #6b3a2e}
.pill.mute{background:#1d1610;color:var(--muted);border:1px solid var(--line)}
.panel{padding:18px;border:1px solid var(--line);border-radius:12px;background:var(--panel);margin-bottom:18px}
.panel h2{margin:0 0 12px;font:700 .8rem system-ui,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#c89f63}
.settings{display:grid;grid-template-columns:auto 1fr auto auto;gap:12px;align-items:end}
.settings label{display:grid;gap:5px;font-size:.85rem;color:var(--muted)}
.settings input[type=number],.settings input[type=text]{padding:10px;border:1px solid var(--line2);border-radius:7px;background:#090604;color:#fff;font:inherit}
.settings input[type=number]{width:96px}
.settings .check{display:flex;align-items:center;gap:8px;padding-bottom:10px;color:var(--text);font-size:.95rem}
.check input{width:18px;height:18px;accent-color:#c0934d}
button,.btn{padding:9px 14px;border:1px solid #8b6737;border-radius:8px;background:#2b1b10;color:#efd09a;font:inherit;font-size:.92rem;cursor:pointer}
button:hover{background:#392516}
button.ghost{border-color:var(--line2);background:transparent;color:var(--muted)}
.hero{display:flex;gap:16px;align-items:center;margin-bottom:16px}
.hero img{width:84px;height:84px;border-radius:50%;object-fit:cover;object-position:top;border:1px solid var(--line2)}
.tools{position:sticky;top:0;z-index:5;display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:10px 0;margin-bottom:6px;background:linear-gradient(var(--bg) 70%,transparent)}
.tools input{flex:1 1 220px;padding:10px 12px;border:1px solid var(--line2);border-radius:8px;background:#090604;color:#fff;font:inherit}
.chips{display:flex;gap:6px}
.chip{padding:7px 12px;border:1px solid var(--line2);border-radius:99px;background:transparent;color:var(--muted);font-size:.85rem}
.chip[aria-pressed=true]{border-color:#c49251;background:#3a2615;color:var(--gold)}
.players{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.player{padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--panel)}
.player header{display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--line)}
.player h3{margin:0;color:var(--gold);font:600 1.05rem Georgia,serif}
.player header small{color:var(--muted);font:600 .72rem system-ui,sans-serif}
.char{display:grid;grid-template-columns:1fr auto;gap:4px 10px;align-items:center;padding:8px 0;border-bottom:1px dashed #2c2016}
.char:last-child{border-bottom:0}
.char .name{font-weight:600}
.char .detail{grid-column:1/-1;color:var(--muted);font-size:.82rem;line-height:1.35}
.char form{display:flex;gap:6px;align-items:center}
.char button{padding:5px 10px;font-size:.8rem}
.char.is-used .name{color:#d9c3a6}
.log{list-style:none;margin:0;padding:0}
.log li{padding:7px 0;border-bottom:1px solid #2c2016;font-size:.9rem;color:#d4c3a6}
.log time{display:inline-block;min-width:62px;color:var(--muted);font:600 .75rem system-ui,sans-serif}
.note{margin-top:10px;color:#8c7b65;font-size:.85rem}
.empty{padding:24px;text-align:center;color:var(--muted)}
[hidden]{display:none!important}
@media(max-width:700px){.settings{grid-template-columns:1fr 1fr}.settings .rule{grid-column:1/-1;order:-1}.players{grid-template-columns:1fr}.hero img{width:64px;height:64px}}
</style>
</head>
<body>
<div class="wrap">
<?php if ($shopId === ''): ?>
    <div class="top"><a href="<?= h($base) ?>mapa/">← Mapa de Waterdeep</a><a href="<?= h($base) ?>cuenta.php">Mi cuenta</a></div>
    <h1>Descuentos de las tiendas</h1>
    <p class="help">Cada personaje tiene el descuento de una tienda <b>una sola vez</b>. Cuando lo usa queda gastado, hasta que tú lo reactives. Entra a una tienda para ver a todos los personajes por jugador.</p>
    <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <div class="shops">
        <?php foreach ($shops as $s):
            $settings = favor_discount_settings($data, $s['id']);
            $usedCount = count(array_intersect_key($data['used'][$s['id']] ?? [], $characters));
            $total = count($characters);
        ?>
            <a class="shop<?= $s['open'] ? '' : ' closed' ?>" href="?tienda=<?= h(rawurlencode($s['id'])) ?>">
                <?php if ($s['image']): ?><img src="<?= h($base . $s['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="ph" aria-hidden="true">✦</span><?php endif; ?>
                <div>
                    <h2><?= h($s['name']) ?></h2>
                    <p class="deal<?= $settings['active'] ? '' : ' none' ?>"><?= h(favor_discount_describe($settings)) ?></p>
                    <div class="counts">
                        <?php if ($settings['active']): ?>
                            <span class="pill ok"><?= $total - $usedCount ?> disponibles</span>
                            <span class="pill used"><?= $usedCount ?> usados</span>
                        <?php else: ?>
                            <span class="pill mute"><?= $s['open'] ? 'Definir descuento' : 'Cerrada en el mapa' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($recent): ?>
        <section class="panel" style="margin-top:22px">
            <h2>Últimos movimientos</h2>
            <ul class="log">
                <?php foreach (array_slice($recent, 0, 15) as $l): ?>
                    <li><time><?= h(when((string)$l['at'])) ?></time> <b><?= h((string)$l['character']) ?></b><?= $l['player'] ? ' (' . h((string)$l['player']) . ')' : '' ?> · <?= h($logLabels[$l['action']] ?? (string)$l['action']) ?> en <?= h($shopNames[$l['shop']] ?? (string)$l['shop']) ?><?= $l['detail'] ? ' · ' . h((string)$l['detail']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php else:
    $s = $shops[$shopId];
    $settings = favor_discount_settings($data, $shopId);
    $used = $data['used'][$shopId] ?? [];
    $usedCount = count(array_intersect_key($used, $characters));
    $inShop = in_array($shopId, FAVOR_DISCOUNT_IN_SHOP, true);
?>
    <div class="top"><a href="descuentos.php">← Todas las tiendas</a><?php if ($s['open']): ?><a href="<?= h($base . $s['url']) ?>">Ir a la tienda →</a><?php endif; ?></div>
    <div class="hero">
        <?php if ($s['image']): ?><img src="<?= h($base . $s['image']) ?>" alt=""><?php endif; ?>
        <div>
            <h1><?= h($s['name']) ?></h1>
            <p class="help" style="margin:0"><?= $inShop
                ? 'El jugador usa el descuento en la tienda al comprar, eligiendo su personaje. Aquí ves quién lo usó, en qué, y lo reactivas.'
                : 'Esta tienda todavía no aplica el descuento sola: márcalo aquí cuando un personaje lo use.' ?></p>
        </div>
    </div>
    <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <form class="panel" method="post">
        <h2>Descuento de la tienda</h2>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="settings">
        <div class="settings">
            <label>Porcentaje<input type="number" name="percent" min="0" max="100" step="1" value="<?= (int)$settings['percent'] ?>"></label>
            <label class="rule">Se aplica…<input type="text" name="rule" maxlength="120" value="<?= h($settings['rule']) ?>" placeholder="en la primera poción"></label>
            <label class="check"><input type="checkbox" name="active"<?= $settings['active'] ? ' checked' : '' ?>> Activo</label>
            <button type="submit">Guardar</button>
        </div>
    </form>

    <div class="tools">
        <input id="search" type="search" placeholder="Buscar personaje o jugador…" autocomplete="off">
        <div class="chips" role="group" aria-label="Mostrar">
            <button class="chip" type="button" data-filter="all" aria-pressed="true">Todos (<?= count($characters) ?>)</button>
            <button class="chip" type="button" data-filter="ok" aria-pressed="false">Disponibles (<?= count($characters) - $usedCount ?>)</button>
            <button class="chip" type="button" data-filter="used" aria-pressed="false">Usados (<?= $usedCount ?>)</button>
        </div>
    </div>

    <div class="players" id="players">
        <?php foreach ($groups as $playerName => $list):
            $playerUsed = count(array_filter($list, static fn(array $c): bool => isset($used[$c['id']])));
        ?>
            <section class="player" data-player="<?= h(mb_strtolower($playerName, 'UTF-8')) ?>">
                <header><h3><?= h($playerName) ?></h3><small><?= count($list) - $playerUsed ?> de <?= count($list) ?> disponibles</small></header>
                <?php foreach ($list as $c): $u = $used[$c['id']] ?? null; ?>
                    <div class="char<?= $u ? ' is-used' : '' ?>" data-name="<?= h(mb_strtolower($c['name'], 'UTF-8')) ?>" data-state="<?= $u ? 'used' : 'ok' ?>">
                        <span class="name"><?= h($c['name']) ?></span>
                        <form method="post"<?= $u ? '' : ' data-ask="1"' ?>>
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="character" value="<?= h($c['id']) ?>"><input type="hidden" name="item" value="">
                            <?php if ($u): ?>
                                <span class="pill used">Usado</span>
                                <button name="action" value="reactivate" type="submit" title="Devolverle el descuento">Reactivar</button>
                            <?php else: ?>
                                <span class="pill ok">Disponible</span>
                                <button class="ghost" name="action" value="mark_used" type="submit" title="Marcar que ya lo usó">Marcar usado</button>
                            <?php endif; ?>
                        </form>
                        <?php if ($u): ?>
                            <span class="detail"><?= h(trim(implode(' · ', array_filter([
                                (string)($u['item'] ?? ''),
                                (string)($u['paid'] ?? '') !== '' ? 'pagó ' . $u['paid'] . ((string)($u['price'] ?? '') !== '' ? ' (antes ' . $u['price'] . ')' : '') : '',
                                when((string)($u['at'] ?? '')),
                            ])))) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <p class="empty" id="noResults" hidden>Ningún personaje coincide.</p>
    <?php if (!$characters): ?><p class="empty">No hay personajes activos.</p><?php endif; ?>

    <?php if ($usedCount): ?>
        <form method="post" onsubmit="return confirm('¿Devolver el descuento a los <?= $usedCount ?> personajes que ya lo usaron?')" style="margin-top:16px">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <button class="ghost" name="action" value="reactivate_all" type="submit">Reactivar a todos</button>
        </form>
    <?php endif; ?>
    <?php if ($shopId === FAVOR_DISCOUNTS_SEED_SHOP && $data['unmatched']): ?>
        <p class="note">De la planilla «Descuento Hecho» no encontré en la Banca a: <?= h(implode(', ', $data['unmatched'])) ?>. Si tienen otro nombre, márcalos a mano.</p>
    <?php endif; ?>
    <?php if ($recent): ?>
        <section class="panel" style="margin-top:22px">
            <h2>Historial de esta tienda</h2>
            <ul class="log">
                <?php foreach (array_slice($recent, 0, 30) as $l): ?>
                    <li><time><?= h(when((string)$l['at'])) ?></time> <b><?= h((string)$l['character']) ?></b><?= $l['player'] ? ' (' . h((string)$l['player']) . ')' : '' ?> · <?= h($logLabels[$l['action']] ?? (string)$l['action']) ?><?= $l['detail'] ? ' · ' . h((string)$l['detail']) : '' ?> <small style="color:#8c7b65">por <?= h((string)$l['by']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>
</div>
<script>
(() => {
const norm=v=>String(v||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().trim();
const q=document.getElementById('search'),chips=[...document.querySelectorAll('.chip')],none=document.getElementById('noResults');
let filter='all';
function apply(){
    const s=norm(q?q.value:'');let total=0;
    document.querySelectorAll('.player').forEach(p=>{
        const byPlayer=!s||norm(p.dataset.player).includes(s);let any=false;
        p.querySelectorAll('.char').forEach(c=>{
            const show=(byPlayer||norm(c.dataset.name).includes(s))&&(filter==='all'||c.dataset.state===filter);
            c.hidden=!show;any=any||show;if(show)total++;
        });
        p.hidden=!any;
    });
    if(none)none.hidden=total>0||!document.querySelector('.player');
}
q&&q.addEventListener('input',apply);
chips.forEach(b=>b.addEventListener('click',()=>{filter=b.dataset.filter;chips.forEach(x=>x.setAttribute('aria-pressed',String(x===b)));apply()}));
// «Marcar usado»: pregunta qué compró (opcional).
document.querySelectorAll('form[data-ask]').forEach(f=>f.addEventListener('submit',e=>{
    const item=prompt('¿En qué lo usó? (opcional)','');
    if(item===null){e.preventDefault();return}
    f.querySelector('[name=item]').value=item;
}));
})();
</script>
</body>
</html>
