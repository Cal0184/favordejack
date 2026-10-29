// La Poción Picante: lo propio de la tienda (colores y el descuento de Yamo-Yamo).
// Catálogo, filtros, favoritos y ficha los hace ../tienda.js.
(() => {
'use strict';
const { esc } = FavorTienda;

const colors = {
    'Curación': '#74d193', 'Restauración': '#69d2ca', 'Blindaje': '#72aee4', 'Arsenal': '#df7b46',
    'Esencias': '#d4b54f', 'Conjuros en Lata': '#ae90e6', 'Utilidad': '#d78ec6', 'Néctares': '#dd6659',
};
const accent = p => colors[p.majorCategory] || '#ad8ec0';

let data = {};
try { data = JSON.parse(document.getElementById('shopData')?.textContent || '{}') || {}; } catch (_) {}

// Descuento de la tienda (lo define el DM): cada personaje lo usa una vez.
// Se elige con qué personaje se compra; si aún lo tiene, los precios se ven
// rebajados y al comprar en la ficha se puede usar (tiendas/compra.js).
const discount = data.discount || {};
const buyers = Array.isArray(discount.characters) ? discount.characters : [];
const discountBar = document.getElementById('discountBar');
let buyerId = loadBuyer();

function loadBuyer() { try { const id = localStorage.getItem('lpp:buyer') || ''; return buyers.some(c => c.id === id) ? id : ''; } catch (_) { return ''; } }
function saveBuyer() { try { localStorage.setItem('lpp:buyer', buyerId); localStorage.setItem('favor:comprador', buyerId); } catch (_) {} }
function buyer() { return buyers.find(c => c.id === buyerId) || null; }
function activePercent() { const b = buyer(); return discount.active && b && b.available ? Math.max(0, Math.min(100, Number(discount.percent) || 0)) : 0; }
function dealText() { return `${Number(discount.percent) || 0} %${discount.rule ? ' ' + discount.rule : ''}`; }
function coinsText(copper) {
    const po = Math.floor(copper / 100), pl = Math.floor(copper % 100 / 10), pc = copper % 10, parts = [];
    if (po) parts.push(`${po.toLocaleString('es-CL')} Po`); if (pl) parts.push(`${pl} Pl`); if (pc) parts.push(`${pc} Pc`);
    return parts.join(' ') || '0 Po';
}
function discounted(p, pct) {
    const m = String(p.price ?? '').trim().match(/^(\d+(?:[.,]\d+)?)\s*po$/i);
    return m ? coinsText(Math.round(Number(m[1].replace(',', '.')) * 100 * (100 - pct) / 100)) : null;
}
function priceHtml(p) {
    const pct = activePercent(), d = pct ? discounted(p, pct) : null;
    if (!d) return esc(p.price);
    return `<s class="price-old">${esc(p.price)}</s> <b class="price-new">${esc(d)}</b>`;
}
function shortDate(iso) { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('es-CL', { day: 'numeric', month: 'short' }); }
function quick(p) {
    const a = [];
    if (p.duration) a.push(`Duración: ${p.duration}`);
    if (p.application) a.push(`Aplicación: ${p.application}`);
    if (p.save) a.push(p.save);
    return a.slice(0, 2).map(x => `<span>${esc(x)}</span>`).join('');
}

const shop = FavorTienda.create({
    storageKey: 'lpp:favorites',
    fallback: 'assets/placeholders/utilidad.svg',
    toastMs: 1600,
    categoryOf: p => p.majorCategory,
    searchText: p => [p.name, p.majorCategory, p.subcategory, p.effect, p.duration, p.application, p.save, p.price],
    count: n => `${n} ${n === 1 ? 'preparado' : 'preparados'}`,
    favoriteText: { on: p => `${p.name} guardado para revisar`, off: p => `${p.name} retirado de favoritos` },
    card: (p, { img, favButton }) => {
        const q = quick(p);
        return `<article class="product-card" style="--accent:${accent(p)}">
 <div class="product-media">${img(p)}${favButton(p)}</div>
 <div class="product-body">
  <div class="product-meta"><span class="product-category">${esc(p.majorCategory)}</span><span class="product-price">${priceHtml(p)}</span></div>
  <h3>${esc(p.name)}</h3><span class="product-subcategory">${esc(p.subcategory)}</span>
  ${q ? `<div class="product-quick">${q}</div>` : ''}
  <p class="product-description">${esc(p.effect)}</p>
  <button class="product-open" type="button" data-open="${esc(p.id)}">Leer etiqueta completa</button>
 </div></article>`;
    },
    detail: (p, { img, buyBox }) => {
        const stats = [];
        if (p.duration) stats.push(['Duración', p.duration]);
        if (p.application) stats.push(['Aplicación', p.application]);
        if (p.save) stats.push(['Salvación', p.save]);
        const statsHtml = stats.length ? `<div class="detail-stats">${stats.map(([a, b]) => `<div class="detail-stat"><span>${esc(a)}</span><strong>${esc(b)}</strong></div>`).join('')}</div>` : '';
        return `<article class="detail" style="--accent:${accent(p)}">
 <div class="detail-media">${img(p)}</div>
 <div class="detail-body">
  <span class="product-category">${esc(p.majorCategory)}</span>
  <h2 id="modalTitle">${esc(p.name)}</h2>
  <div class="detail-price">${priceHtml(p)}</div>
  <div class="detail-sub">${esc(p.subcategory)}</div>
  ${statsHtml}
  <div class="detail-description"><p>${esc(p.effect)}</p></div>
  ${buyBox(p)}
 </div></article>`;
    },
});

function renderDiscountBar() {
    if (!discountBar) return;
    if (!buyers.length || !discount.active) { discountBar.hidden = true; return; }
    const groups = new Map();
    buyers.forEach(c => { if (!groups.has(c.playerName)) groups.set(c.playerName, []); groups.get(c.playerName).push(c); });
    const opt = c => `<option value="${esc(c.id)}"${c.id === buyerId ? ' selected' : ''}>${esc(c.name)}${c.available ? ' · descuento disponible' : ' · descuento usado'}</option>`;
    const options = groups.size > 1
        ? [...groups].map(([name, list]) => `<optgroup label="${esc(name)}">${list.map(opt).join('')}</optgroup>`).join('')
        : buyers.map(opt).join('');
    const b = buyer();
    const state = !b ? `Descuento de Yamo-Yamo: <strong>${esc(dealText())}</strong> de cada personaje. Elige con quién compras.`
        : b.available ? `<strong>${esc(b.name)}</strong> tiene ${esc(dealText())}. Abre la poción que quiera y marca «Usar su descuento» al comprar.`
        : `${esc(b.name)} ya usó su descuento${b.usedItem ? ` (${esc(b.usedItem)}${b.usedAt && shortDate(b.usedAt) ? ', ' + esc(shortDate(b.usedAt)) : ''})` : ''}. Solo el DM puede devolvérselo.`;
    discountBar.innerHTML = `<label class="discount-pick"><span>Comprar como</span><select id="buyerSelect"><option value="">— Sin elegir —</option>${options}</select></label>
 <p class="discount-state${activePercent() ? ' on' : ''}">${state}</p>
 ${discount.admin && discount.manageUrl ? `<a class="discount-manage" href="${esc(discount.manageUrl)}">Panel de descuentos</a>` : ''}`;
    discountBar.hidden = false;
    document.getElementById('buyerSelect').addEventListener('change', e => {
        buyerId = e.target.value; saveBuyer(); renderDiscountBar(); shop.render(); shop.refreshOpen();
    });
}

// Al comprar con descuento (tiendas/compra.js) el personaje ya no lo tiene.
document.addEventListener('favor:compra', e => {
    const d = e.detail || {};
    if (!d.discount) return;
    (d.buyers || []).forEach(nb => { const b = buyers.find(x => x.id === nb.id); if (b && !nb.discount) b.available = false; });
    renderDiscountBar(); shop.render();
});
renderDiscountBar();
})();
