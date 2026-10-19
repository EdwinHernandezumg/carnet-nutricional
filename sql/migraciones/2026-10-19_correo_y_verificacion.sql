-- ============================================================================
-- Migración del 19 de octubre de 2026
--
-- Recuperación de contraseña por correo y código de ingreso desde un
-- dispositivo nuevo (RF-13 de la tesis, requisito del asesor).
--
--   usuario.correo         correo de cada persona (puede ser el personal: las
--                          enfermeras no tienen correo institucional)
--   codigo_acceso          enlaces de recuperación y códigos de ingreso. Se
--                          guarda solo el resumen SHA-256, nunca el código:
--                          quien lea la base no puede usarlos. Vencen, sirven
--                          una sola vez y cuentan los intentos fallidos.
--   dispositivo_confianza  teléfonos y computadoras donde ya se verificó el
--                          código; por 30 días no se vuelve a pedir en ese
--                          dispositivo (el administrador lo recibe siempre).
--
-- Se ejecuta una sola vez sobre una base ya instalada. Una instalación nueva
-- no la necesita: sql/esquema.sql ya trae estos cambios.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE usuario
    ADD COLUMN correo VARCHAR(150) NULL AFTER usuario;

CREATE TABLE codigo_acceso (
    id_codigo   INT NOT NULL AUTO_INCREMENT,
    id_usuario  INT NOT NULL,
    tipo        ENUM('recuperacion', 'ingreso') NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    creado      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence       DATETIME NOT NULL,
    usado       DATETIME NULL,
    intentos    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_codigo),
    KEY idx_codigo_usuario (id_usuario, tipo),
    KEY idx_codigo_hash (codigo_hash),
    CONSTRAINT fk_codigo_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE dispositivo_confianza (
    id_dispositivo INT NOT NULL AUTO_INCREMENT,
    id_usuario     INT NOT NULL,
    token_hash     CHAR(64) NOT NULL,
    descripcion    VARCHAR(150) NULL,
    creado         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence          DATETIME NOT NULL,
    PRIMARY KEY (id_dispositivo),
    UNIQUE KEY uk_dispositivo_token (token_hash),
    KEY idx_dispositivo_usuario (id_usuario),
    CONSTRAINT fk_dispositivo_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
