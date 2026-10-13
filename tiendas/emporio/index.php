<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

const CARTAS_DIR = __DIR__ . '/Cartas';
const DATA_DIR = __DIR__ . '/data';
const CATALOG_FILE = DATA_DIR . '/catalog.json';
const SERVICES_FILE = DATA_DIR . '/services.json';
const OVERRIDES_FILE = DATA_DIR . '/item_overrides.json';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $value): string {
    $value = pathinfo($value, PATHINFO_FILENAME);
    $value = mb_strtolower($value, 'UTF-8');

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') $value = $ascii;

    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
    return trim($value, '-');
}

function catalog_aliases(): array {
    return [
        'abrochar-de-la-profundidad' => 'antorcha-de-la-profundidad',
        'aceite-de-resbaladicida' => 'aceite-de-resbaladicidad',
        'cuerda-escalada' => 'cuerda-de-escalada',
        'cuarda-de-escalada' => 'cuerda-de-escalada',
        'guantes-termanles' => 'guantes-termales',
        'anillo-catapulta' => 'anillo-de-catapulta',
        'bronntanas' => 'bronntanas-blade',
        'cinturon-de-fuerza-de-gigante' => 'cinturon-de-fuerza-de-gigante-fuerza-21',
        'tunica-del-horador' => 'tunica-del-orador',
        'guijarro-de-1-tonelada' => 'piedra-de-una-tonelada',
    ];
}

function canonical_slug(string $value): string {
    $slug = slugify($value);
    $aliases = catalog_aliases();
    return $aliases[$slug] ?? $slug;
}

function ignored_card_slugs(): array {
    return [
        'guantes-de-poder-del-ogro',
        'guantes-de-fuerza-de-ogro',
        'guantes-de-poder-de-ogro',
    ];
}

