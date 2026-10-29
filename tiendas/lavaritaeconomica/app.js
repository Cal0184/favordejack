// La Varita Económica: lo propio de la tienda. El resto lo hace ../tienda.js.
(() => {
'use strict';

const colors = {
    'Pergaminos': '#d3c681', 'Tatuajes': '#d96dc2', 'Brebajes': '#64d78a', 'Mezclas especiales': '#72cbd2',
    'Sustancias': '#d4c64a', 'Objetos mágicos?': '#83c7cf', 'Defectuosos': '#e1714f',
};
const accent = p => colors[p.category] || '#74ef85';
const teaser = p => p.effect || p.description || 'Sin descripción fiable.';

function detailSections(p, esc) {
    const out = [];
    if (p.effect) out.push(`<h4>Efecto</h4><p>${esc(p.effect)}</p>`);
    if (p.sideEffect) out.push(`<h4>Efecto secundario / coste</h4><p>${esc(p.sideEffect)}</p>`);
    if (p.addiction) out.push(`<h4>Adicción</h4><p>${esc(p.addiction)}</p>`);
    return out.join('');
}

const shop = FavorTienda.create({
    storageKey: 'lve:favorites',
    fallback: 'assets/placeholders/objetos.svg',
    searchText: p => [p.name, p.category, p.subcategory, p.price, p.description, p.effect, p.sideEffect, p.addiction, p.sellerNote, p.tags || [], (p.stats || []).flat()],
    count: n => `${n} ${n === 1 ? 'objeto encontrado' : 'objetos encontrados'}`,
    favoriteText: { on: () => 'Marcado para después.', off: () => 'Devuelto a la caja.' },
    card: (p, { esc, img, favButton }) => {
        const tags = (p.tags || []).slice(0, 3).map(x => `<span>${esc(x)}</span>`).join('');
        return `<article class="product-card" style="--accent:${accent(p)}" data-risk="${esc(p.risk || '')}">
 <div class="product-media">${img(p)}${favButton(p)}</div>
 <div class="product-body">
   <div class="product-meta"><span class="product-category">${esc(p.category)}</span><span class="product-price">${esc(p.price)}</span></div>
   <h3>${esc(p.name)}</h3><span class="product-sub">${esc(p.subcategory || '')}</span>
   ${tags ? `<div class="product-tags">${tags}</div>` : ''}
   <p class="product-description">${esc(teaser(p))}</p>
   <button class="product-open" type="button" data-open="${esc(p.id)}">Abrir bolsa</button>
 </div></article>`;
    },
    detail: (p, { esc, img, buyBox }) => {
        const stats = Array.isArray(p.stats) && p.stats.length ? `<div class="detail-stats">${p.stats.map(s => `<div class="detail-stat"><span>${esc(s[0])}</span><strong>${esc(s[1])}</strong></div>`).join('')}</div>` : '';
        const tagHtml = (p.tags || []).length ? `<div class="detail-tags">${p.tags.map(x => `<span>${esc(x)}</span>`).join('')}</div>` : '';
        return `<article class="detail" style="--accent:${accent(p)}">
 <div class="detail-media">${img(p)}</div>
 <div class="detail-body">
   <span class="product-category">${esc(p.category)}</span>
   <h2 id="modalTitle">${esc(p.name)}</h2>
   <div class="detail-price">${esc(p.price)}</div>
   <div class="detail-sub">${esc(p.subcategory || '')}</div>
   ${stats}${tagHtml}
   <div class="detail-description"><p>${esc(p.description || 'Sin descripción disponible.')}</p>${detailSections(p, esc)}</div>
   ${p.sellerNote ? `<div class="detail-note">${esc(p.sellerNote)}</div>` : ''}
   ${buyBox(p)}
 </div></article>`;
    },
});

// Botón «Música» (falta la canción: Cal lo está pensando).
const musicBtn = document.getElementById('musicButton');
const audio = document.getElementById('backgroundMusic');
musicBtn?.addEventListener('click', async () => {
    if (!audio) return;
    if (!audio.paused) { audio.pause(); musicBtn.setAttribute('aria-pressed', 'false'); musicBtn.innerHTML = '<span>♫</span> Música'; return; }
    try {
        await audio.play();
        musicBtn.setAttribute('aria-pressed', 'true');
        musicBtn.innerHTML = '<span>■</span> Pausar';
    } catch (_) {
        shop.showToast('Añade tu audio como assets/tema.mp3');
        musicBtn.setAttribute('aria-pressed', 'false');
    }
});
audio?.addEventListener('error', () => { musicBtn?.setAttribute('aria-pressed', 'false'); });
})();
