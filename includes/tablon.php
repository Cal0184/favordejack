<?php
declare(strict_types=1);

require_once __DIR__ . '/assets.php';

/*
 * Tablón de misiones.
 *
 * Usa las mismas misiones que Archivos del Gremio (tablas Mision y
 * MisionPersonaje) y les agrega cómo se ven en el tablón:
 *   EnTablon        1 = clavada en el tablón (0 = solo en Archivos)
 *   Papel, Tamano   cuál de los papeles y de qué tamaño (1 chico · 2 normal · 3 grande)
 *   Letra, Tinta    tipo de letra y color del texto
 *   PosX, PosY      centro del papel, en % del tablón
 *   Giro, Capa      inclinación en grados y cuál va encima
 *   Detalles        texto largo que se ve al abrir la misión (no va en el papel)
 *   MotivoFin       por qué se rompió (o nota al terminarla)
 *
 * Estados: Publicada (recién clavada) → En curso (al iniciarla) → Completada o
 * Fallida. Cancelada = papel roto.
 */

/*
 * Papeles de Cal: tablon/papeles/papel-N.webp y su versión rota roto-N.webp.
 * «ratio» = ancho/alto de la imagen, «texto» = margen del recuadro del texto en %
 * [arriba, derecha, abajo, izquierda] (deja libres clavos, rollos y el sello) y
 * «escala» = ancho relativo en el tablón (el rollo largo es más angosto).
 * «roto» = [ratio, recuadro del texto] de la versión rota, que tiene otra forma.
 */
const TABLON_PAPELES = [
    1 => ['nombre' => 'Pergamino', 'ratio' => 1000 / 1400, 'texto' => [9, 12, 12, 12], 'escala' => 1, 'roto' => [1000 / 900, [14, 12, 26, 12]]],
    2 => ['nombre' => 'Con sello', 'ratio' => 1000 / 1400, 'texto' => [9, 12, 38, 12], 'escala' => 1.05, 'roto' => [1000 / 900, [20, 10, 26, 10]]],
    3 => ['nombre' => 'Rollo largo', 'ratio' => 601 / 1400, 'texto' => [10, 14, 10, 14], 'escala' => 0.72, 'roto' => [1000 / 1025, [15, 12, 32, 12]]],
    4 => ['nombre' => 'Clavado', 'ratio' => 1000 / 980, 'texto' => [13, 11, 14, 11], 'escala' => 1.1, 'roto' => [1000 / 980, [16, 10, 22, 10]]],
    5 => ['nombre' => 'Nota', 'ratio' => 600 / 675, 'texto' => [18, 13, 20, 13], 'escala' => 0.95, 'roto' => [600 / 675, [22, 14, 30, 13]]],
    6 => ['nombre' => 'Rollo', 'ratio' => 600 / 800, 'texto' => [16, 14, 13, 14], 'escala' => 0.9, 'roto' => [600 / 548, [24, 12, 22, 14]]],
];
/** Ancho del papel en % del tablón según el tamaño. */
const TABLON_TAMANOS = [1 => ['Chico', 12], 2 => ['Normal', 16], 3 => ['Grande', 21]];
const TABLON_LETRAS = [
    'pluma' => ['Pluma', "'IM Fell English',Georgia,serif"],
    'caligrafia' => ['Caligrafía', "'Tangerine',cursive"],
    'mano' => ['A mano', "'Caveat',cursive"],
    'cartel' => ['Cartel', "'Amatic SC',cursive"],
];
const TABLON_TINTAS = ['negra' => ['Negra', '#24160c'], 'roja' => ['Roja', '#8e1d16'], 'sepia' => ['Sepia', '#5b3416']];
const TABLON_ESTADOS = ['Publicada', 'En curso', 'Completada', 'Fallida', 'Cancelada'];
/** Jugadores que no son personas (contenedores de la Banca). */
const TABLON_JUGADORES_OCULTOS = ['J011', 'J012', 'J013'];

