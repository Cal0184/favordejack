<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/tablon.php';
// Los avisos de Discord llegan con el Panel del DM; sin él, el tablón funciona igual.
if (is_file(dirname(__DIR__) . '/includes/discord.php')) require_once dirname(__DIR__) . '/includes/discord.php';

function tablon_respond(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tablon_str(array $data, string $key, int $max): string
{
    return mb_substr(trim((string)($data[$key] ?? '')), 0, $max, 'UTF-8');
}

function tablon_num(array $data, string $key, float $min, float $max, float $default): float
{
    $v = $data[$key] ?? null;
    if (!is_numeric($v)) return $default;
    return max($min, min($max, round((float)$v, 2)));
}

function tablon_exec(mysqli $db, string $sql, array $params): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...array_map(static fn($v) => $v === null ? null : (string)$v, $params));
    $stmt->execute();
    return $stmt;
}

function tablon_mision(mysqli $db, int $id): array
{
    $stmt = tablon_exec($db, 'SELECT ID,Nombre,Estado,Descripcion,RecompensaGeneral,Fecha FROM Mision WHERE ID=? AND EnTablon=1 LIMIT 1', [$id]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('Esa misión ya no está en el tablón. Recarga la página.');
    return $row;
}

function tablon_next_layer(mysqli $db): int
{
    $res = $db->query('SELECT COALESCE(MAX(Capa),0)+1 FROM Mision WHERE EnTablon=1');
    $n = (int)$res->fetch_row()[0];
    $res->free();
    return $n;
}

/** Entre varios puntos al azar, el más lejos de los papeles que ya están. */
function tablon_free_spot(mysqli $db, float $half): array
{
    $taken = [];
    $res = $db->query('SELECT PosX,PosY FROM Mision WHERE EnTablon=1');
    while ($r = $res->fetch_row()) $taken[] = [(float)$r[0], (float)$r[1]];
    $res->free();
    $best = [50.0, 50.0];
    $bestScore = -1.0;
    for ($i = 0; $i < 60; $i++) {
        $x = random_int(1200, 8800) / 100;
        $y = min(max(random_int(2600, 7400) / 100, $half + 2), max($half + 2, 98 - $half));
        $score = 1000.0;
        // El tablón es 3:2, así que un % de alto vale 2/3 de un % de ancho.
        foreach ($taken as [$tx, $ty]) $score = min($score, hypot($x - $tx, ($y - $ty) * 2 / 3));
        if ($score > $bestScore) { $bestScore = $score; $best = [$x, $y]; }
    }
    return $best;
}

function tablon_party_names(mysqli $db, int $id): string
{
    $stmt = tablon_exec($db, "SELECT COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre,mp.NombreSnapshot) FROM MisionPersonaje mp LEFT JOIN Personaje p ON p.ID=mp.PersonajeID LEFT JOIN PersonajePerfil pp ON pp.ID=mp.PersonajeID WHERE mp.MisionID=?", [$id]);
    $names = array_map(static fn(array $r): string => (string)$r[0], $stmt->get_result()->fetch_all(MYSQLI_NUM));
    $stmt->close();
    return implode(', ', $names);
}

function tablon_discord(string $title, string $text, array $fields): void
{
    if (function_exists('favor_discord_send')) favor_discord_send('misiones', $title, mb_substr($text, 0, 600, 'UTF-8'), $fields);
}

try {
    favor_session_start();
    // Las imágenes de las cazas llegan como formulario; lo demás, como JSON.
    $multipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    $data = $multipart ? $_POST : json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) throw new RuntimeException('Pedido no válido. Si subías una imagen, puede que pese demasiado.');
    favor_verify_csrf((string)($data['csrf'] ?? ''));
    $admin = favor_require_admin();
    favor_session_close();

    $action = (string)($data['action'] ?? '');
    $id = (int)($data['id'] ?? 0);
    $db = favor_db();
    favor_ensure_schema($db);
    tablon_ensure_schema($db);

    // Subir la imagen de una caza (antes de guardarla).
    if ($action === 'image') {
        tablon_respond(true, tablon_guardar_imagen($_FILES['image'] ?? [], tablon_str($data, 'name', 80)));
    }

    // Crear o editar el papel.
    if ($action === 'save') {
        $name = tablon_str($data, 'name', 160);
        $text = tablon_str($data, 'text', 1200);
        $hunt = ($data['kind'] ?? '') === 'caza';
        $image = $hunt ? tablon_imagen_valida((string)($data['image'] ?? '')) : '';
        $stars = $hunt ? max(1, min(TABLON_MAX_ESTRELLAS, (int)($data['stars'] ?? 1))) : 0;
        if ($name === '') throw new RuntimeException($hunt ? 'Escribe el nombre de la criatura.' : 'La misión necesita un título.');
        if ($hunt && $image === '') throw new RuntimeException('Sube la imagen de la criatura.');
        if (!$hunt && $text === '') throw new RuntimeException('Escribe el texto que va en el papel.');
        $reward = tablon_str($data, 'reward', 255);
        $details = tablon_str($data, 'details', 20000);
        $type = tablon_str($data, 'type', 80);
        if ($hunt && $type === '') $type = 'Caza de cabezas';
        $paper = (int)($data['paper'] ?? 1);
        // Papel 0 = la imagen ya es el cartel completo (solo en cazas).
        if (!isset(TABLON_PAPELES[$paper]) && !($hunt && $paper === 0)) $paper = 1;
        $imageRatio = 1.0;
        if ($image !== '') {
            $info = @getimagesize(dirname(__DIR__) . '/' . $image);
            if ($info) $imageRatio = round($info[0] / max(1, $info[1]), 4);
        }
        $kind = $hunt ? 'caza' : 'aviso';
        $size = isset(TABLON_TAMANOS[(int)($data['size'] ?? 0)]) ? (int)$data['size'] : 2;
        $font = isset(TABLON_LETRAS[(string)($data['font'] ?? '')]) ? (string)$data['font'] : 'pluma';
        $ink = isset(TABLON_TINTAS[(string)($data['ink'] ?? '')]) ? (string)$data['ink'] : 'negra';

        if ($id > 0) {
            tablon_mision($db, $id);
            tablon_exec($db, 'UPDATE Mision SET Nombre=?,Descripcion=?,RecompensaGeneral=?,Detalles=?,Tipo=?,Papel=?,Tamano=?,Letra=?,Tinta=?,Clase=?,Imagen=?,ImagenRatio=?,Estrellas=? WHERE ID=?',
                [$name, $text, $reward, $details, $type, $paper, $size, $font, $ink, $kind, $image, $imageRatio, $stars, $id])->close();
            tablon_respond(true, ['message' => 'Misión actualizada.', 'id' => $id]);
        }

        // Papel nuevo: en el hueco más libre del tablón, algo torcido.
        // Medio alto del papel en % del tablón (3:2), para que no quede cortado abajo.
        $ratio = $paper === 0 ? $imageRatio : TABLON_PAPELES[$paper]['ratio'];
        $half = TABLON_TAMANOS[$size][1] * ($paper === 0 ? 1 : TABLON_PAPELES[$paper]['escala']) / $ratio * 1.5 / 2;
        [$x, $y] = tablon_free_spot($db, $half);
        $rot = random_int(-40, 40) / 10;
        tablon_exec($db, "INSERT INTO Mision (Nombre,Tipo,Estado,Descripcion,RecompensaGeneral,CreadoPor,EnTablon,Papel,Tamano,Letra,Tinta,PosX,PosY,Giro,Capa,Detalles,Clase,Imagen,ImagenRatio,Estrellas) VALUES (?,?,'Publicada',?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$name, $type, $text, $reward, (string)$admin['username'], $paper, $size, $font, $ink, $x, $y, $rot, tablon_next_layer($db), $details, $kind, $image, $imageRatio, $stars])->close();
        $id = (int)$db->insert_id;
        tablon_discord(($hunt ? '🎯 Nueva caza de cabezas: ' : '📜 Nueva misión en el tablón: ') . $name, $text, [['Recompensa', $reward], ['Tipo', $type], ['Dificultad', $hunt ? str_repeat('★', $stars) : '']]);
        tablon_respond(true, ['message' => 'Misión clavada en el tablón.', 'id' => $id]);
    }

    // Mover un papel (y dejarlo encima de los demás).
    if ($action === 'move') {
        tablon_mision($db, $id);
        $layer = tablon_next_layer($db);
        tablon_exec($db, 'UPDATE Mision SET PosX=?,PosY=?,Capa=? WHERE ID=?',
            [tablon_num($data, 'x', 0, 100, 50), tablon_num($data, 'y', 0, 100, 50), $layer, $id])->close();
        tablon_respond(true, ['layer' => $layer]);
    }

    // Iniciar (o cambiar el grupo de una misión en curso).
    if ($action === 'start') {
        $m = tablon_mision($db, $id);
        if (!in_array($m['Estado'], ['Publicada', 'En curso'], true)) throw new RuntimeException('Solo se puede iniciar una misión publicada o en curso.');
        $ids = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), (array)($data['party'] ?? [])), 'strlen')));
        if (!$ids) throw new RuntimeException('Agrega al menos un personaje a la misión.');
        if (count($ids) > 30) throw new RuntimeException('Son demasiados personajes para una misión.');

        $db->begin_transaction();
        try {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = tablon_exec($db, "SELECT p.ID,COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre) AS Nombre,pp.Clase,pp.Nivel FROM Personaje p LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID WHERE p.ID IN ($marks)", $ids);
            $chars = [];
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) $chars[(string)$c['ID']] = $c;
            $stmt->close();
            if (!$chars) throw new RuntimeException('Esos personajes ya no existen.');

            // Se quitan los que salieron; los que siguen conservan su recompensa y notas.
            $keep = array_keys($chars);
            $marks = implode(',', array_fill(0, count($keep), '?'));
            tablon_exec($db, "DELETE FROM MisionPersonaje WHERE MisionID=? AND PersonajeID NOT IN ($marks)", array_merge([$id], $keep))->close();
            foreach ($chars as $cid => $c) {
                tablon_exec($db, 'INSERT IGNORE INTO MisionPersonaje (MisionID,PersonajeID,NombreSnapshot,ClaseSnapshot,NivelSnapshot) VALUES (?,?,?,?,?)',
                    [$id, $cid, (string)$c['Nombre'], (string)($c['Clase'] ?? ''), $c['Nivel'] !== null ? (string)$c['Nivel'] : null])->close();
            }
            tablon_exec($db, "UPDATE Mision SET Estado='En curso',Fecha=COALESCE(Fecha,CURDATE()) WHERE ID=?", [$id])->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        if ($m['Estado'] === 'Publicada') {
            tablon_discord('⚔️ Misión iniciada: ' . $m['Nombre'], (string)$m['Descripcion'], [['Grupo', tablon_party_names($db, $id)], ['Recompensa', (string)$m['RecompensaGeneral']]]);
        }
        tablon_respond(true, ['message' => $m['Estado'] === 'Publicada' ? '¡Misión iniciada!' : 'Grupo actualizado.']);
    }

    // Terminar: Completada o Fallida (pone el sello).
    if ($action === 'finish') {
        $m = tablon_mision($db, $id);
        $state = (string)($data['state'] ?? '');
        if (!in_array($state, ['Completada', 'Fallida', 'En curso'], true)) throw new RuntimeException('Estado no válido.');
        if ($m['Estado'] === 'Cancelada' || $m['Estado'] === 'Publicada') throw new RuntimeException('Primero hay que iniciar la misión.');
        $note = tablon_str($data, 'note', 500);
        tablon_exec($db, 'UPDATE Mision SET Estado=?,MotivoFin=?,Fecha=COALESCE(Fecha,CURDATE()) WHERE ID=?', [$state, $note, $id])->close();
        if ($state !== 'En curso' && $state !== $m['Estado']) {
            tablon_discord(($state === 'Completada' ? '🏆 Misión completada: ' : '💀 Misión fallida: ') . $m['Nombre'], $note, [['Grupo', tablon_party_names($db, $id)], ['Recompensa', (string)$m['RecompensaGeneral']]]);
        }
        tablon_respond(true, ['message' => $state === 'En curso' ? 'La misión vuelve a estar en curso.' : 'Misión marcada como ' . mb_strtolower($state, 'UTF-8') . '.']);
    }

    // Eliminar: el papel queda roto en el tablón, con su motivo.
    if ($action === 'break') {
        $m = tablon_mision($db, $id);
        $reason = tablon_str($data, 'reason', 500);
        tablon_exec($db, "UPDATE Mision SET Estado='Cancelada',MotivoFin=? WHERE ID=?", [$reason, $id])->close();
        if ($m['Estado'] !== 'Cancelada') tablon_discord('❌ Misión eliminada: ' . $m['Nombre'], $reason !== '' ? 'Motivo: ' . $reason : '', []);
        tablon_respond(true, ['message' => 'Misión eliminada. Su papel quedó roto en el tablón.']);
    }

    // Arreglar un papel roto (deshacer «Eliminar»).
    if ($action === 'restore') {
        $m = tablon_mision($db, $id);
        if ($m['Estado'] !== 'Cancelada') throw new RuntimeException('Esa misión no está eliminada.');
        $count = tablon_exec($db, 'SELECT COUNT(*) FROM MisionPersonaje WHERE MisionID=?', [$id]);
        $hasParty = (int)$count->get_result()->fetch_row()[0] > 0;
        $count->close();
        tablon_exec($db, "UPDATE Mision SET Estado=?,MotivoFin='' WHERE ID=?", [$hasParty ? 'En curso' : 'Publicada', $id])->close();
        tablon_respond(true, ['message' => 'El papel volvió a quedar entero.']);
    }

    // Volver a colgar en el tablón una misión descolgada (desde la Historia).
    if ($action === 'pin') {
        $stmt = tablon_exec($db, 'SELECT ID FROM Mision WHERE ID=? LIMIT 1', [$id]);
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$exists) throw new RuntimeException('Esa misión ya no existe.');
        tablon_exec($db, 'UPDATE Mision SET EnTablon=1,Capa=? WHERE ID=?', [tablon_next_layer($db), $id])->close();
        tablon_respond(true, ['message' => 'La misión volvió al tablón.']);
    }

    // Descolgar: sale del tablón pero sigue en el Registro de Misiones de Archivos.
    if ($action === 'unpin') {
        tablon_mision($db, $id);
        tablon_exec($db, 'UPDATE Mision SET EnTablon=0 WHERE ID=?', [$id])->close();
        tablon_respond(true, ['message' => 'Papel descolgado. La misión sigue en Archivos del Gremio.']);
    }

    throw new RuntimeException('Acción desconocida.');
} catch (Throwable $e) {
    tablon_respond(false, ['error' => $e->getMessage()], 400);
}
