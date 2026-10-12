<?php
/**
 * Vista optimizada para impresión / generación de PDF desde el navegador.
 * Variables: $filas, $totales, $filtros, $tituloFiltro
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte consolidado · Carnet Digital</title>
    <style>
        @page { size: landscape; margin: 1.5cm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Georgia, serif;
            color: #000; margin: 0; padding: 1.5rem;
            font-size: 11pt;
        }
        .encabezado { text-align: center; margin-bottom: 1.2rem; border-bottom: 2px solid #14304a; padding-bottom: 0.8rem; }
        .encabezado h1 { font-size: 15pt; margin: 0 0 0.2rem; color: #14304a; }
        .encabezado h2 { font-size: 12pt; margin: 0 0 0.3rem; font-weight: normal; }
        .encabezado .lugar { font-size: 10pt; color: #444; }
        .meta { font-size: 9.5pt; color: #444; margin-bottom: 0.9rem; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #666; padding: 5px 7px; text-align: center; }
        th { background: #e8eef5; font-weight: bold; }
        td:first-child, td:nth-child(2) { text-align: left; }
        tfoot td { background: #f0f0f0; font-weight: bold; }
        .pie-doc { margin-top: 1.5rem; font-size: 9pt; color: #555; border-top: 1px solid #999; padding-top: 0.5rem; }
        .firma { margin-top: 2.5rem; display: flex; justify-content: space-around; font-size: 10pt; }
        .firma div { text-align: center; }
        .linea-firma { border-top: 1px solid #000; width: 200px; margin: 0 auto 0.3rem; }
        .btn-imprimir {
            display: inline-block; margin-bottom: 1rem; padding: 0.5rem 1.2rem;
            background: #1d9e75; color: #fff; border: none; border-radius: 6px;
            cursor: pointer; font-size: 11pt; font-family: Arial, sans-serif;
        }
        @media print { .btn-imprimir, .nota-impresion { display: none; } body { padding: 0; } }
        .nota-impresion { font-size: 9.5pt; color: #666; margin-bottom: 1rem; font-family: Arial, sans-serif; }
    </style>
</head>
<body>

<button class="btn-imprimir" onclick="window.print()">Imprimir o guardar como PDF</button>
<p class="nota-impresion">Al imprimir, elija “Guardar como PDF” en el destino para
   obtener el archivo. Se recomienda orientación horizontal.</p>

<div class="encabezado">
    <h1>Carnet Digital de Monitoreo Nutricional Infantil</h1>
    <h2>Reporte consolidado del estado nutricional por sector</h2>
    <div class="lugar">Puesto de Salud de Chivarreto, San Francisco El Alto, Totonicapán</div>
</div>

<div class="meta">
    <strong>Generado:</strong> <?= date('d-m-Y H:i') ?> &nbsp;|&nbsp;
    <strong>Alcance:</strong> <?= htmlspecialchars($tituloFiltro) ?> &nbsp;|&nbsp;
    <strong>Elaborado por:</strong> <?= htmlspecialchars(Auth::usuario()['nombre']) ?>
</div>

<table>
    <thead>
        <tr>
            <th>Territorio</th>
            <th>Sector</th>
            <th>Total niños</th>
            <th>Desnutrición aguda</th>
            <th>Desnutrición crónica</th>
            <th>Riesgo (bajo peso)</th>
            <th>Cerca del umbral</th>
            <th>Sobrepeso</th>
            <th>Normal</th>
            <th>Sin control</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td>Territorio <?= htmlspecialchars($f['territorio']) ?></td>
                <td>Sector <?= htmlspecialchars($f['sector']) ?></td>
                <td><?= (int)$f['total_ninos'] ?></td>
                <td><?= (int)$f['desnutricion_aguda'] ?></td>
                <td><?= (int)$f['desnutricion_cronica'] ?></td>
                <td><?= (int)$f['riesgo'] ?></td>
                <td><?= (int)$f['cerca_umbral'] ?></td>
                <td><?= (int)$f['sobrepeso'] ?></td>
                <td><?= (int)$f['normal'] ?></td>
                <td><?= (int)$f['sin_control'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL GENERAL</td>
            <td><?= $totales['total_ninos'] ?></td>
            <td><?= $totales['desnutricion_aguda'] ?></td>
            <td><?= $totales['desnutricion_cronica'] ?></td>
            <td><?= $totales['riesgo'] ?></td>
            <td><?= $totales['cerca_umbral'] ?></td>
            <td><?= $totales['sobrepeso'] ?></td>
            <td><?= $totales['normal'] ?></td>
            <td><?= $totales['sin_control'] ?></td>
        </tr>
    </tfoot>
</table>

<div class="pie-doc">
    Clasificación conforme a los patrones de crecimiento infantil de la Organización
    Mundial de la Salud. “Cerca del umbral” identifica a los niños cuya puntuación Z
    se encuentra entre −1 y −2 desviaciones estándar, es decir, aproximándose al
    umbral de riesgo sin haberlo cruzado. “Sin control” corresponde a niños
    registrados que aún no cuentan con mediciones.
</div>

<div class="firma">
    <div>
        <div class="linea-firma"></div>
        Elaborado por
    </div>
    <div>
        <div class="linea-firma"></div>
        Vo. Bo. Dirección del Puesto de Salud
    </div>
</div>

</body>
</html>
