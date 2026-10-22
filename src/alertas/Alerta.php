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
     * Vuelve a generar las alertas de las mediciones del niño posteriores a
     * una medición, para que su evolución ("mejoró", "empeoró") se compare con
     * el control vigente anterior. Se usa al anular una medición y al
     * registrar una con fecha anterior (por ejemplo, al pasar el carnet de
     * papel al sistema en cualquier orden).
     */
    public static function regenerarPosteriores(int $idNino, string $fechaToma, int $idMedicion): void
    {
        $db = Database::obtener();
        $stmt = $db->prepare("SELECT id_medicion FROM medicion
                              WHERE id_nino = :nino AND anulado = 0
                                AND (fecha_toma > :fecha1 OR (fecha_toma = :fecha2 AND id_medicion > :id))
                              ORDER BY fecha_toma, id_medicion");
        $stmt->execute([':nino' => $idNino, ':fecha1' => $fechaToma, ':fecha2' => $fechaToma, ':id' => $idMedicion]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idPosterior) {
            self::generar((int) $idPosterior, $idNino);
        }
    }

    /**
     * Genera la alerta para una medición recién calculada. Se invoca desde
     * CU-03/CU-04 tras calcular los indicadores. Se puede volver a ejecutar
     * sobre la misma medición (por ejemplo, al recalcular): reemplaza la
     * alerta anterior de esa medición.
     */
    public static function generar(int $idMedicion, int $idNino): void
    {
        $db = Database::obtener();

        // Al regenerar, se reemplaza la alerta anterior de esta medición.
        $db->prepare("DELETE FROM alerta WHERE id_medicion = :id")->execute([':id' => $idMedicion]);

        // Nivel global de esta medición (lo calcula el motor CU-04).
        $global = Calculo::nivelAlertaGlobal($idMedicion);
        $nivel = $global['nivel'];
        $descripcion = $global['descripcion'];

        // Compara con la medición ANTERIOR del niño para la evolución.
        $evolucion = self::compararConAnterior($idNino, $idMedicion, $nivel);
        if ($evolucion !== '') {
            $descripcion .= '. ' . $evolucion;
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
     * Usa el nivel calculado de la medición anterior, tenga o no alerta: así
     * también avisa cuando un niño pasa de normal a una zona de riesgo.
     */
    private static function compararConAnterior(int $idNino, int $idMedicionActual, string $nivelActual): string
    {
        $db = Database::obtener();

        // Medición inmediatamente anterior a la actual (por fecha de toma).
        $sql = "SELECT m.id_medicion
                FROM medicion m
                JOIN medicion actual ON actual.id_medicion = :id_actual
                WHERE m.id_nino = :id_nino
                  AND m.anulado = 0
                  AND (m.fecha_toma < actual.fecha_toma
                       OR (m.fecha_toma = actual.fecha_toma AND m.id_medicion < actual.id_medicion))
                ORDER BY m.fecha_toma DESC, m.id_medicion DESC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_nino' => $idNino, ':id_actual' => $idMedicionActual]);
        $ant = $stmt->fetch();

        if (!$ant) {
            return ''; // Es el primer control del niño.
        }

        $nivelAnterior = Calculo::nivelAlertaGlobal((int) $ant['id_medicion'])['nivel'];
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
     * Base común del panel de seguimiento: el ÚLTIMO CONTROL de cada niño
     * activo y su alerta, si ese control la tiene. Así, si un niño se recupera
     * y su último control sale normal, deja de aparecer en el seguimiento. Es
     * la misma base del panel de indicadores en "Estado actual", por eso las
     * cifras de los dos paneles coinciden.
     */
    private const BASE_SEGUIMIENTO = "
        FROM nino n
        JOIN medicion m ON m.id_medicion = (
            SELECT m2.id_medicion FROM medicion m2
            WHERE m2.id_nino = n.id_nino AND m2.anulado = 0
            ORDER BY m2.fecha_toma DESC, m2.id_medicion DESC
            LIMIT 1
        )
        JOIN alerta a ON a.id_medicion = m.id_medicion AND a.estado = 'activa'
        JOIN sector s ON s.id_sector = n.id_sector
        JOIN territorio t ON t.id_territorio = s.id_territorio
        LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
        WHERE n.estado = 'activo'";

    /**
     * Panel de seguimiento: niños cuyo último control tiene una alerta,
     * ordenados por gravedad. Una fila por niño.
     */
    public static function panelSeguimiento(array $filtros = []): array
    {
        $db = Database::obtener();

        $condiciones = '';
        $params = [];
        if (!empty($filtros['id_sector'])) {
            $condiciones .= " AND n.id_sector = :id_sector";
            $params[':id_sector'] = $filtros['id_sector'];
        }
        if (!empty($filtros['nivel'])) {
            $condiciones .= " AND a.nivel = :nivel";
            $params[':nivel'] = $filtros['nivel'];
        }

        $sql = "SELECT n.id_nino, n.nombres, n.apellidos, n.fecha_nacimiento, n.sexo,
                       n.nombre_madre, n.telefono, s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre,
                       a.nivel, a.descripcion, a.fecha_generacion,
                       m.fecha_toma"
             . self::BASE_SEGUIMIENTO . $condiciones . "
                ORDER BY FIELD(a.nivel,
                    'desnutricion_aguda','desnutricion_cronica','riesgo',
                    'sobrepeso','cerca_umbral'),
                    m.fecha_toma DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Conteos por nivel para las tarjetas del panel, con el mismo filtro de
     * sector que la lista (el filtro de nivel no aplica: las tarjetas muestran
     * todos los niveles).
     */
    public static function resumenNiveles(string $idSector = ''): array
    {
        $db = Database::obtener();
        $condicion = '';
        $params = [];
        if ($idSector !== '') {
            $condicion = " AND n.id_sector = :id_sector";
            $params[':id_sector'] = $idSector;
        }
        $stmt = $db->prepare("SELECT a.nivel, COUNT(*) AS total" . self::BASE_SEGUIMIENTO . $condicion
            . " GROUP BY a.nivel");
        $stmt->execute($params);
        $res = [];
        foreach ($stmt->fetchAll() as $f) { $res[$f['nivel']] = (int)$f['total']; }
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
