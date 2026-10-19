-- ============================================================================
-- Carnet Digital de Monitoreo Nutricional Infantil
-- Estructura de la base de datos (17 tablas)
--
-- Se importa en una base vacía, antes de datos_iniciales.sql y oms_lms.sql.
--
-- Generado el 19-10-2026 10:26 desde la base local con:
--   php scripts/exportar_esquema.php
-- No editar a mano: cambiar la base local y volver a generarlo.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `catalogo_suplemento` (
  `id_catalogo_suplemento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `presentacion` varchar(60) DEFAULT NULL,
  `indicacion` varchar(100) DEFAULT NULL,
  `edad_desde_meses` tinyint(3) unsigned DEFAULT NULL,
  `edad_hasta_meses` tinyint(3) unsigned DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_catalogo_suplemento`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `catalogo_vacuna` (
  `id_catalogo_vacuna` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `dosis` varchar(30) NOT NULL,
  `edad_sugerida` varchar(60) DEFAULT NULL,
  `edad_meses` smallint(5) unsigned DEFAULT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `equivale_a` int(11) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_catalogo_vacuna`),
  UNIQUE KEY `uq_vacuna` (`nombre`,`dosis`),
  KEY `fk_vacuna_equivale` (`equivale_a`),
  CONSTRAINT `fk_vacuna_equivale` FOREIGN KEY (`equivale_a`) REFERENCES `catalogo_vacuna` (`id_catalogo_vacuna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `oms_lms` (
  `id_lms` int(11) NOT NULL AUTO_INCREMENT,
  `indicador` enum('peso_edad','talla_edad','peso_longitud','peso_talla') NOT NULL,
  `sexo` enum('M','F') NOT NULL,
  `x` decimal(5,1) NOT NULL,
  `l` decimal(10,6) NOT NULL,
  `m` decimal(10,6) NOT NULL,
  `s` decimal(10,6) NOT NULL,
  PRIMARY KEY (`id_lms`),
  UNIQUE KEY `uq_lms` (`indicador`,`sexo`,`x`),
  KEY `idx_lms_busqueda` (`indicador`,`sexo`,`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `puesto_salud` (
  `id_puesto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `distrito` varchar(100) DEFAULT NULL,
  `area_salud` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_puesto`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `territorio` (
  `id_territorio` int(11) NOT NULL AUTO_INCREMENT,
  `id_puesto` int(11) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id_territorio`),
  KEY `fk_territorio_puesto` (`id_puesto`),
  CONSTRAINT `fk_territorio_puesto` FOREIGN KEY (`id_puesto`) REFERENCES `puesto_salud` (`id_puesto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sector` (
  `id_sector` int(11) NOT NULL AUTO_INCREMENT,
  `id_territorio` int(11) NOT NULL,
  `letra` varchar(5) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_sector`),
  KEY `fk_sector_territorio` (`id_territorio`),
  CONSTRAINT `fk_sector_territorio` FOREIGN KEY (`id_territorio`) REFERENCES `territorio` (`id_territorio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_rol` int(11) NOT NULL,
  `id_puesto` int(11) NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `debe_cambiar_pass` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_alta` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_baja` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_usuario_rol` (`id_rol`),
  KEY `fk_usuario_puesto` (`id_puesto`),
  KEY `fk_usuario_sector` (`id_sector`),
  CONSTRAINT `fk_usuario_puesto` FOREIGN KEY (`id_puesto`) REFERENCES `puesto_salud` (`id_puesto`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `fk_usuario_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `id_registro_afectado` int(11) DEFAULT NULL,
  `detalle` varchar(300) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_origen` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_bitacora`),
  KEY `idx_bitacora_fecha` (`fecha_hora`),
  KEY `idx_bitacora_usuario` (`id_usuario`,`fecha_hora`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `paraje` (
  `id_paraje` int(11) NOT NULL AUTO_INCREMENT,
  `id_sector` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_paraje`),
  KEY `idx_paraje_sector` (`id_sector`),
  CONSTRAINT `fk_paraje_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `nino` (
  `id_nino` int(11) NOT NULL AUTO_INCREMENT,
  `id_sector` int(11) NOT NULL,
  `id_paraje` int(11) DEFAULT NULL,
  `cui` varchar(13) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `sexo` enum('M','F') NOT NULL,
  `pueblo` tinyint(3) unsigned DEFAULT NULL,
  `comunidad_linguistica` tinyint(3) unsigned DEFAULT NULL,
  `nombre_madre` varchar(150) DEFAULT NULL,
  `nombre_padre` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado` enum('activo','egresado','traslado') NOT NULL DEFAULT 'activo',
  `fecha_egreso` date DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_nino`),
  KEY `fk_nino_sector` (`id_sector`),
  KEY `fk_nino_paraje` (`id_paraje`),
  KEY `idx_nino_cui` (`cui`),
  KEY `idx_nino_estado` (`estado`),
  KEY `idx_nino_nombre` (`apellidos`,`nombres`),
  CONSTRAINT `fk_nino_paraje` FOREIGN KEY (`id_paraje`) REFERENCES `paraje` (`id_paraje`),
  CONSTRAINT `fk_nino_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `suplementacion` (
  `id_suplementacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_nino` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `fecha_entrega` date NOT NULL,
  `cantidad` varchar(50) DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_anulacion` varchar(200) DEFAULT NULL,
  `referencia_conocimiento` varchar(60) DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_suplementacion`),
  KEY `fk_suplementacion_usuario` (`id_usuario`),
  KEY `idx_suplementacion_nino` (`id_nino`),
  KEY `fk_suplementacion_anulado_por` (`anulado_por`),
  CONSTRAINT `fk_suplementacion_anulado_por` FOREIGN KEY (`anulado_por`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_suplementacion_nino` FOREIGN KEY (`id_nino`) REFERENCES `nino` (`id_nino`),
  CONSTRAINT `fk_suplementacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vacuna_aplicada` (
  `id_vacuna_aplicada` int(11) NOT NULL AUTO_INCREMENT,
  `id_nino` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_vacuna` varchar(80) NOT NULL,
  `dosis` varchar(30) NOT NULL,
  `fecha_aplicacion` date NOT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `lugar_vacunacion` tinyint(3) unsigned DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_anulacion` varchar(200) DEFAULT NULL,
  `referencia_conocimiento` varchar(60) DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_vacuna_aplicada`),
  KEY `fk_vacuna_usuario` (`id_usuario`),
  KEY `idx_vacuna_nino` (`id_nino`),
  KEY `fk_vacuna_anulado_por` (`anulado_por`),
  CONSTRAINT `fk_vacuna_anulado_por` FOREIGN KEY (`anulado_por`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_vacuna_nino` FOREIGN KEY (`id_nino`) REFERENCES `nino` (`id_nino`),
  CONSTRAINT `fk_vacuna_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cita` (
  `id_cita` int(11) NOT NULL AUTO_INCREMENT,
  `id_nino` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_programada` date NOT NULL,
  `estado` enum('programada','cumplida','inasistencia','reprogramada') NOT NULL DEFAULT 'programada',
  `aviso_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_asistencia` date DEFAULT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `observaciones` varchar(300) DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_anulacion` varchar(200) DEFAULT NULL,
  `referencia_conocimiento` varchar(60) DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_cita`),
  KEY `fk_cita_nino` (`id_nino`),
  KEY `idx_cita_fecha` (`fecha_programada`,`estado`),
  KEY `fk_cita_usuario` (`id_usuario`),
  KEY `fk_cita_anulado_por` (`anulado_por`),
  CONSTRAINT `fk_cita_anulado_por` FOREIGN KEY (`anulado_por`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_cita_nino` FOREIGN KEY (`id_nino`) REFERENCES `nino` (`id_nino`),
  CONSTRAINT `fk_cita_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `medicion` (
  `id_medicion` int(11) NOT NULL AUTO_INCREMENT,
  `id_nino` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_toma` date NOT NULL,
  `peso_kg` decimal(5,2) NOT NULL,
  `talla_cm` decimal(5,2) NOT NULL,
  `tipo_talla` enum('longitud','talla') NOT NULL,
  `edad_meses` int(11) NOT NULL,
  `observaciones` varchar(300) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_anulacion` varchar(200) DEFAULT NULL,
  `referencia_conocimiento` varchar(60) DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_medicion`),
  KEY `fk_medicion_usuario` (`id_usuario`),
  KEY `idx_medicion_nino_fecha` (`id_nino`,`fecha_toma`),
  KEY `fk_medicion_anulado_por` (`anulado_por`),
  CONSTRAINT `fk_medicion_anulado_por` FOREIGN KEY (`anulado_por`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `fk_medicion_nino` FOREIGN KEY (`id_nino`) REFERENCES `nino` (`id_nino`),
  CONSTRAINT `fk_medicion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `chk_medicion_peso` CHECK (`peso_kg` > 0 and `peso_kg` < 60),
  CONSTRAINT `chk_medicion_talla` CHECK (`talla_cm` > 0 and `talla_cm` < 200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `alerta` (
  `id_alerta` int(11) NOT NULL AUTO_INCREMENT,
  `id_nino` int(11) NOT NULL,
  `id_medicion` int(11) NOT NULL,
  `nivel` enum('normal','cerca_umbral','riesgo','desnutricion_aguda','desnutricion_cronica','sobrepeso') NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `estado` enum('activa','resuelta','seguimiento') NOT NULL DEFAULT 'activa',
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_alerta`),
  KEY `fk_alerta_medicion` (`id_medicion`),
  KEY `idx_alerta_nino_estado` (`id_nino`,`estado`),
  CONSTRAINT `fk_alerta_medicion` FOREIGN KEY (`id_medicion`) REFERENCES `medicion` (`id_medicion`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerta_nino` FOREIGN KEY (`id_nino`) REFERENCES `nino` (`id_nino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `indicador` (
  `id_indicador` int(11) NOT NULL AUTO_INCREMENT,
  `id_medicion` int(11) NOT NULL,
  `tipo` enum('peso_edad','talla_edad','peso_longitud','peso_talla') NOT NULL,
  `puntuacion_z` decimal(5,2) NOT NULL,
  `clasificacion` varchar(50) NOT NULL,
  `fecha_calculo` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_indicador`),
  UNIQUE KEY `uq_indicador_medicion_tipo` (`id_medicion`,`tipo`),
  CONSTRAINT `fk_indicador_medicion` FOREIGN KEY (`id_medicion`) REFERENCES `medicion` (`id_medicion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
