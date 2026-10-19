-- ============================================================================
-- Carnet Digital de Monitoreo Nutricional Infantil
-- Datos iniciales: roles, estructura territorial y catálogos
--
-- Puesto de Salud de Chivarreto: 2 territorios, 7 sectores y 29 parajes. Esquema de vacunación del cuaderno SIGSA 5a 2026.
--
-- Generado el 19-10-2026 10:52 desde la base local con:
--   php scripts/exportar_esquema.php
-- No editar a mano: cambiar la base local y volver a generarlo.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- rol (3 filas)
INSERT INTO `rol` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'administrador', 'Gestión de usuarios y consulta de reportes. No captura datos clínicos.'),
(2, 'enfermeria', 'Registra y modifica información clínica de su sector asignado.'),
(3, 'consulta', 'Acceso de solo lectura a la información clínica.');

-- puesto_salud (1 fila)
INSERT INTO `puesto_salud` (`id_puesto`, `nombre`, `codigo`, `distrito`, `area_salud`, `activo`) VALUES
(1, 'Puesto de Salud Chivarreto', 'PS-CHIV', 'San Francisco El Alto', 'Totonicapán', 1);

-- territorio (2 filas)
INSERT INTO `territorio` (`id_territorio`, `id_puesto`, `numero`, `nombre`) VALUES
(1, 1, '8', 'Puesto de Salud del Centro'),
(2, 1, '9', 'Centro Comunitario Cuesta del Aire');

-- sector (7 filas)
INSERT INTO `sector` (`id_sector`, `id_territorio`, `letra`, `descripcion`) VALUES
(1, 1, 'A', 'Sector A - Territorio 8'),
(2, 1, 'B', 'Sector B - Territorio 8'),
(3, 1, 'C', 'Sector C - Territorio 8'),
(4, 1, 'D', 'Sector D - Territorio 8'),
(5, 2, 'A', 'Sector A - Territorio 9'),
(6, 2, 'B', 'Sector B - Territorio 9'),
(7, 2, 'C', 'Sector C - Territorio 9');

-- paraje (29 filas)
INSERT INTO `paraje` (`id_paraje`, `id_sector`, `nombre`, `activo`) VALUES
(1, 1, 'Chimaldonado', 1),
(2, 1, 'Xecorral', 1),
(3, 1, 'Chi Alvarado', 1),
(4, 1, 'Chi Rojop', 1),
(5, 1, 'Chi Alvarez', 1),
(6, 1, 'Xetux', 1),
(7, 1, 'Cho Juyup', 1),
(8, 2, 'Chi Potrer', 1),
(9, 2, 'Pasacaquim', 1),
(10, 3, 'Chi Perez', 1),
(11, 3, 'Chi Oxlaj', 1),
(12, 3, 'Chi Gonzalez', 1),
(13, 4, 'Chi Lopez', 1),
(14, 4, 'Chi Hernández', 1),
(15, 4, 'Cho Caja', 1),
(16, 4, 'Chui Caja', 1),
(17, 4, 'Xe Caja', 1),
(18, 5, 'Chiventur', 1),
(19, 5, 'Pachaj', 1),
(20, 5, 'Chi Ordoñez', 1),
(21, 5, 'Chi Calles', 1),
(22, 5, 'Cho Tz\'inchaj', 1),
(23, 5, 'Chi Chaj', 1),
(24, 6, 'Cuesta del Aire', 1),
(25, 6, 'Xetrubala', 1),
(26, 6, 'Chi Herrera', 1),
(27, 7, 'Tzanjuyup', 1),
(28, 7, 'Chi Chavez', 1),
(29, 7, 'Chi Ajtij', 1);

