<?php
/**
 * Motor de cálculo del estado nutricional (CU-04).
 *
 * Aplica el método LMS de la OMS para obtener la puntuación Z de cada
 * indicador antropométrico, y clasifica el estado según los puntos de corte
 * oficiales de la OMS.
 *
 * Fórmula LMS (Box-Cox):
 *   Z = ((valor / M)^L - 1) / (S * L)   si L != 0
 *   Z = ln(valor / M) / S                si L == 0
 *
 * Indicadores:
 *   - peso_edad     (peso para la edad)
 *   - talla_edad    (longitud o talla para la edad)
 *   - peso_longitud (peso para la longitud, menores de 2 años)
 *   - peso_talla    (peso para la talla, de 2 años en adelante)
 *
 * Reglas de la OMS que aplica, igual que su programa oficial WHO Anthro:
 *   1. Edad exacta. La edad se calcula en días a la fecha de la toma y se
 *      convierte a meses con decimales (un mes = 30.4375 días). Como las tablas
 *      vienen mes a mes, los valores L, M y S se interpolan entre el mes
 *      anterior y el siguiente. Usar meses cumplidos subestima la edad: en los
 *      primeros meses, un bebé de 55 días con 4.3 kg daba Z = -0.29 (normal)
 *      cuando le corresponde -1.71 (cerca del umbral).
 *   2. Longitud o talla según la edad. La OMS compara a los menores de 2 años
 *      con su longitud (acostado) y a los mayores con su talla (de pie). Si se
 *      midió de la otra forma, se convierte: medido de pie antes de los 2 años
 *      se suma 0.7 cm; medido acostado desde los 2 años se resta 0.7 cm. Con
 *      esa medida se calcula la talla para la edad y se elige entre peso para
 *      la longitud (menor de 2 años) y peso para la talla (desde los 2 años).
 *   3. Aplicación restringida del LMS. En los indicadores de peso, más allá de
 *      ±3 Z la OMS mide la distancia con la separación entre 2 y 3 Z, para no
 *      exagerar los valores extremos. La clasificación no cambia.
 *
 * La lógica está separada de los datos: los valores L, M y S vienen de la
 * tabla oms_lms. Si la OMS actualiza sus tablas, se recargan los datos y se
 * recalculan las mediciones con scripts/recalcular_indicadores.php.
 *
 * Referencia: World Health Organization, WHO Child Growth Standards (2006) y
 * WHO Anthro, manual de software.
 */

class Calculo
{
    /** Días de un mes según la OMS (365.25 / 12). */
    const DIAS_POR_MES = 30.4375;

    /** Desde esta edad en días (24 meses) la OMS usa la talla de pie. */
    const DIAS_24_MESES = 731;

    /** Diferencia entre longitud (acostado) y talla (de pie), en cm. */
    const DIFERENCIA_LONGITUD_TALLA = 0.7;

    /** Meses de margen al final de las tablas de edad (llegan hasta 60). */
    const MARGEN_EDAD_MESES = 1.0;

    /** Indicadores de peso, a los que se aplica la regla de ±3 Z. */
    const INDICADORES_PESO = ['peso_edad', 'peso_longitud', 'peso_talla'];

