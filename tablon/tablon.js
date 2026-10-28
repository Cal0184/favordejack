/* Tablón de misiones · Favor de Jack */
(() => {
    'use strict';

    const S = JSON.parse(document.getElementById('tablon-data').textContent);
    const $ = (sel, root = document) => root.querySelector(sel);
    const board = $('#tablon-surface');
    const papers = Object.fromEntries(S.papers.map(p => [p.id, p]));
    const sizes = Object.fromEntries(S.sizes.map(s => [s.id, s]));
    const fonts = Object.fromEntries(S.fonts.map(f => [f.id, f]));
    const inks = Object.fromEntries(S.inks.map(i => [i.id, i]));
    const STAMPS = {Completada: ['COMPLETADA', 'ok'], Fallida: ['NO COMPLETADA', 'bad']};
    let missions = S.missions;
    let animate = {};   // id → 'stamp' | 'break' | 'pin', para la animación del siguiente dibujo

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const byId = id => missions.find(m => m.id === id);

    async function api(action, payload = {}) {
        const res = await fetch(S.api, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({...payload, action, csrf: S.csrf}),
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* respuesta vacía */ }
        if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'El servidor no respondió. Inténtalo otra vez.');
        return data;
    }

    async function reload() {
        const res = await fetch(S.data, {credentials: 'same-origin', cache: 'no-store'});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'No pude leer el tablón.');
        missions = data.missions;
        drawBoard();
    }

    function toast(text) {
        const el = $('#tablon-toast');
        if (!el) return;
        el.textContent = text;
        el.hidden = false;
        el.classList.remove('show');
        void el.offsetWidth;
        el.classList.add('show');
        clearTimeout(toast.t);
        toast.t = setTimeout(() => { el.hidden = true; }, 3800);
    }

    /* ---------- Papel ---------- */

    // Un papel con su texto. opts.width en % del contenedor (tablón) o null (ocupa su caja).
    function paperHTML(m, {onBoard = false} = {}) {
        const p = papers[m.paper] || S.papers[0];
        const broken = m.state === 'Cancelada';
        const [t, r, b, l] = broken ? p.brokenBox : p.box;
        const ratio = broken ? p.brokenRatio : p.ratio;
        const font = fonts[m.font] || S.fonts[0];
        const ink = inks[m.ink] || S.inks[0];
        const stamp = STAMPS[m.state];
        const party = m.party || [];
        const shown = party.slice(0, 4);
        const style = onBoard
            ? `--x:${m.x};--y:${m.y};--w:${((sizes[m.size] || sizes[2]).width * (p.scale || 1)).toFixed(2)};--rot:${m.rot}deg;--ratio:${ratio};z-index:${10 + m.layer}`
            : `--ratio:${ratio}`;
        return `<div class="paper${broken ? ' is-broken' : ''} state-${esc(m.state).replace(/\s+/g, '-').toLowerCase()}${onBoard ? ' on-board' : ''}" data-id="${m.id}" style="${style}"${onBoard ? ` role="button" tabindex="0" aria-label="${esc(m.name)} · ${esc(m.state)}"` : ''}>
            <img class="paper-img" src="${esc(broken ? p.broken : p.img)}" alt="" draggable="false">
            <div class="paper-ink">
                <div class="paper-text" style="top:${t}%;right:${r}%;bottom:${b}%;left:${l}%;font-family:${esc(font.family)};color:${esc(ink.color)}">
                    <div class="paper-fit">
                        <h3>${esc(m.name)}</h3>
                        <p>${esc(m.text)}</p>
                        ${m.reward ? `<p class="paper-reward">Paga: ${esc(m.reward)}</p>` : ''}
                    </div>
                </div>
            </div>
            ${shown.length ? `<div class="paper-party">${shown.map(c => `<span class="paper-face" title="${esc(c.name)}">${c.portrait ? `<img src="${esc(c.portrait)}" alt="" draggable="false" loading="lazy">` : `<b>${esc((c.short || c.name).charAt(0))}</b>`}</span>`).join('')}${party.length > shown.length ? `<span class="paper-face more">+${party.length - shown.length}</span>` : ''}</div>` : ''}
            ${stamp ? `<span class="paper-stamp ${stamp[1]}">${stamp[0]}</span>` : ''}
            ${m.state === 'En curso' ? '<span class="paper-ribbon">En curso</span>' : ''}
        </div>`;
    }

    // Achica el texto hasta que cabe en el papel. Se busca directo en cqw (% del ancho
    // del papel), así no hay que recalcularlo cuando cambia el tamaño de la pantalla.
    function fit(paperEl) {
        const box = paperEl.querySelector('.paper-text');
        const inner = paperEl.querySelector('.paper-fit');
        if (!box || !inner || paperEl.clientWidth < 20) return null;
        let lo = 2, hi = 16, best = lo;
        for (let i = 0; i < 14; i++) {
            const mid = (lo + hi) / 2;
            box.style.fontSize = mid + 'cqw';
            if (inner.scrollHeight <= box.clientHeight + 1 && inner.scrollWidth <= box.clientWidth + 1) { best = mid; lo = mid; } else { hi = mid; }
        }
        // Un poco de margen: al girar o redondear píxeles una línea puede saltar.
        best *= 0.97;
        box.style.fontSize = best.toFixed(3) + 'cqw';
        return best / 100;
    }

    function fitAll(root = document) {
        root.querySelectorAll('.paper').forEach(fit);
    }

    /* ---------- Tablón ---------- */

    function drawBoard() {
        board.querySelectorAll('.paper').forEach(el => el.remove());
        board.insertAdjacentHTML('beforeend', missions.map(m => paperHTML(m, {onBoard: true})).join(''));
        board.querySelectorAll('.paper').forEach(el => {
            fit(el);
            const a = animate[el.dataset.id];
            if (a) el.classList.add('anim-' + a);
        });
        animate = {};
        $('#tablon-empty').hidden = missions.length > 0;
        const count = s => missions.filter(m => m.state === s).length;
        const parts = [['Publicada', 'publicada', 'publicadas'], ['En curso', 'en curso', 'en curso'], ['Completada', 'completada', 'completadas'], ['Fallida', 'fallida', 'fallidas']]
            .map(([s, one, many]) => [count(s), one, many]).filter(([n]) => n > 0).map(([n, one, many]) => `${n} ${n === 1 ? one : many}`);
        $('#tablon-counts').textContent = parts.join(' · ');
    }

    // Arrastrar papeles (solo DM). Un toque sin moverse abre la misión.
    function setupBoardPointer() {
        let drag = null;
        board.addEventListener('pointerdown', e => {
            const el = e.target.closest('.paper.on-board');
            if (!el || e.button > 0) return;
            const rect = board.getBoundingClientRect();
            drag = {el, id: +el.dataset.id, sx: e.clientX, sy: e.clientY, rect, moved: false, pointer: e.pointerId};
            if (S.admin) el.setPointerCapture(e.pointerId);
        });
        board.addEventListener('pointermove', e => {
            if (!drag || !S.admin || e.pointerId !== drag.pointer) return;
            const dx = e.clientX - drag.sx, dy = e.clientY - drag.sy;
            if (!drag.moved && Math.hypot(dx, dy) < 6) return;
            if (!drag.moved) {
                drag.moved = true;
                drag.el.classList.add('is-dragging');
                drag.el.style.zIndex = 9999;
            }
            const m = byId(drag.id);
            drag.x = Math.max(2, Math.min(98, m.x + dx / drag.rect.width * 100));
            drag.y = Math.max(3, Math.min(97, m.y + dy / drag.rect.height * 100));
            drag.el.style.setProperty('--x', drag.x);
            drag.el.style.setProperty('--y', drag.y);
        });
        const end = async e => {
            if (!drag || e.pointerId !== drag.pointer) return;
            const d = drag;
            drag = null;
            if (!d.moved) {
                if (e.type === 'pointerup') openView(d.id);
                return;
            }
            d.el.classList.remove('is-dragging');
            const m = byId(d.id);
            m.x = +d.x.toFixed(2);
            m.y = +d.y.toFixed(2);
            try {
                const r = await api('move', {id: d.id, x: m.x, y: m.y});
                m.layer = r.layer;
                d.el.style.zIndex = 10 + r.layer;
            } catch (err) {
                toast(err.message);
                reload().catch(() => {});
            }
        };
        board.addEventListener('pointerup', end);
        board.addEventListener('pointercancel', end);
        board.addEventListener('keydown', e => {
            const el = e.target.closest('.paper.on-board');
            if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openView(+el.dataset.id); }
        });
    }

    /* ---------- Diálogos ---------- */

    function openDialog(dlg) {
        if (!dlg.open) dlg.showModal();
        requestAnimationFrame(() => fitAll(dlg));
    }
    document.addEventListener('click', e => {
        const closer = e.target.closest('[data-close]');
        if (closer) closer.closest('dialog').close();
        else if (e.target.tagName === 'DIALOG') e.target.close();   // clic fuera del cuadro
    });

    function showError(el, msg) {
        el.textContent = msg;
        el.hidden = !msg;
    }

    const fmtDate = v => {
        if (!v) return '';
        const d = new Date(String(v).replace(' ', 'T'));
        return isNaN(d) ? v : d.toLocaleDateString('es-CL', {day: 'numeric', month: 'long', year: 'numeric'});
    };

    function openView(id) {
        const m = byId(id);
        if (!m) return;
        const dlg = $('#dlg-view');
        $('#view-paper').innerHTML = paperHTML(m);
        $('#view-state').innerHTML = `<span class="chip chip-${esc(m.state).replace(/\s+/g, '-').toLowerCase()}">${m.state === 'Cancelada' ? 'Eliminada' : esc(m.state)}</span>${m.type ? ` <span class="chip">${esc(m.type)}</span>` : ''}`;
        $('#view-title').textContent = m.name;
        $('#view-reward').innerHTML = m.reward ? `<b>Recompensa:</b> ${esc(m.reward)}` : '';
        $('#view-details').innerHTML = `<p class="view-text">${esc(m.text)}</p>${m.details ? `<p class="view-more">${esc(m.details)}</p>` : ''}`;
        $('#view-party').innerHTML = m.party.length
            ? `<h3>Grupo</h3><ul>${m.party.map(c => `<li>${c.portrait ? `<img src="${esc(c.portrait)}" alt="">` : `<span class="face-fallback">${esc((c.short || c.name).charAt(0))}</span>`}<span>${esc(c.name)}${c.player ? `<small>${esc(c.player)}</small>` : ''}</span></li>`).join('')}</ul>`
            : '';
        const reasonLabel = m.state === 'Cancelada' ? 'Motivo' : 'Nota del DM';
        $('#view-reason').innerHTML = m.reason ? `<b>${reasonLabel}:</b> ${esc(m.reason)}` : '';
        $('#view-meta').textContent = [m.createdBy ? 'Clavada por ' + m.createdBy : '', fmtDate(m.created)].filter(Boolean).join(' · ');
        $('#view-actions').innerHTML = S.admin ? actionsFor(m) : '';
        openDialog(dlg);
    }

    function actionsFor(m) {
        const b = (act, label, cls = '') => `<button type="button" class="tablon-btn ${cls}" data-act="${act}" data-id="${m.id}">${label}</button>`;
        const list = {
            'Publicada': [b('start', '⚔ Iniciar misión', 'tablon-btn-main'), b('edit', 'Editar'), b('break', 'Eliminar', 'danger')],
            'En curso': [b('finish-ok', '✔ Completada', 'tablon-btn-main'), b('finish-bad', '✘ Fallida'), b('start', 'Cambiar grupo'), b('edit', 'Editar'), b('break', 'Eliminar', 'danger')],
            'Completada': [b('reopen', 'Volver a «En curso»'), b('edit', 'Editar'), b('unpin', 'Descolgar del tablón')],
            'Fallida': [b('reopen', 'Volver a «En curso»'), b('edit', 'Editar'), b('unpin', 'Descolgar del tablón')],
            'Cancelada': [b('restore', 'Arreglar el papel'), b('unpin', 'Descolgar del tablón')],
        };
        return (list[m.state] || [b('edit', 'Editar'), b('unpin', 'Descolgar del tablón')]).join('');
    }

    /* ---------- Acciones del DM ---------- */

    function setupAdmin() {
        $('#tablon-new').addEventListener('click', () => openEdit(null));

        $('#view-actions').addEventListener('click', e => {
            const btn = e.target.closest('[data-act]');
            if (!btn) return;
            const m = byId(+btn.dataset.id);
            if (!m) return;
            const act = btn.dataset.act;
            if (act === 'edit') { $('#dlg-view').close(); openEdit(m); }
            if (act === 'start') { $('#dlg-view').close(); openStart(m); }
            if (act === 'break') ask({
                title: 'Eliminar misión', text: `El papel de «${m.name}» quedará roto en el tablón. Los jugadores verán el motivo.`,
                label: 'Motivo (opcional)', button: 'Romper el papel', danger: true,
                run: note => api('break', {id: m.id, reason: note}), anim: [m.id, 'break'],
            });
            if (act === 'finish-ok' || act === 'finish-bad') {
                const ok = act === 'finish-ok';
                ask({
                    title: ok ? 'Misión completada' : 'Misión fallida', text: `Se pondrá el sello ${ok ? '«COMPLETADA»' : '«NO COMPLETADA»'} en «${m.name}».`,
                    label: 'Nota (opcional)', button: ok ? 'Poner sello' : 'Marcar como fallida',
                    run: note => api('finish', {id: m.id, state: ok ? 'Completada' : 'Fallida', note}), anim: [m.id, 'stamp'],
                });
            }
            if (act === 'reopen') run(() => api('finish', {id: m.id, state: 'En curso', note: ''}));
            if (act === 'restore') run(() => api('restore', {id: m.id}), [m.id, 'pin']);
            if (act === 'unpin') ask({
                title: 'Descolgar del tablón', text: `«${m.name}» deja de verse en el tablón, pero sigue guardada en el Registro de Misiones de Archivos del Gremio.`,
                button: 'Descolgar', run: () => api('unpin', {id: m.id}),
            });
        });

        setupEdit();
        setupStart();

        $('#ask-form').addEventListener('submit', async e => {
            e.preventDefault();
            const a = ask.current;
            if (!a) return;
            const btn = $('#ask-submit');
            btn.disabled = true;
            try {
                const r = await a.run($('#ask-form').note.value.trim());
                $('#dlg-ask').close();
                $('#dlg-view').close();
                if (a.anim) animate[a.anim[0]] = a.anim[1];
                await reload();
                toast(r.message || 'Listo.');
            } catch (err) {
                showError($('#ask-error'), err.message);
            } finally {
                btn.disabled = false;
            }
        });
    }

    function ask(a) {
        ask.current = a;
        $('#ask-title').textContent = a.title;
        $('#ask-text').textContent = a.text;
        $('#ask-field').hidden = !a.label;
        $('#ask-label').textContent = a.label || '';
        $('#ask-form').note.value = '';
        const btn = $('#ask-submit');
        btn.textContent = a.button;
        btn.classList.toggle('danger', !!a.danger);
        showError($('#ask-error'), '');
        $('#dlg-ask').showModal();
    }

    async function run(fn, anim) {
        try {
            const r = await fn();
            $('#dlg-view').close();
            if (anim) animate[anim[0]] = anim[1];
            await reload();
            toast(r.message || 'Listo.');
        } catch (err) {
            toast(err.message);
        }
    }

    /* Ingresar / editar */

    let draft = null;

    function setupEdit() {
        const form = $('#edit-form');
        const seg = (el, items, key, render) => {
            el.innerHTML = items.map(it => `<button type="button" data-${key}="${it.id}" aria-pressed="false">${render(it)}</button>`).join('');
            el.addEventListener('click', e => {
                const btn = e.target.closest(`[data-${key}]`);
                if (!btn) return;
                const v = btn.dataset[key];
                draft[key] = key === 'size' || key === 'paper' ? +v : v;
                syncEdit();
            });
        };
        seg($('#paper-choices'), S.papers, 'paper', p => `<img src="${esc(p.img)}" alt=""><span>${esc(p.name)}</span>`);
        seg($('#size-choices'), S.sizes, 'size', s => esc(s.name));
        seg($('#font-choices'), S.fonts, 'font', f => `<span style="font-family:${esc(f.family)}">${esc(f.name)}</span>`);
        seg($('#ink-choices'), S.inks, 'ink', i => `<i style="background:${esc(i.color)}"></i>${esc(i.name)}`);

        let raf = 0;
        form.addEventListener('input', () => {
            for (const k of ['name', 'text', 'reward', 'type', 'details']) draft[k] = form[k].value;
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(drawPreview);
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const err = $('#edit-error');
            if (!draft.name.trim() || !draft.text.trim()) return showError(err, 'Falta el título o el texto del papel.');
            const btn = $('#edit-submit');
            btn.disabled = true;
            try {
                const r = await api('save', {id: draft.id, name: draft.name, text: draft.text, reward: draft.reward, type: draft.type, details: draft.details, paper: draft.paper, size: draft.size, font: draft.font, ink: draft.ink});
                $('#dlg-edit').close();
                animate[r.id] = draft.id ? '' : 'pin';
                await reload();
                toast(r.message);
            } catch (ex) {
                showError(err, ex.message);
            } finally {
                btn.disabled = false;
            }
        });
    }

    function openEdit(m) {
        draft = m ? {...m} : {id: 0, name: '', text: '', reward: '', type: '', details: '', paper: 1, size: 2, font: 'pluma', ink: 'negra', state: 'Publicada', party: []};
        const form = $('#edit-form');
        for (const k of ['name', 'text', 'reward', 'type', 'details']) form[k].value = draft[k] || '';
        $('#edit-title').textContent = m ? 'Editar misión' : 'Ingresar misión';
        $('#edit-submit').textContent = m ? 'Guardar cambios' : 'Clavar en el tablón';
        showError($('#edit-error'), '');
        syncEdit();
        openDialog($('#dlg-edit'));
        drawPreview();
        form.name.focus();
    }

    function syncEdit() {
        for (const key of ['paper', 'size', 'font', 'ink']) {
            document.querySelectorAll(`[data-${key}]`).forEach(b => b.setAttribute('aria-pressed', String(b.dataset[key] === String(draft[key]))));
        }
        drawPreview();
    }

    function drawPreview() {
        const stage = $('#edit-preview');
        const preview = {...draft, name: draft.name || 'Título de la misión', text: draft.text || 'Aquí va el texto del papel…', state: draft.id ? draft.state : 'Publicada'};
        stage.innerHTML = paperHTML(preview);
        // La vista previa respeta el tamaño elegido: más chico = menos espacio para el texto.
        const el = stage.querySelector('.paper');
        el.style.width = Math.min(100, sizes[draft.size].width * (papers[draft.paper].scale || 1) / sizes[3].width * 100) + '%';
        const ratio = fit(el);
        $('#edit-warn').hidden = ratio === null || ratio > 0.045 || !draft.text;
    }

    /* Iniciar misión: arrastrar personajes */

    let startMission = null;
    let party = [];
    const chars = Object.fromEntries(S.players.flatMap(p => p.characters.map(c => [c.id, {...c, player: p.name}])));

    function setupStart() {
        const playersEl = $('#start-players');
        playersEl.innerHTML = S.players.map(p => `<button type="button" role="tab" class="start-player" data-player="${esc(p.id)}" aria-selected="false" style="--bg:${esc(p.bg || '#22402F')}">
            <span class="start-player-face">${p.icon ? `<img src="${esc(p.icon)}" alt="">` : esc(p.emoji || p.name.charAt(0))}</span><span>${esc(p.name)}</span></button>`).join('');
        playersEl.addEventListener('click', e => {
            const btn = e.target.closest('[data-player]');
            if (btn) showPlayer(btn.dataset.player);
        });

        // Arrastre con puntero (sirve con ratón y con el dedo). Un toque sin arrastrar
        // también agrega o quita al personaje.
        const dlg = $('#dlg-start');
        const drop = $('#start-drop');
        let drag = null;
        dlg.addEventListener('pointerdown', e => {
            const card = e.target.closest('.char-card');
            if (!card || e.button > 0) return;
            drag = {card, id: card.dataset.char, sx: e.clientX, sy: e.clientY, ghost: null, pointer: e.pointerId, fromParty: !!card.closest('#start-party')};
            card.setPointerCapture(e.pointerId);
        });
        dlg.addEventListener('pointermove', e => {
            if (!drag || e.pointerId !== drag.pointer) return;
            if (!drag.ghost) {
                if (Math.hypot(e.clientX - drag.sx, e.clientY - drag.sy) < 6) return;
                drag.ghost = drag.card.cloneNode(true);
                drag.ghost.classList.add('char-ghost');
                dlg.appendChild(drag.ghost);
            }
            drag.ghost.style.left = e.clientX + 'px';
            drag.ghost.style.top = e.clientY + 'px';
            drop.classList.toggle('is-over', overDrop(e));
        });
        const overDrop = e => {
            const r = drop.getBoundingClientRect();
            return e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
        };
        const end = e => {
            if (!drag || e.pointerId !== drag.pointer) return;
            const d = drag;
            drag = null;
            drop.classList.remove('is-over');
            if (d.ghost) {
                d.ghost.remove();
                const inside = overDrop(e);
                if (inside && !party.includes(d.id)) addToParty(d.id);
                if (!inside && d.fromParty) removeFromParty(d.id);
            } else if (e.type === 'pointerup') {
                party.includes(d.id) ? removeFromParty(d.id) : addToParty(d.id);
            }
        };
        dlg.addEventListener('pointerup', end);
        dlg.addEventListener('pointercancel', end);
        dlg.addEventListener('keydown', e => {
            const card = e.target.closest('.char-card');
            if (card && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                const id = card.dataset.char;
                party.includes(id) ? removeFromParty(id) : addToParty(id);
            }
        });

        $('#start-submit').addEventListener('click', async () => {
            const btn = $('#start-submit');
            btn.disabled = true;
            try {
                const r = await api('start', {id: startMission.id, party});
                $('#dlg-start').close();
                animate[startMission.id] = 'pin';
                await reload();
                toast(r.message);
            } catch (err) {
                showError($('#start-error'), err.message);
            } finally {
                btn.disabled = false;
            }
        });
    }

    const charCard = (c, inParty) => `<div class="char-card${inParty ? ' in-party' : ''}${['Muerto', 'Retirado'].includes(c.state) ? ' is-out' : ''}" data-char="${esc(c.id)}" tabindex="0" role="button" aria-pressed="${inParty}" title="${esc(c.name)}">
        <span class="char-face">${c.portrait ? `<img src="${esc(c.portrait)}" alt="" draggable="false">` : `<b>${esc((c.short || c.name).charAt(0))}</b>`}</span>
        <span class="char-name">${esc(c.short || c.name)}${c.class ? `<small>${esc(c.class)}</small>` : ''}${c.state && c.state !== 'Activo' ? `<small class="char-state">${esc(c.state)}</small>` : ''}</span>
        ${inParty ? '<span class="char-remove" aria-hidden="true">×</span>' : ''}
    </div>`;

    const outRank = c => ({Retirado: 1, Muerto: 2}[c.state] || 0);

    function showPlayer(pid) {
        document.querySelectorAll('.start-player').forEach(b => b.setAttribute('aria-selected', String(b.dataset.player === pid)));
        const p = S.players.find(x => x.id === pid);
        $('#start-chars').innerHTML = p
            ? `<p class="start-chars-title">Personajes de ${esc(p.name)}</p><div class="char-grid">${[...p.characters].sort((a, b) => outRank(a) - outRank(b)).map(c => charCard(c, party.includes(c.id))).join('')}</div>`
            : '<p class="start-chars-title">Toca el retrato de un jugador para ver sus personajes.</p>';
        showPlayer.current = pid;
    }

    function drawParty() {
        const list = party.map(id => chars[id] || {id, name: id, short: id});
        $('#start-party').innerHTML = list.map(c => charCard(c, true)).join('');
        $('#start-drop-empty').hidden = party.length > 0;
        $('#start-drop-title').textContent = `${startMission.name} · ${party.length} ${party.length === 1 ? 'personaje' : 'personajes'}`;
        if (showPlayer.current) showPlayer(showPlayer.current);
    }

    function addToParty(id) {
        if (!party.includes(id)) party.push(id);
        drawParty();
        const card = $(`#start-party [data-char="${CSS.escape(id)}"]`);
        if (card) card.classList.add('anim-pin');
    }

    function removeFromParty(id) {
        party = party.filter(x => x !== id);
        drawParty();
    }

    function openStart(m) {
        startMission = m;
        // Personajes que ya no están en la lista de jugadores (p. ej. de un NPC) se conservan.
        for (const c of m.party) if (!chars[c.id]) chars[c.id] = c;
        party = m.party.map(c => c.id);
        $('#start-title').textContent = m.state === 'En curso' ? 'Cambiar grupo' : 'Iniciar misión';
        $('#start-submit').textContent = m.state === 'En curso' ? 'Guardar grupo' : 'Iniciar misión';
        showError($('#start-error'), '');
        showPlayer.current = null;
        showPlayer(null);
        drawParty();
        $('#dlg-start').showModal();
    }

    // Un retrato que no carga se cambia por la inicial (o el emoji del jugador).
    document.addEventListener('error', e => {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || img.classList.contains('paper-img')) return;
        const holder = img.closest('.start-player-face,.char-face,.paper-face,.view-party li');
        if (!holder) return;
        const label = img.closest('[title]')?.title || img.closest('.start-player')?.textContent || img.closest('li')?.textContent || '?';
        const b = document.createElement(holder.matches('li') ? 'span' : 'b');
        if (holder.matches('li')) b.className = 'face-fallback';
        b.textContent = img.closest('.start-player') ? (S.players.find(p => p.id === img.closest('.start-player').dataset.player)?.emoji || label.trim().charAt(0)) : label.trim().charAt(0);
        img.replaceWith(b);
    }, true);

    /* ---------- Inicio ---------- */

    drawBoard();
    setupBoardPointer();
    if (S.admin) setupAdmin();
    // Las letras de Google llegan después: se vuelve a ajustar el texto.
    if (document.fonts) {
        document.fonts.ready.then(() => fitAll());
        document.fonts.addEventListener('loadingdone', () => fitAll());
    }
    // Si el papel cambia mucho de tamaño (girar el móvil) se reajusta.
    let lastW = board.clientWidth;
    window.addEventListener('resize', () => {
        if (Math.abs(board.clientWidth - lastW) > 80) { lastW = board.clientWidth; fitAll(board); }
    });
})();
