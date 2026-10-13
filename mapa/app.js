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
const minZoom=.72,maxZoom=2.2;
const coarse=window.matchMedia?.('(hover: none), (pointer: coarse)')?.matches??false;

const clamp=(v,min,max)=>Math.min(max,Math.max(min,v));
const shopById=id=>shops.find(s=>String(s.id)===String(id));
const markerById=id=>markers.find(m=>m.dataset.shopId===id);

function baseMapWidth(){return Math.min(1160,Math.max(310,viewport.clientWidth-12))}
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
function setZoom(v){zoom=clamp(v,minZoom,maxZoom);updateStageWidth(true)}
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
     markers.forEach(x=>{if(x!==m)x.classList.remove('touch-open')});
     m.classList.add('touch-open');
     if(!coarse)setTimeout(()=>m.classList.remove('touch-open'),2400);
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

directory.forEach(btn=>btn.addEventListener('click',()=>centerShop(btn.dataset.focusShop||'')));

markers.forEach(marker=>{
 marker.addEventListener('mouseenter',()=>setActive(marker.dataset.shopId||''));
 marker.addEventListener('focus',()=>setActive(marker.dataset.shopId||''));
 if(coarse){
   marker.addEventListener('click',e=>{
     if(!marker.classList.contains('touch-open')){
       e.preventDefault();
       markers.forEach(x=>x.classList.remove('touch-open'));
       marker.classList.add('touch-open');
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

function init(){
 zoom=innerWidth<=560?1.16:1;
 updateStageWidth(false);
 requestAnimationFrame(()=>{if(activeShop&&!editMode)centerShop(activeShop,false)});
}
if(image.complete)init();else image.addEventListener('load',init,{once:true});
let rt=0;addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(()=>updateStageWidth(true),100)});
})();