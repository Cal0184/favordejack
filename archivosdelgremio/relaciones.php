<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
favor_session_start();
$user=favor_current_user();
function hr(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function relation_asset_url(?string $url):string{
 // Todas las imágenes viven en media/ (fase 2); favor_asset_url() traduce las rutas antiguas.
 $value=trim(str_replace('\\','/',(string)$url));return $value===''?'':favor_asset_url($value);
}
function rel_can_edit(?array $u,array $c):bool{return $u && (($u['role']??'')==='admin'||(string)($u['playerId']??'')===(string)$c['Jugador']);}
$db=favor_db();$error='';$message='';$characters=[];$relations=[];
// TEMPORAL 2.2: J013 ('Personajes Fuera') es un contenedor legado de la migracion de Banca.
// Relaciones trabaja solo con las identidades reales de Archivos hasta rehacer/unificar la BD.
try{
 favor_ensure_schema($db);
 $sql="SELECT p.ID,p.Jugador,p.Nombre,p.IconUrl,p.PortraitUrl,p.Activo,j.Nombre AS JugadorNombre,COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre) AS NombreCompleto,pp.Estado,COALESCE(NULLIF(pp.Categoria,''),'Personaje') AS Categoria FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID WHERE p.Jugador <> 'J013' ORDER BY COALESCE(j.Orden,9999),COALESCE(p.Orden,9999),p.Nombre";
 $res=$db->query($sql);while($r=$res->fetch_assoc()){$r['displayName']=(string)($r['NombreCompleto']?:$r['Nombre']);$r['Categoria']=(string)($r['Categoria']?:'Personaje');$characters[(string)$r['ID']]=$r;}$res->free();
 $isAdmin=$user && (($user['role']??'')==='admin');
 $playerId=(string)($user['playerId']??'');
 $editableCharacters=array_filter($characters,fn($c)=>$isAdmin||($playerId!==''&&(string)$c['Jugador']===$playerId));
 $focusId=trim((string)($_GET['character']??$_POST['character']??''));
 if($focusId===''||!isset($characters[$focusId])){$focusId=array_key_first($editableCharacters)??array_key_first($characters)??'';}
 $focus=$focusId!==''?($characters[$focusId]??null):null;
 if($_SERVER['REQUEST_METHOD']==='POST'){
  favor_verify_csrf((string)($_POST['csrf']??''));
  if(!$focus)throw new RuntimeException('Personaje no válido.');
  if(!rel_can_edit($user,$focus))throw new RuntimeException('No tienes permiso para modificar las relaciones de este personaje.');
  $action=(string)($_POST['action']??'');
  if($action==='add'){
   $other=trim((string)($_POST['other']??''));if($other===''||$other===$focusId||!isset($characters[$other]))throw new RuntimeException('Selecciona otro personaje.');
   $a=$focusId;$b=$other;if(strcmp($a,$b)>0){[$a,$b]=[$b,$a];}
   $type=mb_substr(trim((string)($_POST['type']??'Unidos')),0,80,'UTF-8')?:'Unidos';$note=mb_substr(trim((string)($_POST['note']??'')),0,500,'UTF-8');$author=(string)($user['username']??'');
   $st=$db->prepare('INSERT INTO PersonajeRelacion (PersonajeA,PersonajeB,Tipo,Nota,CreadoPor) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE Tipo=VALUES(Tipo),Nota=VALUES(Nota),CreadoPor=VALUES(CreadoPor)');$st->bind_param('sssss',$a,$b,$type,$note,$author);$st->execute();$st->close();$message='Relación guardada.';
  }elseif($action==='delete'){
   $id=max(0,(int)($_POST['id']??0));$st=$db->prepare('SELECT PersonajeA,PersonajeB FROM PersonajeRelacion WHERE ID=? LIMIT 1');$st->bind_param('i',$id);$st->execute();$rr=$st->get_result()->fetch_assoc();$st->close();if(!$rr)throw new RuntimeException('La relación ya no existe.');
   if($focusId!==$rr['PersonajeA']&&$focusId!==$rr['PersonajeB'])throw new RuntimeException('Esa relación no pertenece al personaje seleccionado.');
   $st=$db->prepare('DELETE FROM PersonajeRelacion WHERE ID=?');$st->bind_param('i',$id);$st->execute();$st->close();$message='Relación eliminada.';
  }
 }
 $res=$db->query('SELECT ID,PersonajeA,PersonajeB,Tipo,Nota,CreadoPor,Actualizado FROM PersonajeRelacion ORDER BY Actualizado DESC,ID DESC');while($r=$res->fetch_assoc())$relations[]=$r;$res->free();
}catch(Throwable $e){$error=$e->getMessage();}finally{$db->close();}
$focus=$focusId!==''?($characters[$focusId]??null):null;$canEdit=$focus?rel_can_edit($user,$focus):false;
$graphChars=[];$graphRels=[];
if($focus){
 $ids=[$focusId=>true];foreach($relations as $r){if($r['PersonajeA']===$focusId){$ids[$r['PersonajeB']]=true;}if($r['PersonajeB']===$focusId){$ids[$r['PersonajeA']]=true;}}
 // Incluye un segundo anillo para que se entienda como árbol/red, sin saturar con todo el censo.
 foreach($relations as $r){if(isset($ids[$r['PersonajeA']])||isset($ids[$r['PersonajeB']])){$ids[$r['PersonajeA']]=true;$ids[$r['PersonajeB']]=true;}}
 foreach(array_keys($ids) as $id)if(isset($characters[$id])){$c=$characters[$id];$graphChars[]=['id'=>$id,'name'=>$c['displayName'],'player'=>$c['JugadorNombre'],'category'=>$c['Categoria']??'Personaje','active'=>(int)($c['Activo']??1),'image'=>relation_asset_url((string)($c['PortraitUrl']?:$c['IconUrl']??'')),'focus'=>$id===$focusId];}
 foreach($relations as $r)if(isset($ids[$r['PersonajeA']],$ids[$r['PersonajeB']]))$graphRels[]=$r;
}else{
 foreach($characters as $id=>$c)$graphChars[]=['id'=>$id,'name'=>$c['displayName'],'player'=>$c['JugadorNombre'],'category'=>$c['Categoria']??'Personaje','active'=>(int)($c['Activo']??1),'image'=>relation_asset_url((string)($c['PortraitUrl']?:$c['IconUrl']??'')),'focus'=>false];$graphRels=$relations;
}
$focusRelations=array_values(array_filter($relations,fn($r)=>$focusId!==''&&($r['PersonajeA']===$focusId||$r['PersonajeB']===$focusId)));
$centralChoices=[];
foreach($editableCharacters as $id=>$c){$centralChoices[]=['id'=>$id,'name'=>$c['displayName'],'player'=>$c['JugadorNombre'],'category'=>$c['Categoria']??'Personaje','active'=>(int)($c['Activo']??1),'image'=>relation_asset_url((string)($c['PortraitUrl']?:$c['IconUrl']??''))];}
$relationChoices=[];
foreach($characters as $id=>$c){$relationChoices[]=['id'=>$id,'name'=>$c['displayName'],'player'=>$c['JugadorNombre'],'category'=>$c['Categoria']??'Personaje','active'=>(int)($c['Activo']??1)];}
$playerNames=array_values(array_unique(array_filter(array_map(fn($c)=>(string)($c['JugadorNombre']??''),$characters))));sort($playerNames,SORT_NATURAL|SORT_FLAG_CASE);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Relaciones 2.2 · Archivos del Gremio</title>
<style>
:root{
 --night:#0b0908;--night2:#15100c;--leather:#25170f;--leather2:#3a2516;
 --paper:#e7d2a4;--paper2:#cdb17b;--ink:#352617;--muted:#826c4c;
 --gold:#c69a4f;--gold2:#8d642d;--wine:#6f2d35;--wine2:#3e171d;--cream:#f3e5c4;
 --line:#9b7c4d;--green:#506b4d;
}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;color:var(--cream);font-family:Georgia,"Times New Roman",serif;background:
 radial-gradient(circle at 15% 10%,#6d492033 0 1px,transparent 2px),
 radial-gradient(circle at 82% 16%,#a47b4033 0 1px,transparent 2px),
 radial-gradient(circle at 70% 70%,#5e3b1838,transparent 28%),
 linear-gradient(120deg,#0a0807 0%,#17100b 46%,#080706 100%);background-size:54px 54px,71px 71px,100% 100%,100% 100%;}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.55;background:
 linear-gradient(90deg,transparent 0 49.7%,#c79a4a0d 50%,transparent 50.3%),
 radial-gradient(ellipse at center,transparent 0 48%,#0009 100%);z-index:-1}
a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}
.topbar{position:sticky;top:0;z-index:50;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px;padding:11px clamp(12px,3vw,44px);border-bottom:1px solid #8e672f88;background:#100c09ee;backdrop-filter:blur(12px);box-shadow:0 10px 30px #0007}
.topbar .navlink{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #78562c;border-radius:999px;background:linear-gradient(#2e1e13,#1b110c);color:#e7d2a3;transition:.18s}.topbar .navlink:hover{border-color:#c3964b;transform:translateY(-1px)}
.brand{text-align:center}.brand strong{display:block;color:#e4c57d;font-size:1.12rem;letter-spacing:.16em;text-transform:uppercase}.brand span{display:block;margin-top:2px;color:#987f5b;font-size:.7rem;letter-spacing:.08em}
.page{width:min(1500px,96vw);margin:22px auto 64px;display:grid;grid-template-columns:1fr;gap:20px;align-items:start}
.controls-deck{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(280px,.8fr) minmax(360px,1.25fr);gap:16px;align-items:start}
.controls-deck .panel{height:auto;min-width:0}.controls-deck .legend{grid-column:1/-1;text-align:center}
.hero{grid-column:1/-1;position:relative;overflow:hidden;padding:26px 30px;border:1px solid #76552c;border-radius:20px;background:
 linear-gradient(100deg,#2d1b11f4,#17100cf4 68%,#351d15f4),
 radial-gradient(circle at 80% 50%,#a3723033,transparent 35%);box-shadow:0 24px 70px #0008,inset 0 0 0 1px #c7954933}
.hero:before,.hero:after{content:"✦";position:absolute;top:50%;transform:translateY(-50%);color:#b98d46;font-size:2.5rem;opacity:.35}.hero:before{left:14px}.hero:after{right:14px}
.hero .eyebrow{color:#cda75e;font-size:.74rem;letter-spacing:.22em;text-transform:uppercase}.hero h1{margin:5px 0 7px;font-size:clamp(1.8rem,3vw,3rem);line-height:1;color:#f0ddb1;text-shadow:0 2px 18px #000}.hero p{max-width:830px;margin:0;color:#b9a07a;line-height:1.55}
.paper{position:relative;border:9px solid #3b2415;border-radius:19px;background:
 radial-gradient(circle at 12% 8%,#fff7 0 2%,transparent 22%),
 radial-gradient(circle at 80% 22%,#8c6a3220,transparent 28%),
 linear-gradient(140deg,var(--paper),#eedcb8 50%,var(--paper2));color:var(--ink);box-shadow:0 28px 70px #0009,inset 0 0 0 2px #8b673888}
.paper:before,.paper:after{content:"◆";position:absolute;z-index:3;color:#795527;font-size:17px;opacity:.75}.paper:before{left:9px;top:7px}.paper:after{right:9px;bottom:7px}
.graph-card{padding:22px;min-height:0;align-self:start}.graph-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:15px}.graph-head .titleline{display:flex;align-items:center;gap:12px}.sigil{display:grid;place-items:center;width:44px;height:44px;border-radius:50%;border:1px solid #805c2e;background:#d6bb85;color:#6f4325;box-shadow:inset 0 0 0 4px #ead7ad}.graph-head h2{margin:0;color:#542f22;font-size:1.4rem}.graph-head p{margin:4px 0 0;color:#806847;font-size:.9rem}.focus-badge{padding:7px 11px;border:1px solid #9b743b;border-radius:999px;background:#d7bc86;color:#65421f;font-size:.76rem;white-space:nowrap}
.graph{position:relative;height:clamp(560px,64vh,700px);min-height:0;border:1px solid #9c7947;border-radius:14px;background:
 radial-gradient(circle at center,#f5e8c8 0 2px,transparent 3px),
 radial-gradient(circle at center,transparent 0 29%,#ad895127 29.3% 29.7%,transparent 30%),
 radial-gradient(circle at center,transparent 0 46%,#ad89511f 46.3% 46.7%,transparent 47%),
 linear-gradient(#6f542715 1px,transparent 1px),linear-gradient(90deg,#6f542715 1px,transparent 1px),
 radial-gradient(circle at center,#f3e2bd,#d8bd86);background-size:auto,auto,auto,34px 34px,34px 34px,auto;overflow:hidden;box-shadow:inset 0 0 40px #6e4a2738}
.graph:after{content:"";position:absolute;inset:18px;border:1px solid #8f6b3f44;border-radius:50%;pointer-events:none}.graph svg{position:absolute;inset:0;width:100%;height:100%;z-index:1}.edge{stroke:#735536;stroke-width:2;opacity:.57}.edge.focus{stroke:#7a2831;stroke-width:3.3;opacity:.92;filter:drop-shadow(0 0 3px #fff5)}.edge-label{font-size:12px;fill:#5d432d;text-anchor:middle;paint-order:stroke;stroke:#ead7ae;stroke-width:4px;stroke-linejoin:round}
.node{position:absolute;z-index:2;width:116px;transform:translate(-50%,-50%);text-align:center;color:#493420;cursor:pointer;transition:.18s}.node:hover{z-index:8;transform:translate(-50%,-50%) scale(1.06)}.node-img{width:72px;height:86px;margin:auto;border:2px solid #8a6332;border-radius:50% 50% 42% 42%/42% 42% 25% 25%;overflow:hidden;background:linear-gradient(#51402c,#2d2118);box-shadow:0 8px 15px #57361f55,inset 0 0 0 3px #e6d0a044}.node img{width:100%;height:100%;object-fit:cover;object-position:center top}.node strong{display:block;margin-top:6px;font-size:.78rem;line-height:1.05;text-shadow:0 1px #f3e4c0}.node small{display:block;color:#806746;font-size:.64rem}.node .npcmark{display:inline-block;margin-top:3px;padding:2px 5px;border:1px solid #8b6230;border-radius:999px;background:#d6ba82;color:#6c4823;font-size:.55rem;letter-spacing:.08em}.node.focus .node-img{width:96px;height:114px;border:4px solid #702b33;box-shadow:0 0 0 4px #c59a4b,0 13px 28px #5f351f77}.node.focus strong{font-size:.9rem;color:#612830}
.side{display:grid;gap:14px;height:auto}.panel{position:relative;padding:18px;border:1px solid #694a28;border-radius:16px;background:
 linear-gradient(145deg,#21150f,#100b08 78%);box-shadow:0 18px 45px #0008,inset 0 0 0 1px #c7954930;overflow:hidden}.panel:before{content:"";position:absolute;left:18px;right:18px;top:0;height:1px;background:linear-gradient(90deg,transparent,#d2a65788,transparent)}.panel h2,.panel h3{margin:0;color:#e2c27c}.panel .sub{margin:5px 0 13px;color:#8f7a5c;font-size:.78rem;line-height:1.4}
.filter-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.filter-grid .wide{grid-column:1/-1}.filter-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.control{width:100%;padding:9px 10px;border:1px solid #614524;border-radius:8px;outline:none;background:#0c0806;color:#eadbb9}.control:focus{border-color:#b38443;box-shadow:0 0 0 2px #b3844326}.control::placeholder{color:#725f47}.mini-label{display:block;margin:0 0 5px;color:#aa916d;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase}
.choice-list{display:grid;gap:7px;margin-top:10px;max-height:250px;overflow:auto;padding-right:3px}.choice-list::-webkit-scrollbar{width:7px}.choice-list::-webkit-scrollbar-thumb{background:#5a3c22;border-radius:20px}.choice-card{display:grid;grid-template-columns:42px 1fr auto;gap:9px;align-items:center;width:100%;padding:7px 8px;border:1px solid #47311c;border-radius:10px;background:#120c08;color:#d9c49a;text-align:left;cursor:pointer;transition:.16s}.choice-card:hover,.choice-card.active{border-color:#a87a39;background:#22150e;transform:translateX(2px)}.choice-card.active{box-shadow:inset 3px 0 #a66a3a}.choice-card img,.choice-fallback{width:42px;height:48px;border-radius:7px;object-fit:cover;object-position:center top;background:#3d2a1d;border:1px solid #74502b}.choice-fallback{display:grid;place-items:center;color:#ae8a51}.choice-card strong{display:block;font-size:.78rem;color:#ead7aa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.choice-card small{display:block;margin-top:2px;color:#b39a78;font-size:.64rem}.tag{padding:3px 6px;border:1px solid #6b4d28;border-radius:999px;color:#a98c62;font-size:.56rem;text-transform:uppercase;letter-spacing:.05em}.tag.npc{border-color:#704f6d;color:#bca0b9}.results-note{margin-top:8px;color:#746147;font-size:.68rem}.results-note b{color:#b79969}
.relation-list{display:grid;gap:8px;max-height:250px;overflow:auto;padding-right:3px}.relation{padding:10px 11px;border:1px solid #50371e;border-radius:10px;background:#100b08}.relation-row{display:block}.relation strong{color:#e0c287}.relation small{display:block;margin-top:3px;color:#9f8a69;line-height:1.35}.field{display:grid;gap:5px;margin:9px 0}.field span{color:#b9a178;font-size:.76rem}.field input,.field select,.field textarea{width:100%;padding:9px;border:1px solid #6d4c2a;border-radius:7px;background:#0b0705;color:#eadbb9;outline:none}.field textarea{min-height:76px;resize:vertical}.field input:focus,.field textarea:focus{border-color:#a77a3c}.button{padding:8px 11px;border:1px solid #805b2e;border-radius:8px;background:#2c1d11;color:#ead3a1;cursor:pointer;transition:.16s}.button:hover{filter:brightness(1.12);transform:translateY(-1px)}.button.primary{width:100%;background:linear-gradient(#8b6532,#5d3f20);font-weight:bold}.button.primary:disabled{opacity:.45;cursor:not-allowed;transform:none}.button.danger{border-color:#7f4543;color:#e5aea7;background:#281110}.notice{margin-bottom:10px;padding:9px 11px;border:1px solid #557149;background:#142313;color:#c8dfb8;border-radius:8px}.notice.error{border-color:#814643;background:#261311;color:#efb6ad}.empty{color:#8f7c60;font-style:italic;font-size:.8rem}.legend{font-size:.76rem;color:#8f7a5b;line-height:1.55}.legend strong{color:#c1a06e}.selected-person{display:none;margin-top:9px;padding:9px;border:1px solid #77542c;border-radius:9px;background:#20140d}.selected-person.show{display:flex;align-items:center;justify-content:space-between;gap:8px}.selected-person b{color:#dfc083;font-size:.8rem}.selected-person span{color:#8e7857;font-size:.68rem}
.compact-choice-list{display:grid;gap:6px;margin-top:10px;max-height:220px;overflow:auto;padding-right:3px}.compact-choice-list::-webkit-scrollbar{width:7px}.compact-choice-list::-webkit-scrollbar-thumb{background:#5a3c22;border-radius:20px}
.compact-choice{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;width:100%;padding:9px 10px;border:1px solid #47311c;border-radius:9px;background:#120c08;color:#d9c49a;text-align:left;cursor:pointer;transition:.16s}.compact-choice:hover{border-color:#a87a39;background:#22150e;transform:translateX(2px)}.compact-choice strong{display:block;color:#ead7aa;font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.compact-choice small{display:block;margin-top:2px;color:#b39a78;font-size:.66rem}.compact-choice .meta{display:flex;gap:5px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.status-dot{width:7px;height:7px;border-radius:50%;background:#657c52;box-shadow:0 0 0 2px #657c5228}.status-dot.inactive{background:#735e52}
.panel-center{text-align:center}.panel-center .sub{max-width:520px;margin-left:auto;margin-right:auto}.panel-center .filter-grid{text-align:left}.panel-center .results-note{text-align:center}

@media(max-width:1080px){.controls-deck{grid-template-columns:1fr 1fr}.controls-deck .panel:last-child{grid-column:1/-1}.hero{grid-column:1/-1}.graph{height:600px}}
@media(max-width:760px){.topbar{grid-template-columns:auto 1fr}.topbar .navlink:last-child{display:none}.brand{text-align:right}.brand span{display:none}.page{width:calc(100% - 2vw);margin-top:12px}.hero{padding:21px 24px}.controls-deck{grid-template-columns:1fr}.controls-deck .panel:last-child{grid-column:auto}.graph-card{padding:10px;border-width:6px}.graph{height:560px}.graph-head{flex-direction:column}.focus-badge{display:none}.node{width:86px}.node-img{width:56px;height:68px}.node.focus .node-img{width:72px;height:88px}.edge-label{font-size:9px}.filter-grid,.filter-grid.three{grid-template-columns:1fr}}
</style>
<?php favor_head('Relaciones · Archivos del Gremio'); ?>
</head>
<body>
<?php favor_site_bar(); ?>
<header class="topbar">
 <div class="brand"><strong>Relaciones</strong><span>Archivo de vínculos del gremio · versión 2.3</span></div>
 <a class="navlink" href="<?= favor_base_path() ?>">Favor de Jack</a>
</header>

<main class="page">
 <section class="hero">
  <div class="eyebrow">Crónicas del gremio</div>
  <h1>La red que une nuestras historias</h1>
  <p>Explora amistades, rivalidades, familias y alianzas. Elige un personaje central para desplegar su círculo cercano y las conexiones que nacen a su alrededor.</p>
 </section>

 <section class="paper graph-card">
  <div class="graph-head">
   <div class="titleline"><div class="sigil">✦</div><div><h2><?= $focus?hr((string)$focus['displayName']):'Relaciones del gremio' ?></h2><p>Haz clic en un retrato del mapa para centrar la red en ese personaje.</p></div></div>
   <?php if($focus):?><span class="focus-badge"><?=hr((string)($focus['Categoria']??'Personaje'))?> · <?=hr((string)$focus['JugadorNombre'])?></span><?php endif;?>
  </div>
  <div class="graph" id="graph"><svg id="edges" aria-hidden="true"></svg><div id="nodes"></div></div>
 </section>

 <section class="controls-deck">
  <section class="panel panel-center">
   <h2><?= $isAdmin?'Personaje central':'Tus personajes' ?></h2>
   <p class="sub"><?= $isAdmin?'Busca y filtra cualquier expediente para centrar la red.':'Aquí solo aparecen los personajes que puedes modificar.' ?></p>
   <div class="filter-grid">
    <div class="wide"><label class="mini-label" for="centralSearch">Buscar</label><input class="control" id="centralSearch" placeholder="Nombre del personaje…"></div>
    <div><label class="mini-label" for="centralCategory">Tipo</label><select class="control" id="centralCategory"><option value="">Todos</option><option value="Personaje">Personajes</option><option value="NPC">NPC</option></select></div>
    <div><label class="mini-label" for="centralActive">Estado</label><select class="control" id="centralActive"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
    <?php if($isAdmin):?><div class="wide"><label class="mini-label" for="centralPlayer">Jugador</label><select class="control" id="centralPlayer"><option value="">Todos los jugadores</option><?php foreach($playerNames as $pn):?><option value="<?=hr($pn)?>"><?=hr($pn)?></option><?php endforeach;?></select></div><?php endif;?>
   </div>
   <div class="choice-list" id="centralList"></div><div class="results-note" id="centralCount"></div>
  </section>

  <section class="panel">
   <h3>Uniones de <?= $focus?hr((string)$focus['displayName']):'personaje' ?></h3>
   <p class="sub">Relaciones registradas actualmente.</p>
   <?php if($error):?><div class="notice error"><?=hr($error)?></div><?php endif;?>
   <?php if($message):?><div class="notice"><?=hr($message)?></div><?php endif;?>
   <div class="relation-list">
   <?php if(!$focusRelations):?><span class="empty">Todavía no hay relaciones registradas.</span>
   <?php else:foreach($focusRelations as $r):$other=$r['PersonajeA']===$focusId?$r['PersonajeB']:$r['PersonajeA'];$oc=$characters[$other]??null;if(!$oc)continue;?>
    <div class="relation"><div class="relation-row"><strong><?=hr((string)$oc['displayName'])?></strong><small><?=hr((string)$r['Tipo'])?><?=trim((string)$r['Nota'])!==''?' · '.hr((string)$r['Nota']):''?></small></div>
    <?php if($canEdit):?><form method="post" style="margin-top:8px" onsubmit="return confirm('¿Quitar esta unión?')"><input type="hidden" name="csrf" value="<?=hr(favor_csrf())?>"><input type="hidden" name="character" value="<?=hr($focusId)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$r['ID']?>"><button class="button danger" type="submit">Quitar unión</button></form><?php endif;?></div>
   <?php endforeach;endif;?></div>
  </section>

  <?php if($canEdit):?><section class="panel">
   <h3>Unir personaje</h3><p class="sub">Busca primero; ya no necesitas recorrer todo el censo.</p>
   <form method="post" id="relationForm"><input type="hidden" name="csrf" value="<?=hr(favor_csrf())?>"><input type="hidden" name="character" value="<?=hr($focusId)?>"><input type="hidden" name="action" value="add"><input type="hidden" name="other" id="otherInput" value="">
    <div class="filter-grid three">
     <div class="wide"><label class="mini-label" for="relationSearch">Buscar</label><input class="control" id="relationSearch" placeholder="Nombre del personaje…"></div>
     <div><label class="mini-label" for="relationCategory">Tipo</label><select class="control" id="relationCategory"><option value="">Todos</option><option value="Personaje">Personajes</option><option value="NPC">NPC</option></select></div>
     <div><label class="mini-label" for="relationActive">Estado</label><select class="control" id="relationActive"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
     <div><label class="mini-label" for="relationPlayer">Jugador</label><select class="control" id="relationPlayer"><option value="">Todos</option><?php foreach($playerNames as $pn):?><option value="<?=hr($pn)?>"><?=hr($pn)?></option><?php endforeach;?></select></div>
    </div>
    <div class="compact-choice-list" id="relationChoices"></div><div class="results-note" id="relationCount"></div>
    <div class="selected-person" id="selectedPerson"><div><b id="selectedName"></b><span id="selectedMeta"></span></div><button class="button" type="button" id="clearSelected">Cambiar</button></div>
    <label class="field"><span>Tipo de relación</span><input name="type" maxlength="80" value="Unidos" placeholder="Amigos, familia, rivales…"></label>
    <label class="field"><span>Nota</span><textarea name="note" maxlength="500" placeholder="Contexto opcional"></textarea></label>
    <button class="button primary" id="saveRelation" type="submit" disabled>Guardar unión</button>
   </form>
  </section><?php endif;?>

  <section class="panel legend"><strong>Permisos:</strong> un jugador modifica únicamente las relaciones de sus propios personajes. Puede relacionarlos con cualquier personaje o NPC disponible. El administrador puede editar todos los expedientes.</section>
 </section>
</main>
<script>
const chars=<?=json_encode($graphChars,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;
const rels=<?=json_encode($graphRels,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;
const focus=<?=json_encode($focusId)?>;
const centralChoices=<?=json_encode($centralChoices,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;
const relationChoices=<?=json_encode($relationChoices,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;
const graph=document.getElementById('graph'),nodesEl=document.getElementById('nodes'),svg=document.getElementById('edges');
function esc(v){return String(v??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]))}
function norm(v){return String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim()}
function imgHtml(c){return c.image?'<img src="'+esc(c.image)+'" alt="">':'<span class="choice-fallback">✦</span>'}
function draw(){if(!graph)return;nodesEl.innerHTML='';svg.innerHTML='';const W=graph.clientWidth,H=graph.clientHeight,cx=W/2,cy=H/2,pos={};const focusChar=chars.find(c=>c.id===focus);const first=new Set();rels.forEach(r=>{if(r.PersonajeA===focus)first.add(r.PersonajeB);if(r.PersonajeB===focus)first.add(r.PersonajeA)});const ring1=chars.filter(c=>c.id!==focus&&first.has(c.id)),ring2=chars.filter(c=>c.id!==focus&&!first.has(c.id));if(focusChar)pos[focus]={x:cx,y:cy};const putRing=(arr,rad)=>arr.forEach((c,i)=>{const a=(Math.PI*2*i/Math.max(1,arr.length))-Math.PI/2;pos[c.id]={x:cx+Math.cos(a)*Math.min(rad,W*.39),y:cy+Math.sin(a)*Math.min(rad,H*.39)}});putRing(ring1,Math.min(W,H)*.30);putRing(ring2,Math.min(W,H)*.44);if(!focusChar)putRing(chars,Math.min(W,H)*.38);
 rels.forEach(r=>{const a=pos[r.PersonajeA],b=pos[r.PersonajeB];if(!a||!b)return;const ns='http://www.w3.org/2000/svg';const line=document.createElementNS(ns,'line');line.setAttribute('x1',a.x);line.setAttribute('y1',a.y);line.setAttribute('x2',b.x);line.setAttribute('y2',b.y);line.setAttribute('class','edge '+((r.PersonajeA===focus||r.PersonajeB===focus)?'focus':''));svg.appendChild(line);const t=document.createElementNS(ns,'text');t.setAttribute('x',(a.x+b.x)/2);t.setAttribute('y',(a.y+b.y)/2-5);t.setAttribute('class','edge-label');t.textContent=r.Tipo||'Unidos';svg.appendChild(t)});
 chars.forEach(c=>{const p=pos[c.id];if(!p)return;const a=document.createElement('a');a.className='node '+(c.id===focus?'focus':'');a.href='?character='+encodeURIComponent(c.id);a.style.left=p.x+'px';a.style.top=p.y+'px';a.innerHTML='<div class="node-img">'+(c.image?'<img src="'+esc(c.image)+'" alt="">':'✦')+'</div><strong>'+esc(c.name)+'</strong><small>'+esc(c.player||'')+'</small>'+((c.category||'Personaje')==='NPC'?'<span class="npcmark">NPC</span>':'');nodesEl.appendChild(a)});
}
function renderCentral(){const host=document.getElementById('centralList');if(!host)return;const q=norm(document.getElementById('centralSearch').value),cat=document.getElementById('centralCategory').value,act=document.getElementById('centralActive').value,pl=document.getElementById('centralPlayer')?.value||'';let list=centralChoices.filter(c=>(!q||norm(c.name+' '+c.player).includes(q))&&(!cat||c.category===cat)&&(!act||String(c.active)===act)&&(!pl||c.player===pl));const total=list.length;list=list.slice(0,24);host.innerHTML=list.length?list.map(c=>'<button type="button" class="choice-card '+(c.id===focus?'active':'')+'" data-id="'+esc(c.id)+'">'+imgHtml(c)+'<span><strong>'+esc(c.name)+'</strong><small>'+esc(c.player||'Sin jugador')+'</small></span><span class="tag '+(c.category==='NPC'?'npc':'')+'">'+esc(c.category||'Personaje')+'</span></button>').join(''):'<span class="empty">No hay coincidencias.</span>';host.querySelectorAll('.choice-card').forEach(b=>b.onclick=()=>location.href='?character='+encodeURIComponent(b.dataset.id));document.getElementById('centralCount').innerHTML=total>24?'Mostrando <b>24</b> de <b>'+total+'</b>. Usa los filtros para acotar.':'<b>'+total+'</b> resultado'+(total===1?'':'s')+'.'}
['centralSearch','centralCategory','centralActive','centralPlayer'].forEach(id=>document.getElementById(id)?.addEventListener(id==='centralSearch'?'input':'change',renderCentral));
function renderRelationChoices(){const host=document.getElementById('relationChoices');if(!host)return;const q=norm(document.getElementById('relationSearch').value),cat=document.getElementById('relationCategory').value,act=document.getElementById('relationActive').value,pl=document.getElementById('relationPlayer').value;let list=relationChoices.filter(c=>c.id!==focus&&(!q||norm(c.name+' '+c.player).includes(q))&&(!cat||c.category===cat)&&(!act||String(c.active)===act)&&(!pl||c.player===pl));const total=list.length;list=list.slice(0,20);host.innerHTML=list.length?list.map(c=>'<button type="button" class="compact-choice" data-id="'+esc(c.id)+'"><span><strong>'+esc(c.name)+'</strong><small>'+esc(c.player||'Sin jugador')+'</small></span><span class="meta"><i class="status-dot '+(c.active?'':'inactive')+'"></i><span class="tag '+(c.category==='NPC'?'npc':'')+'">'+esc(c.category||'Personaje')+'</span></span></button>').join(''):'<span class="empty">No hay coincidencias.</span>';host.querySelectorAll('.compact-choice').forEach(b=>b.onclick=()=>selectRelation(b.dataset.id));document.getElementById('relationCount').innerHTML=total>20?'Mostrando <b>20</b> de <b>'+total+'</b>. Escribe un nombre o usa los filtros.':'<b>'+total+'</b> resultado'+(total===1?'':'s')+'.'}
function selectRelation(id){const c=relationChoices.find(x=>x.id===id);if(!c)return;document.getElementById('otherInput').value=id;document.getElementById('selectedName').textContent=c.name;document.getElementById('selectedMeta').textContent=(c.player||'Sin jugador')+' · '+(c.category||'Personaje');document.getElementById('selectedPerson').classList.add('show');document.getElementById('saveRelation').disabled=false;document.getElementById('relationChoices').style.display='none';document.getElementById('relationCount').style.display='none'}
function clearRelation(){document.getElementById('otherInput').value='';document.getElementById('selectedPerson').classList.remove('show');document.getElementById('saveRelation').disabled=true;document.getElementById('relationChoices').style.display='grid';document.getElementById('relationCount').style.display='block';renderRelationChoices()}
['relationSearch','relationCategory','relationActive','relationPlayer'].forEach(id=>document.getElementById(id)?.addEventListener(id==='relationSearch'?'input':'change',renderRelationChoices));document.getElementById('clearSelected')?.addEventListener('click',clearRelation);document.getElementById('relationForm')?.addEventListener('submit',e=>{if(!document.getElementById('otherInput').value){e.preventDefault();alert('Selecciona un personaje para crear la relación.')}});
addEventListener('resize',()=>{clearTimeout(window._rt);window._rt=setTimeout(draw,100)});draw();renderCentral();renderRelationChoices();
</script>
</body></html>
