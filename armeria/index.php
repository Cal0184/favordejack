<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/armeria.php';

// Consulta abierta como el Reglamento; editar es solo para el admin.
favor_session_start();
try {
    $user = favor_current_user();
} catch (Throwable $e) {
    $user = null;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
$csrf = $isAdmin ? favor_csrf() : '';
favor_session_close();

$catalogo = null;
$error = '';
try {
    $db = favor_db();
    $catalogo = armeria_catalogo($db, $isAdmin);
    $db->close();
} catch (Throwable $e) {
    error_log('armeria: ' . $e->getMessage());
    $error = 'No pude leer el catálogo de armamento. Inténtalo de nuevo en un momento.';
}

$boot = [
    'catalogo' => $catalogo,
    'admin' => $isAdmin,
    'csrf' => $csrf,
    'api' => favor_base_path() . 'armeria/api.php',
];
$total = $catalogo ? count(array_filter($catalogo['objetos'], static fn(array $o): bool => $o['activo'] ?? true)) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#120c08">
<title>Armas y Armaduras · Favor de Jack</title>
<?php favor_head('Armas y Armaduras · Favor de Jack', 'Compendio de armas, armaduras y escudos del Favor de Jack: daño, coste, propiedades y reglas especiales.'); ?>
<link rel="stylesheet" href="assets/armeria.css<?= favor_v(__DIR__ . '/assets/armeria.css') ?>">
<script src="assets/armeria.js<?= favor_v(__DIR__ . '/assets/armeria.js') ?>" defer></script>
</head>
<body class="arm-body">
<?php favor_site_bar(); ?>

<main class="arm" id="arm" data-vista="catalogo">
    <header class="arm-head">
        <div class="arm-title">
            <span class="arm-seal" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7l23 23M7 9l2-2M30 27l4 4-3 3-4-4M34 31l5 5M37 39l3-3"/><path d="M39 7L16 30M41 9l-2-2M18 27l-4 4 3 3 4-4M14 31l-5 5M11 39l-3-3"/></svg></span>
            <div>
                <h1>Armas y Armaduras</h1>
                <p>Compendio del gremio · <span id="armTotal"><?= (int)$total ?></span> entradas</p>
            </div>
        </div>
        <nav class="arm-tabs" role="tablist" aria-label="Secciones">
            <button type="button" role="tab" id="tabCatalogo" aria-controls="vistaCatalogo" aria-selected="true" data-vista="catalogo">Catálogo</button>
            <button type="button" role="tab" id="tabGlosario" aria-controls="vistaGlosario" aria-selected="false" data-vista="glosario">Propiedades</button>
        </nav>
        <?php if ($isAdmin): ?>
            <div class="arm-admin-bar">
                <button type="button" class="arm-btn arm-btn-gold" id="armNuevo">+ Nuevo objeto</button>
                <button type="button" class="arm-btn" id="armNuevaProp">+ Propiedad</button>
                <button type="button" class="arm-btn" id="armReimportar" title="Vuelve a leer los datos del documento sin tocar lo que editaste">Reimportar</button>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($error !== ''): ?>
        <p class="arm-error" role="alert"><?= favor_h($error) ?></p>
    <?php endif; ?>

    <section class="arm-view" id="vistaCatalogo" role="tabpanel" aria-labelledby="tabCatalogo">
        <div class="arm-searchrow">
            <label class="arm-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="armBuscar" type="search" autocomplete="off" spellcheck="false" placeholder="Buscar por nombre, propiedad o regla…" aria-label="Buscar armamento">
            </label>
            <button type="button" class="arm-btn arm-filtros-toggle" id="armFiltrosBtn" aria-expanded="false" aria-controls="armFiltros">Filtros <b id="armFiltrosN" hidden></b></button>
        </div>
        <div class="arm-cats-strip" id="armCatsStrip" role="tablist" aria-label="Categorías"></div>

        <div class="arm-grid">
            <aside class="arm-filtros" id="armFiltros" aria-label="Categorías y filtros">
                <div class="arm-panel">
                    <h2 class="arm-panel-title">Categorías</h2>
                    <div id="armCats" class="arm-cats"></div>
                </div>
                <div class="arm-panel" id="armFiltrosPanel">
                    <div class="arm-panel-head">
                        <h2 class="arm-panel-title">Filtros</h2>
                        <button type="button" class="arm-link" id="armLimpiar">Limpiar filtros</button>
                    </div>
                    <div id="armFiltrosBody"></div>
                </div>
            </aside>

            <section class="arm-lista" aria-label="Catálogo">
                <div class="arm-toolbar">
                    <p class="arm-count" id="armCount" aria-live="polite"></p>
                    <label>Orden
                        <select id="armOrden">
                            <option value="doc">Del documento</option>
                            <option value="nombre">Nombre (A–Z)</option>
                            <option value="nombre-desc">Nombre (Z–A)</option>
                            <option value="valor-desc">Daño / CA (mayor)</option>
                            <option value="valor">Daño / CA (menor)</option>
                            <option value="coste">Coste (menor)</option>
                            <option value="coste-desc">Coste (mayor)</option>
                        </select>
                    </label>
                    <label>Mostrar
                        <select id="armPorPagina">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="0">Todos</option>
                        </select>
                    </label>
                </div>
                <div class="arm-tablewrap">
                    <table class="arm-table">
                        <thead>
                            <tr>
                                <th scope="col"><button type="button" data-sort="nombre">Nombre</button></th>
                                <th scope="col" class="arm-col-cat">Categoría</th>
                                <th scope="col" class="arm-col-num"><button type="button" data-sort="valor">Daño / CA</button></th>
                                <th scope="col" class="arm-col-num"><button type="button" data-sort="coste">Coste</button></th>
                            </tr>
                        </thead>
                        <tbody id="armFilas"></tbody>
                    </table>
                    <p class="arm-vacio" id="armVacio" hidden>Nada coincide con la búsqueda y los filtros. <button type="button" class="arm-link" data-limpiar>Limpiar filtros</button></p>
                </div>
                <nav class="arm-pager" id="armPager" aria-label="Páginas"></nav>
            </section>

            <article class="arm-ficha" id="armFicha" aria-live="polite" tabindex="-1">
                <p class="arm-ficha-vacia">Elige un objeto del catálogo para ver su ficha.</p>
            </article>
        </div>
    </section>

    <section class="arm-view" id="vistaGlosario" role="tabpanel" aria-labelledby="tabGlosario" hidden>
        <div class="arm-searchrow">
            <label class="arm-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="armBuscarProp" type="search" autocomplete="off" spellcheck="false" placeholder="Buscar propiedad o texto de su definición…" aria-label="Buscar propiedad">
            </label>
        </div>
        <div class="arm-glosario" id="armGlosario"></div>
    </section>
</main>

<dialog class="arm-dialog arm-dialog-prop" id="armPropDialog" aria-labelledby="armPropTitulo">
    <div class="arm-parchment">
        <h2 id="armPropTitulo"></h2>
        <div id="armPropTexto"></div>
        <div class="arm-dialog-actions">
            <button type="button" class="arm-btn" id="armPropGlosario">Ver en el glosario</button>
            <button type="button" class="arm-btn arm-btn-gold" data-cerrar>Cerrar</button>
        </div>
    </div>
</dialog>

<?php if ($isAdmin): ?>
<dialog class="arm-dialog arm-dialog-editor" id="armEditor" aria-labelledby="armEditorTitulo">
    <form class="arm-editor" id="armEditorForm" method="dialog" novalidate>
        <header><h2 id="armEditorTitulo">Editar objeto</h2><button type="button" class="arm-x" data-cerrar aria-label="Cerrar">×</button></header>
        <div class="arm-editor-body" id="armEditorBody"></div>
        <p class="arm-editor-error" id="armEditorError" role="alert" hidden></p>
        <footer>
            <button type="button" class="arm-btn arm-btn-danger" id="armEditorBorrar" hidden>Eliminar</button>
            <button type="button" class="arm-btn" id="armEditorActivar" hidden>Desactivar</button>
            <span class="arm-flex"></span>
            <button type="button" class="arm-btn" data-cerrar>Cancelar</button>
            <button type="submit" class="arm-btn arm-btn-gold" id="armEditorGuardar">Guardar</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<p class="arm-toast" id="armToast" role="status" hidden></p>

<script type="application/json" id="armBoot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</body>
</html>
