<?php
declare(strict_types=1);

/*
 * Patch Notes del Favor de Jack: historial de cambios oficiales de la mesa.
 * Son notas históricas: una versión posterior puede deshacer lo que dijo una
 * anterior (por ejemplo, los baneos y desbaneos del Libro del Sexo), así que el
 * texto de cada versión se conserva tal cual, sin aplicar cambios hacia atrás.
 *
 * Este archivo es solo el punto de partida: los parches se crean, editan y borran
 * desde la propia página (admin → Patch Notes → «+ Nuevo parche» o «Editar»).
 * La primera vez que se guarda uno, todos pasan a data/regulations.json y desde
 * entonces este archivo ya no se lee (cambiarlo aquí no tendría efecto).
 */
return [
    [
        'version' => '1.8',
        'date' => '2026-04-04',
        'name' => 'La Comprensión Lectora se Fue de Sabático',
        'body' => <<<'TXT'
## Revisión de puntos de golpe
Después de una importante revelación sobre las reglas, se revisarán las fichas de todos los personajes para comprobar que sus puntos de golpe máximos aumenten correctamente.

**Regla sobre los cambios del modificador de Constitución:**

> Si tu modificador de Constitución cambia, tu máximo de puntos de golpe también cambia, como si tuvieras el nuevo modificador desde el nivel 1.

> Por ejemplo, si aumentas tu puntuación de Constitución al alcanzar el nivel 4 y tu modificador de Constitución aumenta de +1 a +2, ajustas tu máximo de puntos de golpe como si el modificador siempre hubiera sido +2. Así, sumas 3 puntos de golpe por tus primeros tres niveles y luego tiras los dados de golpe para el nivel 4 usando tu nuevo modificador.

> O si estás en el nivel 7 y algún efecto reduce tu puntuación de Constitución de tal manera que tu modificador de Constitución disminuye en 1, tu máximo de puntos de golpe se reduce en 7.

## Desbaneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.7',
        'date' => '2026-01-22',
        'name' => 'Sed incesante',
        'body' => <<<'TXT'
## Rework: Tienda de pociones
- La tienda de pociones se ha rehecho para mejorar su comprensión, presentación visual, precios y descripciones.
- **Promoción:** La siguiente poción comprada tendrá un **50 % de descuento**, una vez por personaje.

## Reajustes
- **Cazadores de cabezas:** Con la nueva administración de Waterdeep, la recompensa por estrella aumenta de **100 po** a **300 po**.
- Las recompensas pasan a tener un **mínimo de 300 po** y un **máximo de 900 po**.

## Baneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.6',
        'date' => '2025-02-17',
        'name' => 'Resurgimiento de las Sombras',
        'body' => <<<'TXT'
## Nueva habilidad para pícaros (Rogues): Asesinar
> Durante el primer turno de combate, tienes ventaja en las tiradas de ataque contra cualquier criatura que aún no haya actuado. Si atacas a una criatura sorprendida, cualquier golpe que asestes será un golpe crítico automático.

## Reajustes
- **Los Juegos de los Dioses:** Cada jugador podrá realizar **un único cambio de personaje** durante la partida.

## Desbaneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.5',
        'date' => '2025-02-13',
        'name' => 'La Última Frontera',
        'body' => <<<'TXT'
## Nueva acción: Interponerse
Puedes proteger a un aliado cercano. **Cuando una criatura realiza un ataque contra un aliado que se encuentra a 5 pies de ti, puedes usar tu reacción para redirigir el ataque hacia ti.** La tirada de ataque se resuelve normalmente; solo cambia el objetivo.

## Aumento mágico para magos
- Los **magos** reciben más usos de conjuros conforme suben de nivel.
- Para los valores detallados, consulta la **tabla de combate** de la nota original.

## Trasfondos con dotes desbloqueados
Se permite elegir trasfondos que otorguen dotes, con estas condiciones:
- Deben tener una conexión temática con el personaje.
- Se debe agregar un texto introductorio del personaje en la sección **«Historias»**.

## Baneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.4',
        'date' => '2024-11-04',
        'name' => 'Los Tres Caminos',
        'body' => <<<'TXT'
## Reajustes: Nuevas opciones de equipo inicial
Al crear un personaje, se aplican las siguientes opciones de equipo inicial:
- **Arma:** Puede seleccionarse cualquier arma, ignorando el rasgo de *Costosa*, siempre que el personaje tenga competencia con ella.
- **Armadura:** Puede elegirse cualquier armadura sin pagar su costo, siempre que se respeten los requisitos de competencia y Fuerza.
- **Set de curación:** El personaje comienza con **1 Poción de Curación Mediana y 2 Pociones de Curación Pequeñas**.

## Estilos de combate
- Se desbloquean los estilos de combate opcionales de **Unearthed Arcana (UA)**.

## Desbaneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.3.2',
        'date' => '2024-09-15',
        'name' => '',
        'body' => <<<'TXT'
## Reajustes
- **Armas +1:** Su daño se considera del tipo habitual del arma. Por ejemplo, el daño de una espada corta será **cortante**.
TXT,
    ],
    [
        'version' => '1.3.1',
        'date' => '2024-09-08',
        'name' => '',
        'body' => <<<'TXT'
## Reajustes
- **Tabla de críticos — Nuevo resultado de 20:** «Lanzas 2d20 utilizando los resultados de ellos en la tabla de críticos».
- **Bárbaro, subclase Senda de la Bestia:** El daño de la cola aumenta de **1d8** a **1d10**.
TXT,
    ],
    [
        'version' => '1.3',
        'date' => '2024-08-25',
        'name' => 'Nuevo Mundo',
        'body' => <<<'TXT'
## Nueva regla: Revisión de fichas
Con la incorporación de nuevo contenido, puede resultar complicado mantener un orden claro de clases, subclases, conjuros y dotes. Para evitar inconvenientes, **un DM deberá revisar y aprobar cada ficha nueva antes de que pueda utilizarse**.

## Nuevo contenido
- **Nivel inicial:** Los personajes nuevos comienzan en **nivel 5**.
- **Unearthed Arcana (UA):** Se desbloquea parcialmente su contenido. Quienes deseen utilizar material de UA deberán consultarlo con un DM.
- **Trasfondos:** Se permite utilizar trasfondos de **Dandwiki**.

## Reajustes
- Se elimina el antiguo **sistema de Dificultad** y se adopta un enfoque más narrativo.

## Reworks
- **Tigger Vesel:** Actualizado para ser más fiel al material original.

## Baneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.2',
        'date' => '2024-06-30',
        'name' => 'Nuevos Horizontes',
        'body' => <<<'TXT'
## Nuevo contenido y reglas
- Las **reglas opcionales de cada clase** se desbloquean al alcanzar el **nivel 5**.
- **Entrenamientos:** Si se ha mencionado que un personaje entrenó con una herramienta, habilidad o arma, adquirirá lo entrenado cuando suba de nivel.

## Buffeos
- **Monje:** Puede realizar un ataque desarmado como acción adicional sin necesidad de usar antes la acción de Ataque.

## Desbaneos
- **Libro del Sexo.**
TXT,
    ],
    [
        'version' => '1.1',
        'date' => '2024-06-10',
        'name' => 'Que la magia flote',
        'body' => <<<'TXT'
## Mejoras de clase
- **Mago:** Para diferenciar a los magos de las demás clases mágicas, se añade un uso adicional a los conjuros de nivel 1. Estas mejoras aumentarán conforme el personaje suba de nivel.

## Objetos removidos
- **Libro del Sexo.**

## Aumentos de precio
- **Daga Parry:** de **10** a **20**.
TXT,
    ],
];
