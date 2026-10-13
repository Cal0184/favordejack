<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/ficha-import.php';

favor_session_start();
$id = (string)($_GET['id'] ?? '');

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$row = null;
$data = [];
$canEdit = false;
try {
    $db = favor_db();
    ficha_ensure_schema($db);
    $stmt = ficha_q($db, 'SELECT f.*, p.Nombre, p.Jugador, p.PortraitUrl, j.Nombre AS JugadorNombre FROM PersonajeFicha f '
        . 'JOIN Personaje p ON p.ID=f.PersonajeID LEFT JOIN Jugador j ON j.ID=p.Jugador WHERE f.PersonajeID=?', [$id]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();
    $user = favor_session_user();
    $canEdit = $row && $user && ficha_can_edit($user, $row);
    if ($row) $data = json_decode((string)$row['Datos'], true) ?: [];
} catch (Throwable $e) {
    error_log('hoja: ' . $e);
}

if ($row && $canEdit && isset($_GET['pdf'])) {
    $path = ficha_pdf_path((string)$row['PersonajeID']);
    if (is_file($path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('~[^A-Za-z0-9_ -]~', '', (string)$row['Nombre']) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}

header('Content-Type: text/html; charset=utf-8');
if (!$row) http_response_code(404);
$s = $data['hoja'] ?? [];
$p = $data['perfil'] ?? [];
$block = static function (string $title, ?string $text, string $icon = '✦'): string {
    $text = trim((string)$text);
    return $text === '' ? '' : '<section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">' . $icon . '</span><h2>' . h($title) . '</h2></div><div class="fx-text">' . h($text) . '</div></section>';
};
$base = favor_base_path();
$mod = static fn(?int $v): string => $v === null ? '' : (($m = intdiv($v - 10 + 20, 2) - 10) >= 0 ? '+' . $m : (string)$m);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#061813">
    <title><?= h($row ? $row['Nombre'] . ' · Hoja' : 'Hoja no encontrada') ?> · Favor de Jack</title>
    <link rel="icon" href="<?= h($base) ?>favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="ficha.css?v=<?= (int)@filemtime(__DIR__ . '/ficha.css') ?>">
    <style>:root{--bg-image:url("<?= h($base) ?>inventario/assets/ui/inventory-bg.webp");--paper-image:url("<?= h($base) ?>inventario/assets/ui/inventory-parchment.webp")}</style>
</head>
<body>
<header class="fx-top">
    <a class="fx-brand" href="<?= h($base) ?>">
        <img src="<?= h($base) ?>inventario/assets/ui/favor-logo.svg" alt="" aria-hidden="true">
        <span><b>Favor de Jack</b><small>Hoja de personaje</small></span>
    </a>
    <nav>
        <a class="fx-btn" href="<?= h($base) ?>archivosdelgremio/<?= $row ? '?character=' . h(rawurlencode($id)) : '' ?>">← Archivo</a>
        <?php if ($row): ?><a class="fx-btn hide-sm" href="<?= h($base) ?>inventario/?character=<?= h(rawurlencode($id)) ?>">Inventario</a><?php endif; ?>
    </nav>
</header>
<main class="fx-wrap">
<?php if (!$row): ?>
    <section class="fx-card"><div class="fx-head"><span class="ico">📜</span><h2>Hoja no encontrada</h2></div><p class="fx-lead">Este personaje todavía no tiene una ficha importada.</p></section>
<?php else: ?>
    <section class="fx-card">
        <div class="fx-hero">
            <div>
                <span class="fx-eyebrow">Jugador: <?= h($row['JugadorNombre'] ?? $row['Jugador']) ?></span>
                <h1><?= h($p['NombreCompleto'] ?? $row['Nombre']) ?></h1>
                <div class="sub"><?= h(implode(' · ', array_filter([$s['claseNivel'] ?? '', $p['Raza'] ?? '', $s['trasfondo'] ?? '', $s['alineamiento'] ?? '']))) ?></div>
            </div>
            <?php if ($canEdit): ?>
                <div class="fx-links">
                    <a class="fx-btn" href="?id=<?= h(rawurlencode($id)) ?>&amp;pdf=1" target="_blank" rel="noopener">PDF original</a>
                    <a class="fx-btn fx-btn--primary" href="importar-ficha.php?character=<?= h(rawurlencode($id)) ?>">Reimportar ficha</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="fx-stats">
            <?php foreach (['Fuerza' => 'FUE', 'Destreza' => 'DES', 'Constitucion' => 'CON', 'Inteligencia' => 'INT', 'Sabiduria' => 'SAB', 'Carisma' => 'CAR'] as $k => $label): ?>
                <div class="fx-stat"><b><?= $label ?></b><span><?= h($p[$k] ?? '—') ?></span><small><?= h($mod(isset($p[$k]) ? (int)$p[$k] : null)) ?></small></div>
            <?php endforeach; ?>
        </div>
        <hr class="fx-divider">
        <div class="fx-grid">
            <?php foreach (['ca' => 'Clase de armadura', 'pgMax' => 'PG máximos', 'pgTemporales' => 'PG temporales', 'iniciativa' => 'Iniciativa', 'velocidad' => 'Velocidad', 'dadosGolpe' => 'Dados de golpe', 'competencia' => 'Competencia', 'percepcionPasiva' => 'Percepción pasiva', 'experiencia' => 'Experiencia'] as $k => $label): ?>
                <?php if (($s[$k] ?? '') !== ''): ?><div class="fx-box"><b><?= h($label) ?></b><span><?= h($s[$k]) ?></span></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="fx-cols">
        <?php if (!empty($s['salvaciones'])): ?>
            <section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">⛨</span><h2>Tiradas de salvación</h2></div><ul class="fx-list">
                <?php foreach ($s['salvaciones'] as $x): ?><li class="<?= !empty($x['proficient']) ? 'prof' : '' ?>"><span><span class="dot"><?= !empty($x['proficient']) ? '●' : '○' ?></span><?= h($x['name']) ?></span><span><?= h($x['value']) ?></span></li><?php endforeach; ?>
            </ul></section>
        <?php endif; ?>
        <?php if (!empty($s['habilidades'])): ?>
            <section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">✧</span><h2>Habilidades</h2></div><ul class="fx-list">
                <?php foreach ($s['habilidades'] as $x): ?><li class="<?= !empty($x['proficient']) ? 'prof' : '' ?>"><span><span class="dot"><?= !empty($x['proficient']) ? '●' : '○' ?></span><?= h($x['name']) ?></span><span><?= h($x['value']) ?></span></li><?php endforeach; ?>
            </ul></section>
        <?php endif; ?>
    </div>
    <div style="height:18px"></div>

    <?php if (!empty($s['ataques'])): ?>
        <section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">⚔</span><h2>Ataques</h2></div>
            <table class="fx-table"><tr><th>Nombre</th><th>Bonificador</th><th>Daño / tipo</th></tr>
            <?php foreach ($s['ataques'] as $a): ?><tr><td><?= h($a['name']) ?></td><td><?= h($a['bonus']) ?></td><td><?= h($a['damage']) ?></td></tr><?php endforeach; ?>
            </table>
            <?php if (($s['ataquesNotas'] ?? '') !== ''): ?><p class="fx-text"><?= h($s['ataquesNotas']) ?></p><?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($s['conjuros'])): $c = $s['conjuros']; ?>
        <section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">✺</span><h2>Conjuros</h2></div>
            <?php $info = array_filter([($c['clase'] ?? '') !== '' ? 'Clase: ' . $c['clase'] : '', ($c['aptitud'] ?? '') !== '' ? 'Aptitud: ' . $c['aptitud'] : '', ($c['cd'] ?? '') !== '' ? 'CD: ' . $c['cd'] : '', ($c['ataque'] ?? '') !== '' ? 'Ataque: ' . $c['ataque'] : '']); ?>
            <?php if ($info): ?><div class="fx-pills" style="margin:0 0 12px"><?php foreach ($info as $i): ?><span class="fx-pill gold"><?= h($i) ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if (!empty($c['lista'])): ?><div class="fx-chips"><?php foreach ($c['lista'] as $sp): ?><span class="fx-chip"><?= h($sp) ?></span><?php endforeach; ?></div><?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="fx-cols">
        <?= $block('Rasgos de personalidad', $s['personalidad'] ?? '', '☙') ?>
        <?= $block('Ideales', $s['ideales'] ?? '', '☼') ?>
        <?= $block('Vínculos', $s['vinculos'] ?? '', '∞') ?>
        <?= $block('Defectos', $s['defectos'] ?? '', '☠') ?>
    </div>
    <div style="height:18px"></div>
    <?= $block('Rasgos y atributos', $s['rasgos'] ?? '', '✦') ?>
    <?= $block('Rasgos y atributos adicionales', $s['rasgosAdicionales'] ?? '', '✧') ?>
    <?= $block('Otras competencias e idiomas', $s['competenciasIdiomas'] ?? '', '✎') ?>
    <?php if (!empty($s['aspecto'])): ?>
        <section class="fx-card"><div class="fx-head"><span class="ico" aria-hidden="true">◐</span><h2>Aspecto</h2></div><div class="fx-grid">
            <?php foreach ($s['aspecto'] as $k => $v): ?><div class="fx-box"><b><?= h($k) ?></b><span style="font-size:1rem"><?= h($v) ?></span></div><?php endforeach; ?>
        </div></section>
    <?php endif; ?>
    <?= $block('Aliados y organizaciones', $s['aliados'] ?? '', '⚑') ?>
    <?= $block('Historia del personaje', $p['Biografia'] ?? '', '📜') ?>
    <p class="fx-foot">Ficha importada el <?= h(substr((string)$row['Importado'], 0, 16)) ?> (UTC)<?= $row['ImportadoPor'] ? ' por ' . h($row['ImportadoPor']) : '' ?>.</p>
<?php endif; ?>
</main>
</body>
</html>
