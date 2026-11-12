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
$total = (int)($catalogo['total'] ?? 0); // COUNT(*) en MySQL, objetos activos
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
        <div class="arm-hero">
            <div class="arm-title">
                <span class="arm-seal" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7l23 23M7 9l2-2M30 27l4 4-3 3-4-4M34 31l5 5M37 39l3-3"/><path d="M39 7L16 30M41 9l-2-2M18 27l-4 4 3 3 4-4M14 31l-5 5M11 39l-3-3"/></svg></span>
                <div>
                    <h1>Armas y Armaduras</h1>
                    <p>Catálogo del Gremio · <span id="armTotal"><?= (int)$total ?></span> objetos registrados</p>
                </div>
            </div>
            <?php if ($isAdmin): ?>
                <div class="arm-admin-bar">
                    <button type="button" class="arm-btn arm-btn-gold" id="armNuevo">+ Nuevo objeto</button>
                    <button type="button" class="arm-btn" id="armNuevaProp">+ Propiedad</button>
                    <button type="button" class="arm-btn" data-nueva-nota="0">+ Aclaración</button>
                    <button type="button" class="arm-btn" id="armReimportar" title="Vuelve a leer los datos del documento sin tocar lo que editaste">Reimportar</button>
                </div>
            <?php endif; ?>
        </div>
        <nav class="arm-tabs" role="tablist" aria-label="Secciones">
            <button type="button" role="tab" id="tabCatalogo" aria-controls="vistaCatalogo" aria-selected="true" data-vista="catalogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg><span>Catálogo</span></button>
            <button type="button" role="tab" id="tabGlosario" aria-controls="vistaGlosario" aria-selected="false" data-vista="glosario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M3 5c3-1.5 6-1.5 9 .5 3-2 6-2 9-.5v14c-3-1.5-6-1.5-9 .5-3-2-6-2-9-.5z"/><path d="M12 5.5v14"/></svg><span>Propiedades</span></button>
            <button type="button" role="tab" id="tabReglas" aria-controls="vistaReglas" aria-selected="false" data-vista="reglas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h11a2 2 0 0 1 0 4h-1v12a2 2 0 0 1-2 2H6a2 2 0 0 1 0-4h1z"/><path d="M10 8h4M10 12h4"/></svg><span>Reglas y notas</span></button>
        </nav>
    </header>

    <?php if ($error !== ''): ?>
        <p class="arm-error" role="alert"><?= favor_h($error) ?></p>
    <?php endif; ?>

    <section class="arm-view" id="vistaCatalogo" role="tabpanel" aria-labelledby="tabCatalogo">
        <div class="arm-cats-strip" id="armCatsStrip" role="tablist" aria-label="Categorías"></div>

        <div class="arm-grid">
            <aside class="arm-filtros" id="armFiltros" aria-label="Categorías y filtros">
                <div class="arm-panel arm-panel-cats">
                    <h2 class="arm-panel-title">Categoría</h2>
                    <div id="armCats" class="arm-cats"></div>
                </div>
                <div class="arm-panel" id="armFiltrosPanel">
                    <h2 class="arm-panel-title">Filtros</h2>
                    <div id="armFiltrosBody"></div>
                    <button type="button" class="arm-limpiar" id="armLimpiar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.6M4 4v4.6h4.6"/></svg>Limpiar filtros</button>
                </div>
            </aside>

            <section class="arm-lista" aria-label="Catálogo">
                <div class="arm-toolbar">
                    <label class="arm-search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <input id="armBuscar" type="search" autocomplete="off" spellcheck="false" placeholder="Buscar por nombre, propiedad, regla o atributo…" aria-label="Buscar armamento">
                    </label>
                    <label class="arm-orden"><span class="arm-sr">Orden</span>
                        <select id="armOrden" aria-label="Orden">
                            <option value="nombre">Orden: Nombre (A – Z)</option>
                            <option value="nombre-desc">Orden: Nombre (Z – A)</option>
                            <option value="doc">Orden: del documento</option>
                            <option value="valor-desc">Orden: Daño / CA (mayor)</option>
                            <option value="valor">Orden: Daño / CA (menor)</option>
                            <option value="coste">Orden: Coste (menor)</option>
                            <option value="coste-desc">Orden: Coste (mayor)</option>
                        </select>
                    </label>
                    <button type="button" class="arm-btn arm-filtros-toggle" id="armFiltrosBtn" aria-expanded="false" aria-controls="armFiltros">Filtros <b id="armFiltrosN" hidden></b></button>
                </div>
                <div class="arm-tablewrap">
                    <table class="arm-table">
                        <thead>
                            <tr>
                                <th scope="col"><button type="button" data-sort="nombre">Nombre <span class="arm-sorticon" aria-hidden="true">⇅</span></button></th>
                                <th scope="col" class="arm-col-cat">Tipo</th>
                                <th scope="col" class="arm-col-num"><button type="button" data-sort="valor">Daño / CA</button></th>
                                <th scope="col" class="arm-col-num"><button type="button" data-sort="coste">Coste</button></th>
                                <th scope="col" class="arm-col-chev"><span class="arm-sr">Abrir</span></th>
                            </tr>
                        </thead>
                        <tbody id="armFilas"></tbody>
                    </table>
                    <p class="arm-vacio" id="armVacio" hidden>Nada coincide con la búsqueda y los filtros. <button type="button" class="arm-link" data-limpiar>Limpiar filtros</button></p>
                </div>
                <div class="arm-pie">
                    <nav class="arm-pager" id="armPager" aria-label="Páginas"></nav>
                    <div class="arm-pie-info">
                        <p class="arm-count" id="armCount" aria-live="polite"></p>
                        <label class="arm-porpagina">Mostrar
                            <select id="armPorPagina">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="0">Todos</option>
                            </select>
                        </label>
                    </div>
                </div>
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

    <section class="arm-view" id="vistaReglas" role="tabpanel" aria-labelledby="tabReglas" hidden>
        <div class="arm-searchrow">
            <label class="arm-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="armBuscarRegla" type="search" autocomplete="off" spellcheck="false" placeholder="Buscar en reglas, excepciones y aclaraciones…" aria-label="Buscar en reglas y notas">
            </label>
        </div>
        <div class="arm-reglas" id="armReglas"></div>
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
