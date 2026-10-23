<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/clave.php';
require_once __DIR__ . '/includes/auth-widget.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $sent=false; $asked='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $asked=trim((string)($_POST['username'] ?? ''));
        if ($asked==='') throw new RuntimeException('Escribe tu usuario.');
        // Frena a quien pruebe usuarios en masa.
        $now=time(); $tries=array_values(array_filter((array)($_SESSION['favor_hint_tries'] ?? []), static fn($t)=>is_int($t) && $t>$now-300));
        if (count($tries)>=5) throw new RuntimeException('Demasiadas solicitudes seguidas. Espera unos minutos e inténtalo otra vez.');
        $tries[]=$now; $_SESSION['favor_hint_tries']=$tries;
        $db=favor_db();
        try { favor_reset_request($db, $asked); } finally { $db->close(); }
        // Siempre la misma respuesta: no revela si la cuenta existe.
        $sent=true;
    } catch(Throwable $e) { $error=$e->getMessage(); }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recordar contraseña · Favor de Jack</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(circle at top,#4a2f1c,#17100c 48%,#080605);color:#eadcb9;font:18px Georgia,serif}.box{width:min(560px,100%);padding:28px;border:1px solid #8d6633;border-radius:12px;background:#e7d5aa;color:#312218;box-shadow:0 24px 65px #0009}.box h1{margin-top:0;color:#5b2f25}.field{display:grid;gap:6px}.field input{padding:11px;border:1px solid #a38352;background:#fff7df;color:#2d2118;font:inherit}.btn{margin-top:12px;padding:10px 14px;border:1px solid #76501e;background:#755022;color:#fff0c6;font:inherit;cursor:pointer}.hint,.error{margin:16px 0 0;padding:14px}.hint{border:1px solid #9c7c49;background:#f3e5c3;font-size:1.08rem}.error{border:1px solid #a55957;background:#edcbb8;color:#742b30}.links{display:flex;gap:16px;flex-wrap:wrap;margin-top:18px}.links a{color:#6d3b2d;text-decoration:none;font-weight:bold}</style><?php favor_head('Olvidé mi contraseña · Favor de Jack'); ?>
</head><body class="favor-plain">
<?php favor_site_bar(); ?><div class="favor-center"><main class="box"><h1>¿Olvidaste tu contraseña?</h1><?php if($sent):?><div class="hint"><strong>Aviso enviado.</strong><br>Si esa cuenta existe, el DM ya lo ve en su panel. Él te dará una contraseña temporal (pídesela por Discord) y al entrar la cambias por una tuya.</div><?php else:?><p>Escribe tu usuario y le avisaremos al DM para que te dé una contraseña temporal.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(favor_csrf())?>"><label class="field"><span>Usuario</span><input name="username" value="<?=h($asked)?>" required autocomplete="username" autofocus></label><button class="btn">Avisar al DM</button></form><?php endif?><?php if($error):?><div class="error"><?=h($error)?></div><?php endif?><div class="links"><a href="<?= favor_base_path() ?>login.php">← Volver a entrar</a><a href="<?= favor_base_path() ?>">Entrar como invitado</a></div></main></div></body></html>
