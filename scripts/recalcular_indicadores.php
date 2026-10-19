<?php
/**
 * Recalcula las puntuaciones Z y las alertas de todas las mediciones.
 *
 * Se usa cuando cambia el motor de cálculo o cuando se recargan las tablas
 * de la OMS (oms_lms). Recorre a cada niño en orden de fecha de toma, porque
 * la alerta de un control se compara con el control anterior.
 *
 * Todo se hace en una sola transacción: si algo falla, no se cambia nada.
 *
 * Solo se ejecuta desde la línea de comandos, nunca desde el navegador:
 *   php scripts/recalcular_indicadores.php
 * La carpeta scripts/ está fuera de public/, así que la web no llega a ella.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Database.php';
require __DIR__ . '/../src/calculo/Calculo.php';
require __DIR__ . '/../src/alertas/Alerta.php';

$db = Database::obtener();
$mediciones = $db->query(
    "SELECT id_medicion, id_nino FROM medicion WHERE anulado = 0 ORDER BY id_nino, fecha_toma, id_medicion"
)->fetchAll();

$db->beginTransaction();
try {
    foreach ($mediciones as $m) {
        Calculo::procesar((int) $m['id_medicion']);
        Alerta::generar((int) $m['id_medicion'], (int) $m['id_nino']);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Error al recalcular: ' . $e->getMessage() . PHP_EOL . 'No se cambió nada.' . PHP_EOL);
    exit(1);
}

echo 'Se recalcularon ' . count($mediciones) . ' mediciones y sus alertas.' . PHP_EOL;
