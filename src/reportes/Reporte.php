<?php
/**
 * Panel de indicadores y reportes (CU-07): inteligencia de negocios del puesto.
 *
 * Responde a una carencia documentada en el levantamiento: el sistema
 * institucional en uso (SIGSA Web) no genera reportes utilizables por el
 * puesto, y la nutricionista necesita consolidados para planificar.
 *
 * Toda la información sale de un solo conjunto de datos, ninos(): un renglón
 * por niño activo con su última medición DENTRO del período elegido. De ahí se
 * calculan las tarjetas, las gráficas y las exportaciones (PDF, Excel y CSV),
 * así las cifras coinciden siempre en todos los formatos.
 */

class Reporte
{
    /**
     * Grupos de edad (meses cumplidos a hoy): [clave => [nombre, desde, hasta)].
     * Son los cortes usuales en salud infantil. PENDIENTE: confirmarlos con el
     * puesto o con los formatos de SIGSA; si cambian, solo se ajusta aquí.
     */
    const GRUPOS_EDAD = [
        '0-5'   => ['0 a 5 meses', 0, 6],
        '6-11'  => ['6 a 11 meses', 6, 12],
        '12-23' => ['12 a 23 meses', 12, 24],
        '24-59' => ['24 a 59 meses', 24, 60],
        '60-83' => ['5 a 6 años', 60, 84],
    ];

    /**
     * Frecuencia de control según la normativa (dato de la enfermera):
     * [desde el mes, hasta antes del mes, cada cuántos meses]. De 5 a 7 años
     * solo hay control de vacunación, así que no se cuentan como atrasados.
     */
    const FRECUENCIA_CONTROL = [
        [0, 24, 1],
        [24, 36, 3],
        [36, 60, 6],
    ];

    /** Días de tolerancia antes de considerar un control como atrasado. */
    const DIAS_GRACIA = 7;

    /** Niveles (la condición más grave de cada niño), de más a menos grave. */
    const NIVELES = [
        'desnutricion_aguda', 'desnutricion_cronica', 'riesgo', 'sobrepeso',
        'cerca_umbral', 'normal', 'sin_control',
    ];

    /** Condiciones contadas por separado (un niño puede tener varias). */
    const CONDICIONES = [
        'aguda'     => 'Desnutrición aguda',
        'cronica'   => 'Desnutrición crónica',
        'bajo_peso' => 'Bajo peso',
        'sobrepeso' => 'Sobrepeso',
        'cerca'     => 'Cerca del umbral',
    ];

    /**
     * Períodos rápidos del filtro: [clave => nombre]. "Estado actual" (el que
     * se usa por omisión) toma el último control de cada niño, sea de cuando
     * sea: es la misma base del panel de seguimiento, así las cifras de los
     * dos paneles coinciden. Los demás sirven para analizar un período.
     */
    const PERIODOS = [
        'actual' => 'Estado actual (último control de cada niño)',
        'mes'    => 'Este mes',
        '3m'     => 'Últimos 3 meses',
        '12m'    => 'Últimos 12 meses',
        'anio'   => 'Este año',
    ];

    /** Inicio del período "Estado actual": antes de que naciera cualquier niño activo. */
    const INICIO_ESTADO_ACTUAL = '2000-01-01';

    // ---------------------------------------------------------------------
    // Filtros
    // ---------------------------------------------------------------------

