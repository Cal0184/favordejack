# Mudar Favor de Jack a otro dominio o proveedor

La web se divide en dos partes:

- **El código**, que está en GitHub (este repositorio).
- **Los datos**: la base de datos, los retratos e iconos subidos, las fichas PDF y los JSON que edita la web. Viajan en un **paquete de respaldo** (.zip).

Con las dos partes la web queda igual en cualquier servidor con PHP 8 y MySQL o MariaDB. Funciona en la raíz del dominio (`midominio.com/`) o en una carpeta (`midominio.com/favordejack/`) sin cambiar nada.

## 1. En la web vieja: sacar el paquete

1. Entra con tu cuenta de admin y ve a **Mi cuenta → Respaldo de la web** (`admin/respaldo.php`).
2. Pulsa **Crear paquete ahora**.
3. Pulsa **Descargar** y guarda el .zip en tu ordenador.

No compartas el paquete: lleva las contraseñas cifradas de los usuarios.

## 2. En el hosting nuevo: preparar

1. Crea una **base de datos vacía** y un **usuario** con todos los permisos sobre ella (en el panel del hosting, «Bases de datos MySQL»).
2. Descarga el código de GitHub: botón verde **Code → Download ZIP**.
3. Sube el contenido del ZIP a la carpeta de la web: a `public_html/` si va en la raíz, o a `public_html/favordejack/` si va en una carpeta.
4. Copia `config.example.php` como **`config.local.php`** y rellena el nombre de la base de datos, el usuario y la contraseña.

## 3. Cargar los datos

1. Abre el paquete .zip en tu ordenador y saca el archivo **`base-de-datos.sql`**.
2. En phpMyAdmin, entra en la base de datos nueva y usa **Importar** con ese archivo. Esto crea las tablas y los usuarios, así que ya puedes iniciar sesión.
3. Sube el paquete .zip entero por FTP a la carpeta **`respaldos/`** de la web.
4. Entra con tu cuenta de admin en `admin/respaldo.php`. En el paquete, abre **Restaurar este paquete**, escribe `RESTAURAR` y confirma. Esto copia las imágenes, las fichas y los datos de las tiendas.

Si el servidor tiene SSH, los pasos 1 a 4 se reducen a uno:
`php instalar/respaldo-cli.php restaurar respaldos/paquete.zip`

## 4. Apuntar el dominio

1. En el registrador del dominio, cambia los **DNS** (o el registro A) a los del hosting nuevo.
2. Activa **HTTPS** (Let's Encrypt) en el panel del hosting nuevo.
3. Si cambias de dominio, deja en el viejo una redirección al nuevo.

## Instalar desde cero (sin datos)

Importa `instalar/esquema.sql` en una base vacía. Crea todas las tablas, sin usuarios ni personajes.

## Qué archivos no están en GitHub

Están en `.gitignore`: `config.local.php`, `bancadigital/config.php`, `respaldos/`, `media/characters|icons|profiles/`, `bancadigital/data/`, `bancadigital/icons|characters/` (copia antigua, vacía tras «Ordenar imágenes»), `archivosdelgremio/fichas/`, y los pedidos y decisiones del Emporio de Ecix's (`tiendas/emporio/data/`). Todo eso viaja en el paquete, junto con las cartas de `tiendas/emporio/Cartas/`.
