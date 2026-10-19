<?php
/**
 * Formato SIGSA 3 (FOR-SIGSA-3CS, versión 3.0): registro diario de consulta.
 *
 * El puesto llena este formato a mano cada día, además del cuaderno SIGSA 5a,
 * y la digitadora lo ingresa después en SIGSA Web. Para no escribir dos veces
 * lo mismo, el sistema imprime la hoja con las atenciones a niños ya llenas:
 * una fila por niño y día (control de crecimiento, vacunas y suplementos). Las
 * demás filas quedan en blanco para el resto de pacientes del día.
 *
 * Solo se llena lo que el sistema sabe con certeza. Lo que no registra
 * (discapacidad, tipo de consulta, referencias) queda en blanco para
 * completarlo a mano. El formato oficial está en docs/formatos-sigsa/.
 */

class FormatoSigsa3
{
    /** Filas por cara del formato oficial. */
    const FILAS_POR_HOJA = 20;

    /**
     * Código CIE-10 del control de crecimiento y de la suplementación. Están
     * vacíos hasta que el puesto confirme cuáles escribe; al ponerlos aquí, la
     * hoja los imprime sola. En la vacunación no se escribe un código: en la
     * visita del 16 de octubre de 2026 el puesto explicó que en esa columna
     * anota la vacuna y la dosis (Hexavalente 1a, SPR R1...), y el sistema lo
     * hace igual.
     */
    const CIE10 = [
        'control'    => '',
        'suplemento' => '',
    ];

    /** Papel, en hoja horizontal: nombre y tamaño para la regla @page. */
    const PAPELES = [
        'oficio' => ['Oficio (8.5 x 13 pulgadas)', '13in 8.5in'],
        'carta'  => ['Carta (8.5 x 11 pulgadas)', '11in 8.5in'],
    ];

    /** Clasificaciones del motor que no son un problema de salud. */
    const CLASIFICACIONES_NORMALES = ['Peso adecuado', 'Talla adecuada', 'Peso adecuado para la talla'];

    /**
     * Filtros de la hoja: un día (por omisión, hoy) o el mes completo de ese
     * día, y el papel. Cualquier valor inválido vuelve al valor por omisión.
     */
    public static function filtros(array $get): array
    {
        $hoy = date('Y-m-d');
        $fecha = $get['fecha'] ?? $hoy;
        if (Validacion::fecha($fecha, 'Fecha', '2000-01-01', $hoy) !== null) {
            $fecha = $hoy;
        }
        $mesCompleto = ($get['mes_completo'] ?? '') === '1';
        $papel = isset(self::PAPELES[$get['papel'] ?? '']) ? $get['papel'] : 'oficio';

        $d = new DateTime($fecha);
        return [
            'fecha'        => $fecha,
            'mes_completo' => $mesCompleto,
            'papel'        => $papel,
            'desde'        => $mesCompleto ? $d->format('Y-m-01') : $fecha,
            'hasta'        => $mesCompleto ? $d->format('Y-m-t') : $fecha,
            'mes'          => Cita::MESES[(int)$d->format('n')],
            'anio'         => $d->format('Y'),
        ];
    }

