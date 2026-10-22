<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
require_once __DIR__ . '/lib.php';

$user = $GLOBALS['__favor_auth_widget_user'] ?? null;
$csrf = $user ? favor_csrf() : '';
favor_session_close();

[$cards, $dupes] = emporio_shop_cards();
$services = array_values(array_filter(load_json_file(EMPORIO_SERVICES_FILE), 'is_array'));
$categories = array_values(array_unique(array_column($cards, 'category')));
natcasesort($categories);
$categories = array_values($categories);
$isAdmin = ($user['role'] ?? '') === 'admin';

$shopData = [
    'cards' => array_values(array_map(static fn(array $c): array => [
        'id' => $c['id'],
        'name' => $c['name'],
        'category' => $c['category'],
        'price' => $c['priceLabel'],
        'pricePo' => $c['pricePo'],
        'stock' => $c['stock'],
        'soldOut' => $c['soldOut'],
        'description' => $c['description'] !== '' ? $c['description'] : 'Ecix’s todavía no ha terminado de registrar las propiedades de este objeto.',
        'url' => $c['url'],
        'thumb' => $c['thumb'],
    ], $cards)),
    'services' => array_map(static fn(array $s): array => [
        'id' => slugify((string)($s['id'] ?? $s['name'] ?? '')),
        'name' => (string)($s['name'] ?? ''),
        'price' => emporio_price_label((string)($s['price'] ?? '')),
        'pricePo' => emporio_price_po((string)($s['price'] ?? '')),
        'description' => (string)($s['description'] ?? ''),
    ], $services),
    'user' => $user ? ['name' => (string)$user['displayName'], 'admin' => $isAdmin] : null,
    'characters' => emporio_characters($user),
    'orders' => array_map('emporio_order_public', emporio_orders_for($user, 20)),
    'csrf' => $csrf,
    'loginUrl' => favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'tiendas/emporio/'),
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#130d09">
<meta name="description" content="El Emporio de Ecix's: objetos mágicos, curiosidades y servicios arcanos de Favor de Jack.">
<title>El Emporio de Ecix's</title>
<link rel="preload" as="image" href="../../media/shops/ecix/interior-960.webp" media="(max-width: 960px)">
<link rel="preload" as="image" href="../../media/shops/ecix/interior.webp" media="(min-width: 961px)">
<link rel="stylesheet" href="assets/emporio.css<?= favor_v(__DIR__ . '/assets/emporio.css') ?>">
</head>
<body>
<a class="skip" href="#catalogo">Saltar al catálogo</a>
<header class="site-header">
    <a class="brand" href="./" aria-label="El Emporio de Ecix's, inicio">
        <span class="brand-mark" aria-hidden="true">E</span>
        <span class="brand-copy">
            <small>Objetos extraordinarios · Tratos inolvidables</small>
            <strong>El Emporio de Ecix's</strong>
        </span>
    </a>
    <nav class="header-actions" aria-label="Secciones del Emporio">
        <button id="catalogTab" class="tab active" type="button" aria-pressed="true">Catálogo</button>
        <button id="servicesTab" class="tab" type="button" aria-pressed="false">Servicios</button>
        <?php if ($user): ?><button id="ordersTab" class="tab" type="button" aria-pressed="false">Mis pedidos</button><?php endif; ?>
        <?php if ($isAdmin): ?><a href="admin.php">Admin</a><?php endif; ?>
        <a href="<?= e(favor_base_path()) ?>mapa/">← Mapa</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-inner">
        <p class="eyebrow">El archivo arcano ha vuelto a abrirse</p>
        <h1>Todo tiene<br>un precio.</h1>
        <p class="hero-text">Reliquias, curiosidades y errores de la magia reunidos bajo un mismo techo. Elige, compara y deja tu pedido: Ecix's lo revisará en persona.</p>
        <div class="hero-stats" aria-label="Resumen">
            <span><b><?= count($cards) ?></b> objetos</span>
            <span><b><?= count($categories) ?></b> categorías</span>
            <span><b><?= count($services) ?></b> servicios</span>
        </div>
    </div>
    <figure class="hero-seller">
        <img src="../../media/shops/ecix/ecix.webp" width="520" height="1146" alt="Ecix's, el dueño del Emporio" onerror="this.onerror=null;this.src='../../media/shops/ecix/ecix-fallback.svg'">
        <figcaption>«Si brilla, lo tengo. Si no brilla, lo tengo más caro.»</figcaption>
    </figure>
