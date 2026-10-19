<?php

/**
 * Historial del niño: datos y listado de mediciones.
 * Variables: $nino, $mediciones, $mensaje (opcional)
 */
$titulo = 'Historial del niño';
require __DIR__ . '/../layouts/encabezado.php';

function edadLegibleH(int $meses): string
{
    $anios = intdiv($meses, 12);
    $m = $meses % 12;
    $partes = [];
    if ($anios > 0) $partes[] = $anios . ($anios === 1 ? ' año' : ' años');
    if ($m > 0)     $partes[] = $m . ($m === 1 ? ' mes' : ' meses');
    return $partes ? implode(' ', $partes) : 'menos de 1 mes';
}

/**
 * Celda con la puntuación Z de un indicador, coloreada según el nivel.
 * $etiqueta es el título que se ve en la tarjeta del teléfono.
 */
function celdaZ(array $porTipo, string $tipo, string $etiqueta): string
{
    $td = '<td data-label="' . htmlspecialchars($etiqueta) . '">';
    if (!isset($porTipo[$tipo])) return $td . Formato::sinDato() . '</td>';
    $z = (float)$porTipo[$tipo]['puntuacion_z'];
    $clase = 'nivel-normal';
    if ($z < -2) $clase = 'nivel-riesgo';
    elseif ($z < -1) $clase = 'nivel-cerca';
    return $td . '<span class="etiqueta ' . $clase . '">'
        . number_format($z, 2) . '</span></td>';
}

/**
 * Botones para corregir o anular un registro, o la nota de anulación si ya
 * se anuló (queda tachado en la tabla, como la línea roja del carnet). Se usa
 * también en views/carnet/secciones_historial.php.
 */
function accionesRegistro(string $tipo, array $r, int $id, bool $puedeEditar): string
{
    if (!empty($r['anulado'])) {
        return '<span class="nota-anulado">Anulada el ' . Formato::fecha(substr((string)$r['fecha_anulacion'], 0, 10))
            . ' por ' . htmlspecialchars($r['anulado_por_nombre'] ?? '') . '. Motivo: '
            . htmlspecialchars($r['motivo_anulacion'] ?? '')
            . (!empty($r['referencia_conocimiento']) ? '. Conocimiento: ' . htmlspecialchars($r['referencia_conocimiento']) : '')
            . '</span>';
    }
    if (!$puedeEditar) {
        return '';
    }
    $url = APP_URL . '/index.php?accion=';
    $html = '';
    if (Registro::tipoValido($tipo, true)) {
        $html .= '<a class="enlace-boton" href="' . $url . 'registro_corregir&tipo=' . $tipo . '&id=' . $id . '">Corregir</a> ';
    }
    return $html . '<a class="enlace-boton enlace-anular" href="' . $url . 'registro_anular&tipo=' . $tipo . '&id=' . $id . '">Anular</a>';
}

$puedeEditar = Auth::tieneRol('enfermeria') && $nino['estado'] === 'activo';
?>

<h1 class="titulo-pagina">Historial del niño</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span>Edad: <?= Formato::edad($nino['fecha_nacimiento']) ?></span>
    <span>Nac. <?= Formato::fecha($nino['fecha_nacimiento']) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
    <span>Paraje: <?= Formato::dato($nino['paraje_nombre']) ?></span>
    <span class="etiqueta etiqueta-<?= $nino['estado'] === 'activo' ? 'activo' : 'egresado' ?>"><?= htmlspecialchars(ucfirst($nino['estado'])) ?></span>
</div>

<?php if (!empty($alertaActual)): ?>
    <div class="aviso-alerta <?= Alerta::claseNivel($alertaActual['nivel']) ?>">
        <strong><?= htmlspecialchars(Alerta::etiquetaNivel($alertaActual['nivel'])) ?>:</strong>
        <?= htmlspecialchars($alertaActual['descripcion']) ?>
    </div>
<?php endif; ?>

<div class="barra-acciones">
    <?php if (Auth::tieneRol('enfermeria') && $nino['estado'] === 'activo'): ?>
        <a href="<?= APP_URL ?>/index.php?accion=medicion_nueva&id_nino=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
            Nueva medición
        </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/index.php?accion=ninos" class="btn-secundario">Volver al listado</a>
    <a href="<?= APP_URL ?>/index.php?accion=nino_graficas&id=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
        Ver gráficas de crecimiento
    </a>
    <!-- Copia del carnet para la madre, el padre o el responsable -->
    <a href="<?= APP_URL ?>/index.php?accion=carnet_familia&id=<?= (int)$nino['id_nino'] ?>" target="_blank" class="btn-secundario">
        <?= Icono::ver('documento', 20) ?> Carnet para la familia
    </a>
</div>

<h2 class="subtitulo-seccion">Mediciones registradas</h2>

<?php if (empty($mediciones)): ?>
    <p class="vacio">Este niño aún no tiene mediciones registradas.</p>
<?php else: ?>
    <table class="tabla tabla-tarjetas">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Edad</th>
                <th>Peso (kg)</th>
                <th>Talla (cm)</th>
                <th>P/E (Z)</th>
                <th>T/E (Z)</th>
                <th>P/T (Z)</th>
                <th>Registró</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mediciones as $m): ?>
                <tr<?= $m['anulado'] ? ' class="fila-anulada"' : '' ?>>
                    <td class="celda-titulo"><?= Formato::fecha($m['fecha_toma']) ?></td>
                    <td data-label="Edad"><?= edadLegibleH((int)$m['edad_meses']) ?></td>
                    <td data-label="Peso (kg)"><?= htmlspecialchars($m['peso_kg']) ?></td>
                    <td data-label="Talla (cm)"><?= htmlspecialchars($m['talla_cm']) ?></td>
                    <?php
                    $inds = $indicadoresPorMedicion[$m['id_medicion']] ?? [];
                    $porTipo = [];
                    foreach ($inds as $i) {
                        $porTipo[$i['tipo']] = $i;
                    }
                    echo celdaZ($porTipo, 'peso_edad', 'P/E (Z)');
                    echo celdaZ($porTipo, 'talla_edad', 'T/E (Z)');
                    // Acostado (menor de 2 años) usa peso para longitud; de pie, peso para talla.
                    echo celdaZ($porTipo, isset($porTipo['peso_longitud']) ? 'peso_longitud' : 'peso_talla', 'P/T (Z)');
                    ?>
                    <td data-label="Registró"><?= htmlspecialchars($m['registrado_por']) ?></td>
                    <td class="acciones-fila"><?= accionesRegistro('medicion', $m, (int)$m['id_medicion'], $puedeEditar) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/../carnet/secciones_historial.php'; ?>
<?php require __DIR__ . '/../layouts/pie.php'; ?>