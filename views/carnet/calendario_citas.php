<?php
/**
 * Calendario mensual de citas. Cada día muestra cuántas citas tiene, con un
 * color según la carga del puesto. Al tocar un día se listan sus citas abajo.
 * El calendario muestra la carga de todo el puesto, sin filtro por sector,
 * porque la capacidad de atención es del puesto completo.
 * Variables: $mes, $inicio, $fin, $dia, $conteo, $citasDia
 */
$titulo = 'Calendario de citas';
require __DIR__ . '/../layouts/encabezado.php';

$hoy          = date('Y-m-d');
$diaElegido   = $dia ? $dia->format('Y-m-d') : '';
$mesAnterior  = (clone $mes)->modify('-1 month')->format('Y-m');
$mesSiguiente = (clone $mes)->modify('+1 month')->format('Y-m');
$urlBase      = APP_URL . '/index.php?accion=citas_calendario';
$puedeEditar  = Auth::tieneRol('enfermeria');
?>

<h1 class="titulo-pagina">Citas</h1>

<?php $pestanaActiva = 'calendario'; require __DIR__ . '/pestanas_citas.php'; ?>

<div class="cal-encabezado">
    <h2 class="cal-mes"><?= htmlspecialchars(Cita::mesLegible($mes)) ?></h2>
    <div class="cal-navegacion">
        <a href="<?= $urlBase ?>&mes=<?= $mesAnterior ?>" class="btn-secundario"
           aria-label="Mes anterior" title="Mes anterior"><?= Icono::ver('volver', 20) ?></a>
        <a href="<?= $urlBase ?>" class="btn-secundario">Hoy</a>
        <a href="<?= $urlBase ?>&mes=<?= $mesSiguiente ?>" class="btn-secundario"
           aria-label="Mes siguiente" title="Mes siguiente"><?= Icono::ver('avanzar', 20) ?></a>
    </div>
</div>

<div class="cal-leyenda">
    <span><span class="cal-muestra carga-normal"></span> Hasta <?= Cita::CARGA_NORMAL ?> citas</span>
    <span><span class="cal-muestra carga-alta"></span> <?= Cita::CARGA_NORMAL + 1 ?> a <?= Cita::CARGA_ALTA ?> citas (alta afluencia)</span>
    <span><span class="cal-muestra carga-excedida"></span> Más de <?= Cita::CARGA_ALTA ?> citas</span>
</div>

<div class="calendario">
    <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia): ?>
        <div class="cal-dia-semana"><?= $nombreDia ?></div>
    <?php endforeach; ?>

    <?php for ($cuadro = clone $inicio; $cuadro <= $fin; $cuadro->modify('+1 day')): ?>
        <?php
        $fecha    = $cuadro->format('Y-m-d');
        $cantidad = $conteo[$fecha] ?? 0;
        $clases   = ['cal-dia'];
        if ($cuadro->format('Y-m') !== $mes->format('Y-m')) $clases[] = 'fuera-mes';
        if ($fecha < $hoy)          $clases[] = 'pasado';
        if ($fecha === $hoy)        $clases[] = 'hoy';
        if ($fecha === $diaElegido) $clases[] = 'elegido';
        ?>
        <a href="<?= $urlBase ?>&mes=<?= $cuadro->format('Y-m') ?>&dia=<?= $fecha ?>#citas-del-dia"
           class="<?= implode(' ', $clases) ?>"
           aria-label="<?= htmlspecialchars(Cita::fechaLegible($fecha)) ?>: <?= $cantidad ?> <?= $cantidad === 1 ? 'cita' : 'citas' ?>">
            <span class="cal-numero"><?= $cuadro->format('j') ?></span>
            <?php if ($cantidad > 0): ?>
                <span class="cal-carga carga-<?= Cita::nivelCarga($cantidad) ?>">
                    <?= $cantidad ?><span class="cal-texto-citas"> <?= $cantidad === 1 ? 'cita' : 'citas' ?></span>
                </span>
            <?php endif; ?>
        </a>
    <?php endfor; ?>
</div>

<section id="citas-del-dia">
<?php if (!$dia): ?>
    <p class="vacio">Toque un día del calendario para ver sus citas.</p>
<?php else: ?>
    <h2 class="subtitulo-seccion">
        <?= Icono::ver('citas') ?> Citas del <?= htmlspecialchars(Cita::fechaLegible($diaElegido)) ?>
    </h2>

    <?php if (empty($citasDia)): ?>
        <p class="vacio">No hay citas para este día.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla tabla-tarjetas">
            <thead>
                <tr>
                    <th>Niño</th>
                    <th>Sector</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Aviso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($citasDia as $c): ?>
                    <?php
                    // Solo una cita programada de hoy en adelante admite aviso por WhatsApp.
                    $admiteAviso = $c['estado'] === 'programada' && $diaElegido >= $hoy;
                    // Una cita programada que ya llegó o pasó se puede marcar como asistida o no.
                    $admiteAsistencia = $puedeEditar && $c['estado'] === 'programada' && $diaElegido <= $hoy;
                    $enlace = $admiteAviso ? Cita::enlaceWhatsApp($c) : '';
                    ?>
                    <tr>
                        <td class="celda-titulo">
                            <?= htmlspecialchars($c['apellidos'] . ', ' . $c['nombres']) ?>
                            <?php if (!empty($c['paraje_nombre'])): ?>
                                <br><small class="sub-dato"><?= htmlspecialchars($c['paraje_nombre']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Sector">T<?= htmlspecialchars($c['territorio_numero']) ?> · <?= htmlspecialchars($c['sector_letra']) ?></td>
                        <td data-label="Motivo"><?= Formato::dato($c['motivo']) ?></td>
                        <td data-label="Estado">
                            <span class="etiqueta <?= Cita::claseEstado($c['estado']) ?>"><?= htmlspecialchars(ucfirst($c['estado'])) ?></span>
                        </td>
                        <td data-label="Aviso">
                            <?php if (!$admiteAviso): ?>
                                <?= Formato::sinDato('No aplica') ?>
                            <?php elseif ($c['aviso_enviado']): ?>
                                <span class="etiqueta etiqueta-activo">Enviado</span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta-egresado">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones-fila">
                            <?php if ($admiteAviso): ?>
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
                            <?php endif; ?>
                            <?php if ($admiteAsistencia): ?>
                                <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado">
                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                    <input type="hidden" name="estado" value="cumplida">
                                    <button type="submit" class="enlace-boton">Asistió</button>
                                </form>
                                <form method="post" action="<?= APP_URL ?>/index.php?accion=cita_estado">
                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                    <input type="hidden" name="estado" value="inasistencia">
                                    <button type="submit" class="enlace-boton">No asistió</button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$c['id_nino'] ?>"><?= Icono::ver('ojo', 18) ?> Ver niño</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="conteo"><?= count($citasDia) ?> cita(s) en este día.</p>
    <?php endif; ?>
<?php endif; ?>
</section>

<script>
    // Marca el aviso como enviado al abrir WhatsApp (sin interrumpir la navegación).
    function marcarEnviado(idCita) {
        const url = '<?= APP_URL ?>/index.php?accion=cita_aviso_enviado&id=' + idCita;
        fetch(url).catch(function () { /* silencioso */ });
    }
</script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
