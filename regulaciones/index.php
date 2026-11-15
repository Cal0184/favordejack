<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib.php';

favor_session_start();
try {
    $user = favor_current_user();
} catch (Throwable $e) {
    $user = null;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
$csrf = $isAdmin ? favor_csrf() : '';
favor_session_close();

$data = reg_load();
// Los borradores solo los ve el admin.
$rules = array_values(array_filter($data['rules'], static fn(array $r): bool => $isAdmin || empty($r['draft'])));
$categories = reg_categories($data, $rules);
$updated = (string)($data['updated'] ?? '');

$byCategory = array_fill_keys($categories, 0);
$byType = array_fill_keys(array_keys(REG_TYPES), 0);
foreach ($rules as $rule) {
    $byCategory[(string)($rule['category'] ?? '')] = ($byCategory[(string)($rule['category'] ?? '')] ?? 0) + 1;
    $t = (string)($rule['type'] ?? 'regla');
    if (isset($byType[$t])) $byType[$t]++;
}
// Una sección sin nada que leer no se ofrece a los jugadores; el admin la ve igual.
if (!$isAdmin) $categories = array_values(array_filter($categories, static fn(string $c): bool => ($byCategory[$c] ?? 0) > 0));

// Patch Notes: historial de versiones (el más nuevo primero).
$patches = [];
foreach (reg_patches($data) as $patch) {
    $time = strtotime($patch['date']) ?: 0;
    preg_match_all('/^## (.+)$/mu', $patch['body'], $heads);
    $patches[] = $patch + [
        'id' => reg_patch_id($patch['version']),
        'dateLabel' => $time ? date('d/m/Y', $time) : $patch['date'],
        'summary' => implode(' · ', $heads[1] ?? []),
    ];
}

// Tarjetas de la portada móvil: secciones y tipos con su icono.
$homeCards = [];
foreach (REG_TYPE_ICONS as $type => $icon) {
    if (!$isAdmin && ($byType[$type] ?? 0) === 0) continue;
    $homeCards[] = ['filter' => 'type:' . $type, 'label' => REG_TYPES[$type] . 's', 'icon' => $icon, 'count' => (int)$byType[$type], 'unit' => 'cambios', 'tone' => $type];
}
foreach ($categories as $category) {
    $homeCards[] = ['filter' => 'cat:' . $category, 'label' => $category, 'icon' => reg_category_icon($category), 'count' => (int)($byCategory[$category] ?? 0), 'unit' => 'reglas', 'tone' => 'cat'];
}

$config = $isAdmin ? [
    'csrf' => $csrf,
    'categories' => $categories,
    'icons' => reg_icon_names(),
    'sprite' => reg_sprite_url(),
    'types' => REG_TYPES,
    'rules' => array_column(array_map(static fn(array $r): array => [
        'id' => (string)($r['id'] ?? ''),
        'type' => (string)($r['type'] ?? 'regla'),
        'category' => (string)($r['category'] ?? ''),
        'title' => (string)($r['title'] ?? ''),
        'icon' => (string)($r['icon'] ?? ''),
        'summary' => (string)($r['summary'] ?? ''),
        'body' => (string)($r['body'] ?? ''),
        'source' => (string)($r['source'] ?? ''),
        'mesa' => !empty($r['mesa']),
        'draft' => !empty($r['draft']),
    ], $rules), null, 'id'),
    'patches' => array_column(array_map(static fn(array $p): array => [
        'id' => $p['id'], 'version' => $p['version'], 'date' => $p['date'], 'name' => $p['name'], 'body' => $p['body'],
    ], $patches), null, 'id'),
    'today' => date('Y-m-d'),
] : null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#100b08">
<title>Regulaciones · Favor de Jack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cinzel+Decorative:wght@700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<?php favor_head('Reglamento · Favor de Jack', 'Consulta rápida de reglas, ajustes y restricciones de las partidas del Favor de Jack.'); ?>
<link rel="stylesheet" href="assets/regulations.css<?= favor_v(__DIR__ . '/assets/regulations.css') ?>">
</head>
<body>
<?php favor_site_bar(); ?>

<header class="masthead">
    <p class="motto motto-left" aria-hidden="true">Lex<br>Ludus<br>Societas<br>Aeterna</p>
    <div class="masthead-center">
        <div class="seal" aria-hidden="true"><?= reg_icon('scales') ?></div>
        <p class="eyebrow">Archivo oficial del gremio</p>
        <h1>Regulaciones</h1>
        <p class="lead-short">Normas, buffs, nerfeos y cambios oficiales</p>
        <p class="lead">Consulta rápida de reglas, ajustes y restricciones registradas para las partidas de Favor de Jack.</p>
        <?php if ($updated !== ''): ?><p class="updated">Actualizado: <?= reg_h($updated) ?></p><?php endif; ?>
    </div>
    <p class="motto motto-right" aria-hidden="true">Mismas<br>reglas<br>más grandes<br>historias</p>
</header>

<main class="book" id="regBook">
    <nav class="index" aria-label="Secciones del reglamento">
        <button class="index-item active" type="button" data-filter="all">
            <?= reg_icon('open-book') ?><span>Todas</span><b><?= count($rules) ?></b>
        </button>
        <?php if ($patches || $isAdmin): ?>
            <button class="index-item index-item-patch" type="button" data-filter="cat:Patch Notes">
                <?= reg_icon('scroll-quill') ?><span>Patch Notes</span><b><?= count($patches) ?></b>
            </button>
        <?php endif; ?>
        <?php foreach ($categories as $category): ?>
            <button class="index-item" type="button" data-filter="cat:<?= reg_h($category) ?>">
                <?= reg_icon(reg_category_icon($category)) ?><span><?= reg_h($category) ?></span><b><?= (int)($byCategory[$category] ?? 0) ?></b>
            </button>
        <?php endforeach; ?>
        <hr>
        <?php foreach (REG_TYPE_ICONS as $type => $icon): ?>
            <button class="index-item" type="button" data-filter="type:<?= reg_h($type) ?>">
                <?= reg_icon($icon) ?><span><?= reg_h(REG_TYPES[$type] . 's') ?></span><b><?= (int)$byType[$type] ?></b>
            </button>
        <?php endforeach; ?>
        <p class="index-motto" aria-hidden="true"><?= reg_icon('scales') ?>Conocimiento<br>ordena<br>nuestra libertad</p>
    </nav>

    <section class="page page-list" aria-label="Lista de regulaciones">
        <div class="list-tools">
        <div class="list-head">
            <label class="searchbox">
                <svg class="reg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="ruleSearch" type="search" autocomplete="off" placeholder="Buscar regulación, acción, estado…" aria-label="Buscar regulación">
            </label>
            <p class="count"><strong id="visibleCount"><?= count($rules) ?></strong><span>regulaciones</span></p>
        </div>
        <div class="list-crumb" id="listCrumb">
            <button type="button" class="crumb-back" id="crumbBack"><span aria-hidden="true">‹</span> Secciones</button>
            <span class="crumb-title" id="crumbTitle">Todas</span>
        </div>
        <div class="chips" role="group" aria-label="Filtrar por sección">
            <button class="chip active" type="button" data-filter="all">Todas</button>
            <?php if ($patches || $isAdmin): ?><button class="chip chip-patch" type="button" data-filter="cat:Patch Notes">Patch Notes</button><?php endif; ?>
            <?php foreach ($categories as $category): ?>
                <button class="chip" type="button" data-filter="cat:<?= reg_h($category) ?>"><?= reg_h($category) ?></button>
            <?php endforeach; ?>
            <?php foreach (REG_TYPE_ICONS as $type => $icon): ?>
                <button class="chip chip-type" type="button" data-filter="type:<?= reg_h($type) ?>"><?= reg_h(REG_TYPES[$type] . 's') ?></button>
            <?php endforeach; ?>
        </div>
        </div>
        <div class="reg-home" id="regHome">
            <div class="home-grid">
                <button class="home-card home-card-all" type="button" data-filter="all" data-home-open>
                    <span class="home-medal"><?= reg_icon('open-book') ?></span>
                    <span class="home-name">Todas las reglas</span>
                    <span class="home-count"><?= count($rules) ?> reglas</span>
                </button>
                <?php if ($patches || $isAdmin): ?>
                <button class="home-card home-card-patch" type="button" data-filter="cat:Patch Notes">
                    <span class="home-medal"><?= reg_icon('scroll-quill') ?></span>
                    <span class="home-name">Patch Notes</span>
                    <span class="home-count"><?= count($patches) ?> versiones</span>
                </button>
                <?php endif; ?>
                <?php foreach ($homeCards as $card): ?>
                <button class="home-card home-card-<?= reg_h($card['tone']) ?>" type="button" data-filter="<?= reg_h($card['filter']) ?>">
                    <span class="home-medal"><?= reg_icon($card['icon']) ?></span>
                    <span class="home-name"><?= reg_h($card['label']) ?></span>
                    <span class="home-count"><?= $card['count'] ?> <?= $card['count'] === 1 ? rtrim($card['unit'], 's') : $card['unit'] ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php if ($patches): ?>
            <h2 class="home-heading"><span>Cambios recientes</span></h2>
            <div class="home-recent">
                <?php foreach (array_slice($patches, 0, 3) as $patch): ?>
                <a class="recent-card" href="#<?= reg_h($patch['id']) ?>">
                    <span class="recent-version">v<?= reg_h($patch['version']) ?></span>
                    <span class="recent-text">
                        <span class="recent-date"><?= reg_h($patch['dateLabel']) ?></span>
                        <strong><?= reg_h($patch['name'] !== '' ? $patch['name'] : 'Versión ' . $patch['version']) ?></strong>
                        <small><?= reg_h($patch['summary']) ?></small>
                    </span>
                    <span class="chev" aria-hidden="true">›</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($isAdmin): ?>
            <button class="new-rule" type="button" id="newRule"><span aria-hidden="true">+</span> <em id="newRuleLabel">Nueva regulación</em></button>
        <?php endif; ?>

        <ul class="rule-list" id="ruleList">
            <?php foreach ($rules as $rule):
                $id = (string)($rule['id'] ?? '');
                $type = (string)($rule['type'] ?? 'regla');
                $category = (string)($rule['category'] ?? '');
                $title = (string)($rule['title'] ?? 'Sin título');
                $summary = (string)($rule['summary'] ?? '');
                $search = mb_strtolower($title . ' ' . $category . ' ' . $summary . ' ' . (string)($rule['body'] ?? ''), 'UTF-8');
            ?>
            <li class="rule-item" id="<?= reg_h($id) ?>"
                data-type="<?= reg_h($type) ?>" data-category="<?= reg_h($category) ?>" data-search="<?= reg_h($search) ?>">
                <button class="rule-open" type="button" aria-controls="ruleDetail">
                    <span class="medal"><?= reg_icon((string)($rule['icon'] ?? '') ?: reg_category_icon($category)) ?></span>
                    <span class="rule-text">
                        <span class="rule-line">
                            <span class="rule-title"><?= reg_h($title) ?></span>
                            <span class="pill"><?= reg_h($category) ?></span>
                            <?php if ($type !== 'regla'): ?><span class="pill pill-<?= reg_h($type) ?>"><?= reg_h(REG_TYPES[$type] ?? $type) ?></span><?php endif; ?>
                            <?php if (!empty($rule['draft'])): ?><span class="pill pill-draft">Borrador</span><?php endif; ?>
                        </span>
                        <?php if ($summary !== ''): ?><span class="rule-summary"><?= reg_h($summary) ?></span><?php endif; ?>
                    </span>
                    <span class="rule-date"><?= reg_h((string)($rule['updated'] ?? '')) ?></span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
                <template class="rule-tpl">
                    <div class="detail-head">
                        <span class="medal medal-big"><?= reg_icon((string)($rule['icon'] ?? '') ?: reg_category_icon($category)) ?></span>
                        <div class="detail-titles">
                            <p class="detail-tags">
                                <span class="pill"><?= reg_h($category) ?></span>
                                <?php if ($type !== 'regla'): ?><span class="pill pill-<?= reg_h($type) ?>"><?= reg_h(REG_TYPES[$type] ?? $type) ?></span><?php endif; ?>
                                <?php if (!empty($rule['mesa'])): ?><span class="pill pill-mesa">Regla de la mesa</span><?php endif; ?>
                                <?php if (!empty($rule['draft'])): ?><span class="pill pill-draft">Borrador · solo admin</span><?php endif; ?>
                            </p>
                            <h2 id="detailTitle"><?= reg_h($title) ?></h2>
                            <p class="detail-date">Última actualización<br><strong><?= reg_h((string)($rule['updated'] ?? '')) ?></strong></p>
                        </div>
                    </div>
                    <?php if ($summary !== ''): ?><p class="detail-summary"><?= reg_h($summary) ?></p><?php endif; ?>
                    <div class="detail-body"><?= reg_render((string)($rule['body'] ?? '')) ?></div>
                    <?php if (($rule['source'] ?? '') !== ''): ?><p class="detail-source">Fuente: <?= reg_h((string)$rule['source']) ?></p><?php endif; ?>
                </template>
            </li>
            <?php endforeach; ?>
            <?php foreach ($patches as $patch):
                $search = mb_strtolower('patch notes parche version ' . $patch['version'] . ' ' . $patch['name'] . ' ' . $patch['dateLabel'] . ' ' . $patch['body'], 'UTF-8');
            ?>
            <li class="rule-item rule-patch" id="<?= reg_h($patch['id']) ?>"
                data-type="parche" data-category="Patch Notes" data-search="<?= reg_h($search) ?>">
                <button class="rule-open" type="button" aria-controls="ruleDetail">
                    <span class="medal medal-version"><small>v</small><?= reg_h($patch['version']) ?></span>
                    <span class="rule-text">
                        <span class="rule-line">
                            <span class="rule-title"><?= reg_h($patch['name'] !== '' ? $patch['name'] : 'Versión ' . $patch['version']) ?></span>
                            <span class="pill pill-patch pill-date"><?= reg_h($patch['dateLabel']) ?></span>
                        </span>
                        <span class="rule-summary"><?= reg_h($patch['summary']) ?></span>
                    </span>
                    <span class="rule-date"><?= reg_h($patch['dateLabel']) ?></span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
                <template class="rule-tpl">
                    <div class="detail-head">
                        <span class="medal medal-big medal-version"><small>versión</small><?= reg_h($patch['version']) ?></span>
                        <div class="detail-titles">
                            <p class="detail-tags"><span class="pill pill-patch">Patch Notes</span><span class="pill">Versión <?= reg_h($patch['version']) ?></span></p>
                            <h2 id="detailTitle"><?= reg_h($patch['name'] !== '' ? '«' . $patch['name'] . '»' : 'Versión ' . $patch['version']) ?></h2>
                            <p class="detail-date">Publicado<br><strong><?= reg_h($patch['dateLabel']) ?></strong></p>
                        </div>
                    </div>
                    <p class="detail-summary detail-history">Nota histórica: una versión posterior puede cambiar lo que dice esta.</p>
                    <div class="detail-body"><?= reg_render($patch['body']) ?></div>
                </template>
            </li>
            <?php endforeach; ?>
        </ul>

        <div id="emptyState" class="empty-state" hidden>
            <?= reg_icon('scroll-unfurled', 'reg-icon empty-icon') ?>
            <h2>No hay regulaciones aquí</h2>
            <p id="emptyText">Prueba con otro filtro o término de búsqueda.</p>
        </div>
    </section>

    <section class="page page-detail" id="ruleDetail" aria-live="polite" aria-labelledby="detailTitle" tabindex="-1">
        <button class="detail-close" type="button" id="detailClose">‹ Volver a la lista</button>
        <div id="detailContent" class="detail-content">
            <div class="detail-placeholder">
                <?= reg_icon('open-book', 'reg-icon empty-icon') ?>
                <p>Elige una regulación de la lista para leerla.</p>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <div class="detail-admin" id="detailAdmin" hidden>
                <button type="button" class="btn" id="editRule">Editar</button>
                <button type="button" class="btn btn-danger" id="deleteRule">Borrar</button>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="reg-footer">
    Favor de Jack · Reglamento del Gremio · Iconos de <a href="https://game-icons.net" target="_blank" rel="noopener">game-icons.net</a> (CC BY 3.0)
</footer>

<?php if ($isAdmin): ?>
<dialog class="editor" id="ruleEditor" aria-labelledby="editorTitle">
    <form method="dialog" id="ruleForm" novalidate>
        <h2 id="editorTitle">Nueva regulación</h2>
        <div class="field-grid">
            <label class="field field-wide">Título
                <input name="title" maxlength="120" required autocomplete="off">
            </label>
            <label class="field">Categoría
                <input name="category" maxlength="40" list="regCategories" required autocomplete="off" placeholder="Elige o escribe una nueva">
                <datalist id="regCategories">
                    <?php foreach ($categories as $category): ?><option value="<?= reg_h($category) ?>"><?php endforeach; ?>
                </datalist>
            </label>
            <label class="field">Tipo
                <select name="type">
                    <?php foreach (REG_TYPES as $value => $label): ?><option value="<?= reg_h($value) ?>"><?= reg_h($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field field-wide">Resumen <small>(una línea, sale en la lista)</small>
                <input name="summary" maxlength="200" autocomplete="off">
            </label>
            <fieldset class="field field-wide icon-field">
                <legend>Icono</legend>
                <div class="icon-grid" id="iconGrid"></div>
            </fieldset>
            <div class="field field-wide">
                <label for="ruleBody">Texto</label>
                <div class="md-editor" data-md-editor>
                    <textarea id="ruleBody" name="body" rows="12" maxlength="20000" required></textarea>
                </div>
            </div>
            <label class="field">Fuente <small>(opcional)</small>
                <input name="source" maxlength="120" autocomplete="off" placeholder="PHB, pág. 192">
            </label>
            <div class="field checks">
                <label><input type="checkbox" name="mesa"> Regla de la mesa</label>
                <label><input type="checkbox" name="draft"> Borrador (solo lo ve el admin)</label>
            </div>
        </div>
        <p class="editor-error" id="editorError" role="alert" hidden></p>
        <div class="editor-actions">
            <button type="button" class="btn" value="cancel" id="editorCancel">Cancelar</button>
            <button type="submit" class="btn btn-gold" id="editorSave">Guardar</button>
        </div>
    </form>
</dialog>
<dialog class="editor editor-patch" id="patchEditor" aria-labelledby="patchEditorTitle">
    <form method="dialog" id="patchForm" novalidate>
        <h2 id="patchEditorTitle">Nuevo parche</h2>
        <div class="field-grid field-grid-patch">
            <label class="field">Versión
                <input name="version" maxlength="20" required autocomplete="off" inputmode="decimal" placeholder="1.9">
            </label>
            <label class="field">Fecha
                <input name="date" type="date" required>
            </label>
            <label class="field field-wide">Nombre del parche <small>(opcional, por ejemplo «Sed incesante»)</small>
                <input name="name" maxlength="120" autocomplete="off">
            </label>
            <div class="field field-wide">
                <label for="patchBody">Cambios de esta versión</label>
                <div class="md-editor" data-md-editor data-md-sections="Novedades|Reajustes|Rework|Buffs|Nerfeos|Baneos|Desbaneos">
                    <textarea id="patchBody" name="body" rows="12" maxlength="20000" required></textarea>
                </div>
            </div>
        </div>
        <p class="editor-error" id="patchError" role="alert" hidden></p>
        <div class="editor-actions">
            <button type="button" class="btn" id="patchCancel">Cancelar</button>
            <button type="submit" class="btn btn-gold" id="patchSave">Guardar parche</button>
        </div>
    </form>
</dialog>
<script>window.REG_ADMIN = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>
<?php if ($isAdmin): ?><script src="assets/editor.js<?= favor_v(__DIR__ . '/assets/editor.js') ?>" defer></script><?php endif; ?>
<script src="assets/regulations.js<?= favor_v(__DIR__ . '/assets/regulations.js') ?>" defer></script>
</body>
</html>
