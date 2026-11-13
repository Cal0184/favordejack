<?php
declare(strict_types=1);

/*
 * Favor de Jack · instalador para la mudanza a un hosting nuevo.
 *
 * Con la carpeta de la web ya copiada y un paquete de «Exportar todo» en
 * respaldos/, esta página:
 *   1. comprueba que el servidor tiene lo necesario (PHP, extensiones, permisos);
 *   2. escribe config.local.php con los datos de la base de datos nueva;
 *   3. carga el paquete (base de datos y archivos) con favor_backup_restore().
 *
 * Solo funciona mientras exista instalar/clave.php (viene en el paquete de la
 * mudanza, no en Git). Al terminar bien, la clave se borra y la página se cierra.
 */

const INST_KEY_FILE = __DIR__ . '/clave.php';

$key = is_file(INST_KEY_FILE) ? (string)(require INST_KEY_FILE) : '';
if (strlen($key) < 16) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("La instalación está cerrada.\n");
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
@set_time_limit(0);

$root = dirname(__DIR__);

function inst_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Lo que el servidor necesita: [texto, ¿ok?, ¿imprescindible?]. */
function inst_checks(string $root): array
{
    $gdWebp = function_exists('imagewebp');
    return [
        ['PHP 8.1 o más nuevo (tiene ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
        ['Extensión mysqli (base de datos)', extension_loaded('mysqli'), true],
        ['Extensión zip (paquetes de respaldo)', class_exists('ZipArchive'), true],
        ['Extensión mbstring (textos con tildes)', extension_loaded('mbstring'), true],
        ['Extensión gd con WebP (imágenes)', extension_loaded('gd') && $gdWebp, false],
        ['Se puede escribir en la carpeta de la web (config.local.php)', is_writable($root), true],
        ['Se puede escribir en respaldos/', is_dir($root . '/respaldos') && is_writable($root . '/respaldos'), true],
        ['Se puede escribir en media/', is_dir($root . '/media') && is_writable($root . '/media'), false],
    ];
}

/** Paquetes de respaldos/, el más nuevo primero (por la fecha del nombre). */
function inst_packages(string $root): array
{
    $list = [];
    foreach (glob($root . '/respaldos/*.zip') ?: [] as $file) {
        $name = basename($file);
        $stamp = preg_match('/(\d{8})-(\d{6})/', $name, $m) ? $m[1] . $m[2] : date('YmdHis', (int)filemtime($file));
        $list[$name] = ['name' => $name, 'stamp' => $stamp, 'size' => (int)filesize($file), 'safety' => str_contains($name, 'antes-de-restaurar')];
    }
    uasort($list, static fn($a, $b) => [$a['safety'], -(int)$a['stamp']] <=> [$b['safety'], -(int)$b['stamp']]);
    return $list;
}

function inst_date(string $stamp): string
{
    return preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', $stamp, $m) ? "$m[3]-$m[2]-$m[1] $m[4]:$m[5]" : $stamp;
}

$checks = inst_checks($root);
$ready = !in_array(false, array_map(static fn($c) => $c[1] || !$c[2], $checks), true);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

$step = 'key';
$error = '';
$done = null;
$given = (string)($_POST['clave'] ?? '');
$f = [
    'host' => trim((string)($_POST['host'] ?? 'localhost')),
    'port' => (int)($_POST['port'] ?? 3306) ?: 3306,
    'database' => trim((string)($_POST['database'] ?? '')),
    'username' => trim((string)($_POST['username'] ?? '')),
    'password' => (string)($_POST['password'] ?? ''),
    'package' => (string)($_POST['package'] ?? ''),
    'replace' => !empty($_POST['replace']),
    'force_https' => isset($_POST['database']) ? !empty($_POST['force_https']) : $https,
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($key, trim($given))) {
        sleep(1);
        $error = 'Esa clave no es correcta. Está en el paso a paso de la mudanza.';
    } else {
        $step = 'form';
        $packages = inst_packages($root);
        if (isset($_POST['database'])) {
            try {
                if (!$ready) throw new RuntimeException('Al servidor le falta algo de la lista de arriba. Pídeselo al hosting y vuelve a intentarlo.');
                if ($f['database'] === '' || $f['username'] === '') throw new RuntimeException('Escribe el nombre de la base de datos y el usuario.');
                if (!isset($packages[$f['package']])) throw new RuntimeException('Elige un paquete de la lista.');

                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                try {
                    $db = new mysqli($f['host'], $f['username'], $f['password'], $f['database'], $f['port']);
                } catch (mysqli_sql_exception $e) {
                    throw new RuntimeException('No pude entrar a la base de datos con esos datos. Revisa nombre, usuario y contraseña. (' . $e->getMessage() . ')');
                }
                $db->set_charset('utf8mb4');
                $tables = (int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetch_row()[0];
                if ($tables > 0 && !$f['replace']) {
                    throw new RuntimeException("La base de datos ya tiene $tables tablas. Si es la base nueva de la web y quieres reemplazar lo que tenga, marca la casilla de abajo.");
                }

                // config.local.php (se escribe primero: favor_db() lo lee al restaurar).
                $config = [
                    'db' => ['host' => $f['host'], 'port' => $f['port'], 'database' => $f['database'], 'username' => $f['username'], 'password' => $f['password']],
                    'base_path' => '',
                    'force_https' => $f['force_https'],
                ];
                $php = "<?php\ndeclare(strict_types=1);\n\n// Escrito por el instalador el " . date('Y-m-d H:i') . ". Ver config.example.php.\nreturn " . var_export($config, true) . ";\n";
                $tmp = $root . '/config.local.php.' . bin2hex(random_bytes(4)) . '.tmp';
                if (@file_put_contents($tmp, $php) === false || !@rename($tmp, $root . '/config.local.php')) {
                    @unlink($tmp);
                    throw new RuntimeException('No pude escribir config.local.php. Dale permisos de escritura a la carpeta de la web.');
                }
                @chmod($root . '/config.local.php', 0640);
                if (function_exists('opcache_invalidate')) @opcache_invalidate($root . '/config.local.php', true);

                require_once $root . '/includes/respaldo.php';
                $summary = favor_backup_restore($db, $root . '/respaldos/' . $f['package'], false);
                $db->close();
                @unlink(INST_KEY_FILE);
                $done = $summary;
                $step = 'done';
            } catch (Throwable $e) {
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Error inesperado: ' . $e->getMessage();
            }
        }
    }
}
$base = rtrim(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/instalar/index.php'))), '/') . '/';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Instalar · Favor de Jack</title>
<style>
:root{--bg:#100b08;--panel:#1b130d;--ink:#f1e4c6;--muted:#c9b896;--gold:#e0b96f;--line:rgba(205,160,82,.45);--bad:#f0a497;--ok:#9fd99a}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 50% 0,#2a1b10,var(--bg) 60%);color:var(--ink);font:18px/1.5 Georgia,'Times New Roman',serif;padding:24px 16px}
main{max-width:640px;margin:0 auto}
h1{font-size:1.7rem;color:var(--gold);margin:0 0 4px;letter-spacing:.04em}
h2{font-size:1.1rem;color:var(--gold);margin:22px 0 8px}
p{margin:6px 0}
.muted{color:var(--muted);font-size:.95rem}
.card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px;margin-top:16px}
ul.checks{list-style:none;padding:0;margin:0}
ul.checks li{padding:4px 0}
.ok{color:var(--ok)}.bad{color:var(--bad)}.warn{color:var(--gold)}
label{display:block;margin:12px 0 4px;color:var(--muted);font-size:.95rem}
input[type=text],input[type=password],input[type=number],select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--line);background:#0c0806;color:var(--ink);font:inherit}
.row{display:grid;grid-template-columns:2fr 1fr;gap:10px}
.check{display:flex;gap:10px;align-items:flex-start;margin-top:12px;color:var(--ink)}
.check input{width:20px;height:20px;margin-top:3px}
button{margin-top:18px;width:100%;min-height:48px;border:0;border-radius:10px;background:linear-gradient(180deg,#f0cf87,#c99a45);color:#1a1006;font:700 1.05rem Georgia,serif;cursor:pointer}
.error{margin-top:16px;padding:12px 14px;border-radius:10px;border:1px solid var(--bad);background:rgba(240,164,151,.1);color:var(--bad)}
a{color:var(--gold)}
@media(max-width:480px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
    <h1>Instalar Favor de Jack</h1>
    <p class="muted">Mudanza al hosting nuevo: base de datos y archivos desde un paquete de «Exportar todo».</p>

    <?php if ($error !== ''): ?><div class="error" role="alert"><?= inst_h($error) ?></div><?php endif; ?>

    <?php if ($step === 'done'): ?>
        <div class="card">
            <h2>✓ ¡Listo! La web ya está en el hosting nuevo</h2>
            <p>Se cargaron <?= (int)$done['statements'] ?> instrucciones de la base de datos y <?= (int)$done['files'] ?> archivos.</p>
            <?php if (!empty($done['failed'])): ?>
                <p class="warn">Estos archivos no se pudieron copiar (revisa los permisos y vuelve a restaurar desde Respaldo):</p>
                <ul><?php foreach ($done['failed'] as $file): ?><li><?= inst_h((string)$file) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <p>El instalador quedó cerrado.</p>
            <p><a href="<?= inst_h($base) ?>login.php">Entrar con tu cuenta →</a></p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>1. El servidor</h2>
            <ul class="checks">
                <?php foreach ($checks as [$text, $ok, $must]): ?>
                    <li class="<?= $ok ? 'ok' : ($must ? 'bad' : 'warn') ?>"><?= $ok ? '✓' : ($must ? '✗' : '!') ?> <?= inst_h($text) ?><?= !$ok && !$must ? ' (recomendado)' : '' ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$ready): ?><p class="bad">Falta algo imprescindible (✗). Pídeselo al soporte del hosting.</p><?php endif; ?>
        </div>

        <form class="card" method="post" autocomplete="off">
            <?php if ($step === 'key'): ?>
                <h2>2. Clave de instalación</h2>
                <label for="clave">Escribe la clave que viene en el paso a paso</label>
                <input type="text" id="clave" name="clave" required autofocus spellcheck="false">
                <button type="submit">Continuar</button>
            <?php else: ?>
                <input type="hidden" name="clave" value="<?= inst_h($given) ?>">
                <h2>2. Base de datos nueva</h2>
                <p class="muted">Los datos que te dio el panel del hosting al crear la base de datos.</p>
                <div class="row">
                    <div><label for="host">Servidor</label><input type="text" id="host" name="host" value="<?= inst_h($f['host']) ?>" required></div>
                    <div><label for="port">Puerto</label><input type="number" id="port" name="port" value="<?= (int)$f['port'] ?>" required></div>
                </div>
                <label for="database">Nombre de la base de datos</label>
                <input type="text" id="database" name="database" value="<?= inst_h($f['database']) ?>" required spellcheck="false">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" value="<?= inst_h($f['username']) ?>" required spellcheck="false">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" value="<?= inst_h($f['password']) ?>">

                <h2>3. Paquete a cargar</h2>
                <?php if (!$packages): ?>
                    <p class="bad">No hay ningún paquete .zip en la carpeta respaldos/. Súbelo ahí con el administrador de archivos y recarga esta página.</p>
                <?php else: ?>
                    <label for="package">El más nuevo va primero</label>
                    <select id="package" name="package">
                        <?php foreach ($packages as $p): ?>
                            <option value="<?= inst_h($p['name']) ?>"<?= $p['name'] === $f['package'] ? ' selected' : '' ?>><?= inst_h(inst_date($p['stamp'])) ?> · <?= number_format($p['size'] / 1048576, 1, ',', '.') ?> MB<?= $p['safety'] ? ' · automático' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <h2>4. Opciones</h2>
                <label class="check"><input type="checkbox" name="force_https" value="1"<?= $f['force_https'] ? ' checked' : '' ?>>
                    <span>Usar siempre HTTPS (candado). Márcala solo si esta página ya se abre con <strong>https://</strong>.</span></label>
                <label class="check"><input type="checkbox" name="replace" value="1"<?= $f['replace'] ? ' checked' : '' ?>>
                    <span>La base de datos ya tiene tablas y quiero reemplazarlas.</span></label>

                <button type="submit"<?= $packages ? '' : ' disabled' ?>>Instalar</button>
                <p class="muted">Puede tardar un par de minutos. No cierres la página.</p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
