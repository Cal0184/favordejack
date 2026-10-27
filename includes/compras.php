<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Compras reales: cuando el DM aprueba un pedido, se cobra en la Banca y los
 * objetos entran al Inventario del personaje, todo en una sola transacción.
 * Si después el pedido se rechaza o cancela, el oro vuelve (como ingreso) y se
 * quitan del Inventario los objetos que entraron con esa compra.
 *
 * El cobro queda guardado en el pedido (charge) para poder devolverlo:
 *   { at, by, copper, movement, items: [IDs de InventarioItem] }
 */

/** Valor en cobre de cada moneda, de menor a mayor. */
const FAVOR_COIN_COPPER = ['pc' => 1, 'pl' => 10, 'po' => 100, 'ppt' => 1000];
const FAVOR_COIN_COLUMNS = ['ppt' => 'Platino', 'po' => 'Oro', 'pl' => 'Plata', 'pc' => 'Cobre'];
/** Estados de un pedido que ya están cobrados. */
const FAVOR_ORDER_CHARGED_STATES = ['aprobado', 'entregado'];

function favor_coins_copper(array $coins): int
{
    $total = 0;
    foreach (FAVOR_COIN_COPPER as $coin => $value) $total += (int)($coins[$coin] ?? 0) * $value;
    return $total;
}

/** «1.234 po 5 pl» a partir de cobre. */
function favor_copper_label(int $copper): string
{
    $po = intdiv($copper, 100);
    $pl = intdiv($copper % 100, 10);
    $pc = $copper % 10;
    $parts = [];
    if ($po) $parts[] = number_format($po, 0, ',', '.') . ' po';
    if ($pl) $parts[] = $pl . ' pl';
    if ($pc) $parts[] = $pc . ' pc';
    return $parts ? implode(' ', $parts) : '0 po';
}

/**
 * Paga $cost cobre con las monedas de la bolsa, como haría el jugador: primero
 * las monedas grandes que caben justas y, si falta, rompe la moneda más chica
 * que alcance y recibe el vuelto en monedas menores. Devuelve la bolsa nueva.
 */
function favor_coins_pay(array $coins, int $cost): array
{
    if (favor_coins_copper($coins) < $cost) {
        throw new RuntimeException('No le alcanza: tiene ' . favor_copper_label(favor_coins_copper($coins)) . ' y hacen falta ' . favor_copper_label($cost) . '.');
    }
    $left = [];
    foreach (FAVOR_COIN_COPPER as $coin => $value) $left[$coin] = max(0, (int)($coins[$coin] ?? 0));
    $rest = $cost;
    foreach (array_reverse(FAVOR_COIN_COPPER, true) as $coin => $value) {
        $take = min($left[$coin], intdiv($rest, $value));
        $left[$coin] -= $take;
        $rest -= $take * $value;
    }
    if ($rest > 0) {
        // Queda una moneda que vale más que lo que falta: se rompe y vuelve el cambio.
        foreach (FAVOR_COIN_COPPER as $coin => $value) {
            if ($left[$coin] > 0 && $value > $rest) {
                $left[$coin]--;
                $change = $value - $rest;
                foreach (array_reverse(FAVOR_COIN_COPPER, true) as $small => $smallValue) {
                    if ($smallValue >= $value) continue;
                    $left[$small] += intdiv($change, $smallValue);
                    $change %= $smallValue;
                }
                $rest = 0;
                break;
            }
        }
    }
    if ($rest > 0) throw new RuntimeException('No pude armar el pago con las monedas de la bolsa.');
    return $left;
}

