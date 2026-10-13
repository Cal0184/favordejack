(() => {
'use strict';

const dataNode=document.getElementById('shopData');
let data={products:[],shop:{}};
try{data=JSON.parse(dataNode?.textContent||'{}')}catch(_){}
const products=Array.isArray(data.products)?data.products:[];

const colors={
'Curación':'#74d193','Restauración':'#69d2ca','Blindaje':'#72aee4','Arsenal':'#df7b46',
'Esencias':'#d4b54f','Conjuros en Lata':'#ae90e6','Utilidad':'#d78ec6','Néctares':'#dd6659'
};

const grid=document.getElementById('productGrid');
const search=document.getElementById('searchInput');
const sort=document.getElementById('sortSelect');
const count=document.getElementById('resultsCount');
const empty=document.getElementById('emptyState');
const clear=document.getElementById('clearFilters');
const tabs=[...document.querySelectorAll('.category-tab')];
const favBtn=document.getElementById('favoritesButton');
const favCount=document.getElementById('favoriteCount');
const modal=document.getElementById('productModal');
const modalContent=document.getElementById('modalContent');
const toast=document.getElementById('toast');

let category='Todos',favoritesOnly=false,lastFocused=null,toastTimer=0;
let favorites=loadFavorites();

function loadFavorites(){try{const x=JSON.parse(localStorage.getItem('lpp:favorites')||'[]');return new Set(Array.isArray(x)?x:[])}catch(_){return new Set()}}
function saveFavorites(){try{localStorage.setItem('lpp:favorites',JSON.stringify([...favorites]))}catch(_){}}
function esc(v){return String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;')}
function norm(v){return String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()}
function numericPrice(v){const m=norm(v).match(/(\d+(?:[.,]\d+)?)/);return m?Number(m[1].replace(',','.')):Infinity}
function fallbackImage(p){return esc(p.fallback||'assets/placeholders/utilidad.svg')}
function img(p){const fb=fallbackImage(p);return `<img src="${esc(p.image||fb)}" data-fallback="${fb}" alt="${esc(p.name)}" loading="lazy">`}
function bindFallbacks(scope=document){scope.querySelectorAll('img[data-fallback]').forEach(im=>{if(im.dataset.bound)return;im.dataset.bound='1';im.addEventListener('error',()=>{im.src=im.dataset.fallback},{once:true})})}
function quick(p){const a=[];if(p.duration)a.push(`Duración: ${p.duration}`);if(p.application)a.push(`Aplicación: ${p.application}`);if(p.save)a.push(p.save);return a.slice(0,2).map(x=>`<span>${esc(x)}</span>`).join('')}
function filtered(){
 const q=norm(search?.value||'').trim();
 let list=products.filter(p=>{
  if(category!=='Todos'&&p.majorCategory!==category)return false;
  if(favoritesOnly&&!favorites.has(p.id))return false;
  if(!q)return true;
  return norm([p.name,p.majorCategory,p.subcategory,p.effect,p.duration,p.application,p.save,p.price].join(' ')).includes(q)
 });
 const s=sort?.value||'default';
 if(s==='name-asc')list.sort((a,b)=>a.name.localeCompare(b.name,'es'));
 if(s==='price-asc')list.sort((a,b)=>numericPrice(a.price)-numericPrice(b.price));
 if(s==='price-desc')list.sort((a,b)=>numericPrice(b.price)-numericPrice(a.price));
 return list;
}
function card(p){
 const fav=favorites.has(p.id),accent=colors[p.majorCategory]||'#ad8ec0';
 return `<article class="product-card" style="--accent:${accent}">
 <div class="product-media">${img(p)}<button class="favorite-icon ${fav?'active':''}" type="button" data-favorite="${esc(p.id)}" aria-pressed="${fav?'true':'false'}">♥</button></div>
 <div class="product-body">
  <div class="product-meta"><span class="product-category">${esc(p.majorCategory)}</span><span class="product-price">${esc(p.price)}</span></div>
  <h3>${esc(p.name)}</h3><span class="product-subcategory">${esc(p.subcategory)}</span>
  ${quick(p)?`<div class="product-quick">${quick(p)}</div>`:''}
  <p class="product-description">${esc(p.effect)}</p>
  <button class="product-open" type="button" data-open="${esc(p.id)}">Leer etiqueta completa</button>
 </div></article>`
}
function render(){
 const list=filtered();
 if(grid){grid.innerHTML=list.map(card).join('');grid.hidden=!list.length;bindFallbacks(grid)}
 if(empty)empty.hidden=!!list.length;
 if(count)count.textContent=`${list.length} ${list.length===1?'preparado':'preparados'}`;
 const active=category!=='Todos'||favoritesOnly||!!search?.value.trim()||(sort?.value||'default')!=='default';
 if(clear)clear.hidden=!active;
 if(favCount)favCount.textContent=String(favorites.size);
 if(favBtn)favBtn.setAttribute('aria-pressed',favoritesOnly?'true':'false')
}
function setCategory(value){
 category=value;
 tabs.forEach(t=>t.classList.toggle('active',t.dataset.category===category));
 render()
}
function toastMsg(msg){if(!toast)return;clearTimeout(toastTimer);toast.textContent=msg;toast.classList.add('show');toastTimer=setTimeout(()=>toast.classList.remove('show'),1600)}
function toggleFavorite(id){const p=products.find(x=>x.id===id);if(!p)return;if(favorites.has(id)){favorites.delete(id);toastMsg(`${p.name} retirado de favoritos`)}else{favorites.add(id);toastMsg(`${p.name} guardado para revisar`)}saveFavorites();render()}
function openProduct(id){
 const p=products.find(x=>x.id===id);if(!p||!modal||!modalContent)return;
 lastFocused=document.activeElement;
 const accent=colors[p.majorCategory]||'#ad8ec0';
 const stats=[];
 if(p.duration)stats.push(['Duración',p.duration]);
 if(p.application)stats.push(['Aplicación',p.application]);
 if(p.save)stats.push(['Salvación',p.save]);
 const statsHtml=stats.length?`<div class="detail-stats">${stats.map(([a,b])=>`<div class="detail-stat"><span>${esc(a)}</span><strong>${esc(b)}</strong></div>`).join('')}</div>`:'';
 modalContent.innerHTML=`<article class="detail" style="--accent:${accent}">
 <div class="detail-media">${img(p)}</div>
 <div class="detail-body">
  <span class="product-category">${esc(p.majorCategory)}</span>
  <h2 id="modalTitle">${esc(p.name)}</h2>
  <div class="detail-price">${esc(p.price)}</div>
  <div class="detail-sub">${esc(p.subcategory)}</div>
  ${statsHtml}
  <div class="detail-description"><p>${esc(p.effect)}</p></div>
  <div class="detail-note">Esta ficha reproduce la información del catálogo de la tienda. Las compras todavía no modifican la Banca Digital ni un stock compartido.</div>
 </div></article>`;
 bindFallbacks(modalContent);
 modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open')
}
function closeModal(){if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');if(lastFocused instanceof HTMLElement)lastFocused.focus()}

tabs.forEach(t=>t.addEventListener('click',()=>setCategory(t.dataset.category||'Todos')));
document.querySelectorAll('[data-jump-category]').forEach(a=>a.addEventListener('click',()=>setCategory(a.dataset.jumpCategory||'Todos')));
search?.addEventListener('input',render);sort?.addEventListener('change',render);
clear?.addEventListener('click',()=>{if(search)search.value='';if(sort)sort.value='default';favoritesOnly=false;setCategory('Todos')});
favBtn?.addEventListener('click',()=>{favoritesOnly=!favoritesOnly;render()});
document.addEventListener('click',e=>{
 const f=e.target.closest('[data-favorite]');if(f){e.preventDefault();e.stopPropagation();toggleFavorite(f.dataset.favorite);return}
 const o=e.target.closest('[data-open]');if(o){openProduct(o.dataset.open);return}
 if(e.target.closest('[data-close-modal]'))closeModal()
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal?.classList.contains('open'))closeModal()});
render();
})();