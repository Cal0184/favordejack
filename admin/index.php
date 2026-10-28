<?php
declare(strict_types=1);

/*
 * Panel del DM: todo lo del máster en una página. Solo administradores.
 *  - Pedidos por aprobar (aprobar = cobra en la Banca y pasa al Inventario)
 *  - Dar o quitar oro a varios personajes a la vez
 *  - Actividad reciente de la Banca y avisos enviados a Discord
 *  - Configuración de los avisos en Discord
 */
require_once dirname(__DIR__) . '/tiendas/emporio/lib.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/estado.php';
require_once dirname(__DIR__) . '/includes/clave.php';

favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_url('login.php?next=' . rawurlencode(favor_url('admin/'))), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Esta página es solo para el DM (administradores).');
}
$csrf = favor_csrf();
favor_session_close();
$by = (string)($user['displayName'] ?? $user['username']);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'order') {
            $state = (string)($_POST['state'] ?? '');
            if (!in_array($state, ['aprobado', 'rechazado'], true)) throw new RuntimeException('Estado no válido.');
            $reply = mb_substr(trim((string)($_POST['reply'] ?? '')), 0, 500, 'UTF-8');
            [$order, $note] = emporio_order_set_state((string)($_POST['id'] ?? ''), $state, $reply, emporio_final_price($_POST['final'] ?? ''), $user);
            $notice = 'Pedido de ' . ($order['characterName'] ?? '') . ' ' . $state . '. ' . $note;
        } elseif ($action === 'gold') {
            $ids = array_values(array_filter(array_map('strval', (array)($_POST['chars'] ?? [])), 'strlen'));
            if (!$ids) throw new RuntimeException('Marca al menos un personaje.');
            $copper = max(0, (int)($_POST['po'] ?? 0)) * 100 + max(0, (int)($_POST['pl'] ?? 0)) * 10 + max(0, (int)($_POST['pc'] ?? 0));
            if ($copper <= 0) throw new RuntimeException('Escribe cuánto oro.');
            if ($copper > 100000000) throw new RuntimeException('Esa cantidad es demasiado grande.');
            $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 200, 'UTF-8');
            if ($reason === '') throw new RuntimeException('Escribe el motivo (por ejemplo, «Recompensa de la misión del puerto»). Lo verán en su historial de la Banca.');
            $give = ($_POST['mode'] ?? 'give') !== 'take';
            $all = favor_discount_characters();
            $ids = array_values(array_filter($ids, static fn(string $id): bool => isset($all[$id])));
            $results = favor_bank_adjust($ids, $copper, $give, $reason, $by);
            $ok = array_keys(array_filter($results, static fn(string $r): bool => $r === ''));
            $fails = array_filter($results, static fn(string $r): bool => $r !== '');
            $names = static fn(array $list): string => implode(', ', array_map(static fn(string $id): string => $all[$id]['name'] ?? $id, $list));
            if ($ok) $notice = ($give ? 'Dado ' : 'Quitado ') . favor_copper_label($copper) . ($give ? ' a ' : ' de ') . $names($ok) . '.';
            foreach ($fails as $id => $why) $error .= ($all[$id]['name'] ?? $id) . ': ' . $why . ' ';
            $error = trim($error);
        } elseif ($action === 'estado') {
            $db = favor_db();
            try {
                $estado = favor_estado_normalizar((string)($_POST['estado'] ?? ''));
                $note = favor_estado_aplicar($db, (string)($_POST['id'] ?? ''), $estado);
            } finally {
                $db->close();
            }
            $notice = trim('Estado puesto en «' . $estado . '» en la Banca y en Archivos. ' . $note);
        } elseif ($action === 'discord_save' || $action === 'discord_test') {
            $webhooks = [];
            foreach (array_merge(['general'], array_keys(FAVOR_DISCORD_EVENTS)) as $k) {
                $webhooks[$k] = favor_discord_clean_url((string)($_POST['webhook'][$k] ?? ''));
            }
            $events = [];
            foreach (array_keys(FAVOR_DISCORD_EVENTS) as $k) $events[$k] = isset($_POST['event'][$k]);
            $invite = favor_discord_clean_invite((string)($_POST['invite'] ?? ''));
            favor_discord_update(static function (array $c) use ($webhooks, $events, $invite): array {
                $c['invite'] = $invite;
                $c['webhooks'] = $webhooks;
                $c['events'] = $events;
                return $c;
            });
            $notice = 'Configuración de Discord guardada.';
            if ($action === 'discord_test') {
                $sent = 0;
                $urls = array_unique(array_filter($webhooks));
                if (!$urls) throw new RuntimeException('Guardado. Para probar avisos, pega primero el enlace del webhook.');
                foreach ($urls as $url) {
                    $fail = favor_discord_send('prueba', '✨ Aviso de prueba', 'Si ves esto, la web de Favor de Jack ya puede avisar en este canal.', [['Enviado por', $by]], $url);
                    if ($fail !== '') throw new RuntimeException('Discord no aceptó el aviso: ' . $fail);
                    $sent++;
                }
                $notice = 'Guardado y enviado ' . ($sent === 1 ? 'un aviso de prueba' : $sent . ' avisos de prueba') . '. Míralo en Discord.';
            }
        }
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Algo falló en la base de datos.';
        if (!$e instanceof RuntimeException) error_log('Panel del DM: ' . $e->getMessage());
    }
}

