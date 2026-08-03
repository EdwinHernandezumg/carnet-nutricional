-- =====================================================
-- Sistema de monitoreo nutricional infantil
-- Puesto de salud de Chivarreto, Totonicapán
-- Esquema completo + datos iniciales
-- =====================================================

CREATE DATABASE IF NOT EXISTS carnet_nutricional
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carnet_nutricional;

-- -----------------------------------------------------
-- Estructura territorial
-- -----------------------------------------------------

CREATE TABLE puesto_salud (
  id_puesto  INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(100) NOT NULL,
  codigo     VARCHAR(20)  NOT NULL UNIQUE,
  distrito   VARCHAR(100),
  area_salud VARCHAR(100),
  activo     BOOLEAN      NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE territorio (
  id_territorio INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto     INT          NOT NULL,
  numero        VARCHAR(10)  NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  CONSTRAINT fk_territorio_puesto
    FOREIGN KEY (id_puesto) REFERENCES puesto_salud(id_puesto)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sector (
  id_sector     INT AUTO_INCREMENT PRIMARY KEY,
  id_territorio INT        NOT NULL,
  letra         VARCHAR(5) NOT NULL,
  descripcion   VARCHAR(100),
  CONSTRAINT fk_sector_territorio
    FOREIGN KEY (id_territorio) REFERENCES territorio(id_territorio)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE paraje (
  id_paraje INT AUTO_INCREMENT PRIMARY KEY,
  id_sector INT          NOT NULL,
  nombre    VARCHAR(100) NOT NULL,
  activo    BOOLEAN      NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_paraje_sector
    FOREIGN KEY (id_sector) REFERENCES sector(id_sector) ON DELETE RESTRICT,
  INDEX idx_paraje_sector (id_sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Usuarios y roles
-- -----------------------------------------------------

CREATE TABLE rol (
  id_rol      INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuario (
  id_usuario      INT AUTO_INCREMENT PRIMARY KEY,
  id_rol          INT          NOT NULL,
  id_puesto       INT          NOT NULL,
  id_sector       INT          NULL,
  nombre_completo VARCHAR(150) NOT NULL,
  usuario         VARCHAR(50)  NOT NULL UNIQUE,
  contrasena_hash VARCHAR(255) NOT NULL,
  activo          BOOLEAN      NOT NULL DEFAULT TRUE,
  fecha_alta      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_baja      DATETIME     NULL,
  CONSTRAINT fk_usuario_rol
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE RESTRICT,
  CONSTRAINT fk_usuario_puesto
    FOREIGN KEY (id_puesto) REFERENCES puesto_salud(id_puesto) ON DELETE RESTRICT,
  CONSTRAINT fk_usuario_sector
    FOREIGN KEY (id_sector) REFERENCES sector(id_sector) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Niños
-- -----------------------------------------------------

CREATE TABLE nino (
  id_nino          INT AUTO_INCREMENT PRIMARY KEY,
  id_sector        INT          NOT NULL,
  id_paraje        INT          NULL,
  cui              VARCHAR(13)  NULL,
  nombres          VARCHAR(100) NOT NULL,
  apellidos        VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE         NOT NULL,
  sexo             ENUM('M','F') NOT NULL,
  etnia            VARCHAR(50),
  nombre_madre     VARCHAR(150),
  nombre_padre     VARCHAR(150),
  telefono         VARCHAR(20),
  direccion        VARCHAR(200),
  estado           ENUM('activo','egresado','traslado') NOT NULL DEFAULT 'activo',
  fecha_egreso     DATE         NULL,
  fecha_registro   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_nino_sector
    FOREIGN KEY (id_sector) REFERENCES sector(id_sector) ON DELETE RESTRICT,
  CONSTRAINT fk_nino_paraje
    FOREIGN KEY (id_paraje) REFERENCES paraje(id_paraje) ON DELETE RESTRICT,
  INDEX idx_nino_cui    (cui),
  INDEX idx_nino_estado (estado),
  INDEX idx_nino_nombre (apellidos, nombres)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Mediciones e indicadores
-- -----------------------------------------------------

CREATE TABLE medicion (
  id_medicion    INT AUTO_INCREMENT PRIMARY KEY,
  id_nino        INT          NOT NULL,
  id_usuario     INT          NOT NULL,
  fecha_toma     DATE         NOT NULL,
  peso_kg        DECIMAL(5,2) NOT NULL,
  talla_cm       DECIMAL(5,2) NOT NULL,
  tipo_talla     ENUM('longitud','talla') NOT NULL,
  edad_meses     INT          NOT NULL,
  observaciones  VARCHAR(300),
  fecha_registro DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_medicion_nino
    FOREIGN KEY (id_nino) REFERENCES nino(id_nino) ON DELETE RESTRICT,
  CONSTRAINT fk_medicion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  CONSTRAINT chk_medicion_peso  CHECK (peso_kg  > 0 AND peso_kg  < 60),
  CONSTRAINT chk_medicion_talla CHECK (talla_cm > 0 AND talla_cm < 200),
  INDEX idx_medicion_nino_fecha (id_nino, fecha_toma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE indicador (
  id_indicador  INT AUTO_INCREMENT PRIMARY KEY,
  id_medicion   INT          NOT NULL,
  tipo          ENUM('peso_edad','talla_edad','peso_talla') NOT NULL,
  puntuacion_z  DECIMAL(5,2) NOT NULL,
  clasificacion VARCHAR(50)  NOT NULL,
  fecha_calculo DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_indicador_medicion
    FOREIGN KEY (id_medicion) REFERENCES medicion(id_medicion) ON DELETE CASCADE,
  UNIQUE KEY uq_indicador_medicion_tipo (id_medicion, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Alertas
-- -----------------------------------------------------

CREATE TABLE alerta (
  id_alerta        INT AUTO_INCREMENT PRIMARY KEY,
  id_nino          INT          NOT NULL,
  id_medicion      INT          NOT NULL,
  nivel            ENUM('normal','cerca_umbral','riesgo','desnutricion_aguda','desnutricion_cronica','sobrepeso') NOT NULL,
  descripcion      VARCHAR(200) NOT NULL,
  estado           ENUM('activa','resuelta','seguimiento') NOT NULL DEFAULT 'activa',
  fecha_generacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion DATETIME     NULL,
  CONSTRAINT fk_alerta_nino
    FOREIGN KEY (id_nino) REFERENCES nino(id_nino) ON DELETE RESTRICT,
  CONSTRAINT fk_alerta_medicion
    FOREIGN KEY (id_medicion) REFERENCES medicion(id_medicion) ON DELETE CASCADE,
  INDEX idx_alerta_nino_estado (id_nino, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Vacunación, suplementación y citas
-- -----------------------------------------------------

CREATE TABLE vacuna_aplicada (
  id_vacuna_aplicada INT AUTO_INCREMENT PRIMARY KEY,
  id_nino            INT         NOT NULL,
  id_usuario         INT         NOT NULL,
  nombre_vacuna      VARCHAR(80) NOT NULL,
  dosis              VARCHAR(30) NOT NULL,
  fecha_aplicacion   DATE        NOT NULL,
  lote               VARCHAR(50),
  lugar              VARCHAR(100),
  CONSTRAINT fk_vacuna_nino
    FOREIGN KEY (id_nino) REFERENCES nino(id_nino) ON DELETE RESTRICT,
  CONSTRAINT fk_vacuna_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  INDEX idx_vacuna_nino (id_nino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suplementacion (
  id_suplementacion INT AUTO_INCREMENT PRIMARY KEY,
  id_nino           INT         NOT NULL,
  id_usuario        INT         NOT NULL,
  tipo              VARCHAR(50) NOT NULL,
  fecha_entrega     DATE        NOT NULL,
  cantidad          VARCHAR(50),
  CONSTRAINT fk_suplementacion_nino
    FOREIGN KEY (id_nino) REFERENCES nino(id_nino) ON DELETE RESTRICT,
  CONSTRAINT fk_suplementacion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  INDEX idx_suplementacion_nino (id_nino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cita (
  id_cita          INT AUTO_INCREMENT PRIMARY KEY,
  id_nino          INT         NOT NULL,
  fecha_programada DATE        NOT NULL,
  estado           ENUM('programada','cumplida','inasistencia','reprogramada') NOT NULL DEFAULT 'programada',
  fecha_asistencia DATE        NULL,
  motivo           VARCHAR(100),
  CONSTRAINT fk_cita_nino
    FOREIGN KEY (id_nino) REFERENCES nino(id_nino) ON DELETE RESTRICT,
  INDEX idx_cita_fecha (fecha_programada, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Bitácora de auditoría
-- -----------------------------------------------------

CREATE TABLE bitacora (
  id_bitacora          INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario           INT         NOT NULL,
  accion               VARCHAR(50) NOT NULL,
  tabla_afectada       VARCHAR(50) NOT NULL,
  id_registro_afectado INT         NULL,
  detalle              VARCHAR(300),
  fecha_hora           DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_origen            VARCHAR(45),
  CONSTRAINT fk_bitacora_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
  INDEX idx_bitacora_fecha   (fecha_hora),
  INDEX idx_bitacora_usuario (id_usuario, fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

INSERT INTO rol (nombre, descripcion) VALUES
('administrador', 'Gestión de usuarios y consulta de reportes. No captura datos clínicos.'),
('enfermeria',    'Registra y modifica información clínica de su sector asignado.'),
('consulta',      'Acceso de solo lectura a la información clínica.');

INSERT INTO puesto_salud (nombre, codigo, distrito, area_salud) VALUES
('Puesto de Salud Chivarreto', 'PS-CHIV', 'San Francisco El Alto', 'Totonicapán');

INSERT INTO territorio (id_puesto, numero, nombre) VALUES
(1, '8', 'Puesto de Salud del Centro'),
(1, '9', 'Centro Comunitario Cuesta del Aire');

INSERT INTO sector (id_territorio, letra, descripcion) VALUES
(1, 'A', 'Sector A - Territorio 8'),
(1, 'B', 'Sector B - Territorio 8'),
(1, 'C', 'Sector C - Territorio 8'),
(1, 'D', 'Sector D - Territorio 8'),
(2, 'A', 'Sector A - Territorio 9'),
(2, 'B', 'Sector B - Territorio 9'),
(2, 'C', 'Sector C - Territorio 9');

INSERT INTO paraje (id_sector, nombre) VALUES
(1, 'Chimaldonado'),
(1, 'Xecorral'),
(1, 'Chi Alvarado'),
(1, 'Chi Rojop'),
(1, 'Chi Alvarez'),
(1, 'Xetux'),
(1, 'Cho Juyup'),
(2, 'Chi Potrer'),
(2, 'Pasacaquim'),
(3, 'Chi Perez'),
(3, 'Chi Oxlaj'),
(3, 'Chi Gonzalez'),
(4, 'Chi Lopez'),
(4, 'Chi Hernández'),
(4, 'Cho Caja'),
(4, 'Chui Caja'),
(4, 'Xe Caja'),
(5, 'Chiventur'),
(5, 'Pachaj'),
(5, 'Chi Ordoñez'),
(5, 'Chi Calles'),
(5, 'Cho Tz''inchaj'),
(5, 'Chi Chaj'),
(6, 'Cuesta del Aire'),
(6, 'Xetrubala'),
(6, 'Chi Herrera'),
(7, 'Tzanjuyup'),
(7, 'Chi Chavez'),
(7, 'Chi Ajtij');