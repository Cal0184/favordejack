(() => {
'use strict';

const DATA = JSON.parse(document.getElementById('emporio-data').textContent);
const CARDS = Object.fromEntries(DATA.cards.map(c => [c.id, c]));
const SERVICES = Object.fromEntries(DATA.services.map(s => ['servicio:' + s.id, s]));
const ORDER_ITEMS = {...CARDS, ...SERVICES};
const $ = s => document.querySelector(s);
const cardEls = [...document.querySelectorAll('.card')];
const grid = $('#grid');

const els = {
    search: $('#search'), searchClear: $('#searchClear'), category: $('#category'),
    availability: $('#availability'), sort: $('#sort'), buyer: $('#buyer'), affordable: $('#affordable'),
    budgetInfo: $('#budgetInfo'), favoritesToggle: $('#favoritesToggle'), favoriteCount: $('#favoriteCount'),
    compareButton: $('#compareButton'), randomButton: $('#randomButton'), clearButton: $('#clearButton'),
    resultState: $('#resultState'), noResults: $('#noResults'), modal: $('#modal'), modalBody: $('#modalBody'),
    toast: $('#toast'), orderBar: $('#orderBar'), orderCount: $('#orderCount'), orderTotal: $('#orderTotal'),
};

/* ---------- utilidades ---------- */

// El navegador puede bloquear el almacenamiento (modo privado): la tienda sigue funcionando sin recordar.
const store = {
    get(key, fallback) { try { const v = JSON.parse(localStorage.getItem(key)); return v ?? fallback; } catch (_) { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* sin almacenamiento */ } },
};
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const normalize = v => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase('es').replace(/\s+/g, ' ').trim();
const po = n => Number(n).toLocaleString('es-CL', {maximumFractionDigits: 2}) + ' po';
function descriptionHtml(text) {
    const chunks = String(text ?? '').split(/\n\s*\n/g).map(s => s.trim()).filter(Boolean);
    return chunks.length ? chunks.map(c => `<p>${esc(c).replace(/\n/g, '<br>')}</p>`).join('') : '<p>Sin descripción registrada.</p>';
}
let toastTimer = 0;
function toast(text) {
    els.toast.textContent = text;
    els.toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { els.toast.hidden = true; }, 2600);
}
async function copyText(text, done) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (_) {
        const area = Object.assign(document.createElement('textarea'), {value: text});
        document.body.append(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    toast(done);
}

/* ---------- estado ---------- */

let favorites = store.get('ecixs_favorites_v4', []).filter(id => CARDS[id]);
let compare = store.get('ecixs_compare_v5', []).filter(id => CARDS[id]).slice(0, 3);
let order = store.get('ecixs_order_v1', []).filter(l => ORDER_ITEMS[l.id] && l.qty > 0);
let onlyFavorites = false;
let visibleIds = [];
const characters = Object.fromEntries((DATA.characters || []).map(c => [c.id, c]));
const buyer = () => characters[els.buyer?.value || ''] || null;

/* ---------- filtros, orden y URL ---------- */

function sortCards() {
    const mode = els.sort.value;
    const byName = (a, b) => CARDS[a.dataset.id].name.localeCompare(CARDS[b.dataset.id].name, 'es');
    const price = el => CARDS[el.dataset.id].pricePo;
    const sorted = [...cardEls].sort((a, b) => {
        if (mode === 'price-asc' || mode === 'price-desc') {
            const pa = price(a), pb = price(b);
            if (pa === null && pb === null) return byName(a, b);
            if (pa === null) return 1;
            if (pb === null) return -1;
            return (mode === 'price-asc' ? pa - pb : pb - pa) || byName(a, b);
        }
        if (mode === 'category') return CARDS[a.dataset.id].category.localeCompare(CARDS[b.dataset.id].category, 'es') || byName(a, b);
        return byName(a, b);
    });
    grid.append(...sorted, els.noResults);
}

function sync() {
    const q = normalize(els.search.value);
    const cat = els.category.value;
    const avail = els.availability.value;
    const who = buyer();
    const onlyAffordable = !!(who && els.affordable?.checked);
    let shown = 0;
    visibleIds = [];

    cardEls.forEach(el => {
        const id = el.dataset.id;
        const c = CARDS[id];
        const fav = favorites.includes(id);
        const cmp = compare.includes(id);
        el.classList.toggle('favorite', fav);
        el.classList.toggle('selected', cmp);
        const favBtn = el.querySelector('.favorite-btn');
        favBtn.setAttribute('aria-pressed', String(fav));
        favBtn.setAttribute('aria-label', fav ? `Quitar ${c.name} de favoritos` : `Añadir ${c.name} a favoritos`);
        const cmpBtn = el.querySelector('.select-btn');
        cmpBtn.setAttribute('aria-pressed', String(cmp));
        cmpBtn.textContent = cmp ? 'Quitar' : 'Comparar';
        const orderBtn = el.querySelector('.order-btn');
        if (orderBtn) {
            const line = order.find(l => l.id === id);
            orderBtn.classList.toggle('in-order', !!line);
            orderBtn.textContent = line ? `En pedido (${line.qty})` : '+ Pedido';
        }

        const afford = el.querySelector('.afford');
        let canPay = null;
        if (who) {
            if (c.pricePo === null) { afford.className = 'afford unknown'; afford.textContent = 'Precio a consultar'; }
            else if (who.goldExact >= c.pricePo) { canPay = true; afford.className = 'afford yes'; afford.textContent = `✓ ${who.name} puede pagarlo`; }
            else { canPay = false; afford.className = 'afford no'; afford.textContent = `Le faltan ${po(c.pricePo - who.goldExact)}`; }
        }
        afford.hidden = !who;

        const hidden = (q && !normalize(`${c.name} ${c.category} ${c.description}`).includes(q))
            || (cat && c.category !== cat)
            || (avail === 'available' && c.soldOut) || (avail === 'soldout' && !c.soldOut)
            || (onlyFavorites && !fav)
            || (onlyAffordable && canPay !== true);
        el.hidden = hidden;
        if (!hidden) { shown++; visibleIds.push(id); }
    });
    // visibleIds sigue el orden en pantalla para las flechas del detalle.
    visibleIds = [...grid.querySelectorAll('.card:not([hidden])')].map(el => el.dataset.id);

    els.favoriteCount.textContent = favorites.length;
    els.favoritesToggle.setAttribute('aria-pressed', String(onlyFavorites));
    els.compareButton.disabled = compare.length < 2;
    els.compareButton.textContent = compare.length >= 2 ? `⚖ Comparar (${compare.length})` : `⚖ Comparador (${compare.length}/3)`;
    els.searchClear.hidden = !els.search.value;
    els.noResults.hidden = !(shown === 0 && cardEls.length > 0);
    if (els.budgetInfo) {
        els.budgetInfo.innerHTML = who
            ? `<b>${esc(who.name)}</b> tiene <b>${esc(po(who.goldExact))}</b> en la Banca.`
            : 'Elige un personaje para ver qué le alcanza.';
    }

    const parts = [];
    if (q) parts.push(`«${els.search.value.trim()}»`);
    if (cat) parts.push(cat);
    if (avail === 'available') parts.push('disponibles');
    if (avail === 'soldout') parts.push('agotados');
    if (onlyFavorites) parts.push('favoritos');
    if (onlyAffordable) parts.push(`al alcance de ${who.name}`);
    els.resultState.textContent = parts.length
        ? `${shown} resultado${shown === 1 ? '' : 's'} · ${parts.join(' · ')}`
        : `Mostrando ${shown} objeto${shown === 1 ? '' : 's'}.`;

    store.set('ecixs_favorites_v4', favorites);
    store.set('ecixs_compare_v5', compare);
    if (els.buyer) store.set('ecixs_buyer_v1', els.buyer.value);
    syncOrderBar();
    writeUrl();
}

// Los filtros quedan en la dirección: se puede recargar o compartir la búsqueda.
function writeUrl() {
    const p = new URLSearchParams();
    if (els.search.value.trim()) p.set('q', els.search.value.trim());
    if (els.category.value) p.set('cat', els.category.value);
    if (els.availability.value) p.set('disp', els.availability.value);
    if (els.sort.value !== 'name') p.set('orden', els.sort.value);
    if (onlyFavorites) p.set('fav', '1');
    const qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
}
function readUrl() {
    const p = new URLSearchParams(location.search);
    els.search.value = p.get('q') || '';
    const setSelect = (sel, v) => { if (v && [...sel.options].some(o => o.value === v)) sel.value = v; };
    setSelect(els.category, p.get('cat'));
    setSelect(els.availability, p.get('disp'));
    setSelect(els.sort, p.get('orden'));
    onlyFavorites = p.get('fav') === '1';
    if (els.buyer) setSelect(els.buyer, store.get('ecixs_buyer_v1', ''));
}

function clearFilters() {
    els.search.value = '';
    els.category.value = '';
    els.availability.value = '';
    onlyFavorites = false;
    if (els.affordable) els.affordable.checked = false;
    sync();
    els.search.focus();
}

/* ---------- favoritos, comparador y pedido ---------- */

function toggleFav(id) {
    favorites = favorites.includes(id) ? favorites.filter(x => x !== id) : [...favorites, id];
    sync();
}
function toggleCompare(id) {
    if (compare.includes(id)) compare = compare.filter(x => x !== id);
    else {
        if (compare.length >= 3) { compare.shift(); toast('El comparador admite 3 objetos: quité el más antiguo.'); }
        compare.push(id);
    }
    sync();
}

function orderTotal() {
    let total = 0, unknown = false;
    order.forEach(l => {
        const item = ORDER_ITEMS[l.id];
        if (item.pricePo === null) unknown = true; else total += item.pricePo * l.qty;
    });
    return {total, unknown};
}
function saveOrder() { store.set('ecixs_order_v1', order); sync(); }
function addToOrder(id) {
    if (!DATA.user) { location.href = DATA.loginUrl; return; }
    const line = order.find(l => l.id === id);
    if (line) line.qty = Math.min(line.qty + 1, 20); else order.push({id, qty: 1});
    saveOrder();
    toast(`${ORDER_ITEMS[id].name} quedó en tu pedido.`);
}
function syncOrderBar() {
    if (!els.orderBar) return;
    const count = order.reduce((n, l) => n + l.qty, 0);
    const {total, unknown} = orderTotal();
    els.orderBar.hidden = count === 0 || !els.modal.hidden;
    els.orderCount.textContent = count;
    els.orderTotal.textContent = po(total) + (unknown ? ' + a consultar' : '');
}

/* ---------- ventanas ---------- */

let lastFocus = null;
let modalKind = '';
function openModal(html, kind) {
    if (els.modal.hidden) lastFocus = document.activeElement;
    modalKind = kind;
    els.modalBody.innerHTML = html;
    els.modal.hidden = false;
    document.body.classList.add('modal-open');
    syncOrderBar();
    (els.modalBody.querySelector('[data-autofocus]') || els.modal.querySelector('.modal-card')).focus();
}
function closeModal() {
    if (els.modal.hidden) return;
    els.modal.hidden = true;
    els.modalBody.innerHTML = '';
    document.body.classList.remove('modal-open');
    if (modalKind === 'detail' && location.hash.startsWith('#carta-')) history.replaceState(null, '', location.pathname + location.search);
    modalKind = '';
    syncOrderBar();
    lastFocus?.focus?.();
}

function openCard(id) {
    const c = CARDS[id];
    if (!c) return;
    const pos = visibleIds.indexOf(id);
    const prev = pos > 0 ? visibleIds[pos - 1] : null;
    const next = pos >= 0 && pos < visibleIds.length - 1 ? visibleIds[pos + 1] : null;
    const who = buyer();
    let affordLine = '';
    if (who && c.pricePo !== null) {
        affordLine = who.goldExact >= c.pricePo
            ? `<p class="afford yes">✓ ${esc(who.name)} puede pagarlo (le quedarían ${esc(po(who.goldExact - c.pricePo))}).</p>`
            : `<p class="afford no">A ${esc(who.name)} le faltan ${esc(po(c.pricePo - who.goldExact))}.</p>`;
    }
    const inOrder = order.find(l => l.id === id);
    openModal(`
        <article class="detail">
            <div class="detail-image"><img src="${esc(c.url)}" alt="${esc(c.name)}" data-fallback="${esc(c.thumb)}"></div>
            <div class="detail-copy">
                <p class="eyebrow">${esc(c.category)}</p>
                <h2 id="modalTitle">${esc(c.name)}</h2>
                <p class="detail-price">${esc(c.price)}</p>
                <p class="stock ${c.soldOut ? 'zero' : ''}">${c.soldOut ? 'Agotado' : 'Stock: ' + Number(c.stock)}</p>
                ${affordLine}
                <div class="detail-section-title">Descripción y propiedades</div>
                <div class="detail-description">${descriptionHtml(c.description)}</div>
                <div class="detail-actions">
                    ${DATA.user ? `<button type="button" class="primary" data-act="order" ${c.soldOut ? 'disabled' : ''}>${inOrder ? `En el pedido (${inOrder.qty}) · añadir otro` : '+ Añadir al pedido'}</button>` : ''}
                    <button type="button" data-act="fav">${favorites.includes(id) ? '♥ Quitar favorito' : '♡ Añadir favorito'}</button>
                    <button type="button" data-act="cmp">${compare.includes(id) ? 'Quitar del comparador' : 'Añadir al comparador'}</button>
                    <button type="button" data-act="link">🔗 Copiar enlace</button>
                    <button type="button" data-act="discord">Copiar para Discord</button>
                </div>
            </div>
        </article>
        <nav class="detail-nav" aria-label="Recorrer el catálogo">
            <button type="button" data-go="${esc(prev || '')}" ${prev ? '' : 'disabled'}>← Anterior</button>
            <span>${pos >= 0 ? `${pos + 1} de ${visibleIds.length}` : ''}</span>
            <button type="button" data-go="${esc(next || '')}" ${next ? '' : 'disabled'}>Siguiente →</button>
        </nav>`, 'detail');
    els.modal.dataset.card = id;
    history.replaceState(null, '', location.pathname + location.search + '#carta-' + id);
}

function compareNow() {
    const selected = compare.map(id => CARDS[id]).filter(Boolean);
    if (selected.length < 2) return;
    openModal(`<h2 id="modalTitle" class="sr-only" hidden>Comparador</h2><div class="compare-grid">${selected.map(c => `
        <article class="compare-item">
            <img src="${esc(c.thumb)}" alt="${esc(c.name)}">
            <h3>${esc(c.name)}</h3>
            <p class="compare-meta">${esc(c.price)} · ${c.soldOut ? 'Agotado' : 'Stock: ' + Number(c.stock)}</p>
            <p class="compare-meta">${esc(c.category)}</p>
            <div class="compare-description">${descriptionHtml(c.description)}</div>
            <button class="compare-remove" type="button" data-uncompare="${esc(c.id)}">Quitar</button>
        </article>`).join('')}</div>`, 'compare');
}

function orderForm() {
    if (!order.length) { toast('Tu pedido está vacío.'); return; }
    const {total, unknown} = orderTotal();
    const options = (DATA.characters || []).map(c => `<option value="${esc(c.id)}" ${els.buyer?.value === c.id ? 'selected' : ''}>${esc(c.name)}${DATA.user.admin ? ' (' + esc(c.playerName) + ')' : ''} · ${esc(po(c.goldExact))}</option>`).join('');
    const who = characters[els.buyer?.value || ''];
    const warning = who && !unknown && total > who.goldExact
        ? `<p class="order-warning">A ${esc(who.name)} le faltan ${esc(po(total - who.goldExact))} para todo el pedido. Puedes enviarlo igual; Ecix's decide.</p>` : '';
    openModal(`
        <form class="order-form" id="orderForm">
            <h2 id="modalTitle">Tu pedido a Ecix's</h2>
            <ul class="order-lines">${order.map(l => {
                const item = ORDER_ITEMS[l.id];
                return `<li>
                    ${item.thumb ? `<img src="${esc(item.thumb)}" alt="">` : '<span aria-hidden="true">✦</span>'}
                    <span><b>${esc(item.name)}</b><br><small class="line-price">${esc(item.price)}</small></span>
                    <span class="qty"><button type="button" data-qty="-1" data-line="${esc(l.id)}" aria-label="Uno menos">−</button><b>${l.qty}</b><button type="button" data-qty="1" data-line="${esc(l.id)}" aria-label="Uno más">+</button></span>
                    <button class="remove" type="button" data-remove="${esc(l.id)}" aria-label="Quitar ${esc(item.name)}">×</button>
                </li>`;
            }).join('')}</ul>
            <div class="order-total"><span>Total estimado</span><span>${esc(po(total))}${unknown ? ' + precios a consultar' : ''}</span></div>
            ${warning}
            <label class="field"><span class="field-label">Personaje que compra</span>
                <select name="character" required data-autofocus><option value="">— Elige personaje —</option>${options}</select></label>
            <label class="field"><span class="field-label">Mensaje para Ecix's (opcional)</span>
                <textarea name="note" maxlength="500" placeholder="Para cuándo lo necesitas, si quieres regatear…"></textarea></label>
            <div class="detail-actions">
                <button type="submit" class="primary">Enviar pedido</button>
                <button type="button" data-act="empty">Vaciar pedido</button>
            </div>
        </form>`, 'order');
}

async function sendOrder(form) {
    const button = form.querySelector('[type=submit]');
    button.disabled = true;
    try {
        const res = await fetch('pedidos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf},
            body: JSON.stringify({action: 'create', character: form.character.value, note: form.note.value, items: order}),
        });
        const out = await res.json().catch(() => ({ok: false, error: 'El servidor no respondió bien.'}));
        if (!out.ok) throw new Error(out.error || 'No se pudo enviar el pedido.');
        order = [];
        saveOrder();
        DATA.orders.unshift(out.order);
        closeModal();
        renderOrders();
        showView('orders');
        toast('Pedido enviado. Ecix\'s lo revisará pronto.');
    } catch (err) {
        toast(err.message);
        button.disabled = false;
    }
}

async function cancelOrder(id) {
    if (!confirm('¿Cancelar este pedido?')) return;
    try {
        const res = await fetch('pedidos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf},
            body: JSON.stringify({action: 'cancel', id}),
        });
        const out = await res.json().catch(() => ({ok: false}));
        if (!out.ok) throw new Error(out.error || 'No se pudo cancelar.');
        DATA.orders = DATA.orders.map(o => o.id === id ? out.order : o);
        renderOrders();
        toast('Pedido cancelado.');
    } catch (err) { toast(err.message); }
}

