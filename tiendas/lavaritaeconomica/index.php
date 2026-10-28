<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth-widget.php';
require_once dirname(__DIR__, 2) . '/includes/tiendas.php';
favor_session_start();
$compra = favor_tienda_client($GLOBALS['__favor_auth_widget_user'] ?? null, 'varita-economica');

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

$shopData = [
    'products' => $products,
    'shop' => [
        'name' => 'La Varita Económica',
        'sellerA' => 'El Señor Blanco',
        'sellerB' => 'Pinkman',
        'backUrl' => favor_base_path() . 'mapa/',
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090d0a">
<title>La Varita Económica · Favor de Jack</title>
<meta name="description" content="Mercado clandestino de pergaminos, brebajes, objetos mágicos dudosos y mercancía defectuosa.">
<link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
<?php favor_head('La Varita Económica · Favor de Jack', 'Pergaminos baratos y magia de procedencia cuestionable.'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<div class="noise" aria-hidden="true"></div>

<header class="topbar">
    <span class="back-link" aria-hidden="true"></span>
    <a class="brand" href="#inicio">
        <span class="brand-symbol">Vₑ</span>
        <span><strong>La Varita Económica</strong><small>Sin factura · Sin preguntas</small></span>
    </a>
    <nav class="topnav">
        <a href="#mercancia">Mercancía</a>
        <a href="#laboratorio">Laboratorio</a>
        <a href="#vendedores">Blanco / Pinkman</a>
        <button class="music-button" id="musicButton" type="button" aria-pressed="false"><span>♫</span> Música</button>
        <button class="favorites-button" id="favoritesButton" type="button" aria-pressed="false">♥ <b id="favoriteCount">0</b></button>
    </nav>
</header>

<audio id="backgroundMusic" loop preload="none">
    <source src="assets/tema.mp3" type="audio/mpeg">
</audio>

<main>
<section class="hero" id="inicio">
    <img class="hero-bg" src="assets/interior-varita-economica.webp" alt="Interior de La Varita Económica">
    <div class="hero-shade"></div>
    <div class="hazard-band top" aria-hidden="true"></div>

    <div class="hero-copy">
        <div class="contraband-stamp">MERCANCÍA NO DECLARADA</div>
        <p class="eyebrow">Debajo de la mesa · probablemente legal en algún plano</p>

        <h1 class="chemical-title" aria-label="La Varita Económica">
            <span class="chem-word" aria-hidden="true"><span class="chem-box"><b>Va</b><small>??</small></span><span class="chem-rest">rita</span></span>
            <span class="chem-word" aria-hidden="true"><span class="chem-box green"><b>Ec</b><small>Au</small></span><span class="chem-rest">onómica</span></span>
        </h1>

        <p class="hero-lead">Pergaminos baratos, magia de procedencia cuestionable, mezclas experimentales, mercancía restringida y objetos cuyo defecto viene incluido en el precio.</p>

        <div class="seller-quotes">
            <blockquote><b>BLANCO:</b> “La fórmula es correcta. El problema casi siempre es el usuario.”</blockquote>
            <blockquote class="pink"><b>PINKMAN:</b> “Bro, prende esto… creo que es seguro.”</blockquote>
        </div>

        <div class="hero-actions">
            <a class="btn acid" href="#mercancia">Ver lo que hay</a>
            <a class="btn dirty" href="#laboratorio">Ir al laboratorio</a>
        </div>

        <div class="stats-tape">
            <span><b>95</b> cosas en venta</span>
            <span><b>12</b> defectuosas</span>
            <span><b>7</b> restringidas</span>
            <span><b>0</b> garantías</span>
        </div>
    </div>

    <div class="hero-vendors">
        <div class="vendor vendor-white">
            <img src="../../media/shops/lavaritaeconomica/blanco.webp" alt="El Señor Blanco">
            <div class="name-tag white"><small>EL SEÑOR</small><strong>BLANCO</strong></div>
        </div>
        <div class="vendor vendor-pink">
            <img src="../../media/shops/lavaritaeconomica/pinkman.webp" alt="Pinkman">
            <div class="name-tag pink"><small>SOCIO / APRENDIZ</small><strong>PINKMAN</strong></div>
        </div>
    </div>
</section>

<?php if ($dataError): ?>
<div class="data-error" role="alert"><?= htmlspecialchars($dataError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="warning-strip">
    <span>⚠ NO HAY DEVOLUCIONES</span>
    <span>⚗ NO LAMER EL CONTENIDO</span>
    <span>✦ “DEFECTUOSO” NO SIGNIFICA “INÚTIL”</span>
    <span>☠ PINKMAN NO DA SOPORTE TÉCNICO</span>
</section>

<section class="inventory-section" id="mercancia">
    <div class="section-head crooked">
        <div>
            <span class="marker">LISTA QUE NO EXISTE</span>
            <h2>Mercancía</h2>
        </div>
        <p>Está ordenado porque es una página web. No porque Blanco y Pinkman sepan dónde dejaron las cosas.</p>
    </div>

    <div class="desk-controls">
        <div class="torn-note">
            <b>BUSCAR:</b>
            <label class="search-box"><span>⌕</span><input id="searchInput" type="search" placeholder="Nombre, efecto, maldición…" autocomplete="off"></label>
        </div>

        <div class="torn-note small">
            <b>ORDENAR:</b>
            <select id="sortSelect">
                <option value="default">Como cayó de la caja</option>
                <option value="name-asc">A–Z</option>
                <option value="price-asc">Más barato</option>
                <option value="price-desc">Más caro</option>
            </select>
        </div>
    </div>

    <div class="filter-wall" id="categoryTabs">
        <button class="filter-chip active" type="button" data-category="Todos">Todos<b>95</b></button>
<button class="filter-chip" type="button" data-category="Pergaminos">Pergaminos<b>16</b></button>
<button class="filter-chip" type="button" data-category="Tatuajes">Tatuajes<b>3</b></button>
<button class="filter-chip" type="button" data-category="Brebajes">Brebajes<b>5</b></button>
<button class="filter-chip" type="button" data-category="Mezclas especiales">Mezclas especiales<b>3</b></button>
<button class="filter-chip" type="button" data-category="Sustancias">Sustancias<b>7</b></button>
<button class="filter-chip" type="button" data-category="Objetos mágicos?">Objetos mágicos?<b>49</b></button>
<button class="filter-chip" type="button" data-category="Defectuosos">Defectuosos<b>12</b></button>
    </div>

    <div class="results-line">
        <span id="resultsCount">95 objetos encontrados</span>
        <button id="clearFilters" type="button" hidden>Quitar filtros</button>
    </div>

    <div class="product-grid" id="productGrid"></div>
    <div class="empty-state" id="emptyState" hidden>
        <strong>Pinkman juraba que había uno.</strong>
        <span>Busca otra cosa. O vuelve mañana.</span>
    </div>
</section>

<section class="lab-section" id="laboratorio">
    <div class="lab-wall">
        <div class="lab-title">
            <span class="hazard-icon">⚗</span>
            <div><p class="eyebrow">Detrás de la cortina</p><h2>El laboratorio</h2></div>
        </div>

        <div class="lab-grid">
            <article class="lab-note white-note">
                <span class="tape"></span>
                <small>BLANCO / INVENTARIO CONTROLADO</small>
                <h3>Pergaminos por encargo</h3>
                <p>Si un pergamino no está disponible, puede obtenerse bajo encargo por el costo correspondiente.</p>
                <dl>
                    <div><dt>Cantrip</dt><dd>25 gp</dd></div>
                    <div><dt>Nivel 1</dt><dd>50 gp</dd></div>
                    <div><dt>Nivel 2</dt><dd>100 gp</dd></div>
                    <div><dt>Nivel 3</dt><dd>200 gp</dd></div>
                    <div><dt>Nivel 4</dt><dd>250 gp</dd></div>
                </dl>
                <em>White jura que no puede bajarlos más.</em>
            </article>

            <article class="lab-note pink-note">
                <span class="tape pink-tape"></span>
                <small>PINKMAN / INVENTARIO “FLEXIBLE”</small>
                <h3>Si no está aquí…</h3>
                <p>“Bro, si no está aquí, yo te lo traigo. Tú pagas, yo lo consigo. Así de simple.”</p>
                <div class="scribble">NO PREGUNTAR<br>DÓNDE.</div>
            </article>

            <article class="lab-note danger-note">
                <span class="tape"></span>
                <small>ADVERTENCIA</small>
                <h3>Sustancias</h3>
                <p>Las fichas incluyen sus efectos, tiradas de adicción y consecuencias exactamente porque este puesto vende mercancía bastante menos inocente que una varita barata.</p>
                <div class="risk-bars"><i></i><i></i><i></i><i></i><i></i></div>
            </article>
        </div>

        <div class="featured-cards">
            <figure><img src="assets/cartas/ira-del-barbaro.webp" alt="Carta Ira del Bárbaro"><figcaption>Ira del Bárbaro</figcaption></figure>
            <figure><img src="assets/cartas/acelerador.webp" alt="Carta Acelerador"><figcaption>Acelerador</figcaption></figure>
            <figure><img src="assets/cartas/mentas-del-bardo.webp" alt="Carta Mentas del Bardo"><figcaption>Mentas del Bardo</figcaption></figure>
        </div>
    </div>
</section>

<section class="vendors-section" id="vendedores">
    <article class="seller-profile white-profile">
        <div class="seller-art"><img src="../../media/shops/lavaritaeconomica/blanco.webp" alt="El Señor Blanco"></div>
        <div class="seller-copy">
            <span class="evidence-label">SUJETO A</span>
            <h2>El Señor Blanco</h2>
            <p>Un mago obsesionado con “la fórmula perfecta”. Habla como si todo el mundo fuera su aprendiz fallido. Revisa cada pergamino con la misma seriedad que un médico viendo una autopsia. Si le compras algo, te da instrucciones que nadie pidió.</p>
        </div>
    </article>

    <article class="seller-profile pink-profile">
        <div class="seller-copy">
            <span class="evidence-label pink-label">SUJETO B</span>
            <h2>Pinkman</h2>
            <p>Un aprendiz demasiado emocionado para estar a cargo de magia peligrosa. Sonríe, vende rápido y no sabe cómo funcionan la mitad de las cosas que ofrece.</p>
            <blockquote>“Bro, prende esto… creo que es seguro.”</blockquote>
        </div>
        <div class="seller-art"><img src="../../media/shops/lavaritaeconomica/pinkman.webp" alt="Pinkman"></div>
    </article>
</section>
</main>

<footer class="footer">
    <span>La Varita Económica · Si preguntan, nunca estuvimos aquí.</span>
    <a href="<?= favor_base_path() ?>mapa/">← Volver al mapa</a>
</footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Cerrar">×</button>
        <div id="modalContent"></div>
    </div>
</div>

<div class="toast" id="toast" aria-live="polite"></div>

<script id="shopData" type="application/json"><?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script id="favorCompra" type="application/json"><?= json_encode($compra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="../compra.js<?= favor_v(dirname(__DIR__) . '/compra.js') ?>" defer></script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>" defer></script>
</body>
</html>
