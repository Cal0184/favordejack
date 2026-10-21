<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
require_once dirname(__DIR__, 2) . '/includes/descuentos.php';
favor_session_start();
favor_session_close();
$discount = favor_discount_shop_data($GLOBALS['__favor_auth_widget_user'] ?? null);
$discount['manageUrl'] = favor_base_path() . 'tiendas/descuentos.php';

$productsPath = __DIR__ . '/productos.json';
$products = [];
$dataError = null;

if (is_file($productsPath)) {
    $decoded = json_decode((string) file_get_contents($productsPath), true);
    if (is_array($decoded)) {
        $products = $decoded;
    } else {
        $dataError = 'No fue posible leer productos.json.';
    }
} else {
    $dataError = 'No se encontró productos.json.';
}

$shopData = [
    'products' => $products,
    'shop' => [
        'name' => 'La Poción Picante',
        'seller' => 'Yamo-Yamo',
        'backUrl' => favor_base_path() . 'mapa/',
    ],
    'discount' => $discount,
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#120d17">
<title>La Poción Picante · Favor de Jack</title>
<meta name="description" content="Catálogo digital de La Poción Picante: curación, remedios, blindaje, esencias, conjuros y néctares.">
<link rel="stylesheet" href="style.css?v=2">
</head>
<body>
<?php favor_auth_widget(); ?>
<header class="topbar">
    <a class="back-link" href="<?= favor_base_path() ?>mapa/">← Mapa de Waterdeep</a>
    <a class="brand" href="#inicio">
        <span class="brand-bottle" aria-hidden="true">◈</span>
        <span><strong>La Poción Picante</strong><small>Botica · Elixires · Alquimia</small></span>
    </a>
    <nav class="topnav">
        <a href="#catalogo">Catálogo</a>
        <a href="#reglas">Reglas</a>
        <a href="#yamo-yamo">Yamo-Yamo</a>
        <button class="favorites-button" id="favoritesButton" type="button" aria-pressed="false">♥ <span>Favoritos</span> <b id="favoriteCount">0</b></button>
    </nav>
</header>

<main>
<section class="hero" id="inicio">
    <img class="hero-bg" src="assets/interior-pocion-picante.webp" alt="Interior de La Poción Picante">
    <div class="hero-shade"></div>
    <div class="brew-glow" aria-hidden="true"></div>

    <div class="hero-content">
        <p class="eyebrow">Botica de Waterdeep · 74 preparados registrados</p>
        <div class="shop-sign">
            <span class="nail n1"></span><span class="nail n2"></span>
            <h1>La Poción<br><em>Picante</em></h1>
            <div class="sign-flasks" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        </div>
        <p class="hero-copy">Curación, remedios, blindajes alquímicos, aceites, esencias, conjuros embotellados y preparados draconianos reunidos en un solo catálogo.</p>
        <div class="hero-actions">
            <a class="button primary" href="#catalogo">Abrir estanterías</a>
            <a class="button ghost" href="#reglas">Consultar reglas</a>
        </div>
        <div class="hero-stats">
            <div><strong>74</strong><span>Preparados</span></div>
            <div><strong>7</strong><span>Curación</span></div>
            <div><strong>16</strong><span>Conjuros</span></div>
            <div><strong>6</strong><span>Néctares</span></div>
        </div>
    </div>

    <div class="seller-stage" id="yamo-yamo">
        <div class="seller-name">Yamo-Yamo</div>
        <img src="assets/vendedores/yamo-yamo.png" alt="Yamo-Yamo">
    </div>
</section>

<?php if ($dataError): ?>
<div class="data-error" role="alert"><?= htmlspecialchars($dataError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="shelves-section" aria-label="Familias del catálogo">
    <div class="shelf-strip">
        <a href="#catalogo" data-jump-category="Curación"><span class="vial green"></span><strong>Curación</strong><small>7</small></a>
        <a href="#catalogo" data-jump-category="Restauración"><span class="vial teal"></span><strong>Restauración</strong><small>9</small></a>
        <a href="#catalogo" data-jump-category="Blindaje"><span class="vial blue"></span><strong>Blindaje</strong><small>10</small></a>
        <a href="#catalogo" data-jump-category="Arsenal"><span class="vial orange"></span><strong>Arsenal</strong><small>9</small></a>
        <a href="#catalogo" data-jump-category="Esencias"><span class="vial gold"></span><strong>Esencias</strong><small>10</small></a>
        <a href="#catalogo" data-jump-category="Conjuros en Lata"><span class="vial purple"></span><strong>Conjuros</strong><small>16</small></a>
        <a href="#catalogo" data-jump-category="Utilidad"><span class="vial pink"></span><strong>Utilidad</strong><small>7</small></a>
        <a href="#catalogo" data-jump-category="Néctares"><span class="vial red"></span><strong>Néctares</strong><small>6</small></a>
    </div>
</section>

<section class="catalog-section" id="catalogo">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Estanterías de Yamo-Yamo</p>
            <h2>Catálogo</h2>
        </div>
        <p>Los productos están agrupados por familia y subcategoría. Las fichas conservan los efectos, duraciones, aplicaciones y salvaciones indicados en el documento de la tienda.</p>
    </div>

    <div class="discount-bar" id="discountBar" hidden></div>

    <div class="catalog-workbench">
        <div class="workbench-label"><span>Registro alquímico</span><i></i></div>
        <div class="catalog-toolbar">
            <label class="search-box"><span aria-hidden="true">⌕</span><input id="searchInput" type="search" placeholder="Buscar poción, efecto, condición…" autocomplete="off"></label>
            <select id="sortSelect" aria-label="Ordenar catálogo">
                <option value="default">Orden del catálogo</option>
                <option value="name-asc">Nombre A–Z</option>
                <option value="price-asc">Precio menor primero</option>
                <option value="price-desc">Precio mayor primero</option>
            </select>
        </div>
        <div class="category-tabs" id="categoryTabs">
            <button class="category-tab active" type="button" data-category="Todos">Todos <span>74</span></button>
<button class="category-tab" type="button" data-category="Curación">Curación <span>7</span></button>
<button class="category-tab" type="button" data-category="Restauración">Restauración <span>9</span></button>
<button class="category-tab" type="button" data-category="Blindaje">Blindaje <span>10</span></button>
<button class="category-tab" type="button" data-category="Arsenal">Arsenal <span>9</span></button>
<button class="category-tab" type="button" data-category="Esencias">Esencias <span>10</span></button>
<button class="category-tab" type="button" data-category="Conjuros en Lata">Conjuros en Lata <span>16</span></button>
<button class="category-tab" type="button" data-category="Utilidad">Utilidad <span>7</span></button>
<button class="category-tab" type="button" data-category="Néctares">Néctares <span>6</span></button>
        </div>
    </div>

    <div class="results-line"><span id="resultsCount">74 preparados</span><button class="clear-filters" id="clearFilters" type="button" hidden>Limpiar filtros</button></div>
    <div class="product-grid" id="productGrid"></div>
    <div class="empty-state" id="emptyState" hidden><strong>No hay ningún frasco con esa etiqueta.</strong><span>Prueba otro término o abre otra estantería.</span></div>
</section>

<section class="rules-section" id="reglas">
    <div class="section-heading">
        <div><p class="eyebrow">Notas de consumo</p><h2>Reglas de la botica</h2></div>
        <p>Estas reglas están separadas del catálogo para que puedan consultarse rápidamente durante una partida.</p>
    </div>

    <div class="rules-grid">
        <article class="rule-card healing">
            <span class="rule-symbol">✚</span>
            <h3>Pociones de curación</h3>
            <p><b>Acción:</b> recuperas la cantidad máxima de HP posible.</p>
            <p><b>Acción Bonus:</b> lanzas los dados para determinar la curación.</p>
            <p><b>Dársela a un aliado:</b> requiere una acción.</p>
        </article>
        <article class="rule-card standard">
            <span class="rule-symbol">◈</span>
            <h3>Otras pociones</h3>
            <p>Cualquier poción que no sea de curación solo puede usarse como acción completa.</p>
        </article>
        <article class="rule-card armor">
            <span class="rule-symbol">⬡</span>
            <h3>Blindaje</h3>
            <p>No se pueden sumar dos pociones de la misma subcategoría.</p>
        </article>
        <article class="rule-card essence">
            <span class="rule-symbol">◇</span>
            <h3>Herencia Animal</h3>
            <p>La puntuación aumenta en +4 —lo que sube el modificador en +2— hasta un máximo de 20. No se pueden acumular dos opciones de atributo a la vez.</p>
        </article>
        <article class="rule-card dragon">
            <span class="rule-symbol">◆</span>
            <h3>Néctares Draconianos</h3>
            <p>Duran 1 hora. Exhalar el aliento requiere una acción. Tras usarlo, tira 1d4: ese es el número de turnos que debes esperar para volver a usarlo. Una salvación exitosa reduce el daño a la mitad.</p>
        </article>
    </div>
</section>

<section class="seller-section">
    <div class="seller-art">
        <div class="seller-aura" aria-hidden="true"></div>
        <img src="assets/vendedores/yamo-yamo.png" alt="Yamo-Yamo">
    </div>
    <div class="seller-simple">
        <p class="eyebrow">Vendedor</p>
        <h2>Yamo-Yamo</h2>
    </div>
</section>
</main>

<footer class="footer"><span>La Poción Picante · Catálogo digital del Favor de Jack</span><a href="<?= favor_base_path() ?>mapa/">Volver al mapa de Waterdeep</a></footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ficha">×</button>
        <div id="modalContent"></div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script id="shopData" type="application/json"><?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="app.js?v=2" defer></script>
</body>
</html>