function pretty_name(string $file): string {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $name = preg_replace('/[_\-]+/u', ' ', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = trim($name);

    if ($name !== '' && function_exists('mb_strtolower') && $name === mb_strtolower($name, 'UTF-8')) {
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    return $name !== '' ? $name : 'Objeto sin nombre';
}

function load_json_file(string $file, array $fallback = []): array {
    if (!is_file($file)) return $fallback;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $fallback;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

/**
 * Acepta tanto el catálogo antiguo en forma de lista:
 * [ {"id":"..."}, ... ]
 * como el formato nuevo indexado por ID:
 * { "id": { ... } }
 */
function normalize_catalog(array $raw): array {
    $out = [];

    foreach ($raw as $key => $row) {
        if (!is_array($row)) continue;

        $rawId = trim((string)($row['id'] ?? (is_string($key) ? $key : '')));
        if ($rawId === '') continue;

        $id = canonical_slug($rawId);
        if ($id === '') continue;

        $row['id'] = $id;
        $out[$id] = isset($out[$id]) && is_array($out[$id])
            ? array_replace($out[$id], $row)
            : $row;

    }

    return $out;
}

function card_url(string $file): string {
    return 'Cartas/' . rawurlencode($file);
}

function value_from_sources(array $saved, array $override, string $field, mixed $fallback): mixed {
    // El catálogo administrable manda. Overrides solo completa datos ausentes.
    if (array_key_exists($field, $saved) && $saved[$field] !== '' && $saved[$field] !== null) {
        return $saved[$field];
    }
    if (array_key_exists($field, $override) && $override[$field] !== '' && $override[$field] !== null) {
        return $override[$field];
    }
    return $fallback;
}

function scan_cards(array $catalog, array $overrides): array {
    if (!is_dir(CARTAS_DIR)) return [];

    $allowed = ['png','webp','jpg','jpeg','gif'];
    $extensionPriority = ['webp' => 5, 'png' => 4, 'jpg' => 3, 'jpeg' => 2, 'gif' => 1];
    $candidates = [];

    foreach (scandir(CARTAS_DIR) ?: [] as $file) {
        if ($file === '.' || $file === '..') continue;

        $path = CARTAS_DIR . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;

        $fileSlug = slugify($file);
        if ($fileSlug === '' || in_array($fileSlug, ignored_card_slugs(), true)) continue;

        $id = canonical_slug($fileSlug);

        // Si existen PNG y WebP del mismo objeto, usamos una sola imagen de forma determinista.
        $priority = $extensionPriority[$ext] ?? 0;
        if (isset($candidates[$id]) && ($candidates[$id]['priority'] ?? 0) >= $priority) continue;
        $candidates[$id] = ['file' => $file, 'priority' => $priority];
    }

    $cards = [];
    foreach ($candidates as $id => $candidate) {
        $file = (string)$candidate['file'];
        $pretty = pretty_name($file);
        $prettySlug = canonical_slug($pretty);

        $saved = $catalog[$id] ?? $catalog[$prettySlug] ?? [];
        if (!is_array($saved)) $saved = [];

        $override = $overrides[$id] ?? $overrides[$prettySlug] ?? [];
        if (!is_array($override)) $override = [];

        $name = trim((string)value_from_sources($saved, $override, 'name', $pretty));
        if ($name === '') $name = $pretty;

        $category = trim((string)value_from_sources($saved, $override, 'category', 'Sin categoría'));
        if ($category === '') $category = 'Sin categoría';

        $price = trim((string)value_from_sources($saved, $override, 'price', '?'));
        if ($price === '') $price = '?';

        $stockRaw = value_from_sources($saved, $override, 'stock', 1);
        $stock = max(0, (int)$stockRaw);

        $description = trim((string)value_from_sources($saved, $override, 'description', ''));
        if ($description === '') {
            $description = 'Ecix’s todavía no ha terminado de registrar las propiedades de este objeto.';
        }

        $availableRaw = value_from_sources($saved, $override, 'available', true);
        $available = (bool)$availableRaw;

        $cards[] = [
            'id' => $id,
            'file' => $file,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'stock' => $stock,
            'description' => $description,
            'available' => $available,
            'url' => card_url($file),
        ];
    }

    usort($cards, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
    return $cards;
}

$catalog = normalize_catalog(load_json_file(CATALOG_FILE));
$overrides = normalize_catalog(load_json_file(OVERRIDES_FILE));
$services = load_json_file(SERVICES_FILE);
$cards = scan_cards($catalog, $overrides);
$categories = array_values(array_unique(array_column($cards, 'category')));
natcasesort($categories);
$categories = array_values($categories);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#130d09">
<title>El Emporio de Ecix's</title>
<style>
:root{
    --bg:#0d0907;--paper:#e7d2a7;--paper2:#f4e4bf;--ink:#352317;
    --gold:#c0934d;--gold2:#f0cd85;--muted:#a6967f;--line:#5d4228;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;background:var(--bg)}
body{margin:0;min-height:100vh;color:#eadcc0;font-family:Georgia,"Times New Roman",serif;background:radial-gradient(circle at 50% -10%,#51331d 0,transparent 38rem),linear-gradient(180deg,#130d09,#090705)}
body.modal-open{overflow:hidden}
button,input,select{font:inherit}button{cursor:pointer}a{color:inherit;text-decoration:none}
.site-header{position:sticky;top:0;z-index:100;min-height:76px;display:grid;grid-template-columns:auto 1fr auto;gap:20px;align-items:center;padding:12px clamp(16px,4vw,58px);border-bottom:1px solid #604629;background:#100b08ef;backdrop-filter:blur(12px);box-shadow:0 10px 30px #0006}
.brand{display:flex;align-items:center;gap:13px}.brand-mark{width:50px;height:50px;display:grid;place-items:center;border:1px solid #b98542;border-radius:50%;color:var(--gold2);background:radial-gradient(circle,#402715,#17100b 72%);font-size:1.55rem}
.brand-copy small{display:block;margin-bottom:2px;color:#9d8b72;font:700 .62rem/1.3 system-ui,sans-serif;letter-spacing:.15em;text-transform:uppercase}.brand-copy h1{margin:0;color:#e4c17d;font-size:clamp(1.15rem,2vw,1.65rem);font-weight:600}
.header-actions{justify-self:end;display:flex;align-items:center;gap:8px;flex-wrap:wrap}.pill,.header-actions a,.header-actions button{padding:8px 11px;border:1px solid #4d3926;border-radius:999px;background:#17100c;color:#ad9c84;font:600 .74rem system-ui,sans-serif}.header-actions button:hover,.header-actions a:hover{border-color:#866039;color:#ead0a0}
.hero{position:relative;min-height:300px;display:grid;place-items:center;padding:58px 20px 76px;text-align:center;border-bottom:1px solid #392719;overflow:hidden}.hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 18%,#9d672638 0,transparent 24rem),linear-gradient(#0c080574,#0c0805e8)}.hero-inner{position:relative;z-index:1;max-width:870px}
.eyebrow{margin:0 0 13px;color:#c49d61;font:800 .69rem/1.3 system-ui,sans-serif;letter-spacing:.23em;text-transform:uppercase}.hero h2{margin:0;color:#f1d69f;font-size:clamp(3rem,7vw,6rem);line-height:.88;font-weight:500;text-shadow:0 8px 24px #000}.hero p:not(.eyebrow){width:min(650px,100%);margin:23px auto 0;color:#bfae92;font-size:clamp(1rem,2vw,1.15rem);line-height:1.55}
.catalog-shell{width:min(1450px,94vw);margin:0 auto;padding:36px 0 90px}.catalog-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.catalog-head h2{margin:3px 0 0;color:#e7ca8c;font-size:clamp(2rem,4vw,3.1rem);font-weight:500}.catalog-note{max-width:560px;color:#94856f;text-align:right;line-height:1.45}

/* Buscador y filtros: separados de las acciones globales */
.filter-panel{position:sticky;top:88px;z-index:50;margin-bottom:12px;padding:13px;border:1px solid #4b3825;border-radius:14px;background:linear-gradient(145deg,#16100cF5,#0f0b08F5);backdrop-filter:blur(14px);box-shadow:0 14px 34px #0007}
.filter-grid{display:grid;grid-template-columns:minmax(300px,1.6fr) minmax(190px,.7fr) minmax(165px,.55fr);gap:10px;align-items:end}
.field{display:grid;gap:6px}.field-label{color:#95836b;font:800 .63rem/1 system-ui,sans-serif;letter-spacing:.13em;text-transform:uppercase}
.search-box{position:relative;display:flex;align-items:center;min-height:50px;border:1px solid #654927;border-radius:10px;background:#090604;box-shadow:inset 0 0 0 1px #0008;transition:.18s}
.search-box:focus-within{border-color:#bd8a49;box-shadow:0 0 0 3px #a8722b1f,inset 0 0 0 1px #0008}.search-icon{position:absolute;left:15px;color:#c19558;font-size:1.25rem;pointer-events:none}.search-box input{width:100%;height:48px;padding:0 44px 0 45px;border:0;outline:0;background:transparent;color:#f2dfbd}.search-box input::placeholder{color:#6f6253}.search-clear{position:absolute;right:7px;width:34px;height:34px;border:0;border-radius:50%;background:transparent;color:#796c5c;font-size:1.15rem}.search-clear:hover{background:#21160f;color:#e3c28b}.search-clear[hidden]{display:none}
.filter-panel select{width:100%;height:50px;padding:0 38px 0 12px;border:1px solid #493623;border-radius:10px;background:#0b0806;color:#f2dfbd;outline:0}.filter-panel select:focus{border-color:#bd8a49}
.catalog-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 24px;padding:0 2px}.result-state{color:#8e806d;font:600 .76rem system-ui,sans-serif}.action-group{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.tool-btn{min-height:40px;padding:0 14px;border:1px solid #493623;border-radius:999px;background:#17100c;color:#b9a88f;font:700 .76rem system-ui,sans-serif}.tool-btn:hover{border-color:#805c35;color:#e7cc9c}.tool-btn.active{border-color:#ae8041;color:#efd098;background:#2b1b10}.tool-btn.secondary{color:#887b6b}.tool-btn:disabled{opacity:.45;cursor:not-allowed}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}.card{position:relative;overflow:hidden;display:flex;flex-direction:column;min-height:430px;border:1px solid #3f3021;border-radius:11px;background:linear-gradient(150deg,#1b130f,#100c09);box-shadow:0 12px 30px #0005;transition:.2s}.card:hover{transform:translateY(-6px);border-color:#896438;box-shadow:0 20px 38px #0009}.card.selected{border-color:#d09a4d;box-shadow:0 0 0 1px #d09a4d55,0 20px 38px #0009}.card.favorite::after{content:"♥";position:absolute;top:10px;right:10px;z-index:2;width:32px;height:32px;display:grid;place-items:center;border:1px solid #9b6e42;border-radius:50%;background:#140d09e8;color:#d78378}
.card.soldout .card-image img{filter:grayscale(.8) brightness(.55)}.card.soldout::before{content:"AGOTADO";position:absolute;left:-48px;top:26px;z-index:5;width:180px;text-align:center;padding:7px 0;transform:rotate(-35deg);background:#772f2b;color:#fff0dd;border:1px solid #a85a51;font:800 .72rem system-ui,sans-serif;letter-spacing:.12em}
.card-image{position:relative;width:100%;aspect-ratio:5/7;overflow:hidden;display:grid;place-items:center;padding:8px;border:0;border-bottom:1px solid #3d2c1d;background:radial-gradient(circle,#392515 0,#100b08 67%)}.card-image::after{content:"Ver detalles";position:absolute;left:50%;bottom:14px;transform:translate(-50%,8px);padding:7px 11px;border:1px solid #8c6337;border-radius:999px;background:#120c08e8;color:#d9ba86;font:700 .7rem system-ui,sans-serif;opacity:0;transition:.2s;white-space:nowrap}.card:hover .card-image::after,.card-image:focus-visible::after{opacity:1;transform:translate(-50%,0)}.card-image img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 8px 12px #000a)}
.card-body{flex:1;display:flex;flex-direction:column;padding:14px}.category{display:inline-block;width:max-content;max-width:100%;margin-bottom:7px;padding:4px 8px;border:1px solid #5a442b;border-radius:99px;color:#b9a486;font:700 .65rem system-ui,sans-serif;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.card h3{margin:2px 0 10px;color:#e6d1aa;font-size:1.03rem;line-height:1.25;font-weight:600}.item-meta{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}.price{margin:0;color:#e0b26a;font-weight:700}.stock{font:700 .72rem system-ui,sans-serif;color:#9eb27c}.stock.zero{color:#d2847d}.detail-hint{margin:0 0 12px;color:#746858;font:600 .72rem/1.35 system-ui,sans-serif}
.card-actions{margin-top:auto;display:grid;grid-template-columns:40px 1fr;gap:6px}.card-actions button{min-height:38px;border:1px solid #483624;border-radius:7px;background:#17100c;color:#a99a84}.card-actions button:hover{border-color:#785632;color:#dfc08d}.favorite-btn.active{color:#e58d80;border-color:#8b4f48}.select-btn.active{background:#3a2515;color:#efce91;border-color:#a3763c}
.empty{grid-column:1/-1;padding:80px 20px;border:1px dashed #493824;border-radius:10px;text-align:center;color:#887a68}.no-results{display:none}.no-results.show{display:block}
.services{display:none}.services.active{display:block}.catalog-view.hidden{display:none}.services-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px}.service-card{padding:24px;border:1px solid #5a4128;border-radius:10px;background:linear-gradient(150deg,#1d140e,#110c09);box-shadow:0 12px 30px #0005}.service-card h3{margin:5px 0;color:#e7ca8c;font-size:1.4rem}.service-card .price{font-size:1.2rem}.service-card p{color:#ad9c84;line-height:1.6}
.modal{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;padding:20px;background:#000d;backdrop-filter:blur(8px)}.modal-card{position:relative;width:min(1000px,96vw);max-height:94vh;overflow:auto;border:1px solid #7c5931;border-radius:12px;background:linear-gradient(145deg,#21160f,#0f0b08);box-shadow:0 24px 70px #000b}.modal-close{position:sticky;top:12px;z-index:4;float:right;margin:12px 12px 0 0;width:42px;height:42px;border:1px solid #694a29;border-radius:50%;background:#17100ce8;color:#d8c097;font-size:1.3rem}.modal-close:hover{border-color:#b17b3e;color:#fff0ca}
.detail{display:grid;grid-template-columns:minmax(300px,45%) 1fr;gap:28px;padding:28px}.detail-image{min-height:520px;display:grid;place-items:center;padding:14px;border:1px solid #4b3724;border-radius:9px;background:radial-gradient(circle,#342216,#090705 72%)}.detail-image img{width:100%;max-height:75vh;object-fit:contain}.detail-copy{padding:20px 8px 20px 0}.detail-copy h2{margin:0 0 13px;color:#efd29c;font-size:clamp(2rem,4vw,3.6rem);line-height:.95;font-weight:500}.detail-price{font-size:1.4rem;color:#e0b26a;font-weight:700}.detail-section-title{margin:24px 0 10px;color:#c89f63;font:800 .67rem/1 system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase}.detail-description{color:#c2b29b;line-height:1.7}.detail-description p{margin:0 0 12px}.detail-description p:last-child{margin-bottom:0}.detail-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}.detail-actions button{padding:11px 15px;border:1px solid #604526;border-radius:7px;background:#1a110c;color:#d1bb94}.detail-actions button:hover{border-color:#9b6c37;color:#f1d29b}
.compare-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:28px}.compare-item{padding:15px;border:1px solid #503824;border-radius:9px;background:#100c09}.compare-item img{width:100%;height:min(55vh,520px);object-fit:contain;background:#090705}.compare-item h3{margin:13px 0 5px;color:#e4c58c;text-align:center}.compare-item .compare-meta{text-align:center;color:#b49d7a;margin:5px 0}.compare-description{margin-top:14px;padding-top:14px;border-top:1px solid #38271b;color:#9f907b;line-height:1.55;font-size:.86rem}
footer{padding:35px 20px 50px;border-top:1px solid #2f2117;color:#6f6456;text-align:center;font:600 .72rem system-ui,sans-serif}
@media(max-width:1000px){.filter-grid{grid-template-columns:1fr 1fr}.field.search-field{grid-column:1/-1}}
@media(max-width:850px){.site-header{grid-template-columns:1fr}.header-actions{justify-self:start}.catalog-head{display:block}.catalog-note{text-align:left;margin-top:8px}.detail{grid-template-columns:1fr}.detail-image{min-height:300px}.filter-panel{top:168px}.catalog-actions{align-items:flex-start;flex-direction:column}.action-group{justify-content:flex-start}}
@media(max-width:560px){.filter-grid{grid-template-columns:1fr}.field.search-field{grid-column:auto}.filter-panel{position:relative;top:auto}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.card{min-height:0}.card-body{padding:9px}.card h3{font-size:.84rem}.category{font-size:.58rem}.item-meta{align-items:flex-start;flex-direction:column}.detail-hint{display:none}.card-actions{grid-template-columns:34px 1fr}.compare-grid{grid-template-columns:1fr;padding:14px}.action-group{width:100%}.tool-btn{flex:1}.header-actions .pill{display:none}}
</style>
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="brand-mark">E</div>
        <div class="brand-copy">
            <small>Objetos extraordinarios · Tratos inolvidables</small>
            <h1>El Emporio de Ecix's</h1>
        </div>
    </div>
    <div></div>
    <div class="header-actions">
        <span class="pill"><b id="visibleCount"><?= count($cards) ?></b> objetos</span>
        <span class="pill">♥ <b id="favoriteCount">0</b></span>
        <span class="pill">⚖ <b id="compareCount">0</b>/2</span>
        <button id="catalogTab" type="button">Catálogo</button>
        <button id="servicesTab" type="button">Servicios</button>
        <a href="admin.php">Admin</a>
        <a href="/favordejack/">Volver</a>
    </div>
</header>

<section class="hero">
    <div class="hero-inner">
        <p class="eyebrow">El archivo arcano ha vuelto a abrirse</p>
        <h2>Todo tiene<br>un precio.</h2>
        <p>Reliquias, curiosidades y errores de la magia reunidos bajo un mismo techo.</p>
    </div>
</section>

<main class="catalog-shell">
    <section id="catalogView" class="catalog-view">
        <div class="catalog-head">
            <div><p class="eyebrow">Catálogo encantado</p><h2>Las mercancías</h2></div>
            <div class="catalog-note">La carta muestra solo lo esencial. Haz clic en la imagen para abrir la descripción completa y las propiedades del objeto.</div>
        </div>

        <div class="filter-panel" aria-label="Filtros del catálogo">
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
            </div>
        </div>

        <div class="catalog-actions">
            <div id="resultState" class="result-state">Mostrando <?= count($cards) ?> objetos.</div>
            <div class="action-group">
                <button id="favoritesToggle" class="tool-btn" type="button">♡ Ver favoritos</button>
                <button id="compareButton" class="tool-btn" type="button">⚖ Abrir comparador</button>
                <button id="clearButton" class="tool-btn secondary" type="button">↺ Limpiar filtros</button>
            </div>
        </div>

        <div id="grid" class="grid">
            <?php if (!$cards): ?><div class="empty">No encontré imágenes dentro de <code>Cartas/</code>.</div><?php endif; ?>

            <?php foreach ($cards as $card): ?>
                <?php $soldOut = (!$card['available'] || $card['stock'] <= 0); ?>
                <article
                    class="card <?= $soldOut ? 'soldout' : '' ?>"
                    data-id="<?= e($card['id']) ?>"
                    data-name="<?= e($card['name']) ?>"
                    data-category="<?= e($card['category']) ?>"
                    data-description="<?= e($card['description']) ?>"
                    data-availability="<?= $soldOut ? 'soldout' : 'available' ?>"
                >
                    <button class="card-image" type="button" aria-label="Ver detalles de <?= e($card['name']) ?>">
                        <img loading="lazy" src="<?= e($card['url']) ?>" alt="<?= e($card['name']) ?>">
                    </button>
                    <div class="card-body">
                        <span class="category"><?= e($card['category']) ?></span>
                        <h3><?= e($card['name']) ?></h3>
                        <div class="item-meta">
                            <p class="price"><?= e($card['price']) ?></p>
                            <span class="stock <?= $card['stock'] <= 0 ? 'zero' : '' ?>"><?= $card['stock'] <= 0 ? 'Agotado' : 'Stock: ' . $card['stock'] ?></span>
                        </div>
                        <p class="detail-hint">Haz clic en la carta para ver descripción y propiedades.</p>
                        <div class="card-actions">
                            <button class="favorite-btn" type="button" aria-label="Añadir a favoritos" title="Favorito">♥</button>
                            <button class="select-btn" type="button">Comparar</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <div id="noResults" class="empty no-results">No hay objetos que coincidan con esos filtros.</div>
        </div>
    </section>

    <section id="servicesView" class="services">
        <div class="catalog-head">
            <div><p class="eyebrow">Servicios de Ecix's</p><h2>Trabajos especiales</h2></div>
        </div>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <article class="service-card">
                    <p class="eyebrow">Servicio</p>
                    <h3><?= e((string)$service['name']) ?></h3>
                    <p class="price"><?= e((string)$service['price']) ?></p>
                    <p><?= e((string)$service['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<footer>El Emporio de Ecix's · Catálogo arcano de Favor de Jack</footer>

<script>
(() => {
'use strict';

const DATA = <?= json_encode(array_column($cards, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const cards = [...document.querySelectorAll('.card')];
const search = document.querySelector('#search');
const searchClear = document.querySelector('#searchClear');
const category = document.querySelector('#category');
const availability = document.querySelector('#availability');
const favoritesToggle = document.querySelector('#favoritesToggle');
const compareButton = document.querySelector('#compareButton');
const clearButton = document.querySelector('#clearButton');
const visibleCount = document.querySelector('#visibleCount');
const favoriteCount = document.querySelector('#favoriteCount');
const compareCount = document.querySelector('#compareCount');
const resultState = document.querySelector('#resultState');
const noResults = document.querySelector('#noResults');

let favorites = read('ecixs_favorites_v4').filter(id => DATA[id]);
let compare = read('ecixs_compare_v4').filter(id => DATA[id]).slice(0,2);
let onlyFavorites = false;

function read(key){
    try{
        const x=JSON.parse(localStorage.getItem(key)||'[]');
        return Array.isArray(x)?x.map(String):[];
    }catch(_){return[]}
}
function esc(v){
    return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function normalize(v){
    return String(v??'')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g,'')
        .toLocaleLowerCase('es')
        .replace(/\s+/g,' ')
        .trim();
}
function descriptionHtml(text){
    const chunks=String(text??'')
        .split(/\n\s*\n/g)
        .map(s=>s.trim())
        .filter(Boolean);
    if(!chunks.length) return '<p>Sin descripción registrada.</p>';
    return chunks.map(chunk=>`<p>${esc(chunk).replace(/\n/g,'<br>')}</p>`).join('');
}

function sync(){
    const q=normalize(search.value);
    const cat=normalize(category.value);
    const avail=availability.value;
    let shown=0;

    cards.forEach(card=>{
        const id=card.dataset.id;
        const fav=favorites.includes(id);
        const cmp=compare.includes(id);
        card.classList.toggle('favorite',fav);
        card.classList.toggle('selected',cmp);

        const favBtn=card.querySelector('.favorite-btn');
        favBtn.classList.toggle('active',fav);
        favBtn.setAttribute('aria-label',fav?'Quitar de favoritos':'Añadir a favoritos');
        favBtn.title=fav?'Quitar favorito':'Añadir favorito';

        const cmpBtn=card.querySelector('.select-btn');
        cmpBtn.classList.toggle('active',cmp);
        cmpBtn.textContent=cmp?'Quitar':'Comparar';

        const haystack=normalize(`${card.dataset.name} ${card.dataset.category} ${card.dataset.description}`);
        const cardCategory=normalize(card.dataset.category);
        const hiddenBySearch=q && !haystack.includes(q);
        const hiddenByCategory=cat && cardCategory!==cat;
        const hiddenByAvailability=avail && card.dataset.availability!==avail;
        const hiddenByFavorites=onlyFavorites && !fav;
        const hidden=hiddenBySearch||hiddenByCategory||hiddenByAvailability||hiddenByFavorites;

        card.hidden=hidden;
        if(!hidden) shown++;
    });

    visibleCount.textContent=shown;
    favoriteCount.textContent=favorites.length;
    compareCount.textContent=compare.length;
    favoritesToggle.textContent=onlyFavorites?'♥ Mostrando favoritos':'♡ Ver favoritos';
    favoritesToggle.classList.toggle('active',onlyFavorites);
    compareButton.disabled=compare.length!==2;
    compareButton.textContent=compare.length===2?'⚖ Comparar seleccionados':`⚖ Comparador (${compare.length}/2)`;
    searchClear.hidden=!search.value;
    noResults.classList.toggle('show',shown===0 && cards.length>0);

    const parts=[];
    if(q) parts.push(`búsqueda “${search.value.trim()}”`);
    if(category.value) parts.push(category.value);
    if(avail==='available') parts.push('disponibles');
    if(avail==='soldout') parts.push('agotados');
    if(onlyFavorites) parts.push('favoritos');
    resultState.textContent=parts.length
        ? `${shown} resultado${shown===1?'':'s'} · ${parts.join(' · ')}`
        : `Mostrando ${shown} objeto${shown===1?'':'s'}.`;

    localStorage.setItem('ecixs_favorites_v4',JSON.stringify(favorites));
    localStorage.setItem('ecixs_compare_v4',JSON.stringify(compare));
}

function toggleFav(id){
    favorites=favorites.includes(id)?favorites.filter(x=>x!==id):[...favorites,id];
    sync();
}
function toggleCompare(id){
    if(compare.includes(id)){
        compare=compare.filter(x=>x!==id);
    }else{
        if(compare.length>=2) compare.shift();
        compare.push(id);
    }
    sync();
}
function openCard(id){
    const c=DATA[id];
    if(!c)return;
    modal(`
        <article class="detail">
            <div class="detail-image"><img src="${esc(c.url)}" alt="${esc(c.name)}"></div>
            <div class="detail-copy">
                <p class="eyebrow">${esc(c.category)}</p>
                <h2>${esc(c.name)}</h2>
                <p class="detail-price">${esc(c.price)}</p>
                <p class="stock ${Number(c.stock)<=0?'zero':''}">${Number(c.stock)<=0?'Agotado':'Stock: '+Number(c.stock)}</p>
                <div class="detail-section-title">Descripción y propiedades</div>
                <div class="detail-description">${descriptionHtml(c.description)}</div>
                <div class="detail-actions">
                    <button data-fav="${esc(id)}">${favorites.includes(id)?'♥ Quitar favorito':'♡ Añadir favorito'}</button>
                    <button data-cmp="${esc(id)}">${compare.includes(id)?'Quitar del comparador':'Añadir al comparador'}</button>
                </div>
            </div>
        </article>
    `);
    document.querySelector(`[data-fav="${CSS.escape(id)}"]`)?.addEventListener('click',()=>{toggleFav(id);closeModal()});
    document.querySelector(`[data-cmp="${CSS.escape(id)}"]`)?.addEventListener('click',()=>{toggleCompare(id);closeModal()});
}
function compareNow(){
    if(compare.length!==2){return}
    const selected=compare.map(id=>DATA[id]).filter(Boolean);
    if(selected.length!==2)return;
    modal(`<div class="compare-grid">${selected.map(c=>`
        <article class="compare-item">
            <img src="${esc(c.url)}" alt="${esc(c.name)}">
            <h3>${esc(c.name)}</h3>
            <p class="compare-meta">${esc(c.price)} · ${Number(c.stock)<=0?'Agotado':'Stock: '+Number(c.stock)}</p>
            <div class="compare-description">${descriptionHtml(c.description)}</div>
        </article>`).join('')}</div>`);
}
function modal(html){
    closeModal();
    const m=document.createElement('div');
    m.className='modal';m.id='modal';
    m.innerHTML=`<div class="modal-card"><button class="modal-close" type="button" aria-label="Cerrar">×</button>${html}</div>`;
    m.onclick=e=>{if(e.target===m)closeModal()};
    m.querySelector('.modal-close').onclick=closeModal;
    document.body.append(m);
    document.body.classList.add('modal-open');
}
function closeModal(){
    document.querySelector('#modal')?.remove();
    document.body.classList.remove('modal-open');
}

cards.forEach(card=>{
    const id=card.dataset.id;
    card.querySelector('.card-image').onclick=()=>openCard(id);
    card.querySelector('.favorite-btn').onclick=()=>toggleFav(id);
    card.querySelector('.select-btn').onclick=()=>toggleCompare(id);
});

search.addEventListener('input',sync);
category.addEventListener('change',sync);
availability.addEventListener('change',sync);
searchClear.addEventListener('click',()=>{search.value='';search.focus();sync()});
favoritesToggle.addEventListener('click',()=>{onlyFavorites=!onlyFavorites;sync()});
compareButton.addEventListener('click',compareNow);
clearButton.addEventListener('click',()=>{
    search.value='';
    category.value='';
    availability.value='';
    onlyFavorites=false;
    sync();
    search.focus();
});

document.querySelector('#catalogTab').onclick=()=>{
    document.querySelector('#catalogView').classList.remove('hidden');
    document.querySelector('#servicesView').classList.remove('active');
};
document.querySelector('#servicesTab').onclick=()=>{
    document.querySelector('#catalogView').classList.add('hidden');
    document.querySelector('#servicesView').classList.add('active');
};

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});
sync();
})();
</script>
</body>
</html>