    /**
     * Lee y valida los filtros que llegan en la dirección. Cualquier valor
     * que no sea válido se ignora (se usa el valor por omisión).
     */
    public static function filtros(array $get): array
    {
        $hoy = date('Y-m-d');

        // Período: un período rápido, o fechas desde/hasta elegidas. Si no se
        // indica nada (o las fechas no son válidas), "Estado actual".
        $desde = $get['desde'] ?? '';
        $hasta = $get['hasta'] ?? '';
        $fechasElegidas = !isset($get['periodo']) || $get['periodo'] === '';
        $periodo = $fechasElegidas && ($desde !== '' || $hasta !== '') ? '' : ($get['periodo'] ?? 'actual');
        if (isset(self::PERIODOS[$periodo])) {
            [$desde, $hasta] = self::rangoDePeriodo($periodo);
        } else {
            $periodo = '';
            if (Validacion::fecha($hasta, 'hasta', null, $hoy) !== null) {
                $hasta = $hoy;
            }
            if (Validacion::fecha($desde, 'desde', null, $hasta) !== null) {
                $periodo = 'actual';
                [$desde, $hasta] = self::rangoDePeriodo('actual');
            }
        }

        $sectores = Catalogo::sectores();
        $parajes  = Catalogo::parajes();
        $f = [
            'periodo'       => $periodo,
            'desde'         => $desde,
            'hasta'         => $hasta,
            'id_territorio' => self::valorDeLista($get['id_territorio'] ?? '',
                                   array_column(self::territorios(), 'id_territorio')),
            'id_sector'     => self::valorDeLista($get['id_sector'] ?? '', array_column($sectores, 'id_sector')),
            'id_paraje'     => self::valorDeLista($get['id_paraje'] ?? '', array_column($parajes, 'id_paraje')),
            'sexo'          => self::valorDeLista($get['sexo'] ?? '', ['M', 'F']),
            'grupo_edad'    => self::valorDeLista($get['grupo_edad'] ?? '', array_keys(self::GRUPOS_EDAD)),
        ];
        return $f;
    }

    /** Fechas [desde, hasta] de un período rápido. */
    private static function rangoDePeriodo(string $periodo): array
    {
        $hoy = date('Y-m-d');
        switch ($periodo) {
            case 'actual': return [self::INICIO_ESTADO_ACTUAL, $hoy];
            case 'mes':  return [date('Y-m-01'), $hoy];
            case '12m':  return [date('Y-m-d', strtotime('-12 months +1 day')), $hoy];
            case 'anio': return [date('Y-01-01'), $hoy];
            default:     return [date('Y-m-d', strtotime('-3 months +1 day')), $hoy];
        }
    }

    private static function valorDeLista($valor, array $permitidos): string
    {
        return in_array((string) $valor, array_map('strval', $permitidos), true) ? (string) $valor : '';
    }

    /** Parámetros de la dirección que conservan los filtros (para enlaces y exportar). */
    public static function parametros(array $f): array
    {
        $p = array_filter([
            'id_territorio' => $f['id_territorio'],
            'id_sector'     => $f['id_sector'],
            'id_paraje'     => $f['id_paraje'],
            'sexo'          => $f['sexo'],
            'grupo_edad'    => $f['grupo_edad'],
        ], fn($v) => $v !== '');
        if ($f['periodo'] !== '') {
            $p['periodo'] = $f['periodo'];
        } else {
            $p['desde'] = $f['desde'];
            $p['hasta'] = $f['hasta'];
        }
        return $p;
    }

    /** Descripción de los filtros en texto, para los encabezados de PDF, Excel y CSV. */
    public static function descripcionFiltros(array $f): string
    {
        $partes = [$f['periodo'] === 'actual'
            ? 'Estado actual (último control de cada niño)'
            : 'Período: ' . Formato::fechaTexto($f['desde']) . ' a ' . Formato::fechaTexto($f['hasta'])];
        foreach (self::territorios() as $t) {
            if ((string) $t['id_territorio'] === $f['id_territorio']) {
                $partes[] = 'Territorio ' . $t['numero'] . ' (' . $t['nombre'] . ')';
            }
        }
        foreach (Catalogo::sectores() as $s) {
            if ((string) $s['id_sector'] === $f['id_sector']) {
                $partes[] = 'Sector ' . $s['letra'] . ' del territorio ' . $s['territorio'];
            }
        }
        foreach (Catalogo::parajes() as $p) {
            if ((string) $p['id_paraje'] === $f['id_paraje']) {
                $partes[] = 'Paraje ' . $p['nombre'];
            }
        }
        if ($f['sexo'] !== '') {
            $partes[] = $f['sexo'] === 'M' ? 'Niños' : 'Niñas';
        }
        if ($f['grupo_edad'] !== '') {
            $partes[] = self::GRUPOS_EDAD[$f['grupo_edad']][0];
        }
        return implode(' · ', $partes);
    }

