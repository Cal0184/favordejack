<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/auth-widget.php';
require_once __DIR__ . '/includes/emojis.php';

favor_session_start();

$user = favor_current_user(true);
$csrf = favor_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    favor_verify_csrf((string)($_POST['csrf'] ?? ''));
}

favor_session_close();

$message = '';
$error = '';
$player = null;

function hp(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$allowedEmojis = favor_allowed_emojis();

if ($user) {
    $db = favor_db();

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $handle = trim((string)($_POST['handle'] ?? ''));
            $handle = ltrim($handle, '#');
            $handle = mb_substr($handle, 0, 30, 'UTF-8');

            $emoji = trim((string)($_POST['emoji'] ?? '🛡️'));
            if (!array_key_exists($emoji, $allowedEmojis)) {
                $emoji = '🛡️';
            }

            $bg = trim((string)($_POST['iconBg'] ?? '#22402F'));
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bg)) {
                $bg = '#22402F';
            }

            $icon = favor_safe_asset_input((string)($_POST['iconUrl'] ?? ''));
            if (trim((string)($_POST['iconUrl'] ?? '')) !== '' && $icon === '') {
                throw new RuntimeException(
                    'La URL del icono no es válida. Usa https:// o una ruta dentro de ' . favor_base_path() . 'media/.'
                );
            }

            $stmt = $db->prepare(
                'UPDATE Jugador SET Handle=?, Emoji=?, IconBg=?, IconUrl=? WHERE ID=?'
            );
            $stmt->bind_param(
                'sssss',
                $handle,
                $emoji,
                $bg,
                $icon,
                $user['playerId']
            );
            $stmt->execute();
            $stmt->close();

            $message = 'Perfil actualizado.';
        }

        $stmt = $db->prepare(
            'SELECT ID,Nombre,Handle,Emoji,IconBg,IconUrl,Activo
             FROM Jugador
             WHERE ID=?
             LIMIT 1'
        );
        $stmt->bind_param('s', $user['playerId']);
        $stmt->execute();
        $player = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    } finally {
        $db->close();
    }
}

$iconUrl = $player
    ? favor_asset_url((string)($player['IconUrl'] ?? ''))
    : '';

$bg = (
    $player
    && preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($player['IconBg'] ?? ''))
)
    ? (string)$player['IconBg']
    : '#22402F';

$emoji = $player
    ? trim((string)($player['Emoji'] ?? '🛡️'))
    : '🛡️';

