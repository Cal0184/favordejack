"""Extrae Armas - Armaduras.docx a armamento.json (+ inventario para el informe).

Uso: python3 -I extraer.py documento.docx salida.json inventario.json
"""
import json, re, sys, unicodedata
import docx
from docx.table import Table
from docx.text.paragraph import Paragraph

SRC, OUT, INV = sys.argv[1], sys.argv[2], sys.argv[3]
d = docx.Document(SRC)


def norm(s):
    s = unicodedata.normalize('NFD', s.lower())
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    s = s.replace('​', '')
    return re.sub(r'\s+', ' ', s).strip()


def slug(s):
    return re.sub(r'[^a-z0-9]+', '-', norm(s).replace("'", '')).strip('-')


def clean(s):
    # Solo limpieza invisible: espacios de ancho cero, espacios dobles y bordes.
    s = s.replace('​', '').replace('\xa0', ' ')
    return re.sub(r'[ \t]+', ' ', s).strip()


blocks = []
for el in d.element.body.iterchildren():
    tag = el.tag.split('}')[1]
    if tag == 'p':
        p = Paragraph(el, d)
        blocks.append(('p', p.style.name, p.text))
    elif tag == 'tbl':
        rows = []
        for r in Table(el, d).rows:
            cells, prev = [], None
            for c in r.cells:
                if c._tc is prev:
                    continue
                prev = c._tc
                cells.append(c.text)
            rows.append(cells)
        blocks.append(('t', None, rows))

avisos = {}  # clave objeto -> [texto]
informe = []  # avisos generales


def aviso(clave, txt):
    avisos.setdefault(clave, []).append(txt)


# ---------------------------------------------------------------- categorías
CATEGORIAS = [
    ('simple', 'Armas simples', 'arma'),
    ('simple-distancia', 'Armas simples a distancia', 'arma'),
    ('marcial', 'Armas marciales', 'arma'),
    ('marcial-distancia', 'Armas marciales a distancia', 'arma'),
    ('armadura-ligera', 'Armaduras ligeras', 'armadura'),
    ('armadura-media', 'Armaduras medias', 'armadura'),
    ('armadura-pesada', 'Armaduras pesadas', 'armadura'),
    ('escudo', 'Escudos', 'escudo'),
]
TABLA_CAT = {
    'armas simples': 'simple',
    'armas a distancia simples': 'simple-distancia',
    'armas marciales': 'marcial',
    'armas a distancia marciales': 'marcial-distancia',
}

# ------------------------------------------------------------- propiedades
# clave -> (nombre visible, alias en las tablas, título en el glosario del Word)
PROPS = {
    'municion': ('Munición', ['municion'], 'Munición'),
    'sutil': ('Sutil', ['sutil'], 'Sutil'),
    'pesada': ('Pesada', ['pesada', 'pesado'], 'Pesado'),
    'guante': ('Guante', ['guante', 'guantes'], 'Guante'),
    'ligera': ('Ligera', ['ligera'], 'Ligera'),
    'oculta': ('Oculta', ['oculta'], 'Oculta'),
    'recarga': ('Recarga', ['recarga'], 'Cargando'),
    'costosa': ('Costosa', ['costosa'], 'Costosa'),
    'desarmarte': ('Desarmarte', ['desarmarte'], 'Desarmarte'),
    'retornable': ('Retornable', ['retornable'], 'Retornable'),
    'rango': ('Rango', ['rango'], 'Rango'),
    'alcance': ('Alcance', ['alcance'], 'Alcanzar'),
    'especial': ('Especial', ['especial'], 'Especial'),
    'arrojadiza': ('Arrojadiza', ['arrojadiza', 'arrojable'], 'Lanzado'),
    'dos-manos': ('A dos manos', ['a 2 manos', 'a dos manos', 'de dos manos'], 'Dos manos'),
    'versatil': ('Versátil', ['versatil'], 'Versátil'),
    'incomoda': ('Incómoda', ['incomoda'], 'Incomoda'),
    'destructora': ('Destructora', ['destruccion'], 'Destructora'),
}
ALIAS = []
for k, (_, al, _) in PROPS.items():
    for a in al:
        ALIAS.append((a, k))
