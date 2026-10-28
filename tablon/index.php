<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/tablon.php';

favor_session_start();
$user = favor_current_user();
$isAdmin = $user !== null && ($user['role'] ?? '') === 'admin';
$csrf = $isAdmin ? favor_csrf() : '';
favor_session_close();

$missions = [];
$players = [];
$error = '';
if ($user) {
    try {
        $db = favor_db();
        favor_ensure_schema($db);
        tablon_ensure_schema($db);
        $missions = tablon_misiones($db);
        if ($isAdmin) $players = tablon_jugadores($db);
        $db->close();
    } catch (Throwable $e) {
        $error = 'No pude leer el tablón: ' . $e->getMessage();
    }
}

// Solo los datos del tablón, para refrescarlo sin recargar la página.
if ($user && isset($_GET['datos'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $error === '', 'missions' => $missions, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$fonts = [];
foreach (TABLON_LETRAS as $k => [$label, $family]) $fonts[] = ['id' => $k, 'name' => $label, 'family' => $family];
$inks = [];
foreach (TABLON_TINTAS as $k => [$label, $color]) $inks[] = ['id' => $k, 'name' => $label, 'color' => $color];
$sizes = [];
foreach (TABLON_TAMANOS as $k => [$label, $width]) $sizes[] = ['id' => $k, 'name' => $label, 'width' => $width];

$surface = '';
foreach (['webp', 'jpg', 'png'] as $ext) {
    if (is_file(__DIR__ . '/papeles/tablon.' . $ext)) { $surface = favor_static('tablon/papeles/tablon.' . $ext); break; }
}

$boot = [
    'admin' => $isAdmin,
    'csrf' => $csrf,
    'api' => favor_url('tablon/api.php'),
    'data' => favor_url('tablon/?datos=1'),
    'papers' => tablon_papeles(),
    'sizes' => $sizes,
    'fonts' => $fonts,
    'inks' => $inks,
    'missions' => $missions,
    'players' => $players,
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1c1009">
<title>Tablón de Misiones · Favor de Jack</title>
<?php favor_head('Tablón de Misiones · Favor de Jack', 'Las misiones del gremio: publicadas, en curso y terminadas.'); ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amatic+SC:wght@700&family=Caveat:wght@500;700&family=IM+Fell+English:ital@0;1&family=Tangerine:wght@700&display=swap">
<link rel="stylesheet" href="<?= favor_h(favor_static('tablon/tablon.css')) ?>">
<?php if ($user): ?><script src="<?= favor_h(favor_static('tablon/tablon.js')) ?>" defer></script><?php endif; ?>
</head>
<body class="tablon-page<?= $isAdmin ? ' is-admin' : '' ?>">
<?php favor_site_bar(); ?>

<main class="tablon-shell">
    <header class="tablon-head">
        <div class="tablon-sign">
            <span class="tablon-sign-nail" aria-hidden="true"></span>
            <h1>Tablón de Misiones</h1>
            <p>Trabajos, encargos y favores para el gremio</p>
            <span class="tablon-sign-nail" aria-hidden="true"></span>
        </div>
        <?php if ($user): ?>
        <div class="tablon-tools">
            <p class="tablon-counts" id="tablon-counts" aria-live="polite"></p>
            <?php if ($isAdmin): ?>
                <button type="button" class="tablon-btn tablon-btn-main" id="tablon-new">＋ Ingresar misión</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <?php if (!$user): ?>
        <section class="tablon-guest">
            <h2>El tablón es solo para miembros del gremio</h2>
            <p>Entra con tu cuenta para revisar las misiones.</p>
            <a class="tablon-btn tablon-btn-main" href="<?= favor_h(favor_url('login.php?next=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? '')))) ?>">Entrar</a>
        </section>
    <?php else: ?>
        <?php if ($error !== ''): ?><p class="tablon-error" role="alert"><?= favor_h($error) ?></p><?php endif; ?>
        <p class="tablon-hint"><?= $isAdmin ? 'Arrastra los papeles para acomodarlos. Tócalos para iniciar, terminar o eliminar una misión.' : 'Toca un papel para leer la misión completa.' ?><span class="tablon-hint-mobile"> Desliza el tablón hacia los lados para verlo completo.</span></p>
        <div class="tablon-scroll">
            <div class="tablon-board" id="tablon-board">
                <div class="tablon-surface" id="tablon-surface"<?= $surface !== '' ? ' style="background-image:url(\'' . favor_h($surface) . '\')"' : '' ?>>
                    <p class="tablon-empty" id="tablon-empty" hidden><?= $isAdmin ? 'El tablón está vacío. Usa «Ingresar misión» para clavar el primer papel.' : 'Por ahora no hay misiones en el tablón.' ?></p>
                </div>
                <span class="tablon-bolt b1"></span><span class="tablon-bolt b2"></span><span class="tablon-bolt b3"></span><span class="tablon-bolt b4"></span>
            </div>
        </div>
        <p class="tablon-toast" id="tablon-toast" role="status" aria-live="polite" hidden></p>
    <?php endif; ?>
</main>

<?php if ($user): ?>
<!-- Ver una misión -->
<dialog class="tablon-dialog" id="dlg-view" aria-labelledby="view-title">
    <div class="dlg-body dlg-view">
        <div class="view-paper" id="view-paper"></div>
        <div class="view-info">
            <p class="view-state" id="view-state"></p>
            <h2 id="view-title"></h2>
            <p class="view-reward" id="view-reward"></p>
            <div class="view-details" id="view-details"></div>
            <div class="view-party" id="view-party"></div>
            <p class="view-reason" id="view-reason"></p>
            <p class="view-meta" id="view-meta"></p>
            <div class="view-actions" id="view-actions"></div>
        </div>
    </div>
    <button type="button" class="dlg-close" data-close aria-label="Cerrar">×</button>
</dialog>

<?php if ($isAdmin): ?>
<!-- Ingresar / editar misión -->
<dialog class="tablon-dialog dlg-wide" id="dlg-edit" aria-labelledby="edit-title">
    <form class="dlg-body dlg-edit" id="edit-form" novalidate>
        <div class="edit-fields">
            <h2 id="edit-title">Ingresar misión</h2>
            <fieldset class="edit-papers">
                <legend>Papel</legend>
                <div class="paper-choices" id="paper-choices"></div>
            </fieldset>
            <label>Título <span class="req">*</span><input name="name" maxlength="160" required placeholder="Ej.: Lobos en el camino de Bruschk"></label>
            <label>Texto del papel <span class="req">*</span><textarea name="text" rows="5" maxlength="1200" required placeholder="Lo que se lee en el papel clavado."></textarea></label>
            <p class="edit-warn" id="edit-warn" hidden>El texto es largo para este papel y quedará muy chico. Prueba un papel o tamaño más grande, o acórtalo.</p>
            <div class="edit-row">
                <label>Recompensa<input name="reward" maxlength="255" placeholder="Ej.: 200 monedas de oro"></label>
                <label>Tipo<input name="type" maxlength="80" placeholder="Ej.: Caza, Escolta…"></label>
            </div>
            <label>Más detalles <small>(no va en el papel; se ve al abrir la misión)</small><textarea name="details" rows="3" maxlength="20000" placeholder="Quién la encarga, pistas, condiciones…"></textarea></label>
            <div class="edit-row edit-looks">
                <fieldset><legend>Tamaño</legend><div class="seg" id="size-choices"></div></fieldset>
                <fieldset><legend>Letra</legend><div class="seg" id="font-choices"></div></fieldset>
                <fieldset><legend>Tinta</legend><div class="seg" id="ink-choices"></div></fieldset>
            </div>
            <p class="dlg-error" id="edit-error" role="alert" hidden></p>
            <div class="dlg-actions">
                <button type="button" class="tablon-btn" data-close>Cancelar</button>
                <button type="submit" class="tablon-btn tablon-btn-main" id="edit-submit">Clavar en el tablón</button>
            </div>
        </div>
        <div class="edit-preview">
            <p class="edit-preview-label">Así se verá</p>
            <div class="edit-preview-stage" id="edit-preview"></div>
        </div>
    </form>
    <button type="button" class="dlg-close" data-close aria-label="Cerrar">×</button>
</dialog>

<!-- Iniciar misión: armar el grupo -->
<dialog class="tablon-dialog dlg-wide" id="dlg-start" aria-labelledby="start-title">
    <div class="dlg-body dlg-start">
        <h2 id="start-title">Iniciar misión</h2>
        <p class="start-hint">Elige un jugador y arrastra sus personajes al papel de la misión (o tócalos).</p>
        <div class="start-players" id="start-players" role="tablist" aria-label="Jugadores"></div>
        <div class="start-main">
            <div class="start-chars" id="start-chars" aria-live="polite"></div>
            <div class="start-drop" id="start-drop">
                <p class="start-drop-title" id="start-drop-title"></p>
                <div class="start-party" id="start-party"></div>
                <p class="start-drop-empty" id="start-drop-empty">Suelta aquí a los personajes</p>
            </div>
        </div>
        <p class="dlg-error" id="start-error" role="alert" hidden></p>
        <div class="dlg-actions">
            <button type="button" class="tablon-btn" data-close>Cancelar</button>
            <button type="button" class="tablon-btn tablon-btn-main" id="start-submit">Iniciar misión</button>
        </div>
    </div>
    <button type="button" class="dlg-close" data-close aria-label="Cerrar">×</button>
</dialog>

<!-- Confirmar (eliminar, terminar, descolgar) -->
<dialog class="tablon-dialog dlg-small" id="dlg-ask" aria-labelledby="ask-title">
    <form class="dlg-body" id="ask-form">
        <h2 id="ask-title"></h2>
        <p id="ask-text"></p>
        <label id="ask-field" hidden><span id="ask-label"></span><textarea name="note" rows="3" maxlength="500"></textarea></label>
        <p class="dlg-error" id="ask-error" role="alert" hidden></p>
        <div class="dlg-actions">
            <button type="button" class="tablon-btn" data-close>Cancelar</button>
            <button type="submit" class="tablon-btn tablon-btn-main" id="ask-submit"></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script id="tablon-data" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
</body>
</html>
