<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
favor_session_start();
favor_session_close();

$productsPath = __DIR__ . '/productos.json';
$products = [];
$dataError = null;

if (is_file($productsPath)) {
    $decoded = json_decode((string)file_get_contents($productsPath), true);
    if (is_array($decoded)) {
        $products = favor_shop_drop_missing_images($decoded, __DIR__);
    } else {
        $dataError = 'No fue posible leer productos.json.';
    }
} else {
    $dataError = 'No se encontró productos.json.';
}

$counts = ['Objetos mágicos' => 0, 'Mejoras mágicas' => 0];
foreach ($products as $product) {
    $category = (string)($product['category'] ?? '');
    if (isset($counts[$category])) $counts[$category]++;
}

$shopData = [
    'products' => $products,
    'shop' => [
        'name' => 'La Dama de las Llamas',
        'backUrl' => favor_base_path() . 'mapa/',
        'seller' => 'Gaston',
        'arcaneSeller' => 'Axe Rein',
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#160c07">
<title>La Dama de las Llamas · Favor de Jack</title>
<meta name="description" content="Herrería La Dama de las Llamas. Forja, compra y venta de armas y armaduras, objetos mágicos y mejoras arcanas.">
<link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
</head>
<body>
<?php favor_auth_widget(); ?>
<header class="topbar">
    <a class="back-link" href="<?= favor_base_path() ?>mapa/">← Mapa de Waterdeep</a>

    <a class="brand" href="#inicio">
        <span class="brand-emblem" aria-hidden="true">⚒</span>
        <span>
            <strong>La Dama de las Llamas</strong>
            <small>Herrería · Forja · Mejoras arcanas</small>
        </span>
    </a>

    <nav class="topnav" aria-label="Navegación de la herrería">
        <a href="#forja">La Forja</a>
        <a href="#catalogo">Catálogo</a>
        <a href="#maestros">Maestros</a>
        <a href="#proyecto-axe">En desarrollo</a>
        <button class="favorites-button" id="favoritesButton" type="button" aria-pressed="false">
            ♥ <span>Favoritos</span> <b id="favoriteCount">0</b>
        </button>
    </nav>
</header>

<main>
<section class="hero" id="inicio">
    <img class="hero-bg" src="assets/interior-herreria.webp" alt="Interior de la herrería La Dama de las Llamas">
    <div class="hero-shade"></div>
    <div class="embers" aria-hidden="true"></div>

    <div class="hero-copy">
        <p class="eyebrow">Herrería de oficio · Metal primero, magia después</p>
        <div class="forge-sign">
            <span class="sign-rivet r1"></span><span class="sign-rivet r2"></span>
            <h1>La Dama<br><em>de las</em> Llamas</h1>
            <p>No somos una armería. Somos quienes hacen que el acero merezca ser vendido.</p>
        </div>

        <p class="hero-lead">Aquí se trabaja el metal, se reciben armas y armaduras para compraventa y, cuando el encargo exige algo más que martillo y yunque, Axe se encarga de la parte arcana.</p>

        <div class="hero-actions">
            <a class="button forge-button" href="#catalogo">Revisar piezas y mejoras</a>
            <a class="button ghost-button" href="#maestros">Conocer a los herreros</a>
        </div>

        <div class="hero-stats">
            <div><small>Catálogo</small><strong><?= count($products) ?></strong><span>entradas</span></div>
            <div><small>Objetos</small><strong><?= $counts['Objetos mágicos'] ?></strong><span>mágicos</span></div>
            <div><small>Banco arcano</small><strong><?= $counts['Mejoras mágicas'] ?></strong><span>mejoras</span></div>
        </div>
    </div>

    <div class="hero-vendors" aria-label="Gaston y Axe Rein">
        <div class="vendor gaston">
            <img src="../../media/shops/ladamadelasllamas/gaston.webp" alt="Gaston, herrero principal">
            <div class="vendor-plate">
                <small>Herrero principal</small>
                <strong>Gaston</strong>
            </div>
        </div>
        <div class="vendor axe">
            <img src="../../media/shops/ladamadelasllamas/axe-rein.webp" alt="Axe Rein, especialista en efectos mágicos">
            <div class="vendor-plate arcane">
                <small>Efectos y mejoras arcanas</small>
                <strong>Axe Rein</strong>
            </div>
        </div>
    </div>
</section>

<?php if ($dataError): ?>
<div class="data-error" role="alert"><?= htmlspecialchars($dataError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="forge-section" id="forja">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Una herrería, no una vitrina</p>
            <h2>Trabajo de taller</h2>
        </div>
        <p>La Dama de las Llamas puede comprar y vender armas y armaduras, pero su identidad es el oficio: metal, forja y encargos que pasan por manos de herreros antes de llegar al mostrador.</p>
    </div>

    <div class="service-grid">
        <article class="service-card physical">
            <span class="service-icon">⚒</span>
            <div>
                <small>Banco de Gaston</small>
                <h3>Forja y trabajo físico</h3>
                <p>Gaston es el herrero principal y la cara del trabajo tradicional de la tienda.</p>
            </div>
        </article>

        <article class="service-card trade">
            <span class="service-icon">⇄</span>
            <div>
                <small>Mostrador</small>
                <h3>Compra y venta</h3>
                <p>La herrería también recibe y vende armas y armaduras. La tasación no forma parte todavía del catálogo automático.</p>
            </div>
        </article>

        <article class="service-card arcane">
            <span class="service-icon">✦</span>
            <div>
                <small>Banco de Axe</small>
                <h3>Efectos mágicos</h3>
                <p>Axe se ocupa de encantamientos, mejoras arcanas y de hacer que una buena pieza se convierta en algo bastante menos normal.</p>
            </div>
        </article>
    </div>
</section>

<section class="catalog-section" id="catalogo">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Piezas listas · Encargos arcanos</p>
            <h2>Catálogo de la herrería</h2>
        </div>
        <p>Por ahora esta página funciona como catálogo. No descuenta dinero de la Banca Digital ni modifica stock compartido.</p>
    </div>

    <div class="catalog-console">
        <div class="console-head">
            <span>Registro de encargos y piezas</span>
            <i></i>
        </div>

        <div class="catalog-toolbar">
            <label class="search-box">
                <span aria-hidden="true">⌕</span>
                <input id="searchInput" type="search" placeholder="Buscar objeto o mejora…" autocomplete="off">
            </label>
            <select id="sortSelect" aria-label="Ordenar catálogo">
                <option value="default">Orden del catálogo</option>
                <option value="name-asc">Nombre A–Z</option>
                <option value="price-asc">Precio menor primero</option>
                <option value="price-desc">Precio mayor primero</option>
            </select>
        </div>

        <div class="category-tabs" id="categoryTabs">
            <button class="category-tab active" type="button" data-category="Todos">Todo <span><?= count($products) ?></span></button>
            <button class="category-tab" type="button" data-category="Objetos mágicos">Objetos mágicos <span><?= $counts['Objetos mágicos'] ?></span></button>
            <button class="category-tab arcane-tab" type="button" data-category="Mejoras mágicas">Mejoras de Axe <span><?= $counts['Mejoras mágicas'] ?></span></button>
        </div>
    </div>

    <div class="results-line">
        <span id="resultsCount"><?= count($products) ?> entradas</span>
        <button id="clearFilters" class="clear-filters" type="button" hidden>Limpiar filtros</button>
    </div>

    <div class="product-grid" id="productGrid"></div>
    <div class="empty-state" id="emptyState" hidden>
        <strong>No hay ninguna pieza con esa descripción.</strong>
        <span>Prueba con otro término o cambia de banco.</span>
    </div>
</section>

<section class="masters-section" id="maestros">
    <div class="masters-title">
        <p class="eyebrow">Dos bancos · Dos especialidades</p>
        <h2>Los maestros de la llama</h2>
    </div>

    <article class="master-card gaston-card">
        <div class="master-art"><img src="../../media/shops/ladamadelasllamas/gaston.webp" alt="Gaston"></div>
        <div class="master-copy">
            <span class="role physical-role">Herrero principal</span>
            <h3>Gaston</h3>
            <p>Es quien representa la herrería tradicional: metal, oficio y criterio de herrero. Si una pieza necesita pasar por el yunque antes de llegar a las manos de un aventurero, este es su banco.</p>
            <div class="master-tags"><span>Forja</span><span>Armas y armaduras</span><span>Tasación</span></div>
        </div>
    </article>

    <article class="master-card axe-card">
        <div class="master-copy">
            <span class="role arcane-role">Efectos mágicos y mejoras arcanas</span>
            <h3>Axe Rein</h3>
            <p>El lado más experimental del taller. Axe trabaja sobre piezas ya existentes para añadir efectos, utilidades y mejoras mágicas. Su banco se reconoce rápido: suele ser el que brilla.</p>
            <div class="master-tags arcane-tags"><span>Runas</span><span>Encantamientos</span><span>Mejoras +1 / +2</span></div>
        </div>
        <div class="master-art axe-art"><img src="../../media/shops/ladamadelasllamas/axe-rein.webp" alt="Axe Rein"></div>
    </article>
</section>

<section class="development-section" id="proyecto-axe">
    <div class="development-inner">
        <div class="development-sigil" aria-hidden="true">
            <span>✦</span><i></i><i></i><i></i>
        </div>
        <div class="development-copy">
            <span class="status-badge"><b></b> EN DESARROLLO</span>
            <p class="eyebrow">Banco experimental de Axe</p>
            <h2>Una nueva idea del chico</h2>
            <p>Axe está trabajando en algo nuevo para ampliar lo que puede ofrecer la herrería. Todavía no hay precio, reglas ni producto terminado: por ahora permanece como un proyecto interno del taller.</p>
            <p class="development-note">Cuando decidamos qué es exactamente, esta sección está preparada para convertirse en una ficha, servicio o sistema nuevo sin rehacer la página.</p>
        </div>
        <div class="blueprint" aria-hidden="true">
            <div class="bp-ring"></div>
            <div class="bp-line l1"></div>
            <div class="bp-line l2"></div>
            <div class="bp-line l3"></div>
            <span>AXE / PROTOTIPO</span>
        </div>
    </div>
</section>
</main>

<footer class="footer">
    <span>La Dama de las Llamas · Herrería del Favor de Jack</span>
    <a href="<?= favor_base_path() ?>mapa/">Volver al mapa de Waterdeep</a>
</footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ficha">×</button>
        <div id="modalContent"></div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script id="shopData" type="application/json"><?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>" defer></script>
</body>
</html>
