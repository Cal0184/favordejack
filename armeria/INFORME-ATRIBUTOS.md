# Armas y Armaduras · Atributo de ataque, requisitos y reglas asociadas

Generado desde `armeria/data/armamento.json` con `armeria/herramientas/clasificar-ataque.py`. Lo que corrija el admin desde la web queda en MySQL y no se pisa al reimportar.

## 1. Reglas usadas para clasificar

- Arma cuerpo a cuerpo: **Fuerza**. Con la propiedad **Sutil**: **Fuerza o Destreza** (eliges una; el mismo modificador para ataque y daño, como dice la definición de Sutil del documento).
- Arma a distancia: **Destreza**. Con Sutil: Fuerza o Destreza.
- Arrojadiza cuerpo a cuerpo: conserva el modificador de cuerpo a cuerpo (definición «Lanzado» del documento).
- Munición en un arma cuerpo a cuerpo (Hoopak): el disparo es a distancia, Destreza.
- Si la regla especial del arma cambia el atributo sin ambigüedad, manda la regla (ver excepciones). El requisito de puntuación (p. ej. Arco de guerra, Fuerza o Destreza 20) se guarda aparte y **no** cambia el atributo.

## 2. Recuento

| Atributo | Armas |
|---|---|
| Fuerza | 85 |
| Destreza | 20 |
| Fuerza o Destreza | 53 |
| Según el modo | 4 |
| Por revisar | 0 |
| **Total armas** | **162** |

Además, el **Escudo de Aspis** tiene un modo de ataque (regla «Intento»: 1d6 + Fuerza). El resto de armaduras y escudos no atacan.

Filtro «Fuerza»: 142 armas (las de Fuerza, las Sutiles y las que tienen algún modo con Fuerza). Filtro «Destreza»: 77. Filtro «Fuerza o Destreza»: 54. «Por revisar»: 0.

## 3. Excepciones (la regla especial cambia o divide el atributo)

- **Hoopak** — Cuerpo a cuerpo (bastón o lanza): Fuerza; Honda (munición): Destreza. La regla dice que se usa «como honda, bastón corto y lanza». Como bastón o lanza es cuerpo a cuerpo sin Sutil (Fuerza); como honda dispara munición (Destreza).
- **Falchion** — A una mano: Fuerza; A dos manos (Versátil): Fuerza o Destreza. Regla especial: «Cuando se empuña con las dos manos, se considera un arma delicada». A una mano usa Fuerza; a dos manos puedes elegir Fuerza o Destreza.
- **Hacha Ballesta** — Disparo con munición: Destreza; Cuerpo a cuerpo (hacha): Fuerza. Regla especial: se puede usar para ataques cuerpo a cuerpo con 1d8 cortante. Ese modo no tiene Sutil, así que usa Fuerza; el disparo usa Destreza.
- **Arco espada** — Disparo con munición: Destreza; Cuerpo a cuerpo (espada): Fuerza. Regla especial: se puede usar como arma marcial cuerpo a cuerpo (1d4 cortante, a dos manos, pesada). Ese modo no tiene Sutil, así que usa Fuerza; el disparo usa Destreza.
- **Escudo de Aspis** — Golpe con el escudo (arma improvisada): Fuerza. Regla especial «Intento»: el daño es 1d6 + tu modificador de Fuerza.

## 4. Casos que conviene que confirmes (clasificados por la regla general, sin marcar «Por revisar»)

- **Peñasco**: pesado y a dos manos, pero el documento lo pone entre las armas a distancia sin otra regla → Destreza. Si en la mesa se lanza con Fuerza, cámbialo desde «Atributo y requisitos».
- **Dardo**: en este documento no tiene Sutil (en el manual oficial sí) → Destreza.
- **Boomerang Gigante**: el documento lo pone como arma marcial cuerpo a cuerpo arrojadiza → Fuerza también al lanzarlo.
- **Hoopak**: la regla solo dice que se usa como honda, bastón y lanza; se separaron los modos (bastón/lanza Fuerza, honda Destreza).
- **Falchion**: a una mano Fuerza; a dos manos «se considera un arma delicada» → Fuerza o Destreza.

No quedó ninguna arma «Por revisar»: todas encajan en una regla general o en una excepción escrita.

## 5. Requisitos y condiciones guardados

