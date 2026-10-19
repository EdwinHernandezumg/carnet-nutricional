-- ============================================================================
-- Migración del 19 de octubre de 2026
--
-- Número de carnet del niño. En la visita del 16 de octubre de 2026 la jefa
-- del puesto explicó que la columna "No. Historia clínica" del SIGSA 3 se
-- llena con el número de carnet del niño. Con este campo, la hoja del SIGSA 3
-- lo imprime sola y se puede buscar al niño por ese número.
--
-- Es opcional (los niños ya registrados no lo tienen todavía) y no se repite
-- dentro del mismo puesto de salud (se valida en el servidor).
--
-- Se ejecuta una sola vez sobre una base ya instalada. Una instalación nueva
-- no la necesita: sql/esquema.sql ya trae la columna.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE nino
    ADD COLUMN numero_carnet VARCHAR(20) NULL AFTER cui,
    ADD INDEX idx_nino_numero_carnet (numero_carnet);
