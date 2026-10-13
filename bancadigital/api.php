<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/migrations.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const COINS = ['ppt', 'po', 'pl', 'pc'];
const MAX_COIN_VALUE = 1000000000;

function respond(array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text(mixed $value, int $max): string {
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function read_body(): array {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) {
        respond(['ok' => false, 'error' => 'Datos inválidos.'], 400);
    }
    return $body;
}

function require_csrf(): void {
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string)($_SESSION['csrf'] ?? '');
    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
        respond(['ok' => false, 'error' => 'La sesión de seguridad venció. Recarga la página.'], 403);
    }
}

function current_user(mysqli $db): array {
    $global = favor_current_user(true);
    if (!$global) {
        return [
            'id' => 0,
            'username' => '',
            'playerId' => '',
            'role' => 'guest',
            'active' => false,
            'displayName' => 'Invitado',
            'loggedIn' => false,
        ];
    }

    // Compatibilidad con la sesión histórica de la Banca.
    $_SESSION['banca_user'] = [
        'id' => (int)$global['id'],
        'username' => (string)$global['username'],
        'playerId' => (string)$global['playerId'],
        'role' => (string)$global['role'],
        'active' => true,
    ];

    return [
        'id' => (int)$global['id'],
        'username' => (string)$global['username'],
        'playerId' => (string)$global['playerId'],
        'role' => (string)$global['role'],
        'active' => true,
        'displayName' => (string)$global['displayName'],
        'loggedIn' => true,
    ];
}

function public_user(array $user): array {
    return [
        'username' => (string)($user['username'] ?? ''),
        'displayName' => (string)($user['displayName'] ?? 'Invitado'),
        'playerId' => (string)($user['playerId'] ?? ''),
        'role' => (string)($user['role'] ?? 'guest'),
        'loggedIn' => !empty($user['loggedIn']),
    ];
}

function require_banca_login(array $user): void {
    if (empty($user['loggedIn']) || ($user['role'] ?? '') === 'guest') {
        respond(['ok' => false, 'error' => 'Inicia sesión para modificar la Banca.'], 401);
    }
}

function clean_asset_url(mixed $value): string {
    return favor_safe_asset_input(clean_text($value, 300));
}

