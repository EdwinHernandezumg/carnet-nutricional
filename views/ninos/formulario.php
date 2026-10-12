<?php

$esEdicion = isset($nino) && $nino;
$datos = $datos ?? ($nino ?? []);
$errores = $errores ?? [];
$titulo = $esEdicion ? 'Editar niño' : 'Registrar niño';

// En un registro nuevo, la fecha de nacimiento más antigua aceptada (menor de 7 años).
$fechaMinima = (new DateTime(Nino::fechaLimiteNacimiento()))->modify('+1 day')->format('Y-m-d');

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
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="nombres" name="nombres" required maxlength="100" data-filtro="nombre" autocapitalize="words"
                        placeholder="Nombres del niño"
                        value="<?= htmlspecialchars($datos['nombres'] ?? '') ?>">
                </div>
            </div>
            <div class="campo">
                <label for="apellidos">Apellidos *</label>
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="apellidos" name="apellidos" required maxlength="100" data-filtro="nombre" autocapitalize="words"
                        placeholder="Apellidos del niño"
                        value="<?= htmlspecialchars($datos['apellidos'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="fila">
            <div class="campo">
                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                <div class="con-icono">
                    <?= Icono::ver('calendario', 20) ?>
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                        max="<?= date('Y-m-d') ?>"
                        <?= $esEdicion ? '' : 'min="' . $fechaMinima . '"' ?>
                        value="<?= htmlspecialchars($datos['fecha_nacimiento'] ?? '') ?>">
                </div>
            </div>
            <div class="campo">
                <label for="sexo">Sexo *</label>
                <div class="con-icono">
                    <?= Icono::ver('sexo', 20) ?>
                    <select id="sexo" name="sexo" required>
                        <option value="">Seleccione</option>
                        <option value="M" <?= (($datos['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= (($datos['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>
            </div>
            <div class="campo">
                <label for="cui">CUI (si tiene)</label>
                <div class="con-icono">
                    <?= Icono::ver('documento', 20) ?>
                    <input type="text" id="cui" name="cui" maxlength="13" data-filtro="digitos" inputmode="numeric" pattern="[0-9]{13}" title="13 dígitos, sin espacios ni guiones"
                        placeholder="13 dígitos"
                        value="<?= htmlspecialchars($datos['cui'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="campo">
            <label for="etnia">Pueblo o comunidad lingüística</label>
            <div class="con-icono">
                <?= Icono::ver('idioma', 20) ?>
                <input type="text" id="etnia" name="etnia" maxlength="50" data-filtro="palabras"
                    value="<?= htmlspecialchars($datos['etnia'] ?? '') ?>"
                    placeholder="Maya k'iche', mestizo, etc.">
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Ubicación</legend>
        <div class="fila">
            <div class="campo">
                <label for="id_sector">Sector *</label>
                <div class="con-icono">
                    <?= Icono::ver('mapa', 20) ?>
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
            </div>
            <div class="campo">
                <label for="id_paraje">Paraje</label>
                <div class="con-icono">
                    <?= Icono::ver('ubicacion', 20) ?>
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
        </div>
        <div class="campo">
            <label for="direccion">Dirección</label>
            <div class="con-icono">
                <?= Icono::ver('casa', 20) ?>
                <input type="text" id="direccion" name="direccion" maxlength="200"
                    placeholder="Referencia de la vivienda"
                    value="<?= htmlspecialchars($datos['direccion'] ?? '') ?>">
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Familia</legend>
        <div class="fila">
            <div class="campo">
                <label for="nombre_madre">Nombre de la madre o responsable</label>
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="nombre_madre" name="nombre_madre" maxlength="150" data-filtro="nombre" autocapitalize="words"
                        placeholder="Nombre completo"
                        value="<?= htmlspecialchars($datos['nombre_madre'] ?? '') ?>">
                </div>
            </div>
            <div class="campo">
                <label for="nombre_padre">Nombre del padre</label>
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="nombre_padre" name="nombre_padre" maxlength="150" data-filtro="nombre" autocapitalize="words"
                        placeholder="Nombre completo"
                        value="<?= htmlspecialchars($datos['nombre_padre'] ?? '') ?>">
                </div>
            </div>
        </div>
        <p class="ayuda-grupo">Ingrese al menos uno: el nombre de la madre o responsable, o el del padre.</p>
        <div class="campo campo-telefono">
            <label for="telefono">Teléfono</label>
            <div class="con-icono">
                <?= Icono::ver('telefono', 20) ?>
                <input type="tel" id="telefono" name="telefono" data-filtro="digitos"
                    maxlength="8" pattern="[0-9]{8}"
                    inputmode="numeric"
                    placeholder="8 dígitos"
                    title="Ingrese 8 dígitos"
                    value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>">
            </div>
        </div>
    </fieldset>

    <div class="acciones">
                <a href="<?= APP_URL ?>/index.php?accion=ninos" class="btn-secundario"><?= Icono::ver('cancelar') ?> Cancelar</a>
        <button type="submit" class="btn-primario"><?= Icono::ver('guardar') ?> <?= $esEdicion ? 'Guardar cambios' : 'Registrar niño' ?></button>
    </div>
</form>

<!-- Filtra los parajes según el sector elegido -->
<script>
    (function() {
        var selSector = document.getElementById('id_sector');
        var selParaje = document.getElementById('id_paraje');
        var opciones = Array.prototype.slice.call(selParaje.options);

        function filtrar() {
            var sector = selSector.value;
            selParaje.innerHTML = '';
            opciones.forEach(function(op) {
                if (op.value === '' || op.getAttribute('data-sector') === sector) {
                    selParaje.appendChild(op);
                }
            });
        }
        selSector.addEventListener('change', filtrar);
        filtrar();
    })();

    // Exige al menos uno de los dos nombres (madre o padre) antes de enviar.
    // Es solo una comodidad: la regla también se valida en el servidor.
    (function() {
        var madre = document.getElementById('nombre_madre');
        var padre = document.getElementById('nombre_padre');

        function revisar() {
            var ambosVacios = madre.value.trim() === '' && padre.value.trim() === '';
            madre.setCustomValidity(ambosVacios
                ? 'Ingrese el nombre de la madre o responsable, o el del padre.'
                : '');
        }
        madre.addEventListener('input', revisar);
        padre.addEventListener('input', revisar);
        revisar();
    })();

    // Mensaje claro cuando la fecha queda fuera de rango (también se valida en el servidor).
    (function() {
        var fecha = document.getElementById('fecha_nacimiento');

        function revisarFecha() {
            var mensaje = '';
            if (fecha.validity.rangeUnderflow) {
                mensaje = 'El sistema registra niños menores de <?= Nino::EDAD_MAXIMA_ANIOS ?> años.';
            } else if (fecha.validity.rangeOverflow) {
                mensaje = 'La fecha de nacimiento no puede ser posterior a hoy.';
            }
            fecha.setCustomValidity(mensaje);
        }
        fecha.addEventListener('input', revisarFecha);
        fecha.addEventListener('change', revisarFecha);
        revisarFecha();
    })();
</script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
