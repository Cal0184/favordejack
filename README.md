# Favor de Jack

Portal digital de gestión del gremio de aventureros: personajes, economía, inventarios, relaciones, misiones, regulaciones, mapa y tiendas.

> **Proyecto público, datos privados.** Este repositorio contiene el código y recursos públicos. Las contraseñas del servidor, respaldos, fichas privadas y datos generados en producción nunca deben incorporarse a Git.

## Tecnologías y requisitos

- PHP 8.2 o superior recomendado, compatible con la base PHP 8 del proyecto.
- MySQL o MariaDB; extensiones PHP `mysqli` y `mbstring`.
- Apache con `AllowOverride All` **o** Nginx con reglas de acceso equivalentes (véase [SECURITY.md](SECURITY.md)).
- Sin frameworks ni gestor de dependencias de terceros obligatorio.

## Módulos

| Directorio | Función |
| --- | --- |
| `bancadigital/` | Banca y saldos de personajes |
| `archivosdelgremio/` | Expedientes, personajes y relaciones |
| `inventario/` | Inventario y equipo |
| `tablon/` | Misiones y recompensas |
| `regulaciones/` | Reglamento y parches |
| `mapa/` | Mapa interactivo y tiendas |
| `tiendas/` | Emporio, tienda del gremio y demás comercios |
| `cronicas/` | Crónicas de la campaña |
| `rolldice/` | Herramientas de dados |
| `admin/` | Administración y respaldos |
| `includes/` | Servicios y funciones compartidos |
| `media/` y `assets/` | Recursos gráficos compartidos |
| `instalar/` | Instalador y esquema inicial |

## Instalación local

1. Instala PHP 8.2+, MySQL/MariaDB y un servidor web con PHP habilitado.
2. Clona el repositorio en la raíz del sitio o una subcarpeta.
3. Copia `config.example.php` a `config.local.php` y configura la base de datos. **No publiques ese archivo.**
4. Importa `instalar/esquema.sql` en una base vacía, o restaura un respaldo confiable siguiendo [MUDANZA.md](MUDANZA.md).
5. Configura las reglas de seguridad del servidor antes de publicar el sitio. Las reglas `.htaccess` **no funcionan en Nginx**.
6. Comprueba inicio de sesión, banca, personajes, tiendas y carga de imágenes.

La ruta base se detecta automáticamente desde `includes/paths.php`. No es necesario modificar rutas para ejecutar el sitio en una subcarpeta.

## Desarrollo y despliegue

- Trabaja en ramas independientes; integra mediante pull requests.
- No subas `config.local.php`, credenciales, exportaciones, datos de usuarios ni respaldos.
- El código versionado y los datos vivos son cosas distintas: antes de actualizar, guarda un respaldo desde `admin/respaldo.php`.
- GitHub Actions comprueba sintaxis PHP, JSON y posibles credenciales en commits.
- Para migrar a otro servidor, consulta [MUDANZA.md](MUDANZA.md).
- Para protección de archivos y respuesta a incidentes, consulta [SECURITY.md](SECURITY.md).

## Referencias

- [Imágenes y rutas](IMAGENES.md)
- [Mudanza y restauración](MUDANZA.md)
- [Política de seguridad](SECURITY.md)

**Nota:** El repositorio no contiene una copia completa de la base de datos ni de los archivos privados de producción; clonar GitHub no equivale a restaurar el sitio.
