<?php
declare(strict_types=1);
session_start();

const CARTAS_DIR = __DIR__ . '/Cartas';
const DATA_DIR = __DIR__ . '/data';
const CATALOG_FILE = DATA_DIR . '/catalog.json';
const OVERRIDES_FILE = DATA_DIR . '/item_overrides.json';
const ADMIN_FILE = DATA_DIR . '/admin.php';

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function slugify(string $value): string {
    $value = pathinfo($value, PATHINFO_FILENAME);
    $value = mb_strtolower($value, 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') $value = $ascii;
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
    return trim($value, '-');
}

function catalog_aliases(): array {
    return [
        'abrochar-de-la-profundidad' => 'antorcha-de-la-profundidad',
        'aceite-de-resbaladicida' => 'aceite-de-resbaladicidad',
        'cuerda-escalada' => 'cuerda-de-escalada',
        'cuarda-de-escalada' => 'cuerda-de-escalada',
        'guantes-termanles' => 'guantes-termales',
        'anillo-catapulta' => 'anillo-de-catapulta',
        'bronntanas' => 'bronntanas-blade',
        'cinturon-de-fuerza-de-gigante' => 'cinturon-de-fuerza-de-gigante-fuerza-21',
        'tunica-del-horador' => 'tunica-del-orador',
        'guijarro-de-1-tonelada' => 'piedra-de-una-tonelada',
    ];
}

function canonical_slug(string $value): string {
    $slug = slugify($value);
    $aliases = catalog_aliases();
    return $aliases[$slug] ?? $slug;
}

function ignored_card_slugs(): array {
    return [
        'guantes-de-poder-del-ogro',
        'guantes-de-fuerza-de-ogro',
        'guantes-de-poder-de-ogro',
    ];
}

function pretty_name(string $file): string {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $name = preg_replace('/[_\-]+/u',' ',$name) ?? $name;
    $name = preg_replace('/\s+/u',' ',$name) ?? $name;
    $name = trim($name);
    if ($name !== '' && $name === mb_strtolower($name, 'UTF-8')) {
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
    return $name ?: 'Objeto sin nombre';
}

function load_json(string $file, array $fallback=[]): array {
    if(!is_file($file)) return $fallback;
    $raw=@file_get_contents($file);
    if($raw===false||trim($raw)==='') return $fallback;
    $data=json_decode($raw,true);
    return is_array($data)?$data:$fallback;
}

function normalize_catalog(array $raw): array {
    $out=[];
    foreach($raw as $key=>$row){
        if(!is_array($row)) continue;
        $rawId=trim((string)($row['id']??(is_string($key)?$key:'')));
        if($rawId==='') continue;
        $id=canonical_slug($rawId);
        if($id==='') continue;
        $row['id']=$id;
        $out[$id]=isset($out[$id])&&is_array($out[$id])?array_replace($out[$id],$row):$row;

    }
    return $out;
}

function save_json(string $file,array $data): bool {
    if(!is_dir(DATA_DIR)&&!@mkdir(DATA_DIR,0775,true)) return false;
    $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if($json===false)return false;
    $tmp=$file.'.tmp';
    if(@file_put_contents($tmp,$json,LOCK_EX)===false)return false;
    return @rename($tmp,$file);
}

function load_admin(): ?array {
    if(!is_file(ADMIN_FILE))return null;
    $data=require ADMIN_FILE;
    return is_array($data)?$data:null;
}

function source_value(array $saved,array $override,string $field,mixed $fallback): mixed {
    if(array_key_exists($field,$saved)&&$saved[$field]!==''&&$saved[$field]!==null) return $saved[$field];
    if(array_key_exists($field,$override)&&$override[$field]!==''&&$override[$field]!==null) return $override[$field];
    return $fallback;
}

function scan_cards(array $catalog,array $overrides): array {
    if(!is_dir(CARTAS_DIR))return[];
    $allowed=['png','webp','jpg','jpeg','gif'];
    $priority=['webp'=>5,'png'=>4,'jpg'=>3,'jpeg'=>2,'gif'=>1];
    $files=[];

    foreach(scandir(CARTAS_DIR)?:[] as $file){
        if($file==='.'||$file==='..')continue;
        $path=CARTAS_DIR.DIRECTORY_SEPARATOR.$file;
        if(!is_file($path))continue;
        $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
        if(!in_array($ext,$allowed,true))continue;

        $fileSlug=slugify($file);
        if(in_array($fileSlug,ignored_card_slugs(),true))continue;
        $id=canonical_slug($fileSlug);
        $p=$priority[$ext]??0;
        if(isset($files[$id])&&($files[$id]['priority']??0)>=$p)continue;
        $files[$id]=['file'=>$file,'priority'=>$p];
    }

    $cards=[];
    foreach($files as $id=>$meta){
        $file=(string)$meta['file'];
        $pretty=pretty_name($file);
        $prettySlug=canonical_slug($pretty);
        $saved=$catalog[$id]??$catalog[$prettySlug]??[];
        $override=$overrides[$id]??$overrides[$prettySlug]??[];
        if(!is_array($saved))$saved=[];
        if(!is_array($override))$override=[];

        $cards[]=[
            'id'=>$id,
            'file'=>$file,
            'url'=>'Cartas/'.rawurlencode($file),
            'name'=>trim((string)source_value($saved,$override,'name',$pretty))?:$pretty,
            'category'=>trim((string)source_value($saved,$override,'category','Sin categoría'))?:'Sin categoría',
            'price'=>trim((string)source_value($saved,$override,'price','?'))?:'?',
            'stock'=>max(0,(int)source_value($saved,$override,'stock',1)),
            'description'=>trim((string)source_value($saved,$override,'description','')),
            'available'=>(bool)source_value($saved,$override,'available',true),
        ];
    }
    usort($cards,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
    return $cards;
}

$admin=load_admin();
if($admin===null){header('Location: setup-admin.php');exit;}

$error='';$notice='';

if(isset($_GET['logout'])){
    $_SESSION=[];session_destroy();header('Location: admin.php');exit;
}

if(empty($_SESSION['emporio_admin'])){
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='login'){
        $u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');
        if(hash_equals((string)($admin['username']??''),$u)&&password_verify($p,(string)($admin['password_hash']??''))){
            session_regenerate_id(true);$_SESSION['emporio_admin']=true;header('Location: admin.php');exit;
        }
        $error='Usuario o contraseña incorrectos.';
    }
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin · Emporio</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0907;color:#eadcc0;font-family:Georgia,serif}main{width:min(430px,92vw);padding:28px;border:1px solid #72512e;border-radius:12px;background:#17100c}h1{color:#e8c785}label{display:grid;gap:6px;margin:15px 0}input{padding:11px;border:1px solid #5b4329;border-radius:6px;background:#090604;color:#fff;font:inherit}button,a{display:inline-block;padding:10px 15px;border:1px solid #8b6737;border-radius:7px;background:#2b1b10;color:#efd09a;text-decoration:none;font:inherit}.error{padding:10px;border:1px solid #8e4a44;background:#2a1412;color:#efbbb5}</style>
    </head><body><main><h1>Administración del Emporio</h1><?php if($error):?><div class="error"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required></label><label>Contraseña<input type="password" name="password" required></label><button>Entrar</button> <a href="index.php">Volver</a></form></main></body></html><?php
    exit;
}

$catalog=normalize_catalog(load_json(CATALOG_FILE));
$overrides=normalize_catalog(load_json(OVERRIDES_FILE));

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save'){
    $id=canonical_slug(trim((string)($_POST['id']??'')));
    if($id==='')$error='Objeto inválido.';
    else{
        $catalog[$id]=[
            'id'=>$id,
            'name'=>trim((string)($_POST['name']??'')),
            'category'=>trim((string)($_POST['category']??'Sin categoría'))?:'Sin categoría',
            'price'=>trim((string)($_POST['price']??'?'))?:'?',
            'stock'=>max(0,(int)($_POST['stock']??0)),
            'description'=>trim((string)($_POST['description']??'')),
            'available'=>isset($_POST['available']),
        ];
        if(save_json(CATALOG_FILE,$catalog))$notice='Cambios guardados. El catálogo quedó normalizado por ID.';
        else$error='No pude guardar data/catalog.json. Revisa permisos.';
    }
}

$cards=scan_cards($catalog,$overrides);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin · Emporio</title>
<style>
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#3e2818,#0d0907 45%);color:#eadcc0;font-family:Georgia,serif}header{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:15px;padding:14px 4vw;border-bottom:1px solid #5a4128;background:#100b08ee;backdrop-filter:blur(10px)}header h1{margin:0;color:#e5c37e;font-size:1.5rem}nav{display:flex;gap:8px;flex-wrap:wrap}a,button{padding:9px 13px;border:1px solid #76562f;border-radius:7px;background:#21150e;color:#ebcb91;text-decoration:none;font:inherit;cursor:pointer}main{width:min(1350px,94vw);margin:30px auto 70px}.toolbar{position:sticky;top:77px;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;padding:10px;border:1px solid #493724;border-radius:9px;background:#100b08ee;backdrop-filter:blur(10px)}.toolbar input{width:min(460px,100%);padding:11px;border:1px solid #5a4128;border-radius:7px;background:#0b0806;color:#fff}.notice,.error{padding:11px 13px;margin:10px 0;border-radius:7px}.notice{border:1px solid #61734e;background:#142013;color:#b9d4a6}.error{border:1px solid #864d48;background:#271311;color:#efb8b0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:16px}.card{display:grid;grid-template-columns:120px 1fr;gap:14px;padding:13px;border:1px solid #483522;border-radius:10px;background:#15100c;box-shadow:0 15px 35px #0005}.card img{width:120px;height:168px;object-fit:contain;background:#090604;border:1px solid #33251a}.card h2{margin:0 0 8px;color:#e4c388;font-size:1.05rem}label{display:grid;gap:4px;margin:7px 0;color:#a99880;font-size:.8rem}input,textarea{width:100%;padding:9px;border:1px solid #4b3825;border-radius:6px;background:#090604;color:#f4e5ca;font:inherit}textarea{min-height:135px;resize:vertical;line-height:1.45}.row{display:grid;grid-template-columns:1fr 110px;gap:8px}.check{display:flex;align-items:center;gap:8px}.check input{width:auto}.filename{color:#776b5c;word-break:break-all;font-size:.72rem}.id-note{margin-top:3px;color:#665b4d;font:600 .65rem system-ui,sans-serif}.save{width:100%;margin-top:8px}.empty{padding:30px;border:1px dashed #5a4128;text-align:center;color:#8c7b65}@media(max-width:580px){header{flex-direction:column;align-items:flex-start}.toolbar{position:relative;top:auto;flex-direction:column;align-items:stretch}.card{grid-template-columns:1fr}.card img{width:100%;height:260px}}
</style></head><body>
<header><h1>Admin · El Emporio de Ecix's</h1><nav><a href="index.php">Ver tienda</a><a href="?logout=1">Cerrar sesión</a></nav></header>
<main>
<div class="toolbar"><div><strong><?=count($cards)?> objetos detectados</strong><div class="filename">El catálogo acepta el JSON antiguo y el formato por ID. Al guardar, se normaliza automáticamente.</div></div><input id="search" type="search" placeholder="Buscar por nombre, categoría o ID..."></div>
<?php if($notice):?><div class="notice"><?=e($notice)?></div><?php endif?><?php if($error):?><div class="error"><?=e($error)?></div><?php endif?>
<section class="grid">
<?php foreach($cards as $card):?>
<form class="card" method="post" data-search="<?=e(mb_strtolower($card['name'].' '.$card['category'].' '.$card['id'],'UTF-8'))?>">
<img src="<?=e($card['url'])?>" alt="<?=e($card['name'])?>">
<div>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($card['id'])?>">
<h2><?=e($card['name'])?></h2><div class="filename"><?=e($card['file'])?></div><div class="id-note">ID interno: <?=e($card['id'])?></div>
<label>Nombre visible<input name="name" value="<?=e($card['name'])?>" required></label>
<label>Categoría<input name="category" value="<?=e($card['category'])?>"></label>
<div class="row"><label>Precio<input name="price" value="<?=e($card['price'])?>"></label><label>Stock<input name="stock" type="number" min="0" value="<?=(int)$card['stock']?>"></label></div>
<label>Descripción<textarea name="description" placeholder="Describe qué hace, sus límites, usos y requisitos."><?=e($card['description'])?></textarea></label>
<label class="check"><input type="checkbox" name="available" <?=$card['available']?'checked':''?>> Disponible</label>
<button class="save" type="submit">Guardar cambios</button>
</div>
</form>
<?php endforeach?>
<?php if(!$cards):?><div class="empty">No se encontraron cartas en la carpeta Cartas/.</div><?php endif?>
</section>
</main>
<script>
const q=document.querySelector('#search'),cards=[...document.querySelectorAll('.card')];
const normalize=v=>String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim();
q.oninput=()=>{const s=normalize(q.value);cards.forEach(c=>c.hidden=s&&!normalize(c.dataset.search).includes(s))};
</script></body></html>
