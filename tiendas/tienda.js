/*
 * Motor común de las tiendas temáticas (Balas y Sangre, la Dama de las Llamas,
 * la Poción Picante y la Varita Económica).
 *
 * Hace lo que todas comparten: leer el catálogo (#shopData), buscar, filtrar por
 * categoría, ordenar, favoritos, la ficha del producto (con la caja de compra de
 * tiendas/compra.js), el aviso flotante y cerrar con Escape.
 * Cada tienda, en su app.js, solo pone lo suyo: cómo se ve una tarjeta y una
 * ficha, sus colores, textos y extras (frases del vendedor, descuentos, música).
 *
 *   FavorTienda.create({
 *     storageKey: 'xyz:favorites',     // favoritos en este navegador
 *     categoryOf: p => p.category,      // campo que usan las pestañas
 *     searchText: p => [...],           // textos donde busca el buscador
 *     card: (p, t) => '<article…>',     // tarjeta de la cuadrícula
 *     detail: (p, t) => '<article…>',   // contenido de la ficha
 *     count: n => `${n} productos`,     // texto del contador
 *   });
 */
(() => {
'use strict';

function esc(v) {
    return String(v ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
function norm(v) {
    return String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
/** Primer número del precio («25 Po» → 25). Sin número va al final. */
function numericPrice(v) {
    const m = norm(v).match(/(\d+(?:[.,]\d+)?)/);
    return m ? Number(m[1].replace(',', '.')) : Number.POSITIVE_INFINITY;
}

function create(cfg) {
    let data = { products: [], shop: {} };
    try { data = JSON.parse(document.getElementById('shopData')?.textContent || '{}') || data; } catch (_) {}
    const products = Array.isArray(data.products) ? data.products : [];

    const $ = id => document.getElementById(id);
    const grid = $('productGrid');
    const search = $('searchInput');
    const sort = $('sortSelect');
    const count = $('resultsCount');
    const empty = $('emptyState');
    const clear = $('clearFilters');
    const favBtn = $('favoritesButton');
    const favCount = $('favoriteCount');
    const modal = $('productModal');
    const modalContent = $('modalContent');
    const toast = $('toast');
    const tabs = [...document.querySelectorAll(cfg.tabs || '.category-tab, .filter-chip')].filter(t => t.dataset.category);

    const categoryOf = cfg.categoryOf || (p => p.category);
    const priceOf = cfg.price || numericPrice;
    let category = 'Todos';
    let favoritesOnly = false;
    let lastFocused = null;
    let toastTimer = 0;
    let openId = '';
    const favorites = loadFavorites();

    function loadFavorites() {
        try {
            const raw = JSON.parse(localStorage.getItem(cfg.storageKey) || '[]');
            return new Set(Array.isArray(raw) ? raw : []);
        } catch (_) { return new Set(); }
    }
    function saveFavorites() {
        try { localStorage.setItem(cfg.storageKey, JSON.stringify([...favorites])); } catch (_) {}
    }

    function img(p, className = '') {
        const fb = esc(p.fallback || cfg.fallback || '');
        const cls = className ? ` class="${esc(className)}"` : '';
        return `<img${cls} src="${esc(p.image || fb)}" data-fallback="${fb}" alt="${esc(p.name)}" loading="lazy">`;
    }
    function bindFallbacks(scope = document) {
        scope.querySelectorAll('img[data-fallback]').forEach(im => {
            if (im.dataset.bound) return;
            im.dataset.bound = '1';
            im.addEventListener('error', () => {
                const fb = im.dataset.fallback;
                if (fb && im.getAttribute('src') !== fb) im.setAttribute('src', fb);
            }, { once: true });
        });
    }
    /** Botón de favorito de una tarjeta (igual en todas, con nombre para lectores de pantalla). */
    function favButton(p) {
        const on = favorites.has(p.id);
        return `<button class="favorite-icon ${on ? 'active' : ''}" type="button" data-favorite="${esc(p.id)}" aria-label="${on ? 'Quitar de favoritos' : 'Añadir a favoritos'}: ${esc(p.name)}" aria-pressed="${on ? 'true' : 'false'}">♥</button>`;
    }
    const buyBox = p => (window.FavorCompra ? window.FavorCompra.html(p) : '');

    const tools = { esc, norm, img, favButton, buyBox, data, products, isFavorite: id => favorites.has(id) };

    function filtered() {
        const q = norm(search?.value || '').trim();
        const list = products.filter(p => {
            if (category !== 'Todos' && categoryOf(p) !== category) return false;
            if (favoritesOnly && !favorites.has(p.id)) return false;
            if (!q) return true;
            return norm(cfg.searchText(p).flat().join(' ')).includes(q);
        });
        const s = sort?.value || 'default';
        if (s === 'name-asc') list.sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'));
        if (s === 'price-asc') list.sort((a, b) => priceOf(a.price) - priceOf(b.price));
        if (s === 'price-desc') list.sort((a, b) => priceOf(b.price) - priceOf(a.price));
        return list;
    }

    function render() {
        const list = filtered();
        if (grid) {
            grid.innerHTML = list.map(p => cfg.card(p, tools)).join('');
            grid.hidden = !list.length;
            bindFallbacks(grid);
        }
        if (empty) empty.hidden = !!list.length;
        if (count) count.textContent = cfg.count(list.length);
        const active = category !== 'Todos' || favoritesOnly || !!search?.value.trim() || (sort?.value || 'default') !== 'default';
        if (clear) clear.hidden = !active;
        if (favCount) favCount.textContent = String(favorites.size);
        if (favBtn) favBtn.setAttribute('aria-pressed', favoritesOnly ? 'true' : 'false');
    }

    function setCategory(value) {
        category = value || 'Todos';
        tabs.forEach(t => {
            const on = t.dataset.category === category;
            t.classList.toggle('active', on);
            t.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        render();
    }

    function showToast(msg) {
        if (!toast) return;
        clearTimeout(toastTimer);
        toast.textContent = msg;
        toast.classList.add('show');
        toastTimer = setTimeout(() => toast.classList.remove('show'), cfg.toastMs || 1700);
    }

    function toggleFavorite(id) {
        const p = products.find(x => x.id === id);
        if (!p) return;
        const msg = cfg.favoriteText || {};
        if (favorites.has(id)) {
            favorites.delete(id);
            showToast(typeof msg.off === 'function' ? msg.off(p) : `${p.name} retirado de favoritos`);
        } else {
            favorites.add(id);
            showToast(typeof msg.on === 'function' ? msg.on(p) : `${p.name} guardado para revisar`);
        }
        saveFavorites();
        render();
    }

    function openProduct(id) {
        const p = products.find(x => x.id === id);
        if (!p || !modal || !modalContent) return;
        const wasOpen = modal.classList.contains('open');
        if (!wasOpen) lastFocused = document.activeElement;
        openId = id;
        modalContent.innerHTML = cfg.detail(p, tools);
        bindFallbacks(modalContent);
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        if (!wasOpen) modal.querySelector('.modal-close')?.focus({ preventScroll: true });
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        openId = '';
        if (lastFocused instanceof HTMLElement) lastFocused.focus();
    }

    tabs.forEach(t => t.addEventListener('click', () => setCategory(t.dataset.category)));
    document.querySelectorAll('[data-jump-category]').forEach(a => a.addEventListener('click', () => setCategory(a.dataset.jumpCategory)));
    search?.addEventListener('input', render);
    sort?.addEventListener('change', render);
    favBtn?.addEventListener('click', () => { favoritesOnly = !favoritesOnly; render(); });
    clear?.addEventListener('click', () => {
        favoritesOnly = false;
        if (search) search.value = '';
        if (sort) sort.value = 'default';
        setCategory('Todos');
    });
    document.addEventListener('click', e => {
        const fav = e.target.closest('[data-favorite]');
        if (fav) { e.preventDefault(); e.stopPropagation(); toggleFavorite(fav.dataset.favorite); return; }
        const open = e.target.closest('[data-open]');
        if (open) { openProduct(open.dataset.open); return; }
        if (e.target.closest('[data-close-modal]')) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal?.classList.contains('open')) closeModal();
    });

    tabs.forEach(t => t.setAttribute('aria-pressed', t.classList.contains('active') ? 'true' : 'false'));
    render();

    return {
        render, showToast, openProduct, closeModal, setCategory,
        /** Vuelve a pintar la ficha abierta (por ejemplo al cambiar de comprador). */
        refreshOpen() { if (openId && modal?.classList.contains('open')) openProduct(openId); },
        ...tools,
    };
}

window.FavorTienda = { create, esc, norm, numericPrice };
})();
