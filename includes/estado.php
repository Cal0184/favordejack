<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Estado del personaje (Activo, Retirado, Muerto…) igual en toda la web.
 *
 * Cada sección lo guardaba a su manera:
 *  - Banca: mueve al personaje al «jugador» especial Muertos o Personajes Fuera.
 *  - Archivos: PersonajePerfil.Estado, a menudo en una ficha aparte (arch-…)
 *    con el mismo nombre que el personaje de la Banca.
 *  - Inventario y Relaciones: solo miraban Personaje.Activo.
 *
 * Desde ahora manda Archivos (PersonajePerfil.Estado). Al cambiarlo en Archivos
 * se coloca igual en la Banca, y al moverlo en la Banca se anota en Archivos.
 * Las fichas «gemelas» se reconocen por el mismo nombre, solo si no hay dudas.
 * Al mandar un personaje a Muertos o Personajes Fuera se recuerda su dueño
 * (Personaje.JugadorOriginal) para poder devolverlo si vuelve a estar activo.
 */

const FAVOR_ESTADOS = [
    'Activo' => ['color' => '#6d9f4a', 'icono' => '●'],
    'Retirado' => ['color' => '#b08a4a', 'icono' => '◐'],
    'Desaparecido' => ['color' => '#7d8fa8', 'icono' => '?'],
    'Schrödinger' => ['color' => '#9a7ac0', 'icono' => '◇'],
    'Muerto' => ['color' => '#b0504a', 'icono' => '✝'],
];
const FAVOR_ESTADO_GRUPO_MUERTOS = 'muertos';
const FAVOR_ESTADO_GRUPOS_FUERA = ['personajes fuera', 'retirados'];
const FAVOR_ESTADO_GRUPOS = ['npc', 'npcs', 'muertos', 'personajes fuera', 'retirados'];

function favor_estado_clave(string $v): string
{
    $v = mb_strtolower(trim($v), 'UTF-8');
    return strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n']);
}

/** «muerta», «RETIRADO», «schrodinger» → nombre canónico ('' si no se reconoce). */
function favor_estado_normalizar(?string $estado): string
{
    $k = favor_estado_clave((string)$estado);
    if ($k === '') return '';
    if (str_starts_with($k, 'activ')) return 'Activo';
    if (str_starts_with($k, 'retir') || $k === 'fuera') return 'Retirado';
    if (str_starts_with($k, 'muert') || str_starts_with($k, 'fallec')) return 'Muerto';
    if (str_starts_with($k, 'desapare')) return 'Desaparecido';
    if (str_starts_with($k, 'schr')) return 'Schrödinger';
    return '';
}

/** Estado que se deduce de dónde está el personaje en la Banca. */
function favor_estado_banca(string $jugadorNombre, int $activo): string
{
    $g = favor_estado_clave($jugadorNombre);
    if ($g === FAVOR_ESTADO_GRUPO_MUERTOS) return 'Muerto';
    if (in_array($g, FAVOR_ESTADO_GRUPOS_FUERA, true)) return 'Retirado';
    return $activo === 1 ? 'Activo' : 'Retirado';
}

function favor_estado_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $row = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='estado_v1' LIMIT 1");
    if ($row && $row->fetch_assoc()) return;
    $col = $db->query("SHOW COLUMNS FROM Personaje LIKE 'JugadorOriginal'");
    if ($col && !$col->fetch_assoc()) $db->query("ALTER TABLE Personaje ADD COLUMN JugadorOriginal varchar(5) NULL DEFAULT NULL");
    $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('estado_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
}

/**
 * Todos los personajes con su estado unificado:
 * id => [id, nombre, jugador, jugadorNombre, enBanca, estadoBanca, estadoArchivos, estado, gemelos[]].
 */
