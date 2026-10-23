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
<body>
<?php favor_site_bar(); ?>
<header class="map-header">
    <span class="home-link" aria-hidden="true"></span>

    <div class="map-title">
        <span class="title-ornament">✦</span>
        <div><small>Directorio comercial del gremio</small><h1>Waterdeep</h1></div>
        <span class="title-ornament mirror">✦</span>
    </div>

    <div class="header-actions">
        <a class="roll20-link" href="https://app.roll20.net/campaigns/details/20586133/-" target="_blank" rel="noopener noreferrer">⚔ Abrir Roll20</a>
        <div class="header-count"><strong><?= count($shops) ?>/<?= count($allShops) ?></strong><span>tiendas digitalizadas</span></div>
    </div>
</header>

<main class="map-shell">
    <?php if ($dataError): ?>
        <div class="map-error" role="alert"><?= h($dataError) ?></div>
    <?php endif; ?>

    <section class="map-intro">
        <div>
            <p class="overline">Waterdeep · comercio disponible</p>
            <h2>¿A dónde vamos?</h2>
            <p>Los sellos del mapa corresponden únicamente a tiendas que ya tienen página. Pasa sobre uno para abrir la ficha; haz click para entrar.</p>
        </div>
        <div class="intro-side">
            <div class="intro-stats">
                <span><b><?= count($shops) ?></b> abiertas</span>
                <span><b><?= $pendingCount ?></b> pendientes</span>
            </div>
            <a class="intro-roll20" href="https://app.roll20.net/campaigns/details/20586133/-" target="_blank" rel="noopener noreferrer"><b>⚔ Sede del Gremio</b><small>Abrir campaña en Roll20 ↗</small></a>
        </div>
    </section>

    <div class="map-layout">
        <aside class="directory">
            <div class="directory-head">
                <span>DIRECTORIO</span>
                <strong>Tiendas disponibles</strong>
            </div>

            <div class="directory-list">
                <?php foreach ($shops as $shop): ?>
                <button class="directory-shop" type="button" data-focus-shop="<?= h((string)$shop['id']) ?>">
                    <span class="dir-icon theme-<?= h((string)($shop['theme'] ?? 'default')) ?>"><?= h((string)($shop['marker'] ?? '•')) ?></span>
                    <span class="dir-copy">
                        <strong><?= h((string)$shop['name']) ?></strong>
                        <small><?= h((string)$shop['category']) ?></small>
                        <?php
                        $sellerNames = [];
                        foreach (($shop['sellers'] ?? []) as $seller) {
                            if (!empty($seller['name'])) $sellerNames[] = (string)$seller['name'];
                        }
                        ?>
                        <?php if ($sellerNames): ?><em><?= h(implode(' · ', $sellerNames)) ?></em><?php endif; ?>
                    </span>
                    <span class="dir-arrow">›</span>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="directory-note">
                <span>⌖</span>
                <p>En móvil: primer toque abre la ficha; segundo toque entra. El zoom no cambia las coordenadas de las tiendas.</p>
            </div>
        </aside>

        <section class="map-board">
            <div class="map-toolbar">
                <div class="map-caption">
                    <span class="map-seal">W</span>
                    <span><strong>Plano comercial</strong><small>Sellos activos del Favor de Jack</small></span>
                </div>
                <div class="zoom-controls">
                    <button type="button" data-zoom="out" aria-label="Alejar">−</button>
                    <button type="button" data-zoom="reset" class="zoom-reset">100%</button>
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
                        <span class="shop-pin">
                            <span class="pin-label"><?= h((string)($shop['marker'] ?? '•')) ?></span>
                        </span>

                        <span class="shop-card">
                            <span class="card-ribbon"><?= h((string)$shop['category']) ?></span>

                            <?php if ($sellers): ?>
                            <span class="seller-row count-<?= min(count($sellers), 2) ?>">
                                <?php foreach (array_slice($sellers, 0, 2) as $seller): ?>
                                <span class="seller-cell">
                                    <span class="seller-portrait">
                                        <span class="portrait-placeholder"><?= h(firstChar((string)($seller['name'] ?? '?'))) ?></span>
                                        <?php if (!empty($seller['image'])): ?>
                                            <img src="<?= h((string)$seller['image']) ?>" alt="" onerror="<?php if (!empty($seller['fallback'])): ?>this.onerror=null;this.src='<?= h((string)$seller['fallback']) ?>'<?php else: ?>this.remove()<?php endif; ?>">
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

                            <span class="shop-enter">Entrar <b>→</b></span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="map-status">
                <span id="mapStatus">Pasa sobre un sello comercial.</span>
                <span class="map-scale"><i></i><i></i><i></i><i></i></span>
            </div>
        </section>
    </div>
</main>

<div class="coordinate-panel" id="coordinatePanel" hidden>
    <strong>EDITOR DE POSICIONES</strong>
    <span id="editorHelp">Selecciona una tienda y luego haz click en el edificio.</span>
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
