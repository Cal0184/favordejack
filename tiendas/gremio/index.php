<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
require_once dirname(__DIR__, 2) . '/includes/gremio.php';
favor_session_start();
$user = $GLOBALS['__favor_auth_widget_user'] ?? null;
$compra = favor_tienda_client($user, FAVOR_GREMIO_SHOP);
favor_session_close();
// Las compras de esta tienda salen del Almacén (con existencias), no de productos.json.
$compra['url'] = 'api.php?action=buy';
$isAdmin = favor_gremio_is_admin($user);

$products = [];
$dataError = null;
try {
    $products = favor_gremio_products();
} catch (Throwable $e) {
    error_log('Tienda del Gremio: ' . $e->getMessage());
    $dataError = 'No se pudo abrir el Almacén del Gremio. Inténtalo en un momento.';
}

$groups = [];
foreach ($products as $p) {
    $g = $p['subcategory'] !== '' ? $p['subcategory'] : 'De aventureros';
    $groups[$g] = ($groups[$g] ?? 0) + 1;
}
$inStock = count(array_filter($products, static fn(array $p): bool => $p['available']));

$shopData = [
    'products' => $products,
    'shop' => ['name' => FAVOR_GREMIO_NAME, 'seller' => 'Lady Gena', 'backUrl' => favor_base_path() . 'mapa/'],
];
$gremio = [
    'api' => 'api.php',
    'csrf' => $user ? favor_csrf() : '',
    'user' => (bool)$user,
    'admin' => $isAdmin,
    'login' => $compra['login'],
    'characters' => array_map(static fn(array $b): array => ['id' => $b['id'], 'name' => $b['name'], 'playerName' => $b['playerName']], $compra['buyers']),
    'inventory' => favor_url('inventario/'),
    'pending' => $isAdmin ? favor_gremio_pending_count() : 0,
];

