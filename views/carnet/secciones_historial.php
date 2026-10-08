<?php
/**
 * Secciones de vacunación, suplementación y citas del niño.
 * Se incluye desde views/mediciones/historial.php.
 * Variables esperadas: $nino, $vacunas, $suplementos, $citas, $esquemaVacunas
 */
$puedeEditar = Auth::tieneRol('enfermeria') && $nino['estado'] === 'activo';
?>

<!-- ================= VACUNACIÓN ================= -->
    <h2 class="subtitulo-seccion"><?= Icono::ver('vacuna') ?> Vacunacion</h2>

<?php if ($puedeEditar): ?>
    <div class="barra-acciones">
        <a href="<?= APP_URL ?>/index.php?accion=vacuna_nueva&id_nino=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
            Registrar vacuna
        </a>
    </div>
<?php endif; ?>

<?php if (empty($vacunas)): ?>
    <p class="vacio">No hay vacunas registradas.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Fecha</th><th>Vacuna</th><th>Dosis</th><th>Lote</th><th>Lugar</th><th>Registró</th></tr>
        </thead>
        <tbody>
            <?php foreach ($vacunas as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['fecha_aplicacion']) ?></td>
                    <td><?= htmlspecialchars($v['nombre_vacuna']) ?></td>
                    <td><?= htmlspecialchars($v['dosis']) ?></td>
                    <td><?= Formato::dato($v['lote']) ?></td>
                    <td><?= Formato::dato($v['lugar']) ?></td>
                    <td><?= Formato::dato($v['registrado_por']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Esquema completo: qué tiene y qué falta -->
<details class="bloque-esquema">
    <summary>Ver esquema de vacunación completo</summary>
    <div class="grid-esquema">
        <?php foreach ($esquemaVacunas as $e): ?>
            <div class="item-esquema <?= $e['aplicada'] ? 'esq-ok' : 'esq-pendiente' ?>">
                <span class="esq-marca"><?= $e['aplicada'] ? '●' : '○' ?></span>
                <div>
                    <strong><?= htmlspecialchars($e['nombre']) ?></strong>
                    <small><?= htmlspecialchars($e['dosis']) ?>
                        <?php if ($e['edad_sugerida']): ?>
                            · <?= htmlspecialchars($e['edad_sugerida']) ?>
                        <?php endif; ?>
                    </small>
                    <?php if ($e['aplicada']): ?>
                        <small class="esq-fecha"><?= htmlspecialchars($e['fecha']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="nota-esquema">El esquema mostrado es referencial y puede ajustarse
       en el catálogo del sistema conforme a la normativa vigente del Ministerio.</p>
</details>

<!-- ================= SUPLEMENTACIÓN ================= -->
    <h2 class="subtitulo-seccion"><?= Icono::ver('suplemento') ?> Suplementacion</h2>

<?php if ($puedeEditar): ?>
    <div class="barra-acciones">
        <a href="<?= APP_URL ?>/index.php?accion=suplemento_nuevo&id_nino=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
            Registrar suplemento
        </a>
    </div>
<?php endif; ?>

<?php if (empty($suplementos)): ?>
    <p class="vacio">No hay entregas de suplementos registradas.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Fecha</th><th>Suplemento</th><th>Cantidad</th><th>Registró</th></tr>
        </thead>
        <tbody>
            <?php foreach ($suplementos as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['fecha_entrega']) ?></td>
                    <td><?= htmlspecialchars($s['tipo']) ?></td>
                    <td><?= Formato::dato($s['cantidad']) ?></td>
                    <td><?= Formato::dato($s['registrado_por']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- ================= CITAS ================= -->
<h2 class="subtitulo-seccion"><?= Icono::ver('citas') ?> Citas</h2>

<?php if ($puedeEditar): ?>
    <div class="barra-acciones">
        <a href="<?= APP_URL ?>/index.php?accion=cita_nueva&id_nino=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
            Programar cita
        </a>
    </div>
<?php endif; ?>

<?php if (empty($citas)): ?>
    <p class="vacio">No hay citas registradas.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Asistencia</th><th>Programó</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['fecha_programada']) ?></td>
                    <td><?= htmlspecialchars($c['motivo'] ?? 'Control') ?></td>
                    <td>
                        <?php
                            $claseEstado = [
                                'programada'   => 'etiqueta-aviso',
                                'cumplida'     => 'etiqueta-activo',
                                'inasistencia' => 'nivel-riesgo',
                                'reprogramada' => 'etiqueta-egresado',
                            ][$c['estado']] ?? 'etiqueta-egresado';
                        ?>
                        <span class="etiqueta <?= $claseEstado ?>"><?= htmlspecialchars(ucfirst($c['estado'])) ?></span>
                    </td>
                    <td><?= Formato::dato($c['fecha_asistencia'], $c['estado'] === 'programada' ? 'Pendiente' : Formato::SIN_DATO) ?></td>
                    <td><?= Formato::dato($c['programada_por']) ?></td>
                    <td class="acciones-fila">
                        <?php if ($puedeEditar && $c['estado'] === 'programada'): ?>
                            <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado" style="display:inline;">
                                <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                <input type="hidden" name="estado" value="cumplida">
                                <button type="submit" class="enlace-boton">Asistió</button>
                            </form>
                            <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado" style="display:inline;">
                                <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                <input type="hidden" name="estado" value="inasistencia">
                                <button type="submit" class="enlace-boton">No asistió</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
