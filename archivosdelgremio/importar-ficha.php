<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/ficha-import.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';

favor_session_start();
$self = favor_base_path() . 'archivosdelgremio/importar-ficha.php';
$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode($self), true, 303);
    exit;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
$csrf = favor_csrf();
// Leer y procesar el PDF tarda: la sesión se suelta ya y solo se reabre un
// instante para apuntar o quitar la subida pendiente.
favor_session_close();

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Imagen de la categoría, la misma que usa el Inventario. */
function category_image(string $category): string
{
    $n = ficha_fold($category);
    $file = str_contains($n, 'arma') ? 'item-arma' : (str_contains($n, 'magic') ? 'item-magico' : (str_contains($n, 'vanidad') ? 'item-vanidad' : 'item-utilidad'));
    return favor_base_path() . 'inventario/assets/items/' . $file . '.webp';
}

function ability_mod(?int $score): string
{
    if ($score === null) return '';
    $m = (int)floor(($score - 10) / 2);
    return $m >= 0 ? '+' . $m : (string)$m;
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
            $entry = ['user' => (int)$user['id'], 'file' => (string)$file['name']];
            favor_session_write(static function () use ($token, $entry): void {
                $list = array_slice((array)($_SESSION['ficha_import'] ?? []), -4, null, true);
                $list[$token] = $entry;
                $_SESSION['ficha_import'] = $list;
            });
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
                    // En el expediente, cada dato trae "sheet" (usar la ficha) o "keep" (dejar lo actual).
                    'fields' => array_keys(array_filter((array)($_POST['field'] ?? []), static fn($v) => $v === 'sheet')),
                    'items' => array_map('intval', (array)($_POST['items'] ?? [])),
                    'itemActions' => array_map('strval', (array)($_POST['itemAction'] ?? [])),
                ], $path);
                favor_session_write(static function () use ($token): void {
                    $list = (array)($_SESSION['ficha_import'] ?? []);
                    unset($list[$token]);
                    $_SESSION['ficha_import'] = $list;
                });
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
$base = favor_base_path();
$stepNo = ['upload' => 1, 'preview' => 2, 'done' => 3][$step] ?? 1;
$groups = ['nuevo' => [], 'distinto' => [], 'igual' => [], 'conjuro' => [], 'dinero' => []];
foreach ($plan['items'] ?? [] as $it) $groups[$it['status']][] = $it;
$fieldsNew = array_filter($plan['fields'] ?? [], static fn($f) => $f['isNew']);
$fieldsDiff = array_filter($plan['fields'] ?? [], static fn($f) => !$f['isNew']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#061813">
    <title>Importar ficha · Favor de Jack</title>
    <link rel="icon" href="<?= h($base) ?>favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="ficha.css?v=<?= (int)@filemtime(__DIR__ . '/ficha.css') ?>">
    <style>:root{--bg-image:url("<?= h($base) ?>inventario/assets/ui/inventory-bg.webp");--paper-image:url("<?= h($base) ?>inventario/assets/ui/inventory-parchment.webp")}</style>
<?php favor_head('Importar ficha · Archivos del Gremio'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<header class="fx-top">
    <nav>
        <a class="fx-btn hide-sm" href="<?= h($base) ?>inventario/">Inventario</a>
    </nav>
</header>

<main class="fx-wrap">
    <ol class="fx-steps" aria-label="Pasos">
        <?php foreach ([1 => 'Subir ficha', 2 => 'Revisar cambios', 3 => 'Listo'] as $n => $label): ?>
            <li class="<?= $n === $stepNo ? 'on' : ($n < $stepNo ? 'done' : '') ?>"<?= $n === $stepNo ? ' aria-current="step"' : '' ?>><i><?= $n < $stepNo ? '✓' : $n ?></i><span><?= h($label) ?></span></li>
        <?php endforeach; ?>
    </ol>

    <?php if ($error !== ''): ?><div class="fx-alert" role="alert"><span aria-hidden="true">⚠</span><div><?= h($error) ?></div></div><?php endif; ?>

<?php if ($step === 'upload'): ?>
    <form class="fx-card" method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="fx-head"><span class="ico" aria-hidden="true">📜</span><h2>Sube la ficha del personaje</h2></div>
        <p class="fx-lead">La web lee la ficha y te muestra qué cambiaría en el Archivo y el Inventario. No se guarda nada hasta que lo confirmes.</p>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="step" value="preview">
        <input type="hidden" name="character" value="<?= h($selectedId) ?>">
        <label class="fx-drop" id="drop">
            <span class="seal" aria-hidden="true">📜</span>
            <strong>Arrastra aquí el PDF o pulsa para elegirlo</strong>
            <span class="fx-muted">Ficha oficial de D&amp;D 5e rellenable · hasta 15 MB</span>
            <span class="file" id="fileName"></span>
            <input type="file" name="ficha" id="fileInput" accept="application/pdf,.pdf" required>
        </label>
        <div class="fx-tips">
            <div class="fx-tip"><b>Archivo</b>Clase, nivel, raza, características e historia.</div>
            <div class="fx-tip"><b>Inventario</b>Armas, armadura y equipo. Los conjuros y el dinero no se cuentan como objetos.</div>
            <div class="fx-tip"><b>Hoja</b>CA, PG, habilidades, rasgos y conjuros, para verlos en el Archivo.</div>
        </div>
        <div class="fx-actionbar">
            <p>Las fichas escaneadas o escritas a mano no se pueden leer.</p>
            <div class="btns"><button class="fx-btn fx-btn--primary" type="submit">Leer ficha</button></div>
        </div>
    </form>
    <script>
        (function () {
            var drop = document.getElementById('drop'), input = document.getElementById('fileInput'), out = document.getElementById('fileName');
            function show() { out.textContent = input.files && input.files[0] ? '✓ ' + input.files[0].name : ''; }
            input.addEventListener('change', show);
            ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('drag'); }); });
            ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('drag'); }); });
            drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
        })();
    </script>