/** Inserta un movimiento en la Banca y deja el saldo nuevo en Personaje. */
function favor_bank_movement(mysqli $db, string $charId, array $old, array $new, string $type, string $reason, string $author): string
{
    $stmt = $db->prepare('UPDATE Personaje SET Platino=?, Oro=?, Plata=?, Cobre=? WHERE ID=?');
    $stmt->bind_param('iiiis', $new['ppt'], $new['po'], $new['pl'], $new['pc'], $charId);
    $stmt->execute();
    $stmt->close();

    $id = bin2hex(random_bytes(12));
    $date = gmdate('Y-m-d H:i:s') . '.000';
    $d = [];
    foreach (FAVOR_COIN_COPPER as $coin => $value) $d[$coin] = (int)$new[$coin] - (int)($old[$coin] ?? 0);
    $reason = mb_substr($reason, 0, 255, 'UTF-8');
    $author = mb_substr($author, 0, 100, 'UTF-8');
    $stmt = $db->prepare(
        'INSERT INTO Movimiento (ID, Personaje, Fecha, Tipo, DeltaPlatino, DeltaOro, DeltaPlata, DeltaCobre, Motivo, Autor, SaldoPlatino, SaldoOro, SaldoPlata, SaldoCobre) '
        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('ssssiiiissiiii', $id, $charId, $date, $type, $d['ppt'], $d['po'], $d['pl'], $d['pc'], $reason, $author, $new['ppt'], $new['po'], $new['pl'], $new['pc']);
    $stmt->execute();
    $stmt->close();
    return $id;
}

/** Bolsa del personaje bloqueada para la transacción. */
function favor_bank_lock(mysqli $db, string $charId): array
{
    $stmt = $db->prepare('SELECT Nombre, Platino, Oro, Plata, Cobre, Activo FROM Personaje WHERE ID = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('s', $charId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('El personaje del pedido ya no existe en la Banca.');
    $coins = [];
    foreach (FAVOR_COIN_COLUMNS as $coin => $col) $coins[$coin] = (int)$row[$col];
    return $coins;
}

/**
 * Cobra un pedido: descuenta $copper de la Banca y mete $items al Inventario.
 * $items: [{name, category, qty, description, image}]. Devuelve el registro del cobro.
 */
function favor_purchase_charge(string $charId, int $copper, array $items, string $reason, string $author, string $notes): array
{
    $db = favor_db();
    $db->begin_transaction();
    try {
        $old = favor_bank_lock($db, $charId);
        $movement = '';
        if ($copper > 0) {
            $new = favor_coins_pay($old, $copper);
            $movement = favor_bank_movement($db, $charId, $old, $new, 'spend', $reason, $author);
        }
        $ids = [];
        $st = $db->prepare('INSERT INTO InventarioItem (PersonajeID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,CreadoPor) VALUES (?,?,?,?,0,?,?,?,?)');
        foreach ($items as $it) {
            $name = mb_substr((string)$it['name'], 0, 160, 'UTF-8');
            $category = mb_substr((string)($it['category'] ?? '') ?: 'Objeto', 0, 80, 'UTF-8');
            $qty = max(1, (int)($it['qty'] ?? 1));
            $description = (string)($it['description'] ?? '');
            $image = (string)($it['image'] ?? '');
            $by = mb_substr($author, 0, 50, 'UTF-8');
            $st->bind_param('sssissss', $charId, $name, $category, $qty, $description, $notes, $image, $by);
            $st->execute();
            $ids[] = (int)$db->insert_id;
        }
        $st->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
    return ['at' => date('c'), 'by' => $author, 'copper' => $copper, 'movement' => $movement, 'items' => $ids];
}

/**
 * Deshace un cobro: el oro vuelve como ingreso (en po, pl y pc) y se quitan los
 * objetos de esa compra que sigan en el Inventario. Devuelve cuántos se quitaron.
 */
function favor_purchase_refund(string $charId, array $charge, string $reason, string $author): int
{
    $copper = max(0, (int)($charge['copper'] ?? 0));
    $ids = array_values(array_filter(array_map('intval', (array)($charge['items'] ?? []))));
    $db = favor_db();
    $db->begin_transaction();
    try {
        $old = favor_bank_lock($db, $charId);
        if ($copper > 0) {
            $new = $old;
            $new['po'] += intdiv($copper, 100);
            $new['pl'] += intdiv($copper % 100, 10);
            $new['pc'] += $copper % 10;
            favor_bank_movement($db, $charId, $old, $new, 'add', $reason, $author);
        }
        $removed = 0;
        if ($ids) {
            $st = $db->prepare('DELETE FROM InventarioItem WHERE ID = ? AND PersonajeID = ?');
            foreach ($ids as $id) {
                $st->bind_param('is', $id, $charId);
                $st->execute();
                $removed += $st->affected_rows;
            }
            $st->close();
        }
        $db->commit();
        return $removed;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
}

/**
 * El DM da (o quita) la misma cantidad a varios personajes, cada uno con su
 * movimiento en la Banca. Devuelve [nombre => '' si salió bien, o el motivo].
 */
function favor_bank_adjust(array $charIds, int $copper, bool $give, string $reason, string $author): array
{
    $results = [];
    $db = favor_db();
    try {
        foreach (array_unique($charIds) as $charId) {
            $charId = (string)$charId;
            $db->begin_transaction();
            try {
                $old = favor_bank_lock($db, $charId);
                if ($give) {
                    $new = $old;
                    $new['po'] += intdiv($copper, 100);
                    $new['pl'] += intdiv($copper % 100, 10);
                    $new['pc'] += $copper % 10;
                } else {
                    $new = favor_coins_pay($old, $copper);
                }
                favor_bank_movement($db, $charId, $old, $new, $give ? 'add' : 'spend', $reason, $author);
                $db->commit();
                $results[$charId] = '';
            } catch (Throwable $e) {
                $db->rollback();
                $results[$charId] = $e instanceof RuntimeException ? $e->getMessage() : 'Error de la base de datos.';
            }
        }
    } finally {
        $db->close();
    }
    return $results;
}

/** Últimos movimientos de la Banca, con el nombre del personaje. */
function favor_bank_recent(int $limit = 25): array
{
    $db = favor_db();
    try {
        $stmt = $db->prepare(
            'SELECT m.Fecha, m.Tipo, m.DeltaPlatino, m.DeltaOro, m.DeltaPlata, m.DeltaCobre, m.Motivo, m.Autor, p.Nombre '
            . 'FROM Movimiento m LEFT JOIN Personaje p ON p.ID = m.Personaje ORDER BY m.Fecha DESC LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(static fn(array $r): array => [
            'at' => (string)$r['Fecha'],
            'type' => (string)$r['Tipo'],
            'copper' => (int)$r['DeltaPlatino'] * 1000 + (int)$r['DeltaOro'] * 100 + (int)$r['DeltaPlata'] * 10 + (int)$r['DeltaCobre'],
            'reason' => (string)$r['Motivo'],
            'author' => (string)$r['Autor'],
            'character' => (string)($r['Nombre'] ?? '¿?'),
        ], $rows);
    } finally {
        $db->close();
    }
}
