<?php
declare(strict_types=1);
/*
 * Relaciones · la cámara de vínculos del gremio (Archivos del Gremio).
 *
 *   relaciones.php  → datos, permisos y guardado (este archivo).
 *   relaciones.css  → aspecto (sala de piedra, paneles de madera, mapa de pergamino).
 *   relaciones.js   → mapa de vínculos, buscador, filtros y el bloque «Vincular personajes».
 *
 * Los vínculos viven en la tabla PersonajeRelacion (un par A–B por fila, A < B).
 * Guardar el mismo par otra vez lo actualiza (así funciona «Editar»).
 * El JS envía los cambios con fetch (ajax=1) y recibe la lista nueva en JSON;
 * sin JS el formulario normal sigue funcionando y vuelve a la página.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/assets.php';
require_once dirname(__DIR__) . '/includes/imagenes.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/auth-widget.php';
require_once dirname(__DIR__) . '/includes/estado.php';
favor_session_start();
// En POST favor_current_user() relee la cuenta de la base de datos (permisos al día).
try{$user=favor_current_user();}catch(Throwable $e){$user=null;$userError='No se pudo comprobar tu cuenta. Inténtalo otra vez.';}
$csrf=$user?favor_csrf():'';
favor_session_close();
function hr(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
// Tipos de vínculo con su color (el texto de la relación sigue siendo libre).
const REL_VINCULOS=[
 'familia'=>['Familia','#c9982b'],'amistad'=>['Amistad','#4f9a4c'],'amor'=>['Amor','#c8558f'],'aliados'=>['Aliados','#4a83bd'],
 'mentor'=>['Mentor y alumno','#8a68c4'],'rivalidad'=>['Rivalidad','#d47b2c'],'enemistad'=>['Enemistad','#b83a33'],'otro'=>['Otro','#8c7d68'],
];
// Iconos de cada vínculo (trazos SVG de 24×24; el color lo pone el CSS).
const REL_ICONOS=[
 'familia'=>'<circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M2.5 20c0-3.4 2.5-6 5.5-6s5.5 2.6 5.5 6"/><path d="M13.6 14.8c.9-.5 1.9-.8 2.9-.8 2.8 0 5 2.4 5 5.5"/>',
 'amistad'=>'<path d="M7 11 3 7l3-3 4 2h4l4-2 3 3-4 4"/><path d="m7 11 5 5c.8.8 2 .8 2.8 0l3.2-3.2"/><path d="m10 13 2-2M12.5 15.5l2-2"/><path d="M10 6 7.5 9a1.6 1.6 0 0 0 2.3 2.3L12 9"/>',
 'amor'=>'<path d="M12 20s-7.5-4.6-9.2-9.4A4.9 4.9 0 0 1 12 7.3a4.9 4.9 0 0 1 9.2 3.3C19.5 15.4 12 20 12 20z"/>',
 'aliados'=>'<path d="M12 3 4.5 6v5.5c0 4.6 3.2 7.9 7.5 9.5 4.3-1.6 7.5-4.9 7.5-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
 'mentor'=>'<path d="M2.5 5.5c3-1.2 6.6-1 9.5.9 2.9-1.9 6.5-2.1 9.5-.9v13c-3-1.2-6.6-1-9.5.9-2.9-1.9-6.5-2.1-9.5-.9z"/><path d="M12 6.4v13"/>',
 'rivalidad'=>'<path d="M14.5 17.5 3 6V3h3l11.5 11.5"/><path d="m13 19 6-6M16 16l4 4"/><path d="M9.5 6.5 6 3"/><path d="M14.5 9.5 21 3v0h0v3l-6.5 6.5"/><path d="m5 13 6 6M8 16l-4 4"/>',
 'enemistad'=>'<path d="M12 3a7.5 7.5 0 0 0-4.8 13.3V20h9.6v-3.7A7.5 7.5 0 0 0 12 3z"/><circle cx="9.2" cy="11.5" r="1.5"/><circle cx="14.8" cy="11.5" r="1.5"/><path d="M10.4 20v-2.2M13.6 20v-2.2"/>',
 'otro'=>'<path d="M12 3.5l1.9 6.6 6.6 1.9-6.6 1.9L12 20.5l-1.9-6.6L3.5 12l6.6-1.9z"/>',
];
// Categorías extra (además de Personaje y NPC) para los grupos de la Banca.
const REL_FUERA='Personajes Fuera';
const REL_MUERTOS='Muertos';
function rel_icon(string $k,string $class='rel-ico'):string{return '<svg class="'.$class.'" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.(REL_ICONOS[$k]??REL_ICONOS['otro']).'</svg>';}
/** Adivina el vínculo por el texto («Hermano» → familia) para las relaciones viejas. */
function rel_guess_vinculo(string $tipo):string{
 $t=mb_strtolower($tipo,'UTF-8');
 $map=['familia'=>['herman','padre','madre','hij','prim','tio','tía','abuel','famil','espos','sobrin','cuñad'],'amor'=>['amor','novi','amante','pareja','follaron','casad','enamor','beso'],
  'enemistad'=>['enemig','odio','odia','venganza','némesis','nemesis','traici'],'rivalidad'=>['rival','competen'],'mentor'=>['mentor','tutor','maestr','alumn','aprendiz','recluta','comandante','discípul','discipul'],
  'aliados'=>['alia','compañer','companer','socio','equipo','grupo','unidos','gremio'],'amistad'=>['amig','amistad','colega']];
 foreach($map as $k=>$words)foreach($words as $w)if(str_contains($t,$w))return $k;
 return 'otro';
}
function rel_ensure_vinculo(mysqli $db):void{
 $r=$db->query("SELECT Valor FROM FavorMeta WHERE Clave='relacion_vinculo_v1' LIMIT 1");if($r&&$r->fetch_assoc())return;
 $c=$db->query("SHOW COLUMNS FROM PersonajeRelacion LIKE 'Vinculo'");if($c&&!$c->fetch_assoc())$db->query("ALTER TABLE PersonajeRelacion ADD COLUMN Vinculo varchar(20) NULL DEFAULT NULL");
 $st=$db->prepare('UPDATE PersonajeRelacion SET Vinculo=? WHERE ID=?');
 foreach($db->query('SELECT ID,Tipo FROM PersonajeRelacion WHERE Vinculo IS NULL')->fetch_all(MYSQLI_ASSOC) as $row){$v=rel_guess_vinculo((string)$row['Tipo']);$id=(int)$row['ID'];$st->bind_param('si',$v,$id);$st->execute();}
 $st->close();
 $db->query("INSERT INTO FavorMeta (Clave,Valor) VALUES ('relacion_vinculo_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
}
function rel_can_edit(?array $u,array $c):bool{return $u && (($u['role']??'')==='admin'||(string)($u['playerId']??'')===(string)$c['Jugador']);}
/** Todas las relaciones, en la forma que usa el JS (sin el autor). */
function rel_load(mysqli $db):array{
 $out=[];$res=$db->query('SELECT ID,PersonajeA,PersonajeB,Tipo,COALESCE(Vinculo,\'otro\') AS Vinculo,Nota FROM PersonajeRelacion ORDER BY Actualizado DESC,ID DESC');
 while($r=$res->fetch_assoc()){$v=(string)$r['Vinculo'];$out[]=['id'=>(int)$r['ID'],'a'=>(string)$r['PersonajeA'],'b'=>(string)$r['PersonajeB'],'tipo'=>(string)$r['Tipo'],'vinculo'=>isset(REL_VINCULOS[$v])?$v:'otro','nota'=>(string)($r['Nota']??'')];}
 $res->free();return $out;
}
$wantsJson=($_SERVER['REQUEST_METHOD']??'')==='POST'&&(($_POST['ajax']??'')==='1');
function rel_json(array $data,int $code=200):void{http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

$db=favor_db();$error=$userError??'';$characters=[];$relations=[];
$isAdmin=$user && (($user['role']??'')==='admin');
$playerId=(string)($user['playerId']??'');
$focusId='';
// Los grupos especiales de la Banca (Muertos, Personajes Fuera) también salen,
// con su propia categoría. Si un personaje de esos grupos tiene su ficha
// «gemela» en Archivos (mismo nombre), se muestra solo la de Archivos para no
// duplicarlo, salvo que la copia de la Banca ya tenga relaciones.
try{
 favor_ensure_schema($db);
 rel_ensure_vinculo($db);
 $estados=favor_estado_todos($db);
 $sql="SELECT p.ID,p.Jugador,p.Nombre,p.IconUrl,p.PortraitUrl,p.Activo,j.Nombre AS JugadorNombre,COALESCE(NULLIF(pp.NombreCompleto,''),p.Nombre) AS NombreCompleto,pp.Estado,COALESCE(NULLIF(pp.Categoria,''),'Personaje') AS Categoria FROM Personaje p LEFT JOIN Jugador j ON j.ID=p.Jugador LEFT JOIN PersonajePerfil pp ON pp.ID=p.ID ORDER BY COALESCE(j.Orden,9999),COALESCE(p.Orden,9999),p.Nombre";
 $related=[];$rr=$db->query('SELECT PersonajeA,PersonajeB FROM PersonajeRelacion');while($x=$rr->fetch_row()){$related[(string)$x[0]]=true;$related[(string)$x[1]]=true;}$rr->free();
 // Nombres (corto y completo) de las fichas de Archivos que no son de un grupo especial.
 $archNames=[];$rr=$db->query('SELECT p.ID,p.Nombre,pp.NombreCompleto FROM Personaje p JOIN PersonajePerfil pp ON pp.ID=p.ID');while($x=$rr->fetch_row()){if(!empty($estados[(string)$x[0]]['especial']))continue;foreach([$x[1],$x[2]] as $nm){$k=favor_estado_clave((string)$nm);if($k!=='')$archNames[$k]=true;}}$rr->free();
 $res=$db->query($sql);while($r=$res->fetch_assoc()){$cid=(string)$r['ID'];$est=$estados[$cid]??[];
  $grupo=favor_estado_clave((string)$r['JugadorNombre']);
  if(!empty($est['especial'])&&empty($est['npc'])&&!isset($related[$cid])&&(!empty($est['gemelos'])||isset($archNames[favor_estado_clave((string)$r['Nombre'])])))continue;
  $r['displayName']=(string)($r['NombreCompleto']?:$r['Nombre']);$r['Estado']=$est['estado']??'Activo';$r['Categoria']=(string)($r['Categoria']?:'Personaje');
  if($r['Categoria']!=='NPC'&&in_array($grupo,FAVOR_ESTADO_GRUPOS_FUERA,true))$r['Categoria']=REL_FUERA;
  elseif($r['Categoria']!=='NPC'&&$grupo===FAVOR_ESTADO_GRUPO_MUERTOS)$r['Categoria']=REL_MUERTOS;
  $characters[$cid]=$r;}$res->free();
 $editableCharacters=array_filter($characters,fn($c)=>rel_can_edit($user,$c));
 $focusId=trim((string)($_GET['character']??''));
 if($focusId===''||!isset($characters[$focusId])){$focusId=(string)(array_key_first($editableCharacters)??array_key_first($characters)??'');}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  favor_verify_csrf((string)($_POST['csrf']??''));
  // «character» es el personaje propio desde el que se edita (Personaje A al crear).
  $actorId=trim((string)($_POST['character']??''));$actor=$characters[$actorId]??null;
  if(!$actor)throw new RuntimeException('Personaje no válido.');
  if(!rel_can_edit($user,$actor))throw new RuntimeException('No tienes permiso para modificar las relaciones de este personaje.');
  $action=(string)($_POST['action']??'');
  if($action==='add'){
   $other=trim((string)($_POST['other']??''));if($other===''||$other===$actorId||!isset($characters[$other]))throw new RuntimeException('Selecciona otro personaje.');
   $a=$actorId;$b=$other;if(strcmp($a,$b)>0){[$a,$b]=[$b,$a];}
   $vinculo=(string)($_POST['vinculo']??'');$type=mb_substr(trim((string)($_POST['type']??'')),0,80,'UTF-8');
   if(!isset(REL_VINCULOS[$vinculo]))$vinculo=$type!==''?rel_guess_vinculo($type):'aliados';
   if($type==='')$type=REL_VINCULOS[$vinculo][0];
   $note=mb_substr(trim((string)($_POST['note']??'')),0,500,'UTF-8');$author=(string)($user['username']??'');
   $st=$db->prepare('INSERT INTO PersonajeRelacion (PersonajeA,PersonajeB,Tipo,Vinculo,Nota,CreadoPor) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE Tipo=VALUES(Tipo),Vinculo=VALUES(Vinculo),Nota=VALUES(Nota),CreadoPor=VALUES(CreadoPor)');$st->bind_param('ssssss',$a,$b,$type,$vinculo,$note,$author);$st->execute();$updated=$st->affected_rows!==1;$st->close();
   $message=$updated?'Vínculo actualizado.':'Vínculo guardado.';$ok=$updated?'editada':'guardada';
  }elseif($action==='delete'){
   $id=max(0,(int)($_POST['id']??0));$st=$db->prepare('SELECT PersonajeA,PersonajeB FROM PersonajeRelacion WHERE ID=? LIMIT 1');$st->bind_param('i',$id);$st->execute();$rr=$st->get_result()->fetch_assoc();$st->close();if(!$rr)throw new RuntimeException('La relación ya no existe.');
   if($actorId!==$rr['PersonajeA']&&$actorId!==$rr['PersonajeB'])throw new RuntimeException('Esa relación no pertenece al personaje seleccionado.');
   $st=$db->prepare('DELETE FROM PersonajeRelacion WHERE ID=?');$st->bind_param('i',$id);$st->execute();$st->close();$message='Vínculo eliminado.';$ok='eliminada';
  }else throw new RuntimeException('Acción no válida.');
  if($wantsJson)rel_json(['ok'=>true,'message'=>$message,'rels'=>rel_load($db)]);
  // Sin JS: vuelve a la página (así recargar no repite el envío).
  $back=trim((string)($_POST['return']??''));if(!isset($characters[$back]))$back=$actorId;
  header('Location: relaciones.php?character='.rawurlencode($back).'&ok='.$ok.'#mapa',true,303);exit;
 }
 $relations=rel_load($db);
}catch(Throwable $e){
 if($wantsJson)rel_json(['ok'=>false,'error'=>$e->getMessage()],400);
 $error=$e->getMessage();
}finally{$db->close();}
$okMessages=['guardada'=>'Vínculo guardado.','editada'=>'Vínculo actualizado.','eliminada'=>'Vínculo eliminado.'];
$flash=$okMessages[(string)($_GET['ok']??'')]??'';
$focus=$focusId!==''?($characters[$focusId]??null):null;
// Censo para el JS: todos los personajes (para el mapa y la búsqueda) con la marca «edit».
$chars=[];foreach($characters as $id=>$c){$chars[]=['id'=>(string)$id,'name'=>(string)$c['displayName'],'player'=>(string)($c['JugadorNombre']??''),'category'=>(string)($c['Categoria']??'Personaje'),'estado'=>favor_estado_publico((string)($c['Estado']??'Activo')),'image'=>favor_thumb_url((string)($c['PortraitUrl']?:$c['IconUrl']??'')),'edit'=>rel_can_edit($user,$c)];}
$playerNames=array_values(array_unique(array_filter(array_map(fn($c)=>(string)($c['JugadorNombre']??''),$characters))));sort($playerNames,SORT_NATURAL|SORT_FLAG_CASE);
// Para un jugador, la lista empieza mostrando sus propios personajes.
$myPlayer='';if(!$isAdmin&&$playerId!==''){foreach($characters as $c){if((string)$c['Jugador']===$playerId){$myPlayer=(string)($c['JugadorNombre']??'');break;}}}
$canLink=$user&&array_filter($chars,fn($c)=>$c['edit']);
$vincJs=[];foreach(REL_VINCULOS as $k=>[$l,$col])$vincJs[$k]=['label'=>$l,'color'=>$col,'icon'=>REL_ICONOS[$k]];
$data=['chars'=>$chars,'rels'=>$relations,'focus'=>$focusId,'vinculos'=>$vincJs,'canLink'=>(bool)$canLink,'isAdmin'=>(bool)$isAdmin,'myPlayer'=>$myPlayer,'loggedIn'=>(bool)$user];
$estadoOpts='';foreach(array_keys(FAVOR_ESTADOS) as $en)$estadoOpts.='<option value="'.hr($en).'">'.hr($en).'</option>';
$tipoOpts='<option value="">Todos</option><option value="Personaje">Personajes</option><option value="NPC">NPC</option><option value="'.hr(REL_MUERTOS).'">Muertos</option><option value="'.hr(REL_FUERA).'">Personajes Fuera</option>';
$playerOpts='';foreach($playerNames as $pn)$playerOpts.='<option value="'.hr($pn).'">'.hr($pn).'</option>';
$focusName=$focus?(string)$focus['displayName']:'';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Relaciones · Archivos del Gremio</title>
<?php favor_head('Relaciones · Archivos del Gremio', 'Vínculos, alianzas y rivalidades entre los aventureros del Favor de Jack.'); ?>
<link rel="stylesheet" href="relaciones.css<?= favor_v(__DIR__ . '/relaciones.css') ?>">
</head>
<body class="rel-page">
<?php favor_site_bar(); ?>
<main class="rel-hall" id="relHall">
 <header class="rel-head">
  <a class="rel-back" href="./"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg><span>Archivos del Gremio</span></a>
  <div class="rel-title">
   <p class="rel-eyebrow">Favor de Jack</p>
   <h1>Relaciones</h1>
   <p class="rel-sub">Vínculos, alianzas y rivalidades del gremio</p>
  </div>
  <p class="rel-head-count"><b id="relTotal"><?= count($relations) ?></b> <span>lazos en el archivo</span></p>
 </header>

 <?php if($error):?><div class="rel-notice is-error" role="alert"><?=hr($error)?></div><?php endif;?>

 <aside class="rel-roster rel-panel" aria-labelledby="rosterTitle">
  <div class="rel-panel-head">
   <h2 id="rosterTitle">Expedientes</h2>
   <p>Elige quién ocupa el centro del mapa.</p>
  </div>
  <label class="rel-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><span class="sr-only">Buscar personaje</span><input id="rosterSearch" type="search" placeholder="Buscar personaje…" autocomplete="off"></label>
  <div class="rel-filters">
   <label><span>Tipo</span><select id="rosterCategory"><?=$tipoOpts?></select></label>
   <label><span>Estado</span><select id="rosterEstado"><option value="">Todos</option><?=$estadoOpts?></select></label>
   <label class="wide"><span>Jugador</span><select id="rosterPlayer"><option value="">Todos los jugadores</option><?=$playerOpts?></select></label>
  </div>
  <div class="rel-roster-list" id="rosterList" role="list"></div>
  <p class="rel-roster-count" id="rosterCount" aria-live="polite"></p>
 </aside>

 <section class="rel-board" id="mapa" aria-labelledby="boardTitle">
  <div class="rel-board-frame">
   <div class="rel-plaque">
    <span class="rel-plaque-face" id="plaqueFace" aria-hidden="true"></span>
    <div><h2 id="boardTitle"><?= $focusName!==''?hr($focusName):'Relaciones del gremio' ?></h2><p id="boardSub">Red de relaciones</p></div>
   </div>
   <label class="rel-ring2"><input type="checkbox" id="ring2Toggle"><span>Ver también sus otros lazos</span></label>
   <div class="rel-map" id="relMap"><svg class="rel-edges" id="relEdges" aria-hidden="true"></svg><div class="rel-nodes" id="relNodes"></div><div class="rel-map-empty" id="relEmpty" hidden></div></div>
   <p class="rel-map-hint"><span class="hint-mouse">Haz clic en</span><span class="hint-touch">Toca</span> un retrato para centrar la red en ese personaje.</p>
  </div>
  <ul class="rel-legend" aria-label="Colores de los vínculos">
   <?php foreach(REL_VINCULOS as $vk=>[$vl,$vc]):?><li style="--vc:<?=hr($vc)?>"><?=rel_icon($vk)?><?=hr($vl)?></li><?php endforeach;?>
  </ul>
 </section>

 <aside class="rel-summary rel-panel is-leather" aria-labelledby="summaryTitle">
  <div class="rel-panel-head">
   <h2 id="summaryTitle">Vínculos<?= $focusName!==''?' de '.hr($focusName):'' ?></h2>
   <p id="summaryCount">Lazos registrados</p>
  </div>
  <div class="rel-type-counts" id="typeCounts"></div>
  <ul class="rel-bond-list" id="bondList"></ul>
 </aside>

 <section class="rel-forge rel-panel" id="vincular" aria-labelledby="forgeTitle">
  <div class="rel-forge-intro">
   <svg class="rel-forge-chain" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
   <div><h2 id="forgeTitle">Vincular personajes</h2><p id="forgeLead">Une a dos miembros del gremio con un lazo.</p></div>
  </div>
  <?php if($canLink):?>
  <form method="post" id="relForm" class="rel-forge-form" novalidate>
   <input type="hidden" name="csrf" value="<?=hr($csrf)?>">
   <input type="hidden" name="action" value="add">
   <input type="hidden" name="character" id="slotAInput" value="">
   <input type="hidden" name="other" id="slotBInput" value="">
   <input type="hidden" name="return" id="returnInput" value="<?=hr($focusId)?>">
   <div class="rel-pair">
    <button type="button" class="rel-slot" id="slotA" data-slot="a" aria-haspopup="dialog"><span class="rel-slot-face"></span><span class="rel-slot-text"><small>Personaje A · tuyo</small><strong>Elegir…</strong></span></button>
    <div class="rel-bond-preview" id="bondPreview" aria-hidden="true"><svg viewBox="0 0 120 24" preserveAspectRatio="none"><path d="M2 12 C 30 2, 90 22, 118 12"/></svg><span class="rel-bond-badge"></span></div>
    <button type="button" class="rel-slot" id="slotB" data-slot="b" aria-haspopup="dialog"><span class="rel-slot-face"></span><span class="rel-slot-text"><small>Personaje B</small><strong>Elegir…</strong></span></button>
   </div>
   <fieldset class="rel-kinds"><legend>Tipo de vínculo</legend>
    <?php foreach(REL_VINCULOS as $vk=>[$vl,$vc]):?><label style="--vc:<?=hr($vc)?>"><input type="radio" name="vinculo" value="<?=hr($vk)?>"<?=$vk==='amistad'?' checked':''?>><span><?=rel_icon($vk)?><?=hr($vl)?></span></label><?php endforeach;?>
   </fieldset>
   <div class="rel-details">
    <label><span>Cómo lo describirías <i>(opcional)</i></span><input name="type" id="typeInput" maxlength="80" placeholder="Hermanos, compañeros de armas…"></label>
    <label><span>Nota o contexto <i>(opcional)</i></span><textarea name="note" id="noteInput" maxlength="500" rows="2" placeholder="Cómo se conocieron, qué los une…"></textarea></label>
   </div>
   <div class="rel-forge-foot">
    <p class="rel-forge-preview" id="forgePreview" aria-live="polite"></p>
    <button type="button" class="rel-btn is-ghost" id="cancelEdit" hidden>Cancelar edición</button>
    <button type="submit" class="rel-btn is-gold" id="saveBond"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Añadir relación</span></button>
   </div>
  </form>
  <?php elseif($user):?>
  <p class="rel-forge-locked">Todavía no tienes personajes propios en los Archivos. Cuando tengas uno podrás vincularlo con cualquier miembro del gremio.</p>
  <?php else:?>
  <p class="rel-forge-locked"><a href="<?=hr(favor_base_path().'login.php?next='.rawurlencode((string)($_SERVER['REQUEST_URI']??'')))?>">Inicia sesión</a> para vincular a tus personajes con el resto del gremio.</p>
  <?php endif;?>
 </section>
</main>

<dialog class="rel-picker" id="relPicker" aria-labelledby="pickerTitle">
 <form method="dialog" class="rel-picker-head"><h2 id="pickerTitle">Elegir personaje</h2><button class="rel-icon-btn" value="cancel" aria-label="Cerrar">✕</button></form>
 <label class="rel-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><span class="sr-only">Buscar personaje</span><input id="pickerSearch" type="search" placeholder="Buscar personaje…" autocomplete="off"></label>
 <div class="rel-filters is-three">
  <label><span>Tipo</span><select id="pickerCategory"><?=$tipoOpts?></select></label>
  <label><span>Estado</span><select id="pickerEstado"><option value="">Todos</option><?=$estadoOpts?></select></label>
  <label><span>Jugador</span><select id="pickerPlayer"><option value="">Todos</option><?=$playerOpts?></select></label>
 </div>
 <div class="rel-roster-list is-picker" id="pickerList" role="list"></div>
 <p class="rel-roster-count" id="pickerCount" aria-live="polite"></p>
</dialog>

<div class="rel-toast" id="relToast" role="status" aria-live="polite"<?= $flash===''?' hidden':'' ?>><?=hr($flash)?></div>
<script type="application/json" id="relData"><?=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP)?></script>
<script src="relaciones.js<?= favor_v(__DIR__ . '/relaciones.js') ?>"></script>
</body></html>
