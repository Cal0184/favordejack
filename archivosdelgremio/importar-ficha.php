<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/ficha-import.php';

favor_session_start();
$self = favor_base_path() . 'archivosdelgremio/importar-ficha.php';
$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode($self), true, 303);
    exit;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
$csrf = favor_csrf();

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$step = 'upload';
$error = '';
$parsed = null;
$plan = null;
$result = null;
$token = '';
$characters = [];
$players = [];
$target = 'existing';
$selectedId = (string)($_GET['character'] ?? '');
$selectedPlayer = (string)($user['playerId'] ?? '');

try {
    $db = favor_db();
    favor_ensure_schema($db);
    ficha_ensure_schema($db);
    ficha_cleanup_pending();
    $characters = ficha_editable_characters($db, $user);
    $players = ficha_players($db, $user);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $action = (string)($_POST['step'] ?? '');
        $pending = $_SESSION['ficha_import'] ?? [];

        if ($action === 'preview' && isset($_FILES['ficha']) && ($_FILES['ficha']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['ficha'];
            $code = (int)$file['error'];
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El PDF pesa más de lo que el servidor permite subir.');
            if ($code !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('No se pudo subir el archivo (código ' . $code . ').');
            if ((int)$file['size'] > FICHA_MAX_BYTES) throw new RuntimeException('El PDF debe pesar menos de 15 MB.');
            $bytes = (string)file_get_contents((string)$file['tmp_name']);
            $parsed = ficha_interpret(FichaPdfReader::fields($bytes));
            $token = bin2hex(random_bytes(16));
            if (!@move_uploaded_file((string)$file['tmp_name'], ficha_storage_dir() . '/tmp-' . $token . '.pdf')) {
                throw new RuntimeException('El servidor no pudo guardar el PDF temporalmente.');
            }
            $pending = array_slice($pending, -4, null, true);
            $pending[$token] = ['user' => (int)$user['id'], 'file' => (string)$file['name']];
            $_SESSION['ficha_import'] = $pending;
            $guess = ficha_guess_character($characters, (string)$parsed['name']);
            if ($selectedId === '' || !in_array($selectedId, array_column($characters, 'ID'), true)) $selectedId = (string)($_POST['character'] ?? '');
            if ($selectedId === '' || !in_array($selectedId, array_column($characters, 'ID'), true)) $selectedId = (string)$guess;
            $target = $selectedId !== '' ? 'existing' : 'new';
            $step = 'preview';
        } elseif ($action === 'preview' || $action === 'apply') {
            $token = (string)($_POST['token'] ?? '');
            $path = ficha_storage_dir() . '/tmp-' . preg_replace('~[^a-f0-9]~', '', $token) . '.pdf';
            if (!isset($pending[$token]) || (int)$pending[$token]['user'] !== (int)$user['id'] || !is_file($path)) {
                throw new RuntimeException('La subida caducó. Vuelve a elegir el PDF.');
            }
            $parsed = ficha_interpret(FichaPdfReader::fields((string)file_get_contents($path)));
            $target = ($_POST['target'] ?? '') === 'new' ? 'new' : 'existing';
            $selectedId = (string)($_POST['characterId'] ?? '');
            $selectedPlayer = (string)($_POST['playerId'] ?? $selectedPlayer);
            $step = 'preview';

            if ($action === 'apply') {
                if ($target === 'existing' && !in_array($selectedId, array_column($characters, 'ID'), true)) {
                    throw new RuntimeException('Elige el personaje que quieres actualizar.');
                }
                $result = ficha_apply($db, $user, $parsed, [
                    'target' => $target,
                    'characterId' => $selectedId,
                    'playerId' => $selectedPlayer,
                    'fields' => array_map('strval', (array)($_POST['fields'] ?? [])),
                    'items' => array_map('intval', (array)($_POST['items'] ?? [])),
                    'itemActions' => array_map('strval', (array)($_POST['itemAction'] ?? [])),
                ], $path);
                unset($pending[$token]);
                $_SESSION['ficha_import'] = $pending;
                $step = 'done';
            }
        } else {
            throw new RuntimeException('Elige un PDF para subir.');
        }
    }

    if ($step === 'preview') {
        $plan = ficha_plan($db, $parsed, $target === 'existing' && $selectedId !== '' ? $selectedId : null);
    }
    $db->close();
} catch (Throwable $e) {
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Ocurrió un error inesperado. Inténtalo otra vez.';
    if (!$e instanceof RuntimeException) error_log('importar-ficha: ' . $e);
    if ($step === 'preview' && $plan === null && $parsed !== null && isset($db)) {
        try { $plan = ficha_plan($db, $parsed, $target === 'existing' && $selectedId !== '' ? $selectedId : null); } catch (Throwable) { $step = 'upload'; }
    } elseif ($step !== 'preview') {
        $step = 'upload';
    }
}

$sheet = $parsed['sheet'] ?? [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2b1a10">
    <title>Importar ficha · Archivos del Gremio</title>
    <link rel="icon" href="<?= favor_base_path() ?>favicon.ico">
    <style>
        :root { --ink:#2b1a10; --paper:#f3e6c8; --paper2:#e9d6ac; --gold:#b8862b; --muted:#6b5338; --line:#c9ad78; --ok:#2f6b3a; --bad:#9b2b22; }
        * { box-sizing:border-box; }
        body { margin:0; background:#1d120b; color:var(--ink); font:16px/1.5 Georgia, "Times New Roman", serif; }
        a { color:#7a4b12; }
        .wrap { max-width:980px; margin:0 auto; padding:24px 16px 64px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; color:#e8d3a4; margin-bottom:16px; }
        .top a { color:#e8d3a4; }
        h1 { margin:0; font-size:1.6rem; letter-spacing:.02em; }
        .card { background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:20px; margin-bottom:16px; box-shadow:0 6px 24px rgba(0,0,0,.35); }
        h2 { margin:0 0 12px; font-size:1.2rem; border-bottom:1px solid var(--line); padding-bottom:6px; }
        .muted { color:var(--muted); font-size:.92rem; }
        .error { background:#f6d7d2; border:1px solid var(--bad); color:var(--bad); padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .success { background:#dcebd6; border:1px solid var(--ok); color:var(--ok); padding:12px 14px; border-radius:8px; }
        .drop { display:block; border:2px dashed var(--line); border-radius:10px; padding:28px; text-align:center; background:var(--paper2); cursor:pointer; }
        .drop input { display:block; margin:12px auto 0; max-width:100%; }
        button, .btn { font:inherit; background:var(--ink); color:#f3e6c8; border:0; border-radius:8px; padding:10px 18px; cursor:pointer; text-decoration:none; display:inline-block; }
        button.secondary, .btn.secondary { background:transparent; color:var(--ink); border:1px solid var(--ink); }
        table { width:100%; border-collapse:collapse; font-size:.95rem; }
        th, td { text-align:left; padding:7px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); }
        td.old { color:var(--muted); text-decoration:line-through; }
        td { overflow-wrap:anywhere; }
        td.check { width:34px; }
        .pill { display:inline-block; font-size:.75rem; padding:1px 8px; border-radius:99px; border:1px solid var(--line); background:var(--paper2); white-space:nowrap; }
        .pill.exists { color:var(--muted); }
        tr.conflict td { background:#f7e3b5; }
        .diffs { margin-top:6px; font-size:.9rem; }
        .diffs select { margin-top:4px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:8px 16px; }
        .grid div { background:var(--paper2); border-radius:8px; padding:8px 10px; }
        .grid b { display:block; font-size:.75rem; text-transform:uppercase; color:var(--muted); letter-spacing:.05em; }
        .target label { display:block; margin:6px 0; }
        select { font:inherit; padding:6px; max-width:100%; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .scroll { overflow-x:auto; }
        @media (max-width:600px) { .hide-sm { display:none; } th, td { padding:6px 4px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>📜 Importar ficha de personaje</h1>
        <a href="<?= favor_base_path() ?>archivosdelgremio/">← Archivos del Gremio</a>
    </div>

    <?php if ($error !== ''): ?><div class="error" role="alert"><?= h($error) ?></div><?php endif; ?>

    <?php if ($step === 'upload'): ?>
        <form class="card" method="post" enctype="multipart/form-data">
            <h2>1. Sube la ficha en PDF</h2>
            <p>Sirve la ficha oficial de D&amp;D 5e <strong>rellenable</strong> (en español o inglés). La web lee sus campos y te muestra qué cambiaría antes de guardar nada.</p>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="step" value="preview">
            <input type="hidden" name="character" value="<?= h($selectedId) ?>">
            <label class="drop">
                <strong>Elige el archivo PDF</strong><br>
                <span class="muted">Máximo 15 MB. Una ficha escaneada o hecha a mano no se puede leer.</span>
                <input type="file" name="ficha" accept="application/pdf,.pdf" required>
            </label>
            <p class="actions" style="margin-top:16px"><button type="submit">Leer ficha</button></p>
        </form>

    <?php elseif ($step === 'done' && $result): ?>
        <div class="card">
            <h2>Ficha importada</h2>
            <div class="success">
                <?php foreach ($result['summary'] as $line): ?><div>✓ <?= h($line) ?></div><?php endforeach; ?>
            </div>
            <p class="actions" style="margin-top:16px">
                <a class="btn" href="<?= favor_base_path() ?>archivosdelgremio/?character=<?= h(rawurlencode($result['id'])) ?>">Ver en el Archivo</a>
                <a class="btn secondary" href="<?= favor_base_path() ?>archivosdelgremio/hoja.php?id=<?= h(rawurlencode($result['id'])) ?>">Ver hoja</a>
                <a class="btn secondary" href="<?= favor_base_path() ?>inventario/?character=<?= h(rawurlencode($result['id'])) ?>">Inventario</a>
                <a class="btn secondary" href="<?= favor_base_path() ?>bancadigital/?character=<?= h(rawurlencode($result['id'])) ?>">Banca</a>
                <a href="<?= h($self) ?>">Importar otra</a>
            </p>
        </div>

    <?php elseif ($step === 'preview' && $plan): ?>
        <form method="post" id="importForm">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="step" value="apply" id="stepField">

            <div class="card">
                <h2>2. Ficha leída: <?= h($parsed['name'] ?: 'sin nombre') ?></h2>
                <div class="grid">
                    <div><b>Clase y nivel</b><?= h($sheet['claseNivel'] ?? '—') ?></div>
                    <div><b>Raza</b><?= h($parsed['profile']['Raza'] ?: '—') ?></div>
                    <div><b>Trasfondo</b><?= h($sheet['trasfondo'] ?? '—') ?></div>
                    <div><b>Alineamiento</b><?= h($sheet['alineamiento'] ?? '—') ?></div>
                    <div><b>CA · PG</b><?= h(($sheet['ca'] ?? '—') . ' · ' . ($sheet['pgMax'] ?? '—')) ?></div>
                    <div><b>Jugador en la ficha</b><?= h($parsed['playerName'] ?: '—') ?></div>
                </div>
            </div>

            <div class="card target">
                <h2>3. ¿A qué personaje se aplica?</h2>
                <label><input type="radio" name="target" value="existing" <?= $target === 'existing' ? 'checked' : '' ?> <?= $characters ? '' : 'disabled' ?>> Actualizar un personaje existente:</label>
                <select name="characterId" data-replan>
                    <option value="">— Elige —</option>
                    <?php foreach ($characters as $c): ?>
                        <option value="<?= h($c['ID']) ?>" <?= $c['ID'] === $selectedId ? 'selected' : '' ?>><?= h(($c['JugadorNombre'] ?? $c['Jugador']) . ' · ' . $c['Nombre'] . ((int)$c['Activo'] === 1 ? '' : ' (inactivo)')) ?></option>
                    <?php endforeach; ?>
                </select>
                <label><input type="radio" name="target" value="new" <?= $target === 'new' ? 'checked' : '' ?>> Crear un personaje nuevo llamado <strong><?= h($parsed['name']) ?></strong> para el jugador:</label>
                <select name="playerId">
                    <?php foreach ($players as $p): ?>
                        <option value="<?= h($p['ID']) ?>" <?= $p['ID'] === $selectedPlayer ? 'selected' : '' ?>><?= h($p['Nombre'] . ((int)$p['Activo'] === 1 ? '' : ' (inactivo)')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($parsed['playerName'] !== ''): ?><p class="muted">En la ficha dice jugador "<?= h($parsed['playerName']) ?>". Elige el jugador correcto de la lista.</p><?php endif; ?>
                <noscript><p><button type="submit" class="secondary" onclick="document.getElementById('stepField').value='preview'">Recalcular cambios</button></p></noscript>
            </div>

            <div class="card">
                <h2>Archivo (expediente)</h2>
                <?php if (!$plan['fields']): ?>
                    <p class="muted">Nada que cambiar: el expediente ya coincide con la ficha.</p>
                <?php else: ?>
                    <?php if ($target === 'new'): ?><p class="muted">Personaje nuevo: se guardan todos estos datos.</p><?php endif; ?>
                    <div class="scroll"><table>
                        <tr><th></th><th>Dato</th><th class="hide-sm">Ahora</th><th>Ficha</th></tr>
                        <?php foreach ($plan['fields'] as $f): ?>
                            <tr>
                                <td class="check"><input type="checkbox" name="fields[]" value="<?= h($f['col']) ?>" <?= $f['checked'] || $target === 'new' ? 'checked' : '' ?> <?= $target === 'new' ? 'disabled' : '' ?> aria-label="Actualizar <?= h($f['label']) ?>"></td>
                                <td><?= h($f['label']) ?></td>
                                <td class="old hide-sm"><?= h(mb_strimwidth($f['old'], 0, 120, '…', 'UTF-8')) ?></td>
                                <td><?= nl2br(h(mb_strimwidth($f['new'], 0, 300, '…', 'UTF-8'))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table></div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Inventario</h2>
                <?php if (!$plan['items']): ?>
                    <p class="muted">La ficha no trae objetos.</p>
                <?php else: ?>
                    <?php $conflicts = array_filter($plan['items'], static fn($it) => $it['status'] === 'distinto'); ?>
                    <p class="muted">Nunca se borra nada. Los objetos nuevos se añaden; los que ya están iguales se dejan como están.</p>
                    <?php if ($conflicts): ?><p><strong><?= count($conflicts) ?> objeto(s) ya existen con diferencias:</strong> elige qué hacer con cada uno.</p><?php endif; ?>
                    <div class="scroll"><table>
                        <tr><th></th><th>Objeto</th><th>Cant.</th><th class="hide-sm">Categoría</th><th>De</th></tr>
                        <?php foreach ($plan['items'] as $it): ?>
                            <tr<?= $it['status'] === 'distinto' ? ' class="conflict"' : '' ?>>
                                <td class="check">
                                    <?php if ($it['status'] === 'nuevo'): ?><input type="checkbox" name="items[]" value="<?= (int)$it['index'] ?>" checked aria-label="Añadir <?= h($it['name']) ?>">
                                    <?php elseif ($it['status'] === 'igual'): ?><input type="checkbox" disabled aria-label="<?= h($it['name']) ?> ya está"><?php endif; ?>
                                </td>
                                <td><?= h($it['name']) ?><?php if ($it['description'] !== ''): ?><br><span class="muted"><?= h($it['description']) ?></span><?php endif; ?>
                                    <?php if ($it['status'] === 'igual'): ?> <span class="pill exists">ya está</span><?php endif; ?>
                                    <?php if ($it['equipped']): ?> <span class="pill">equipado</span><?php endif; ?>
                                    <?php if ($it['status'] === 'distinto'): ?>
                                        <div class="diffs">
                                            <span class="pill">ya existe</span>
                                            <?php foreach ($it['diffs'] as $d): ?><div><?= h($d['label']) ?>: <s><?= h($d['old'] !== '' ? $d['old'] : '(vacío)') ?></s> → <strong><?= h($d['new']) ?></strong></div><?php endforeach; ?>
                                            <select name="itemAction[<?= (int)$it['index'] ?>]" aria-label="Qué hacer con <?= h($it['name']) ?>">
                                                <option value="keep" selected>Dejar como está</option>
                                                <option value="update">Actualizar con la ficha</option>
                                                <option value="add">Añadir otro aparte</option>
                                            </select>
                                        </div>
                                    <?php endif; ?></td>
                                <td><?= (int)$it['quantity'] ?></td>
                                <td class="hide-sm"><?= h($it['category']) ?></td>
                                <td><span class="pill"><?= h($it['source']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table></div>
                    <p class="muted">Los nombres salen del texto de la ficha; luego puedes editarlos en el Inventario.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Hoja completa</h2>
                <p class="muted">CA, PG, salvaciones, habilidades, ataques, rasgos, personalidad y conjuros se guardan y se ven en el Archivo con el botón "Hoja". También se guarda una copia del PDF. El dinero de la ficha no se toca: se lleva en la Banca Digital.</p>
                <p class="actions">
                    <button type="submit">Importar ficha</button>
                    <a class="btn secondary" href="<?= h($self) ?>">Cancelar</a>
                </p>
            </div>
        </form>
        <script>
            (function () {
                var form = document.getElementById('importForm');
                function replan() { document.getElementById('stepField').value = 'preview'; form.submit(); }
                form.querySelectorAll('input[name=target]').forEach(function (el) { el.addEventListener('change', replan); });
                form.querySelector('[data-replan]').addEventListener('change', function () {
                    form.querySelector('input[name=target][value=existing]').checked = true;
                    replan();
                });
                form.addEventListener('submit', function (ev) {
                    if (document.getElementById('stepField').value !== 'apply') return;
                    var t = form.querySelector('input[name=target]:checked');
                    if (t && t.value === 'existing' && !form.querySelector('[name=characterId]').value) {
                        ev.preventDefault();
                        alert('Elige el personaje que quieres actualizar o marca "Crear un personaje nuevo".');
                    }
                });
            })();
        </script>
    <?php endif; ?>
</div>
</body>
</html>
