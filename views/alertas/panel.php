<?php
/**
 * Panel de seguimiento nutricional: niños con alerta activa.
 * Variables: $ninos, $resumen, $sectores, $filtros
 */
$titulo = 'Panel de seguimiento';
require __DIR__ . '/../layouts/encabezado.php';

function edadPanel(string $fechaNac): string {
    $d = (new DateTime($fechaNac))->diff(new DateTime('now'));
    if ($d->y > 0) return $d->y . ($d->y === 1 ? ' año' : ' años');
    return $d->m . ' meses';
}
?>

<h1 class="titulo-pagina">Panel de seguimiento nutricional</h1>
<p class="intro-panel">Niños con alerta activa, ordenados por prioridad. El nivel
   <strong>“cerca del umbral”</strong> identifica a quienes se aproximan a la
   desnutrición aunque aún no la crucen.</p>

<!-- Tarjetas de resumen -->
<div class="tarjetas-resumen">
    <div class="tarjeta-r tr-roja">
        <span class="tr-num"><?= ($resumen['desnutricion_aguda'] ?? 0) + ($resumen['desnutricion_cronica'] ?? 0) ?></span>
        <span class="tr-lbl">Desnutrición</span>
    </div>
    <div class="tarjeta-r tr-amarilla">
        <span class="tr-num"><?= $resumen['cerca_umbral'] ?? 0 ?></span>
        <span class="tr-lbl">Cerca del umbral</span>
    </div>
    <div class="tarjeta-r tr-naranja">
        <span class="tr-num"><?= $resumen['riesgo'] ?? 0 ?></span>
        <span class="tr-lbl">Riesgo (bajo peso)</span>
    </div>
    <div class="tarjeta-r tr-azul">
        <span class="tr-num"><?= $resumen['sobrepeso'] ?? 0 ?></span>
        <span class="tr-lbl">Sobrepeso</span>
    </div>
</div>

<!-- Filtros -->
<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="panel_alertas">
    <select name="id_sector">
        <option value="">Todos los sectores</option>
        <?php foreach ($sectores as $s): ?>
            <option value="<?= $s['id_sector'] ?>"
                <?= (($filtros['id_sector'] ?? '') == $s['id_sector']) ? 'selected' : '' ?>>
                Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="nivel">
        <option value="">Todos los niveles</option>
        <option value="desnutricion_aguda"   <?= (($filtros['nivel'] ?? '')==='desnutricion_aguda')?'selected':'' ?>>Desnutrición aguda</option>
        <option value="desnutricion_cronica" <?= (($filtros['nivel'] ?? '')==='desnutricion_cronica')?'selected':'' ?>>Desnutrición crónica</option>
        <option value="riesgo"               <?= (($filtros['nivel'] ?? '')==='riesgo')?'selected':'' ?>>Riesgo</option>
        <option value="cerca_umbral"         <?= (($filtros['nivel'] ?? '')==='cerca_umbral')?'selected':'' ?>>Cerca del umbral</option>
    </select>
    <button type="submit" class="btn-primario">Filtrar</button>
</form>

<?php if (empty($ninos)): ?>
    <p class="vacio">No hay niños con alerta activa para los filtros seleccionados.</p>
<?php else: ?>
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr>
                <th>Nivel</th>
                <th>Niño</th>
                <th>Edad</th>
                <th>Sector</th>
                <th>Último control</th>
                <th>Detalle</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ninos as $n): ?>
                <tr>
                    <td data-label="Nivel">
                        <span class="etiqueta <?= Alerta::claseNivel($n['nivel']) ?>">
                            <?= htmlspecialchars(Alerta::etiquetaNivel($n['nivel'])) ?>
                        </span>
                    </td>
                    <td class="celda-titulo"><?= htmlspecialchars($n['apellidos'] . ', ' . $n['nombres']) ?></td>
                    <td data-label="Edad"><?= edadPanel($n['fecha_nacimiento']) ?></td>
                    <td data-label="Sector">T<?= htmlspecialchars($n['territorio_numero']) ?> · <?= htmlspecialchars($n['sector_letra']) ?></td>
                    <td data-label="Último control"><?= Formato::fecha($n['fecha_toma']) ?></td>
                    <td data-label="Detalle" class="detalle-alerta celda-larga"><?= htmlspecialchars($n['descripcion']) ?></td>
                    <td class="acciones-fila"><a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$n['id_nino'] ?>"><?= Icono::ver('ojo', 18) ?> Ver</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="conteo"><?= count($ninos) ?> niño(s) en seguimiento.</p>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
