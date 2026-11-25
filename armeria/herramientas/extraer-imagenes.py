"""Extrae la ilustración de cada arma del Word «Catálogo ilustrado de armas» y la guarda en webp.

Cada fila de tabla del Word = [imagen | nombre, coste, daño…]. El nombre se empareja con
armamento.json (sin tildes ni mayúsculas) y la imagen se guarda con la clave del objeto:
    armeria/img/armas/<clave>.webp        (ficha, hasta 640 px)
    armeria/img/armas/mini/<clave>.webp   (lista, 96 px)

Uso: python3 -I extraer-imagenes.py catalogo.docx armamento.json carpeta-salida
"""
import json, re, sys, unicodedata, zipfile, io, os
import docx
from docx.oxml.ns import qn
from PIL import Image

SRC, JSON, OUT = sys.argv[1], sys.argv[2], sys.argv[3]


def norm(s):
    s = unicodedata.normalize('NFD', s.lower())
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    return re.sub(r'\s+', ' ', s.replace('​', '')).strip()


def guardar(im, path, lado, calidad):
    im = im.copy()
    im.thumbnail((lado, lado), Image.LANCZOS)
    im.save(path, 'WEBP', quality=calidad, method=6)


d = docx.Document(SRC)
rels = {r.rId: r for r in d.part.rels.values()}
objetos = {norm(o['nombre']): o for o in json.load(open(JSON, encoding='utf-8'))['objetos'] if o['tipo'] == 'arma'}
os.makedirs(os.path.join(OUT, 'mini'), exist_ok=True)

hechas, sin_pareja = {}, []
for tbl in d.element.body.iter(qn('w:tbl')):
    for row in tbl.iter(qn('w:tr')):
        cs = list(row.iter(qn('w:tc')))
        if len(cs) < 2:
            continue
        blips = list(cs[0].iter(qn('a:blip')))
        textos = [''.join(t.text or '' for t in p.iter(qn('w:t'))).strip() for p in cs[1].iter(qn('w:p'))]
        textos = [t for t in textos if t]
        if not blips or not textos:
            continue
        nombre = textos[0]
        o = objetos.get(norm(nombre))
        if not o:
            sin_pareja.append(nombre)
            continue
        blob = rels[blips[0].get(qn('r:embed'))].target_part.blob
        im = Image.open(io.BytesIO(blob)).convert('RGBA')
        caja = im.getchannel('A').point(lambda a: 255 if a > 8 else 0).getbbox()
        if caja:  # recorta el margen transparente para que el arma llene el marco
            m = 6
            im = im.crop((max(0, caja[0] - m), max(0, caja[1] - m), min(im.width, caja[2] + m), min(im.height, caja[3] + m)))
        guardar(im, os.path.join(OUT, o['clave'] + '.webp'), 640, 82)
        guardar(im, os.path.join(OUT, 'mini', o['clave'] + '.webp'), 96, 80)
        hechas[o['clave']] = nombre

faltan = [o['nombre'] for k, o in objetos.items() if o['clave'] not in hechas]
print(f'{len(hechas)} imágenes guardadas · sin pareja en el JSON: {sin_pareja} · armas sin imagen: {faltan}')