- **Lanza de caballería** (condición): Sin montura necesitas las dos manos para empuñarla. Desventaja contra objetivos a 5 pies o menos.
- **Cadena con Bola** (condición): Reduce tu velocidad en 25 pies; con Fuerza 20 o más se ignora.
- **Espada Bastarda** (condición): Es Incómoda si la empuñas a una mano sin ser competente.
- **Yo-Yo con hoja** (condición): Recogerlo exige Juego de manos CD 15, salvo competencia específica con yo-yos con cuchillas.
- **Ultra Espadón** (requisito): Sin Fuerza 19 o más tienes desventaja en las tiradas de ataque.
- **Ultra Espadón** (requisito): Las criaturas Pequeñas o menores no pueden empuñarla.
- **Arco de guerra** (requisito): Requiere una puntuación de Fuerza o Destreza de 20.
- **Arco de guerra** (condición): Desventaja al atacar a un objetivo a 15 pies o menos.
- **Cota de mallas** (requisito): Fuerza 13 (columna «Fuerza» de la tabla).
- **Bandas** (requisito): Fuerza 15 (columna «Fuerza» de la tabla).
- **Placas** (requisito): Fuerza 15 (columna «Fuerza» de la tabla).
- **Escudo de cometa** (requisito): Fuerza 13 (columna «Fuerza» de la tabla).
- **Escudo de Tres Cuartos** (requisito): Fuerza 16 (columna «Fuerza» de la tabla).
- **Escudo de pared** (requisito): Fuerza 17 (columna «Fuerza» de la tabla).
- **Escudo de Aspis** (requisito): Fuerza 11 (columna «Fuerza» de la tabla).
- **Escudo de ruptura** (requisito): Fuerza 13 (columna «Fuerza» de la tabla).
- **Escudo medio** (requisito): Fuerza 11 (columna «Fuerza» de la tabla).
- **Escudo grande** (requisito): Fuerza 13 (columna «Fuerza» de la tabla).
- **Escudo pesado** (requisito): Fuerza 15 (columna «Fuerza» de la tabla).

## 6. Clasificación arma por arma

### Armas simples

| Arma | Atributo | Modos | Propiedades clave | Regla especial |
|---|---|---|---|---|
| Bastón | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Daga | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Arrojadiza, Sutil | — |
| Gran clava | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Hacha de mano | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Hoz | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Jabalina | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Lanza | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Lanza corta | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Lanza Larga | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Martillo ligero | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Maza | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Clava | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Pala | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Brandistock | Fuerza | Cuerpo a cuerpo: FUE | — | Brandistock |
| Guante Garra | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Red atrapa Bichos | Fuerza | Cuerpo a cuerpo: FUE | — | Red atrapa bichos |
| Garra | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Garra |
| Bate | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Manopla | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Manopla con hoja | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Bokken | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Cestus | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| fauchard | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Hoz de granjero | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Falx | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Eku | Fuerza | Cuerpo a cuerpo: FUE | — | Eku |
| Ikakalaka | Fuerza | Cuerpo a cuerpo: FUE | — | Ikakalaka |
| Gladius | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Nunchaku | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Cuchillo Ritual | Fuerza | Cuerpo a cuerpo: FUE | — | Cuchillo Ritual |
| Daga de Parry | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Daga de Parry |
| Picota | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Langmesser | Fuerza | Cuerpo a cuerpo: FUE | — | Langmesser |
| Hoopak | Según el modo | Cuerpo a cuerpo (bastón o lanza): FUE · Honda (munición): DES | Munición | Hoopak |
| Karambit | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Karambit |
| Shotel | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Shotel |
| Machete | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Cuchillo de cuerno | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Cuchillo de cuerno |
| Guadaña | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Anillo con punta | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Palo con puntas | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Mazo antorcha | Fuerza | Cuerpo a cuerpo: FUE | — | Mazo Antorcha / Antorcha larga |
| Antorcha Larga | Fuerza | Cuerpo a cuerpo: FUE | — | Mazo Antorcha / Antorcha larga |
| Wakizashi | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Yo-yo | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada de madera | Fuerza | Cuerpo a cuerpo: FUE | — | Espada de madera |
| Cuchillo de médico | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | Cuchillo de medico |

### Armas simples a distancia

| Arma | Atributo | Modos | Propiedades clave | Regla especial |
|---|---|---|---|---|
| Arco corto | Destreza | A distancia: DES | Munición | — |
| Ballesta ligera | Destreza | A distancia: DES | Munición | — |
| Dardo | Destreza | Arrojadiza: DES | Arrojadiza | — |
| Honda | Destreza | A distancia: DES | Munición | — |
| Banana Explosiva | Destreza | Arrojadiza: DES | Arrojadiza | Banana Explosiva |
| Boomerang | Destreza | Arrojadiza: DES | Arrojadiza | — |
| Fuego en Frasco | Fuerza o Destreza | Arrojadiza: FUE/DES | Sutil, Arrojadiza | Fuego en Frasco |
| Shuriken | Fuerza o Destreza | Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Bastón Slinga | Destreza | A distancia: DES | Munición | — |

### Armas marciales

