"""Añade a armamento.json el atributo de ataque y los requisitos de cada objeto.

Uso: python3 -I clasificar-ataque.py armamento.json

Se ejecuta después de extraer-docx.py y se puede repetir: siempre recalcula a partir
de la categoría, las propiedades y las reglas especiales del documento.

Reglas generales (las del propio Word: Sutil, Lanzado/Arrojadiza y Munición):
  * Arma cuerpo a cuerpo .................... Fuerza.
  * Arma cuerpo a cuerpo con Sutil ........... Fuerza o Destreza (eliges una).
  * Arma a distancia ......................... Destreza.
  * Arma a distancia con Sutil ............... Fuerza o Destreza.
  * Arrojadiza cuerpo a cuerpo ............... el mismo modificador que en cuerpo a cuerpo.
  * Munición en un arma cuerpo a cuerpo ...... ese modo de disparo es a distancia: Destreza.
Después se aplican las EXCEPCIONES: solo las que la regla especial dice sin ambigüedad.
"""
import json, sys

SRC = sys.argv[1]
data = json.load(open(SRC, encoding='utf-8'))

NOMBRE = {'FUE': 'Fuerza', 'DES': 'Destreza', 'FUE/DES': 'Fuerza o Destreza'}

T_SUTIL = 'Esta arma posee la propiedad Sutil, por lo que puedes elegir Fuerza o Destreza para las tiradas de ataque y daño (el mismo modificador en las dos).'
T_CAC = 'Es un arma cuerpo a cuerpo sin la propiedad Sutil: usa Fuerza para las tiradas de ataque y daño.'
T_DIST = 'Es un arma a distancia: usa Destreza para las tiradas de ataque y daño.'
T_DIST_SUTIL = 'Es un arma a distancia con la propiedad Sutil: puedes elegir Fuerza o Destreza para las tiradas de ataque y daño (el mismo modificador en las dos).'
T_LANZAR = 'Si la lanzas (Arrojadiza), usas el mismo modificador que en cuerpo a cuerpo, como indica la propiedad Lanzado del documento.'
T_MUNICION = 'Su modo de disparo con munición es un ataque a distancia, que usa Destreza.'


def props(o):
    return {p['clave'] for p in o.get('propiedades', [])}


def general(o):
    """Clasificación según las reglas generales. Devuelve (modos, motivo)."""
    ps = props(o)
    distancia = o['categoria'].endswith('distancia')
    sutil = 'sutil' in ps
    modos, motivo = [], []
    if distancia:
        at = 'FUE/DES' if sutil else 'DES'
        modo = 'Arrojadiza' if 'arrojadiza' in ps and 'municion' not in ps else 'A distancia'
        modos.append({'modo': modo, 'atributo': at, 'dano': '', 'nota': ''})
        motivo.append(T_DIST_SUTIL if sutil else T_DIST)
    else:
        at = 'FUE/DES' if sutil else 'FUE'
        modos.append({'modo': 'Cuerpo a cuerpo', 'atributo': at, 'dano': '', 'nota': ''})
        motivo.append(T_SUTIL if sutil else T_CAC)
        if 'arrojadiza' in ps:
            modos.append({'modo': 'Arrojadiza', 'atributo': at, 'dano': '', 'nota': ''})
            motivo.append(T_LANZAR)
        if 'municion' in ps:
            modos.append({'modo': 'Disparo con munición', 'atributo': 'DES', 'dano': '', 'nota': ''})
            motivo.append(T_MUNICION)
    return modos, ' '.join(motivo)


def resumen(modos):
    ats = {m['atributo'] for m in modos}
    if not ats:
        return ''
    if 'REVISAR' in ats:
        return 'REVISAR'
    return ats.pop() if len(ats) == 1 else 'MODOS'


