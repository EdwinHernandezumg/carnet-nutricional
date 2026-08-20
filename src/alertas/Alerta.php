<?php
/**
 * Generación y gestión de alertas nutricionales (CU-05).
 *
 * Se apoya en los indicadores calculados por CU-04. Cuando una medición
 * cae en zona de riesgo, genera una alerta con su nivel. Además compara
 * con la medición anterior del niño para indicar la evolución (mejoró,
 * empeoró o se mantiene).
 *
 * El nivel "cerca_umbral" es el diferenciador clave del sistema: identifica
 * a los niños que se aproximan a la desnutrición aunque aún no la crucen,
 * tal como lo solicitó la nutricionista en el levantamiento.
 */

class Alerta
{
    // Orden de gravedad para comparar niveles.
    private static array $orden = [
        'normal' => 0,
        'cerca_umbral' => 1,
        'sobrepeso' => 2,
        'riesgo' => 2,
        'desnutricion_cronica' => 3,
        'desnutricion_aguda' => 4,
    ];

    /**
     * Genera (o actualiza) la alerta para una medición recién calculada.
     * Se invoca desde CU-03/CU-04 tras calcular los indicadores.
     */
    public static function generar(int $idMedicion, int $idNino): void
    {
        $db = Database::obtener();

        // Nivel global de esta medición (lo calcula el motor CU-04).
        $global = Calculo::nivelAlertaGlobal($idMedicion);
        $nivel = $global['nivel'];
        $descripcion = $global['descripcion'];

        // Compara con la medición ANTERIOR del niño para la evolución.
        $evolucion = self::compararConAnterior($idNino, $idMedicion, $nivel);
        if ($evolucion !== '') {
            $descripcion .= ' — ' . $evolucion;
        }

        // Si el nivel es normal, no se crea alerta de riesgo (pero sí queda el cálculo).
        if ($nivel === 'normal') {
            return;
        }

        // Registra la alerta asociada a la medición y al niño.
        $sql = "INSERT INTO alerta (id_nino, id_medicion, nivel, descripcion, estado)
                VALUES (:id_nino, :id_medicion, :nivel, :desc, 'activa')";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'     => $idNino,
            ':id_medicion' => $idMedicion,
            ':nivel'       => $nivel,
            ':desc'        => $descripcion,
        ]);
    }

    /**
     * Compara el nivel actual con el de la medición anterior del niño.
     * Devuelve un texto de evolución: "Mejoró...", "Empeoró..." o "".
     */
    private static function compararConAnterior(int $idNino, int $idMedicionActual, string $nivelActual): string
    {
        $db = Database::obtener();

        // Busca la alerta de la medición inmediatamente anterior (por fecha).
        $sql = "SELECT a.nivel
                FROM alerta a
                JOIN medicion m ON m.id_medicion = a.id_medicion
                WHERE a.id_nino = :id_nino
                  AND a.id_medicion <> :id_actual
                ORDER BY m.fecha_toma DESC, a.id_alerta DESC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_nino' => $idNino, ':id_actual' => $idMedicionActual]);
        $ant = $stmt->fetch();

        if (!$ant) {
            return ''; // No hay medición previa con alerta.
        }

        $nivelAnterior = $ant['nivel'];
        $oa = self::$orden[$nivelAnterior] ?? 0;
        $oc = self::$orden[$nivelActual] ?? 0;

        if ($oc < $oa) {
            return 'Mejoró respecto al control anterior';
        }
        if ($oc > $oa) {
            return 'Empeoró respecto al control anterior';
        }
        return 'Se mantiene respecto al control anterior';
    }

    /**
     * Panel de seguimiento: todos los niños con alerta activa, ordenados
     * por gravedad. Devuelve una fila por niño (su alerta más reciente).
     */
    public static function panelSeguimiento(array $filtros = []): array
    {
        $db = Database::obtener();

        $where = ["a.estado = 'activa'", "n.estado = 'activo'"];
        $params = [];

        if (!empty($filtros['id_sector'])) {
            $where[] = "n.id_sector = :id_sector";
            $params[':id_sector'] = $filtros['id_sector'];
        }
        if (!empty($filtros['nivel'])) {
            $where[] = "a.nivel = :nivel";
            $params[':nivel'] = $filtros['nivel'];
        }

        // Toma la alerta más reciente por niño.
        $sql = "SELECT n.id_nino, n.nombres, n.apellidos, n.fecha_nacimiento, n.sexo,
                       s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre,
                       a.nivel, a.descripcion, a.fecha_generacion,
                       m.fecha_toma
                FROM alerta a
                JOIN medicion m ON m.id_medicion = a.id_medicion
                JOIN nino n ON n.id_nino = a.id_nino
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE " . implode(' AND ', $where) . "
                  AND a.id_alerta = (
                      SELECT a2.id_alerta FROM alerta a2
                      JOIN medicion m2 ON m2.id_medicion = a2.id_medicion
                      WHERE a2.id_nino = a.id_nino AND a2.estado = 'activa'
                      ORDER BY m2.fecha_toma DESC, a2.id_alerta DESC LIMIT 1
                  )
                ORDER BY FIELD(a.nivel,
                    'desnutricion_aguda','desnutricion_cronica','riesgo',
                    'sobrepeso','cerca_umbral'),
                    m.fecha_toma DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Conteos por nivel para las tarjetas de resumen del panel.
     */
    public static function resumenNiveles(): array
    {
        $db = Database::obtener();
        $sql = "SELECT a.nivel, COUNT(DISTINCT a.id_nino) AS total
                FROM alerta a
                JOIN nino n ON n.id_nino = a.id_nino
                WHERE a.estado = 'activa' AND n.estado = 'activo'
                  AND a.id_alerta = (
                      SELECT a2.id_alerta FROM alerta a2
                      JOIN medicion m2 ON m2.id_medicion = a2.id_medicion
                      WHERE a2.id_nino = a.id_nino AND a2.estado = 'activa'
                      ORDER BY m2.fecha_toma DESC, a2.id_alerta DESC LIMIT 1
                  )
                GROUP BY a.nivel";
        $filas = $db->query($sql)->fetchAll();
        $res = [];
        foreach ($filas as $f) { $res[$f['nivel']] = (int)$f['total']; }
        return $res;
    }

    /** Etiqueta legible de un nivel. */
    public static function etiquetaNivel(string $nivel): string
    {
        return [
            'cerca_umbral'         => 'Cerca del umbral',
            'riesgo'               => 'Riesgo (bajo peso)',
            'desnutricion_aguda'   => 'Desnutrición aguda',
            'desnutricion_cronica' => 'Desnutrición crónica',
            'sobrepeso'            => 'Sobrepeso',
            'normal'               => 'Normal',
        ][$nivel] ?? $nivel;
    }

    /** Clase CSS de color de un nivel. */
    public static function claseNivel(string $nivel): string
    {
        return [
            'cerca_umbral'         => 'nivel-cerca',
            'riesgo'               => 'nivel-riesgo',
            'desnutricion_aguda'   => 'nivel-riesgo',
            'desnutricion_cronica' => 'nivel-riesgo',
            'sobrepeso'            => 'nivel-cerca',
            'normal'               => 'nivel-normal',
        ][$nivel] ?? 'nivel-normal';
    }
}
