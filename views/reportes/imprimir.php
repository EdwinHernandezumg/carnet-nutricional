<?php
/**
 * Vista del panel de indicadores para imprimir o guardar como PDF desde el
 * navegador (decisión de diseño: PDF sin librerías externas). Lleva las mismas
 * gráficas del panel (public/js/panel-indicadores.js en modo impresión) y las
 * tablas con las cifras, con los filtros aplicados.
 * Variables: $filtros, $ninos, $resumen, $tendencia
 */
$t = $resumen['tarjetas'];
$etiquetasNivel = [];
foreach (Reporte::NIVELES as $nivel) {
    $etiquetasNivel[$nivel] = Reporte::etiquetaNivel($nivel);
}
$datosPanel = [
    'urlPanel'       => APP_URL . '/index.php?accion=reportes',
    'parametros'     => Reporte::parametros($filtros),
    'resumen'        => $resumen,
    'tendencia'      => $tendencia,
    'etiquetasNivel' => $etiquetasNivel,
    'condiciones'    => Reporte::CONDICIONES,
];
$conAlerta = array_values(array_filter($ninos, fn($n) => !in_array($n['nivel'], ['normal', 'sin_control'], true)));
usort($conAlerta, fn($a, $b) => array_search($a['nivel'], Reporte::NIVELES) <=> array_search($b['nivel'], Reporte::NIVELES)
    ?: strcmp($a['nombre'], $b['nombre']));