ALIAS.sort(key=lambda x: -len(x[0]))
ALIAS_RE = re.compile(r'(?<![a-z])(' + '|'.join(re.escape(a) for a, _ in ALIAS) + r')(?![a-z])')
ALIAS_MAP = dict(ALIAS)


def parse_props(raw, clave):
    """Devuelve [(clave, detalle)] respetando el orden del Word."""
    txt = clean(raw)
    if txt in ('', '-', '—', '\\-', '---'):
        return []
    n = norm(txt)
    out = []
    pos = 0
    pending_paren = None
    for m in ALIAS_RE.finditer(n):
        between = n[pos:m.start()]
        # Paréntesis suelto entre propiedades: «munición, (80/320)».
        loose = re.search(r'\(([^)]*)\)', between)
        if loose and out:
            k, det = out[-1]
            if not det:
                out[-1] = (k, loose.group(1).strip())
                aviso(clave, f'El rango «({loose.group(1)})» aparece separado por una coma de «{PROPS[k][0]}»; se asoció a esa propiedad.')
        leftover = re.sub(r'\([^)]*\)', '', between).strip(' ,.;')
        if leftover:
            aviso(clave, f'Texto de propiedades no reconocido: «{leftover}».')
        k = ALIAS_MAP[m.group(1)]
        rest = n[m.end():]
        pm = re.match(r'\s*\(([^)]*)\)', rest)
        det = ''
        end = m.end()
        if pm:
            det = pm.group(1).strip()
            end = m.end() + pm.end()
        out.append((k, det))
        pos = end
    tail = re.sub(r'\([^)]*\)', '', n[pos:]).strip(' ,.;')
    loose = re.search(r'\(([^)]*)\)', n[pos:])
    if loose and out and not out[-1][1]:
        k, _ = out[-1]
        out[-1] = (k, loose.group(1).strip())
        aviso(clave, f'El rango «({loose.group(1)})» aparece separado por una coma de «{PROPS[k][0]}»; se asoció a esa propiedad.')
    if 'destruccion' in n:
        aviso(clave, 'La tabla dice «Destrucción»; en el glosario la propiedad se llama «Destructora».')
    if 'arrojable' in n:
        aviso(clave, 'La tabla dice «arrojable»; se trató como la propiedad «Arrojadiza».')
    if 'versatil' in n and 'versátil' not in txt.lower():
        aviso(clave, 'La tabla escribe «Versatil» sin tilde.')
    if tail:
        aviso(clave, f'Texto de propiedades no reconocido: «{tail}».')
    # Avisos de forma.
    if re.search(r'\w\(', n):
        aviso(clave, 'Falta un espacio antes del paréntesis en las propiedades (p. ej. «Munición(25/100)»).')
    if re.search(r'\) [a-z]', n) and not re.search(r'\), ', n.split(')')[0] + '), '):
        pass
    if re.search(r'\)\s+[a-z]', n):
        aviso(clave, 'Falta una coma entre dos propiedades («… (30/120) recarga»).')
    if '. ' in txt:
        aviso(clave, 'Las propiedades usan un punto en lugar de una coma como separador.')
    seen = set()
    final = []
    for k, det in out:
        if k in seen:
            aviso(clave, f'La propiedad «{PROPS[k][0]}» aparece repetida.')
            continue
        seen.add(k)
        final.append((k, det))
    return final


# -------------------------------------------------------------------- daño
TIPOS = ['cortante', 'perforante', 'contundente', 'fuego']


