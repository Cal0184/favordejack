<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

// Toma la identidad mientras todavía se pueden enviar cabeceras. Las páginas
// pueden liberar la sesión inmediatamente después y el widget no la reabre al
// final del HTML (eso volvería a bloquear requests y puede fallar si ya hubo output).
if (!array_key_exists('__favor_auth_widget_user', $GLOBALS)) {
    $GLOBALS['__favor_auth_widget_user'] = favor_current_user();
}

function favor_auth_widget(): void
{
    $user = $GLOBALS['__favor_auth_widget_user'] ?? null;
    ?>
    <style>
    .favor-auth-widget{position:fixed;z-index:9999;right:14px;bottom:14px;display:flex;align-items:center;gap:7px;max-width:calc(100vw - 28px);padding:7px 8px;border:1px solid rgba(198,151,58,.62);border-radius:9px;background:rgba(18,12,8,.94);box-shadow:0 8px 25px rgba(0,0,0,.48);backdrop-filter:blur(7px);font:14px Georgia,serif;color:#efdcb5}.favor-auth-widget span{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.favor-auth-widget a{display:inline-flex;align-items:center;min-height:30px;padding:5px 9px;border:1px solid rgba(198,151,58,.42);border-radius:6px;background:#2b1b10;color:#efd09a!important;text-decoration:none!important}.favor-auth-widget a:hover{border-color:#e3c073;background:#392516}.favor-auth-widget .favor-auth-exit{color:#d9b4a4!important}@media(max-width:520px){.favor-auth-widget{left:8px;right:8px;bottom:8px;justify-content:center}.favor-auth-widget span{max-width:120px}}
    </style>
    <div class="favor-auth-widget" aria-label="Sesión de Favor de Jack">
        <?php if ($user): ?>
            <span><?= htmlspecialchars((string)$user['displayName'], ENT_QUOTES, 'UTF-8') ?><?= ($user['role'] ?? '') === 'admin' ? ' · Admin' : '' ?></span>
            <a href="<?= favor_base_path() ?>cuenta.php">Mi cuenta</a>
            <a class="favor-auth-exit" href="<?= favor_base_path() ?>logout.php">Salir</a>
        <?php else: ?>
            <span>Modo lectura</span>
            <a href="<?= favor_base_path() ?>login.php">Iniciar sesión</a>
        <?php endif; ?>
    </div>
    <?php
}
