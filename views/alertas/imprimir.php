<?php
/**
 * Lista de seguimiento para imprimir o guardar como PDF desde el navegador
 * (decisión de diseño: PDF sin librerías externas). Sirve para llevarla en
 * papel a las visitas domiciliarias: trae la madre, el teléfono y una columna
 * en blanco para anotar lo que se encontró en la visita.
 * Variables: $ninos, $resumen, $textoFiltros
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Lista de seguimiento · Carnet Digital</title>
    <style>
        @page { size: landscape; margin: 1.3cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; padding: 1.2rem; font-size: 10pt; }
        .encabezado { text-align: center; margin-bottom: 0.9rem; border-bottom: 2px solid #14304a; padding-bottom: 0.6rem; }
        .encabezado h1 { font-size: 15pt; margin: 0 0 0.2rem; color: #14304a; }
        .encabezado h2 { font-size: 12pt; margin: 0 0 0.25rem; font-weight: normal; }
        .encabezado .lugar { font-size: 9.5pt; color: #444; }
        .meta { font-size: 9pt; color: #333; margin-bottom: 0.8rem; line-height: 1.5; }
        .tarjetas { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 0.8rem; }
        .tarjetas div { border: 1px solid #999; border-radius: 4px; padding: 5px 8px; }
        .tarjetas strong { display: block; font-size: 14pt; color: #14304a; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th, td { border: 1px solid #777; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e8eef5; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        td.num { text-align: center; width: 2.2em; }
        td.anotar { width: 22%; }
        .nivel { font-weight: bold; white-space: nowrap; }
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
<body>
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>

<button class="btn-imprimir" onclick="window.print()">Imprimir o guardar como PDF</button>
<p class="nota-impresion">En el destino de la impresión, elija "Guardar como PDF" para obtener el archivo.</p>

<div class="encabezado">
    <h1><?= htmlspecialchars(APP_NOMBRE) ?></h1>
    <h2>Lista de seguimiento nutricional</h2>
    <div class="lugar">Puesto de Salud de Chivarreto, San Francisco El Alto, Totonicapán</div>
</div>

<div class="meta">
    <strong>Filtros:</strong> <?= htmlspecialchars($textoFiltros) ?><br>
    <strong>Generado:</strong> <?= date('d-m-Y H:i') ?> &nbsp;|&nbsp;
    <strong>Elaborado por:</strong> <?= htmlspecialchars(Auth::usuario()['nombre']) ?>
</div>

<div class="tarjetas">
    <div><strong><?= ($resumen['desnutricion_aguda'] ?? 0) + ($resumen['desnutricion_cronica'] ?? 0) ?></strong>Desnutrición (aguda o crónica)</div>
    <div><strong><?= $resumen['cerca_umbral'] ?? 0 ?></strong>Cerca del umbral</div>
    <div><strong><?= $resumen['riesgo'] ?? 0 ?></strong>Riesgo (bajo peso)</div>
    <div><strong><?= $resumen['sobrepeso'] ?? 0 ?></strong>Sobrepeso</div>
</div>

<?php if (!$ninos): ?>
    <p>No hay niños con alerta para los filtros seleccionados.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>N.º</th><th>Nivel</th><th>Niño</th><th>Edad</th><th>Sector y paraje</th>
                <th>Madre y teléfono</th><th>Último control</th><th>Detalle</th><th>Anotaciones de la visita</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ninos as $i => $n): ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td class="nivel"><?= htmlspecialchars(Alerta::etiquetaNivel($n['nivel'])) ?></td>
                    <td><?= htmlspecialchars($n['apellidos'] . ', ' . $n['nombres']) ?></td>
                    <td><?= htmlspecialchars(Formato::edad($n['fecha_nacimiento'])) ?></td>
                    <td>T<?= htmlspecialchars($n['territorio_numero']) ?> · <?= htmlspecialchars($n['sector_letra']) ?>,
                        <?= htmlspecialchars($n['paraje_nombre'] ?? 'sin paraje') ?></td>
                    <td><?= htmlspecialchars($n['nombre_madre'] ?: 'Sin dato') ?><br><?= htmlspecialchars($n['telefono'] ?: 'Sin teléfono') ?></td>
                    <td><?= Formato::fecha($n['fecha_toma']) ?></td>
                    <td><?= htmlspecialchars($n['descripcion']) ?></td>
                    <td class="anotar"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="pie-doc">
    <?= count($ninos) ?> niño(s) en seguimiento. Estado actual: cada niño aparece una sola vez, con la condición
    más grave de su último control (la misma regla del panel de indicadores en "Estado actual"). Clasificación
    conforme a los patrones de crecimiento infantil de la Organización Mundial de la Salud. "Cerca del umbral"
    identifica a los niños con alguna puntuación Z entre -2 y -1 desviaciones estándar: se aproximan al umbral
    de riesgo sin haberlo cruzado. Documento con datos personales de niños: guardarlo y desecharlo con cuidado.
</div>

<div class="firma">
    <div><div class="linea-firma"></div>Elaborado por</div>
    <div><div class="linea-firma"></div>Revisado por</div>
</div>

<script>
    // Abre el cuadro de impresión al cargar (sin_imprimir=1 lo evita, para revisar la hoja).
    if (!/[?&]sin_imprimir=1/.test(window.location.search)) {
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    }
</script>
</body>
</html>
