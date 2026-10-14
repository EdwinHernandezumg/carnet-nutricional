<?php
/**
 * Secciones de vacunación, suplementación y citas del niño.
 * Se incluye desde views/mediciones/historial.php.
 * Variables esperadas: $nino, $vacunas, $suplementos, $citas, $esquemaVacunas
 */
$puedeEditar = Auth::tieneRol('enfermeria') && $nino['estado'] === 'activo';
?>

<!-- ================= VACUNACIÓN ================= -->
    <h2 class="subtitulo-seccion"><?= Icono::ver('vacuna') ?> Vacunación</h2>

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
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr><th>Fecha</th><th>Vacuna</th><th>Dosis</th><th>Lote</th><th>Lugar</th><th>Registró</th></tr>
        </thead>
        <tbody>
            <?php foreach ($vacunas as $v): ?>
                <tr>
                    <td data-label="Fecha"><?= Formato::fecha($v['fecha_aplicacion']) ?></td>
                    <td class="celda-titulo"><?= htmlspecialchars($v['nombre_vacuna']) ?></td>
                    <td data-label="Dosis"><?= htmlspecialchars($v['dosis']) ?></td>
                    <td data-label="Lote"><?= Formato::dato($v['lote']) ?></td>
                    <td data-label="Lugar"><?= Formato::dato(Vacuna::etiquetaLugar($v['lugar_vacunacion'])) ?></td>
                    <td data-label="Registró"><?= Formato::dato($v['registrado_por']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Esquema completo: qué tiene, qué le falta y qué le toca después -->
<?php $pendientes = count(array_filter($esquemaVacunas, fn($e) => $e['estado'] === 'pendiente')); ?>
<details class="bloque-esquema">
    <summary>Ver esquema de vacunación completo
        <?php if ($pendientes > 0): ?>
            <span class="esq-aviso"><?= $pendientes ?> pendiente<?= $pendientes === 1 ? '' : 's' ?></span>
        <?php endif; ?>
    </summary>
    <div class="leyenda-esquema">
        <span><span class="esq-marca esq-marca-ok">●</span> Aplicada</span>
        <span><span class="esq-marca esq-marca-pendiente">●</span> Pendiente: por su edad ya le tocaba</span>
        <span><span class="esq-marca">○</span> Todavía no le toca</span>
    </div>
    <div class="grid-esquema">
        <?php foreach ($esquemaVacunas as $e): ?>
            <div class="item-esquema esq-<?= $e['estado'] ?>">
                <span class="esq-marca"><?= $e['estado'] === 'proxima' ? '○' : '●' ?></span>
                <div>
                    <strong><?= htmlspecialchars($e['nombre']) ?></strong>
                    <small><?= htmlspecialchars($e['dosis']) ?>
                        <?php if ($e['edad_sugerida']): ?>
                            · <?= htmlspecialchars($e['edad_sugerida']) ?>
                        <?php endif; ?>
                    </small>
                    <?php if ($e['estado'] === 'aplicada'): ?>
                        <small class="esq-fecha"><?= Formato::fecha($e['fecha']) ?></small>
                        <?php if ($e['equivalente']): ?>
                            <small>Con <?= htmlspecialchars($e['equivalente']) ?> (esquema anterior)</small>
                        <?php endif; ?>
                    <?php elseif ($e['estado'] === 'pendiente'): ?>
                        <small class="esq-falta">Pendiente</small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="nota-esquema">Esquema nacional de vacunación vigente, según el cuaderno
       SIGSA 5a 2026 del Ministerio de Salud. Las dosis del esquema anterior (carnet
       de 2018) aparecen en la lista de arriba y cuentan para la dosis equivalente.</p>
</details>

<!-- ================= SUPLEMENTACIÓN ================= -->
    <h2 class="subtitulo-seccion"><?= Icono::ver('suplemento') ?> Suplementación</h2>

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
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr><th>Fecha</th><th>Suplemento</th><th>Cantidad</th><th>Registró</th></tr>
        </thead>
        <tbody>
            <?php foreach ($suplementos as $s): ?>
                <tr>
                    <td data-label="Fecha"><?= Formato::fecha($s['fecha_entrega']) ?></td>
                    <td class="celda-titulo"><?= htmlspecialchars($s['tipo']) ?></td>
                    <td data-label="Cantidad"><?= Formato::dato($s['cantidad']) ?></td>
                    <td data-label="Registró"><?= Formato::dato($s['registrado_por']) ?></td>
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
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Asistencia</th><th>Programó</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td class="celda-titulo"><?= Formato::fecha($c['fecha_programada']) ?></td>
                    <td data-label="Motivo"><?= htmlspecialchars($c['motivo'] ?? 'Control') ?></td>
                    <td data-label="Estado">
                        <span class="etiqueta <?= Cita::claseEstado($c['estado']) ?>"><?= htmlspecialchars(ucfirst($c['estado'])) ?></span>
                    </td>
                    <td data-label="Asistencia"><?= Formato::fecha($c['fecha_asistencia'], $c['estado'] === 'programada' ? 'Pendiente' : Formato::SIN_DATO) ?></td>
                    <td data-label="Programó"><?= Formato::dato($c['programada_por']) ?></td>
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