</section>

<main id="catalogo" class="catalog-shell">
    <section id="catalogView" class="view">
        <div class="catalog-head">
            <div><p class="eyebrow">Catálogo encantado</p><h2>Las mercancías</h2></div>
            <p class="catalog-note">Pulsa una carta para ver sus propiedades.<span class="kbd-hint"> Con <kbd>←</kbd> <kbd>→</kbd> pasas a la siguiente sin cerrar.</span></p>
        </div>

        <div class="filter-panel" role="search" aria-label="Filtros del catálogo">
            <div class="filter-grid">
                <label class="field search-field">
                    <span class="field-label">Buscar objeto</span>
                    <span class="search-box">
                        <span class="search-icon" aria-hidden="true">⌕</span>
                        <input id="search" type="search" placeholder="Nombre, categoría o propiedad..." autocomplete="off">
                        <button id="searchClear" class="search-clear" type="button" aria-label="Vaciar búsqueda" hidden>×</button>
                    </span>
                </label>
                <label class="field">
                    <span class="field-label">Categoría</span>
                    <select id="category">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>"><?= e($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Disponibilidad</span>
                    <select id="availability">
                        <option value="">Todos</option>
                        <option value="available">Disponibles</option>
                        <option value="soldout">Agotados</option>
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Ordenar</span>
                    <select id="sort">
                        <option value="name">Nombre (A-Z)</option>
                        <option value="price-asc">Precio: menor a mayor</option>
                        <option value="price-desc">Precio: mayor a menor</option>
                        <option value="category">Categoría</option>
                    </select>
                </label>
            </div>
            <div class="budget-row" id="budgetRow">
                <?php if ($user && $shopData['characters']): ?>
                    <label class="field budget-field">
                        <span class="field-label">¿Me alcanza? Elige personaje</span>
                        <select id="buyer">
                            <option value="">— Sin elegir —</option>
                            <?php $lastPlayer = null; foreach ($shopData['characters'] as $c): ?>
                                <?php if ($isAdmin && $lastPlayer !== $c['playerName']): ?><?= $lastPlayer !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($c['playerName']) ?>"><?php $lastPlayer = $c['playerName']; endif; ?>
                                <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?> · <?= number_format($c['gold'], 0, ',', '.') ?> po</option>
                            <?php endforeach; ?>
                            <?= $isAdmin && $lastPlayer !== null ? '</optgroup>' : '' ?>
                        </select>
                    </label>
                    <label class="check"><input id="affordable" type="checkbox"> Solo lo que puede pagar</label>
                    <span id="budgetInfo" class="budget-info" aria-live="polite"></span>
                <?php elseif ($user): ?>
                    <span class="budget-info">No encontré personajes activos en tu cuenta para calcular el saldo.</span>
                <?php else: ?>
                    <span class="budget-info"><a href="<?= e($shopData['loginUrl']) ?>">Inicia sesión</a> para ver qué puede pagar tu personaje y dejarle pedidos a Ecix's.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="catalog-actions">
            <div id="resultState" class="result-state" aria-live="polite">Mostrando <?= count($cards) ?> objetos.</div>
            <div class="action-group">
                <button id="favoritesToggle" class="tool-btn" type="button" aria-pressed="false">♡ Favoritos (<span id="favoriteCount">0</span>)</button>
                <button id="compareButton" class="tool-btn" type="button">⚖ Comparador (0/3)</button>
                <button id="randomButton" class="tool-btn" type="button">✦ Sorpréndeme</button>
                <button id="clearButton" class="tool-btn secondary" type="button">↺ Limpiar filtros</button>
            </div>
        </div>

        <div id="grid" class="grid">
            <?php if (!$cards): ?><div class="empty">No encontré imágenes dentro de <code>Cartas/</code>.</div><?php endif; ?>
            <?php foreach ($cards as $card): ?>
                <article class="card<?= $card['soldOut'] ? ' soldout' : '' ?>" id="carta-<?= e($card['id']) ?>" data-id="<?= e($card['id']) ?>">
                    <button class="card-image" type="button" aria-label="Ver detalles de <?= e($card['name']) ?>">
                        <img loading="lazy" decoding="async" width="480" height="667"
                            src="<?= e($card['thumb']) ?>"
                            alt="<?= e($card['name']) ?>"
                            data-fallback="<?= e($card['url']) ?>">
                    </button>
                    <div class="card-body">
                        <span class="category"><?= e($card['category']) ?></span>
                        <h3><?= e($card['name']) ?></h3>
                        <div class="item-meta">
                            <p class="price"><?= e($card['priceLabel']) ?></p>
                            <span class="stock<?= $card['soldOut'] ? ' zero' : '' ?>"><?= $card['soldOut'] ? 'Agotado' : 'Stock: ' . $card['stock'] ?></span>
                        </div>
                        <p class="afford" hidden></p>
                        <div class="card-actions">
                            <button class="favorite-btn" type="button" aria-label="Añadir a favoritos" aria-pressed="false">♥</button>
                            <button class="select-btn" type="button" aria-pressed="false">Comparar</button>
                            <?php if ($user): ?><button class="order-btn" type="button"<?= $card['soldOut'] ? ' disabled title="Agotado"' : '' ?>>+ Pedido</button><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            <div id="noResults" class="empty" hidden>No hay objetos que coincidan con esos filtros. <button id="noResultsClear" class="link-btn" type="button">Limpiar filtros</button></div>
        </div>
    </section>

    <section id="servicesView" class="view" hidden>
        <div class="catalog-head">
            <div><p class="eyebrow">Servicios de Ecix's</p><h2>Trabajos especiales</h2></div>
            <p class="catalog-note">Ecix's también trabaja por encargo. Los servicios se piden igual que los objetos.</p>
        </div>
        <div class="services-grid">
            <?php foreach ($shopData['services'] as $service): ?>
                <article class="service-card" data-service="<?= e($service['id']) ?>">
                    <p class="eyebrow">Servicio</p>
                    <h3><?= e($service['name']) ?></h3>
                    <p class="price"><?= e($service['price']) ?></p>
                    <p><?= e($service['description']) ?></p>
                    <?php if ($user): ?><button class="tool-btn service-order" type="button">+ Añadir al pedido</button><?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$services): ?><div class="empty">Ecix's no tiene servicios publicados por ahora.</div><?php endif; ?>
        </div>
    </section>

    <?php if ($user): ?>
    <section id="ordersView" class="view" hidden>
        <div class="catalog-head">
            <div><p class="eyebrow">Tus tratos con Ecix's</p><h2><?= $isAdmin ? 'Pedidos recientes' : 'Mis pedidos' ?></h2></div>
            <p class="catalog-note">Ecix's (el DM) revisa cada pedido. El oro no se descuenta solo: lo hace el DM en la Banca cuando lo aprueba.</p>
        </div>
        <div id="ordersList" class="orders-list"></div>
    </section>
    <?php endif; ?>
</main>

<?php if ($user): ?>
<div id="orderBar" class="order-bar" hidden>
    <span><b id="orderCount">0</b> en el pedido · <span id="orderTotal">0 po</span></span>
    <button id="orderOpen" class="tool-btn active" type="button">Revisar y enviar</button>
</div>
<?php endif; ?>

<footer>El Emporio de Ecix's · Catálogo arcano de Favor de Jack</footer>

<div id="modal" class="modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle" tabindex="-1">
        <button class="modal-close" type="button" aria-label="Cerrar">×</button>
        <div id="modalBody"></div>
    </div>
</div>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>

<script id="emporio-data" type="application/json"><?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/emporio.js<?= favor_v(__DIR__ . '/assets/emporio.js') ?>" defer></script>
<?php favor_auth_widget(); ?>
</body>
</html>