if (!array_key_exists($emoji, $allowedEmojis)) {
    $emoji = '🛡️';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mi perfil · Favor de Jack</title>

    <style>
        :root{
            --bg:#0e0907;
            --panel:#17100c;
            --panel2:#23170f;
            --gold:#c79a46;
            --gold2:#e8c77d;
            --cream:#efe0bd;
            --muted:#ad9870;
            --line:#674823;
            --danger:#9b4b4b;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            min-height:100vh;
            color:var(--cream);
            font-family:Georgia,serif;
            background:
                radial-gradient(circle at 50% -15%,#5d3a181f,transparent 35rem),
                linear-gradient(#130c08,#080504);
        }

        a{color:inherit}

        .top{
            width:min(1120px,94vw);
            margin:18px auto 0;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
        }

        .top a{
            padding:9px 13px;
            border:1px solid var(--line);
            border-radius:7px;
            background:#1d130d;
            text-decoration:none;
            color:#dfc58f;
        }

        .shell{
            width:min(1120px,94vw);
            margin:16px auto 60px;
            display:grid;
            grid-template-columns:330px 1fr;
            gap:20px;
        }

        .card{
            border:1px solid #50381f;
            border-radius:16px;
            background:linear-gradient(145deg,#1c130e,#100b08);
            box-shadow:0 24px 70px #0008;
            overflow:hidden;
        }

        .preview{
            min-height:430px;
            display:grid;
            place-items:center;
            padding:30px;
            background:
                radial-gradient(
                    circle at 50% 25%,
                    color-mix(in srgb,var(--profile) 55%,transparent),
                    transparent 60%
                ),
                linear-gradient(#17100c,#0b0705);
        }

        .avatar{
            width:190px;
            height:190px;
            border-radius:50%;
            display:grid;
            place-items:center;
            overflow:hidden;
            border:4px solid #c49a4c;
            box-shadow:
                0 0 0 8px #120b07,
                0 22px 60px #0009;
            background:var(--profile);
            font-size:5rem;
        }

        .avatar img{
            width:100%;
            height:100%;
            object-fit:cover;
        }

        .preview h1{
            margin:22px 0 3px;
            color:#f0d8a5;
            font-size:2.25rem;
            text-align:center;
        }

        .preview p{
            margin:0;
            color:#a9916d;
        }

        .form{padding:28px}

        .form h2{
            margin:0;
            color:#e7c67d;
            font-size:2rem;
        }

        .form>p{color:#a99575}

        .fields{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
            margin-top:22px;
        }

        .field{
            display:grid;
            gap:6px;
        }

        .field.full{grid-column:1/-1}

        .field>span{
            color:#c8ad7c;
            font-size:.82rem;
            font-weight:700;
        }

        .field input{
            width:100%;
            padding:11px 12px;
            border:1px solid #614525;
            border-radius:7px;
            background:#0c0806;
            color:#f1dfba;
            font:inherit;
        }

        .field input[type=color]{
            padding:4px;
            height:46px;
        }

        .emoji-field{
            grid-column:1/-1;
        }

        .emoji-picker{
            display:grid;
            grid-template-columns:repeat(10,minmax(42px,1fr));
            gap:8px;
            padding:10px;
            border:1px solid #614525;
            border-radius:9px;
            background:#0c0806;
        }

        .emoji-option{
            min-width:0;
            height:48px;
            padding:0;
            border:1px solid #4d361f;
            border-radius:8px;
            background:#17100c;
            color:#fff;
            font:inherit;
            font-size:1.65rem;
            line-height:1;
            cursor:pointer;
            transition:
                transform .12s ease,
                border-color .12s ease,
                background .12s ease,
                box-shadow .12s ease;
        }

        .emoji-option:hover{
            transform:translateY(-1px);
            border-color:#a67a3b;
            background:#21160f;
        }

        .emoji-option.is-selected{
            border-color:#e1b65f;
            background:#3a2815;
            box-shadow:
                0 0 0 2px #e1b65f33,
                inset 0 0 18px #dca7471f;
        }

        .emoji-option:focus-visible{
            outline:2px solid #f1cf83;
            outline-offset:2px;
        }

        .actions{
            display:flex;
            justify-content:flex-end;
            gap:9px;
            margin-top:22px;
        }

        .button{
            padding:10px 15px;
            border:1px solid #805d2e;
            border-radius:7px;
            background:linear-gradient(#8b6531,#5b3d1e);
            color:#fff0c8;
            font:700 .9rem Georgia,serif;
            cursor:pointer;
        }

        .notice{
            margin-top:14px;
            padding:10px 12px;
            border:1px solid #577149;
            background:#132312;
            color:#c9e1b7;
        }

        .error{
            border-color:#854a48;
            background:#261311;
            color:#efb8b0;
        }

        .login{
            padding:50px;
            text-align:center;
        }

        .login h1{color:#e4c17b}

        .login a{
            display:inline-block;
            margin-top:12px;
            padding:11px 15px;
            border:1px solid #805d2e;
            border-radius:7px;
            background:#24170e;
            text-decoration:none;
        }

        .help{
            margin-top:18px;
            padding:12px;
            border-left:3px solid #8f6a34;
            background:#160f0a;
            color:#a99575;
            line-height:1.5;
        }

        @media(max-width:900px){
            .emoji-picker{
                grid-template-columns:repeat(5,minmax(44px,1fr));
            }
        }

        @media(max-width:760px){
            .shell{
                grid-template-columns:1fr;
            }

            .preview{
                min-height:310px;
            }

            .fields{
                grid-template-columns:1fr;
            }

            .field.full,
            .emoji-field{
                grid-column:auto;
            }
        }
    </style>
</head>

<body style="--profile:<?=hp($bg)?>">
<?php favor_auth_widget(); ?>

<div class="top">
    <a href="<?= favor_base_path() ?>">← Favor de Jack</a>
    <a href="<?= favor_base_path() ?>cuenta.php">Seguridad de la cuenta</a>
</div>

<?php if (!$user || !$player): ?>
    <main class="shell" style="display:block">
        <section class="card login">
            <h1>Mi perfil</h1>
            <p>Inicia sesión para personalizar tu perfil del gremio.</p>
            <a href="<?= favor_base_path() ?>login.php?next=<?= rawurlencode(favor_base_path()) ?>perfil.php">
                Iniciar sesión
            </a>
        </section>
    </main>
<?php else: ?>
    <main class="shell">
        <section class="card preview">
            <div>
                <div class="avatar" id="avatarPreview">
                    <?php if ($iconUrl): ?>
                        <img
                            id="avatarImg"
                            src="<?=hp($iconUrl)?>"
                            alt="Icono de <?=hp((string)$player['Nombre'])?>"
                            decoding="async"
                            onerror="this.style.display='none';document.getElementById('avatarEmoji').style.display='grid'"
                        >
                    <?php endif; ?>

                    <span
                        id="avatarEmoji"
                        style="<?= $iconUrl ? 'display:none' : 'display:grid' ?>;place-items:center;width:100%;height:100%"
                    ><?=hp($emoji)?></span>
                </div>

                <h1><?=hp((string)$player['Nombre'])?></h1>
                <p>#<?=hp((string)($player['Handle'] ?: 'sin-subnombre'))?></p>
            </div>
        </section>

        <section class="card form">
            <h2>Mi perfil</h2>
            <p>Estos datos se comparten con la Banca y las futuras herramientas del gremio.</p>

            <?php if ($message): ?>
                <div class="notice"><?=hp($message)?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notice error"><?=hp($error)?></div>
            <?php endif; ?>

            <form method="post" id="profileForm">
                <input type="hidden" name="csrf" value="<?=hp($csrf)?>">
                <input type="hidden" name="emoji" id="emojiInput" value="<?=hp($emoji)?>">

                <div class="fields">
                    <label class="field">
                        <span>Subnombre</span>
                        <input
                            name="handle"
                            maxlength="30"
                            value="<?=hp((string)($player['Handle'] ?? ''))?>"
                            placeholder="dragonrojo"
                        >
                    </label>

                    <label class="field">
                        <span>Color de fondo</span>
                        <input
                            type="color"
                            name="iconBg"
                            id="bgInput"
                            value="<?=hp($bg)?>"
                        >
                    </label>

                    <div class="field emoji-field">
                        <span>Emoji / símbolo</span>

                        <div
                            class="emoji-picker"
                            id="emojiPicker"
                            role="group"
                            aria-label="Selecciona un emoji para tu perfil"
                        >
                            <?php foreach ($allowedEmojis as $emojiValue => $emojiLabel): ?>
                                <button
                                    type="button"
                                    class="emoji-option<?= $emoji === $emojiValue ? ' is-selected' : '' ?>"
                                    data-emoji="<?=hp($emojiValue)?>"
                                    title="<?=hp($emojiLabel)?>"
                                    aria-label="<?=hp($emojiLabel)?>"
                                    aria-pressed="<?= $emoji === $emojiValue ? 'true' : 'false' ?>"
                                ><?=hp($emojiValue)?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label class="field full">
                        <span>URL del icono</span>
                        <input
                            name="iconUrl"
                            id="iconUrlInput"
                            maxlength="500"
                            value="<?=hp((string)($player['IconUrl'] ?? ''))?>"
                            placeholder="https://... o media/profiles/..."
                        >
                    </label>
                </div>

                <div class="help">
                    El emoji ahora se elige únicamente desde la lista disponible.
                    Si dejas la URL vacía, se usa el emoji seleccionado sobre el color elegido.
                    Las imágenes compartidas del proyecto viven bajo
                    <strong><?= favor_base_path() ?>media/</strong>.
                </div>

                <div class="actions">
                    <button class="button" type="submit">
                        Guardar perfil
                    </button>
                </div>
            </form>
        </section>
    </main>

    <script>
        const bg = document.getElementById('bgInput');
        const emoji = document.getElementById('emojiInput');
        const avatar = document.getElementById('avatarPreview');
        const emojiEl = document.getElementById('avatarEmoji');
        const emojiButtons = document.querySelectorAll('.emoji-option');

        function redraw() {
            if (bg && avatar) {
                document.body.style.setProperty('--profile', bg.value);
                avatar.style.background = bg.value;
            }

            if (emojiEl && emoji) {
                emojiEl.textContent = emoji.value || '🛡️';
            }
        }

        emojiButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const selectedEmoji = button.dataset.emoji || '🛡️';

                emoji.value = selectedEmoji;

                emojiButtons.forEach((item) => {
                    const selected = item === button;
                    item.classList.toggle('is-selected', selected);
                    item.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                redraw();
            });
        });

        bg?.addEventListener('input', redraw);
    </script>
<?php endif; ?>
</body>
</html>
