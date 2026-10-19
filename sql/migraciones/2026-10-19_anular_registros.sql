-- ============================================================================
-- Migración del 19 de octubre de 2026
--
-- Anular registros con motivo, nunca borrarlos. Lo pidió el personal del
-- puesto en la visita del 16 de octubre de 2026 ("¿qué pasa si me
-- equivoco?") y sigue su forma de trabajar: en el carnet se tacha con línea
-- roja; en el cuaderno SIGSA 5a se hace un "conocimiento" con el motivo; en
-- SIGSA Web se corrige libremente durante el mes y después con un
-- conocimiento.
--
-- En las mediciones, vacunas, suplementos y citas se agrega:
--   anulado                 1 si se anuló; deja de contar en cálculos y reportes
--   motivo_anulacion        por qué se anuló
--   referencia_conocimiento número o referencia del conocimiento, obligatoria
--                           cuando la atención es de un mes anterior
--   anulado_por, fecha_anulacion  quién y cuándo
-- Las correcciones (vacuna, suplemento y cita) quedan en la bitácora con el
-- valor anterior, el nuevo, el motivo y la referencia.
--
-- Se ejecuta una sola vez sobre una base ya instalada (en el servidor, desde
-- la pestaña SQL de phpMyAdmin o con la consola). Una instalación nueva no la
-- necesita: sql/esquema.sql ya trae estas columnas.
-- ============================================================================

ALTER TABLE medicion
    ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN motivo_anulacion VARCHAR(200) NULL,
    ADD COLUMN referencia_conocimiento VARCHAR(60) NULL,
    ADD COLUMN anulado_por INT NULL,
    ADD COLUMN fecha_anulacion DATETIME NULL,
    ADD CONSTRAINT fk_medicion_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuario (id_usuario);

ALTER TABLE vacuna_aplicada
    ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN motivo_anulacion VARCHAR(200) NULL,
    ADD COLUMN referencia_conocimiento VARCHAR(60) NULL,
    ADD COLUMN anulado_por INT NULL,
    ADD COLUMN fecha_anulacion DATETIME NULL,
    ADD CONSTRAINT fk_vacuna_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuario (id_usuario);

ALTER TABLE suplementacion
    ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN motivo_anulacion VARCHAR(200) NULL,
    ADD COLUMN referencia_conocimiento VARCHAR(60) NULL,
    ADD COLUMN anulado_por INT NULL,
    ADD COLUMN fecha_anulacion DATETIME NULL,
    ADD CONSTRAINT fk_suplementacion_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuario (id_usuario);

ALTER TABLE cita
    ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN motivo_anulacion VARCHAR(200) NULL,
    ADD COLUMN referencia_conocimiento VARCHAR(60) NULL,
    ADD COLUMN anulado_por INT NULL,
    ADD COLUMN fecha_anulacion DATETIME NULL,
    ADD CONSTRAINT fk_cita_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuario (id_usuario);
