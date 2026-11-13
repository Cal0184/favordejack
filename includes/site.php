<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assets.php';

/*
 * Favor de Jack · piezas comunes de todas las páginas.
 *
 *   favor_head()     → dentro de <head>: favicon, vista previa para Discord/WhatsApp,
 *                      noindex y la hoja de estilos común.
 *   favor_site_bar() → justo después de <body>: barra superior (volver, logo, menú,
 *                      sesión) y, en el móvil, la barra de navegación de abajo.
 *
 * Los enlaces del menú y a dónde lleva «Volver» se deciden aquí, en un solo sitio.
 */

/** Secciones del menú principal: clave => [texto, ruta, icono]. */
function favor_nav_items(): array
{
    return [
        'inicio' => ['Inicio', '', 'home'],
        'banca' => ['Banca', 'bancadigital/', 'coins'],
        'archivos' => ['Archivos', 'archivosdelgremio/', 'folder'],
        'inventario' => ['Inventario', 'inventario/', 'bag'],
        'misiones' => ['Misiones', 'tablon/', 'board'],
        'dados' => ['Dados', 'rolldice/', 'die'],
        'mapa' => ['Mapa y tiendas', 'mapa/', 'map'],
        'reglamento' => ['Reglamento', 'regulaciones/', 'scales'],
        'cronicas' => ['Crónicas', 'cronicas/', 'hourglass'],
    ];
}

/** Lo que va en la barra de abajo del móvil (el resto queda en «Más»). */
const FAVOR_NAV_MOBILE = ['inicio', 'banca', 'archivos', 'dados'];

/**
 * Página actual según el archivo PHP que se está ejecutando:
 * [sección del menú, ruta de «Volver», texto de «Volver», tema de color].
 */
function favor_page_context(): array
{
    $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $page = str_starts_with($script, $root . DIRECTORY_SEPARATOR)
        ? str_replace(DIRECTORY_SEPARATOR, '/', substr($script, strlen($root) + 1))
        : 'index.php';

    $hub = ['', 'al inicio'];
    $map = ['mapa/', 'al mapa'];
    $files = ['archivosdelgremio/', 'a Archivos'];
    $account = ['cuenta.php', 'a Mi cuenta'];
    $emporio = ['tiendas/emporio/', 'al Emporio'];

    $pages = [
        'index.php' => ['inicio', null, 'gremio'],
        'bancadigital/index.php' => ['banca', $hub, 'gremio'],
        'bancadigital/app.php' => ['banca', $hub, 'gremio'],
        'archivosdelgremio/index.php' => ['archivos', $hub, 'gremio'],
        'archivosdelgremio/hoja.php' => ['archivos', $files, 'gremio'],
        'archivosdelgremio/relaciones.php' => ['archivos', $files, 'gremio'],
        'archivosdelgremio/importar-ficha.php' => ['archivos', $files, 'gremio'],
        'inventario/index.php' => ['inventario', $hub, 'bosque'],
        'rolldice/index.php' => ['dados', $hub, 'gremio'],
        'tablon/index.php' => ['misiones', $hub, 'gremio'],
        'mapa/index.php' => ['mapa', $hub, 'gremio'],
        'regulaciones/index.php' => ['reglamento', $hub, 'gremio'],
        'cronicas/index.php' => ['cronicas', $hub, 'gremio'],
        'perfil.php' => ['perfil', $hub, 'gremio'],
        'cuenta.php' => ['cuenta', $hub, 'gremio'],
        'login.php' => ['cuenta', $hub, 'gremio'],
        'olvide-clave.php' => ['cuenta', ['login.php', 'a Entrar'], 'gremio'],
        'logout.php' => ['cuenta', $hub, 'gremio'],
        'admin/index.php' => ['cuenta', $hub, 'gremio'],
        'admin/respaldo.php' => ['cuenta', is_file(dirname(__DIR__) . '/admin/index.php') ? ['admin/', 'al Panel del DM'] : $account, 'gremio'],
        'admin/imagenes.php' => ['cuenta', $account, 'gremio'],
        'timetoplay/index.php' => ['wiki', $hub, 'gremio'],
        'tiendas/descuentos.php' => ['mapa', $map, 'gremio'],
        'tiendas/balas-y-sangre/index.php' => ['mapa', $map, 'balas'],
        'tiendas/ladamadelasllamas/index.php' => ['mapa', $map, 'llamas'],
        'tiendas/lapocionpicante/index.php' => ['mapa', $map, 'pocion'],
        'tiendas/lavaritaeconomica/index.php' => ['mapa', $map, 'varita'],
        'tiendas/gremio/index.php' => ['mapa', $map, 'gremio'],
        'tiendas/emporio/index.php' => ['mapa', $map, 'emporio'],
        'tiendas/emporio/admin.php' => ['mapa', $emporio, 'emporio'],
        'tiendas/emporio/pedidos.php' => ['mapa', $emporio, 'emporio'],
    ];
    [$section, $back, $theme] = $pages[$page] ?? ['', $hub, 'gremio'];
    // Una página puede pedir un «Volver» más preciso: $GLOBALS['favor_back'] = [ruta, texto].
    if (isset($GLOBALS['favor_back']) && is_array($GLOBALS['favor_back'])) $back = $GLOBALS['favor_back'];
    return ['page' => $page, 'section' => $section, 'back' => $back, 'theme' => $theme];
}

