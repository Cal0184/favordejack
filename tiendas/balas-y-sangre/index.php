<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
require_once dirname(__DIR__, 2) . '/includes/tiendas.php';
favor_session_start();
$compra = favor_tienda_client($GLOBALS['__favor_auth_widget_user'] ?? null, 'balas-y-sangre');
favor_session_close();

$productsPath = __DIR__ . '/productos.json';
$products = [];
$dataError = null;

if (is_file($productsPath)) {
    $decoded = json_decode((string) file_get_contents($productsPath), true);
    if (is_array($decoded)) {
        $products = favor_shop_drop_missing_images($decoded, __DIR__);
    } else {
        $dataError = 'No fue posible leer productos.json.';
    }
} else {
    $dataError = 'No se encontró productos.json.';
}

$counts = [
    'Armas de fuego' => 0,
    'Munición' => 0,
    'Pólvora' => 0,
    'Herramientas' => 0,
];
foreach ($products as $product) {
    $category = (string)($product['category'] ?? '');
    if (array_key_exists($category, $counts)) {
        $counts[$category]++;
    }
}

$shopData = [
    'products' => $products,
    'shop' => [
        'name' => 'Balas y Sangre',
        'seller' => 'Madeline Ironslinger',
        'backUrl' => favor_base_path(),
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17100b">
    <title>Balas y Sangre · Favor de Jack</title>
    <meta name="description" content="Armería-taller de Madeline Ironslinger. Armas de fuego, munición, pólvora y herramientas.">
    <link rel="icon" href="assets/placeholders/arma.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
<?php favor_head('Balas y Sangre · Favor de Jack', 'Armería, munición y herramientas en Waterdeep.'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<header class="topbar" id="topbar">
    <span class="back-link" aria-hidden="true"></span>

    <a class="brand" href="#inicio" aria-label="Ir al inicio de Balas y Sangre">
        <span class="brand-mark" aria-hidden="true"><i></i>B&amp;S<i></i></span>
        <span class="brand-copy">
            <strong>Balas y Sangre</strong>
            <small>Armería · Taller · Munición</small>
        </span>
    </a>

    <nav class="topnav" aria-label="Navegación de la tienda">
        <a href="#catalogo">Inventario</a>
        <a href="#reglas">Manual</a>
        <a href="#madeline">Madeline</a>
        <button class="favorites-button" id="favoritesButton" type="button" aria-pressed="false">
            <span class="heart" aria-hidden="true">♥</span>
            <span>Favoritos</span>
            <b id="favoriteCount">0</b>
        </button>
    </nav>
</header>

<main>
    <section class="hero" id="inicio" aria-labelledby="heroTitle">
        <img class="hero-bg" src="assets/interior-balas-y-sangre.webp" alt="Interior de la tienda Balas y Sangre">
        <div class="hero-shade" aria-hidden="true"></div>
        <div class="hero-grain" aria-hidden="true"></div>

        <div class="hero-content">
            <div class="forge-status"><span></span>Taller abierto · Inventario de Madeline</div>
            <p class="eyebrow">Armería de oficio · Pólvora · Herramientas</p>
            <div class="shop-sign">
                <span class="sign-bolt sign-bolt-a" aria-hidden="true"></span>
                <span class="sign-bolt sign-bolt-b" aria-hidden="true"></span>
                <h1 id="heroTitle">Balas <em>y</em> Sangre</h1>
                <p>Trabajo honesto. Acero recto. Pólvora seca.</p>
            </div>
            <p class="hero-copy">Equipo para aventureros que confían más en la puntería, el acero y sus propias manos que en un hechizo oportuno.</p>
            <div class="hero-actions">
                <a class="button primary" href="#catalogo"><span aria-hidden="true">◆</span> Revisar inventario</a>
                <a class="button ghost" href="#madeline">Hablar con Madeline</a>
            </div>
            <div class="shop-stats" aria-label="Resumen del catálogo">
                <div><span class="stat-kicker">Inventario</span><strong><?= count($products) ?></strong><span>Productos</span></div>
                <div><span class="stat-kicker">Armería</span><strong><?= $counts['Armas de fuego'] ?></strong><span>Armas</span></div>
                <div><span class="stat-kicker">Polvorín</span><strong><?= $counts['Munición'] ?></strong><span>Municiones</span></div>
                <div><span class="stat-kicker">Banco</span><strong><?= $counts['Herramientas'] ?></strong><span>Herramientas</span></div>
            </div>
        </div>

        <div class="seller-stage">
            <div class="seller-quote-card" aria-live="polite">
                <span class="quote-label">Madeline dice</span>
                <p id="heroSellerQuote">“Menos palabras. Más puntería.”</p>
            </div>
            <img src="../../media/shops/balas-y-sangre/madeline-ironslinger.webp" alt="Madeline Ironslinger sosteniendo un arma de fuego">
            <div class="seller-nameplate" aria-hidden="true">
                <small>Maestra del taller</small>
                <strong>Madeline Ironslinger</strong>
            </div>
        </div>

        <div class="bench-edge" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>
    </section>

    <?php if ($dataError): ?>
        <div class="data-error" role="alert"><?= htmlspecialchars($dataError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="catalog-section" id="catalogo" aria-labelledby="catalogTitle">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Banco de trabajo · Inventario disponible</p>
                <h2 id="catalogTitle">El banco de Madeline</h2>
                <div class="heading-rule" aria-hidden="true"><span></span></div>
            </div>
            <p class="section-note">Cada pieza está catalogada para consulta. Por ahora, ninguna acción descuenta dinero de la Banca Digital ni modifica stock.</p>
        </div>

        <div class="workbench-console">
            <div class="console-title" aria-hidden="true">
                <span class="console-rivet"></span>
                <strong>Registro de piezas</strong>
                <span class="console-rivet"></span>
            </div>
            <div class="catalog-toolbar" aria-label="Filtros del catálogo">
                <label class="search-box">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <span class="sr-only">Buscar objeto</span>
                    <input id="searchInput" type="search" placeholder="Buscar arma, munición o herramienta…" autocomplete="off">
                </label>
                <label class="sort-box">
                    <span class="sr-only">Ordenar catálogo</span>
                    <select id="sortSelect" aria-label="Ordenar catálogo">
                        <option value="default">Orden del catálogo</option>
                        <option value="name-asc">Nombre A–Z</option>
                        <option value="price-asc">Precio menor primero</option>
                        <option value="price-desc">Precio mayor primero</option>
                    </select>
                </label>
            </div>

            <div class="category-tabs" id="categoryTabs" role="group" aria-label="Categorías">
                <button class="category-tab active" type="button" data-category="Todos"><i aria-hidden="true">◆</i> Todo el banco <span><?= count($products) ?></span></button>
                <button class="category-tab" type="button" data-category="Armas de fuego"><i aria-hidden="true">†</i> Armas <span><?= $counts['Armas de fuego'] ?></span></button>
                <button class="category-tab" type="button" data-category="Munición"><i aria-hidden="true">•</i> Munición <span><?= $counts['Munición'] ?></span></button>
                <button class="category-tab" type="button" data-category="Pólvora"><i aria-hidden="true">✦</i> Pólvora <span><?= $counts['Pólvora'] ?></span></button>
                <button class="category-tab" type="button" data-category="Herramientas"><i aria-hidden="true">⚒</i> Herramientas <span><?= $counts['Herramientas'] ?></span></button>
            </div>
        </div>

        <div class="results-line">
            <span><i aria-hidden="true"></i><b id="resultsCount"><?= count($products) ?> productos</b> sobre la mesa</span>
            <button class="clear-filters" id="clearFilters" type="button" hidden>Restablecer banco</button>
        </div>

        <div class="product-grid" id="productGrid" aria-live="polite"></div>
        <div class="empty-state" id="emptyState" hidden>
            <span class="empty-emblem" aria-hidden="true">⌁</span>
            <strong>No hay nada con esa marca.</strong>
            <span>Prueba otro nombre o abre otro cajón del taller.</span>
        </div>
    </section>

    <section class="rules-section" id="reglas" aria-labelledby="rulesTitle">
        <div class="section-heading compact">
            <div>
                <p class="eyebrow">Manual clavado a la pared</p>
                <h2 id="rulesTitle">Reglas del taller</h2>
                <div class="heading-rule" aria-hidden="true"><span></span></div>
            </div>
            <p class="section-note">Las fichas muestran Recarga y Fallo. Este es el resumen que Madeline espera que leas antes de romper algo caro.</p>
        </div>

        <div class="rules-board">
            <article class="rule-card">
                <span class="rule-pin" aria-hidden="true"></span>
                <span class="rule-number">01</span>
                <h3>Recargar</h3>
                <p>El arma se puede disparar un número de veces igual a su puntuación de recarga antes de que debas gastar 1 ataque o 1 acción para recargar. Debes tener una mano libre para hacerlo.</p>
            </article>
            <article class="rule-card">
                <span class="rule-pin" aria-hidden="true"></span>
                <span class="rule-number">02</span>
                <h3>Fallar</h3>
                <p>Si la tirada de ataque es igual o inferior a la puntuación de Fallo, el ataque falla y el arma queda inutilizable hasta intentar repararla con Herramientas de Tinker. La CD es 8 + la puntuación de fallo.</p>
            </article>
            <article class="rule-card">
                <span class="rule-pin" aria-hidden="true"></span>
                <span class="rule-number">03</span>
                <h3>Sin competencia</h3>
                <p>Una criatura que use un arma de fuego sin ser competente aumenta la puntuación de Fallo del arma en 1.</p>
            </article>
            <article class="rule-card special-rule">
                <span class="rule-pin" aria-hidden="true"></span>
                <span class="rule-number">04</span>
                <h3>Mecanismos especiales</h3>
                <p>Pistola Espada, Hacha Pistola, Colt 1851, Lemat y Gatling incluyen reglas propias. Inspecciona su ficha antes de usarlas.</p>
            </article>
        </div>
    </section>

    <section class="seller-section" id="madeline" aria-labelledby="sellerTitle">
        <div class="seller-art-panel">
            <div class="seller-art-bg" aria-hidden="true"></div>
            <div class="seller-silhouette" aria-hidden="true"></div>
            <img src="../../media/shops/balas-y-sangre/madeline-ironslinger.webp" alt="Madeline Ironslinger, vendedora de Balas y Sangre">
            <div class="seller-stamp" aria-hidden="true">B&amp;S</div>
        </div>

        <div class="seller-copy">
            <p class="eyebrow">Tras el mostrador</p>
            <h2 id="sellerTitle">Madeline<br><span>Ironslinger</span></h2>
            <p class="seller-role">Veterana · Armera · Comerciante</p>
            <p class="seller-lead">Firme, reservada y marcada por su experiencia militar. Desde que estableció la tienda se ha permitido hablar con más libertad y expresar su opinión sin demasiados filtros.</p>

            <div class="seller-facts">
                <article>
                    <span>Especialidad</span>
                    <strong>Armas, pólvora y equipo práctico</strong>
                </article>
                <article>
                    <span>Filosofía</span>
                    <strong>Habilidad antes que magia</strong>
                </article>
                <article>
                    <span>Busca</span>
                    <strong>Recursos escasos de las planicies</strong>
                </article>
            </div>

            <div class="seller-dialogue">
                <span class="dialogue-screw screw-a" aria-hidden="true"></span>
                <span class="dialogue-screw screw-b" aria-hidden="true"></span>
                <blockquote id="sellerQuoteText">“Si puedes resolverlo con tus manos, ¿para qué abrir la boca y esperar que un hechizo haga el trabajo?”</blockquote>
                <button id="sellerQuoteButton" class="quote-button" type="button">Otra opinión de Madeline</button>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <span><b>Balas y Sangre</b> · Catálogo digital del Favor de Jack</span>
    <span class="footer-mark" aria-hidden="true">◆</span>
    <a href="<?= favor_base_path() ?>mapa/">Volver al mapa de Waterdeep</a>
</footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-rail" aria-hidden="true"><span></span><b>Mesa de inspección</b><span></span></div>
        <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ficha">×</button>
        <div id="modalContent"></div>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script id="shopData" type="application/json"><?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script id="favorCompra" type="application/json"><?= json_encode($compra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="../compra.js<?= favor_v(dirname(__DIR__) . '/compra.js') ?>" defer></script>
<script src="../tienda.js<?= favor_v(dirname(__DIR__) . '/tienda.js') ?>" defer></script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>" defer></script>
</body>
</html>
