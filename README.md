# Favor de Jack

Portal digital de administración del gremio: personajes, banca, inventario, misiones, regulaciones, mapa de Waterdeep y tiendas.

> **Repositorio público; datos privados.** Este repositorio contiene código y recursos públicos, no las credenciales ni los datos vivos del servidor.

## Tecnologías

PHP 8 (8.2+ recomendado), MySQL/MariaDB y servidor web con PHP. La aplicación no requiere frameworks. La ruta base se detecta desde `includes/paths.php`, tanto en la raíz de un dominio como en una subcarpeta.

## Estructura

| Ruta | Contenido |
| --- | --- |
| `bancadigital/` | Banca y economía |
| `archivosdelgremio/` | Personajes, fichas y relaciones |
| `inventario/` | Equipo y objetos |
| `tablon/` | Misiones |
| `regulaciones/` | Reglas y parches |
| `mapa/` | Mapa interactivo |
| `tiendas/` | Emporio y comercios |
| `cronicas/` | Crónicas |
| `rolldice/` | Dados |
| `timetoplay/` | Contenido del gremio |
| `admin/` | Administración y respaldos |
| `includes/` | Código PHP compartido |
| `assets/`, `media/` | Recursos gráficos |
| `instalar/` | Instalación y esquema SQL |

## Instalación y mantenimiento

1. Clona el repositorio en un servidor con PHP y MySQL/MariaDB.
2. Copia `config.example.php` como **`config.local.php`** y configura la base de datos. Nunca subas la copia real a Git.
3. Para instalar desde cero, importa `instalar/esquema.sql`. Para restaurar el sitio existente, exporta primero un paquete desde `admin/respaldo.php` y utiliza el instalador de `instalar/` con su clave de instalación privada.
4. Configura HTTPS y las protecciones del servidor web. **Nginx no lee `.htaccess`**: si usas aaPanel/Nginx, aplica las reglas de [seguridad](.github/SECURITY.md) manualmente.
5. Verifica inicio de sesión, imágenes, banca, inventario y tiendas tras los cambios.

**Código y datos son independientes:** GitHub no contiene la base de datos, retratos privados, fichas ni los paquetes de respaldo. Conserva los respaldos fuera del repositorio y, cuando sea posible, fuera de la raíz pública del servidor.

## Imágenes

Los recursos compartidos se gestionan en `assets/` y `media/`. Cada tienda mantiene sus propios recursos en su directorio; las miniaturas generadas y los archivos subidos por usuarios no deben incorporarse a Git.

## Desarrollo y seguridad

Realiza los cambios en ramas y revisa los pull requests antes de integrarlos. GitHub Actions comprueba la sintaxis PHP/JSON y busca posibles credenciales; no sustituye las pruebas en el servidor.

Consulta la [política de seguridad](.github/SECURITY.md) para proteger archivos internos y responder a una exposición de credenciales.
