<?php
/**
 * Formularios de vacuna, suplemento y cita.
 * Variables: $nino, $tipo ('vacuna'|'suplemento'|'cita'), $catalogo, $errores
 */
$errores = $errores ?? [];
$titulos = [
    'vacuna'     => 'Registrar vacuna aplicada',
    'suplemento' => 'Registrar entrega de suplemento',
    'cita'       => 'Programar cita',
];
$titulo = $titulos[$tipo] ?? 'Registro';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina"><?= htmlspecialchars($titulo) ?></h1>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span>Nac. <?= Formato::fecha($nino['fecha_nacimiento']) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
</div>

<?php if ($errores): ?>
    <div class="mensaje-error">
        <ul><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= APP_URL ?>/index.php?accion=<?= $tipo ?>_guardar&id_nino=<?= (int)$nino['id_nino'] ?>"
      class="formulario">

<?php if ($tipo === 'vacuna'): ?>

    <div class="campo">
        <label for="vacuna_sel">Vacuna y dosis *</label>
        <select id="vacuna_sel" name="vacuna_sel" required>
            <option value="">Seleccione</option>
            <?php foreach ($catalogo as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre'] . '|' . $c['dosis']) ?>">
                    <?= htmlspecialchars($c['nombre'] . ' — ' . $c['dosis']) ?>
                    <?php if ($c['edad_sugerida']): ?>
                        (<?= htmlspecialchars($c['edad_sugerida']) ?>)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="fila">
        <div class="campo">
            <label for="fecha_aplicacion">Fecha de aplicación *</label>
            <input type="date" id="fecha_aplicacion" name="fecha_aplicacion" required
                   max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="campo">
            <label for="lote">Lote</label>
            <input type="text" id="lote" name="lote" maxlength="50">
        </div>
        <div class="campo">
            <label for="lugar">Lugar de aplicación</label>
            <input type="text" id="lugar" name="lugar" maxlength="100"
                   placeholder="Puesto de salud, jornada, domicilio">
        </div>
    </div>

<?php elseif ($tipo === 'suplemento'): ?>

    <div class="fila">
        <div class="campo">
            <label for="tipo_sup">Suplemento *</label>
            <select id="tipo_sup" name="tipo_sup" required>
                <option value="">Seleccione</option>
                <?php foreach ($catalogo as $c): ?>
                    <option value="<?= htmlspecialchars($c['nombre']) ?>">
                        <?= htmlspecialchars($c['nombre']) ?>
                        <?php if ($c['presentacion']): ?>
                            (<?= htmlspecialchars($c['presentacion']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="fecha_entrega">Fecha de entrega *</label>
            <input type="date" id="fecha_entrega" name="fecha_entrega" required
                   max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="campo">
            <label for="cantidad">Cantidad entregada</label>
            <input type="text" id="cantidad" name="cantidad" maxlength="50"
                   placeholder="Ej. 30 sobres, 1 frasco">
        </div>
    </div>

<?php else: /* cita */ ?>

    <div class="fila">
        <div class="campo">
            <label for="fecha_programada">Fecha de la cita *</label>
            <input type="date" id="fecha_programada" name="fecha_programada" required
                   min="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['fecha_programada'] ?? '') ?>">
            <p id="aviso-carga" class="aviso-carga" role="status" hidden></p>
        </div>
        <div class="campo">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo">
                <option value="Control de crecimiento">Control de crecimiento</option>
                <option value="Vacunación">Vacunación</option>
                <option value="Entrega de suplemento">Entrega de suplemento</option>
                <option value="Seguimiento nutricional">Seguimiento nutricional</option>
                <option value="Otro">Otro</option>
            </select>
        </div>
    </div>

    <div class="campo">
        <label for="observaciones">Observaciones</label>
        <textarea id="observaciones" name="observaciones" rows="2"
                  placeholder="Indicaciones para la madre o responsable (opcional)"></textarea>
    </div>

    <?php if (empty($nino['telefono'])): ?>
        <div class="aviso-instrumento">
            Este niño no tiene teléfono registrado, por lo que no se podrá enviar
            el aviso por WhatsApp. Puede agregarlo editando los datos del niño.
        </div>
    <?php endif; ?>

    <script>
        // Al elegir la fecha, avisa cuántas citas hay ese día y cuántas serían
        // con esta. Solo orienta a la enfermera: no impide guardar la cita.
        (function () {
            var cargaPorDia = <?= json_encode($cargaPorDia ?? [], JSON_FORCE_OBJECT) ?>;
            var limiteNormal = <?= Cita::CARGA_NORMAL ?>;
            var limiteAlta = <?= Cita::CARGA_ALTA ?>;
            var campo = document.getElementById('fecha_programada');
            var aviso = document.getElementById('aviso-carga');

            function mostrarCarga() {
                if (!campo.value) {
                    aviso.hidden = true;
                    return;
                }
                var cantidad = cargaPorDia[campo.value] || 0;
                var total = cantidad + 1;
                var texto, nivel;

                if (cantidad === 0) {
                    texto = 'Ese día no hay otras citas programadas.';
                } else if (cantidad === 1) {
                    texto = 'Ese día ya hay 1 cita programada.';
                } else {
                    texto = 'Ese día ya hay ' + cantidad + ' citas programadas.';
                }

                if (total > limiteAlta) {
                    nivel = 'excedida';
                    texto += ' Con esta serían ' + total + ', más del máximo observado en el puesto ('
                           + limiteAlta + '). Si es posible, elija otro día.';
                } else if (total > limiteNormal) {
                    nivel = 'alta';
                    texto += ' Con esta serían ' + total + ': día de alta afluencia.';
                } else {
                    nivel = 'normal';
                }

                aviso.className = 'aviso-carga carga-' + nivel;
                aviso.textContent = texto;
                aviso.hidden = false;
            }

            campo.addEventListener('change', mostrarCarga);
            campo.addEventListener('input', mostrarCarga);
            mostrarCarga();
        })();
    </script>

<?php endif; ?>

    <div class="acciones">
        <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$nino['id_nino'] ?>" class="btn-secundario">Cancelar</a>
        <button type="submit" class="btn-primario">Guardar</button>
    </div>
</form>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
