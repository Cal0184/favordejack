# Favor de Jack

Portal del gremio: Banca, Inventario, Archivos del Gremio, Relaciones, dados, reglamento, mapa de Waterdeep y tiendas.

- PHP 8 + MySQL/MariaDB, sin frameworks ni dependencias.
- Funciona en la raíz de un dominio o en cualquier carpeta: la ruta base se detecta sola (`includes/paths.php`).
- La configuración del servidor va en `config.local.php` (plantilla: `config.example.php`). Nunca se sube a GitHub.
- Los datos (base de datos, imágenes subidas, fichas) se exportan e importan desde `admin/respaldo.php`.

Para cambiar de dominio o proveedor, sigue [MUDANZA.md](MUDANZA.md).

## Probar en local

1. Instala Apache con PHP 8 y MariaDB, con `AllowOverride All`.
2. Copia el repositorio en la carpeta web y crea `config.local.php`.
3. Importa `instalar/esquema.sql`, o un paquete de respaldo (ver MUDANZA.md).
