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
            'SELECT u.ID,u.Username,u.Jugador,u.Rol,u.Activo,j.Nombre AS JugadorNombre,j.Activo AS JugadorActivo,j.IconUrl,j.IconBg,j.Emoji '
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
    ] + favor_user_visual($row);
    $_SESSION['favor_user'] = $user;
    return $user;
}

/** Icono del jugador para la barra común (se guarda en la sesión). */
function favor_user_visual(array $row): array
{
    $bg = trim((string)($row['IconBg'] ?? ''));
    return [
        'iconUrl' => trim((string)($row['IconUrl'] ?? '')),
        'iconBg' => preg_match('/^#[0-9A-Fa-f]{6}$/', $bg) ? $bg : '',
        'emoji' => mb_substr(trim((string)($row['Emoji'] ?? '')), 0, 16, 'UTF-8'),
    ];
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

    $db = favor_db();
    try {
        // El límite se guarda en la base de datos: borrar cookies o cambiar de
        // navegador no da intentos nuevos.
        $keys = favor_login_keys($username);
        $wait = favor_login_wait($db, $keys);
        if ($wait > 0) throw new RuntimeException(favor_login_wait_message($wait));

        $stmt = $db->prepare(
            'SELECT u.ID,u.Username,u.PasswordHash,u.Salt,u.PasswordIterations,u.Jugador,u.Rol,u.Activo, '
            . 'j.Nombre AS JugadorNombre,j.Activo AS JugadorActivo,j.IconUrl,j.IconBg,j.Emoji '
            . 'FROM Usuario u LEFT JOIN Jugador j ON j.ID=u.Jugador WHERE LOWER(u.Username)=LOWER(?) LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = $row && (int)$row['Activo'] === 1 && $row['JugadorNombre'] !== null && (int)$row['JugadorActivo'] === 1
            && favor_password_matches($row, $password);

        if (!$valid) {
            $wait = favor_login_fail($db, $keys);
            $message = 'Usuario o contraseña incorrectos.';
            if ($wait > 0) $message .= ' ' . favor_login_wait_message($wait);
            throw new RuntimeException($message);
        }
        favor_login_clear($db, $keys['user']);
    } finally {
        $db->close();
    }

    session_regenerate_id(true);
    $user = [
        'id' => (int)$row['ID'],
        'username' => (string)$row['Username'],
        'playerId' => (string)$row['Jugador'],
        'role' => (string)$row['Rol'],
        'active' => true,
        'displayName' => (string)$row['JugadorNombre'],
    ] + favor_user_visual($row);
    $_SESSION['favor_user'] = $user;
    $_SESSION['favor_csrf'] = bin2hex(random_bytes(24));
    return $user;
}

/*
 * Límite de intentos de entrada.
 * Por usuario: FAVOR_LOGIN_MAX_FAILS fallos seguidos bloquean esa cuenta
 * FAVOR_LOGIN_LOCK_SECONDS. Por conexión (IP): un tope más alto frena a quien
 * prueba muchos usuarios distintos. Un acierto borra los fallos de la cuenta.
 */
const FAVOR_LOGIN_MAX_FAILS = 5;
const FAVOR_LOGIN_MAX_FAILS_IP = 20;
const FAVOR_LOGIN_LOCK_SECONDS = 900;

function favor_login_keys(string $username): array
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return [
        'user' => 'u:' . hash('sha256', $username),
        'ip' => 'ip:' . hash('sha256', $ip),
    ];
}

