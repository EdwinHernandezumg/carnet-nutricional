<?php
/**
 * Carnet para la familia: la copia del carnet que se entrega a la madre, al
 * padre o al responsable del niño.
 *
 * El carnet físico le pertenece a la madre. Si el sistema reemplaza al papel,
 * la familia no debe perder el acceso a los datos de su hijo; además, escuelas
 * y hogares comunitarios piden copia del carnet para la inscripción. Esta
 * copia se imprime o se guarda como PDF desde el navegador (sin librerías
 * externas) y lleva firma y sello del puesto.
 *
 * Las gráficas se dibujan aquí como SVG, en el servidor: se imprimen nítidas
 * y no dependen de internet ni de JavaScript.
 */

class CarnetFamilia
{
    /** Las tablas de la OMS cargadas llegan a los 5 años. */
    const MESES_MAXIMOS_GRAFICA = 60;

    /** Reúne todo lo que muestra el carnet. Devuelve null si el niño no existe. */
    public static function datos(int $idNino): ?array
    {
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            return null;
        }

        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT p.nombre, p.distrito, p.area_salud
             FROM puesto_salud p
             JOIN territorio t ON t.id_puesto = p.id_puesto
             JOIN sector s ON s.id_territorio = t.id_territorio
             WHERE s.id_sector = :id_sector"
        );
        $stmt->execute([':id_sector' => $nino['id_sector']]);
        $puesto = $stmt->fetch() ?: ['nombre' => 'Puesto de salud', 'distrito' => null, 'area_salud' => null];

        // Controles de crecimiento, del más antiguo al más reciente, con el
        // estado nutricional que calculó el motor en cada uno.
        $controles = [];
        foreach (array_reverse(Medicion::listarPorNino($idNino)) as $m) {
            $controles[] = [
                'fecha'      => $m['fecha_toma'],
                'edad_meses' => (int)$m['edad_meses'],
                'peso_kg'    => (float)$m['peso_kg'],
                'talla_cm'   => (float)$m['talla_cm'],
                'tipo_talla' => $m['tipo_talla'],
                'nivel'      => Calculo::nivelAlertaGlobal((int)$m['id_medicion'])['nivel'],
            ];
        }

        // Próxima cita programada, si la hay.
        $proximaCita = null;
        foreach (Cita::listarPorNino($idNino) as $c) {
            if ($c['estado'] === 'programada' && $c['fecha_programada'] >= date('Y-m-d')) {
                $proximaCita = $c; // La lista viene de la más lejana a la más cercana.
            }
        }

        return [
            'nino'          => $nino,
            'puesto'        => $puesto,
            'esquema'       => Vacuna::esquemaDelNino($idNino, $nino['fecha_nacimiento']),
            'otras_vacunas' => self::dosisFueraDelEsquema($idNino),
            'controles'     => $controles,
            'suplementos'   => array_reverse(Suplementacion::listarPorNino($idNino)),
            'proxima_cita'  => $proximaCita,
            'graficas'      => $controles ? [
                GraficaCrecimiento::datos($idNino, 'peso_edad'),
                GraficaCrecimiento::datos($idNino, 'talla_edad'),
            ] : [],
        ];
    }

    /**
     * Dosis registradas que no son del esquema vigente (por ejemplo,
     * Pentavalente del carnet de 2018), con la dosis vigente que cubren.
     */
    private static function dosisFueraDelEsquema(int $idNino): array
    {
        $catalogo = [];
        foreach (Vacuna::catalogo() as $c) {
            $catalogo[$c['nombre'] . '|' . $c['dosis']] = $c;
        }
        $porId = array_column(Vacuna::catalogo(), null, 'id_catalogo_vacuna');

        $otras = [];
        foreach (array_reverse(Vacuna::listarPorNino($idNino)) as $v) {
            $c = $catalogo[$v['nombre_vacuna'] . '|' . $v['dosis']] ?? null;
            if ($c !== null && $c['vigente']) {
                continue;
            }
            $equivale = ($c !== null && $c['equivale_a'] !== null) ? $porId[$c['equivale_a']] ?? null : null;
            $otras[] = [
                'vacuna'   => $v['nombre_vacuna'] . ' ' . $v['dosis'],
                'fecha'    => $v['fecha_aplicacion'],
                'equivale' => $equivale ? $equivale['nombre'] . ' ' . $equivale['dosis'] : null,
            ];
        }
        return $otras;
    }

    /** Estado nutricional en palabras para la familia. */
    public static function estadoNutricional(string $nivel): string
    {
        return [
            'normal'               => 'Normal',
            'cerca_umbral'         => 'En vigilancia',
            'riesgo'               => 'Bajo peso',
            'sobrepeso'            => 'Sobrepeso',
            'desnutricion_cronica' => 'Desnutrición crónica (talla baja)',
            'desnutricion_aguda'   => 'Desnutrición aguda',
        ][$nivel] ?? 'Sin dato';
    }

    /**
     * Gráfica de crecimiento en SVG: la franja normal de la OMS (entre -2 y +2
     * desviaciones estándar), la mediana y el recorrido del niño.
     * $g es lo que devuelve GraficaCrecimiento::datos() para un indicador por
     * edad (el eje X son meses).
     */
    public static function graficaSvg(array $g, int $edadMesesActual): string
    {
        if (empty($g['referencia'])) {
            return '';
        }

        // Eje X: desde el nacimiento hasta la edad del niño, redondeada hacia
        // arriba a medio año (mínimo un año, máximo cinco).
        $xMax = (int)min(self::MESES_MAXIMOS_GRAFICA, max(12, ceil(($edadMesesActual + 1) / 6) * 6));
        $ref = array_values(array_filter($g['referencia'], fn($r) => $r['x'] <= $xMax));
        $nino = array_values(array_filter($g['nino'], fn($p) => $p['x'] <= $xMax));

        // Eje Y: lo que cubren las curvas y los puntos, con un margen.
        $valores = array_merge(array_column($ref, 'menos2'), array_column($ref, 'mas2'), array_column($nino, 'y'));
        $yMin = min($valores);
        $yMax = max($valores);
        $paso = self::pasoRedondo(($yMax - $yMin) / 6);
        $yMin = floor($yMin / $paso) * $paso;
        $yMax = ceil($yMax / $paso) * $paso;

        // Área de dibujo.
        $ancho = 520; $alto = 300;
        $izq = 54; $der = 32; $arr = 12; $aba = 46;
        $px = fn(float $x) => $izq + ($x / $xMax) * ($ancho - $izq - $der);
        $py = fn(float $y) => $arr + (1 - ($y - $yMin) / ($yMax - $yMin)) * ($alto - $arr - $aba);
        $n = fn(float $v) => number_format($v, 1, '.', '');

        $svg = '<svg class="grafica-carnet" viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" '
             . 'aria-label="' . htmlspecialchars($g['titulo']) . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<title>' . htmlspecialchars($g['titulo']) . '</title>';

        // Cuadrícula y números de los ejes.
        $pasoX = $xMax <= 24 ? 3 : 6;
        for ($x = 0; $x <= $xMax; $x += $pasoX) {
            $svg .= '<line x1="' . $n($px($x)) . '" y1="' . $arr . '" x2="' . $n($px($x)) . '" y2="' . ($alto - $aba)
                  . '" stroke="#e3e9ef" stroke-width="1"/>';
            $svg .= '<text x="' . $n($px($x)) . '" y="' . ($alto - $aba + 17) . '" font-size="13" text-anchor="middle" fill="#55657a">'
                  . $x . '</text>';
        }
        for ($y = $yMin; $y <= $yMax + 0.0001; $y += $paso) {
            $svg .= '<line x1="' . $izq . '" y1="' . $n($py($y)) . '" x2="' . ($ancho - $der) . '" y2="' . $n($py($y))
                  . '" stroke="#e3e9ef" stroke-width="1"/>';
            $svg .= '<text x="' . ($izq - 6) . '" y="' . $n($py($y) + 4.5) . '" font-size="13" text-anchor="end" fill="#55657a">'
                  . (floor($y) == $y ? (int)$y : $n($y)) . '</text>';
        }

        // Franja normal entre -2 y +2, y las tres curvas.
        $arriba = array_map(fn($r) => $n($px($r['x'])) . ',' . $n($py($r['mas2'])), $ref);
        $abajo  = array_map(fn($r) => $n($px($r['x'])) . ',' . $n($py($r['menos2'])), array_reverse($ref));
        $svg .= '<polygon points="' . implode(' ', array_merge($arriba, $abajo)) . '" fill="#1d9e75" fill-opacity="0.10"/>';
        foreach (['mas2' => '#d9822b', 'menos2' => '#d9822b', 'mediana' => '#1d9e75'] as $clave => $color) {
            $puntos = array_map(fn($r) => $n($px($r['x'])) . ',' . $n($py($r[$clave])), $ref);
            $svg .= '<polyline points="' . implode(' ', $puntos) . '" fill="none" stroke="' . $color
                  . '" stroke-width="' . ($clave === 'mediana' ? 2 : 1.5) . '"/>';
            $ultimo = end($ref);
            $svg .= '<text x="' . ($ancho - $der + 4) . '" y="' . $n($py($ultimo[$clave]) + 4.5)
                  . '" font-size="13" fill="' . $color . '">' . ['mas2' => '+2', 'menos2' => '-2', 'mediana' => '0'][$clave] . '</text>';
        }

        // Recorrido del niño.
        if ($nino) {
            $puntos = array_map(fn($p) => $n($px($p['x'])) . ',' . $n($py($p['y'])), $nino);
            if (count($puntos) > 1) {
                $svg .= '<polyline points="' . implode(' ', $puntos) . '" fill="none" stroke="#14304a" stroke-width="2"/>';
            }
            foreach ($nino as $p) {
                $svg .= '<circle cx="' . $n($px($p['x'])) . '" cy="' . $n($py($p['y'])) . '" r="4" fill="#14304a">'
                      . '<title>' . htmlspecialchars($p['fecha'] . ': ' . $p['y']) . '</title></circle>';
            }
        }

        // Nombres de los ejes.
        $svg .= '<text x="' . (($izq + $ancho - $der) / 2) . '" y="' . ($alto - 6) . '" font-size="13" text-anchor="middle" fill="#2b3947">'
              . htmlspecialchars($g['eje_x']) . '</text>';
        $svg .= '<text transform="translate(12 ' . (($arr + $alto - $aba) / 2) . ') rotate(-90)" font-size="13" text-anchor="middle" fill="#2b3947">'
              . htmlspecialchars($g['eje_y']) . '</text>';

        return $svg . '</svg>';
    }

    /** Paso "redondo" para los números del eje: 0.5, 1, 2, 5, 10, 20... */
    private static function pasoRedondo(float $aproximado): float
    {
        $base = pow(10, floor(log10(max($aproximado, 0.1))));
        foreach ([1, 2, 5, 10] as $f) {
            if ($f * $base >= $aproximado) {
                return $f * $base;
            }
        }
        return 10 * $base;
    }
}