    /** Territorios, para el filtro. */
    public static function territorios(): array
    {
        $db = Database::obtener();
        return $db->query("SELECT id_territorio, numero, nombre FROM territorio ORDER BY numero")->fetchAll();
    }

    // ---------------------------------------------------------------------
    // Datos: un renglón por niño
    // ---------------------------------------------------------------------

    /**
     * Niños activos que cumplen los filtros, cada uno con su última medición
     * dentro del período, sus puntuaciones Z, su nivel (condición más grave),
     * sus condiciones por separado y si tiene el control atrasado.
     */
    public static function ninos(array $f): array
    {
        $db = Database::obtener();

        $where = ["n.estado = 'activo'"];
        $params = [':desde' => $f['desde'], ':hasta' => $f['hasta']];
        if ($f['id_territorio'] !== '') { $where[] = 't.id_territorio = :territorio'; $params[':territorio'] = $f['id_territorio']; }
        if ($f['id_sector'] !== '')     { $where[] = 'n.id_sector = :sector';         $params[':sector'] = $f['id_sector']; }
        if ($f['id_paraje'] !== '')     { $where[] = 'n.id_paraje = :paraje';         $params[':paraje'] = $f['id_paraje']; }
        if ($f['sexo'] !== '')          { $where[] = 'n.sexo = :sexo';                $params[':sexo'] = $f['sexo']; }

        // Última medición del niño dentro del período, y último control de todos.
        $sql = "SELECT n.id_nino, n.nombres, n.apellidos, n.sexo, n.fecha_nacimiento,
                       n.id_sector, s.letra AS sector, t.id_territorio, t.numero AS territorio,
                       n.id_paraje, p.nombre AS paraje,
                       m.id_medicion, m.fecha_toma,
                       (SELECT MAX(m4.fecha_toma) FROM medicion m4 WHERE m4.id_nino = n.id_nino) AS ultimo_control
                FROM nino n
                JOIN sector s     ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                LEFT JOIN medicion m ON m.id_medicion = (
                    SELECT m2.id_medicion FROM medicion m2
                    WHERE m2.id_nino = n.id_nino AND m2.fecha_toma BETWEEN :desde AND :hasta
                    ORDER BY m2.fecha_toma DESC, m2.id_medicion DESC
                    LIMIT 1
                )
                WHERE " . implode(' AND ', $where) . "
                ORDER BY n.apellidos, n.nombres";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        // Puntuaciones Z y nivel de alerta de esas mediciones, en dos consultas.
        $ids = array_values(array_filter(array_column($filas, 'id_medicion')));
        $zetas = self::porMedicion("SELECT id_medicion, tipo, puntuacion_z FROM indicador WHERE id_medicion IN (%s)", $ids);
        $niveles = self::porMedicion("SELECT id_medicion, nivel FROM alerta WHERE id_medicion IN (%s)", $ids);

        $hoy = new DateTime('today');
        $ninos = [];
        foreach ($filas as $r) {
            $edadMeses = (new DateTime($r['fecha_nacimiento']))->diff($hoy);
            $edadMeses = $edadMeses->y * 12 + $edadMeses->m;
            $grupo = self::grupoDeEdad($edadMeses);
            if ($f['grupo_edad'] !== '' && $grupo !== $f['grupo_edad']) {
                continue;
            }

            $z = ['peso_edad' => null, 'talla_edad' => null, 'peso_talla' => null];
            foreach ($zetas[$r['id_medicion']] ?? [] as $ind) {
                $clave = in_array($ind['tipo'], ['peso_longitud', 'peso_talla'], true) ? 'peso_talla' : $ind['tipo'];
                $z[$clave] = (float) $ind['puntuacion_z'];
            }

            if ($r['id_medicion'] === null) {
                $nivel = 'sin_control';
            } else {
                $nivel = $niveles[$r['id_medicion']][0]['nivel'] ?? 'normal';
            }

            $ninos[] = [
                'id_nino'       => (int) $r['id_nino'],
                'nombre'        => $r['apellidos'] . ', ' . $r['nombres'],
                'sexo'          => $r['sexo'],
                'edad_meses'    => $edadMeses,
                'edad_texto'    => Formato::edad($r['fecha_nacimiento']),
                'grupo_edad'    => $grupo,
                'id_territorio' => (int) $r['id_territorio'],
                'territorio'    => $r['territorio'],
                'id_sector'     => (int) $r['id_sector'],
                'sector'        => 'T' . $r['territorio'] . ' · ' . $r['sector'],
                'id_paraje'     => $r['id_paraje'] !== null ? (int) $r['id_paraje'] : null,
                'paraje'        => $r['paraje'],
                'fecha_control' => $r['fecha_toma'],
                'z'             => $z,
                'nivel'         => $nivel,
                'condiciones'   => self::condiciones($z),
                'atrasado'      => self::controlAtrasado($edadMeses, $r['ultimo_control'], $hoy),
            ];
        }
        return $ninos;
    }

