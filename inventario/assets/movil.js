/* Inventario en el móvil: los desplegables de filtro se pliegan tras un botón «Filtros». */
(function () {
    'use strict';
    var box = document.getElementById('inventoryFilters');
    var toggle = document.getElementById('filtersToggle');
    var badge = document.getElementById('filtersBadge');
    if (!box || !toggle) return;
    var selects = [
        ['categoryFilter', ''],
        ['equipmentFilter', 'all'],
        ['sortFilter', 'default']
    ].map(function (pair) { return [document.getElementById(pair[0]), pair[1]]; })
     .filter(function (pair) { return pair[0]; });

    function updateBadge() {
        var n = selects.filter(function (pair) { return pair[0].value !== pair[1]; }).length;
        if (badge) {
            badge.textContent = String(n);
            badge.hidden = n === 0;
        }
    }

    toggle.addEventListener('click', function () {
        var open = !box.classList.contains('filters-open');
        box.classList.toggle('filters-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    selects.forEach(function (pair) { pair[0].addEventListener('change', updateBadge); });
    updateBadge();
})();
