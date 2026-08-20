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
 *   - talla_edad    (talla/longitud para la edad)
 *   - peso_longitud (peso para la longitud, medición acostado, < 2 años)
 *   - peso_talla    (peso para la talla, medición de pie, >= 2 años)
 * El motor elige peso_longitud o peso_talla según cómo se midió al niño
 * (campo tipo_talla de la medición), tal como lo hace la OMS.
 *
 * Referencia: World Health Organization, WHO Child Growth Standards.
 */

class Calculo
{
    public static function procesar(int $idMedicion): array
    {
        $db = Database::obtener();

        $sql = "SELECT m.id_medicion, m.peso_kg, m.talla_cm, m.edad_meses, m.tipo_talla,
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

        $resultados = [];

        // --- Peso para la edad ---
        $z = self::calcularZ('peso_edad', $d['sexo'], $d['edad_meses'], $d['peso_kg']);
        if ($z !== null) {
            $clas = self::clasificarPesoEdad($z);
            self::guardarIndicador($idMedicion, 'peso_edad', $z, $clas);
            $resultados['peso_edad'] = ['z' => $z, 'clasificacion' => $clas];
        }

        // --- Talla para la edad ---
        $z = self::calcularZ('talla_edad', $d['sexo'], $d['edad_meses'], $d['talla_cm']);
        if ($z !== null) {
            $clas = self::clasificarTallaEdad($z);
            self::guardarIndicador($idMedicion, 'talla_edad', $z, $clas);
            $resultados['talla_edad'] = ['z' => $z, 'clasificacion' => $clas];
        }

        // --- Peso para la longitud/talla ---
        // Elige el indicador según cómo se midió: acostado (longitud) o de pie (talla).
        $indPT = ($d['tipo_talla'] === 'longitud') ? 'peso_longitud' : 'peso_talla';
        $tallaRef = round($d['talla_cm'] * 2) / 2; // la OMS tabula cada 0.5 cm
        $z = self::calcularZ($indPT, $d['sexo'], $tallaRef, $d['peso_kg']);
        if ($z !== null) {
            $clas = self::clasificarPesoTalla($z);
            self::guardarIndicador($idMedicion, $indPT, $z, $clas);
            $resultados[$indPT] = ['z' => $z, 'clasificacion' => $clas];
        }

        return ['ok' => true, 'indicadores' => $resultados];
    }

    public static function calcularZ(string $indicador, string $sexo, float $x, float $valor): ?float
    {
        $lms = self::buscarLMS($indicador, $sexo, $x);
        if (!$lms) {
            return null;
        }

        $L = (float) $lms['l'];
        $M = (float) $lms['m'];
        $S = (float) $lms['s'];

        if ($valor <= 0 || $M <= 0 || $S == 0) {
            return null;
        }

        if (abs($L) < 1e-7) {
            $z = log($valor / $M) / $S;
        } else {
            $z = (pow($valor / $M, $L) - 1) / ($S * $L);
        }

        return round($z, 2);
    }

    private static function buscarLMS(string $indicador, string $sexo, float $x): ?array
    {
        $db = Database::obtener();
        $sql = "SELECT l, m, s, x
                FROM oms_lms
                WHERE indicador = :ind AND sexo = :sexo
                ORDER BY ABS(x - :x) ASC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':ind' => $indicador, ':sexo' => $sexo, ':x' => $x]);
        $r = $stmt->fetch();

        if (!$r) return null;

        // Tolerancia: 1 mes para edad, 0.5 cm para peso/talla.
        $esPesoTalla = in_array($indicador, ['peso_longitud', 'peso_talla'], true);
        $tolerancia = $esPesoTalla ? 0.5 : 1.0;
        if (abs((float)$r['x'] - $x) > $tolerancia) {
            return null;
        }
        return $r;
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