function favor_login_table(mysqli $db): void
{
    static $ready = false;
    if ($ready) return;
    $db->query("CREATE TABLE IF NOT EXISTS LoginIntento (
        Clave char(67) NOT NULL PRIMARY KEY,
        Fallos int unsigned NOT NULL DEFAULT 0,
        Primero int unsigned NOT NULL DEFAULT 0,
        BloqueadoHasta int unsigned NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii");
    $ready = true;
}

/** Segundos que faltan para poder intentarlo otra vez (0 = puede entrar). */
function favor_login_wait(mysqli $db, array $keys): int
{
    favor_login_table($db);
    $now = time();
    $stmt = $db->prepare('SELECT MAX(BloqueadoHasta) AS Hasta FROM LoginIntento WHERE Clave IN (?,?)');
    $stmt->bind_param('ss', $keys['user'], $keys['ip']);
    $stmt->execute();
    $until = (int)($stmt->get_result()->fetch_assoc()['Hasta'] ?? 0);
    $stmt->close();
    return max(0, $until - $now);
}

/** Apunta un fallo y devuelve cuánto hay que esperar si con él se llegó al tope. */
function favor_login_fail(mysqli $db, array $keys): int
{
    favor_login_table($db);
    $now = time();
    $windowStart = $now - FAVOR_LOGIN_LOCK_SECONDS;
    $wait = 0;
    foreach (['user' => FAVOR_LOGIN_MAX_FAILS, 'ip' => FAVOR_LOGIN_MAX_FAILS_IP] as $kind => $max) {
        $key = $keys[$kind];
        // Los fallos viejos (fuera de la ventana) no cuentan.
        $stmt = $db->prepare(
            'INSERT INTO LoginIntento (Clave,Fallos,Primero,BloqueadoHasta) VALUES (?,1,?,0) '
            . 'ON DUPLICATE KEY UPDATE Fallos=IF(Primero<?,1,Fallos+1), Primero=IF(Primero<?,VALUES(Primero),Primero)'
        );
        $stmt->bind_param('siii', $key, $now, $windowStart, $windowStart);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('SELECT Fallos FROM LoginIntento WHERE Clave=?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $fails = (int)($stmt->get_result()->fetch_assoc()['Fallos'] ?? 0);
        $stmt->close();

        if ($fails >= $max) {
            $until = $now + FAVOR_LOGIN_LOCK_SECONDS;
            $stmt = $db->prepare('UPDATE LoginIntento SET Fallos=0, Primero=?, BloqueadoHasta=? WHERE Clave=?');
            $stmt->bind_param('iis', $now, $until, $key);
            $stmt->execute();
            $stmt->close();
            $wait = FAVOR_LOGIN_LOCK_SECONDS;
        }
    }
    // Limpieza ligera de filas que ya no sirven.
    $old = $now - 86400;
    $stmt = $db->prepare('DELETE FROM LoginIntento WHERE BloqueadoHasta<? AND Primero<?');
    $stmt->bind_param('ii', $now, $old);
    $stmt->execute();
    $stmt->close();
    return $wait;
}

function favor_login_clear(mysqli $db, string $key): void
{
    $stmt = $db->prepare('DELETE FROM LoginIntento WHERE Clave=?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->close();
}

function favor_login_wait_message(int $seconds): string
{
    $minutes = max(1, (int)ceil($seconds / 60));
    return 'Demasiados intentos fallidos. Espera ' . $minutes . ($minutes === 1 ? ' minuto' : ' minutos') . ' e inténtalo otra vez.';
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

/**
 * ¿El recordatorio deja ver la contraseña? Prueba el texto completo y cada
 * palabra suelta (hasta 6), porque el recordatorio lo puede leer cualquiera
 * que escriba el usuario en «Olvidé mi contraseña».
 */
function favor_hint_reveals_password(array $row, string $hint): bool
{
    $hint = trim($hint);
    if ($hint === '') return false;
    $candidates = [$hint];
    $words = preg_split('/[\s,;:"\'()¿?¡!]+/u', $hint, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach (array_unique($words) as $word) {
        if (count($candidates) > 6) break;
        if ($word !== $hint && mb_strlen($word, 'UTF-8') >= 3) $candidates[] = $word;
    }
    foreach ($candidates as $candidate) {
        if (favor_password_matches($row, $candidate)) return true;
    }
    return false;
}
