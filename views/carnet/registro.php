<?php
/**
 * Anular o corregir un registro (medición, vacuna, suplemento o cita).
 * Variables: $tipo, $registro, $idRegistro, $esCorreccion, $fueraDelMes,
 * $errores, $datos, $motivo, $referencia, $urlHistorial.
 */
$definicion = Registro::TIPOS[$tipo];
$titulo = ($esCorreccion ? 'Corregir ' : 'Anular ') . $definicion['nombre'];
$fechaAtencion = Registro::fechaAtencion($tipo, $registro);

// Resumen del registro, para que se vea bien qué se va a anular o corregir.
$resumen = match ($tipo) {
    'medicion'   => 'Medición del ' . Formato::fecha($registro['fecha_toma']) . ': '
                    . htmlspecialchars($registro['peso_kg']) . ' kg, ' . htmlspecialchars($registro['talla_cm']) . ' cm ('
                    . ($registro['tipo_talla'] === 'longitud' ? 'acostado' : 'de pie') . ')',
    'vacuna'     => htmlspecialchars($registro['nombre_vacuna'] . ' ' . $registro['dosis'])
                    . ', aplicada el ' . Formato::fecha($registro['fecha_aplicacion'])
                    . ($registro['lote'] ? ', lote ' . htmlspecialchars($registro['lote']) : ''),
    'suplemento' => htmlspecialchars($registro['tipo']) . ', entregado el ' . Formato::fecha($registro['fecha_entrega'])
                    . ($registro['cantidad'] ? ' (' . htmlspecialchars($registro['cantidad']) . ')' : ''),
    'cita'       => 'Cita del ' . Formato::fecha($registro['fecha_programada']) . ': '
                    . htmlspecialchars($registro['motivo'] ?? 'Control') . ' (' . htmlspecialchars($registro['estado']) . ')',
};

require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina"><?= htmlspecialchars($titulo) ?></h1>

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

<div class="ficha-nino">
    <strong><?= htmlspecialchars($registro['apellidos'] . ', ' . $registro['nombres']) ?></strong>
    <span><?= $resumen ?></span>
</div>

<?php if ($esCorreccion): ?>
    <div class="aviso-instrumento">
        La corrección queda en la bitácora con el valor anterior, el nuevo y el motivo.
        Para cambiar la <?= $tipo === 'suplemento' ? 'clase de suplemento' : ($tipo === 'vacuna' ? 'vacuna o la dosis' : 'cita por otra') ?>,
        anule este registro y registre el correcto.
    </div>
<?php else: ?>
    <div class="aviso-instrumento">
        No se borra: queda tachada en el historial, con el motivo, y deja de contar en
        las alertas, las gráficas, los reportes y los formatos.
        <?php if ($tipo === 'medicion'): ?>
            Después puede registrar la medición correcta.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($fueraDelMes): ?>
    <div class="aviso-mes">
        <strong>Atención de un mes anterior (<?= Formato::fecha($fechaAtencion) ?>).</strong>
        Como en SIGSA Web, además del motivo se necesita la referencia del conocimiento.
    </div>
<?php endif; ?>

<form method="post" class="formulario"
    action="<?= APP_URL ?>/index.php?accion=<?= $esCorreccion ? 'registro_corregir_guardar' : 'registro_anular_guardar' ?>&tipo=<?= $tipo ?>&id=<?= (int)$idRegistro ?>"
    <?= $esCorreccion ? '' : 'onsubmit="return confirm(\'¿Anular este registro? Quedará tachado en el historial.\');"' ?>>

    <?php if ($esCorreccion): ?>
        <?php foreach (Registro::CORREGIBLES[$tipo] as $campo => $def): ?>
            <div class="campo">
                <label for="<?= $campo ?>"><?= htmlspecialchars($def['etiqueta']) ?><?= $def['tipo'] === 'texto' ? '' : ' *' ?></label>
                <?php if ($def['tipo'] === 'fecha'): ?>
                    <input type="date" id="<?= $campo ?>" name="<?= $campo ?>" required
                        value="<?= htmlspecialchars($datos[$campo] ?? '') ?>">
                <?php elseif ($def['tipo'] === 'lista'): ?>
                    <select id="<?= $campo ?>" name="<?= $campo ?>" required>
                        <?php foreach ($def['opciones'] as $clave => $texto): ?>
                            <?php $valor = array_is_list($def['opciones']) ? $texto : $clave; ?>
                            <option value="<?= htmlspecialchars((string)$valor) ?>"
                                <?= (string)($datos[$campo] ?? '') === (string)$valor ? 'selected' : '' ?>>
                                <?= htmlspecialchars($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" id="<?= $campo ?>" name="<?= $campo ?>" maxlength="<?= (int)$def['maximo'] ?>"
                        value="<?= htmlspecialchars($datos[$campo] ?? '') ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="campo">
        <label for="motivo_cambio">Motivo de la anulación o corrección *</label>
        <textarea id="motivo_cambio" name="motivo_cambio" rows="3" required minlength="5" maxlength="<?= Registro::MOTIVO_MAX ?>"
            placeholder="Por ejemplo: se escribió mal el peso; se registró en el niño equivocado"><?= htmlspecialchars($motivo) ?></textarea>
    </div>

    <?php if ($fueraDelMes): ?>
        <div class="campo">
            <label for="referencia">Referencia del conocimiento *</label>
            <input type="text" id="referencia" name="referencia" required maxlength="<?= Registro::REFERENCIA_MAX ?>"
                placeholder="Número o fecha del conocimiento" value="<?= htmlspecialchars($referencia) ?>">
        </div>
    <?php endif; ?>

    <div class="acciones">
        <a href="<?= $urlHistorial ?>" class="btn-secundario">Cancelar</a>
        <button type="submit" class="<?= $esCorreccion ? 'btn-primario' : 'btn-peligro' ?>">
            <?= $esCorreccion ? 'Guardar corrección' : 'Anular' ?>
        </button>
    </div>
</form>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
