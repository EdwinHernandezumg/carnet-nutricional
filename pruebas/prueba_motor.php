<?php
/**
 * Pruebas del motor de cálculo (CU-04).
 *
 * Cada caso compara el resultado del motor con el valor esperado según las
 * reglas de la OMS. Sirve para verificar el motor después de cualquier
 * cambio en el código o en las tablas oms_lms.
 *
 * Se ejecuta desde la línea de comandos:
 *   php pruebas/prueba_motor.php
 * Termina con código 0 si todo pasa y 1 si alguna prueba falla.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de comandos.');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Database.php';
require __DIR__ . '/../src/calculo/Calculo.php';

$fallas = 0;
$total  = 0;

/** Compara un resultado con el esperado y muestra el renglón de la prueba. */
function comprobar(string $caso, $obtenido, $esperado, float $tolerancia = 0.005): void
{
    global $fallas, $total;
    $total++;
    $ok = ($esperado === null)
        ? $obtenido === null
        : ($obtenido !== null && abs($obtenido - $esperado) <= $tolerancia);
    if (!$ok) $fallas++;
    $mostrar = fn($v) => $v === null ? 'sin dato' : number_format($v, 2);
    printf("  %-6s %-62s obtenido %8s  esperado %8s\n",
        $ok ? 'OK' : 'FALLA', $caso, $mostrar($obtenido), $mostrar($esperado));
}

/** Fila L, M, S de la tabla, tal como la publica la OMS. */
function fila(string $indicador, string $sexo, float $x): array
{
    $db = Database::obtener();
    $stmt = $db->prepare("SELECT l, m, s FROM oms_lms WHERE indicador = :ind AND sexo = :sexo AND x = :x");
    $stmt->execute([':ind' => $indicador, ':sexo' => $sexo, ':x' => $x]);
    $f = $stmt->fetch();
    return [(float) $f['l'], (float) $f['m'], (float) $f['s']];
}

echo "1. La mediana da Z = 0 y el valor de -2 DE da Z = -2 (criterio de CLAUDE.md)\n";
foreach ([['peso_edad', 'M', 6.0], ['talla_edad', 'F', 12.0], ['peso_longitud', 'F', 74.0], ['peso_talla', 'M', 100.0]] as [$ind, $sexo, $x]) {
    [$L, $M, $S] = fila($ind, $sexo, $x);
    comprobar("$ind, sexo $sexo, x = $x: mediana", Calculo::calcularZ($ind, $sexo, $x, $M), 0.0);
    comprobar("$ind, sexo $sexo, x = $x: valor de -2 DE", Calculo::calcularZ($ind, $sexo, $x, Calculo::valorEnZ($L, $M, $S, -2)), -2.0);
}

echo "\n2. Bajo peso da Z negativo y sobrepeso da Z positivo\n";
[$L, $M, $S] = fila('peso_edad', 'M', 12.0);
comprobar('peso_edad 12 meses, 90 % de la mediana (negativo)', Calculo::calcularZ('peso_edad', 'M', 12.0, $M * 0.9) < 0 ? 1.0 : 0.0, 1.0);
comprobar('peso_edad 12 meses, 115 % de la mediana (positivo)', Calculo::calcularZ('peso_edad', 'M', 12.0, $M * 1.15) > 0 ? 1.0 : 0.0, 1.0);

echo "\n3. Regla 1: edad exacta, interpolando entre meses\n";
[, $M6]  = fila('peso_edad', 'M', 6.0);
[, $M7]  = fila('peso_edad', 'M', 7.0);
comprobar('6.5 meses: la mediana interpolada da Z = 0', Calculo::calcularZ('peso_edad', 'M', 6.5, ($M6 + $M7) / 2), 0.0, 0.01);
comprobar('Bebé de 55 días con 4.3 kg (antes daba -0.29)', Calculo::calcularZ('peso_edad', 'M', 55 / Calculo::DIAS_POR_MES, 4.3), -1.71, 0.01);

echo "\n4. Regla 2: conversión de 0.7 cm entre longitud y talla\n";
comprobar('Menor de 2 años medido de pie (77.0 cm)', Calculo::tallaSegunEdad(77.0, 'talla', true), 77.7);
comprobar('Menor de 2 años medido acostado (77.0 cm)', Calculo::tallaSegunEdad(77.0, 'longitud', true), 77.0);
comprobar('Mayor de 2 años medido acostado (90.0 cm)', Calculo::tallaSegunEdad(90.0, 'longitud', false), 89.3);
comprobar('Mayor de 2 años medido de pie (90.0 cm)', Calculo::tallaSegunEdad(90.0, 'talla', false), 90.0);

echo "\n5. Regla 3: aplicación restringida del LMS más allá de ±3 Z (solo peso)\n";
[$L, $M, $S] = fila('peso_longitud', 'F', 74.0);
$sd3n = Calculo::valorEnZ($L, $M, $S, -3);
$sd2n = Calculo::valorEnZ($L, $M, $S, -2);
$sd3p = Calculo::valorEnZ($L, $M, $S, 3);
$sd2p = Calculo::valorEnZ($L, $M, $S, 2);
comprobar('Valor de -3 DE da exactamente -3', Calculo::calcularZ('peso_longitud', 'F', 74.0, $sd3n), -3.0);
comprobar('Una separación (de -2 a -3 DE) más abajo da -4', Calculo::calcularZ('peso_longitud', 'F', 74.0, $sd3n - ($sd2n - $sd3n)), -4.0);
comprobar('Una separación (de +2 a +3 DE) más arriba da +4', Calculo::calcularZ('peso_longitud', 'F', 74.0, $sd3p + ($sd3p - $sd2p)), 4.0);
comprobar('Niña id 15: 6.10 kg y 74.0 cm (antes daba -4.59)', Calculo::calcularZ('peso_longitud', 'F', 74.0, 6.10), -4.38, 0.01);
[$L, $M, $S] = fila('talla_edad', 'F', 12.0);
comprobar('Talla para la edad no se restringe: -4 DE da -4', Calculo::calcularZ('talla_edad', 'F', 12.0, Calculo::valorEnZ($L, $M, $S, -4)), -4.0);

echo "\n6. Fuera de las tablas de la OMS no se calcula (no se inventan datos)\n";
comprobar('Edad de 62 meses', Calculo::calcularZ('peso_edad', 'M', 62.0, 18.0), null);
comprobar('Edad de 60.5 meses (último mes, como WHO Anthro)', Calculo::calcularZ('peso_edad', 'M', 60.5, fila('peso_edad', 'M', 60.0)[1]), 0.0);
comprobar('Longitud de 44.0 cm (la tabla empieza en 45)', Calculo::calcularZ('peso_longitud', 'F', 44.0, 2.5), null);
comprobar('Talla de 121.0 cm (la tabla termina en 120)', Calculo::calcularZ('peso_talla', 'M', 121.0, 22.0), null);

echo "\n" . ($total - $fallas) . " de $total pruebas pasaron." . PHP_EOL;
exit($fallas > 0 ? 1 : 0);
