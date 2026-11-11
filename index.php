<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/auth-widget.php';

favor_session_start();
$sessionUser = favor_current_user();
favor_session_close();

$routes = [
    'bank'        => favor_base_path() . 'bancadigital/',
    'armory'      => favor_base_path() . 'armeria/',
    'inventory'   => favor_base_path() . 'inventario/',
    'dice'        => favor_base_path() . 'rolldice/',
    'files'       => favor_base_path() . 'archivosdelgremio/',
    'missions'    => favor_base_path() . 'tablon/',
    'map'         => favor_base_path() . 'mapa/',
    'regulations' => favor_base_path() . 'regulaciones/',
    'timeline'    => favor_base_path() . 'cronicas/',
];

function route(string $key, array $routes): string
{
    return htmlspecialchars($routes[$key] ?? '#', ENT_QUOTES, 'UTF-8');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b0806">
<title>Favor de Jack — Gremio de Aventureros</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cinzel+Decorative:wght@700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#090705;
    --panel:#12100d;
    --panel-2:#19140f;
    --bronze:#9d7842;
    --bronze-soft:#c3a16a;
    --bronze-dark:#5a4429;
    --cream:#eadfc9;
    --muted:#a99c87;
    --line:rgba(157,120,66,.54);
    --line-soft:rgba(195,161,106,.24);
    --shadow:0 18px 46px rgba(0,0,0,.52);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    min-height:100vh;
    color:var(--cream);
    font-family:'EB Garamond',Georgia,serif;
    background:
        radial-gradient(circle at 50% -8%,rgba(157,120,66,.09),transparent 28rem),
        linear-gradient(rgba(4,3,2,.26),rgba(4,3,2,.54)),
        repeating-linear-gradient(0deg,rgba(255,255,255,.012) 0 1px,transparent 1px 7px),
        #090705;
    overflow-x:hidden;
}
body::before{
    content:"";
    position:fixed;
    inset:0;
    pointer-events:none;
    z-index:-1;
    background:
        radial-gradient(ellipse at center,transparent 42%,rgba(0,0,0,.55) 100%),
        linear-gradient(90deg,rgba(0,0,0,.28),transparent 15%,transparent 85%,rgba(0,0,0,.28));
}
a{color:inherit;text-decoration:none}
button,summary{font:inherit}

.shell{
    width:min(1500px,calc(100% - 28px));
    margin:14px auto 28px;
    position:relative;
    padding:0 10px 34px;
}
.shell::after{
    content:"";
    display:block;
    width:min(760px,70vw);
    height:1px;
    margin:26px auto 0;
    background:linear-gradient(90deg,transparent,var(--bronze),transparent);
}

.header{
    min-height:168px;
    display:grid;
    place-items:center;
    text-align:center;
    position:relative;
    padding:28px 220px 18px;
}
.header::before,
.header::after{
    content:"";
    position:absolute;
    top:46px;
    width:min(31vw,480px);
    height:1px;
    background:linear-gradient(90deg,transparent,var(--bronze),transparent);
}
.header::before{left:0}
.header::after{right:0}
.brand{
    margin:0;
    font-family:'Cinzel Decorative','Cinzel',serif;
    font-size:clamp(2.7rem,5.5vw,5.4rem);
    line-height:1;
    letter-spacing:.045em;
    color:#b68d55;
    text-transform:uppercase;
    text-shadow:0 2px 0 #312316,0 0 18px rgba(182,141,85,.12);
}
.subtitle{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:14px;
    margin:11px 0 0;
    font-family:'Cinzel',serif;
    text-transform:uppercase;
    letter-spacing:.17em;
    font-size:clamp(.75rem,1.35vw,1.05rem);
    color:#c4ac86;
}
.subtitle::before,.subtitle::after{
    content:"";
    height:1px;
    width:min(170px,16vw);
    background:linear-gradient(90deg,transparent,var(--bronze),transparent);
}