    /** Puesto de salud del usuario, para el encabezado y para filtrar a sus niños. */
    public static function puestoDelUsuario(int $idUsuario): array
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT p.id_puesto, p.nombre, p.distrito, p.area_salud
             FROM usuario u JOIN puesto_salud p ON p.id_puesto = u.id_puesto
             WHERE u.id_usuario = :id"
        );
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetch() ?: ['id_puesto' => 0, 'nombre' => '', 'distrito' => '', 'area_salud' => ''];
    }

    /**
     * Filas del formato: una por niño atendido en cada día del período, con
     * cada celda como la pide el SIGSA 3.
     */
    public static function filas(array $f, array $puesto): array
    {
        $db = Database::obtener();
        $rango = fn(string $sufijo) => [':desde' . $sufijo => $f['desde'], ':hasta' . $sufijo => $f['hasta']];

        // Qué se hizo cada día a cada niño del puesto.
        $atenciones = [];
        $agregar = function (string $sql, array $params, string $tipo) use ($db, &$atenciones) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                $atenciones[$r['fecha'] . '|' . $r['id_nino']][$tipo][] = $r;
            }
        };
        $delPuesto = "JOIN nino n ON n.id_nino = x.id_nino
                      JOIN sector s ON s.id_sector = n.id_sector
                      JOIN territorio t ON t.id_territorio = s.id_territorio AND t.id_puesto = :puesto";

        $agregar("SELECT x.id_medicion, x.id_nino, x.fecha_toma AS fecha FROM medicion x $delPuesto
                  WHERE x.anulado = 0 AND x.fecha_toma BETWEEN :desde1 AND :hasta1 ORDER BY x.id_medicion",
            $rango('1') + [':puesto' => $puesto['id_puesto']], 'mediciones');
        $agregar("SELECT x.id_nino, x.fecha_aplicacion AS fecha, x.nombre_vacuna, x.dosis FROM vacuna_aplicada x $delPuesto
                  WHERE x.anulado = 0 AND x.fecha_aplicacion BETWEEN :desde2 AND :hasta2 ORDER BY x.id_vacuna_aplicada",
            $rango('2') + [':puesto' => $puesto['id_puesto']], 'vacunas');
        $agregar("SELECT x.id_nino, x.fecha_entrega AS fecha, x.tipo, x.cantidad, c.presentacion
                  FROM suplementacion x LEFT JOIN catalogo_suplemento c ON c.nombre = x.tipo $delPuesto
                  WHERE x.anulado = 0 AND x.fecha_entrega BETWEEN :desde3 AND :hasta3 ORDER BY x.id_suplementacion",
            $rango('3') + [':puesto' => $puesto['id_puesto']], 'suplementos');

        // Clasificaciones de cada medición.
        $stmtInd = $db->prepare("SELECT clasificacion FROM indicador WHERE id_medicion = :id ORDER BY tipo");

        $filas = [];
        foreach ($atenciones as $clave => $a) {
            [$fecha, $idNino] = explode('|', $clave);
            $nino = Nino::obtener((int)$idNino);
            if (!$nino) {
                continue;
            }

            $diagnostico = [];
            $cie10 = [];
            foreach ($a['mediciones'] ?? [] as $m) {
                $stmtInd->execute([':id' => $m['id_medicion']]);
                // Sin la aclaración entre paréntesis, para que quepa en la celda:
                // "Retardo de talla (desnutrición crónica)" queda "Retardo de talla".
                $problemas = array_map(fn($c) => preg_replace('/\s*\(.*\)$/u', '', $c),
                    array_diff($stmtInd->fetchAll(PDO::FETCH_COLUMN), self::CLASIFICACIONES_NORMALES));
                $diagnostico[] = 'Control de crecimiento: ' . ($problemas ? implode('; ', $problemas) : 'normal');
                $cie10[] = self::CIE10['control'];
            }
            if (!empty($a['vacunas'])) {
                // Como lo llena el puesto: "Vacunación" en el diagnóstico y la
                // vacuna con su dosis en la columna del código CIE-10.
                $diagnostico[] = 'Vacunación';
                foreach ($a['vacunas'] as $v) {
                    $cie10[] = $v['nombre_vacuna'] . ' ' . $v['dosis'];
                }
            }
            if (!empty($a['suplementos']) && !$diagnostico) {
                $diagnostico[] = 'Suplementación';
                $cie10[] = self::CIE10['suplemento'];
            }

            // Edad a la fecha de la consulta, en la casilla que pide el formato:
            // días si es menor de un mes, meses si es menor de un año, si no años.
            $edad = (new DateTime($nino['fecha_nacimiento']))->diff(new DateTime($fecha));
            $meses = $edad->y * 12 + $edad->m;

            $filas[] = [
                'fecha'        => $fecha,
                'dia'          => (int)substr($fecha, 8, 2),
                // El puesto usa el número de carnet como número de historia clínica.
                'historia'     => $nino['numero_carnet'] ?? '',
                'nombre'       => $nino['nombres'] . ' ' . $nino['apellidos'],
                'cui'          => $nino['cui'] ?? '',
                'sexo'         => $nino['sexo'],
                'pueblo'       => $nino['pueblo'] ?? '',
                'comunidad'    => $nino['comunidad_linguistica'] ?? '',
                'edad_dias'    => $meses === 0 ? $edad->days : '',
                'edad_meses'   => $meses > 0 && $edad->y === 0 ? $meses : '',
                'edad_anios'   => $edad->y > 0 ? $edad->y : '',
                // Para un niño pequeño se sabe con certeza que no aplican.
                'orientacion'  => '0',
                'escolaridad'  => $edad->y < 4 ? '1' : '',
                'profesion'    => '0',
                'control'      => '0',
                'direccion'    => implode(', ', array_filter([$nino['paraje_nombre'], $nino['direccion']])),
                'municipio'    => $puesto['distrito'],
                'diagnostico'  => $diagnostico,
                'cie10'        => array_filter($cie10),
                'tratamiento'  => array_column($a['suplementos'] ?? [], 'tipo'),
                'presentacion' => array_map(fn($s) => $s['presentacion'] ?? '', $a['suplementos'] ?? []),
                'cantidad'     => array_map(fn($s) => $s['cantidad'] ?? '', $a['suplementos'] ?? []),
                'acompanante'  => $nino['nombre_madre'] ?: ($nino['nombre_padre'] ?? ''),
                'apellidos'    => $nino['apellidos'],
            ];
        }

        usort($filas, fn($x, $y) => [$x['fecha'], $x['apellidos'], $x['nombre']] <=> [$y['fecha'], $y['apellidos'], $y['nombre']]);
        return $filas;
    }

    /** Reparte las filas en hojas de 20; la última se completa con filas en blanco. */
    public static function hojas(array $filas): array
    {
        $hojas = array_chunk($filas, self::FILAS_POR_HOJA) ?: [[]];
        $ultima = count($hojas) - 1;
        $hojas[$ultima] = array_pad($hojas[$ultima], self::FILAS_POR_HOJA, null);
        return $hojas;
    }
}
