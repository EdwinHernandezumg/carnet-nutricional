-- ============================================================================
-- Migración del 19 de octubre de 2026
--
-- Catálogo de suplementos editable desde el sistema. En la visita del 16 de
-- octubre de 2026 la jefa del puesto informó que ya no se entrega hierro y
-- que se agregó el suplemento nutricional lipídico (SNL), y pidió poder
-- agregar y quitar suplementos sin depender del desarrollador.
--
-- En catalogo_suplemento se agregan:
--   indicacion        dosis y frecuencia, por ejemplo "1 sobre al día"
--   edad_desde_meses  edad a la que se empieza a dar (opcional)
--   edad_hasta_meses  última edad a la que se da (opcional)
-- Con la edad, el formulario de entrega avisa si el niño está fuera de ese
-- rango, sin impedir el registro.
--
-- suplementacion.tipo guarda el nombre del suplemento: pasa de 50 a 80
-- caracteres, el mismo largo del nombre en el catálogo.
--
-- Un suplemento nunca se borra: se desactiva, deja de aparecer en el
-- formulario y las entregas ya registradas se conservan.
--
-- Se ejecuta una sola vez sobre una base ya instalada. Una instalación nueva
-- no la necesita: sql/esquema.sql y sql/datos_iniciales.sql ya lo traen.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE catalogo_suplemento
    ADD COLUMN indicacion VARCHAR(100) NULL AFTER presentacion,
    ADD COLUMN edad_desde_meses TINYINT UNSIGNED NULL AFTER indicacion,
    ADD COLUMN edad_hasta_meses TINYINT UNSIGNED NULL AFTER edad_desde_meses;

ALTER TABLE suplementacion
    MODIFY tipo VARCHAR(80) NOT NULL;

-- Ya no se entrega hierro (visita del 16 de octubre de 2026).
UPDATE catalogo_suplemento SET activo = 0
WHERE nombre = 'Hierro (sulfato ferroso)';

-- Suplemento nutricional lipídico: un sobre de 20 g al día, de 6 a 23 meses,
-- entrega mensual. Ocupa en la lista el lugar que tenía el hierro.
INSERT INTO catalogo_suplemento
    (nombre, presentacion, indicacion, edad_desde_meses, edad_hasta_meses, orden, activo)
VALUES
    ('Suplemento nutricional lipídico (SNL)', 'Sobre de 20 g', '1 sobre al día; entrega mensual', 6, 23, 2, 1);
