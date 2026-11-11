<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/compras.php';
require_once __DIR__ . '/estado.php';
require_once __DIR__ . '/migrations.php';

/*
 * Recompensas de misiones: cuando una misión pasa a «Completada», cada
 * participante recibe en la Banca la recompensa en monedas de la misión.
 *
 * La recompensa en monedas son tres columnas de Mision (el texto libre
 * RecompensaGeneral sigue igual y nunca se interpreta):
 *   RecompensaCantidad  cantidad (NULL = sin recompensa en monedas)
 *   RecompensaMoneda    po | pl | pc
 *   RecompensaModo      personaje (cada uno recibe la cantidad) | total (se reparte)
 *
 * Cada pago queda en MisionPago, con una sola fila por misión y personaje
 * (clave única): guardar otra vez la misión, o completarla de nuevo, no paga
 * dos veces. Si la misión cambia después, lo pagado no se toca: el Panel del DM
 * enseña lo entregado y los participantes pendientes para revisarlo a mano.
 */

const FAVOR_RECOMPENSA_MONEDAS = ['po' => 'PO', 'pl' => 'PL', 'pc' => 'PC'];
const FAVOR_RECOMPENSA_MODOS = ['personaje' => 'por personaje', 'total' => 'total a repartir'];
const FAVOR_RECOMPENSA_MAX = 10000000;

