<?php
/**
 * Genera los datos para las gráficas de crecimiento de la OMS (CU-04, visual).
 *
 * Las curvas de referencia (-2 DE, mediana, +2 DE) se calculan a partir de los
 * mismos valores L, M, S de la tabla oms_lms, aplicando la fórmula LMS inversa:
 *   valor(z) = M * (1 + L*S*z)^(1/L)      si L != 0
 *   valor(z) = M * exp(S*z)                si L == 0
 *
 * No requiere datos adicionales: reutiliza las tablas ya cargadas.
 */

class GraficaCrecimiento
{
    /**
     * Devuelve los datos de una gráfica para un niño e indicador.
     * $indicador: 'peso_edad' | 'talla_edad' | 'peso_talla' (esta última usa la
     *             tabla de peso para la longitud o para la talla según la edad).
     *
     * Retorna un arreglo con:
     *   - titulo: nombre de la gráfica
     *   - eje_x: etiqueta del eje X ('Edad (meses)', 'Longitud (cm)' o 'Talla (cm)')
     *   - eje_y: etiqueta del eje Y
     *   - referencia: [ {x, menos2, mediana, mas2}, ... ]
     *   - nino: [ {x, y, fecha}, ... ]  (las mediciones del niño)
     */
    public static function datos(int $idNino, string $indicador): array
    {
        $db = Database::obtener();

        // Sexo del niño (las curvas dependen del sexo) y fecha de nacimiento.
        $stmt = $db->prepare("SELECT sexo, fecha_nacimiento FROM nino WHERE id_nino = :id");
        $stmt->execute([':id' => $idNino]);
        $nino = $stmt->fetch();
        if (!$nino) return [];

        $sexo = $nino['sexo'];

        if (!in_array($indicador, ['peso_edad', 'talla_edad', 'peso_talla'], true)) return [];

        // Mediciones del niño, con la edad exacta a la fecha de cada toma.
        $stmt = $db->prepare(
            "SELECT m.peso_kg, m.talla_cm, m.tipo_talla, m.fecha_toma,
                    DATEDIFF(m.fecha_toma, n.fecha_nacimiento) AS edad_dias
             FROM medicion m
             JOIN nino n ON n.id_nino = m.id_nino
             WHERE m.id_nino = :id
             ORDER BY m.fecha_toma, m.id_medicion"
        );
        $stmt->execute([':id' => $idNino]);
        $mediciones = $stmt->fetchAll();

        // Tabla de referencia y título de la gráfica, igual que en el motor de
        // cálculo. Peso para la talla: la OMS usa la tabla de longitud antes de
        // los 2 años y la de talla después; se elige según la edad del niño en
        // su última medición (o hoy, si aún no tiene), para no mezclar las dos.
        $ultimaEdadDias = $mediciones
            ? (int) end($mediciones)['edad_dias']
            : (int) (new DateTime($nino['fecha_nacimiento']))->diff(new DateTime('today'))->days;
        $tablaLongitud = $ultimaEdadDias < Calculo::DIAS_24_MESES;

        $config = [
            'peso_edad'  => ['tabla' => 'peso_edad',  'titulo' => 'Peso para la edad',
                             'eje_x' => 'Edad (meses)', 'eje_y' => 'Peso (kg)'],
            'talla_edad' => ['tabla' => 'talla_edad', 'titulo' => 'Talla para la edad',
                             'eje_x' => 'Edad (meses)', 'eje_y' => 'Talla (cm)'],
            'peso_talla' => $tablaLongitud
                ? ['tabla' => 'peso_longitud', 'titulo' => 'Peso para la longitud',
                   'eje_x' => 'Longitud (cm)', 'eje_y' => 'Peso (kg)']
                : ['tabla' => 'peso_talla', 'titulo' => 'Peso para la talla',
                   'eje_x' => 'Talla (cm)', 'eje_y' => 'Peso (kg)'],
        ];
        $cfg = $config[$indicador];

        // --- Curvas de referencia (-2 DE, mediana, +2 DE) ---
        $stmt = $db->prepare("SELECT x, l, m, s FROM oms_lms WHERE indicador = :ind AND sexo = :sexo ORDER BY x");
        $stmt->execute([':ind' => $cfg['tabla'], ':sexo' => $sexo]);
        $referencia = [];
        foreach ($stmt->fetchAll() as $f) {
            $L = (float) $f['l']; $M = (float) $f['m']; $S = (float) $f['s'];
            $referencia[] = [
                'x'       => (float) $f['x'],
                'menos2'  => round(self::valorEnZ($L, $M, $S, -2), 2),
                'mediana' => round($M, 2),
                'mas2'    => round(self::valorEnZ($L, $M, $S, 2), 2),
            ];
        }

        // --- Recorrido del niño ---
        // Cada punto usa la misma medida que el motor de cálculo (regla 2 de la
        // OMS): longitud antes de los 2 años y talla después, con ±0.7 cm si se
        // midió de la otra forma. Así la gráfica coincide con la puntuación Z.
        $puntos = [];
        foreach ($mediciones as $m) {
            $edadDias  = (int) $m['edad_dias'];
            $edadMeses = round($edadDias / Calculo::DIAS_POR_MES, 2);
            $peso      = (float) $m['peso_kg'];
            if ($indicador === 'peso_talla') {
                // La medida se lleva a la de la tabla elegida (longitud o talla).
                $talla = Calculo::tallaSegunEdad((float) $m['talla_cm'], $m['tipo_talla'], $tablaLongitud);
                $puntos[] = ['x' => round($talla, 1), 'y' => $peso];
            } elseif ($indicador === 'talla_edad') {
                $talla = Calculo::tallaSegunEdad((float) $m['talla_cm'], $m['tipo_talla'],
                    $edadDias < Calculo::DIAS_24_MESES);
                $puntos[] = ['x' => $edadMeses, 'y' => round($talla, 1)];
            } else {
                $puntos[] = ['x' => $edadMeses, 'y' => $peso];
            }
            $puntos[count($puntos) - 1]['fecha'] = Formato::fechaTexto($m['fecha_toma']);
        }
        // Para la gráfica de peso para la talla, el recorrido va ordenado por talla.
        if ($indicador === 'peso_talla') {
            usort($puntos, fn($a, $b) => $a['x'] <=> $b['x']);
        }

        return [
            'titulo'     => $cfg['titulo'],
            'eje_x'      => $cfg['eje_x'],
            'eje_y'      => $cfg['eje_y'],
            'referencia' => $referencia,
            'nino'       => $puntos,
        ];
    }

    /** Fórmula LMS inversa: obtiene el valor para una puntuación Z dada. */
    private static function valorEnZ(float $L, float $M, float $S, float $z): float
    {
        if (abs($L) < 1e-7) {
            return $M * exp($S * $z);
        }
        return $M * pow(1 + $L * $S * $z, 1 / $L);
    }
}
