<?php

/**
 * Formulario de registro de medición.
 * Variables: $nino, $edadMeses, $tipoSugerido, $instrumento, $errores, $datos
 */
$errores = $errores ?? [];
$datos = $datos ?? [];
$titulo = 'Registrar medición';
require __DIR__ . '/../layouts/encabezado.php';

function edadLegible(int $meses): string
{
    $anios = intdiv($meses, 12);
    $m = $meses % 12;
    $partes = [];
    if ($anios > 0) $partes[] = $anios . ($anios === 1 ? ' año' : ' años');
    if ($m > 0)     $partes[] = $m . ($m === 1 ? ' mes' : ' meses');
    return $partes ? implode(' ', $partes) : 'menos de 1 mes';
}
?>

<h1 class="titulo-pagina">Registrar medición</h1>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span><?= edadLegible($edadMeses) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
</div>

<?php if ($nino['estado'] !== 'activo'): ?>
    <div class="mensaje-error">
        Este niño tiene estado <strong><?= htmlspecialchars($nino['estado']) ?></strong>.
        No se pueden registrar mediciones nuevas.
    </div>
<?php else: ?>

    <?php if ($errores): ?>
        <div class="mensaje-error">
            <strong>Revise los siguientes puntos:</strong>
            <ul>
                <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="aviso-instrumento"><?= htmlspecialchars($instrumento) ?></div>

    <form method="post" action="<?= APP_URL ?>/index.php?accion=medicion_guardar&id_nino=<?= (int)$nino['id_nino'] ?>" class="formulario">

        <div class="fila">
            <div class="campo">
                <label for="fecha_toma">Fecha de la toma *</label>
                <input type="date" id="fecha_toma" name="fecha_toma" required
                    max="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($datos['fecha_toma'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="campo">
                <label for="peso_kg">Peso (kg) *</label>
                <input type="number" id="peso_kg" name="peso_kg" required
                    step="0.01" min="0.5" max="59.99"
                    placeholder="Ej. 11.40"
                    value="<?= htmlspecialchars($datos['peso_kg'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="talla_cm">Talla / longitud (cm) *</label>
                <input type="number" id="talla_cm" name="talla_cm" required
                    step="0.1" min="30" max="150"
                    placeholder="Ej. 88.5"
                    value="<?= htmlspecialchars($datos['talla_cm'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="tipo_talla">Tipo de medición
                <small>(sugerido por la edad, puede ajustarlo)</small>
            </label>
            <select id="tipo_talla" name="tipo_talla" required>
                <option value="longitud" <?= (($datos['tipo_talla'] ?? $tipoSugerido) === 'longitud') ? 'selected' : '' ?>>
                    Longitud — acostado (infantómetro)
                </option>
                <option value="talla" <?= (($datos['tipo_talla'] ?? $tipoSugerido) === 'talla') ? 'selected' : '' ?>>
                    Talla — de pie (tallímetro)
                </option>
            </select>
        </div>

        <div class="campo">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="2"
                placeholder="Notas de la consulta (opcional)"><?= htmlspecialchars($datos['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="acciones">
            <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$nino['id_nino'] ?>" class="btn-secundario">Cancelar</a>
            <button type="submit" class="btn-primario">
                <?= Icono::ver('guardar') ?> Guardar medicion
            </button>
        </div>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>