// Balas y Sangre: lo propio de la tienda (estadísticas de armas, precios en oro y
// plata, frases de Madeline). Catálogo, filtros, favoritos y ficha: ../tienda.js.
(() => {
    'use strict';

    const sellerQuotes = [
        '“Menos palabras. Más puntería.”',
        '“Si puedes resolverlo con tus manos, no necesitas un truco mágico.”',
        '“La pólvora no tiene ego. Tú sí. Así que apunta bien.”',
        '“No vendo milagros. Vendo herramientas para seguir vivo.”',
        '“Si no sabes qué hace el mecanismo, no pongas el dedo ahí.”',
        '“Cuida tu arma y ella tendrá la decencia de no fallarte cuando importe.”'
    ];

    function categoryKey(category) {
        if (category === 'Armas de fuego') return 'armas';
        if (category === 'Munición') return 'municion';
        if (category === 'Pólvora') return 'polvora';
        if (category === 'Herramientas') return 'herramientas';
        return 'otros';
    }

    // «6 Plata / 2 Oro» cuenta como 2,6 de oro: suma todas las monedas del precio.
    function numericPrice(price) {
        const text = FavorTienda.norm(price);
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

    function detailStats(product, esc) {
        const stats = [];
        if (product.damage) stats.push(['Daño', product.damage]);
        if (product.range) stats.push(['Rango', product.range]);
        if (product.ammunition) stats.push(['Capacidad', product.ammunition]);
        if (product.ammo_price) stats.push(['Munición', product.ammo_price]);
        if (product.quantity) stats.push(['Cantidad', product.quantity]);
        return stats.map(([label, value]) => `<div class="detail-stat"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('');
    }

    function descriptionHtml(product, esc) {
        return esc(shortDescription(product))
            .split(/\n{2,}/)
            .map(paragraph => `<p>${paragraph.replaceAll('\n', '<br>')}</p>`)
            .join('');
    }

    FavorTienda.create({
        storageKey: 'bys:favorites',
        fallback: 'assets/placeholders/herramientas.svg',
        toastMs: 1900,
        price: numericPrice,
        searchText: p => [p.name, p.category, p.price, p.damage, p.range, p.ammunition, p.quantity, Array.isArray(p.properties) ? p.properties : [], p.description],
        count: n => `${n} ${n === 1 ? 'producto' : 'productos'}`,
        favoriteText: {
            on: p => `${p.name} marcado para revisar después`,
            off: p => `${p.name} retirado del cajón de favoritos`,
        },
        card: (product, { esc, img, favButton }) => {
            const stats = quickStats(product).map(value => `<span>${esc(value)}</span>`).join('');
            return `
            <article class="product-card" data-product-id="${esc(product.id)}" data-category-key="${categoryKey(product.category)}">
                <div class="product-media">
                    ${img(product)}
                    ${favButton(product)}
                </div>
                <div class="product-body">
                    <div class="product-meta">
                        <span class="product-category">${esc(product.category)}</span>
                        <span class="product-price">${esc(product.price)}</span>
                    </div>
                    <h3>${esc(product.name)}</h3>
                    ${stats ? `<div class="product-quick">${stats}</div>` : ''}
                    <p class="product-description">${esc(shortDescription(product))}</p>
                    <button class="product-open" type="button" data-open="${esc(product.id)}">Inspeccionar pieza</button>
                </div>
            </article>`;
        },
        detail: (product, { esc, img, buyBox }) => {
            const properties = Array.isArray(product.properties) && product.properties.length
                ? `<div class="detail-properties">${product.properties.map(prop => `<span>${esc(prop)}</span>`).join('')}</div>`
                : '';
            const stats = detailStats(product, esc);
            const accent = product.category === 'Armas de fuego' ? '#8f413a' : product.category === 'Herramientas' ? '#59684f' : '#9f7440';
            return `
            <article class="detail">
                <div class="detail-media">${img(product)}</div>
                <div class="detail-body">
                    <span class="product-category" style="--card-accent:${accent}">${esc(product.category)}</span>
                    <h2 id="modalTitle">${esc(product.name)}</h2>
                    <div class="detail-price">${esc(product.price)}</div>
                    ${stats ? `<div class="detail-stats">${stats}</div>` : ''}
                    ${properties}
                    <div class="detail-description">${descriptionHtml(product, esc)}</div>
                    ${buyBox(product)}
                </div>
            </article>`;
        },
    });

    // Frases de Madeline.
    const sellerQuoteText = document.getElementById('sellerQuoteText');
    const heroSellerQuote = document.getElementById('heroSellerQuote');
    let quoteIndex = 0;
    document.getElementById('sellerQuoteButton')?.addEventListener('click', () => {
        quoteIndex = (quoteIndex + 1) % sellerQuotes.length;
        const quote = sellerQuotes[quoteIndex];
        if (sellerQuoteText) sellerQuoteText.textContent = quote;
        if (heroSellerQuote) heroSellerQuote.textContent = quote;
    });
})();
