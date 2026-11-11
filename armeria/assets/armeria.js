/* Favor de Jack · Armas y Armaduras. Sin librerías: todo el catálogo llega en la página
   y aquí se busca, filtra, ordena y pagina sin recargar. */
(() => {
    'use strict';
    const $ = (s, el = document) => el.querySelector(s);
    const boot = JSON.parse($('#armBoot').textContent || '{}');
    const ADMIN = !!boot.admin;
    let cat = boot.catalogo || { categorias: [], propiedades: [], reglas: [], objetos: [] };

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fold = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const paras = t => String(t || '').split(/\n{2,}|\n/).filter(x => x.trim()).map(p => `<p>${esc(p)}</p>`).join('');
    const mobile = () => window.matchMedia('(max-width: 899px)').matches;

    // ------------------------------------------------------------ índices
    let propByClave = {}, reglaById = {}, catByClave = {}, reglaUsos = {};
    function indexar() {
        propByClave = Object.fromEntries(cat.propiedades.map(p => [p.clave, p]));
        reglaById = Object.fromEntries(cat.reglas.map(r => [r.id, r]));
        catByClave = Object.fromEntries(cat.categorias.map(c => [c.clave, c]));
        reglaUsos = {};
        for (const o of cat.objetos) {
            if (o.reglaId) (reglaUsos[o.reglaId] ||= []).push(o);
            const regla = o.reglaId ? reglaById[o.reglaId] : null;
            o._texto = fold([
                o.nombre, catByClave[o.categoria]?.nombre, o.dano, o.ca, o.coste, o.propiedadesTexto,
                ...o.propiedades.map(p => (propByClave[p.clave]?.nombre || '') + ' ' + p.detalle),
                regla ? regla.titulo + ' ' + regla.texto : '',
            ].join(' '));
            o._nombre = fold(o.nombre);
            o._valor = o.tipo === 'arma' ? o.danoPromedio : (o.tipo === 'armadura' ? o.caBase : o.caBono);
        }
        $('#armTotal').textContent = cat.objetos.filter(o => o.activo !== false).length;
    }

    // ------------------------------------------------------------- estado
    const st = {
        q: '', cat: 'todo', dano: new Set(), props: new Set(), coste: '', uso: '', comp: '',
        fuerza: false, sigilo: false, regla: false, inactivos: false, avisos: false,
        orden: 'doc', porPagina: 50, pagina: 1, sel: null, vista: 'catalogo', qProp: '',
    };

    const GRUPOS = [
        { id: 'todo', nombre: 'Todo', test: () => true },
        { id: 'armas', nombre: 'Armas', test: o => o.tipo === 'arma' },
        { id: 'cat:simple', nombre: 'Simples', sub: true },
        { id: 'cat:simple-distancia', nombre: 'Simples a distancia', sub: true },
        { id: 'cat:marcial', nombre: 'Marciales', sub: true },
        { id: 'cat:marcial-distancia', nombre: 'Marciales a distancia', sub: true },
        { id: 'armaduras', nombre: 'Armaduras', test: o => o.tipo === 'armadura' },
        { id: 'cat:armadura-ligera', nombre: 'Ligeras', sub: true },
        { id: 'cat:armadura-media', nombre: 'Medias', sub: true },
        { id: 'cat:armadura-pesada', nombre: 'Pesadas', sub: true },
        { id: 'cat:escudo', nombre: 'Escudos' },
    ];
    const grupoTest = id => {
        const g = GRUPOS.find(x => x.id === id);
        if (g?.test) return g.test;
        const c = id.slice(4);
        return o => o.categoria === c;
    };
    const COSTES = [
        ['', 'Cualquier precio'], ['0-100', 'Hasta 1 Oro'], ['101-1000', '1 – 10 Oro'], ['1001-5000', '10 – 50 Oro'],
        ['5001-10000', '50 – 100 Oro'], ['10001-50000', '100 – 500 Oro'], ['50001-', 'Más de 500 Oro'],
    ];
    const ETIQ_CAT = {
        'simple': 'Arma simple cuerpo a cuerpo', 'simple-distancia': 'Arma simple a distancia',
        'marcial': 'Arma marcial cuerpo a cuerpo', 'marcial-distancia': 'Arma marcial a distancia',
        'armadura-ligera': 'Armadura ligera', 'armadura-media': 'Armadura media', 'armadura-pesada': 'Armadura pesada', 'escudo': 'Escudo',
    };
    const ICON = {
        arma: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 3H21v6.5L9 21.5 2.5 15z"/><path d="m5 13 6 6M3 21l3-3"/></svg>',
        distancia: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3c7 3 13 9 16 16"/><path d="M5 3 3 21l18-2"/><path d="M8 16 18 6M15 6h3v3"/></svg>',
        armadura: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8l4 3-2 5v9H6v-9L4 6z"/><path d="M8 3c0 2 2 3 4 3s4-1 4-3M12 6v14"/></svg>',
        escudo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="M12 3v18M4.5 11h15"/></svg>',
    };
    const iconoDe = o => o.tipo === 'arma' ? (o.categoria.endsWith('distancia') ? ICON.distancia : ICON.arma) : ICON[o.tipo];
    const MARCA_REGLA = '<span class="arm-mark" title="Tiene regla especial" aria-label="Tiene regla especial">✦</span>';

    // --------------------------------------------------------- utilidades
    function valorTexto(o) {
        if (o.tipo === 'arma') return o.dano || '—';
        if (o.tipo === 'armadura') return 'CA ' + (o.ca || '—');
        return o.ca && o.ca !== '-' ? 'CA ' + o.ca : 'CA —';
    }
    function desTexto(o) {
        if (o.modDes === 'completo') return 'Completo';
        if (o.modDes === 'max') return `Máx. +${o.desMax ?? '?'}`;
        if (o.modDes === 'ninguno') return 'No se suma';
        return '—';
    }
    const propLabel = p => (propByClave[p.clave]?.nombre || p.clave) + (p.detalle ? ` (${p.detalle})` : '');

    // ------------------------------------------------------------ filtrado
    function filtrar(ignorar) {
        const tokens = fold(st.q).split(/\s+/).filter(Boolean);
        const gtest = grupoTest(ignorar === 'cat' ? 'todo' : st.cat);
        let rango = null;
        if (st.coste) { const [a, b] = st.coste.split('-'); rango = [Number(a) || 0, b === '' ? Infinity : Number(b)]; }
        return cat.objetos.filter(o => {
            if (!st.inactivos && o.activo === false) return false;
            if (!gtest(o)) return false;
            for (const t of tokens) if (!o._texto.includes(t)) return false;
            if (st.dano.size && !(o.tiposDano || []).some(t => st.dano.has(t))) return false;
            if (st.props.size) {
                const mine = new Set(o.propiedades.map(p => p.clave));
                for (const p of st.props) if (!mine.has(p)) return false;
            }
            if (rango && (o.costeCobre == null || o.costeCobre < rango[0] || o.costeCobre > rango[1])) return false;
            if (st.uso === 'cuerpo' && !(o.tipo === 'arma' && !o.categoria.endsWith('distancia'))) return false;
            if (st.uso === 'distancia' && !(o.tipo === 'arma' && o.categoria.endsWith('distancia'))) return false;
            if (st.comp === 'simple' && !o.categoria.startsWith('simple')) return false;
            if (st.comp === 'marcial' && !o.categoria.startsWith('marcial')) return false;
            if (st.fuerza && o.fuerzaMin == null) return false;
            if (st.sigilo && !o.sigiloDesventaja) return false;
            if (st.regla && !o.reglaId) return false;
            if (st.avisos && !o.notas) return false;
            return true;
        });
    }
    function ordenar(list) {
        const [k, dir] = st.orden.split('-');
        const m = dir === 'desc' ? -1 : 1;
        const tipoRank = { arma: 0, armadura: 1, escudo: 2 };
        const tokens = fold(st.q).trim();
        const out = list.slice();
        const cmpNull = (a, b) => (a == null) - (b == null);
        out.sort((a, b) => {
            if (k === 'nombre') return m * a.nombre.localeCompare(b.nombre, 'es', { sensitivity: 'base' });
            if (k === 'coste') return cmpNull(a.costeCobre, b.costeCobre) || m * ((a.costeCobre ?? 0) - (b.costeCobre ?? 0)) || a.nombre.localeCompare(b.nombre, 'es');
            if (k === 'valor') return tipoRank[a.tipo] - tipoRank[b.tipo] || cmpNull(a._valor, b._valor) || m * ((a._valor ?? 0) - (b._valor ?? 0)) || a.nombre.localeCompare(b.nombre, 'es');
            // Orden del documento; con búsqueda, primero lo que coincide por nombre.
            if (tokens) {
                const an = a._nombre.startsWith(tokens) ? 0 : a._nombre.includes(tokens) ? 1 : 2;
                const bn = b._nombre.startsWith(tokens) ? 0 : b._nombre.includes(tokens) ? 1 : 2;
                if (an !== bn) return an - bn;
            }
            return 0;
        });
        return out;
    }

    // ------------------------------------------------------------- render
    function renderCats() {
        const base = filtrar('cat');
        const html = GRUPOS.map(g => {
            const n = base.filter(grupoTest(g.id)).length;
            return `<button type="button" class="arm-cat${g.sub ? ' sub' : ''}" data-cat="${g.id}" aria-pressed="${st.cat === g.id}"><span>${esc(g.nombre)}</span><b>${n}</b></button>`;
        }).join('');
        $('#armCats').innerHTML = html;
        $('#armCatsStrip').innerHTML = GRUPOS.map(g => `<button type="button" role="tab" data-cat="${g.id}" aria-selected="${st.cat === g.id}">${esc(g.nombre)}</button>`).join('');
    }

    function renderFiltros() {
        const tipos = [...new Set(cat.objetos.flatMap(o => o.tiposDano || []))].sort((a, b) => a.localeCompare(b, 'es'));
        const usadas = {};
        for (const o of cat.objetos) for (const p of o.propiedades) usadas[p.clave] = (usadas[p.clave] || 0) + 1;
        const props = cat.propiedades.filter(p => usadas[p.clave]).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
        const chk = (name, val, label, on) => `<label class="arm-check"><input type="checkbox" data-f="${name}" value="${esc(val)}"${on ? ' checked' : ''}><span>${label}</span></label>`;
        const seg = (name, opts) => `<div class="arm-seg" role="group">${opts.map(([v, l]) => `<button type="button" data-seg="${name}" data-v="${v}" aria-pressed="${st[name] === v}">${l}</button>`).join('')}</div>`;
        $('#armFiltrosBody').innerHTML = `
            <fieldset><legend>Uso</legend>
                ${seg('uso', [['', 'Todas'], ['cuerpo', 'Cuerpo a cuerpo'], ['distancia', 'A distancia']])}
                ${seg('comp', [['', 'Todas'], ['simple', 'Simples'], ['marcial', 'Marciales']])}
            </fieldset>
            <fieldset><legend>Tipo de daño</legend><div class="arm-checks">
                ${tipos.map(t => chk('dano', t, esc(t[0].toUpperCase() + t.slice(1)), st.dano.has(t))).join('')}
            </div></fieldset>
            <fieldset><legend>Propiedades <small>(todas las marcadas)</small></legend><div class="arm-chips">
                ${props.map(p => `<button type="button" class="arm-chip" data-prop="${esc(p.clave)}" aria-pressed="${st.props.has(p.clave)}">${esc(p.nombre)} <small>${usadas[p.clave]}</small></button>`).join('')}
            </div></fieldset>
            <fieldset><legend>Coste</legend>
                <select data-f="coste" aria-label="Intervalo de precio">${COSTES.map(([v, l]) => `<option value="${v}"${st.coste === v ? ' selected' : ''}>${l}</option>`).join('')}</select>
            </fieldset>
            <fieldset><legend>Otros</legend><div class="arm-checks">
                ${chk('regla', '1', 'Con regla especial ' + MARCA_REGLA, st.regla)}
                ${chk('fuerza', '1', 'Requisito de Fuerza', st.fuerza)}
                ${chk('sigilo', '1', 'Desventaja en Sigilo', st.sigilo)}
                ${ADMIN ? chk('inactivos', '1', 'Mostrar desactivados', st.inactivos) + chk('avisos', '1', 'Con avisos de revisión', st.avisos) : ''}
            </div></fieldset>`;
    }

    function nFiltros() {
        return st.dano.size + st.props.size + (st.coste ? 1 : 0) + (st.uso ? 1 : 0) + (st.comp ? 1 : 0)
            + st.fuerza + st.sigilo + st.regla + st.avisos + st.inactivos;
    }

    let visibles = [];
    function renderLista() {
        visibles = ordenar(filtrar());
        const total = visibles.length;
        const pp = st.porPagina || total || 1;
        const paginas = Math.max(1, Math.ceil(total / pp));
        st.pagina = Math.min(Math.max(1, st.pagina), paginas);
        const desde = (st.pagina - 1) * pp;
        const page = st.porPagina ? visibles.slice(desde, desde + pp) : visibles;
        $('#armFilas').innerHTML = page.map(o => `
            <tr data-id="${o.id}" tabindex="0" aria-selected="${o.id === st.sel}"${o.activo === false ? ' class="inactivo"' : ''}>
                <td class="arm-c-nombre"><div class="arm-c-in"><span class="arm-ico">${iconoDe(o)}</span><span class="arm-nombre">${esc(o.nombre)}${o.reglaId ? MARCA_REGLA : ''}${ADMIN && o.notas ? '<span class="arm-aviso" title="Tiene avisos de revisión">!</span>' : ''}</span><span class="arm-c-catm">${esc(catByClave[o.categoria]?.nombre || '')}</span></div></td>
                <td class="arm-col-cat">${esc(catByClave[o.categoria]?.nombre || '')}</td>
                <td class="arm-col-num arm-c-valor">${esc(valorTexto(o))}</td>
                <td class="arm-col-num arm-c-coste">${esc(o.coste || '—')}</td>
            </tr>`).join('');
        $('#armVacio').hidden = total > 0;
        $('#armCount').innerHTML = total === 0 ? 'Sin resultados'
            : `<strong>${total}</strong> ${total === 1 ? 'objeto' : 'objetos'}${st.porPagina && paginas > 1 ? ` · ${desde + 1}–${Math.min(desde + pp, total)}` : ''}`;
        const n = nFiltros();
        const b = $('#armFiltrosN');
        b.hidden = n === 0; b.textContent = n;
        // Paginación compacta.
        if (paginas <= 1) { $('#armPager').innerHTML = ''; return; }
        const btn = (p, l = p, extra = '') => `<button type="button" data-page="${p}"${p === st.pagina ? ' aria-current="page"' : ''}${extra}>${l}</button>`;
        let html = btn(Math.max(1, st.pagina - 1), '‹', st.pagina === 1 ? ' disabled' : '') ;
        for (let p = 1; p <= paginas; p++) {
            if (p === 1 || p === paginas || Math.abs(p - st.pagina) <= 1) html += btn(p);
            else if (Math.abs(p - st.pagina) === 2) html += '<span>…</span>';
        }
        html += btn(Math.min(paginas, st.pagina + 1), '›', st.pagina === paginas ? ' disabled' : '');
        $('#armPager').innerHTML = html;
    }

    function stat(label, value, extra = '') {
        return `<div class="arm-stat${extra}"><span>${esc(label)}</span><strong>${esc(value || '—')}</strong></div>`;
    }

    function renderFicha() {
        const el = $('#armFicha');
        const o = cat.objetos.find(x => x.id === st.sel);
        document.body.classList.toggle('arm-ficha-abierta', !!o && mobile());
        if (!o) { el.innerHTML = '<p class="arm-ficha-vacia">Elige un objeto del catálogo para ver su ficha.</p>'; return; }
        const regla = o.reglaId ? reglaById[o.reglaId] : null;
        let stats = '';
        if (o.tipo === 'arma') {
            stats = stat('Daño', o.dano) + stat('Tipo', (o.tiposDano || []).map(t => t[0].toUpperCase() + t.slice(1)).join(' / ') || '—') + stat('Coste', o.coste) + (o.alcance ? stat('Rango', o.alcance + ' pies') : '');
        } else if (o.tipo === 'armadura') {
            stats = stat('Clase de armadura', o.ca, ' wide') + stat('Destreza', desTexto(o)) + stat('Fuerza', o.fuerza) + stat('Sigilo', o.sigilo) + stat('Coste', o.coste);
        } else {
            stats = stat('Bonif. CA', o.ca && o.ca !== '-' ? o.ca : '—') + stat('Fuerza', o.fuerza) + stat('Sigilo', o.sigilo) + stat('Coste', o.coste);
        }
        const tags = o.propiedades.map(p => `<button type="button" class="arm-tag" data-def="${esc(p.clave)}" title="Ver definición">${esc(propLabel(p))}</button>`).join('');
        const otros = regla ? (reglaUsos[regla.id] || []).filter(x => x.id !== o.id) : [];
        el.innerHTML = `
            ${mobile() ? '<button type="button" class="arm-ficha-cerrar" data-cerrar-ficha>‹ Volver a la lista</button>' : ''}
            <header class="arm-ficha-head">
                <span class="arm-ficha-ico">${iconoDe(o)}</span>
                <div><h2>${esc(o.nombre)}</h2><p>${esc(ETIQ_CAT[o.categoria] || catByClave[o.categoria]?.nombre || '')}${o.activo === false ? ' · <em>desactivado</em>' : ''}</p></div>
            </header>
            <div class="arm-stats">${stats}</div>
            ${o.tipo !== 'armadura' ? `<section class="arm-sec"><h3>Propiedades</h3>${tags ? `<div class="arm-tags">${tags}</div>` : '<p class="arm-muted">Sin propiedades.</p>'}
                ${o.propiedadesTexto && o.propiedadesTexto !== tags ? `<p class="arm-orig">En la tabla: «${esc(o.propiedadesTexto)}»</p>` : ''}</section>` : ''}
            ${regla ? `<section class="arm-sec arm-regla"><h3>${MARCA_REGLA} Regla especial${fold(regla.titulo) !== fold(o.nombre) ? ` · <span>${esc(regla.titulo)}</span>` : ''}</h3>${paras(regla.texto)}
                ${otros.length ? `<p class="arm-muted">También se aplica a: ${otros.map(x => `<button type="button" class="arm-link" data-ir="${x.id}">${esc(x.nombre)}</button>`).join(', ')}</p>` : ''}</section>` : ''}
            ${ADMIN ? `<section class="arm-sec arm-adm">
                ${o.notas ? `<div class="arm-notas"><h3>Avisos de revisión</h3><ul>${o.notas.split('\n').map(n => `<li>${esc(n)}</li>`).join('')}</ul></div>` : ''}
                <p class="arm-muted">Identificador: <code>${esc(o.clave)}</code> · ${o.origen === 'admin' ? 'añadido a mano' : 'del documento'}${o.editado ? ' · editado' : ''}</p>
                <button type="button" class="arm-btn arm-btn-gold" data-editar="${o.id}">Editar</button>
            </section>` : `<p class="arm-id">Ref. ${esc(o.clave)}</p>`}`;
    }

    function renderGlosario() {
        const tokens = fold(st.qProp).split(/\s+/).filter(Boolean);
        const usos = {};
        for (const o of cat.objetos) if (o.activo !== false) for (const p of o.propiedades) usos[p.clave] = (usos[p.clave] || 0) + 1;
        const list = cat.propiedades.filter(p => { const t = fold(p.nombre + ' ' + p.tituloDocumento + ' ' + p.definicion); return tokens.every(x => t.includes(x)); });
        $('#armGlosario').innerHTML = (cat.propiedadesIntro && !tokens.length ? `<p class="arm-glos-intro">${esc(cat.propiedadesIntro)}</p>` : '')
            + (list.length ? list.map(p => `
            <article class="arm-glos" id="prop-${esc(p.clave)}">
                <header><h2>${esc(p.nombre)}</h2>${p.tituloDocumento && fold(p.tituloDocumento) !== fold(p.nombre) ? `<span class="arm-muted">En el documento: «${esc(p.tituloDocumento)}»</span>` : ''}</header>
                ${p.definicion ? paras(p.definicion) : '<p class="arm-muted">El documento no incluye una definición.</p>'}
                <footer>
                    ${usos[p.clave] ? `<button type="button" class="arm-link" data-ver-prop="${esc(p.clave)}">Ver ${usos[p.clave]} ${usos[p.clave] === 1 ? 'objeto' : 'objetos'} con esta propiedad</button>` : '<span class="arm-muted">Ningún objeto la usa.</span>'}
                    ${ADMIN ? `<button type="button" class="arm-btn" data-editar-prop="${p.id}">Editar</button>` : ''}
                </footer>
            </article>`).join('') : '<p class="arm-vacio">Ninguna propiedad coincide.</p>');
    }

    function render() {
        renderCats();
        renderLista();
        renderFicha();
        guardarURL();
    }

    // ---------------------------------------------------------------- URL
    function guardarURL() {
        const u = new URLSearchParams();
        if (st.q) u.set('q', st.q);
        if (st.cat !== 'todo') u.set('cat', st.cat);
        if (st.props.size) u.set('prop', [...st.props].join(','));
        if (st.dano.size) u.set('dano', [...st.dano].join(','));
        for (const k of ['coste', 'uso', 'comp']) if (st[k]) u.set(k, st[k]);
        for (const k of ['fuerza', 'sigilo', 'regla']) if (st[k]) u.set(k, '1');
        if (st.orden !== 'doc') u.set('orden', st.orden);
        if (st.porPagina !== 50) u.set('n', st.porPagina);
        if (st.pagina > 1) u.set('p', st.pagina);
        const o = cat.objetos.find(x => x.id === st.sel);
        if (o) u.set('ver', o.clave);
        if (st.vista === 'glosario') u.set('vista', 'propiedades');
        const qs = u.toString();
        history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    }
    function leerURL() {
        const u = new URLSearchParams(location.search);
        st.q = u.get('q') || '';
        const c = u.get('cat');
        if (c && GRUPOS.some(g => g.id === c)) st.cat = c;
        (u.get('prop') || '').split(',').filter(Boolean).forEach(p => st.props.add(p));
        (u.get('dano') || '').split(',').filter(Boolean).forEach(p => st.dano.add(p));
        for (const k of ['coste', 'uso', 'comp']) st[k] = u.get(k) || '';
        for (const k of ['fuerza', 'sigilo', 'regla']) st[k] = u.get(k) === '1';
        st.orden = u.get('orden') || 'doc';
        const n = Number(u.get('n'));
        if ([0, 25, 50, 100].includes(n) && u.has('n')) st.porPagina = n;
        st.pagina = Number(u.get('p')) || 1;
        const ver = u.get('ver');
        const o = ver && cat.objetos.find(x => x.clave === ver);
        if (o) st.sel = o.id;
        if (u.get('vista') === 'propiedades') st.vista = 'glosario';
        $('#armBuscar').value = st.q;
        $('#armOrden').value = st.orden;
        $('#armPorPagina').value = String(st.porPagina);
    }

    // ------------------------------------------------------------ acciones
    function seleccionar(id, abrir = true) {
        st.sel = id;
        $('#armFilas').querySelectorAll('tr').forEach(tr => tr.setAttribute('aria-selected', String(Number(tr.dataset.id) === id)));
        renderFicha();
        guardarURL();
        if (abrir && mobile()) { window.scrollTo({ top: 0 }); $('#armFicha').focus({ preventScroll: true }); }
    }
    function cerrarFicha() {
        const id = st.sel;
        st.sel = null;
        renderFicha();
        guardarURL();
        const tr = id && $(`#armFilas tr[data-id="${id}"]`);
        if (tr) { tr.focus({ preventScroll: true }); tr.scrollIntoView({ block: 'center' }); }
    }
    function cambiar(fn) { fn(); st.pagina = 1; render(); }
    function limpiar() {
        cambiar(() => {
            st.dano.clear(); st.props.clear();
            Object.assign(st, { coste: '', uso: '', comp: '', fuerza: false, sigilo: false, regla: false, avisos: false, inactivos: false, q: '', cat: 'todo' });
            $('#armBuscar').value = '';
        });
        renderFiltros();
    }
    function vista(v) {
        st.vista = v;
        $('#arm').dataset.vista = v;
        $('#vistaCatalogo').hidden = v !== 'catalogo';
        $('#vistaGlosario').hidden = v !== 'glosario';
        $('#tabCatalogo').setAttribute('aria-selected', String(v === 'catalogo'));
        $('#tabGlosario').setAttribute('aria-selected', String(v === 'glosario'));
        if (v === 'glosario') renderGlosario();
        document.body.classList.toggle('arm-ficha-abierta', v === 'catalogo' && !!st.sel && mobile());
        guardarURL();
    }
    function verDefinicion(clave) {
        const p = propByClave[clave];
        if (!p) return;
        $('#armPropTitulo').textContent = p.nombre;
        $('#armPropTexto').innerHTML = (p.tituloDocumento && fold(p.tituloDocumento) !== fold(p.nombre) ? `<p class="arm-muted">En el documento: «${esc(p.tituloDocumento)}»</p>` : '')
            + (p.definicion ? paras(p.definicion) : '<p class="arm-muted">El documento no incluye una definición.</p>');
        $('#armPropGlosario').dataset.clave = clave;
        abrirDialogo($('#armPropDialog'));
    }
    function abrirDialogo(d) { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }
    function cerrarDialogo(d) { if (typeof d.close === 'function') d.close(); else d.removeAttribute('open'); }

    let toastT = 0;
    function toast(msg, bad = false) {
        const t = $('#armToast');
        t.textContent = msg; t.hidden = false; t.classList.toggle('bad', bad);
        clearTimeout(toastT); toastT = setTimeout(() => { t.hidden = true; }, 4200);
    }

    // ------------------------------------------------------------- eventos
    let qT = 0;
    $('#armBuscar').addEventListener('input', e => { clearTimeout(qT); qT = setTimeout(() => cambiar(() => { st.q = e.target.value.trim(); }), 120); });
    $('#armBuscarProp').addEventListener('input', e => { st.qProp = e.target.value; renderGlosario(); });
    $('#armOrden').addEventListener('change', e => cambiar(() => { st.orden = e.target.value; }));
    $('#armPorPagina').addEventListener('change', e => cambiar(() => { st.porPagina = Number(e.target.value); }));
    document.querySelector('.arm-table thead').addEventListener('click', e => {
        const b = e.target.closest('[data-sort]');
        if (!b) return;
        const k = b.dataset.sort;
        const sel = st.orden === k ? (k === 'nombre' ? 'nombre-desc' : k + '-desc') : (st.orden === k + '-desc' ? 'doc' : (k === 'valor' ? 'valor-desc' : k));
        $('#armOrden').value = sel;
        cambiar(() => { st.orden = sel; });
    });
    document.addEventListener('click', e => {
        const t = e.target;
        let b;
        if ((b = t.closest('[data-cat]'))) return cambiar(() => { st.cat = b.dataset.cat; });
        if ((b = t.closest('#armFilas tr'))) return seleccionar(Number(b.dataset.id));
        if ((b = t.closest('[data-page]'))) { st.pagina = Number(b.dataset.page); renderLista(); guardarURL(); document.querySelector('.arm-lista').scrollIntoView({ block: 'start', behavior: 'auto' }); return; }
        if ((b = t.closest('[data-def]'))) return verDefinicion(b.dataset.def);
        if ((b = t.closest('[data-ir]'))) { const id = Number(b.dataset.ir); if (!visibles.some(o => o.id === id)) limpiar(); return seleccionar(id); }
        if ((b = t.closest('[data-prop]'))) { const k = b.dataset.prop; cambiar(() => { st.props.has(k) ? st.props.delete(k) : st.props.add(k); }); b.setAttribute('aria-pressed', String(st.props.has(k))); return; }
        if ((b = t.closest('[data-seg]'))) { const k = b.dataset.seg; cambiar(() => { st[k] = b.dataset.v; }); renderFiltros(); return; }
        if ((b = t.closest('[data-vista]')) && b.tagName === 'BUTTON') return vista(b.dataset.vista);
        if ((b = t.closest('[data-ver-prop]'))) { limpiar(); st.props.add(b.dataset.verProp); renderFiltros(); vista('catalogo'); render(); return; }
        if (t.closest('[data-limpiar]') || t.closest('#armLimpiar')) return limpiar();
        if (t.closest('[data-cerrar-ficha]')) return cerrarFicha();
        if ((b = t.closest('[data-cerrar]'))) return cerrarDialogo(b.closest('dialog'));
        if (t.closest('#armFiltrosBtn')) {
            const open = !$('#armFiltros').classList.contains('open');
            $('#armFiltros').classList.toggle('open', open);
            $('#armFiltrosBtn').setAttribute('aria-expanded', String(open));
            return;
        }
        if (t.closest('#armPropGlosario')) {
            const k = $('#armPropGlosario').dataset.clave;
            cerrarDialogo($('#armPropDialog'));
            st.qProp = ''; $('#armBuscarProp').value = '';
            vista('glosario');
            document.getElementById('prop-' + k)?.scrollIntoView({ block: 'start' });
            return;
        }
        if (ADMIN) adminClick(t);
    });
    $('#armFiltrosBody').addEventListener('change', e => {
        const i = e.target;
        const f = i.dataset.f;
        if (!f) return;
        cambiar(() => {
            if (f === 'dano') i.checked ? st.dano.add(i.value) : st.dano.delete(i.value);
            else if (f === 'coste') st.coste = i.value;
            else st[f] = i.checked;
        });
    });
    $('#armFilas').addEventListener('keydown', e => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); seleccionar(Number(tr.dataset.id)); }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next = e.key === 'ArrowDown' ? tr.nextElementSibling : tr.previousElementSibling;
            if (next) { next.focus(); seleccionar(Number(next.dataset.id), false); }
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.body.classList.contains('arm-ficha-abierta') && !document.querySelector('dialog[open]')) cerrarFicha();
    });
    // Clic fuera del contenido de un diálogo lo cierra.
    document.querySelectorAll('dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) cerrarDialogo(d); }));
    window.matchMedia('(max-width: 899px)').addEventListener('change', () => renderFicha());

    // ------------------------------------------------------- administración
    async function api(body) {
        const r = await fetch(boot.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ csrf: boot.csrf, ...body }) });
        let j = null;
        try { j = await r.json(); } catch (_) { /* vacío */ }
        if (!j || !j.ok) throw new Error(j?.error || 'No se pudo guardar. Revisa la conexión.');
        if (j.catalogo) { cat = j.catalogo; indexar(); renderFiltros(); }
        return j;
    }

    let editando = null;
    function campo(label, html, cls = '') { return `<label class="arm-field ${cls}"><span>${label}</span>${html}</label>`; }
    const inp = (name, v, attrs = '') => `<input name="${name}" value="${esc(v ?? '')}" ${attrs}>`;

    function abrirEditor(o) {
        editando = o ? { ...o } : { id: 0, tipo: 'arma', categoria: 'simple', nombre: '', coste: '', dano: '', alcance: '', propiedades: [], reglaId: null };
        pintarEditor();
        abrirDialogo($('#armEditor'));
    }
    function pintarEditor() {
        const o = editando;
        const regla = o.reglaId ? reglaById[o.reglaId] : null;
        const compartida = regla ? (reglaUsos[regla.id] || []).filter(x => x.id !== o.id) : [];
        const cats = cat.categorias.filter(c => c.tipo === o.tipo);
        const mine = Object.fromEntries((o.propiedades || []).map(p => [p.clave, p.detalle]));
        const props = cat.propiedades.slice().sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
        let especifico = '';
        if (o.tipo === 'arma') {
            especifico = campo('Daño', inp('dano', o.dano, 'placeholder="1d8 cortante" maxlength="120"'))
                + campo('Rango (pies)', inp('alcance', o.alcance, 'placeholder="20/60" maxlength="40"'));
        } else {
            especifico = campo(o.tipo === 'armadura' ? 'Clase de armadura (texto)' : 'Bonificación de CA (texto)', inp('ca', o.ca, `placeholder="${o.tipo === 'armadura' ? '14 + DES (máx. +2)' : '+2'}" maxlength="60"`))
                + (o.tipo === 'armadura'
                    ? campo('CA base (número)', inp('caBase', o.caBase, 'type="number" min="0" max="40"'))
                    + campo('Modificador de Destreza', `<select name="modDes"><option value="completo"${o.modDes === 'completo' ? ' selected' : ''}>Completo</option><option value="max"${o.modDes === 'max' ? ' selected' : ''}>Con máximo</option><option value="ninguno"${o.modDes === 'ninguno' ? ' selected' : ''}>No se suma</option></select>`)
                    + campo('Máximo de Destreza', inp('desMax', o.desMax, 'type="number" min="0" max="10"'))
                    : campo('Bonificación (número)', inp('caBono', o.caBono, 'type="number" min="-10" max="20"')))
                + campo('Fuerza (texto)', inp('fuerza', o.fuerza, 'placeholder="Fue 13" maxlength="40"'))
                + campo('Fuerza mínima (número)', inp('fuerzaMin', o.fuerzaMin, 'type="number" min="1" max="30"'))
                + campo('Sigilo (texto)', inp('sigilo', o.sigilo, 'placeholder="Desventaja" maxlength="40"'))
                + `<label class="arm-check"><input type="checkbox" name="sigiloDesventaja"${o.sigiloDesventaja ? ' checked' : ''}><span>Desventaja en Sigilo (para el filtro)</span></label>`;
        }
        $('#armEditorTitulo').textContent = o.id ? 'Editar ' + o.nombre : 'Nuevo objeto';
        $('#armEditorBody').innerHTML = `
            <div class="arm-editor-grid">
                ${campo('Tipo', `<select name="tipo">${['arma', 'armadura', 'escudo'].map(t => `<option value="${t}"${o.tipo === t ? ' selected' : ''}>${t[0].toUpperCase() + t.slice(1)}</option>`).join('')}</select>`)}
                ${campo('Categoría', `<select name="categoria">${cats.map(c => `<option value="${c.clave}"${o.categoria === c.clave ? ' selected' : ''}>${esc(c.nombre)}</option>`).join('')}</select>`)}
                ${campo('Nombre', inp('nombre', o.nombre, 'required maxlength="160"'), 'span2')}
                ${campo('Coste', inp('coste', o.coste, 'placeholder="10 Oro" maxlength="40"'))}
                ${especifico}
            </div>
            ${o.tipo !== 'armadura' ? `<fieldset class="arm-editor-props"><legend>Propiedades <small>(marca y añade el detalle si hace falta: «1d8», «20/60»)</small></legend>
                ${props.map(p => `<div class="arm-editor-prop"><label class="arm-check"><input type="checkbox" data-ep="${esc(p.clave)}"${p.clave in mine ? ' checked' : ''}><span>${esc(p.nombre)}</span></label><input data-epd="${esc(p.clave)}" value="${esc(mine[p.clave] || '')}" maxlength="60" aria-label="Detalle de ${esc(p.nombre)}"></div>`).join('')}
            </fieldset>` : ''}
            <fieldset class="arm-editor-regla"><legend>Regla especial</legend>
                ${compartida.length ? `<p class="arm-warn">Esta regla la comparte con: ${compartida.map(x => esc(x.nombre)).join(', ')}. Si cambias el texto, cambia en todos.</p>` : ''}
                ${campo('Título', inp('reglaTitulo', regla?.titulo || '', 'maxlength="160" placeholder="Igual que el nombre si lo dejas vacío"'))}
                ${campo('Texto completo (déjalo vacío si no tiene)', `<textarea name="reglaTexto" rows="7">${esc(regla?.texto || '')}</textarea>`)}
            </fieldset>
            ${o.notas ? `<div class="arm-notas"><h3>Avisos de la importación</h3><ul>${o.notas.split('\n').map(n => `<li>${esc(n)}</li>`).join('')}</ul></div>` : ''}
            ${o.original && o.original.length ? `<p class="arm-orig">Fila original del documento: ${o.original.map(c => `«${esc(c)}»`).join(' · ')}</p>` : ''}`;
        $('#armEditorError').hidden = true;
        $('#armEditorBorrar').hidden = !(o.id && o.origen === 'admin');
        $('#armEditorActivar').hidden = !o.id;
        $('#armEditorActivar').textContent = o.activo === false ? 'Reactivar' : 'Desactivar';
    }
    function leerEditor() {
        const f = $('#armEditorForm');
        const o = { id: editando.id };
        f.querySelectorAll('[name]').forEach(i => { o[i.name] = i.type === 'checkbox' ? i.checked : i.value; });
        o.propiedades = [...f.querySelectorAll('[data-ep]')].filter(i => i.checked).map(i => ({ clave: i.dataset.ep, detalle: f.querySelector(`[data-epd="${i.dataset.ep}"]`).value }));
        return o;
    }
    if (ADMIN) {
        $('#armEditorForm').addEventListener('change', e => {
            if (e.target.name === 'tipo') {
                const keep = leerEditor();
                Object.assign(editando, keep, { tipo: e.target.value, categoria: cat.categorias.find(c => c.tipo === e.target.value)?.clave });
                pintarEditor();
            }
        });
        $('#armEditorForm').addEventListener('submit', async e => {
            e.preventDefault();
            const data = leerEditor();
            if (!data.nombre.trim()) { $('#armEditorError').textContent = 'El objeto necesita un nombre.'; $('#armEditorError').hidden = false; return; }
            const btn = $('#armEditorGuardar');
            btn.disabled = true;
            try {
                if (editando._prop) {
                    const j = await api({ action: 'propiedad', propiedad: data });
                    cerrarDialogo($('#armEditor'));
                    renderGlosario(); render(); toast(j.message);
                } else {
                    const j = await api({ action: 'guardar', objeto: data });
                    cerrarDialogo($('#armEditor'));
                    st.sel = j.selected;
                    const nuevo = cat.objetos.find(x => x.id === j.selected);
                    if (nuevo && !filtrar().some(x => x.id === nuevo.id)) limpiar();
                    render(); toast(j.message);
                }
            } catch (err) {
                $('#armEditorError').textContent = err.message; $('#armEditorError').hidden = false;
            } finally { btn.disabled = false; }
        });
    }
    function abrirEditorProp(p) {
        editando = { _prop: true, id: p ? p.id : 0 };
        $('#armEditorTitulo').textContent = p ? 'Editar propiedad' : 'Nueva propiedad';
        $('#armEditorBody').innerHTML = campo('Nombre', inp('nombre', p?.nombre || '', 'required maxlength="80"'))
            + (p?.tituloDocumento ? `<p class="arm-muted">En el documento: «${esc(p.tituloDocumento)}»</p>` : '')
            + campo('Definición (texto completo)', `<textarea name="definicion" rows="10">${esc(p?.definicion || '')}</textarea>`);
        $('#armEditorError').hidden = true;
        $('#armEditorBorrar').hidden = !(p && p.origen === 'admin');
        $('#armEditorActivar').hidden = true;
        abrirDialogo($('#armEditor'));
    }
    async function adminClick(t) {
        let b;
        if (t.closest('#armNuevo')) return abrirEditor(null);
        if (t.closest('#armNuevaProp')) return abrirEditorProp(null);
        if ((b = t.closest('[data-editar]'))) return abrirEditor(cat.objetos.find(x => x.id === Number(b.dataset.editar)));
        if ((b = t.closest('[data-editar-prop]'))) return abrirEditorProp(cat.propiedades.find(x => x.id === Number(b.dataset.editarProp)));
        if (t.closest('#armEditorActivar')) {
            const on = editando.activo === false;
            if (!on && !confirm(`¿Desactivar «${editando.nombre}»? Los jugadores dejarán de verlo. Podrás reactivarlo desde «Mostrar desactivados».`)) return;
            try {
                const j = await api({ action: 'activar', id: editando.id, activo: on });
                cerrarDialogo($('#armEditor'));
                if (!on) st.inactivos = true;
                renderFiltros(); render(); toast(j.message);
            } catch (err) { toast(err.message, true); }
            return;
        }
        if (t.closest('#armEditorBorrar')) {
            if (editando._prop) {
                if (!confirm('¿Eliminar esta propiedad del glosario?')) return;
                try { const j = await api({ action: 'eliminar_propiedad', id: editando.id }); cerrarDialogo($('#armEditor')); renderGlosario(); toast(j.message); } catch (err) { toast(err.message, true); }
                return;
            }
            if (!confirm(`¿Eliminar «${editando.nombre}» para siempre? Esta acción no se puede deshacer.`)) return;
            try { const j = await api({ action: 'eliminar', id: editando.id }); cerrarDialogo($('#armEditor')); st.sel = null; render(); toast(j.message); } catch (err) { toast(err.message, true); }
            return;
        }
        if (t.closest('#armReimportar')) {
            if (!confirm('¿Volver a importar los datos del documento? No se tocan los objetos que editaste ni los desactivados.')) return;
            try { const j = await api({ action: 'reimportar' }); render(); toast(j.message); } catch (err) { toast(err.message, true); }
        }
    }

    // ---------------------------------------------------------------- inicio
    indexar();
    leerURL();
    renderFiltros();
    if (!st.sel && !mobile()) {
        const first = ordenar(filtrar())[0];
        if (first) st.sel = first.id;
    }
    render();
    if (st.vista === 'glosario') vista('glosario');
})();
