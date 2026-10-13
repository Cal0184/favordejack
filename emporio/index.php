<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/paths.php';

/*
 * El Emporio de Ecix's
 * Versión pública: sin login de jugadores ni dependencia con Banca Digital.
 *
 * - El catálogo es público.
 * - Los favoritos se guardan en una cookie del navegador.
 * - Solo el administrador usa sesión PHP.
 * - Las credenciales del administrador viven en admin.json.
 */

date_default_timezone_set('America/Santiago');

// Sesión EXCLUSIVA del administrador del Emporio.
// El nombre y el path evitan cualquier choque con la sesión de Banca Digital.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ecixs_admin_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path' => favor_base_path() . 'emporio/',
    ]);
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

const DATA_DIR = __DIR__ . '/data';
const CARDS_DIR = __DIR__ . '/Cartas';
const ADMIN_FILE = __DIR__ . '/admin.json';
const FAVORITES_COOKIE = 'ecixs_favorites';
define('HOME_URL', favor_base_path());
define('BANK_URL', favor_base_path() . 'bancadigital/');

function json_file(string $name): string {
    return DATA_DIR . '/' . $name . '.json';
}

function load_json(string $name, array $fallback = []): array {
    $raw = @file_get_contents(json_file($name));
    $data = $raw === false ? null : json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function save_json(string $name, array $data): bool {
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0775, true)) return false;
    $file = json_file($name);
    $tmp = $file . '.tmp';
    $ok = @file_put_contents(
        $tmp,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    return $ok !== false && @rename($tmp, $file);
}

