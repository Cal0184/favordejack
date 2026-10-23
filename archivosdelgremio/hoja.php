<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/ficha-import.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';

favor_session_start();
$id = (string)($_GET['id'] ?? '');
$GLOBALS['favor_back'] = ['archivosdelgremio/?character=' . rawurlencode($id), 'a su archivo'];

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
$block = static function (string $title, ?string $text): string {
    $text = trim((string)$text);
    return $text === '' ? '' : '<section class="card"><h2>' . h($title) . '</h2><div class="text">' . nl2br(h($text)) . '</div></section>';
};
$mod = static fn(?int $v): string => $v === null ? '' : (($m = intdiv($v - 10 + 20, 2) - 10) >= 0 ? '+' . $m : (string)$m);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2b1a10">
    <title><?= h($row ? $row['Nombre'] . ' · Hoja' : 'Hoja no encontrada') ?> · Archivos del Gremio</title>
    <link rel="icon" href="<?= favor_base_path() ?>favicon.ico">
    <style>
        :root { --ink:#2b1a10; --paper:#f3e6c8; --paper2:#e9d6ac; --muted:#6b5338; --line:#c9ad78; }
        * { box-sizing:border-box; }
        body { margin:0; background:#1d120b; color:var(--ink); font:16px/1.5 Georgia, "Times New Roman", serif; }
        .wrap { max-width:1000px; margin:0 auto; padding:24px 16px 64px; }
        .top { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; color:#e8d3a4; margin-bottom:16px; }
        .top a { color:#e8d3a4; }
        h1 { margin:0; font-size:1.7rem; }
        .sub { color:#cdb68a; margin:2px 0 0; }
        .card { background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:16px 18px; margin-bottom:14px; box-shadow:0 6px 24px rgba(0,0,0,.35); }
        h2 { margin:0 0 10px; font-size:1.05rem; border-bottom:1px solid var(--line); padding-bottom:4px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:8px; }
        .stat { background:var(--paper2); border-radius:8px; padding:8px; text-align:center; }
        .stat b { display:block; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
        .stat span { font-size:1.3rem; }
        .stat small { display:block; color:var(--muted); }
        .cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
        ul.list { list-style:none; margin:0; padding:0; columns:2; column-gap:18px; }
        ul.list li { display:flex; justify-content:space-between; gap:8px; border-bottom:1px dotted var(--line); padding:2px 0; break-inside:avoid; }
        ul.list li.prof { font-weight:bold; }
        table { width:100%; border-collapse:collapse; }
        td, th { text-align:left; padding:5px 6px; border-bottom:1px solid var(--line); }
        .text { white-space:normal; overflow-wrap:anywhere; }
        .muted { color:var(--muted); font-size:.9rem; }
        .btn { color:#f3e6c8; background:#5a3a1c; border-radius:8px; padding:6px 12px; text-decoration:none; }
        @media (max-width:520px) { ul.list { columns:1; } }
    </style>
<?php favor_head('Hoja de personaje · Favor de Jack'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<div class="wrap">
<?php if (!$row): ?>
    <div class="top"><h1>Hoja no encontrada</h1><a href="<?= favor_base_path() ?>archivosdelgremio/">← Archivos del Gremio</a></div>
    <div class="card">Este personaje todavía no tiene una ficha importada.</div>
<?php else: ?>
    <div class="top">
        <div>
            <h1><?= h($p['NombreCompleto'] ?? $row['Nombre']) ?></h1>
            <p class="sub"><?= h(implode(' · ', array_filter([$s['claseNivel'] ?? '', $p['Raza'] ?? '', $s['trasfondo'] ?? '', $s['alineamiento'] ?? '', 'Jugador: ' . ($row['JugadorNombre'] ?? $row['Jugador'])]))) ?></p>
        </div>
        <div>
            <?php if ($canEdit): ?><a class="btn" href="?id=<?= h(rawurlencode($id)) ?>&amp;pdf=1" target="_blank" rel="noopener">PDF original</a>
            <a class="btn" href="importar-ficha.php?character=<?= h(rawurlencode($id)) ?>">Reimportar</a><?php endif; ?>
        </div>
    </div>

    <section class="card">
        <div class="grid">
            <?php foreach (['Fuerza' => 'FUE', 'Destreza' => 'DES', 'Constitucion' => 'CON', 'Inteligencia' => 'INT', 'Sabiduria' => 'SAB', 'Carisma' => 'CAR'] as $k => $label): ?>
                <div class="stat"><b><?= $label ?></b><span><?= h($p[$k] ?? '—') ?></span><small><?= h($mod(isset($p[$k]) ? (int)$p[$k] : null)) ?></small></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <div class="grid">
            <?php foreach (['ca' => 'Clase de armadura', 'iniciativa' => 'Iniciativa', 'velocidad' => 'Velocidad', 'pgMax' => 'PG máximos', 'pgTemporales' => 'PG temporales', 'dadosGolpe' => 'Dados de golpe', 'competencia' => 'Competencia', 'percepcionPasiva' => 'Percepción pasiva', 'experiencia' => 'Experiencia'] as $k => $label): ?>
                <?php if (($s[$k] ?? '') !== ''): ?><div class="stat"><b><?= h($label) ?></b><span><?= h($s[$k]) ?></span></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="cols">
        <?php if (!empty($s['salvaciones'])): ?>
            <section class="card"><h2>Tiradas de salvación</h2><ul class="list">
                <?php foreach ($s['salvaciones'] as $x): ?><li class="<?= !empty($x['proficient']) ? 'prof' : '' ?>"><span><?= !empty($x['proficient']) ? '● ' : '○ ' ?><?= h($x['name']) ?></span><span><?= h($x['value']) ?></span></li><?php endforeach; ?>
            </ul></section>
        <?php endif; ?>
        <?php if (!empty($s['habilidades'])): ?>
            <section class="card"><h2>Habilidades</h2><ul class="list">
                <?php foreach ($s['habilidades'] as $x): ?><li class="<?= !empty($x['proficient']) ? 'prof' : '' ?>"><span><?= !empty($x['proficient']) ? '● ' : '○ ' ?><?= h($x['name']) ?></span><span><?= h($x['value']) ?></span></li><?php endforeach; ?>
            </ul></section>
        <?php endif; ?>
    </div>

    <?php if (!empty($s['ataques'])): ?>
        <section class="card"><h2>Ataques</h2><table>
            <tr><th>Nombre</th><th>Bonificador</th><th>Daño / tipo</th></tr>
            <?php foreach ($s['ataques'] as $a): ?><tr><td><?= h($a['name']) ?></td><td><?= h($a['bonus']) ?></td><td><?= h($a['damage']) ?></td></tr><?php endforeach; ?>
        </table><?php if (($s['ataquesNotas'] ?? '') !== ''): ?><p class="text"><?= nl2br(h($s['ataquesNotas'])) ?></p><?php endif; ?></section>
    <?php endif; ?>

    <div class="cols">
        <?= $block('Rasgos de personalidad', $s['personalidad'] ?? '') ?>
        <?= $block('Ideales', $s['ideales'] ?? '') ?>
        <?= $block('Vínculos', $s['vinculos'] ?? '') ?>
        <?= $block('Defectos', $s['defectos'] ?? '') ?>
    </div>
    <?= $block('Rasgos y atributos', $s['rasgos'] ?? '') ?>
    <?= $block('Rasgos y atributos adicionales', $s['rasgosAdicionales'] ?? '') ?>
    <?= $block('Otras competencias e idiomas', $s['competenciasIdiomas'] ?? '') ?>
    <?php if (!empty($s['aspecto'])): ?>
        <section class="card"><h2>Aspecto</h2><div class="grid">
            <?php foreach ($s['aspecto'] as $k => $v): ?><div class="stat"><b><?= h($k) ?></b><span style="font-size:1rem"><?= h($v) ?></span></div><?php endforeach; ?>
        </div></section>
    <?php endif; ?>
    <?= $block('Aliados y organizaciones', $s['aliados'] ?? '') ?>
    <?= $block('Historia del personaje', $p['Biografia'] ?? '') ?>
    <?php if (!empty($s['conjuros'])): $c = $s['conjuros']; ?>
        <section class="card"><h2>Conjuros</h2>
            <p><?= h(implode(' · ', array_filter([($c['clase'] ?? '') !== '' ? 'Clase: ' . $c['clase'] : '', ($c['aptitud'] ?? '') !== '' ? 'Aptitud: ' . $c['aptitud'] : '', ($c['cd'] ?? '') !== '' ? 'CD: ' . $c['cd'] : '', ($c['ataque'] ?? '') !== '' ? 'Ataque: ' . $c['ataque'] : '']))) ?></p>
            <?php if (!empty($c['lista'])): ?><p><?= h(implode(' · ', $c['lista'])) ?></p><?php endif; ?>
        </section>
    <?php endif; ?>
    <p class="muted" style="color:#cdb68a">Importada el <?= h(substr((string)$row['Importado'], 0, 16)) ?> (UTC)<?= $row['ImportadoPor'] ? ' por ' . h($row['ImportadoPor']) : '' ?>.</p>
<?php endif; ?>
</div>
</body>
</html>
