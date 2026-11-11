<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth-widget.php';
favor_session_start();
favor_session_close();

$shopsPath = __DIR__ . '/tiendas.json';
$allShops = [];
$dataError = null;

if (is_file($shopsPath)) {
    $decoded = json_decode((string)file_get_contents($shopsPath), true);
    if (is_array($decoded)) {
        $allShops = $decoded;
    } else {
        $dataError = 'No fue posible leer tiendas.json.';
    }
} else {
    $dataError = 'No se encontró tiendas.json.';
}

$shops = array_values(array_filter($allShops, static function ($shop): bool {
    return is_array($shop)
        && !empty($shop['enabled'])
        && !empty($shop['url'])
        && isset($shop['x'], $shop['y']);
}));

$pendingCount = count(array_filter($allShops, static function ($shop): bool {
    return is_array($shop) && empty($shop['enabled']);
}));

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Las rutas de tiendas.json son relativas a la raíz de la web («tiendas/emporio/»). */
function shopUrl(string $value): string {
    if ($value === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $value)) return $value;
    return favor_url($value);
}

function firstChar(string $value): string {
    if ($value === '') return '?';
    return function_exists('mb_substr') ? mb_substr($value, 0, 1, 'UTF-8') : substr($value, 0, 1);
}

function markerClass(array $shop): string {
    $x = (float)($shop['x'] ?? 50);
    $y = (float)($shop['y'] ?? 50);
    $classes = ['shop-marker'];
    if ($x >= 69) $classes[] = 'open-left';
    if ($y <= 18) $classes[] = 'open-down';
    if (!empty($shop['theme'])) $classes[] = 'theme-' . preg_replace('/[^a-z0-9_-]/i', '', (string)$shop['theme']);
    return implode(' ', $classes);
}

/**
 * Icono medieval de cada comercio. Se puede fijar con «icon» en tiendas.json
 * (swords, tome, anvil, potion, wand, guild); si no, sale del tema de la tienda.
 */
function shopIcon(array $shop): string {
    $icons = ['swords', 'tome', 'anvil', 'potion', 'wand', 'guild'];
    $icon = (string)($shop['icon'] ?? '');
    if (in_array($icon, $icons, true)) return $icon;
    $byTheme = ['blood' => 'swords', 'emporium' => 'tome', 'forge' => 'anvil', 'potion' => 'potion', 'blackmarket' => 'wand'];
    return $byTheme[(string)($shop['theme'] ?? '')] ?? 'guild';
}

function iconSvg(array $shop, string $class = 'ico'): string {
    return '<svg class="' . h($class) . '" aria-hidden="true" focusable="false"><use href="#ico-' . shopIcon($shop) . '"/></svg>';
}

function sellerNames(array $shop): array {
    $names = [];
    foreach (($shop['sellers'] ?? []) as $seller) {
        if (is_array($seller) && !empty($seller['name'])) $names[] = (string)$seller['name'];
    }
    return $names;
}