function gh(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1a120c">
<title>Tienda del Gremio · Favor de Jack</title>
<meta name="description" content="La Tienda del Gremio Per-Favore: objetos mágicos a la venta y compra de objetos a los aventureros.">
<link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
<?php favor_head('Tienda del Gremio · Favor de Jack', 'Lady Gena compra y vende objetos mágicos para el gremio Per-Favore.'); ?>
</head>
<body>
<?php favor_site_bar(); ?>

<main class="guild">
<section class="guild-hero" aria-labelledby="guildTitle">
    <div class="hero-copy">
        <p class="eyebrow">Gremio de aventureros Per-Favore</p>
        <h1 id="guildTitle">Tienda del Gremio</h1>
        <p class="lead">Lady Gena, jefa del gremio, guarda aquí los objetos mágicos del Per-Favore. Lo que hay en el almacén se puede comprar con el oro de la Banca, y el gremio también compra lo que traen sus aventureros.</p>
        <ul class="hero-facts">
            <li><b><?= $inStock ?></b> objetos a la venta</li>
            <li><b>Banca</b> e <b>Inventario</b> al instante</li>
        </ul>
    </div>
    <figure class="gena">
        <img src="../../media/shops/gremio/lady-gena.webp" width="676" height="1121" alt="Lady Gena, jefa del gremio Per-Favore" fetchpriority="high">
        <figcaption><strong>Lady Gena</strong><span>Jefa del gremio</span></figcaption>
    </figure>
</section>

<nav class="guild-tabs" role="tablist" aria-label="Áreas de la tienda">
    <button type="button" role="tab" id="tab-comprar" aria-controls="panel-comprar" aria-selected="true" data-tab="comprar"><span aria-hidden="true">⚖</span> Comprar objetos</button>
    <button type="button" role="tab" id="tab-vender" aria-controls="panel-vender" aria-selected="false" data-tab="vender" tabindex="-1"><span aria-hidden="true">⇄</span> Vender al gremio</button>
    <?php if ($isAdmin): ?>
    <button type="button" role="tab" id="tab-almacen" aria-controls="panel-almacen" aria-selected="false" data-tab="almacen" tabindex="-1"><span aria-hidden="true">⚿</span> Almacén del Gremio <b class="tab-badge" id="pendingBadge"<?= $gremio['pending'] ? '' : ' hidden' ?>><?= (int)$gremio['pending'] ?></b></button>
    <?php endif; ?>
</nav>

<?php if ($dataError): ?><div class="data-error" role="alert"><?= gh($dataError) ?></div><?php endif; ?>

<!-- ============ Comprar ============ -->
<section class="panel" id="panel-comprar" role="tabpanel" aria-labelledby="tab-comprar">
    <div class="toolbar">
        <label class="search-box"><span aria-hidden="true">⌕</span><span class="sr-only">Buscar</span><input id="searchInput" type="search" placeholder="Buscar objeto, conjuro o efecto…" autocomplete="off"></label>
        <select id="sortSelect" aria-label="Ordenar">
            <option value="default">Orden del almacén</option>
            <option value="name-asc">Nombre A–Z</option>
            <option value="price-asc">Precio menor primero</option>
            <option value="price-desc">Precio mayor primero</option>
        </select>
        <button class="fav-toggle" id="favoritesButton" type="button" aria-pressed="false">♥ Favoritos <b id="favoriteCount">0</b></button>
    </div>
    <div class="chips" role="group" aria-label="Tipo de objeto">
        <button class="filter-chip active" type="button" data-category="Todos">Todos <span><?= count($products) ?></span></button>
        <?php foreach ($groups as $g => $n): ?>
        <button class="filter-chip" type="button" data-category="<?= gh($g) ?>"><?= gh($g) ?> <span><?= $n ?></span></button>
        <?php endforeach; ?>
    </div>
    <div class="results-line"><span id="resultsCount"></span><button class="clear-filters" id="clearFilters" type="button" hidden>Limpiar filtros</button></div>
    <div class="product-grid" id="productGrid"></div>
    <div class="empty-state" id="emptyState" hidden><strong>Ese objeto no está en el almacén.</strong><span>Prueba con otro nombre o quita los filtros.</span></div>
</section>

<!-- ============ Vender ============ -->
<section class="panel" id="panel-vender" role="tabpanel" aria-labelledby="tab-vender" hidden>
    <div class="sell-intro">
        <h2>Vender objetos mágicos al gremio</h2>
        <p>Elige a tu personaje y el objeto que quieres ofrecer. Si el gremio ya tiene precio para ese objeto, la venta es inmediata; si no, Lady Gena lo valora primero y tú decides si aceptas. El objeto sale de tu Inventario y el oro entra a tu Banca en el mismo momento.</p>
    </div>
    <?php if (!$user): ?>
        <p class="notice"><a href="<?= gh($compra['login']) ?>">Inicia sesión</a> para venderle objetos al gremio.</p>
    <?php elseif (!$gremio['characters']): ?>
        <p class="notice">No tienes personajes activos en la Banca.</p>
    <?php else: ?>
        <label class="field sell-char"><span>Personaje</span><select id="sellCharacter"><option value="">— Elige personaje —</option><?php foreach ($gremio['characters'] as $c): ?><option value="<?= gh($c['id']) ?>"><?= gh($c['name']) ?><?= $isAdmin ? ' · ' . gh($c['playerName']) : '' ?></option><?php endforeach; ?></select></label>
        <div id="sellArea" aria-live="polite"></div>
    <?php endif; ?>
</section>

<?php if ($isAdmin): ?>
<!-- ============ Almacén (DM) ============ -->
<section class="panel" id="panel-almacen" role="tabpanel" aria-labelledby="tab-almacen" hidden>
    <div class="sell-intro">
        <h2>Almacén del Gremio</h2>
        <p>Solo lo ve el DM. Decide qué se vende y qué guarda el gremio, sus precios y existencias, valora lo que ofrecen los aventureros y fija cuánto paga el gremio por cada objeto.</p>
    </div>
    <div id="storageArea" aria-live="polite"><p class="loading">Abriendo el almacén…</p></div>
</section>
<?php endif; ?>
</main>

<footer class="guild-footer"><span>Tienda del Gremio · Per-Favore</span><a href="<?= favor_base_path() ?>mapa/">← Volver al mapa de Waterdeep</a></footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ficha">×</button>
        <div id="modalContent"></div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script id="shopData" type="application/json"><?= json_encode($shopData, $json) ?></script>
<script id="favorCompra" type="application/json"><?= json_encode($compra, $json) ?></script>
<script id="gremioData" type="application/json"><?= json_encode($gremio, $json) ?></script>
<script src="../compra.js<?= favor_v(dirname(__DIR__) . '/compra.js') ?>" defer></script>
<script src="../tienda.js<?= favor_v(dirname(__DIR__) . '/tienda.js') ?>" defer></script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>" defer></script>
</body>
</html>
