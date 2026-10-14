<?php
/**
 * Genera los archivos SQL de instalación a partir de la base local, que es la
 * fuente de verdad del sistema:
 *
 *   sql/esquema.sql           estructura de todas las tablas, sin datos
 *   sql/datos_iniciales.sql   roles, puesto, territorios, sectores, parajes y
 *                             catálogos de vacunas y suplementos
 *   sql/oms_lms.sql           las 728 filas de L, M y S de la OMS
 *
 * Se vuelve a ejecutar cada vez que cambia la estructura de la base o algún
 * catálogo, para que la instalación en la nube quede igual a la local:
 *   php scripts/exportar_esquema.php
 *
 * No exporta niños, mediciones, usuarios ni bitácora: esos datos nunca salen
 * de la base donde se registraron.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Database.php';

$db = Database::obtener();
$carpeta = __DIR__ . '/../sql/';

// Tablas de catálogo que forman los datos iniciales, en orden de dependencia.
$catalogos = ['rol', 'puesto_salud', 'territorio', 'sector', 'paraje', 'catalogo_suplemento', 'catalogo_vacuna'];

/** Encabezado común de los archivos generados. */
function encabezado(string $titulo, string $detalle): string
{
    return "-- ============================================================================\n"
         . "-- Carnet Digital de Monitoreo Nutricional Infantil\n"
         . "-- $titulo\n"
         . "--\n"
         . "-- $detalle\n"
         . "--\n"
         . "-- Generado el " . date('d-m-Y H:i') . " desde la base local con:\n"
         . "--   php scripts/exportar_esquema.php\n"
         . "-- No editar a mano: cambiar la base local y volver a generarlo.\n"
         . "-- ============================================================================\n\n"
         . "SET NAMES utf8mb4;\n"
         . "SET FOREIGN_KEY_CHECKS = 0;\n\n";
}

/** Valor listo para un INSERT: NULL, número tal cual o texto entre comillas. */
function valorSql(PDO $db, $valor): string
{
    if ($valor === null) {
        return 'NULL';
    }
    if (is_int($valor) || is_float($valor)) {
        return (string)$valor;
    }
    return $db->quote((string)$valor);
}

/** INSERT de todas las filas de una tabla, en bloques de 100 filas. */
function insertsDeTabla(PDO $db, string $tabla): string
{
    $filas = $db->query("SELECT * FROM `$tabla` ORDER BY 1")->fetchAll();
    if (!$filas) {
        return "-- $tabla: sin filas\n\n";
    }
    $columnas = '`' . implode('`, `', array_keys($filas[0])) . '`';
    $sql = "-- $tabla (" . count($filas) . (count($filas) === 1 ? ' fila' : ' filas') . ")\n";
    foreach (array_chunk($filas, 100) as $bloque) {
        $valores = array_map(
            fn($f) => '(' . implode(', ', array_map(fn($v) => valorSql($db, $v), $f)) . ')',
            $bloque
        );
        $sql .= "INSERT INTO `$tabla` ($columnas) VALUES\n" . implode(",\n", $valores) . ";\n";
    }
    return $sql . "\n";
}

// ---------- Orden de las tablas: primero las que otras referencian ----------
$tablas = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                      ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$dependencias = array_fill_keys($tablas, []);
foreach ($db->query("SELECT DISTINCT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
                       AND REFERENCED_TABLE_NAME <> TABLE_NAME")->fetchAll() as $r) {
    $dependencias[$r['TABLE_NAME']][] = $r['REFERENCED_TABLE_NAME'];
}
$ordenadas = [];
while (count($ordenadas) < count($tablas)) {
    $avance = false;
    foreach ($tablas as $t) {
        if (!in_array($t, $ordenadas, true) && !array_diff($dependencias[$t], $ordenadas)) {
            $ordenadas[] = $t;
            $avance = true;
        }
    }
    if (!$avance) {
        exit("Hay una referencia circular entre tablas; revisar las llaves foráneas.\n");
    }
}

// ---------- sql/esquema.sql ----------
$esquema = encabezado('Estructura de la base de datos (' . count($ordenadas) . ' tablas)',
    'Se importa en una base vacía, antes de datos_iniciales.sql y oms_lms.sql.');
foreach ($ordenadas as $t) {
    $crear = $db->query("SHOW CREATE TABLE `$t`")->fetch()['Create Table'];
    // El contador AUTO_INCREMENT de la base local no se copia: la instalación empieza en 1.
    $esquema .= preg_replace('/ AUTO_INCREMENT=\d+/', '', $crear) . ";\n\n";
}
file_put_contents($carpeta . 'esquema.sql', $esquema . "SET FOREIGN_KEY_CHECKS = 1;\n");

// ---------- sql/datos_iniciales.sql ----------
$datos = encabezado('Datos iniciales: roles, estructura territorial y catálogos',
    'Puesto de Salud de Chivarreto: 2 territorios, 7 sectores y 29 parajes. Esquema de vacunación del cuaderno SIGSA 5a 2026.');
foreach ($catalogos as $t) {
    $datos .= insertsDeTabla($db, $t);
}
file_put_contents($carpeta . 'datos_iniciales.sql', $datos . "SET FOREIGN_KEY_CHECKS = 1;\n");

// ---------- sql/oms_lms.sql ----------
$oms = encabezado('Patrones de crecimiento de la OMS: valores L, M y S',
    'Tablas oficiales de la OMS (2006) para peso para la edad, talla para la edad, peso para la longitud y peso para la talla.');
file_put_contents($carpeta . 'oms_lms.sql', $oms . insertsDeTabla($db, 'oms_lms') . "SET FOREIGN_KEY_CHECKS = 1;\n");

echo "Generados en sql/:\n";
echo "  esquema.sql          " . count($ordenadas) . " tablas: " . implode(', ', $ordenadas) . "\n";
echo "  datos_iniciales.sql  " . implode(', ', $catalogos) . "\n";
echo "  oms_lms.sql          " . $db->query('SELECT COUNT(*) FROM oms_lms')->fetchColumn() . " filas\n";