function reply(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean(string $value, int $max = 180): string {
    $value = trim(strip_tags($value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function csrf_ok(): bool {
    $sent = (string)($_POST['csrf'] ?? '');
    $expected = (string)($_SESSION['csrf'] ?? '');
    return $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
}

function load_admin_config(): ?array {
    $raw = @file_get_contents(ADMIN_FILE);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    if (empty($data['username']) || empty($data['password_hash'])) return null;
    return $data;
}

function admin_logged(): bool {
    return !empty($_SESSION['ecixs_admin']) && is_array($_SESSION['ecixs_admin']);
}

function admin_display_name(): string {
    if (!admin_logged()) return 'Visitante';
    return (string)($_SESSION['ecixs_admin']['display'] ?? 'Administrador');
}

function favorites_cookie_read(): array {
    $raw = (string)($_COOKIE[FAVORITES_COOKIE] ?? '');
    if ($raw === '') return [];

    // Formato compacto: id.id.id  (cada id solo contiene hexadecimal)
    $ids = array_values(array_filter(explode('.', $raw), static fn($id) => preg_match('/^[a-f0-9]{20}$/', $id) === 1));
    return array_values(array_unique($ids));
}

function favorites_cookie_write(array $favorites): bool {
    $favorites = array_values(array_unique(array_filter(
        array_map('strval', $favorites),
        static fn($id) => preg_match('/^[a-f0-9]{20}$/', $id) === 1
    )));

    // 143 cartas * ~21 bytes sigue bajo el límite habitual de 4 KB por cookie.
    $value = implode('.', $favorites);
    return setcookie(FAVORITES_COOKIE, $value, [
        'expires' => time() + 60 * 60 * 24 * 365 * 2,
        'path' => favor_base_path() . 'emporio/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function card_id(string $file): string {
    return substr(hash('sha256', $file), 0, 20);
}

function key_name(string $value): string {
    $value = pathinfo($value, PATHINFO_FILENAME);
    if (function_exists('transliterator_transliterate')) {
        $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
    } else {
        $value = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
    }
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $value), '-');
}

function catalog_data(): array {
    return load_json('catalog');
}

function scan_cards(): array {
    if (!is_dir(CARDS_DIR)) return [];
    $files = array_values(array_filter(
        scandir(CARDS_DIR) ?: [],
        fn($f) => is_file(CARDS_DIR . '/' . $f) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)
    ));
    natcasesort($files);
    return array_values($files);
}

function cards_data(): array {
    $meta = load_json('cards');
    $catalog = catalog_data();
    $fixed = load_json('item_overrides');
    $out = [];
    $byKey = [];

    foreach ($catalog as $entry) {
        $byKey[key_name((string)($entry['name'] ?? ''))] = $entry;
    }

    foreach (scan_cards() as $file) {
        $id = card_id($file);
        $base = pathinfo($file, PATHINFO_FILENAME);
        $fileKey = key_name($base);
        $entry = $byKey[$fileKey] ?? null;

        if (in_array($fileKey, [
            'guantes-de-poder-del-ogro',
            'guantes-de-fuerza-de-ogro',
            'guantes-de-poder-de-ogro'
        ], true)) continue;

        if (!$entry && $fileKey !== '') {
            $best = null;
            $distance = PHP_INT_MAX;
            foreach ($byKey as $candidateKey => $candidate) {
                $d = levenshtein($fileKey, $candidateKey);
                if ($d < $distance) {
                    $distance = $d;
                    $best = $candidate;
                }
            }
            if ($best && $distance <= max(2, (int)floor(strlen($fileKey) * 0.16))) $entry = $best;
        }

        $m = $entry ?? [];
        if (isset($fixed[$fileKey]) && is_array($fixed[$fileKey])) {
            $m = array_merge($m, $fixed[$fileKey]);
        }

        $override = $meta[$id] ?? [];
        foreach (['name', 'category', 'price', 'description'] as $field) {
            $value = trim((string)($override[$field] ?? ''));
            if ($value !== '' && !in_array($value, ['Consultar', 'Descripción no encontrada en el catálogo.'], true)) {
                $m[$field] = $value;
            }
        }
        if (array_key_exists('available', $override)) $m['available'] = (bool)$override['available'];
        if (array_key_exists('stock', $override)) $m['stock'] = max(0, (int)$override['stock']);

        $out[] = [
            'id' => $id,
            'file' => $file,
            'name' => $m['name'] ?? str_replace(['_', '-'], ' ', $base),
            'category' => $m['category'] ?? 'Sin categoría',
            'price' => $m['price'] ?? 'Consultar',
            'description' => $m['description'] ?? 'Descripción no encontrada en el catálogo.',
            'stock' => $m['stock'] ?? 1,
            'available' => $m['available'] ?? true,
            'matched' => (bool)($entry || isset($fixed[$fileKey])),
        ];
    }

    return $out;
}


function bootstrap_data(): void {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    foreach (['cards' => [], 'requests' => [], 'audit' => []] as $name => $fallback) {
        if (!is_file(json_file($name))) save_json($name, $fallback);
    }
}

bootstrap_data();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

$action = (string)($_POST['action'] ?? '');
$adminLoginError = '';

// ------------------------------------------------------------
// LOGIN / LOGOUT DEL ADMIN (formularios normales, no AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'admin_login') {
    if (!csrf_ok()) {
        $adminLoginError = 'La sesión de seguridad venció. Recarga la página.';
    } else {
        $config = load_admin_config();
        $username = clean((string)($_POST['username'] ?? ''), 80);
        $password = (string)($_POST['password'] ?? '');

        if (!$config) {
            $adminLoginError = 'No se encontró un admin.json válido.';
        } elseif (
            hash_equals((string)$config['username'], $username)
            && password_verify($password, (string)$config['password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['ecixs_admin'] = [
                'username' => (string)$config['username'],
                'display' => (string)($config['display'] ?? 'Administrador'),
                'login_at' => date(DATE_ATOM),
            ];
            header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? 'index.php'), '?'));
            exit;
        } else {
            $adminLoginError = 'Usuario o contraseña incorrectos.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'admin_logout') {
    if (csrf_ok()) {
        unset($_SESSION['ecixs_admin']);
        session_regenerate_id(true);
    }
    header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? 'index.php'), '?'));
    exit;
}

// Compatibilidad: si assets/app.js intenta usar "logout", solo cierra Admin.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    if (!csrf_ok()) reply(['ok' => false, 'error' => 'La sesión de seguridad venció.'], 403);
    unset($_SESSION['ecixs_admin']);
    reply(['ok' => true]);
}