    /**
     * Calcula y guarda los indicadores de una medición. Se puede volver a
     * ejecutar sobre la misma medición: reemplaza los indicadores anteriores.
     */
    public static function procesar(int $idMedicion): array
    {
        $db = Database::obtener();

        $sql = "SELECT m.id_medicion, m.peso_kg, m.talla_cm, m.tipo_talla,
                       DATEDIFF(m.fecha_toma, n.fecha_nacimiento) AS edad_dias,
                       n.sexo
                FROM medicion m
                JOIN nino n ON n.id_nino = m.id_nino
                WHERE m.id_medicion = :id
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idMedicion]);
        $d = $stmt->fetch();

        if (!$d) {
            return ['ok' => false, 'error' => 'Medición no encontrada.'];
        }

        // Regla 1: edad exacta en meses, con decimales.
        $edadDias  = (int) $d['edad_dias'];
        $edadMeses = $edadDias / self::DIAS_POR_MES;
        $menorDe2  = $edadDias < self::DIAS_24_MESES;

        // Regla 2: longitud para menores de 2 años, talla desde los 2 años.
        $talla = self::tallaSegunEdad((float) $d['talla_cm'], $d['tipo_talla'], $menorDe2);
        $peso  = (float) $d['peso_kg'];

        // Al recalcular, se reemplazan los indicadores anteriores de la medición.
        $db->prepare("DELETE FROM indicador WHERE id_medicion = :id")->execute([':id' => $idMedicion]);

        $resultados = [];

        // --- Peso para la edad ---
        $z = self::calcularZ('peso_edad', $d['sexo'], $edadMeses, $peso);
        if ($z !== null) {
            $clas = self::clasificarPesoEdad($z);
            self::guardarIndicador($idMedicion, 'peso_edad', $z, $clas);
            $resultados['peso_edad'] = ['z' => $z, 'clasificacion' => $clas];
        }

        // --- Talla para la edad ---
        $z = self::calcularZ('talla_edad', $d['sexo'], $edadMeses, $talla);
        if ($z !== null) {
            $clas = self::clasificarTallaEdad($z);
            self::guardarIndicador($idMedicion, 'talla_edad', $z, $clas);
            $resultados['talla_edad'] = ['z' => $z, 'clasificacion' => $clas];
        }

        // --- Peso para la longitud (menor de 2 años) o para la talla ---
        $indPT = $menorDe2 ? 'peso_longitud' : 'peso_talla';
        $z = self::calcularZ($indPT, $d['sexo'], $talla, $peso);
        if ($z !== null) {
            $clas = self::clasificarPesoTalla($z);
            self::guardarIndicador($idMedicion, $indPT, $z, $clas);
            $resultados[$indPT] = ['z' => $z, 'clasificacion' => $clas];
        }

        return ['ok' => true, 'indicadores' => $resultados];
    }

    /**
     * Convierte la medida a la que corresponde según la edad (regla 2):
     * longitud antes de los 2 años y talla desde los 2 años.
     */
    public static function tallaSegunEdad(float $tallaCm, string $tipoTalla, bool $menorDe2): float
    {
        if ($menorDe2 && $tipoTalla === 'talla') {
            return $tallaCm + self::DIFERENCIA_LONGITUD_TALLA;
        }
        if (!$menorDe2 && $tipoTalla === 'longitud') {
            return $tallaCm - self::DIFERENCIA_LONGITUD_TALLA;
        }
        return $tallaCm;
    }

    /**
     * Puntuación Z de un valor. $x es la edad en meses (con decimales) o la
     * longitud/talla en cm, según el indicador. Devuelve null si el valor de
     * $x queda fuera de las tablas de la OMS.
     */
    public static function calcularZ(string $indicador, string $sexo, float $x, float $valor): ?float
    {
        $lms = self::lmsInterpolado($indicador, $sexo, $x);
        if ($lms === null || $valor <= 0) {
            return null;
        }
        [$L, $M, $S] = $lms;
        if ($M <= 0 || $S == 0) {
            return null;
        }

        if (abs($L) < 1e-7) {
            $z = log($valor / $M) / $S;
        } else {
            $z = (pow($valor / $M, $L) - 1) / ($S * $L);
        }

        // Regla 3: aplicación restringida del LMS en los indicadores de peso.
        if (in_array($indicador, self::INDICADORES_PESO, true)) {
            if ($z > 3) {
                $sd3 = self::valorEnZ($L, $M, $S, 3);
                $sd2 = self::valorEnZ($L, $M, $S, 2);
                $z = 3 + ($valor - $sd3) / ($sd3 - $sd2);
            } elseif ($z < -3) {
                $sd3 = self::valorEnZ($L, $M, $S, -3);
                $sd2 = self::valorEnZ($L, $M, $S, -2);
                $z = -3 + ($valor - $sd3) / ($sd2 - $sd3);
            }
        }

        return round($z, 2);
    }

    /** Fórmula LMS inversa: valor que corresponde a una puntuación Z dada. */
    public static function valorEnZ(float $L, float $M, float $S, float $z): float
    {
        if (abs($L) < 1e-7) {
            return $M * exp($S * $z);
        }
        return $M * pow(1 + $L * $S * $z, 1 / $L);
    }

    /**
     * Valores L, M y S para un valor de $x, interpolados entre la fila anterior
     * y la siguiente de la tabla (regla 1). No se extrapola fuera de la tabla,
     * salvo el último mes de las tablas de edad, como hace WHO Anthro.
     * Devuelve [L, M, S] o null.
     */
    private static function lmsInterpolado(string $indicador, string $sexo, float $x): ?array
    {
        $db = Database::obtener();
        $sql = "(SELECT x, l, m, s FROM oms_lms
                  WHERE indicador = :ind1 AND sexo = :sexo1 AND x <= :x1
                  ORDER BY x DESC LIMIT 1)
                UNION ALL
                (SELECT x, l, m, s FROM oms_lms
                  WHERE indicador = :ind2 AND sexo = :sexo2 AND x >= :x2
                  ORDER BY x ASC LIMIT 1)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':ind1' => $indicador, ':sexo1' => $sexo, ':x1' => $x,
            ':ind2' => $indicador, ':sexo2' => $sexo, ':x2' => $x,
        ]);
        $filas = $stmt->fetchAll();

        $esEdad = in_array($indicador, ['peso_edad', 'talla_edad'], true);

        if (count($filas) === 1) {
            // Solo hay fila anterior: $x pasó el final de la tabla.
            $fila = $filas[0];
            $pasado = $x - (float) $fila['x'];
            if ($esEdad && $pasado >= 0 && $pasado <= self::MARGEN_EDAD_MESES) {
                return [(float) $fila['l'], (float) $fila['m'], (float) $fila['s']];
            }
            return null;
        }
        if (count($filas) !== 2) {
            return null;
        }

        [$a, $b] = $filas;
        $xa = (float) $a['x'];
        $xb = (float) $b['x'];
        if ($xb <= $xa) {
            return [(float) $a['l'], (float) $a['m'], (float) $a['s']];
        }

        $f = ($x - $xa) / ($xb - $xa);
        return [
            (float) $a['l'] + $f * ((float) $b['l'] - (float) $a['l']),
            (float) $a['m'] + $f * ((float) $b['m'] - (float) $a['m']),
            (float) $a['s'] + $f * ((float) $b['s'] - (float) $a['s']),
        ];
    }

    private static function guardarIndicador(int $idMedicion, string $tipo, float $z, string $clasificacion): void
    {
        $db = Database::obtener();
        $sql = "INSERT INTO indicador (id_medicion, tipo, puntuacion_z, clasificacion)
                VALUES (:id, :tipo, :z, :clas)
                ON DUPLICATE KEY UPDATE
                    puntuacion_z = VALUES(puntuacion_z),
                    clasificacion = VALUES(clasificacion),
                    fecha_calculo = CURRENT_TIMESTAMP";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idMedicion, ':tipo' => $tipo, ':z' => $z, ':clas' => $clasificacion]);
    }

    // ---------- Clasificación por puntos de corte de la OMS ----------

    public static function clasificarPesoEdad(float $z): string
    {
        if ($z < -3) return 'Bajo peso severo';
        if ($z < -2) return 'Bajo peso';
        if ($z <= 2)  return 'Peso adecuado';
        return 'Peso elevado para la edad';
    }

    public static function clasificarTallaEdad(float $z): string
    {
        if ($z < -3) return 'Retardo de talla severo';
        if ($z < -2) return 'Retardo de talla (desnutrición crónica)';
        if ($z <= 3)  return 'Talla adecuada';
        return 'Talla alta';
    }

    public static function clasificarPesoTalla(float $z): string
    {
        if ($z < -3) return 'Desnutrición aguda severa';
        if ($z < -2) return 'Desnutrición aguda';
        if ($z <= 1)  return 'Peso adecuado para la talla';
        if ($z <= 2)  return 'Riesgo de sobrepeso';
        if ($z <= 3)  return 'Sobrepeso';
        return 'Obesidad';
    }

    /**
     * Nivel de alerta global de una medición (el más grave prevalece).
     */
    public static function nivelAlertaGlobal(int $idMedicion): array
    {
        $db = Database::obtener();
        $sql = "SELECT tipo, puntuacion_z, clasificacion FROM indicador WHERE id_medicion = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idMedicion]);
        $indicadores = $stmt->fetchAll();

        $peorNivel = 'normal';
        $descripciones = [];
        $orden = ['normal' => 0, 'cerca_umbral' => 1, 'riesgo' => 2,
                  'sobrepeso' => 2, 'desnutricion_cronica' => 3, 'desnutricion_aguda' => 4];

        // Nombre corto de cada indicador para los mensajes.
        $nombreInd = [
            'peso_edad'     => 'Peso para la edad',
            'talla_edad'    => 'Talla para la edad',
            'peso_longitud' => 'Peso para la longitud',
            'peso_talla'    => 'Peso para la talla',
        ];

        foreach ($indicadores as $ind) {
            $z = (float) $ind['puntuacion_z'];
            $tipo = $ind['tipo'];
            $nombre = $nombreInd[$tipo] ?? $tipo;
            $nivel = 'normal';
            $texto = '';

            if ($tipo === 'peso_longitud' || $tipo === 'peso_talla') {
                if ($z < -2) {
                    $nivel = 'desnutricion_aguda';
                    $texto = $ind['clasificacion'] . ' (Z=' . number_format($z, 2) . ')';
                } elseif ($z < -1) {
                    $nivel = 'cerca_umbral';
                    $texto = $nombre . ' acercándose al umbral de desnutrición aguda (Z=' . number_format($z, 2) . ')';
                } elseif ($z > 2) {
                    $nivel = 'sobrepeso';
                    $texto = $ind['clasificacion'] . ' (Z=' . number_format($z, 2) . ')';
                }
            } elseif ($tipo === 'talla_edad') {
                if ($z < -2) {
                    $nivel = 'desnutricion_cronica';
                    $texto = $ind['clasificacion'] . ' (Z=' . number_format($z, 2) . ')';
                } elseif ($z < -1) {
                    $nivel = 'cerca_umbral';
                    $texto = $nombre . ' acercándose al umbral de retardo de talla (Z=' . number_format($z, 2) . ')';
                }
            } elseif ($tipo === 'peso_edad') {
                if ($z < -2) {
                    $nivel = 'riesgo';
                    $texto = $ind['clasificacion'] . ' (Z=' . number_format($z, 2) . ')';
                } elseif ($z < -1) {
                    $nivel = 'cerca_umbral';
                    $texto = $nombre . ' acercándose al umbral de bajo peso (Z=' . number_format($z, 2) . ')';
                }
            }

            if ($orden[$nivel] > $orden[$peorNivel]) {
                $peorNivel = $nivel;
            }
            if ($texto !== '') {
                $descripciones[] = $texto;
            }
        }

        return [
            'nivel' => $peorNivel,
            'descripcion' => $descripciones ? implode('; ', $descripciones) : 'Indicadores dentro de rango normal.',
        ];
    }
}
