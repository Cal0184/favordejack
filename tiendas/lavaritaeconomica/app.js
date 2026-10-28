(() => {
'use strict';

let data={products:[],shop:{}};
try{data=JSON.parse(document.getElementById('shopData')?.textContent||'{}')}catch(_){}
const products=Array.isArray(data.products)?data.products:[];

const colors={
'Pergaminos':'#d3c681','Tatuajes':'#d96dc2','Brebajes':'#64d78a','Mezclas especiales':'#72cbd2',
'Sustancias':'#d4c64a','Objetos mágicos?':'#83c7cf','Defectuosos':'#e1714f'
};

const grid=document.getElementById('productGrid');
const search=document.getElementById('searchInput');
const sort=document.getElementById('sortSelect');
const tabs=[...document.querySelectorAll('[data-category]')];
const count=document.getElementById('resultsCount');
const clear=document.getElementById('clearFilters');
const empty=document.getElementById('emptyState');
const favBtn=document.getElementById('favoritesButton');
const favCount=document.getElementById('favoriteCount');
const modal=document.getElementById('productModal');
const modalContent=document.getElementById('modalContent');
const toast=document.getElementById('toast');
const musicBtn=document.getElementById('musicButton');
const audio=document.getElementById('backgroundMusic');

let category='Todos',favoritesOnly=false,lastFocused=null,toastTimer=0;
let favorites=loadFavs();

function loadFavs(){try{const v=JSON.parse(localStorage.getItem('lve:favorites')||'[]');return new Set(Array.isArray(v)?v:[])}catch(_){return new Set()}}
function saveFavs(){try{localStorage.setItem('lve:favorites',JSON.stringify([...favorites]))}catch(_){}}
function esc(v){return String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;')}
function norm(v){return String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()}
function price(v){const m=norm(v).match(/(\d+(?:[.,]\d+)?)/);return m?Number(m[1].replace(',','.')):Infinity}
function img(p){const fb=esc(p.fallback||'assets/placeholders/objetos.svg');return `<img src="${esc(p.image||fb)}" data-fallback="${fb}" alt="${esc(p.name)}" loading="lazy">`}
function bindFallbacks(scope=document){scope.querySelectorAll('img[data-fallback]').forEach(im=>{if(im.dataset.bound)return;im.dataset.bound='1';im.addEventListener('error',()=>{im.src=im.dataset.fallback},{once:true})})}
function haystack(p){return [p.name,p.category,p.subcategory,p.price,p.description,p.effect,p.sideEffect,p.addiction,p.sellerNote,...(p.tags||[]),...(p.stats||[]).flat()].join(' ')}
function filtered(){
 const q=norm(search?.value||'').trim();
 let list=products.filter(p=>{
   if(category!=='Todos'&&p.category!==category)return false;
   if(favoritesOnly&&!favorites.has(p.id))return false;
   return !q||norm(haystack(p)).includes(q)
 });
 const s=sort?.value||'default';
 if(s==='name-asc')list.sort((a,b)=>a.name.localeCompare(b.name,'es'));
 if(s==='price-asc')list.sort((a,b)=>price(a.price)-price(b.price));
 if(s==='price-desc')list.sort((a,b)=>price(b.price)-price(a.price));
 return list;
}
function tags(p){return (p.tags||[]).slice(0,3).map(x=>`<span>${esc(x)}</span>`).join('')}
function teaser(p){
 if(p.effect)return p.effect;
 if(p.description)return p.description;
 return 'Sin descripción fiable.';
}
function card(p){
 const accent=colors[p.category]||'#74ef85',fav=favorites.has(p.id);
 return `<article class="product-card" style="--accent:${accent}" data-risk="${esc(p.risk||'')}">
 <div class="product-media">${img(p)}<button class="favorite-icon ${fav?'active':''}" type="button" data-favorite="${esc(p.id)}" aria-pressed="${fav?'true':'false'}">♥</button></div>
 <div class="product-body">
   <div class="product-meta"><span class="product-category">${esc(p.category)}</span><span class="product-price">${esc(p.price)}</span></div>
   <h3>${esc(p.name)}</h3><span class="product-sub">${esc(p.subcategory||'')}</span>
   ${tags(p)?`<div class="product-tags">${tags(p)}</div>`:''}
   <p class="product-description">${esc(teaser(p))}</p>
   <button class="product-open" type="button" data-open="${esc(p.id)}">Abrir bolsa</button>
 </div></article>`;
}
function render(){
 const list=filtered();
 if(grid){grid.innerHTML=list.map(card).join('');grid.hidden=!list.length;bindFallbacks(grid)}
 if(empty)empty.hidden=!!list.length;
 if(count)count.textContent=`${list.length} ${list.length===1?'objeto encontrado':'objetos encontrados'}`;
 if(clear)clear.hidden=category==='Todos'&&!favoritesOnly&&!search?.value.trim()&&(sort?.value||'default')==='default';
 if(favCount)favCount.textContent=String(favorites.size);
 if(favBtn)favBtn.setAttribute('aria-pressed',favoritesOnly?'true':'false');
}
function setCategory(value){
 category=value;
 tabs.forEach(t=>t.classList.toggle('active',t.dataset.category===value));
 render()
}
function toastMsg(msg){if(!toast)return;clearTimeout(toastTimer);toast.textContent=msg;toast.classList.add('show');toastTimer=setTimeout(()=>toast.classList.remove('show'),1700)}
function toggleFav(id){const p=products.find(x=>x.id===id);if(!p)return;if(favorites.has(id)){favorites.delete(id);toastMsg('Devuelto a la caja.')}else{favorites.add(id);toastMsg('Marcado para después.')}saveFavs();render()}
function detailSections(p){
 const out=[];
 if(p.effect)out.push(`<h4>Efecto</h4><p>${esc(p.effect)}</p>`);
 if(p.sideEffect)out.push(`<h4>Efecto secundario / coste</h4><p>${esc(p.sideEffect)}</p>`);
 if(p.addiction)out.push(`<h4>Adicción</h4><p>${esc(p.addiction)}</p>`);
 return out.join('');
}
function openProduct(id){
 const p=products.find(x=>x.id===id);if(!p||!modal||!modalContent)return;
 lastFocused=document.activeElement;
 const accent=colors[p.category]||'#74ef85';
 const stats=Array.isArray(p.stats)&&p.stats.length?`<div class="detail-stats">${p.stats.map(s=>`<div class="detail-stat"><span>${esc(s[0])}</span><strong>${esc(s[1])}</strong></div>`).join('')}</div>`:'';
 const tagHtml=(p.tags||[]).length?`<div class="detail-tags">${p.tags.map(x=>`<span>${esc(x)}</span>`).join('')}</div>`:'';
 modalContent.innerHTML=`<article class="detail" style="--accent:${accent}">
 <div class="detail-media">${img(p)}</div>
 <div class="detail-body">
   <span class="product-category">${esc(p.category)}</span>
   <h2 id="modalTitle">${esc(p.name)}</h2>
   <div class="detail-price">${esc(p.price)}</div>
   <div class="detail-sub">${esc(p.subcategory||'')}</div>
   ${stats}${tagHtml}
   <div class="detail-description"><p>${esc(p.description||'Sin descripción disponible.')}</p>${detailSections(p)}</div>
   ${p.sellerNote?`<div class="detail-note">${esc(p.sellerNote)}</div>`:''}
   ${window.FavorCompra?FavorCompra.html(p):''}
 </div></article>`;
 bindFallbacks(modalContent);
 modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open')
}
function closeModal(){if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');if(lastFocused instanceof HTMLElement)lastFocused.focus()}

tabs.forEach(t=>t.addEventListener('click',()=>setCategory(t.dataset.category||'Todos')));
search?.addEventListener('input',render);sort?.addEventListener('change',render);
clear?.addEventListener('click',()=>{category='Todos';favoritesOnly=false;if(search)search.value='';if(sort)sort.value='default';tabs.forEach(t=>t.classList.toggle('active',t.dataset.category==='Todos'));render()});
favBtn?.addEventListener('click',()=>{favoritesOnly=!favoritesOnly;render()});
document.addEventListener('click',e=>{
 const f=e.target.closest('[data-favorite]');if(f){e.preventDefault();e.stopPropagation();toggleFav(f.dataset.favorite);return}
 const o=e.target.closest('[data-open]');if(o){openProduct(o.dataset.open);return}
 if(e.target.closest('[data-close-modal]'))closeModal()
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal?.classList.contains('open'))closeModal()});

musicBtn?.addEventListener('click',async()=>{
 if(!audio)return;
 if(!audio.paused){audio.pause();musicBtn.setAttribute('aria-pressed','false');musicBtn.innerHTML='<span>♫</span> Música';return}
 try{
   await audio.play();
   musicBtn.setAttribute('aria-pressed','true');
   musicBtn.innerHTML='<span>■</span> Pausar';
 }catch(_){
   toastMsg('Añade tu audio como assets/tema.mp3');
   musicBtn.setAttribute('aria-pressed','false');
 }
});
audio?.addEventListener('error',()=>{musicBtn?.setAttribute('aria-pressed','false')});

render();
})();