<?php
declare(strict_types=1);

require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/imagenes.php';

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
 *   Clase           'aviso' (texto) o 'caza' (caza de cabezas: imagen + estrellas)
 *   Imagen          la criatura (media/cazas/…); con Papel 0 es el cartel completo
 *   ImagenRatio     ancho/alto de esa imagen
 *   Estrellas       dificultad de la caza, 1 a 5
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
/** Estados que ya pasaron (van a la Historia). */
const TABLON_TERMINADAS = ['Completada', 'Fallida', 'Cancelada'];
const TABLON_MAX_ESTRELLAS = 5;
/** Carpeta de las imágenes de las cazas (viaja en el respaldo con el resto de media/). */
const TABLON_CAZAS_DIR = 'media/cazas';
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
    $flag = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='tablon_v2' LIMIT 1");
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
        'Clase' => "varchar(10) NOT NULL DEFAULT 'aviso'",
        'Imagen' => "varchar(300) NOT NULL DEFAULT ''",
        'ImagenRatio' => "decimal(6,4) NOT NULL DEFAULT 1",
        'Estrellas' => "tinyint unsigned NOT NULL DEFAULT 0",
    ];
    $existing = [];
    $res = $db->query('SHOW COLUMNS FROM Mision');
    while ($row = $res->fetch_assoc()) $existing[(string)$row['Field']] = true;
    $res->free();
    foreach ($columns as $name => $definition) {
        if (!isset($existing[$name])) $db->query("ALTER TABLE Mision ADD COLUMN `$name` $definition");
    }
    if (!isset($existing['EnTablon'])) $db->query('ALTER TABLE Mision ADD INDEX idx_mision_tablon (EnTablon)');
    $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('tablon_v2','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
}

/** Misiones clavadas en el tablón, con su grupo. */
function tablon_misiones(mysqli $db): array
{
    return tablon_cargar($db, 'EnTablon=1', 'Capa, ID');
}

/** Misiones que ya pasaron (terminadas o eliminadas), estén o no en el tablón. */
function tablon_historia(mysqli $db): array
{
    $states = "'" . implode("','", TABLON_TERMINADAS) . "'";
    return tablon_cargar($db, "Estado IN ($states)", 'COALESCE(Fecha, DATE(Actualizado)) DESC, ID DESC LIMIT 400');
}

/** $where y $order son texto fijo del código, nunca del navegador. */
function tablon_cargar(mysqli $db, string $where, string $order): array
{
    $missions = [];
    $res = $db->query(
        'SELECT ID,Nombre,Fecha,Tipo,Estado,Descripcion,RecompensaGeneral,CreadoPor,Creado,Actualizado,EnTablon,Papel,Tamano,Letra,Tinta,PosX,PosY,Giro,Capa,Detalles,MotivoFin,Clase,Imagen,ImagenRatio,Estrellas '
        . "FROM Mision WHERE $where ORDER BY $order"
    );
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['ID'];
        $hunt = $r['Clase'] === 'caza' && $r['Imagen'] !== '';
        $paper = (int)$r['Papel'];
        if (!isset(TABLON_PAPELES[$paper]) && !($hunt && $paper === 0)) $paper = 1;
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
            'updated' => (string)$r['Actualizado'],
            'onBoard' => (bool)$r['EnTablon'],
            'kind' => $hunt ? 'caza' : 'aviso',
            'image' => $hunt ? favor_asset_url((string)$r['Imagen']) : '',
            'imagePath' => $hunt ? (string)$r['Imagen'] : '',
            'imageRatio' => (float)$r['ImagenRatio'] ?: 1.0,
            'stars' => max(0, min(TABLON_MAX_ESTRELLAS, (int)$r['Estrellas'])),
            'paper' => $paper,
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
            'portrait' => favor_thumb_url((string)(($r['PortraitUrl'] ?? '') ?: ($r['IconUrl'] ?? ''))),
        ];
    }
    $res->free();
    return array_values($missions);
}

/** Guarda la imagen subida de una caza en media/cazas/ (WebP, con transparencia). */
function tablon_guardar_imagen(array $file, string $name): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Elige una imagen.');
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('La imagen pesa demasiado para el servidor. Prueba con una más liviana.');
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir la imagen (código ' . $error . ').');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('La imagen no es válida.');
    if ((int)($file['size'] ?? 0) > 15 * 1024 * 1024) throw new RuntimeException('La imagen debe pesar menos de 15 MB.');
    $info = @getimagesize($tmp);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($types[(string)$info['mime']])) throw new RuntimeException('Usa una imagen JPG, PNG o WebP.');

    $dir = dirname(__DIR__) . '/' . TABLON_CAZAS_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('No pude crear la carpeta ' . TABLON_CAZAS_DIR . '/.');
    if (!is_writable($dir)) throw new RuntimeException('La carpeta ' . TABLON_CAZAS_DIR . '/ no tiene permiso de escritura.');

    $slug = strtolower((string)(function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name));
    $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', $slug), '-') ?: 'caza';
    $slug = substr($slug, 0, 40) . '-' . bin2hex(random_bytes(3));

    if (favor_image_gd_ready() && ($img = favor_image_open($tmp)) !== null) {
        $img = favor_image_fit($img, 1000, 1500);
        $w = imagesx($img);
        $h = imagesy($img);
        $rel = TABLON_CAZAS_DIR . '/' . $slug . '.webp';
        if (!favor_image_save($img, dirname(__DIR__) . '/' . $rel, 86)) throw new RuntimeException('No pude guardar la imagen.');
        imagedestroy($img);
    } else {
        // Sin GD se guarda tal cual.
        [$w, $h] = [(int)$info[0], (int)$info[1]];
        $rel = TABLON_CAZAS_DIR . '/' . $slug . '.' . $types[(string)$info['mime']];
        if (!@move_uploaded_file($tmp, dirname(__DIR__) . '/' . $rel)) throw new RuntimeException('No pude guardar la imagen.');
        @chmod(dirname(__DIR__) . '/' . $rel, 0644);
    }
    return ['path' => $rel, 'url' => favor_asset_url($rel), 'ratio' => round($w / max(1, $h), 4)];
}

/** Ruta de imagen de caza que viene del navegador: solo archivos de media/cazas/ que existen. */
function tablon_imagen_valida(string $path): string
{
    $path = trim($path);
    if (!preg_match('#^media/cazas/[a-z0-9\-]+\.(?:webp|png|jpg)$#', $path)) return '';
    return is_file(dirname(__DIR__) . '/' . $path) ? $path : '';
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
            'icon' => favor_asset_url(favor_asset_existing((string)$r['IconUrl'])),
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
            'portrait' => favor_thumb_url((string)(($r['PortraitUrl'] ?? '') ?: ($r['IconUrl'] ?? ''))),
        ];
    }
    $res->free();
    return array_values(array_filter($players, static fn(array $p): bool => $p['characters'] !== []));
}
