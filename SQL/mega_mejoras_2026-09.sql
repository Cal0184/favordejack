-- Favor de Jack · Mega mejoras 2026-09
-- OPCIONAL: el código ejecuta estas migraciones automáticamente al abrir
-- Banca / Archivos / Inventario con un usuario de BD que tenga CREATE/UPDATE.
-- Usa este archivo solo si prefieres crear las tablas manualmente en phpMyAdmin.

CREATE TABLE IF NOT EXISTS FavorMeta (
    Clave varchar(80) NOT NULL PRIMARY KEY,
    Valor text NULL,
    Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS InventarioItem (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PersonajeRelacion (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migración de rutas antiguas. Es segura si ya están migradas porque solo toca
-- valores que todavía comienzan por icons/ o characters/.
UPDATE Personaje SET IconUrl=CONCAT('media/icons/',SUBSTRING(IconUrl,7)) WHERE IconUrl LIKE 'icons/%';
UPDATE Personaje SET PortraitUrl=CONCAT('media/characters/',SUBSTRING(PortraitUrl,12)) WHERE PortraitUrl LIKE 'characters/%';
UPDATE Jugador SET IconUrl=CONCAT('media/icons/',SUBSTRING(IconUrl,7)) WHERE IconUrl LIKE 'icons/%';
INSERT INTO FavorMeta (Clave,Valor) VALUES ('media_paths_v1','ok') ON DUPLICATE KEY UPDATE Valor='ok';