    /**
     * Ejecuta una consulta "... IN (%s)" con una lista de ids y agrupa las
     * filas por id_medicion. Cada id va en su propio marcador de posición.
     */
    private static function porMedicion(string $sql, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $marcadores = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $marcadores[] = ':id' . $i;
            $params[':id' . $i] = (int) $id;
        }
        $stmt = Database::obtener()->prepare(sprintf($sql, implode(', ', $marcadores)));
        $stmt->execute($params);
        $agrupado = [];
        foreach ($stmt->fetchAll() as $fila) {
            $agrupado[$fila['id_medicion']][] = $fila;
        }
        return $agrupado;
    }

    /** Clave del grupo de edad para una edad en meses cumplidos. */
    public static function grupoDeEdad(int $meses): string
    {
        foreach (self::GRUPOS_EDAD as $clave => [, $desde, $hasta]) {
            if ($meses >= $desde && $meses < $hasta) {
                return $clave;
            }
        }
        return '';
    }

    /**
     * Condiciones por separado, con los mismos puntos de corte del motor
     * (Calculo::nivelAlertaGlobal): un niño puede tener varias a la vez.
     */
    private static function condiciones(array $z): array
    {
        $pt = $z['peso_talla']; $te = $z['talla_edad']; $pe = $z['peso_edad'];
        $cerca = fn($v) => $v !== null && $v >= -2 && $v < -1;
        return [
            'aguda'     => $pt !== null && $pt < -2,
            'cronica'   => $te !== null && $te < -2,
            'bajo_peso' => $pe !== null && $pe < -2,
            'sobrepeso' => $pt !== null && $pt > 2,
            'cerca'     => $cerca($pt) || $cerca($te) || $cerca($pe),
        ];
    }

    /**
     * ¿Le toca control y no lo ha tenido? Según la frecuencia de la normativa
     * para su edad, más unos días de tolerancia. Devuelve null de 5 años en
     * adelante (solo control de vacunación).
     */
    private static function controlAtrasado(int $edadMeses, ?string $ultimoControl, DateTime $hoy): ?bool
    {
        foreach (self::FRECUENCIA_CONTROL as [$desde, $hasta, $cadaMeses]) {
            if ($edadMeses >= $desde && $edadMeses < $hasta) {
                if ($ultimoControl === null) {
                    return true;
                }
                $dias = (new DateTime($ultimoControl))->diff($hoy)->days;
                return $dias > $cadaMeses * Calculo::DIAS_POR_MES + self::DIAS_GRACIA;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // Indicadores para el panel
    // ---------------------------------------------------------------------

    /** Tarjetas, gráficas por sector, paraje, nivel y condición. */
    public static function resumen(array $ninos, array $f): array
    {
        $evaluados = array_filter($ninos, fn($n) => $n['nivel'] !== 'sin_control');
        $total = count($ninos);

        // Por nivel (la condición más grave de cada niño)
        $porNivel = array_fill_keys(self::NIVELES, 0);
        foreach ($ninos as $n) {
            $porNivel[$n['nivel']]++;
        }

        // Tarjetas. Las de estado nutricional cuentan a cada niño UNA vez, por
        // su condición más grave: es la misma regla del panel de seguimiento,
        // así las cifras de los dos paneles coinciden y siempre suman el total.
        $tarjetas = [
            'activos'      => $total,
            'evaluados'    => count($evaluados),
            'porcentaje_evaluados' => $total ? round(count($evaluados) * 100 / $total) : null,
            'atrasados'    => count(array_filter($ninos, fn($n) => $n['atrasado'] === true)),
            'inasistencia' => self::porcentajeInasistencia($ninos, $f),
            'desnutricion' => $porNivel['desnutricion_aguda'] + $porNivel['desnutricion_cronica'],
            'cerca_umbral' => $porNivel['cerca_umbral'],
            'riesgo'       => $porNivel['riesgo'],
            'sobrepeso'    => $porNivel['sobrepeso'],
        ];

        // Por sector: todos los sectores dentro de los filtros de lugar, aunque tengan 0.
        $porSector = [];
        foreach (Catalogo::sectores() as $s) {
            if (($f['id_sector'] !== '' && (string) $s['id_sector'] !== $f['id_sector'])) continue;
            if ($f['id_territorio'] !== '' && !self::sectorEnTerritorio($s, $f['id_territorio'])) continue;
            $porSector[$s['id_sector']] = ['id_sector' => (int) $s['id_sector'],
                'nombre' => 'T' . $s['territorio'] . ' · ' . $s['letra'], 'total' => 0]
                + array_fill_keys(self::NIVELES, 0);
        }
        foreach ($ninos as $n) {
            if (isset($porSector[$n['id_sector']])) {
                $porSector[$n['id_sector']]['total']++;
                $porSector[$n['id_sector']][$n['nivel']]++;
            }
        }

        // Por paraje: niños con alguna alerta (nivel distinto de normal y sin control).
        $porParaje = [];
        foreach ($ninos as $n) {
            $clave = $n['id_paraje'] ?? 0;
            if (!isset($porParaje[$clave])) {
                $porParaje[$clave] = ['id_paraje' => $n['id_paraje'], 'id_sector' => $n['id_sector'],
                    'nombre' => $n['paraje'] ?? 'Sin paraje', 'total' => 0, 'con_alerta' => 0];
            }
            $porParaje[$clave]['total']++;
            if (!in_array($n['nivel'], ['normal', 'sin_control'], true)) {
                $porParaje[$clave]['con_alerta']++;
            }
        }
        $porParaje = array_values(array_filter($porParaje, fn($p) => $p['con_alerta'] > 0));
        usort($porParaje, fn($a, $b) => [$b['con_alerta'], $a['nombre']] <=> [$a['con_alerta'], $b['nombre']]);

        // Cada condición por separado (entre los evaluados): un niño puede
        // contar en varias. Solo se usa en su propia gráfica, con ese aviso.
        $porCondicion = [];
        foreach (array_keys(self::CONDICIONES) as $c) {
            $porCondicion[$c] = count(array_filter($evaluados, fn($n) => $n['condiciones'][$c]));
        }

        return [
            'tarjetas'     => $tarjetas,
            'por_nivel'    => $porNivel,
            'por_sector'   => array_values($porSector),
            'por_paraje'   => $porParaje,
            'por_condicion'=> $porCondicion,
        ];
    }

    private static function sectorEnTerritorio(array $sector, string $idTerritorio): bool
    {
        foreach (self::territorios() as $t) {
            if ((string) $t['id_territorio'] === $idTerritorio) {
                return (string) $t['numero'] === (string) $sector['territorio'];
            }
        }
        return false;
    }

    /**
     * Porcentaje de inasistencia a citas en el período, entre los niños del
     * reporte: inasistencias / (cumplidas + inasistencias). Null si no hubo.
     */
    private static function porcentajeInasistencia(array $ninos, array $f): ?int
    {
        $ids = array_column($ninos, 'id_nino');
        if (!$ids) {
            return null;
        }
        $marcadores = [];
        $params = [':desde' => $f['desde'], ':hasta' => $f['hasta']];
        foreach ($ids as $i => $id) {
            $marcadores[] = ':n' . $i;
            $params[':n' . $i] = $id;
        }
        $stmt = Database::obtener()->prepare(
            "SELECT SUM(estado = 'inasistencia') AS faltas, SUM(estado IN ('cumplida', 'inasistencia')) AS atendidas
             FROM cita
             WHERE fecha_programada BETWEEN :desde AND :hasta
               AND id_nino IN (" . implode(', ', $marcadores) . ")"
        );
        $stmt->execute($params);
        $r = $stmt->fetch();
        return ((int) $r['atendidas']) > 0 ? (int) round($r['faltas'] * 100 / $r['atendidas']) : null;
    }

    /**
     * Tendencia de los últimos 12 meses (hasta el mes de "hasta"), con los
     * mismos niños del reporte: por mes, de los niños medidos ese mes (su
     * última medición del mes), qué porcentaje tiene desnutrición o bajo peso,
     * cuál está cerca del umbral y cuál tiene sobrepeso.
     */
    public static function tendencia(array $ninos, array $f): array
    {
        $fin = new DateTime(substr($f['hasta'], 0, 7) . '-01');
        $inicio = (clone $fin)->modify('-11 months');
        $meses = [];
        for ($m = clone $inicio; $m <= $fin; $m->modify('+1 month')) {
            $meses[$m->format('Y-m')] = ['mes' => $m->format('Y-m'),
                'etiqueta' => substr(Cita::MESES[(int) $m->format('n')], 0, 3) . ' ' . $m->format('Y'),
                'evaluados' => 0, 'desnutricion' => 0, 'cerca' => 0, 'sobrepeso' => 0];
        }

        $ids = array_column($ninos, 'id_nino');
        if ($ids) {
            $marcadores = [];
            $params = [':inicio' => $inicio->format('Y-m-d'), ':fin' => $fin->format('Y-m-t')];
            foreach ($ids as $i => $id) {
                $marcadores[] = ':n' . $i;
                $params[':n' . $i] = $id;
            }
            // Mediciones de esos niños en los 12 meses, con su nivel de alerta.
            $stmt = Database::obtener()->prepare(
                "SELECT m.id_nino, m.fecha_toma, COALESCE(a.nivel, 'normal') AS nivel
                 FROM medicion m
                 LEFT JOIN alerta a ON a.id_medicion = m.id_medicion
                 WHERE m.fecha_toma BETWEEN :inicio AND :fin
                   AND m.id_nino IN (" . implode(', ', $marcadores) . ")
                 ORDER BY m.fecha_toma, m.id_medicion"
            );
            $stmt->execute($params);
            // La última medición de cada niño en cada mes (las posteriores reemplazan).
            $ultimas = [];
            foreach ($stmt->fetchAll() as $r) {
                $ultimas[substr($r['fecha_toma'], 0, 7)][$r['id_nino']] = $r['nivel'];
            }
            foreach ($ultimas as $mes => $porNino) {
                if (!isset($meses[$mes])) continue;
                foreach ($porNino as $nivel) {
                    $meses[$mes]['evaluados']++;
                    if (in_array($nivel, ['desnutricion_aguda', 'desnutricion_cronica', 'riesgo'], true)) {
                        $meses[$mes]['desnutricion']++;
                    } elseif ($nivel === 'cerca_umbral') {
                        $meses[$mes]['cerca']++;
                    } elseif ($nivel === 'sobrepeso') {
                        $meses[$mes]['sobrepeso']++;
                    }
                }
            }
        }
        return array_values($meses);
    }

    /** Nombre legible de un nivel, incluido "sin control". */
    public static function etiquetaNivel(string $nivel): string
    {
        return $nivel === 'sin_control' ? 'Sin control' : Alerta::etiquetaNivel($nivel);
    }

    // ---------------------------------------------------------------------
    // Exportación (CSV y Excel). El PDF es la vista de impresión del panel.
    // ---------------------------------------------------------------------

    /** Lista de niños para exportar: encabezado y una fila por niño. */
    public static function filasNinos(array $ninos): array
    {
        $filas = [[
            'Territorio', 'Sector', 'Paraje', 'Niño', 'Sexo', 'Edad (meses)', 'Grupo de edad',
            'Último control', 'P/E (Z)', 'T/E (Z)', 'P/T (Z)', 'Condición más grave',
            'Desnutrición aguda', 'Desnutrición crónica', 'Bajo peso', 'Sobrepeso', 'Cerca del umbral',
            'Control atrasado',
        ]];
        $siNo = fn(bool $v) => $v ? 'Sí' : 'No';
        foreach ($ninos as $n) {
            $filas[] = [
                'T' . $n['territorio'], $n['sector'], $n['paraje'] ?? 'Sin paraje', $n['nombre'],
                $n['sexo'] === 'M' ? 'Masculino' : 'Femenino', $n['edad_meses'],
                self::GRUPOS_EDAD[$n['grupo_edad']][0] ?? '',
                $n['fecha_control'] ? Formato::fechaTexto($n['fecha_control']) : 'Sin control',
                $n['z']['peso_edad'], $n['z']['talla_edad'], $n['z']['peso_talla'],
                self::etiquetaNivel($n['nivel']),
                $siNo($n['condiciones']['aguda']), $siNo($n['condiciones']['cronica']),
                $siNo($n['condiciones']['bajo_peso']), $siNo($n['condiciones']['sobrepeso']),
                $siNo($n['condiciones']['cerca']),
                $n['atrasado'] === null ? 'No aplica (5 años o más)' : $siNo($n['atrasado']),
            ];
        }
        return $filas;
    }

    /**
     * Descarga la lista de niños en CSV. Separador punto y coma y BOM UTF-8,
     * para que Excel en español la abra con columnas y tildes correctas.
     */
    public static function descargarCsv(array $ninos, array $f): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ninos_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');

        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        $escribir = fn(array $fila) => fputcsv($salida, array_map([self::class, 'celdaSegura'], $fila), ';');
        $escribir([APP_NOMBRE . ' · Puesto de Salud Chivarreto, San Francisco El Alto, Totonicapán']);
        $escribir(['Lista de niños del panel de indicadores']);
        $escribir([self::descripcionFiltros($f)]);
        $escribir(['Generado el ' . date('d-m-Y H:i')]);
        $escribir([]);
        foreach (self::filasNinos($ninos) as $fila) {
            $escribir($fila);
        }
        fclose($salida);
        exit;
    }

    /**
     * Evita la "inyección de fórmulas" en CSV: un texto que empieza con
     * = + - @ podría ejecutarse como fórmula al abrirlo en Excel. Se le
     * antepone un apóstrofo. Los números se dejan como números.
     */
    public static function celdaSegura($valor)
    {
        if (is_string($valor) && $valor !== '' && strpbrk($valor[0], "=+-@\t\r") !== false) {
            return "'" . $valor;
        }
        return $valor;
    }

    /** Descarga el libro de Excel con varias hojas: resumen, gráficas y lista de niños. */
    public static function descargarExcel(array $ninos, array $resumen, array $tendencia, array $f): void
    {
        $t = $resumen['tarjetas'];
        $porcentaje = fn($parte, $total) => $total ? round($parte * 100 / $total, 1) : null;
        $libro = new LibroExcel();

        $hojaResumen = [
            ['Indicador', 'Valor'],
            ['Niños activos', $t['activos']],
            ['Con control (evaluados)', $t['evaluados']],
            ['Porcentaje con control', $t['porcentaje_evaluados']],
            ['Controles atrasados', $t['atrasados']],
            ['Desnutrición, aguda o crónica (condición más grave)', $t['desnutricion']],
            ['Cerca del umbral (condición más grave)', $t['cerca_umbral']],
            ['Riesgo, bajo peso (condición más grave)', $t['riesgo']],
            ['Sobrepeso (condición más grave)', $t['sobrepeso']],
            ['Inasistencia a citas (%)', $t['inasistencia'] ?? 'Sin citas'],
        ];
        $hojaResumen[] = [];
        $hojaResumen[] = [self::descripcionFiltros($f)];
        $hojaResumen[] = ['Generado el ' . date('d-m-Y H:i')];
        $libro->agregarHoja('Resumen', $hojaResumen);

        $encabezado = ['Sector', 'Total'];
        foreach (self::NIVELES as $nivel) {
            $encabezado[] = self::etiquetaNivel($nivel);
        }
        $hojaSector = [$encabezado];
        foreach ($resumen['por_sector'] as $s) {
            $fila = [$s['nombre'], $s['total']];
            foreach (self::NIVELES as $nivel) {
                $fila[] = $s[$nivel];
            }
            $hojaSector[] = $fila;
        }
        $libro->agregarHoja('Por sector', $hojaSector);

        $hojaParaje = [['Paraje', 'Niños', 'Con alerta', 'Porcentaje con alerta']];
        foreach ($resumen['por_paraje'] as $p) {
            $hojaParaje[] = [$p['nombre'], $p['total'], $p['con_alerta'], $porcentaje($p['con_alerta'], $p['total'])];
        }
        $libro->agregarHoja('Por paraje', $hojaParaje);

        $hojaCondicion = [['Condición más grave de cada niño', 'Niños']];
        foreach ($resumen['por_nivel'] as $nivel => $cantidad) {
            $hojaCondicion[] = [self::etiquetaNivel($nivel), $cantidad];
        }
        $hojaCondicion[] = [];
        $hojaCondicion[] = ['Cada condición por separado (un niño puede tener varias)', 'Niños'];
        foreach (self::CONDICIONES as $c => $nombre) {
            $hojaCondicion[] = [$nombre, $resumen['por_condicion'][$c]];
        }
        $libro->agregarHoja('Por condición', $hojaCondicion);

        $hojaTendencia = [['Mes', 'Evaluados', 'Desnutrición o bajo peso', '%', 'Cerca del umbral', '%', 'Sobrepeso', '%']];
        foreach ($tendencia as $m) {
            $hojaTendencia[] = [$m['etiqueta'], $m['evaluados'],
                $m['desnutricion'], $porcentaje($m['desnutricion'], $m['evaluados']),
                $m['cerca'], $porcentaje($m['cerca'], $m['evaluados']),
                $m['sobrepeso'], $porcentaje($m['sobrepeso'], $m['evaluados'])];
        }
        $libro->agregarHoja('Tendencia', $hojaTendencia);

        $libro->agregarHoja('Niños', self::filasNinos($ninos));
        $libro->descargar('reporte_nutricional_' . date('Y-m-d'));
    }
}