// Datos para la página.
$orders = array_values(array_filter(emporio_orders(), static fn(array $o): bool => ($o['state'] ?? '') === 'pendiente'));
usort($orders, static fn(array $a, array $b): int => strcmp((string)($a['created'] ?? ''), (string)($b['created'] ?? '')));
$gold = [];
foreach (emporio_characters($user) as $c) $gold[$c['id']] = $c['goldExact'];
try { $characters = favor_discount_characters(); } catch (Throwable) { $characters = []; }
$byPlayer = [];
foreach ($characters as $c) $byPlayer[$c['playerName']][] = $c;
try { $recent = favor_bank_recent(12); } catch (Throwable) { $recent = []; }

// Misiones recientes con su grupo, para repartir la recompensa de un clic.
$missions = [];
try {
    $db = favor_db();
    $rows = $db->query("SELECT ID, Nombre, Estado, RecompensaGeneral FROM Mision WHERE Estado IN ('En curso','Completada') ORDER BY Actualizado DESC, ID DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    $stmt = $db->prepare('SELECT PersonajeID, Recompensa FROM MisionPersonaje WHERE MisionID = ?');
    foreach ($rows as $r) {
        $id = (int)$r['ID'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $party = array_values(array_filter($stmt->get_result()->fetch_all(MYSQLI_ASSOC), static fn(array $p): bool => isset($characters[(string)$p['PersonajeID']])));
        if (!$party) continue;
        $missions[] = [
            'name' => (string)$r['Nombre'],
            'state' => (string)$r['Estado'],
            'reward' => (string)$r['RecompensaGeneral'],
            'chars' => array_map(static fn(array $p): string => (string)$p['PersonajeID'], $party),
        ];
    }
    $stmt->close();
    $db->close();
} catch (Throwable) {
    $missions = [];
}
$discord = favor_discord_config();
// Jugadores que pidieron una contraseña temporal (se dan en Mi cuenta).
try {
    $db = favor_db();
    $resets = count(favor_reset_pending($db));
    $db->close();
} catch (Throwable) {
    $resets = 0;
}
// Personajes cuyo estado no coincide entre la Banca y Archivos (una fila por pareja).
$mismatch = [];
try {
    $db = favor_db();
    $all = favor_estado_todos($db);
    $db->close();
    $seen = [];
    foreach ($all as $c) {
        if ($c['coincide'] || isset($seen[$c['id']])) continue;
        foreach (array_merge([$c['id']], $c['gemelos']) as $g) $seen[$g] = true;
        $owner = $c['especial'] ? '' : $c['jugadorNombre'];
        foreach ($c['gemelos'] as $g) if ($owner === '' && !$all[$g]['especial']) $owner = $all[$g]['jugadorNombre'];
        $mismatch[] = $c + ['dueno' => $owner];
    }
} catch (Throwable) {
    $mismatch = [];
}
$discordReady = (string)$discord['webhooks']['general'] !== '' || array_filter(array_slice($discord['webhooks'], 1));

function po_label(float $po): string
{
    return number_format($po, fmod($po, 1) ? 2 : 0, ',', '.') . ' po';
}
$typeLabels = ['add' => 'Ingreso', 'spend' => 'Gasto', 'exchange' => 'Cambio', 'initial' => 'Saldo inicial', 'correction' => 'Corrección'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Panel del DM · Favor de Jack</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;color:#eadcb9;font:17px/1.5 Georgia,'EB Garamond',serif;background:#120c08 radial-gradient(circle at 50% 0,#2b1b10,#120c08 70%)}
main{max-width:1100px;margin:0 auto;padding:24px 16px 80px}
a{color:#e8c785}
h1{margin:.2em 0 .1em;font:600 1.9rem Georgia,serif;color:#f0cf8a}
h2{margin:0 0 .5em;font-size:1.25rem;color:#e8c785;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
h3{margin:0;font-size:1.05rem;color:#f0cf8a}
.sub{margin:0 0 18px;color:#bba98a}
.shortcuts{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 20px}
.shortcuts a{padding:7px 13px;border:1px solid #6b4d2c;border-radius:99px;background:#1b130d;text-decoration:none;font-size:.92rem}
.shortcuts a:hover{background:#2a1c11}
.card{margin:0 0 18px;padding:18px;border:1px solid #6b4d2c;border-radius:12px;background:#1b130dcc;animation:rise .45s ease both}
.card:nth-of-type(2){animation-delay:.06s}.card:nth-of-type(3){animation-delay:.12s}.card:nth-of-type(4){animation-delay:.18s}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.card{animation:none}}
.badge{display:inline-grid;place-items:center;min-width:24px;height:24px;padding:0 7px;border-radius:99px;background:#c49251;color:#1a1009;font:800 .78rem system-ui,sans-serif}
.note{font-size:.92rem;color:#bba98a;margin:.2em 0 .8em}
.msg{padding:12px 14px;border-radius:9px;margin:0 0 18px}
.ok{border:1px solid #5d7a3e;background:#1d2a12;color:#d6efb7}
.err{border:1px solid #8e4a44;background:#2a1412;color:#efbbb5}
button{min-height:44px;padding:8px 16px;border:1px solid #a87b3f;border-radius:8px;background:#3a2414;color:#f3d79f;font:inherit;cursor:pointer}
button:hover{background:#4a2e18}
button.good{border-color:#6d8f45;background:#24331a;color:#d6efb7}
button.danger{border-color:#9b4a40;background:#3a1714;color:#f2c1b8}
input[type=text],input[type=url],input[type=number],textarea{width:100%;min-height:42px;padding:8px;border:1px solid #5b4329;border-radius:8px;background:#0b0705;color:#fff;font:inherit}
textarea{min-height:60px;resize:vertical}
label{display:grid;gap:4px;color:#cbb894;font-size:.92rem}
.order{display:grid;grid-template-columns:1fr minmax(240px,320px);gap:14px;padding:14px 0;border-top:1px solid #3d2c1d}
.order:first-of-type{border-top:0}
.order ul{margin:6px 0;padding-left:20px}
.muted{color:#a99677;font-size:.88rem}
.warn{color:#f0b48a;font-weight:700}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.actions button{flex:1}
.coins{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.mode{display:flex;gap:16px;flex-wrap:wrap;margin:8px 0}
.mode label,.check{display:flex;align-items:center;gap:8px;color:#eadcb9;font-size:1rem}
.mode input,.check input{width:20px;height:20px;accent-color:#c49251}
.players{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin:10px 0}
.player{padding:10px;border:1px solid #3d2c1d;border-radius:9px;background:#140e0a}
.player strong{display:flex;justify-content:space-between;gap:8px;color:#e0b26a;font-size:.95rem}
.player strong button{min-height:28px;padding:2px 9px;font-size:.75rem}
.player .check{padding:3px 0;font-size:.95rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
table{width:100%;border-collapse:collapse;font-size:.9rem}
td{padding:6px 4px;border-top:1px solid #33251a;vertical-align:top}
.plus{color:#b8d39a;white-space:nowrap}.minus{color:#e5a69d;white-space:nowrap}
.hooks{display:grid;gap:10px}
details{margin:10px 0}
summary{cursor:pointer;color:#e0b26a}
ol{padding-left:22px}
.empty{padding:16px;border:1px dashed #5a4128;border-radius:9px;text-align:center;color:#a99677}
@media (max-width:760px){.order,.grid2{grid-template-columns:1fr}}
</style>
<?php favor_head('Panel del DM · Favor de Jack'); ?>
</head>
<body class="favor-plain">
<?php favor_site_bar(); ?><div class="favor-pad">
<main>
    <h1>Panel del DM</h1>
    <p class="sub">Aprueba compras, reparte oro y controla los avisos de Discord desde un solo sitio.</p>
    <nav class="shortcuts" aria-label="Atajos del DM">
        <a href="<?= e(favor_url('tiendas/emporio/admin.php?vista=pedidos')) ?>">Todos los pedidos</a>
        <a href="<?= e(favor_url('tiendas/emporio/admin.php')) ?>">Cartas del Emporio</a>
        <a href="<?= e(favor_url('tiendas/descuentos.php')) ?>">Descuentos</a>
        <a href="<?= e(favor_url('bancadigital/')) ?>">Banca</a>
        <?php if (is_file(dirname(__DIR__) . '/tablon/index.php')): ?><a href="<?= e(favor_url('tablon/')) ?>">Tablón de misiones</a><?php endif; ?>
        <a href="<?= e(favor_url('archivosdelgremio/')) ?>">Misiones (Archivos)</a>
        <a href="<?= e(favor_url('timetoplay/')) ?>">Wiki del DM</a>
        <a href="<?= e(favor_url('admin/respaldo.php')) ?>">Respaldo</a>
    </nav>

    <?php if ($resets): ?><div class="msg ok" role="status">🔑 <?= $resets === 1 ? 'Un jugador olvidó su contraseña' : $resets . ' jugadores olvidaron su contraseña' ?>. <a href="<?= e(favor_url('cuenta.php')) ?>">Dale una temporal en Mi cuenta</a>.</div><?php endif; ?>
    <?php if ($notice): ?><div class="msg ok" role="status"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err" role="alert"><?= e($error) ?></div><?php endif; ?>

    <section class="card" id="pedidos">
        <h2>Pedidos por aprobar <?php if ($orders): ?><span class="badge"><?= count($orders) ?></span><?php endif; ?></h2>
        <p class="note">Al <b>aprobar</b>, el precio se cobra solo en la Banca y los objetos entran al Inventario del personaje. Si no le alcanza, el pedido no se aprueba y te lo digo.</p>
        <?php if (!$orders): ?><div class="empty">No hay pedidos pendientes. 🎉</div><?php endif; ?>
        <?php foreach ($orders as $o): $p = emporio_order_public($o); $has = $gold[(string)($o['characterId'] ?? '')] ?? null; ?>
            <article class="order">
                <div>
                    <h3><?= e($p['character']) ?> <span class="muted">· <?= e($p['player']) ?> · Emporio de Ecix's</span></h3>
                    <p class="muted"><?= e(date('d-m-Y H:i', strtotime($p['created']) ?: time())) ?></p>
                    <ul><?php foreach ($p['items'] as $i): ?><li><?= (int)($i['qty'] ?? 1) > 1 ? (int)$i['qty'] . ' × ' : '' ?><?= e((string)($i['name'] ?? '')) ?> <span class="muted"><?= e((string)($i['price'] ?? '')) ?></span></li><?php endforeach; ?></ul>
                    <?php if ($p['discount']): ?><p class="muted">✦ Descuento <?= (int)$p['discount']['percent'] ?> % en <?= e((string)$p['discount']['item']) ?></p><?php endif; ?>
                    <p>
                        <b><?= $p['total'] !== null ? 'Total: ' . e(po_label((float)$p['total'])) : 'Hay objetos sin precio' ?></b>
                        <?php if ($has !== null): ?> · tiene <?= e(po_label((float)$has)) ?><?php if ($p['total'] !== null && $has < (float)$p['total']): ?> <span class="warn">· no le alcanza</span><?php endif; ?><?php endif; ?>
                    </p>
                    <?php if ($p['note'] !== ''): ?><p class="muted">Mensaje: <?= e($p['note']) ?></p><?php endif; ?>
                </div>
                <form method="post" action="#pedidos">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="order"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
                    <label>Precio final (po)<input type="text" name="final" inputmode="decimal" placeholder="<?= $p['total'] !== null ? e((string)$p['total']) . ' (vacío = este)' : 'Obligatorio' ?>"></label>
                    <label>Respuesta de Ecix's<textarea name="reply" maxlength="500" placeholder="Opcional, el jugador la ve en «Mis pedidos»"></textarea></label>
                    <div class="actions" style="margin-top:8px">
                        <button class="good" type="submit" name="state" value="aprobado">Aprobar y cobrar</button>
                        <button class="danger" type="submit" name="state" value="rechazado">Rechazar</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="card" id="oro">
        <h2>Dar o quitar oro</h2>
        <p class="note">Para recompensas de misión, multas o correcciones. Cada personaje marcado recibe (o pierde) la misma cantidad y lo ve en su historial de la Banca con el motivo.</p>
        <form method="post" action="#oro" onsubmit="return confirm('¿Aplicar este movimiento a los personajes marcados?')">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="gold">
            <div class="mode" role="radiogroup" aria-label="Dar o quitar">
                <label><input type="radio" name="mode" value="give" checked> Dar</label>
                <label><input type="radio" name="mode" value="take"> Quitar</label>
            </div>
            <div class="coins">
                <label>Oro (po)<input type="number" name="po" min="0" step="1" inputmode="numeric" placeholder="0"></label>
                <label>Plata (pl)<input type="number" name="pl" min="0" step="1" inputmode="numeric" placeholder="0"></label>
                <label>Cobre (pc)<input type="number" name="pc" min="0" step="1" inputmode="numeric" placeholder="0"></label>
            </div>
            <?php if ($missions): ?>
                <label style="margin-top:8px">Repartir la recompensa de una misión (marca a su grupo)
                    <select id="mission-pick" style="min-height:42px;padding:8px;border:1px solid #5b4329;border-radius:8px;background:#0b0705;color:#fff;font:inherit">
                        <option value="">Elige una misión…</option>
                        <?php foreach ($missions as $m): ?>
                            <option data-chars="<?= e(json_encode($m['chars'])) ?>" data-reason="<?= e('Recompensa: ' . $m['name']) ?>" data-reward="<?= e($m['reward']) ?>"><?= e($m['name']) ?> · <?= e($m['state']) ?> · <?= count($m['chars']) ?> personajes</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="note" id="mission-reward" hidden></p>
            <?php endif; ?>
            <label style="margin-top:8px">Motivo<input type="text" name="reason" maxlength="200" required placeholder="Recompensa de la misión…"></label>
            <div class="players">
                <?php foreach ($byPlayer as $player => $list): ?>
                    <div class="player">
                        <strong><?= e($player) ?> <button type="button" data-all>Todos</button></strong>
                        <?php foreach ($list as $c): ?>
                            <label class="check"><input type="checkbox" name="chars[]" value="<?= e($c['id']) ?>"> <?= e($c['name']) ?> <span class="muted"><?= isset($gold[$c['id']]) ? e(po_label((float)$gold[$c['id']])) : '' ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit">Aplicar</button>
        </form>
    </section>

    <?php if ($mismatch): ?>
    <section class="card" id="estados">
        <h2>Estados que no coinciden <span class="badge"><?= count($mismatch) ?></span></h2>
        <p class="note">Estos personajes tienen un estado en la Banca y otro en Archivos. Elige el correcto y se pone igual en las dos (y por tanto en Inventario y Relaciones). Desde ahora, cambiarlo en Archivos lo cambia también en la Banca.</p>
        <?php foreach ($mismatch as $m): ?>
            <form method="post" action="#estados" class="order" style="grid-template-columns:1fr auto;align-items:center">
                <div>
                    <h3><?= e($m['nombre']) ?> <span class="muted">· <?= e($m['dueno'] ?: 'jugador desconocido') ?></span></h3>
                    <p class="muted">Banca: <b><?= e($m['estadoBanca'] ?: 'archivado') ?></b> · Archivos: <b><?= e($m['estadoArchivos']) ?></b></p>
                </div>
                <div class="actions">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="estado"><input type="hidden" name="id" value="<?= e($m['id']) ?>">
                    <?php foreach (array_unique(array_filter([$m['estadoArchivos'], $m['estadoBanca'] ?: 'Retirado'])) as $opt): ?>
                        <button type="submit" name="estado" value="<?= e($opt) ?>">Dejar «<?= e($opt) ?>»</button>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <div class="grid2">
        <section class="card" id="actividad">
            <h2>Últimos movimientos de la Banca</h2>
            <?php if (!$recent): ?><div class="empty">Sin movimientos.</div><?php else: ?>
            <table>
                <?php foreach ($recent as $m): ?>
                    <tr>
                        <td><b><?= e($m['character']) ?></b><br><span class="muted"><?= e($m['reason'] !== '' ? $m['reason'] : ($typeLabels[$m['type']] ?? $m['type'])) ?> · <?= e($m['author']) ?></span></td>
                        <td class="<?= $m['copper'] >= 0 ? 'plus' : 'minus' ?>"><?= $m['copper'] >= 0 ? '+' : '−' ?><?= e(favor_copper_label(abs($m['copper']))) ?><br><span class="muted"><?= e(date('d-m H:i', (strtotime($m['at'] . ' UTC') ?: time()))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </section>

        <section class="card" id="avisos">
            <h2>Últimos avisos a Discord</h2>
            <p class="note">Compras aprobadas, misiones y tiradas críticas que la web mandó.</p>
            <?php if (!$discord['log']): ?><div class="empty"><?= $discordReady ? 'Todavía no se ha mandado ninguno.' : 'Discord aún no está conectado (abajo).' ?></div><?php else: ?>
            <table>
                <?php foreach (array_slice($discord['log'], 0, 15) as $l): ?>
                    <tr>
                        <td><?= e((string)$l['title']) ?><?php if (empty($l['ok'])): ?><br><span class="warn"><?= e((string)$l['error']) ?></span><?php endif; ?></td>
                        <td class="muted"><?= e(date('d-m H:i', strtotime((string)$l['at']) ?: time())) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <section class="card" id="discord">
        <h2>Avisos en Discord <?= $discordReady ? '<span class="badge" style="background:#6d8f45">conectado</span>' : '' ?></h2>
        <details<?= $discordReady ? '' : ' open' ?>>
            <summary>Cómo conseguir el enlace del webhook (una sola vez)</summary>
            <ol>
                <li>En Discord, pasa el ratón por el canal donde quieres los avisos y pulsa la rueda ⚙️ (<b>Editar canal</b>).</li>
                <li>Ve a <b>Integraciones</b> → <b>Webhooks</b> → <b>Nuevo webhook</b>.</li>
                <li>Ponle de nombre «Favor de Jack» y pulsa <b>Copiar URL del webhook</b>.</li>
                <li>Pégala abajo en «Canal general», pulsa <b>Guardar y probar</b> y mira el canal.</li>
            </ol>
            <p class="note">Ese enlace es como una llave del canal: no lo compartas. Solo se guarda en el servidor.</p>
        </details>
        <form method="post" action="#discord" class="hooks">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label>Invitación a vuestro Discord (el botón «Háblame por Discord» de la web lleva aquí)<input type="url" name="invite" value="<?= e((string)$discord['invite']) ?>" placeholder="https://discord.gg/…" autocomplete="off"></label>
            <label>Canal general (todos los avisos van aquí)<input type="url" name="webhook[general]" value="<?= e((string)$discord['webhooks']['general']) ?>" placeholder="https://discord.com/api/webhooks/…" autocomplete="off"></label>
            <?php foreach (FAVOR_DISCORD_EVENTS as $k => $label): ?>
                <div class="player">
                    <label class="check"><input type="checkbox" name="event[<?= e($k) ?>]" <?= !empty($discord['events'][$k]) ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <label class="muted">Otro canal solo para esto (opcional)<input type="url" name="webhook[<?= e($k) ?>]" value="<?= e((string)$discord['webhooks'][$k]) ?>" placeholder="Vacío = canal general" autocomplete="off"></label>
                </div>
            <?php endforeach; ?>
            <div class="actions">
                <button type="submit" name="action" value="discord_save">Guardar</button>
                <button class="good" type="submit" name="action" value="discord_test">Guardar y probar</button>
            </div>
        </form>
    </section>
</main>
</div>
<script>
const pick = document.getElementById('mission-pick');
if (pick) pick.addEventListener('change', () => {
    const opt = pick.selectedOptions[0];
    const hint = document.getElementById('mission-reward');
    if (!opt || !opt.dataset.chars) { hint.hidden = true; return; }
    const ids = JSON.parse(opt.dataset.chars);
    document.querySelectorAll('input[name="chars[]"]').forEach(b => { b.checked = ids.includes(b.value); });
    document.querySelector('input[name=reason]').value = opt.dataset.reason;
    document.querySelector('input[name=mode][value=give]').checked = true;
    hint.textContent = opt.dataset.reward ? 'Recompensa anotada en la misión: ' + opt.dataset.reward + '. Escribe arriba cuánto le toca a cada uno.' : 'La misión no tiene recompensa anotada: escribe arriba cuánto le toca a cada uno.';
    hint.hidden = false;
});
document.querySelectorAll('[data-all]').forEach(btn => btn.addEventListener('click', () => {
    const boxes = btn.closest('.player').querySelectorAll('input[type=checkbox]');
    const all = Array.from(boxes).every(b => b.checked);
    boxes.forEach(b => { b.checked = !all; });
}));
</script>
</body>
</html>