| Arma | Atributo | Modos | Propiedades clave | Regla especial |
|---|---|---|---|---|
| Alabarda | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Atarraga | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Cimitarra | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada corta | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada Media | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada larga | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Espadón | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Espada ropera | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Hacha de batalla | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Gran hacha | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Guja | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Lanza de caballería | Fuerza | Cuerpo a cuerpo: FUE | — | Lanza de Caballería |
| Látigo | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Lucero del alba | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Martillo de guerra | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Mayal | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Pica | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Pica de guerra | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Tridente | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Maza de Asesio | Fuerza | Cuerpo a cuerpo: FUE | — | Maza de Asedio |
| Ancla | Fuerza | Cuerpo a cuerpo: FUE | — | Ancla |
| Espada Brazo | Fuerza | Cuerpo a cuerpo: FUE | — | Espada Brazo |
| Armando espada | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Brazaletes blindados | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Hoja Oculta | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Hoja Oculta |
| Cadena con Bola | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | Bola y cadena |
| Bangaliss | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada Bastarda | Fuerza | Cuerpo a cuerpo: FUE | — | Espada Bastarda |
| Espada ancha con empuñadura de cesta | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Tanto | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Tachi | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Odachi | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Bat'leth | Fuerza | Cuerpo a cuerpo: FUE | — | Bat'leth |
| Guadaña de Combate | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | Guadaña de combate |
| Latigo de combate | Fuerza | Cuerpo a cuerpo: FUE | — | Latigo de combate |
| Garra de oso | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Cable de combate | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada berserk | Fuerza | Cuerpo a cuerpo: FUE | — | Espada berserk |
| Pico de cuervo | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Bident | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Billhook | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Blackjack | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Lanza espada | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Yo-Yo con hoja | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Arrojadiza, Sutil | Yo-yo con hoja |
| Latigo Cortante | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Latigo Cortante |
| Tonfa con cuchillas | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Tonfa con cuchillas |
| Guantelete afilado | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Bumerang con cuchillas | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Lanza jabalí | Fuerza | Cuerpo a cuerpo: FUE | — | Lanza jabalí |
| Sable | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Gran látigo | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Claymore | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Kusarigamas | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Hoz de cadena | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Hacha de cadena | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Mayal de incensario | Fuerza | Cuerpo a cuerpo: FUE | — | Mayal de incensario |
| Bastón espada | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Bastón espada |
| Cuchilla creciente | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Guadaña creciente | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Látigo de daga | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Daga y cuerda | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Espadas gemelas | Fuerza | Cuerpo a cuerpo: FUE | — | Espadas gemelas |
| Lanza gemela | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | Lanza gemela |
| Guadaña doble | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Hacha Doble | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Abanico de Guerra | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Sierra de guerra | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| hacha de asta | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Partizan | Fuerza | Cuerpo a cuerpo: FUE | — | Partizan |
| Ninjato | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Ninjato |
| Naginata | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | Naginata |
| Kunai | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | Kunai |
| Kodachi | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Khopesh | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Katana | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Espada de gancho | Fuerza | Cuerpo a cuerpo: FUE | — | Espada de gancho |
| Gran Guadaña | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Gran cadena | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Hacha divisoria | Fuerza | Cuerpo a cuerpo: FUE | — | — |
| Ultra Espadón | Fuerza | Cuerpo a cuerpo: FUE | — | Ultra Espadón |
| Martillo Horno | Fuerza | Cuerpo a cuerpo: FUE | — | Martillo Horno |
| Guadaña del verdugo | Fuerza | Cuerpo a cuerpo: FUE | — | Guadaña del verdugo |
| Gran guadaña, variante | Fuerza | Cuerpo a cuerpo: FUE | — | Gran guadaña, variante |
| Espada Plegable | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |
| Shuriken de Fuma | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES · Arrojadiza: FUE/DES | Arrojadiza, Sutil | — |
| Boomerang Gigante | Fuerza | Cuerpo a cuerpo: FUE · Arrojadiza: FUE | Arrojadiza | — |
| Falchion | Según el modo | A una mano: FUE · A dos manos (Versátil): FUE/DES | — | Falchion |
| Espada del verdugo | Fuerza | Cuerpo a cuerpo: FUE | — | Espada del verdugo |
| Gran hacha del verdugo | Fuerza | Cuerpo a cuerpo: FUE | — | Gran hacha del verdugo |
| Espada de duelo | Fuerza o Destreza | Cuerpo a cuerpo: FUE/DES | Sutil | — |

### Armas marciales a distancia

