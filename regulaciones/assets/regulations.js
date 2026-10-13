(() => {
    'use strict';

    const search = document.getElementById('ruleSearch');
    const cards = [...document.querySelectorAll('.rule-card')];
    const typeTabs = [...document.querySelectorAll('.type-tab')];
    const categoryChips = [...document.querySelectorAll('.category-chip')];
    const visibleCount = document.getElementById('visibleCount');
    const emptyState = document.getElementById('emptyState');
    const emptyText = document.getElementById('emptyText');

    let activeType = typeTabs.find(btn => btn.classList.contains('active'))?.dataset.type || 'regla';
    let activeCategory = '';

    const normalize = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    function updateTypeCounts() {
        document.querySelectorAll('[data-count-type]').forEach(el => {
            const type = el.dataset.countType;
            el.textContent = cards.filter(card => card.dataset.type === type).length;
        });
    }

    function applyFilters() {
        const q = normalize(search?.value || '');
        let shown = 0;

        for (const card of cards) {
            const matchesType = card.dataset.type === activeType;
            const matchesCategory = !activeCategory || card.dataset.category === activeCategory;
            const haystack = normalize(card.dataset.search || '');
            const matchesSearch = !q || haystack.includes(q);
            const show = matchesType && matchesCategory && matchesSearch;

            card.classList.toggle('hidden', !show);
            if (show) shown += 1;
        }

        if (visibleCount) visibleCount.textContent = String(shown);
        if (emptyState) emptyState.classList.toggle('hidden', shown !== 0);

        if (emptyText && shown === 0) {
            if (!cards.some(card => card.dataset.type === activeType)) {
                emptyText.textContent = 'Todavía no hay entradas registradas en esta sección.';
            } else {
                emptyText.textContent = 'Prueba con otro filtro o término de búsqueda.';
            }
        }
    }

    typeTabs.forEach(btn => {
        btn.addEventListener('click', () => {
            activeType = btn.dataset.type || 'regla';
            typeTabs.forEach(item => {
                const active = item === btn;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            activeCategory = '';
            categoryChips.forEach(chip => chip.classList.toggle('active', !chip.dataset.category));
            applyFilters();
        });
    });

    categoryChips.forEach(btn => {
        btn.addEventListener('click', () => {
            activeCategory = btn.dataset.category || '';
            categoryChips.forEach(item => item.classList.toggle('active', item === btn));
            applyFilters();
        });
    });

    search?.addEventListener('input', applyFilters);

    updateTypeCounts();
    applyFilters();
})();
