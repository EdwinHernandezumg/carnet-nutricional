<?php
/**
 * Historial del niño: datos y listado de mediciones.
 * Variables: $nino, $mediciones, $mensaje (opcional)
 */
$titulo = 'Historial del niño';
require __DIR__ . '/../layouts/encabezado.php';

function edadLegibleH(int $meses): string {
    $anios = intdiv($meses, 12);
    $m = $meses % 12;
    $partes = [];
    if ($anios > 0) $partes[] = $anios . ($anios === 1 ? ' año' : ' años');
    if ($m > 0)     $partes[] = $m . ($m === 1 ? ' mes' : ' meses');
    return $partes ? implode(' ', $partes) : 'menos de 1 mes';
}

function celdaZ(array $porTipo, string $tipo): string {
    if (!isset($porTipo[$tipo])) return '<td>—</td>';
    $z = (float)$porTipo[$tipo]['puntuacion_z'];
    $clase = 'nivel-normal';
    if ($z < -2) $clase = 'nivel-riesgo';
    elseif ($z < -1) $clase = 'nivel-cerca';
    return '<td><span class="etiqueta ' . $clase . '">'
         . number_format($z, 2) . '</span></td>';
}

?>

<h1 class="titulo-pagina">Historial del niño</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span>Nac. <?= htmlspecialchars($nino['fecha_nacimiento']) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
    <span>Paraje: <?= htmlspecialchars($nino['paraje_nombre'] ?? '—') ?></span>
    <span class="etiqueta etiqueta-<?= $nino['estado'] === 'activo' ? 'activo' : 'egresado' ?>"><?= htmlspecialchars($nino['estado']) ?></span>
</div>

<div class="barra-acciones">
    <?php if (Auth::tieneRol('enfermeria') && $nino['estado'] === 'activo'): ?>
        <a href="<?= APP_URL ?>/index.php?accion=medicion_nueva&id_nino=<?= (int)$nino['id_nino'] ?>" class="btn-primario">
            Nueva medición
        </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/index.php?accion=ninos" class="btn-secundario">Volver al listado</a>
</div>

<h2 class="subtitulo-seccion">Mediciones registradas</h2>

<?php if (empty($mediciones)): ?>
    <p class="vacio">Este niño aún no tiene mediciones registradas.</p>
<?php else: ?>
    <table class="tabla">
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
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mediciones as $m): ?>
                 <tr>
                    <td><?= htmlspecialchars($m['fecha_toma']) ?></td>
                    <td><?= edadLegibleH((int)$m['edad_meses']) ?></td>
                    <td><?= htmlspecialchars($m['peso_kg']) ?></td>
                    <td><?= htmlspecialchars($m['talla_cm']) ?></td>
                    <?php
                        $inds = $indicadoresPorMedicion[$m['id_medicion']] ?? [];
                        $porTipo = [];
                        foreach ($inds as $i) { $porTipo[$i['tipo']] = $i; }
                        echo celdaZ($porTipo, 'peso_edad');
                        echo celdaZ($porTipo, 'talla_edad');
                        echo celdaZ($porTipo, 'peso_longitud') !== '<td>—</td>'
                             ? celdaZ($porTipo, 'peso_longitud')
                             : celdaZ($porTipo, 'peso_talla');
                    ?>
                    <td><?= htmlspecialchars($m['registrado_por']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