function asset_name(string $value): string {
    $value = preg_replace('/[<>:"\/\\\\|?*]/u', '', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return $value ?: 'personaje';
}

function character_asset_name(string $value): string {
    $normalized = asset_name($value);
    $aliases = [
        'aliche de fleur' => 'aliche',
        'julieta ciero' => 'julieta',
        'pahnaetiel' => 'pan',
        'samantha masoch' => 'samantha',
        'iván greco' => 'ivangreco',
    ];
    return $aliases[$normalized] ?? $normalized;
}

function is_text_only_player_name(string $value): bool {
    return in_array(asset_name($value), ['npc', 'npcs', 'muertos', 'personajes fuera', 'retirados'], true);
}

function player_from_row(array $row): array {
    return [
        'id' => (string)$row['ID'],
        'name' => (string)$row['Nombre'],
        'handle' => (string)$row['Handle'],
        'emoji' => (string)$row['Emoji'],
        'iconBg' => (string)($row['IconBg'] ?? '#22402F'),
        'iconUrl' => (string)$row['IconUrl'],
        'order' => (int)$row['Orden'],
        'active' => (bool)$row['Activo'],
    ];
}

function fetch_player(mysqli $db, string $id): ?array {
    $stmt = $db->prepare('SELECT ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo FROM Jugador WHERE ID = ? LIMIT 1');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? player_from_row($row) : null;
}

function character_from_row(array $row): array {
    $character = [
        'id' => (string)$row['ID'],
        'playerId' => (string)$row['Jugador'],
        'name' => (string)$row['Nombre'],
        'coins' => [
            'ppt' => (int)$row['Platino'],
            'po' => (int)$row['Oro'],
            'pl' => (int)$row['Plata'],
            'pc' => (int)$row['Cobre'],
        ],
        'description' => (string)$row['Descripcion'],
        'notes' => (string)$row['Notas'],
        'order' => (int)$row['Orden'],
        'active' => (bool)$row['Activo'],
    ];
    if ($row['IconUrl'] !== null && (string)$row['IconUrl'] !== '') $character['iconUrl'] = (string)$row['IconUrl'];
    if ($row['PortraitUrl'] !== null && (string)$row['PortraitUrl'] !== '') $character['portraitUrl'] = (string)$row['PortraitUrl'];
    return $character;
}

function fetch_players(mysqli $db, array $user): array {
    // La Banca es visible en modo lectura para todos. Los permisos se aplican al modificar.
    $result = $db->query('SELECT ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo FROM Jugador ORDER BY Orden, Nombre');
    $players = [];
    while ($row = $result->fetch_assoc()) $players[] = player_from_row($row);
    $result->free();
    return $players;
}

function fetch_characters(mysqli $db, array $user): array {
    // Igual que los jugadores: lectura global, escritura limitada por can_access_character().
    $result = $db->query('SELECT ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo FROM Personaje ORDER BY Jugador, Orden, Nombre');
    $characters = [];
    while ($row = $result->fetch_assoc()) $characters[] = character_from_row($row);
    $result->free();
    return $characters;
}

function fetch_character(mysqli $db, string $id, bool $forUpdate = false): ?array {
    $sql = 'SELECT ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo FROM Personaje WHERE ID = ? LIMIT 1';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? character_from_row($row) : null;
}

function can_access_character(array $user, array $character): bool {
    return ($user['role'] ?? '') === 'admin' || ($character['playerId'] ?? '') === ($user['playerId'] ?? '');
}

function movement_from_row(array $row): array {
    $date = (string)$row['Fecha'];
    $dateIso = str_replace(' ', 'T', $date) . 'Z';
    return [
        'id' => (string)$row['ID'],
        'date' => $dateIso,
        'type' => (string)$row['Tipo'],
        'delta' => [
            'ppt' => (int)$row['DeltaPlatino'],
            'po' => (int)$row['DeltaOro'],
            'pl' => (int)$row['DeltaPlata'],
            'pc' => (int)$row['DeltaCobre'],
        ],
        'reason' => (string)$row['Motivo'],
        'author' => (string)$row['Autor'],
        'resultBalance' => [
            'ppt' => (int)$row['SaldoPlatino'],
            'po' => (int)$row['SaldoOro'],
            'pl' => (int)$row['SaldoPlata'],
            'pc' => (int)$row['SaldoCobre'],
        ],
    ];
}

function fetch_movements(mysqli $db, string $charId): array {
    $stmt = $db->prepare(
        'SELECT ID, Personaje, Fecha, Tipo, DeltaPlatino, DeltaOro, DeltaPlata, DeltaCobre, Motivo, Autor, SaldoPlatino, SaldoOro, SaldoPlata, SaldoCobre '
        . 'FROM Movimiento WHERE Personaje = ? ORDER BY Fecha ASC, ID ASC'
    );
    $stmt->bind_param('s', $charId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = movement_from_row($row);
    $stmt->close();
    return $items;
}

function close_session_lock(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

try {
    /** @var mysqli $db */
    $db = require __DIR__ . '/db.php';
    favor_ensure_schema($db);
    $user = current_user($db);
    $action = (string)($_GET['action'] ?? 'state');

    if ($action === 'state') {
        respond([
            'ok' => true,
            'players' => fetch_players($db, $user),
            'characters' => fetch_characters($db, $user),
            'user' => public_user($user),
            'csrf' => (string)($_SESSION['csrf'] ?? ''),
        ]);
    }

    if ($action === 'movements') {
        $charId = clean_text($_GET['character_id'] ?? '', 100);
        $character = fetch_character($db, $charId);
        if (!$character) {
            respond(['ok' => false, 'error' => 'Personaje no encontrado.'], 404);
        }
        // El historial es parte del modo lectura público.
        respond(['ok' => true, 'movements' => fetch_movements($db, $charId)]);
    }

    if ($action === 'transfer_targets') {
        require_banca_login($user);
        $sourceId = clean_text($_GET['source_id'] ?? '', 100);
        $source = fetch_character($db, $sourceId);
        if (!$source || !can_access_character($user, $source)) {
            respond(['ok' => false, 'error' => 'Personaje de origen no autorizado.'], 403);
        }

        $stmt = $db->prepare(
            'SELECT p.ID, p.Nombre, j.Nombre AS JugadorNombre '
            . 'FROM Personaje p INNER JOIN Jugador j ON j.ID = p.Jugador '
            . 'WHERE p.Activo = 1 AND j.Activo = 1 AND p.ID <> ? '
            . 'ORDER BY j.Orden, p.Orden, p.Nombre'
        );
        $stmt->bind_param('s', $sourceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $targets = [];
        while ($row = $result->fetch_assoc()) {
            $targets[] = [
                'id' => (string)$row['ID'],
                'name' => (string)$row['Nombre'],
                'playerName' => (string)$row['JugadorNombre'],
            ];
        }
        $stmt->close();
        respond(['ok' => true, 'targets' => $targets]);
    }

    require_banca_login($user);
    require_csrf();
    $body = read_body();

    if ($action === 'save_player') {
        $candidate = $body['player'] ?? null;
        if (!is_array($candidate)) respond(['ok' => false, 'error' => 'Jugador inválido.'], 400);

        $id = clean_text($candidate['id'] ?? '', 5);
        if (!preg_match('/^[A-Za-z0-9_-]{1,5}$/', $id)) {
            respond(['ok' => false, 'error' => 'ID de jugador inválido.'], 400);
        }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo FROM Jugador WHERE ID = ? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $oldRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $isAdmin = ($user['role'] ?? '') === 'admin';
            if (!$isAdmin && $id !== ($user['playerId'] ?? '')) {
                throw new DomainException('No puedes modificar ese jugador.');
            }
            if (!$oldRow && !$isAdmin) {
                throw new DomainException('Solo un administrador puede crear jugadores.');
            }

            $name = clean_text($candidate['name'] ?? '', 60);
            if ($name === '') throw new DomainException('Escribe un nombre para el jugador.');
            $handle = clean_text($candidate['handle'] ?? '', 40);
            $emoji = clean_text($candidate['emoji'] ?? ($oldRow['Emoji'] ?? '🛡️'), 16);
            if ($emoji === '') $emoji = '🛡️';
            $iconBg = clean_text($candidate['iconBg'] ?? ($oldRow['IconBg'] ?? '#22402F'), 20);
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $iconBg)) $iconBg = '#22402F';
            $icon = $oldRow ? (string)($oldRow['IconUrl'] ?? '') : '';
            if ($isAdmin) $icon = clean_asset_url($candidate['iconUrl'] ?? $icon);
            $order = $oldRow ? (int)$oldRow['Orden'] : max(0, min(65535, (int)($candidate['order'] ?? 0)));
            $active = $oldRow ? (int)$oldRow['Activo'] : 1;
            if ($isAdmin) {
                $order = max(0, min(65535, (int)($candidate['order'] ?? $order)));
                $active = (($candidate['active'] ?? true) !== false) ? 1 : 0;
            }

            if ($oldRow) {
                $stmt = $db->prepare('UPDATE Jugador SET Nombre=?, Handle=?, Emoji=?, IconBg=?, IconUrl=?, Orden=?, Activo=? WHERE ID=?');
                $stmt->bind_param('sssssiis', $name, $handle, $emoji, $iconBg, $icon, $order, $active, $id);
            } else {
                $stmt = $db->prepare('INSERT INTO Jugador (ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('ssssssii', $id, $name, $handle, $emoji, $iconBg, $icon, $order, $active);
            }
            $stmt->execute();
            $stmt->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        respond(['ok' => true, 'player' => fetch_player($db, $id)]);
    }

    if ($action === 'save_character') {
        $candidate = $body['character'] ?? null;
        if (!is_array($candidate)) respond(['ok' => false, 'error' => 'Personaje inválido.'], 400);

        $id = clean_text($candidate['id'] ?? '', 100);
        $requestedPid = clean_text($candidate['playerId'] ?? '', 5);
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id) || !preg_match('/^[A-Za-z0-9_-]{1,5}$/', $requestedPid)) {
            respond(['ok' => false, 'error' => 'Datos de personaje inválidos.'], 400);
        }

        $initialCoins = $body['initialCoins'] ?? null;
        if ($initialCoins !== null && !is_array($initialCoins)) {
            respond(['ok' => false, 'error' => 'Saldo inicial inválido.'], 400);
        }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo FROM Personaje WHERE ID=? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $oldRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $pid = $oldRow ? (string)$oldRow['Jugador'] : $requestedPid;
            if (($user['role'] ?? '') !== 'admin' && $pid !== ($user['playerId'] ?? '')) {
                throw new DomainException('No puedes modificar ese personaje.');
            }

            $stmt = $db->prepare('SELECT ID, Nombre FROM Jugador WHERE ID=? LIMIT 1');
            $stmt->bind_param('s', $pid);
            $stmt->execute();
            $playerRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$playerRow) throw new DomainException('Jugador no encontrado.');

            $name = clean_text($candidate['name'] ?? '', 100);
            if ($name === '') throw new DomainException('Escribe un nombre para el personaje.');
            $description = clean_text($candidate['description'] ?? '', 255);
            $notes = clean_text($candidate['notes'] ?? '', 5000);
            $active = (($candidate['active'] ?? true) !== false) ? 1 : 0;

            $playerName = (string)$playerRow['Nombre'];
            $isAdmin = ($user['role'] ?? '') === 'admin';

            // NewBanca 2.0: máximo 5 personajes activos por jugador real.
            // Las categorías técnicas NPC / Muertos / Personajes fuera no cuentan como jugadores.
            // Se valida tanto al crear como al reactivar un personaje previamente desactivado.
            if (!is_text_only_player_name($playerName) && $active === 1 && (!$oldRow || (int)$oldRow['Activo'] !== 1)) {
                $stmt = $db->prepare('SELECT COUNT(*) AS Total FROM Personaje WHERE Jugador=? AND Activo=1');
                $stmt->bind_param('s', $pid);
                $stmt->execute();
                $activeCount = (int)($stmt->get_result()->fetch_assoc()['Total'] ?? 0);
                $stmt->close();
                if ($activeCount >= 5) {
                    throw new DomainException('Este jugador ya tiene el máximo de 5 personajes activos.');
                }
            }

            if (is_text_only_player_name($playerName)) {
                $iconUrl = null;
                $portraitUrl = null;
            } elseif ($oldRow) {
                $iconUrl = $oldRow['IconUrl'] !== null ? (string)$oldRow['IconUrl'] : '';
                $portraitUrl = $oldRow['PortraitUrl'] !== null ? (string)$oldRow['PortraitUrl'] : '';
                if ($isAdmin) {
                    $candidateIcon = clean_asset_url($candidate['iconUrl'] ?? $iconUrl);
                    $candidatePortrait = clean_asset_url($candidate['portraitUrl'] ?? $portraitUrl);
                    $iconUrl = $candidateIcon !== '' ? $candidateIcon : null;
                    $portraitUrl = $candidatePortrait !== '' ? $candidatePortrait : null;
                }
            } else {
                $playerFolder = asset_name($playerName);
                $characterFile = character_asset_name($name);
                $iconUrl = "media/icons/$playerFolder/$characterFile.webp";
                $portraitUrl = "media/characters/$playerFolder/$characterFile.webp";
                if ($isAdmin) {
                    $candidateIcon = clean_asset_url($candidate['iconUrl'] ?? '');
                    $candidatePortrait = clean_asset_url($candidate['portraitUrl'] ?? '');
                    if ($candidateIcon !== '') $iconUrl = $candidateIcon;
                    if ($candidatePortrait !== '') $portraitUrl = $candidatePortrait;
                }
            }

            if ($oldRow) {
                $stmt = $db->prepare('UPDATE Personaje SET Nombre=?, Descripcion=?, Notas=?, IconUrl=?, PortraitUrl=?, Activo=? WHERE ID=?');
                $stmt->bind_param('sssssis', $name, $description, $notes, $iconUrl, $portraitUrl, $active, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $db->prepare('SELECT COALESCE(MAX(Orden), -1) + 1 AS NextOrder FROM Personaje WHERE Jugador=?');
                $stmt->bind_param('s', $pid);
                $stmt->execute();
                $nextOrder = (int)($stmt->get_result()->fetch_assoc()['NextOrder'] ?? 0);
                $stmt->close();

                $coins = ['ppt' => 0, 'po' => 0, 'pl' => 0, 'pc' => 0];
                if (is_array($initialCoins)) {
                    foreach (COINS as $coin) {
                        $value = $initialCoins[$coin] ?? 0;
                        if (!is_int($value) || $value < 0 || $value > MAX_COIN_VALUE) {
                            throw new DomainException('Saldo inicial inválido.');
                        }
                        $coins[$coin] = $value;
                    }
                }

                $stmt = $db->prepare(
                    'INSERT INTO Personaje (ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->bind_param('sssiiiissssii', $id, $pid, $name, $coins['ppt'], $coins['po'], $coins['pl'], $coins['pc'], $description, $notes, $iconUrl, $portraitUrl, $nextOrder, $active);
                $stmt->execute();
                $stmt->close();

                if (max($coins) > 0) {
                    $movementId = bin2hex(random_bytes(12));
                    $date = gmdate('Y-m-d H:i:s') . '.000';
                    $type = 'initial';
                    $reason = 'Saldo inicial';
                    $author = clean_text((string)$user['displayName'], 100);
                    $stmt = $db->prepare(
                        'INSERT INTO Movimiento (ID, Personaje, Fecha, Tipo, DeltaPlatino, DeltaOro, DeltaPlata, DeltaCobre, Motivo, Autor, SaldoPlatino, SaldoOro, SaldoPlata, SaldoCobre) '
                        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->bind_param(
                        'ssssiiiissiiii',
                        $movementId, $id, $date, $type,
                        $coins['ppt'], $coins['po'], $coins['pl'], $coins['pc'],
                        $reason, $author,
                        $coins['ppt'], $coins['po'], $coins['pl'], $coins['pc']
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        respond([
            'ok' => true,
            'character' => fetch_character($db, $id),
            'movements' => $oldRow ? null : fetch_movements($db, $id),
        ]);
    }

    if ($action === 'move_character') {
        if (($user['role'] ?? '') !== 'admin') {
            respond(['ok' => false, 'error' => 'Solo un administrador puede mover personajes entre estas secciones.'], 403);
        }

        $characterId = clean_text($body['character_id'] ?? '', 100);
        $targetPlayerId = clean_text($body['target_player_id'] ?? '', 5);
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $characterId) || !preg_match('/^[A-Za-z0-9_-]{1,5}$/', $targetPlayerId)) {
            respond(['ok' => false, 'error' => 'Datos de movimiento inválidos.'], 400);
        }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare(
                'SELECT p.ID, p.Jugador, j.Nombre AS JugadorNombre '
                . 'FROM Personaje p INNER JOIN Jugador j ON j.ID=p.Jugador '
                . 'WHERE p.ID=? LIMIT 1 FOR UPDATE'
            );
            $stmt->bind_param('s', $characterId);
            $stmt->execute();
            $sourceRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$sourceRow) throw new DomainException('Personaje no encontrado.');

            $stmt = $db->prepare('SELECT ID, Nombre, Activo FROM Jugador WHERE ID=? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('s', $targetPlayerId);
            $stmt->execute();
            $targetRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$targetRow || (int)$targetRow['Activo'] !== 1) throw new DomainException('La sección de destino no está disponible.');

            $sourceKey = asset_name((string)$sourceRow['JugadorNombre']);
            $targetKey = asset_name((string)$targetRow['Nombre']);
            $sourceIsRetired = in_array($sourceKey, ['personajes fuera', 'retirados'], true);
            $targetIsRetired = in_array($targetKey, ['personajes fuera', 'retirados'], true);
            $validPair = ($sourceIsRetired && $targetKey === 'muertos')
                || ($sourceKey === 'muertos' && $targetIsRetired);
            if (!$validPair) {
                throw new DomainException('Solo se permite mover entre Retirados y Muertos desde esta utilidad.');
            }

            $stmt = $db->prepare('SELECT COALESCE(MAX(Orden), -1) + 1 AS NextOrder FROM Personaje WHERE Jugador=?');
            $stmt->bind_param('s', $targetPlayerId);
            $stmt->execute();
            $nextOrder = (int)($stmt->get_result()->fetch_assoc()['NextOrder'] ?? 0);
            $stmt->close();

            $stmt = $db->prepare('UPDATE Personaje SET Jugador=?, Orden=? WHERE ID=?');
            $stmt->bind_param('sis', $targetPlayerId, $nextOrder, $characterId);
            $stmt->execute();
            $stmt->close();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        respond(['ok' => true, 'character' => fetch_character($db, $characterId)]);
    }

    if ($action === 'save_players') {
        $incoming = $body['players'] ?? null;
        if (!is_array($incoming)) respond(['ok' => false, 'error' => 'Lista de jugadores inválida.'], 400);

        $db->begin_transaction();
        try {
            foreach ($incoming as $candidate) {
                if (!is_array($candidate)) continue;
                $id = clean_text($candidate['id'] ?? '', 5);
                if (!preg_match('/^[A-Za-z0-9_-]{1,5}$/', $id)) continue;

                $stmt = $db->prepare('SELECT ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo FROM Jugador WHERE ID = ? LIMIT 1 FOR UPDATE');
                $stmt->bind_param('s', $id);
                $stmt->execute();
                $oldRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $isAdmin = ($user['role'] ?? '') === 'admin';
                if (!$isAdmin && $id !== ($user['playerId'] ?? '')) continue;
                if (!$oldRow && !$isAdmin) continue;

                $name = clean_text($candidate['name'] ?? '', 60);
                if ($name === '') continue;
                $handle = clean_text($candidate['handle'] ?? '', 40);
                $emoji = clean_text($candidate['emoji'] ?? ($oldRow['Emoji'] ?? '🛡️'), 16);
                if ($emoji === '') $emoji = '🛡️';
                $iconBg = clean_text($candidate['iconBg'] ?? ($oldRow['IconBg'] ?? '#22402F'), 20);
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $iconBg)) $iconBg = '#22402F';
                $icon = $oldRow ? (string)($oldRow['IconUrl'] ?? '') : '';
                if ($isAdmin) {
                    $icon = clean_asset_url($candidate['iconUrl'] ?? $icon);
                }
                $order = $oldRow ? (int)$oldRow['Orden'] : (int)($candidate['order'] ?? 0);
                $active = $oldRow ? (int)$oldRow['Activo'] : 1;
                if ($isAdmin) {
                    $order = max(0, min(65535, (int)($candidate['order'] ?? $order)));
                    $active = (($candidate['active'] ?? true) !== false) ? 1 : 0;
                }

                if ($oldRow) {
                    $stmt = $db->prepare('UPDATE Jugador SET Nombre=?, Handle=?, Emoji=?, IconBg=?, IconUrl=?, Orden=?, Activo=? WHERE ID=?');
                    $stmt->bind_param('sssssiis', $name, $handle, $emoji, $iconBg, $icon, $order, $active, $id);
                } else {
                    $stmt = $db->prepare('INSERT INTO Jugador (ID, Nombre, Handle, Emoji, IconBg, IconUrl, Orden, Activo) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ssssssii', $id, $name, $handle, $emoji, $iconBg, $icon, $order, $active);
                }
                $stmt->execute();
                $stmt->close();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        respond(['ok' => true, 'players' => fetch_players($db, $user)]);
    }

    if ($action === 'save_characters') {
        $incoming = $body['characters'] ?? null;
        if (!is_array($incoming)) respond(['ok' => false, 'error' => 'Lista de personajes inválida.'], 400);

        $db->begin_transaction();
        try {
            foreach ($incoming as $candidate) {
                if (!is_array($candidate)) continue;
                $id = clean_text($candidate['id'] ?? '', 100);
                $requestedPid = clean_text($candidate['playerId'] ?? '', 5);
                if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id) || !preg_match('/^[A-Za-z0-9_-]{1,5}$/', $requestedPid)) continue;

                $stmt = $db->prepare('SELECT ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo FROM Personaje WHERE ID=? LIMIT 1 FOR UPDATE');
                $stmt->bind_param('s', $id);
                $stmt->execute();
                $oldRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $pid = $oldRow ? (string)$oldRow['Jugador'] : $requestedPid;
                if (($user['role'] ?? '') !== 'admin' && $pid !== ($user['playerId'] ?? '')) continue;

                $stmt = $db->prepare('SELECT ID, Nombre FROM Jugador WHERE ID=? LIMIT 1');
                $stmt->bind_param('s', $pid);
                $stmt->execute();
                $playerRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$playerRow) continue;

                $name = clean_text($candidate['name'] ?? '', 100);
                if ($name === '') continue;
                $description = clean_text($candidate['description'] ?? '', 255);
                $notes = clean_text($candidate['notes'] ?? '', 5000);
                $active = (($candidate['active'] ?? true) !== false) ? 1 : 0;

                $playerName = (string)$playerRow['Nombre'];
                $isAdmin = ($user['role'] ?? '') === 'admin';
                if (is_text_only_player_name($playerName)) {
                    $iconUrl = null;
                    $portraitUrl = null;
                } elseif ($oldRow) {
                    // Conservar las rutas existentes al renombrar. Solo un admin puede modificarlas desde Banca.
                    $iconUrl = $oldRow['IconUrl'] !== null ? (string)$oldRow['IconUrl'] : '';
                    $portraitUrl = $oldRow['PortraitUrl'] !== null ? (string)$oldRow['PortraitUrl'] : '';
                    if ($isAdmin) {
                        $candidateIcon = clean_asset_url($candidate['iconUrl'] ?? $iconUrl);
                        $candidatePortrait = clean_asset_url($candidate['portraitUrl'] ?? $portraitUrl);
                        $iconUrl = $candidateIcon !== '' ? $candidateIcon : null;
                        $portraitUrl = $candidatePortrait !== '' ? $candidatePortrait : null;
                    }
                } else {
                    $playerFolder = asset_name($playerName);
                    $characterFile = character_asset_name($name);
                    $iconUrl = "media/icons/$playerFolder/$characterFile.webp";
                    $portraitUrl = "media/characters/$playerFolder/$characterFile.webp";
                    if ($isAdmin) {
                        $candidateIcon = clean_asset_url($candidate['iconUrl'] ?? '');
                        $candidatePortrait = clean_asset_url($candidate['portraitUrl'] ?? '');
                        if ($candidateIcon !== '') $iconUrl = $candidateIcon;
                        if ($candidatePortrait !== '') $portraitUrl = $candidatePortrait;
                    }
                }

                if ($oldRow) {
                    $stmt = $db->prepare('UPDATE Personaje SET Nombre=?, Descripcion=?, Notas=?, IconUrl=?, PortraitUrl=?, Activo=? WHERE ID=?');
                    $stmt->bind_param('sssssis', $name, $description, $notes, $iconUrl, $portraitUrl, $active, $id);
                } else {
                    $stmt = $db->prepare('SELECT COALESCE(MAX(Orden), -1) + 1 AS NextOrder FROM Personaje WHERE Jugador=?');
                    $stmt->bind_param('s', $pid);
                    $stmt->execute();
                    $nextOrder = (int)($stmt->get_result()->fetch_assoc()['NextOrder'] ?? 0);
                    $stmt->close();
                    $zero = 0;
                    $stmt = $db->prepare(
                        'INSERT INTO Personaje (ID, Jugador, Nombre, Platino, Oro, Plata, Cobre, Descripcion, Notas, IconUrl, PortraitUrl, Orden, Activo) '
                        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->bind_param('sssiiiissssii', $id, $pid, $name, $zero, $zero, $zero, $zero, $description, $notes, $iconUrl, $portraitUrl, $nextOrder, $active);
                }
                $stmt->execute();
                $stmt->close();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        respond(['ok' => true, 'characters' => fetch_characters($db, $user)]);
    }

    if ($action === 'transfer') {
        $sourceId = clean_text($body['source_id'] ?? '', 100);
        $targetId = clean_text($body['target_id'] ?? '', 100);
        $amountIn = $body['amounts'] ?? [];
        $reason = clean_text($body['reason'] ?? '', 120);

        if ($sourceId === '' || $targetId === '' || $sourceId === $targetId || !is_array($amountIn)) {
            respond(['ok' => false, 'error' => 'Transferencia inválida.'], 400);
        }

        $amounts = [];
        foreach (COINS as $coin) {
            $value = $amountIn[$coin] ?? 0;
            if (!is_int($value) || $value < 0 || $value > MAX_COIN_VALUE) {
                respond(['ok' => false, 'error' => 'Cantidad de monedas inválida.'], 400);
            }
            $amounts[$coin] = $value;
        }
        if (max($amounts) <= 0) {
            respond(['ok' => false, 'error' => 'Debes transferir al menos una moneda.'], 400);
        }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare(
                'SELECT p.ID, p.Jugador, p.Nombre, p.Platino, p.Oro, p.Plata, p.Cobre, p.Descripcion, p.Notas, '
                . 'p.IconUrl, p.PortraitUrl, p.Orden, p.Activo, j.Activo AS JugadorActivo '
                . 'FROM Personaje p INNER JOIN Jugador j ON j.ID = p.Jugador '
                . 'WHERE p.ID IN (?, ?) ORDER BY p.ID FOR UPDATE'
            );
            $stmt->bind_param('ss', $sourceId, $targetId);
            $stmt->execute();
            $result = $stmt->get_result();
            $locked = [];
            while ($row = $result->fetch_assoc()) {
                $locked[(string)$row['ID']] = $row;
            }
            $stmt->close();

            if (!isset($locked[$sourceId], $locked[$targetId])) {
                throw new DomainException('No se encontró uno de los personajes.');
            }

            $source = character_from_row($locked[$sourceId]);
            $target = character_from_row($locked[$targetId]);

            if (!can_access_character($user, $source)) {
                throw new DomainException('No puedes transferir dinero desde ese personaje.');
            }
            if (!$source['active'] || !$target['active'] || (int)$locked[$targetId]['JugadorActivo'] !== 1) {
                throw new DomainException('El personaje de destino no está disponible.');
            }

            $sourceNew = [];
            $targetNew = [];
            foreach (COINS as $coin) {
                $sourceNew[$coin] = (int)$source['coins'][$coin] - $amounts[$coin];
                $targetNew[$coin] = (int)$target['coins'][$coin] + $amounts[$coin];
                if ($sourceNew[$coin] < 0) {
                    throw new DomainException('El personaje no tiene monedas suficientes para esa transferencia.');
                }
                if ($targetNew[$coin] > MAX_COIN_VALUE) {
                    throw new DomainException('El saldo del destinatario superaría el límite permitido.');
                }
            }

            $stmt = $db->prepare('UPDATE Personaje SET Platino=?, Oro=?, Plata=?, Cobre=? WHERE ID=?');
            $stmt->bind_param('iiiis', $sourceNew['ppt'], $sourceNew['po'], $sourceNew['pl'], $sourceNew['pc'], $sourceId);
            $stmt->execute();
            $stmt->bind_param('iiiis', $targetNew['ppt'], $targetNew['po'], $targetNew['pl'], $targetNew['pc'], $targetId);
            $stmt->execute();
            $stmt->close();

            $date = gmdate('Y-m-d H:i:s') . '.000';
            $author = clean_text((string)$user['displayName'], 100);
            $reasonSuffix = $reason !== '' ? ' — ' . $reason : '';
            $sourceReason = clean_text('Transferencia a ' . $target['name'] . $reasonSuffix, 255);
            $targetReason = clean_text('Transferencia de ' . $source['name'] . $reasonSuffix, 255);

            $insert = $db->prepare(
                'INSERT INTO Movimiento (ID, Personaje, Fecha, Tipo, DeltaPlatino, DeltaOro, DeltaPlata, DeltaCobre, Motivo, Autor, SaldoPlatino, SaldoOro, SaldoPlata, SaldoCobre) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );

            $sourceMovementId = bin2hex(random_bytes(12));
            $sourceType = 'spend';
            $srcPpt = -$amounts['ppt']; $srcPo = -$amounts['po']; $srcPl = -$amounts['pl']; $srcPc = -$amounts['pc'];
            $insert->bind_param(
                'ssssiiiissiiii',
                $sourceMovementId, $sourceId, $date, $sourceType,
                $srcPpt, $srcPo, $srcPl, $srcPc,
                $sourceReason, $author,
                $sourceNew['ppt'], $sourceNew['po'], $sourceNew['pl'], $sourceNew['pc']
            );
            $insert->execute();

            $targetMovementId = bin2hex(random_bytes(12));
            $targetType = 'add';
            $dstPpt = $amounts['ppt']; $dstPo = $amounts['po']; $dstPl = $amounts['pl']; $dstPc = $amounts['pc'];
            $insert->bind_param(
                'ssssiiiissiiii',
                $targetMovementId, $targetId, $date, $targetType,
                $dstPpt, $dstPo, $dstPl, $dstPc,
                $targetReason, $author,
                $targetNew['ppt'], $targetNew['po'], $targetNew['pl'], $targetNew['pc']
            );
            $insert->execute();
            $insert->close();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $updatedSource = fetch_character($db, $sourceId);
        respond([
            'ok' => true,
            'source' => $updatedSource,
            'character' => $updatedSource, // compatibilidad con clientes anteriores
            'target' => fetch_character($db, $targetId),
            'movements' => fetch_movements($db, $sourceId),
        ]);
    }

    if ($action === 'movement') {
        $charId = clean_text($body['character_id'] ?? '', 100);
        $type = clean_text($body['type'] ?? '', 20);
        if (!in_array($type, ['add', 'spend', 'exchange', 'initial', 'correction'], true)) {
            respond(['ok' => false, 'error' => 'Tipo de movimiento inválido.'], 400);
        }

        $deltaIn = $body['delta'] ?? [];
        if (!is_array($deltaIn)) respond(['ok' => false, 'error' => 'Cantidad de monedas inválida.'], 400);
        $delta = [];
        foreach (COINS as $coin) {
            $value = $deltaIn[$coin] ?? 0;
            if (!is_int($value) || abs($value) > MAX_COIN_VALUE) {
                respond(['ok' => false, 'error' => 'Cantidad de monedas inválida.'], 400);
            }
            $delta[$coin] = $value;
        }

        if ($type === 'add' && min($delta) < 0) respond(['ok' => false, 'error' => 'Movimiento de ingreso inválido.'], 400);
        // Un gasto puede incluir cambio automático: por ejemplo -1 PO +9 PL +9 PC
        // equivale a gastar 1 PC. Validamos el valor total, no cada denominación aislada.
        $deltaCopperValue = $delta['ppt'] * 1000 + $delta['po'] * 100 + $delta['pl'] * 10 + $delta['pc'];
        if ($type === 'spend' && $deltaCopperValue >= 0) {
            respond(['ok' => false, 'error' => 'Movimiento de gasto inválido.'], 400);
        }
        if ($type === 'exchange' && $deltaCopperValue !== 0) {
            respond(['ok' => false, 'error' => 'El cambio no conserva el valor.'], 400);
        }

        $reason = clean_text($body['reason'] ?? '', 255);
        $db->begin_transaction();
        try {
            $character = fetch_character($db, $charId, true);
            if (!$character || !can_access_character($user, $character)) {
                throw new DomainException('Personaje no autorizado.');
            }

            $newCoins = [];
            foreach (COINS as $coin) {
                $new = (int)($character['coins'][$coin] ?? 0) + $delta[$coin];
                if ($new < 0 || $new > MAX_COIN_VALUE) {
                    throw new DomainException('El saldo no puede quedar negativo ni superar el límite permitido.');
                }
                $newCoins[$coin] = $new;
            }

            $stmt = $db->prepare('UPDATE Personaje SET Platino=?, Oro=?, Plata=?, Cobre=? WHERE ID=?');
            $stmt->bind_param('iiiis', $newCoins['ppt'], $newCoins['po'], $newCoins['pl'], $newCoins['pc'], $charId);
            $stmt->execute();
            $stmt->close();

            $movementId = bin2hex(random_bytes(12));
            $date = gmdate('Y-m-d H:i:s') . '.000';
            $author = clean_text((string)$user['displayName'], 100);
            $stmt = $db->prepare(
                'INSERT INTO Movimiento (ID, Personaje, Fecha, Tipo, DeltaPlatino, DeltaOro, DeltaPlata, DeltaCobre, Motivo, Autor, SaldoPlatino, SaldoOro, SaldoPlata, SaldoCobre) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'ssssiiiissiiii',
                $movementId,
                $charId,
                $date,
                $type,
                $delta['ppt'],
                $delta['po'],
                $delta['pl'],
                $delta['pc'],
                $reason,
                $author,
                $newCoins['ppt'],
                $newCoins['po'],
                $newCoins['pl'],
                $newCoins['pc']
            );
            $stmt->execute();
            $stmt->close();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $updated = fetch_character($db, $charId);
        $movements = fetch_movements($db, $charId);
        $movement = end($movements) ?: null;
        respond(['ok' => true, 'character' => $updated, 'movement' => $movement, 'movements' => $movements]);
    }

    respond(['ok' => false, 'error' => 'Acción desconocida.'], 404);
} catch (DomainException $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 403);
} catch (Throwable $e) {
    error_log('Banca Per-Favore MySQL: ' . $e->getMessage());
    respond(['ok' => false, 'error' => 'No se pudo completar la operación en la base de datos.'], 500);
}
