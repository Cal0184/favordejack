/* Versión móvil del lanzador: el resultado se abre como hoja inferior.
   En escritorio no hace nada (todo sigue como antes). */
(function () {
    'use strict';
    var mq = window.matchMedia('(max-width: 819px)');
    var column = document.querySelector('.jack-right-column');
    if (!column || typeof window.goToSection !== 'function') return;

    var head = document.createElement('div');
    head.className = 'jack-sheet-head';
    head.innerHTML = '<p class="jack-sheet-title">Resultado</p>' +
        '<button type="button" class="jack-sheet-close" aria-label="Cerrar resultado">×</button>';
    column.insertBefore(head, column.firstChild);

    var backdrop = document.createElement('div');
    backdrop.className = 'jack-sheet-backdrop';
    document.body.appendChild(backdrop);

    var pill = document.createElement('button');
    pill.type = 'button';
    pill.className = 'jack-last-roll';
    pill.hidden = true;
    pill.innerHTML = '<b>–</b><span>Ver última tirada</span>';
    document.body.appendChild(pill);

    column.setAttribute('aria-label', 'Resultado de la tirada');

    function lastValue() {
        var anim = window.rollMode === 'animation';
        var el = document.getElementById(anim ? 'roll_animation_element_result_left_text_main' : 'roll_history_element_result_left_text_main');
        return el ? el.textContent.trim() : '';
    }

    function open() {
        document.body.classList.add('jack-roll-open');
        column.scrollTop = 0;
    }

    function close() {
        if (!document.body.classList.contains('jack-roll-open')) return;
        document.body.classList.remove('jack-roll-open');
        // El número final llega cuando termina la animación: se lee al cerrar.
        var v = lastValue();
        pill.querySelector('b').textContent = v || '–';
        pill.hidden = false;
    }

    var original = window.goToSection;
    window.goToSection = function (section) {
        if (!mq.matches) return original(section);
        if (section === 'rollSection') open();
        else if (section === 'selectDice') close();
        else original(section);
    };

    head.querySelector('.jack-sheet-close').addEventListener('click', close);
    backdrop.addEventListener('click', close);
    pill.addEventListener('click', open);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
    mq.addEventListener('change', function () {
        if (!mq.matches) document.body.classList.remove('jack-roll-open');
    });
})();