/** Columnas y tabla de las recompensas (se crean solas, una vez). */
function favor_recompensas_schema(mysqli $db): void
{
    static $done = false;
    if ($done) return;
    favor_ensure_schema($db);
    $flag = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='recompensas_v1' LIMIT 1");
    $ok = $flag && $flag->fetch_assoc();
    if ($flag) $flag->free();
    if ($ok) { $done = true; return; }

    $existing = [];
    $res = $db->query('SHOW COLUMNS FROM Mision');
    while ($row = $res->fetch_assoc()) $existing[(string)$row['Field']] = true;
    $res->free();
    $columns = [
        'RecompensaCantidad' => 'int unsigned NULL DEFAULT NULL',
        'RecompensaMoneda' => "varchar(2) NOT NULL DEFAULT 'po'",
        'RecompensaModo' => "varchar(10) NOT NULL DEFAULT 'personaje'",
    ];
    foreach ($columns as $name => $definition) {
        if (!isset($existing[$name])) $db->query("ALTER TABLE Mision ADD COLUMN `$name` $definition");
    }
    // Sin clave foránea a Mision a propósito: si alguien borra la misión, el
    // registro del pago se conserva junto al movimiento de la Banca.
    $db->query(
        'CREATE TABLE IF NOT EXISTS MisionPago ('
        . ' ID int unsigned NOT NULL AUTO_INCREMENT,'
        . ' MisionID int unsigned NOT NULL,'
        . ' MisionNombre varchar(160) NOT NULL DEFAULT \'\','
        . ' PersonajeID varchar(100) NOT NULL,'
        . ' MovimientoID varchar(100) NOT NULL,'
        . ' Cobre int unsigned NOT NULL,'
        . ' Detalle varchar(60) NOT NULL DEFAULT \'\','
        . ' Fecha datetime NOT NULL DEFAULT current_timestamp(),'
        . ' Autor varchar(100) NOT NULL DEFAULT \'\','
        . ' PRIMARY KEY (ID),'
        . ' UNIQUE KEY uq_misionpago (MisionID, PersonajeID),'
        . ' KEY idx_misionpago_personaje (PersonajeID),'
        . ' KEY idx_misionpago_movimiento (MovimientoID)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('recompensas_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
    $done = true;
}

/** Lee rewardAmount / rewardCoin / rewardMode de un formulario. */
function favor_recompensa_desde(array $data): array
{
    $raw = trim((string)($data['rewardAmount'] ?? ''));
    $amount = null;
    if ($raw !== '') {
        if (!preg_match('/^\d{1,8}$/', $raw)) throw new RuntimeException('La recompensa en monedas debe ser un número entero (o déjala vacía).');
        $amount = (int)$raw;
        if ($amount > FAVOR_RECOMPENSA_MAX) throw new RuntimeException('Esa recompensa es demasiado grande.');
        if ($amount === 0) $amount = null;
    }
    $coin = (string)($data['rewardCoin'] ?? 'po');
    if (!isset(FAVOR_RECOMPENSA_MONEDAS[$coin])) $coin = 'po';
    $mode = (string)($data['rewardMode'] ?? 'personaje');
    if (!isset(FAVOR_RECOMPENSA_MODOS[$mode])) $mode = 'personaje';
    return ['amount' => $amount, 'coin' => $coin, 'mode' => $mode];
}

function favor_recompensa_guardar(mysqli $db, int $missionId, array $reward): void
{
    $stmt = $db->prepare('UPDATE Mision SET RecompensaCantidad=?, RecompensaMoneda=?, RecompensaModo=? WHERE ID=?');
    $amount = $reward['amount'];
    $stmt->bind_param('issi', $amount, $reward['coin'], $reward['mode'], $missionId);
    $stmt->execute();
    $stmt->close();
}

/** «200 PO por personaje» */
function favor_recompensa_texto(?int $amount, string $coin, string $mode): string
{
    if (!$amount) return '';
    return number_format($amount, 0, ',', '.') . ' ' . (FAVOR_RECOMPENSA_MONEDAS[$coin] ?? 'PO') . ' ' . (FAVOR_RECOMPENSA_MODOS[$mode] ?? '');
}

/** Cobre → monedas de la Banca (PO, PL, PC; el platino ya no se reparte). */
function favor_recompensa_monedas(int $copper): array
{
    return ['ppt' => 0, 'po' => intdiv($copper, 100), 'pl' => intdiv($copper % 100, 10), 'pc' => $copper % 10];
}

/**
 * Paga la recompensa de una misión completada a los participantes que aún no la
 * recibieron. Todo en una transacción: o se pagan todos (saldo + movimiento +
 * registro), o ninguno. Nunca adivina: sin cantidad, sin participantes o con un
 * total ya repartido, devuelve un aviso y no paga.
 *
 * @return array{paid: list<array{id:string,name:string,copper:int}>, warnings: list<string>}
 */
function favor_recompensa_pagar(mysqli $db, int $missionId, string $author): array
{
    favor_recompensas_schema($db);
    $out = ['paid' => [], 'warnings' => []];

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT ID, Nombre, Estado, RecompensaCantidad, RecompensaMoneda, RecompensaModo FROM Mision WHERE ID=? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $missionId);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$m) throw new RuntimeException('La misión ya no existe.');
        $name = (string)$m['Nombre'];

        if ((string)$m['Estado'] !== 'Completada') {
            $db->rollback();
            $out['warnings'][] = 'La misión no está completada: no se pagó nada.';
            return $out;
        }
        $amount = $m['RecompensaCantidad'] !== null ? (int)$m['RecompensaCantidad'] : 0;
        if ($amount <= 0) {
            $db->rollback();
            $out['warnings'][] = '«' . $name . '» no tiene recompensa en monedas: no se pagó nada. Si corresponde, pon la cantidad en la misión y paga desde el Panel del DM.';
            return $out;
        }
        $coin = isset(FAVOR_RECOMPENSA_MONEDAS[(string)$m['RecompensaMoneda']]) ? (string)$m['RecompensaMoneda'] : 'po';
        $mode = (string)$m['RecompensaModo'] === 'total' ? 'total' : 'personaje';

        // Participantes y pagos ya hechos.
        $stmt = $db->prepare('SELECT mp.PersonajeID, COALESCE(NULLIF(mp.NombreSnapshot,\'\'), p.Nombre) AS Nombre, p.ID AS Existe FROM MisionPersonaje mp LEFT JOIN Personaje p ON p.ID = mp.PersonajeID WHERE mp.MisionID=? ORDER BY mp.PersonajeID');
        $stmt->bind_param('i', $missionId);
        $stmt->execute();
        $party = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $stmt = $db->prepare('SELECT PersonajeID FROM MisionPago WHERE MisionID=?');
        $stmt->bind_param('i', $missionId);
        $stmt->execute();
        $paid = [];
        foreach ($stmt->get_result()->fetch_all() as $r) $paid[(string)$r[0]] = true;
        $stmt->close();

        $npc = favor_npc_ids($db);
        $pending = [];
        foreach ($party as $p) {
            $id = (string)$p['PersonajeID'];
            if (isset($paid[$id])) continue;
            if ($p['Existe'] === null) { $out['warnings'][] = (string)$p['Nombre'] . ' ya no existe en la Banca: no se le pagó.'; continue; }
            if (isset($npc[$id])) { $out['warnings'][] = (string)$p['Nombre'] . ' es NPC (no usa la Banca): no se le pagó.'; continue; }
            $pending[$id] = (string)$p['Nombre'];
        }
        if (!$party) $out['warnings'][] = '«' . $name . '» no tiene participantes: no se pagó nada.';
        if (!$pending) { $db->rollback(); return $out; }

        $copper = $amount * FAVOR_COIN_COPPER[$coin];
        if ($mode === 'total') {
            if ($paid) {
                $db->rollback();
                $out['warnings'][] = 'El total de «' . $name . '» ya se repartió entre ' . count($paid) . ' personajes; los que se sumaron después (' . implode(', ', $pending) . ') hay que revisarlos a mano.';
                return $out;
            }
            $share = intdiv($copper, count($pending));
            $left = $copper - $share * count($pending);
            if ($share <= 0) {
                $db->rollback();
                $out['warnings'][] = 'El total de «' . $name . '» no alcanza para repartir entre ' . count($pending) . ' personajes: no se pagó nada.';
                return $out;
            }
            if ($left > 0) $out['warnings'][] = 'Al repartir quedaron ' . favor_copper_label($left) . ' sin repartir (no se pueden partir monedas de cobre).';
            $delta = favor_recompensa_monedas($share);
            $detail = favor_copper_label($share) . ' (parte de ' . favor_recompensa_texto($amount, $coin, 'total') . ')';
        } else {
            $delta = ['ppt' => 0, 'po' => 0, 'pl' => 0, 'pc' => 0];
            $delta[$coin] = $amount;
            $share = $copper;
            $detail = favor_recompensa_texto($amount, $coin, 'personaje');
        }

        $reason = 'Recompensa de misión: ' . $name . ' (#' . $missionId . ')';
        $log = $db->prepare('INSERT INTO MisionPago (MisionID, MisionNombre, PersonajeID, MovimientoID, Cobre, Detalle, Autor) VALUES (?,?,?,?,?,?,?)');
        foreach ($pending as $charId => $charName) {
            $old = favor_bank_lock($db, $charId);
            $new = $old;
            foreach ($delta as $c => $v) {
                $new[$c] = (int)$old[$c] + $v;
                if ($new[$c] > 1000000000) throw new RuntimeException('El saldo de ' . $charName . ' superaría el límite de la Banca.');
            }
            $movement = favor_bank_movement($db, $charId, $old, $new, 'add', $reason, $author);
            $detailCut = mb_substr($detail, 0, 60, 'UTF-8');
            $authorCut = mb_substr($author, 0, 100, 'UTF-8');
            $nameCut = mb_substr($name, 0, 160, 'UTF-8');
            $log->bind_param('isssiss', $missionId, $nameCut, $charId, $movement, $share, $detailCut, $authorCut);
            $log->execute();
            $out['paid'][] = ['id' => $charId, 'name' => $charName, 'copper' => $share];
        }
        $log->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $out;
}