def parse_dano(raw, clave):
    txt = clean(raw).rstrip('|').strip()
    if raw.strip().endswith('|'):
        aviso(clave, 'La celda de daño termina con un carácter «|» sobrante; se quitó al importar.')
    n = norm(txt)
    tipos = [t for t in TIPOS if re.search(r'\b' + t + r'\b', n)]
    dice = re.findall(r'\b(\d+)d(\d+)\b|\b(\d+)\b', n)
    first = None
    if dice:
        a, b, c = dice[0]
        first = f'{a}d{b}' if a else c
    prom = None
    if first:
        if 'd' in first:
            a, b = map(int, first.split('d'))
            prom = a * (b + 1) / 2
        else:
            prom = float(first)
    # Texto mostrado: tipos de daño en minúscula (como en la mayoría de filas).
    shown = txt
    for t in TIPOS:
        shown = re.sub(r'\b' + t.capitalize() + r'\b', t, shown)
    if txt in ('', '-', '—'):
        shown = '—'
    elif not first:
        aviso(clave, f'Daño dudoso en el documento: «{txt}». Se conserva tal cual.')
    elif not tipos:
        aviso(clave, f'El daño «{txt}» no indica tipo de daño.')
    return shown, first, prom, tipos


# ------------------------------------------------------------------- coste
MON = {'cobre': 1, 'plata': 10, 'oro': 100}


def parse_coste(raw, clave):
    txt = clean(raw)
    m = re.match(r'^(\d+)\s*(cobre|plata|oro)$', norm(txt))
    if not m:
        aviso(clave, f'Coste con formato inesperado: «{txt}».')
        return txt, None
    return txt, int(m.group(1)) * MON[m.group(2)]


objetos = []
usadas = set()


def nueva_clave(nombre):
    base = slug(nombre)
    k, i = base, 2
    while k in usadas:
        k = f'{base}-{i}'
        i += 1
    if k != base:
        informe.append(f'Nombre repetido en el documento: «{nombre}» (clave {k}).')
    usadas.add(k)
    return k


orden = 0
tablas = [b[2] for b in blocks if b[0] == 't']
assert len(tablas) == 6, len(tablas)

for rows in tablas[:4]:
    titulo = norm(clean(rows[0][0]))
    cat = TABLA_CAT[titulo]
    assert [norm(c) for c in rows[1]] == ['nombre', 'coste', 'dano', 'propiedades'], rows[1]
    for r in rows[2:]:
        if len(r) == 5 and r[3].strip() == '' and r[2].strip().endswith('|'):
            pass
        nombre_raw = r[0]
        nombre = clean(nombre_raw)
        clave = nueva_clave(nombre)
        celdas = [clean(c) for c in r]
        if len(r) != 4:
            aviso(clave, f'La fila tiene {len(r)} celdas en lugar de 4 (hay una celda vacía de más en la tabla).')
            # «Shuriken de Fuma»: 1d6 Cortante | (vacía) | propiedades
            r = [r[0], r[1], r[2] + '|', r[-1]]
        if nombre != nombre_raw.strip():
            aviso(clave, 'El nombre tenía espacios de más; se limpiaron.')
        if nombre[:1].islower():
            aviso(clave, f'El nombre empieza con minúscula en el documento («{nombre}»); se muestra tal cual.')
        coste, cobre = parse_coste(r[1], clave)
        dano, dados, prom, tipos = parse_dano(r[2], clave)
        props = parse_props(r[3], clave)
        pk = [k for k, _ in props]
        alcance = ''
        for k, det in props:
            if k in ('rango', 'arrojadiza', 'municion') and det and re.match(r'^\d+(\s*/\s*\d+)?$', det):
                alcance = det.replace(' ', '')
                break
        if 'versatil' in pk and not dict(props)['versatil']:
            aviso(clave, 'Tiene la propiedad Versátil sin el daño a dos manos entre paréntesis.')
        if ('arrojadiza' in pk or 'municion' in pk or 'rango' in pk) and not alcance:
            aviso(clave, 'Arma arrojadiza o de munición sin rango indicado.')
        if alcance and '/' not in alcance:
            aviso(clave, f'El rango «{alcance}» tiene un solo número (normal/largo esperado).')
        orden += 1
        objetos.append({
            'clave': clave, 'tipo': 'arma', 'categoria': cat, 'nombre': nombre,
            'coste': coste, 'costeCobre': cobre,
            'dano': dano, 'danoDados': dados, 'danoPromedio': prom, 'tiposDano': tipos,
            'propiedadesTexto': clean(r[3]) if clean(r[3]) not in ('\\-',) else '-',
            'propiedades': [{'clave': k, 'detalle': det} for k, det in props],
            'alcance': alcance,
            'original': celdas, 'orden': orden,
        })

