<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
$authPath = dirname(__DIR__) . '/includes/auth.php';
$user = null;

if (is_file($authPath)) {
    require_once $authPath;
    if (function_exists('favor_session_start')) {
        favor_session_start();
        if (function_exists('favor_current_user')) {
            $user = favor_current_user();
        }
        if (function_exists('favor_session_close')) {
            favor_session_close();
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

function rg_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$dataPath = __DIR__ . '/data/regulations.json';
$payload = ['tabs' => [], 'rules' => [], 'updated' => ''];

if (is_file($dataPath)) {
    $decoded = json_decode((string)file_get_contents($dataPath), true);
    if (is_array($decoded)) {
        $payload = array_replace($payload, $decoded);
    }
}

$rules = is_array($payload['rules'] ?? null) ? $payload['rules'] : [];
$tabs = is_array($payload['tabs'] ?? null) ? $payload['tabs'] : [];
$updated = (string)($payload['updated'] ?? '');

$categories = [];
foreach ($rules as $rule) {
    $category = trim((string)($rule['category'] ?? 'General'));
    if ($category !== '') $categories[$category] = true;
}
$categories = array_keys($categories);
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
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cinzel+Decorative:wght@700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/regulations.css<?= favor_v(__DIR__ . '/assets/regulations.css') ?>">
<?php favor_head('Reglamento · Favor de Jack'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<header class="topbar">
    <div class="identity">
        <?php if ($user): ?>
            <span><?= rg_h((string)($user['displayName'] ?? $user['username'] ?? 'Aventurero')) ?></span>
        <?php else: ?>
            <span>Modo lectura</span>
        <?php endif; ?>
    </div>
</header>

<main class="shell">
    <section class="masthead">
        <div class="seal" aria-hidden="true">⚖</div>
        <p class="eyebrow">Archivo oficial del gremio</p>
        <h1>Regulaciones</h1>
        <p class="lead">Consulta rápida de reglas, ajustes y restricciones registradas para las partidas de Favor de Jack.</p>
        <?php if ($updated !== ''): ?>
            <p class="updated">Actualizado: <?= rg_h($updated) ?></p>
        <?php endif; ?>
    </section>

    <section class="controls" aria-label="Filtros de regulaciones">
        <label class="searchbox">
            <span aria-hidden="true">⌕</span>
            <input id="ruleSearch" type="search" autocomplete="off" placeholder="Buscar regulación, acción, estado...">
        </label>

        <div class="type-tabs" role="tablist" aria-label="Tipo de regulación">
            <?php foreach ($tabs as $i => $tab): 
                $id = (string)($tab['id'] ?? '');
                $label = (string)($tab['label'] ?? $id);
            ?>
                <button class="type-tab<?= $i === 0 ? ' active' : '' ?>"
                        type="button"
                        data-type="<?= rg_h($id) ?>"
                        role="tab"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                    <?= rg_h($label) ?>
                    <span class="tab-count" data-count-type="<?= rg_h($id) ?>">0</span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="category-row" id="categoryFilters">
            <button class="category-chip active" type="button" data-category="">Todas</button>
            <?php foreach ($categories as $category): ?>
                <button class="category-chip" type="button" data-category="<?= rg_h($category) ?>"><?= rg_h($category) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="result-line">
        <strong id="visibleCount"><?= count($rules) ?></strong>
        <span>regulaciones visibles</span>
    </div>

    <section id="rulesGrid" class="rules-grid">
        <?php foreach ($rules as $rule):
            $id = (string)($rule['id'] ?? '');
            $title = (string)($rule['title'] ?? 'Sin título');
            $category = (string)($rule['category'] ?? 'General');
            $type = (string)($rule['type'] ?? 'regla');
            $content = (string)($rule['html'] ?? '');
            $searchText = trim(strip_tags($title . ' ' . $category . ' ' . $content));
        ?>
            <article class="rule-card"
                     id="<?= rg_h($id) ?>"
                     data-type="<?= rg_h($type) ?>"
                     data-category="<?= rg_h($category) ?>"
                     data-search="<?= rg_h(mb_strtolower($searchText, 'UTF-8')) ?>">
                <details>
                    <summary>
                        <span class="rule-heading">
                            <span class="rule-category"><?= rg_h($category) ?></span>
                            <span class="rule-title"><?= rg_h($title) ?></span>
                        </span>
                        <span class="chevron" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="rule-content">
                        <?= $content ?>
                    </div>
                </details>
            </article>
        <?php endforeach; ?>
    </section>

    <section id="emptyState" class="empty-state hidden">
        <div class="empty-symbol">✦</div>
        <h2>No hay regulaciones en esta sección</h2>
        <p id="emptyText">Prueba con otro filtro o término de búsqueda.</p>
    </section>

    <footer>
        Favor de Jack · Reglamento del Gremio
    </footer>
</main>

<script src="assets/regulations.js<?= favor_v(__DIR__ . '/assets/regulations.js') ?>" defer></script>
</body>
</html>