// ------------------------------------------------------------
// ACCIONES AJAX DEL EMPORIO
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['admin_login', 'admin_logout'], true)) {
    if (!csrf_ok()) {
        reply(['ok' => false, 'error' => 'La sesión de seguridad venció. Recarga la página.'], 403);
    }

    if ($action === 'favorite') {
        $id = clean((string)($_POST['id'] ?? ''), 30);
        if (!preg_match('/^[a-f0-9]{20}$/', $id)) {
            reply(['ok' => false, 'error' => 'Favorito inválido.'], 422);
        }

        $mine = favorites_cookie_read();
        $mine = in_array($id, $mine, true)
            ? array_values(array_diff($mine, [$id]))
            : array_values(array_unique([...$mine, $id]));

        if (!favorites_cookie_write($mine)) {
            reply(['ok' => false, 'error' => 'El navegador no permitió guardar el favorito.'], 500);
        }
        reply(['ok' => true, 'favorites' => $mine]);
    }

    // Las solicitudes siguen funcionando sin cuenta: quedan como "Visitante".
    if ($action === 'request') {
        $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
        if (!is_array($ids) || !$ids) reply(['ok' => false, 'error' => 'Selecciona al menos una carta.'], 422);

        $cards = [];
        foreach (cards_data() as $c) $cards[$c['id']] = $c;
        $selected = [];
        foreach ($ids as $id) {
            if (isset($cards[$id])) $selected[] = $cards[$id]['name'];
        }
        if (!$selected) reply(['ok' => false, 'error' => 'No se encontraron objetos válidos.'], 422);

        $requests = load_json('requests');
        array_unshift($requests, [
            'id' => bin2hex(random_bytes(6)),
            'user' => 'visitante',
            'playerId' => '',
            'display' => clean((string)($_POST['display'] ?? 'Visitante'), 80) ?: 'Visitante',
            'cards' => $selected,
            'note' => clean((string)($_POST['note'] ?? ''), 500),
            'date' => date(DATE_ATOM),
            'status' => 'Pendiente',
        ]);
        if (!save_json('requests', $requests)) reply(['ok' => false, 'error' => 'No se pudo guardar la solicitud.'], 500);
        reply(['ok' => true]);
    }

    if ($action === 'request_service') {
        $serviceId = clean((string)($_POST['id'] ?? ''), 80);
        $service = null;
        foreach (load_json('services') as $candidate) {
            if (($candidate['id'] ?? '') === $serviceId) {
                $service = $candidate;
                break;
            }
        }
        if (!$service) reply(['ok' => false, 'error' => 'Servicio no encontrado.'], 404);

        $requests = load_json('requests');
        array_unshift($requests, [
            'id' => bin2hex(random_bytes(6)),
            'user' => 'visitante',
            'playerId' => '',
            'display' => clean((string)($_POST['display'] ?? 'Visitante'), 80) ?: 'Visitante',
            'cards' => [],
            'services' => [(string)$service['name']],
            'note' => clean((string)($_POST['note'] ?? ''), 500),
            'date' => date(DATE_ATOM),
            'status' => 'Pendiente',
        ]);
        if (!save_json('requests', $requests)) reply(['ok' => false, 'error' => 'No se pudo guardar la solicitud.'], 500);
        reply(['ok' => true]);
    }

    // Desde aquí, todo es exclusivo del administrador local del Emporio.
    if (!admin_logged()) {
        reply(['ok' => false, 'error' => 'Acceso exclusivo del administrador.'], 403);
    }

    if ($action === 'save_card') {
        $id = clean((string)($_POST['id'] ?? ''), 30);
        $valid = false;
        foreach (scan_cards() as $f) {
            if (card_id($f) === $id) {
                $valid = true;
                break;
            }
        }
        if (!$valid) reply(['ok' => false, 'error' => 'Carta no encontrada.'], 404);

        $meta = load_json('cards');
        $meta[$id] = [
            'name' => clean((string)($_POST['name'] ?? '')),
            'category' => clean((string)($_POST['category'] ?? 'Sin categoría'), 100),
            'price' => clean((string)($_POST['price'] ?? ''), 80),
            'description' => clean((string)($_POST['description'] ?? ''), 6000),
            'stock' => max(0, (int)($_POST['stock'] ?? 0)),
            'available' => ($_POST['available'] ?? '0') === '1',
        ];
        if (!save_json('cards', $meta)) reply(['ok' => false, 'error' => 'No se pudo escribir en data/.'], 500);
        reply(['ok' => true]);
    }

    if ($action === 'request_status') {
        $id = clean((string)($_POST['id'] ?? ''), 30);
        $status = clean((string)($_POST['status'] ?? ''), 30);
        $allowed = ['Pendiente', 'Aceptada', 'Rechazada', 'Entregada'];
        if (!in_array($status, $allowed, true)) reply(['ok' => false, 'error' => 'Estado inválido.'], 422);

        $requests = load_json('requests');
        $found = false;
        foreach ($requests as &$request) {
            if (($request['id'] ?? '') === $id) {
                $request['status'] = $status;
                $found = true;
                break;
            }
        }
        unset($request);
        if (!$found) reply(['ok' => false, 'error' => 'Solicitud no encontrada.'], 404);
        if (!save_json('requests', $requests)) reply(['ok' => false, 'error' => 'No se pudo actualizar.'], 500);
        reply(['ok' => true]);
    }

    reply(['ok' => false, 'error' => 'Acción desconocida.'], 400);
}

