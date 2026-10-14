-- ============================================================================
-- Migración del 14 de octubre de 2026
--
-- 1. Esquema nacional de vacunación vigente, tomado de la portada del cuaderno
--    SIGSA 5a versión 2026 (MSPAS, docs/formatos-sigsa/). El catálogo anterior
--    era el del carnet de 2018: Pentavalente y Polio por separado, sin
--    Hexavalente ni Varicela. Las dosis del esquema anterior se conservan
--    marcadas como no vigentes, para poder registrar las que ya trae el carnet
--    físico de los niños mayores. "equivale_a" indica la dosis del esquema
--    vigente que cubren (por validar con el personal del puesto).
-- 2. Lugar de vacunación con el código LV del SIGSA 5a (antes, texto libre).
-- 3. Pueblo y comunidad lingüística con los códigos del SIGSA 3 y 5a (antes,
--    un solo campo "etnia" de texto libre).
--
-- Se ejecuta una sola vez sobre la base anterior. Los nombres de los códigos
-- están en src/core/Catalogo.php y src/carnet/Vacuna.php.
-- ============================================================================

-- ---------- 1. Catálogo de vacunas ----------

ALTER TABLE catalogo_vacuna
    ADD COLUMN edad_meses SMALLINT UNSIGNED NULL AFTER edad_sugerida,
    ADD COLUMN vigente TINYINT(1) NOT NULL DEFAULT 1 AFTER edad_meses,
    ADD COLUMN equivale_a INT NULL AFTER vigente;

DELETE FROM catalogo_vacuna;

INSERT INTO catalogo_vacuna
    (id_catalogo_vacuna, nombre, dosis, edad_sugerida, edad_meses, vigente, equivale_a, orden, activo)
VALUES
    -- Esquema nacional vigente (cuaderno SIGSA 5a 2026)
    ( 1, 'Hepatitis B',          'Única',      'Primeras 24 horas de vida', 0,  1, NULL,  1, 1),
    ( 2, 'BCG',                  'Única',      'Al nacer, antes de 1 año',  0,  1, NULL,  2, 1),
    ( 3, 'Hexavalente',          '1a',         '2 meses',                   2,  1, NULL,  3, 1),
    ( 4, 'Neumococo',            '1a',         '2 meses',                   2,  1, NULL,  4, 1),
    ( 5, 'Rotavirus',            '1a',         '2 meses',                   2,  1, NULL,  5, 1),
    ( 6, 'Hexavalente',          '2a',         '4 meses',                   4,  1, NULL,  6, 1),
    ( 7, 'OPV',                  '2a',         '4 meses',                   4,  1, NULL,  7, 1),
    ( 8, 'Neumococo',            '2a',         '4 meses',                   4,  1, NULL,  8, 1),
    ( 9, 'Rotavirus',            '2a',         '4 meses',                   4,  1, NULL,  9, 1),
    (10, 'Hexavalente',          '3a',         '6 meses',                   6,  1, NULL, 10, 1),
    (11, 'Influenza estacional', '1a',         '6 meses',                   6,  1, NULL, 11, 1),
    (12, 'Influenza estacional', '2a',         '7 meses',                   7,  1, NULL, 12, 1),
    (13, 'SPR',                  '1a',         '12 meses',                  12, 1, NULL, 13, 1),
    (14, 'Neumococo',            'Refuerzo',   '12 meses',                  12, 1, NULL, 14, 1),
    (15, 'Varicela',             '1a',         '15 meses',                  15, 1, NULL, 15, 1),
    (16, 'Influenza estacional', 'Refuerzo 1', '18 meses',                  18, 1, NULL, 16, 1),
    (17, 'SPR',                  '2a',         '18 meses',                  18, 1, NULL, 17, 1),
    (18, 'Hexavalente',          'Refuerzo 1', '18 meses',                  18, 1, NULL, 18, 1),
    (19, 'OPV',                  'Refuerzo 1', '18 meses',                  18, 1, NULL, 19, 1),
    (20, 'Influenza estacional', 'Refuerzo 2', '2 años',                    24, 1, NULL, 20, 1),
    (21, 'OPV',                  'Refuerzo 2', '4 años',                    48, 1, NULL, 21, 1),
    (22, 'DPT',                  'Refuerzo 2', '4 años',                    48, 1, NULL, 22, 1),
    (23, 'Varicela',             'Refuerzo',   '4 años',                    48, 1, NULL, 23, 1),
    -- Esquema anterior (carnet de 2018): solo para dosis ya aplicadas
    (24, 'Pentavalente',         '1a',         '2 meses',                   2,  0, 3,    24, 1),
    (25, 'Pentavalente',         '2a',         '4 meses',                   4,  0, 6,    25, 1),
    (26, 'Pentavalente',         '3a',         '6 meses',                   6,  0, 10,   26, 1),
    (27, 'Polio (fIPV)',         '1a',         '2 meses',                   2,  0, NULL, 27, 1),
    (28, 'Polio (fIPV)',         '2a',         '4 meses',                   4,  0, 7,    28, 1),
    (29, 'Polio (OPV)',          '3a',         '6 meses',                   6,  0, NULL, 29, 1),
    (30, 'DPT',                  'Refuerzo 1', '18 meses',                  18, 0, 18,   30, 1);

ALTER TABLE catalogo_vacuna
    AUTO_INCREMENT = 31,
    ADD CONSTRAINT fk_vacuna_equivale FOREIGN KEY (equivale_a)
        REFERENCES catalogo_vacuna (id_catalogo_vacuna);

-- Dosis ya registradas con los nombres del catálogo anterior.
UPDATE vacuna_aplicada SET nombre_vacuna = 'Polio (fIPV)'
    WHERE nombre_vacuna = 'Polio (IPV/OPV)' AND dosis IN ('1a', '2a');
UPDATE vacuna_aplicada SET nombre_vacuna = 'Polio (OPV)'
    WHERE nombre_vacuna = 'Polio (IPV/OPV)' AND dosis = '3a';
UPDATE vacuna_aplicada SET nombre_vacuna = 'OPV'
    WHERE nombre_vacuna = 'Polio (OPV)' AND dosis IN ('Refuerzo 1', 'Refuerzo 2');
UPDATE vacuna_aplicada SET dosis = 'Única'
    WHERE nombre_vacuna = 'Hepatitis B' AND dosis = 'Recién nacido';

-- ---------- 2. Lugar de vacunación (código LV del SIGSA 5a) ----------

ALTER TABLE vacuna_aplicada
    ADD COLUMN lugar_vacunacion TINYINT UNSIGNED NULL AFTER lote;

UPDATE vacuna_aplicada SET lugar_vacunacion = 1
    WHERE LOWER(lugar) REGEXP 'puesto|centro|establecimiento|^pc ';

ALTER TABLE vacuna_aplicada DROP COLUMN lugar;

-- ---------- 3. Pueblo y comunidad lingüística (SIGSA 3 y 5a) ----------

ALTER TABLE nino
    ADD COLUMN pueblo TINYINT UNSIGNED NULL AFTER sexo,
    ADD COLUMN comunidad_linguistica TINYINT UNSIGNED NULL AFTER pueblo;

UPDATE nino SET pueblo = 1 WHERE LOWER(etnia) REGEXP 'mestizo|ladino';
UPDATE nino SET pueblo = 2 WHERE LOWER(etnia) REGEXP 'maya|k.?iche';
UPDATE nino SET comunidad_linguistica = 11 WHERE LOWER(etnia) REGEXP 'k.?iche';

ALTER TABLE nino DROP COLUMN etnia;