-- catalogo_suplemento (7 filas)
INSERT INTO `catalogo_suplemento` (`id_catalogo_suplemento`, `nombre`, `presentacion`, `indicacion`, `edad_desde_meses`, `edad_hasta_meses`, `orden`, `activo`) VALUES
(1, 'Vitamina A', 'Cápsula', NULL, NULL, NULL, 1, 1),
(2, 'Hierro (sulfato ferroso)', 'Jarabe o tableta', NULL, NULL, NULL, 2, 0),
(3, 'Micronutrientes espolvoreados', 'Sobres', NULL, NULL, NULL, 3, 1),
(4, 'Zinc', 'Jarabe o tableta', NULL, NULL, NULL, 4, 1),
(5, 'Desparasitante (albendazol)', 'Tableta', NULL, NULL, NULL, 5, 1),
(6, 'Alimento complementario fortificado', 'Bolsa', NULL, NULL, NULL, 6, 1),
(7, 'Suplemento nutricional lipídico (SNL)', 'Sobre de 20 g', '1 sobre al día; entrega mensual', 6, 23, 2, 1);

-- catalogo_vacuna (30 filas)
INSERT INTO `catalogo_vacuna` (`id_catalogo_vacuna`, `nombre`, `dosis`, `edad_sugerida`, `edad_meses`, `vigente`, `equivale_a`, `orden`, `activo`) VALUES
(1, 'Hepatitis B', 'Única', 'Primeras 24 horas de vida', 0, 1, NULL, 1, 1),
(2, 'BCG', 'Única', 'Al nacer, antes de 1 año', 0, 1, NULL, 2, 1),
(3, 'Hexavalente', '1a', '2 meses', 2, 1, NULL, 3, 1),
(4, 'Neumococo', '1a', '2 meses', 2, 1, NULL, 4, 1),
(5, 'Rotavirus', '1a', '2 meses', 2, 1, NULL, 5, 1),
(6, 'Hexavalente', '2a', '4 meses', 4, 1, NULL, 6, 1),
(7, 'OPV', '2a', '4 meses', 4, 1, NULL, 7, 1),
(8, 'Neumococo', '2a', '4 meses', 4, 1, NULL, 8, 1),
(9, 'Rotavirus', '2a', '4 meses', 4, 1, NULL, 9, 1),
(10, 'Hexavalente', '3a', '6 meses', 6, 1, NULL, 10, 1),
(11, 'Influenza estacional', '1a', '6 meses', 6, 1, NULL, 11, 1),
(12, 'Influenza estacional', '2a', '7 meses', 7, 1, NULL, 12, 1),
(13, 'SPR', '1a', '12 meses', 12, 1, NULL, 13, 1),
(14, 'Neumococo', 'Refuerzo', '12 meses', 12, 1, NULL, 14, 1),
(15, 'Varicela', '1a', '15 meses', 15, 1, NULL, 15, 1),
(16, 'Influenza estacional', 'Refuerzo 1', '18 meses', 18, 1, NULL, 16, 1),
(17, 'SPR', '2a', '18 meses', 18, 1, NULL, 17, 1),
(18, 'Hexavalente', 'Refuerzo 1', '18 meses', 18, 1, NULL, 18, 1),
(19, 'OPV', 'Refuerzo 1', '18 meses', 18, 1, NULL, 19, 1),
(20, 'Influenza estacional', 'Refuerzo 2', '2 años', 24, 1, NULL, 20, 1),
(21, 'OPV', 'Refuerzo 2', '4 años', 48, 1, NULL, 21, 1),
(22, 'DPT', 'Refuerzo 2', '4 años', 48, 1, NULL, 22, 1),
(23, 'Varicela', 'Refuerzo', '4 años', 48, 1, NULL, 23, 1),
(24, 'Pentavalente', '1a', '2 meses', 2, 0, 3, 24, 1),
(25, 'Pentavalente', '2a', '4 meses', 4, 0, 6, 25, 1),
(26, 'Pentavalente', '3a', '6 meses', 6, 0, 10, 26, 1),
(27, 'Polio (fIPV)', '1a', '2 meses', 2, 0, NULL, 27, 1),
(28, 'Polio (fIPV)', '2a', '4 meses', 4, 0, 7, 28, 1),
(29, 'Polio (OPV)', '3a', '6 meses', 6, 0, NULL, 29, 1),
(30, 'DPT', 'Refuerzo 1', '18 meses', 18, 0, 18, 30, 1);

SET FOREIGN_KEY_CHECKS = 1;