$isAdmin = admin_logged();
$state = [
    // Se mantiene true para que el app.js actual abra directamente el catálogo.
    'logged' => true,
    'admin' => $isAdmin,
    'user' => admin_display_name(),
    'username' => $isAdmin ? (string)($_SESSION['ecixs_admin']['username'] ?? 'admin') : 'visitante',
    'playerId' => '',
    'role' => $isAdmin ? 'admin' : 'guest',
    'csrf' => (string)$_SESSION['csrf'],
    'cards' => cards_data(),
    'services' => load_json('services'),
    'favorites' => favorites_cookie_read(),
    'requests' => $isAdmin ? load_json('requests') : [],
    'homeUrl' => HOME_URL,
    'bankUrl' => BANK_URL,
    'changePasswordUrl' => '',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>El Emporio de Ecix's</title>
<link rel="stylesheet" href="assets/style.css?v=20260826-final">
<link rel="stylesheet" href="assets/update.css?v=20260826-final">
<style>
/* Botón Admin independiente de la estética/JS existente */
.ecixs-admin-button{
    position:fixed;right:16px;bottom:16px;z-index:9998;
    padding:9px 14px;border:1px solid rgba(209,169,92,.65);border-radius:8px;
    background:rgba(20,14,11,.92);color:#e7d2a1;cursor:pointer;
    font:600 .76rem Georgia,serif;letter-spacing:.08em;text-transform:uppercase;
    box-shadow:0 8px 22px rgba(0,0,0,.35)
}
.ecixs-admin-button:hover{filter:brightness(1.15)}
.ecixs-admin-overlay{
    position:fixed;inset:0;z-index:9999;display:none;place-items:center;
    padding:18px;background:rgba(0,0,0,.72);backdrop-filter:blur(4px)
}
.ecixs-admin-overlay.open{display:grid}
.ecixs-admin-box{
    width:min(390px,100%);padding:24px;border:1px solid rgba(209,169,92,.55);
    border-radius:12px;background:#17110d;color:#eadbb8;box-shadow:0 24px 70px #000b
}
.ecixs-admin-box h2{margin:0 0 8px;color:#e5c37b;font-family:Georgia,serif}
.ecixs-admin-box p{color:#c8b999;margin:0 0 16px}
.ecixs-admin-box label{display:block;margin:12px 0 5px;color:#d9c9aa}
.ecixs-admin-box input{width:100%;box-sizing:border-box;padding:10px 11px;border:1px solid #675844;border-radius:7px;background:#0e0b09;color:#fff}
.ecixs-admin-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:18px}
.ecixs-admin-actions button{padding:9px 13px;border:1px solid #756247;border-radius:7px;background:#21170f;color:#eadbb8;cursor:pointer}
.ecixs-admin-actions .primary{background:#6b4c1f;border-color:#a77b38;color:#fff3d4}
.ecixs-admin-error{margin:12px 0 0!important;color:#ffadad!important;font-weight:700}

/* ================= AJUSTES VISUALES 26-08 ================= */
/* Las cartas deben verse completas y centradas, nunca recortadas. */
#app img[src*="Cartas/"],
#app img[src*="/Cartas/"]{
    display:block !important;
    width:100% !important;
    height:auto !important;
    max-height:none !important;
    object-fit:contain !important;
    object-position:center center !important;
    margin-left:auto !important;
    margin-right:auto !important;
}
#app :is(div,figure,picture):has(> img[src*="Cartas/"]),
#app :is(div,figure,picture):has(> img[src*="/Cartas/"]){
    height:auto !important;
    max-height:none !important;
    overflow:visible !important;
}

/* Navegación de la esquina: Catálogo y Servicios centrados. */
#nav > :nth-child(1),
#nav > :nth-child(2){
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    text-align:center !important;
}
#nav > :nth-child(1) *,
#nav > :nth-child(2) *{
    text-align:center !important;
}


</style>
</head>
<body>
<div class="mist"></div>
<header>
    <img src="Logo.jpg" alt="Logo de El Emporio" onerror="this.style.display='none'">
    <div>
        <small>OBJETOS EXTRAORDINARIOS · TRATOS INOLVIDABLES</small>
        <h1>El Emporio de Ecix's</h1>
    </div>
    <nav id="nav"></nav>
</header>
<main id="app"></main>
<footer>¿Algo salió mal? <a href="#" id="discord">Háblame por Discord, pelotudo</a></footer>

<button type="button" class="ecixs-admin-button" id="ecixs-admin-open">Admin</button>
<div class="ecixs-admin-overlay <?= $adminLoginError !== '' ? 'open' : '' ?>" id="ecixs-admin-overlay" aria-hidden="<?= $adminLoginError !== '' ? 'false' : 'true' ?>">
    <div class="ecixs-admin-box" role="dialog" aria-modal="true" aria-labelledby="ecixs-admin-title">
        <?php if ($isAdmin): ?>
            <h2 id="ecixs-admin-title">Administrador activo</h2>
            <p>Estás usando el panel administrativo local del Emporio.</p>
            <form method="post">
                <input type="hidden" name="action" value="admin_logout">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="ecixs-admin-actions">
                    <button type="button" data-admin-close>Cerrar</button>
                    <button type="submit" class="primary">Cerrar sesión Admin</button>
                </div>
            </form>
        <?php else: ?>
            <h2 id="ecixs-admin-title">Admin</h2>
            <p>Acceso exclusivo para administrar el Emporio.</p>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="admin_login">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="admin-user">Usuario</label>
                <input id="admin-user" name="username" required autocomplete="username">
                <label for="admin-password">Contraseña</label>
                <input id="admin-password" name="password" type="password" required autocomplete="current-password">
                <?php if ($adminLoginError !== ''): ?>
                    <p class="ecixs-admin-error"><?= htmlspecialchars($adminLoginError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="ecixs-admin-actions">
                    <button type="button" data-admin-close>Cancelar</button>
                    <button type="submit" class="primary">Entrar</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>window.APP_STATE=<?= json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/app.js?v=20260826-final"></script>
<script>
(() => {
    const overlay = document.getElementById('ecixs-admin-overlay');
    const openBtn = document.getElementById('ecixs-admin-open');
    const open = () => {
        overlay?.classList.add('open');
        overlay?.setAttribute('aria-hidden', 'false');
    };
    const close = () => {
        overlay?.classList.remove('open');
        overlay?.setAttribute('aria-hidden', 'true');
    };

    openBtn?.addEventListener('click', open);
    document.querySelectorAll('[data-admin-close]').forEach(btn => btn.addEventListener('click', close));
    overlay?.addEventListener('click', event => { if (event.target === overlay) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
})();
</script>
</body>
</html>
