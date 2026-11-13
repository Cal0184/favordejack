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

## 2. En el hosting nuevo: copiar la web

1. Crea una **base de datos vacía** y un **usuario** con todos los permisos sobre ella (en el panel del hosting).
2. Copia la carpeta entera de la web vieja (con su `respaldos/` y el paquete del paso 1) a la carpeta del dominio nuevo: `public_html/` si va en la raíz.
   Si no puedes copiar la carpeta, sirve también el código de GitHub (**Code → Download ZIP**) más el paquete en `respaldos/`.
3. Crea `instalar/clave.php` con una clave larga: `<?php return 'tu-clave-larga';` (el paquete de mudanza ya lo trae).

## 3. Cargar los datos: el instalador

1. Abre `https://dominio-nuevo/instalar/`.
2. Escribe la clave, los datos de la base de datos nueva y elige el paquete.
3. Pulsa **Instalar**. Escribe `config.local.php`, carga la base de datos y los archivos del paquete, y se cierra solo (borra `clave.php`).

Si el servidor tiene SSH, también sirve:
`php instalar/respaldo-cli.php restaurar respaldos/paquete.zip` (con `config.local.php` hecho a mano desde `config.example.php`).

## 4. Apuntar el dominio

1. En el registrador del dominio, cambia los **DNS** (o el registro A) a los del hosting nuevo.
2. Activa **HTTPS** (Let's Encrypt) en el panel del hosting nuevo y pon `'force_https' => true` en `config.local.php` (el instalador lo marca si ya abriste con https://).
3. Si cambias de dominio, deja en el viejo una redirección al nuevo: una carpeta con solo un `index.php` que haga `header('Location: https://nuevo/…', true, 301)` y un `.htaccess` que mande todo a ese `index.php`.

## Instalar desde cero (sin datos)

Importa `instalar/esquema.sql` en una base vacía. Crea todas las tablas, sin usuarios ni personajes.

## Qué archivos no están en GitHub

Están en `.gitignore`: `config.local.php`, `bancadigital/config.php`, `respaldos/`, `media/characters|icons|profiles/`, `bancadigital/data/`, `bancadigital/icons|characters/` (copia antigua, vacía tras «Ordenar imágenes»), `archivosdelgremio/fichas/`, y los pedidos y decisiones del Emporio de Ecix's (`tiendas/emporio/data/`). Todo eso viaja en el paquete, junto con las cartas de `tiendas/emporio/Cartas/`.