$isAdmin = (($GLOBALS['__favor_auth_widget_user'] ?? null)['role'] ?? '') === 'admin';
$roll20 = 'https://app.roll20.net/campaigns/details/20586133/-';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#17100b">
<title>Mapa Comercial de Waterdeep · Favor de Jack</title>
<meta name="description" content="Mapa interactivo de las tiendas digitalizadas de Waterdeep.">
<link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
<?php favor_head('Mapa comercial de Waterdeep · Favor de Jack', 'Las tiendas de Waterdeep para los aventureros del Favor de Jack.'); ?>
</head>
<body class="map-page">
<?php favor_site_bar(); ?>
<svg class="icon-sprite" aria-hidden="true" focusable="false">
    <symbol id="ico-swords" viewBox="0 0 24 24"><path d="M4 4l10.5 10.5M20 4L9.5 14.5M4 4v3l9 9M20 4v3l-9 9M7 13l4 4M17 13l-4 4M5.5 18.5l2-2M18.5 18.5l-2-2M4 20l2-2M20 20l-2-2"/></symbol>
    <symbol id="ico-tome" viewBox="0 0 24 24"><path d="M5 4.5h11.5a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2zM5 18a2 2 0 0 1 2-2h11.5"/><path d="M12 6.8l.9 2.1 2.2.2-1.7 1.4.6 2.2-2-1.2-2 1.2.6-2.2-1.7-1.4 2.2-.2z"/></symbol>
    <symbol id="ico-anvil" viewBox="0 0 24 24"><path d="M3 8h13.5c0 2.6 2 3.6 4.5 3.6V13c-3 0-4.6 1.4-5 3H8c-.6-2-2.2-3-4.2-3.6zM9 16l-1.5 4h9L15 16M6 8V5.5M6 5.5h3"/></symbol>
    <symbol id="ico-potion" viewBox="0 0 24 24"><path d="M9.5 3h5M10.5 3v5.2L6 15.4A3.8 3.8 0 0 0 9.3 21h5.4a3.8 3.8 0 0 0 3.3-5.6l-4.5-7.2V3M7.6 14h8.8"/></symbol>
    <symbol id="ico-wand" viewBox="0 0 24 24"><path d="M4 20L15 9M13.5 7.5l3 3M18 2.5v3M16.5 4h3M20.5 8v2.4M19.3 9.2h2.4M11 3.5v2M10 4.5h2"/></symbol>
    <symbol id="ico-guild" viewBox="0 0 24 24"><path d="M12 3l7.5 2.6v5.6c0 4.6-3.2 8-7.5 9.8-4.3-1.8-7.5-5.2-7.5-9.8V5.6zM12 7.5v9M8.5 11h7"/></symbol>
    <symbol id="ico-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l5 5"/></symbol>
</svg>

