/*
 * Botón «Comprar» de las tiendas temáticas. Cada tienda pone en su ficha
 * FavorCompra.html(producto) y este archivo se encarga del resto: elegir
 * personaje, cantidad y descuento, cobrar en la Banca (tiendas/comprar.php) y
 * avisar de que el objeto ya está en el Inventario.
 * Los datos vienen en <script id="favorCompra" type="application/json">.
 */
(() => {
'use strict';
let cfg = {};
try { cfg = JSON.parse(document.getElementById('favorCompra')?.textContent || '{}') || {}; } catch (_) { cfg = {}; }
let buyers = Array.isArray(cfg.buyers) ? cfg.buyers : [];
const products = new Map();
const KEY = 'favor:comprador';

const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
function coins(copper) {
  const po = Math.floor(copper / 100), pl = Math.floor(copper % 100 / 10), pc = copper % 10, parts = [];
  if (po) parts.push(`${po.toLocaleString('es-CL')} po`); if (pl) parts.push(`${pl} pl`); if (pc) parts.push(`${pc} pc`);
  return parts.join(' ') || '0 po';
}
// Igual que favor_price_copper() en includes/tiendas.php.
function priceCopper(price) {
  const m = String(price ?? '').match(/(\d+(?:[.,]\d+)?)\s*(?:de\s+)?(oro|po|gp|g|plata|pl|sp|cobre|pc|cp)?/i);
  if (!m) return null;
  const n = Number(m[1].replace(/\.(?=\d{3}\b)/g, '').replace(',', '.'));
  const u = (m[2] || '').toLowerCase();
  const c = Math.round(n * (['plata', 'pl', 'sp'].includes(u) ? 10 : ['cobre', 'pc', 'cp'].includes(u) ? 1 : 100));
  return c > 0 ? c : null;
}
function chosen() {
  let id = '';
  try { id = localStorage.getItem(KEY) || localStorage.getItem('lpp:buyer') || ''; } catch (_) {}
  return buyers.some(b => b.id === id) ? id : (buyers.length === 1 ? buyers[0].id : '');
}
function remember(id) { try { localStorage.setItem(KEY, id); localStorage.setItem('lpp:buyer', id); } catch (_) {} }

function html(p) {
  if (!p || !p.id) return '';
  products.set(p.id, p);
  if (p.available === false) return '<div class="fbuy fbuy-off">Ahora no está a la venta.</div>';
  const unit = priceCopper(p.price);
  if (unit === null) return '<div class="fbuy fbuy-off">Sin precio: pregúntale al DM.</div>';
  if (!cfg.user) return `<div class="fbuy fbuy-off"><a href="${esc(cfg.login || '#')}">Inicia sesión</a> para comprarlo con tu oro de la Banca.</div>`;
  if (!buyers.length) return '<div class="fbuy fbuy-off">No tienes personajes activos en la Banca para comprar.</div>';
  const sel = chosen();
  const opts = buyers.map(b => `<option value="${esc(b.id)}"${b.id === sel ? ' selected' : ''}>${esc(b.name)}${buyers.some(o => o.playerName !== b.playerName) ? ' · ' + esc(b.playerName) : ''}</option>`).join('');
  return `<div class="fbuy" data-fbuy="${esc(p.id)}">
  <div class="fbuy-row">
   <label class="fbuy-field"><span>Comprar con</span><select data-fbuy-char>${buyers.length > 1 && !sel ? '<option value="">— Elige personaje —</option>' : ''}${opts}</select></label>
   <label class="fbuy-field fbuy-qty"><span>Cantidad</span><input type="number" min="1" max="50" value="1" inputmode="numeric" data-fbuy-qty></label>
  </div>
  <label class="fbuy-discount" hidden><input type="checkbox" data-fbuy-disc> <span></span></label>
  <p class="fbuy-sum" aria-live="polite"></p>
  <button type="button" class="fbuy-btn" data-fbuy-go>Comprar</button>
 </div>`;
}

function refresh(box) {
  const p = products.get(box.dataset.fbuy); if (!p) return;
  const b = buyers.find(x => x.id === box.querySelector('[data-fbuy-char]')?.value);
  const qtyInput = box.querySelector('[data-fbuy-qty]');
  const qty = Math.max(1, Math.min(50, parseInt(qtyInput?.value, 10) || 1));
  const unit = priceCopper(p.price);
  const disc = box.querySelector('.fbuy-discount'), check = box.querySelector('[data-fbuy-disc]');
  const canDisc = !!(cfg.discount && b && b.discount);
  disc.hidden = !canDisc;
  if (canDisc) disc.querySelector('span').textContent = `Usar su descuento de ${cfg.discount.percent} % en una unidad${cfg.discount.rule ? ' (' + cfg.discount.rule + ')' : ''}. Solo se usa una vez en esta tienda.`;
  if (!canDisc) check.checked = false;
  const off = check.checked ? Math.round(unit * cfg.discount.percent / 100) : 0;
  const total = unit * qty - off;
  const sum = box.querySelector('.fbuy-sum'), btn = box.querySelector('[data-fbuy-go]');
  if (!b) { sum.textContent = 'Elige con qué personaje compras.'; btn.disabled = true; return; }
  const short = b.copper < total;
  sum.innerHTML = `Total <b>${esc(coins(total))}</b>${off ? ` <s>${esc(coins(unit * qty))}</s>` : ''} · ${esc(b.name)} tiene ${esc(coins(b.copper))}${short ? ' <em>· no le alcanza</em>' : ''}`;
  sum.classList.toggle('short', short);
  btn.disabled = short;
  btn.textContent = `Comprar por ${coins(total)}`;
}

async function buy(box) {
  const p = products.get(box.dataset.fbuy); if (!p) return;
  const btn = box.querySelector('[data-fbuy-go]');
  const character = box.querySelector('[data-fbuy-char]')?.value || '';
  const qty = Math.max(1, Math.min(50, parseInt(box.querySelector('[data-fbuy-qty]')?.value, 10) || 1));
  const discount = !!box.querySelector('[data-fbuy-disc]')?.checked;
  btn.disabled = true; btn.textContent = 'Comprando…';
  let j = null;
  try {
    const r = await fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf || '' }, body: JSON.stringify({ shop: cfg.shop, item: p.id, qty, character, discount }) });
    j = await r.json();
  } catch (_) { j = { ok: false, error: 'No pude conectar. Revisa tu conexión e inténtalo otra vez.' }; }
  if (!j || !j.ok) {
    let err = box.querySelector('.fbuy-error');
    if (!err) { err = document.createElement('p'); err.className = 'fbuy-error'; err.setAttribute('role', 'alert'); box.appendChild(err); }
    err.textContent = j?.error || 'No se pudo comprar.';
    refresh(box); return;
  }
  if (Array.isArray(j.buyers)) buyers = j.buyers;
  box.outerHTML = `<div class="fbuy fbuy-done" role="status">✓ <strong>${esc(j.character)}</strong> compró ${j.qty > 1 ? esc(j.qty) + ' × ' : ''}${esc(j.item)} por <b>${esc(j.paid)}</b>${j.discount ? ` (con ${esc(j.discount)} % de descuento)` : ''}. Ya está en su <a href="${esc(j.inventory)}">Inventario</a> y el gasto en la Banca.</div>`;
  document.dispatchEvent(new CustomEvent('favor:compra', { detail: j }));
}