function renderOrders() {
    const list = $('#ordersList');
    if (!list) return;
    if (!DATA.orders.length) {
        list.innerHTML = '<div class="empty">Todavía no hay pedidos. Usa «+ Pedido» en las cartas para armar uno.</div>';
        return;
    }
    const date = s => { const d = new Date(s); return isNaN(d) ? '' : d.toLocaleString('es-CL', {dateStyle: 'medium', timeStyle: 'short'}); };
    list.innerHTML = DATA.orders.map(o => `
        <article class="order-card">
            <header>
                <h3>${esc(o.character)}${DATA.user.admin ? ` <span class="muted">· ${esc(o.player)}</span>` : ''}</h3>
                <span class="state state-${esc(o.state)}">${esc(o.stateLabel)}</span>
            </header>
            <p class="muted">${esc(date(o.created))}${o.total !== null ? ' · Total estimado ' + esc(po(o.total)) : ''}</p>
            <ul>${o.items.map(i => `<li>${Number(i.qty) > 1 ? Number(i.qty) + ' × ' : ''}${esc(i.name)} <span class="muted">${esc(i.price)}</span></li>`).join('')}</ul>
            ${o.note ? `<p class="muted">Tu mensaje: ${esc(o.note)}</p>` : ''}
            ${o.reply ? `<p class="reply">Ecix's: ${esc(o.reply)}</p>` : ''}
            ${o.state === 'pendiente' && !DATA.user.admin ? `<button class="tool-btn secondary" type="button" data-cancel="${esc(o.id)}">Cancelar pedido</button>` : ''}
            ${DATA.user.admin ? '<a class="tool-btn" href="admin.php?vista=pedidos">Gestionar en el admin</a>' : ''}
        </article>`).join('');
}

/* ---------- pestañas ---------- */

function showView(name) {
    ['catalog', 'services', 'orders'].forEach(v => {
        const view = $(`#${v}View`);
        const tab = $(`#${v}Tab`);
        if (view) view.hidden = v !== name;
        if (tab) { tab.classList.toggle('active', v === name); tab.setAttribute('aria-pressed', String(v === name)); }
    });
    if (name === 'orders') renderOrders();
}