$atrasados = array_values(array_filter($ninos, fn($n) => $n['atrasado'] === true));
$pct = fn($parte, $total) => $total ? round($parte * 100 / $total, 1) . ' %' : 'Sin dato';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel de indicadores · Carnet Digital</title>
    <style>
        @page { size: landscape; margin: 1.3cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; padding: 1.2rem; font-size: 10pt; }
        .encabezado { text-align: center; margin-bottom: 0.9rem; border-bottom: 2px solid #14304a; padding-bottom: 0.6rem; }
        .encabezado h1 { font-size: 15pt; margin: 0 0 0.2rem; color: #14304a; }
        .encabezado h2 { font-size: 12pt; margin: 0 0 0.25rem; font-weight: normal; }
        .encabezado .lugar { font-size: 9.5pt; color: #444; }
        .meta { font-size: 9pt; color: #333; margin-bottom: 0.8rem; line-height: 1.5; }
        h3 { font-size: 11pt; color: #14304a; margin: 0.9rem 0 0.4rem; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 0.5rem; }
        th, td { border: 1px solid #777; padding: 3px 6px; text-align: center; }
        th { background: #e8eef5; }
        td.izq, th.izq { text-align: left; }
        .tarjetas { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 0.4rem; }
        .tarjetas div { border: 1px solid #999; border-radius: 4px; padding: 5px 8px; }
        .tarjetas strong { display: block; font-size: 14pt; color: #14304a; }
        .graficas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .grafica { border: 1px solid #bbb; border-radius: 4px; padding: 6px; break-inside: avoid; }
        .grafica h3 { margin-top: 0; }
        canvas { width: 100% !important; height: auto !important; }
        .seccion { break-inside: avoid; }
        .salto { break-before: page; }
        .pie-doc { margin-top: 1rem; font-size: 8.5pt; color: #444; border-top: 1px solid #999; padding-top: 0.4rem; }
        .firma { margin-top: 2.2rem; display: flex; justify-content: space-around; font-size: 9.5pt; break-inside: avoid; }
        .firma div { text-align: center; }
        .linea-firma { border-top: 1px solid #000; width: 200px; margin: 0 auto 0.3rem; }
        .btn-imprimir { display: inline-block; margin-bottom: 0.6rem; padding: 0.5rem 1.2rem; background: #1d9e75;
            color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 11pt; }
        .nota-impresion { font-size: 9.5pt; color: #666; margin: 0 0 0.8rem; }
        @media print { .btn-imprimir, .nota-impresion { display: none; } body { padding: 0; } }
    </style>
</head>
<body class="modo-impresion">

<button class="btn-imprimir" onclick="window.print()">Imprimir o guardar como PDF</button>
<p class="nota-impresion">En el destino de la impresión, elija "Guardar como PDF" para obtener el archivo.</p>

<div class="encabezado">
    <h1><?= htmlspecialchars(APP_NOMBRE) ?></h1>
    <h2>Panel de indicadores del estado nutricional</h2>
    <div class="lugar">Puesto de Salud de Chivarreto, San Francisco El Alto, Totonicapán</div>
</div>

<div class="meta">
    <strong>Filtros:</strong> <?= htmlspecialchars(Reporte::descripcionFiltros($filtros)) ?><br>
    <strong>Generado:</strong> <?= date('d-m-Y H:i') ?> &nbsp;|&nbsp;
    <strong>Elaborado por:</strong> <?= htmlspecialchars(Auth::usuario()['nombre']) ?>
</div>

<div class="tarjetas">
    <div><strong><?= $t['activos'] ?></strong>Niños activos</div>
    <div><strong><?= $t['evaluados'] ?> (<?= $t['porcentaje_evaluados'] ?? 0 ?> %)</strong><?= $filtros['periodo'] === 'actual' ? 'Con control registrado' : 'Evaluados en el período' ?></div>
    <div><strong><?= $t['atrasados'] ?></strong>Controles atrasados</div>
    <div><strong><?= $t['inasistencia'] !== null ? $t['inasistencia'] . ' %' : 'Sin citas' ?></strong>Inasistencia a citas</div>
    <div><strong><?= $t['desnutricion'] ?></strong>Desnutrición (aguda o crónica)</div>
    <div><strong><?= $t['cerca_umbral'] ?></strong>Cerca del umbral</div>
    <div><strong><?= $t['riesgo'] ?></strong>Riesgo (bajo peso)</div>
    <div><strong><?= $t['sobrepeso'] ?></strong>Sobrepeso</div>
</div>

<div class="graficas">
    <div class="grafica"><h3>Estado nutricional por sector</h3><canvas id="grafica_sector" width="560" height="260"></canvas></div>
    <div class="grafica"><h3>Parajes con más casos</h3><canvas id="grafica_paraje" width="560" height="260"></canvas></div>
    <div class="grafica"><h3>Condición más grave de cada niño</h3><canvas id="grafica_nivel" width="560" height="240"></canvas></div>
    <div class="grafica"><h3>Cada condición por separado</h3><canvas id="grafica_condicion" width="560" height="240"></canvas></div>
</div>
<div class="grafica" style="margin-top:10px"><h3>Tendencia de los últimos 12 meses (porcentaje de los niños medidos cada mes)</h3>
    <canvas id="grafica_tendencia" width="1140" height="240"></canvas></div>

<div class="seccion salto">
    <h3>Estado nutricional por sector</h3>
    <table>
        <thead><tr><th class="izq">Sector</th><th>Total</th>
            <?php foreach (Reporte::NIVELES as $nivel): ?><th><?= htmlspecialchars(Reporte::etiquetaNivel($nivel)) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
            <?php foreach ($resumen['por_sector'] as $s): ?>
                <tr><td class="izq"><?= htmlspecialchars($s['nombre']) ?></td><td><?= $s['total'] ?></td>
                    <?php foreach (Reporte::NIVELES as $nivel): ?><td><?= $s[$nivel] ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th class="izq">Total</th><th><?= $t['activos'] ?></th>
            <?php foreach (Reporte::NIVELES as $nivel): ?><th><?= $resumen['por_nivel'][$nivel] ?></th><?php endforeach; ?>
        </tr></tfoot>
    </table>
</div>

<div class="seccion">
    <h3>Tendencia mensual</h3>
    <table>
        <thead><tr><th class="izq">Mes</th><th>Medidos</th><th>Desnutrición o bajo peso</th><th>Cerca del umbral</th><th>Sobrepeso</th></tr></thead>
        <tbody>
            <?php foreach ($tendencia as $m): ?>
                <tr><td class="izq"><?= htmlspecialchars($m['etiqueta']) ?></td><td><?= $m['evaluados'] ?></td>
                    <td><?= $m['desnutricion'] ?> (<?= $pct($m['desnutricion'], $m['evaluados']) ?>)</td>
                    <td><?= $m['cerca'] ?> (<?= $pct($m['cerca'], $m['evaluados']) ?>)</td>
                    <td><?= $m['sobrepeso'] ?> (<?= $pct($m['sobrepeso'], $m['evaluados']) ?>)</td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="salto">
    <h3>Niños con alguna alerta (<?= count($conAlerta) ?>)</h3>
    <?php if (!$conAlerta): ?>
        <p>No hay niños con alerta en el período.</p>
    <?php else: ?>
        <table>
            <thead><tr><th class="izq">Niño</th><th>Sector</th><th class="izq">Paraje</th><th>Edad</th>
                <th>Último control</th><th>P/E (Z)</th><th>T/E (Z)</th><th>P/T (Z)</th><th class="izq">Condición más grave</th></tr></thead>
            <tbody>
                <?php foreach ($conAlerta as $n): ?>
                    <tr><td class="izq"><?= htmlspecialchars($n['nombre']) ?></td><td><?= htmlspecialchars($n['sector']) ?></td>
                        <td class="izq"><?= htmlspecialchars($n['paraje'] ?? 'Sin paraje') ?></td><td><?= htmlspecialchars($n['edad_texto']) ?></td>
                        <td><?= Formato::fecha($n['fecha_control']) ?></td>
                        <?php foreach (['peso_edad', 'talla_edad', 'peso_talla'] as $ind): ?>
                            <td><?= $n['z'][$ind] !== null ? number_format($n['z'][$ind], 2) : 'Sin dato' ?></td>
                        <?php endforeach; ?>
                        <td class="izq"><?= htmlspecialchars(Reporte::etiquetaNivel($n['nivel'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Niños con el control atrasado según la normativa (<?= count($atrasados) ?>)</h3>
    <?php if (!$atrasados): ?>
        <p>Ningún niño tiene el control atrasado.</p>
    <?php else: ?>
        <table>
            <thead><tr><th class="izq">Niño</th><th>Sector</th><th class="izq">Paraje</th><th>Edad</th><th>Último control en el período</th></tr></thead>
            <tbody>
                <?php foreach ($atrasados as $n): ?>
                    <tr><td class="izq"><?= htmlspecialchars($n['nombre']) ?></td><td><?= htmlspecialchars($n['sector']) ?></td>
                        <td class="izq"><?= htmlspecialchars($n['paraje'] ?? 'Sin paraje') ?></td><td><?= htmlspecialchars($n['edad_texto']) ?></td>
                        <td><?= Formato::fecha($n['fecha_control'], 'Sin control') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="pie-doc">
    Clasificación conforme a los patrones de crecimiento infantil de la Organización Mundial de la Salud,
    con la última medición de cada niño dentro del período. Las cifras de estado nutricional cuentan a cada
    niño una sola vez, por su condición más grave (la misma regla del panel de seguimiento); solo la gráfica
    "Cada condición por separado" cuenta a un niño en varias. "Cerca del umbral" identifica a los niños con
    alguna puntuación Z entre -2 y -1 desviaciones estándar: se aproximan al umbral de riesgo sin haberlo
    cruzado. "Control atrasado" sigue la frecuencia de la normativa: cada mes antes de los 2 años, cada
    3 meses de 2 a 3 años y cada 6 meses de 3 a 5 años.
</div>

<div class="firma">
    <div><div class="linea-firma"></div>Elaborado por</div>
    <div><div class="linea-firma"></div>Revisado por</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    const datosPanel = <?= json_encode($datosPanel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= Recurso::url('js/panel-indicadores.js') ?>"></script>
</body>
</html>