| Arma | Atributo | Modos | Propiedades clave | Regla especial |
|---|---|---|---|---|
| Arco Largo | Destreza | A distancia: DES | Munición | — |
| Ballesta de mano | Destreza | A distancia: DES | Munición | — |
| Ballesta pesada | Destreza | A distancia: DES | Munición | — |
| Cerbatana | Destreza | A distancia: DES | Munición | — |
| Red | Destreza | Arrojadiza: DES | Arrojadiza | Red |
| Ballesta Recargable | Destreza | A distancia: DES | Munición | Ballesta Recargable |
| Hacha Ballesta | Según el modo | Disparo con munición: DES · Cuerpo a cuerpo (hacha): FUE | Munición | Hacha Ballesta |
| Slinga de guerra | Destreza | A distancia: DES | Munición | — |
| Arco de guerra | Destreza | A distancia: DES | Munición | Arco de guerra |
| Ballesta oculta | Destreza | A distancia: DES | Munición | — |
| Ballesta de repetición | Destreza | A distancia: DES | Munición | — |
| Chakram | Fuerza o Destreza | Arrojadiza: FUE/DES | Sutil, Arrojadiza | — |
| Arco espada | Según el modo | Disparo con munición: DES · Cuerpo a cuerpo (espada): FUE | Munición | Arco espada |
| Peñasco | Destreza | Arrojadiza: DES | Arrojadiza | — |
| Boleadora | Destreza | Arrojadiza: DES | Arrojadiza | Boleadora |
| Bola con pinchos | Destreza | Arrojadiza: DES | Arrojadiza | — |

## 7. Reglas especiales asociadas (62 reglas, 63 objetos)

| Regla del documento | Objetos |
|---|---|
| Ancla | Ancla |
| Arco de guerra | Arco de guerra |
| Arco espada | Arco espada |
| Ballesta Recargable | Ballesta Recargable |
| Banana Explosiva | Banana Explosiva |
| Bastón espada | Bastón espada |
| Bat'leth | Bat'leth |
| Bola y cadena | Cadena con Bola |
| Boleadora | Boleadora |
| Brandistock | Brandistock |
| Cuchillo de cuerno | Cuchillo de cuerno |
| Cuchillo de medico | Cuchillo de médico |
| Cuchillo Ritual | Cuchillo Ritual |
| Daga de Parry | Daga de Parry |
| Eku | Eku |
| Escudo de Aspis | Escudo de Aspis |
| Escudo de cometa | Escudo de cometa |
| Escudo de pared | Escudo de pared |
| Escudo de ruptura | Escudo de ruptura |
| Escudo de Tres Cuartos | Escudo de Tres Cuartos |
| Escudo grande | Escudo grande |
| Escudo medio | Escudo medio |
| Escudo pesado | Escudo pesado |
| Espada Bastarda | Espada Bastarda |
| Espada berserk | Espada berserk |
| Espada Brazo | Espada Brazo |
| Espada de gancho | Espada de gancho |
| Espada de madera | Espada de madera |
| Espada del verdugo | Espada del verdugo |
| Espadas gemelas | Espadas gemelas |
| Falchion | Falchion |
| Fuego en Frasco | Fuego en Frasco |
| Garra | Garra |
| Gran guadaña, variante | Gran guadaña, variante |
| Gran hacha del verdugo | Gran hacha del verdugo |
| Guadaña de combate | Guadaña de Combate |
| Guadaña del verdugo | Guadaña del verdugo |
| Hacha Ballesta | Hacha Ballesta |
| Hoja Oculta | Hoja Oculta |
| Hoopak | Hoopak |
| Ikakalaka | Ikakalaka |
| Karambit | Karambit |
| Kunai | Kunai |
| Langmesser | Langmesser |
| Lanza de Caballería | Lanza de caballería |
| Lanza gemela | Lanza gemela |
| Lanza jabalí | Lanza jabalí |
| Latigo Cortante | Latigo Cortante |
| Latigo de combate | Latigo de combate |
| Martillo Horno | Martillo Horno |
| Mayal de incensario | Mayal de incensario |
| Maza de Asedio | Maza de Asesio |
| Mazo Antorcha / Antorcha larga | Mazo antorcha, Antorcha Larga |
| Naginata | Naginata |
| Ninjato | Ninjato |
| Partizan | Partizan |
| Red | Red |
| Red atrapa bichos | Red atrapa Bichos |
| Shotel | Shotel |
| Tonfa con cuchillas | Tonfa con cuchillas |
| Ultra Espadón | Ultra Espadón |
| Yo-yo con hoja | Yo-Yo con hoja |

## 8. Propiedades del glosario y cuántos objetos las usan

| Propiedad | Objetos |
|---|---|
| A dos manos | 50 |
| Alcance | 24 |
| Arrojadiza | 33 |
| Costosa | 11 |
| Desarmarte | 3 |
| Destructora | 1 |
| Especial | 63 |
| Guante | 6 |
| Incómoda | 5 |
| Ligera | 46 |
| Munición | 16 |
| Oculta | 10 |
| Pesada | 48 |
| Rango | 18 |
| Recarga | 8 |
| Retornable | 8 |
| Sutil | 53 |
| Versátil | 29 |