function favor_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dirección completa (con https://dominio) para la vista previa de Discord/WhatsApp. */
function favor_absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return $path;
    return ($https ? 'https://' : 'http://') . $host . $path;
}

/**
 * Etiquetas comunes del <head>. $title y $description son opcionales: si no se
 * dan, la vista previa usa el nombre del gremio.
 */
function favor_head(string $title = '', string $description = ''): void
{
    $siteName = 'Favor de Jack';
    $title = trim($title) !== '' ? trim($title) : 'Favor de Jack · Gremio de Aventureros';
    $description = trim($description) !== '' ? trim($description) : 'Banca, archivos, dados y tiendas de la campaña del Favor de Jack.';
    $uri = (string)($_SERVER['REQUEST_URI'] ?? favor_base_path());
    $uri = preg_replace('/[?#].*$/s', '', $uri) ?? favor_base_path();
    $image = favor_absolute_url(favor_base_path() . 'assets/vista-previa.jpg');
    ?>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= favor_h(favor_static('assets/favicon.ico')) ?>" sizes="any">
<link rel="icon" type="image/webp" href="<?= favor_h(favor_static(FAVOR_LOGO)) ?>">
<link rel="apple-touch-icon" href="<?= favor_h(favor_static('assets/apple-touch-icon.png')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= favor_h($siteName) ?>">
<meta property="og:locale" content="es_CL">
<meta property="og:title" content="<?= favor_h($title) ?>">
<meta property="og:description" content="<?= favor_h($description) ?>">
<meta property="og:url" content="<?= favor_h(favor_absolute_url($uri)) ?>">
<meta property="og:image" content="<?= favor_h($image) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Emblema del Favor de Jack sobre la Sede del Gremio">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=EB+Garamond:wght@400;600&display=swap">
<link rel="stylesheet" href="<?= favor_h(favor_static('assets/favor-ui.css')) ?>">
<script src="<?= favor_h(favor_static('assets/favor-ui.js')) ?>" defer></script>
<?php
}

