/*
 * Relaciones · mapa de vínculos, expedientes, resumen y «Vincular personajes».
 * Los datos llegan en <script id="relData"> desde relaciones.php; los cambios se
 * envían a la misma página con fetch (ajax=1), que responde con la lista nueva.
 */
(() => {
  'use strict';
  const D = JSON.parse(document.getElementById('relData').textContent);
  const V = D.vinculos;
  const ORDER = Object.keys(V);
  const byId = new Map(D.chars.map(c => [c.id, c]));
  let rels = D.rels;
  let focus = byId.has(D.focus) ? D.focus : '';

  const $ = id => document.getElementById(id);
  const esc = v => String(v ?? '').replace(/[&<>"']/g, x => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[x]));
  const norm = v => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const vin = k => V[k] || V.otro;
  const icon = (k, cls = 'rel-ico') => '<svg class="' + cls + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + vin(k).icon + '</svg>';
  const initial = c => esc(((c && c.name && c.name.match(/\p{L}|\p{N}/u)) || ['✦'])[0].toUpperCase());
  const face = c => c && c.image
    ? '<img src="' + esc(c.image) + '" alt="" loading="lazy" decoding="async" onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'rel-initial\',textContent:\'' + initial(c).replace(/'/g, '') + '\'}))">'
    : '<span class="rel-initial">' + initial(c) + '</span>';
  /** Texto libre de la relación, solo si dice algo más que el nombre del vínculo. */
  const caption = r => { const t = (r.tipo || '').trim(); return t && norm(t) !== norm(vin(r.vinculo).label) && norm(t) !== 'unidos' ? t : ''; };
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /** Relaciones de un personaje, con «other» = el otro extremo. */
  function bondsOf(id) {
    return rels.filter(r => r.a === id || r.b === id)
      .map(r => ({ ...r, other: r.a === id ? r.b : r.a }))
      .filter(r => byId.has(r.other))
      .sort((x, y) => ORDER.indexOf(x.vinculo) - ORDER.indexOf(y.vinculo) || byId.get(x.other).name.localeCompare(byId.get(y.other).name, 'es'));
  }
  const bondCount = new Map();
  function countBonds() { bondCount.clear(); rels.forEach(r => { bondCount.set(r.a, (bondCount.get(r.a) || 0) + 1); bondCount.set(r.b, (bondCount.get(r.b) || 0) + 1); }); }

  /* ───────── Avisos ───────── */
  const toastEl = $('relToast');
  let toastTimer = 0;
  function toast(text, isError = false) {
    toastEl.textContent = text; toastEl.hidden = false;
    toastEl.classList.toggle('is-error', isError); toastEl.classList.remove('is-out');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.classList.add('is-out'); setTimeout(() => { toastEl.hidden = true; }, 350); }, isError ? 6000 : 3500);
  }
  if (!toastEl.hidden) toast(toastEl.textContent);
  if (/[?&]ok=/.test(location.search)) history.replaceState(history.state, '', location.pathname + '?character=' + encodeURIComponent(focus));

  /* ───────── Filtros comunes (expedientes y selector) ───────── */
  function filterChars(prefix, extra) {
    const q = norm($(prefix + 'Search').value), cat = $(prefix + 'Category').value, est = $(prefix + 'Estado').value, pl = $(prefix + 'Player').value;
    return D.chars.filter(c => (!q || norm(c.name + ' ' + c.player).includes(q)) && (!cat || c.category === cat)
      && (!est || (c.estado && c.estado.label) === est) && (!pl || c.player === pl) && (!extra || extra(c)));
  }
  function cardHtml(c, active, attr) {
    const n = bondCount.get(c.id) || 0;
    const gone = c.estado && c.estado.label !== 'Activo';
    return '<button type="button" class="rel-card' + (active ? ' is-active' : '') + (gone ? ' is-gone' : '') + '" ' + attr + '="' + esc(c.id) + '"' + (active ? ' aria-current="true"' : '') + '>'
      + '<span class="rel-card-face">' + face(c) + '</span>'
      + '<span class="rel-card-text"><strong>' + esc(c.name) + '</strong><small>' + esc(c.player || 'Sin jugador')
      + (gone ? ' · <i style="color:' + esc(c.estado.color) + '">' + esc(c.estado.icon + ' ' + c.estado.label) + '</i>' : '') + '</small></span>'
      + '<span class="rel-card-meta"><span class="rel-tag' + (c.category === 'NPC' ? ' is-npc' : '') + '" title="' + esc(c.category || 'Personaje') + '">' + esc(c.category === 'Personajes Fuera' ? 'Fuera' : (c.category || 'Personaje')) + '</span>'
      + (n ? '<span class="rel-card-n" title="' + n + ' vínculo' + (n === 1 ? '' : 's') + '">' + n + '</span>' : '') + '</span></button>';
  }

  /* ───────── Columna izquierda: expedientes ───────── */
  const rosterList = $('rosterList');
  function renderRoster() {
    const list = filterChars('roster');
    rosterList.innerHTML = list.length ? list.map(c => cardHtml(c, c.id === focus, 'data-focus')).join('') : '<p class="rel-empty">Ningún expediente coincide.</p>';
    $('rosterCount').innerHTML = list.length === D.chars.length ? '<b>' + list.length + '</b> expedientes' : 'Mostrando <b>' + list.length + '</b> de <b>' + D.chars.length + '</b>';
  }
  ['rosterSearch', 'rosterCategory', 'rosterEstado', 'rosterPlayer'].forEach(id => $(id).addEventListener(id === 'rosterSearch' ? 'input' : 'change', renderRoster));
  rosterList.addEventListener('click', e => {
    const b = e.target.closest('[data-focus]'); if (!b) return;
    setFocus(b.dataset.focus);
    // En el móvil el mapa queda debajo: se lleva la vista hasta él.
    if (matchMedia('(max-width: 900px)').matches) $('mapa').scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
  });
  if (D.myPlayer && [...$('rosterPlayer').options].some(o => o.value === D.myPlayer)) $('rosterPlayer').value = D.myPlayer;

  /* ───────── Centro: el mapa ───────── */
  const map = $('relMap'), nodesEl = $('relNodes'), svg = $('relEdges'), emptyEl = $('relEmpty');
  const nodeEls = new Map();
  const NS = 'http://www.w3.org/2000/svg';
  let ring2 = false, lastFocus = null;
  try { ring2 = localStorage.getItem('rel-ring2') === '1'; } catch (e) { /* sin almacenamiento */ }
  $('ring2Toggle').checked = ring2;
  $('ring2Toggle').addEventListener('change', e => { ring2 = e.target.checked; try { localStorage.setItem('rel-ring2', ring2 ? '1' : '0'); } catch (x) { /* nada */ } drawMap(); });

  function nodeHtml(c, kind, bond) {
    const gone = c.estado && c.estado.label !== 'Activo';
    let h = '<span class="rel-node-face">' + face(c) + '</span><span class="rel-node-name">' + esc(c.name) + '</span>';
    if (kind === 'focus') h += '<span class="rel-node-player">' + esc(c.player || '') + '</span>';
    if (bond) {
      const cap = caption(bond);
      h += '<span class="rel-chip" style="--vc:' + vin(bond.vinculo).color + '">' + icon(bond.vinculo) + esc(vin(bond.vinculo).label) + '</span>';
      if (cap) h += '<span class="rel-node-cap" title="' + esc(cap) + '">' + esc(cap) + '</span>';
    }
    if (gone) h += '<span class="rel-node-estado" style="--ec:' + esc(c.estado.color) + '">' + esc(c.estado.icon + ' ' + c.estado.label) + '</span>';
    if (c.category === 'NPC') h += '<span class="rel-node-npc">NPC</span>';
    return h;
  }

  function drawMap() {
    const W = map.clientWidth;
    if (!W) return;
    const compact = W < 640;
    const me = byId.get(focus);
    const bonds = me ? bondsOf(focus) : [];
    const n = bonds.length;
    // Lazos de sus lazos (segundo círculo opcional): se calculan antes para dejarles sitio.
    const ring1 = new Set(bonds.map(b => b.other));
    const extraEdges = [], farKids = new Map();
    if (me) rels.forEach(r => {
      if (r.a === focus || r.b === focus) return;
      const inA = ring1.has(r.a), inB = ring1.has(r.b);
      if (inA && inB) { extraEdges.push(r); return; }
      if (!ring2 || (!inA && !inB)) return;
      const parent = inA ? r.a : r.b, kid = inA ? r.b : r.a;
      if (!byId.has(kid) || kid === focus) return;
      if (!farKids.has(kid)) farKids.set(kid, parent);
      extraEdges.push(r);
    });
    const hasFar = farKids.size > 0;
    // Más vínculos → el pergamino crece en vez de amontonar retratos.
    let H = compact ? 470 : Math.max(540, Math.min(760, window.innerHeight * 0.68));
    if (compact && n > 6) H = 470 + Math.ceil((n - 6) / 2) * 120;
    if (!compact && n > 10) H = Math.max(H, 760 + (n - 10) * 26);
    if (hasFar) H += compact ? 190 : 200;
    map.style.height = H + 'px';
    map.classList.toggle('is-compact', compact);

    const cx = W / 2, cy = H / 2;
    const d = compact ? 62 : 94, dc = compact ? 104 : 150;
    const padX = compact ? 40 : 80, padY = compact ? 54 : (hasFar ? 96 : 84);
    const rx = Math.max(90, cx - padX), ry = Math.max(90, cy - padY);
    const kx = hasFar ? (compact ? 0.9 : 0.6) : 1, ky = hasFar ? (compact ? 0.5 : 0.6) : 1;
    const pos = new Map();
    if (me) pos.set(focus, { x: cx, y: cy, kind: 'focus' });
    // Primer círculo: sobre una elipse; con muchos, se alternan dos anillos.
    const perim = Math.PI * (rx * kx + ry * ky);
    const twoRings = n * (d + (compact ? 30 : 46)) > perim;
    const offset = n % 2 === 0 && n > 0 ? Math.PI / n : 0;
    bonds.forEach((b, i) => {
      const a = -Math.PI / 2 + offset + (Math.PI * 2 * i) / Math.max(1, n);
      const k = twoRings && i % 2 ? 0.66 : 1;
      pos.set(b.other, { x: cx + Math.cos(a) * rx * kx * k, y: cy + Math.sin(a) * ry * ky * k, kind: 'ring', bond: b, angle: a });
    });
    // Segundo círculo: en el borde del pergamino, alrededor del ángulo de su «padre».
    const perParent = new Map();
    farKids.forEach((parent, kid) => { if (!perParent.has(parent)) perParent.set(parent, []); perParent.get(parent).push(kid); });
    const step = (compact ? 0.34 : 0.22) * (n > 4 ? 4 / n : 1) + 0.08;
    perParent.forEach((kids, parent) => {
      const base = pos.get(parent).angle;
      kids.forEach((kid, j) => {
        const ang = base + (j - (kids.length - 1) / 2) * step;
        const sy = Math.sin(ang), fy = compact ? Math.sign(sy || 1) * Math.max(Math.abs(sy), 0.8) : sy;
        pos.set(kid, { x: cx + Math.cos(ang) * rx, y: cy + fy * ry, kind: 'far', parent, angle: ang });
      });
    });

    // Líneas.
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.innerHTML = '';
    const focusChanged = lastFocus !== focus;
    const curve = (p, q, bend) => {
      const mx = (p.x + q.x) / 2, my = (p.y + q.y) / 2, dx = q.x - p.x, dy = q.y - p.y, len = Math.hypot(dx, dy) || 1;
      const c = { x: mx - (dy / len) * len * bend, y: my + (dx / len) * len * bend };
      return { d: 'M' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ' Q' + c.x.toFixed(1) + ' ' + c.y.toFixed(1) + ' ' + q.x.toFixed(1) + ' ' + q.y.toFixed(1), mid: { x: (p.x + 2 * c.x + q.x) / 4, y: (p.y + 2 * c.y + q.y) / 4 } };
    };
    const addPath = (dAttr, cls, color, id) => {
      const path = document.createElementNS(NS, 'path');
      path.setAttribute('d', dAttr); path.setAttribute('class', cls); path.setAttribute('pathLength', '1');
      path.style.setProperty('--vc', color); if (id) path.dataset.id = id;
      svg.appendChild(path); return path;
    };
    extraEdges.forEach(r => {
      const p = pos.get(r.a), q = pos.get(r.b); if (!p || !q) return;
      addPath(curve(p, q, 0.08).d, 'rel-edge is-far' + (focusChanged && !reduced ? ' is-drawing' : ''), vin(r.vinculo).color);
    });
    bonds.forEach((b, i) => {
      const p = pos.get(focus), q = pos.get(b.other);
      const c = curve(p, q, (i % 2 ? 1 : -1) * 0.07);
      const color = vin(b.vinculo).color;
      addPath(c.d, 'rel-edge-shadow', color);
      addPath(c.d, 'rel-edge' + (focusChanged && !reduced ? ' is-drawing' : ''), color, b.other);
      const g = document.createElementNS(NS, 'rect');
      const s = compact ? 9 : 12;
      g.setAttribute('x', (c.mid.x - s / 2).toFixed(1)); g.setAttribute('y', (c.mid.y - s / 2).toFixed(1));
      g.setAttribute('width', s); g.setAttribute('height', s);
      g.setAttribute('transform', 'rotate(45 ' + c.mid.x.toFixed(1) + ' ' + c.mid.y.toFixed(1) + ')');
      g.setAttribute('class', 'rel-edge-gem'); g.style.setProperty('--vc', color);
      svg.appendChild(g);
    });

    // Retratos (se reutilizan para que se deslicen al cambiar de centro).
    const seen = new Set();
    pos.forEach((p, id) => {
      const c = byId.get(id); seen.add(id);
      let el = nodeEls.get(id);
      const isNew = !el;
      if (isNew) {
        el = document.createElement('a'); el.dataset.id = id; nodeEls.set(id, el); nodesEl.appendChild(el);
        el.style.left = cx + 'px'; el.style.top = cy + 'px'; el.classList.add('is-entering');
      }
      el.className = 'rel-node is-' + p.kind + (c.estado && c.estado.label !== 'Activo' ? ' is-gone' : '') + (isNew ? ' is-entering' : '');
      el.href = '?character=' + encodeURIComponent(id);
      el.style.setProperty('--d', (p.kind === 'focus' ? dc : p.kind === 'far' ? (compact ? 38 : 54) : d) + 'px');
      if (p.bond) el.style.setProperty('--vc', vin(p.bond.vinculo).color); else el.style.removeProperty('--vc');
      el.innerHTML = nodeHtml(c, p.kind, p.bond);
      const label = c.name + (p.bond ? ', ' + vin(p.bond.vinculo).label + (caption(p.bond) ? ' (' + caption(p.bond) + ')' : '') : p.kind === 'focus' ? ', personaje central' : '');
      el.setAttribute('aria-label', label + '. Centrar la red aquí.');
      if (p.kind === 'focus') el.setAttribute('aria-current', 'true'); else el.removeAttribute('aria-current');
      const place = () => { el.style.left = p.x + 'px'; el.style.top = p.y + 'px'; el.classList.remove('is-entering'); };
      if (isNew && !reduced) requestAnimationFrame(() => requestAnimationFrame(place)); else place();
    });
    nodeEls.forEach((el, id) => {
      if (seen.has(id)) return;
      nodeEls.delete(id); el.classList.add('is-leaving');
      setTimeout(() => el.remove(), reduced ? 0 : 320);
    });

    // Mensaje de mapa vacío.
    if (!me) { emptyEl.hidden = false; emptyEl.innerHTML = '<p>Todavía no hay expedientes en el archivo.</p>'; }
    else if (!n) {
      emptyEl.hidden = false;
      emptyEl.innerHTML = '<p><strong>' + esc(me.name) + '</strong> aún no tiene vínculos registrados.</p>'
        + (D.canLink ? '<button type="button" class="rel-btn is-gold is-small" data-goforge>Crear el primero</button>' : '');
    } else emptyEl.hidden = true;
    lastFocus = focus;
  }
  nodesEl.addEventListener('click', e => {
    const a = e.target.closest('.rel-node'); if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault(); if (a.dataset.id !== focus) setFocus(a.dataset.id);
  });
  nodesEl.addEventListener('pointerover', e => { const a = e.target.closest('.rel-node'); svg.querySelectorAll('.is-hot').forEach(p => p.classList.remove('is-hot')); if (a) svg.querySelector('[data-id="' + CSS.escape(a.dataset.id) + '"]')?.classList.add('is-hot'); });
  nodesEl.addEventListener('pointerleave', () => svg.querySelectorAll('.is-hot').forEach(p => p.classList.remove('is-hot')));
  emptyEl.addEventListener('click', e => { if (e.target.closest('[data-goforge]')) goForge(); });

  /* ───────── Derecha: resumen de vínculos ───────── */
  const meEditable = () => !!(byId.get(focus) || {}).edit;
  const actorFor = r => meEditable() ? focus : ((byId.get(r.other) || {}).edit ? r.other : '');
  function renderSummary() {
    const me = byId.get(focus);
    const bonds = me ? bondsOf(focus) : [];
    const name = me ? me.name : '';
    $('boardTitle').textContent = name || 'Relaciones del gremio';
    $('boardSub').textContent = me ? 'Red de relaciones · ' + bonds.length + ' vínculo' + (bonds.length === 1 ? '' : 's') + (me.player ? ' · ' + me.player : '') : 'Archivo vacío';
    $('plaqueFace').innerHTML = me ? face(me) : '';
    $('summaryTitle').textContent = name ? 'Vínculos de ' + name : 'Vínculos';
    $('summaryCount').textContent = bonds.length ? bonds.length + ' lazo' + (bonds.length === 1 ? '' : 's') + ' registrado' + (bonds.length === 1 ? '' : 's') : 'Sin lazos todavía';
    $('relTotal').textContent = rels.length;
    const counts = {}; bonds.forEach(b => { counts[b.vinculo] = (counts[b.vinculo] || 0) + 1; });
    $('typeCounts').innerHTML = ORDER.filter(k => counts[k]).map(k => '<span class="rel-chip is-count" style="--vc:' + vin(k).color + '">' + icon(k) + esc(vin(k).label) + ' <b>' + counts[k] + '</b></span>').join('');
    $('bondList').innerHTML = bonds.length ? bonds.map(b => {
      const o = byId.get(b.other), cap = caption(b), actor = actorFor(b);
      return '<li class="rel-bond" style="--vc:' + vin(b.vinculo).color + '">'
        + '<button type="button" class="rel-bond-who" data-focus="' + esc(o.id) + '" title="Centrar el mapa en ' + esc(o.name) + '"><span class="rel-card-face">' + face(o) + '</span><span><strong>' + esc(o.name) + '</strong><small>' + esc(o.player || 'Sin jugador') + '</small></span></button>'
        + '<div class="rel-bond-body"><span class="rel-chip" style="--vc:' + vin(b.vinculo).color + '">' + icon(b.vinculo) + esc(vin(b.vinculo).label) + '</span>'
        + (cap ? '<span class="rel-bond-cap">' + esc(cap) + '</span>' : '')
        + (b.nota.trim() ? '<p class="rel-bond-note">' + esc(b.nota) + '</p>' : '') + '</div>'
        + (actor ? '<div class="rel-bond-actions"><button type="button" class="rel-btn is-ghost is-small" data-edit="' + b.id + '">Editar</button><button type="button" class="rel-btn is-danger is-small" data-del="' + b.id + '">Quitar</button></div>' : '')
        + '</li>';
    }).join('') : '<li class="rel-empty">' + (me ? 'Ningún vínculo registrado.' + (D.canLink ? ' Usa «Vincular personajes» para crear el primero.' : '') : '') + '</li>';
  }
  $('bondList').addEventListener('click', e => {
    const go = e.target.closest('[data-focus]'), ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    const note = e.target.closest('.rel-bond-note');
    if (note) { note.classList.toggle('is-open'); return; }
    if (go) { setFocus(go.dataset.focus); return; }
    const find = id => bondsOf(focus).find(b => String(b.id) === String(id));
    if (ed) { const b = find(ed.dataset.edit); if (b) startEdit(b); }
    if (del) { const b = find(del.dataset.del); if (b) removeBond(b); }
  });

  /* ───────── Abajo: vincular personajes ───────── */
  const form = $('relForm');
  const forge = { a: '', b: '', editing: null };
  const editableChars = D.chars.filter(c => c.edit);
  function chosenVinculo() { const r = form && form.querySelector('input[name="vinculo"]:checked'); return r ? r.value : 'amistad'; }
  function pairOf(a, b) { return rels.find(r => (r.a === a && r.b === b) || (r.a === b && r.b === a)); }
  function resetForge() {
    if (!form) return;
    forge.editing = null;
    if (meEditable()) { forge.a = focus; forge.b = ''; }
    else { forge.a = editableChars[0] ? editableChars[0].id : ''; forge.b = focus && focus !== forge.a ? focus : ''; }
    $('typeInput').value = ''; $('noteInput').value = '';
    renderForge();
  }
  function paintSlot(el, c, label) {
    el.querySelector('.rel-slot-face').innerHTML = c ? face(c) : '<span class="rel-initial is-empty">?</span>';
    el.querySelector('small').textContent = label;
    el.querySelector('strong').textContent = c ? c.name : 'Elegir personaje';
    el.classList.toggle('is-empty', !c);
  }
  function renderForge() {
    if (!form) return;
    const A = byId.get(forge.a), B = byId.get(forge.b), k = chosenVinculo(), v = vin(k);
    const editing = forge.editing !== null;
    paintSlot($('slotA'), A, D.isAdmin ? 'Personaje A' : 'Personaje A · tuyo');
    paintSlot($('slotB'), B, 'Personaje B');
    $('slotA').disabled = editing || (!D.isAdmin && editableChars.length < 2 && !!A);
    $('slotB').disabled = editing;
    $('slotAInput').value = forge.a; $('slotBInput').value = forge.b;
    $('returnInput').value = focus;
    form.style.setProperty('--vc', v.color);
    const prev = $('bondPreview');
    prev.classList.toggle('is-ready', !!(A && B));
    prev.querySelector('.rel-bond-badge').innerHTML = icon(k) + '<span>' + esc(v.label) + '</span>';
    const existing = A && B ? pairOf(A.id, B.id) : null;
    $('forgeTitle').textContent = editing ? 'Editar vínculo' : 'Vincular personajes';
    $('forgeLead').textContent = editing ? 'Cambia el tipo de lazo, la descripción o la nota.' : 'Une a dos miembros del gremio con un lazo.';
    const msg = $('forgePreview');
    if (!A || !B) msg.innerHTML = 'Elige a los dos personajes para unirlos.';
    else if (existing && !editing) msg.innerHTML = '<b>' + esc(A.name) + '</b> y <b>' + esc(B.name) + '</b> ya tienen un lazo (' + esc(vin(existing.vinculo).label) + '): al guardar se actualizará.';
    else msg.innerHTML = '<b>' + esc(A.name) + '</b> y <b>' + esc(B.name) + '</b> quedarán unidos por <b style="color:' + v.color + '">' + esc(v.label) + '</b>.';
    $('saveBond').disabled = !(A && B);
    $('saveBond').querySelector('span').textContent = editing || existing ? 'Guardar cambios' : 'Añadir relación';
    $('cancelEdit').hidden = !editing;
  }
  function startEdit(b) {
    if (!form) return;
    const actor = actorFor(b); if (!actor) return;
    forge.editing = b.id; forge.a = actor; forge.b = actor === b.a ? b.b : b.a;
    const radio = form.querySelector('input[name="vinculo"][value="' + b.vinculo + '"]'); if (radio) radio.checked = true;
    $('typeInput').value = caption(b); $('noteInput').value = b.nota || '';
    renderForge(); goForge();
  }
  function goForge() {
    const sec = $('vincular'); sec.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
    sec.classList.remove('is-called'); void sec.offsetWidth; sec.classList.add('is-called');
    const target = form && (forge.b ? form.querySelector('input[name="vinculo"]:checked') : $('slotB'));
    if (target) setTimeout(() => target.focus({ preventScroll: true }), reduced ? 0 : 400);
  }
  async function send(fd) {
    fd.set('ajax', '1');
    const res = await fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let json = null; try { json = await res.json(); } catch (e) { /* respuesta no JSON */ }
    if (!json) throw new Error('No se pudo guardar. Recarga la página e inténtalo otra vez.');
    if (!json.ok) throw new Error(json.error || 'No se pudo guardar.');
    rels = json.rels; countBonds();
    return json;
  }
  if (form) {
    form.addEventListener('change', e => { if (e.target.name === 'vinculo') renderForge(); });
    $('cancelEdit').addEventListener('click', resetForge);
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (!forge.a || !forge.b) { toast('Elige a los dos personajes para crear el vínculo.', true); return; }
      const btn = $('saveBond'); btn.disabled = true; btn.classList.add('is-busy');
      const a = forge.a, b = forge.b;
      try {
        const json = await send(new FormData(form));
        toast(json.message);
        if (focus !== a && focus !== b) focus = a;
        resetForge(); renderAll(true);
        const p = svg.querySelector('[data-id="' + CSS.escape(focus === a ? b : a) + '"]'); if (p) p.classList.add('is-new');
      } catch (err) { toast(err.message, true); }
      finally { btn.classList.remove('is-busy'); renderForge(); }
    });
  }
  async function removeBond(b) {
    const actor = actorFor(b); if (!actor || !form) return;
    const o = byId.get(b.other), me = byId.get(focus);
    if (!confirm('¿Quitar el vínculo entre ' + me.name + ' y ' + o.name + '?')) return;
    const fd = new FormData();
    fd.set('csrf', form.querySelector('input[name="csrf"]').value); fd.set('action', 'delete'); fd.set('character', actor); fd.set('id', String(b.id));
    try { const json = await send(fd); toast(json.message); if (forge.editing === b.id) resetForge(); renderAll(true); }
    catch (err) { toast(err.message, true); }
  }

  /* ───────── Selector de personaje (ventana) ───────── */
  const picker = $('relPicker');
  let pickSlot = 'b';
  function renderPicker() {
    const other = pickSlot === 'a' ? forge.b : forge.a, cur = pickSlot === 'a' ? forge.a : forge.b;
    const list = filterChars('picker', c => c.id !== other && (pickSlot === 'b' || c.edit));
    $('pickerList').innerHTML = list.length ? list.map(c => cardHtml(c, c.id === cur, 'data-pick')).join('') : '<p class="rel-empty">Ningún personaje coincide.</p>';
    $('pickerCount').innerHTML = '<b>' + list.length + '</b> resultado' + (list.length === 1 ? '' : 's');
  }
  function openPicker(slot) {
    pickSlot = slot;
    $('pickerTitle').textContent = slot === 'a' ? (D.isAdmin ? 'Elegir Personaje A' : 'Elegir uno de tus personajes') : 'Elegir Personaje B';
    ['pickerSearch', 'pickerCategory', 'pickerEstado', 'pickerPlayer'].forEach(id => { $(id).value = ''; });
    renderPicker();
    if (picker.showModal) picker.showModal(); else picker.setAttribute('open', '');
    if (!matchMedia('(hover: none)').matches) setTimeout(() => $('pickerSearch').focus(), 30);
  }
  if (form) {
    $('slotA').addEventListener('click', () => openPicker('a'));
    $('slotB').addEventListener('click', () => openPicker('b'));
    ['pickerSearch', 'pickerCategory', 'pickerEstado', 'pickerPlayer'].forEach(id => $(id).addEventListener(id === 'pickerSearch' ? 'input' : 'change', renderPicker));
    $('pickerList').addEventListener('click', e => {
      const b = e.target.closest('[data-pick]'); if (!b) return;
      forge[pickSlot] = b.dataset.pick; picker.close ? picker.close() : picker.removeAttribute('open');
      renderForge(); (pickSlot === 'a' ? $('slotA') : $('slotB')).focus();
    });
    picker.addEventListener('click', e => { if (e.target === picker) picker.close(); });
  }

  /* ───────── Cambiar de personaje central ───────── */
  function renderAll(keepForge) {
    renderRoster(); drawMap(); renderSummary();
    if (!keepForge && forge.editing === null) resetForge(); else renderForge();
    const active = rosterList.querySelector('.is-active');
    if (active && !keepForge) active.scrollIntoView({ block: 'nearest' });
  }
  function setFocus(id, push = true) {
    if (!byId.has(id)) return;
    focus = id;
    if (push) history.pushState({ focus: id }, '', '?character=' + encodeURIComponent(id));
    document.title = byId.get(id).name + ' · Relaciones · Archivos del Gremio';
    renderAll(false);
  }
  addEventListener('popstate', e => {
    const id = (e.state && e.state.focus) || new URLSearchParams(location.search).get('character') || D.focus;
    if (byId.has(id) && id !== focus) setFocus(id, false);
  });
  history.replaceState({ focus }, '', location.href.replace(/#.*$/, '') + location.hash);

  let resizeTimer = 0;
  new ResizeObserver(() => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => { lastFocus = focus; drawMap(); }, 80); }).observe(map);
  countBonds();
  renderAll(false);
})();
