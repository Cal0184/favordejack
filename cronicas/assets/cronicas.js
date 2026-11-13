/*
 * Favor de Jack · Crónicas: la línea de tiempo de la campaña.
 *
 * Viene de la aplicación «Crónicas» (timeline.js, music.js y app.js), pero los
 * datos ya no viven en el navegador: llegan del servidor en window.CRONICAS y
 * el admin los cambia con api.php. Los personajes son los de la web.
 */
(() => {
    'use strict';

    const S = window.CRONICAS;
    if (!S) return;
    const $ = sel => document.querySelector(sel);

    // ───────────── Utilidades ─────────────
    const esc = str => String(str ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const norm = v => String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
    const formatYear = y => (y < 0 ? `${Math.abs(y)} a.L.` : y === 0 ? 'Año 0' : `Año ${y}`);
    const formatYearShort = y => (y < 0 ? `${Math.abs(y)} aL` : `${y}`);
    const media = path => (path ? S.base + path : '');
    const store = {
        get(key, fallback) { try { const v = localStorage.getItem('cronicas-' + key); return v === null ? fallback : JSON.parse(v); } catch { return fallback; } },
        set(key, value) { try { localStorage.setItem('cronicas-' + key, JSON.stringify(value)); } catch { /* sin almacenamiento */ } },
    };

    function showToast(message, duration = 3000) {
        const box = $('.toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        box.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }

    function playSound(name) {
        if (!S.admin) return;
        const audio = new Audio(S.sounds + name);
        audio.volume = 0.6;
        audio.play().catch(() => {});
    }

    async function api(payload, file) {
        let body;
        const headers = {};
        if (file) {
            body = new FormData();
            Object.entries({ ...payload, csrf: S.csrf }).forEach(([k, v]) => body.append(k, v));
            body.append('file', file, file.name || 'archivo');
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify({ ...payload, csrf: S.csrf });
        }
        const res = await fetch('api.php', { method: 'POST', headers, body, credentials: 'same-origin' });
        let data = null;
        try { data = await res.json(); } catch { /* respuesta no JSON */ }
        if (!data?.ok) throw new Error(data?.error || (res.status === 413 ? `El archivo pesa demasiado para el servidor (máximo ${S.uploadMax}).` : 'No se pudo guardar. Revisa tu conexión.'));
        return data;
    }

    // ───────────── Datos ─────────────
    const events = () => [...S.events].sort((a, b) => a.year - b.year);
    const eventById = id => S.events.find(e => e.id === id) || null;
    const lineById = id => S.timelines.find(t => t.id === id) || null;
    const parallels = () => S.timelines.filter(t => t.id !== 'main');
    const PARALLEL_COLORS = ['#F59E0B', '#8B5CF6', '#3B82F6', '#10B981', '#F43F5E', '#EC4899', '#06B6D4', '#84CC16', '#E11D48', '#7C3AED'];
    function suggestColor() {
        const used = parallels().map(t => (t.color || '').toLowerCase());
        return PARALLEL_COLORS.find(c => !used.includes(c.toLowerCase())) || PARALLEL_COLORS[parallels().length % PARALLEL_COLORS.length];
    }
    function charInfo(c) {
        if (c.id) {
            const k = S.characters[c.id];
            return k ? { name: k.name, img: k.img, sheet: k.sheet ? S.sheetUrl + encodeURIComponent(c.id) : '' } : null;
        }
        return { name: c.name, img: '', sheet: '' };
    }

    // ───────────── Música ─────────────
    const music = {
        audio: new Audio(),
        current: null,
        userPaused: false,
        listeners: new Set(),
        init() {
            this.audio.loop = true;
            this.audio.volume = clamp(Number(store.get('volume', 0.4)) || 0, 0, 1);
            this.audio.addEventListener('play', () => this.notify());
            this.audio.addEventListener('pause', () => this.notify());
        },
        tracks: () => S.music,
        name() {
            const t = S.music.find(m => m.id === this.current);
            return t ? t.name : 'Sin música';
        },
        notify() {
            const state = { playing: !this.audio.paused, name: this.name(), volume: this.audio.volume };
            this.listeners.forEach(fn => fn(state));
        },
        async play(id) {
            const t = S.music.find(m => m.id === id);
            if (!t) return;
            if (this.current !== id) {
                this.current = id;
                this.audio.src = media(t.file);
            }
            this.userPaused = false;
            try { await this.audio.play(); } catch { /* el navegador pide un toque antes */ }
            this.notify();
        },
        playRandom() {
            if (!S.music.length) return;
            return this.play(S.music[Math.floor(Math.random() * S.music.length)].id);
        },
        toggle() {
            if (this.audio.paused) {
                if (this.current) { this.audio.play().catch(() => {}); this.userPaused = false; } else this.playRandom();
            } else {
                this.audio.pause();
                this.userPaused = true;
            }
            this.notify();
        },
        stop() {
            this.audio.pause();
            this.audio.removeAttribute('src');
            this.current = null;
            this.notify();
        },
        next() {
            if (!S.music.length) return;
            const i = S.music.findIndex(m => m.id === this.current);
            return this.play(S.music[(i + 1) % S.music.length].id);
        },
        setVolume(v) {
            this.audio.volume = clamp(v, 0, 1);
            store.set('volume', this.audio.volume);
            this.notify();
        },
    };

    // ───────────── Línea de tiempo ─────────────
    const CONFIG = { MIN_PPY: 0.5, MAX_PPY: 30, DEFAULT_PPY: 3, PADDING: 200 };
    const viewport = $('#timeline-viewport');
    const timeline = {
        ppy: clamp(Number(store.get('zoom', CONFIG.DEFAULT_PPY)) || CONFIG.DEFAULT_PPY, CONFIG.MIN_PPY, CONFIG.MAX_PPY),
        selected: null,
        canvas: null,
        minYear: 0,
        maxYear: 100,
        query: '',
        init() {
            this.canvas = document.createElement('div');
            this.canvas.className = 'timeline-canvas';
            viewport.appendChild(this.canvas);
            this.setupDrag();
            this.setupZoom();
            this.render();
        },
        range() {
            let min = 0;
            let max = 100;
            const list = events();
            if (list.length) {
                min = Math.min(...list.map(e => e.year));
                max = Math.max(...list.map(e => e.year));
            }
            S.timelines.forEach(t => {
                if (t.startYear !== null && t.startYear !== undefined) min = Math.min(min, t.startYear);
                if (t.endYear !== null && t.endYear !== undefined) max = Math.max(max, t.endYear);
            });
            const buffer = Math.max(50, (max - min) * 0.15);
            return { min: Math.floor(min - buffer), max: Math.ceil(max + buffer) };
        },
        x(year) { return CONFIG.PADDING + (year - this.minYear) * this.ppy; },
        render() {
            const { min, max } = this.range();
            this.minYear = min;
            this.maxYear = max;
            const width = (max - min) * this.ppy + CONFIG.PADDING * 2;
            this.canvas.innerHTML = '';
            this.canvas.style.width = `${Math.max(width, viewport.clientWidth)}px`;
            this.renderScale();
            this.renderLines();
            const hint = document.createElement('div');
            hint.className = 'timeline-hint';
            hint.textContent = events().length
                ? 'Arrastra para navegar • Ctrl + rueda o los botones para el zoom'
                : (S.admin ? 'Todavía no hay eventos • Pulsa «✦ Evento» para crear el primero' : 'Todavía no hay eventos en las crónicas');
            this.canvas.appendChild(hint);
            this.applySearch();
        },
        renderScale() {
            const scale = document.createElement('div');
            scale.className = 'timeline-scale';
            const p = this.ppy;
            const interval = p >= 15 ? 1 : p >= 8 ? 5 : p >= 4 ? 10 : p >= 2 ? 25 : p >= 1 ? 50 : 100;
            const major = interval * 5;
            for (let y = Math.ceil(this.minYear / interval) * interval; y <= this.maxYear; y += interval) {
                const mark = document.createElement('div');
                mark.className = 'year-mark' + (y % major === 0 ? ' major' : '');
                mark.style.left = `${this.x(y)}px`;
                mark.innerHTML = `<span class="year-label">${formatYearShort(y)}</span><div class="year-tick"></div>`;
                scale.appendChild(mark);
            }
            this.canvas.appendChild(scale);
        },
        renderLines() {
            const box = document.createElement('div');
            box.className = 'timeline-lines';
            const main = lineById('main');
            const par = parallels();
            par.filter((_, i) => i % 2 === 0).forEach(t => box.appendChild(this.line(t, false)));
            if (main) box.appendChild(this.line(main, true));
            par.filter((_, i) => i % 2 !== 0).forEach(t => box.appendChild(this.line(t, false)));
            this.canvas.appendChild(box);
        },
        line(t, isMain) {
            const el = document.createElement('div');
            el.className = 'timeline-line ' + (isMain ? 'main-line' : 'parallel-line');
            if (!isMain) el.style.setProperty('--line-color', t.color);
            el.dataset.timelineId = t.id;
            const track = document.createElement('div');
            track.className = 'line-track';
            if (!isMain && t.startYear !== null && t.endYear !== null) {
                track.style.left = `${this.x(t.startYear)}px`;
                track.style.right = 'auto';
                track.style.width = `${this.x(t.endYear) - this.x(t.startYear)}px`;
            }
            el.appendChild(track);
            const label = document.createElement(S.admin && !isMain ? 'button' : 'div');
            label.className = 'line-label';
            label.textContent = t.name;
            label.style.left = `${Math.max(8, isMain ? viewport.scrollLeft + 8 : this.x(t.startYear ?? this.minYear) - 4)}px`;
            if (!isMain) label.style.color = t.color;
            if (S.admin && !isMain) {
                label.type = 'button';
                label.title = 'Editar esta línea';
                label.addEventListener('click', e => { e.stopPropagation(); openParallelModal(t.id); });
            }
            el.appendChild(label);
            events().filter(e => e.timelineId === t.id).forEach(e => el.appendChild(this.node(e)));
            return el;
        },
        node(ev) {
            const node = document.createElement('button');
            node.type = 'button';
            node.className = 'event-node' + (this.selected === ev.id ? ' active' : '') + (ev.draft ? ' draft' : '');
            node.style.left = `${this.x(ev.year)}px`;
            node.dataset.eventId = ev.id;
            node.dataset.search = norm(ev.name + ' ' + (ev.summary || '') + ' ' + (ev.characters || []).map(c => charInfo(c)?.name || '').join(' '));
            node.setAttribute('aria-label', `${ev.name}, ${formatYear(ev.year)}`);
            node.innerHTML = `<span class="node-tooltip"><span class="tooltip-name">${esc(ev.name)}${ev.draft ? ' · borrador' : ''}</span><span class="tooltip-year">${formatYearShort(ev.year)}</span></span>`;
            node.addEventListener('click', e => { e.stopPropagation(); this.select(ev.id); });
            return node;
        },
        select(id, { scroll = false } = {}) {
            this.canvas.querySelector('.event-node.active')?.classList.remove('active');
            this.selected = id;
            const node = this.canvas.querySelector(`[data-event-id="${CSS.escape(id)}"]`);
            node?.classList.add('active');
            if (scroll) { const ev = eventById(id); if (ev) this.scrollToYear(ev.year); }
            if (history.replaceState) history.replaceState(null, '', '#' + id);
            handleEventSelect(id);
        },
        deselect() {
            this.canvas.querySelector('.event-node.active')?.classList.remove('active');
            this.selected = null;
            if (location.hash && history.replaceState) history.replaceState(null, '', location.pathname + location.search);
            handleEventDeselect();
        },
        scrollToYear(year) {
            viewport.scrollLeft = this.x(year) - viewport.clientWidth / 2;
        },
        applySearch() {
            const q = norm(this.query);
            this.canvas.classList.toggle('searching', q !== '');
            this.canvas.querySelectorAll('.event-node').forEach(n => n.classList.toggle('match', q !== '' && n.dataset.search.includes(q)));
        },
        setZoom(ppy, anchorX = viewport.clientWidth / 2) {
            const old = this.ppy;
            const next = clamp(ppy, CONFIG.MIN_PPY, CONFIG.MAX_PPY);
            if (next === old) return;
            const yearAt = this.minYear + (viewport.scrollLeft + anchorX - CONFIG.PADDING) / old;
            this.ppy = next;
            store.set('zoom', next);
            this.render();
            viewport.scrollLeft = CONFIG.PADDING + (yearAt - this.minYear) * next - anchorX;
            updateZoomDisplay();
        },
        setupDrag() {
            let dragging = false;
            let moved = false;
            let startX = 0;
            let startScroll = 0;
            viewport.addEventListener('mousedown', e => {
                if (e.button !== 0 || e.target.closest('.event-node, .line-label')) return;
                dragging = true;
                moved = false;
                startX = e.clientX;
                startScroll = viewport.scrollLeft;
                viewport.classList.add('dragging');
            });
            window.addEventListener('mousemove', e => {
                if (!dragging) return;
                if (Math.abs(e.clientX - startX) > 4) moved = true;
                viewport.scrollLeft = startScroll - (e.clientX - startX);
            });
            window.addEventListener('mouseup', () => {
                if (!dragging) return;
                dragging = false;
                viewport.classList.remove('dragging');
            });
            viewport.addEventListener('click', e => {
                if (moved) { moved = false; return; }
                if (!e.target.closest('.event-node, .line-label') && this.selected) this.deselect();
            });
            viewport.addEventListener('keydown', e => {
                if (e.key === 'ArrowRight') { viewport.scrollLeft += 120; e.preventDefault(); }
                if (e.key === 'ArrowLeft') { viewport.scrollLeft -= 120; e.preventDefault(); }
                if (e.key === '+' || e.key === '=') this.setZoom(this.ppy * 1.5);
                if (e.key === '-') this.setZoom(this.ppy / 1.5);
            });
            // La etiqueta de la línea principal acompaña al desplazamiento.
            viewport.addEventListener('scroll', () => {
                const label = this.canvas.querySelector('.main-line .line-label');
                if (label) label.style.left = `${viewport.scrollLeft + 8}px`;
            }, { passive: true });
        },
        setupZoom() {
            viewport.addEventListener('wheel', e => {
                if (!e.ctrlKey) return;
                e.preventDefault();
                this.setZoom(this.ppy * (e.deltaY > 0 ? 0.85 : 1.18), e.clientX - viewport.getBoundingClientRect().left);
            }, { passive: false });
        },
    };

    function updateZoomDisplay() {
        $('#zoom-level').textContent = `${Math.round((timeline.ppy / CONFIG.DEFAULT_PPY) * 100)}%`;
    }

    // ───────────── Panel del evento ─────────────
    const panel = $('#event-panel');
    const bgLayer = $('#background-layer');

    function handleEventSelect(id) {
        const ev = eventById(id);
        if (!ev) return;
        panel.querySelector('.panel-event-name').textContent = ev.name + (ev.draft ? ' (borrador)' : '');
        panel.querySelector('.panel-event-year').textContent = formatYear(ev.year);
        panel.querySelector('.panel-event-summary').textContent = ev.summary || 'Sin descripción.';
        const t = lineById(ev.timelineId) || lineById('main');
        panel.querySelector('.badge-dot').style.backgroundColor = t?.color || '#d4a843';
        panel.querySelector('.badge-name').textContent = t?.name || 'Historia Principal';

        const box = panel.querySelector('.panel-characters');
        box.innerHTML = '';
        const list = (ev.characters || []).map(charInfo).filter(Boolean);
        if (!list.length) box.innerHTML = '<span class="muted">Sin personajes.</span>';
        list.forEach(c => {
            const el = document.createElement(c.sheet ? 'a' : 'span');
            el.className = 'panel-character';
            if (c.sheet) { el.href = c.sheet; el.title = 'Ver su hoja en Archivos'; }
            el.innerHTML = (c.img ? `<img src="${esc(c.img)}" alt="" loading="lazy">` : `<span class="char-initial">${esc(c.name.charAt(0).toUpperCase())}</span>`) + `<span>${esc(c.name)}</span>`;
            box.appendChild(el);
        });

        if (ev.image) {
            bgLayer.style.backgroundImage = `url("${media(ev.image)}")`;
            bgLayer.classList.add('active');
        } else {
            bgLayer.classList.remove('active');
            bgLayer.style.backgroundImage = '';
        }
        if (ev.music && !music.userPaused && music.current !== ev.music) music.play(ev.music);

        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        if (S.admin) {
            panel.querySelector('.btn-edit-event').onclick = () => { closePanel(); openEventModal('edit', id); };
            panel.querySelector('.btn-delete-event').onclick = () => openDeleteConfirm(id, ev.name);
        }
    }

    function closePanel() {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
    }

    function handleEventDeselect() {
        closePanel();
        bgLayer.classList.remove('active');
        bgLayer.style.backgroundImage = '';
    }

    // ───────────── Ventana modal ─────────────
    const modal = $('#modal-overlay');
    let lastFocus = null;
    function openModal(title, body, footer) {
        lastFocus = document.activeElement;
        $('#modal-title').textContent = title;
        $('#modal-body').innerHTML = body;
        $('#modal-footer').innerHTML = footer;
        modal.classList.add('active');
        setTimeout(() => modal.querySelector('input, textarea, select, button.btn')?.focus(), 30);
    }
    function closeModal() {
        modal.classList.remove('active');
        lastFocus?.focus?.();
    }
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    modal.querySelector('.modal-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (modal.classList.contains('active')) closeModal();
        else if (timeline.selected) timeline.deselect();
    });
    panel.querySelector('.panel-close').addEventListener('click', () => timeline.deselect());

    function busy(btn, text) {
        const old = btn.textContent;
        btn.disabled = true;
        btn.textContent = text;
        return () => { btn.disabled = false; btn.textContent = old; };
    }

    // ───────────── Crear y editar eventos (admin) ─────────────
    let form = null;

    function openEventModal(mode, id = null) {
        const ev = mode === 'edit' ? eventById(id) : null;
        form = {
            mode, id,
            characters: ev ? (ev.characters || []).map(c => ({ ...c })) : [],
            image: ev?.image || '',
        };
        const lines = S.timelines.map(t => `<option value="${esc(t.id)}"${ev && ev.timelineId === t.id ? ' selected' : ''}>${esc(t.name)}</option>`).join('');
        const tracks = S.music.map(m => `<option value="${esc(m.id)}"${ev && ev.music === m.id ? ' selected' : ''}>${esc(m.name)}</option>`).join('');
        openModal(mode === 'create' ? 'Nuevo evento' : 'Editar evento', `
            <div class="form-row">
                <div class="form-group"><label for="event-name">Nombre</label>
                    <input type="text" id="event-name" class="form-input" maxlength="140" placeholder="La Caída del Imperio" value="${esc(ev?.name || '')}"></div>
                <div class="form-group"><label for="event-year">Año</label>
                    <input type="number" id="event-year" class="form-input" placeholder="0" value="${ev ? ev.year : ''}"></div>
            </div>
            <div class="form-group"><label for="event-summary">Resumen</label>
                <textarea id="event-summary" class="form-textarea" maxlength="8000" placeholder="Describe lo que sucedió…">${esc(ev?.summary || '')}</textarea></div>
            <div class="form-group"><label for="event-timeline">Línea de tiempo</label>
                <select id="event-timeline" class="form-select">${lines}</select></div>
            <div class="form-group"><label for="char-search">Personajes</label>
                <div class="character-input-wrapper">
                    <input type="text" id="char-search" class="form-input" placeholder="Busca un personaje de la Banca o escribe un nombre…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="char-dropdown">
                    <div class="character-dropdown" id="char-dropdown" role="listbox"></div>
                    <div class="character-tags" id="char-tags"></div>
                </div></div>
            <div class="form-group"><label for="event-music">Música (opcional)</label>
                <div class="music-select-row">
                    <select id="event-music" class="form-select"><option value="">Sin música</option>${tracks}</select>
                    <label class="music-upload-btn" title="Subir canción">🎵<input type="file" accept="audio/*" id="event-music-upload"></label>
                </div></div>
            <div class="form-group"><label>Imagen de fondo (opcional)</label>
                <label class="file-upload-area" id="bg-upload-area">
                    <span class="upload-icon">🖼️</span><span class="upload-text">Toca o arrastra una imagen</span><span class="upload-hint">JPG, PNG o WebP · máx. ${esc(S.uploadMax)}</span>
                    <input type="file" accept="image/*" id="bg-image-upload">
                </label>
                <div id="bg-image-preview"></div></div>
            <label class="check-row"><input type="checkbox" id="event-draft"${ev?.draft ? ' checked' : ''}> Borrador (solo lo ve el admin)</label>
        `, `
            ${mode === 'edit' ? '<button class="btn btn-danger" type="button" id="modal-delete-event">🗑 Eliminar</button>' : ''}
            <button class="btn" type="button" id="modal-cancel">Cancelar</button>
            <button class="btn btn-primary" type="button" id="modal-save">${mode === 'create' ? '✦ Crear evento' : '✦ Guardar cambios'}</button>
        `);
        renderCharacterTags();
        renderBgPreview();
        setupCharacterInput();
        $('#modal-cancel').addEventListener('click', closeModal);
        $('#modal-save').addEventListener('click', saveEvent);
        $('#modal-delete-event')?.addEventListener('click', () => openDeleteConfirm(id, ev?.name || 'Evento'));

        const area = $('#bg-upload-area');
        $('#bg-image-upload').addEventListener('change', e => e.target.files[0] && uploadBg(e.target.files[0]));
        area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
        area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.classList.remove('drag-over');
            if (e.dataTransfer.files[0]) uploadBg(e.dataTransfer.files[0]);
        });
        $('#event-music-upload').addEventListener('change', async e => {
            const file = e.target.files[0];
            if (!file) return;
            try {
                const track = await uploadTrack(file);
                const select = $('#event-music');
                select.insertAdjacentHTML('beforeend', `<option value="${esc(track.id)}">${esc(track.name)}</option>`);
                select.value = track.id;
            } catch (err) { showToast(err.message, 5000); }
        });
    }

    async function uploadBg(file) {
        const preview = $('#bg-image-preview');
        preview.innerHTML = '<p class="muted">Subiendo imagen…</p>';
        try {
            const data = await api({ action: 'upload_image' }, file);
            form.image = data.path;
        } catch (err) {
            showToast(err.message, 5000);
        }
        renderBgPreview();
    }

    function renderBgPreview() {
        const box = $('#bg-image-preview');
        if (!form.image) { box.innerHTML = ''; return; }
        box.innerHTML = `<div class="image-preview"><img src="${esc(media(form.image))}" alt="Fondo del evento"><button class="remove-preview" type="button" title="Quitar imagen" aria-label="Quitar imagen">✕</button></div>`;
        box.querySelector('.remove-preview').addEventListener('click', () => { form.image = ''; renderBgPreview(); });
    }

    async function saveEvent() {
        const name = $('#event-name').value.trim();
        const year = $('#event-year').value;
        if (!name) return showToast('El nombre es obligatorio');
        if (year === '') return showToast('El año es obligatorio');
        const done = busy($('#modal-save'), 'Guardando…');
        try {
            const data = await api({
                action: 'save_event',
                id: form.mode === 'edit' ? form.id : '',
                event: {
                    name, year: Number(year),
                    summary: $('#event-summary').value,
                    timelineId: $('#event-timeline').value,
                    characters: form.characters,
                    image: form.image,
                    music: $('#event-music').value,
                    draft: $('#event-draft').checked,
                },
            });
            const at = S.events.findIndex(e => e.id === data.event.id);
            if (at >= 0) S.events[at] = data.event; else S.events.push(data.event);
            showToast(form.mode === 'create' ? `✦ «${name}» creado` : `✦ «${name}» actualizado`);
            playSound(form.mode === 'create' ? 'crear.mp3' : 'editar.mp3');
            closeModal();
            timeline.render();
            timeline.select(data.event.id, { scroll: form.mode === 'create' });
        } catch (err) {
            showToast(err.message, 5000);
            done();
        }
    }

    // Personajes: los de la web, o un nombre libre para figuras de la historia.
    function setupCharacterInput() {
        const input = $('#char-search');
        const drop = $('#char-dropdown');
        const show = () => {
            const q = norm(input.value);
            const chosen = new Set(form.characters.filter(c => c.id).map(c => c.id));
            const found = Object.entries(S.characters)
                .filter(([id, c]) => !c.dup && !chosen.has(id) && (!q || norm(c.name).includes(q)))
                .slice(0, 40);
            drop.innerHTML = found.map(([id, c]) => `
                <div class="character-dropdown-item" role="option" data-id="${esc(id)}">
                    ${c.img ? `<img src="${esc(c.img)}" alt="" loading="lazy">` : `<div class="char-placeholder">${esc(c.name.charAt(0).toUpperCase())}</div>`}
                    <span>${esc(c.name)}</span>
                </div>`).join('');
            const raw = input.value.trim();
            if (raw && !found.some(([, c]) => norm(c.name) === norm(raw))) {
                drop.insertAdjacentHTML('beforeend', `<div class="character-dropdown-item create" role="option" data-name="${esc(raw)}">
                    <div class="char-placeholder">+</div><span>Añadir «${esc(raw)}» (sin ficha)</span></div>`);
            }
            if (!drop.children.length) drop.innerHTML = '<div class="character-dropdown-item empty">No hay más personajes</div>';
            drop.classList.add('visible');
            input.setAttribute('aria-expanded', 'true');
        };
        const hide = () => { drop.classList.remove('visible'); input.setAttribute('aria-expanded', 'false'); };
        const pick = item => {
            if (item.dataset.id) form.characters.push({ id: item.dataset.id });
            else if (item.dataset.name) form.characters.push({ name: item.dataset.name });
            else return;
            input.value = '';
            renderCharacterTags();
            show();
            input.focus();
        };
        input.addEventListener('input', show);
        input.addEventListener('focus', show);
        input.addEventListener('blur', () => setTimeout(hide, 180));
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const first = drop.querySelector('.character-dropdown-item[data-id], .character-dropdown-item[data-name]');
            if (first) pick(first);
        });
        drop.addEventListener('mousedown', e => {
            const item = e.target.closest('.character-dropdown-item');
            if (!item) return;
            e.preventDefault();
            pick(item);
        });
    }

    function renderCharacterTags() {
        const box = $('#char-tags');
        box.innerHTML = '';
        form.characters.forEach((c, i) => {
            const info = charInfo(c);
            if (!info) return;
            const tag = document.createElement('span');
            tag.className = 'tag' + (c.id ? '' : ' tag-free');
            tag.innerHTML = (info.img ? `<img src="${esc(info.img)}" alt="">` : '') + `<span>${esc(info.name)}</span><button type="button" class="tag-remove" aria-label="Quitar a ${esc(info.name)}">×</button>`;
            tag.querySelector('.tag-remove').addEventListener('click', () => { form.characters.splice(i, 1); renderCharacterTags(); });
            box.appendChild(tag);
        });
    }

    function openDeleteConfirm(id, name) {
        openModal('Confirmar eliminación', `
            <div class="confirm-dialog">
                <div class="confirm-icon">⚠️</div>
                <p>¿Seguro que quieres eliminar este evento?</p>
                <p class="confirm-name">«${esc(name)}»</p>
                <p class="muted">No se puede deshacer (salvo con un respaldo).</p>
            </div>`, `
            <button class="btn" type="button" id="modal-cancel">Cancelar</button>
            <button class="btn btn-danger" type="button" id="modal-confirm-delete">🗑 Eliminar</button>`);
        $('#modal-cancel').addEventListener('click', closeModal);
        $('#modal-confirm-delete').addEventListener('click', async e => {
            const done = busy(e.currentTarget, 'Eliminando…');
            try {
                await api({ action: 'delete_event', id });
                S.events = S.events.filter(ev => ev.id !== id);
                closeModal();
                timeline.deselect();
                timeline.render();
                showToast(`«${name}» eliminado`);
                playSound('borrar.mp3');
            } catch (err) { showToast(err.message, 5000); done(); }
        });
    }

    // ───────────── Líneas paralelas (admin) ─────────────
    function openParallelModal(id = null) {
        const t = id ? lineById(id) : null;
        const color = t?.color || suggestColor();
        openModal(t ? 'Editar línea paralela' : 'Nueva línea paralela', `
            <div class="form-group"><label for="parallel-name">Nombre</label>
                <input type="text" id="parallel-name" class="form-input" maxlength="80" placeholder="Guerra Élfica, Era de los Dragones…" value="${esc(t?.name || '')}"></div>
            <div class="form-row">
                <div class="form-group"><label for="parallel-start">Año de inicio</label>
                    <input type="number" id="parallel-start" class="form-input" placeholder="0" value="${t ? t.startYear : ''}"></div>
                <div class="form-group"><label for="parallel-end">Año de fin</label>
                    <input type="number" id="parallel-end" class="form-input" placeholder="500" value="${t ? t.endYear : ''}"></div>
            </div>
            <div class="form-group"><label for="parallel-color">Color</label>
                <div class="color-picker-wrapper"><input type="color" id="parallel-color" value="${esc(color)}"><span id="parallel-color-label">${esc(color)}</span></div></div>
            ${t ? '<div class="divider"></div><button class="btn btn-danger btn-sm" type="button" id="parallel-delete-btn">🗑 Eliminar esta línea</button>' : ''}
        `, `
            <button class="btn" type="button" id="modal-cancel">Cancelar</button>
            <button class="btn btn-primary" type="button" id="modal-save">${t ? '✦ Guardar' : '✦ Crear línea'}</button>`);
        $('#parallel-color').addEventListener('input', e => { $('#parallel-color-label').textContent = e.target.value; });
        $('#modal-cancel').addEventListener('click', closeModal);
        $('#parallel-delete-btn')?.addEventListener('click', async () => {
            if (!confirm('¿Eliminar esta línea paralela? Sus eventos pasan a la línea principal.')) return;
            try {
                await api({ action: 'delete_line', id });
                S.timelines = S.timelines.filter(x => x.id !== id);
                S.events.forEach(e => { if (e.timelineId === id) e.timelineId = 'main'; });
                closeModal();
                timeline.render();
                showToast('Línea eliminada');
            } catch (err) { showToast(err.message, 5000); }
        });
        $('#modal-save').addEventListener('click', async e => {
            const name = $('#parallel-name').value.trim();
            const start = $('#parallel-start').value;
            const end = $('#parallel-end').value;
            if (!name) return showToast('El nombre es obligatorio');
            if (start === '' || end === '') return showToast('Los años son obligatorios');
            if (Number(end) <= Number(start)) return showToast('El año de fin debe ser mayor al de inicio');
            const done = busy(e.currentTarget, 'Guardando…');
            try {
                const data = await api({ action: 'save_line', id: id || '', line: { name, startYear: Number(start), endYear: Number(end), color: $('#parallel-color').value } });
                const at = S.timelines.findIndex(x => x.id === data.line.id);
                if (at >= 0) S.timelines[at] = data.line; else S.timelines.push(data.line);
                closeModal();
                timeline.render();
                showToast(`«${name}» ${id ? 'actualizada' : 'creada'}`);
            } catch (err) { showToast(err.message, 5000); done(); }
        });
    }

    function openLinesModal() {
        const list = parallels();
        openModal('Líneas de tiempo', list.length
            ? `<ul class="line-list">${list.map(t => `<li><span class="badge-dot" style="background:${esc(t.color)}"></span><span class="line-name">${esc(t.name)}</span><span class="muted">${formatYearShort(t.startYear)} – ${formatYearShort(t.endYear)}</span><button class="btn btn-sm" type="button" data-edit="${esc(t.id)}">✏️ Editar</button></li>`).join('')}</ul>`
            : '<div class="empty-state"><div class="empty-icon">⫘</div><p>Todavía no hay líneas paralelas.</p></div>',
            '<button class="btn" type="button" id="modal-cancel">Cerrar</button><button class="btn btn-primary" type="button" id="modal-new-line">✦ Nueva línea</button>');
        $('#modal-cancel').addEventListener('click', closeModal);
        $('#modal-new-line').addEventListener('click', () => openParallelModal());
        $('#modal-body').querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openParallelModal(b.dataset.edit)));
    }

    // ───────────── Biblioteca de música ─────────────
    async function uploadTrack(file) {
        showToast(`Subiendo «${file.name}»…`);
        const data = await api({ action: 'upload_music', name: file.name }, file);
        S.music.push(data.track);
        showToast(`♫ «${data.track.name}» agregada`);
        renderMusicLibrary();
        return data.track;
    }

    function renderMusicLibrary() {
        const list = $('#music-library-list');
        if (!S.music.length) {
            list.innerHTML = '<div class="library-empty"><div class="empty-icon">🎵</div><p>No hay canciones.</p></div>';
            return;
        }
        list.innerHTML = '';
        S.music.forEach(t => {
            const item = document.createElement('div');
            const on = music.current === t.id;
            item.className = 'library-item' + (on ? ' active' : '');
            item.innerHTML = `<span class="item-icon">${on ? '♫' : '♪'}</span><button type="button" class="item-name">${esc(t.name)}</button>`
                + (S.admin && t.id !== 'tema' ? '<button type="button" class="item-delete" title="Eliminar" aria-label="Eliminar canción">✕</button>' : '');
            item.querySelector('.item-name').addEventListener('click', () => { music.play(t.id); renderMusicLibrary(); });
            item.querySelector('.item-delete')?.addEventListener('click', async e => {
                e.stopPropagation();
                if (!confirm(`¿Eliminar «${t.name}» de la biblioteca?`)) return;
                try {
                    await api({ action: 'delete_music', id: t.id });
                    if (music.current === t.id) music.stop();
                    S.music = S.music.filter(m => m.id !== t.id);
                    S.events.forEach(ev => { if (ev.music === t.id) ev.music = ''; });
                    renderMusicLibrary();
                } catch (err) { showToast(err.message, 5000); }
            });
            list.appendChild(item);
        });
    }

    function setupMusicPlayerUI() {
        const playBtn = $('#music-play-btn');
        const slider = $('#music-volume');
        const icon = $('#music-volume-icon');
        const library = $('#music-library');
        const toggle = $('#music-library-toggle');
        playBtn.addEventListener('click', () => music.toggle());
        $('#music-next-btn').addEventListener('click', () => music.next());
        slider.addEventListener('input', e => music.setVolume(parseFloat(e.target.value)));
        icon.addEventListener('click', () => {
            if (music.audio.volume > 0) { icon.dataset.prev = music.audio.volume; music.setVolume(0); }
            else music.setVolume(parseFloat(icon.dataset.prev || '0.4'));
        });
        toggle.addEventListener('click', () => {
            library.classList.toggle('open');
            toggle.classList.toggle('active');
            if (library.classList.contains('open')) renderMusicLibrary();
        });
        music.listeners.add(st => {
            $('#music-track-name').textContent = st.name;
            $('#music-track-status').textContent = st.playing ? '♫ REPRODUCIENDO' : '⏸ PAUSADO';
            playBtn.textContent = st.playing ? '⏸' : '▶';
            playBtn.classList.toggle('playing', st.playing);
            slider.value = st.volume;
            icon.textContent = st.volume === 0 ? '🔇' : st.volume < 0.5 ? '🔉' : '🔊';
            if (library.classList.contains('open')) renderMusicLibrary();
        });
        $('#library-upload-input')?.addEventListener('change', async e => {
            for (const file of e.target.files) {
                try { await uploadTrack(file); } catch (err) { showToast(err.message, 5000); }
            }
            e.target.value = '';
        });
        music.notify();
    }

    // ───────────── Importar desde la aplicación Crónicas (admin) ─────────────
    function pickFile(accept) {
        return new Promise(resolve => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = accept;
            input.onchange = () => resolve(input.files[0] || null);
            input.click();
        });
    }

    async function importOld() {
        const file = await pickFile('.json,application/json');
        if (!file) return;
        let dump;
        try { dump = JSON.parse(await file.text()); } catch { return showToast('Ese archivo no es un respaldo de Crónicas.', 5000); }
        const data = dump?.data;
        if (!data || !Array.isArray(data.events)) return showToast('Ese archivo no es un respaldo de Crónicas.', 5000);
        if (!confirm(`Se van a agregar ${data.events.length} eventos y ${(data.timelines || []).filter(t => t.id !== 'main').length} líneas paralelas. ¿Seguir?`)) return;

        const toFile = async (item, fallback) => {
            const blob = await (await fetch(item.base64)).blob();
            return new File([blob], item.name || fallback, { type: item.type || blob.type });
        };
        const images = new Map((dump.images || []).map(i => [i.id, i]));
        const imagePath = new Map();
        const musicId = new Map();
        let failed = 0;
        for (const ev of data.events) {
            const img = ev.backgroundImageId && images.get(ev.backgroundImageId);
            if (!img || imagePath.has(img.id)) continue;
            try { imagePath.set(img.id, (await api({ action: 'upload_image' }, await toFile(img, 'fondo.png'))).path); } catch { failed++; }
        }
        const usedMusic = new Set(data.events.map(e => e.musicId).filter(Boolean));
        for (const m of dump.music || []) {
            if (!usedMusic.has(m.id) || !m.base64) continue;
            try { musicId.set(m.id, (await uploadTrack(await toFile(m, 'cancion.mp3'))).id); } catch { failed++; }
        }
        // Los personajes con el mismo nombre que uno de la web se enlazan a él.
        const byName = new Map(Object.entries(S.characters).filter(([, c]) => !c.dup).map(([id, c]) => [norm(c.name), id]));
        const oldChars = new Map((data.characters || []).map(c => [c.id, c.name]));
        const evs = data.events.map(e => ({
            name: e.name, year: e.year, summary: e.summary || '', timelineId: e.timelineId || 'main',
            image: imagePath.get(e.backgroundImageId) || '',
            music: musicId.get(e.musicId) || '',
            characters: (e.characters || []).map(cid => oldChars.get(cid)).filter(Boolean)
                .map(name => (byName.has(norm(name)) ? { id: byName.get(norm(name)) } : { name })),
        }));
        try {
            const res = await api({ action: 'import', events: evs, timelines: data.timelines || [], campaignName: data.config?.campaignName || '' });
            alert(`Listo: se agregaron ${res.events} eventos.` + (failed ? ` ${failed} imagen(es) o canción(es) no se pudieron subir (quizá pesan demasiado).` : ''));
            location.reload();
        } catch (err) { showToast(err.message, 6000); }
    }

    // ───────────── Inicio ─────────────
    music.init();
    timeline.init();
    setupMusicPlayerUI();
    updateZoomDisplay();
    $('#zoom-in').addEventListener('click', () => timeline.setZoom(timeline.ppy * 1.5));
    $('#zoom-out').addEventListener('click', () => timeline.setZoom(timeline.ppy / 1.5));
    $('#eventSearch').addEventListener('input', e => { timeline.query = e.target.value; timeline.applySearch(); });
    $('#eventSearch').addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        const hit = timeline.canvas.querySelector('.event-node.match');
        if (hit) timeline.select(hit.dataset.eventId, { scroll: true });
    });

    const splash = $('#splash-screen');
    const enter = () => {
        splash.classList.add('hidden');
        music.playRandom();
        viewport.focus({ preventScroll: true });
    };
    splash.addEventListener('click', enter, { once: true });
    splash.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); enter(); } });
    splash.focus({ preventScroll: true });

    const fromHash = decodeURIComponent(location.hash.slice(1));
    const first = events();
    if (fromHash && eventById(fromHash)) setTimeout(() => timeline.select(fromHash, { scroll: true }), 60);
    else setTimeout(() => timeline.scrollToYear(first.length ? first[0].year : 0), 60);
    window.addEventListener('resize', () => timeline.render());

    if (S.admin) {
        $('#btn-new-event').addEventListener('click', () => openEventModal('create'));
        $('#btn-new-parallel').addEventListener('click', () => openParallelModal());
        $('#btn-lines').addEventListener('click', openLinesModal);
        $('#btn-import').addEventListener('click', importOld);
        const title = $('#campaignName');
        title.addEventListener('dblclick', async () => {
            const name = prompt('Nombre de la campaña:', title.textContent);
            if (!name || !name.trim()) return;
            try {
                await api({ action: 'rename', name: name.trim() });
                title.textContent = name.trim();
                $('#splashTitle').textContent = name.trim();
                showToast('Nombre actualizado');
            } catch (err) { showToast(err.message, 5000); }
        });
    }
})();
