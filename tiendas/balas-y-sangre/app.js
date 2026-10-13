(() => {
    'use strict';

    const rootData = document.getElementById('shopData');
    let stateData = { products: [], shop: {} };
    try {
        stateData = JSON.parse(rootData?.textContent || '{}');
    } catch (error) {
        console.error('No se pudo leer la información de la tienda.', error);
    }

    const products = Array.isArray(stateData.products) ? stateData.products : [];
    const grid = document.getElementById('productGrid');
    const emptyState = document.getElementById('emptyState');
    const resultsCount = document.getElementById('resultsCount');
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const tabs = [...document.querySelectorAll('.category-tab')];
    const clearFilters = document.getElementById('clearFilters');
    const favoritesButton = document.getElementById('favoritesButton');
    const favoriteCount = document.getElementById('favoriteCount');
    const modal = document.getElementById('productModal');
    const modalContent = document.getElementById('modalContent');
    const toast = document.getElementById('toast');
    const sellerQuoteText = document.getElementById('sellerQuoteText');
    const heroSellerQuote = document.getElementById('heroSellerQuote');
    const sellerQuoteButton = document.getElementById('sellerQuoteButton');

    const sellerQuotes = [
        '“Menos palabras. Más puntería.”',
        '“Si puedes resolverlo con tus manos, no necesitas un truco mágico.”',
        '“La pólvora no tiene ego. Tú sí. Así que apunta bien.”',
        '“No vendo milagros. Vendo herramientas para seguir vivo.”',
        '“Si no sabes qué hace el mecanismo, no pongas el dedo ahí.”',
        '“Cuida tu arma y ella tendrá la decencia de no fallarte cuando importe.”'
    ];

    let activeCategory = 'Todos';
    let favoritesOnly = false;
    let favorites = loadFavorites();
    let lastFocused = null;
    let toastTimer = 0;
    let quoteIndex = 0;

    function loadFavorites() {
        try {
            const parsed = JSON.parse(localStorage.getItem('bys:favorites') || '[]');
            return new Set(Array.isArray(parsed) ? parsed : []);
        } catch (_) {
            return new Set();
        }
    }

    function saveFavorites() {
        try {
            localStorage.setItem('bys:favorites', JSON.stringify([...favorites]));
        } catch (_) {}
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function normalize(value) {
        return String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function categoryKey(category) {
        if (category === 'Armas de fuego') return 'armas';
        if (category === 'Munición') return 'municion';
        if (category === 'Pólvora') return 'polvora';
        if (category === 'Herramientas') return 'herramientas';
        return 'otros';
    }

    function numericPrice(price) {
        const text = normalize(price);
        const values = [...text.matchAll(/(\d+(?:[.,]\d+)?)\s*(oro|po|gp|plata|pp|pc)?/g)];
        if (!values.length) return Number.POSITIVE_INFINITY;
        let total = 0;
        for (const match of values) {
            const n = Number(String(match[1]).replace(',', '.')) || 0;
            const unit = match[2] || 'oro';
            if (unit === 'plata') total += n / 10;
            else total += n;
        }
        return total;
    }

    function shortDescription(product) {
        if (product.description) return product.description;
        if (product.category === 'Armas de fuego') {
            return `${product.damage || '—'} de daño · rango ${product.range || '—'} · ${Array.isArray(product.properties) ? product.properties.join(', ') : ''}`;
        }
        return 'Producto disponible en el catálogo de Balas y Sangre.';
    }

    function quickStats(product) {
        const list = [];
        if (product.damage) list.push(`Daño ${product.damage}`);
        if (product.range) list.push(`Rango ${product.range}`);
        if (product.ammunition) list.push(product.ammunition);
        if (product.quantity) list.push(`Cantidad ${product.quantity}`);
        return list.slice(0, 3);
    }

    function imageHtml(product, className = '') {
        const fallback = escapeHtml(product.fallback || 'assets/placeholders/herramientas.svg');
        const src = escapeHtml(product.image || fallback);
        return `<img class="${className}" src="${src}" data-fallback="${fallback}" alt="${escapeHtml(product.name)}" loading="lazy">`;
    }

    function bindImageFallbacks(scope = document) {
        scope.querySelectorAll('img[data-fallback]').forEach(img => {
            if (img.dataset.fallbackBound === '1') return;
            img.dataset.fallbackBound = '1';
            img.addEventListener('error', () => {
                const fallback = img.dataset.fallback;
                if (fallback && img.getAttribute('src') !== fallback) img.setAttribute('src', fallback);
            }, { once: true });
        });
    }

    function currentProducts() {
        const query = normalize(searchInput?.value || '').trim();
        let list = products.filter(product => {
            if (activeCategory !== 'Todos' && product.category !== activeCategory) return false;
            if (favoritesOnly && !favorites.has(product.id)) return false;
            if (!query) return true;
            const haystack = normalize([
                product.name,
                product.category,
                product.price,
                product.damage,
                product.range,
                product.ammunition,
                product.quantity,
                ...(Array.isArray(product.properties) ? product.properties : []),
                product.description,
            ].join(' '));
            return haystack.includes(query);
        });

        const sort = sortSelect?.value || 'default';
        if (sort === 'name-asc') list.sort((a, b) => a.name.localeCompare(b.name, 'es'));
        if (sort === 'price-asc') list.sort((a, b) => numericPrice(a.price) - numericPrice(b.price));
        if (sort === 'price-desc') list.sort((a, b) => numericPrice(b.price) - numericPrice(a.price));
        return list;
    }

    function productCard(product) {
        const isFavorite = favorites.has(product.id);
        const stats = quickStats(product).map(value => `<span>${escapeHtml(value)}</span>`).join('');
        return `
            <article class="product-card" data-product-id="${escapeHtml(product.id)}" data-category-key="${categoryKey(product.category)}">
                <div class="product-media">
                    ${imageHtml(product)}
                    <button class="favorite-icon ${isFavorite ? 'active' : ''}" type="button" data-favorite="${escapeHtml(product.id)}" aria-label="${isFavorite ? 'Quitar de favoritos' : 'Añadir a favoritos'}" aria-pressed="${isFavorite ? 'true' : 'false'}">♥</button>
                </div>
                <div class="product-body">
                    <div class="product-meta">
                        <span class="product-category">${escapeHtml(product.category)}</span>
                        <span class="product-price">${escapeHtml(product.price)}</span>
                    </div>
                    <h3>${escapeHtml(product.name)}</h3>
                    ${stats ? `<div class="product-quick">${stats}</div>` : ''}
                    <p class="product-description">${escapeHtml(shortDescription(product))}</p>
                    <button class="product-open" type="button" data-open-product="${escapeHtml(product.id)}">Inspeccionar pieza</button>
                </div>
            </article>`;
    }

    function render() {
        const list = currentProducts();
        if (grid) grid.innerHTML = list.map(productCard).join('');
        bindImageFallbacks(grid || document);
        if (emptyState) emptyState.hidden = list.length !== 0;
        if (grid) grid.hidden = list.length === 0;
        if (resultsCount) resultsCount.textContent = `${list.length} ${list.length === 1 ? 'producto' : 'productos'}`;

        const filtersActive = activeCategory !== 'Todos' || favoritesOnly || Boolean(searchInput?.value.trim()) || (sortSelect?.value || 'default') !== 'default';
        if (clearFilters) clearFilters.hidden = !filtersActive;
        updateFavoritesUi();
    }

    function updateFavoritesUi() {
        if (favoriteCount) favoriteCount.textContent = String(favorites.size);
        if (favoritesButton) favoritesButton.setAttribute('aria-pressed', favoritesOnly ? 'true' : 'false');
    }

    function toggleFavorite(id) {
        const product = products.find(item => item.id === id);
        if (!product) return;
        if (favorites.has(id)) {
            favorites.delete(id);
            showToast(`${product.name} retirado del cajón de favoritos`);
        } else {
            favorites.add(id);
            showToast(`${product.name} marcado para revisar después`);
        }
        saveFavorites();
        render();
    }

    function showToast(message) {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('show');
        toastTimer = window.setTimeout(() => toast.classList.remove('show'), 1900);
    }

    function detailStats(product) {
        const stats = [];
        if (product.damage) stats.push(['Daño', product.damage]);
        if (product.range) stats.push(['Rango', product.range]);
        if (product.ammunition) stats.push(['Capacidad', product.ammunition]);
        if (product.ammo_price) stats.push(['Munición', product.ammo_price]);
        if (product.quantity) stats.push(['Cantidad', product.quantity]);
        return stats.map(([label, value]) => `<div class="detail-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    }

    function descriptionHtml(product) {
        const description = shortDescription(product);
        return escapeHtml(description)
            .split(/\n{2,}/)
            .map(paragraph => `<p>${paragraph.replaceAll('\n', '<br>')}</p>`)
            .join('');
    }

    function openProduct(id) {
        const product = products.find(item => item.id === id);
        if (!product || !modal || !modalContent) return;
        lastFocused = document.activeElement;
        const properties = Array.isArray(product.properties) && product.properties.length
            ? `<div class="detail-properties">${product.properties.map(prop => `<span>${escapeHtml(prop)}</span>`).join('')}</div>`
            : '';
        const stats = detailStats(product);
        modalContent.innerHTML = `
            <article class="detail">
                <div class="detail-media">${imageHtml(product)}</div>
                <div class="detail-body">
                    <span class="product-category" style="--card-accent:${product.category === 'Armas de fuego' ? '#8f413a' : product.category === 'Herramientas' ? '#59684f' : '#9f7440'}">${escapeHtml(product.category)}</span>
                    <h2 id="modalTitle">${escapeHtml(product.name)}</h2>
                    <div class="detail-price">${escapeHtml(product.price)}</div>
                    ${stats ? `<div class="detail-stats">${stats}</div>` : ''}
                    ${properties}
                    <div class="detail-description">${descriptionHtml(product)}</div>
                    <div class="detail-note"><strong>Nota del mostrador:</strong> esta ficha es informativa. Aún no descuenta dinero de la Banca Digital ni modifica el stock compartido.</div>
                </div>
            </article>`;
        bindImageFallbacks(modalContent);
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        modal.querySelector('.modal-close')?.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (lastFocused instanceof HTMLElement) lastFocused.focus();
    }

    function showNextSellerQuote() {
        quoteIndex = (quoteIndex + 1) % sellerQuotes.length;
        const quote = sellerQuotes[quoteIndex];
        if (sellerQuoteText) sellerQuoteText.textContent = quote;
        if (heroSellerQuote) heroSellerQuote.textContent = quote;
    }

    tabs.forEach(tab => tab.addEventListener('click', () => {
        activeCategory = tab.dataset.category || 'Todos';
        tabs.forEach(item => item.classList.toggle('active', item === tab));
        render();
    }));

    searchInput?.addEventListener('input', render);
    sortSelect?.addEventListener('change', render);
    favoritesButton?.addEventListener('click', () => {
        favoritesOnly = !favoritesOnly;
        render();
    });
    clearFilters?.addEventListener('click', () => {
        activeCategory = 'Todos';
        favoritesOnly = false;
        if (searchInput) searchInput.value = '';
        if (sortSelect) sortSelect.value = 'default';
        tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.category === 'Todos'));
        render();
    });
    sellerQuoteButton?.addEventListener('click', showNextSellerQuote);

    grid?.addEventListener('click', event => {
        const favorite = event.target.closest('[data-favorite]');
        if (favorite) {
            toggleFavorite(favorite.dataset.favorite);
            return;
        }
        const opener = event.target.closest('[data-open-product]');
        if (opener) openProduct(opener.dataset.openProduct);
    });

    modal?.addEventListener('click', event => {
        if (event.target.closest('[data-close-modal]')) closeModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal?.classList.contains('open')) closeModal();
    });

    render();
})();