function favor_estado_todos(mysqli $db): array
{
    favor_estado_ensure($db);
    $rows = $db->query(
        'SELECT p.ID, p.Nombre, p.Jugador, p.Activo, p.JugadorOriginal, j.Nombre AS JugadorNombre, pp.Estado, pp.Categoria '
        . 'FROM Personaje p LEFT JOIN Jugador j ON j.ID = p.Jugador LEFT JOIN PersonajePerfil pp ON pp.ID = p.ID'
    )->fetch_all(MYSQLI_ASSOC);

    $out = [];
    $byName = [];
    foreach ($rows as $r) {
        $grupo = favor_estado_clave((string)$r['JugadorNombre']);
        $npc = in_array($grupo, ['npc', 'npcs'], true) || favor_estado_clave((string)$r['Categoria']) === 'npc';
        $enBanca = (int)$r['Activo'] === 1;
        $id = (string)$r['ID'];
        $out[$id] = [
            'id' => $id,
            'nombre' => (string)$r['Nombre'],
            'jugador' => (string)$r['Jugador'],
            'jugadorNombre' => (string)$r['JugadorNombre'],
            'jugadorOriginal' => (string)($r['JugadorOriginal'] ?? ''),
            'especial' => in_array($grupo, FAVOR_ESTADO_GRUPOS, true),
            'npc' => $npc,
            'enBanca' => $enBanca,
            'estadoBanca' => $enBanca ? favor_estado_banca((string)$r['JugadorNombre'], 1) : '',
            'estadoArchivos' => favor_estado_normalizar($r['Estado'] ?? ''),
            'tienePerfil' => $r['Estado'] !== null,
            'gemelos' => [],
        ];
        if (!$npc) $byName[favor_estado_clave((string)$r['Nombre'])][] = $id;
    }
    // Gemelos: una ficha de la Banca y una de Archivos con el mismo nombre.
    foreach ($byName as $ids) {
        if (count($ids) < 2) continue;
        $banca = array_values(array_filter($ids, static fn(string $id): bool => $out[$id]['enBanca']));
        $archivo = array_values(array_filter($ids, static fn(string $id): bool => !$out[$id]['enBanca'] && $out[$id]['tienePerfil']));
        if (count($banca) !== 1 || count($archivo) !== 1) continue; // nombre repetido: no adivinamos
        $out[$banca[0]]['gemelos'][] = $archivo[0];
        $out[$archivo[0]]['gemelos'][] = $banca[0];
    }
    foreach ($out as $id => &$c) {
        $archivos = $c['estadoArchivos'];
        foreach ($c['gemelos'] as $g) if ($archivos === '') $archivos = $out[$g]['estadoArchivos'];
        $banca = $c['estadoBanca'];
        foreach ($c['gemelos'] as $g) if ($banca === '') $banca = $out[$g]['estadoBanca'];
        $c['estadoArchivos'] = $archivos;
        $c['estadoBanca'] = $banca;
        // Manda Archivos; si no tiene ficha, la Banca; si no está en ninguna, Retirado.
        $c['estado'] = $archivos !== '' ? $archivos : ($banca !== '' ? $banca : 'Retirado');
        // Retirado, Desaparecido y Schrödinger viven juntos en Personajes Fuera.
        // Fuera de la Banca (archivado) vale para cualquier estado menos Activo.
        $c['coincide'] = $c['npc'] || $archivos === ''
            || ($banca === '' ? $archivos !== 'Activo' : favor_estado_lugar($archivos) === favor_estado_lugar($banca));
    }
    unset($c);
    return $out;
}

/** Dónde va en la Banca: 'activo', 'muertos' o 'fuera'. */
function favor_estado_lugar(string $estado): string
{
    return match ($estado) {
        'Activo' => 'activo',
        'Muerto' => 'muertos',
        default => 'fuera',
    };
}

/** ID del «jugador» especial de la Banca ('' si no existe). */
function favor_estado_grupo_id(mysqli $db, string $lugar): string
{
    $wanted = $lugar === 'muertos' ? [FAVOR_ESTADO_GRUPO_MUERTOS] : FAVOR_ESTADO_GRUPOS_FUERA;
    foreach ($db->query('SELECT ID, Nombre FROM Jugador ORDER BY Activo DESC, Orden')->fetch_all(MYSQLI_ASSOC) as $j) {
        if (in_array(favor_estado_clave((string)$j['Nombre']), $wanted, true)) return (string)$j['ID'];
    }
    return '';
}

/**
 * Pone $estado a un personaje y a su gemelo: en Archivos (si tiene ficha) y en
 * la Banca (lo mueve a Muertos, a Personajes Fuera o de vuelta a su jugador).
 * Devuelve un aviso con lo que hizo o no pudo hacer.
 */
