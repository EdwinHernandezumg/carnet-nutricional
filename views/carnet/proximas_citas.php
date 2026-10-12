<?php
/**
 * Próximas citas, con aviso por WhatsApp (enlace gratuito).
 * Variables: $citas, $vencidas, $sectores, $filtros, $dias
 */
$titulo = 'Próximas citas';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina">Citas</h1>

<?php $pestanaActiva = 'lista'; require __DIR__ . '/pestanas_citas.php'; ?>

<p class="intro-panel">Próximas citas programadas en los siguientes <?= (int)$dias ?> días.
   El botón de WhatsApp abre la aplicación con el mensaje ya redactado; el envío
   lo realiza el personal con un toque, sin costo alguno.</p>

<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="citas_proximas">
    <select name="id_sector">
        <option value="">Todos los sectores</option>
        <?php foreach ($sectores as $s): ?>
            <option value="<?= $s['id_sector'] ?>"
                <?= (($filtros['id_sector'] ?? '') == $s['id_sector']) ? 'selected' : '' ?>>
                Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="dias">
        <option value="3"  <?= ($dias == 3)  ? 'selected' : '' ?>>Próximos 3 días</option>
        <option value="7"  <?= ($dias == 7)  ? 'selected' : '' ?>>Próximos 7 días</option>
        <option value="15" <?= ($dias == 15) ? 'selected' : '' ?>>Próximos 15 días</option>
        <option value="30" <?= ($dias == 30) ? 'selected' : '' ?>>Próximos 30 días</option>
    </select>
    <button type="submit" class="btn-primario"><?= Icono::ver('buscar') ?> Ver</button>
</form>

<?php if (empty($citas)): ?>
    <p class="vacio">No hay citas programadas en el periodo seleccionado.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Falta</th>
                <th>Niño</th>
                <th>Sector</th>
                <th>Motivo</th>
                <th>Aviso</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $c): ?>
                <?php $enlace = Cita::enlaceWhatsApp($c); ?>
                <tr>
                    <td><?= Formato::fecha($c['fecha_programada']) ?></td>
                    <td>
                        <?php $d = (int)$c['dias_faltantes']; ?>
                        <span class="etiqueta <?= $d <= 1 ? 'nivel-cerca' : 'nivel-normal' ?>">
                            <?= $d === 0 ? 'Hoy' : ($d === 1 ? 'Mañana' : $d . ' días') ?>
                        </span>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['apellidos'] . ', ' . $c['nombres']) ?>
                        <?php if (!empty($c['paraje_nombre'])): ?>
                            <br><small class="sub-dato"><?= htmlspecialchars($c['paraje_nombre']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>T<?= htmlspecialchars($c['territorio_numero']) ?> · <?= htmlspecialchars($c['sector_letra']) ?></td>
                    <td><?= htmlspecialchars($c['motivo'] ?? 'Control') ?></td>
                    <td>
                        <?php if ($c['aviso_enviado']): ?>
                            <span class="etiqueta etiqueta-activo">Enviado</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta-egresado">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="acciones-fila">
                        <?php if ($enlace !== ''): ?>
                            <a href="<?= htmlspecialchars($enlace) ?>" target="_blank"
                               class="btn-whatsapp"
                               onclick="marcarEnviado(<?= (int)$c['id_cita'] ?>)">
                                <?= Icono::ver('whatsapp', 18) ?> Avisar por WhatsApp
                            </a>
                        <?php else: ?>
                            <span class="sin-telefono" title="El niño no tiene teléfono registrado de 8 dígitos">
                                Sin teléfono
                            </span>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$c['id_nino'] ?>"><?= Icono::ver('ojo', 18) ?> Ver niño</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="conteo"><?= count($citas) ?> cita(s) programada(s).</p>
<?php endif; ?>

<?php if (!empty($vencidas)): ?>
    <h2 class="subtitulo-seccion">Citas vencidas sin registrar</h2>
    <p class="intro-panel">Estas citas ya pasaron y siguen marcadas como programadas.
       Conviene registrar si el niño asistió o no.</p>
    <table class="tabla">
        <thead>
            <tr><th>Fecha</th><th>Niño</th><th>Sector</th><th>Motivo</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($vencidas as $v): ?>
                <tr>
                    <td><?= Formato::fecha($v['fecha_programada']) ?></td>
                    <td><?= htmlspecialchars($v['apellidos'] . ', ' . $v['nombres']) ?></td>
                    <td>T<?= htmlspecialchars($v['territorio_numero']) ?> · <?= htmlspecialchars($v['sector_letra']) ?></td>
                    <td><?= htmlspecialchars($v['motivo'] ?? 'Control') ?></td>
                    <td class="acciones-fila">
                        <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado" style="display:inline;">
                            <input type="hidden" name="id_cita" value="<?= (int)$v['id_cita'] ?>">
                            <input type="hidden" name="estado" value="cumplida">
                            <button type="submit" class="enlace-boton">Asistió</button>
                        </form>
                        <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado" style="display:inline;">
                            <input type="hidden" name="id_cita" value="<?= (int)$v['id_cita'] ?>">
                            <input type="hidden" name="estado" value="inasistencia">
                            <button type="submit" class="enlace-boton">No asistió</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
    // Marca el aviso como enviado al abrir WhatsApp (sin interrumpir la navegación).
    function marcarEnviado(idCita) {
        const url = '<?= APP_URL ?>/index.php?accion=cita_aviso_enviado&id=' + idCita;
        fetch(url).catch(function () { /* silencioso */ });
    }
</script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
