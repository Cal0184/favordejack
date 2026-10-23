<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth-widget.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'cuenta.php'), true, 303);
    exit;
}
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $notice=''; $hint='';
$db = favor_db();
try {
    $stmt=$db->prepare('SELECT PasswordHint FROM Usuario WHERE ID=? LIMIT 1');
    $id=(int)$user['id']; $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    $hint=(string)($row['PasswordHint'] ?? '');

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $action=(string)($_POST['action'] ?? '');
        if ($action==='save_hint') {
            $hint=trim((string)($_POST['hint'] ?? ''));
            if (mb_strlen($hint,'UTF-8')>255) throw new RuntimeException('El recordatorio no puede superar 255 caracteres.');
            $stmt=$db->prepare('SELECT PasswordHash,Salt,PasswordIterations FROM Usuario WHERE ID=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $pw=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($pw && favor_hint_reveals_password($pw,$hint)) throw new RuntimeException('El recordatorio contiene tu contraseña. Cualquiera puede leerlo desde «Olvidé mi contraseña», así que escribe solo una pista.');
            $stmt=$db->prepare('UPDATE Usuario SET PasswordHint=? WHERE ID=?'); $stmt->bind_param('si',$hint,$id); $stmt->execute(); $stmt->close();
            $notice='Recordatorio guardado.';
        } elseif ($action==='change_password') {
            $current=(string)($_POST['current_password'] ?? ''); $new=(string)($_POST['new_password'] ?? ''); $confirm=(string)($_POST['confirm_password'] ?? '');
            if (strlen($new)<8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            if ($new!==$confirm) throw new RuntimeException('Las contraseñas nuevas no coinciden.');
            $stmt=$db->prepare('SELECT PasswordHash,Salt,PasswordIterations FROM Usuario WHERE ID=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $pw=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$pw || !favor_password_matches($pw,$current)) throw new RuntimeException('La contraseña actual no es correcta.');
            $salt=bin2hex(random_bytes(16)); $iterations=max(200000,(int)($pw['PasswordIterations'] ?? 200000)); $hash=hash_pbkdf2('sha256',$new,$salt,$iterations,64,false);
            $stmt=$db->prepare('UPDATE Usuario SET PasswordHash=?,Salt=?,PasswordIterations=? WHERE ID=?'); $stmt->bind_param('ssii',$hash,$salt,$iterations,$id); $stmt->execute(); $stmt->close();
            $_SESSION['favor_csrf']=bin2hex(random_bytes(24));
            $notice='Contraseña actualizada.';
        }
    }
} catch (Throwable $e) { $error=$e->getMessage(); }
finally { $db->close(); }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi cuenta · Favor de Jack</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:26px;color:#eadcb9;font:18px 'EB Garamond',Georgia,serif;background:radial-gradient(circle at top,#4b2d1a,#17100c 48%,#080605)}.wrap{width:min(850px,100%);margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.top a{color:#edce91;text-decoration:none}.actions a{color:#6d3b2d;font-weight:600;text-decoration:none}.panel{padding:28px;border:1px solid #98703a;border-radius:12px;background:#e8d7ad;color:#312217;box-shadow:0 20px 55px #0008}.panel h1,.panel h2{font-family:'Cinzel',serif;color:#522d22}.panel h1{margin:0}.identity{margin:5px 0 24px;color:#72583d}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{padding:18px;border:1px solid #b49763;background:#f2e4c2}.card h2{margin-top:0;font-size:1.05rem}.field{display:grid;gap:5px;margin:10px 0}.field input,.field textarea{width:100%;padding:10px;border:1px solid #a48654;background:#fff7df;color:#2f2117;font:inherit}.field textarea{min-height:110px;resize:vertical}.btn{padding:9px 13px;border:1px solid #76501e;background:#795324;color:#fff0c8;font:inherit;font-weight:600;cursor:pointer}.notice,.error{padding:10px 12px;margin:0 0 16px}.notice{border:1px solid #658052;background:#dae7c7;color:#3e5836}.error{border:1px solid #a95d5a;background:#eed0bd;color:#762c30}.help{font-size:.86rem;color:#786145}.actions{display:flex;gap:14px;flex-wrap:wrap;margin-top:18px}@media(max-width:720px){body{padding:12px}.grid{grid-template-columns:1fr}.panel{padding:20px}}</style><?php favor_head('Mi cuenta · Favor de Jack'); ?>
</head><body class="favor-plain">
<?php favor_site_bar(); ?><div class="favor-pad"><div class="wrap"><main class="panel"><h1>Mi cuenta</h1><p class="identity"><?=h((string)$user['displayName'])?> · @<?=h((string)$user['username'])?><?=($user['role']??'')==='admin'?' · Administrador':''?></p><?php if($notice):?><div class="notice"><?=h($notice)?></div><?php endif?><?php if($error):?><div class="error"><?=h($error)?></div><?php endif?><div class="grid"><section class="card"><h2>Recordatorio de contraseña</h2><p class="help">Escribe una pregunta o una pista para tu yo del futuro. No pongas la contraseña literal.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(favor_csrf())?>"><input type="hidden" name="action" value="save_hint"><label class="field"><span>Pregunta o texto</span><textarea name="hint" maxlength="255" placeholder="Ej.: ¿Qué nombre tenía mi primer personaje?"><?=h($hint)?></textarea></label><button class="btn">Guardar recordatorio</button></form></section><section class="card"><h2>Cambiar contraseña</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(favor_csrf())?>"><input type="hidden" name="action" value="change_password"><label class="field"><span>Contraseña actual</span><input type="password" name="current_password" required autocomplete="current-password"></label><label class="field"><span>Nueva contraseña</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label><label class="field"><span>Repetir contraseña</span><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label><button class="btn">Cambiar contraseña</button></form></section></div><div class="actions"><a href="<?= favor_base_path() ?>olvide-clave.php">Ver pantalla de recordatorio</a></div></main></div></div></body></html>