# Armaduras
rows = tablas[4]
assert norm(rows[1][0]) == 'armadura'
cat = None
for r in rows[2:]:
    n0 = norm(clean(r[0]))
    if len(r) == 1:
        cat = {'armaduras ligeras': 'armadura-ligera', 'armaduras medias': 'armadura-media', 'armaduras pesadas': 'armadura-pesada'}[n0]
        continue
    nombre = clean(r[0])
    clave = nueva_clave(nombre)
    coste, cobre = parse_coste(r[1], clave)
    ca = clean(r[2])
    m = re.match(r'^(\d+)(?:\s*\+\s*DES(?:\s*\(máx\. \+(\d+)\))?)?$', ca)
    if not m:
        aviso(clave, f'Clase de armadura con formato inesperado: «{ca}».')
        base, mod, mx = None, None, None
    else:
        base = int(m.group(1))
        if 'DES' not in ca:
            mod, mx = 'ninguno', None
        elif m.group(2):
            mod, mx = 'max', int(m.group(2))
        else:
            mod, mx = 'completo', None
    fue = clean(r[3])
    fm = re.match(r'^Fue (\d+)$', fue)
    sig = clean(r[4])
    orden += 1
    objetos.append({
        'clave': clave, 'tipo': 'armadura', 'categoria': cat, 'nombre': nombre,
        'coste': coste, 'costeCobre': cobre,
        'ca': ca, 'caBase': base, 'modDes': mod, 'desMax': mx,
        'fuerza': fue, 'fuerzaMin': int(fm.group(1)) if fm else None,
        'sigilo': sig, 'sigiloDesventaja': norm(sig) == 'desventaja',
        'propiedades': [], 'original': [clean(c) for c in r], 'orden': orden,
    })

# Escudos
rows = tablas[5]
assert [norm(c) for c in rows[1]] == ['nombre', 'coste', 'ca', 'propiedades', 'fuerza', 'sigilo'], rows[1]
for r in rows[2:]:
    nombre = clean(r[0])
    clave = nueva_clave(nombre)
    coste, cobre = parse_coste(r[1], clave)
    ca = clean(r[2])
    cm = re.match(r'^\+(\d+)$', ca)
    if not cm:
        aviso(clave, f'Bonificación de CA dudosa: «{ca}». El documento no da un valor.')
    fue = clean(r[4])
    sig = clean(r[5])
    props = parse_props(r[3], clave)
    orden += 1
    objetos.append({
        'clave': clave, 'tipo': 'escudo', 'categoria': 'escudo', 'nombre': nombre,
        'coste': coste, 'costeCobre': cobre,
        'ca': ca, 'caBono': int(cm.group(1)) if cm else None,
        'fuerza': fue if fue not in ('-', '—') else '—', 'fuerzaMin': int(fue) if fue.isdigit() else None,
        'sigilo': sig if sig not in ('-', '—') else '—', 'sigiloDesventaja': norm(sig) == 'desventaja',
        'propiedadesTexto': clean(r[3]),
        'propiedades': [{'clave': k, 'detalle': det} for k, det in props],
        'original': [clean(c) for c in r], 'orden': orden,
    })
    if r[1] != r[1].rstrip():
        pass

# --------------------------------------------------------- glosario y reglas
paras = [(s, t) for kind, s, t in blocks if kind == 'p']
i_prop = next(i for i, (s, t) in enumerate(paras) if s.startswith('Heading') and norm(t) == 'propiedades de arma')
i_esp = next(i for i, (s, t) in enumerate(paras) if s == 'Heading 1' and norm(t) == 'especial')