/* ---------- eventos ---------- */

cardEls.forEach(el => {
    const id = el.dataset.id;
    el.querySelector('.card-image').addEventListener('click', () => openCard(id));
    el.querySelector('.favorite-btn').addEventListener('click', () => toggleFav(id));
    el.querySelector('.select-btn').addEventListener('click', () => toggleCompare(id));
    el.querySelector('.order-btn')?.addEventListener('click', () => addToOrder(id));
});
// Si una miniatura WebP falta en el servidor, se muestra la carta original.
document.addEventListener('error', e => {
    const img = e.target;
    if (img.tagName === 'IMG' && img.dataset.fallback && img.src.indexOf(img.dataset.fallback) === -1) {
        img.removeAttribute('srcset');
        img.src = img.dataset.fallback;
    }
}, true);

els.search.addEventListener('input', sync);
[els.category, els.availability, els.affordable].forEach(el => el?.addEventListener('change', sync));
els.buyer?.addEventListener('change', () => { if (!els.buyer.value && els.affordable) els.affordable.checked = false; sync(); });
els.sort.addEventListener('change', () => { sortCards(); sync(); });
els.searchClear.addEventListener('click', () => { els.search.value = ''; els.search.focus(); sync(); });
els.favoritesToggle.addEventListener('click', () => { onlyFavorites = !onlyFavorites; sync(); });
els.compareButton.addEventListener('click', compareNow);
els.clearButton.addEventListener('click', clearFilters);
$('#noResultsClear').addEventListener('click', clearFilters);
els.randomButton.addEventListener('click', () => {
    const pool = visibleIds.length ? visibleIds : Object.keys(CARDS);
    if (!pool.length) return;
    const id = pool[Math.floor(Math.random() * pool.length)];
    const el = document.getElementById('carta-' + id);
    el?.scrollIntoView({behavior: 'smooth', block: 'center'});
    el?.classList.remove('flash'); void el?.offsetWidth; el?.classList.add('flash');
    setTimeout(() => openCard(id), 450);
});
$('#catalogTab').addEventListener('click', () => showView('catalog'));
$('#servicesTab').addEventListener('click', () => showView('services'));
$('#ordersTab')?.addEventListener('click', () => showView('orders'));
$('#orderOpen')?.addEventListener('click', orderForm);
$('#ordersList')?.addEventListener('click', e => { const b = e.target.closest('[data-cancel]'); if (b) cancelOrder(b.dataset.cancel); });
document.querySelectorAll('.service-order').forEach(b => b.addEventListener('click', () => addToOrder('servicio:' + b.closest('[data-service]').dataset.service)));

