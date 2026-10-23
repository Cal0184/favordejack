<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth-widget.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$next = favor_safe_next($_GET['next'] ?? $_POST['next'] ?? favor_base_path());
$error = '';
$user = favor_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        favor_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        header('Location: ' . $next, true, 303);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($user && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $next, true, 303);
    exit;
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#100b08"><title>Iniciar sesión · Favor de Jack</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;color:#f0dfba;font-family:'EB Garamond',Georgia,serif;background:radial-gradient(circle at 50% 20%,#4b301d 0,#1b110c 39%,#080605 100%)}.box{width:min(470px,100%);padding:12px;border:1px solid #a7792a;background:#17100c;box-shadow:0 28px 80px #000b,inset 0 0 0 4px #0b0806;border-radius:15px}.paper{position:relative;padding:34px;border:1px solid #8e692d;border-radius:9px;background:linear-gradient(145deg,#f4e6c1,#dcc28d);color:#302116;box-shadow:inset 0 0 34px #6c431b20}.paper:before,.paper:after{content:"";position:absolute;width:35px;height:35px;border:2px solid #a7792a}.paper:before{top:9px;left:9px;border-right:0;border-bottom:0}.paper:after{right:9px;bottom:9px;border-left:0;border-top:0}.seal{width:84px;height:84px;margin:0 auto 16px;border-radius:50%;overflow:hidden;border:2px solid #a7792a;background:#0f0b08;box-shadow:0 7px 17px #0004}.seal img{width:100%;height:100%;object-fit:cover}.kicker{text-align:center;margin:0;color:#856a40;font-style:italic}.paper h1{text-align:center;margin:3px 0 4px;font-family:'Cinzel',serif;color:#4a241d;font-size:1.7rem}.sub{text-align:center;margin:0 0 20px;color:#6f5639}.error{padding:9px 11px;margin:0 0 13px;border:1px solid #a95c5a;background:#efd0c0;color:#792c30}.field{display:grid;gap:5px;margin:12px 0}.field span{font-weight:600;color:#5b432d}.field input{width:100%;padding:11px 12px;border:1px solid #a78957;border-radius:5px;background:#fff7df;color:#302116;font:inherit;outline:none}.field input:focus{border-color:#7a5425;box-shadow:0 0 0 3px #a7792a22}.primary{width:100%;padding:11px;margin-top:8px;border:1px solid #76501e;border-radius:5px;background:linear-gradient(#946b33,#67461f);color:#fff0c8;font:bold 1rem 'EB Garamond',serif;cursor:pointer}.links{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:16px}.links a{color:#6c3d2e;text-decoration:none;font-weight:600}.links a:hover{text-decoration:underline}@media(max-width:520px){.paper{padding:28px 20px}.links{display:grid;text-align:center;justify-content:stretch}}</style><?php favor_head('Entrar · Favor de Jack'); ?>
</head><body>
<?php favor_site_bar(); ?>
<div class="box"><main class="paper"><div class="seal"><img src="<?= favor_static(FAVOR_LOGO) ?>" alt=""></div><p class="kicker">Acceso unificado</p><h1>Favor de Jack</h1><p class="sub">Una sola sesión para todos los servicios del gremio.</p>
<?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= h(favor_csrf()) ?>"><input type="hidden" name="next" value="<?= h($next) ?>"><label class="field"><span>Usuario</span><input name="username" maxlength="50" required autocomplete="username" autofocus></label><label class="field"><span>Contraseña</span><input name="password" type="password" required autocomplete="current-password"></label><button class="primary" type="submit">Iniciar sesión</button></form>
<div class="links"><a href="<?= favor_base_path() ?>olvide-clave.php">Olvidé mi contraseña</a><a href="<?= favor_base_path() ?>">Entrar como invitado</a></div></main></div></body></html>