titulos_glos = {norm(v[2]): k for k, v in PROPS.items()}
glosario = {}
intro = []
actual = None
for s, t in paras[i_prop + 1:i_esp]:
    lines = [clean(x) for x in t.split('\n')]
    lines = [x for x in lines if x]
    if not lines:
        continue
    head = norm(lines[0]).rstrip(':').strip()
    if head in titulos_glos:
        actual = titulos_glos[head]
        glosario[actual] = {'tituloDocumento': lines[0].rstrip(':').strip(), 'parrafos': lines[1:]}
        continue
    if actual is None:
        intro.extend(lines)
    else:
        glosario[actual]['parrafos'].extend(lines)

for k in PROPS:
    if k not in glosario:
        informe.append(f'La propiedad «{PROPS[k][0]}» se usa en las tablas pero no tiene definición en el documento.')

propiedades = []
for o, (k, (nombre, al, tit)) in enumerate(PROPS.items(), 1):
    g = glosario.get(k)
    propiedades.append({
        'clave': k, 'nombre': nombre,
        'tituloDocumento': g['tituloDocumento'] if g else '',
        'definicion': '\n\n'.join(g['parrafos']) if g else '',
        'orden': o,
    })

# Reglas especiales
por_nombre = {norm(o['nombre']): o for o in objetos}
ALIAS_REGLA = {
    'maza de asedio': ['maza de asesio'],
    'bola y cadena': ['cadena con bola'],
    'mazo antorcha / antorcha larga': ['mazo antorcha', 'antorcha larga'],
}
reglas = []
cur = None
for s, t in paras[i_esp + 1:]:
    lines = [clean(x) for x in t.split('\n')]
    lines = [x for x in lines if x]
    if not lines:
        continue
    is_head = s.startswith('Heading 2')
    # «Escudo pesado:» está escrito como texto normal en negrita dentro de «Escudo de pared».
    if not is_head and len(lines) == 1 and lines[0].endswith(':') and norm(lines[0][:-1]) in por_nombre:
        is_head = True
        informe.append(f'La regla de «{lines[0][:-1]}» no tiene estilo de título en el Word (está dentro de «Escudo de pared»); se separó como regla propia.')
    if is_head:
        titulo = lines[0].rstrip(':').strip()
        cur = {'titulo': titulo, 'parrafos': lines[1:]}
        reglas.append(cur)
        if t.startswith('\n'):
            informe.append(f'El título «{titulo}» empieza con un salto de línea vacío en el Word.')
        if lines[1:]:
            informe.append(f'En «{titulo}», parte del texto de la regla está dentro del título del Word («{lines[1][:50]}…»); se movió al cuerpo de la regla.')
        continue
    if cur is None:
        informe.append(f'Párrafo sin regla asociada: «{lines[0][:60]}».')
        continue
    cur['parrafos'].extend(lines)

reglas_out = []
for rg in reglas:
    n = norm(rg['titulo'])
    destinos = ALIAS_REGLA.get(n, [n])
    clave = 'regla-' + slug(rg['titulo'].split('/')[0])
    found = []
    for dn in destinos:
        o = por_nombre.get(dn)
        if not o:
            informe.append(f'La regla «{rg["titulo"]}» no corresponde a ningún objeto de las tablas.')
            continue
        if o.get('regla'):
            informe.append(f'«{o["nombre"]}» tiene más de una regla especial.')
        o['regla'] = clave
        found.append(o['nombre'])
        if norm(o['nombre']) != n and n not in ALIAS_REGLA:
            pass
    if n in ALIAS_REGLA and n != 'mazo antorcha / antorcha larga':
        for o_n in found:
            aviso(por_nombre[norm(o_n)]['clave'], f'La regla especial está titulada «{rg["titulo"]}» en el documento, pero el objeto se llama «{o_n}» en la tabla.')
    reglas_out.append({'clave': clave, 'titulo': rg['titulo'], 'texto': '\n\n'.join(rg['parrafos'])})

