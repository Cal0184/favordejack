<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/imagenes.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/ficha-import.php';
require_once dirname(__DIR__) . '/includes/estado.php';
require_once dirname(__DIR__) . '/includes/recompensas.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
guild_session_start();
$csrf = guild_csrf();
$isAdmin = guild_is_admin();
$isLogged = guild_is_logged_in();
$sessionUser = guild_user();
$adminUser = guild_admin_user();
favor_session_close();

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean(?string $value): string {
    return trim((string)$value);
}

function portrait_url(?string $url): string {
    // Todas las imágenes de jugadores y personajes viven en media/ (fase 2);
    // favor_asset_url() traduce las rutas antiguas de bancadigital/.
    $value = trim(str_replace('\\', '/', (string)$url));
    return $value === '' ? '' : favor_asset_url($value);
}


$characters = [];
$players = [];
$missions = [];
$error = '';
$missionsInstalled = false;

try {
    $dbPath = __DIR__ . '/../bancadigital/db.php';
    if (!is_file($dbPath)) {
        throw new RuntimeException('No se encontró ' . favor_base_path() . 'bancadigital/db.php');
    }

    /** @var mysqli $db */
    $db = require $dbPath;
    if (!$db instanceof mysqli) {
        throw new RuntimeException('db.php no devolvió una conexión mysqli válida.');
    }
    favor_ensure_schema($db);
    guild_ensure_profile_category_schema($db);

    $playerResult = $db->query('SELECT ID, Nombre, Handle, Activo, Orden FROM Jugador ORDER BY COALESCE(Orden,9999), Nombre');
    while ($playerRow = $playerResult->fetch_assoc()) {
        $players[] = [
            'id' => (string)$playerRow['ID'],
            'name' => clean($playerRow['Nombre'] ?? ''),
            'handle' => clean($playerRow['Handle'] ?? ''),
            'active' => (bool)$playerRow['Activo'],
        ];
    }
    $playerResult->free();

    $sql = <<<'SQL'
SELECT
    p.ID,
    p.Jugador,
    p.Nombre,
    p.Descripcion,
    p.Notas,
    p.IconUrl,
    p.PortraitUrl,
    p.Orden,
    p.Activo,
    j.Nombre AS JugadorNombre,
    j.Handle AS JugadorHandle,
    pp.NombreCompleto,
    pp.Altura,
    pp.Edad,
    pp.Sexo,
    pp.Raza,
    pp.TipoRaza,
    pp.Clase,
    pp.Nivel,
    pp.Fuerza,
    pp.Destreza,
    pp.Constitucion,
    pp.Inteligencia,
    pp.Sabiduria,
    pp.Carisma,
    pp.Dotes,
    pp.NivelAmenaza,
    pp.Estado,
    pp.Categoria,
    pp.Biografia,
    pp.Actualizado,
    pp.ID AS PerfilID
FROM Personaje p
LEFT JOIN PersonajePerfil pp ON pp.ID = p.ID
LEFT JOIN Jugador j ON j.ID = p.Jugador
ORDER BY COALESCE(j.Orden,9999), COALESCE(p.Orden,9999), COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre)
SQL;

    // Personajes creados en la Banca que aún no tienen expediente también salen
    // (con su mismo ID): al guardarlos se crea el expediente. No salen los de
    // grupos especiales, ni los que ya tienen su ficha «gemela» en Archivos, ni
    // los que el archivero retiró a propósito («Retirar de Archivos»): los
    // creados aquí (ID arch-…) siempre nacen con expediente, así que sin él es
    // que se retiraron; los demás quedan apuntados en FavorMeta.
    $estados = favor_estado_todos($db);
    $removed = guild_removed_profiles($db);
    $result = $db->query($sql);
    while ($row = $result->fetch_assoc()) {
        $pending = $row['PerfilID'] === null;
        $est = $estados[(string)$row['ID']] ?? null;
        if ($pending && (!$est || $est['especial'] || $est['npc'] || $est['gemelos']
            || str_starts_with((string)$row['ID'], 'arch-') || isset($removed[(string)$row['ID']]))) continue;
        if ($pending) $row['Estado'] = $est['estado'];
        $characters[] = [
            'pendingProfile' => $pending,
            'id' => (string)$row['ID'],
            'playerId' => (string)$row['Jugador'],
            'name' => clean($row['NombreCompleto'] ?? '') ?: clean($row['Nombre'] ?? ''),
            'shortName' => clean($row['Nombre'] ?? ''),
            'player' => clean($row['JugadorNombre'] ?? '') ?: clean($row['Jugador'] ?? ''),
            'handle' => clean($row['JugadorHandle'] ?? ''),
            'portrait' => portrait_url($row['PortraitUrl'] ?? ''),
            'icon' => portrait_url($row['IconUrl'] ?? ''),
            // Miniatura para tarjetas y listas; el retrato entero solo en el expediente.
            'thumb' => favor_thumb_url((string)(($row['PortraitUrl'] ?? '') ?: ($row['IconUrl'] ?? ''))),
            'description' => clean($row['Descripcion'] ?? ''),
            'biography' => clean($row['Biografia'] ?? ''),
            'height' => $row['Altura'] !== null ? (string)$row['Altura'] : '',
            'age' => clean($row['Edad'] ?? ''),
            'sex' => clean($row['Sexo'] ?? ''),
            'race' => clean($row['Raza'] ?? ''),
            'raceType' => clean($row['TipoRaza'] ?? ''),
            'class' => clean($row['Clase'] ?? ''),
            'level' => $row['Nivel'] !== null ? (string)$row['Nivel'] : '',
            'str' => $row['Fuerza'] !== null ? (string)$row['Fuerza'] : '',
            'dex' => $row['Destreza'] !== null ? (string)$row['Destreza'] : '',
            'con' => $row['Constitucion'] !== null ? (string)$row['Constitucion'] : '',
            'int' => $row['Inteligencia'] !== null ? (string)$row['Inteligencia'] : '',
            'wis' => $row['Sabiduria'] !== null ? (string)$row['Sabiduria'] : '',
            'cha' => $row['Carisma'] !== null ? (string)$row['Carisma'] : '',
            'feats' => $row['Dotes'] !== null ? (string)$row['Dotes'] : '',
            'threat' => clean($row['NivelAmenaza'] ?? ''),
            'state' => clean($row['Estado'] ?? '') ?: 'Activo',
            'category' => clean($row['Categoria'] ?? '') ?: 'Personaje',
            'updated' => clean($row['Actualizado'] ?? ''),
        ];
    }
    $result->free();

    // Personajes con ficha PDF importada (botón "Hoja" en el folio).
    ficha_ensure_schema($db);
    try { ficha_pdf_secure_all(); } catch (Throwable $e) { error_log('fichas: ' . $e->getMessage()); }
    $sheetIds = [];
    $sheetResult = $db->query('SELECT PersonajeID FROM PersonajeFicha');
    while ($sheetRow = $sheetResult->fetch_row()) $sheetIds[(string)$sheetRow[0]] = true;
    $sheetResult->free();
    foreach ($characters as &$characterRef) $characterRef['hasSheet'] = isset($sheetIds[$characterRef['id']]);
    unset($characterRef);

    $tableCheck = $db->query("SHOW TABLES LIKE 'Mision'");
    $hasMission = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) $tableCheck->free();
    $tableCheck = $db->query("SHOW TABLES LIKE 'MisionPersonaje'");
    $hasMissionPerson = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) $tableCheck->free();
    $missionsInstalled = $hasMission && $hasMissionPerson;

    if ($missionsInstalled) {
        favor_recompensas_schema($db);
        $missionRows = [];
        $res = $db->query(
            'SELECT ID, Nombre, Fecha, Tipo, Estado, Descripcion, RecompensaGeneral, RecompensaCantidad, RecompensaMoneda, RecompensaModo, CreadoPor, Creado, Actualizado ' .
            'FROM Mision ORDER BY COALESCE(Fecha, DATE(Creado)) DESC, ID DESC'
        );
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['ID'];
            $missionRows[$id] = [
                'id' => $id,
                'name' => (string)$row['Nombre'],
                'date' => (string)($row['Fecha'] ?? ''),
                'type' => (string)$row['Tipo'],
                'state' => (string)$row['Estado'],
                'description' => (string)($row['Descripcion'] ?? ''),
                'generalReward' => (string)$row['RecompensaGeneral'],
                'rewardAmount' => $row['RecompensaCantidad'] !== null ? (int)$row['RecompensaCantidad'] : null,
                'rewardCoin' => (string)$row['RecompensaMoneda'],
                'rewardMode' => (string)$row['RecompensaModo'],
                'createdBy' => (string)$row['CreadoPor'],
                'created' => (string)$row['Creado'],
                'updated' => (string)$row['Actualizado'],
                'participants' => [],
            ];
        }
        $res->free();

        if ($missionRows) {
            $res = $db->query(
                'SELECT mp.MisionID, mp.PersonajeID, mp.NombreSnapshot, mp.ClaseSnapshot, mp.NivelSnapshot, mp.Recompensa, mp.Notas, ' .
                'COALESCE(NULLIF(pp.NombreCompleto,\'\'),p.Nombre,mp.NombreSnapshot) AS NombreActual, p.PortraitUrl ' .
                'FROM MisionPersonaje mp ' .
                'LEFT JOIN Personaje p ON p.ID = mp.PersonajeID ' .
                'LEFT JOIN PersonajePerfil pp ON pp.ID = mp.PersonajeID ' .
                'ORDER BY mp.MisionID DESC, NombreActual'
            );
            while ($row = $res->fetch_assoc()) {
                $missionId = (int)$row['MisionID'];
                if (!isset($missionRows[$missionId])) continue;
                $missionRows[$missionId]['participants'][] = [
                    'id' => (string)$row['PersonajeID'],
                    'name' => clean($row['NombreActual'] ?? '') ?: clean($row['NombreSnapshot'] ?? ''),
                    'snapshotName' => (string)$row['NombreSnapshot'],
                    'class' => (string)$row['ClaseSnapshot'],
                    'level' => $row['NivelSnapshot'] !== null ? (string)$row['NivelSnapshot'] : '',
                    'reward' => (string)$row['Recompensa'],
                    'notes' => (string)($row['Notas'] ?? ''),
                    'portrait' => favor_thumb_url((string)($row['PortraitUrl'] ?? '')),
                ];
            }
            $res->free();
        }
        $missions = array_values($missionRows);
    }

    $db->close();
} catch (Throwable $e) {
    http_response_code(500);
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2b1a10">
    <title>Archivos del Gremio · Favor de Jack</title>
    <meta name="guild-build" content="3.7-paths">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css<?= favor_v(__DIR__ . '/style.css') ?>">
<?php favor_head('Archivos del Gremio · Favor de Jack'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<div class="wood-backdrop" aria-hidden="true"></div>

<header class="guild-header">
    <div class="header-beam">

        <div class="guild-brand">
            <span class="brand-ornament" aria-hidden="true">❦</span>
            <div>
                <p>Biblioteca y Archivo Mayor</p>
                <h1>Archivos del Gremio</h1>
            </div>
            <span class="brand-ornament mirror" aria-hidden="true">❦</span>
        </div>
    </div>

    <nav class="book-tabs" aria-label="Secciones de los archivos">
        <button type="button" class="book-tab active" data-view="characters">Personajes</button>
        <button type="button" class="book-tab" data-view="charts">Gráficos</button>
        <button type="button" class="book-tab" data-view="missions">Registro de Misiones</button>
        <a class="book-tab relation-link" href="relaciones.php">Relaciones</a>
        <?php if ($isLogged): ?><a class="book-tab relation-link" href="importar-ficha.php">Importar ficha</a><?php endif; ?>
        <?php if ($isAdmin): ?>
            <button type="button" class="book-tab admin-tab" data-view="admin">Mesa del Archivero</button>
        <?php endif; ?>
    </nav>
</header>

<main class="library-shell">
    <?php if ($error !== ''): ?>
        <section class="parchment error-sheet">
            <h2>No se pudieron abrir los archivos</h2>
            <p><?= h($error) ?></p>
        </section>
    <?php else: ?>
        <section class="view active" id="view-characters" data-view-panel="characters">
            <div class="open-book character-book">
                <div class="book-page book-page-left">
                    <div class="page-heading">
                        <span class="chapter-mark">I</span>
                        <div>
                            <p class="overline">Índice de aventureros</p>
                            <h2>Personajes</h2>
                            <p>Expedientes conservados por el gremio. Selecciona un retrato para abrir su ficha.</p>
                        </div>
                    </div>

                    <div class="toolbar parchment-controls">
                        <label class="search-box">
                            <span aria-hidden="true">⌕</span>
                            <input id="searchInput" type="search" placeholder="Buscar nombre, jugador, raza o clase…" autocomplete="off">
                        </label>
                        <select id="classFilter" aria-label="Filtrar por clase"><option value="">Todas las clases</option></select>
                        <select id="playerFilter" aria-label="Filtrar por jugador"><option value="">Todos los jugadores</option></select>
                        <select id="categoryFilter" aria-label="Filtrar por tipo"><option value="">Todos los tipos</option><option value="Personaje">Personajes</option><option value="NPC">NPC</option></select>
                        <button type="button" class="ink-button subtle" id="clearFilters">Limpiar</button>
                    </div>
                    <p class="result-line" id="resultCount"><?= count($characters) ?> personajes en el índice</p>
                </div>

                <div class="book-page book-page-right character-catalogue-page">
                    <div class="character-catalogue">
                        <button class="catalog-arrow catalog-arrow-prev" id="characterPrev" type="button" aria-label="Página anterior" title="Página anterior">‹</button>

                        <div class="catalog-viewport">
                            <div id="characterGrid" class="character-grid" aria-live="polite"></div>
                            <div id="emptyState" class="empty-state" hidden>
                                <span>❧</span>
                                <h3>No encontramos ese expediente</h3>
                                <p>Prueba con otro término o limpia los filtros.</p>
                            </div>
                        </div>

                        <button class="catalog-arrow catalog-arrow-next" id="characterNext" type="button" aria-label="Página siguiente" title="Página siguiente">›</button>
                    </div>

                    <div class="catalog-footer" id="characterPager" aria-live="polite">
                        <span class="catalog-page-label" id="characterPageLabel">Página 1 de 1</span>
                        <div class="catalog-dots" id="characterPageDots" aria-label="Páginas del catálogo"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="view" id="view-charts" data-view-panel="charts" hidden>
            <div class="open-book charts-book">
                <div class="book-page">
                    <div class="page-heading compact">
                        <span class="chapter-mark">II</span>
                        <div>
                            <p class="overline">Censo del gremio</p>
                            <h2>Clases más jugadas</h2>
                            <p>Distribución calculada directamente desde los expedientes actuales.</p>
                        </div>
                    </div>
                    <div class="chart-list" id="classChart"></div>
                </div>
                <div class="book-page">
                    <div class="page-heading compact">
                        <span class="chapter-mark">III</span>
                        <div>
                            <p class="overline">Censo del gremio</p>
                            <h2>Razas más jugadas</h2>
                            <p>Las razas más frecuentes entre los personajes registrados.</p>
                        </div>
                    </div>
                    <div class="chart-list" id="raceChart"></div>
                </div>
            </div>

            <div class="height-ledger parchment">
                <div class="page-heading compact height-heading">
                    <span class="chapter-mark">IV</span>
                    <div>
                        <p class="overline">Galería de proporciones</p>
                        <h2>Comparación de alturas</h2>
                        <p>Todos los personajes con altura registrada, colocados sobre la misma escala. Los retratos están escalados según su altura real. Haz clic en un personaje para abrir su expediente.</p>
                    </div>
                </div>
                <div class="height-scroll" id="heightScroll">
                    <div class="height-stage" id="heightChart" aria-label="Comparación de alturas de los personajes"></div>
                </div>
            </div>
        </section>

        <section class="view" id="view-missions" data-view-panel="missions" hidden>
            <div class="mission-ledger parchment">
                <div class="page-heading compact">
                    <span class="chapter-mark">V</span>
                    <div>
                        <p class="overline">Libro de expediciones</p>
                        <h2>Registro de Misiones</h2>
                        <p>Qué misión realizó cada personaje, cuándo ocurrió y qué recompensa recibió.</p>
                    </div>
                </div>

                <?php if (!$missionsInstalled): ?>
                    <div class="notice-sheet">
                        <strong>El libro de misiones todavía no está instalado.</strong>
                        <p>Importa <code>archivosdelgremio_misiones.sql</code> una sola vez en phpMyAdmin.</p>
                    </div>
                <?php else: ?>
                    <div class="mission-toolbar">
                        <label class="search-box"><span>⌕</span><input id="missionSearch" type="search" placeholder="Buscar misión o personaje…"></label>
                    </div>
                    <div id="missionList" class="mission-list"></div>
                    <div id="missionEmpty" class="empty-state" hidden><span>✒</span><h3>El libro está vacío</h3><p>Aún no hay misiones registradas.</p></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isAdmin): ?>
        <section class="view" id="view-admin" data-view-panel="admin" hidden>
            <div class="admin-desk parchment">
                <div class="admin-heading">
                    <div>
                        <p class="overline">Acceso del archivero</p>
                        <h2>Mesa de Administración</h2>
                        <p>Sesión: <strong><?= h((string)($adminUser['username'] ?? 'admin')) ?></strong></p>
                    </div>
                    <button class="ink-button danger-outline" id="adminLogout" type="button">Cerrar sesión</button>
                </div>

                <div class="admin-tabs">
                    <button class="admin-section-tab active" type="button" data-admin-panel="characters">Editar personajes</button>
                    <button class="admin-section-tab" type="button" data-admin-panel="missions">Administrar misiones</button>
                </div>

                <div class="admin-panel active" data-admin-content="characters">
                    <div class="admin-character-dashboard">
                        <div class="admin-stat-grid" id="adminCharacterStats" aria-label="Resumen de expedientes"></div>

                        <div class="admin-actions-row admin-character-actions">
                            <button class="ink-button" type="button" id="newCharacter">+ Añadir personaje</button>
                            <a class="ink-button" href="importar-ficha.php">📜 Importar ficha PDF</a>
                            <span>Crear el personaje, su expediente y su retrato desde una sola pantalla, o leerlo de su ficha PDF.</span>
                        </div>

                        <div class="admin-filter-panel">
                            <label class="admin-search-box">
                                <span aria-hidden="true">⌕</span>
                                <input id="adminCharacterSearch" type="search" placeholder="Buscar nombre, jugador, raza o clase…" autocomplete="off">
                            </label>

                            <div class="admin-filter-grid">
                                <label>
                                    <span>Jugador</span>
                                    <select id="adminPlayerFilter"><option value="">Todos</option></select>
                                </label>
                                <label>
                                    <span>Estado</span>
                                    <select id="adminStateFilter"><option value="">Todos</option></select>
                                </label>
                                <label>
                                    <span>Clase</span>
                                    <select id="adminClassFilter"><option value="">Todas</option></select>
                                </label>
                                <label>
                                    <span>Tipo</span>
                                    <select id="adminCategoryFilter"><option value="">Todos</option><option value="Personaje">Personajes</option><option value="NPC">NPC</option></select>
                                </label>
                                <label>
                                    <span>Ordenar</span>
                                    <select id="adminSort">
                                        <option value="name-asc">Nombre A–Z</option>
                                        <option value="name-desc">Nombre Z–A</option>
                                        <option value="player">Jugador</option>
                                        <option value="state">Estado</option>
                                        <option value="updated">Última actualización</option>
                                    </select>
                                </label>
                            </div>

                            <div class="admin-filter-flags">
                                <label class="admin-check"><input id="adminMissingImage" type="checkbox"> <span>Solo sin imagen</span></label>
                                <label class="admin-check"><input id="adminIncomplete" type="checkbox"> <span>Solo datos incompletos</span></label>
                                <button class="ink-button subtle" id="adminClearFilters" type="button">Limpiar filtros</button>
                            </div>
                        </div>

                        <div class="admin-list-heading">
                            <p id="adminCharacterCount">0 expedientes</p>
                            <label class="admin-page-size">Mostrar
                                <select id="adminPageSize">
                                    <option value="24">24</option>
                                    <option value="48">48</option>
                                    <option value="96">96</option>
                                    <option value="all">Todos</option>
                                </select>
                            </label>
                        </div>

                        <div class="admin-list" id="adminCharacterList"></div>
                        <div class="admin-list-empty" id="adminCharacterEmpty" hidden>
                            <strong>No encontramos expedientes con esos filtros.</strong>
                            <span>Prueba otro nombre o limpia los filtros.</span>
                        </div>
                        <div class="admin-pagination" id="adminPagination" aria-label="Paginación de expedientes"></div>
                    </div>
                </div>

                <div class="admin-panel" data-admin-content="missions" hidden>
                    <?php if ($missionsInstalled): ?>
                        <div class="admin-actions-row"><button class="ink-button" type="button" id="newMission">+ Nueva misión</button></div>
                        <section class="mission-drafts" id="missionDrafts" hidden aria-labelledby="missionDraftsTitle">
                            <h3 id="missionDraftsTitle">Borradores <span id="missionDraftsCount">0</span></h3>
                            <p>Misiones a medio escribir. Se guardan solas mientras escribes; continúa cuando quieras.</p>
                            <div class="mission-drafts-list" id="missionDraftsList"></div>
                        </section>
                        <div class="admin-list" id="adminMissionList"></div>
                    <?php else: ?>
                        <div class="notice-sheet"><strong>Primero importa el SQL de misiones.</strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<div class="modal" id="characterModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-character></div>
    <article class="folio-modal" role="dialog" aria-modal="true" aria-labelledby="characterModalName">
        <button class="modal-close" type="button" data-close-character aria-label="Cerrar">×</button>
        <div id="characterModalContent"></div>
    </article>
</div>

<div class="modal" id="adminModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-admin></div>
    <article class="admin-modal-card" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
        <button class="modal-close" type="button" data-close-admin aria-label="Cerrar">×</button>
        <div id="adminModalContent"></div>
    </article>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
window.GUILD_STATE = <?= json_encode([
    'characters' => $characters,
    'players' => $players,
    'missions' => $missions,
    'missionsInstalled' => $missionsInstalled,
    'isAdmin' => $isAdmin,
    'isLogged' => $isLogged,
    'user' => $sessionUser,
    'csrf' => $csrf,
    'api' => 'api.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="app.js<?= favor_v(__DIR__ . '/app.js') ?>"></script>
</body>
</html>
