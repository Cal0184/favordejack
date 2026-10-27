<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/imagenes.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/discord.php';
guild_session_start();

function respond(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db(): mysqli
{
    $path = __DIR__ . '/../bancadigital/db.php';
    if (!is_file($path)) {
        throw new RuntimeException('No se encontró bancadigital/db.php.');
    }
    $db = require $path;
    if (!$db instanceof mysqli) {
        throw new RuntimeException('La conexión MySQL no es válida.');
    }
    favor_ensure_schema($db);
    guild_ensure_profile_category_schema($db);
    return $db;
}

function input_data(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($type, 'application/json')) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function strv(array $data, string $key, int $max = 10000): string
{
    $value = trim((string)($data[$key] ?? ''));
    return mb_substr($value, 0, $max, 'UTF-8');
}

function nullable_number_string(array $data, string $key): ?string
{
    $raw = trim((string)($data[$key] ?? ''));
    if ($raw === '') return null;
    if (!is_numeric($raw)) throw new RuntimeException("El campo $key debe ser numérico.");
    return $raw;
}

function bind_execute(mysqli_stmt $stmt, array $params): void
{
    if (!$params) {
        $stmt->execute();
        return;
    }
    $types = str_repeat('s', count($params));
    $refs = [];
    foreach ($params as $i => $value) {
        $params[$i] = $value;
        $refs[$i] = &$params[$i];
    }
    $stmt->bind_param($types, ...$refs);
    $stmt->execute();
}


function safe_asset_segment(string $value): string
{
    $value = trim($value);
    if ($value === '') return 'personaje';
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') $value = $converted;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'personaje';
}

function normalize_portrait_path(?string $value): ?string
{
    $path = favor_safe_asset_input($value);
    return $path !== '' ? $path : null;
}


function uploaded_portrait(array $file, string $playerName, string $characterName): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return ['path' => null, 'absolute' => null];
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el retrato (código ' . $error . ').');

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('El archivo de retrato no es válido.');
    if ($size <= 0 || $size > 12 * 1024 * 1024) throw new RuntimeException('El retrato debe pesar menos de 12 MB.');

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) throw new RuntimeException('El archivo subido no parece ser una imagen válida.');
    $mime = (string)($imageInfo['mime'] ?? '');
    if ($mime === '' && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Formato de imagen no permitido. Usa JPG, PNG o WebP.');

    $folder = safe_asset_segment($playerName);
    $base = safe_asset_segment($characterName);
    $directory = dirname(__DIR__) . '/media/characters/' . $folder;
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('El servidor no pudo crear la carpeta del jugador en characters/.');
    }
    if (!is_writable($directory)) throw new RuntimeException('La carpeta characters/' . $folder . ' no tiene permiso de escritura para PHP.');

    $extension = $allowed[$mime];
    $targetExtension = $extension;
    $target = $directory . '/' . $base . '.' . $targetExtension;
    $converted = false;

    // Fotos del móvil: se enderezan (EXIF) y se reducen al tamaño del retrato.
    if (favor_image_gd_ready()) {
        $image = favor_image_open($tmp);
        if ($image !== null) {
            [$maxW, $maxH] = FAVOR_IMAGE_LIMITS['characters'];
            $image = favor_image_fit($image, $maxW, $maxH);
            $targetExtension = 'webp';
            $target = $directory . '/' . $base . '.webp';
            $converted = favor_image_save($image, $target, 85);
            imagedestroy($image);
        }
    }

    if (!$converted) {
        $targetExtension = $extension;
        $target = $directory . '/' . $base . '.' . $targetExtension;
        if (!@move_uploaded_file($tmp, $target)) throw new RuntimeException('El servidor no pudo guardar el retrato.');
    }

    @chmod($target, 0644);
    return [
        'path' => 'media/characters/' . $folder . '/' . $base . '.' . $targetExtension,
        'absolute' => $target,
    ];
}

function new_character_id(mysqli $db): string
{
    for ($i = 0; $i < 6; $i++) {
        $id = 'arch-' . bin2hex(random_bytes(14));
        $stmt = $db->prepare('SELECT ID FROM Personaje WHERE ID=? LIMIT 1');
        bind_execute($stmt, [$id]);
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) return $id;
    }
    throw new RuntimeException('No se pudo generar un ID único para el personaje.');
}

