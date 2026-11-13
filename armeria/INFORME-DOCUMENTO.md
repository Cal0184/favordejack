# Armas y Armaduras · Informe de importación del Word

Fuente: `Armas - Armaduras.docx`. Datos importados: `armeria/data/armamento.json` (se cargan solos en la base de datos al abrir el catálogo).

**Regla seguida:** no se corrigió ninguna mecánica ni ningún texto de reglas. Las erratas se conservan tal cual y se listan aquí para que decidas. Cada aviso aparece en la ficha del objeto («Advertencias del documento»), en la pestaña «Reglas y notas» y en el filtro «Con advertencias del documento». El admin los borra desde «Editar» cuando corrige el dato. El atributo de ataque y los requisitos están en `INFORME-ATRIBUTOS.md`.

## 1. Recuento

| Categoría | Entradas |
|---|---|
| Armas simples | 47 |
| Armas simples a distancia | 9 |
| Armas marciales | 90 |
| Armas marciales a distancia | 16 |
| Armaduras ligeras | 3 |
| Armaduras medias | 5 |
| Armaduras pesadas | 4 |
| Escudos | 11 |
| **Total** | **185** (162 armas, 12 armaduras, 11 escudos) |

- Propiedades del glosario: **18**, todas con su definición del documento.
- Reglas especiales: **62**, asociadas a **63** objetos (la regla «Mazo Antorcha / Antorcha larga» la comparten dos).
- Todos los objetos marcados «Especial» tienen su regla, y todas las reglas tienen su objeto.
- No se eliminó ni se combinó ninguna entrada.

## 2. Normalizaciones aplicadas (seguras, sin cambiar mecánicas)

- Los tipos de daño se muestran en minúscula («1d6 Cortante» → «1d6 cortante»). El texto original de cada fila queda guardado en la base de datos.
- Se quitaron espacios sobrantes y un «|» suelto en la celda de daño de *Shuriken de Fuma*.
- Los nombres de propiedad escritos de varias formas se unifican en una sola etiqueta que enlaza a su definición:

| En las tablas | Título en el glosario del Word | Etiqueta usada |
|---|---|---|
| Pesada | Pesado | Pesada |
| Recarga | Cargando | Recarga |
| Alcance | Alcanzar | Alcance |
| Arrojadiza, arrojable | Lanzado | Arrojadiza |
| A 2 manos, a dos manos, de dos manos | Dos manos | A dos manos |
| Guante, Guantes | Guante | Guante |
| Incomoda | Incomoda | Incómoda |
| Destrucción (Pala) | Destructora | Destructora |
| Versatil, Versátil | Versátil | Versátil |

El glosario muestra igualmente el título del documento («En el documento: Lanzado»).

## 3. Problemas de estructura del Word (resueltos al importar)

- La regla de «Escudo pesado» no tiene estilo de título en el Word (está dentro de «Escudo de pared»); se separó como regla propia.
- En «Espada de madera», parte del texto de la regla está dentro del título del Word («Úsese y tírese.…»); se movió al cuerpo de la regla.
- El título «Espadas gemelas» empieza con un salto de línea vacío en el Word.
- En «Ninjato», parte del texto de la regla está dentro del título del Word («Los ataques contra objetivos sorprendidos causan 1…»); se movió al cuerpo de la regla.
- En «Kunai», parte del texto de la regla está dentro del título del Word («Al empuñar un kunai en cada mano, si dominas la Fu…»); se movió al cuerpo de la regla.
- Dos reglas tienen un título distinto del nombre de su objeto: «Maza de Asedio» (tabla: *Maza de Asesio*) y «Bola y cadena» (tabla: *Cadena con Bola*). Se asociaron por contexto; conviene unificar el nombre.
- «Cuchillo de medico» (regla) y «Cuchillo de médico» (tabla) solo difieren en la tilde.

## 4. Valores dudosos por objeto