document.addEventListener('change', e => {
  const box = e.target.closest('[data-fbuy]'); if (!box) return;
  if (e.target.matches('[data-fbuy-char]')) remember(e.target.value);
  refresh(box);
});
document.addEventListener('input', e => { const box = e.target.closest('[data-fbuy]'); if (box && e.target.matches('[data-fbuy-qty]')) refresh(box); });
document.addEventListener('click', e => { const go = e.target.closest('[data-fbuy-go]'); if (go) { e.preventDefault(); buy(go.closest('[data-fbuy]')); } });
// Las fichas se dibujan con innerHTML: se completa cada caja nueva al aparecer.
new MutationObserver(() => document.querySelectorAll('[data-fbuy]:not([data-fbuy-ready])').forEach(b => { b.dataset.fbuyReady = '1'; refresh(b); }))
  .observe(document.body, { childList: true, subtree: true });

const css = document.createElement('style');
css.textContent = `.fbuy{margin:16px 0 4px;padding:14px;border:1px solid color-mix(in srgb,currentColor 25%,transparent);border-radius:12px;background:color-mix(in srgb,currentColor 6%,transparent);font-size:.95rem;line-height:1.4}
.fbuy-off,.fbuy-done{opacity:.95}.fbuy-done{border-color:#5a8a3d;background:color-mix(in srgb,#5a8a3d 14%,transparent)}
.fbuy a{color:inherit;text-decoration:underline}
.fbuy-row{display:flex;gap:10px;flex-wrap:wrap}.fbuy-field{display:flex;flex-direction:column;gap:4px;flex:1 1 180px;font-size:.82rem;opacity:.95}
.fbuy-qty{flex:0 0 90px}.fbuy select,.fbuy input[type=number]{font:inherit;font-size:.95rem;padding:8px 10px;border-radius:8px;border:1px solid color-mix(in srgb,currentColor 30%,transparent);background:rgba(0,0,0,.25);color:inherit;width:100%;box-sizing:border-box}
.fbuy select option{color:#222;background:#fff}
.fbuy-discount[hidden]{display:none}.fbuy-discount{display:flex;gap:8px;align-items:flex-start;margin:10px 0 0;font-size:.88rem;cursor:pointer}.fbuy-discount input{margin-top:3px}
.fbuy-sum{margin:10px 0;font-size:.9rem}.fbuy-sum s{opacity:.6}.fbuy-sum.short em{color:#e0705f;font-style:normal;font-weight:700}
.fbuy-btn{font:inherit;font-weight:700;width:100%;padding:11px 14px;border-radius:10px;border:0;cursor:pointer;background:#c49251;color:#1b130b;transition:transform .15s,filter .15s}
.fbuy-btn:hover:not(:disabled){filter:brightness(1.1);transform:translateY(-1px)}.fbuy-btn:disabled{opacity:.5;cursor:not-allowed}
.fbuy-error{margin:8px 0 0;color:#e0705f;font-weight:600}`;
document.head.appendChild(css);

window.FavorCompra = { html };
})();