function favor_estado_aplicar(mysqli $db, string $id, string $estado): string
{
    $estado = favor_estado_normalizar($estado);
    if ($estado === '') throw new RuntimeException('Estado no válido.');
    $todos = favor_estado_todos($db);
    if (!isset($todos[$id])) throw new RuntimeException('Personaje no encontrado.');
    $ids = array_merge([$id], $todos[$id]['gemelos']);
    $notes = [];

    $st = $db->prepare('UPDATE PersonajePerfil SET Estado=? WHERE ID=?');
    foreach ($ids as $cid) {
        if (!$todos[$cid]['tienePerfil']) continue;
        $st->bind_param('ss', $estado, $cid);
        $st->execute();
    }
    $st->close();

    // Una ficha archivada sin gemelo en la Banca vuelve a la Banca si pasa a Activo.
    if (!$todos[$id]['gemelos'] && !$todos[$id]['npc'] && !$todos[$id]['enBanca'] && $estado === 'Activo') {
        $st = $db->prepare('UPDATE Personaje SET Activo=1 WHERE ID=?');
        $st->bind_param('s', $id);
        $st->execute();
        $st->close();
        return '';
    }

    foreach ($ids as $cid) {
        $c = $todos[$cid];
        if (!$c['enBanca'] || $c['npc']) continue;
        $lugar = favor_estado_lugar($estado);
        if (favor_estado_lugar($c['estadoBanca'] ?: 'Activo') === $lugar && !($lugar === 'activo' && $c['especial'])) continue;
        if ($lugar === 'activo') {
            $owner = $c['jugadorOriginal'];
            foreach ($c['gemelos'] as $g) if ($owner === '' && !$todos[$g]['especial']) $owner = $todos[$g]['jugador'];
            if ($owner === '') {
                $notes[] = 'No sé de qué jugador es ' . $c['nombre'] . ': muévelo en la Banca.';
                continue;
            }
            $st = $db->prepare('UPDATE Personaje SET Jugador=?, JugadorOriginal=NULL WHERE ID=?');
            $st->bind_param('ss', $owner, $cid);
        } else {
            $grupo = favor_estado_grupo_id($db, $lugar);
            if ($grupo === '') {
                $notes[] = 'Falta el grupo «' . ($lugar === 'muertos' ? 'Muertos' : 'Personajes Fuera') . '» en la Banca.';
                continue;
            }
            $original = $c['especial'] ? ($c['jugadorOriginal'] ?: null) : $c['jugador'];
            $st = $db->prepare('UPDATE Personaje SET Jugador=?, JugadorOriginal=? WHERE ID=?');
            $st->bind_param('sss', $grupo, $original, $cid);
        }
        $st->execute();
        $st->close();
    }
    return implode(' ', $notes);
}

/** Estado que se muestra al jugador, listo para JSON. */
function favor_estado_publico(string $estado): array
{
    $e = FAVOR_ESTADOS[$estado] ?? FAVOR_ESTADOS['Retirado'];
    return ['label' => $estado, 'color' => $e['color'], 'icon' => $e['icono']];
}

/**
 * Después de un cambio hecho en la Banca (archivar, mover a Muertos…): anota en
 * Archivos el estado que se deduce, si no encaja con el que tenía.
 */
function favor_estado_desde_banca(mysqli $db, string $id): void
{
    $todos = favor_estado_todos($db);
    $c = $todos[$id] ?? null;
    if (!$c || $c['npc'] || $c['coincide']) return;
    $estado = $c['enBanca'] ? favor_estado_banca($c['jugadorNombre'], 1) : 'Retirado';
    $st = $db->prepare('UPDATE PersonajePerfil SET Estado=? WHERE ID=?');
    foreach (array_merge([$id], $c['gemelos']) as $cid) {
        $st->bind_param('ss', $estado, $cid);
        $st->execute();
    }
    $st->close();
}

/** Estado unificado de un personaje (con caché por petición). */
function favor_estado_de(string $id): string
{
    static $mapa = null;
    if ($mapa === null) {
        try {
            $db = favor_db();
            try { $mapa = favor_estado_todos($db); } finally { $db->close(); }
        } catch (Throwable) {
            $mapa = [];
        }
    }
    return $mapa[$id]['estado'] ?? 'Activo';
}

/**
 * NPC: no usan la Banca ni compran (Cal, 29-10-2026); solo viven en Archivos y
 * Relaciones. Son NPC los personajes del grupo «NPC» de la Banca y los que
 * Archivos marca con la categoría NPC. Sus datos (y el oro que tuvieran)
 * siguen en la base: solo no se muestran. Devuelve [id => true].
 */
function favor_npc_ids(mysqli $db): array
{
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    $result = $db->query('SELECT p.ID, j.Nombre FROM Personaje p INNER JOIN Jugador j ON j.ID = p.Jugador');
    while ($row = $result->fetch_assoc()) {
        if (in_array(favor_estado_clave((string)$row['Nombre']), ['npc', 'npcs'], true)) $ids[(string)$row['ID']] = true;
    }
    $result->free();
    try {
        $result = $db->query("SELECT ID FROM PersonajePerfil WHERE Categoria = 'NPC'");
        while ($row = $result->fetch_row()) $ids[(string)$row[0]] = true;
        $result->free();
    } catch (mysqli_sql_exception) {
        // Instalación sin la columna Categoria todavía: solo cuenta el grupo NPC.
    }
    return $ids;
}
