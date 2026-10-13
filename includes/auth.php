<?php
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/**
 * Favor de Jack · autenticación global.
 * Una sola sesión para toda la web.
 */

function favor_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('FAVOR_DE_JACK');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => favor_base_path(),
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Libera el bloqueo de la sesión antes de consultas, imágenes o respuestas largas. */
function favor_session_close(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}

function favor_db(): mysqli
{
    $path = dirname(__DIR__) . '/bancadigital/db.php';
    if (!is_file($path)) throw new RuntimeException('No se encontró bancadigital/db.php.');
    $db = require $path;
    if (!$db instanceof mysqli) throw new RuntimeException('La conexión MySQL no es válida.');
    return $db;
}

function favor_csrf(): string
{
    favor_session_start();
    if (empty($_SESSION['favor_csrf']) || !is_string($_SESSION['favor_csrf'])) {
        $_SESSION['favor_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['favor_csrf'];
}

function favor_verify_csrf(?string $token): void
{
    $known = favor_csrf();
    if (!$token || !hash_equals($known, $token)) {
        throw new RuntimeException('La sesión de seguridad venció. Recarga la página e inténtalo otra vez.');
    }
}

function favor_safe_next(?string $next, ?string $fallback = null): string
{
    $fallback ??= favor_base_path();
    $next = trim((string)$next);
    if ($next === '' || !str_starts_with($next, favor_base_path())) return $fallback;
    // Evita redirecciones a otro dominio («//sitio» o barras invertidas).
    if (str_starts_with($next, '//') || str_contains($next, '\\')) return $fallback;
    if (str_contains($next, "\r") || str_contains($next, "\n")) return $fallback;
    return $next;
}

function favor_session_user(): ?array
{
    favor_session_start();
    $user = $_SESSION['favor_user'] ?? null;
    return is_array($user) && !empty($user['active']) && !empty($user['id']) ? $user : null;
}

function favor_current_user(bool $refresh = false): ?array
{
    $cached = favor_session_user();
    if (!$cached) return null;
    if (!$refresh) return $cached;

    $db = favor_db();
    try {
        $id = (int)$cached['id'];
        $stmt = $db->prepare(
            'SELECT u.ID,u.Username,u.Jugador,u.Rol,u.Activo,j.Nombre AS JugadorNombre,j.Activo AS JugadorActivo '
            . 'FROM Usuario u LEFT JOIN Jugador j ON j.ID=u.Jugador WHERE u.ID=? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } finally {
        $db->close();
    }

    if (!$row || (int)$row['Activo'] !== 1 || $row['JugadorNombre'] === null || (int)$row['JugadorActivo'] !== 1) {
        favor_logout();
        return null;
    }

    $user = [
        'id' => (int)$row['ID'],
        'username' => (string)$row['Username'],
        'playerId' => (string)$row['Jugador'],
        'role' => (string)$row['Rol'],
        'active' => true,
        'displayName' => (string)$row['JugadorNombre'],
    ];
    $_SESSION['favor_user'] = $user;
    return $user;
}

function favor_is_logged_in(): bool
{
    return favor_session_user() !== null;
}

function favor_is_admin(): bool
{
    $user = favor_session_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function favor_require_login(): array
{
    $user = favor_current_user(true);
    if (!$user) throw new RuntimeException('Debes iniciar sesión para modificar información.');
    return $user;
}

function favor_require_admin(): array
{
    $user = favor_require_login();
    if (($user['role'] ?? '') !== 'admin') throw new RuntimeException('Esta acción requiere una cuenta de administrador.');
    return $user;
}

function favor_login(string $username, string $password): array
{
    favor_session_start();
    $username = mb_strtolower(trim($username), 'UTF-8');
    if ($username === '' || $password === '') throw new RuntimeException('Escribe tu usuario y contraseña.');

    $now = time();
    $blocked = (int)($_SESSION['favor_login_blocked_until'] ?? 0);
    if ($blocked > $now) throw new RuntimeException('Demasiados intentos. Espera un minuto e inténtalo otra vez.');

    $db = favor_db();
    try {
        $stmt = $db->prepare(
            'SELECT u.ID,u.Username,u.PasswordHash,u.Salt,u.PasswordIterations,u.Jugador,u.Rol,u.Activo, '
            . 'j.Nombre AS JugadorNombre,j.Activo AS JugadorActivo '
            . 'FROM Usuario u LEFT JOIN Jugador j ON j.ID=u.Jugador WHERE LOWER(u.Username)=LOWER(?) LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } finally {
        $db->close();
    }

    $valid = false;
    if ($row && (int)$row['Activo'] === 1 && $row['JugadorNombre'] !== null && (int)$row['JugadorActivo'] === 1) {
        $calc = hash_pbkdf2('sha256', $password, (string)$row['Salt'], (int)$row['PasswordIterations'], 64, false);
        $valid = hash_equals(
            strtolower(trim((string)$row['PasswordHash'])),
            strtolower(trim($calc))
        );
    }

    if (!$valid) {
        $attempts = (int)($_SESSION['favor_login_attempts'] ?? 0) + 1;
        $_SESSION['favor_login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['favor_login_attempts'] = 0;
            $_SESSION['favor_login_blocked_until'] = $now + 60;
        }
        throw new RuntimeException('Usuario o contraseña incorrectos.');
    }

    session_regenerate_id(true);
    $user = [
        'id' => (int)$row['ID'],
        'username' => (string)$row['Username'],
        'playerId' => (string)$row['Jugador'],
        'role' => (string)$row['Rol'],
        'active' => true,
        'displayName' => (string)$row['JugadorNombre'],
    ];
    $_SESSION['favor_user'] = $user;
    $_SESSION['favor_csrf'] = bin2hex(random_bytes(24));
    $_SESSION['favor_login_attempts'] = 0;
    unset($_SESSION['favor_login_blocked_until']);
    return $user;
}

function favor_logout(): void
{
    favor_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

function favor_password_matches(array $row, string $password): bool
{
    $calc = hash_pbkdf2('sha256', $password, (string)$row['Salt'], (int)$row['PasswordIterations'], 64, false);
    return hash_equals(strtolower(trim((string)$row['PasswordHash'])), strtolower(trim($calc)));
}
