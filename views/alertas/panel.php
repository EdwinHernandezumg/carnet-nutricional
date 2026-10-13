<?php
/**
 * Panel de seguimiento nutricional: la lista de trabajo del puesto.
 * Muestra a los niños cuyo último control tiene una alerta, cada uno una sola
 * vez por su condición más grave. Es la misma regla del panel de indicadores
 * en "Estado actual", por eso las cifras de los dos paneles coinciden.
 * Variables: $ninos, $resumen, $sectores, $filtros
 */
$titulo = 'Panel de seguimiento';
require __DIR__ . '/../layouts/encabezado.php';

$url = APP_URL . '/index.php?accion=';
// Los mismos filtros para "Imprimir lista" y para abrir el panel de indicadores.
$qsImprimir    = http_build_query(array_filter($filtros + ['imprimir' => 1], fn($v) => $v !== ''));
$qsIndicadores = http_build_query(array_filter(['id_sector' => $filtros['id_sector']], fn($v) => $v !== ''));
$niveles = ['desnutricion_aguda', 'desnutricion_cronica', 'riesgo', 'sobrepeso', 'cerca_umbral'];
?>

<h1 class="titulo-pagina">Panel de seguimiento nutricional</h1>
<p class="intro-panel">Niños cuyo <strong>último control</strong> tiene una alerta,
   por prioridad. Cada uno aparece una vez, por su condición más grave.
   <strong>“Cerca del umbral”</strong>: se aproximan a la desnutrición sin haberla cruzado.</p>

<!-- Tarjetas de resumen (respetan el filtro de sector) -->
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

<!-- Imprimir la lista y pasar al análisis, con el mismo sector elegido -->
<div class="barra-exportar barra-seguimiento">
    <a href="<?= $url ?>panel_alertas&<?= htmlspecialchars($qsImprimir) ?>" target="_blank" class="btn-secundario">
        <?= Icono::ver('imprimir', 20) ?> Imprimir lista
    </a>
    <a href="<?= $url ?>reportes<?= $qsIndicadores !== '' ? '&' . htmlspecialchars($qsIndicadores) : '' ?>" class="btn-secundario">
        <?= Icono::ver('grafica', 20) ?> Ver análisis en Indicadores
    </a>
</div>

<!-- Filtros -->
<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="panel_alertas">
    <select name="id_sector" aria-label="Sector">
        <option value="">Todos los sectores</option>
        <?php foreach ($sectores as $s): ?>
            <option value="<?= (int)$s['id_sector'] ?>"
                <?= $filtros['id_sector'] === (string)$s['id_sector'] ? 'selected' : '' ?>>
                Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="nivel" aria-label="Nivel">
        <option value="">Todos los niveles</option>
        <?php foreach ($niveles as $nivel): ?>
            <option value="<?= $nivel ?>" <?= $filtros['nivel'] === $nivel ? 'selected' : '' ?>>
                <?= htmlspecialchars(Alerta::etiquetaNivel($nivel)) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primario"><?= Icono::ver('filtrar', 20) ?> Filtrar</button>
</form>

<?php if (empty($ninos)): ?>
    <p class="vacio">No hay niños con alerta para los filtros seleccionados.</p>
<?php else: ?>
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr>
                <th>Nivel</th>
                <th>Niño</th>
                <th>Edad</th>
                <th>Sector y paraje</th>
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
                    <td data-label="Edad"><?= htmlspecialchars(Formato::edad($n['fecha_nacimiento'])) ?></td>
                    <td data-label="Sector y paraje">T<?= htmlspecialchars($n['territorio_numero']) ?> · <?= htmlspecialchars($n['sector_letra']) ?>,
                        <?= Formato::dato($n['paraje_nombre'], 'sin paraje') ?></td>
                    <td data-label="Último control"><?= Formato::fecha($n['fecha_toma']) ?></td>
                    <td data-label="Detalle" class="detalle-alerta celda-larga"><?= htmlspecialchars($n['descripcion']) ?></td>
                    <td class="acciones-fila"><a href="<?= $url ?>nino_historial&id=<?= (int)$n['id_nino'] ?>"><?= Icono::ver('ojo', 18) ?> Ver</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="conteo"><?= count($ninos) ?> niño(s) en seguimiento.</p>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
