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
     * $indicador: 'peso_edad' | 'talla_edad' | 'peso_talla' (peso_longitud se
     *             trata junto con peso_talla según el eje de talla).
     *
     * Retorna un arreglo con:
     *   - eje_x: etiqueta del eje X ('Edad (meses)' o 'Talla (cm)')
     *   - eje_y: etiqueta del eje Y
     *   - referencia: [ {x, menos2, mediana, mas2}, ... ]
     *   - nino: [ {x, y}, ... ]  (las mediciones del niño)
     */
    public static function datos(int $idNino, string $indicador): array
    {
        $db = Database::obtener();

        // Sexo del niño (las curvas dependen del sexo).
        $stmt = $db->prepare("SELECT sexo, fecha_nacimiento FROM nino WHERE id_nino = :id");
        $stmt->execute([':id' => $idNino]);
        $nino = $stmt->fetch();
        if (!$nino) return [];

        $sexo = $nino['sexo'];

        // Configura ejes según el indicador.
        $config = [
            'peso_edad'  => ['eje_x' => 'Edad (meses)', 'eje_y' => 'Peso (kg)',  'col_x' => 'edad_meses', 'col_y' => 'peso_kg'],
            'talla_edad' => ['eje_x' => 'Edad (meses)', 'eje_y' => 'Talla (cm)', 'col_x' => 'edad_meses', 'col_y' => 'talla_cm'],
            'peso_talla' => ['eje_x' => 'Talla (cm)',   'eje_y' => 'Peso (kg)',  'col_x' => 'talla_cm',    'col_y' => 'peso_kg'],
        ];
        if (!isset($config[$indicador])) return [];
        $cfg = $config[$indicador];

        // Para peso_talla, las tablas OMS están en 'peso_longitud' (<2a) y 'peso_talla' (>=2a).
        // Para la gráfica combinamos ambas por el eje talla.
        $indicadoresLMS = ($indicador === 'peso_talla')
            ? ['peso_longitud', 'peso_talla']
            : [$indicador];

        // --- Curvas de referencia ---
        $placeholders = implode(',', array_fill(0, count($indicadoresLMS), '?'));
        $sql = "SELECT x, l, m, s FROM oms_lms
                WHERE indicador IN ($placeholders) AND sexo = ?
                ORDER BY x";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($indicadoresLMS, [$sexo]));
        $filas = $stmt->fetchAll();

        $referencia = [];
        $vistos = [];
        foreach ($filas as $f) {
            $x = (float) $f['x'];
            if (isset($vistos[(string)$x])) continue; // evita duplicados en el solape
            $vistos[(string)$x] = true;
            $L = (float)$f['l']; $M = (float)$f['m']; $S = (float)$f['s'];
            $referencia[] = [
                'x'       => $x,
                'menos2'  => round(self::valorEnZ($L, $M, $S, -2), 2),
                'mediana' => round($M, 2),
                'mas2'    => round(self::valorEnZ($L, $M, $S, 2), 2),
            ];
        }

        // --- Recorrido del niño ---
        $sql = "SELECT {$cfg['col_x']} AS x, {$cfg['col_y']} AS y, fecha_toma
                FROM medicion
                WHERE id_nino = :id
                ORDER BY {$cfg['col_x']}";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idNino]);
        $medNino = [];
        foreach ($stmt->fetchAll() as $m) {
            $medNino[] = ['x' => (float)$m['x'], 'y' => (float)$m['y']];
        }

        return [
            'eje_x'      => $cfg['eje_x'],
            'eje_y'      => $cfg['eje_y'],
            'referencia' => $referencia,
            'nino'       => $medNino,
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
