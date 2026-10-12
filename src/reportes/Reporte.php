<?php
/**
 * Reportes consolidados (CU-07).
 *
 * Responde a una carencia documentada en el levantamiento: el sistema
 * institucional en uso no genera reportes utilizables por el puesto, y la
 * nutricionista requiere consolidados para planificar el seguimiento.
 *
 * Reporte principal: resumen estadístico del estado nutricional por sector
 * y territorio, con conteos por nivel de alerta.
 */

class Reporte
{
    /**
     * Resumen por sector: cuenta niños activos y su distribución por nivel.
     * Cada niño se cuenta una sola vez, según su alerta activa más reciente.
     * Los niños sin alerta se contabilizan como "normal".
     *
     * Filtros admitidos: id_territorio, fecha_desde, fecha_hasta.
     */
    public static function resumenPorSector(array $filtros = []): array
    {
        $db = Database::obtener();

        $where = ["n.estado = 'activo'"];
        $params = [];

        if (!empty($filtros['id_territorio'])) {
            $where[] = "t.id_territorio = :id_territorio";
            $params[':id_territorio'] = $filtros['id_territorio'];
        }

        // Sub-consulta: nivel vigente de cada niño (alerta activa más reciente).
        // Si no tiene alerta, se considera 'normal'.
        $filtroFecha = '';
        if (!empty($filtros['fecha_desde'])) {
            $filtroFecha .= " AND m2.fecha_toma >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $filtroFecha .= " AND m2.fecha_toma <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql = "SELECT
                    t.numero AS territorio,
                    t.nombre AS territorio_nombre,
                    s.letra  AS sector,
                    s.id_sector,
                    COUNT(*) AS total_ninos,
                    SUM(CASE WHEN nivel_actual = 'desnutricion_aguda'   THEN 1 ELSE 0 END) AS desnutricion_aguda,
                    SUM(CASE WHEN nivel_actual = 'desnutricion_cronica' THEN 1 ELSE 0 END) AS desnutricion_cronica,
                    SUM(CASE WHEN nivel_actual = 'riesgo'               THEN 1 ELSE 0 END) AS riesgo,
                    SUM(CASE WHEN nivel_actual = 'cerca_umbral'         THEN 1 ELSE 0 END) AS cerca_umbral,
                    SUM(CASE WHEN nivel_actual = 'sobrepeso'            THEN 1 ELSE 0 END) AS sobrepeso,
                    SUM(CASE WHEN nivel_actual = 'normal'               THEN 1 ELSE 0 END) AS normal,
                    SUM(CASE WHEN tiene_medicion = 0                    THEN 1 ELSE 0 END) AS sin_control
                FROM (
                    SELECT
                        n.id_nino,
                        n.id_sector,
                        COALESCE((
                            SELECT a2.nivel
                            FROM alerta a2
                            JOIN medicion m2 ON m2.id_medicion = a2.id_medicion
                            WHERE a2.id_nino = n.id_nino AND a2.estado = 'activa' {$filtroFecha}
                            ORDER BY m2.fecha_toma DESC, a2.id_alerta DESC
                            LIMIT 1
                        ), 'normal') AS nivel_actual,
                        (SELECT COUNT(*) FROM medicion m3 WHERE m3.id_nino = n.id_nino) AS tiene_medicion
                    FROM nino n
                    WHERE n.estado = 'activo'
                ) AS resumen
                JOIN nino n       ON n.id_nino = resumen.id_nino
                JOIN sector s     ON s.id_sector = resumen.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                WHERE " . implode(' AND ', $where) . "
                GROUP BY t.id_territorio, t.numero, t.nombre, s.id_sector, s.letra
                ORDER BY t.numero, s.letra";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Totales generales (suma de todos los sectores del reporte).
     */
    public static function totales(array $filas): array
    {
        $t = [
            'total_ninos' => 0, 'desnutricion_aguda' => 0, 'desnutricion_cronica' => 0,
            'riesgo' => 0, 'cerca_umbral' => 0, 'sobrepeso' => 0, 'normal' => 0, 'sin_control' => 0,
        ];
        foreach ($filas as $f) {
            foreach ($t as $k => $v) {
                $t[$k] += (int)($f[$k] ?? 0);
            }
        }
        return $t;
    }

    /**
     * Lista de territorios para el filtro.
     */
    public static function territorios(): array
    {
        $db = Database::obtener();
        return $db->query("SELECT id_territorio, numero, nombre FROM territorio ORDER BY numero")->fetchAll();
    }

    /**
     * Genera el contenido CSV del reporte y lo envía al navegador para descarga.
     * Usa punto y coma como separador (compatible con Excel en configuración
     * regional de Guatemala) y BOM UTF-8 para que las tildes se vean bien.
     */
    public static function descargarCSV(array $filas, array $totales, string $nombreArchivo): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $salida = fopen('php://output', 'w');

        // BOM para que Excel reconozca UTF-8 (tildes y ñ).
        fwrite($salida, "\xEF\xBB\xBF");

        // Encabezado descriptivo
        fputcsv($salida, ['Carnet Digital de Monitoreo Nutricional Infantil'], ';');
        fputcsv($salida, ['Puesto de Salud Chivarreto, San Francisco El Alto, Totonicapan'], ';');
        fputcsv($salida, ['Resumen del estado nutricional por sector'], ';');
        fputcsv($salida, ['Generado el', date('d-m-Y H:i')], ';');
        fputcsv($salida, [], ';');

        // Encabezados de columna
        fputcsv($salida, [
            'Territorio', 'Sector', 'Total ninos', 'Desnutricion aguda',
            'Desnutricion cronica', 'Riesgo (bajo peso)', 'Cerca del umbral',
            'Sobrepeso', 'Normal', 'Sin control',
        ], ';');

        foreach ($filas as $f) {
            fputcsv($salida, [
                'T' . $f['territorio'],
                $f['sector'],
                $f['total_ninos'],
                $f['desnutricion_aguda'],
                $f['desnutricion_cronica'],
                $f['riesgo'],
                $f['cerca_umbral'],
                $f['sobrepeso'],
                $f['normal'],
                $f['sin_control'],
            ], ';');
        }

        // Fila de totales
        fputcsv($salida, [
            'TOTAL', '',
            $totales['total_ninos'],
            $totales['desnutricion_aguda'],
            $totales['desnutricion_cronica'],
            $totales['riesgo'],
            $totales['cerca_umbral'],
            $totales['sobrepeso'],
            $totales['normal'],
            $totales['sin_control'],
        ], ';');

        fclose($salida);
        exit;
    }
}
