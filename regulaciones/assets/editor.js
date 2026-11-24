/*
 * Favor de Jack · Regulaciones: editor visual del texto (solo admin).
 *
 * El texto se sigue guardando con el formato sencillo de siempre (## apartado,
 * - punto, 1. paso, > nota, | tabla |, **negrita**, *cursiva*), pero nadie tiene
 * que escribirlo a mano: los botones lo ponen y la vista previa enseña cómo
 * quedará, con el mismo aspecto que la página.
 */
(() => {
    'use strict';

    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    // La cursiva usa «lookbehind», que los navegadores muy viejos no entienden:
    // si falla, la vista previa sigue funcionando sin cursiva.
    let EM = null;
    try { EM = new RegExp('(?<![\\w*])\\*(?!\\s)(.+?)(?<!\\s)\\*(?![\\w*])', 'gu'); } catch { /* sin cursiva */ }

    function inline(text) {
        let html = esc(text).replace(/\*\*(.+?)\*\*/gu, '<strong>$1</strong>');
        if (EM) html = html.replace(EM, '<em>$1</em>');
        return html;
    }

    /** Copia de reg_render() de lib.php: mismo texto → mismo HTML. */
    function render(text) {
        const out = [];
        let block = null;
        let items = [];
        const flush = () => {
            if (block === null) return;
            if (block === 'p') out.push('<p>' + items.map(inline).join(' ') + '</p>');
            else if (block === 'ol' || block === 'ul') {
                out.push(`<${block} class="reg-${block}">` + items.map(i => '<li>' + inline(i) + '</li>').join('') + `</${block}>`);
            } else if (block === 'note') {
                out.push('<div class="reg-note"><span class="reg-note-mark" aria-hidden="true">!</span><p>' + items.map(inline).join(' ') + '</p></div>');
            } else if (block === 'table') {
                const rows = items.map(line => line.trim().replace(/^\|+|\|+$/g, '').split('|').map(c => c.trim()));
                const head = rows.shift() || [];
                out.push('<div class="reg-table"><table><thead><tr>' + head.map(c => '<th>' + inline(c) + '</th>').join('')
                    + '</tr></thead><tbody>' + rows.map(r => '<tr>' + r.map(c => '<td>' + inline(c) + '</td>').join('') + '</tr>').join('')
                    + '</tbody></table></div>');
            }
            block = null;
            items = [];
        };
        for (const raw of String(text).split(/\r\n|\r|\n/)) {
            const line = raw.trim();
            if (line === '') { flush(); continue; }
            if (line.startsWith('## ')) {
                flush();
                out.push('<h3 class="reg-section"><span>' + inline(line.slice(3)) + '</span></h3>');
                continue;
            }
            let kind, value, m;
            if (/^\|.*\|$/u.test(line)) {
                if (/^\|[\s:|-]+\|$/.test(line)) continue;
                kind = 'table'; value = line;
            } else if ((m = line.match(/^\d+[.)]\s+(.*)$/u))) { kind = 'ol'; value = m[1]; }
            else if ((m = line.match(/^[-•*]\s+(.*)$/u))) { kind = 'ul'; value = m[1]; }
            else if (line.startsWith('>')) { kind = 'note'; value = line.slice(1).trimStart(); }
            else { kind = 'p'; value = line; }
            if (block !== kind) flush();
            block = kind;
            items.push(value);
        }
        flush();
        return out.join('\n');
    }

    // Marcas de comienzo de línea que ponen los botones de bloque.
    const PREFIX = {
        h: { re: /^##\s+/, make: () => '## ' },
        ul: { re: /^[-•*]\s+/, make: () => '- ' },
        ol: { re: /^\d+[.)]\s+/, make: n => n + '. ' },
        note: { re: /^>\s?/, make: () => '> ' },
    };
    const ANY_PREFIX = /^(##\s+|[-•*]\s+|\d+[.)]\s+|>\s?)/;

    const BUTTONS = [
        { act: 'h', glyph: '✥', label: 'Apartado', tip: 'Título de apartado (con su línea decorativa)' },
        { act: 'ul', glyph: '•', label: 'Lista', tip: 'Lista con puntos' },
        { act: 'ol', glyph: '1.', label: 'Pasos', tip: 'Pasos numerados' },
        { act: 'note', glyph: '!', label: 'Nota', tip: 'Recuadro de nota o cita' },
        { act: 'table', glyph: '▦', label: 'Tabla', tip: 'Tabla (la primera fila es la cabecera)' },
        { sep: true },
        { act: 'bold', glyph: 'N', label: 'Negrita', tip: 'Negrita (Ctrl+B)', cls: 'md-b' },
        { act: 'italic', glyph: 'C', label: 'Cursiva', tip: 'Cursiva (Ctrl+I)', cls: 'md-i' },
    ];

    /** Cambia [start, end) por text conservando «Deshacer» (Ctrl+Z) cuando el navegador lo permite. */
    function replace(ta, start, end, text, selStart, selEnd) {
        ta.focus();
        ta.setSelectionRange(start, end);
        let done = false;
        try { done = document.execCommand('insertText', false, text); } catch { done = false; }
        if (!done || ta.value.slice(start, start + text.length) !== text) {
            ta.setRangeText(text, start, end, 'end');
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
        ta.setSelectionRange(selStart ?? start + text.length, selEnd ?? selStart ?? start + text.length);
    }

    function lineRange(ta) {
        const v = ta.value;
        const start = v.lastIndexOf('\n', ta.selectionStart - 1) + 1;
        let endAt = ta.selectionEnd;
        if (endAt > ta.selectionStart && v[endAt - 1] === '\n') endAt -= 1;
        let end = v.indexOf('\n', endAt);
        if (end === -1) end = v.length;
        return { start, end, lines: v.slice(start, end).split('\n') };
    }

    function toggleBlock(ta, act) {
        const { start, end, lines } = lineRange(ta);
        const p = PREFIX[act];
        const filled = lines.filter(l => l.trim() !== '');
        const allHave = filled.length > 0 && filled.every(l => p.re.test(l.trim()));
        let n = 0;
        const next = lines.map(l => {
            const body = l.trim().replace(ANY_PREFIX, '');
            if (allHave) return body;
            if (l.trim() === '' && lines.length > 1) return l;
            n += 1;
            return p.make(n) + body;
        });
        // Un apartado nuevo queda separado del texto de arriba.
        let text = next.join('\n');
        let lead = '';
        if (act === 'h' && !allHave && start > 0 && ta.value[start - 2] !== undefined && ta.value[start - 2] !== '\n') lead = '\n';
        text = lead + text;
        const emptyLine = lines.length === 1 && lines[0].trim() === '';
        if (act === 'h' && emptyLine && !allHave) {
            replace(ta, start, end, lead + '## Nuevo apartado', start + lead.length + 3, start + lead.length + 17);
            return;
        }
        replace(ta, start, end, text);
    }

    function wrap(ta, mark, placeholder) {
        const { selectionStart: s, selectionEnd: e, value: v } = ta;
        const sel = v.slice(s, e);
        if (sel && sel.startsWith(mark) && sel.endsWith(mark) && sel.length > mark.length * 2) {
            const inner = sel.slice(mark.length, -mark.length);
            replace(ta, s, e, inner, s, s + inner.length);
            return;
        }
        if (v.slice(s - mark.length, s) === mark && v.slice(e, e + mark.length) === mark && (mark === '**' || v[s - 3] !== '*')) {
            replace(ta, s - mark.length, e + mark.length, sel, s - mark.length, e - mark.length);
            return;
        }
        // Los espacios de los bordes quedan fuera de la marca (si no, no se aplicaría).
        const lead = sel.match(/^\s*/)[0];
        const trail = sel.slice(lead.length).match(/\s*$/)[0];
        const core = sel.slice(lead.length, sel.length - trail.length) || placeholder;
        const text = lead + mark + core + mark + trail;
        const a = s + lead.length + mark.length;
        replace(ta, s, e, text, a, a + core.length);
    }

    function insertBlock(ta, text, selFrom, selLen) {
        const v = ta.value;
        const s = ta.selectionStart;
        const lineStart = v.lastIndexOf('\n', s - 1) + 1;
        const lineEnd = v.indexOf('\n', s) === -1 ? v.length : v.indexOf('\n', s);
        const atEmptyLine = v.slice(lineStart, lineEnd).trim() === '';
        const pos = atEmptyLine ? lineStart : lineEnd;
        const before = pos === 0 || (atEmptyLine && (lineStart < 2 || v[lineStart - 2] === '\n')) ? '' : (atEmptyLine ? '\n' : '\n\n');
        const end = atEmptyLine ? lineEnd : pos;
        const after = v.slice(end).startsWith('\n\n') || end === v.length ? '' : '\n';
        const full = before + text + after;
        const a = pos + before.length + selFrom;
        replace(ta, pos, end, full, a, a + selLen);
    }

    function addSection(ta, name) {
        const v = ta.value.replace(/\s+$/, '');
        const text = (v ? v + '\n\n' : '') + '## ' + name + '\n- ';
        ta.focus();
        ta.setSelectionRange(0, ta.value.length);
        replace(ta, 0, ta.value.length, text);
        ta.scrollTop = ta.scrollHeight;
    }

    // Enter dentro de una lista sigue la lista; Enter en un punto vacío la termina.
    function onEnter(ta, e) {
        if (e.shiftKey || e.isComposing || ta.selectionStart !== ta.selectionEnd) return;
        const v = ta.value;
        const s = ta.selectionStart;
        const lineStart = v.lastIndexOf('\n', s - 1) + 1;
        const line = v.slice(lineStart, s);
        const m = line.match(/^(\s*)([-•*]|\d+[.)]|>)(\s+)(.*)$/) || line.match(/^(\s*)(>)()(.*)$/);
        if (!m) return;
        e.preventDefault();
        if (m[4].trim() === '' && v.slice(s, v.indexOf('\n', s) === -1 ? v.length : v.indexOf('\n', s)).trim() === '') {
            replace(ta, lineStart, s, '');
            return;
        }
        let mark = m[2];
        const num = mark.match(/^(\d+)([.)])$/);
        if (num) mark = (Number(num[1]) + 1) + num[2];
        replace(ta, s, s, '\n' + m[1] + mark + (m[3] || ' '));
    }

    function enhance(host) {
        const ta = host.querySelector('textarea');
        if (!ta || host.dataset.ready) return null;
        host.dataset.ready = '1';
        const uid = ta.id || 'md' + Math.random().toString(36).slice(2);

        const bar = document.createElement('div');
        bar.className = 'md-toolbar';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', 'Formato del texto');
        bar.setAttribute('aria-controls', uid);
        for (const b of BUTTONS) {
            if (b.sep) { const sep = document.createElement('span'); sep.className = 'md-sep'; sep.setAttribute('aria-hidden', 'true'); bar.append(sep); continue; }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'md-btn ' + (b.cls || '');
            btn.dataset.act = b.act;
            btn.title = b.tip;
            btn.innerHTML = `<span class="md-glyph" aria-hidden="true">${b.glyph}</span><span class="md-label">${b.label}</span>`;
            bar.append(btn);
        }

        let sections = null;
        if (host.dataset.mdSections) {
            sections = document.createElement('div');
            sections.className = 'md-sections';
            const label = document.createElement('span');
            label.textContent = 'Añadir apartado:';
            sections.append(label);
            for (const name of host.dataset.mdSections.split('|')) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'md-section';
                btn.textContent = '+ ' + name;
                btn.addEventListener('click', () => { addSection(ta, name); });
                sections.append(btn);
            }
        }

        const tabs = document.createElement('div');
        tabs.className = 'md-tabs';
        tabs.setAttribute('role', 'tablist');
        tabs.innerHTML = '<button type="button" role="tab" aria-selected="true" data-tab="write">Escribir</button>'
            + '<button type="button" role="tab" aria-selected="false" data-tab="preview">Vista previa</button>';

        const panes = document.createElement('div');
        panes.className = 'md-panes';
        const preview = document.createElement('div');
        preview.className = 'md-preview';
        preview.setAttribute('aria-live', 'polite');
        preview.innerHTML = '<p class="md-preview-title" aria-hidden="true">Así se verá</p><div class="detail-body"></div>';
        const out = preview.querySelector('.detail-body');
        ta.replaceWith(panes);
        panes.append(ta, preview);

        const help = document.createElement('details');
        help.className = 'md-help';
        help.innerHTML = '<summary>¿Prefieres escribirlo a mano?</summary><p>Los botones solo ponen estas marcas al principio de la línea o alrededor de las palabras:</p>'
            + '<ul><li><code>## </code> apartado</li><li><code>- </code> punto de lista</li><li><code>1. </code> paso numerado</li>'
            + '<li><code>&gt; </code> nota en recuadro</li><li><code>| a | b |</code> fila de tabla</li><li><code>**texto**</code> negrita · <code>*texto*</code> cursiva</li>'
            + '<li>Una línea en blanco empieza un párrafo nuevo.</li></ul>';

        host.prepend(bar);
        if (sections) bar.after(sections);
        (sections || bar).after(tabs);
        host.append(help);

        const refresh = () => {
            out.innerHTML = ta.value.trim() ? render(ta.value) : '<p class="md-empty">Aquí aparecerá el texto tal como lo verán los jugadores.</p>';
        };
        let timer = 0;
        ta.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 120); });

        bar.addEventListener('mousedown', e => { if (e.target.closest('.md-btn')) e.preventDefault(); });
        bar.addEventListener('click', e => {
            const btn = e.target.closest('.md-btn');
            if (!btn) return;
            setTab('write');
            const act = btn.dataset.act;
            if (act === 'bold') wrap(ta, '**', 'texto en negrita');
            else if (act === 'italic') wrap(ta, '*', 'texto en cursiva');
            else if (act === 'table') insertBlock(ta, '| Columna 1 | Columna 2 |\n| dato | dato |\n| dato | dato |', 2, 9);
            else toggleBlock(ta, act);
        });
        ta.addEventListener('keydown', e => {
            if (e.key === 'Enter') { onEnter(ta, e); return; }
            if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'b' || e.key === 'B')) { e.preventDefault(); wrap(ta, '**', 'texto en negrita'); }
            if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'i' || e.key === 'I')) { e.preventDefault(); wrap(ta, '*', 'texto en cursiva'); }
        });

        function setTab(name) {
            host.classList.toggle('show-preview', name === 'preview');
            tabs.querySelectorAll('[data-tab]').forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
            if (name === 'preview') refresh();
        }
        tabs.addEventListener('click', e => { const t = e.target.closest('[data-tab]'); if (t) setTab(t.dataset.tab); });

        return { textarea: ta, refresh, reset: () => { setTab('write'); refresh(); } };
    }

    window.RegEditor = { enhance, render };
})();