.user-menu{
    position:absolute;
    z-index:30;
    top:24px;
    right:12px;
}
.user-menu summary{
    list-style:none;
    display:flex;
    align-items:center;
    gap:9px;
    min-height:44px;
    padding:5px 9px 5px 5px;
    border:1px solid transparent;
    border-radius:24px;
    color:#d9c6a4;
    cursor:pointer;
    user-select:none;
    transition:border-color .16s ease,background .16s ease;
}
.user-menu summary::-webkit-details-marker{display:none}
.user-menu summary:hover,.user-menu[open] summary{
    border-color:rgba(157,120,66,.46);
    background:rgba(20,16,12,.86);
}
.user-avatar{
    width:36px;
    height:36px;
    border-radius:50%;
    overflow:hidden;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    background:var(--avatar-bg,#5b554d);
    border:1px solid rgba(195,161,106,.72);
    box-shadow:0 0 0 2px #090705;
    font-size:1.15rem;
}
.user-avatar img{width:100%;height:100%;display:block;object-fit:cover}
.user-avatar .avatar-fallback{display:grid;place-items:center;width:100%;height:100%}
.user-name{
    max-width:130px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-family:'Cinzel',serif;
    font-size:.78rem;
    letter-spacing:.02em;
}
.user-crown{color:#b78d50;font-size:.82rem}
.user-chevron{width:14px;height:14px;opacity:.72;transition:transform .16s ease}
.user-menu[open] .user-chevron{transform:rotate(180deg)}
.user-popover{
    position:absolute;
    top:50px;
    right:0;
    width:210px;
    padding:8px;
    border:1px solid rgba(157,120,66,.5);
    border-radius:9px;
    background:rgba(14,11,8,.97);
    box-shadow:0 18px 40px rgba(0,0,0,.55);
    backdrop-filter:blur(8px);
}
.user-popover-head{
    padding:9px 10px 10px;
    border-bottom:1px solid rgba(157,120,66,.18);
    margin-bottom:5px;
}
.user-popover-head strong{display:block;color:#decaa6;font-family:'Cinzel',serif;font-size:.82rem}
.user-popover-head small{display:block;margin-top:2px;color:#8f836f}
.user-popover a{
    display:flex;
    align-items:center;
    gap:8px;
    padding:9px 10px;
    border-radius:6px;
    color:#c9baa1;
    font-size:.9rem;
}
.user-popover a:hover{background:#211912;color:#eee0c5}
.user-popover .danger{color:#c7a097}

.hero{
    position:relative;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:68px;
    padding:8px 12px 18px;
    align-items:stretch;
}
.scene{
    min-height:350px;
    border:1px solid var(--line);
    background:#080604;
    box-shadow:inset 0 0 0 4px #0b0806,inset 0 0 0 5px var(--line-soft),0 15px 28px rgba(0,0,0,.4);
    overflow:hidden;
    position:relative;
    border-radius:3px;
}
.scene img{
    width:100%;
    height:100%;
    display:block;
    object-fit:cover;
    transition:transform .55s ease,filter .55s ease;
}
.scene:hover img{transform:scale(1.018);filter:brightness(1.04)}
.scene::after{
    content:"";
    position:absolute;
    inset:0;
    pointer-events:none;
    background:linear-gradient(180deg,transparent 54%,rgba(4,3,2,.76) 100%);
}
.scene-copy{
    position:absolute;
    z-index:2;
    left:28px;
    right:24px;
    bottom:20px;
    display:grid;
    grid-template-columns:1fr auto;
    align-items:end;
    gap:16px;
}
.scene-title{
    font-family:'Cinzel',serif;
    color:#c7a56f;
    text-transform:uppercase;
    letter-spacing:.035em;
    font-size:clamp(1.2rem,2.1vw,1.9rem);
}
.scene-sub{
    margin-top:4px;
    color:#c3b7a2;
    font-family:'Cinzel',serif;
    text-transform:uppercase;
    letter-spacing:.14em;
    font-size:clamp(.62rem,.95vw,.8rem);
}
.scene-action{
    min-width:120px;
    padding:8px 16px;
    border:1px solid rgba(157,120,66,.7);
    background:rgba(8,6,4,.82);
    color:#ceb185;
    font-family:'Cinzel',serif;
    font-size:.73rem;
    letter-spacing:.07em;
    text-align:center;
}
.emblem{
    position:absolute;
    z-index:5;
    left:50%;
    top:50%;
    transform:translate(-50%,-50%);
    width:clamp(160px,17vw,250px);
    aspect-ratio:1;
    border-radius:50%;
    overflow:hidden;
    border:3px solid #8e6836;
    background:#0f0b08;
    box-shadow:0 0 0 5px #100c09,0 0 0 6px rgba(195,161,106,.34),0 14px 34px rgba(0,0,0,.64);
}
.emblem img{width:100%;height:100%;object-fit:cover;display:block;transform:scale(1.04)}

.nav-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    padding:5px 12px 0;
}
.nav-card{
    min-height:108px;
    border:1px solid rgba(157,120,66,.48);
    background:
        radial-gradient(circle at 15% 10%,rgba(157,120,66,.07),transparent 34%),
        linear-gradient(180deg,#17120e,#0f0c09);
    box-shadow:inset 0 0 0 3px #0a0806,inset 0 0 0 4px rgba(195,161,106,.10),0 10px 20px rgba(0,0,0,.25);
    display:grid;
    grid-template-columns:72px 1fr 20px;
    align-items:center;
    padding:15px 16px;
    position:relative;
    cursor:pointer;
    transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease;
}
.nav-card:hover,.nav-card:focus-visible{
    transform:translateY(-2px);
    border-color:rgba(195,161,106,.72);
    box-shadow:inset 0 0 0 3px #0a0806,inset 0 0 0 4px rgba(195,161,106,.15),0 14px 26px rgba(0,0,0,.36);
    outline:none;
}
.nav-card::before,.nav-card::after{
    content:"";
    position:absolute;
    width:14px;
    height:14px;
    border-color:rgba(195,161,106,.7);
    border-style:solid;
    opacity:.55;
}
.nav-card::before{left:6px;top:6px;border-width:1px 0 0 1px}
.nav-card::after{right:6px;bottom:6px;border-width:0 1px 1px 0}
.nav-card.coming-soon{
    cursor:not-allowed;
    opacity:.58;
    filter:saturate(.62);
}
.nav-card.coming-soon:hover{transform:none;border-color:rgba(157,120,66,.48);box-shadow:inset 0 0 0 3px #0a0806,inset 0 0 0 4px rgba(195,161,106,.10),0 10px 20px rgba(0,0,0,.25)}
.nav-card.coming-soon .nav-title::after{
    content:"Próximamente";
    display:table;
    margin:5px 0 2px;
    padding:2px 8px;
    border:1px solid rgba(195,161,106,.38);
    border-radius:9px;
    background:rgba(9,7,5,.8);
    color:#b8a182;
    font-size:.66rem;
    line-height:1.25;
    letter-spacing:.08em;
}
.nav-icon{
    width:50px;
    height:50px;
    display:grid;
    place-items:center;
    color:#b5915e;
    filter:drop-shadow(0 2px 3px #000);
}
.nav-icon svg{width:46px;height:46px;display:block}
.nav-title{
    position:relative;
    display:block;
    font-family:'Cinzel',serif;
    font-size:clamp(.95rem,1.35vw,1.2rem);
    letter-spacing:.045em;
    text-transform:uppercase;
    color:#c6a56f;
}
.nav-sub{display:block;margin-top:3px;font-size:.88rem;color:#a79a86}
.nav-arrow{color:#aa8757;font-size:1.5rem;line-height:1;text-align:center;transform:translateY(-1px)}

@media(max-width:1080px){
    .header{padding-left:150px;padding-right:150px}
    .hero{gap:34px}.scene{min-height:315px}.nav-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:760px){
    .shell{width:min(100% - 14px,1500px);margin-top:7px;padding-bottom:22px}
    .header{min-height:145px;padding:24px 12px 18px;align-items:start}
    .brand{margin-top:22px}.header::before,.header::after{display:none}
    .user-menu{top:9px;right:2px}.user-name{display:none}.user-crown{display:none}
    .hero{grid-template-columns:1fr;gap:12px;padding:8px 4px 12px}.scene{min-height:280px}
    /* En el móvil el emblema va encima de las imágenes, sin taparlas. */
    .emblem{position:relative;left:auto;top:auto;order:-1;width:120px;margin:0 auto 4px;transform:none}
    .scene-copy{left:18px;right:18px;bottom:15px}.scene-action{min-width:94px;padding:7px 10px}
    .nav-grid{padding:4px;gap:10px}
}
@media(max-width:520px){
    .brand{font-size:2.35rem;letter-spacing:.025em}.subtitle{font-size:.64rem;gap:7px}
    .scene{min-height:235px}.scene-copy{grid-template-columns:1fr}.scene-action{display:none}
    .nav-grid{grid-template-columns:1fr}.nav-card{min-height:88px;grid-template-columns:58px 1fr 18px;padding:12px 13px}
    .nav-icon{width:42px;height:42px}.nav-icon svg{width:38px;height:38px}
    .user-popover{position:fixed;right:8px;top:60px;width:min(220px,calc(100vw - 16px))}
}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
</style>
<?php favor_head('Favor de Jack · Gremio de Aventureros'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<main class="shell">
    <header class="header">
        <div>
            <h1 class="brand">Favor de Jack</h1>
            <div class="subtitle">Gremio de Aventureros</div>
        </div>
    </header>

    <section class="hero" aria-label="Accesos principales">
        <a class="scene" href="https://app.roll20.net/campaigns/details/20586133/-" target="_blank" rel="noopener noreferrer" aria-label="Abrir Sede del Gremio en Roll20">
            <img src="assets/sede-gremio.webp" alt="Sede del Gremio" fetchpriority="high">
            <div class="scene-copy">
                <div>
                    <div class="scene-title">Sede del Gremio</div>
                    <div class="scene-sub">Nuestro hogar, siempre abierto.</div>
                </div>
                <span class="scene-action">Entrar &nbsp;›</span>
            </div>
        </a>

        <div class="emblem" aria-hidden="true">
            <img src="<?= favor_static(FAVOR_LOGO) ?>" alt="">
        </div>

        <a class="scene" href="<?= route('map', $routes) ?>" aria-label="Abrir Waterdeep">
            <img src="assets/waterdeep.webp" alt="Vista de Waterdeep" fetchpriority="high">
            <div class="scene-copy">
                <div>
                    <div class="scene-title">Waterdeep</div>
                    <div class="scene-sub">Intrigas, oportunidades, aventuras.</div>
                </div>
                <span class="scene-action">Entrar &nbsp;›</span>
            </div>
        </a>
    </section>

    <nav class="nav-grid" aria-label="Servicios del gremio">
        <a class="nav-card" href="<?= route('bank', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2"><ellipse cx="20" cy="42" rx="13" ry="5"/><path d="M7 42v9c0 3 6 5 13 5s13-2 13-5v-9M10 35c2-2 6-3 10-3 7 0 13 2 13 5s-6 5-13 5-13-2-13-5c0-.8.6-1.5 1.6-2.2"/><ellipse cx="43" cy="28" rx="12" ry="5"/><path d="M31 28v9c0 3 5 5 12 5s12-2 12-5v-9M34 21c2-2 5-3 9-3 7 0 12 2 12 5s-5 5-12 5-12-2-12-5c0-.7.4-1.3 1.2-1.9"/></svg></span>
            <span><span class="nav-title">Banca Digital</span><span class="nav-sub">Monedas, transferencias e historial.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('armory', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9l31 31M9 12l3-3M40 36l5 5-4 4-5-5M45 41l7 7M49 53l4-4"/><path d="M52 9 21 40M55 12l-3-3M24 36l-5 5 4 4 5-5M19 41l-7 7M15 53l-4-4"/></svg></span>
            <span><span class="nav-title">Armas y Armaduras</span><span class="nav-sub">Catálogo de armas, armaduras y escudos.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('inventory', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><path d="M21 20v-4a11 11 0 0 1 22 0v4"/><path d="M16 20h32l4 34H12l4-34Z"/><path d="M22 31h20v15H22z"/><path d="M27 31v-4h10v4"/></svg></span>
            <span><span class="nav-title">Inventario</span><span class="nav-sub">Equipo y objetos por personaje.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('dice', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linejoin="round"><path d="M32 7 54 20 48 47 32 57 16 47 10 20 32 7Z"/><path d="M10 20h44M16 47l16-40 16 40M16 47h32M10 20l22 37 22-37"/></svg></span>
            <span><span class="nav-title">Roll Dice</span><span class="nav-sub">Tiradas rápidas y registro de dados.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('files', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><path d="M8 18h18l5 6h25v26H8V18Z"/><path d="M8 24h48"/></svg></span>
            <span><span class="nav-title">Archivos</span><span class="nav-sub">Archivos del Gremio.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('missions', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><rect x="6" y="9" width="52" height="46" rx="2"/><path d="M14 17h16v20l-3 3H14z"/><path d="M35 19h15v26H35z"/><circle cx="22" cy="17" r="1.6"/><circle cx="42.5" cy="19" r="1.6"/></svg></span>
            <span><span class="nav-title">Tablón de Misiones</span><span class="nav-sub">Encargos y trabajos del gremio.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('regulations', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M32 9v45M20 14h24M14 24h12l-6 13-6-13Zm24 0h12l-6 13-6-13Z"/><path d="M12 39h16M36 39h16M22 54h20"/></svg></span>
            <span><span class="nav-title">Regulaciones</span><span class="nav-sub">Subclases baneadas · Buffeos · Nerfeos.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <a class="nav-card" href="<?= route('timeline', $routes) ?>">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 8h24M20 56h24M23 8c0 12 5 17 9 20-4 3-9 8-9 20M41 8c0 12-5 17-9 20 4 3 9 8 9 20"/></svg></span>
            <span><span class="nav-title">Línea de Tiempo</span><span class="nav-sub">Historia del gremio y sus personajes.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </a>

        <div class="nav-card coming-soon" aria-disabled="true" title="Funciones Beta — Próximamente">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M24 8h16M28 8v15L14 48a6 6 0 0 0 5 9h26a6 6 0 0 0 5-9L36 23V8"/><path d="M21 43h22"/><circle cx="27" cy="47" r="2"/><circle cx="38" cy="38" r="2"/></svg></span>
            <span><span class="nav-title">Funciones Beta</span><span class="nav-sub">Experimentos y herramientas en desarrollo.</span></span>
            <span class="nav-arrow" aria-hidden="true">›</span>
        </div>
    </nav>
</main>
</body>
</html>
