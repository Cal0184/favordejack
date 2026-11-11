/*
 * Archivo de Misiones como biblioteca (Archivos del Gremio).
 *
 * Cada misión completada trae del servidor su libro (m.book): modelo 1–4,
 * posición en el archivo y estantería. Aquí solo se dibuja: la apariencia y
 * el orden nunca se deciden en el navegador.
 *
 * Solo se dibuja la estantería que se está mirando (36 libros como mucho).
 */
(() => {
    'use strict';

    const G = window.GuildArchive;
    const S = window.GUILD_STATE || {};
    const stage = document.getElementById('bookcaseStage');
    if (!G || !stage) return;

    const $ = (sel, root = document) => root.querySelector(sel);
    const {esc, normalize} = G;
    const CAPACITY = Number(S.libraryCapacity) || 36;
    const PER_ROW = 12;
    const ROWS = Math.ceil(CAPACITY / PER_ROW);
    const SHELF_KEY = 'guildLibraryShelf';
    const MODE_KEY = 'guildLibraryMode';
    const RETURN_KEY = 'guildLibraryReturn';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const books = G.missions.filter(m => m.book).sort((a, b) => a.book.position - b.book.position);
    const unshelved = G.missions.filter(m => !m.book);
    const shelfCount = Math.max(1, Math.ceil(books.length / CAPACITY));
    const byId = new Map(G.missions.map(m => [String(m.id), m]));

    const nav = {
        first: $('#shelfFirst'), prev: $('#shelfPrev'), next: $('#shelfNext'),
        indicator: $('#shelfIndicator'), bar: $('#shelfNav'),
    };
    const card = $('#bookCard');
    const modal = $('#bookModal');
    const modalContent = $('#bookModalContent');
    const search = $('#missionSearch');
    const results = $('#libraryResults');
    const modeBtn = $('#libraryMode');
    const listWrap = $('#missionListWrap');

    const roman = n => {
        const map = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
        let out = '';
        for (const [v, s] of map) while (n >= v) { out += s; n -= v; }
        return out;
    };
    const store = {
        get(k) { try { return sessionStorage.getItem(k); } catch (_) { return null; } },
        set(k, v) { try { sessionStorage.setItem(k, v); } catch (_) {} },
        del(k) { try { sessionStorage.removeItem(k); } catch (_) {} },
    };
    const local = {
        get(k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (_) {} },
    };

    // Pequeña variación de altura, fija por misión (no cambia al recargar).
    function jitter(id) {
        let h = 2166136261;
        for (const ch of String(id)) h = Math.imul(h ^ ch.charCodeAt(0), 16777619);
        return ((h >>> 0) % 7) - 3; // −3 … +3
    }

    // ------------------------------------------------------------
    // Contador
    // ------------------------------------------------------------
    const count = $('#libraryCount');
    if (count) {
        count.innerHTML = `Misiones archivadas: <strong>${books.length}</strong> <span aria-hidden="true">·</span> Estanterías ocupadas: <strong>${books.length ? shelfCount : 0}</strong>`;
    }
    const aside = $('#libraryAside');
    if (aside && unshelved.length) {
        aside.hidden = false;
        aside.textContent = unshelved.length === 1
            ? 'Hay 1 misión registrada que no está completada (en curso, fallida o cancelada): no ocupa libro, pero sigue en «Ver como lista».'
            : `Hay ${unshelved.length} misiones registradas que no están completadas (en curso, fallidas o canceladas): no ocupan libro, pero siguen en «Ver como lista».`;
    }

    // ------------------------------------------------------------
    // Dibujo de una estantería
    // ------------------------------------------------------------
    let current = Math.min(shelfCount, Math.max(1, Number(store.get(SHELF_KEY)) || shelfCount));

    function bookHtml(m) {
        const b = m.book;
        return `<button type="button" class="book book--m${b.model}" data-book="${esc(m.id)}" style="--j:${jitter(m.id)}"
            aria-label="${esc(m.name)}. Libro ${b.position} del archivo" aria-describedby="bookCard">
            <span class="book-title">${esc(m.name)}</span></button>`;
    }

    function shelfHtml(n) {
        const slice = books.slice((n - 1) * CAPACITY, n * CAPACITY);
        let rows = '';
        for (let r = 0; r < ROWS; r++) {
            const inRow = slice.slice(r * PER_ROW, (r + 1) * PER_ROW);
            const empty = !books.length && r === 1
                ? '<p class="shelf-empty">Las historias de nuestros aventureros aún están por escribirse.</p>' : '';
            rows += `<div class="shelf-row"><div class="shelf-books">${inRow.map(bookHtml).join('')}${empty}</div><div class="shelf-board" aria-hidden="true"></div></div>`;
        }
        return `<div class="bookcase" data-shelf="${n}">
            <div class="bookcase-crown"><div class="bookcase-plate"><span>Archivo de misiones</span><small>Estantería ${roman(n)}</small></div></div>
            <div class="bookcase-body">${rows}</div>
            <div class="bookcase-base" aria-hidden="true"></div>
        </div>`;
    }

    function updateNav() {
        nav.indicator.textContent = `Estantería ${roman(current)} de ${roman(shelfCount)}`;
        nav.prev.disabled = current <= 1;
        nav.first.disabled = current <= 1;
        nav.next.disabled = current >= shelfCount;
        nav.bar.classList.toggle('single', shelfCount <= 1);
    }

    let animating = null;
    function showShelf(n, direction = 0) {
        n = Math.min(shelfCount, Math.max(1, n));
        const changed = n !== current || !stage.firstElementChild;
        current = n;
        store.set(SHELF_KEY, String(n));
        updateNav();
        if (!changed) return Promise.resolve();
        hideCard(true);
        const swap = () => { stage.innerHTML = shelfHtml(n); };
        if (!direction || reduceMotion.matches || !stage.animate || !stage.firstElementChild) {
            swap();
            return Promise.resolve();
        }
        // Paso lateral breve: como caminar hacia la siguiente sección.
        animating?.cancel();
        const out = stage.animate(
            [{transform: 'translateX(0)', opacity: 1}, {transform: `translateX(${-direction * 46}px)`, opacity: 0}],
            {duration: 170, easing: 'ease-in'}
        );
        animating = out;
        return out.finished.then(() => {
            swap();
            animating = stage.animate(
                [{transform: `translateX(${direction * 46}px)`, opacity: 0}, {transform: 'translateX(0)', opacity: 1}],
                {duration: 220, easing: 'ease-out'}
            );
        }).catch(() => swap());
    }

    nav.first?.addEventListener('click', () => showShelf(1, -1));
    nav.prev?.addEventListener('click', () => showShelf(current - 1, -1));
    nav.next?.addEventListener('click', () => showShelf(current + 1, 1));

    // Deslizar con el dedo sobre la estantería también cambia de estantería.
    let touchStart = null;
    stage.addEventListener('touchstart', e => {
        const t = e.touches[0];
        touchStart = e.touches.length === 1 ? {x: t.clientX, y: t.clientY} : null;
    }, {passive: true});
    stage.addEventListener('touchend', e => {
        if (!touchStart) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - touchStart.x, dy = t.clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.6) return;
        if (dx < 0 && current < shelfCount) showShelf(current + 1, 1);
        if (dx > 0 && current > 1) showShelf(current - 1, -1);
    }, {passive: true});

    // ------------------------------------------------------------
    // Ficha al pasar el ratón / primer toque
    // ------------------------------------------------------------
    const money = m => m.rewardAmount
        ? `${Number(m.rewardAmount).toLocaleString('es-CL')} ${String(m.rewardCoin || 'po').toUpperCase()} ${m.rewardMode === 'total' ? 'en total, a repartir' : 'por personaje'}`
        : '';
    function rewardLines(m) {
        const lines = [];
        if (money(m)) lines.push(money(m));
        if (m.generalReward) lines.push(m.generalReward);
        (m.participants || []).forEach(p => { if (p.reward) lines.push(`${p.name}: ${p.reward}`); });
        return lines;
    }
    function cardHtml(m) {
        const people = (m.participants || []).map(p => `<li>${esc(p.name)}</li>`).join('');
        const rewards = rewardLines(m).map(r => `<li>${esc(r)}</li>`).join('');
        return `<span class="book-card-arrow" aria-hidden="true"></span>
            <h3>${esc(m.name)}</h3>
            <p class="book-card-meta">${esc(G.formatDate(m.date))}${m.type ? ` · ${esc(m.type)}` : ''}</p>
            <div class="book-card-block"><strong>Participantes</strong>${people ? `<ul>${people}</ul>` : '<p>Sin participantes registrados.</p>'}</div>
            <div class="book-card-block"><strong>Recompensa</strong>${rewards ? `<ul>${rewards}</ul>` : '<p>Sin recompensa registrada.</p>'}</div>
            <button type="button" class="book-card-open" data-open-book="${esc(m.id)}">Abrir el libro</button>`;
    }

    let cardFor = null;     // id del libro con ficha
    let pinned = false;     // ficha fijada (toque o clic de teclado)
    let hideTimer = null;

    function placeCard(btn) {
        const r = btn.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        const cw = card.offsetWidth, ch = card.offsetHeight;
        const gap = 12, edge = 8;
        let left, top, side;
        if (vw >= 600) {
            side = r.right + gap + cw <= vw - edge ? 'right' : 'left';
            left = side === 'right' ? r.right + gap : r.left - gap - cw;
            top = r.top + r.height / 2 - ch / 2;
        } else {
            // Teléfono: la ficha va encima o debajo del libro, a lo ancho.
            side = r.bottom + gap + ch <= vh - edge || r.top - gap - ch < edge ? 'below' : 'above';
            top = side === 'below' ? r.bottom + gap : r.top - gap - ch;
            left = r.left + r.width / 2 - cw / 2;
        }
        left = Math.max(edge, Math.min(left, vw - cw - edge));
        top = Math.max(edge, Math.min(top, vh - ch - edge));
        card.style.left = `${Math.round(left)}px`;
        card.style.top = `${Math.round(top)}px`;
        card.dataset.side = side;
        // La flecha apunta al libro aunque la ficha se haya desplazado.
        if (side === 'right' || side === 'left') {
            card.style.setProperty('--arrow', `${Math.round(Math.max(18, Math.min(ch - 18, r.top + r.height / 2 - top)))}px`);
        } else {
            card.style.setProperty('--arrow', `${Math.round(Math.max(18, Math.min(cw - 18, r.left + r.width / 2 - left)))}px`);
        }
    }

    function showCard(btn, pin = false) {
        const m = byId.get(btn.dataset.book);
        if (!m || !card) return;
        clearTimeout(hideTimer);
        if (cardFor !== btn.dataset.book) {
            stage.querySelectorAll('.book.is-active').forEach(b => b.classList.remove('is-active'));
            card.innerHTML = cardHtml(m);
            cardFor = btn.dataset.book;
        }
        btn.classList.add('is-active');
        pinned = pin || pinned;
        card.hidden = false;
        card.classList.toggle('pinned', pinned);
        placeCard(btn);
        requestAnimationFrame(() => card.classList.add('show'));
    }

    function hideCard(now = false) {
        clearTimeout(hideTimer);
        const done = () => {
            if (!card) return;
            card.classList.remove('show', 'pinned');
            card.hidden = true;
            stage.querySelectorAll('.book.is-active').forEach(b => b.classList.remove('is-active'));
            cardFor = null;
            pinned = false;
        };
        if (now) done(); else hideTimer = setTimeout(done, 160);
    }

    const activeBook = () => cardFor ? stage.querySelector(`.book[data-book="${CSS.escape(cardFor)}"]`) : null;

    stage.addEventListener('pointerover', e => {
        if (e.pointerType !== 'mouse') return;
        const btn = e.target.closest('.book');
        if (btn && !(pinned && cardFor !== btn.dataset.book)) { pinned = false; showCard(btn); }
    });
    stage.addEventListener('pointerout', e => {
        if (e.pointerType !== 'mouse' || pinned) return;
        const btn = e.target.closest('.book');
        if (btn && !btn.contains(e.relatedTarget)) hideCard();
    });
    card?.addEventListener('pointerenter', () => clearTimeout(hideTimer));
    card?.addEventListener('pointerleave', e => { if (e.pointerType === 'mouse' && !pinned) hideCard(); });

    let lastPointer = 'mouse';
    stage.addEventListener('pointerdown', e => { lastPointer = e.pointerType || 'mouse'; });
    stage.addEventListener('click', e => {
        const btn = e.target.closest('.book');
        if (!btn) return;
        // Ratón: abrir directamente. Dedo o teclado: el primer toque enseña la ficha.
        const keyboard = e.detail === 0;
        if (lastPointer === 'mouse' && !keyboard) { openBook(btn.dataset.book); return; }
        if (cardFor === btn.dataset.book && pinned) { openBook(btn.dataset.book); return; }
        showCard(btn, true);
        lastPointer = 'mouse';
    });
    stage.addEventListener('focusin', e => {
        const btn = e.target.closest('.book');
        if (btn && btn.matches(':focus-visible')) showCard(btn);
    });
    stage.addEventListener('focusout', e => {
        if (!pinned && !card?.contains(e.relatedTarget) && !e.relatedTarget?.closest?.('.book')) hideCard();
    });
    // Flechas del teclado para recorrer los libros.
    stage.addEventListener('keydown', e => {
        const btn = e.target.closest('.book');
        if (!btn || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
        const all = [...stage.querySelectorAll('.book')];
        let i = all.indexOf(btn);
        if (e.key === 'ArrowLeft') i--; else if (e.key === 'ArrowRight') i++;
        else if (e.key === 'Home') i = 0; else i = all.length - 1;
        if (i < 0 && current > 1) { e.preventDefault(); showShelf(current - 1, -1).then(() => [...stage.querySelectorAll('.book')].pop()?.focus()); return; }
        if (i >= all.length && current < shelfCount) { e.preventDefault(); showShelf(current + 1, 1).then(() => stage.querySelector('.book')?.focus()); return; }
        if (all[i]) { e.preventDefault(); all[i].focus(); }
    });

    card?.addEventListener('click', e => {
        const open = e.target.closest('[data-open-book]');
        if (open) openBook(open.dataset.openBook);
    });
    // Tocar fuera cierra la ficha fijada.
    document.addEventListener('pointerdown', e => {
        if (!cardFor || card.contains(e.target) || e.target.closest?.('.book')) return;
        hideCard(true);
    });
    window.addEventListener('resize', () => { const b = activeBook(); if (b && !card.hidden) placeCard(b); else hideCard(true); });
    window.addEventListener('scroll', () => {
        if (card.hidden) return;
        const b = activeBook();
        const r = b?.getBoundingClientRect();
        if (b && r.bottom > 0 && r.top < window.innerHeight) placeCard(b); else hideCard(true);
    }, {passive: true});

    // ------------------------------------------------------------
    // Libro abierto: la misión completa
    // ------------------------------------------------------------
    function openBook(id) {
        const m = byId.get(String(id));
        if (!m || !modal) return;
        hideCard(true);
        const people = (m.participants || []).map(p => `
            <li><button type="button" class="participant-chip" data-participant-id="${esc(p.id)}">
                ${p.portrait ? `<img src="${esc(p.portrait)}" alt="" loading="lazy">` : ''}
                <span><strong>${esc(p.name)}</strong>${p.class || p.level ? `<small>${esc([p.class, p.level ? `nivel ${p.level}` : ''].filter(Boolean).join(' · '))}</small>` : ''}${p.reward ? `<small>Recompensa: ${esc(p.reward)}</small>` : ''}${p.notes ? `<small class="participant-note">${esc(p.notes)}</small>` : ''}</span>
            </button></li>`).join('');
        const where = m.book ? `Estantería ${roman(m.book.shelf)} · Libro ${m.book.position}` : 'Aún sin libro en la biblioteca';
        const rewards = [
            money(m) ? `<li><strong>Pago en la Banca:</strong> ${esc(money(m))}</li>` : '',
            m.generalReward ? `<li><strong>Recompensa general:</strong> ${esc(m.generalReward)}</li>` : '',
        ].join('');
        modalContent.innerHTML = `
            <div class="open-tome ${m.book ? `tome--m${m.book.model}` : ''}">
                <section class="tome-page tome-left">
                    <p class="overline">${esc(where)}</p>
                    <h2 id="bookModalTitle">${esc(m.name)}</h2>
                    <p class="mission-meta">${esc(G.formatDate(m.date))}${m.type ? ` · ${esc(m.type)}` : ''}</p>
                    <span class="mission-status">${esc(m.state || 'Registrada')}</span>
                    ${m.description ? `<div class="mission-description">${esc(m.description)}</div>` : '<p class="tome-quiet">No se escribió una crónica para esta misión.</p>'}
                </section>
                <section class="tome-page tome-right">
                    <h3>Participantes</h3>
                    ${people ? `<ul class="tome-people">${people}</ul>` : '<p class="tome-quiet">Sin participantes registrados.</p>'}
                    <h3>Recompensa</h3>
                    ${rewards ? `<ul class="tome-rewards">${rewards}</ul>` : (m.participants || []).some(p => p.reward) ? '<p class="tome-quiet">Cada participante tiene su recompensa anotada junto a su nombre.</p>' : '<p class="tome-quiet">Sin recompensa registrada.</p>'}
                    ${G.openMissionEditor ? `<div class="tome-actions"><button type="button" class="ink-button" data-book-edit="${esc(m.id)}">Editar misión</button></div>` : ''}
                </section>
            </div>`;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        G.setModalOpen(true);
        store.set('guildLibraryOpenBook', String(m.id));
        setTimeout(() => modal.querySelector('.modal-close')?.focus(), 30);
    }
    let returnFocus = null;
    function closeBook() {
        if (!modal?.classList.contains('open')) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        G.setModalOpen(false);
        store.del('guildLibraryOpenBook');
        returnFocus?.focus?.();
        returnFocus = null;
    }
    modal?.addEventListener('click', e => {
        if (e.target.closest('[data-close-book]')) { closeBook(); return; }
        const chip = e.target.closest('[data-participant-id]');
        if (chip) {
            const c = G.characters.find(x => x.id === chip.dataset.participantId);
            if (c) G.openCharacter(c);
            return;
        }
        const edit = e.target.closest('[data-book-edit]');
        if (edit && G.openMissionEditor) {
            const m = byId.get(edit.dataset.bookEdit);
            // Al guardar la página se recarga: se vuelve a esta estantería y a este libro.
            store.set(RETURN_KEY, String(m.id));
            G.openMissionEditor(m);
        }
    });
    stage.addEventListener('click', e => { const b = e.target.closest('.book'); if (b) returnFocus = b; }, true);
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if ($('#adminModal')?.classList.contains('open') || $('#characterModal')?.classList.contains('open')) return;
        if (modal?.classList.contains('open')) { closeBook(); return; }
        if (cardFor) hideCard(true);
        if (!results.hidden) closeResults();
    });

    // ------------------------------------------------------------
    // Buscador: encuentra el libro, va a su estantería y lo destaca
    // ------------------------------------------------------------
    const haystack = m => normalize([m.name, m.type, ...(m.participants || []).map(p => p.name)].join(' '));
    let found = [];
    let cursor = -1;
    function closeResults() { results.hidden = true; results.innerHTML = ''; cursor = -1; search?.setAttribute('aria-expanded', 'false'); }
    function renderResults() {
        if (listMode) { closeResults(); return; }
        const q = normalize(search.value);
        if (!q) { closeResults(); return; }
        const words = q.split(/\s+/);
        found = G.missions.filter(m => { const h = haystack(m); return words.every(w => h.includes(w)); })
            .sort((a, b) => (a.book ? 0 : 1) - (b.book ? 0 : 1) || (a.book?.position || 0) - (b.book?.position || 0))
            .slice(0, 8);
        cursor = -1;
        results.innerHTML = found.length ? found.map((m, i) => {
            const who = (m.participants || []).map(p => p.name).join(', ');
            return `<button type="button" role="option" class="library-result" data-result="${i}" id="libraryResult${i}">
                <strong>${esc(m.name)}</strong>
                <small>${m.book ? `Estantería ${roman(m.book.shelf)} · libro ${m.book.position}` : `${esc(m.state || 'Sin estado')} · aún sin libro`}${who ? ` · ${esc(who)}` : ''}</small>
            </button>`;
        }).join('') : '<p class="library-noresult">Ningún libro coincide.</p>';
        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }
    function goToMission(m) {
        closeResults();
        if (!m.book) { openBook(m.id); return; }
        const target = m.book.shelf;
        const dir = target === current ? 0 : (target > current ? 1 : -1);
        showShelf(target, dir).then(() => setTimeout(() => {
            const btn = stage.querySelector(`.book[data-book="${CSS.escape(String(m.id))}"]`);
            if (!btn) return;
            btn.scrollIntoView({block: 'nearest', inline: 'center', behavior: reduceMotion.matches ? 'auto' : 'smooth'});
            btn.classList.remove('is-found');
            void btn.offsetWidth;
            btn.classList.add('is-found');
            btn.focus({preventScroll: true});
            setTimeout(() => btn.classList.remove('is-found'), 2600);
        }, dir ? 240 : 0));
    }
    search?.setAttribute('aria-expanded', 'false');
    search?.addEventListener('input', renderResults);
    search?.addEventListener('focus', () => { if (search.value) renderResults(); });
    search?.addEventListener('keydown', e => {
        if (results.hidden || listMode) return;
        const opts = [...results.querySelectorAll('[data-result]')];
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!opts.length) return;
            cursor = (cursor + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
            opts.forEach((o, i) => o.classList.toggle('is-cursor', i === cursor));
            search.setAttribute('aria-activedescendant', opts[cursor].id);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const m = found[cursor >= 0 ? cursor : 0];
            if (m) goToMission(m);
        }
    });
    results?.addEventListener('click', e => {
        const opt = e.target.closest('[data-result]');
        if (opt) goToMission(found[Number(opt.dataset.result)]);
    });
    document.addEventListener('pointerdown', e => {
        if (!results.hidden && !e.target.closest('.library-search')) closeResults();
    });

    // ------------------------------------------------------------
    // Biblioteca / lista clásica (la lista de siempre sigue disponible)
    // ------------------------------------------------------------
    let listMode = local.get(MODE_KEY) === 'list';
    function applyMode() {
        stage.hidden = listMode;
        nav.bar.hidden = listMode;
        listWrap.hidden = !listMode;
        modeBtn.textContent = listMode ? 'Ver biblioteca' : 'Ver como lista';
        modeBtn.setAttribute('aria-pressed', String(listMode));
        search.placeholder = listMode ? 'Filtrar misiones o personajes…' : 'Buscar misión o personaje…';
        if (listMode) { closeResults(); hideCard(true); search.dispatchEvent(new Event('input')); }
    }
    modeBtn?.addEventListener('click', () => {
        listMode = !listMode;
        local.set(MODE_KEY, listMode ? 'list' : 'books');
        applyMode();
    });

    // ------------------------------------------------------------
    // Arranque
    // ------------------------------------------------------------
    applyMode();
    showShelf(current);

    // Volver al libro tras editar la misión (la página se recarga al guardar),
    // o abrir uno pedido por enlace: archivosdelgremio/#mision=ID
    const back = store.get(RETURN_KEY);
    const hashId = location.hash.startsWith('#mision=') ? decodeURIComponent(location.hash.slice(8)) : '';
    const wanted = back || hashId;
    if (wanted && byId.has(wanted)) {
        store.del(RETURN_KEY);
        store.del('guildReturnView');
        G.switchView('missions');
        const m = byId.get(wanted);
        if (m.book) showShelf(m.book.shelf);
        openBook(m.id);
    }
})();
