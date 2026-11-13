<?php
declare(strict_types=1);

require_once __DIR__ . '/tiendas.php';
require_once __DIR__ . '/assets.php';

/*
 * Tienda del Gremio (Per-Favore) · Lady Gena.
 *
 * Todo lo que vende el gremio sale de su Almacén (tabla GremioAlmacen), con
 * existencias, precio de venta y destino:
 *   venta     → se ve en la tienda y cualquier aventurero lo puede comprar
 *   reservado → el gremio lo guarda para sí; no aparece en la tienda
 * El catálogo inicial (tiendas/gremio/catalogo.json, sacado del documento de la
 * tienda) se carga en el Almacén la primera vez.
 *
 * Los aventureros también le venden objetos mágicos al gremio:
 *   - si el DM fijó un precio de compra para ese objeto (GremioTarifa), la venta
 *     es inmediata al confirmar;
 *   - si no, se manda a valoración (GremioOferta); el DM pone el precio y el
 *     jugador acepta o rechaza.
 * Cada compra y venta es una sola transacción de la base de datos que mueve el
 * oro en la Banca (Movimiento) y el objeto entre InventarioItem y el Almacén,
 * con las filas bloqueadas (FOR UPDATE): si algo falla no se mueve nada, y el
 * mismo objeto no se puede vender ni comprar dos veces. Todo queda en
 * GremioRegistro.
 */

const FAVOR_GREMIO_SHOP = 'tienda-del-gremio';
const FAVOR_GREMIO_NAME = 'Tienda del Gremio';
const FAVOR_GREMIO_DIR = 'tiendas/gremio/';
const FAVOR_GREMIO_CATALOG = __DIR__ . '/../tiendas/gremio/catalogo.json';
/** Mismo criterio que el Inventario para «Objeto mágico» (inventario/assets/inventory.js). */
const FAVOR_GREMIO_MAGIC_RE = '/(objeto\s*m[aá]gico|m[aá]gic|encantad|poci[oó]n|pergamino|varita|bast[oó]n|orbe|reliquia|anillo|amuleto|grimorio|libro de conjuros|\+\d)/iu';
const FAVOR_GREMIO_MAX_COPPER = 100000000;