| Objeto | Categoría | Aviso |
|---|---|---|
| Lanza Larga | Armas simples | Daño dudoso en el documento: «z». Se conserva tal cual. |
| Pala | Armas simples | La tabla dice «Destrucción»; en el glosario la propiedad se llama «Destructora». |
| Garra | Armas simples | Las propiedades usan un punto en lugar de una coma como separador. |
| fauchard | Armas simples | El nombre empieza con minúscula en el documento («fauchard»); se muestra tal cual. |
| Arco corto | Armas simples a distancia | El rango «(80/320)» aparece separado por una coma de «Munición»; se asoció a esa propiedad. |
| Espada Media | Armas marciales | La tabla escribe «Versatil» sin tilde. |
| Tridente | Armas marciales | Tiene la propiedad Versátil sin el daño a dos manos entre paréntesis. |
| Tanto | Armas marciales | La tabla dice «arrojable»; se trató como la propiedad «Arrojadiza». |
| Yo-Yo con hoja | Armas marciales | El rango «10» tiene un solo número (normal/largo esperado). |
| hacha de asta | Armas marciales | El nombre empieza con minúscula en el documento («hacha de asta»); se muestra tal cual. |
| Shuriken de Fuma | Armas marciales | La celda de daño termina con un carácter «|» sobrante; se quitó al importar. |
| Ballesta de mano | Armas marciales a distancia | Falta una coma entre dos propiedades («… (30/120) recarga»). |
| Cerbatana | Armas marciales a distancia | Falta un espacio antes del paréntesis en las propiedades (p. ej. «Munición(25/100)»). |
| Escudo de ruptura | Escudos | Bonificación de CA dudosa: «-». El documento no da un valor. |
| Maza de Asesio | Armas marciales | La regla especial está titulada «Maza de Asedio» en el documento, pero el objeto se llama «Maza de Asesio» en la tabla. |
| Cadena con Bola | Armas marciales | La regla especial está titulada «Bola y cadena» en el documento, pero el objeto se llama «Cadena con Bola» en la tabla. |
| Red atrapa Bichos | Armas simples | Revisar: la tabla da 1 de daño contundente, pero la regla especial dice que la criatura diminuta golpeada «no sufre daño». |
| Arco espada | Armas marciales a distancia | Revisar: la regla menciona la propiedad «arma doble», que no está definida en el glosario del documento. |
| Espada Bastarda | Armas marciales | Revisar: la tabla marca «Incómoda» siempre, pero la regla especial solo la aplica al empuñarla con una mano sin competencia. |
| Hoja Oculta | Armas marciales | Revisar redacción: «causa 1d4 de daño adicional y daño perforante» (¿1d4 de daño perforante adicional?). |
| Daga y cuerda | Armas marciales | Revisar: rango 20/30 (el largo alcance suele ser varias veces el normal). |
| Gran Guadaña | Armas marciales | Hay dos entradas parecidas: «Gran Guadaña» (1d6, versátil) y «Gran guadaña, variante» (2d4, con regla Cosechar). Se conservan separadas. |
| Gran guadaña, variante | Armas marciales | Hay dos entradas parecidas: «Gran Guadaña» (1d6, versátil) y «Gran guadaña, variante» (2d4, con regla Cosechar). Se conservan separadas. |
| Mazo antorcha | Armas simples | Comparte la regla «Mazo Antorcha / Antorcha larga» con la Antorcha Larga. Revisar: es un mazo con daño perforante. |
| Antorcha Larga | Armas simples | Comparte la regla «Mazo Antorcha / Antorcha larga» con el Mazo antorcha. |
| Hoopak | Armas simples | La regla especial del documento solo describe el arma; no explica una mecánica concreta. |
| Banana Explosiva | Armas simples a distancia | Errata en la regla: «tirada brazode salvación». Se conserva el texto original. |
| Boleadora | Armas marciales a distancia | Errata en la regla: «derrigolpeada». Se conserva el texto original. |
| Daga de Parry | Armas simples | Errata en la regla: «variafunciones». Se conserva el texto original. |
| Guadaña del verdugo | Armas marciales | Errata en la regla: «incapacitad». Se conserva el texto original. |
| Shotel | Armas simples | Errata en la regla: «Cuando haces un ataque esta arma» y falta el punto final. |
| Martillo Horno | Armas marciales | Revisar redacción de la regla: «… de daño por fuego adicional de restablecerse al estado normal». |
| Latigo de combate | Armas marciales | El nombre se escribe «Latigo» sin tilde en el documento. |
| Latigo Cortante | Armas marciales | El nombre se escribe «Latigo» sin tilde en el documento. |
| Escudo pesado | Escudos | Su regla está escrita dentro de la de «Escudo de pared» y sin estilo de título en el Word. |
| Bumerang con cuchillas | Armas marciales | Se escribe «Bumerang» aquí y «Boomerang» en otras entradas. |

Los más importantes para decidir:

1. **Lanza Larga**: su daño en el Word es «z». Se muestra «z» hasta que lo corrijas desde Editar.
2. **Escudo de ruptura**: no tiene bonificación de CA («-»).
3. **Tridente**: Versátil sin el daño a dos manos.
4. **Red atrapa Bichos**: la tabla da daño 1 y la regla dice que no hace daño.
5. **Yo-Yo con hoja**: rango «10» con un solo número.

## 5. Erratas y traducciones en el glosario (texto conservado tal cual)

- **Munición**: «Dibujar la munición» (del inglés *draw*, sacar), «Armas improvisadas más adelante en la sección» remite a una sección que no está en el documento.
- **Sutil**: habla de «arma de delicadeza» y «rollos» de ataque y daño (tiradas).
- **Pesado**: «para que una pequeña criatura lo use».
- **Oculta**: la frase está partida en dos párrafos («…la oculte fácilmente.» / «obtienes ventaja…»).
- **Desarmarte**: «Puedes añades tu bonificación…».
- **Lanzado**: «propiedad arrojada», «Handaxe», «rollo de ataque».
- **Especial**: remite a «Armas especiales más adelante en esta sección»; en el catálogo cada regla está en la ficha de su objeto.
- **Rango** y **Alcanzar**: traducción literal («largo alcance», «agrega 5 pies a su alcance cuando atacas con él»).

## 6. Entradas parecidas que se conservan separadas

- *Gran Guadaña* (1d6, versátil) y *Gran guadaña, variante* (2d4, regla Cosechar).
- *Garra*, *Guante Garra* y *Garra de oso*. *Hoz* y *Hoz de granjero*. *Red* y *Red atrapa Bichos*.
- *Boomerang*, *Boomerang Gigante* y *Bumerang con cuchillas* (dos grafías).