els.modal.addEventListener('click', e => {
    if (e.target === els.modal || e.target.closest('.modal-close')) { closeModal(); return; }
    const go = e.target.closest('[data-go]');
    if (go && go.dataset.go) { openCard(go.dataset.go); return; }
    const un = e.target.closest('[data-uncompare]');
    if (un) { toggleCompare(un.dataset.uncompare); compare.length >= 2 ? compareNow() : closeModal(); return; }
    const qty = e.target.closest('[data-qty]');
    if (qty) {
        const line = order.find(l => l.id === qty.dataset.line);
        if (line) line.qty = Math.max(0, Math.min(20, line.qty + Number(qty.dataset.qty)));
        order = order.filter(l => l.qty > 0);
        saveOrder();
        order.length ? orderForm() : closeModal();
        return;
    }
    const rm = e.target.closest('[data-remove]');
    if (rm) { order = order.filter(l => l.id !== rm.dataset.remove); saveOrder(); order.length ? orderForm() : closeModal(); return; }
    const act = e.target.closest('[data-act]')?.dataset.act;
    const id = els.modal.dataset.card;
    if (!act) return;
    if (act === 'empty') { order = []; saveOrder(); closeModal(); toast('Pedido vaciado.'); return; }
    const c = CARDS[id];
    if (!c) return;
    if (act === 'order') { addToOrder(id); openCard(id); }
    if (act === 'fav') { toggleFav(id); openCard(id); }
    if (act === 'cmp') { toggleCompare(id); openCard(id); }
    if (act === 'link') copyText(location.origin + location.pathname + '#carta-' + id, 'Enlace copiado.');
    if (act === 'discord') copyText(`**${c.name}** · ${c.price}\n*${c.category}*\n> ${c.description.replace(/\n+/g, '\n> ')}\n${location.origin + location.pathname}#carta-${id}`, 'Texto copiado: pégalo en Discord.');
});
els.modal.addEventListener('submit', e => { e.preventDefault(); sendOrder(e.target); });

document.addEventListener('keydown', e => {
    if (els.modal.hidden) return;
    if (e.key === 'Escape') { closeModal(); return; }
    if (modalKind === 'detail' && !e.target.closest('input,textarea,select') && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
        const btn = els.modal.querySelectorAll('[data-go]')[e.key === 'ArrowLeft' ? 0 : 1];
        if (btn?.dataset.go) openCard(btn.dataset.go);
    }
    // Mantiene el foco dentro de la ventana.
    if (e.key === 'Tab') {
        const f = [...els.modal.querySelectorAll('button:not(:disabled),select,textarea,input,a[href],[tabindex="0"]')];
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
});

function openFromHash() {
    const m = location.hash.match(/^#carta-(.+)$/);
    if (m && CARDS[decodeURIComponent(m[1])]) { showView('catalog'); openCard(decodeURIComponent(m[1])); }
}
window.addEventListener('hashchange', openFromHash);

readUrl();
sortCards();
sync();
openFromHash();
})();