# Excepciones: la regla especial del arma cambia o amplía el atributo. Solo casos inequívocos.
EXCEPCIONES = {
    'falchion': {
        'motivo': 'Arma cuerpo a cuerpo sin Sutil (Fuerza), salvo cuando la empuñas a dos manos: entonces su regla especial le da Sutil y puedes elegir Fuerza o Destreza.',
        'modos': [
            {'modo': 'A una mano', 'atributo': 'FUE', 'dano': '1d8 cortante', 'nota': ''},
            {'modo': 'A dos manos (Versátil)', 'atributo': 'FUE/DES', 'dano': '2d4 cortante', 'nota': 'La regla especial la convierte en arma Sutil («delicada»).'},
        ],
        'excepcion': 'Regla especial: «Cuando se empuña con las dos manos, se considera un arma delicada». A una mano usa Fuerza; a dos manos puedes elegir Fuerza o Destreza.',
    },
    'arco-espada': {
        'motivo': 'Arma doble: como arco dispara munición (Destreza) y como espada es un arma cuerpo a cuerpo sin Sutil (Fuerza).',
        'modos': [
            {'modo': 'Disparo con munición', 'atributo': 'DES', 'dano': '1d6 perforante', 'nota': ''},
            {'modo': 'Cuerpo a cuerpo (espada)', 'atributo': 'FUE', 'dano': '1d4 cortante', 'nota': 'Arma marcial a dos manos y pesada, sin Sutil.'},
        ],
        'excepcion': 'Regla especial: se puede usar como arma marcial cuerpo a cuerpo (1d4 cortante, a dos manos, pesada). Ese modo no tiene Sutil, así que usa Fuerza; el disparo usa Destreza.',
    },
    'hacha-ballesta': {
        'motivo': 'Arma doble: como ballesta dispara munición (Destreza) y como hacha es un arma cuerpo a cuerpo sin Sutil (Fuerza).',
        'modos': [
            {'modo': 'Disparo con munición', 'atributo': 'DES', 'dano': '1d8 perforante', 'nota': ''},
            {'modo': 'Cuerpo a cuerpo (hacha)', 'atributo': 'FUE', 'dano': '1d8 cortante', 'nota': 'Sin Sutil.'},
        ],
        'excepcion': 'Regla especial: se puede usar para ataques cuerpo a cuerpo con 1d8 cortante. Ese modo no tiene Sutil, así que usa Fuerza; el disparo usa Destreza.',
    },
    'hoopak': {
        'motivo': 'Arma de varios usos: como bastón o lanza es cuerpo a cuerpo sin Sutil (Fuerza); como honda dispara munición (Destreza).',
        'modos': [
            {'modo': 'Cuerpo a cuerpo (bastón o lanza)', 'atributo': 'FUE', 'dano': '1d6 contundente / 1d6 perforante', 'nota': ''},
            {'modo': 'Honda (munición)', 'atributo': 'DES', 'dano': '', 'nota': 'Rango 20/60.'},
        ],
        'excepcion': 'La regla dice que se usa «como honda, bastón corto y lanza». Como bastón o lanza es cuerpo a cuerpo sin Sutil (Fuerza); como honda dispara munición (Destreza).',
    },
    'escudo-de-aspis': {
        'modos': [
            {'modo': 'Golpe con el escudo (arma improvisada)', 'atributo': 'FUE', 'dano': '1d6 contundente', 'nota': ''},
        ],
        'motivo': 'Los escudos no atacan normalmente, pero la regla «Intento» de este escudo permite golpear con él: 1d6 contundente + tu modificador de Fuerza.',
        'excepcion': 'Regla especial «Intento»: el daño es 1d6 + tu modificador de Fuerza.',
    },
}

