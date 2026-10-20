<?php
/**
 * Carnet para la familia: copia del carnet del niño para la madre, el padre o
 * el responsable. En el teléfono se lee en la pantalla; al imprimir sale en
 * hoja carta (o se guarda como PDF y se comparte por WhatsApp).
 * Variables: $carnet (CarnetFamilia::datos), $nino
 */
$puesto = $carnet['puesto'];
$edadMeses = Nino::edadEnMeses($nino['fecha_nacimiento']);

$pueblo = Catalogo::PUEBLOS[(int)$nino['pueblo']] ?? null;
if ($pueblo !== null && $nino['comunidad_linguistica'] !== null) {
    $pueblo .= ', ' . (Catalogo::COMUNIDADES_LINGUISTICAS[(int)$nino['comunidad_linguistica']] ?? '');
}

// Un dato de la ficha: el valor protegido, o "Sin dato" en gris.
$dato = fn(?string $valor) => Formato::dato($valor);

$pendientes = count(array_filter($carnet['esquema'], fn($e) => $e['estado'] === 'pendiente'));
$cita = $carnet['proxima_cita'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carnet de <?= htmlspecialchars($nino['nombres'] . ' ' . $nino['apellidos']) ?> · Carnet Digital</title>
    <style>
        @page { size: letter; margin: 1.2cm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif; color: #1f2d3a; margin: 0;
            background: #eef3f6; font-size: 15px; line-height: 1.4;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .barra-pantalla {
            max-width: 820px; margin: 0 auto; padding: 14px 16px 0;
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        }
        .barra-pantalla button, .barra-pantalla a {
            font: inherit; font-weight: 700; border-radius: 8px; padding: 10px 16px;
            text-decoration: none; cursor: pointer; min-height: 46px;
            display: inline-flex; align-items: center;
        }
        .barra-pantalla button { background: #1d9e75; color: #fff; border: none; }
        .barra-pantalla a { background: #fff; color: #0f6e56; border: 1.5px solid #b5ddce; }
        .barra-pantalla p { flex-basis: 100%; margin: 4px 0 0; font-size: 13px; color: #55657a; }

        .hoja {
            max-width: 820px; margin: 12px auto 24px; background: #fff; padding: 20px 16px;
            border-radius: 10px; box-shadow: 0 2px 12px rgba(20, 48, 74, 0.08);
        }
        .cabecera {
            display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px;
            align-items: flex-end; border-bottom: 3px solid #1d9e75; padding-bottom: 10px; margin-bottom: 14px;
        }
        .cabecera .puesto { font-weight: 700; color: #14304a; font-size: 16px; }
        .cabecera .lugar { font-size: 12.5px; color: #55657a; }
        .cabecera h1 { margin: 0; font-size: 26px; color: #1d9e75; line-height: 1.1; }
        .cabecera .sub { font-size: 13px; color: #14304a; font-weight: 700; }

        h2 {
            font-size: 16px; color: #fff; background: #14304a; margin: 18px 0 8px;
            padding: 6px 10px; border-radius: 6px;
        }
        .ficha { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
        .ficha div { border-bottom: 1px solid #dfe6ee; padding: 3px 0; min-width: 0; }
        .ficha .ancho { grid-column: 1 / -1; }
        .ficha > div > span { display: block; font-size: 11.5px; color: #55657a; }
        .ficha strong { font-size: 14.5px; overflow-wrap: anywhere; }
        .sin-dato { color: #8a97a5; font-weight: normal; font-style: italic; }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th { text-align: left; background: #e6f4ef; color: #0f6e56; font-size: 12px; padding: 5px 6px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e8edf2; vertical-align: top; }
        tr.grupo td { border-top: 1.5px solid #b5ddce; }
        td.edad { font-weight: 700; color: #14304a; width: 24%; }
        .pendiente { color: #a8551a; font-weight: 700; }
        td.fecha, .nw { white-space: nowrap; }
        .nota { font-size: 12.5px; color: #55657a; margin: 6px 0 0; }

        .cita {
            margin-top: 12px; padding: 10px 12px; border-radius: 8px;
            background: #fff8e1; border: 1.5px solid #f0c75e; font-size: 14px;
        }
        .graficas { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .graficas figure { margin: 0; border: 1px solid #dfe6ee; border-radius: 8px; padding: 6px; }
        .graficas figcaption { font-weight: 700; font-size: 13px; color: #14304a; margin: 0 0 2px 4px; }
        .grafica-carnet { width: 100%; height: auto; display: block; }
        .leyenda { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12px; color: #55657a; margin: 6px 0 10px; }
        .leyenda i { display: inline-block; width: 18px; height: 10px; vertical-align: middle; margin-right: 4px; border-radius: 2px; }

        .estado { font-weight: 700; }
        .estado-normal { color: #0f6e56; }
        .estado-cerca_umbral { color: #8a6500; }
        .estado-otro { color: #a8551a; }

        .firma { display: flex; flex-wrap: wrap; gap: 24px; justify-content: space-between; margin-top: 26px; align-items: flex-end; }
        .firma .linea { flex: 1 1 240px; text-align: center; font-size: 12.5px; }
        .firma .linea div { border-top: 1px solid #1f2d3a; margin: 40px 10px 4px; }
        .sello {
            width: 150px; height: 90px; border: 1.5px dashed #9aa8b6; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: 12px; color: #9aa8b6;
        }
        .pie { margin-top: 16px; font-size: 11.5px; color: #55657a; border-top: 1px solid #dfe6ee; padding-top: 8px; }

        @media (max-width: 600px) {
            .ficha, .graficas { grid-template-columns: 1fr; }
            .cabecera h1 { font-size: 22px; }
            table { font-size: 12.5px; }
            th, td { padding: 4px 4px; }
        }
        /* Impreso: dos hojas carta. La primera con los datos y las vacunas,
           la segunda con el crecimiento, los suplementos, la firma y el sello. */
        @media print {
            body { background: #fff; font-size: 9.5pt; }
            .barra-pantalla { display: none; }
            .hoja { max-width: none; margin: 0; padding: 0; box-shadow: none; border-radius: 0; }
            .cabecera { padding-bottom: 6px; margin-bottom: 8px; }
            .cabecera h1 { font-size: 18pt; }
            .ficha { grid-template-columns: 1fr 1fr 1fr; gap: 2px 14px; }
            .ficha .direccion { grid-column: span 2; }
            .ficha > div > span { font-size: 7.5pt; }
            .ficha strong { font-size: 10pt; }
            h2 { font-size: 11pt; margin: 10px 0 5px; padding: 4px 8px; }
            table { font-size: 9pt; }
            th, td { padding: 2px 5px; }
            .nota { font-size: 8.5pt; margin-top: 4px; }
            .salto { break-before: page; }
            tr, figure, .cita, .firma { break-inside: avoid; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>

<div class="barra-pantalla">
    <button type="button" onclick="window.print()">Imprimir o guardar como PDF</button>
    <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$nino['id_nino'] ?>">Volver al historial</a>
    <p>En el teléfono: toque "Imprimir o guardar como PDF", elija "Guardar como PDF" y
       comparta el archivo por WhatsApp con la familia.</p>
</div>

<main class="hoja">

    <header class="cabecera">
        <div>
            <div class="puesto"><?= htmlspecialchars($puesto['nombre']) ?></div>
            <div class="lugar">
                <?= $puesto['distrito'] ? 'Distrito ' . htmlspecialchars($puesto['distrito']) : '' ?>
                <?= $puesto['area_salud'] ? ' · Área de Salud ' . htmlspecialchars($puesto['area_salud']) : '' ?>
            </div>
        </div>
        <div>
            <h1>Carnet del niño</h1>
            <div class="sub">menor de 7 años · copia para la familia</div>
        </div>
    </header>

    <section class="ficha" aria-label="Datos del niño">
        <div class="ancho"><span>Nombre del niño</span><strong><?= htmlspecialchars($nino['nombres'] . ' ' . $nino['apellidos']) ?></strong></div>
        <div><span>Fecha de nacimiento</span><strong><?= Formato::fecha($nino['fecha_nacimiento']) ?></strong> (<?= htmlspecialchars(Formato::edad($nino['fecha_nacimiento'])) ?>)</div>
        <div><span>Sexo</span><strong><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></strong></div>
        <div><span>CUI</span><strong><?= $dato($nino['cui']) ?></strong></div>
        <div><span>No. de carnet</span><strong><?= $dato($nino['numero_carnet']) ?></strong></div>
        <div><span>Pueblo</span><strong><?= $dato($pueblo) ?></strong></div>
        <div><span>Comunidad (paraje)</span><strong><?= $dato($nino['paraje_nombre']) ?></strong></div>
        <div><span>Nombre de la madre o responsable</span><strong><?= $dato($nino['nombre_madre']) ?></strong></div>
        <div><span>Nombre del padre</span><strong><?= $dato($nino['nombre_padre']) ?></strong></div>
        <div><span>Teléfono</span><strong><?= $dato($nino['telefono']) ?></strong></div>
        <div><span>Territorio y sector</span><strong>T<?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></strong></div>
        <div class="ancho direccion"><span>Dirección</span><strong><?= $dato($nino['direccion']) ?></strong></div>
    </section>

    <h2>Vacunación</h2>
    <table>
        <thead><tr><th>Edad</th><th>Vacuna</th><th>Dosis</th><th>Fecha</th></tr></thead>
        <tbody>
            <?php $edadAnterior = null; ?>
            <?php foreach ($carnet['esquema'] as $e): ?>
                <?php $nuevoGrupo = $e['edad_sugerida'] !== $edadAnterior; $edadAnterior = $e['edad_sugerida']; ?>
                <tr class="<?= $nuevoGrupo ? 'grupo' : '' ?>">
                    <td class="edad"><?= $nuevoGrupo ? htmlspecialchars($e['edad_sugerida']) : '' ?></td>
                    <td><?= htmlspecialchars($e['nombre']) ?></td>
                    <td><?= htmlspecialchars($e['dosis']) ?></td>
                    <td class="fecha">
                        <?php if ($e['estado'] === 'aplicada'): ?>
                            <?= Formato::fecha($e['fecha']) ?>
                        <?php elseif ($e['estado'] === 'cubierta'): ?>
                            <?= Formato::fecha($e['fecha']) ?><br><small>con <?= htmlspecialchars($e['equivalente']) ?> (esquema anterior)</small>
                        <?php elseif ($e['estado'] === 'pendiente'): ?>
                            <span class="pendiente">Pendiente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pendientes > 0): ?>
        <p class="nota"><strong class="pendiente"><?= $pendientes ?> vacuna<?= $pendientes === 1 ? '' : 's' ?> pendiente<?= $pendientes === 1 ? '' : 's' ?>:</strong>
            por su edad ya le tocaban. Acuda al puesto de salud para ponerlas al día.</p>
    <?php endif; ?>
    <?php if ($carnet['otras_vacunas']): ?>
        <?php
        $textosOtras = array_map(fn($o) => htmlspecialchars($o['vacuna']) . ' el ' . Formato::fecha($o['fecha'])
            . ($o['equivale'] ? ' (cuenta como ' . htmlspecialchars($o['equivale']) . ')' : ''), $carnet['otras_vacunas']);
        ?>
        <p class="nota"><strong>Otras dosis registradas (esquema anterior):</strong> <?= implode('; ', $textosOtras) ?>.</p>
    <?php endif; ?>
    <p class="nota">Esquema nacional de vacunación vigente del Ministerio de Salud Pública y Asistencia Social.</p>

    <?php if ($cita): ?>
        <div class="cita">
            <strong>Próxima cita:</strong> <?= htmlspecialchars(Cita::fechaLegible($cita['fecha_programada'])) ?>
            <?= $cita['motivo'] ? ' · ' . htmlspecialchars($cita['motivo']) : '' ?>
        </div>
    <?php endif; ?>

    <section class="salto">
        <h2>Crecimiento</h2>
        <?php if (!$carnet['controles']): ?>
            <p>Todavía no tiene controles de peso y talla registrados.</p>
        <?php else: ?>
            <div class="graficas">
                <?php foreach ($carnet['graficas'] as $g): ?>
                    <figure>
                        <figcaption><?= htmlspecialchars($g['titulo']) ?></figcaption>
                        <?= CarnetFamilia::graficaSvg($g, $edadMeses) ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <div class="leyenda">
                <span><i style="background: rgba(29,158,117,0.18)"></i>Rango normal según la OMS (entre -2 y +2)</span>
                <span><i style="background: #1d9e75; height: 3px"></i>Promedio (0)</span>
                <span><i style="background: #14304a; height: 3px"></i>Su niño</span>
            </div>
            <?php if ($edadMeses > CarnetFamilia::MESES_MAXIMOS_GRAFICA): ?>
                <p class="nota">Las gráficas de la OMS llegan a los 5 años.</p>
            <?php endif; ?>

            <table>
                <thead><tr><th>Fecha</th><th>Edad</th><th>Peso</th><th>Longitud o talla</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($carnet['controles'] as $c): ?>
                        <tr>
                            <td class="fecha"><?= Formato::fecha($c['fecha']) ?></td>
                            <td><?= htmlspecialchars(Formato::edadEnMeses($c['edad_meses'])) ?></td>
                            <td><span class="nw"><?= number_format($c['peso_kg'], 2) ?> kg</span><br><small class="nw"><?= Formato::libras($c['peso_kg']) ?></small></td>
                            <td><span class="nw"><?= number_format($c['talla_cm'], 1) ?> cm</span><br><small><?= $c['tipo_talla'] === 'longitud' ? 'acostado' : 'de pie' ?></small></td>
                            <td class="estado <?= in_array($c['nivel'], ['normal', 'cerca_umbral'], true) ? 'estado-' . $c['nivel'] : 'estado-otro' ?>">
                                <?= htmlspecialchars(CarnetFamilia::estadoNutricional($c['nivel'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (in_array('cerca_umbral', array_column($carnet['controles'], 'nivel'), true)): ?>
                <p class="nota"><strong>En vigilancia:</strong> el crecimiento todavía es normal, pero está
                    cerca del límite. Es importante no faltar a los controles.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <h2>Vitaminas, minerales y desparasitante</h2>
    <?php if (!$carnet['suplementos']): ?>
        <p>No tiene entregas registradas.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Fecha</th><th>Entrega</th><th>Cantidad</th></tr></thead>
            <tbody>
                <?php foreach ($carnet['suplementos'] as $s): ?>
                    <tr>
                        <td class="fecha"><?= Formato::fecha($s['fecha_entrega']) ?></td>
                        <td><?= htmlspecialchars($s['tipo']) ?></td>
                        <td><?= $dato($s['cantidad']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="firma">
        <div class="linea"><div></div>Firma y nombre de quien entrega</div>
        <div class="sello">Sello del puesto</div>
    </div>

    <p class="pie">
        Emitido el <?= date('d-m-Y') ?> a las <?= date('H:i') ?> por <?= htmlspecialchars(Auth::usuario()['nombre']) ?>.
        Copia con los datos registrados en el Carnet Digital de Monitoreo Nutricional Infantil del
        <?= htmlspecialchars($puesto['nombre']) ?> hasta esa fecha. Si encuentra un error, acuda al puesto de salud.
    </p>
</main>

</body>
</html>