function favor_gremio_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) return;
    $db->query("CREATE TABLE IF NOT EXISTS GremioAlmacen (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Nombre varchar(160) NOT NULL,
        Categoria varchar(80) NOT NULL DEFAULT 'Objeto mágico',
        Subcategoria varchar(80) NOT NULL DEFAULT '',
        Descripcion text NULL,
        Vinculacion varchar(80) NOT NULL DEFAULT '',
        ImageUrl varchar(500) NULL,
        Destino enum('venta','reservado') NOT NULL DEFAULT 'reservado',
        Existencias int unsigned NOT NULL DEFAULT 1,
        PrecioVenta int unsigned NULL,
        Origen enum('catalogo','aventurero','admin') NOT NULL DEFAULT 'admin',
        CatalogoID varchar(80) NULL,
        ProcedenciaPersonaje varchar(100) NULL,
        ProcedenciaNombre varchar(100) NOT NULL DEFAULT '',
        PrecioPagado int unsigned NULL,
        Revisar varchar(255) NOT NULL DEFAULT '',
        Nota text NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_gremio_catalogo (CatalogoID),
        INDEX idx_gremio_destino (Destino)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS GremioTarifa (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Clave varchar(160) NOT NULL,
        Nombre varchar(160) NOT NULL,
        PrecioCompra int unsigned NOT NULL,
        Nota varchar(255) NOT NULL DEFAULT '',
        ActualizadoPor varchar(100) NOT NULL DEFAULT '',
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_gremio_tarifa (Clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS GremioOferta (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        PersonajeID varchar(100) NOT NULL,
        PersonajeNombre varchar(100) NOT NULL DEFAULT '',
        InventarioID int unsigned NOT NULL,
        Nombre varchar(160) NOT NULL,
        Categoria varchar(80) NOT NULL DEFAULT '',
        Descripcion text NULL,
        Estado enum('pendiente','ofertada','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
        Precio int unsigned NULL,
        NotaJugador varchar(500) NOT NULL DEFAULT '',
        NotaDM varchar(500) NOT NULL DEFAULT '',
        Usuario varchar(100) NOT NULL DEFAULT '',
        AlmacenID int unsigned NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Resuelto datetime NULL,
        INDEX idx_oferta_personaje (PersonajeID),
        INDEX idx_oferta_estado (Estado),
        INDEX idx_oferta_inventario (InventarioID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS GremioRegistro (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Tipo enum('compra','venta','anulacion','valoracion','almacen') NOT NULL,
        PersonajeID varchar(100) NULL,
        PersonajeNombre varchar(100) NOT NULL DEFAULT '',
        AlmacenID int unsigned NULL,
        Objeto varchar(160) NOT NULL DEFAULT '',
        Cantidad int unsigned NOT NULL DEFAULT 1,
        Cobre int NOT NULL DEFAULT 0,
        Movimiento varchar(100) NOT NULL DEFAULT '',
        InventarioID int unsigned NULL,
        Usuario varchar(100) NOT NULL DEFAULT '',
        Detalle varchar(500) NOT NULL DEFAULT '',
        Anulado tinyint(1) NOT NULL DEFAULT 0,
        INDEX idx_registro_fecha (Fecha),
        INDEX idx_registro_personaje (PersonajeID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    favor_gremio_seed($db);
    $done = true;
}

/** Carga una sola vez el catálogo del documento en el Almacén (no pisa cambios del DM). */
function favor_gremio_seed(mysqli $db): void
{
    $stmt = $db->prepare("SELECT 1 FROM FavorMeta WHERE Clave = 'gremio_catalogo_v1' LIMIT 1");
    $stmt->execute();
    $seeded = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($seeded) return;

    $db->begin_transaction();
    try {
        $st = $db->prepare("INSERT IGNORE INTO GremioAlmacen (Nombre,Categoria,Subcategoria,Descripcion,Vinculacion,ImageUrl,Destino,Existencias,PrecioVenta,Origen,CatalogoID,ProcedenciaNombre,Revisar) VALUES (?,?,?,?,?,?,'venta',?,?,'catalogo',?,'Catálogo inicial del gremio',?)");
        foreach (favor_gremio_catalog() as $item) {
            $name = $item['name'];
            $cat = $item['category'];
            $sub = $item['subcategory'];
            $desc = $item['effect'];
            $att = $item['attunement'];
            $img = $item['image'];
            $stock = $item['stock'];
            $price = $item['copper'];
            $cid = $item['id'];
            $review = $item['review'];
            $st->bind_param('ssssssiiss', $name, $cat, $sub, $desc, $att, $img, $stock, $price, $cid, $review);
            $st->execute();
        }
        $st->close();
        $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('gremio_catalogo_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Catálogo del documento «Tienda del Gremio» ya normalizado. */
function favor_gremio_catalog(): array
{
    $raw = json_decode((string)@file_get_contents(FAVOR_GREMIO_CATALOG), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $p) {
        if (!is_array($p) || empty($p['id']) || empty($p['name'])) continue;
        $copper = favor_price_copper((string)($p['price'] ?? ''));
        $image = (string)($p['image'] ?? '');
        $out[] = [
            'id' => (string)$p['id'],
            'name' => (string)$p['name'],
            'category' => (string)($p['category'] ?? 'Objeto mágico'),
            'subcategory' => (string)($p['subcategory'] ?? ''),
            'effect' => (string)($p['effect'] ?? ''),
            'attunement' => (string)($p['attunement'] ?? ''),
            'image' => $image !== '' ? FAVOR_GREMIO_DIR . $image : '',
            'stock' => max(0, (int)($p['stock'] ?? 1)),
            'copper' => $copper,
            'review' => (string)($p['review'] ?? ''),
        ];
    }
    return $out;
}

function favor_gremio_db(): mysqli
{
    $db = favor_db();
    favor_gremio_ensure($db);
    return $db;
}

function favor_gremio_is_admin(?array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

/** Clave para emparejar nombres de objetos («Anillo de Resistencia al ácido» = «anillo de resistencia al acido»). */
function favor_gremio_key(string $name): string
{
    return mb_substr(favor_discount_name_key($name), 0, 160, 'UTF-8');
}

/** «450 po», «12,5» o «450» → cobre. null si no es un precio válido. */
function favor_gremio_parse_price(mixed $value): ?int
{
    if (is_int($value)) $copper = $value * 100;
    else {
        $text = trim((string)$value);
        if ($text === '') return null;
        if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $text)) $copper = (int)round((float)str_replace(',', '.', $text) * 100);
        else $copper = favor_price_copper($text);
    }
    if ($copper === null || $copper <= 0 || $copper > FAVOR_GREMIO_MAX_COPPER) return null;
    return $copper;
}

/** Personaje con el que actúa la persona: uno suyo (el admin, cualquiera). */
function favor_gremio_character(array $user, string $characterId): array
{
    $chars = favor_discount_characters(favor_gremio_is_admin($user) ? null : (string)$user['playerId']);
    if (!isset($chars[$characterId])) throw new RuntimeException('Elige uno de tus personajes.');
    return $chars[$characterId];
}

function favor_gremio_author(array $user): string
{
    return mb_substr((string)($user['displayName'] ?? $user['username'] ?? 'Gremio'), 0, 100, 'UTF-8');
}

function favor_gremio_log(mysqli $db, array $row): int
{
    $tipo = (string)$row['tipo'];
    $char = isset($row['char']) ? (string)$row['char'] : null;
    $charName = mb_substr((string)($row['charName'] ?? ''), 0, 100, 'UTF-8');
    $almacen = isset($row['almacen']) ? (int)$row['almacen'] : null;
    $obj = mb_substr((string)($row['objeto'] ?? ''), 0, 160, 'UTF-8');
    $qty = max(1, (int)($row['qty'] ?? 1));
    $copper = (int)($row['copper'] ?? 0);
    $mov = (string)($row['mov'] ?? '');
    $inv = isset($row['inv']) ? (int)$row['inv'] : null;
    $usr = mb_substr((string)($row['user'] ?? ''), 0, 100, 'UTF-8');
    $det = mb_substr((string)($row['detalle'] ?? ''), 0, 500, 'UTF-8');
    $st = $db->prepare('INSERT INTO GremioRegistro (Tipo,PersonajeID,PersonajeNombre,AlmacenID,Objeto,Cantidad,Cobre,Movimiento,InventarioID,Usuario,Detalle) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $st->bind_param('sssisiisiss', $tipo, $char, $charName, $almacen, $obj, $qty, $copper, $mov, $inv, $usr, $det);
    $st->execute();
    $id = (int)$db->insert_id;
    $st->close();
    return $id;
}

/** Suma $copper a la bolsa en po, pl y pc (como los reembolsos de las tiendas). */
function favor_gremio_coins_add(array $coins, int $copper): array
{
    $coins['po'] += intdiv($copper, 100);
    $coins['pl'] += intdiv($copper % 100, 10);
    $coins['pc'] += $copper % 10;
    return $coins;
}

/* ------------------------------------------------------------------
 * Catálogo a la venta
 * ------------------------------------------------------------------ */

function favor_gremio_product(array $r): array
{
    $copper = $r['PrecioVenta'] !== null ? (int)$r['PrecioVenta'] : null;
    $stock = (int)$r['Existencias'];
    return [
        'id' => 'g' . (int)$r['ID'],
        'name' => (string)$r['Nombre'],
        'category' => (string)$r['Categoria'],
        'subcategory' => (string)$r['Subcategoria'],
        'effect' => (string)($r['Descripcion'] ?? ''),
        'attunement' => (string)$r['Vinculacion'],
        'price' => $copper !== null ? favor_copper_label($copper) : '',
        'copper' => $copper,
        'stock' => $stock,
        'available' => $stock > 0 && $copper !== null,
        'image' => (string)($r['ImageUrl'] ?? '') !== '' ? favor_url((string)$r['ImageUrl']) : '',
        'origin' => (string)$r['Origen'],
        'from' => (string)$r['Origen'] === 'aventurero' ? (string)$r['ProcedenciaNombre'] : '',
        'review' => (string)$r['Revisar'],
    ];
}

/** Lo que se ve en «Comprar»: objetos con destino venta (los agotados también, marcados). */
function favor_gremio_products(): array
{
    $db = favor_gremio_db();
    try {
        $rows = $db->query("SELECT * FROM GremioAlmacen WHERE Destino = 'venta' AND PrecioVenta IS NOT NULL ORDER BY Existencias = 0, Origen = 'aventurero', ID")->fetch_all(MYSQLI_ASSOC);
    } finally {
        $db->close();
    }
    return array_map('favor_gremio_product', $rows);
}

/**
 * Un aventurero compra al gremio: cobra en la Banca, entra al Inventario y se
 * descuenta del Almacén, todo junto. El descuento de la tienda (si el DM lo
 * activó) se usa como en las demás tiendas.
 */
function favor_gremio_buy(array $user, string $productId, int $qty, string $characterId, bool $useDiscount): array
{
    if (!preg_match('/^g(\d+)$/', $productId, $m)) throw new RuntimeException('Ese objeto ya no está en la tienda.');
    $almacenId = (int)$m[1];
    $qty = max(1, min(50, $qty));
    $character = favor_gremio_character($user, $characterId);
    $by = favor_gremio_author($user);

    // Precio y existencias se leen del servidor, nunca del navegador.
    $db = favor_gremio_db();
    try {
        $st = $db->prepare("SELECT * FROM GremioAlmacen WHERE ID = ? LIMIT 1");
        $st->bind_param('i', $almacenId);
        $st->execute();
        $item = $st->get_result()->fetch_assoc();
        $st->close();
    } finally {
        $db->close();
    }
    if (!$item || $item['Destino'] !== 'venta' || $item['PrecioVenta'] === null) throw new RuntimeException('Ese objeto ya no está a la venta.');
    if ((int)$item['Existencias'] < $qty) throw new RuntimeException((int)$item['Existencias'] > 0 ? 'Solo quedan ' . (int)$item['Existencias'] . ' en el almacén.' : 'Ese objeto está agotado.');
    $unit = (int)$item['PrecioVenta'];
    $name = (string)$item['Nombre'];
    $copper = $unit * $qty;

    $discount = null;
    $orderId = 'g' . date('ymd') . '-' . bin2hex(random_bytes(3));
    if ($useDiscount) {
        $settings = favor_discount_settings(favor_discounts_load(), FAVOR_GREMIO_SHOP);
        $off = (int)round($unit * $settings['percent'] / 100);
        favor_discount_use(FAVOR_GREMIO_SHOP, $character, ['item' => $name, 'price' => favor_copper_label($unit), 'paid' => favor_discount_coins($unit - $off), 'order' => $orderId], (string)$user['username']);
        $discount = ['percent' => $settings['percent'], 'copper' => $off];
        $copper -= $off;
    }

    $description = trim(implode("\n", array_filter([
        (string)($item['Descripcion'] ?? ''),
        (string)$item['Vinculacion'] !== '' ? 'Vinculación: ' . $item['Vinculacion'] : '',
        (string)$item['Origen'] === 'aventurero' && (string)$item['ProcedenciaNombre'] !== '' ? 'Procedencia: ' . $item['ProcedenciaNombre'] : '',
    ])));
    $notes = 'Comprado en la ' . FAVOR_GREMIO_NAME . ' el ' . date('d-m-Y') . '.';

    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        // Existencias bloqueadas: dos compras a la vez no se llevan la misma unidad.
        $st = $db->prepare("SELECT Existencias, PrecioVenta, Destino FROM GremioAlmacen WHERE ID = ? FOR UPDATE");
        $st->bind_param('i', $almacenId);
        $st->execute();
        $now = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$now || $now['Destino'] !== 'venta' || (int)$now['PrecioVenta'] !== $unit) throw new RuntimeException('El gremio acaba de cambiar este objeto. Recarga la tienda.');
        if ((int)$now['Existencias'] < $qty) throw new RuntimeException('Otro aventurero se llevó el último. Ya no queda en el almacén.');

        $old = favor_bank_lock($db, $character['id']);
        $new = favor_coins_pay($old, $copper);
        $movement = $copper > 0 ? favor_bank_movement($db, $character['id'], $old, $new, 'spend', 'Compra en la ' . FAVOR_GREMIO_NAME . ': ' . ($qty > 1 ? $qty . ' × ' : '') . $name, $by) : '';

        $st = $db->prepare('UPDATE GremioAlmacen SET Existencias = Existencias - ? WHERE ID = ? AND Existencias >= ?');
        $st->bind_param('iii', $qty, $almacenId, $qty);
        $st->execute();
        if ($st->affected_rows !== 1) throw new RuntimeException('No quedan existencias suficientes.');
        $st->close();

        $cat = mb_substr((string)$item['Categoria'] ?: 'Objeto mágico', 0, 80, 'UTF-8');
        $img = (string)($item['ImageUrl'] ?? '');
        $creator = mb_substr($by, 0, 50, 'UTF-8');
        $st = $db->prepare('INSERT INTO InventarioItem (PersonajeID,Nombre,Categoria,Cantidad,Equipado,Descripcion,Notas,ImageUrl,CreadoPor) VALUES (?,?,?,?,0,?,?,?,?)');
        $st->bind_param('sssissss', $character['id'], $name, $cat, $qty, $description, $notes, $img, $creator);
        $st->execute();
        $invId = (int)$db->insert_id;
        $st->close();

        $logId = favor_gremio_log($db, [
            'tipo' => 'compra', 'char' => $character['id'], 'charName' => $character['name'], 'almacen' => $almacenId,
            'objeto' => $name, 'qty' => $qty, 'copper' => $copper, 'mov' => $movement, 'inv' => $invId, 'user' => $by,
            'detalle' => ($discount ? 'Con descuento de ' . $discount['percent'] . ' %. ' : '') . 'Pedido ' . $orderId,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($discount) favor_discount_restore(FAVOR_GREMIO_SHOP, $character, (string)$user['username'], $orderId, 'compra fallida');
        throw $e;
    } finally {
        $db->close();
    }

    favor_discord_send('compras', '🛒 ' . $character['name'] . ' compró en la ' . FAVOR_GREMIO_NAME,
        '• ' . ($qty > 1 ? $qty . ' × ' : '') . $name,
        [['Jugador', $character['playerName']], ['Pagó', favor_copper_label($copper)], ['Descuento', $discount ? $discount['percent'] . ' %' : '']]);

    return [
        'id' => $logId, 'characterName' => $character['name'], 'item' => $name, 'qty' => $qty,
        'copper' => $copper, 'discount' => $discount, 'stock' => (int)$now['Existencias'] - $qty,
    ];
}

/* ------------------------------------------------------------------
 * Vender al gremio
 * ------------------------------------------------------------------ */

function favor_gremio_is_magic(array $row): bool
{
    return (bool)preg_match(FAVOR_GREMIO_MAGIC_RE, (string)$row['Categoria']) || (bool)preg_match(FAVOR_GREMIO_MAGIC_RE, (string)$row['Nombre']);
}

function favor_gremio_needs_attunement(array $row): bool
{
    $text = (string)($row['Descripcion'] ?? '') . ' ' . (string)($row['Notas'] ?? '');
    return (bool)preg_match('/vinculaci[oó]n:\s*requiere|requiere vinculaci[oó]n|vinculad[oa]|sintoniz/iu', $text)
        && !preg_match('/vinculaci[oó]n:\s*no requerida/iu', $text);
}

/** Precios de compra fijados por el DM: [clave => fila]. */
function favor_gremio_rates(mysqli $db): array
{
    $out = [];
    foreach ($db->query('SELECT ID, Clave, Nombre, PrecioCompra, Nota, ActualizadoPor, Actualizado FROM GremioTarifa ORDER BY Nombre')->fetch_all(MYSQLI_ASSOC) as $r) {
        $out[(string)$r['Clave']] = $r;
    }
    return $out;
}

/**
 * Objetos del personaje que se pueden ofrecer al gremio, con la oferta si la
 * hay, y sus solicitudes de valoración. Solo se leen filas de ese personaje.
 */
function favor_gremio_sellables(array $user, string $characterId): array
{
    $character = favor_gremio_character($user, $characterId);
    $db = favor_gremio_db();
    try {
        $st = $db->prepare('SELECT ID, Nombre, Categoria, Cantidad, Equipado, Descripcion, Notas, ImageUrl FROM InventarioItem WHERE PersonajeID = ? ORDER BY Nombre');
        $st->bind_param('s', $character['id']);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        $rates = favor_gremio_rates($db);
        $st = $db->prepare("SELECT ID, InventarioID, Nombre, Estado, Precio, NotaJugador, NotaDM, Creado, Resuelto FROM GremioOferta WHERE PersonajeID = ? ORDER BY ID DESC LIMIT 30");
        $st->bind_param('s', $character['id']);
        $st->execute();
        $offers = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    } finally {
        $db->close();
    }
    $open = [];
    foreach ($offers as $o) {
        if (in_array($o['Estado'], ['pendiente', 'ofertada'], true)) $open[(int)$o['InventarioID']] = $o;
    }
    $items = [];
    foreach ($rows as $r) {
        $rate = $rates[favor_gremio_key((string)$r['Nombre'])] ?? null;
        $offer = $open[(int)$r['ID']] ?? null;
        $items[] = [
            'id' => (int)$r['ID'],
            'name' => (string)$r['Nombre'],
            'category' => (string)$r['Categoria'],
            'qty' => (int)$r['Cantidad'],
            'equipped' => (bool)$r['Equipado'],
            'description' => (string)($r['Descripcion'] ?? ''),
            'image' => favor_asset_url((string)($r['ImageUrl'] ?? '')),
            'magic' => favor_gremio_is_magic($r),
            'attunement' => favor_gremio_needs_attunement($r),
            'rate' => $rate ? (int)$rate['PrecioCompra'] : null,
            'rateLabel' => $rate ? favor_copper_label((int)$rate['PrecioCompra']) : '',
            'offer' => $offer ? ['id' => (int)$offer['ID'], 'state' => (string)$offer['Estado'], 'price' => $offer['Precio'] !== null ? (int)$offer['Precio'] : null, 'priceLabel' => $offer['Precio'] !== null ? favor_copper_label((int)$offer['Precio']) : '', 'noteDM' => (string)$offer['NotaDM']] : null,
        ];
    }
    return [
        'character' => ['id' => $character['id'], 'name' => $character['name']],
        'items' => $items,
        'offers' => array_map(static fn(array $o): array => [
            'id' => (int)$o['ID'], 'inventory' => (int)$o['InventarioID'], 'name' => (string)$o['Nombre'], 'state' => (string)$o['Estado'],
            'price' => $o['Precio'] !== null ? favor_copper_label((int)$o['Precio']) : '', 'noteDM' => (string)$o['NotaDM'],
            'at' => (string)$o['Creado'], 'resolved' => (string)($o['Resuelto'] ?? ''),
        ], $offers),
    ];
}

/** El jugador manda un objeto a valoración del DM (no se mueve nada todavía). */
function favor_gremio_request(array $user, string $characterId, int $inventoryId, string $note): array
{
    $character = favor_gremio_character($user, $characterId);
    $note = mb_substr(trim($note), 0, 500, 'UTF-8');
    $by = favor_gremio_author($user);
    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        $item = favor_gremio_lock_item($db, $character['id'], $inventoryId);
        $st = $db->prepare("SELECT ID FROM GremioOferta WHERE InventarioID = ? AND Estado IN ('pendiente','ofertada') LIMIT 1 FOR UPDATE");
        $st->bind_param('i', $inventoryId);
        $st->execute();
        $dup = $st->get_result()->fetch_row();
        $st->close();
        if ($dup) throw new RuntimeException('Ese objeto ya está esperando la valoración del gremio.');
        $name = (string)$item['Nombre'];
        $cat = (string)$item['Categoria'];
        $desc = (string)($item['Descripcion'] ?? '');
        $usr = (string)$user['username'];
        $st = $db->prepare('INSERT INTO GremioOferta (PersonajeID,PersonajeNombre,InventarioID,Nombre,Categoria,Descripcion,NotaJugador,Usuario) VALUES (?,?,?,?,?,?,?,?)');
        $st->bind_param('ssisssss', $character['id'], $character['name'], $inventoryId, $name, $cat, $desc, $note, $usr);
        $st->execute();
        $offerId = (int)$db->insert_id;
        $st->close();
        favor_gremio_log($db, ['tipo' => 'valoracion', 'char' => $character['id'], 'charName' => $character['name'], 'objeto' => $name, 'inv' => $inventoryId, 'user' => $by, 'detalle' => 'Pide valoración #' . $offerId . ($note !== '' ? ': ' . $note : '')]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
    favor_discord_send('compras', '⚖️ ' . $character['name'] . ' ofrece un objeto al gremio', '• ' . $name, [['Jugador', $character['playerName']], ['Nota', $note]]);
    return ['id' => $offerId, 'name' => $name];
}

/** Objeto del Inventario de ese personaje, bloqueado. Lanza si no es suyo o no se puede vender. */
function favor_gremio_lock_item(mysqli $db, string $charId, int $inventoryId): array
{
    $st = $db->prepare('SELECT ID, Nombre, Categoria, Cantidad, Equipado, Descripcion, Notas, ImageUrl FROM InventarioItem WHERE ID = ? AND PersonajeID = ? LIMIT 1 FOR UPDATE');
    $st->bind_param('is', $inventoryId, $charId);
    $st->execute();
    $item = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$item) throw new RuntimeException('Ese objeto ya no está en el Inventario de tu personaje.');
    if ((int)$item['Cantidad'] < 1) throw new RuntimeException('No te queda ninguna unidad de ese objeto.');
    if ((int)$item['Equipado'] === 1) throw new RuntimeException('Ese objeto está equipado. Quítatelo en el Inventario antes de venderlo.');
    return $item;
}

/**
 * Vende una unidad al gremio. Con $offerId acepta la oferta del DM; sin él usa
 * el precio de compra fijado. En una transacción: sale del Inventario, entra al
 * Almacén (reservado) y el oro entra a la Banca.
 */
function favor_gremio_sell(array $user, string $characterId, int $inventoryId, ?int $offerId, int $expectedCopper, bool $unbound): array
{
    $character = favor_gremio_character($user, $characterId);
    $by = favor_gremio_author($user);
    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        $item = favor_gremio_lock_item($db, $character['id'], $inventoryId);
        if (favor_gremio_needs_attunement($item) && !$unbound) throw new RuntimeException('Este objeto pide vinculación: confirma que tu personaje ya no está vinculado a él.');

        if ($offerId !== null) {
            $st = $db->prepare("SELECT ID, Estado, Precio, InventarioID, PersonajeID FROM GremioOferta WHERE ID = ? FOR UPDATE");
            $st->bind_param('i', $offerId);
            $st->execute();
            $offer = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$offer || (string)$offer['PersonajeID'] !== $character['id'] || (int)$offer['InventarioID'] !== $inventoryId) throw new RuntimeException('No encontré esa oferta.');
            if ($offer['Estado'] !== 'ofertada' || $offer['Precio'] === null) throw new RuntimeException($offer['Estado'] === 'pendiente' ? 'El gremio todavía no valora ese objeto.' : 'Esa oferta ya no está abierta.');
            $copper = (int)$offer['Precio'];
        } else {
            $st = $db->prepare('SELECT PrecioCompra FROM GremioTarifa WHERE Clave = ? LIMIT 1');
            $key = favor_gremio_key((string)$item['Nombre']);
            $st->bind_param('s', $key);
            $st->execute();
            $rate = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$rate) throw new RuntimeException('El gremio no tiene precio para ese objeto: mándalo a valoración.');
            $copper = (int)$rate['PrecioCompra'];
            // Una oferta abierta del mismo objeto se cierra con esta venta.
            $st = $db->prepare("SELECT ID FROM GremioOferta WHERE InventarioID = ? AND Estado IN ('pendiente','ofertada') LIMIT 1 FOR UPDATE");
            $st->bind_param('i', $inventoryId);
            $st->execute();
            $open = $st->get_result()->fetch_row();
            $st->close();
            $offerId = $open ? (int)$open[0] : null;
        }
        if ($copper !== $expectedCopper) throw new RuntimeException('El precio del gremio cambió: ahora ofrece ' . favor_copper_label($copper) . '. Revísalo y confirma otra vez.');
        if ($copper <= 0) throw new RuntimeException('La oferta no es válida.');

        // 1) Sale una unidad del Inventario.
        if ((int)$item['Cantidad'] > 1) {
            $st = $db->prepare('UPDATE InventarioItem SET Cantidad = Cantidad - 1 WHERE ID = ? AND PersonajeID = ? AND Cantidad > 1');
        } else {
            $st = $db->prepare('DELETE FROM InventarioItem WHERE ID = ? AND PersonajeID = ? AND Cantidad = 1');
        }
        $st->bind_param('is', $inventoryId, $character['id']);
        $st->execute();
        if ($st->affected_rows !== 1) throw new RuntimeException('El objeto cambió mientras lo vendías. Inténtalo otra vez.');
        $st->close();

        // 2) Entra al Almacén, reservado hasta que el gremio decida.
        $name = mb_substr((string)$item['Nombre'], 0, 160, 'UTF-8');
        $cat = mb_substr((string)$item['Categoria'] ?: 'Objeto mágico', 0, 80, 'UTF-8');
        $desc = (string)($item['Descripcion'] ?? '');
        $att = favor_gremio_needs_attunement($item) ? 'Requiere' : '';
        $img = (string)($item['ImageUrl'] ?? '');
        $from = mb_substr($character['name'] . ' (' . $character['playerName'] . ')', 0, 100, 'UTF-8');
        $note = 'Comprado a ' . $character['name'] . ' el ' . date('d-m-Y') . ' por ' . favor_copper_label($copper) . '. Inventario #' . $inventoryId . '.';
        $st = $db->prepare("INSERT INTO GremioAlmacen (Nombre,Categoria,Descripcion,Vinculacion,ImageUrl,Destino,Existencias,Origen,ProcedenciaPersonaje,ProcedenciaNombre,PrecioPagado,Nota) VALUES (?,?,?,?,?,'reservado',1,'aventurero',?,?,?,?)");
        $st->bind_param('sssssssis', $name, $cat, $desc, $att, $img, $character['id'], $from, $copper, $note);
        $st->execute();
        $almacenId = (int)$db->insert_id;
        $st->close();

        // 3) El oro entra a la Banca.
        $old = favor_bank_lock($db, $character['id']);
        $movement = favor_bank_movement($db, $character['id'], $old, favor_gremio_coins_add($old, $copper), 'add', 'Venta al Gremio: ' . $name, $by);

        if ($offerId !== null) {
            $st = $db->prepare("UPDATE GremioOferta SET Estado = 'aceptada', AlmacenID = ?, Precio = ?, Resuelto = NOW() WHERE ID = ? AND Estado IN ('pendiente','ofertada')");
            $st->bind_param('iii', $almacenId, $copper, $offerId);
            $st->execute();
            $st->close();
        }
        favor_gremio_log($db, [
            'tipo' => 'venta', 'char' => $character['id'], 'charName' => $character['name'], 'almacen' => $almacenId,
            'objeto' => $name, 'copper' => $copper, 'mov' => $movement, 'inv' => $inventoryId, 'user' => $by,
            'detalle' => $offerId !== null ? 'Oferta #' . $offerId : 'Precio de compra del gremio',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
    favor_discord_send('compras', '🏛️ ' . $character['name'] . ' vendió al gremio', '• ' . $name, [['Jugador', $character['playerName']], ['Recibió', favor_copper_label($copper)]]);
    return ['name' => $name, 'copper' => $copper, 'paid' => favor_copper_label($copper), 'character' => $character['name'], 'almacen' => $almacenId];
}

/** El jugador rechaza la oferta del DM o retira su solicitud. */
function favor_gremio_cancel(array $user, string $characterId, int $offerId): void
{
    $character = favor_gremio_character($user, $characterId);
    $db = favor_gremio_db();
    try {
        $st = $db->prepare("UPDATE GremioOferta SET Estado = 'cancelada', Resuelto = NOW() WHERE ID = ? AND PersonajeID = ? AND Estado IN ('pendiente','ofertada')");
        $st->bind_param('is', $offerId, $character['id']);
        $st->execute();
        if ($st->affected_rows !== 1) throw new RuntimeException('Esa solicitud ya estaba cerrada.');
        $st->close();
    } finally {
        $db->close();
    }
}

/* ------------------------------------------------------------------
 * Almacén (solo admin)
 * ------------------------------------------------------------------ */

function favor_gremio_require_admin(?array $user): void
{
    if (!favor_gremio_is_admin($user)) throw new RuntimeException('Solo el DM puede gestionar el Almacén del Gremio.');
}

function favor_gremio_storage(array $user): array
{
    favor_gremio_require_admin($user);
    $db = favor_gremio_db();
    try {
        $items = $db->query('SELECT * FROM GremioAlmacen ORDER BY Destino, Existencias = 0, Nombre')->fetch_all(MYSQLI_ASSOC);
        $offers = $db->query("SELECT o.*, i.Cantidad AS Quedan FROM GremioOferta o LEFT JOIN InventarioItem i ON i.ID = o.InventarioID AND i.PersonajeID = o.PersonajeID WHERE o.Estado IN ('pendiente','ofertada') ORDER BY o.Estado = 'ofertada', o.ID")->fetch_all(MYSQLI_ASSOC);
        $rates = array_values(favor_gremio_rates($db));
        $log = $db->query('SELECT * FROM GremioRegistro ORDER BY ID DESC LIMIT 60')->fetch_all(MYSQLI_ASSOC);
    } finally {
        $db->close();
    }
    return [
        'items' => array_map(static fn(array $r): array => [
            'id' => (int)$r['ID'], 'name' => (string)$r['Nombre'], 'category' => (string)$r['Categoria'], 'subcategory' => (string)$r['Subcategoria'],
            'description' => (string)($r['Descripcion'] ?? ''), 'attunement' => (string)$r['Vinculacion'],
            'destino' => (string)$r['Destino'], 'stock' => (int)$r['Existencias'],
            'price' => $r['PrecioVenta'] !== null ? round((int)$r['PrecioVenta'] / 100, 2) : null,
            'origin' => (string)$r['Origen'], 'from' => (string)$r['ProcedenciaNombre'],
            'paid' => $r['PrecioPagado'] !== null ? favor_copper_label((int)$r['PrecioPagado']) : '',
            'review' => (string)$r['Revisar'], 'note' => (string)($r['Nota'] ?? ''), 'at' => (string)$r['Creado'],
        ], $items),
        'offers' => array_map(static fn(array $o): array => [
            'id' => (int)$o['ID'], 'character' => (string)$o['PersonajeNombre'], 'name' => (string)$o['Nombre'], 'category' => (string)$o['Categoria'],
            'description' => (string)($o['Descripcion'] ?? ''), 'state' => (string)$o['Estado'],
            'price' => $o['Precio'] !== null ? round((int)$o['Precio'] / 100, 2) : null,
            'noteP' => (string)$o['NotaJugador'], 'noteDM' => (string)$o['NotaDM'], 'at' => (string)$o['Creado'],
            'stillOwned' => $o['Quedan'] !== null,
        ], $offers),
        'rates' => array_map(static fn(array $r): array => [
            'id' => (int)$r['ID'], 'name' => (string)$r['Nombre'], 'price' => round((int)$r['PrecioCompra'] / 100, 2),
            'label' => favor_copper_label((int)$r['PrecioCompra']), 'note' => (string)$r['Nota'], 'by' => (string)$r['ActualizadoPor'],
        ], $rates),
        'log' => array_map(static fn(array $l): array => [
            'id' => (int)$l['ID'], 'at' => (string)$l['Fecha'], 'type' => (string)$l['Tipo'], 'character' => (string)$l['PersonajeNombre'],
            'item' => (string)$l['Objeto'], 'qty' => (int)$l['Cantidad'], 'copper' => (int)$l['Cobre'], 'label' => favor_copper_label(abs((int)$l['Cobre'])),
            'user' => (string)$l['Usuario'], 'detail' => (string)$l['Detalle'], 'undone' => (bool)$l['Anulado'],
            'canUndo' => (string)$l['Tipo'] === 'compra' && !(int)$l['Anulado'],
        ], $log),
    ];
}

/** El DM cambia un objeto del Almacén: destino, precio de venta, existencias y textos. */
function favor_gremio_save_item(array $user, array $in): void
{
    favor_gremio_require_admin($user);
    $id = (int)($in['id'] ?? 0);
    $destino = (string)($in['destino'] ?? '');
    if (!in_array($destino, ['venta', 'reservado'], true)) throw new RuntimeException('Elige si el objeto está a la venta o reservado.');
    $stock = (int)($in['stock'] ?? -1);
    if ($stock < 0 || $stock > 999) throw new RuntimeException('Las existencias van de 0 a 999.');
    $priceRaw = $in['price'] ?? '';
    $price = (string)$priceRaw === '' ? null : favor_gremio_parse_price((string)$priceRaw);
    if ((string)$priceRaw !== '' && $price === null) throw new RuntimeException('El precio no es válido. Escribe las piezas de oro, por ejemplo 450.');
    if ($destino === 'venta' && $price === null) throw new RuntimeException('Para ponerlo a la venta necesita un precio.');
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 160, 'UTF-8');
    if ($name === '') throw new RuntimeException('El objeto necesita un nombre.');
    $desc = mb_substr(trim((string)($in['description'] ?? '')), 0, 5000, 'UTF-8');
    $att = mb_substr(trim((string)($in['attunement'] ?? '')), 0, 80, 'UTF-8');
    $cat = mb_substr(trim((string)($in['category'] ?? '')) ?: 'Objeto mágico', 0, 80, 'UTF-8');
    $review = !empty($in['reviewed']) ? '' : null;
    $by = favor_gremio_author($user);

    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        $st = $db->prepare('SELECT Nombre FROM GremioAlmacen WHERE ID = ? FOR UPDATE');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) throw new RuntimeException('Ese objeto ya no está en el Almacén.');
        $sql = 'UPDATE GremioAlmacen SET Nombre=?, Categoria=?, Descripcion=?, Vinculacion=?, Destino=?, Existencias=?, PrecioVenta=?' . ($review !== null ? ", Revisar=''" : '') . ' WHERE ID=?';
        $st = $db->prepare($sql);
        $st->bind_param('sssssiii', $name, $cat, $desc, $att, $destino, $stock, $price, $id);
        $st->execute();
        $st->close();
        favor_gremio_log($db, ['tipo' => 'almacen', 'almacen' => $id, 'objeto' => $name, 'qty' => max(1, $stock), 'user' => $by,
            'detalle' => ($destino === 'venta' ? 'A la venta' : 'Reservado') . ' · ' . $stock . ' en existencias' . ($price !== null ? ' · ' . favor_copper_label($price) : '')]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
}

/** El DM añade al Almacén un objeto que no vino de ninguna venta. */
function favor_gremio_add_item(array $user, array $in): int
{
    favor_gremio_require_admin($user);
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 160, 'UTF-8');
    if ($name === '') throw new RuntimeException('El objeto necesita un nombre.');
    $db = favor_gremio_db();
    try {
        $cat = 'Objeto mágico';
        $st = $db->prepare("INSERT INTO GremioAlmacen (Nombre,Categoria,Destino,Existencias,Origen,ProcedenciaNombre) VALUES (?,?,'reservado',1,'admin','Añadido por el DM')");
        $st->bind_param('ss', $name, $cat);
        $st->execute();
        $id = (int)$db->insert_id;
        $st->close();
    } finally {
        $db->close();
    }
    favor_gremio_save_item($user, ['id' => $id] + $in);
    return $id;
}

/** El DM pone precio a una solicitud (y si quiere lo recuerda para ese objeto) o la rechaza. */
function favor_gremio_value(array $user, int $offerId, string $priceRaw, string $note, bool $remember, bool $reject): void
{
    favor_gremio_require_admin($user);
    $note = mb_substr(trim($note), 0, 500, 'UTF-8');
    $by = favor_gremio_author($user);
    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        $st = $db->prepare("SELECT * FROM GremioOferta WHERE ID = ? FOR UPDATE");
        $st->bind_param('i', $offerId);
        $st->execute();
        $offer = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$offer || !in_array($offer['Estado'], ['pendiente', 'ofertada'], true)) throw new RuntimeException('Esa solicitud ya estaba cerrada.');
        if ($reject) {
            $st = $db->prepare("UPDATE GremioOferta SET Estado = 'rechazada', NotaDM = ?, Resuelto = NOW() WHERE ID = ?");
            $st->bind_param('si', $note, $offerId);
            $detail = 'Rechazada' . ($note !== '' ? ': ' . $note : '');
        } else {
            $price = favor_gremio_parse_price($priceRaw);
            if ($price === null) throw new RuntimeException('Escribe cuánto ofrece el gremio, por ejemplo 200.');
            $st = $db->prepare("UPDATE GremioOferta SET Estado = 'ofertada', Precio = ?, NotaDM = ? WHERE ID = ?");
            $st->bind_param('isi', $price, $note, $offerId);
            $detail = 'Ofrece ' . favor_copper_label($price);
            if ($remember) favor_gremio_rate_write($db, (string)$offer['Nombre'], $price, '', $by);
        }
        $st->execute();
        $st->close();
        favor_gremio_log($db, ['tipo' => 'valoracion', 'char' => (string)$offer['PersonajeID'], 'charName' => (string)$offer['PersonajeNombre'], 'objeto' => (string)$offer['Nombre'], 'inv' => (int)$offer['InventarioID'], 'user' => $by, 'detalle' => $detail . ' (#' . $offerId . ')']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
}

function favor_gremio_rate_write(mysqli $db, string $name, int $copper, string $note, string $by): void
{
    $key = favor_gremio_key($name);
    if ($key === '') throw new RuntimeException('El objeto necesita un nombre.');
    $name = mb_substr(trim($name), 0, 160, 'UTF-8');
    $note = mb_substr(trim($note), 0, 255, 'UTF-8');
    $st = $db->prepare('INSERT INTO GremioTarifa (Clave,Nombre,PrecioCompra,Nota,ActualizadoPor) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE Nombre=VALUES(Nombre), PrecioCompra=VALUES(PrecioCompra), Nota=VALUES(Nota), ActualizadoPor=VALUES(ActualizadoPor)');
    $st->bind_param('ssiss', $key, $name, $copper, $note, $by);
    $st->execute();
    $st->close();
}

/** El DM fija (o quita, con precio vacío) cuánto paga el gremio por un objeto. */
function favor_gremio_save_rate(array $user, string $name, string $priceRaw, string $note, bool $delete): void
{
    favor_gremio_require_admin($user);
    $db = favor_gremio_db();
    try {
        if ($delete) {
            $key = favor_gremio_key($name);
            $st = $db->prepare('DELETE FROM GremioTarifa WHERE Clave = ?');
            $st->bind_param('s', $key);
            $st->execute();
            $st->close();
            return;
        }
        $price = favor_gremio_parse_price($priceRaw);
        if ($price === null) throw new RuntimeException('El precio no es válido. Escribe las piezas de oro, por ejemplo 200.');
        favor_gremio_rate_write($db, $name, $price, $note, favor_gremio_author($user));
    } finally {
        $db->close();
    }
}

/** El DM anula una compra en la tienda: vuelve el oro, sale del Inventario (si sigue) y vuelve al Almacén. */
function favor_gremio_undo(array $user, int $logId): string
{
    favor_gremio_require_admin($user);
    $by = favor_gremio_author($user);
    $db = favor_gremio_db();
    $db->begin_transaction();
    try {
        $st = $db->prepare("SELECT * FROM GremioRegistro WHERE ID = ? FOR UPDATE");
        $st->bind_param('i', $logId);
        $st->execute();
        $log = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$log || $log['Tipo'] !== 'compra') throw new RuntimeException('No encontré esa compra.');
        if ((int)$log['Anulado']) throw new RuntimeException('Esa compra ya estaba anulada.');
        $char = (string)$log['PersonajeID'];
        $copper = (int)$log['Cobre'];
        $qty = (int)$log['Cantidad'];

        $old = favor_bank_lock($db, $char);
        $mov = $copper > 0 ? favor_bank_movement($db, $char, $old, favor_gremio_coins_add($old, $copper), 'add', 'Compra anulada por el DM: ' . $log['Objeto'] . ' (' . FAVOR_GREMIO_NAME . ')', $by) : '';

        // Solo se anula si el objeto sigue en su Inventario: si ya lo vendió o
        // gastó, devolver el oro lo dejaría con las dos cosas.
        $removed = 0;
        if ($log['InventarioID'] !== null) {
            $inv = (int)$log['InventarioID'];
            $st = $db->prepare('DELETE FROM InventarioItem WHERE ID = ? AND PersonajeID = ? AND Cantidad >= ?');
            $st->bind_param('isi', $inv, $char, $qty);
            $st->execute();
            $removed = $st->affected_rows;
            $st->close();
        }
        if (!$removed) throw new RuntimeException('No se puede anular: el objeto ya no está entero en el Inventario de ' . $log['PersonajeNombre'] . ' (lo vendió, gastó o movió). Si hace falta, ajusta el oro a mano en la Banca.');
        if ($log['AlmacenID'] !== null) {
            $aid = (int)$log['AlmacenID'];
            $st = $db->prepare('UPDATE GremioAlmacen SET Existencias = Existencias + ? WHERE ID = ?');
            $st->bind_param('ii', $qty, $aid);
            $st->execute();
            $st->close();
        }
        $st = $db->prepare('UPDATE GremioRegistro SET Anulado = 1 WHERE ID = ?');
        $st->bind_param('i', $logId);
        $st->execute();
        $st->close();
        favor_gremio_log($db, ['tipo' => 'anulacion', 'char' => $char, 'charName' => (string)$log['PersonajeNombre'], 'almacen' => $log['AlmacenID'] !== null ? (int)$log['AlmacenID'] : null,
            'objeto' => (string)$log['Objeto'], 'qty' => $qty, 'copper' => -$copper, 'mov' => $mov, 'user' => $by, 'detalle' => 'Anula la compra #' . $logId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->close();
    }
    return 'Devuelto ' . favor_copper_label($copper) . ' a ' . $log['PersonajeNombre'] . '; el objeto volvió al Almacén.';
}

/** Ofertas que esperan al DM (para el aviso del menú). */
function favor_gremio_pending_count(): int
{
    try {
        $db = favor_gremio_db();
        try {
            $r = $db->query("SELECT COUNT(*) FROM GremioOferta WHERE Estado = 'pendiente'")->fetch_row();
            return (int)($r[0] ?? 0);
        } finally {
            $db->close();
        }
    } catch (Throwable) {
        return 0;
    }
}
