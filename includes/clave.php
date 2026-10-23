<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/*
 * «Olvidé mi contraseña» sin pistas públicas.
 *
 * 1. El jugador escribe su usuario: queda una solicitud para el DM (la página
 *    responde lo mismo exista o no el usuario, así no revela cuentas).
 * 2. El DM, en «Mi cuenta», pulsa «Poner contraseña temporal» y se la pasa al
 *    jugador por Discord.
 * 3. Al entrar con ella, la web le pide cambiarla.
 *
 * Tabla ClaveSolicitud. Estado: pendiente → temporal → hecha.
 */

function favor_reset_table(mysqli $db): void
{
    static $ready = false;
    if ($ready) return;
    $db->query("CREATE TABLE IF NOT EXISTS ClaveSolicitud (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Usuario int unsigned NOT NULL,
        Estado enum('pendiente','temporal','hecha') NOT NULL DEFAULT 'pendiente',
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        Autor varchar(100) NULL,
        INDEX idx_clave_usuario (Usuario, Estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

/** Deja una solicitud para el DM. No dice si el usuario existe. */
function favor_reset_request(mysqli $db, string $username): void
{
    favor_reset_table($db);
    $stmt = $db->prepare('SELECT ID FROM Usuario WHERE LOWER(Username)=LOWER(?) AND Activo=1 LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return;

    $id = (int)$row['ID'];
    // Una sola solicitud abierta por cuenta.
    $stmt = $db->prepare("SELECT ID FROM ClaveSolicitud WHERE Usuario=? AND Estado='pendiente' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $open = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($open) return;

    $stmt = $db->prepare("INSERT INTO ClaveSolicitud (Usuario,Estado) VALUES (?,'pendiente')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/** Solicitudes abiertas, para el panel del DM. */
function favor_reset_pending(mysqli $db): array
{
    favor_reset_table($db);
    $result = $db->query(
        "SELECT s.ID, s.Usuario, s.Creado, u.Username, j.Nombre AS Jugador FROM ClaveSolicitud s "
        . "JOIN Usuario u ON u.ID=s.Usuario LEFT JOIN Jugador j ON j.ID=u.Jugador "
        . "WHERE s.Estado='pendiente' ORDER BY s.Creado"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/** Contraseña fácil de dictar: palabra-palabra-número. */
function favor_reset_password(): string
{
    $words = ['dragon', 'luna', 'espada', 'runa', 'grifo', 'faro', 'roble', 'cuervo', 'brasa', 'niebla',
        'yunque', 'pocion', 'daga', 'trueno', 'zafiro', 'lobo', 'torre', 'ancla', 'cometa', 'ambar'];
    return $words[random_int(0, count($words) - 1)] . '-' . $words[random_int(0, count($words) - 1)]
        . '-' . random_int(1000, 9999);
}

/** El DM pone una contraseña temporal. Devuelve la contraseña para pasársela al jugador. */
function favor_reset_issue(mysqli $db, int $userId, string $author): string
{
    favor_reset_table($db);
    $password = favor_reset_password();
    $salt = bin2hex(random_bytes(16));
    $iterations = 200000;
    $hash = hash_pbkdf2('sha256', $password, $salt, $iterations, 64, false);

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE Usuario SET PasswordHash=?,Salt=?,PasswordIterations=? WHERE ID=?');
        $stmt->bind_param('ssii', $hash, $salt, $iterations, $userId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed < 1) throw new RuntimeException('No se encontró esa cuenta.');

        $stmt = $db->prepare("UPDATE ClaveSolicitud SET Estado='hecha' WHERE Usuario=? AND Estado IN ('pendiente','temporal')");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO ClaveSolicitud (Usuario,Estado,Autor) VALUES (?,'temporal',?)");
        $stmt->bind_param('is', $userId, $author);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    // Que no le queden intentos fallidos bloqueando la cuenta.
    $stmt = $db->prepare('SELECT Username FROM Usuario WHERE ID=?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $name = (string)($stmt->get_result()->fetch_assoc()['Username'] ?? '');
    $stmt->close();
    if ($name !== '') {
        try { favor_login_clear($db, favor_login_keys(mb_strtolower($name, 'UTF-8'))['user']); } catch (Throwable $e) {}
    }
    return $password;
}

/** ¿Entró con una contraseña temporal que todavía no cambió? */
function favor_reset_must_change(mysqli $db, int $userId): bool
{
    favor_reset_table($db);
    $stmt = $db->prepare("SELECT 1 FROM ClaveSolicitud WHERE Usuario=? AND Estado='temporal' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $found;
}

/** El jugador ya puso su propia contraseña. */
function favor_reset_done(mysqli $db, int $userId): void
{
    favor_reset_table($db);
    $stmt = $db->prepare("UPDATE ClaveSolicitud SET Estado='hecha' WHERE Usuario=? AND Estado='temporal'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}