/** Texto corto para avisar al admin de lo que pasó con el pago. */
function favor_recompensa_resumen(array $result): string
{
    $parts = [];
    if ($result['paid']) {
        $names = array_map(static fn(array $p): string => $p['name'], $result['paid']);
        $parts[] = 'Recompensa pagada: ' . favor_copper_label((int)$result['paid'][0]['copper']) . ' a ' . implode(', ', $names) . '.';
    }
    foreach ($result['warnings'] as $w) $parts[] = $w;
    return implode(' ', $parts);
}

/**
 * Paga sin romper el guardado de la misión: si el pago falla, la misión queda
 * guardada sin pagar (nada a medias) y el admin lo ve en el aviso y en el Panel.
 */
function favor_recompensa_pagar_seguro(mysqli $db, int $missionId, string $author): string
{
    try {
        return favor_recompensa_resumen(favor_recompensa_pagar($db, $missionId, $author));
    } catch (Throwable $e) {
        error_log('Recompensa misión ' . $missionId . ': ' . $e->getMessage());
        return 'No se pudo pagar la recompensa (no se movió dinero): ' . $e->getMessage() . ' Puedes reintentarlo desde el Panel del DM.';
    }
}

/** Misiones completadas con su recompensa, lo pagado y lo pendiente (Panel del DM). */
function favor_recompensas_revision(mysqli $db, int $limit = 15): array
{
    favor_recompensas_schema($db);
    $rows = $db->query("SELECT ID, Nombre, Fecha, RecompensaGeneral, RecompensaCantidad, RecompensaMoneda, RecompensaModo FROM Mision WHERE Estado='Completada' ORDER BY COALESCE(Fecha, DATE(Actualizado)) DESC, ID DESC LIMIT " . max(1, min(100, $limit)))->fetch_all(MYSQLI_ASSOC);
    $partyStmt = $db->prepare('SELECT mp.PersonajeID, COALESCE(NULLIF(mp.NombreSnapshot,\'\'), p.Nombre, mp.PersonajeID) AS Nombre FROM MisionPersonaje mp LEFT JOIN Personaje p ON p.ID=mp.PersonajeID WHERE mp.MisionID=?');
    $paidStmt = $db->prepare('SELECT mg.PersonajeID, COALESCE(p.Nombre, mg.PersonajeID) AS Nombre, mg.Cobre, mg.Detalle, mg.Fecha, mg.Autor FROM MisionPago mg LEFT JOIN Personaje p ON p.ID=mg.PersonajeID WHERE mg.MisionID=? ORDER BY mg.Fecha, mg.ID');
    $npc = favor_npc_ids($db);
    $out = [];
    foreach ($rows as $r) {
        $id = (int)$r['ID'];
        $partyStmt->bind_param('i', $id);
        $partyStmt->execute();
        $party = $partyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $paidStmt->bind_param('i', $id);
        $paidStmt->execute();
        $paid = $paidStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $paidIds = array_flip(array_map(static fn(array $p): string => (string)$p['PersonajeID'], $paid));
        $pending = [];
        foreach ($party as $p) {
            if (!isset($paidIds[(string)$p['PersonajeID']]) && !isset($npc[(string)$p['PersonajeID']])) $pending[] = (string)$p['Nombre'];
        }
        $amount = $r['RecompensaCantidad'] !== null ? (int)$r['RecompensaCantidad'] : 0;
        $out[] = [
            'id' => $id,
            'name' => (string)$r['Nombre'],
            'date' => (string)($r['Fecha'] ?? ''),
            'text' => (string)$r['RecompensaGeneral'],
            'amount' => $amount,
            'reward' => favor_recompensa_texto($amount ?: null, (string)$r['RecompensaMoneda'], (string)$r['RecompensaModo']),
            'mode' => (string)$r['RecompensaModo'],
            'party' => count($party),
            'paid' => $paid,
            'pending' => $pending,
        ];
    }
    $partyStmt->close();
    $paidStmt->close();
    return $out;
}