MANUAL = {
    'red-atrapa-bichos': 'Revisar: la tabla da 1 de daño contundente, pero la regla especial dice que la criatura diminuta golpeada «no sufre daño».',
    'arco-espada': 'Revisar: la regla menciona la propiedad «arma doble», que no está definida en el glosario del documento.',
    'espada-bastarda': 'Revisar: la tabla marca «Incómoda» siempre, pero la regla especial solo la aplica al empuñarla con una mano sin competencia.',
    'hoja-oculta': 'Revisar redacción: «causa 1d4 de daño adicional y daño perforante» (¿1d4 de daño perforante adicional?).',
    'daga-y-cuerda': 'Revisar: rango 20/30 (el largo alcance suele ser varias veces el normal).',
    'tridente': None,
    'gran-guadana': 'Hay dos entradas parecidas: «Gran Guadaña» (1d6, versátil) y «Gran guadaña, variante» (2d4, con regla Cosechar). Se conservan separadas.',
    'gran-guadana-variante': 'Hay dos entradas parecidas: «Gran Guadaña» (1d6, versátil) y «Gran guadaña, variante» (2d4, con regla Cosechar). Se conservan separadas.',
    'mazo-antorcha': 'Comparte la regla «Mazo Antorcha / Antorcha larga» con la Antorcha Larga. Revisar: es un mazo con daño perforante.',
    'antorcha-larga': 'Comparte la regla «Mazo Antorcha / Antorcha larga» con el Mazo antorcha.',
    'hoopak': 'La regla especial del documento solo describe el arma; no explica una mecánica concreta.',
    'banana-explosiva': 'Errata en la regla: «tirada brazode salvación». Se conserva el texto original.',
    'boleadora': 'Errata en la regla: «derrigolpeada». Se conserva el texto original.',
    'daga-de-parry': 'Errata en la regla: «variafunciones». Se conserva el texto original.',
    'guadana-del-verdugo': 'Errata en la regla: «incapacitad». Se conserva el texto original.',
    'shotel': 'Errata en la regla: «Cuando haces un ataque esta arma» y falta el punto final.',
    'martillo-horno': 'Revisar redacción de la regla: «… de daño por fuego adicional de restablecerse al estado normal».',
    'gran-guadana-variante-2': None,
    'latigo-de-combate': 'El nombre se escribe «Latigo» sin tilde en el documento.',
    'latigo-cortante': 'El nombre se escribe «Latigo» sin tilde en el documento.',
    'escudo-pesado': 'Su regla está escrita dentro de la de «Escudo de pared» y sin estilo de título en el Word.',
    'bumerang-con-cuchillas': 'Se escribe «Bumerang» aquí y «Boomerang» en otras entradas.',
}
for k, v in MANUAL.items():
    if v and any(o['clave'] == k for o in objetos):
        aviso(k, v)
    elif v:
        informe.append(f'(interno) clave manual sin objeto: {k}')

for o in objetos:
    o.setdefault('regla', None)
    pk = [p['clave'] for p in o['propiedades']]
    if 'especial' in pk and not o['regla']:
        aviso(o['clave'], 'Tiene la propiedad «Especial», pero el documento no incluye su regla especial.')
    if o['regla'] and 'especial' not in pk:
        aviso(o['clave'], 'Tiene una regla especial en el documento, pero su tabla no marca la propiedad «Especial».')
    o['avisos'] = avisos.get(o['clave'], [])

data = {
    'version': 1,
    'fuente': 'Armas - Armaduras.docx',
    'categorias': [{'clave': c, 'nombre': n, 'tipo': t, 'orden': i} for i, (c, n, t) in enumerate(CATEGORIAS, 1)],
    'propiedadesIntro': '\n\n'.join(intro),
    'propiedades': propiedades,
    'reglas': reglas_out,
    'objetos': objetos,
}
with open(OUT, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=1)
with open(INV, 'w', encoding='utf-8') as f:
    json.dump({'informe': informe, 'avisos': avisos}, f, ensure_ascii=False, indent=1)

from collections import Counter
print(Counter(o['categoria'] for o in objetos))
print('total', len(objetos), 'reglas', len(reglas_out), 'props', len(propiedades))
print('\n'.join(informe))
