<?php
/**
 * Formularios de vacuna, suplemento y cita.
 * Variables: $nino, $tipo ('vacuna'|'suplemento'|'cita'), $catalogo, $errores;
 * para vacunas también $esquema (Vacuna::esquemaDelNino) y $registradas
 * (Vacuna::dosisRegistradas).
 */
$errores = $errores ?? [];
$titulos = [
    'vacuna'     => 'Registrar vacunas aplicadas',
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
    <span>Edad: <?= Formato::edad($nino['fecha_nacimiento']) ?></span>
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

    <?php
    // Varias dosis a la vez: en una visita casi siempre se ponen dos o tres
    // vacunas. Las dosis se agrupan para que la enfermera vea primero las que
    // le tocan al niño; las que ya tiene registradas no se ofrecen.
    $pendientes = array_filter($esquema, fn($e) => $e['estado'] === 'pendiente');
    $otras      = array_filter($esquema, fn($e) => in_array($e['estado'], ['proxima', 'cubierta'], true));
    $anteriores = array_filter($catalogo, fn($c) => !$c['vigente'] && !isset($registradas[$c['nombre'] . '|' . $c['dosis']]));
    $lugarElegido = (string)($_POST['lugar_vacunacion'] ?? Vacuna::LUGAR_PREDETERMINADO);

    // Casilla de una dosis, con su lote debajo (se ve al marcarla).
    function casillaDosis(int $id, string $nombre, string $dosis, ?string $nota): string
    {
        $marcada = ($_POST['dosis_' . $id] ?? '') === '1';
        $titulo  = htmlspecialchars($nombre . ' ' . $dosis);
        return '<div class="dosis-item">'
            . '<label class="dosis-casilla"><input type="checkbox" name="dosis_' . $id . '" value="1"' . ($marcada ? ' checked' : '') . '>'
            . '<span><strong>' . htmlspecialchars($nombre) . '</strong> ' . htmlspecialchars($dosis)
            . ($nota ? ' <small>' . htmlspecialchars($nota) . '</small>' : '') . '</span></label>'
            . '<div class="dosis-lote"><label for="lote_' . $id . '">Lote de ' . $titulo . '</label>'
            . '<input type="text" id="lote_' . $id . '" name="lote_' . $id . '" maxlength="50"'
            . ' value="' . htmlspecialchars(trim($_POST['lote_' . $id] ?? '')) . '"></div>'
            . '</div>';
    }

    // Un grupo plegado se abre si trae una dosis marcada (al volver con errores).
    $hayMarcada = fn(array $ids) => (bool)array_filter($ids, fn($id) => ($_POST['dosis_' . $id] ?? '') === '1');
    ?>

    <div class="fila">
        <div class="campo">
            <label for="fecha_aplicacion">Fecha de aplicación *</label>
            <input type="date" id="fecha_aplicacion" name="fecha_aplicacion" required
                   min="<?= htmlspecialchars($nino['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['fecha_aplicacion'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="campo">
            <label for="lugar_vacunacion">Lugar de vacunación *</label>
            <select id="lugar_vacunacion" name="lugar_vacunacion" required>
                <?php foreach (Vacuna::LUGARES as $codigo => $nombre): ?>
                    <option value="<?= $codigo ?>" <?= $lugarElegido === (string)$codigo ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p class="ayuda-grupo">La fecha y el lugar valen para todas las dosis que marque.</p>

    <fieldset>
        <legend>Dosis pendientes para su edad</legend>
        <?php if (!$pendientes): ?>
            <p class="vacio">No tiene dosis pendientes del esquema vigente.
                Si se le aplicó otra, búsquela en los grupos de abajo.</p>
        <?php else: ?>
            <div class="lista-dosis">
                <?php foreach ($pendientes as $e): ?>
                    <?= casillaDosis($e['id'], $e['nombre'], $e['dosis'], $e['edad_sugerida']) ?>
                <?php endforeach; ?>
            </div>
            <p class="ayuda-grupo dosis-ayuda">Marque las que se aplicaron y escriba el lote de cada una.</p>
        <?php endif; ?>
    </fieldset>

    <?php if ($otras): ?>
        <details class="bloque-dosis" <?= $hayMarcada(array_column($otras, 'id')) ? 'open' : '' ?>>
            <summary>Otras dosis del esquema vigente (<?= count($otras) ?>)</summary>
            <div class="lista-dosis">
                <?php foreach ($otras as $e): ?>
                    <?= casillaDosis($e['id'], $e['nombre'], $e['dosis'],
                        $e['estado'] === 'cubierta' ? 'cubierta con ' . $e['equivalente'] : $e['edad_sugerida']) ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ($anteriores): ?>
        <details class="bloque-dosis" <?= $hayMarcada(array_column($anteriores, 'id_catalogo_vacuna')) ? 'open' : '' ?>>
            <summary>Esquema anterior (carnet de 2018): solo dosis ya aplicadas</summary>
            <div class="lista-dosis">
                <?php foreach ($anteriores as $c): ?>
                    <?= casillaDosis((int)$c['id_catalogo_vacuna'], $c['nombre'], $c['dosis'], $c['edad_sugerida']) ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

<?php elseif ($tipo === 'suplemento'): ?>

    <?php $suplementoElegido = $_POST['tipo_sup'] ?? ''; ?>
    <div class="campo">
        <label for="tipo_sup">Suplemento *</label>
        <!-- Los suplementos salen del catálogo que mantiene el administrador -->
        <select id="tipo_sup" name="tipo_sup" required>
            <option value="">Seleccione</option>
            <?php foreach ($catalogo as $c): ?>
                <?php $edades = Suplementacion::etiquetaEdades($c['edad_desde_meses'], $c['edad_hasta_meses']); ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>"
                    data-detalle="<?= htmlspecialchars(implode(' · ', array_filter([$c['presentacion'], $c['indicacion'], $edades]))) ?>"
                    data-desde="<?= htmlspecialchars((string)$c['edad_desde_meses']) ?>"
                    data-hasta="<?= htmlspecialchars((string)$c['edad_hasta_meses']) ?>"
                    data-edades="<?= htmlspecialchars(mb_strtolower($edades)) ?>"
                    <?= $suplementoElegido === $c['nombre'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small id="detalle-sup" class="detalle-suplemento"></small>
        <p id="aviso-edad-sup" class="aviso-carga carga-alta" role="status" hidden></p>
    </div>

    <div class="fila">
        <div class="campo">
            <label for="fecha_entrega">Fecha de entrega *</label>
            <input type="date" id="fecha_entrega" name="fecha_entrega" required
                   min="<?= htmlspecialchars($nino['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="campo">
            <label for="cantidad">Cantidad entregada</label>
            <input type="text" id="cantidad" name="cantidad" maxlength="50"
                   placeholder="Ej. 30 sobres, 1 frasco"
                   value="<?= htmlspecialchars($_POST['cantidad'] ?? '') ?>">
        </div>
    </div>

    <script>
        // Al elegir el suplemento, muestra su presentación, indicación y edad, y
        // avisa si el niño está fuera de esa edad en la fecha de entrega. Solo
        // orienta a la enfermera: no impide guardar.
        (function () {
            var nacimiento = '<?= htmlspecialchars($nino['fecha_nacimiento']) ?>';
            var lista = document.getElementById('tipo_sup');
            var fecha = document.getElementById('fecha_entrega');
            var detalle = document.getElementById('detalle-sup');
            var aviso = document.getElementById('aviso-edad-sup');

            // Meses cumplidos entre dos fechas aaaa-mm-dd.
            function mesesCumplidos(desde, hasta) {
                var a = desde.split('-').map(Number), b = hasta.split('-').map(Number);
                return (b[0] - a[0]) * 12 + (b[1] - a[1]) - (b[2] < a[2] ? 1 : 0);
            }

            function revisar() {
                var opcion = lista.options[lista.selectedIndex];
                detalle.textContent = opcion && opcion.value ? opcion.dataset.detalle : '';
                aviso.hidden = true;
                if (!opcion || !opcion.value || !fecha.value) {
                    return;
                }
                var meses = mesesCumplidos(nacimiento, fecha.value);
                var desde = opcion.dataset.desde === '' ? null : Number(opcion.dataset.desde);
                var hasta = opcion.dataset.hasta === '' ? null : Number(opcion.dataset.hasta);
                if ((desde !== null && meses < desde) || (hasta !== null && meses > hasta)) {
                    aviso.textContent = 'Este suplemento es para niños ' + opcion.dataset.edades
                        + ' y el niño tiene ' + meses + (meses === 1 ? ' mes' : ' meses')
                        + ' en la fecha de entrega. Revise antes de guardar.';
                    aviso.hidden = false;
                }
            }

            lista.addEventListener('change', revisar);
            fecha.addEventListener('change', revisar);
            revisar();
        })();
    </script>

<?php else: /* cita */ ?>

    <div class="fila">
        <div class="campo">
            <label for="fecha_programada">Fecha de la cita *</label>
            <input type="date" id="fecha_programada" name="fecha_programada" required
                   min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                   value="<?= htmlspecialchars($_POST['fecha_programada'] ?? '') ?>">
            <p id="aviso-carga" class="aviso-carga" role="status" hidden></p>
        </div>
        <div class="campo">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo">
                <?php foreach (Cita::MOTIVOS as $motivo): ?>
                    <option value="<?= htmlspecialchars($motivo) ?>"
                        <?= (($_POST['motivo'] ?? '') === $motivo) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($motivo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="campo">
        <label for="observaciones">Observaciones</label>
        <textarea id="observaciones" name="observaciones" rows="2" maxlength="300"
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
