<?php
declare(strict_types=1);

/*
 * Admin · Exportar todo / Restaurar.
 * Solo administradores. Los paquetes se guardan en respaldos/ (bloqueada al navegador)
 * y se descargan desde aquí.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/respaldo.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';

favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_url('login.php?next=' . rawurlencode(favor_url('admin/respaldo.php'))), true, 303);
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Esta página es solo para administradores.');
}
$csrf = favor_csrf();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function size_label(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    return number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB';
}

// Descarga de un paquete guardado.
if (isset($_GET['descargar'])) {
    favor_session_close();
    $name = (string)$_GET['descargar'];
    $file = favor_backup_dir() . '/' . $name;
    if (!favor_backup_valid_name($name) || !is_file($file)) {
        http_response_code(404);
        exit('No existe ese paquete.');
    }
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    readfile($file);
    exit;
}

$notice = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        favor_session_close();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'export') {
            $db = favor_db();
            try { $path = favor_backup_export($db); } finally { $db->close(); }
            $notice = 'Paquete creado: ' . basename($path) . ' (' . size_label((int)filesize($path)) . '). Descárgalo desde la lista y guárdalo fuera del servidor.';
        } elseif ($action === 'upload') {
            $upload = $_FILES['paquete'] ?? null;
            if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $code = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
                throw new RuntimeException($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE
                    ? 'El paquete pesa más de lo que el servidor deja subir. Súbelo por FTP a la carpeta respaldos/.'
                    : 'No llegó ningún archivo.');
            }
            $name = basename((string)$upload['name']);
            if (!favor_backup_valid_name($name)) $name = 'subido-' . gmdate('Ymd-His') . '.zip';
            $target = favor_backup_dir() . '/' . $name;
            if (is_file($target)) throw new RuntimeException('Ya existe un paquete con ese nombre.');
            if (!move_uploaded_file((string)$upload['tmp_name'], $target)) throw new RuntimeException('No pude guardar el paquete.');
            $notice = "Paquete subido: $name. Ya puedes restaurarlo desde la lista.";
        } elseif ($action === 'restore') {
            $name = (string)($_POST['name'] ?? '');
            if (!favor_backup_valid_name($name) || !is_file(favor_backup_dir() . '/' . $name)) throw new RuntimeException('No existe ese paquete.');
            if (trim((string)($_POST['confirm'] ?? '')) !== 'RESTAURAR') throw new RuntimeException('Escribe RESTAURAR para confirmar.');
            $db = favor_db();
            try { $summary = favor_backup_restore($db, favor_backup_dir() . '/' . $name); } finally { $db->close(); }
            $notice = 'Restaurado ' . $name . ': ' . array_sum(array_map('intval', $summary['tables'])) . ' filas en '
                . count($summary['tables']) . ' tablas y ' . $summary['files'] . ' archivos. '
                . 'Antes se guardó el estado anterior en ' . $summary['safety'] . '.';
        } elseif ($action === 'delete') {
            $name = (string)($_POST['name'] ?? '');
            $file = favor_backup_dir() . '/' . $name;
            if (!favor_backup_valid_name($name) || !is_file($file)) throw new RuntimeException('No existe ese paquete.');
            unlink($file);
            $notice = "Paquete borrado: $name.";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
favor_session_close();

$packages = [];
try { $packages = favor_backup_list(); } catch (Throwable $e) { $error = $error ?: $e->getMessage(); }
$uploadLimit = (string)ini_get('upload_max_filesize');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Respaldo · Favor de Jack</title>
<link rel="icon" href="<?= favor_url_html('favicon.ico') ?>">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:24px 16px;color:#eadcb9;font:17px/1.5 Georgia,'EB Garamond',serif;background:#120c08 radial-gradient(circle at 50% 0,#2b1b10,#120c08 70%)}
main{max-width:760px;margin:0 auto}
a{color:#e8c785}
h1{margin:.2em 0 .1em;font:600 1.9rem Georgia,serif;color:#f0cf8a}
h2{margin:0 0 .4em;font-size:1.2rem;color:#e8c785}
.sub{margin:0 0 22px;color:#bba98a}
.card{margin:0 0 18px;padding:18px;border:1px solid #6b4d2c;border-radius:12px;background:#1b130dcc}
.card p{margin:.3em 0 .8em}
button{min-height:42px;padding:8px 16px;border:1px solid #a87b3f;border-radius:8px;background:#3a2414;color:#f3d79f;font:inherit;cursor:pointer}
button:hover{background:#4a2e18}
button.danger{border-color:#9b4a40;background:#3a1714;color:#f2c1b8}
input[type=text],input[type=file]{width:100%;min-height:42px;padding:8px;border:1px solid #5b4329;border-radius:8px;background:#0b0705;color:#fff;font:inherit}
.msg{padding:12px 14px;border-radius:9px;margin:0 0 18px}
.ok{border:1px solid #5d7a3e;background:#1d2a12;color:#d6efb7}
.err{border:1px solid #8e4a44;background:#2a1412;color:#efbbb5}
.pkg{display:grid;gap:10px;padding:14px 0;border-top:1px solid #3d2c1d}
.pkg:first-of-type{border-top:0}
.pkg strong{word-break:break-all}
.pkg small{color:#a99677}
.row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
details{margin-top:4px}
summary{cursor:pointer;color:#e0b26a}
.restore{display:grid;gap:8px;margin-top:10px}
.note{font-size:.92rem;color:#bba98a}
</style>
<?php favor_head('Respaldo · Favor de Jack'); ?>
</head>
<body class="favor-plain">
<?php favor_site_bar(); ?><div class="favor-pad">
<main>
    <h1>Respaldo de la web</h1>
    <p class="sub">Un paquete guarda la base de datos y todas las imágenes y datos que no están en GitHub. Con el código de GitHub y un paquete puedes montar la web en cualquier dominio o proveedor.</p>

    <?php if ($notice): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>

    <section class="card">
        <h2>Exportar todo</h2>
        <p>Crea un paquete .zip con el estado actual. Puede tardar un minuto por las imágenes.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="export">
            <button type="submit">Crear paquete ahora</button>
        </form>
    </section>

    <section class="card">
        <h2>Paquetes guardados</h2>
        <?php if (!$packages): ?>
            <p class="note">Todavía no hay paquetes.</p>
        <?php endif; ?>
        <?php foreach ($packages as $p): ?>
            <div class="pkg">
                <div><strong><?= h($p['name']) ?></strong><br><small><?= h(gmdate('d/m/Y H:i', (int)$p['time'])) ?> UTC · <?= h(size_label((int)$p['size'])) ?></small></div>
                <div class="row">
                    <a href="?descargar=<?= h(rawurlencode($p['name'])) ?>"><button type="button">Descargar</button></a>
                    <form method="post" onsubmit="return confirm('¿Borrar este paquete del servidor?')">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="name" value="<?= h($p['name']) ?>">
                        <button class="danger" type="submit">Borrar</button>
                    </form>
                </div>
                <details>
                    <summary>Restaurar este paquete</summary>
                    <form class="restore" method="post">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="name" value="<?= h($p['name']) ?>">
                        <p class="note">Reemplaza la base de datos y los archivos por los del paquete. Antes se guarda automáticamente un paquete con el estado actual, por si hay que volver atrás.</p>
                        <label>Escribe <b>RESTAURAR</b> para confirmar<input type="text" name="confirm" autocomplete="off" required></label>
                        <button class="danger" type="submit">Restaurar</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Subir un paquete</h2>
        <p class="note">Este servidor acepta subidas de hasta <?= h($uploadLimit) ?>. Si el paquete pesa más, súbelo por FTP a la carpeta <b>respaldos/</b> de la web y aparecerá en la lista de arriba.</p>
        <form method="post" enctype="multipart/form-data" class="restore">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="paquete" accept=".zip" required>
            <button type="submit">Subir paquete</button>
        </form>
    </section>
</main>
</div></body>
</html>