<?php elseif ($step === 'done' && $result): ?>
    <div class="fx-card">
        <div class="fx-head"><span class="ico" aria-hidden="true">✦</span><h2>Ficha guardada</h2></div>
        <p class="fx-lead">Esto es lo que se actualizó:</p>
        <ul class="fx-done">
            <?php foreach ($result['summary'] as $line): ?><li><?= h($line) ?></li><?php endforeach; ?>
        </ul>
        <div class="fx-links">
            <a class="fx-btn fx-btn--primary" href="<?= h($base) ?>archivosdelgremio/?character=<?= h(rawurlencode($result['id'])) ?>">Ver en el Archivo</a>
            <a class="fx-btn" href="<?= h($base) ?>archivosdelgremio/hoja.php?id=<?= h(rawurlencode($result['id'])) ?>">📜 Ver hoja</a>
            <a class="fx-btn" href="<?= h($base) ?>inventario/?character=<?= h(rawurlencode($result['id'])) ?>">▣ Inventario</a>
            <a class="fx-btn fx-btn--ghost" href="<?= h($self) ?>">Importar otra ficha</a>
        </div>
    </div>

<?php elseif ($step === 'preview' && $plan): ?>
    <form method="post" id="importForm">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <input type="hidden" name="step" value="apply" id="stepField">

        <section class="fx-card">
            <div class="fx-hero">
                <div>
                    <span class="fx-eyebrow">Ficha leída</span>
                    <h1><?= h($parsed['name'] ?: 'Sin nombre') ?></h1>
                    <div class="sub"><?= h(implode(' · ', array_filter([
                        trim(($parsed['profile']['Clase'] ?? '') . (($parsed['profile']['Nivel'] ?? null) ? ' nivel ' . $parsed['profile']['Nivel'] : '')),
                        $parsed['profile']['Raza'] ?? '', $sheet['trasfondo'] ?? '', $sheet['alineamiento'] ?? '',
                    ]))) ?></div>
                    <div class="fx-pills">
                        <?php foreach (['ca' => 'CA', 'pgMax' => 'PG', 'velocidad' => 'Velocidad', 'iniciativa' => 'Iniciativa'] as $k => $label): ?>
                            <?php if (($sheet[$k] ?? '') !== ''): ?><span class="fx-pill gold"><?= h($label) ?> <?= h($sheet[$k]) ?></span><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($parsed['playerName'] !== ''): ?><span class="fx-pill mute">Jugador en la ficha: <?= h($parsed['playerName']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="fx-stats">
                <?php foreach (['Fuerza' => 'FUE', 'Destreza' => 'DES', 'Constitucion' => 'CON', 'Inteligencia' => 'INT', 'Sabiduria' => 'SAB', 'Carisma' => 'CAR'] as $col => $abbr): $v = $parsed['profile'][$col] ?? null; ?>
                    <div class="fx-stat"><b><?= $abbr ?></b><span><?= $v === null ? '—' : (int)$v ?></span><small><?= h(ability_mod($v)) ?></small></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="fx-card">
            <div class="fx-head"><span class="ico" aria-hidden="true">⚑</span><h2>¿De quién es esta ficha?</h2></div>
            <p class="fx-lead">Elige si la ficha actualiza a un personaje que ya está en la web o si crea uno nuevo.</p>
            <div class="fx-choices">
                <label class="fx-choice">
                    <span class="t"><input type="radio" name="target" value="existing" <?= $target === 'existing' ? 'checked' : '' ?> <?= $characters ? '' : 'disabled' ?>> Actualizar un personaje</span>
                    <p>Se comparan sus datos con la ficha y tú decides qué cambia.</p>
                    <select class="fx-select" name="characterId" data-replan aria-label="Personaje a actualizar">
                        <option value="">— Elige un personaje —</option>
                        <?php foreach ($characters as $c): ?>
                            <option value="<?= h($c['ID']) ?>" <?= $c['ID'] === $selectedId ? 'selected' : '' ?>><?= h(($c['JugadorNombre'] ?? $c['Jugador']) . ' · ' . $c['Nombre'] . ((int)$c['Activo'] === 1 ? '' : ' (inactivo)')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="fx-choice">
                    <span class="t"><input type="radio" name="target" value="new" <?= $target === 'new' ? 'checked' : '' ?>> Crear a <?= h($parsed['name'] ?: 'un personaje nuevo') ?></span>
                    <p>Nace en la Banca con 0 monedas, con su expediente y su inventario.</p>
                    <select class="fx-select" name="playerId" aria-label="Jugador del personaje nuevo">
                        <?php foreach ($players as $p): ?>
                            <option value="<?= h($p['ID']) ?>" <?= $p['ID'] === $selectedPlayer ? 'selected' : '' ?>>Jugador: <?= h($p['Nombre'] . ((int)$p['Activo'] === 1 ? '' : ' (inactivo)')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <noscript><p><button type="submit" class="fx-btn" onclick="document.getElementById('stepField').value='preview'">Ver cambios para esta elección</button></p></noscript>
        </section>

        <section class="fx-card">
            <div class="fx-head"><span class="ico" aria-hidden="true">✒</span><h2>Expediente del Archivo</h2></div>
            <?php if ($target === 'new'): ?>
                <p class="fx-lead">Es un personaje nuevo: su expediente se escribe con estos datos de la ficha.</p>
                <div class="fx-rows">
                    <?php foreach ($plan['fields'] as $f): ?>
                        <div class="fx-row"><div class="lbl"><?= h($f['label']) ?></div><div class="fx-new"><span class="v fx-text"><?= h(mb_strimwidth($f['new'], 0, 400, '…', 'UTF-8')) ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$plan['fields']): ?>
                <p class="fx-lead">Todo coincide: el expediente ya tiene los mismos datos que la ficha. No se cambiará nada.</p>
            <?php else: ?>
                <p class="fx-lead">Nada se pisa sin que lo elijas. En cada dato marca qué versión quieres conservar; los datos que no aparecen aquí ya coinciden y no se tocan.</p>
                <div class="fx-summary">
                    <?php if ($fieldsNew): ?><span class="fx-pill"><?= count($fieldsNew) ?> dato(s) que faltan en el Archivo</span><?php endif; ?>
                    <?php if ($fieldsDiff): ?><span class="fx-pill warn"><?= count($fieldsDiff) ?> dato(s) distintos</span><?php endif; ?>
                </div>
                <div class="fx-rows">
                    <?php foreach (array_merge($fieldsNew, $fieldsDiff) as $f): $long = mb_strlen($f['old']) > 160 || mb_strlen($f['new']) > 160; ?>
                        <fieldset class="fx-row" style="border:1px solid var(--line);margin:0">
                            <legend class="sr-only"><?= h($f['label']) ?></legend>
                            <div class="lbl"><?= h($f['label']) ?><br><span class="fx-pill <?= $f['isNew'] ? '' : 'warn' ?>"><?= $f['isNew'] ? 'Falta en el Archivo' : 'Distinto' ?></span></div>
                            <div class="fx-opts">
                                <label class="fx-opt<?= $long ? ' long' : '' ?>">
                                    <input type="radio" name="field[<?= h($f['col']) ?>]" value="keep" <?= $f['useSheet'] ? '' : 'checked' ?>>
                                    <span class="k"><?= $f['isNew'] ? 'Dejar vacío' : 'Conservar lo del Archivo' ?></span>
                                    <span class="v<?= $f['old'] === '' ? ' empty' : '' ?>"><?= $f['old'] === '' ? '(sin dato)' : h(mb_strimwidth($f['old'], 0, 600, '…', 'UTF-8')) ?></span>
                                </label>
                                <label class="fx-opt<?= $long ? ' long' : '' ?>">
                                    <input type="radio" name="field[<?= h($f['col']) ?>]" value="sheet" <?= $f['useSheet'] ? 'checked' : '' ?>>
                                    <span class="k"><?= $f['isNew'] ? 'Añadir de la ficha' : 'Usar lo de la ficha' ?></span>
                                    <span class="v"><?= h(mb_strimwidth($f['new'], 0, 600, '…', 'UTF-8')) ?></span>
                                </label>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="fx-card">
            <div class="fx-head"><span class="ico" aria-hidden="true">▣</span><h2>Inventario</h2></div>
            <p class="fx-lead">Nunca se borra nada del inventario. Revisa lo que se añadirá; lo que no es un objeto queda aparte.</p>

            <?php if ($groups['nuevo']): ?>
                <div class="fx-group">
                    <h3>Se añadirán <span class="n"><?= count($groups['nuevo']) ?></span></h3>
                    <div class="fx-items">
                        <?php foreach ($groups['nuevo'] as $it): ?>
                            <label class="fx-item">
                                <input type="checkbox" name="items[]" value="<?= (int)$it['index'] ?>" checked aria-label="Añadir <?= h($it['name']) ?>">
                                <img src="<?= h(category_image($it['category'])) ?>" alt="" loading="lazy">
                                <span><span class="nm"><?= h($it['name']) ?></span><?php if ($it['description'] !== ''): ?><span class="ds"><?= h($it['description']) ?></span><?php endif; ?></span>
                                <span class="meta"><span class="fx-qty">×<?= (int)$it['quantity'] ?></span><span class="fx-pill mute"><?= h($it['category']) ?></span><?php if ($it['equipped']): ?><span class="fx-pill">Equipado</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($groups['distinto']): ?>
                <div class="fx-group">
                    <h3>Ya los tiene, pero con diferencias <span class="n"><?= count($groups['distinto']) ?></span></h3>
                    <div class="fx-items">
                        <?php foreach ($groups['distinto'] as $it): ?>
                            <div class="fx-item conflict">
                                <span aria-hidden="true">⚖</span>
                                <img src="<?= h(category_image($it['category'])) ?>" alt="" loading="lazy">
                                <span class="nm"><?= h($it['name']) ?></span>
                                <span class="meta"><span class="fx-pill warn">¿Qué hacemos?</span></span>
                                <div class="fx-diffs">
                                    <?php foreach ($it['diffs'] as $d): ?><div><?= h($d['label']) ?>: en el inventario <s><?= h($d['old'] !== '' ? $d['old'] : '(vacío)') ?></s>, en la ficha <strong><?= h($d['new']) ?></strong></div><?php endforeach; ?>
                                    <div class="fx-seg" role="radiogroup" aria-label="Qué hacer con <?= h($it['name']) ?>">
                                        <label><input type="radio" name="itemAction[<?= (int)$it['index'] ?>]" value="keep" checked> Dejarlo como está</label>
                                        <label><input type="radio" name="itemAction[<?= (int)$it['index'] ?>]" value="update"> Actualizar con la ficha</label>
                                        <label><input type="radio" name="itemAction[<?= (int)$it['index'] ?>]" value="add"> Añadir otro aparte</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($groups['igual']): ?>
                <details class="fx-more">
                    <summary>Ya están en el inventario, iguales (<?= count($groups['igual']) ?>): no se tocan</summary>
                    <div class="inner fx-chips"><?php foreach ($groups['igual'] as $it): ?><span class="fx-chip"><?= h($it['name']) ?></span><?php endforeach; ?></div>
                </details>
            <?php endif; ?>

            <?php if ($groups['conjuro'] || $groups['dinero']): ?>
                <details class="fx-more">
                    <summary>No son objetos (<?= count($groups['conjuro']) + count($groups['dinero']) ?>): no van al inventario</summary>
                    <div class="inner">
                        <?php if ($groups['conjuro']): ?>
                            <p class="fx-muted">Estos parecen <strong>conjuros</strong>: se guardan en la Hoja del personaje. Si alguno sí es un objeto (un pergamino, por ejemplo), márcalo.</p>
                            <div class="fx-items">
                                <?php foreach ($groups['conjuro'] as $it): ?>
                                    <label class="fx-item off">
                                        <input type="checkbox" name="items[]" value="<?= (int)$it['index'] ?>" aria-label="Añadir <?= h($it['name']) ?> como objeto">
                                        <img src="<?= h(category_image('Objeto mágico')) ?>" alt="" loading="lazy">
                                        <span class="nm"><?= h($it['name']) ?></span>
                                        <span class="meta"><span class="fx-pill">Conjuro</span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($groups['dinero']): ?>
                            <p class="fx-muted">Esto es <strong>dinero</strong>: no se toca, cada jugador lo lleva en la Banca Digital.</p>
                            <div class="fx-chips"><?php foreach ($groups['dinero'] as $it): ?><span class="fx-chip"><?= h($it['name']) ?></span><?php endforeach; ?></div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!$plan['items']): ?><p class="fx-muted">La ficha no trae objetos.</p><?php endif; ?>
        </section>

        <section class="fx-card">
            <div class="fx-head"><span class="ico" aria-hidden="true">📜</span><h2>Hoja del personaje</h2></div>
            <p class="fx-lead">Todo lo demás queda guardado y se ve en el Archivo con el botón «Hoja»:</p>
            <div class="fx-chips">
                <?php foreach (['salvaciones' => 'Salvaciones', 'habilidades' => 'Habilidades', 'ataques' => 'Ataques', 'rasgos' => 'Rasgos', 'personalidad' => 'Personalidad', 'competenciasIdiomas' => 'Idiomas y competencias'] as $k => $label): ?>
                    <?php if (!empty($sheet[$k])): ?><span class="fx-chip"><?= h($label) ?></span><?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($sheet['conjuros']['lista'])): ?><span class="fx-chip">Conjuros <small><?= count($sheet['conjuros']['lista']) ?></small></span><?php endif; ?>
                <span class="fx-chip">PDF original</span>
            </div>
            <p class="fx-muted" style="margin-top:12px">El dinero de la ficha no se toca: cada jugador lo lleva en la Banca Digital.</p>
        </section>

        <div class="fx-actionbar">
            <p id="actionSummary">Revisa y confirma. Puedes volver a importar la ficha cuando quieras.</p>
            <div class="btns">
                <a class="fx-btn fx-btn--ghost" href="<?= h($self) ?>">Cancelar</a>
                <button class="fx-btn fx-btn--primary" type="submit">Guardar ficha</button>
            </div>
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
            function count() {
                var n = form.querySelectorAll('input[name="items[]"]:checked').length;
                var f = form.querySelectorAll('input[name^="field["][value=sheet]:checked').length;
                var parts = [];
                if (form.querySelector('input[name=target][value=new]:checked')) parts.push('el personaje nuevo con su expediente');
                if (f) parts.push(f + ' dato(s) del expediente');
                if (n) parts.push(n + ' objeto(s) nuevos');
                document.getElementById('actionSummary').textContent = parts.length ? 'Se guardarán ' + parts.join(' y ') + ', más la Hoja.' : 'Solo se guardará la Hoja del personaje.';
            }
            form.addEventListener('change', count); count();
            form.addEventListener('submit', function (ev) {
                if (document.getElementById('stepField').value !== 'apply') return;
                var t = form.querySelector('input[name=target]:checked');
                if (t && t.value === 'existing' && !form.querySelector('[name=characterId]').value) {
                    ev.preventDefault();
                    alert('Elige el personaje que quieres actualizar o marca «Crear».');
                }
            });
        })();
    </script>
<?php endif; ?>
</main>
</body>
</html>
