// La Dama de las Llamas: lo propio de la tienda. El resto lo hace ../tienda.js.
(() => {
'use strict';

const isArcane = p => p.category === 'Mejoras mágicas';

function tags(p, esc) {
    const list = [];
    if (p.appliesTo) list.push(p.appliesTo);
    if (Array.isArray(p.properties)) list.push(...p.properties.slice(0, 2));
    if (Array.isArray(p.variants) && p.variants.length) list.push(`${p.variants.length} variantes`);
    return list.slice(0, 3).map(x => `<span>${esc(x)}</span>`).join('');
}

FavorTienda.create({
    storageKey: 'ddl:favorites',
    fallback: 'assets/placeholders/objeto-magico.svg',
    searchText: p => [p.name, p.category, p.price, p.description, p.appliesTo, p.properties || [], p.variants || []],
    count: n => `${n} ${n === 1 ? 'entrada' : 'entradas'}`,
    favoriteText: { on: p => `${p.name} marcado para revisar`, off: p => `${p.name} retirado de favoritos` },
    card: (p, { esc, img, favButton }) => {
        const t = tags(p, esc);
        return `<article class="product-card" data-category="${esc(p.category)}" style="--accent:${isArcane(p) ? '#65d8d2' : '#b97a37'}">
        <div class="product-media">${img(p)}
            ${favButton(p)}
        </div>
        <div class="product-body">
            <div class="product-meta"><span class="product-category">${esc(p.category)}</span><span class="product-price">${esc(p.price)}</span></div>
            <h3>${esc(p.name)}</h3>
            ${t ? `<div class="product-tags">${t}</div>` : ''}
            <p class="product-description">${esc(p.description || '')}</p>
            <button class="product-open" type="button" data-open="${esc(p.id)}">${isArcane(p) ? 'Revisar mejora' : 'Inspeccionar pieza'}</button>
        </div>
    </article>`;
    },
    detail: (p, { esc, img, buyBox }) => {
        const props = Array.isArray(p.properties) && p.properties.length ? `<div class="detail-properties">${p.properties.map(x => `<span>${esc(x)}</span>`).join('')}</div>` : '';
        const variants = Array.isArray(p.variants) && p.variants.length ? `<div class="detail-variants">${p.variants.map(x => `<span>${esc(x)}</span>`).join('')}</div>` : '';
        return `<article class="detail" data-arcane="${isArcane(p) ? 'true' : 'false'}">
        <div class="detail-media">${img(p)}</div>
        <div class="detail-body">
            <span class="product-category">${esc(p.category)}</span>
            <h2 id="modalTitle">${esc(p.name)}</h2>
            <div class="detail-price">${esc(p.price)}</div>
            ${p.appliesTo ? `<div class="detail-properties"><span>Aplicable a: ${esc(p.appliesTo)}</span></div>` : ''}
            ${props}${variants}
            <div class="detail-description"><p>${esc(p.description).replaceAll('\n', '<br>')}</p></div>
            ${buyBox(p)}
        </div>
    </article>`;
    },
});
})();