function favor_nav_icon(string $name): string
{
    $paths = [
        'home' => '<path d="M4 11 12 4l8 7"/><path d="M6 10v10h12V10"/><path d="M10 20v-6h4v6"/>',
        'coins' => '<ellipse cx="9" cy="15" rx="6" ry="2.5"/><path d="M3 15v3c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-3"/><ellipse cx="15" cy="8" rx="6" ry="2.5"/><path d="M9 8v3c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V8"/>',
        'folder' => '<path d="M3 6h7l2 2h9v11H3z"/><path d="M3 10h18"/>',
        'bag' => '<path d="M8 8V6a4 4 0 0 1 8 0v2"/><path d="M5 8h14l1.5 13h-17z"/>',
        'die' => '<path d="m12 2 9 5v10l-9 5-9-5V7z"/><path d="m3 7 9 5 9-5M12 12v10"/>',
        'map' => '<path d="m3 6 6-2 6 2 6-2v14l-6 2-6-2-6 2z"/><path d="M9 4v14M15 6v14"/>',
        'scales' => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="m5 7-3 6h6zM19 7l-3 6h6z"/>',
        'hourglass' => '<path d="M6 3h12M6 21h12M7 3c0 5 3 7 5 9-2 2-5 4-5 9M17 3c0 5-3 7-5 9 2 2 5 4 5 9"/>',
        'board' => '<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M7 8h5v6H7zM14 9h4v8h-4z"/>',
        'more' => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 4-7 8-7s7 2 8 7"/>',
        'back' => '<path d="M15 5 8 12l7 7"/>',
    ];
    return '<svg class="favor-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Barra común. Usa la sesión leída al cargar auth-widget.php o este archivo. */
function favor_site_bar(): void
{
    static $printed = false;
    if ($printed) return;
    $printed = true;

    // Sesión leída al cargar auth-widget.php (null = invitado); no se reabre aquí.
    $user = array_key_exists('__favor_auth_widget_user', $GLOBALS) ? $GLOBALS['__favor_auth_widget_user'] : null;
    $isAdmin = $user && ($user['role'] ?? '') === 'admin';
    $ctx = favor_page_context();
    $base = favor_base_path();
    $items = favor_nav_items();
    $next = (string)($_SERVER['REQUEST_URI'] ?? $base);
    $loginUrl = $base . 'login.php?next=' . rawurlencode($next);

    $accountLinks = [];
    if ($user) {
        $accountLinks[] = ['Mi perfil', 'perfil.php'];
        $accountLinks[] = ['Mi cuenta', 'cuenta.php'];
        if ($isAdmin) {
            if (is_file(dirname(__DIR__) . '/admin/index.php')) $accountLinks[] = ['Panel del DM', 'admin/'];
            $accountLinks[] = ['Wiki del DM', 'timetoplay/'];
            $accountLinks[] = ['Admin del Emporio', 'tiendas/emporio/admin.php'];
            $accountLinks[] = ['Descuentos', 'tiendas/descuentos.php'];
            $accountLinks[] = ['Almacén del Gremio', 'tiendas/gremio/#almacen'];
            $accountLinks[] = ['Respaldo de la web', 'admin/respaldo.php'];
            $accountLinks[] = ['Ordenar imágenes', 'admin/imagenes.php'];
        }
    }
    $initial = $user ? mb_strtoupper(mb_substr((string)$user['displayName'], 0, 1, 'UTF-8'), 'UTF-8') : '';
    ?>
<a class="favor-skip" href="#favor-contenido">Saltar al contenido</a>
<header class="favor-bar" data-favor-theme="<?= favor_h($ctx['theme']) ?>">
    <div class="favor-bar-inner">
        <?php if ($ctx['back']): ?>
            <a class="favor-btn favor-back" href="<?= favor_h($base . $ctx['back'][0]) ?>"><?= favor_nav_icon('back') ?><span>Volver<span class="favor-back-to"> <?= favor_h($ctx['back'][1]) ?></span></span></a>
        <?php endif; ?>
        <a class="favor-brand" href="<?= favor_h($base) ?>" aria-label="Favor de Jack, inicio">
            <img src="<?= favor_h(favor_static(FAVOR_LOGO)) ?>" alt="" width="34" height="34">
            <span>Favor de Jack</span>
        </a>
        <nav class="favor-menu" aria-label="Secciones">
            <?php foreach ($items as $key => [$label, $path, $icon]): if ($key === 'inicio') continue; ?>
                <a href="<?= favor_h($base . $path) ?>"<?= $ctx['section'] === $key ? ' aria-current="page"' : '' ?>><?= favor_h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="favor-account">
            <?php if ($user): ?>
                <button class="favor-btn favor-account-toggle" type="button" aria-expanded="false" aria-controls="favor-account-menu">
                    <span class="favor-avatar" aria-hidden="true"<?= !empty($user['iconBg']) ? ' style="background:' . favor_h($user['iconBg']) . '"' : '' ?>><?php $favorIcon = favor_asset_existing((string)($user['iconUrl'] ?? '')); if ($favorIcon !== ''): ?><img src="<?= favor_h(favor_asset_url($favorIcon)) ?>" alt="" onerror="this.remove()"><?php endif; ?><span><?= favor_h(!empty($user['emoji']) ? (string)$user['emoji'] : $initial) ?></span></span>
                    <span class="favor-account-name"><?= favor_h((string)$user['displayName']) ?><?= $isAdmin ? ' · Admin' : '' ?></span>
                </button>
                <div class="favor-popover" id="favor-account-menu" hidden>
                    <p class="favor-popover-head"><strong><?= favor_h((string)$user['displayName']) ?></strong><small><?= $isAdmin ? 'Administrador' : 'Aventurero' ?></small></p>
                    <?php foreach ($accountLinks as [$label, $path]): ?>
                        <a href="<?= favor_h($base . $path) ?>"><?= favor_h($label) ?></a>
                    <?php endforeach; ?>
                    <form method="post" action="<?= favor_h($base . 'logout.php') ?>" id="favor-logout-form">
                        <input type="hidden" name="csrf" value="<?= favor_h(favor_csrf_if_session()) ?>">
                        <input type="hidden" name="next" value="<?= favor_h($base) ?>">
                        <button type="submit" class="favor-danger">Cerrar sesión</button>
                    </form>
                </div>
            <?php else: ?>
                <a class="favor-btn favor-login" href="<?= favor_h($loginUrl) ?>"><?= favor_nav_icon('user') ?><span>Entrar</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<nav class="favor-tabbar" data-favor-theme="<?= favor_h($ctx['theme']) ?>" aria-label="Navegación rápida">
    <?php foreach (FAVOR_NAV_MOBILE as $key): [$label, $path, $icon] = $items[$key]; ?>
        <a href="<?= favor_h($base . $path) ?>"<?= $ctx['section'] === $key ? ' aria-current="page"' : '' ?>><?= favor_nav_icon($icon) ?><span><?= favor_h($label) ?></span></a>
    <?php endforeach; ?>
    <button type="button" class="favor-more-toggle" aria-expanded="false" aria-controls="favor-more"<?= isset($items[$ctx['section']]) && !in_array($ctx['section'], FAVOR_NAV_MOBILE, true) ? ' data-current="1"' : '' ?>><?= favor_nav_icon('more') ?><span>Más</span></button>
</nav>
<div class="favor-sheet" id="favor-more" data-favor-theme="<?= favor_h($ctx['theme']) ?>" role="dialog" aria-modal="true" aria-label="Más secciones" hidden>
    <div class="favor-sheet-panel">
        <div class="favor-sheet-grid">
            <?php foreach ($items as $key => [$label, $path, $icon]): if (in_array($key, FAVOR_NAV_MOBILE, true)) continue; ?>
                <a href="<?= favor_h($base . $path) ?>"<?= $ctx['section'] === $key ? ' aria-current="page"' : '' ?>><?= favor_nav_icon($icon) ?><span><?= favor_h($label) ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="favor-sheet-list">
            <?php if ($user): ?>
                <p class="favor-popover-head"><strong><?= favor_h((string)$user['displayName']) ?></strong><small><?= $isAdmin ? 'Administrador' : 'Aventurero' ?></small></p>
                <?php foreach ($accountLinks as [$label, $path]): ?>
                    <a href="<?= favor_h($base . $path) ?>"><?= favor_h($label) ?></a>
                <?php endforeach; ?>
                <button type="submit" form="favor-logout-form" class="favor-danger">Cerrar sesión</button>
            <?php else: ?>
                <a href="<?= favor_h($loginUrl) ?>">Entrar</a>
                <a href="<?= favor_h($base . 'olvide-clave.php') ?>">Olvidé mi contraseña</a>
            <?php endif; ?>
        </div>
        <button type="button" class="favor-btn favor-sheet-close">Cerrar</button>
    </div>
</div>
<?php if ($user && !empty($_SESSION['favor_must_change']) && $ctx['page'] !== 'cuenta.php'): ?>
<p class="favor-notice" role="status">Entraste con una contraseña temporal. <a href="<?= favor_h($base . 'cuenta.php') ?>">Cámbiala en Mi cuenta</a>.</p>
<?php endif; ?>
<span id="favor-contenido" tabindex="-1"></span>
<?php
}

/** Token CSRF sin abrir una sesión cerrada (las páginas la liberan pronto). */
function favor_csrf_if_session(): string
{
    $token = $_SESSION['favor_csrf'] ?? ($GLOBALS['__favor_csrf_token'] ?? '');
    return is_string($token) ? $token : '';
}