<main class="map-shell">
    <header class="map-header">
        <div class="map-title">
            <h1>Waterdeep</h1>
            <p>Mapa comercial del gremio</p>
        </div>
        <div class="header-actions">
            <span class="header-count"><b><?= count($shops) ?></b> de <?= count($allShops) ?> tiendas abiertas</span>
            <?php if ($isAdmin): ?><a class="head-link" href="<?= favor_base_path() ?>tiendas/descuentos.php">Descuentos</a><?php endif; ?>
            <a class="head-link" href="<?= h($roll20) ?>" target="_blank" rel="noopener noreferrer" title="Sede del Gremio: abrir la campaña en Roll20">Roll20 <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <?php if ($dataError): ?>
        <div class="map-error" role="alert"><?= h($dataError) ?></div>
    <?php endif; ?>

    <div class="map-layout">
        <aside class="directory" id="directory" aria-label="Directorio de tiendas">
            <div class="directory-head">
                <h2>Tiendas disponibles</h2>
                <button class="directory-toggle" type="button" id="directoryToggle" aria-expanded="false" aria-controls="directoryBody">
                    <span class="toggle-open">Ver lista</span><span class="toggle-close">Ocultar</span>
                    <b><?= count($shops) ?></b>
                </button>
            </div>

            <div class="directory-body" id="directoryBody">
                <label class="directory-search">
                    <svg class="ico" aria-hidden="true" focusable="false"><use href="#ico-search"/></svg>
                    <span class="sr-only">Buscar tienda</span>
                    <input type="search" id="shopSearch" placeholder="Buscar tienda o servicio" autocomplete="off" enterkeyhint="search">
                </label>

                <ul class="directory-list">
                    <?php foreach ($shops as $shop): ?>
                    <?php
                    $names = sellerNames($shop);
                    $searchText = implode(' ', [(string)$shop['name'], (string)($shop['category'] ?? ''), (string)($shop['description'] ?? ''), implode(' ', $names)]);
                    ?>
                    <li class="dir-item theme-<?= h((string)($shop['theme'] ?? 'default')) ?>" data-search="<?= h($searchText) ?>">
                        <button class="directory-shop" type="button" data-focus-shop="<?= h((string)$shop['id']) ?>"<?= $names ? ' title="' . h(implode(' · ', $names)) . '"' : '' ?>>
                            <span class="dir-icon"><?= iconSvg($shop) ?></span>
                            <span class="dir-copy">
                                <strong><?= h((string)$shop['name']) ?></strong>
                                <small><?= h((string)$shop['category']) ?></small>
                            </span>
                        </button>
                        <a class="dir-enter" href="<?= h(shopUrl((string)$shop['url'])) ?>" aria-label="<?= h('Entrar a ' . (string)$shop['name']) ?>">Entrar <span aria-hidden="true">→</span></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="directory-empty" id="searchEmpty" hidden>Ninguna tienda coincide con la búsqueda.</p>

                <p class="directory-note">
                    <i class="hint-mouse">Pasa el cursor sobre un sello para ver su ficha y haz clic para entrar. Arrastra el plano para moverte.</i><i class="hint-touch">Toca un sello para ver su ficha y tócalo otra vez para entrar. Pellizca o toca dos veces para acercar.</i><?php if ($pendingCount > 0): ?>
                    <span><?= $pendingCount ?> <?= $pendingCount === 1 ? 'tienda más está' : 'tiendas más están' ?> en camino.</span><?php endif; ?>
                </p>
            </div>
        </aside>

        <section class="map-board" aria-label="Mapa de Waterdeep">
            <div class="map-tools">
                <div class="zoom-controls" role="group" aria-label="Zoom del mapa">
                    <button type="button" data-zoom="out" aria-label="Alejar">−</button>
                    <button type="button" data-zoom="reset" class="zoom-reset" aria-label="Volver al zoom normal">100%</button>
                    <button type="button" data-zoom="in" aria-label="Acercar">+</button>
                </div>
            </div>

            <div class="map-viewport" id="mapViewport" tabindex="0">
                <div class="map-stage" id="mapStage">
                    <img class="city-map" id="mapImage" src="assets/mapa-waterdeep.webp" alt="Mapa de Waterdeep" draggable="false">

                    <?php foreach ($shops as $shop): ?>
                    <?php $sellers = is_array($shop['sellers'] ?? null) ? $shop['sellers'] : []; ?>
                    <a
                        class="<?= h(markerClass($shop)) ?>"
                        href="<?= h(shopUrl((string)$shop['url'])) ?>"
                        data-shop-id="<?= h((string)$shop['id']) ?>"
                        data-shop-x="<?= h((string)$shop['x']) ?>"
                        data-shop-y="<?= h((string)$shop['y']) ?>"
                        style="--x:<?= h((string)$shop['x']) ?>%;--y:<?= h((string)$shop['y']) ?>%;"
                        aria-label="<?= h('Entrar a ' . (string)$shop['name']) ?>"
                    >
                        <span class="shop-pin"><?= iconSvg($shop, 'ico pin-icon') ?></span>

                        <span class="shop-card">
                            <span class="card-ribbon"><?= h((string)$shop['category']) ?></span>

                            <?php if ($sellers): ?>
                            <span class="seller-row count-<?= min(count($sellers), 2) ?>">
                                <?php foreach (array_slice($sellers, 0, 2) as $seller): ?>
                                <span class="seller-cell">
                                    <span class="seller-portrait">
                                        <span class="portrait-placeholder"><?= h(firstChar((string)($seller['name'] ?? '?'))) ?></span>
                                        <?php if (!empty($seller['image'])): ?>
                                            <img src="<?= h((string)$seller['image']) ?>" alt="" loading="lazy" onerror="<?php if (!empty($seller['fallback'])): ?>this.onerror=null;this.src='<?= h((string)$seller['fallback']) ?>'<?php else: ?>this.remove()<?php endif; ?>">
                                        <?php endif; ?>
                                    </span>
                                    <b><?= h((string)($seller['name'] ?? '')) ?></b>
                                </span>
                                <?php endforeach; ?>
                            </span>
                            <?php endif; ?>

                            <span class="shop-card-copy">
                                <strong><?= h((string)$shop['name']) ?></strong>
                                <span><?= h((string)$shop['description']) ?></span>
                            </span>

                            <span class="shop-enter">Entrar a la tienda <b aria-hidden="true">→</b></span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="map-status" aria-live="polite">
                <span id="mapStatus">Pasa sobre un sello comercial.</span>
            </div>
        </section>
    </div>
</main>

<div class="coordinate-panel" id="coordinatePanel" hidden>
    <strong>EDITOR DE POSICIONES</strong>
    <span id="editorHelp">Selecciona una tienda y luego haz clic en el edificio.</span>
    <select id="editorShop">
        <?php foreach ($shops as $shop): ?>
            <option value="<?= h((string)$shop['id']) ?>"><?= h((string)$shop['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <code id="coordinateReadout">x: -- · y: --</code>
    <pre id="coordinateJson">{}</pre>
</div>

<script>
window.WATERDEEP_SHOPS = <?= json_encode($shops, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>" defer></script>
</body>
</html>
