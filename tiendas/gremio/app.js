// Tienda del Gremio: catálogo (motor común ../tienda.js + compra ../compra.js),
// «Vender al gremio» y el Almacén del DM. Vender y Almacén se cargan al abrirlos.
(() => {
'use strict';
const { esc } = FavorTienda;
let G = {};
try { G = JSON.parse(document.getElementById('gremioData')?.textContent || '{}') || {}; } catch (_) { G = {}; }
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function coins(copper) {
    copper = Math.round(Number(copper) || 0);
    const po = Math.floor(copper / 100), pl = Math.floor(copper % 100 / 10), pc = copper % 10, parts = [];
    if (po) parts.push(`${po.toLocaleString('es-CL')} po`); if (pl) parts.push(`${pl} pl`); if (pc) parts.push(`${pc} pc`);
    return parts.join(' ') || '0 po';
}
function multiline(text) { return esc(text).replace(/\n/g, '<br>'); }
function shortDate(v) {
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? '' : d.toLocaleDateString('es-CL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

async function api(action, body = {}) {
    let j;
    try {
        const r = await fetch(`${G.api}?action=${encodeURIComponent(action)}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': G.csrf || '' },
            body: JSON.stringify({ action, ...body }),
        });
        j = await r.json();
    } catch (_) { j = { ok: false, error: 'No pude conectar. Revisa tu conexión e inténtalo otra vez.' }; }
    if (!j || !j.ok) throw new Error(j?.error || 'No se pudo completar.');
    return j;
}

/* ---------------- Pestañas ---------------- */
const tabs = [...document.querySelectorAll('[data-tab]')];
const loaded = new Set(['comprar']);
function openTab(name, focus = false) {
    const tab = tabs.find(t => t.dataset.tab === name) || tabs[0];
    tabs.forEach(t => {
        const on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    if (focus) tab.focus();
    if (!loaded.has(tab.dataset.tab)) {
        loaded.add(tab.dataset.tab);
        if (tab.dataset.tab === 'almacen') loadStorage();
    }
    if (location.hash.slice(1) !== tab.dataset.tab) history.replaceState(null, '', '#' + tab.dataset.tab);
}
tabs.forEach((t, i) => {
    t.addEventListener('click', () => openTab(t.dataset.tab));
    t.addEventListener('keydown', e => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        e.preventDefault();
        const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
        openTab(next.dataset.tab, true);
    });
});

/* ---------------- Comprar ---------------- */
const groupOf = p => p.subcategory || 'De aventureros';
const shop = FavorTienda.create({
    storageKey: 'gremio:favorites',
    fallback: 'assets/objetos/objeto.svg',
    categoryOf: groupOf,
    price: v => { const m = String(v ?? '').replace(/\./g, '').match(/(\d+)/); return m ? Number(m[1]) : Infinity; },
    searchText: p => [p.name, p.category, p.subcategory, p.effect, p.attunement, p.from],
    count: n => `${n} ${n === 1 ? 'objeto' : 'objetos'}`,
    favoriteText: { on: p => `${p.name} guardado en favoritos`, off: p => `${p.name} quitado de favoritos` },
    card: (p, { img, favButton }) => `<article class="product-card${p.available ? '' : ' soldout'}">
 <div class="product-media">${img(p)}${favButton(p)}${p.available ? '' : '<span class="stamp">Agotado</span>'}</div>
 <div class="product-body">
  <div class="product-meta"><span class="product-category">${esc(groupOf(p))}</span><span class="product-price">${esc(p.price)}</span></div>
  <h3>${esc(p.name)}</h3>
  <div class="product-tags">${attTag(p)}<span class="tag">${p.available ? `${p.stock} en almacén` : 'Sin existencias'}</span>${p.review ? '<span class="tag warn">En revisión</span>' : ''}</div>
  <p class="product-description">${p.effect ? esc(firstLine(p.effect)) : '<em>Descripción pendiente de revisión.</em>'}</p>
  <button class="product-open" type="button" data-open="${esc(p.id)}">Ver ficha</button>
 </div></article>`,
    detail: (p, { img, buyBox }) => `<article class="detail">
 <div class="detail-media">${img(p)}</div>
 <div class="detail-body">
  <span class="product-category">${esc(p.category)}${p.subcategory ? ' · ' + esc(p.subcategory) : ''}</span>
  <h2 id="modalTitle">${esc(p.name)}</h2>
  <div class="detail-price">${esc(p.price)} <small>${p.available ? `· ${p.stock} en el almacén` : '· agotado'}</small></div>
  <div class="product-tags">${attTag(p)}${p.from ? `<span class="tag">Traído por ${esc(p.from)}</span>` : ''}</div>
  <div class="detail-description">${p.effect ? `<p>${multiline(p.effect)}</p>` : '<p class="review"><b>Descripción pendiente de revisión.</b> ' + esc(p.review || 'El catálogo no trae su efecto.') + '</p>'}</div>
  ${buyBox(p)}
 </div></article>`,
});
function firstLine(t) { const s = String(t).split('\n')[0]; return s.length > 140 ? s.slice(0, 137) + '…' : s; }
function attTag(p) {
    if (!p.attunement) return '';
    const req = /requiere/i.test(p.attunement) && !/no\s+requer/i.test(p.attunement);
    return `<span class="tag ${req ? 'att' : ''}">${req ? 'Requiere vinculación' : 'Sin vinculación'}</span>`;
}
// Tras comprar (../compra.js), el almacén tiene una unidad menos.
document.addEventListener('favor:compra', e => {
    const d = e.detail || {};
    const p = shop.products.find(x => x.id === d.product);
    if (p && Number.isFinite(Number(d.stock))) { p.stock = Number(d.stock); p.available = p.stock > 0; shop.render(); }
});

/* ---------------- Vender al gremio ---------------- */
const sellChar = document.getElementById('sellCharacter');
const sellArea = document.getElementById('sellArea');
let sellData = null, sellOpen = 0;
const CHAR_KEY = 'favor:comprador';
if (sellChar) {
    try { const saved = localStorage.getItem(CHAR_KEY) || ''; if ([...sellChar.options].some(o => o.value === saved && saved)) sellChar.value = saved; } catch (_) {}
    if (!sellChar.value && sellChar.options.length === 2) sellChar.selectedIndex = 1;
    sellChar.addEventListener('change', () => { try { localStorage.setItem(CHAR_KEY, sellChar.value); } catch (_) {} loadSellables(); });
    tabs.find(t => t.dataset.tab === 'vender')?.addEventListener('click', () => { if (!sellData && sellChar.value) loadSellables(); }, { once: true });
}

async function loadSellables(message = '') {
    if (!sellArea) return;
    if (!sellChar.value) { sellArea.innerHTML = '<p class="hint">Elige un personaje para ver sus objetos.</p>'; return; }
    sellArea.innerHTML = '<p class="loading">Revisando el inventario…</p>';
    try { sellData = await api('sellables', { character: sellChar.value }); renderSell(message); }
    catch (err) { sellArea.innerHTML = `<p class="error" role="alert">${esc(err.message)}</p>`; }
}

const stateText = { pendiente: 'Esperando valoración', ofertada: 'Oferta del gremio', aceptada: 'Vendido', rechazada: 'Rechazada por el gremio', cancelada: 'Cerrada' };

function itemRow(it) {
    const status = it.equipped ? '<span class="tag">Equipado</span>'
        : it.offer ? `<span class="tag ${it.offer.state === 'ofertada' ? 'ok' : ''}">${esc(stateText[it.offer.state])}${it.offer.priceLabel ? ': ' + esc(it.offer.priceLabel) : ''}</span>`
        : it.rate ? `<span class="tag ok">El gremio paga ${esc(it.rateLabel)}</span>` : '<span class="tag">Sin precio: a valoración</span>';
    return `<li><button type="button" class="sell-item${sellOpen === it.id ? ' open' : ''}" data-sell-item="${it.id}" aria-expanded="${sellOpen === it.id}">
  <span class="sell-name"><strong>${esc(it.name)}</strong><small>${esc(it.category || 'Objeto')}${it.qty > 1 ? ' · ' + it.qty + ' unidades' : ''}</small></span>${status}</button>
  ${sellOpen === it.id ? sellPanel(it) : ''}</li>`;
}

function sellPanel(it) {
    const desc = it.description ? `<p class="sell-desc">${multiline(it.description)}</p>` : '<p class="sell-desc muted">Sin descripción en el Inventario.</p>';
    if (it.equipped) return `<div class="sell-panel">${desc}<p class="warn-line">Está equipado. Quítatelo en el <a href="${esc(G.inventory)}?character=${esc(sellChar.value)}">Inventario</a> para poder venderlo.</p></div>`;
    const att = it.attunement ? `<label class="check"><input type="checkbox" data-unbound> Confirmo que mi personaje ya no está vinculado a este objeto.</label>` : '';
    if (it.offer && it.offer.state === 'pendiente') return `<div class="sell-panel">${desc}<p>Lady Gena todavía no lo valora. Te avisará aquí con su oferta.</p><button type="button" class="btn ghost" data-cancel="${it.offer.id}">Retirar solicitud</button></div>`;
    const price = it.offer && it.offer.state === 'ofertada' ? it.offer.price : it.rate;
    if (price) {
        return `<div class="sell-panel">${desc}
  ${it.offer?.noteDM ? `<p class="dm-note">Lady Gena: «${esc(it.offer.noteDM)}»</p>` : ''}
  <p class="offer">El gremio ofrece <b>${esc(coins(price))}</b>${it.qty > 1 ? ' por una unidad' : ''}.</p>
  ${att}
  <div class="row"><button type="button" class="btn primary" data-sell="${it.id}" data-expected="${price}"${it.offer ? ` data-offer="${it.offer.id}"` : ''}>Vender por ${esc(coins(price))}</button>
  ${it.offer ? `<button type="button" class="btn ghost" data-cancel="${it.offer.id}">Rechazar oferta</button>` : ''}</div>
  <p class="small">Al confirmar, el objeto pasa al Almacén del Gremio y el oro entra a la Banca de ${esc(sellData.character.name)}.</p></div>`;
    }
    return `<div class="sell-panel">${desc}
  <p>El gremio aún no tiene precio para este objeto. Envíalo a valoración: no sale de tu Inventario hasta que aceptes la oferta.</p>
  <label class="field"><span>Nota para Lady Gena (opcional)</span><textarea data-note maxlength="500" rows="2" placeholder="Rareza, de dónde salió, cuánto pides…"></textarea></label>
  <button type="button" class="btn primary" data-request="${it.id}">Enviar a valoración</button></div>`;
}

function renderSell(message = '') {
    const items = sellData.items || [];
    const magic = items.filter(i => i.magic), other = items.filter(i => !i.magic);
    const history = (sellData.offers || []).filter(o => !['pendiente', 'ofertada'].includes(o.state)).slice(0, 8);
    sellArea.innerHTML = `${message ? `<p class="success" role="status">${esc(message)}</p>` : ''}
 <h3 class="list-title">Objetos mágicos de ${esc(sellData.character.name)}</h3>
 ${magic.length ? `<ul class="sell-list">${magic.map(itemRow).join('')}</ul>` : '<p class="hint">No encontré objetos mágicos en su Inventario.</p>'}
 ${other.length ? `<details class="other"${other.some(i => i.id === sellOpen) ? ' open' : ''}><summary>Otros objetos del Inventario (${other.length})</summary><p class="small">Si alguno es mágico, el gremio lo valora antes de comprarlo.</p><ul class="sell-list">${other.map(itemRow).join('')}</ul></details>` : ''}
 ${history.length ? `<h3 class="list-title">Últimas solicitudes</h3><ul class="history">${history.map(o => `<li><b>${esc(o.name)}</b> · ${esc(stateText[o.state])}${o.price ? ' · ' + esc(o.price) : ''}${o.noteDM ? ` · «${esc(o.noteDM)}»` : ''} <small>${esc(shortDate(o.resolved || o.at))}</small></li>`).join('')}</ul>` : ''}`;
}

sellArea?.addEventListener('click', async e => {
    const row = e.target.closest('[data-sell-item]');
    if (row) { const id = Number(row.dataset.sellItem); sellOpen = sellOpen === id ? 0 : id; renderSell(); sellArea.querySelector(`[data-sell-item="${id}"]`)?.focus(); return; }
    const btn = e.target.closest('[data-sell],[data-request],[data-cancel]');
    if (!btn) return;
    const panel = btn.closest('.sell-panel');
    btn.disabled = true;
    try {
        let j;
        if (btn.dataset.sell) {
            const unbound = panel.querySelector('[data-unbound]');
            if (unbound && !unbound.checked) throw new Error('Marca la casilla de vinculación para confirmar.');
            const name = sellData.items.find(i => i.id === Number(btn.dataset.sell))?.name || 'este objeto';
            if (!confirm(`¿Vender ${name} al gremio por ${coins(btn.dataset.expected)}?`)) { btn.disabled = false; return; }
            j = await api('sell', { character: sellChar.value, inventory: Number(btn.dataset.sell), offer: btn.dataset.offer ? Number(btn.dataset.offer) : null, expected: Number(btn.dataset.expected), unbound: !!unbound?.checked });
        } else if (btn.dataset.request) {
            j = await api('request', { character: sellChar.value, inventory: Number(btn.dataset.request), note: panel.querySelector('[data-note]')?.value || '' });
        } else {
            j = await api('cancel', { character: sellChar.value, offer: Number(btn.dataset.cancel) });
        }
        sellData = j; sellOpen = 0; renderSell(j.message || '');
        sellArea.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    } catch (err) {
        btn.disabled = false;
        let p = panel.querySelector('.error');
        if (!p) { p = document.createElement('p'); p.className = 'error'; p.setAttribute('role', 'alert'); panel.appendChild(p); }
        p.textContent = err.message;
    }
});

/* ---------------- Almacén (DM) ---------------- */
const storageArea = document.getElementById('storageArea');
let store = null, storeFilter = 'todos', storeQuery = '';
async function loadStorage() {
    if (!storageArea) return;
    try { store = await api('storage'); renderStorage(); }
    catch (err) { storageArea.innerHTML = `<p class="error" role="alert">${esc(err.message)}</p>`; }
}
const originText = { catalogo: 'Catálogo inicial', aventurero: 'Comprado a', admin: 'Añadido por el DM' };
const typeText = { compra: 'Compra', venta: 'Venta al gremio', anulacion: 'Anulación', valoracion: 'Valoración', almacen: 'Almacén' };

function offerCard(o) {
    return `<form class="offer-card" data-value="${o.id}">
  <div class="offer-head"><strong>${esc(o.name)}</strong><span>${esc(o.character)} · ${esc(shortDate(o.at))}</span></div>
  ${o.description ? `<p class="small">${multiline(o.description)}</p>` : ''}
  ${o.noteP ? `<p class="dm-note">Jugador: «${esc(o.noteP)}»</p>` : ''}
  ${!o.stillOwned ? '<p class="warn-line">Ya no está en su Inventario: no se podrá vender.</p>' : ''}
  ${o.state === 'ofertada' ? `<p class="small">Oferta enviada: <b>${esc(coins(o.price * 100))}</b>. Esperando al jugador. Puedes cambiarla.</p>` : ''}
  <div class="row">
   <label class="field mini"><span>Ofrecer (po)</span><input name="price" inputmode="decimal" value="${o.price ?? ''}" required></label>
   <label class="field grow"><span>Nota para el jugador</span><input name="note" maxlength="500" value="${esc(o.noteDM)}"></label>
  </div>
  <label class="check"><input type="checkbox" name="remember" checked> Recordar este precio para «${esc(o.name)}» (la próxima venta será inmediata)</label>
  <div class="row"><button class="btn primary" type="submit">${o.state === 'ofertada' ? 'Cambiar oferta' : 'Enviar oferta'}</button><button class="btn ghost" type="button" data-reject="${o.id}">Rechazar</button></div>
 </form>`;
}

function itemForm(it) {
    return `<form class="store-item ${it.destino}${it.stock ? '' : ' empty'}" data-item="${it.id}">
  <div class="store-head">
   <input class="name-input" name="name" value="${esc(it.name)}" aria-label="Nombre" required>
   <span class="origin">${esc(originText[it.origin] || '')}${it.origin === 'aventurero' ? ' ' + esc(it.from) + (it.paid ? ' por ' + esc(it.paid) : '') : ''}</span>
  </div>
  ${it.review ? `<p class="warn-line">Revisar: ${esc(it.review)} <label class="check inline"><input type="checkbox" name="reviewed"> Revisado</label></p>` : ''}
  <div class="row">
   <label class="field"><span>Destino</span><select name="destino"><option value="venta"${it.destino === 'venta' ? ' selected' : ''}>A la venta</option><option value="reservado"${it.destino === 'reservado' ? ' selected' : ''}>Reservado para el gremio</option></select></label>
   <label class="field mini"><span>Precio (po)</span><input name="price" inputmode="decimal" value="${it.price ?? ''}"></label>
   <label class="field mini"><span>Existencias</span><input name="stock" type="number" min="0" max="999" value="${it.stock}"></label>
  </div>
  <details><summary>Descripción, categoría y vinculación</summary>
   <div class="row"><label class="field"><span>Categoría</span><input name="category" value="${esc(it.category)}"></label>
   <label class="field"><span>Vinculación</span><input name="attunement" value="${esc(it.attunement)}" placeholder="Requiere / No requerida"></label></div>
   <label class="field"><span>Descripción</span><textarea name="description" rows="4">${esc(it.description)}</textarea></label>
   ${it.note ? `<p class="small">${esc(it.note)}</p>` : ''}
  </details>
  <button class="btn primary small-btn" type="submit">Guardar</button>
 </form>`;
}

function renderStorage(message = '') {
    const s = store;
    const pend = s.offers.filter(o => o.state === 'pendiente').length;
    const badge = document.getElementById('pendingBadge');
    if (badge) { badge.textContent = String(pend); badge.hidden = !pend; }
    const q = FavorTienda.norm(storeQuery);
    const items = s.items.filter(i => (storeFilter === 'todos' || i.destino === storeFilter) && (!q || FavorTienda.norm(i.name + ' ' + i.from).includes(q)));
    const count = d => s.items.filter(i => i.destino === d).length;
    storageArea.innerHTML = `${message ? `<p class="success" role="status">${esc(message)}</p>` : ''}
 <section class="store-block"><h3>Ofertas por valorar <span class="count">${s.offers.length}</span></h3>
  ${s.offers.length ? s.offers.map(offerCard).join('') : '<p class="hint">No hay objetos esperando valoración.</p>'}</section>

 <section class="store-block"><h3>Existencias</h3>
  <div class="toolbar">
   <div class="chips" role="group" aria-label="Destino">
    ${[['todos', 'Todo', s.items.length], ['venta', 'A la venta', count('venta')], ['reservado', 'Reservado', count('reservado')]].map(([k, l, n]) => `<button type="button" class="filter-chip${storeFilter === k ? ' active' : ''}" data-store-filter="${k}" aria-pressed="${storeFilter === k}">${l} <span>${n}</span></button>`).join('')}
   </div>
   <label class="search-box"><span aria-hidden="true">⌕</span><span class="sr-only">Buscar en el almacén</span><input type="search" id="storeSearch" value="${esc(storeQuery)}" placeholder="Buscar en el almacén…"></label>
  </div>
  <div class="store-grid">${items.map(itemForm).join('') || '<p class="hint">Nada con ese filtro.</p>'}</div>
  <details class="add-item"><summary>+ Añadir un objeto al almacén</summary>
   <form data-add>
    <div class="row"><label class="field grow"><span>Nombre</span><input name="name" required maxlength="160"></label>
    <label class="field mini"><span>Precio (po)</span><input name="price" inputmode="decimal"></label>
    <label class="field mini"><span>Existencias</span><input name="stock" type="number" min="0" max="999" value="1"></label></div>
    <div class="row"><label class="field"><span>Destino</span><select name="destino"><option value="reservado">Reservado para el gremio</option><option value="venta">A la venta</option></select></label>
    <label class="field"><span>Vinculación</span><input name="attunement" placeholder="Requiere / No requerida"></label></div>
    <label class="field"><span>Descripción</span><textarea name="description" rows="3"></textarea></label>
    <input type="hidden" name="category" value="Objeto mágico"><input type="hidden" name="reviewed" value="1">
    <button class="btn primary" type="submit">Añadir</button>
   </form></details>
 </section>

 <section class="store-block"><h3>Cuánto paga el gremio <span class="count">${s.rates.length}</span></h3>
  <p class="small">Con precio fijado, el aventurero vende ese objeto al instante. Sin precio, lo manda a valoración.</p>
  <ul class="rates">${s.rates.map(r => `<li><span><b>${esc(r.name)}</b> · ${esc(r.label)}${r.note ? ' · ' + esc(r.note) : ''}</span><button type="button" class="btn ghost small-btn" data-rate-del="${esc(r.name)}">Quitar</button></li>`).join('') || '<li class="hint">Aún no hay precios de compra.</li>'}</ul>
  <form class="row rate-form" data-rate>
   <label class="field grow"><span>Objeto</span><input name="name" required maxlength="160" list="storeNames"></label>
   <label class="field mini"><span>Paga (po)</span><input name="price" inputmode="decimal" required></label>
   <label class="field grow"><span>Nota</span><input name="note" maxlength="255"></label>
   <button class="btn primary" type="submit">Guardar precio</button>
  </form>
  <datalist id="storeNames">${[...new Set(s.items.map(i => i.name))].map(n => `<option value="${esc(n)}">`).join('')}</datalist>
 </section>

 <section class="store-block"><h3>Registro</h3>
  <ul class="log">${s.log.map(l => `<li class="${l.undone ? 'undone' : ''}"><small>${esc(shortDate(l.at))}</small> <b>${esc(typeText[l.type] || l.type)}</b> · ${esc(l.item)}${l.character ? ' · ' + esc(l.character) : ''}${l.copper ? ' · ' + (l.copper < 0 ? '−' : '') + esc(l.label) : ''}${l.detail ? ` <span class="muted">· ${esc(l.detail)}</span>` : ''}${l.undone ? ' <em>(anulada)</em>' : ''}${l.canUndo ? ` <button type="button" class="btn ghost small-btn" data-undo="${l.id}">Anular y devolver</button>` : ''}</li>`).join('') || '<li class="hint">Sin movimientos todavía.</li>'}</ul>
 </section>`;
}

function formData(form) { const o = {}; new FormData(form).forEach((v, k) => { o[k] = v; }); form.querySelectorAll('input[type=checkbox]').forEach(c => { o[c.name] = c.checked; }); return o; }
async function storeAction(action, body, btn) {
    if (btn) btn.disabled = true;
    try { const j = await api(action, body); store = j; renderStorage(j.message || ''); }
    catch (err) { if (btn) btn.disabled = false; alert(err.message); }
}
storageArea?.addEventListener('submit', e => {
    e.preventDefault();
    const f = e.target, btn = f.querySelector('[type=submit]');
    if (f.dataset.item) storeAction('save_item', { id: Number(f.dataset.item), ...formData(f) }, btn);
    else if (f.dataset.value) storeAction('value', { offer: Number(f.dataset.value), ...formData(f) }, btn);
    else if (f.hasAttribute('data-add')) storeAction('add_item', formData(f), btn);
    else if (f.hasAttribute('data-rate')) storeAction('rate', formData(f), btn);
});
storageArea?.addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.storeFilter) { storeFilter = b.dataset.storeFilter; renderStorage(); return; }
    if (b.dataset.reject) { const f = b.closest('form'); if (confirm('¿Rechazar esta solicitud? El objeto se queda con el jugador.')) storeAction('value', { offer: Number(b.dataset.reject), reject: true, note: f.querySelector('[name=note]')?.value || '' }, b); return; }
    if (b.dataset.rateDel) { if (confirm(`¿Quitar el precio de compra de ${b.dataset.rateDel}?`)) storeAction('rate', { name: b.dataset.rateDel, delete: true }, b); return; }
    if (b.dataset.undo) { if (confirm('¿Anular esta compra? Se devuelve el oro, se quita del Inventario y vuelve al Almacén.')) storeAction('undo', { log: Number(b.dataset.undo) }, b); }
});
storageArea?.addEventListener('input', e => {
    if (e.target.id !== 'storeSearch') return;
    storeQuery = e.target.value;
    const pos = e.target.selectionStart;
    renderStorage();
    const s = document.getElementById('storeSearch'); s.focus(); s.setSelectionRange(pos, pos);
});

/* ---------------- Inicio ---------------- */
addEventListener('hashchange', () => { const h = location.hash.slice(1); if (tabs.some(t => t.dataset.tab === h)) openTab(h); });
const start = location.hash.slice(1);
openTab(tabs.some(t => t.dataset.tab === start) ? start : 'comprar');
if (sellArea && sellChar && !sellChar.value) sellArea.innerHTML = '<p class="hint">Elige un personaje para ver sus objetos.</p>';
if (start === 'vender' && sellChar?.value) loadSellables();
})();
