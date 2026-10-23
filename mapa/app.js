(() => {
'use strict';

const viewport=document.getElementById('mapViewport');
const stage=document.getElementById('mapStage');
const image=document.getElementById('mapImage');
const status=document.getElementById('mapStatus');
const resetLabel=document.querySelector('[data-zoom="reset"]');
const markers=[...document.querySelectorAll('.shop-marker')];
const directory=[...document.querySelectorAll('[data-focus-shop]')];
const shops=Array.isArray(window.WATERDEEP_SHOPS)?window.WATERDEEP_SHOPS:[];

if(!viewport||!stage||!image)return;

let zoom=1,baseWidth=1000,activeShop=shops[0]?.id||'';
const coarse=window.matchMedia?.('(hover: none), (pointer: coarse)')?.matches??false;
const narrow=window.matchMedia?.('(max-width: 820px)');
const board=viewport.closest('.map-board');
// En pantallas estrechas el plano nunca es más angosto que la pantalla (sin franjas grises).
const isCompact=()=>narrow?.matches??false;
const minZoom=()=>isCompact()?1:.72;
const maxZoom=()=>isCompact()?3:2.2;

const clamp=(v,min,max)=>Math.min(max,Math.max(min,v));
const shopById=id=>shops.find(s=>String(s.id)===String(id));
const markerById=id=>markers.find(m=>m.dataset.shopId===id);

function baseMapWidth(){return isCompact()?viewport.clientWidth:Math.min(1160,Math.max(310,viewport.clientWidth-12))}
function updateStageWidth(preserve=true){
 const prev=stage.getBoundingClientRect(),prevW=prev.width||1,prevH=prev.height||1;
 const cx=(viewport.scrollLeft+viewport.clientWidth/2)/prevW;
 const cy=(viewport.scrollTop+viewport.clientHeight/2)/prevH;
 baseWidth=baseMapWidth();stage.style.width=`${Math.round(baseWidth*zoom)}px`;
 requestAnimationFrame(()=>{
   if(!preserve)return;
   const r=stage.getBoundingClientRect();
   viewport.scrollLeft=clamp(cx*r.width-viewport.clientWidth/2,0,Math.max(0,r.width-viewport.clientWidth));
   viewport.scrollTop=clamp(cy*r.height-viewport.clientHeight/2,0,Math.max(0,r.height-viewport.clientHeight));
 });
 if(resetLabel)resetLabel.textContent=`${Math.round(zoom*100)}%`;
}
function setZoom(v){zoom=clamp(v,minZoom(),maxZoom());updateStageWidth(true)}
// Zoom anclado a un punto de la pantalla (los dedos al pellizcar).
function setZoomAt(v,clientX,clientY){
 const next=clamp(v,minZoom(),maxZoom());if(next===zoom)return;
 const vr=viewport.getBoundingClientRect(),r=stage.getBoundingClientRect();
 const px=clientX-vr.left,py=clientY-vr.top;
 const fx=(viewport.scrollLeft+px)/(r.width||1),fy=(viewport.scrollTop+py)/(r.height||1);
 zoom=next;stage.style.width=`${Math.round(baseWidth*zoom)}px`;
 const n=stage.getBoundingClientRect();
 viewport.scrollLeft=fx*n.width-px;viewport.scrollTop=fy*n.height-py;
 if(resetLabel)resetLabel.textContent=`${Math.round(zoom*100)}%`;
 markers.forEach(m=>{if(m.classList.contains('touch-open'))placeCard(m)});
}
// Ficha de la tienda en móvil: encima o debajo del sello y siempre dentro de la pantalla.
function placeCard(marker){
 if(!isCompact())return;
 const card=marker.querySelector('.shop-card');if(!card)return;
 const vr=viewport.getBoundingClientRect(),mr=marker.getBoundingClientRect();
 const mx=mr.left+mr.width/2-vr.left,cw=card.offsetWidth;
 const left=clamp(mx-cw/2,8,Math.max(8,vr.width-cw-8));
 marker.style.setProperty('--card-shift',`${Math.round(left-(mx-cw/2))}px`);
 const below=mr.top-vr.top<card.offsetHeight+16&&mr.bottom-vr.top<vr.height/2;
 marker.classList.toggle('card-below',below);
}
function revealCard(marker){
 if(!isCompact())return;
 const card=marker.querySelector('.shop-card');if(!card)return;
 requestAnimationFrame(()=>{
   const vr=viewport.getBoundingClientRect(),cr=card.getBoundingClientRect(),mr=marker.getBoundingClientRect();
   const top=Math.min(cr.top,mr.top),bottom=Math.max(cr.bottom,mr.bottom);
   let dy=0;
   if(top<vr.top+6)dy=top-vr.top-6;else if(bottom>vr.bottom-6)dy=bottom-vr.bottom+6;
   if(dy)viewport.scrollBy({top:dy,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
 });
}
function openMarker(m){
 markers.forEach(x=>{if(x!==m)x.classList.remove('touch-open')});
 placeCard(m);m.classList.add('touch-open');revealCard(m);
}
function setActive(id){
 activeShop=id;
 directory.forEach(b=>b.classList.toggle('active',b.dataset.focusShop===id));
 const s=shopById(id);
 if(s&&status){
   const names=(s.sellers||[]).map(x=>x.name).filter(Boolean).join(' · ');
   status.textContent=`${s.name}${names?' · '+names:''}`;
 }
}
function centerShop(id,open=true){
 const s=shopById(id),m=markerById(id);if(!s||!m)return;
 setActive(id);
 requestAnimationFrame(()=>{
   const r=stage.getBoundingClientRect(),x=clamp(Number(s.x)/100,0,1),y=clamp(Number(s.y)/100,0,1);
   viewport.scrollTo({
     left:clamp(x*r.width-viewport.clientWidth/2,0,Math.max(0,r.width-viewport.clientWidth)),
     top:clamp(y*r.height-viewport.clientHeight/2,0,Math.max(0,r.height-viewport.clientHeight)),
     behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'
   });
   if(open){
     if(isCompact())setTimeout(()=>openMarker(m),350);
     else{markers.forEach(x=>{if(x!==m)x.classList.remove('touch-open')});m.classList.add('touch-open')}
     if(!coarse&&!isCompact())setTimeout(()=>m.classList.remove('touch-open'),2400);
   }
 });
}

document.querySelectorAll('[data-zoom]').forEach(btn=>btn.addEventListener('click',()=>{
 const action=btn.dataset.zoom;
 if(action==='in')setZoom(zoom+.18);
 if(action==='out')setZoom(zoom-.18);
 if(action==='reset'){
   zoom=1;updateStageWidth(false);
   requestAnimationFrame(()=>{if(activeShop)centerShop(activeShop,false)});
 }
}));

directory.forEach(btn=>btn.addEventListener('click',()=>{
 // En móvil la lista va debajo del plano: subir hasta el plano antes de mostrar la tienda.
 if(isCompact()&&board){
   const r=board.getBoundingClientRect();
   if(r.top<0||r.top>innerHeight*.35)board.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});
 }
 centerShop(btn.dataset.focusShop||'');
}));

markers.forEach(marker=>{
 marker.addEventListener('mouseenter',()=>setActive(marker.dataset.shopId||''));
 marker.addEventListener('focus',()=>setActive(marker.dataset.shopId||''));
 if(coarse){
   marker.addEventListener('click',e=>{
     if(!marker.classList.contains('touch-open')){
       e.preventDefault();
       openMarker(marker);
       setActive(marker.dataset.shopId||'');
     }
   });
 }
});
document.addEventListener('pointerdown',e=>{
 if(coarse&&!e.target.closest('.shop-marker'))markers.forEach(m=>m.classList.remove('touch-open'));
});

// -------- Edit mode --------
// /favordejack/mapa/?edit=1
const params=new URLSearchParams(location.search);
const editMode=params.get('edit')==='1';
const panel=document.getElementById('coordinatePanel');
const editorShop=document.getElementById('editorShop');
const readout=document.getElementById('coordinateReadout');
const jsonOut=document.getElementById('coordinateJson');

function refreshEditor(id){
 const s=shopById(id);if(!s||!readout||!jsonOut)return;
 readout.textContent=`x: ${Number(s.x).toFixed(2)} · y: ${Number(s.y).toFixed(2)}`;
 jsonOut.textContent=JSON.stringify({id:s.id,x:Number(s.x),y:Number(s.y)},null,2);
}
if(editMode&&panel&&editorShop&&readout&&jsonOut){
 panel.hidden=false;stage.style.cursor='crosshair';
 const requested=params.get('shop');
 if(requested&&shopById(requested))editorShop.value=requested;
 setActive(editorShop.value);
 refreshEditor(editorShop.value);
 centerShop(editorShop.value,false);

 editorShop.addEventListener('change',()=>{
   setActive(editorShop.value);refreshEditor(editorShop.value);centerShop(editorShop.value,false);
 });

 stage.addEventListener('click',e=>{
   if(e.target.closest('.shop-marker'))return;
   const r=stage.getBoundingClientRect();
   const x=clamp(((e.clientX-r.left)/r.width)*100,0,100);
   const y=clamp(((e.clientY-r.top)/r.height)*100,0,100);
   const s=shopById(editorShop.value),m=markerById(editorShop.value);
   if(!s||!m)return;
   s.x=x;s.y=y;
   m.style.setProperty('--x',`${x}%`);m.style.setProperty('--y',`${y}%`);
   m.dataset.shopX=x.toFixed(2);m.dataset.shopY=y.toFixed(2);
   m.classList.toggle('open-left',x>=69);m.classList.toggle('open-down',y<=18);
   readout.textContent=`x: ${x.toFixed(2)} · y: ${y.toFixed(2)}`;
   jsonOut.textContent=JSON.stringify({id:s.id,x:Number(x.toFixed(2)),y:Number(y.toFixed(2))},null,2);
   setActive(s.id);
   m.classList.add('touch-open');
 });
}

// Pellizcar con dos dedos acerca o aleja solo el plano, no toda la página.
let pinch=null;
const touchDist=t=>Math.hypot(t[0].clientX-t[1].clientX,t[0].clientY-t[1].clientY);
viewport.addEventListener('touchstart',e=>{
 if(e.touches.length===2){pinch={dist:touchDist(e.touches)||1,zoom};markers.forEach(m=>m.classList.remove('touch-open'))}
},{passive:true});
viewport.addEventListener('touchmove',e=>{
 if(!pinch||e.touches.length!==2)return;
 e.preventDefault();
 const t=e.touches;
 setZoomAt(pinch.zoom*touchDist(t)/pinch.dist,(t[0].clientX+t[1].clientX)/2,(t[0].clientY+t[1].clientY)/2);
},{passive:false});
viewport.addEventListener('touchend',e=>{if(e.touches.length<2)pinch=null});
// Ctrl + rueda (o el gesto de pellizco del trackpad) en el ordenador.
viewport.addEventListener('wheel',e=>{
 if(!e.ctrlKey)return;
 e.preventDefault();
 setZoomAt(zoom*Math.exp(-e.deltaY/300),e.clientX,e.clientY);
},{passive:false});

function syncCompact(){board?.classList.toggle('compact',isCompact());if(isCompact()&&zoom<1)zoom=1}

function init(){
 syncCompact();
 zoom=1;
 updateStageWidth(false);
 requestAnimationFrame(()=>{if(activeShop&&!editMode)centerShop(activeShop,false)});
}
if(image.complete)init();else image.addEventListener('load',init,{once:true});
let rt=0;addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(()=>{syncCompact();updateStageWidth(true);markers.forEach(m=>{if(m.classList.contains('touch-open'))placeCard(m)})},100)});
})();