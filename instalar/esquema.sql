-- Favor de Jack · estructura completa de la base de datos (sin datos).
-- Crea todas las tablas en una base vacía: phpMyAdmin → Importar este archivo.
-- Para traer también los datos usa el paquete de «Exportar todo» del admin.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `FavorMeta` (
  `Clave` varchar(80) NOT NULL,
  `Valor` text DEFAULT NULL,
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`Clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `InventarioItem` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `PersonajeID` varchar(100) NOT NULL,
  `Nombre` varchar(160) NOT NULL,
  `Categoria` varchar(80) NOT NULL DEFAULT 'Objeto',
  `Cantidad` int(10) unsigned NOT NULL DEFAULT 1,
  `Equipado` tinyint(1) NOT NULL DEFAULT 0,
  `Descripcion` text DEFAULT NULL,
  `Notas` text DEFAULT NULL,
  `ImageUrl` varchar(500) DEFAULT NULL,
  `CreadoPor` varchar(50) DEFAULT NULL,
  `Creado` timestamp NOT NULL DEFAULT current_timestamp(),
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_inventario_personaje` (`PersonajeID`),
  KEY `idx_inventario_nombre` (`Nombre`),
  CONSTRAINT `fk_inventario_personaje` FOREIGN KEY (`PersonajeID`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Jugador` (
  `ID` varchar(5) NOT NULL,
  `Nombre` varchar(60) NOT NULL,
  `Handle` varchar(40) NOT NULL DEFAULT '',
  `Emoji` varchar(16) NOT NULL DEFAULT '?️',
  `IconBg` varchar(20) NOT NULL DEFAULT '#22402F',
  `IconUrl` varchar(300) NOT NULL DEFAULT '',
  `Orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`),
  KEY `idx_jugador_activo_orden` (`Activo`,`Orden`),
  KEY `idx_jugador_nombre` (`Nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Mision` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(160) NOT NULL,
  `Fecha` date DEFAULT NULL,
  `Tipo` varchar(80) NOT NULL DEFAULT '',
  `Estado` varchar(50) NOT NULL DEFAULT 'Completada',
  `Descripcion` text DEFAULT NULL,
  `RecompensaGeneral` varchar(255) NOT NULL DEFAULT '',
  `CreadoPor` varchar(50) NOT NULL DEFAULT '',
  `Creado` datetime NOT NULL DEFAULT current_timestamp(),
  `Actualizado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_mision_fecha` (`Fecha`),
  KEY `idx_mision_estado` (`Estado`),
  KEY `idx_mision_tipo` (`Tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `MisionPersonaje` (
  `MisionID` int(10) unsigned NOT NULL,
  `PersonajeID` varchar(100) NOT NULL,
  `NombreSnapshot` varchar(150) NOT NULL DEFAULT '',
  `ClaseSnapshot` varchar(150) NOT NULL DEFAULT '',
  `NivelSnapshot` smallint(5) unsigned DEFAULT NULL,
  `Recompensa` varchar(255) NOT NULL DEFAULT '',
  `Notas` text DEFAULT NULL,
  PRIMARY KEY (`MisionID`,`PersonajeID`),
  KEY `idx_misionpersonaje_personaje` (`PersonajeID`),
  CONSTRAINT `fk_misionpersonaje_mision` FOREIGN KEY (`MisionID`) REFERENCES `Mision` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_misionpersonaje_personaje` FOREIGN KEY (`PersonajeID`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Biblioteca del Archivo de Misiones: un libro por misión completada (modelo y orden fijos).
-- La web la crea sola (includes/biblioteca.php); aquí solo como referencia.
CREATE TABLE IF NOT EXISTS `MisionLibro` (
  `MisionID` int(10) unsigned NOT NULL,
  `Modelo` tinyint unsigned NOT NULL,
  `Orden` int unsigned NOT NULL,
  `Creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MisionID`),
  UNIQUE KEY `uq_misionlibro_orden` (`Orden`),
  CONSTRAINT `fk_misionlibro_mision` FOREIGN KEY (`MisionID`) REFERENCES `Mision` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Movimiento` (
  `ID` varchar(100) NOT NULL,
  `Personaje` varchar(100) NOT NULL,
  `Fecha` datetime(3) NOT NULL,
  `Tipo` enum('add','spend','exchange','initial','correction') NOT NULL,
  `DeltaPlatino` int(11) NOT NULL DEFAULT 0,
  `DeltaOro` int(11) NOT NULL DEFAULT 0,
  `DeltaPlata` int(11) NOT NULL DEFAULT 0,
  `DeltaCobre` int(11) NOT NULL DEFAULT 0,
  `Motivo` varchar(255) NOT NULL DEFAULT '',
  `Autor` varchar(100) NOT NULL DEFAULT '',
  `SaldoPlatino` int(11) NOT NULL DEFAULT 0,
  `SaldoOro` int(11) NOT NULL DEFAULT 0,
  `SaldoPlata` int(11) NOT NULL DEFAULT 0,
  `SaldoCobre` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `idx_movimiento_personaje_fecha` (`Personaje`,`Fecha`),
  KEY `idx_movimiento_fecha` (`Fecha`),
  KEY `idx_movimiento_tipo` (`Tipo`),
  CONSTRAINT `fk_movimiento_personaje` FOREIGN KEY (`Personaje`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Personaje` (
  `ID` varchar(100) NOT NULL,
  `Jugador` varchar(5) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Platino` int(11) NOT NULL DEFAULT 0,
  `Oro` int(11) NOT NULL DEFAULT 0,
  `Plata` int(11) NOT NULL DEFAULT 0,
  `Cobre` int(11) NOT NULL DEFAULT 0,
  `Descripcion` varchar(255) NOT NULL DEFAULT '',
  `Notas` text NOT NULL,
  `IconUrl` varchar(300) DEFAULT NULL,
  `PortraitUrl` varchar(300) DEFAULT NULL,
  `Orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`),
  KEY `idx_personaje_jugador` (`Jugador`),
  KEY `idx_personaje_jugador_activo_orden` (`Jugador`,`Activo`,`Orden`),
  KEY `idx_personaje_nombre` (`Nombre`),
  CONSTRAINT `fk_personaje_jugador` FOREIGN KEY (`Jugador`) REFERENCES `Jugador` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PersonajeFicha` (
  `PersonajeID` varchar(100) NOT NULL,
  `Datos` longtext NOT NULL,
  `Archivo` varchar(255) DEFAULT NULL,
  `ImportadoPor` varchar(100) DEFAULT NULL,
  `Importado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`PersonajeID`),
  CONSTRAINT `fk_ficha_personaje` FOREIGN KEY (`PersonajeID`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PersonajePerfil` (
  `ID` varchar(100) NOT NULL,
  `NombreCompleto` varchar(150) DEFAULT NULL,
  `Altura` decimal(4,2) DEFAULT NULL,
  `Edad` varchar(50) DEFAULT NULL,
  `Sexo` varchar(30) DEFAULT NULL,
  `Raza` varchar(100) DEFAULT NULL,
  `TipoRaza` varchar(100) DEFAULT NULL,
  `Clase` varchar(150) DEFAULT NULL,
  `Nivel` smallint(5) unsigned DEFAULT NULL,
  `Fuerza` tinyint(3) unsigned DEFAULT NULL,
  `Destreza` tinyint(3) unsigned DEFAULT NULL,
  `Constitucion` tinyint(3) unsigned DEFAULT NULL,
  `Inteligencia` tinyint(3) unsigned DEFAULT NULL,
  `Sabiduria` tinyint(3) unsigned DEFAULT NULL,
  `Carisma` tinyint(3) unsigned DEFAULT NULL,
  `Dotes` smallint(5) unsigned DEFAULT NULL,
  `NivelAmenaza` varchar(50) DEFAULT NULL,
  `Estado` varchar(50) NOT NULL DEFAULT 'Activo',
  `Categoria` varchar(20) NOT NULL DEFAULT 'Personaje',
  `Biografia` text DEFAULT NULL,
  `Creado` datetime NOT NULL DEFAULT current_timestamp(),
  `Actualizado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  CONSTRAINT `fk_personajeperfil_personaje` FOREIGN KEY (`ID`) REFERENCES `Personaje` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PersonajeRelacion` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `PersonajeA` varchar(100) NOT NULL,
  `PersonajeB` varchar(100) NOT NULL,
  `Tipo` varchar(80) NOT NULL DEFAULT 'Unidos',
  `Nota` varchar(500) DEFAULT NULL,
  `CreadoPor` varchar(50) DEFAULT NULL,
  `Creado` timestamp NOT NULL DEFAULT current_timestamp(),
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_relacion_par` (`PersonajeA`,`PersonajeB`),
  KEY `idx_relacion_a` (`PersonajeA`),
  KEY `idx_relacion_b` (`PersonajeB`),
  CONSTRAINT `fk_relacion_personaje_a` FOREIGN KEY (`PersonajeA`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_personaje_b` FOREIGN KEY (`PersonajeB`) REFERENCES `Personaje` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- «Olvidé mi contraseña»: avisos al DM y contraseñas temporales (includes/clave.php).
CREATE TABLE IF NOT EXISTS `ClaveSolicitud` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Usuario` int(10) unsigned NOT NULL,
  `Estado` enum('pendiente','temporal','hecha') NOT NULL DEFAULT 'pendiente',
  `Creado` timestamp NOT NULL DEFAULT current_timestamp(),
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Autor` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_clave_usuario` (`Usuario`,`Estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Límite de intentos de entrada (includes/auth.php). Se puede vaciar sin perder nada.
CREATE TABLE IF NOT EXISTS `LoginIntento` (
  `Clave` char(67) NOT NULL,
  `Fallos` int(10) unsigned NOT NULL DEFAULT 0,
  `Primero` int(10) unsigned NOT NULL DEFAULT 0,
  `BloqueadoHasta` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`Clave`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

CREATE TABLE IF NOT EXISTS `Usuario` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `PasswordHash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `Salt` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `PasswordIterations` int(10) unsigned NOT NULL DEFAULT 200000,
  `Jugador` varchar(5) NOT NULL,
  `Rol` enum('player','admin') NOT NULL DEFAULT 'player',
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `PasswordHint` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_usuario_username` (`Username`),
  KEY `idx_usuario_jugador` (`Jugador`),
  CONSTRAINT `fk_usuario_jugador` FOREIGN KEY (`Jugador`) REFERENCES `Jugador` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Armas y Armaduras (los datos del documento se importan solos al abrir armeria/).
CREATE TABLE IF NOT EXISTS `ArmaCategoria` (
  `Clave` varchar(40) NOT NULL,
  `Nombre` varchar(80) NOT NULL,
  `Tipo` varchar(12) NOT NULL,
  `Orden` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaRegla` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Clave` varchar(120) NOT NULL,
  `Titulo` varchar(160) NOT NULL,
  `Texto` mediumtext NOT NULL,
  `Origen` varchar(12) NOT NULL DEFAULT 'documento',
  `Editado` tinyint(1) NOT NULL DEFAULT 0,
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_armaregla_clave` (`Clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaPropiedad` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Clave` varchar(60) NOT NULL,
  `Nombre` varchar(80) NOT NULL,
  `TituloDocumento` varchar(80) NOT NULL DEFAULT '',
  `Definicion` mediumtext NOT NULL,
  `Orden` smallint(6) NOT NULL DEFAULT 0,
  `Origen` varchar(12) NOT NULL DEFAULT 'documento',
  `Editado` tinyint(1) NOT NULL DEFAULT 0,
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_armapropiedad_clave` (`Clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaObjeto` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Clave` varchar(120) NOT NULL,
  `Tipo` varchar(12) NOT NULL,
  `Categoria` varchar(40) NOT NULL,
  `Nombre` varchar(160) NOT NULL,
  `Coste` varchar(40) NOT NULL DEFAULT '',
  `CosteCobre` int(10) unsigned DEFAULT NULL,
  `Dano` varchar(120) NOT NULL DEFAULT '',
  `DanoDados` varchar(20) NOT NULL DEFAULT '',
  `DanoPromedio` decimal(6,2) DEFAULT NULL,
  `TiposDano` varchar(160) NOT NULL DEFAULT '',
  `PropiedadesTexto` varchar(500) NOT NULL DEFAULT '',
  `Alcance` varchar(40) NOT NULL DEFAULT '',
  `CA` varchar(60) NOT NULL DEFAULT '',
  `CABase` tinyint(3) unsigned DEFAULT NULL,
  `CABono` tinyint(4) DEFAULT NULL,
  `ModDes` varchar(12) DEFAULT NULL,
  `DesMax` tinyint(3) unsigned DEFAULT NULL,
  `Fuerza` varchar(40) NOT NULL DEFAULT '',
  `FuerzaMin` tinyint(3) unsigned DEFAULT NULL,
  `Sigilo` varchar(40) NOT NULL DEFAULT '',
  `SigiloDesventaja` tinyint(1) NOT NULL DEFAULT 0,
  `ReglaID` int(10) unsigned DEFAULT NULL,
  `Notas` text DEFAULT NULL,
  `Original` text DEFAULT NULL,
  `Orden` int(11) NOT NULL DEFAULT 0,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `Origen` varchar(12) NOT NULL DEFAULT 'documento',
  `Editado` tinyint(1) NOT NULL DEFAULT 0,
  `CreadoPor` varchar(50) DEFAULT NULL,
  `Creado` timestamp NOT NULL DEFAULT current_timestamp(),
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `AtaqueAtributo` varchar(10) DEFAULT NULL,
  `AtaqueBase` varchar(10) DEFAULT NULL,
  `AtaqueMotivo` text DEFAULT NULL,
  `AtaqueExcepcion` text DEFAULT NULL,
  `AtaqueDuda` text DEFAULT NULL,
  `ClasifEditada` tinyint(1) NOT NULL DEFAULT 0,
  `Descripcion` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_armaobjeto_clave` (`Clave`),
  KEY `idx_armaobjeto_categoria` (`Categoria`,`Activo`),
  KEY `idx_armaobjeto_ataque` (`AtaqueAtributo`),
  KEY `idx_armaobjeto_nombre` (`Nombre`),
  KEY `idx_armaobjeto_regla` (`ReglaID`),
  CONSTRAINT `fk_armaobjeto_regla` FOREIGN KEY (`ReglaID`) REFERENCES `ArmaRegla` (`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaObjetoPropiedad` (
  `ObjetoID` int(10) unsigned NOT NULL,
  `PropiedadID` int(10) unsigned NOT NULL,
  `Detalle` varchar(60) NOT NULL DEFAULT '',
  `Orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`ObjetoID`,`PropiedadID`),
  KEY `idx_aop_propiedad` (`PropiedadID`),
  CONSTRAINT `fk_aop_objeto` FOREIGN KEY (`ObjetoID`) REFERENCES `ArmaObjeto` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_aop_propiedad` FOREIGN KEY (`PropiedadID`) REFERENCES `ArmaPropiedad` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaAtaqueModo` (
  `ObjetoID` int(10) unsigned NOT NULL,
  `Orden` tinyint(3) unsigned NOT NULL,
  `Modo` varchar(80) NOT NULL,
  `Atributo` varchar(10) NOT NULL,
  `Dano` varchar(60) NOT NULL DEFAULT '',
  `Nota` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`ObjetoID`,`Orden`),
  KEY `idx_aam_atributo` (`Atributo`),
  CONSTRAINT `fk_aam_objeto` FOREIGN KEY (`ObjetoID`) REFERENCES `ArmaObjeto` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaRequisito` (
  `ObjetoID` int(10) unsigned NOT NULL,
  `Orden` tinyint(3) unsigned NOT NULL,
  `Tipo` varchar(12) NOT NULL DEFAULT 'requisito',
  `Atributo` varchar(10) DEFAULT NULL,
  `Minimo` tinyint(3) unsigned DEFAULT NULL,
  `Texto` varchar(300) NOT NULL,
  PRIMARY KEY (`ObjetoID`,`Orden`),
  KEY `idx_areq_atributo` (`Atributo`),
  CONSTRAINT `fk_areq_objeto` FOREIGN KEY (`ObjetoID`) REFERENCES `ArmaObjeto` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS `ArmaNota` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ObjetoID` int(10) unsigned DEFAULT NULL,
  `Tipo` varchar(12) NOT NULL DEFAULT 'aclaracion',
  `Titulo` varchar(160) NOT NULL,
  `Texto` mediumtext NOT NULL,
  `Visible` tinyint(1) NOT NULL DEFAULT 1,
  `Autor` varchar(50) DEFAULT NULL,
  `Creado` timestamp NOT NULL DEFAULT current_timestamp(),
  `Actualizado` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `idx_armanota_objeto` (`ObjetoID`),
  CONSTRAINT `fk_armanota_objeto` FOREIGN KEY (`ObjetoID`) REFERENCES `ArmaObjeto` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

