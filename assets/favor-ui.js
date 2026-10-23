/*
 * Favor de Jack · comportamiento común: menú de la cuenta, hoja «Más» del móvil
 * y la tecla Escape para cerrar ventanas en toda la web.
 */
(function () {
    'use strict';

    var lastFocus = null;

    function setOpen(toggle, panel, open) {
        if (!toggle || !panel) return;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
    }

    function closeAccount(returnFocus) {
        var toggle = document.querySelector('.favor-account-toggle');
        var panel = document.getElementById('favor-account-menu');
        if (!panel || panel.hidden) return false;
        setOpen(toggle, panel, false);
        if (returnFocus && toggle) toggle.focus();
        return true;
    }

    function openSheet() {
        var toggle = document.querySelector('.favor-more-toggle');
        var sheet = document.getElementById('favor-more');
        lastFocus = document.activeElement;
        setOpen(toggle, sheet, true);
        document.documentElement.style.overflow = 'hidden';
        var first = sheet && sheet.querySelector('a,button');
        if (first) first.focus();
    }

    function closeSheet() {
        var toggle = document.querySelector('.favor-more-toggle');
        var sheet = document.getElementById('favor-more');
        if (!sheet || sheet.hidden) return false;
        setOpen(toggle, sheet, false);
        document.documentElement.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        return true;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || event.defaultPrevented) return;
        // Las ventanas de cada sección (Banca, Archivos, Inventario, tiendas) ya se
        // cierran con Escape en su propio código; aquí, el menú y la hoja comunes.
        if (closeSheet() || closeAccount(true)) event.preventDefault();
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) return;

        var accountToggle = target.closest('.favor-account-toggle');
        if (accountToggle) {
            var panel = document.getElementById('favor-account-menu');
            setOpen(accountToggle, panel, panel && panel.hidden);
            return;
        }
        if (!target.closest('#favor-account-menu')) closeAccount(false);

        if (target.closest('.favor-more-toggle')) {
            var sheet = document.getElementById('favor-more');
            if (sheet && sheet.hidden) openSheet(); else closeSheet();
            return;
        }
        if (target.closest('.favor-sheet-close') || target.id === 'favor-more') closeSheet();
    });

    // Mantener el foco dentro de la hoja «Más» mientras está abierta.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') return;
        var sheet = document.getElementById('favor-more');
        if (!sheet || sheet.hidden) return;
        var items = sheet.querySelectorAll('a,button');
        if (!items.length) return;
        var first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
        else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
    });

    // Cerrar sesión desde cualquier botón antiguo marcado con data-favor-logout.
    document.addEventListener('click', function (event) {
        var trigger = event.target instanceof Element && event.target.closest('[data-favor-logout]');
        var form = document.getElementById('favor-logout-form');
        if (!trigger || !form) return;
        event.preventDefault();
        form.submit();
    });

    window.favorLogout = function () {
        var form = document.getElementById('favor-logout-form');
        if (form) form.submit();
        else window.location.href = (window.FAVOR_BASE || '/') + 'logout.php';
    };
})();
