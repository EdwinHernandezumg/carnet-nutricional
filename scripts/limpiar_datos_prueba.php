<?php
/**
 * Borra los datos de prueba antes de que el sistema empiece a usarse con datos
 * reales: los niños y todo lo registrado a ellos. Se preparó para pasar
 * carnetchivarreto.com de ambiente de prueba a producción (21 de octubre de
 * 2026), después de que el personal practicara con niños inventados.
 *
 *   php scripts/limpiar_datos_prueba.php
 *   php scripts/limpiar_datos_prueba.php --base=otra_base    (para probar)
 *
 * Borra: niños, mediciones, puntuaciones Z, alertas, vacunas, suplementos y
 * citas. Conserva: las cuentas de usuario (las de prueba se dan de baja desde
 * Usuarios), los catálogos, las tablas de la OMS y la bitácora, donde queda
 * anotada esta limpieza.
 *
 * Es la única excepción a la regla de no borrar nunca: son datos inventados
 * durante las pruebas, no registros de salud. Por eso:
 *   - muestra cuánto va a borrar y pide escribir una frase para confirmar;
 *   - corre una sola vez: deja en la bitácora la marca "inicio_produccion" y,
 *     si la encuentra, se niega, porque desde entonces los datos son reales;
 *   - no corre en el ambiente de demostración (allí se reinstala);
 *   - todo va en una transacción: o se borra todo, o nada.
 * Antes de ejecutarlo, sacar un respaldo de la base (ver docs/instalacion.md).
 */

require __DIR__ . '/consola.php';
soloConsola();

require __DIR__ . '/../config/config.php';

const FRASE = 'BORRAR DATOS DE PRUEBA';

if (APP_ENTORNO === 'demostracion') {
    exit("Este script no corre en el ambiente de demostración: allí los datos ficticios se quedan.\n");
}
$base = opcion('base') ?? DB_NAME;
$db = conectar($base);

$marca = (int)$db->query("SELECT COUNT(*) FROM bitacora WHERE accion = 'inicio_produccion'")->fetchColumn();
if ($marca > 0) {
    exit("La base '$base' ya se limpió antes de empezar a usarse con datos reales.\n"
       . "Desde entonces los datos son reales y este script ya no borra nada.\n");
}

// Lo que se va a borrar, de lo más dependiente a lo menos (por las llaves foráneas).
$tablas = [
    'indicador'       => 'puntuaciones Z',
    'alerta'          => 'alertas',
    'medicion'        => 'mediciones',
    'vacuna_aplicada' => 'vacunas aplicadas',
    'suplementacion'  => 'suplementos entregados',
    'cita'            => 'citas',
    'nino'            => 'niños',
];
echo "\nLimpieza de los datos de prueba de la base '$base'\n\nSe borrará:\n";
$conteo = [];
foreach ($tablas as $tabla => $nombre) {
    $conteo[$tabla] = (int)$db->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
    printf("  %-24s %6d\n", $nombre, $conteo[$tabla]);
}
echo "\nSe conservan las cuentas de usuario, los catálogos, las tablas de la OMS y la bitácora.\n"
   . "¿Ya sacó el respaldo de la base? Si no, cancele con Ctrl+C.\n\n";

if (preguntar('Para confirmar, escriba ' . FRASE) !== FRASE) {
    exit("No coincide. No se borró nada.\n");
}

// Mientras se escribe la frase, Hostinger puede cerrar la conexión: se abre una nueva.
$db = conectar($base);
$idAdministrador = (int)$db->query(
    "SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
     WHERE r.nombre = 'administrador' AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1"
)->fetchColumn();
if ($idAdministrador === 0) {
    exit("No hay ningún administrador activo para firmar la limpieza en la bitácora. No se borró nada.\n");
}

$db->beginTransaction();
try {
    foreach (array_keys($tablas) as $tabla) {
        $db->exec("DELETE FROM $tabla");
    }
    $resumen = [];
    foreach ($tablas as $tabla => $nombre) {
        $resumen[] = $conteo[$tabla] . ' ' . $nombre;
    }
    registrarEnBitacora($db, $idAdministrador, 'inicio_produccion', $idAdministrador,
        'Limpieza de datos de prueba antes de usar el sistema con datos reales: se borraron '
        . implode(', ', $resumen));
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    exit("\nNo se borró nada: " . $e->getMessage() . "\n");
}

echo "\nListo: se borraron los datos de prueba. Desde ahora, todo lo que se registre es real.\n"
   . "Recuerde dar de baja en Usuarios las cuentas de prueba (por ejemplo, prueba.enfermera).\n";
