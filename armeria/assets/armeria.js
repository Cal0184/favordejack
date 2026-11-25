/* Favor de Jack · Armas y Armaduras. Sin librerías: todo el catálogo llega en la página
   y aquí se busca, filtra, ordena y pagina sin recargar. */
(() => {
    'use strict';
    const $ = (s, el = document) => el.querySelector(s);
    const boot = JSON.parse($('#armBoot').textContent || '{}');
    const ADMIN = !!boot.admin;
    let cat = boot.catalogo || { categorias: [], propiedades: [], reglas: [], objetos: [], notas: [], total: 0 };

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fold = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const paras = t => String(t || '').split(/\n{2,}|\n/).filter(x => x.trim()).map(p => `<p>${esc(p)}</p>`).join('');
    const mobile = () => window.matchMedia('(max-width: 899px)').matches;

    // ------------------------------------------------------------ índices
    let propByClave = {}, reglaById = {}, catByClave = {}, reglaUsos = {}, notasDe = {};
    function indexar() {
        cat.notas ||= [];
        notasDe = {};
        for (const n of cat.notas) if (n.objetoId) (notasDe[n.objetoId] ||= []).push(n);
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
                o.ataque ? ATRIB_BUSCA[o.ataque.atributo] + ' ' + o.ataque.modos.map(m => m.modo + ' ' + ATRIB_BUSCA[m.atributo]).join(' ') : '',
                (o.requisitos || []).map(r => r.texto).join(' '),
                o.descripcion,
            ].join(' '));
            const ats = new Set((o.ataque?.modos || []).map(m => m.atributo));
            o._fue = ats.has('FUE') || ats.has('FUE/DES');
            o._des = ats.has('DES') || ats.has('FUE/DES');
            o._elige = ats.has('FUE/DES');
            o._revisar = o.tipo === 'arma' && (!o.ataque || o.ataque.atributo === 'REVISAR' || !!o.ataque.duda);
            o._nombre = fold(o.nombre);
            o._valor = o.tipo === 'arma' ? o.danoPromedio : (o.tipo === 'armadura' ? o.caBase : o.caBono);
        }
        $('#armTotal').textContent = cat.total ?? cat.objetos.filter(o => o.activo !== false).length;
    }

    // ------------------------------------------------------------- estado
    const st = {
        q: '', cat: 'todo', dano: new Set(), props: new Set(), coste: '', costeMax: '', uso: '', comp: '',
        ataque: '', req: '', regla: '', sigilo: false, inactivos: false, avisos: false,
        orden: 'nombre', porPagina: 10, pagina: 1, sel: null, vista: 'catalogo', qProp: '', qRegla: '', secReglas: '',
    };

    const GRUPOS = [
        { id: 'todo', nombre: 'Todas', ico: 'todas', test: () => true },
        { id: 'cat:simple', nombre: 'Armas simples', ico: 'simple' },
        { id: 'cat:simple-distancia', nombre: 'Armas simples a distancia', ico: 'distancia' },
        { id: 'cat:marcial', nombre: 'Armas marciales', ico: 'marcial' },
        { id: 'cat:marcial-distancia', nombre: 'Armas marciales a distancia', ico: 'arco' },
        { id: 'cat:armadura-ligera', nombre: 'Armaduras ligeras', ico: 'armadura' },
        { id: 'cat:armadura-media', nombre: 'Armaduras medias', ico: 'armadura' },
        { id: 'cat:armadura-pesada', nombre: 'Armaduras pesadas', ico: 'armadura' },
        { id: 'cat:escudo', nombre: 'Escudos', ico: 'escudo' },
        // Grupos que se pueden pedir por URL (?cat=armas) aunque no tengan botón.
        { id: 'armas', nombre: 'Todas las armas', oculto: true, test: o => o.tipo === 'arma' },
        { id: 'armaduras', nombre: 'Todas las armaduras', oculto: true, test: o => o.tipo === 'armadura' },
    ];
    const ICO_CAT = {
        todas: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/></svg>',
        simple: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l11 11M4 6l2-2M15 13l3 3-2 2-3-3M17 17l3 3"/></svg>',
        marcial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4l10 10M3 5l2-2M13 12l3 3-2 2-3-3M15 16l4 4"/><path d="M20 4 10 14M21 5l-2-2M11 12l-3 3 2 2 3-3M9 16l-4 4"/></svg>',
        distancia: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20 18 6M14 5h5v5M4 20l1-4M4 20l4-1"/></svg>',
        arco: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3c8 2 13 7 15 15"/><path d="M6 3 3 21l18-3"/><path d="M7 17 17 7M14 7h3v3"/></svg>',
        armadura: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8l4 3-2 5v9H6v-9L4 6z"/><path d="M8 3c0 2 2 3 4 3s4-1 4-3M12 6v14"/></svg>',
        escudo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="M12 3v18M4.5 11h15"/></svg>',
    };
    const PILL = {
        'simple': ['Simple', 'simple'], 'simple-distancia': ['A distancia', 'dist'], 'marcial': ['Marcial', 'marcial'], 'marcial-distancia': ['Marcial a dist.', 'dist'],
        'armadura-ligera': ['Ligera', 'arm'], 'armadura-media': ['Media', 'arm'], 'armadura-pesada': ['Pesada', 'arm'], 'escudo': ['Escudo', 'esc'],
    };
    const pill = (o, cls = '') => { const [t, k] = PILL[o.categoria] || [catByClave[o.categoria]?.nombre || '', 'arm']; return `<span class="arm-pill p-${k}${cls}" title="${esc(catByClave[o.categoria]?.nombre || '')}">${esc(t)}</span>`; };
    // El filtro de atributo no tiene sentido mirando solo armaduras o escudos.
    const sinAtaque = () => st.cat === 'armaduras' || st.cat.startsWith('cat:armadura') || st.cat === 'cat:escudo';
    const ATRIB = { 'FUE': 'Fuerza', 'DES': 'Destreza', 'FUE/DES': 'Fuerza o Destreza', 'MODOS': 'Según el modo', 'REVISAR': 'Por revisar' };
    const ATRIB_CORTO = { 'FUE': 'FUE', 'DES': 'DES', 'FUE/DES': 'FUE / DES', 'MODOS': 'Según modo', 'REVISAR': 'Revisar' };
    const ATRIB_CLASE = { 'FUE': 'fue', 'DES': 'des', 'FUE/DES': 'ambos', 'MODOS': 'modos', 'REVISAR': 'revisar' };
    const ATRIB_BUSCA = { 'FUE': 'fuerza fue', 'DES': 'destreza des', 'FUE/DES': 'fuerza destreza fue des', 'MODOS': 'fuerza destreza modos', 'REVISAR': 'revisar' };
    const NOTA_TIPO = { aclaracion: 'Aclaración', excepcion: 'Excepción', nota: 'Nota mecánica', errata: 'Errata' };
    const badge = (at, extra = '') => at ? `<span class="arm-at at-${ATRIB_CLASE[at] || 'revisar'}"${extra}>${esc(ATRIB_CORTO[at] || at)}</span>` : '';
    // Atributos que permite un objeto, para la etiqueta de la tabla («FUE · DES» si depende del modo).
    function badgeDe(o) {
        if (!o.ataque) return '';
        if (o.ataque.atributo !== 'MODOS') return badge(o.ataque.atributo, ` title="Atributo de ataque: ${esc(ATRIB[o.ataque.atributo])}"`);
        return `<span class="arm-at at-modos" title="El atributo cambia según el modo de ataque">${[...new Set(o.ataque.modos.map(m => ATRIB_CORTO[m.atributo]))].join(' · ')}</span>`;
    }
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
    const TIPO_CORTO = {
        'simple': 'Simple', 'simple-distancia': 'Simple a dist.', 'marcial': 'Marcial', 'marcial-distancia': 'Marcial a dist.',
        'armadura-ligera': 'Arm. ligera', 'armadura-media': 'Arm. media', 'armadura-pesada': 'Arm. pesada', 'escudo': 'Escudo',
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
        const max = st.costeMax !== '' && Number(st.costeMax) >= 0 ? Number(st.costeMax) * 100 : null;
        const ataque = sinAtaque() ? '' : st.ataque;
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
            if (max !== null && (o.costeCobre == null || o.costeCobre > max)) return false;
            if (ataque === 'FUE' && !o._fue) return false;
            if (ataque === 'DES' && !o._des) return false;
            if (ataque === 'FUE/DES' && !o._elige) return false;
            if (ataque === 'REVISAR' && !o._revisar) return false;
            if (st.req) {
                const rs = o.requisitos || [];
                if (st.req === 'cualquiera' && !rs.length) return false;
                if (st.req === 'FUE' && !rs.some(r => r.atributo === 'FUE' || r.atributo === 'FUE/DES')) return false;
                if (st.req === 'DES' && !rs.some(r => r.atributo === 'DES' || r.atributo === 'FUE/DES')) return false;
                if (st.req === 'otros' && !rs.some(r => !r.atributo)) return false;
            }
            if (st.regla === 'con' && !o.reglaId) return false;
            if (st.regla === 'sin' && o.reglaId) return false;
            if (st.regla === 'excepcion' && !(o.ataque && (o.ataque.excepcion || o.ataque.atributo === 'MODOS'))) return false;
            if (st.regla === 'aclaracion' && !notasDe[o.id]) return false;
            if (st.sigilo && !o.sigiloDesventaja) return false;
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
        const visibles = GRUPOS.filter(g => !g.oculto);
        const html = visibles.map(g => {
            const n = base.filter(grupoTest(g.id)).length;
            return `<button type="button" class="arm-cat" data-cat="${g.id}" aria-pressed="${st.cat === g.id}"><i>${ICO_CAT[g.ico] || ''}</i><span>${esc(g.nombre)}</span><b>${n}</b></button>`;
        }).join('');
        $('#armCats').innerHTML = html;
        $('#armCatsStrip').innerHTML = visibles.map(g => `<button type="button" role="tab" data-cat="${g.id}" aria-selected="${st.cat === g.id}">${esc(g.nombre)}</button>`).join('');
    }

    const abiertos = new Set();
    function renderFiltros() {
        const tipos = [...new Set(cat.objetos.flatMap(o => o.tiposDano || []))].sort((a, b) => a.localeCompare(b, 'es'));
        const usadas = {};
        for (const o of cat.objetos) for (const p of o.propiedades) usadas[p.clave] = (usadas[p.clave] || 0) + 1;
        const props = cat.propiedades.filter(p => usadas[p.clave]).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
        const chk = (name, val, label, on) => `<label class="arm-check"><input type="checkbox" data-f="${name}" value="${esc(val)}"${on ? ' checked' : ''}><span>${label}</span></label>`;
        const seg = (name, opts) => `<div class="arm-seg" role="group">${opts.map(([v, l]) => `<button type="button" data-seg="${name}" data-v="${v}" aria-pressed="${st[name] === v}">${l}</button>`).join('')}</div>`;
        const sel = (name, label, opts, extra = '') => `<select data-f="${name}" aria-label="${esc(label)}"${extra}>${opts.map(([v, l]) => `<option value="${v}"${st[name] === v ? ' selected' : ''}>${l}</option>`).join('')}</select>`;
        const grupo = (id, titulo, n, body) => `<details class="arm-fgroup" data-grupo="${id}"${abiertos.has(id) ? ' open' : ''}><summary><span>${titulo}</span>${n ? `<b>${n}</b>` : ''}</summary><div class="arm-fgroup-body">${body}</div></details>`;
        const noAtq = sinAtaque();
        $('#armFiltrosBody').innerHTML =
            grupo('dano', 'Tipo de daño', st.dano.size, `<div class="arm-checks">${tipos.map(t => chk('dano', t, esc(t[0].toUpperCase() + t.slice(1)), st.dano.has(t))).join('')}</div>`)
            + grupo('ataque', 'Atributo de ataque', !noAtq && st.ataque ? 1 : 0,
                sel('ataque', 'Atributo de ataque', [['', 'Todos'], ['FUE', 'Fuerza'], ['DES', 'Destreza'], ['FUE/DES', 'Fuerza o Destreza'], ['REVISAR', 'Por revisar']], noAtq ? ' disabled' : '')
                + `<p class="arm-fhelp">${noAtq ? 'Las armaduras y los escudos no atacan: elige una categoría de armas o «Todas».' : '«Fuerza» incluye las armas que también admiten Destreza, y al revés. «Fuerza o Destreza» muestra solo las que te dejan elegir.'}</p>`)
            + grupo('props', 'Propiedades', st.props.size, `<p class="arm-fhelp">Se muestran los objetos que tienen todas las marcadas.</p><div class="arm-chips">${props.map(p => `<button type="button" class="arm-chip" data-prop="${esc(p.clave)}" aria-pressed="${st.props.has(p.clave)}">${esc(p.nombre)} <small>${usadas[p.clave]}</small></button>`).join('')}</div>`)
            + grupo('coste', 'Coste', (st.coste ? 1 : 0) + (st.costeMax !== '' ? 1 : 0),
                sel('coste', 'Intervalo de precio', COSTES)
                + `<label class="arm-inline">Coste máximo <input type="number" min="0" step="1" inputmode="decimal" data-f="costeMax" value="${esc(st.costeMax)}" placeholder="—"> Oro</label>`)
            + grupo('req', 'Requisitos', st.req ? 1 : 0, sel('req', 'Requisitos', [['', 'Cualquiera'], ['cualquiera', 'Con algún requisito o condición'], ['FUE', 'Requisito de Fuerza'], ['DES', 'Requisito de Destreza'], ['otros', 'Otros (tamaño, montura, competencia…)']]))
            + grupo('regla', 'Reglas especiales', st.regla ? 1 : 0, sel('regla', 'Reglas especiales', [['', 'Todas'], ['con', 'Con regla especial ✦'], ['sin', 'Sin regla especial'], ['excepcion', 'Con excepción de atributo'], ['aclaracion', 'Con aclaración de administración']]))
            + grupo('otros', 'Uso y otros', (st.uso ? 1 : 0) + (st.comp ? 1 : 0) + st.sigilo + st.avisos + st.inactivos,
                seg('uso', [['', 'Todas'], ['cuerpo', 'Cuerpo a cuerpo'], ['distancia', 'A distancia']])
                + seg('comp', [['', 'Todas'], ['simple', 'Simples'], ['marcial', 'Marciales']])
                + `<div class="arm-checks">${chk('sigilo', '1', 'Desventaja en Sigilo', st.sigilo)}${chk('avisos', '1', 'Con advertencias del documento', st.avisos)}${ADMIN ? chk('inactivos', '1', 'Mostrar desactivados', st.inactivos) : ''}</div>`);
    }

    function nFiltros() {
        return st.dano.size + st.props.size + (st.coste ? 1 : 0) + (st.costeMax !== '' ? 1 : 0) + (st.uso ? 1 : 0) + (st.comp ? 1 : 0)
            + (st.ataque && !sinAtaque() ? 1 : 0) + (st.req ? 1 : 0) + (st.regla ? 1 : 0) + st.sigilo + st.avisos + st.inactivos;
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
                <td class="arm-c-nombre"><div class="arm-c-in"><span class="arm-ico">${iconoDe(o)}</span>${o.imagen ? `<span class="arm-mini"><img src="${esc(o.imagen.mini)}" alt="" loading="lazy" decoding="async" width="48" height="48"></span>` : `<span class="arm-mini sin-img" aria-hidden="true">${iconoDe(o)}</span>`}<span class="arm-nombre">${esc(o.nombre)}${o.reglaId ? MARCA_REGLA : ''}${o.notas ? '<span class="arm-aviso" title="Tiene advertencias del documento">!</span>' : ''}</span><span class="arm-c-catm">${pill(o)}${badgeDe(o)}</span></div></td>
                <td class="arm-col-cat"><span class="arm-tipo">${pill(o)}${badgeDe(o)}</span></td>
                <td class="arm-col-num arm-c-valor">${esc(valorTexto(o))}</td>
                <td class="arm-col-num arm-c-coste">${esc(o.coste || '—')}</td>
                <td class="arm-col-chev" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 10 5 5 5-5"/></svg></td>
            </tr>`).join('');
        $('#armVacio').hidden = total > 0;
        $('#armCount').innerHTML = total === 0 ? 'Sin resultados'
            : `<strong>${total}</strong> ${total === 1 ? 'objeto' : 'objetos'}${st.porPagina && paginas > 1 ? ` · ${desde + 1}–${Math.min(desde + pp, total)}` : ''}`;
        $('.arm-pie').hidden = total === 0;
        const n = nFiltros();
        const b = $('#armFiltrosN');
        b.hidden = n === 0; b.textContent = n;
        // Anterior · Página X de Y · Siguiente
        $('#armPager').innerHTML = `<button type="button" class="arm-pg" data-page="${Math.max(1, st.pagina - 1)}"${st.pagina === 1 ? ' disabled' : ''}><span aria-hidden="true">‹</span> Anterior</button>
            <span class="arm-pg-n">Página ${st.pagina} de ${paginas}</span>
            <button type="button" class="arm-pg" data-page="${Math.min(paginas, st.pagina + 1)}"${st.pagina === paginas ? ' disabled' : ''}>Siguiente <span aria-hidden="true">›</span></button>`;
    }

    function stat(label, value, extra = '') {
        return `<div class="arm-stat${extra}"><span>${esc(label)}</span><strong>${esc(value || '—')}</strong></div>`;
    }

    function seccionAtaque(o) {
        const a = o.ataque;
        if (!a) return o.tipo === 'arma'
            ? `<section class="arm-sec arm-atq"><h3>Atributo de ataque</h3><p>${badge('REVISAR')} <span class="arm-muted">Sin clasificar todavía.</span></p></section>` : '';
        const varios = a.modos.length > 1;
        const titulo = a.atributo === 'MODOS' ? 'Depende del modo de ataque' : ATRIB[a.atributo];
        const modos = varios || a.atributo === 'MODOS' || a.modos.some(m => m.dano || m.nota)
            ? `<ul class="arm-modos">${a.modos.map(m => `<li><span class="arm-modo">${esc(m.modo)}</span>${badge(m.atributo)}${m.dano ? `<span class="arm-modo-dano">${esc(m.dano)}</span>` : ''}${m.nota ? `<small>${esc(m.nota)}</small>` : ''}</li>`).join('')}</ul>` : '';
        const base = a.base && a.base !== a.atributo ? `<p class="arm-muted">Por las reglas generales sería: ${esc(ATRIB[a.base] || a.base)}.</p>` : '';
        return `<section class="arm-sec arm-atq"><h3>Atributo de ataque</h3>
            <button type="button" class="arm-atq-btn" data-atq aria-expanded="false" aria-controls="armAtqExp">${a.atributo === 'MODOS' ? badgeDe(o) : badge(a.atributo)}<span>${esc(titulo)}</span><i aria-hidden="true">?</i></button>
            ${modos}
            <div class="arm-atq-exp" id="armAtqExp" hidden>
                <p><strong>${esc(o.nombre)} — ${esc(titulo)}</strong></p>
                ${a.motivo ? `<p>${esc(a.motivo)}</p>` : ''}
                ${a.excepcion ? `<p class="arm-atq-exc"><b>Excepción:</b> ${esc(a.excepcion)}</p>` : ''}
                ${base}
                ${a.editado ? '<p class="arm-muted">Clasificación corregida por la administración.</p>' : ''}
            </div>
            ${a.duda ? `<p class="arm-atq-duda"><b>Por revisar:</b> ${esc(a.duda)}</p>` : ''}
        </section>`;
    }
    function seccionRequisitos(o) {
        const rs = o.requisitos || [];
        if (!rs.length) return '';
        return `<section class="arm-sec"><h3>Requisitos y condiciones</h3><ul class="arm-reqs">${rs.map(r => `<li class="req-${r.tipo}"><span>${r.tipo === 'requisito' ? 'Requisito' : 'Condición'}</span>${esc(r.texto)}</li>`).join('')}</ul></section>`;
    }
    function notaHTML(n, conObjeto = false) {
        const obj = conObjeto && n.objetoId ? cat.objetos.find(x => x.id === n.objetoId) : null;
        return `<div class="arm-nota tipo-${esc(n.tipo)}${n.visible ? '' : ' borrador'}">
            <p class="arm-nota-head"><span class="arm-sello-adm">${esc(NOTA_TIPO[n.tipo] || 'Nota')} · administración</span>${n.visible ? '' : ' <em>(borrador, solo admin)</em>'}${obj ? ` · <button type="button" class="arm-link" data-ir="${obj.id}">${esc(obj.nombre)}</button>` : ''}</p>
            <h4>${esc(n.titulo)}</h4>${paras(n.texto)}
            ${ADMIN ? `<p class="arm-nota-adm"><span class="arm-muted">${esc(n.fecha || '')}${n.autor ? ' · ' + esc(n.autor) : ''}</span> <button type="button" class="arm-link" data-editar-nota="${n.id}">Editar</button></p>` : ''}
        </div>`;
    }

    const ORNA = '<div class="arm-orna" aria-hidden="true"><span></span><svg viewBox="0 0 40 12"><path d="M2 6h8M30 6h8" stroke="currentColor" stroke-width="1.2"/><path d="M20 1l5 5-5 5-5-5z" fill="none" stroke="currentColor" stroke-width="1.3"/><circle cx="12.5" cy="6" r="1.4" fill="currentColor"/><circle cx="27.5" cy="6" r="1.4" fill="currentColor"/></svg><span></span></div>';
    const CAT_FICHA = {
        'simple': 'Arma simple', 'simple-distancia': 'Arma simple a distancia', 'marcial': 'Arma marcial', 'marcial-distancia': 'Arma marcial a distancia',
        'armadura-ligera': 'Armadura ligera', 'armadura-media': 'Armadura media', 'armadura-pesada': 'Armadura pesada', 'escudo': 'Escudo',
    };
    function primeraFrase(t) {
        const txt = String(t || '').replace(/\s+/g, ' ').trim();
        const m = txt.match(/^.{20,}?[.!?](\s|$)/);
        const f = m ? m[0].trim() : txt;
        return f.length > 170 ? f.slice(0, 165).replace(/\s\S*$/, '') + '…' : f;
    }
    function caja(label, big, small = '', extra = '') {
        return `<div class="arm-caja${extra}"><span>${esc(label)}</span><strong>${esc(big || '—')}</strong>${small ? `<small>${esc(small)}</small>` : ''}</div>`;
    }
    function partir(texto) {
        // «1d10 cortante» → ['1d10', 'cortante']; «20 Oro» → ['20', 'oro'].
        const m = String(texto || '').match(/^\s*(\S+)\s+(.+)$/);
        return m ? [m[1], m[2].toLowerCase()] : [texto || '—', ''];
    }

    function renderFicha() {
        const el = $('#armFicha');
        const o = cat.objetos.find(x => x.id === st.sel);
        document.body.classList.toggle('arm-ficha-abierta', !!o && mobile() && st.vista === 'catalogo');
        $('#armFilas')?.querySelectorAll('tr').forEach(tr => tr.setAttribute('aria-selected', String(Number(tr.dataset.id) === st.sel)));
        if (!o) { el.innerHTML = '<p class="arm-ficha-vacia">Elige un objeto del catálogo para ver su ficha.</p>'; return; }
        const regla = o.reglaId ? reglaById[o.reglaId] : null;
        const [cn, cu] = partir(o.coste);
        const propsTexto = o.propiedades.map(propLabel).join(', ');
        let cajas = '';
        if (o.tipo === 'arma') {
            const [dn, du] = partir(o.dano);
            cajas = caja('Daño', dn, du) + caja('Coste', cn, cu) + caja('Propiedades', propsTexto || 'Ninguna', '', ' ancha');
        } else if (o.tipo === 'armadura') {
            cajas = caja('Clase de armadura', o.ca, desTexto(o) !== '—' ? 'Destreza: ' + desTexto(o) : '') + caja('Coste', cn, cu)
                + caja('Fuerza · Sigilo', o.fuerza && o.fuerza !== '—' ? o.fuerza : 'Sin requisito', o.sigilo && o.sigilo !== '—' ? 'Sigilo: ' + o.sigilo : 'Sin penalización', ' ancha');
        } else {
            cajas = caja('Bonif. CA', o.ca && o.ca !== '-' ? o.ca : '—') + caja('Coste', cn, cu) + caja('Propiedades', propsTexto || 'Ninguna', [o.fuerza && o.fuerza !== '—' ? 'Fuerza ' + o.fuerza : '', o.sigilo && o.sigilo !== '—' ? 'Sigilo: ' + o.sigilo : ''].filter(Boolean).join(' · '), ' ancha');
        }
        const props = o.propiedades.map(p => {
            const d = propByClave[p.clave];
            return `<li><button type="button" class="arm-tag" data-def="${esc(p.clave)}" title="Ver la definición completa">${esc(propLabel(p))}</button><span>${esc(primeraFrase(d?.definicion) || 'Sin definición en el documento.')}</span></li>`;
        }).join('');
        const otros = regla ? (reglaUsos[regla.id] || []).filter(x => x.id !== o.id) : [];
        const notas = notasDe[o.id] || [];
        el.innerHTML = `<div class="arm-ficha-in">
            ${mobile() ? '<button type="button" class="arm-ficha-cerrar" data-cerrar-ficha>‹ Volver a la lista</button>' : ''}
            <header class="arm-ficha-head${o.imagen ? ' con-img' : ''}">
                ${o.imagen ? `<button type="button" class="arm-ilus" data-ampliar title="Ver la ilustración en grande"><img src="${esc(o.imagen.ficha)}" alt="Ilustración: ${esc(o.nombre)}" decoding="async"></button>` : ''}
                <h2>${esc(o.nombre)}</h2>
                <span class="arm-catpill">${esc(CAT_FICHA[o.categoria] || catByClave[o.categoria]?.nombre || '')}</span>${o.activo === false ? ' <em class="arm-muted">desactivado</em>' : ''}
            </header>
            <div class="arm-cajas">${cajas}</div>
            ${o.alcance ? `<p class="arm-rango">Rango: ${esc(o.alcance)} pies</p>` : ''}
            ${ORNA}
            ${seccionAtaque(o)}
            ${o.descripcion ? `<section class="arm-sec"><h3>Descripción</h3><div class="arm-desc">${paras(o.descripcion)}</div></section>` : ''}
            ${props ? `<section class="arm-sec"><h3>Propiedades</h3><ul class="arm-props">${props}</ul>
                ${o.propiedadesTexto && fold(o.propiedadesTexto) !== fold(propsTexto) ? `<p class="arm-orig">En la tabla: «${esc(o.propiedadesTexto)}»</p>` : ''}</section>` : ''}
            ${seccionRequisitos(o)}
            ${regla ? ORNA + `<section class="arm-sec arm-regla"><h3>${MARCA_REGLA} Regla especial${fold(regla.titulo) !== fold(o.nombre) ? ` · <span>${esc(regla.titulo)}</span>` : ''}</h3>${paras(regla.texto)}
                <p class="arm-fuente">Texto del documento</p>
                ${otros.length ? `<p class="arm-muted">También se aplica a: ${otros.map(x => `<button type="button" class="arm-link" data-ir="${x.id}">${esc(x.nombre)}</button>`).join(', ')}</p>` : ''}</section>` : ''}
            ${notas.length ? `<section class="arm-sec"><h3>Aclaraciones</h3>${notas.map(n => notaHTML(n)).join('')}</section>` : ''}
            ${o.notas ? `<div class="arm-notas"><h3>Advertencias del documento</h3><ul>${o.notas.split('\n').map(n => `<li>${esc(n)}</li>`).join('')}</ul></div>` : ''}
            ${ADMIN ? `<section class="arm-sec arm-adm">
                <p class="arm-muted">Identificador: <code>${esc(o.clave)}</code> · ${o.origen === 'admin' ? 'añadido a mano' : 'del documento'}${o.editado ? ' · editado' : ''}</p>
                <div class="arm-adm-btns">
                    <button type="button" class="arm-btn arm-btn-gold" data-editar="${o.id}">Editar</button>
                    <button type="button" class="arm-btn" data-editar-ataque="${o.id}">Atributo y requisitos</button>
                    <button type="button" class="arm-btn" data-nueva-nota="${o.id}">+ Aclaración</button>
                </div>
            </section>` : `<p class="arm-id">Ref. ${esc(o.clave)}</p>`}
        </div>`;
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

    // ------------------------------------------------------ reglas y notas
    const SECCIONES = [
        ['', 'Todo'], ['aclaraciones', 'Aclaraciones'], ['reglas', 'Reglas especiales'], ['atributo', 'Excepciones de atributo'], ['avisos', 'Advertencias del documento'],
    ];
    function renderReglas() {
        const tokens = fold(st.qRegla).split(/\s+/).filter(Boolean);
        const ok = t => tokens.every(x => fold(t).includes(x));
        const sec = st.secReglas;
        const ver = id => !sec || sec === id;
        const activos = cat.objetos.filter(o => o.activo !== false || ADMIN);
        const ir = o => `<button type="button" class="arm-link" data-ir="${o.id}">${esc(o.nombre)}</button>`;
        let html = `<div class="arm-seg arm-seg-reglas" role="group" aria-label="Secciones">${SECCIONES.map(([v, l]) => `<button type="button" data-sec-reglas="${v}" aria-pressed="${sec === v}">${l}</button>`).join('')}</div>`;
        let n = 0;

        if (ver('aclaraciones')) {
            const list = cat.notas.filter(x => ok(x.titulo + ' ' + x.texto + ' ' + (cat.objetos.find(o => o.id === x.objetoId)?.nombre || '')));
            n += list.length;
            html += `<section class="arm-rsec"><header><h2>Aclaraciones de la administración</h2><p class="arm-muted">Texto añadido por la administración: no forma parte del documento original.</p>${ADMIN ? '<button type="button" class="arm-btn" data-nueva-nota="0">+ Nueva nota</button>' : ''}</header>
                ${list.length ? `<div class="arm-rgrid">${list.map(x => notaHTML(x, true)).join('')}</div>` : `<p class="arm-muted">${tokens.length ? 'Ninguna coincide.' : 'Todavía no hay aclaraciones.'}</p>`}</section>`;
        }
        if (ver('reglas')) {
            const list = cat.reglas.filter(r => ok(r.titulo + ' ' + r.texto + ' ' + (reglaUsos[r.id] || []).map(o => o.nombre).join(' ')));
            n += list.length;
            html += `<section class="arm-rsec"><header><h2>Reglas especiales <small>${list.length}</small></h2><p class="arm-muted">Texto del documento, tal cual. Cada regla enlaza con su objeto.</p></header>
                <div class="arm-rgrid">${list.map(r => `<article class="arm-rcard"><h3>${esc(r.titulo)}</h3>${paras(r.texto)}
                    <p class="arm-fuente">Texto del documento${(reglaUsos[r.id] || []).length ? ' · ' + (reglaUsos[r.id] || []).map(ir).join(', ') : ''}</p></article>`).join('')}</div></section>`;
        }
        if (ver('atributo')) {
            const list = activos.filter(o => o.ataque && (o.ataque.excepcion || o.ataque.atributo === 'MODOS' || o.ataque.editado || (o.ataque.base && o.ataque.base !== o.ataque.atributo)) && ok(o.nombre + ' ' + o.ataque.excepcion + ' ' + o.ataque.motivo));
            const revisar = activos.filter(o => o._revisar && ok(o.nombre + ' ' + (o.ataque?.duda || '')));
            n += list.length + revisar.length;
            html += `<section class="arm-rsec"><header><h2>Excepciones de atributo <small>${list.length}</small></h2><p class="arm-muted">Armas cuyo atributo no sale solo de la regla general (cuerpo a cuerpo: Fuerza · Sutil: Fuerza o Destreza · a distancia: Destreza).</p></header>
                <div class="arm-rgrid">${list.map(o => `<article class="arm-rcard"><h3>${ir(o)} ${badgeDe(o)}</h3>
                    <ul class="arm-modos">${o.ataque.modos.map(m => `<li><span class="arm-modo">${esc(m.modo)}</span>${badge(m.atributo)}${m.dano ? `<span class="arm-modo-dano">${esc(m.dano)}</span>` : ''}</li>`).join('')}</ul>
                    ${o.ataque.excepcion ? `<p>${esc(o.ataque.excepcion)}</p>` : ''}${o.ataque.editado ? '<p class="arm-fuente">Corregido por la administración</p>' : ''}</article>`).join('')}</div>
                <h3 class="arm-rsub">Por revisar <small>${revisar.length}</small></h3>
                ${revisar.length ? `<ul class="arm-rlist">${revisar.map(o => `<li>${ir(o)}${o.ataque?.duda ? ' — ' + esc(o.ataque.duda) : ' — sin clasificar'}</li>`).join('')}</ul>` : '<p class="arm-muted">Todas las armas tienen su atributo de ataque clasificado.</p>'}</section>`;
        }
        if (ver('avisos')) {
            const list = activos.filter(o => o.notas && ok(o.nombre + ' ' + o.notas));
            n += list.length;
            html += `<section class="arm-rsec"><header><h2>Advertencias del documento <small>${list.length}</small></h2><p class="arm-muted">Datos dudosos o incompletos del Word original. Se muestran tal cual hasta que la administración los corrija.</p></header>
                ${list.length ? `<ul class="arm-rlist">${list.map(o => `<li>${ir(o)}<ul>${o.notas.split('\n').map(t => `<li>${esc(t)}</li>`).join('')}</ul></li>`).join('')}</ul>` : '<p class="arm-muted">Sin advertencias.</p>'}</section>`;
        }
        if (tokens.length && !n) html += '<p class="arm-vacio">Nada coincide con la búsqueda.</p>';
        $('#armReglas').innerHTML = html;
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
        for (const k of ['coste', 'costeMax', 'uso', 'comp', 'ataque', 'req', 'regla']) if (st[k] !== '') u.set(k, st[k]);
        for (const k of ['sigilo', 'avisos']) if (st[k]) u.set(k, '1');
        if (st.orden !== 'nombre') u.set('orden', st.orden);
        if (st.porPagina !== 10) u.set('n', st.porPagina);
        if (st.pagina > 1) u.set('p', st.pagina);
        const o = cat.objetos.find(x => x.id === st.sel);
        if (o) u.set('ver', o.clave);
        if (st.vista === 'glosario') u.set('vista', 'propiedades');
        if (st.vista === 'reglas') u.set('vista', 'reglas');
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
        for (const k of ['coste', 'costeMax', 'uso', 'comp', 'ataque', 'req', 'regla']) st[k] = u.get(k) || '';
        if (!['', 'FUE', 'DES', 'FUE/DES', 'REVISAR'].includes(st.ataque)) st.ataque = '';
        for (const k of ['sigilo', 'avisos']) st[k] = u.get(k) === '1';
        st.orden = u.get('orden') || 'nombre';
        const n = Number(u.get('n'));
        if ([0, 10, 25, 50].includes(n) && u.has('n')) st.porPagina = n;
        st.pagina = Number(u.get('p')) || 1;
        const ver = u.get('ver');
        const o = ver && cat.objetos.find(x => x.clave === ver);
        if (o) st.sel = o.id;
        if (u.get('vista') === 'propiedades') st.vista = 'glosario';
        if (u.get('vista') === 'reglas') st.vista = 'reglas';
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
            Object.assign(st, { coste: '', costeMax: '', uso: '', comp: '', ataque: '', req: '', regla: '', sigilo: false, avisos: false, inactivos: false, q: '', cat: 'todo' });
            $('#armBuscar').value = '';
        });
        renderFiltros();
    }
    function vista(v) {
        st.vista = v;
        $('#arm').dataset.vista = v;
        $('#vistaCatalogo').hidden = v !== 'catalogo';
        $('#vistaGlosario').hidden = v !== 'glosario';
        $('#vistaReglas').hidden = v !== 'reglas';
        $('#tabCatalogo').setAttribute('aria-selected', String(v === 'catalogo'));
        $('#tabGlosario').setAttribute('aria-selected', String(v === 'glosario'));
        $('#tabReglas').setAttribute('aria-selected', String(v === 'reglas'));
        if (v === 'glosario') renderGlosario();
        if (v === 'reglas') renderReglas();
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
    $('#armBuscarRegla').addEventListener('input', e => { st.qRegla = e.target.value; renderReglas(); });
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
        if ((b = t.closest('[data-cat]'))) { cambiar(() => { st.cat = b.dataset.cat; }); renderFiltros(); return; }
        if ((b = t.closest('#armFilas tr'))) return seleccionar(Number(b.dataset.id));
        if ((b = t.closest('[data-page]'))) { st.pagina = Number(b.dataset.page); renderLista(); guardarURL(); document.querySelector('.arm-lista').scrollIntoView({ block: 'start', behavior: 'auto' }); return; }
        if ((b = t.closest('[data-def]'))) return verDefinicion(b.dataset.def);
        if ((b = t.closest('[data-ampliar]'))) {
            const o = cat.objetos.find(x => x.id === st.sel);
            if (!o?.imagen) return;
            $('#armImgGrande').src = o.imagen.ficha;
            $('#armImgGrande').alt = 'Ilustración: ' + o.nombre;
            $('#armImgTitulo').textContent = o.nombre;
            return abrirDialogo($('#armImgDialog'));
        }
        if ((b = t.closest('[data-atq]'))) {
            const exp = $('#armAtqExp');
            exp.hidden = !exp.hidden;
            b.setAttribute('aria-expanded', String(!exp.hidden));
            return;
        }
        if ((b = t.closest('[data-sec-reglas]'))) { st.secReglas = b.dataset.secReglas; renderReglas(); return; }
        if ((b = t.closest('[data-ir]'))) {
            const id = Number(b.dataset.ir);
            if (st.vista !== 'catalogo') vista('catalogo');
            if (!visibles.some(o => o.id === id)) limpiar();
            return seleccionar(id);
        }
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
            else if (i.tagName === 'SELECT' || f === 'costeMax') st[f] = i.value;
            else st[f] = i.checked;
        });
    });
    $('#armFiltrosBody').addEventListener('input', e => {
        if (e.target.dataset.f !== 'costeMax') return;
        clearTimeout(qT); qT = setTimeout(() => { st.costeMax = e.target.value; st.pagina = 1; renderCats(); renderLista(); guardarURL(); }, 250);
    });
    $('#armFiltrosBody').addEventListener('toggle', e => {
        const g = e.target.dataset?.grupo;
        if (g) e.target.open ? abiertos.add(g) : abiertos.delete(g);
    }, true);
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
            ${campo('Descripción (opcional, se muestra en la ficha)', `<textarea name="descripcion" rows="3">${esc(o.descripcion || '')}</textarea>`)}
            <fieldset class="arm-editor-regla"><legend>Regla especial</legend>
                ${compartida.length ? `<p class="arm-warn">Esta regla la comparte con: ${compartida.map(x => esc(x.nombre)).join(', ')}. Si cambias el texto, cambia en todos.</p>` : ''}
                ${campo('Título', inp('reglaTitulo', regla?.titulo || '', 'maxlength="160" placeholder="Igual que el nombre si lo dejas vacío"'))}
                ${campo('Texto completo (déjalo vacío si no tiene)', `<textarea name="reglaTexto" rows="7">${esc(regla?.texto || '')}</textarea>`)}
            </fieldset>
            ${campo('Advertencias del documento (una por línea; bórralas cuando corrijas el dato)', `<textarea name="notas" rows="3">${esc(o.notas || '')}</textarea>`)}
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
            if (editando._ataque || editando._nota) return;
            if (e.target.name === 'tipo') {
                const keep = leerEditor();
                Object.assign(editando, keep, { tipo: e.target.value, categoria: cat.categorias.find(c => c.tipo === e.target.value)?.clave });
                pintarEditor();
            }
        });
        $('#armEditorForm').addEventListener('submit', async e => {
            e.preventDefault();
            const data = leerEditor();
            if (!editando._ataque && !editando._nota && !data.nombre.trim()) { $('#armEditorError').textContent = 'El objeto necesita un nombre.'; $('#armEditorError').hidden = false; return; }
            const btn = $('#armEditorGuardar');
            btn.disabled = true;
            try {
                if (editando._prop) {
                    const j = await api({ action: 'propiedad', propiedad: data });
                    cerrarDialogo($('#armEditor'));
                    renderGlosario(); render(); toast(j.message);
                } else if (editando._ataque) {
                    const j = await api({ action: 'ataque', id: editando.id, ...leerAtaque() });
                    cerrarDialogo($('#armEditor'));
                    render(); toast(j.message);
                } else if (editando._nota) {
                    const j = await api({ action: 'nota', nota: { id: editando.id, ...data } });
                    cerrarDialogo($('#armEditor'));
                    render(); if (st.vista === 'reglas') renderReglas(); toast(j.message);
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
    // Editor del atributo de ataque y los requisitos.
    const OPC_AT = [['FUE', 'Fuerza'], ['DES', 'Destreza'], ['FUE/DES', 'Fuerza o Destreza'], ['REVISAR', 'Por revisar']];
    const filaModo = (m = {}) => `<div class="arm-erow" data-modo>
        ${inp('modo', m.modo || '', 'placeholder="Cuerpo a cuerpo" maxlength="80" aria-label="Modo"')}
        <select data-k="atributo" aria-label="Atributo">${OPC_AT.map(([v, l]) => `<option value="${v}"${m.atributo === v ? ' selected' : ''}>${l}</option>`).join('')}</select>
        ${inp('dano', m.dano || '', 'placeholder="Daño (opcional)" maxlength="60" aria-label="Daño de este modo"')}
        ${inp('nota', m.nota || '', 'placeholder="Nota (opcional)" maxlength="300" aria-label="Nota"')}
        <button type="button" class="arm-x" data-quitar-fila aria-label="Quitar">×</button></div>`;
    const filaReq = (r = {}) => `<div class="arm-erow arm-erow-req" data-req>
        <select data-k="tipo" aria-label="Tipo"><option value="requisito"${r.tipo !== 'condicion' ? ' selected' : ''}>Requisito</option><option value="condicion"${r.tipo === 'condicion' ? ' selected' : ''}>Condición</option></select>
        <select data-k="atributo" aria-label="Atributo"><option value="">Sin atributo</option>${OPC_AT.slice(0, 3).map(([v, l]) => `<option value="${v}"${r.atributo === v ? ' selected' : ''}>${l}</option>`).join('')}</select>
        ${inp('minimo', r.minimo ?? '', 'type="number" min="1" max="30" placeholder="Mín." aria-label="Puntuación mínima"')}
        ${inp('texto', r.texto || '', 'placeholder="Requiere Fuerza 20…" maxlength="300" aria-label="Texto"')}
        <button type="button" class="arm-x" data-quitar-fila aria-label="Quitar">×</button></div>`;
    function abrirEditorAtaque(o) {
        const a = o.ataque || { modos: o.tipo === 'arma' ? [{ modo: 'Cuerpo a cuerpo', atributo: 'REVISAR' }] : [], motivo: '', excepcion: '', duda: '' };
        editando = { _ataque: true, id: o.id };
        $('#armEditorTitulo').textContent = 'Atributo y requisitos · ' + o.nombre;
        $('#armEditorBody').innerHTML = `
            ${a.base ? `<p class="arm-muted">Clasificación por reglas generales (se conserva aparte): <strong>${esc(ATRIB[a.base] || a.base)}</strong>.</p>` : ''}
            <fieldset><legend>Modos de ataque <small>(uno por fila; deja la lista vacía si no ataca)</small></legend>
                <div id="armModos">${a.modos.map(filaModo).join('')}</div>
                <button type="button" class="arm-btn" data-anadir="modo">+ Modo</button></fieldset>
            ${campo('Explicación (se muestra al pulsar el atributo)', `<textarea name="motivo" rows="3">${esc(a.motivo)}</textarea>`)}
            ${campo('Excepción (regla especial que cambia el atributo)', `<textarea name="excepcion" rows="2">${esc(a.excepcion)}</textarea>`)}
            ${campo('Duda pendiente (deja vacío si está clara)', `<textarea name="duda" rows="2">${esc(a.duda)}</textarea>`)}
            <label class="arm-check"><input type="checkbox" name="revisar"${a.atributo === 'REVISAR' ? ' checked' : ''}><span>Marcar como «Por revisar»</span></label>
            <fieldset><legend>Requisitos y condiciones <small>(no son el atributo de ataque)</small></legend>
                <div id="armReqs">${(o.requisitos || []).map(filaReq).join('')}</div>
                <button type="button" class="arm-btn" data-anadir="req">+ Requisito</button></fieldset>
            ${o.origen !== 'admin' && a.editado ? '<p><button type="button" class="arm-link" id="armAtqRestaurar">Volver a la clasificación del documento</button></p>' : ''}`;
        $('#armEditorError').hidden = true;
        $('#armEditorBorrar').hidden = true;
        $('#armEditorActivar').hidden = true;
        abrirDialogo($('#armEditor'));
    }
    function leerAtaque() {
        const f = $('#armEditorForm');
        const fila = el => Object.fromEntries([...el.querySelectorAll('input,select')].map(i => [i.name || i.dataset.k, i.value]));
        return {
            modos: [...f.querySelectorAll('[data-modo]')].map(fila),
            requisitos: [...f.querySelectorAll('[data-req]')].map(fila).filter(r => r.texto.trim()),
            motivo: f.motivo.value, excepcion: f.excepcion.value, duda: f.duda.value, revisar: f.revisar.checked,
        };
    }
    function abrirEditorNota(n, objetoId = 0) {
        editando = { _nota: true, id: n ? n.id : 0 };
        const oid = n ? n.objetoId || 0 : objetoId;
        const objs = cat.objetos.slice().sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
        $('#armEditorTitulo').textContent = n ? 'Editar nota' : 'Nueva aclaración';
        $('#armEditorBody').innerHTML = `<div class="arm-editor-grid">
                ${campo('Tipo', `<select name="tipo">${Object.entries(NOTA_TIPO).map(([v, l]) => `<option value="${v}"${(n?.tipo || 'aclaracion') === v ? ' selected' : ''}>${l}</option>`).join('')}</select>`)}
                ${campo('Objeto (opcional)', `<select name="objetoId"><option value="0">— Nota general —</option>${objs.map(o => `<option value="${o.id}"${o.id === oid ? ' selected' : ''}>${esc(o.nombre)}</option>`).join('')}</select>`)}
                ${campo('Título', inp('titulo', n?.titulo || '', 'required maxlength="160"'), 'span2')}
            </div>
            ${campo('Texto', `<textarea name="texto" rows="7">${esc(n?.texto || '')}</textarea>`)}
            <label class="arm-check"><input type="checkbox" name="visible"${!n || n.visible ? ' checked' : ''}><span>Visible para todos (si no, queda como borrador del admin)</span></label>
            <p class="arm-muted">Se muestra siempre como «aclaración de la administración», separada del texto del documento.</p>`;
        $('#armEditorError').hidden = true;
        $('#armEditorBorrar').hidden = !n;
        $('#armEditorActivar').hidden = true;
        abrirDialogo($('#armEditor'));
    }
    async function adminClick(t) {
        let b;
        if ((b = t.closest('[data-editar-ataque]'))) return abrirEditorAtaque(cat.objetos.find(x => x.id === Number(b.dataset.editarAtaque)));
        if ((b = t.closest('[data-nueva-nota]'))) return abrirEditorNota(null, Number(b.dataset.nuevaNota));
        if ((b = t.closest('[data-editar-nota]'))) return abrirEditorNota(cat.notas.find(x => x.id === Number(b.dataset.editarNota)));
        if ((b = t.closest('[data-anadir]'))) { const box = b.dataset.anadir === 'modo' ? $('#armModos') : $('#armReqs'); box.insertAdjacentHTML('beforeend', b.dataset.anadir === 'modo' ? filaModo({ atributo: 'FUE' }) : filaReq()); return; }
        if ((b = t.closest('[data-quitar-fila]'))) { b.closest('.arm-erow').remove(); return; }
        if (t.closest('#armAtqRestaurar')) {
            if (!confirm('¿Descartar tu corrección y volver a la clasificación del documento?')) return;
            try { const j = await api({ action: 'ataque_restaurar', id: editando.id }); cerrarDialogo($('#armEditor')); render(); toast(j.message); } catch (err) { toast(err.message, true); }
            return;
        }
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
            if (editando._nota) {
                if (!confirm('¿Eliminar esta nota?')) return;
                try { const j = await api({ action: 'eliminar_nota', id: editando.id }); cerrarDialogo($('#armEditor')); render(); if (st.vista === 'reglas') renderReglas(); toast(j.message); } catch (err) { toast(err.message, true); }
                return;
            }
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
    if (st.vista !== 'catalogo') vista(st.vista);
})();
