<?php
/**
 * Formulario de registro/edición de niño.
 * Variables esperadas: $sectores, $parajes, $nino (null si es nuevo),
 * $errores (array), $datos (valores previos en caso de error).
 */
$esEdicion = isset($nino) && $nino;
$datos = $datos ?? ($nino ?? []);
$errores = $errores ?? [];
$titulo = $esEdicion ? 'Editar niño' : 'Registrar niño';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina"><?= $titulo ?></h1>

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

<form method="post"
      action="<?= APP_URL ?>/index.php?accion=<?= $esEdicion ? 'nino_actualizar&id=' . (int)$nino['id_nino'] : 'nino_guardar' ?>"
      class="formulario">

    <fieldset>
        <legend>Identificación del niño</legend>

        <div class="fila">
            <div class="campo">
                <label for="nombres">Nombres *</label>
                <input type="text" id="nombres" name="nombres" required
                       value="<?= htmlspecialchars($datos['nombres'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos" required
                       value="<?= htmlspecialchars($datos['apellidos'] ?? '') ?>">
            </div>
        </div>

        <div class="fila">
            <div class="campo">
                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                       value="<?= htmlspecialchars($datos['fecha_nacimiento'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="sexo">Sexo *</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione</option>
                    <option value="M" <?= (($datos['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= (($datos['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
            <div class="campo">
                <label for="cui">CUI (si tiene)</label>
                <input type="text" id="cui" name="cui" maxlength="13"
                       value="<?= htmlspecialchars($datos['cui'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="etnia">Pueblo o comunidad lingüística</label>
            <input type="text" id="etnia" name="etnia"
                   value="<?= htmlspecialchars($datos['etnia'] ?? '') ?>"
                   placeholder="Maya k'iche', mestizo, etc.">
        </div>
    </fieldset>

    <fieldset>
        <legend>Ubicación</legend>
        <div class="fila">
            <div class="campo">
                <label for="id_sector">Sector *</label>
                <select id="id_sector" name="id_sector" required>
                    <option value="">Seleccione</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= $s['id_sector'] ?>"
                            <?= (($datos['id_sector'] ?? '') == $s['id_sector']) ? 'selected' : '' ?>>
                            Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="id_paraje">Paraje</label>
                <select id="id_paraje" name="id_paraje">
                    <option value="">Seleccione</option>
                    <?php foreach ($parajes as $p): ?>
                        <option value="<?= $p['id_paraje'] ?>"
                            data-sector="<?= $p['id_sector'] ?>"
                            <?= (($datos['id_paraje'] ?? '') == $p['id_paraje']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="campo">
            <label for="direccion">Dirección</label>
            <input type="text" id="direccion" name="direccion"
                   value="<?= htmlspecialchars($datos['direccion'] ?? '') ?>">
        </div>
    </fieldset>

    <fieldset>
        <legend>Familia</legend>
        <div class="fila">
            <div class="campo">
                <label for="nombre_madre">Nombre de la madre o responsable</label>
                <input type="text" id="nombre_madre" name="nombre_madre"
                       value="<?= htmlspecialchars($datos['nombre_madre'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="nombre_padre">Nombre del padre</label>
                <input type="text" id="nombre_padre" name="nombre_padre"
                       value="<?= htmlspecialchars($datos['nombre_padre'] ?? '') ?>">
            </div>
        </div>
        <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono"
                   value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>">
        </div>
    </fieldset>

    <div class="acciones">
        <a href="<?= APP_URL ?>/index.php?accion=ninos" class="btn-secundario">Cancelar</a>
        <button type="submit" class="btn-primario"><?= $esEdicion ? 'Guardar cambios' : 'Registrar niño' ?></button>
    </div>
</form>

<!-- Filtra los parajes según el sector elegido -->
<script>
    (function () {
        var selSector = document.getElementById('id_sector');
        var selParaje = document.getElementById('id_paraje');
        var opciones = Array.prototype.slice.call(selParaje.options);

        function filtrar() {
            var sector = selSector.value;
            selParaje.innerHTML = '';
            opciones.forEach(function (op) {
                if (op.value === '' || op.getAttribute('data-sector') === sector) {
                    selParaje.appendChild(op);
                }
            });
        }
        selSector.addEventListener('change', filtrar);
        filtrar();
    })();
</script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