function tablon_papel_url(int $papel, bool $roto): string
{
    $papel = isset(TABLON_PAPELES[$papel]) ? $papel : 1;
    $base = ($roto ? 'roto-' : 'papel-') . $papel;
    $dir = dirname(__DIR__) . '/tablon/papeles/';
    foreach (['webp', 'png', 'jpg'] as $ext) {
        if (is_file($dir . $base . '.' . $ext)) return favor_static('tablon/papeles/' . $base . '.' . $ext);
    }
    return favor_static('tablon/papeles/' . $base . '.webp');
}

/** Lista de papeles para el navegador. */
function tablon_papeles(): array
{
    $out = [];
    foreach (TABLON_PAPELES as $id => $p) {
        $out[] = ['id' => $id, 'name' => $p['nombre'], 'ratio' => round($p['ratio'], 4), 'box' => $p['texto'], 'scale' => $p['escala'],
            'brokenRatio' => round($p['roto'][0], 4), 'brokenBox' => $p['roto'][1],
            'img' => tablon_papel_url($id, false), 'broken' => tablon_papel_url($id, true)];
    }
    return $out;
}

/** Agrega las columnas del tablón a Mision (una sola vez). */
function tablon_ensure_schema(mysqli $db): void
{
    $flag = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='tablon_v1' LIMIT 1");
    $done = $flag && $flag->fetch_assoc();
    if ($flag) $flag->free();
    if ($done) return;

    $columns = [
        'EnTablon' => "tinyint(1) NOT NULL DEFAULT 0",
        'Papel' => "tinyint unsigned NOT NULL DEFAULT 1",
        'Tamano' => "tinyint unsigned NOT NULL DEFAULT 2",
        'Letra' => "varchar(20) NOT NULL DEFAULT 'pluma'",
        'Tinta' => "varchar(20) NOT NULL DEFAULT 'negra'",
        'PosX' => "decimal(5,2) NOT NULL DEFAULT 50",
        'PosY' => "decimal(5,2) NOT NULL DEFAULT 50",
        'Giro' => "decimal(4,1) NOT NULL DEFAULT 0",
        'Capa' => "int unsigned NOT NULL DEFAULT 0",
        'Detalles' => "text NULL",
        'MotivoFin' => "varchar(500) NOT NULL DEFAULT ''",
    ];
    $existing = [];
    $res = $db->query('SHOW COLUMNS FROM Mision');
    while ($row = $res->fetch_assoc()) $existing[(string)$row['Field']] = true;
    $res->free();
    foreach ($columns as $name => $definition) {
        if (!isset($existing[$name])) $db->query("ALTER TABLE Mision ADD COLUMN `$name` $definition");
    }
    if (!isset($existing['EnTablon'])) $db->query('ALTER TABLE Mision ADD INDEX idx_mision_tablon (EnTablon)');
    $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('tablon_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
}

/** Misiones clavadas en el tablón, con su grupo. */
function tablon_misiones(mysqli $db): array
{
    $missions = [];
    $res = $db->query(
        'SELECT ID,Nombre,Fecha,Tipo,Estado,Descripcion,RecompensaGeneral,CreadoPor,Creado,Papel,Tamano,Letra,Tinta,PosX,PosY,Giro,Capa,Detalles,MotivoFin '
        . 'FROM Mision WHERE EnTablon=1 ORDER BY Capa, ID'
    );
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['ID'];
        $missions[$id] = [
            'id' => $id,
            'name' => (string)$r['Nombre'],
            'date' => (string)($r['Fecha'] ?? ''),
            'type' => (string)$r['Tipo'],
            'state' => (string)$r['Estado'],
            'text' => (string)($r['Descripcion'] ?? ''),
            'reward' => (string)$r['RecompensaGeneral'],
            'details' => (string)($r['Detalles'] ?? ''),
            'reason' => (string)$r['MotivoFin'],
            'createdBy' => (string)$r['CreadoPor'],
            'created' => (string)$r['Creado'],
            'paper' => isset(TABLON_PAPELES[(int)$r['Papel']]) ? (int)$r['Papel'] : 1,
            'size' => isset(TABLON_TAMANOS[(int)$r['Tamano']]) ? (int)$r['Tamano'] : 2,
            'font' => isset(TABLON_LETRAS[(string)$r['Letra']]) ? (string)$r['Letra'] : 'pluma',
            'ink' => isset(TABLON_TINTAS[(string)$r['Tinta']]) ? (string)$r['Tinta'] : 'negra',
            'x' => (float)$r['PosX'],
            'y' => (float)$r['PosY'],
            'rot' => (float)$r['Giro'],
            'layer' => (int)$r['Capa'],
            'party' => [],
        ];
    }
    $res->free();
    if (!$missions) return [];

    $ids = implode(',', array_keys($missions));
    $res = $db->query(
        'SELECT mp.MisionID,mp.PersonajeID,COALESCE(NULLIF(pp.NombreCompleto,\'\'),p.Nombre,mp.NombreSnapshot) AS Nombre,p.Nombre AS Corto,p.PortraitUrl,p.IconUrl,j.Nombre AS Jugador '
        . 'FROM MisionPersonaje mp LEFT JOIN Personaje p ON p.ID=mp.PersonajeID LEFT JOIN PersonajePerfil pp ON pp.ID=mp.PersonajeID LEFT JOIN Jugador j ON j.ID=p.Jugador '
        . "WHERE mp.MisionID IN ($ids) ORDER BY mp.MisionID, Nombre"
    );
    while ($r = $res->fetch_assoc()) {
        $missions[(int)$r['MisionID']]['party'][] = [
            'id' => (string)$r['PersonajeID'],
            'name' => (string)$r['Nombre'],
            'short' => (string)($r['Corto'] ?? $r['Nombre']),
            'player' => (string)($r['Jugador'] ?? ''),
            'portrait' => favor_asset_url((string)($r['PortraitUrl'] ?? '')) ?: favor_asset_url((string)($r['IconUrl'] ?? '')),
        ];
    }
    $res->free();
    return array_values($missions);
}