$data = input_data();
$action = strv($data, 'action', 80);

try {
    if ($action === '') throw new RuntimeException('Acción no indicada.');

    if ($action === 'admin_login') {
        respond(false, ['error' => 'El acceso ahora usa el inicio de sesión único de Favor de Jack.'], 409);
    }

    if ($action === 'admin_logout') {
        guild_verify_csrf(strv($data, 'csrf', 100));
        favor_logout();
        respond(true, ['message' => 'Sesión cerrada.']);
    }

    guild_verify_csrf(strv($data, 'csrf', 100));
    $admin = guild_require_admin();

    if ($action === 'save_character') {
        $id = strv($data, 'id', 100);
        $isNew = $id === '';

        $shortName = strv($data, 'shortName', 100);
        $name = strv($data, 'name', 150);
        if ($shortName === '' && $name !== '') $shortName = $name;
        if ($name === '' && $shortName !== '') $name = $shortName;
        if ($shortName === '' || $name === '') throw new RuntimeException('El nombre no puede quedar vacío.');

        $description = strv($data, 'description', 255);
        $portrait = normalize_portrait_path(strv($data, 'portrait', 300));
        $height = nullable_number_string($data, 'height');
        $age = strv($data, 'age', 50);
        $sex = strv($data, 'sex', 30);
        $race = strv($data, 'race', 100);
        $raceType = strv($data, 'raceType', 100);
        $class = strv($data, 'class', 150);
        $level = nullable_number_string($data, 'level');
        $str = nullable_number_string($data, 'str');
        $dex = nullable_number_string($data, 'dex');
        $con = nullable_number_string($data, 'con');
        $int = nullable_number_string($data, 'int');
        $wis = nullable_number_string($data, 'wis');
        $cha = nullable_number_string($data, 'cha');
        $feats = nullable_number_string($data, 'feats');
        $threat = strv($data, 'threat', 50);
        $state = strv($data, 'state', 50) ?: 'Activo';
        $categoryRaw = strv($data, 'category', 20);
        $category = mb_strtolower($categoryRaw, 'UTF-8') === 'npc' ? 'NPC' : 'Personaje';
        $biography = strv($data, 'biography', 65535);
        $requestedPlayerId = strv($data, 'playerId', 100);

        $db = db();
        $db->begin_transaction();
        $uploadedAbsolute = null;
        try {
            if ($isNew) {
                if ($requestedPlayerId === '') throw new RuntimeException('Selecciona el jugador del personaje.');
                $playerId = $requestedPlayerId;
            } else {
                $lookup = $db->prepare('SELECT Jugador FROM Personaje WHERE ID=? LIMIT 1 FOR UPDATE');
                bind_execute($lookup, [$id]);
                $existing = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$existing) throw new RuntimeException('El personaje ya no existe en la Banca.');
                $playerId = (string)$existing['Jugador'];
            }

            $stmt = $db->prepare('SELECT ID, Nombre FROM Jugador WHERE ID=? LIMIT 1');
            bind_execute($stmt, [$playerId]);
            $player = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$player) throw new RuntimeException('El jugador seleccionado no existe.');

            if ($isNew) {
                $dup = $db->prepare('SELECT ID FROM Personaje WHERE Jugador=? AND LOWER(TRIM(Nombre))=LOWER(TRIM(?)) LIMIT 1');
                bind_execute($dup, [$playerId, $shortName]);
                $duplicate = $dup->get_result()->fetch_assoc();
                $dup->close();
                if ($duplicate) throw new RuntimeException('Ese jugador ya tiene un personaje con ese nombre.');
                $id = new_character_id($db);
            }

            if (isset($_FILES['portraitFile']) && is_array($_FILES['portraitFile'])) {
                $upload = uploaded_portrait($_FILES['portraitFile'], (string)$player['Nombre'], $shortName);
                if ($upload['path'] !== null) {
                    $portrait = (string)$upload['path'];
                    $uploadedAbsolute = $upload['absolute'];
                }
            }

            if ($isNew) {
                $stmt = $db->prepare('SELECT COALESCE(MAX(Orden), -1) + 1 AS NextOrder FROM Personaje WHERE Jugador=?');
                bind_execute($stmt, [$playerId]);
                $nextOrder = (int)($stmt->get_result()->fetch_assoc()['NextOrder'] ?? 0);
                $stmt->close();

                $active = mb_strtolower($state, 'UTF-8') === 'activo' ? '1' : '0';
                $zero = '0';
                $stmt = $db->prepare(
                    'INSERT INTO Personaje (ID,Jugador,Nombre,Platino,Oro,Plata,Cobre,Descripcion,Notas,IconUrl,PortraitUrl,Orden,Activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                bind_execute($stmt, [
                    $id, $playerId, $shortName, $zero, $zero, $zero, $zero, $description, '',
                    $portrait, $portrait, (string)$nextOrder, $active
                ]);
                $stmt->close();

                $stmt = $db->prepare(
                    'INSERT INTO PersonajePerfil (ID,NombreCompleto,Altura,Edad,Sexo,Raza,TipoRaza,Clase,Nivel,Fuerza,Destreza,Constitucion,Inteligencia,Sabiduria,Carisma,Dotes,NivelAmenaza,Estado,Categoria,Biografia) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                bind_execute($stmt, [
                    $id, $name, $height, $age, $sex, $race, $raceType, $class, $level,
                    $str, $dex, $con, $int, $wis, $cha, $feats, $threat, $state, $category, $biography
                ]);
                $stmt->close();
            } else {
                $stmt = $db->prepare('UPDATE Personaje SET Nombre=?, Descripcion=?, PortraitUrl=?, IconUrl=COALESCE(NULLIF(IconUrl,\'\'),?) WHERE ID=?');
                bind_execute($stmt, [$shortName, $description, $portrait, $portrait, $id]);
                $stmt->close();

                $stmt = $db->prepare(
                    'UPDATE PersonajePerfil SET NombreCompleto=?,Altura=?,Edad=?,Sexo=?,Raza=?,TipoRaza=?,Clase=?,Nivel=?,Fuerza=?,Destreza=?,Constitucion=?,Inteligencia=?,Sabiduria=?,Carisma=?,Dotes=?,NivelAmenaza=?,Estado=?,Categoria=?,Biografia=? WHERE ID=?'
                );
                bind_execute($stmt, [
                    $name, $height, $age, $sex, $race, $raceType, $class, $level,
                    $str, $dex, $con, $int, $wis, $cha, $feats, $threat, $state, $category, $biography, $id
                ]);
                if ($stmt->affected_rows === 0) {
                    $check = $db->prepare('SELECT ID FROM PersonajePerfil WHERE ID=?');
                    bind_execute($check, [$id]);
                    $exists = (bool)$check->get_result()->fetch_assoc();
                    $check->close();
                    if (!$exists) throw new RuntimeException('El perfil ya no existe.');
                }
                $stmt->close();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            if ($isNew && $uploadedAbsolute && is_file($uploadedAbsolute)) @unlink($uploadedAbsolute);
            throw $e;
        } finally {
            $db->close();
        }

        respond(true, [
            'message' => $isNew ? ($category === 'NPC' ? 'NPC creado y archivado.' : 'Personaje creado y archivado.') : 'Expediente actualizado.',
            'id' => $id,
            'portrait' => $portrait,
        ]);
    }

    if ($action === 'remove_character_profile') {
        $id = strv($data, 'id', 100);
        if ($id === '') throw new RuntimeException('Personaje no válido.');

        $db = db();
        $stmt = $db->prepare('DELETE FROM PersonajePerfil WHERE ID=?');
        bind_execute($stmt, [$id]);
        $deleted = $stmt->affected_rows;
        $stmt->close();
        $db->close();
        if ($deleted < 1) throw new RuntimeException('El perfil ya no existe.');
        respond(true, ['message' => 'El personaje fue retirado de Archivos del Gremio. La Banca no fue modificada.']);
    }

    if ($action === 'save_mission') {
        $missionId = (int)($data['id'] ?? 0);
        $name = strv($data, 'name', 160);
        if ($name === '') throw new RuntimeException('La misión necesita un nombre.');
        $date = strv($data, 'date', 10);
        $type = strv($data, 'type', 80);
        $state = strv($data, 'state', 50) ?: 'Completada';
        $description = strv($data, 'description', 65535);
        $generalReward = strv($data, 'generalReward', 255);
        $participants = $data['participants'] ?? [];
        if (!is_array($participants)) $participants = [];

        $db = db();
        $oldMissionState = null;
        $missionNames = [];
        $db->begin_transaction();
        try {
            if ($missionId > 0) {
                $stmt = $db->prepare('SELECT Estado FROM Mision WHERE ID=? LIMIT 1');
                bind_execute($stmt, [(string)$missionId]);
                $oldMissionState = (string)($stmt->get_result()->fetch_assoc()['Estado'] ?? '');
                $stmt->close();
                $stmt = $db->prepare('UPDATE Mision SET Nombre=?,Fecha=?,Tipo=?,Estado=?,Descripcion=?,RecompensaGeneral=? WHERE ID=?');
                bind_execute($stmt, [$name, $date !== '' ? $date : null, $type, $state, $description, $generalReward, (string)$missionId]);
                $stmt->close();
            } else {
                $stmt = $db->prepare('INSERT INTO Mision (Nombre,Fecha,Tipo,Estado,Descripcion,RecompensaGeneral,CreadoPor) VALUES (?,?,?,?,?,?,?)');
                bind_execute($stmt, [$name, $date !== '' ? $date : null, $type, $state, $description, $generalReward, (string)$admin['username']]);
                $missionId = (int)$stmt->insert_id;
                $stmt->close();
            }

            $stmt = $db->prepare('DELETE FROM MisionPersonaje WHERE MisionID=?');
            bind_execute($stmt, [(string)$missionId]);
            $stmt->close();

            $seen = [];
            foreach ($participants as $part) {
                if (!is_array($part)) continue;
                $characterId = trim((string)($part['id'] ?? ''));
                if ($characterId === '' || isset($seen[$characterId])) continue;
                $seen[$characterId] = true;

                $lookup = $db->prepare(
                    'SELECT p.ID, COALESCE(NULLIF(pp.NombreCompleto,\'\'),p.Nombre) AS Nombre, pp.Clase, pp.Nivel FROM Personaje p LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID WHERE p.ID=? LIMIT 1'
                );
                bind_execute($lookup, [$characterId]);
                $char = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$char) continue;
                $missionNames[] = (string)$char['Nombre'];

                $reward = mb_substr(trim((string)($part['reward'] ?? '')), 0, 255, 'UTF-8');
                $notes = mb_substr(trim((string)($part['notes'] ?? '')), 0, 65535, 'UTF-8');
                $level = $char['Nivel'] !== null ? (string)$char['Nivel'] : null;

                $stmt = $db->prepare(
                    'INSERT INTO MisionPersonaje (MisionID,PersonajeID,NombreSnapshot,ClaseSnapshot,NivelSnapshot,Recompensa,Notas) VALUES (?,?,?,?,?,?,?)'
                );
                bind_execute($stmt, [
                    (string)$missionId,
                    $characterId,
                    (string)$char['Nombre'],
                    (string)($char['Clase'] ?? ''),
                    $level,
                    $reward,
                    $notes,
                ]);
                $stmt->close();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        } finally {
            $db->close();
        }

        // Aviso en Discord: misión nueva o que cambia de estado.
        if ($oldMissionState === null || mb_strtolower($oldMissionState, 'UTF-8') !== mb_strtolower($state, 'UTF-8')) {
            favor_discord_send(
                'misiones',
                ($oldMissionState === null ? '📜 Nueva misión: ' : '📜 Misión ' . mb_strtolower($state, 'UTF-8') . ': ') . $name,
                mb_substr($description, 0, 600, 'UTF-8'),
                [
                    ['Estado', $state],
                    ['Tipo', $type],
                    ['Fecha', $date !== '' ? date('d-m-Y', strtotime($date) ?: time()) : ''],
                    ['Participantes', implode(', ', $missionNames)],
                    ['Recompensa', $generalReward],
                ]
            );
        }

        respond(true, ['message' => 'Misión guardada.', 'id' => $missionId]);
    }

    if ($action === 'delete_mission') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Misión no válida.');
        $db = db();
        $stmt = $db->prepare('DELETE FROM Mision WHERE ID=?');
        bind_execute($stmt, [(string)$id]);
        $deleted = $stmt->affected_rows;
        $stmt->close();
        $db->close();
        if ($deleted < 1) throw new RuntimeException('La misión ya no existe.');
        respond(true, ['message' => 'Misión eliminada.']);
    }

    throw new RuntimeException('Acción desconocida.');
} catch (Throwable $e) {
    respond(false, ['error' => $e->getMessage()], 400);
}
