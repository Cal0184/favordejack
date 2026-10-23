<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth-widget.php';
require_once __DIR__ . '/includes/clave.php';
favor_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$user = favor_current_user(true);
if (!$user) {
    header('Location: ' . favor_base_path() . 'login.php?next=' . rawurlencode(favor_base_path() . 'cuenta.php'), true, 303);
    exit;
}
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$isAdmin = ($user['role'] ?? '') === 'admin';
$error=''; $notice=''; $tempPassword=''; $tempFor=''; $pending=[]; $accounts=[]; $mustChange=false;
$id=(int)$user['id'];
$db = favor_db();
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        favor_verify_csrf((string)($_POST['csrf'] ?? ''));
        $action=(string)($_POST['action'] ?? '');
        if ($action==='change_password') {
            $current=(string)($_POST['current_password'] ?? ''); $new=(string)($_POST['new_password'] ?? ''); $confirm=(string)($_POST['confirm_password'] ?? '');
            if (strlen($new)<8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            if ($new!==$confirm) throw new RuntimeException('Las contraseñas nuevas no coinciden.');
            $stmt=$db->prepare('SELECT PasswordHash,Salt,PasswordIterations FROM Usuario WHERE ID=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $pw=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$pw || !favor_password_matches($pw,$current)) throw new RuntimeException('La contraseña actual no es correcta.');
            if (favor_password_matches($pw,$new)) throw new RuntimeException('La nueva contraseña tiene que ser distinta de la actual.');
            $salt=bin2hex(random_bytes(16)); $iterations=max(200000,(int)($pw['PasswordIterations'] ?? 200000)); $hash=hash_pbkdf2('sha256',$new,$salt,$iterations,64,false);
            $stmt=$db->prepare('UPDATE Usuario SET PasswordHash=?,Salt=?,PasswordIterations=? WHERE ID=?'); $stmt->bind_param('ssii',$hash,$salt,$iterations,$id); $stmt->execute(); $stmt->close();
            favor_reset_done($db,$id);
            unset($_SESSION['favor_must_change']);
            $_SESSION['favor_csrf']=bin2hex(random_bytes(24));
            $notice='Contraseña actualizada.';
        } elseif ($action==='reset_password') {
            if (!$isAdmin) throw new RuntimeException('Solo el DM puede poner contraseñas temporales.');
            $target=(int)($_POST['user_id'] ?? 0);
            $tempPassword=favor_reset_issue($db,$target,(string)$user['displayName']);
            $stmt=$db->prepare('SELECT Username FROM Usuario WHERE ID=?'); $stmt->bind_param('i',$target); $stmt->execute(); $tempFor=(string)($stmt->get_result()->fetch_assoc()['Username'] ?? ''); $stmt->close();
        }
    }
    $mustChange=favor_reset_must_change($db,$id);
    if ($isAdmin) {
        $pending=favor_reset_pending($db);
        $result=$db->query('SELECT u.ID,u.Username,j.Nombre AS Jugador FROM Usuario u LEFT JOIN Jugador j ON j.ID=u.Jugador WHERE u.Activo=1 ORDER BY u.Username');
        $accounts=$result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch (Throwable $e) { $error=$e->getMessage(); }
finally { $db->close(); }
$csrf=h(favor_csrf());
favor_session_close();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi cuenta · Favor de Jack</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:26px;color:#eadcb9;font:18px 'EB Garamond',Georgia,serif;background:radial-gradient(circle at top,#4b2d1a,#17100c 48%,#080605)}.wrap{width:min(850px,100%);margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.top a{color:#edce91;text-decoration:none}.actions a{color:#6d3b2d;font-weight:600;text-decoration:none}.panel{padding:28px;border:1px solid #98703a;border-radius:12px;background:#e8d7ad;color:#312217;box-shadow:0 20px 55px #0008}.panel h1,.panel h2{font-family:'Cinzel',serif;color:#522d22}.panel h1{margin:0}.identity{margin:5px 0 24px;color:#72583d}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{padding:18px;border:1px solid #b49763;background:#f2e4c2}.card h2{margin-top:0;font-size:1.05rem}.field{display:grid;gap:5px;margin:10px 0}.field input,.field textarea{width:100%;padding:10px;border:1px solid #a48654;background:#fff7df;color:#2f2117;font:inherit}.field textarea{min-height:110px;resize:vertical}.btn{padding:9px 13px;border:1px solid #76501e;background:#795324;color:#fff0c8;font:inherit;font-weight:600;cursor:pointer}.notice,.error{padding:10px 12px;margin:0 0 16px}.notice{border:1px solid #658052;background:#dae7c7;color:#3e5836}.error{border:1px solid #a95d5a;background:#eed0bd;color:#762c30}.help{font-size:.86rem;color:#786145}.actions{display:flex;gap:14px;flex-wrap:wrap;margin-top:18px}@media(max-width:720px){body{padding:12px}.grid{grid-template-columns:1fr}.panel{padding:20px}}.warn{padding:12px 14px;margin:0 0 16px;border:1px solid #b07a2a;background:#f5dfae;color:#5a3a10}.temp{display:inline-block;margin-top:6px;padding:6px 12px;border:1px dashed #76501e;background:#fff7df;font:700 1.2rem monospace;color:#2d2118;user-select:all}.dm{margin-top:18px}.dm table{width:100%;border-collapse:collapse;font-size:.95rem}.dm td,.dm th{padding:7px 6px;border-bottom:1px solid #c9ad79;text-align:left}.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.inline select{padding:9px;border:1px solid #a48654;background:#fff7df;font:inherit;color:#2f2117}</style><?php favor_head('Mi cuenta · Favor de Jack'); ?>
</head><body class="favor-plain">
<?php favor_site_bar(); ?><div class="favor-pad"><div class="wrap"><main class="panel">
<h1>Mi cuenta</h1>
<p class="identity"><?=h((string)$user['displayName'])?> · @<?=h((string)$user['username'])?><?=$isAdmin?' · Administrador':''?></p>
<?php if($mustChange):?><div class="warn"><strong>Estás usando una contraseña temporal.</strong> Ponte una nueva aquí abajo: la temporal te la dio el DM y no debería quedarse.</div><?php endif?>
<?php if($notice):?><div class="notice"><?=h($notice)?></div><?php endif?>
<?php if($error):?><div class="error"><?=h($error)?></div><?php endif?>
<div class="grid">
<section class="card"><h2>Cambiar contraseña</h2><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="change_password"><label class="field"><span><?= $mustChange ? 'Contraseña temporal' : 'Contraseña actual' ?></span><input type="password" name="current_password" required autocomplete="current-password"></label><label class="field"><span>Nueva contraseña</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label><label class="field"><span>Repetir contraseña</span><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label><button class="btn">Cambiar contraseña</button></form></section>
<section class="card"><h2>Si olvidas tu contraseña</h2><p class="help">En «Olvidé mi contraseña» escribes tu usuario y le llega un aviso al DM. Él te da una contraseña temporal (por Discord, por ejemplo) y al entrar con ella la cambias aquí.</p><p class="help">Ya no hay pistas ni recordatorios visibles para cualquiera: así nadie puede adivinar tu contraseña con ellos.</p></section>
</div>
<?php if($isAdmin):?>
<section class="card dm"><h2>Contraseñas de los jugadores (DM)</h2>
<?php if($tempPassword!==''):?><div class="notice">Contraseña temporal para <strong>@<?=h($tempFor)?></strong>:<br><span class="temp"><?=h($tempPassword)?></span><br><span class="help">Pásasela al jugador. Solo se muestra esta vez; al entrar, la web le pedirá cambiarla.</span></div><?php endif?>
<?php if($pending):?><table><thead><tr><th>Pidió ayuda</th><th>Usuario</th><th></th></tr></thead><tbody>
<?php foreach($pending as $row):?><tr><td><?=h(date('d/m/Y H:i',strtotime((string)$row['Creado'])))?></td><td>@<?=h((string)$row['Username'])?><?= $row['Jugador'] ? ' · '.h((string)$row['Jugador']) : '' ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?=(int)$row['Usuario']?>"><button class="btn">Poner contraseña temporal</button></form></td></tr><?php endforeach?>
</tbody></table><?php else:?><p class="help">Nadie ha pedido ayuda con su contraseña.</p><?php endif?>
<form method="post" class="inline" style="margin-top:12px" onsubmit="return confirm('¿Cambiar la contraseña de esta cuenta por una temporal?')"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="reset_password"><label for="reset-user">Otra cuenta:</label><select id="reset-user" name="user_id"><?php foreach($accounts as $acc):?><option value="<?=(int)$acc['ID']?>">@<?=h((string)$acc['Username'])?><?= $acc['Jugador'] ? ' · '.h((string)$acc['Jugador']) : '' ?></option><?php endforeach?></select><button class="btn">Poner contraseña temporal</button></form>
</section>
<?php endif?>
</main></div></div></body></html>
