-- Favor de Jack · Armas y Armaduras: tablas (migración completa, se puede ejecutar varias veces).
-- No hace falta ejecutarla: armeria/ crea estas tablas sola la primera vez que se abre.
-- No toca tablas de la Banca ni del Inventario.
SET NAMES utf8mb4;
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

