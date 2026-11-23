# Seguridad del repositorio y del servidor

Favor de Jack es un repositorio **público**. Los secretos y los datos privados deben mantenerse **fuera de Git** y protegidos por el servidor web.

## No publicar jamás

- `config.local.php`, `bancadigital/config.php`, `.env`, claves SSH/TLS, tokens de API o webhooks.
- Paquetes de `respaldos/`, volcados SQL, contraseñas, secretos de correo o claves de instalación (`instalar/clave.php`).
- Datos vivos de usuarios, fichas privadas, historiales de acceso o exportaciones administrativas.

El archivo `config.example.php` es exclusivamente una plantilla con marcadores de posición. `.gitignore` reduce los accidentes, pero **no protege archivos que Git ya haya registrado** ni elimina secretos del historial. Un repositorio público tampoco puede ocultar información ya descargada por terceros.

## Si una clave se publicó

1. Revoca o cambia **inmediatamente** la credencial en su proveedor (base de datos, correo, API, Discord, etc.).
2. Comprueba registros de acceso y permisos; sustituye las sesiones o tokens afectados.
3. Retira el secreto del código actual y deshazte de copias en artefactos de CI y respaldos públicos.
4. Revisa el historial de Git. Si debe purgarse, planifica una reescritura con `git-filter-repo` y coordina los clones: reescribir historial rompe referencias y **no sustituye la rotación**.
5. No publiques la credencial, ni siquiera parcialmente, en un issue o pull request.

## Apache y Nginx no son equivalentes

En Apache, el archivo raíz `.htaccess` restringe archivos internos. **Nginx ignora por completo `.htaccess`**: en aaPanel debes añadir reglas equivalentes en la configuración del sitio.

Ejemplo orientativo para un despliegue en la **raíz del dominio**. Pega las reglas dentro de `server { ... }` y antes del bloque genérico de PHP o extensiones estáticas; ajusta los prefijos si la aplicación se instala en una subcarpeta:

```nginx
# Nunca servir archivos internos, aunque tengan extensión PHP o JSON.
location ^~ /includes/                  { deny all; }
location ^~ /respaldos/                 { deny all; }
location ^~ /admin/data/                { deny all; }
location ^~ /bancadigital/data/         { deny all; }
location ^~ /archivosdelgremio/fichas/  { deny all; }
location ^~ /cronicas/data/             { deny all; }
location ^~ /regulaciones/data/         { deny all; }
location ^~ /tiendas/emporio/data/      { deny all; }

location = /config.local.php            { deny all; }
location = /config.example.php          { deny all; }
location = /bancadigital/config.php     { deny all; }
location = /instalar/clave.php          { deny all; }
location = /instalar/respaldo-cli.php   { deny all; }

# Impedir ejecución de PHP u otros ejecutables cargados en media/.
location ~* ^/media/.*\.(?:php|phtml|phar)$ { deny all; }

# Bloquear metadatos, claves y formatos internos.
location ~ /\.(?!well-known/)          { deny all; }
location ~* \.(?:sql|json|md|txt|bak|log|ini|env|tmp|swp|orig|old|dist|pem|key|p12|pfx)$ { deny all; }
```

**Atención:** no bloquees `media/` por completo: allí se sirven imágenes legítimas. En Nginx, el orden y la prioridad de los bloques `location` importan; integra este ejemplo con la configuración existente, no sustituyas a ciegas toda la configuración de aaPanel.

Antes de aplicar: crea copia de la configuración. Comprueba con `sudo nginx -t` y solo después recarga con `sudo systemctl reload nginx`. Verifica con peticiones HTTP que un archivo sensible devuelve **403/404** y que imágenes, CSS, JS y PHP legítimos siguen funcionando. La configuración del servidor **no se despliega automáticamente desde GitHub**.

También conviene desactivar el listado de directorios, activar HTTPS, asegurar las cookies y no dejar el instalador operativo tras la mudanza.

## Comprobaciones de GitHub

El flujo `.github/workflows/quality-security.yml` comprueba sintaxis PHP/JSON y ejecuta un detector de secretos. Estas comprobaciones reducen riesgos, pero **no certifican ausencia absoluta de secretos**. Habilita además las opciones de protección de ramas y alertas de secretos en GitHub cuando estén disponibles.

## Informar un problema

Comunica vulnerabilidades y posibles credenciales expuestas por un canal **privado** al administrador de Favor de Jack. No publiques información explotable en issues.