# Notas que no cambian el atributo pero conviene ver junto a él.
NOTAS = {
    'yo-yo-con-hoja': 'Sin lanzarlo causa 1 de daño cortante; lanzado, 2d4. Los dos modos conservan Sutil.',
    'latigo-cortante': 'Su característica especial (agarrar a 10 pies) solo funciona si lo usas con el modificador de Destreza y dominas las armas marciales.',
    'gran-guadana-variante': 'Con «Cosechar» no sumas tu modificador de Fuerza al daño del ataque.',
    'baston-espada': 'Envainada causa 1d4 contundente y gana la propiedad Oculta; el documento no dice que pierda Sutil.',
    'naginata': 'Solo tiene Pesada y Alcance a dos manos; Sutil se mantiene en los dos casos.',
    'batleth': 'Puede cambiar la competencia de la tirada de ataque al daño; el atributo no cambia.',
    'dardo': 'En este documento el dardo no tiene Sutil, así que sigue la regla general de las armas a distancia.',
    'penasco': 'Es pesada y a dos manos, pero el documento la pone entre las armas a distancia y no le da otra regla: Destreza.',
    'boomerang-gigante': 'El documento la clasifica como arma marcial cuerpo a cuerpo arrojadiza: Fuerza también al lanzarla.',
}

# Requisitos y condiciones (no son el atributo de ataque).
REQUISITOS = {
    'arco-de-guerra': [
        {'tipo': 'requisito', 'atributo': 'FUE/DES', 'minimo': 20, 'texto': 'Requiere una puntuación de Fuerza o Destreza de 20.'},
        {'tipo': 'condicion', 'atributo': None, 'minimo': None, 'texto': 'Desventaja al atacar a un objetivo a 15 pies o menos.'},
    ],
    'ultra-espadon': [
        {'tipo': 'requisito', 'atributo': 'FUE', 'minimo': 19, 'texto': 'Sin Fuerza 19 o más tienes desventaja en las tiradas de ataque.'},
        {'tipo': 'requisito', 'atributo': None, 'minimo': None, 'texto': 'Las criaturas Pequeñas o menores no pueden empuñarla.'},
    ],
    'cadena-con-bola': [
        {'tipo': 'condicion', 'atributo': 'FUE', 'minimo': 20, 'texto': 'Reduce tu velocidad en 25 pies; con Fuerza 20 o más se ignora.'},
    ],
    'lanza-de-caballeria': [
        {'tipo': 'condicion', 'atributo': None, 'minimo': None, 'texto': 'Sin montura necesitas las dos manos para empuñarla. Desventaja contra objetivos a 5 pies o menos.'},
    ],
    'yo-yo-con-hoja': [
        {'tipo': 'condicion', 'atributo': None, 'minimo': None, 'texto': 'Recogerlo exige Juego de manos CD 15, salvo competencia específica con yo-yos con cuchillas.'},
    ],
    'espada-bastarda': [
        {'tipo': 'condicion', 'atributo': None, 'minimo': None, 'texto': 'Es Incómoda si la empuñas a una mano sin ser competente.'},
    ],
}

for o in data['objetos']:
    reqs = []
    if o['tipo'] != 'arma' and o.get('fuerzaMin'):
        reqs.append({'tipo': 'requisito', 'atributo': 'FUE', 'minimo': o['fuerzaMin'], 'texto': f"Fuerza {o['fuerzaMin']} (columna «Fuerza» de la tabla)."})
    reqs += REQUISITOS.get(o['clave'], [])
    o['requisitos'] = reqs

    exc = EXCEPCIONES.get(o['clave'])
    if o['tipo'] != 'arma' and not exc:
        o['ataque'] = None
        continue
    if o['tipo'] == 'arma':
        modos, motivo = general(o)
        base = resumen(modos)
    else:
        modos, motivo, base = [], '', ''
    excepcion = ''
    if exc:
        modos = exc['modos']
        motivo = exc.get('motivo', motivo)
        excepcion = exc['excepcion']
    nota = NOTAS.get(o['clave'], '')
    o['ataque'] = {
        'atributo': resumen(modos),
        'base': base,
        'modos': modos,
        'motivo': motivo + (' ' + nota if nota else ''),
        'excepcion': excepcion,
        'duda': '',
    }

json.dump(data, open(SRC, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
cuenta = {}
for o in data['objetos']:
    if o.get('ataque'):
        cuenta[o['ataque']['atributo']] = cuenta.get(o['ataque']['atributo'], 0) + 1
print(json.dumps(cuenta, ensure_ascii=False))
