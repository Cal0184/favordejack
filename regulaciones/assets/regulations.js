(() => {
    'use strict';

    const $ = sel => document.querySelector(sel);
    const book = $('#regBook');
    const search = $('#ruleSearch');
    const items = [...document.querySelectorAll('.rule-item')];
    const filters = [...document.querySelectorAll('[data-filter]')];
    const visibleCount = $('#visibleCount');
    const emptyState = $('#emptyState');
    const emptyText = $('#emptyText');
    const detail = $('#ruleDetail');
    const detailContent = $('#detailContent');
    const detailAdmin = $('#detailAdmin');
    const mobile = window.matchMedia('(max-width: 900px)');

    // Enlaces viejos (#monje, #miss-fire…) siguen llevando a su regla.
    const OLD_IDS = {
        'agarre-presa': 'agarrar', 'estado-agarrado': 'agarrado', 'salir-agarre': 'escapar-agarre',
        'miss-fire': 'fallo-disparo', 'monje': 'monje-desarmado', 'percepcion-expertise': 'percepcion-pericia',
    };

    let filter = 'all';
    let current = null;

    const normalize = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    items.forEach(item => { item.dataset.haystack = normalize(item.dataset.search); });

    const home = $('#regHome');
    const crumbTitle = $('#crumbTitle');
    const listEl = $('#ruleList');
    let atHome = !!home;

    // Portada móvil (tarjetas de sección) ↔ lista filtrada.
    function setHome(on) {
        atHome = !!home && on;
        book.classList.toggle('at-home', atHome);
    }

    function filterLabel(value) {
        if (value === 'all') return 'Todas las reglas';
        const btn = filters.find(b => b.dataset.filter === value && b.classList.contains('chip'))
            || filters.find(b => b.dataset.filter === value);
        return (btn?.querySelector('.home-name, span')?.textContent || btn?.textContent || '').trim();
    }

    // Las filas entran una tras otra, para que se note que el filtro respondió.
    function animateList() {
        if (!listEl) return;
        let i = 0;
        for (const item of items) {
            item.classList.remove('enter');
            if (!item.hidden && i < 14) item.style.setProperty('--i', String(i++));
        }
        void listEl.offsetWidth;
        items.forEach(item => { if (!item.hidden) item.classList.add('enter'); });
    }

    function matchesFilter(item) {
        if (filter === 'all') return item.dataset.type !== 'parche';
        if (filter.startsWith('cat:')) return item.dataset.category === filter.slice(4);
        if (filter.startsWith('type:')) return item.dataset.type === filter.slice(5);
        return true;
    }

    function applyFilters() {
        const words = normalize(search?.value).split(/\s+/).filter(Boolean);
        let shown = 0;
        for (const item of items) {
            const show = matchesFilter(item) && words.every(w => item.dataset.haystack.includes(w));
            item.hidden = !show;
            if (show) shown += 1;
        }
        visibleCount.textContent = String(shown);
        if (crumbTitle) crumbTitle.textContent = words.length && filter === 'all' ? 'Resultados de búsqueda' : filterLabel(filter);
        emptyState.hidden = shown !== 0;
        if (shown === 0) {
            emptyText.textContent = words.length
                ? 'Prueba con otro filtro o término de búsqueda.'
                : 'Todavía no hay entradas registradas en esta sección.';
        }
    }

    function setFilter(value) {
        filter = value;
        filters.forEach(btn => {
            const on = btn.dataset.filter === value;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        applyFilters();
        animateList();
    }

    const visibleItems = () => items.filter(i => !i.hidden);
    const titleOf = item => item?.querySelector('.rule-title')?.textContent.trim() || '';

    // Botones «Anterior / Siguiente» al final del detalle (dentro de la lista filtrada).
    function navFor(item) {
        const list = visibleItems();
        const at = list.indexOf(item);
        const prev = at > 0 ? list[at - 1] : null;
        const next = at >= 0 && at < list.length - 1 ? list[at + 1] : null;
        const nav = document.createElement('nav');
        nav.className = 'detail-nav';
        nav.setAttribute('aria-label', 'Otras regulaciones');
        const make = (target, cls, label) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = cls;
            btn.disabled = !target;
            const small = document.createElement('small');
            small.textContent = label;
            const span = document.createElement('span');
            span.textContent = titleOf(target);
            btn.append(small, span);
            if (target) btn.addEventListener('click', () => go(target, cls === 'nav-prev'));
            return btn;
        };
        nav.append(make(prev, 'nav-prev', '‹ Anterior'), make(next, 'nav-next', 'Siguiente ›'));
        return nav;
    }

    function go(target, backwards = false) {
        if (!target) return;
        history.replaceState(null, '', '#' + target.id);
        detail.classList.toggle('from-left', backwards);
        detail.style.animation = 'none';
        void detail.offsetWidth;
        detail.style.animation = '';
        show(target);
    }

    function step(delta) {
        if (!current) return;
        const list = visibleItems();
        const at = list.indexOf(current);
        go(list[at + delta], delta < 0);
    }

    function show(item, { focus = false } = {}) {
        if (!item) return;
        current = item;
        items.forEach(i => i.classList.toggle('selected', i === item));
        const tpl = item.querySelector('template');
        detailContent.replaceChildren(tpl.content.cloneNode(true), navFor(item));
        if (detailAdmin) detailAdmin.hidden = item.dataset.type === 'parche';
        setHome(false);
        detail.scrollTop = 0;
        if (mobile.matches) {
            book.classList.add('reading');
            document.body.classList.add('reg-reading');
            window.scrollTo({ top: 0 });
        }
        if (focus) detail.focus({ preventScroll: !mobile.matches });
    }

    function closeDetail() {
        book.classList.remove('reading');
        document.body.classList.remove('reg-reading');
        const back = current?.querySelector('.rule-open');
        if (location.hash) history.pushState(null, '', location.pathname + location.search);
        back?.focus({ preventScroll: false });
        back?.scrollIntoView({ block: 'center' });
    }

    function fromHash() {
        let id = decodeURIComponent(location.hash.slice(1));
        id = OLD_IDS[id] || id;
        const item = id && document.getElementById(id);
        if (item && item.classList.contains('rule-item')) {
            if (item.hidden) {
                if (search) search.value = '';
                setFilter(item.dataset.type === 'parche' ? 'cat:Patch Notes' : 'all');
            }
            show(item);
            if (!mobile.matches) item.scrollIntoView({ block: 'nearest' });
        } else if (mobile.matches) {
            book.classList.remove('reading');
            document.body.classList.remove('reg-reading');
        }
    }

    items.forEach(item => {
        item.querySelector('.rule-open').addEventListener('click', () => {
            if (location.hash.slice(1) !== item.id) history.pushState(null, '', '#' + item.id);
            show(item, { focus: true });
        });
    });
    filters.forEach(btn => btn.addEventListener('click', () => {
        setFilter(btn.dataset.filter);
        if (btn.closest('#regHome')) {
            setHome(false);
            const tools = document.querySelector('.list-tools');
            if (mobile.matches && tools) window.scrollTo({ top: tools.getBoundingClientRect().top + window.scrollY - 4, behavior: 'smooth' });
        }
    }));
    search?.addEventListener('input', () => {
        if (search.value.trim() !== '') setHome(false);
        applyFilters();
    });
    document.querySelectorAll('.recent-card').forEach(link => link.addEventListener('click', e => {
        e.preventDefault();
        history.pushState(null, '', link.getAttribute('href'));
        fromHash();
    }));
    $('#crumbBack')?.addEventListener('click', () => {
        if (search) search.value = '';
        setFilter('all');
        setHome(true);
    });
    $('#detailClose')?.addEventListener('click', closeDetail);
    window.addEventListener('popstate', fromHash);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && book.classList.contains('reading') && !document.querySelector('dialog[open]')) closeDetail();
    });

    // Deslizar el dedo a izquierda/derecha cambia de regulación en el móvil.
    let touchX = null, touchY = null;
    detail.addEventListener('touchstart', e => {
        if (!mobile.matches || e.touches.length !== 1) return;
        touchX = e.touches[0].clientX; touchY = e.touches[0].clientY;
    }, { passive: true });
    detail.addEventListener('touchend', e => {
        if (touchX === null) return;
        const dx = e.changedTouches[0].clientX - touchX;
        const dy = e.changedTouches[0].clientY - touchY;
        touchX = null;
        if (e.target.closest('table, .table-wrap, pre')) return;
        if (Math.abs(dx) > 70 && Math.abs(dx) > Math.abs(dy) * 1.6) step(dx < 0 ? 1 : -1);
    }, { passive: true });

    setHome(true);
    applyFilters();
    if (location.hash) fromHash();
    else if (!mobile.matches) show(items.find(i => !i.hidden));

    // ───────────── Admin: crear, editar y borrar ─────────────
    const admin = window.REG_ADMIN;
    if (!admin) return;

    const dialog = $('#ruleEditor');
    const form = $('#ruleForm');
    const errorBox = $('#editorError');
    const saveBtn = $('#editorSave');
    const iconGrid = $('#iconGrid');
    let editingId = '';

    iconGrid.innerHTML = admin.icons.map(name =>
        `<label class="icon-choice" title="${name}"><input type="radio" name="icon" value="${name}">` +
        `<svg class="reg-icon" aria-hidden="true"><use href="${admin.sprite}#${name}"/></svg><span class="sr-only">${name}</span></label>`
    ).join('');

    async function call(payload) {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ...payload, csrf: admin.csrf }),
        });
        let data = null;
        try { data = await res.json(); } catch { /* respuesta no JSON */ }
        if (!data?.ok) throw new Error(data?.error || 'No se pudo guardar. Revisa tu conexión e inténtalo otra vez.');
        return data;
    }

    function openEditor(rule) {
        editingId = rule?.id || '';
        $('#editorTitle').textContent = editingId ? 'Editar regulación' : 'Nueva regulación';
        form.reset();
        const f = form.elements;
        const presetCategory = filter.startsWith('cat:') ? filter.slice(4) : '';
        const presetType = filter.startsWith('type:') ? filter.slice(5) : 'regla';
        f.title.value = rule?.title || '';
        f.category.value = rule?.category || presetCategory;
        f.type.value = rule?.type || presetType;
        f.summary.value = rule?.summary || '';
        f.body.value = rule?.body || '## Descripción\n';
        f.source.value = rule?.source || '';
        f.mesa.checked = !!rule?.mesa;
        f.draft.checked = !!rule?.draft;
        const icon = rule?.icon || 'scroll-unfurled';
        const radio = iconGrid.querySelector(`input[value="${CSS.escape(icon)}"]`);
        if (radio) { radio.checked = true; radio.closest('label').scrollIntoView({ block: 'nearest' }); }
        errorBox.hidden = true;
        dialog.showModal();
        f.title.focus();
    }

    $('#newRule').addEventListener('click', () => openEditor(null));
    $('#editorCancel').addEventListener('click', () => dialog.close());
    $('#editRule')?.addEventListener('click', () => current && openEditor(admin.rules[current.id]));

    $('#deleteRule')?.addEventListener('click', async () => {
        if (!current) return;
        const rule = admin.rules[current.id];
        if (!confirm(`¿Borrar «${rule?.title || current.id}»? No se puede deshacer (salvo con un respaldo).`)) return;
        try {
            await call({ action: 'delete', id: current.id });
            location.href = location.pathname;
        } catch (err) {
            alert(err.message);
        }
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const f = form.elements;
        const rule = {
            title: f.title.value, category: f.category.value, type: f.type.value,
            summary: f.summary.value, body: f.body.value, source: f.source.value,
            icon: form.querySelector('input[name="icon"]:checked')?.value || '',
            mesa: f.mesa.checked, draft: f.draft.checked,
        };
        const missing = !rule.title.trim() ? 'Ponle un título a la regulación.'
            : !rule.category.trim() ? 'Elige o escribe una categoría.'
            : !rule.body.trim() ? 'Escribe el texto de la regulación.' : '';
        if (missing) { errorBox.textContent = missing; errorBox.hidden = false; return; }
        saveBtn.disabled = true;
        saveBtn.textContent = 'Guardando…';
        try {
            const data = await call({ action: 'save', id: editingId, rule });
            location.href = location.pathname + '#' + encodeURIComponent(data.id);
            location.reload();
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.hidden = false;
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar';
        }
    });
})();
