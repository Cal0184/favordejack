<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib.php';

favor_session_start();
try {
    $user = favor_current_user();
} catch (Throwable $e) {
    $user = null;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
$csrf = $isAdmin ? favor_csrf() : '';
favor_session_close();

$data = cr_load();
// Los eventos en borrador solo los ve el admin.
if (!$isAdmin) $data['events'] = array_values(array_filter($data['events'], static fn(array $e): bool => empty($e['draft'])));

$characters = [];
$charError = '';
try {
    $all = cr_characters(favor_db());
    if ($isAdmin) {
        $characters = $all;
    } else {
        foreach ($data['events'] as $e) {
            foreach ($e['characters'] ?? [] as $c) {
                $cid = (string)($c['id'] ?? '');
                if ($cid !== '' && isset($all[$cid])) $characters[$cid] = $all[$cid];
            }
        }
    }
} catch (Throwable $e) {
    $charError = 'No pude leer los personajes de la Banca.';
}

$base = favor_base_path();
$music = array_values(array_filter($data['music'], static fn(array $m): bool => is_file(cr_root() . '/' . (string)($m['file'] ?? ''))));
$payload = [
    'base' => $base,
    'admin' => $isAdmin,
    'csrf' => $csrf,
    'config' => $data['config'],
    'timelines' => $data['timelines'],
    'events' => $data['events'],
    'music' => $music,
    'characters' => $characters,
    'sheetUrl' => $base . 'archivosdelgremio/hoja.php?id=',
    'sounds' => $base . 'cronicas/sonidos/',
    'uploadMax' => (string)ini_get('upload_max_filesize'),
];
$campaign = (string)($data['config']['campaignName'] ?? 'Crónicas');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#080810">
<title>Crónicas · Favor de Jack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<?php favor_head('Crónicas · Favor de Jack', 'La línea de tiempo de la campaña del Favor de Jack.'); ?>
<link rel="stylesheet" href="assets/cronicas.css<?= favor_v(__DIR__ . '/assets/cronicas.css') ?>">
</head>
<body class="cr-body">
<?php favor_site_bar(); ?>

<div class="cr-app" id="crApp">
    <div id="background-layer" aria-hidden="true"></div>

    <div id="splash-screen" role="button" tabindex="0" aria-label="Entrar a las crónicas">
        <div class="splash-content">
            <div class="splash-ornament" aria-hidden="true">— ✦ —</div>
            <h1 id="splashTitle"><?= htmlspecialchars($campaign, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Toca para entrar al mundo</p>
        </div>
    </div>

    <header class="app-header">
        <div class="app-title">
            <span class="icon" aria-hidden="true">⚔️</span>
            <h1 id="campaignName"<?= $isAdmin ? ' title="Doble clic para cambiar el nombre"' : '' ?>><?= htmlspecialchars($campaign, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="header-actions">
            <?php if ($isAdmin): ?>
                <button class="btn" type="button" id="btn-new-event">✦ Evento</button>
                <button class="btn" type="button" id="btn-new-parallel">⫘ Línea paralela</button>
                <button class="btn" type="button" id="btn-lines">☰ Líneas</button>
                <button class="btn btn-sm" type="button" id="btn-import" title="Traer lo guardado en la aplicación Crónicas">📥 Importar</button>
            <?php endif; ?>
            <label class="search-box">
                <span aria-hidden="true">⌕</span>
                <input type="search" id="eventSearch" placeholder="Buscar evento o personaje…" aria-label="Buscar evento o personaje" autocomplete="off">
            </label>
        </div>
    </header>
    <?php if ($charError !== ''): ?><p class="cr-warning"><?= htmlspecialchars($charError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <div id="timeline-viewport" class="timeline-viewport" tabindex="0" aria-label="Línea de tiempo. Arrastra o usa las flechas para moverte."></div>

    <div class="zoom-controls">
        <button class="btn btn-icon" type="button" id="zoom-in" title="Acercar" aria-label="Acercar">＋</button>
        <div class="zoom-level" id="zoom-level">100%</div>
        <button class="btn btn-icon" type="button" id="zoom-out" title="Alejar" aria-label="Alejar">−</button>
    </div>

    <aside id="event-panel" class="event-panel" aria-label="Detalle del evento" aria-hidden="true">
        <div class="panel-header">
            <div>
                <h2 class="panel-event-name">Evento</h2>
                <div class="panel-event-year">Año 0</div>
                <div class="panel-timeline-badge"><span class="badge-dot"></span><span class="badge-name">Principal</span></div>
            </div>
            <button class="panel-close" type="button" title="Cerrar" aria-label="Cerrar">✕</button>
        </div>
        <?php if ($isAdmin): ?>
            <div class="panel-actions">
                <button class="btn btn-edit-event" type="button">✏️ Editar</button>
                <button class="btn btn-danger btn-delete-event" type="button">🗑 Eliminar</button>
            </div>
        <?php endif; ?>
        <div class="panel-body">
            <div class="panel-section">
                <h4>Resumen</h4>
                <div class="section-content panel-event-summary">Sin descripción.</div>
            </div>
            <div class="panel-section">
                <h4>Personajes</h4>
                <div class="panel-characters"></div>
            </div>
        </div>
    </aside>

    <div class="music-player">
        <div class="player-controls">
            <button class="player-btn" type="button" id="music-play-btn" title="Reproducir / pausar" aria-label="Reproducir o pausar">▶</button>
            <button class="player-btn" type="button" id="music-next-btn" title="Siguiente" aria-label="Siguiente canción">⏭</button>
        </div>
        <div class="player-track-info">
            <span class="track-name" id="music-track-name">Sin música</span>
            <span class="track-status" id="music-track-status">⏸ PAUSADO</span>
        </div>
        <div class="player-volume">
            <button class="volume-icon" type="button" id="music-volume-icon" aria-label="Silenciar">🔉</button>
            <input type="range" class="volume-slider" id="music-volume" min="0" max="1" step="0.05" value="0.4" aria-label="Volumen">
        </div>
        <button class="library-toggle" type="button" id="music-library-toggle" title="Biblioteca de música" aria-label="Biblioteca de música">🎵</button>
    </div>

    <div class="music-library" id="music-library">
        <div class="library-header">
            <h4>Biblioteca musical</h4>
            <?php if ($isAdmin): ?>
                <label class="library-upload-btn">+ Subir<input type="file" accept="audio/*" id="library-upload-input" multiple></label>
            <?php endif; ?>
        </div>
        <div class="library-list" id="music-library-list"></div>
    </div>
</div>

<div id="modal-overlay" class="modal-overlay">
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
            <h3 id="modal-title">Modal</h3>
            <button class="modal-close" type="button" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer" id="modal-footer"></div>
    </div>
</div>
<div class="toast-container" aria-live="polite"></div>

<script>window.CRONICAS = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/cronicas.js<?= favor_v(__DIR__ . '/assets/cronicas.js') ?>" defer></script>
</body>
</html>
