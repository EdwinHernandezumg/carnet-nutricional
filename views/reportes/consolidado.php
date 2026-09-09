<?php
/**
 * Reporte consolidado: resumen del estado nutricional por sector.
 * Variables: $filas, $totales, $territorios, $filtros
 */
$titulo = 'Reporte consolidado';
require __DIR__ . '/../layouts/encabezado.php';

// Arma la cadena de filtros para pasarla a la descarga y a la vista imprimible.
$qs = http_build_query(array_filter([
    'id_territorio' => $filtros['id_territorio'] ?? '',
    'fecha_desde'   => $filtros['fecha_desde'] ?? '',
    'fecha_hasta'   => $filtros['fecha_hasta'] ?? '',
]));
?>

<h1 class="titulo-pagina">Reporte consolidado del estado nutricional</h1>
<p class="intro-panel">Resumen por sector y territorio. Cada niño se contabiliza
   una sola vez, según su condición más grave vigente.</p>

<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="reportes">
    <select name="id_territorio">
        <option value="">Todos los territorios</option>
        <?php foreach ($territorios as $t): ?>
            <option value="<?= $t['id_territorio'] ?>"
                <?= (($filtros['id_territorio'] ?? '') == $t['id_territorio']) ? 'selected' : '' ?>>
                Territorio <?= htmlspecialchars($t['numero']) ?> — <?= htmlspecialchars($t['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label class="lbl-filtro">Desde
        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
    </label>
    <label class="lbl-filtro">Hasta
        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
    </label>
    <button type="submit" class="btn-primario">Generar</button>
</form>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=reporte_csv&<?= $qs ?>" class="btn-secundario">
        Descargar en Excel (CSV)
    </a>
    <a href="<?= APP_URL ?>/index.php?accion=reporte_imprimir&<?= $qs ?>" class="btn-secundario" target="_blank">
        Vista para imprimir / PDF
    </a>
</div>

<?php if (empty($filas)): ?>
    <p class="vacio">No hay datos para los filtros seleccionados.</p>
<?php else: ?>
    <div class="tabla-scroll">
    <table class="tabla tabla-reporte">
        <thead>
            <tr>
                <th>Territorio</th>
                <th>Sector</th>
                <th>Total</th>
                <th>Desn. aguda</th>
                <th>Desn. crónica</th>
                <th>Riesgo</th>
                <th>Cerca umbral</th>
                <th>Sobrepeso</th>
                <th>Normal</th>
                <th>Sin control</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td>T<?= htmlspecialchars($f['territorio']) ?></td>
                    <td><?= htmlspecialchars($f['sector']) ?></td>
                    <td><strong><?= (int)$f['total_ninos'] ?></strong></td>
                    <td class="c-grave"><?= (int)$f['desnutricion_aguda'] ?></td>
                    <td class="c-grave"><?= (int)$f['desnutricion_cronica'] ?></td>
                    <td class="c-riesgo"><?= (int)$f['riesgo'] ?></td>
                    <td class="c-cerca"><?= (int)$f['cerca_umbral'] ?></td>
                    <td><?= (int)$f['sobrepeso'] ?></td>
                    <td class="c-normal"><?= (int)$f['normal'] ?></td>
                    <td><?= (int)$f['sin_control'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fila-total">
                <td colspan="2"><strong>TOTAL</strong></td>
                <td><strong><?= $totales['total_ninos'] ?></strong></td>
                <td><strong><?= $totales['desnutricion_aguda'] ?></strong></td>
                <td><strong><?= $totales['desnutricion_cronica'] ?></strong></td>
                <td><strong><?= $totales['riesgo'] ?></strong></td>
                <td><strong><?= $totales['cerca_umbral'] ?></strong></td>
                <td><strong><?= $totales['sobrepeso'] ?></strong></td>
                <td><strong><?= $totales['normal'] ?></strong></td>
                <td><strong><?= $totales['sin_control'] ?></strong></td>
            </tr>
        </tfoot>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