/** Jugadores con sus personajes, para armar el grupo al iniciar una misión. */
function tablon_jugadores(mysqli $db): array
{
    $players = [];
    $res = $db->query('SELECT ID,Nombre,Emoji,IconBg,IconUrl FROM Jugador WHERE Activo=1 ORDER BY Orden, Nombre');
    while ($r = $res->fetch_assoc()) {
        if (in_array((string)$r['ID'], TABLON_JUGADORES_OCULTOS, true)) continue;
        $players[(string)$r['ID']] = [
            'id' => (string)$r['ID'],
            'name' => (string)$r['Nombre'],
            'emoji' => (string)$r['Emoji'],
            'bg' => (string)$r['IconBg'],
            'icon' => favor_asset_url((string)$r['IconUrl']),
            'characters' => [],
        ];
    }
    $res->free();

    $res = $db->query(
        "SELECT p.ID,p.Jugador,p.Nombre,COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre) AS NombreCompleto,p.PortraitUrl,p.IconUrl,pp.Clase,pp.Nivel,"
        . "COALESCE(NULLIF(pp.Estado,''),'Activo') AS Estado,COALESCE(NULLIF(pp.Categoria,''),'Personaje') AS Categoria "
        . 'FROM Personaje p LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID ORDER BY COALESCE(p.Orden,9999), p.Nombre'
    );
    while ($r = $res->fetch_assoc()) {
        $pid = (string)$r['Jugador'];
        if (!isset($players[$pid]) || $r['Categoria'] === 'NPC') continue;
        $players[$pid]['characters'][] = [
            'id' => (string)$r['ID'],
            'name' => (string)$r['NombreCompleto'],
            'short' => (string)$r['Nombre'],
            'class' => trim((string)($r['Clase'] ?? '') . ($r['Nivel'] !== null ? ' · Nv ' . $r['Nivel'] : ''), ' ·'),
            'state' => (string)$r['Estado'],
            'portrait' => favor_asset_url((string)($r['PortraitUrl'] ?? '')) ?: favor_asset_url((string)($r['IconUrl'] ?? '')),
        ];
    }
    $res->free();
    return array_values(array_filter($players, static fn(array $p): bool => $p['characters'] !== []));
}
