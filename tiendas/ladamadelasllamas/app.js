(() => {
'use strict';

const dataNode = document.getElementById('shopData');
let data = {products:[],shop:{}};
try { data = JSON.parse(dataNode?.textContent || '{}'); } catch (_) {}

const products = Array.isArray(data.products) ? data.products : [];
const grid = document.getElementById('productGrid');
const searchInput = document.getElementById('searchInput');
const sortSelect = document.getElementById('sortSelect');
const resultsCount = document.getElementById('resultsCount');
const emptyState = document.getElementById('emptyState');
const clearFilters = document.getElementById('clearFilters');
const categoryTabs = [...document.querySelectorAll('.category-tab')];
const favoritesButton = document.getElementById('favoritesButton');
const favoriteCount = document.getElementById('favoriteCount');
const modal = document.getElementById('productModal');
const modalContent = document.getElementById('modalContent');
const toast = document.getElementById('toast');

let category = 'Todos';
let favoritesOnly = false;
let lastFocused = null;
let toastTimer = 0;
let favorites = loadFavorites();

function loadFavorites(){
    try{
        const raw = JSON.parse(localStorage.getItem('ddl:favorites') || '[]');
        return new Set(Array.isArray(raw) ? raw : []);
    }catch(_){ return new Set(); }
}
function saveFavorites(){
    try{ localStorage.setItem('ddl:favorites', JSON.stringify([...favorites])); }catch(_){}
}
function esc(v){
    return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}
function norm(v){
    return String(v ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
}
function numericPrice(value){
    const match = norm(value).match(/(\d+(?:[.,]\d+)?)/);
    return match ? Number(match[1].replace(',','.')) : Number.POSITIVE_INFINITY;
}
function isArcane(p){ return p.category === 'Mejoras mágicas'; }
function imageHtml(p){
    const fallback = esc(p.fallback || 'assets/placeholders/objeto-magico.svg');
    const src = esc(p.image || fallback);
    return `<img src="${src}" data-fallback="${fallback}" alt="${esc(p.name)}" loading="lazy">`;
}
function bindFallbacks(scope=document){
    scope.querySelectorAll('img[data-fallback]').forEach(img=>{
        if(img.dataset.bound) return;
        img.dataset.bound='1';
        img.addEventListener('error',()=>{
            if(img.src !== img.dataset.fallback) img.src = img.dataset.fallback;
        },{once:true});
    });
}
function filtered(){
    const q = norm(searchInput?.value || '').trim();
    let list = products.filter(p=>{
        if(category !== 'Todos' && p.category !== category) return false;
        if(favoritesOnly && !favorites.has(p.id)) return false;
        if(!q) return true;
        return norm([p.name,p.category,p.price,p.description,p.appliesTo,...(p.properties||[]),...(p.variants||[])].join(' ')).includes(q);
    });
    const sort = sortSelect?.value || 'default';
    if(sort==='name-asc') list.sort((a,b)=>a.name.localeCompare(b.name,'es'));
    if(sort==='price-asc') list.sort((a,b)=>numericPrice(a.price)-numericPrice(b.price));
    if(sort==='price-desc') list.sort((a,b)=>numericPrice(b.price)-numericPrice(a.price));
    return list;
}
function tags(p){
    const list = [];
    if(p.appliesTo) list.push(p.appliesTo);
    if(Array.isArray(p.properties)) list.push(...p.properties.slice(0,2));
    if(Array.isArray(p.variants) && p.variants.length) list.push(`${p.variants.length} variantes`);
    return list.slice(0,3).map(x=>`<span>${esc(x)}</span>`).join('');
}
function card(p){
    const fav = favorites.has(p.id);
    return `<article class="product-card" data-category="${esc(p.category)}" style="--accent:${isArcane(p)?'#65d8d2':'#b97a37'}">
        <div class="product-media">${imageHtml(p)}
            <button class="favorite-icon ${fav?'active':''}" type="button" data-favorite="${esc(p.id)}" aria-pressed="${fav?'true':'false'}">♥</button>
        </div>
        <div class="product-body">
            <div class="product-meta"><span class="product-category">${esc(p.category)}</span><span class="product-price">${esc(p.price)}</span></div>
            <h3>${esc(p.name)}</h3>
            ${tags(p)?`<div class="product-tags">${tags(p)}</div>`:''}
            <p class="product-description">${esc(p.description || '')}</p>
            <button class="product-open" type="button" data-open="${esc(p.id)}">${isArcane(p)?'Revisar mejora':'Inspeccionar pieza'}</button>
        </div>
    </article>`;
}
function render(){
    const list = filtered();
    if(grid){
        grid.innerHTML = list.map(card).join('');
        grid.hidden = !list.length;
        bindFallbacks(grid);
    }
    if(emptyState) emptyState.hidden = !!list.length;
    if(resultsCount) resultsCount.textContent = `${list.length} ${list.length===1?'entrada':'entradas'}`;
    const active = category!=='Todos' || favoritesOnly || !!searchInput?.value.trim() || (sortSelect?.value||'default')!=='default';
    if(clearFilters) clearFilters.hidden = !active;
    if(favoriteCount) favoriteCount.textContent = String(favorites.size);
    if(favoritesButton) favoritesButton.setAttribute('aria-pressed',favoritesOnly?'true':'false');
}
function toastMsg(msg){
    if(!toast) return;
    clearTimeout(toastTimer);
    toast.textContent=msg; toast.classList.add('show');
    toastTimer=setTimeout(()=>toast.classList.remove('show'),1700);
}
function toggleFavorite(id){
    const p=products.find(x=>x.id===id); if(!p) return;
    if(favorites.has(id)){favorites.delete(id);toastMsg(`${p.name} retirado de favoritos`);}
    else{favorites.add(id);toastMsg(`${p.name} marcado para revisar`);}
    saveFavorites(); render();
}
function openProduct(id){
    const p=products.find(x=>x.id===id); if(!p||!modal||!modalContent) return;
    lastFocused=document.activeElement;
    const props=Array.isArray(p.properties)&&p.properties.length?`<div class="detail-properties">${p.properties.map(x=>`<span>${esc(x)}</span>`).join('')}</div>`:'';
    const variants=Array.isArray(p.variants)&&p.variants.length?`<div class="detail-variants">${p.variants.map(x=>`<span>${esc(x)}</span>`).join('')}</div>`:'';
    modalContent.innerHTML=`<article class="detail" data-arcane="${isArcane(p)?'true':'false'}">
        <div class="detail-media">${imageHtml(p)}</div>
        <div class="detail-body">
            <span class="product-category">${esc(p.category)}</span>
            <h2 id="modalTitle">${esc(p.name)}</h2>
            <div class="detail-price">${esc(p.price)}</div>
            ${p.appliesTo?`<div class="detail-properties"><span>Aplicable a: ${esc(p.appliesTo)}</span></div>`:''}
            ${props}${variants}
            <div class="detail-description"><p>${esc(p.description).replaceAll('\n','<br>')}</p></div>
            <div class="detail-note"><strong>Nota del taller:</strong> la ficha es informativa. La compra todavía no descuenta oro de la Banca Digital ni cambia el stock.</div>
        </div>
    </article>`;
    bindFallbacks(modalContent);
    modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open');
}
function closeModal(){
    if(!modal) return;
    modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');
    if(lastFocused instanceof HTMLElement) lastFocused.focus();
}
categoryTabs.forEach(btn=>btn.addEventListener('click',()=>{
    category=btn.dataset.category||'Todos';
    categoryTabs.forEach(x=>x.classList.toggle('active',x===btn));
    render();
}));
searchInput?.addEventListener('input',render);
sortSelect?.addEventListener('change',render);
clearFilters?.addEventListener('click',()=>{
    category='Todos';favoritesOnly=false;
    if(searchInput) searchInput.value='';
    if(sortSelect) sortSelect.value='default';
    categoryTabs.forEach(x=>x.classList.toggle('active',x.dataset.category==='Todos'));
    render();
});
favoritesButton?.addEventListener('click',()=>{
    favoritesOnly=!favoritesOnly;render();
});
document.addEventListener('click',e=>{
    const fav=e.target.closest('[data-favorite]');
    if(fav){e.preventDefault();e.stopPropagation();toggleFavorite(fav.dataset.favorite);return;}
    const open=e.target.closest('[data-open]');
    if(open){openProduct(open.dataset.open);return;}
    if(e.target.closest('[data-close-modal]')) closeModal();
});
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'&&modal?.classList.contains('open')) closeModal();
});
render();
})();