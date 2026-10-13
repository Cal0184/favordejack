<?php
declare(strict_types=1);

require_once __DIR__ . '/assets.php';

/**
 * Migraciones no destructivas para las mejoras 2026-09.
 * Se pueden ejecutar muchas veces: CREATE TABLE IF NOT EXISTS y una bandera
 * en FavorMeta hacen que subir el ZIP sea suficiente en instalaciones con
 * permisos CREATE/ALTER/UPDATE.
 */
function favor_ensure_schema(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS FavorMeta (
        Clave varchar(80) NOT NULL PRIMARY KEY,
        Valor text NULL,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS InventarioItem (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        PersonajeID varchar(100) NOT NULL,
        Nombre varchar(160) NOT NULL,
        Categoria varchar(80) NOT NULL DEFAULT 'Objeto',
        Cantidad int unsigned NOT NULL DEFAULT 1,
        Equipado tinyint(1) NOT NULL DEFAULT 0,
        Descripcion text NULL,
        Notas text NULL,
        ImageUrl varchar(500) NULL,
        CreadoPor varchar(50) NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_inventario_personaje (PersonajeID),
        INDEX idx_inventario_nombre (Nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS PersonajeRelacion (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        PersonajeA varchar(100) NOT NULL,
        PersonajeB varchar(100) NOT NULL,
        Tipo varchar(80) NOT NULL DEFAULT 'Unidos',
        Nota varchar(500) NULL,
        CreadoPor varchar(50) NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_relacion_par (PersonajeA,PersonajeB),
        INDEX idx_relacion_a (PersonajeA),
        INDEX idx_relacion_b (PersonajeB)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migra únicamente las cadenas de rutas; los archivos ya vienen movidos a /media/ en el ZIP.
    $stmt = $db->prepare("SELECT Valor FROM FavorMeta WHERE Clave='media_paths_v1' LIMIT 1");
    $stmt->execute();
    $done = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$done) {
        $db->query("UPDATE Personaje SET IconUrl=CONCAT('media/icons/',SUBSTRING(IconUrl,7)) WHERE IconUrl LIKE 'icons/%'");
        $db->query("UPDATE Personaje SET PortraitUrl=CONCAT('media/characters/',SUBSTRING(PortraitUrl,12)) WHERE PortraitUrl LIKE 'characters/%'");
        $db->query("UPDATE Jugador SET IconUrl=CONCAT('media/icons/',SUBSTRING(IconUrl,7)) WHERE IconUrl LIKE 'icons/%'");
        $stmt = $db->prepare("INSERT INTO FavorMeta (Clave,Valor) VALUES ('media_paths_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok'");
        $stmt->execute();
        $stmt->close();
    }
}
