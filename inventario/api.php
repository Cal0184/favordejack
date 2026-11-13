<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/imagenes.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/estado.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function inv_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function inv_fail(string $message, int $status = 400): void {
    inv_json(['ok' => false, 'error' => $message], $status);
}

function inv_close_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function inv_can_edit(?array $user, array $character): bool {
    return $user && (
        (string)($user['role'] ?? '') === 'admin' ||
        (string)($user['playerId'] ?? '') === (string)($character['Jugador'] ?? '')
    );
}

function inv_character_payload(array $row): array {
    $portraitInput = (string)($row['PortraitUrl'] ?? '');
    return [
        'id' => (string)$row['ID'],
        'playerId' => (string)$row['Jugador'],
        'name' => (string)$row['Nombre'],
        'playerName' => (string)($row['JugadorNombre'] ?? ''),
        'active' => (int)($row['Activo'] ?? 1) === 1,
        'status' => favor_estado_publico(favor_estado_de((string)$row['ID'])),
        'portraitUrl' => favor_thumb_url($portraitInput),
    ];
}

function inv_item_payload(array $row, bool $includePrivateNotes = false): array {
    $rawImage = (string)($row['ImageUrl'] ?? '');
    return [
        'id' => (int)$row['ID'],
        'name' => (string)$row['Nombre'],
        'category' => (string)$row['Categoria'],
        'quantity' => (int)$row['Cantidad'],
        'equipped' => (int)$row['Equipado'] === 1,
        'description' => (string)($row['Descripcion'] ?? ''),
        'notes' => $includePrivateNotes ? (string)($row['Notas'] ?? '') : '',
        'imageInput' => $rawImage,
        'imageUrl' => favor_asset_url($rawImage),
        'created' => (string)($row['Creado'] ?? ''),
        'updated' => (string)($row['Actualizado'] ?? ''),
    ];
}

function inv_character_row(mysqli $db, string $characterId): ?array {
    $st = $db->prepare(
        'SELECT p.ID,p.Jugador,p.Nombre,p.PortraitUrl,p.Activo,j.Nombre AS JugadorNombre,j.Orden AS JugadorOrden,p.Orden '
        . 'FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador WHERE p.ID=? LIMIT 1'
    );
    $st->bind_param('s', $characterId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    return $row;
}

function inv_characters_for_player(mysqli $db, string $playerId): array {
    $st = $db->prepare(
        'SELECT p.ID,p.Jugador,p.Nombre,p.PortraitUrl,p.Activo,j.Nombre AS JugadorNombre,j.Orden AS JugadorOrden,p.Orden '
        . 'FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador WHERE p.Jugador=? AND p.Activo=1 '
        . 'ORDER BY COALESCE(p.Orden,9999),p.Nombre'
    );
    $st->bind_param('s', $playerId);
    $st->execute();
    $result = $st->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = inv_character_payload($row);
    }
    $st->close();
    return $rows;
}

function inv_items_for_character(mysqli $db, string $characterId, bool $includePrivateNotes = false): array {
    $st = $db->prepare(
        'SELECT ID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,Creado,Actualizado '
        . 'FROM InventarioItem WHERE PersonajeID=? ORDER BY Equipado DESC,Categoria,Nombre'
    );
    $st->bind_param('s', $characterId);
    $st->execute();
    $result = $st->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = inv_item_payload($row, $includePrivateNotes);
    }
    $st->close();
    return $rows;
}

function inv_players(mysqli $db): array {
    $result = $db->query('SELECT ID,Nombre,Orden FROM Jugador ORDER BY COALESCE(Orden,9999),Nombre');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (string)$row['ID'],
            'name' => (string)$row['Nombre'],
        ];
    }
    $result->free();
    return $rows;
}

function inv_inventory_payload(mysqli $db, ?array $user, array $character): array {
    $canEdit = inv_can_edit($user, $character);
    return [
        'character' => inv_character_payload($character),
        'canEdit' => $canEdit,
        'items' => inv_items_for_character($db, (string)$character['ID'], $canEdit),
    ];
}

favor_session_start();
$user = favor_current_user();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '')));

if ($method === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
    } catch (Throwable $e) {
        inv_close_session();
        inv_fail('La sesión de edición expiró. Recarga la página e inténtalo de nuevo.', 403);
    }
}
inv_close_session();

