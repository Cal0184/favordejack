<?php
/* INVENTARIO FAVOR DE JACK v13 — estructura limpia */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';

favor_session_start();
$user = favor_current_user();
$csrf = favor_csrf();

ob_start();
favor_auth_widget();
$authWidget = (string)ob_get_clean();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$visualAssets = [
    'background' => 'assets/ui/inventory-bg.webp',
    'parchment' => 'assets/ui/inventory-parchment.webp',
    'categories' => [
        'combat' => 'assets/items/item-arma.webp',
        'magic' => 'assets/items/item-magico.webp',
        'vanity' => 'assets/items/item-vanidad.webp',
        'utility' => 'assets/items/item-utilidad.webp',
    ],
];

$initialCharacter = trim((string)($_GET['character'] ?? ''));
$config = [
    'apiUrl' => 'api.php',
    'csrf' => $csrf,
    'initialCharacter' => $initialCharacter,
    'currentUser' => $user ? [
        'username' => (string)($user['username'] ?? ''),
        'role' => (string)($user['role'] ?? ''),
        'playerId' => (string)($user['playerId'] ?? ''),
    ] : null,
    'visualAssets' => $visualAssets,
    'links' => [
        'bank' => favor_base_path() . 'bancadigital/?character=',
        'archive' => favor_base_path() . 'archivosdelgremio/?character=',
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#061813">
    <title>Inventario · Favor de Jack</title>
    <link rel="stylesheet" href="assets/inventory.css?v=13.1.1">
    <style>
        :root{
            --inventory-bg-image:url(<?= json_encode($visualAssets['background'], JSON_UNESCAPED_SLASHES) ?>);
            --inventory-paper-image:url(<?= json_encode($visualAssets['parchment'], JSON_UNESCAPED_SLASHES) ?>);
        }
    </style>
</head>
<body>
<?= $authWidget ?>

<header class="topbar" id="inventoryTopbar">
    <div class="brand" aria-label="Favor de Jack">
        <img class="brand__logo" src="assets/ui/favor-logo.svg" alt="" aria-hidden="true">
        <div class="brand__copy">
            <span class="brand__text">Favor de Jack</span>
            <span class="brand__subtitle">Inventario del Gremio</span>
        </div>
    </div>

    <div class="topbar__ornament" aria-hidden="true">
        <span></span><i>✦</i><span></span>
    </div>

    <div class="topbar__end">
        <span class="topbar__motto">Buenas historias<br>siempre encuentran su camino.</span>
        <a class="hub-button" href="<?= favor_base_path() ?>" aria-label="Salir del Inventario y volver al Hub">
            <span aria-hidden="true">←</span> Volver al Hub
        </a>
    </div>
</header>

<main class="inventory-app" id="inventoryApp" aria-busy="true">
    <aside class="character-sidebar" id="inventorySidebar" aria-label="Selector de personaje">
        <div class="sidebar__top">
            <div class="sidebar__title-row">
                <div>
                    <span class="eyebrow">Inventario</span>
                    <h1>Personajes activos</h1>
                </div>
                <button class="icon-button" id="refreshCharactersBtn" type="button" title="Actualizar personajes" aria-label="Actualizar personajes">↻</button>
            </div>

            <label class="player-picker" for="playerSelect">
                <span class="player-picker__icon" aria-hidden="true">♙</span>
                <span class="sr-only">Jugador</span>
                <select id="playerSelect" disabled>
                    <option value="">Cargando jugadores…</option>
                </select>
            </label>

            <label class="character-search" for="characterSearch">
                <span aria-hidden="true">⌕</span>
                <input id="characterSearch" type="search" placeholder="Buscar personaje" autocomplete="off" disabled>
            </label>
        </div>

        <div class="character-state" id="characterState" role="status">Cargando personajes…</div>
        <div class="character-list" id="characterList" aria-live="polite"></div>

    </aside>

    <section class="inventory-workspace">
        <div class="global-message" id="globalMessage" role="status" aria-live="polite" hidden></div>

        <section class="welcome-state" id="welcomeState">
            <span class="welcome-state__mark" aria-hidden="true">✦</span>
            <span class="eyebrow">Inventario del gremio</span>
            <h2>Elige un personaje</h2>
            <p>Selecciona un personaje activo para abrir su inventario.</p>
        </section>

        <div id="characterWorkspace" hidden>
            <section class="character-sheet">
                <header class="character-hero">
                    <div class="character-hero__watermark" aria-hidden="true">✦</div>
                    <div class="portrait" id="heroPortrait" aria-hidden="true"><span>✦</span></div>
                    <div class="character-hero__identity">
                        <span class="hero-player" id="heroPlayer">Personaje</span>
                        <h2 id="heroName">—</h2>
                        <p class="hero-meta" id="heroMeta">Inventario del personaje</p>
                        <p class="hero-quote" id="heroQuote">“Un buen equipo abre más puertas.”</p>
                        <div class="hero-status" id="heroStatus"></div>
                    </div>
                    <div class="character-hero__actions">
                        <a class="hero-button" id="bankLink" href="#"><span aria-hidden="true">▣</span> Banca</a>
                        <a class="hero-button" id="archiveLink" href="#"><span aria-hidden="true">▤</span> Archivo</a>
                        <a class="hero-button" id="importSheetLink" href="#" hidden><span aria-hidden="true">📜</span> Importar ficha</a>
                        <button class="hero-button hero-button--primary" id="addItemBtn" type="button" hidden><span aria-hidden="true">＋</span> Añadir objeto</button>
                    </div>
                </header>

                <section class="summary-strip" id="inventorySummary" hidden aria-label="Resumen del inventario">
                    <div class="summary-card summary-card--items"><span>Objetos</span><strong id="summaryItems">0</strong></div>
                    <div class="summary-card summary-card--equipped"><span>Equipados</span><strong id="summaryEquipped">0</strong></div>
                    <div class="summary-card summary-card--units"><span>Unidades</span><strong id="summaryUnits">0</strong></div>
                    <div class="summary-card summary-card--categories"><span>Categorías</span><strong id="summaryCategories">0</strong></div>
                    <blockquote>“Un buen equipo<br>abre más puertas.”</blockquote>
                </section>
            </section>

            <section class="inventory-panel">
                <div class="inventory-filters" id="inventoryFilters" hidden>
                    <label class="search-box" for="itemSearch">
                        <span aria-hidden="true">⌕</span>
                        <input id="itemSearch" type="search" placeholder="Buscar objeto…" autocomplete="off">
                    </label>

                    <label class="sr-only" for="categoryFilter">Categoría</label>
                    <select id="categoryFilter" class="filter-select">
                        <option value="">Todas las categorías</option>
                    </select>

                    <label class="sr-only" for="equipmentFilter">Estado</label>
                    <select id="equipmentFilter" class="filter-select">
                        <option value="all">Todos los estados</option>
                        <option value="equipped">Equipados</option>
                        <option value="unequipped">No equipados</option>
                    </select>

                    <label class="sr-only" for="sortFilter">Orden</label>
                    <select id="sortFilter" class="filter-select">
                        <option value="default">Orden recomendado</option>
                        <option value="name">Nombre A–Z</option>
                        <option value="category">Categoría</option>
                        <option value="quantity">Mayor cantidad</option>
                    </select>

                    <div class="view-switch" aria-label="Vista del inventario">
                        <button type="button" id="cardsViewBtn" data-view="cards" aria-pressed="false"><span aria-hidden="true">▦</span> Tarjetas</button>
                        <button type="button" id="listViewBtn" class="is-active" data-view="list" aria-pressed="true"><span aria-hidden="true">☷</span> Lista</button>
                    </div>

                    <button class="icon-button refresh-inventory" id="refreshInventoryBtn" type="button" title="Actualizar inventario" aria-label="Actualizar inventario"><span aria-hidden="true">↻</span></button>
                </div>

                <div class="inventory-state" id="inventoryState" role="status" aria-live="polite"></div>

                <div class="inventory-list" id="inventoryList" hidden>
                    <div class="inventory-row inventory-row--header" aria-hidden="true">
                        <div>Objeto</div>
                        <div>Categoría</div>
                        <div>Cantidad</div>
                        <div>Equipado</div>
                        <div>Notas</div>
                        <div>Acciones</div>
                    </div>
                    <div id="itemRows"></div>
                </div>

                <div class="inventory-cards" id="inventoryCards" hidden>
                    <div id="itemCards"></div>
                </div>

                <footer class="inventory-footer" id="inventoryFooter" hidden>
                    <span id="resultCount">0 objetos</span>
                    <nav class="pager" id="itemPager" aria-label="Páginas del inventario"></nav>
                </footer>
            </section>
        </div>
    </section>
</main>

<div class="modal" id="itemModal" hidden>
    <div class="modal__backdrop" data-close-modal></div>
    <section class="modal__card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal__heading">
            <div>
                <span class="eyebrow">Inventario</span>
                <h2 id="modalTitle">Añadir objeto</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Cerrar">×</button>
        </div>

        <form class="edit-form" id="itemForm">
            <input type="hidden" id="itemId" value="0">

            <label class="field">
                <span>Nombre</span>
                <input id="itemName" maxlength="160" required>
            </label>

            <label class="field">
                <span>Categoría</span>
                <select id="itemCategory" required>
                    <option value="Arma / Armadura">Arma / Armadura</option>
                    <option value="Objeto mágico">Objeto mágico</option>
                    <option value="Vanidad">Vanidad</option>
                    <option value="Utilidad" selected>Utilidad</option>
                </select>
            </label>

            <label class="field">
                <span>Cantidad</span>
                <input id="itemQuantity" type="number" min="1" max="9999" value="1" required>
            </label>

            <label class="field field--check">
                <span>Estado</span>
                <span><input id="itemEquipped" type="checkbox"> Equipado</span>
            </label>

            <label class="field field--full">
                <span>Descripción</span>
                <textarea id="itemDescription" maxlength="10000" placeholder="Qué es, para qué sirve, propiedades importantes…"></textarea>
            </label>

            <label class="field field--full">
                <span>Notas privadas</span>
                <textarea id="itemNotes" maxlength="10000" placeholder="Solo visibles para quien puede editar este inventario."></textarea>
            </label>

            <label class="field field--full">
                <span>Imagen propia <em>(opcional)</em></span>
                <input id="itemImage" maxlength="500" placeholder="https://… o ruta de imagen">
                <small>Si está vacío se utiliza automáticamente la imagen visual de su categoría.</small>
            </label>

            <div class="form-error field--full" id="formError" hidden></div>
            <div class="modal__actions field--full">
                <button class="button button--secondary" type="button" data-close-modal>Cancelar</button>
                <button class="button button--primary" id="saveItemBtn" type="submit">Guardar</button>
            </div>
        </form>
    </section>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>

<script id="inventoryConfig" type="application/json"><?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/inventory.js?v=13.1.1" defer></script>
</body>
</html>
