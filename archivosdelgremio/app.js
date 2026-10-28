(() => {
    'use strict';

    const FAVOR_BASE = new URL('../', document.currentScript.src).pathname; // raíz de la web, sea cual sea la carpeta
    const S = window.GUILD_STATE || {};
    const characters = Array.isArray(S.characters) ? S.characters : [];
    const players = Array.isArray(S.players) ? S.players : [];
    const missions = Array.isArray(S.missions) ? S.missions : [];
    const isAdmin = !!S.isAdmin;
    const isLogged = !!S.isLogged;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));
    const normalize = value => String(value ?? '')
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const toast = $('#toast');
    let toastTimer;
    function showToast(message, bad = false) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('bad', bad);
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
    }

    async function post(payload) {
        const res = await fetch(S.api || 'api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({...payload, csrf: S.csrf})
        });
        let data = {};
        try { data = await res.json(); } catch (_) {}
        if (!res.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la acción.');
        return data;
    }


    async function postForm(formData) {
        formData.set('csrf', S.csrf || '');
        const res = await fetch(S.api || 'api.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        let data = {};
        try { data = await res.json(); } catch (_) {}
        if (!res.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la acción.');
        return data;
    }

    function resolveAssetSrc(src) {
        const value = String(src || '').trim().replace(/\\/g, '/');
        if (!value) return '';
        if (/^(https?:|data:)/i.test(value)) return value;

        // Todas las imágenes de jugadores y personajes viven en media/ (fase 2).
        let path = value.startsWith(FAVOR_BASE) ? value.slice(FAVOR_BASE.length) : value;
        if (path.startsWith('/')) return path;
        path = path.replace(/^(?:bancadigital\/)?(icons|characters)\//, 'media/$1/');
        return path.startsWith('media/') ? FAVOR_BASE + path : value;
    }

    // Tarjetas y listas usan la miniatura (thumb); el expediente abierto, el retrato entero.
    function imageHtml(character, extra = '', full = false) {
        const src = resolveAssetSrc((full ? '' : character?.thumb) || character?.portrait || character?.icon || '');
        if (!src) return `<div class="placeholder-art ${extra}" aria-hidden="true">✦</div>`;
        return `<img class="${extra}" src="${esc(src)}" alt="${esc(character.name || '')}" loading="lazy" onerror="this.outerHTML='<div class=&quot;placeholder-art&quot; aria-hidden=&quot;true&quot;>✦</div>'">`;
    }

    // ------------------------------------------------------------
    // Navegación por secciones
    // ------------------------------------------------------------
    function switchView(name) {
        $$('[data-view-panel]').forEach(panel => {
            const active = panel.dataset.viewPanel === name;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
        $$('.book-tab[data-view]').forEach(btn => btn.classList.toggle('active', btn.dataset.view === name));
        if (name === 'charts') renderCharts();
        if (name === 'missions') renderMissions();
        if (name === 'admin' && isAdmin) renderAdminLists();
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    $$('.book-tab[data-view]').forEach(btn => btn.addEventListener('click', () => switchView(btn.dataset.view)));

    // ------------------------------------------------------------
    // Personajes
    // ------------------------------------------------------------
    function isActiveCharacter(character) {
        return normalize(character?.state || 'Activo') === 'activo';
    }

    const grid = $('#characterGrid');
    const searchInput = $('#searchInput');
    const classFilter = $('#classFilter');
    const playerFilter = $('#playerFilter');
    const categoryFilter = $('#categoryFilter');
    const resultCount = $('#resultCount');
    const emptyState = $('#emptyState');
    const characterPrev = $('#characterPrev');
    const characterNext = $('#characterNext');
    const characterPageLabel = $('#characterPageLabel');
    const characterPageDots = $('#characterPageDots');
    const CHARACTER_PAGE_SIZE = 6;
    let characterPage = 0;

    function uniqueSorted(values) {
        return [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es', {sensitivity: 'base'}));
    }
    function fillSelect(select, values) {
        if (!select) return;
        values.forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        });
    }
    fillSelect(classFilter, uniqueSorted(characters.map(c => c.class)));
    fillSelect(playerFilter, uniqueSorted(characters.map(c => c.player)));

    function characterCard(c) {
        const subtitleBase = [c.race, c.class].filter(Boolean).join(' · ') || 'Expediente sin clasificar';
        const subtitle = c.category === 'NPC' ? `NPC · ${subtitleBase}` : subtitleBase;
        const inactiveClass = isActiveCharacter(c) ? '' : ' is-inactive';
        return `
            <article class="character-card${inactiveClass}" tabindex="0" role="button" data-character-id="${esc(c.id)}" aria-label="Abrir expediente de ${esc(c.name)}">
                <div class="card-art">${imageHtml(c)}</div>
                <div class="card-caption">
                    <h3 class="card-name">${esc(c.name)}</h3>
                    <p class="card-subtitle">${esc(subtitle)}</p>
                </div>
            </article>`;
    }

    function characterMatches(c) {
        const q = normalize(searchInput?.value || '');
        if (classFilter?.value && c.class !== classFilter.value) return false;
        if (playerFilter?.value && c.player !== playerFilter.value) return false;
        if (categoryFilter?.value && (c.category || 'Personaje') !== categoryFilter.value) return false;
        if (!q) return true;
        return normalize([c.name, c.shortName, c.player, c.handle, c.race, c.raceType, c.class, c.threat, c.state, c.category].join(' ')).includes(q);
    }

    function filteredCharacters() {
        return characters.filter(characterMatches);
    }

    function animateCharacterPage(direction = 0) {
        if (!grid || !direction) return;
        grid.classList.remove('page-forward', 'page-back');
        void grid.offsetWidth;
        grid.classList.add(direction > 0 ? 'page-forward' : 'page-back');
    }

    function renderCharacterPager(total, pageCount) {
        const hasCharacters = total > 0;
        if (characterPrev) {
            characterPrev.hidden = !hasCharacters || pageCount <= 1;
            characterPrev.disabled = characterPage <= 0;
        }
        if (characterNext) {
            characterNext.hidden = !hasCharacters || pageCount <= 1;
            characterNext.disabled = characterPage >= pageCount - 1;
        }
        if (characterPageLabel) {
            characterPageLabel.textContent = hasCharacters ? `Página ${characterPage + 1} de ${pageCount}` : 'Sin páginas';
        }
        if (characterPageDots) {
            characterPageDots.innerHTML = hasCharacters && pageCount > 1
                ? Array.from({length: pageCount}, (_, i) => `
                    <button type="button" class="catalog-dot${i === characterPage ? ' active' : ''}" data-character-page="${i}" aria-label="Ir a la página ${i + 1}" ${i === characterPage ? 'aria-current="page"' : ''}></button>`).join('')
                : '';
        }
    }

    function renderCharacters(direction = 0, resetPage = false) {
        if (!grid) return;
        const visible = filteredCharacters();
        const pageCount = Math.max(1, Math.ceil(visible.length / CHARACTER_PAGE_SIZE));
        if (resetPage) characterPage = 0;
        characterPage = Math.min(Math.max(characterPage, 0), pageCount - 1);

        const start = characterPage * CHARACTER_PAGE_SIZE;
        const current = visible.slice(start, start + CHARACTER_PAGE_SIZE);
        grid.innerHTML = current.map(characterCard).join('');
        animateCharacterPage(direction);

        if (resultCount) {
            const shownFrom = visible.length ? start + 1 : 0;
            const shownTo = visible.length ? Math.min(start + CHARACTER_PAGE_SIZE, visible.length) : 0;
            const filterType = categoryFilter?.value || '';
            const singular = filterType === 'NPC' ? 'NPC' : (filterType === 'Personaje' ? 'personaje' : 'expediente');
            const plural = filterType === 'NPC' ? 'NPC' : (filterType === 'Personaje' ? 'personajes' : 'expedientes');
            resultCount.textContent = visible.length
                ? `${visible.length} ${visible.length === 1 ? singular : plural} · mostrando ${shownFrom}–${shownTo}`
                : `0 ${plural} en el índice`;
        }
        if (emptyState) emptyState.hidden = visible.length > 0;
        renderCharacterPager(visible.length, pageCount);
    }

    [searchInput, classFilter, playerFilter, categoryFilter].forEach(control => control?.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', () => renderCharacters(0, true)));
    $('#clearFilters')?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (classFilter) classFilter.value = '';
        if (playerFilter) playerFilter.value = '';
        if (categoryFilter) categoryFilter.value = '';
        renderCharacters(0, true);
    });

    characterPrev?.addEventListener('click', () => {
        if (characterPage <= 0) return;
        characterPage -= 1;
        renderCharacters(-1);
    });
    characterNext?.addEventListener('click', () => {
        const pageCount = Math.ceil(filteredCharacters().length / CHARACTER_PAGE_SIZE);
        if (characterPage >= pageCount - 1) return;
        characterPage += 1;
        renderCharacters(1);
    });
    characterPageDots?.addEventListener('click', e => {
        const dot = e.target.closest('[data-character-page]');
        if (!dot) return;
        const nextPage = Number(dot.dataset.characterPage);
        if (!Number.isInteger(nextPage) || nextPage === characterPage) return;
        const direction = nextPage > characterPage ? 1 : -1;
        characterPage = nextPage;
        renderCharacters(direction);
    });

    const characterModal = $('#characterModal');
    const characterModalContent = $('#characterModalContent');
    const infoBox = (label, value) => value ? `<div class="info-box"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>` : '';
    const statBox = (label, value) => `<div class="stat-box"><span>${esc(label)}</span><strong>${value !== '' ? esc(value) : '—'}</strong></div>`;

    // Al abrir un modal la página pierde su barra de scroll: guardamos su hueco
    // para que el libro y los marcadores de fondo no se ensanchen ni se muevan.
    function setModalOpen(open) {
        const root = document.documentElement;
        if (open && !document.body.classList.contains('modal-open')) {
            root.classList.toggle('keep-gutter', window.innerWidth > root.clientWidth);
        }
        document.body.classList.toggle('modal-open', open);
        if (!open) root.classList.remove('keep-gutter');
    }

    function openCharacter(c) {
        if (!characterModal || !characterModalContent || !c) return;
        const raceLine = [c.race, c.raceType].filter(Boolean).join(' · ');
        const classLine = [c.class, c.level ? `Nivel ${c.level}` : ''].filter(Boolean).join(' · ');
        const text = c.biography || c.description;
        characterModalContent.innerHTML = `
            <div class="folio-grid">
                <div class="folio-art${isActiveCharacter(c) ? '' : ' is-inactive'}">${imageHtml(c, '', true)}</div>
                <div class="folio-content">
                    <p class="folio-kicker">Archivo de ${esc(c.player || 'miembro desconocido')}</p>
                    <h2 class="folio-name" id="characterModalName">${esc(c.name)}</h2>
                    <p class="folio-subtitle">${esc([raceLine, classLine].filter(Boolean).join(' — ') || 'Información no registrada')}</p>
                    <div class="status-line">
                        ${c.category === 'NPC' ? '<span class="wax-label">NPC</span>' : ''}
                        ${c.state ? `<span class="wax-label state">${esc(c.state)}</span>` : ''}
                        ${c.threat ? `<span class="wax-label">Amenaza · ${esc(c.threat)}</span>` : ''}
                    </div>
                    <div class="info-grid">
                        ${infoBox('Jugador', c.player)}
                        ${infoBox('Sexo', c.sex)}
                        ${infoBox('Edad', c.age)}
                        ${infoBox('Altura', c.height ? `${c.height} m` : '')}
                        ${infoBox('Raza', c.race)}
                        ${infoBox('Tipo de raza', c.raceType)}
                        ${infoBox('Clase', c.class)}
                        ${infoBox('Dotes', c.feats)}
                    </div>
                    <h3 class="section-title">Características</h3>
                    <div class="stats-grid">
                        ${statBox('FUE', c.str)}${statBox('DES', c.dex)}${statBox('CON', c.con)}
                        ${statBox('INT', c.int)}${statBox('SAB', c.wis)}${statBox('CAR', c.cha)}
                    </div>
                    ${text ? `<h3 class="section-title">Notas del expediente</h3><div class="folio-text">${esc(text)}</div>` : ''}
                    <div class="folio-actions">
                        <a class="folio-action primary" href="${FAVOR_BASE}bancadigital/?character=${encodeURIComponent(c.id)}">◈ Ir a su Banca</a>
                        <a class="folio-action" href="${FAVOR_BASE}inventario/?character=${encodeURIComponent(c.id)}">▣ Inventario</a>
                        <a class="folio-action" href="relaciones.php?character=${encodeURIComponent(c.id)}">⌁ Relaciones</a>
                        ${c.hasSheet ? `<a class="folio-action" href="hoja.php?id=${encodeURIComponent(c.id)}">📜 Hoja</a>` : ''}
                    </div>
                    ${c.updated ? `<p class="folio-footer">Última anotación: ${esc(c.updated)}</p>` : ''}
                </div>
            </div>`;
        // Si el retrato aún no ha llegado, al cargar no debe agrandar el folio
        // (movía "Ir a su Banca" bajo el cursor y sacaba una barra de scroll).
        const portrait = $('.folio-art img', characterModalContent);
        if (portrait && !portrait.naturalWidth) {
            const art = portrait.parentElement;
            art.classList.add('is-pending');
            // Cuando llega se muestra entero, pero nunca bajo el cursor: si está sobre los
            // botones, espera a que salga de ellos.
            portrait.addEventListener('load', () => {
                const actions = $('.folio-actions', characterModalContent);
                const release = () => art.classList.remove('is-pending');
                if (actions?.matches(':hover')) actions.addEventListener('pointerleave', release, { once: true });
                else release();
            }, { once: true });
        }
        characterModal.classList.add('open');
        characterModal.setAttribute('aria-hidden', 'false');
        setModalOpen(true);
        history.replaceState(null, '', `#personaje=${encodeURIComponent(c.id)}`);
    }
    function closeCharacter() {
        characterModal?.classList.remove('open');
        characterModal?.setAttribute('aria-hidden', 'true');
        setModalOpen(false);
        if (location.hash.startsWith('#personaje=')) history.replaceState(null, '', location.pathname + location.search);
    }
    grid?.addEventListener('click', e => {
        const card = e.target.closest('[data-character-id]');
        if (card) openCharacter(characters.find(c => c.id === card.dataset.characterId));
    });
    grid?.addEventListener('keydown', e => {
        if (!['Enter', ' '].includes(e.key)) return;
        const card = e.target.closest('[data-character-id]');
        if (!card) return;
        e.preventDefault();
        openCharacter(characters.find(c => c.id === card.dataset.characterId));
    });
    $$('[data-close-character]').forEach(el => el.addEventListener('click', closeCharacter));

    // ------------------------------------------------------------
    // Gráficos
    // ------------------------------------------------------------
    let chartsRendered = false;
    function countBy(field) {
        const map = new Map();
        characters.forEach(c => {
            const value = String(c[field] || '').trim();
            if (!value) return;
            map.set(value, (map.get(value) || 0) + 1);
        });
        return [...map.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'es'));
    }
    function chartHtml(rows) {
        if (!rows.length) return '<div class="chart-empty">No hay datos suficientes.</div>';
        const max = Math.max(...rows.map(([, n]) => n));
        return rows.map(([label, count]) => `
            <div class="chart-row">
                <div class="chart-label" title="${esc(label)}">${esc(label)}</div>
                <div class="chart-track"><div class="chart-bar" style="width:${Math.max(4, (count / max) * 100)}%"></div></div>
                <div class="chart-count">${count}</div>
            </div>`).join('');
    }
    function normalizedHeightMeters(value) {
        const n = Number(value);
        if (!Number.isFinite(n) || n <= 0) return null;
        if (n > 20) return n / 100;
        return n;
    }

    function heightPersonHtml(c, heightPx) {
        const inactiveClass = isActiveCharacter(c) ? '' : ' is-inactive';
        // En el comparador usamos PortraitUrl, no IconUrl: los iconos suelen ser
        // recortes de rostro y no sirven para representar una estatura.
        const src = c.portrait || '';
        const fallback = `<span class="height-fallback" ${src ? 'hidden' : ''} aria-hidden="true">
            <svg viewBox="0 0 100 300" role="presentation" focusable="false">
                <circle cx="50" cy="28" r="24"></circle>
                <path d="M38 54 C28 63 23 83 22 111 L7 164 C4 174 9 181 17 180 L34 128 L36 181 L30 292 L47 292 L51 195 L56 292 L73 292 L66 181 L68 128 L84 180 C93 181 97 174 94 164 L78 111 C77 83 72 63 62 54 Z"></path>
            </svg>
        </span>`;
        const portrait = src ? `<img class="height-photo" src="${esc(src)}" alt="" decoding="async" data-height-photo>` : '';
        const cm = Math.round(normalizedHeightMeters(c.height) * 100);
        return `
            <button type="button" class="height-person${inactiveClass}" data-height-character="${esc(c.id)}" data-height-px="${heightPx}" style="--person-height:${heightPx}px" title="${esc(c.name)} · ${cm} cm">
                <span class="height-value">${cm} cm</span>
                <span class="height-portrait" aria-hidden="true">${portrait}${fallback}</span>
                <span class="height-name">${esc(c.name)}</span>
                <span class="height-player">${esc(c.player || '')}</span>
            </button>`;
    }

    // Devuelve el rectángulo de píxeles realmente visibles del retrato. Esto
    // evita que un PNG/WebP con margen transparente altere la escala.
    // Si la imagen es externa y el navegador bloquea la lectura del canvas,
    // simplemente usamos sus dimensiones completas.
    function visibleImageBounds(img) {
        const nw = img.naturalWidth || 0;
        const nh = img.naturalHeight || 0;
        if (!nw || !nh) return {left: 0, top: 0, right: 1, bottom: 1};

        const sampleH = Math.min(420, nh);
        const sampleW = Math.max(1, Math.round(nw * (sampleH / nh)));
        const canvas = document.createElement('canvas');
        canvas.width = sampleW;
        canvas.height = sampleH;
        const ctx = canvas.getContext('2d', {willReadFrequently: true});
        if (!ctx) return {left: 0, top: 0, right: 1, bottom: 1};

        try {
            ctx.clearRect(0, 0, sampleW, sampleH);
            ctx.drawImage(img, 0, 0, sampleW, sampleH);
            const data = ctx.getImageData(0, 0, sampleW, sampleH).data;
            let minX = sampleW, minY = sampleH, maxX = -1, maxY = -1;
            // alpha > 18 ignora píxeles casi transparentes del antialiasing.
            for (let y = 0; y < sampleH; y += 1) {
                for (let x = 0; x < sampleW; x += 1) {
                    if (data[(y * sampleW + x) * 4 + 3] <= 18) continue;
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
            if (maxX < minX || maxY < minY) return {left: 0, top: 0, right: 1, bottom: 1};
            return {
                left: minX / sampleW,
                top: minY / sampleH,
                right: (maxX + 1) / sampleW,
                bottom: (maxY + 1) / sampleH
            };
        } catch (_) {
            return {left: 0, top: 0, right: 1, bottom: 1};
        }
    }

    function fitHeightPhoto(img) {
        const person = img.closest('.height-person');
        const frame = img.closest('.height-portrait');
        if (!person || !frame || !img.naturalWidth || !img.naturalHeight) return;

        const targetHeight = Number(person.dataset.heightPx || 0);
        if (!targetHeight) return;

        const b = visibleImageBounds(img);
        const contentH = Math.max(.05, b.bottom - b.top);
        const contentW = Math.max(.05, b.right - b.left);
        const naturalRatio = img.naturalWidth / img.naturalHeight;

        // La altura VISIBLE del personaje es exactamente la altura calculada
        // por la regla del gráfico. El tamaño del archivo deja de importar.
        const fullRenderedHeight = targetHeight / contentH;
        const fullRenderedWidth = fullRenderedHeight * naturalRatio;
        const visibleWidth = fullRenderedWidth * contentW;

        frame.style.width = `${Math.max(20, visibleWidth)}px`;
        frame.style.height = `${targetHeight}px`;
        img.style.width = `${fullRenderedWidth}px`;
        img.style.height = `${fullRenderedHeight}px`;
        img.style.left = `${-fullRenderedWidth * b.left}px`;
        img.style.top = `${-fullRenderedHeight * b.top}px`;

        // Cada personaje reserva el ancho real de su ilustración para que armas,
        // alas o capas no se monten encima del siguiente retrato.
        const slot = Math.max(76, Math.ceil(visibleWidth + 18));
        person.style.width = `${slot}px`;
        person.style.flexBasis = `${slot}px`;
        person.classList.add('height-image-ready');
    }

    function resizeHeightStage() {
        const stage = $('#heightChart');
        const people = $('.height-people', stage || document);
        const scroll = $('#heightScroll');
        if (!stage || !people) return;
        const needed = Math.ceil(people.scrollWidth + 92);
        const viewport = Math.max(900, (scroll?.clientWidth || 0));
        stage.style.width = `${Math.max(viewport, needed)}px`;
    }

    function bindHeightPhotos() {
        const stage = $('#heightChart');
        if (!stage) return;
        $$('[data-height-photo]', stage).forEach(img => {
            const fit = () => {
                fitHeightPhoto(img);
                requestAnimationFrame(resizeHeightStage);
            };
            if (img.complete && img.naturalWidth) fit();
            else img.addEventListener('load', fit, {once: true});
            img.addEventListener('error', () => {
                img.hidden = true;
                const fallback = img.nextElementSibling;
                if (fallback) fallback.hidden = false;
                requestAnimationFrame(resizeHeightStage);
            }, {once: true});
        });
        requestAnimationFrame(resizeHeightStage);
    }

    function renderHeightChart() {
        const el = $('#heightChart');
        if (!el) return;
        const rows = characters
            .filter(isActiveCharacter)
            .map(c => ({...c, _h: normalizedHeightMeters(c.height)}))
            .filter(c => c._h && c._h >= .4 && c._h <= 3.5)
            .sort((a, b) => a._h - b._h || a.name.localeCompare(b.name, 'es'));

        if (!rows.length) {
            el.innerHTML = '<div class="chart-empty height-empty">No hay alturas registradas.</div>';
            return;
        }

        const maxCm = Math.max(200, Math.ceil(Math.max(...rows.map(c => c._h * 100)) / 10) * 10 + 10);
        const plotHeight = Math.max(560, Math.round(maxCm * 3));
        const pxPerCm = plotHeight / maxCm;
        el.style.setProperty('--height-plot-px', `${plotHeight}px`);
        el.style.setProperty('--height-max-cm', String(maxCm));

        const ticks = [];
        for (let cm = 0; cm <= maxCm; cm += 10) {
            ticks.push(`<div class="height-grid-line" style="bottom:${cm * pxPerCm}px"><span>${cm} cm</span></div>`);
        }
        const people = rows.map(c => heightPersonHtml(c, Math.round(c._h * 100 * pxPerCm))).join('');
        el.innerHTML = `<div class="height-grid">${ticks.join('')}</div><div class="height-people">${people}</div>`;
        bindHeightPhotos();
    }

    function renderCharts() {
        if (chartsRendered) return;
        const classChart = $('#classChart');
        const raceChart = $('#raceChart');
        if (classChart) classChart.innerHTML = chartHtml(countBy('class'));
        if (raceChart) raceChart.innerHTML = chartHtml(countBy('race'));
        renderHeightChart();
        chartsRendered = true;
    }

    $('#heightChart')?.addEventListener('click', e => {
        const target = e.target.closest('[data-height-character]');
        if (!target) return;
        const c = characters.find(x => x.id === target.dataset.heightCharacter);
        if (c) openCharacter(c);
    });

    // ------------------------------------------------------------
    // Misiones públicas
    // ------------------------------------------------------------
    const missionList = $('#missionList');
    const missionSearch = $('#missionSearch');
    const missionEmpty = $('#missionEmpty');

    function formatDate(value) {
        if (!value) return 'Fecha no registrada';
        const date = new Date(`${value}T12:00:00`);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('es-CL', {day: '2-digit', month: 'long', year: 'numeric'});
    }
    function missionEntry(m) {
        const participants = (m.participants || []).map(p => `
            <button type="button" class="participant-chip" data-participant-id="${esc(p.id)}" title="${esc(p.reward || '')}">
                ${p.portrait ? `<img src="${esc(p.portrait)}" alt="">` : ''}
                <span><strong>${esc(p.name)}</strong>${p.reward ? `<small>${esc(p.reward)}</small>` : ''}</span>
            </button>`).join('');
        return `
            <article class="mission-entry" data-mission-search="${esc(normalize([m.name, m.type, m.state, ...(m.participants || []).map(p => p.name)].join(' ')))}">
                <div class="mission-top">
                    <div>
                        <h3>${esc(m.name)}</h3>
                        <p class="mission-meta">${esc(formatDate(m.date))}${m.type ? ` · ${esc(m.type)}` : ''}</p>
                    </div>
                    <span class="mission-status">${esc(m.state || 'Registrada')}</span>
                </div>
                ${m.description ? `<div class="mission-description">${esc(m.description)}</div>` : ''}
                ${m.generalReward ? `<div class="general-reward"><strong>Recompensa general:</strong> ${esc(m.generalReward)}</div>` : ''}
                ${participants ? `<div class="participant-list">${participants}</div>` : '<p class="mission-meta">Sin participantes registrados.</p>'}
            </article>`;
    }
    function renderMissions() {
        if (!missionList) return;
        const q = normalize(missionSearch?.value || '');
        const visible = missions.filter(m => !q || normalize([m.name, m.type, m.state, m.description, m.generalReward, ...(m.participants || []).flatMap(p => [p.name, p.reward])].join(' ')).includes(q));
        missionList.innerHTML = visible.map(missionEntry).join('');
        if (missionEmpty) missionEmpty.hidden = visible.length > 0;
    }
    missionSearch?.addEventListener('input', renderMissions);
    missionList?.addEventListener('click', e => {
        const chip = e.target.closest('[data-participant-id]');
        if (!chip) return;
        const c = characters.find(c => c.id === chip.dataset.participantId);
        if (c) openCharacter(c);
    });

    // ------------------------------------------------------------
    // Admin modal / sesión
    // ------------------------------------------------------------
    const adminModal = $('#adminModal');
    const adminModalContent = $('#adminModalContent');
    function openAdminModal(html) {
        if (!adminModal || !adminModalContent) return;
        adminModalContent.innerHTML = html;
        adminModal.classList.add('open');
        adminModal.setAttribute('aria-hidden', 'false');
        setModalOpen(true);
    }
    function closeAdminModal() {
        adminModal?.classList.remove('open');
        adminModal?.setAttribute('aria-hidden', 'true');
        setModalOpen(false);
    }
    $$('[data-close-admin]').forEach(el => el.addEventListener('click', closeAdminModal));

    $('#adminLogout')?.addEventListener('click', () => {
        // Cierra sesión con el formulario de la barra común (POST con token).
        if (window.favorLogout) window.favorLogout();
        else window.location.href = FAVOR_BASE + 'logout.php';
    });

    // ------------------------------------------------------------
    // Panel Admin
    // ------------------------------------------------------------
    $$('.admin-section-tab').forEach(btn => btn.addEventListener('click', () => {
        $$('.admin-section-tab').forEach(x => x.classList.toggle('active', x === btn));
        $$('[data-admin-content]').forEach(panel => {
            const active = panel.dataset.adminContent === btn.dataset.adminPanel;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
    }));

    const ADMIN_PROFILE_FIELDS = ['height','age','sex','race','raceType','class','str','dex','con','int','wis','cha','threat'];
    const ADMIN_STANDARD_STATES = ['Activo','Retirado','Muerto','Schrödinger'];

    const ADMIN_STANDARD_CLASSES = [
        '?',
        'Artífice',
        'Bárbaro',
        'Bardo',
        'Brujo',
        'Clérigo',
        'Druida',
        'Explorador',
        'Guerrero',
        'Hechicero',
        'Mago',
        'Monje',
        'Paladín',
        'Pícaro',
        'Multiclase',
        'Blood Hunter'
    ];

    const ADMIN_THREAT_LEVELS = [
        '?',
        'Simple',
        'Común',
        'Difícil',
        'Asking for BAN'
    ];
    let adminPage = 1;
    let adminFiltersReady = false;
    const ADMIN_FILTER_STORAGE_KEY = 'guildAdminFilters';

    function getAdminFilterState() {
        return {
            search: $('#adminCharacterSearch')?.value || '',
            player: $('#adminPlayerFilter')?.value || '',
            state: $('#adminStateFilter')?.value || '',
            className: $('#adminClassFilter')?.value || '',
            category: $('#adminCategoryFilter')?.value || '',
            sort: $('#adminSort')?.value || 'name-asc',
            missingImage: !!$('#adminMissingImage')?.checked,
            incomplete: !!$('#adminIncomplete')?.checked,
            pageSize: $('#adminPageSize')?.value || '24',
            page: adminPage
        };
    }

    function saveAdminFilterState() {
        if (!isAdmin) return;
        try {
            sessionStorage.setItem(ADMIN_FILTER_STORAGE_KEY, JSON.stringify(getAdminFilterState()));
        } catch (_) {}
    }

    function restoreAdminFilterState() {
        let saved = null;
        try {
            saved = JSON.parse(sessionStorage.getItem(ADMIN_FILTER_STORAGE_KEY) || 'null');
        } catch (_) {}
        if (!saved || typeof saved !== 'object') return;

        const setValue = (selector, value) => {
            const el = $(selector);
            if (!el || value === undefined || value === null) return;
            el.value = String(value);
        };

        setValue('#adminCharacterSearch', saved.search || '');
        setValue('#adminPlayerFilter', saved.player || '');
        setValue('#adminStateFilter', saved.state || '');
        setValue('#adminClassFilter', saved.className || '');
        setValue('#adminCategoryFilter', saved.category || '');
        setValue('#adminSort', saved.sort || 'name-asc');
        setValue('#adminPageSize', saved.pageSize || '24');

        if ($('#adminMissingImage')) $('#adminMissingImage').checked = !!saved.missingImage;
        if ($('#adminIncomplete')) $('#adminIncomplete').checked = !!saved.incomplete;

        const savedPage = Number(saved.page);
        adminPage = Number.isFinite(savedPage) && savedPage > 0 ? savedPage : 1;
    }

    function isUnknownValue(value) {
        const raw = String(value ?? '').trim();
        if (!raw) return true;
        const n = normalize(raw);
        return ['?','??','???','-','—','n/a','na','desconocido','desconocida','sin registrar'].includes(n);
    }

    function hasCharacterImage(c) {
        return !!String(c?.portrait || c?.icon || '').trim();
    }

    function adminCompleteness(c) {
        const known = ADMIN_PROFILE_FIELDS.filter(field => !isUnknownValue(c?.[field])).length;
        return {known, total: ADMIN_PROFILE_FIELDS.length, complete: known === ADMIN_PROFILE_FIELDS.length};
    }

    function isIncompleteCharacter(c) {
        return !adminCompleteness(c).complete;
    }

    function adminStatusClass(state) {
        const n = normalize(state || '');
        if (n === 'activo' || n === 'vivo') return 'active';
        if (n === 'retirado') return 'retired';
        if (n === 'muerto') return 'dead';
        if (n === 'schrodinger') return 'schrodinger';
        return 'other';
    }

    function adminCharacterRow(c) {
        const completeness = adminCompleteness(c);
        const missingImage = !hasCharacterImage(c);
        const status = c.state || 'Sin estado';
        const thumb = c.thumb || c.portrait || c.icon;
        return `
            <div class="admin-row admin-character-row${isActiveCharacter(c) ? '' : ' is-inactive'}">
                ${thumb ? `<img src="${esc(thumb)}" alt="" loading="lazy">` : '<div class="admin-thumb admin-thumb-empty" aria-hidden="true">✦</div>'}
                <div class="admin-row-main">
                    <div class="admin-row-titleline">
                        <h3>${esc(c.name)}</h3>
                        ${c.category === 'NPC' ? '<span class="admin-status-chip npc">NPC</span>' : ''}
                        <span class="admin-status-chip ${adminStatusClass(status)}">${esc(status)}</span>
                    </div>
                    <p>${esc(c.player || 'Sin jugador')} · ${esc(c.race || 'Sin raza')} · ${esc(c.class || 'Sin clase')}</p>
                    <div class="admin-row-notes">
                        ${missingImage ? '<span class="admin-warning-chip">Sin imagen</span>' : ''}
                        ${!completeness.complete ? `<span class="admin-warning-chip">Datos ${completeness.known}/${completeness.total}</span>` : '<span class="admin-ok-chip">Ficha base completa</span>'}
                    </div>
                </div>
                <div class="admin-row-actions">
                    <button class="mini-button admin-edit-button" type="button" data-edit-character="${esc(c.id)}">Editar expediente</button>
                </div>
            </div>`;
    }

    function adminMissionRow(m) {
        return `
            <div class="admin-row">
                <div class="admin-thumb" style="display:grid;place-items:center;font-size:1.35rem">✒</div>
                <div><h3>${esc(m.name)}</h3><p>${esc(formatDate(m.date))} · ${(m.participants || []).length} participantes</p></div>
                <div class="admin-row-actions">
                    <button class="mini-button" type="button" data-edit-mission="${m.id}">Editar</button>
                    <button class="mini-button danger" type="button" data-delete-mission="${m.id}">Eliminar</button>
                </div>
            </div>`;
    }

    function fillAdminSelect(select, values) {
        if (!select) return;
        const first = select.querySelector('option');
        select.innerHTML = first ? first.outerHTML : '<option value="">Todos</option>';
        uniqueSorted(values).forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        });
    }

    function ensureAdminFiltersInitialized() {
        if (!isAdmin || adminFiltersReady) return;
        fillAdminSelect($('#adminPlayerFilter'), characters.map(c => c.player).filter(Boolean));
        fillAdminSelect($('#adminStateFilter'), characters.map(c => c.state).filter(Boolean));
        fillAdminSelect($('#adminClassFilter'), characters.map(c => c.class).filter(Boolean));
        restoreAdminFilterState();

        const rerender = () => {
            adminPage = 1;
            saveAdminFilterState();
            renderAdminCharacters();
        };
        $('#adminCharacterSearch')?.addEventListener('input', rerender);
        ['#adminPlayerFilter','#adminStateFilter','#adminClassFilter','#adminCategoryFilter','#adminSort','#adminMissingImage','#adminIncomplete','#adminPageSize']
            .forEach(sel => $(sel)?.addEventListener('change', rerender));
        $('#adminClearFilters')?.addEventListener('click', () => {
            const search = $('#adminCharacterSearch');
            if (search) search.value = '';
            ['#adminPlayerFilter','#adminStateFilter','#adminClassFilter','#adminCategoryFilter'].forEach(sel => { if ($(sel)) $(sel).value = ''; });
            if ($('#adminSort')) $('#adminSort').value = 'name-asc';
            if ($('#adminMissingImage')) $('#adminMissingImage').checked = false;
            if ($('#adminIncomplete')) $('#adminIncomplete').checked = false;
            if ($('#adminPageSize')) $('#adminPageSize').value = '24';
            adminPage = 1;
            try { sessionStorage.removeItem(ADMIN_FILTER_STORAGE_KEY); } catch (_) {}
            renderAdminCharacters();
            search?.focus();
        });
        $('#adminPagination')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-admin-page]');
            if (!btn || btn.disabled) return;
            const next = Number(btn.dataset.adminPage);
            if (!Number.isFinite(next)) return;
            adminPage = next;
            saveAdminFilterState();
            renderAdminCharacters();
            $('#adminCharacterList')?.scrollIntoView({behavior:'smooth', block:'start'});
        });
        adminFiltersReady = true;
    }

    function renderAdminStats() {
        const el = $('#adminCharacterStats');
        if (!el) return;
        const countState = state => characters.filter(c => normalize(c.state) === normalize(state)).length;
        const stats = [
            ['Total', characters.length, 'Todos los expedientes'],
            ['Activos', characters.filter(isActiveCharacter).length, 'Personajes actuales'],
            ['Retirados', countState('Retirado'), 'Fuera de actividad'],
            ['Muertos', countState('Muerto'), 'Archivo histórico'],
            ['Schrödinger', countState('Schrödinger'), 'Estado incierto'],
            ['NPC', characters.filter(c => (c.category || 'Personaje') === 'NPC').length, 'Expedientes no jugadores'],
            ['Sin imagen', characters.filter(c => !hasCharacterImage(c)).length, 'Pendientes de retrato'],
            ['Incompletos', characters.filter(isIncompleteCharacter).length, 'Faltan datos base'],
        ];
        el.innerHTML = stats.map(([label, value, note]) => `
            <div class="admin-stat-card">
                <span>${esc(label)}</span>
                <strong>${value}</strong>
                <small>${esc(note)}</small>
            </div>`).join('');
    }

    function filteredAdminCharacters() {
        const q = normalize($('#adminCharacterSearch')?.value || '');
        const player = $('#adminPlayerFilter')?.value || '';
        const state = $('#adminStateFilter')?.value || '';
        const cls = $('#adminClassFilter')?.value || '';
        const category = $('#adminCategoryFilter')?.value || '';
        const missingImage = !!$('#adminMissingImage')?.checked;
        const incomplete = !!$('#adminIncomplete')?.checked;
        const sort = $('#adminSort')?.value || 'name-asc';

        const rows = characters.filter(c => {
            if (player && c.player !== player) return false;
            if (state && c.state !== state) return false;
            if (cls && c.class !== cls) return false;
            if (category && (c.category || 'Personaje') !== category) return false;
            if (missingImage && hasCharacterImage(c)) return false;
            if (incomplete && !isIncompleteCharacter(c)) return false;
            if (!q) return true;
            return normalize([c.name,c.shortName,c.player,c.handle,c.race,c.raceType,c.class,c.state,c.threat,c.category].join(' ')).includes(q);
        });

        const byText = (a, b, field) => String(a[field] || '').localeCompare(String(b[field] || ''), 'es', {sensitivity:'base'});
        rows.sort((a,b) => {
            if (sort === 'name-desc') return byText(b,a,'name');
            if (sort === 'player') return byText(a,b,'player') || byText(a,b,'name');
            if (sort === 'state') return byText(a,b,'state') || byText(a,b,'name');
            if (sort === 'updated') return String(b.updated || '').localeCompare(String(a.updated || '')) || byText(a,b,'name');
            return byText(a,b,'name');
        });
        return rows;
    }

    function renderAdminPagination(totalRows, pageSize) {
        const el = $('#adminPagination');
        if (!el) return;
        if (!Number.isFinite(pageSize) || totalRows <= pageSize) {
            el.innerHTML = '';
            return;
        }
        const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
        adminPage = Math.min(Math.max(1, adminPage), totalPages);
        const start = Math.max(1, adminPage - 2);
        const end = Math.min(totalPages, start + 4);
        const first = Math.max(1, end - 4);
        const nums = [];
        for (let page = first; page <= end; page++) {
            nums.push(`<button type="button" data-admin-page="${page}" class="${page === adminPage ? 'current' : ''}">${page}</button>`);
        }
        el.innerHTML = `
            <button type="button" data-admin-page="${adminPage - 1}" ${adminPage <= 1 ? 'disabled' : ''}>‹ Anterior</button>
            ${nums.join('')}
            <button type="button" data-admin-page="${adminPage + 1}" ${adminPage >= totalPages ? 'disabled' : ''}>Siguiente ›</button>
            <span>Página ${adminPage} de ${totalPages}</span>`;
    }

    function renderAdminCharacters() {
        if (!isAdmin) return;
        ensureAdminFiltersInitialized();
        renderAdminStats();
        const charList = $('#adminCharacterList');
        if (!charList) return;

        const rows = filteredAdminCharacters();
        const pageValue = $('#adminPageSize')?.value || '24';
        const pageSize = pageValue === 'all' ? Infinity : Math.max(1, Number(pageValue) || 24);
        const totalPages = Number.isFinite(pageSize) ? Math.max(1, Math.ceil(rows.length / pageSize)) : 1;
        adminPage = Math.min(Math.max(1, adminPage), totalPages);
        const visible = Number.isFinite(pageSize) ? rows.slice((adminPage - 1) * pageSize, adminPage * pageSize) : rows;

        charList.innerHTML = visible.map(adminCharacterRow).join('');
        const empty = $('#adminCharacterEmpty');
        if (empty) empty.hidden = rows.length > 0;
        const count = $('#adminCharacterCount');
        if (count) count.textContent = `${rows.length} de ${characters.length} ${rows.length === 1 ? 'expediente' : 'expedientes'}`;
        renderAdminPagination(rows.length, pageSize);
    }

    function renderAdminLists() {
        if (!isAdmin) return;
        renderAdminCharacters();
        const missionAdminList = $('#adminMissionList');
        if (missionAdminList) missionAdminList.innerHTML = missions.length ? missions.map(adminMissionRow).join('') : '<div class="notice-sheet">Todavía no hay misiones registradas.</div>';
    }

    function resolvePortraitPreview(value) {
        return resolveAssetSrc(value);
    }

    function characterEditForm(c = null) {
        const isNew = !c;
        const model = c || {
            id:'', playerId:'', player:'', shortName:'', name:'', portrait:'', icon:'', description:'', biography:'',
            height:'', age:'', sex:'', race:'', raceType:'', class:'?', level:'', feats:'', threat:'?', state:'Activo', category:'Personaje',
            str:'', dex:'', con:'', int:'', wis:'', cha:''
        };
        const field = (label, name, value = '', type = 'text') => `<div class="form-field"><label>${esc(label)}</label><input name="${esc(name)}" type="${esc(type)}" value="${esc(value)}"></div>`;
        const states = [...ADMIN_STANDARD_STATES];
        if (model.state && !states.includes(model.state)) states.push(model.state);
        const preview = model.portrait || model.icon || '';
        const playerOptions = players.map(p => `<option value="${esc(p.id)}" ${model.playerId === p.id ? 'selected' : ''}>${esc(p.name)}${p.active ? '' : ' · inactivo'}</option>`).join('');
        return `
            <h2 id="adminModalTitle">${isNew ? 'Nuevo expediente' : 'Editar expediente'}</h2>
            <p>${isNew ? 'Crea el personaje en la Banca y su ficha del Archivo al mismo tiempo.' : `${esc(model.name)} · Los cambios se guardan directamente en MySQL.`}</p>
            <form class="admin-form" id="characterEditForm" data-id="${esc(model.id)}" enctype="multipart/form-data">
                <div class="admin-edit-summary">
                    <div class="admin-portrait-preview" id="adminPortraitPreview">
                        ${preview ? `<img src="${esc(preview)}" alt="Vista previa de ${esc(model.name)}">` : '<span>Sin imagen</span>'}
                    </div>
                    <div>
                        <strong>${isNew ? 'Retrato del personaje' : esc(model.player || 'Jugador no registrado')}</strong>
                        <p>Selecciona o arrastra un JPG, PNG o WebP. La web lo enviará sola al servidor y guardará su ruta.</p>
                    </div>
                </div>

                <label class="portrait-upload-zone" id="portraitUploadZone" for="adminPortraitFile">
                    <input id="adminPortraitFile" name="portraitFile" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                    <span class="portrait-upload-icon">✦</span>
                    <strong id="portraitUploadTitle">${preview ? 'Cambiar retrato' : 'Seleccionar retrato'}</strong>
                    <span id="portraitUploadText">Haz clic aquí o arrastra la imagen. Máximo 12 MB.</span>
                </label>

                <div class="form-grid">
                    ${isNew ? `<div class="form-field"><label>Jugador</label><select name="playerId" required><option value="">Selecciona un jugador…</option>${playerOptions}</select></div>` : ''}
                    ${field('Nombre corto', 'shortName', model.shortName)}
                    ${field('Nombre completo', 'name', model.name)}
                    <div class="form-field"><label>Altura (m)</label><input name="height" type="number" min="0" step="0.01" value="${esc(model.height)}"></div>
                    ${field('Edad', 'age', model.age)}
                    ${field('Sexo', 'sex', model.sex)}
                    ${field('Raza', 'race', model.race)}
                    ${field('Tipo de raza', 'raceType', model.raceType)}
                    <div class="form-field"><label>Clase</label><select name="class">${(() => {
                        const values = [...ADMIN_STANDARD_CLASSES];
                        if (model.class && !values.includes(model.class)) values.push(model.class);
                        return values.map(v => `<option value="${esc(v)}" ${model.class === v ? 'selected' : ''}>${esc(v)}${!ADMIN_STANDARD_CLASSES.includes(v) ? ' · valor anterior' : ''}</option>`).join('');
                    })()}</select></div>
                    ${field('Nivel', 'level', model.level, 'number')}
                    ${field('Dotes', 'feats', model.feats, 'number')}
                    <div class="form-field"><label>Nivel de amenaza</label><select name="threat">${(() => {
                        const values = [...ADMIN_THREAT_LEVELS];
                        if (model.threat && !values.includes(model.threat)) values.push(model.threat);
                        return values.map(v => `<option value="${esc(v)}" ${model.threat === v ? 'selected' : ''}>${esc(v)}${!ADMIN_THREAT_LEVELS.includes(v) ? ' · valor anterior' : ''}</option>`).join('');
                    })()}</select></div>
                    <div class="form-field"><label>Estado</label><select name="state">${states.map(v => `<option value="${esc(v)}" ${model.state === v ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></div>
                    <div class="form-field"><label>Tipo de expediente</label><select name="category"><option value="Personaje" ${model.category !== 'NPC' ? 'selected' : ''}>Personaje</option><option value="NPC" ${model.category === 'NPC' ? 'selected' : ''}>NPC</option></select><span class="help-text">Puedes cambiar un expediente existente, como Lustia, de Personaje a NPC sin perder sus datos.</span></div>
                </div>
                <div class="form-grid three">
                    ${field('FUE', 'str', model.str, 'number')}${field('DES', 'dex', model.dex, 'number')}${field('CON', 'con', model.con, 'number')}
                    ${field('INT', 'int', model.int, 'number')}${field('SAB', 'wis', model.wis, 'number')}${field('CAR', 'cha', model.cha, 'number')}
                </div>
                <details class="admin-advanced-path">
                    <summary>Ruta manual de imagen (opcional)</summary>
                    <div class="form-field"><label>Ruta del retrato</label><input id="adminPortraitInput" name="portrait" value="${esc(model.portrait)}" placeholder="media/characters/jugador/personaje.webp"><span class="help-text">Solo hace falta si quieres conservar o escribir una ruta manual. Una imagen subida tiene prioridad.</span></div>
                </details>
                <div class="form-field"><label>Descripción corta</label><textarea name="description">${esc(model.description)}</textarea></div>
                <div class="form-field"><label>Biografía / notas del archivo</label><textarea name="biography">${esc(model.biography)}</textarea></div>
                ${isNew ? '' : `<div class="admin-danger-zone">
                    <div><strong>Zona delicada</strong><span>Quitar el perfil lo saca de Archivos del Gremio, pero no borra el personaje ni su dinero de la Banca.</span></div>
                    <button type="button" class="mini-button danger" data-remove-character="${esc(model.id)}">Quitar del archivo</button>
                </div>`}
                <div class="form-actions"><button type="button" class="ink-button subtle" data-modal-cancel>Cancelar</button><button class="ink-button" type="submit">${isNew ? 'Crear personaje' : 'Guardar expediente'}</button></div>
            </form>`;
    }

    function bindPortraitUpload() {
        const input = $('#adminPortraitFile');
        const zone = $('#portraitUploadZone');
        const routeInput = $('#adminPortraitInput');
        const box = $('#adminPortraitPreview');
        const title = $('#portraitUploadTitle');
        const text = $('#portraitUploadText');
        if (!input || !zone || !box) return;

        let objectUrl = '';
        const previewFile = file => {
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/i.test(file.type || '')) {
                showToast('Usa una imagen JPG, PNG o WebP.', true);
                input.value = '';
                return;
            }
            if (file.size > 12 * 1024 * 1024) {
                showToast('La imagen supera el máximo de 12 MB.', true);
                input.value = '';
                return;
            }
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(file);
            box.innerHTML = `<img src="${esc(objectUrl)}" alt="Vista previa del retrato">`;
            if (title) title.textContent = 'Retrato listo para subir';
            if (text) text.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
        };

        input.addEventListener('change', () => previewFile(input.files?.[0]));
        ['dragenter','dragover'].forEach(type => zone.addEventListener(type, e => {
            e.preventDefault();
            zone.classList.add('dragging');
        }));
        ['dragleave','drop'].forEach(type => zone.addEventListener(type, e => {
            e.preventDefault();
            zone.classList.remove('dragging');
        }));
        zone.addEventListener('drop', e => {
            const file = e.dataTransfer?.files?.[0];
            if (!file) return;
            try {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
            } catch (_) {}
            previewFile(file);
        });

        const updateRoutePreview = () => {
            if (input.files?.length) return;
            const src = resolvePortraitPreview(routeInput?.value || '');
            if (!src) {
                box.innerHTML = '<span>Sin imagen</span>';
                return;
            }
            box.innerHTML = `<img src="${esc(src)}" alt="Vista previa">`;
            const img = $('img', box);
            img?.addEventListener('error', () => { box.innerHTML = '<span>No se pudo cargar</span>'; }, {once:true});
        };
        routeInput?.addEventListener('input', updateRoutePreview);
    }

    function bindNewCharacterNameSync() {
        const form = $('#characterEditForm');
        if (!form || form.dataset.id) return;
        const shortName = $('[name="shortName"]', form);
        const fullName = $('[name="name"]', form);
        let shortTouched = false;
        shortName?.addEventListener('input', () => { shortTouched = true; });
        fullName?.addEventListener('input', () => {
            if (!shortTouched && shortName) shortName.value = fullName.value.trim().split(/\s+/)[0] || '';
        });
    }

    function openCharacterAdminForm(c = null) {
        openAdminModal(characterEditForm(c));
        $('[data-modal-cancel]')?.addEventListener('click', closeAdminModal);
        bindPortraitUpload();
        bindNewCharacterNameSync();
        $('#characterEditForm')?.addEventListener('submit', async ev => {
            ev.preventDefault();
            const form = ev.currentTarget;
            const submit = $('button[type="submit"]', form);
            const fd = new FormData(form);
            fd.set('action', 'save_character');
            if (c?.id) fd.set('id', c.id); else fd.delete('id');
            if (submit) {
                submit.disabled = true;
                submit.textContent = c ? 'Guardando…' : 'Creando…';
            }
            try {
                const data = await postForm(fd);
                saveAdminFilterState();
                sessionStorage.setItem('guildReturnView', 'admin');
                showToast(data.message || (c ? 'Expediente actualizado.' : 'Personaje creado.'));
                location.reload();
            } catch (err) {
                showToast(err.message, true);
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = c ? 'Guardar expediente' : 'Crear personaje';
                }
            }
        });
    }

    $('#newCharacter')?.addEventListener('click', () => openCharacterAdminForm(null));

    $('#adminCharacterList')?.addEventListener('click', e => {
        const edit = e.target.closest('[data-edit-character]');
        if (!edit) return;
        const c = characters.find(x => x.id === edit.dataset.editCharacter);
        if (c) openCharacterAdminForm(c);
    });

    adminModalContent?.addEventListener('click', async e => {
        const remove = e.target.closest('[data-remove-character]');
        if (!remove) return;
        const c = characters.find(x => x.id === remove.dataset.removeCharacter);
        if (!c) return;
        const ok = confirm(`¿Quitar a ${c.name} de Archivos del Gremio?\n\nEsto NO lo borrará de la Banca. Solo elimina su PersonajePerfil.`);
        if (!ok) return;
        try {
            const data = await post({action: 'remove_character_profile', id: c.id});
            showToast(data.message || 'Expediente quitado del archivo.');
            location.reload();
        } catch (err) { showToast(err.message, true); }
    });

    function missionForm(m = null) {
        const mission = m || {id: 0, name: '', date: '', type: '', state: 'Completada', description: '', generalReward: '', participants: []};
        const players = uniqueSorted(characters.map(c => c.player));
        return `
            <h2 id="adminModalTitle">${mission.id ? 'Editar misión' : 'Nueva misión'}</h2>
            <p>El registro guarda una copia del nombre, clase y nivel que tenía cada personaje al participar.</p>
            <form class="admin-form" id="missionEditForm" data-id="${mission.id || 0}">
                <div class="form-grid">
                    <div class="form-field"><label>Nombre de la misión</label><input name="name" value="${esc(mission.name)}" required></div>
                    <div class="form-field"><label>Fecha</label><input name="date" type="date" value="${esc(mission.date)}"></div>
                    <div class="form-field"><label>Tipo</label><input name="type" value="${esc(mission.type)}" placeholder="Investigación, combate, rescate…"></div>
                    <div class="form-field"><label>Estado</label><select name="state">${['Publicada','En curso','Completada','Fallida','Cancelada'].map(v => `<option ${mission.state === v ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
                    <div class="form-field full"><label>Recompensa general</label><input name="generalReward" value="${esc(mission.generalReward)}" placeholder="200 po por aventurero, objeto mágico…"></div>
                    <div class="form-field full"><label>Descripción</label><textarea name="description">${esc(mission.description)}</textarea></div>
                </div>

                <section class="participant-selector">
                    <div class="participant-selector-head">
                        <div>
                            <label for="participantPlayerFilter">Añadir participantes</label>
                            <p>Primero elige un jugador. Solo aparecerán sus personajes.</p>
                        </div>
                        <select id="participantPlayerFilter">
                            <option value="">Selecciona un jugador…</option>
                            ${players.map(player => `<option value="${esc(player)}">${esc(player)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="participant-picker" id="participantPicker">
                        <div class="participant-picker-empty">Selecciona un jugador para abrir su carpeta.</div>
                    </div>
                </section>

                <section class="selected-participants-block">
                    <div class="selected-participants-title">
                        <label>Participantes seleccionados</label>
                        <span id="participantSelectedCount">0</span>
                    </div>
                    <div class="participant-selected" id="participantSelected"></div>
                </section>

                <div class="form-actions"><button type="button" class="ink-button subtle" data-modal-cancel>Cancelar</button><button class="ink-button" type="submit">Guardar misión</button></div>
            </form>`;
    }

    function bindMissionForm(mission = null) {
        $('[data-modal-cancel]')?.addEventListener('click', closeAdminModal);
        const form = $('#missionEditForm');
        if (!form) return;

        const selected = new Map((mission?.participants || []).map(p => [p.id, {
            id: p.id,
            reward: p.reward || '',
            notes: p.notes || ''
        }]));
        const filter = $('#participantPlayerFilter', form);
        const picker = $('#participantPicker', form);
        const selectedBox = $('#participantSelected', form);
        const count = $('#participantSelectedCount', form);

        function syncSelectedInputs() {
            $$('[data-selected-participant]', selectedBox).forEach(row => {
                const id = row.dataset.selectedParticipant;
                const item = selected.get(id);
                if (!item) return;
                item.reward = $('[data-selected-reward]', row)?.value || '';
                item.notes = $('[data-selected-notes]', row)?.value || '';
            });
        }

        function renderPicker() {
            if (!picker) return;
            const player = filter?.value || '';
            if (!player) {
                picker.innerHTML = '<div class="participant-picker-empty">Selecciona un jugador para abrir su carpeta.</div>';
                return;
            }
            const available = characters.filter(c => c.player === player);
            picker.innerHTML = available.length ? available.map(c => {
                const chosen = selected.has(c.id);
                return `
                    <button type="button" class="participant-pick-card ${chosen ? 'chosen' : ''}" data-pick-participant="${esc(c.id)}">
                        <span class="participant-pick-portrait">${(c.thumb || c.portrait) ? `<img src="${esc(c.thumb || c.portrait)}" alt="" loading="lazy">` : '✦'}</span>
                        <span><strong>${esc(c.name)}</strong><small>${esc(c.class || 'Sin clase')}</small></span>
                        <b>${chosen ? 'Quitar' : 'Añadir'}</b>
                    </button>`;
            }).join('') : '<div class="participant-picker-empty">Ese jugador no tiene personajes en los archivos.</div>';
        }

        function renderSelected() {
            if (!selectedBox) return;
            syncSelectedInputs();
            const items = [...selected.values()].map(p => ({...p, character: characters.find(c => c.id === p.id)})).filter(x => x.character);
            if (count) count.textContent = String(items.length);
            selectedBox.innerHTML = items.length ? items.map(({id, reward, notes, character: c}) => `
                <div class="participant-selected-row" data-selected-participant="${esc(id)}">
                    <div class="participant-selected-person">
                        <span class="participant-pick-portrait">${(c.thumb || c.portrait) ? `<img src="${esc(c.thumb || c.portrait)}" alt="" loading="lazy">` : '✦'}</span>
                        <span><strong>${esc(c.name)}</strong><small>${esc(c.player)} · ${esc(c.class || 'Sin clase')}</small></span>
                    </div>
                    <input type="text" data-selected-reward placeholder="Recompensa individual" value="${esc(reward)}">
                    <input type="text" data-selected-notes placeholder="Notas" value="${esc(notes)}">
                    <button type="button" class="participant-remove" data-remove-participant="${esc(id)}" aria-label="Quitar a ${esc(c.name)}">×</button>
                </div>`).join('') : '<div class="participant-picker-empty">Todavía no has añadido participantes.</div>';
        }

        filter?.addEventListener('change', renderPicker);
        picker?.addEventListener('click', e => {
            const btn = e.target.closest('[data-pick-participant]');
            if (!btn) return;
            syncSelectedInputs();
            const id = btn.dataset.pickParticipant;
            if (selected.has(id)) selected.delete(id);
            else selected.set(id, {id, reward: '', notes: ''});
            renderSelected();
            renderPicker();
        });
        selectedBox?.addEventListener('click', e => {
            const btn = e.target.closest('[data-remove-participant]');
            if (!btn) return;
            syncSelectedInputs();
            selected.delete(btn.dataset.removeParticipant);
            renderSelected();
            renderPicker();
        });

        renderSelected();
        renderPicker();

        form.addEventListener('submit', async e => {
            e.preventDefault();
            syncSelectedInputs();
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.participants = [...selected.values()];
            try {
                await post({action: 'save_mission', id: Number(form.dataset.id || 0), ...payload});
                showToast('Misión guardada.');
                location.reload();
            } catch (err) { showToast(err.message, true); }
        });
    }

    $('#newMission')?.addEventListener('click', () => {
        openAdminModal(missionForm());
        bindMissionForm();
    });
    $('#adminMissionList')?.addEventListener('click', async e => {
        const edit = e.target.closest('[data-edit-mission]');
        if (edit) {
            const m = missions.find(x => String(x.id) === edit.dataset.editMission);
            if (!m) return;
            openAdminModal(missionForm(m));
            bindMissionForm(m);
            return;
        }
        const del = e.target.closest('[data-delete-mission]');
        if (del) {
            const m = missions.find(x => String(x.id) === del.dataset.deleteMission);
            if (!m || !confirm(`¿Eliminar la misión “${m.name}”?`)) return;
            try {
                await post({action: 'delete_mission', id: m.id});
                showToast('Misión eliminada.');
                location.reload();
            } catch (err) { showToast(err.message, true); }
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (adminModal?.classList.contains('open')) closeAdminModal();
        else if (characterModal?.classList.contains('open')) closeCharacter();
    });

    // Inicio
    renderCharacters();
    renderMissions();
    if (isAdmin) renderAdminLists();

    const returnView = sessionStorage.getItem('guildReturnView');
    if (returnView) {
        sessionStorage.removeItem('guildReturnView');
        if (returnView === 'admin' && isAdmin) switchView('admin');
    }

    const requestedCharacter = new URLSearchParams(location.search).get('character');
    if (requestedCharacter) {
        const c = characters.find(x => x.id === requestedCharacter);
        if (c) openCharacter(c);
    } else if (location.hash.startsWith('#personaje=')) {
        const id = decodeURIComponent(location.hash.slice('#personaje='.length));
        const c = characters.find(x => x.id === id);
        if (c) openCharacter(c);
    }
})();