$db = null;
try {
    $db = favor_db();
    if (in_array($action, ['bootstrap', 'save', 'delete', 'equip'], true)) {
        favor_ensure_schema($db);
    }

    if ($method === 'GET' && $action === 'bootstrap') {
        $players = inv_players($db);
        $requestedCharacter = trim((string)($_GET['characterId'] ?? ''));
        $selectedCharacter = $requestedCharacter !== '' ? inv_character_row($db, $requestedCharacter) : null;
        $selectedPlayerId = $selectedCharacter ? (string)$selectedCharacter['Jugador'] : '';

        if ($selectedPlayerId === '' && $user && (string)($user['playerId'] ?? '') !== '') {
            $candidate = (string)$user['playerId'];
            foreach ($players as $player) {
                if ($player['id'] === $candidate) {
                    $selectedPlayerId = $candidate;
                    break;
                }
            }
        }
        if ($selectedPlayerId === '' && $players) {
            $selectedPlayerId = $players[0]['id'];
        }

        $characters = $selectedPlayerId !== '' ? inv_characters_for_player($db, $selectedPlayerId) : [];
        $inventory = $selectedCharacter ? inv_inventory_payload($db, $user, $selectedCharacter) : null;

        inv_json([
            'ok' => true,
            'data' => [
                'players' => $players,
                'selectedPlayerId' => $selectedPlayerId,
                'characters' => $characters,
                'inventory' => $inventory,
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'characters') {
        $playerId = trim((string)($_GET['playerId'] ?? ''));
        if ($playerId === '') {
            inv_fail('Falta el jugador.', 422);
        }
        inv_json(['ok' => true, 'data' => ['characters' => inv_characters_for_player($db, $playerId)]]);
    }

    if ($method === 'GET' && $action === 'context') {
        $characterId = trim((string)($_GET['characterId'] ?? ''));
        if ($characterId === '') {
            inv_fail('Falta el personaje.', 422);
        }
        $character = inv_character_row($db, $characterId);
        if (!$character) {
            inv_fail('El personaje no existe.', 404);
        }
        inv_json(['ok' => true, 'data' => ['character' => inv_character_payload($character)]]);
    }

    if ($method === 'GET' && $action === 'inventory') {
        $characterId = trim((string)($_GET['characterId'] ?? ''));
        if ($characterId === '') {
            inv_fail('Falta el personaje.', 422);
        }
        $character = inv_character_row($db, $characterId);
        if (!$character) {
            inv_fail('El personaje no existe.', 404);
        }
        inv_json(['ok' => true, 'data' => inv_inventory_payload($db, $user, $character)]);
    }

    if ($method === 'POST' && $action === 'save') {
        $characterId = trim((string)($_POST['characterId'] ?? ''));
        if ($characterId === '') {
            inv_fail('Falta el personaje.', 422);
        }
        $character = inv_character_row($db, $characterId);
        if (!$character) {
            inv_fail('El personaje no existe.', 404);
        }
        if (!inv_can_edit($user, $character)) {
            inv_fail('No tienes permiso para modificar este inventario.', 403);
        }

        $id = max(0, (int)($_POST['id'] ?? 0));
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 160, 'UTF-8');
        if ($name === '') {
            inv_fail('El objeto necesita un nombre.', 422);
        }
        $category = mb_substr(trim((string)($_POST['category'] ?? 'Utilidad')), 0, 80, 'UTF-8');
        if ($category === '') {
            $category = 'Utilidad';
        }
        $quantity = max(1, min(9999, (int)($_POST['quantity'] ?? 1)));
        $equipped = !empty($_POST['equipped']) ? 1 : 0;
        $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 10000, 'UTF-8');
        $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 10000, 'UTF-8');
        $rawImage = trim((string)($_POST['imageUrl'] ?? ''));
        $image = favor_safe_asset_input($rawImage);
        if ($rawImage !== '' && $image === '') {
            inv_fail('La URL de imagen no es válida.', 422);
        }

        if ($id > 0) {
            $check = $db->prepare('SELECT ID FROM InventarioItem WHERE ID=? AND PersonajeID=? LIMIT 1');
            $check->bind_param('is', $id, $characterId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$exists) {
                inv_fail('Ese objeto ya no existe.', 404);
            }

            $st = $db->prepare('UPDATE InventarioItem SET Nombre=?,Categoria=?,Cantidad=?,Equipado=?,Descripcion=?,Notas=?,ImageUrl=? WHERE ID=? AND PersonajeID=?');
            $st->bind_param('ssiisssis', $name, $category, $quantity, $equipped, $description, $notes, $image, $id, $characterId);
            $st->execute();
            $st->close();
            $message = 'Objeto actualizado.';
        } else {
            $author = (string)($user['username'] ?? '');
            $st = $db->prepare('INSERT INTO InventarioItem (PersonajeID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,CreadoPor) VALUES (?,?,?,?,?,?,?,?,?)');
            $st->bind_param('sssiissss', $characterId, $name, $category, $quantity, $equipped, $description, $notes, $image, $author);
            $st->execute();
            $id = (int)$db->insert_id;
            $st->close();
            $message = 'Objeto añadido al inventario.';
        }

        $st = $db->prepare('SELECT ID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,Creado,Actualizado FROM InventarioItem WHERE ID=? AND PersonajeID=? LIMIT 1');
        $st->bind_param('is', $id, $characterId);
        $st->execute();
        $saved = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$saved) {
            inv_fail('El objeto se guardó, pero no pudo recargarse.', 500);
        }

        inv_json(['ok' => true, 'message' => $message, 'data' => ['item' => inv_item_payload($saved, true)]]);
    }


    if ($method === 'POST' && $action === 'equip') {
        $characterId = trim((string)($_POST['characterId'] ?? ''));
        $id = max(0, (int)($_POST['id'] ?? 0));
        $equipped = ((string)($_POST['equipped'] ?? '0')) === '1' ? 1 : 0;
        if ($characterId === '' || $id < 1) {
            inv_fail('Datos de equipamiento incompletos.', 422);
        }
        $character = inv_character_row($db, $characterId);
        if (!$character) {
            inv_fail('El personaje no existe.', 404);
        }
        if (!inv_can_edit($user, $character)) {
            inv_fail('No tienes permiso para modificar este inventario.', 403);
        }

        $st = $db->prepare('UPDATE InventarioItem SET Equipado=? WHERE ID=? AND PersonajeID=?');
        $st->bind_param('iis', $equipped, $id, $characterId);
        $st->execute();
        $affected = $st->affected_rows;
        $st->close();

        $st = $db->prepare('SELECT ID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,Creado,Actualizado FROM InventarioItem WHERE ID=? AND PersonajeID=? LIMIT 1');
        $st->bind_param('is', $id, $characterId);
        $st->execute();
        $saved = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$saved) {
            inv_fail('Ese objeto ya no existe.', 404);
        }

        inv_json([
            'ok' => true,
            'message' => $equipped ? 'Objeto equipado.' : 'Objeto guardado en el inventario.',
            'data' => ['item' => inv_item_payload($saved, true)],
        ]);
    }

    if ($method === 'POST' && $action === 'delete') {
        $characterId = trim((string)($_POST['characterId'] ?? ''));
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($characterId === '' || $id < 1) {
            inv_fail('Datos de eliminación incompletos.', 422);
        }
        $character = inv_character_row($db, $characterId);
        if (!$character) {
            inv_fail('El personaje no existe.', 404);
        }
        if (!inv_can_edit($user, $character)) {
            inv_fail('No tienes permiso para modificar este inventario.', 403);
        }

        $st = $db->prepare('DELETE FROM InventarioItem WHERE ID=? AND PersonajeID=?');
        $st->bind_param('is', $id, $characterId);
        $st->execute();
        $affected = $st->affected_rows;
        $st->close();
        if ($affected < 1) {
            inv_fail('Ese objeto ya no existe.', 404);
        }
        inv_json(['ok' => true, 'message' => 'Objeto eliminado.', 'data' => ['id' => $id]]);
    }

    inv_fail('Acción no válida.', 404);
} catch (Throwable $e) {
    error_log('[Favor de Jack · Inventario] ' . $e->getMessage());
    inv_fail('No fue posible completar la operación del inventario.', 500);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
    }
}
