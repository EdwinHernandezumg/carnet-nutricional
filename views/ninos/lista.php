<?php
/**
 * Listado de niños. Variables: $ninos, $sectores, $filtros, $orden, $direccion,
 * $mensaje (opcional).
 * La tabla se ordena tocando el encabezado de cada columna; el orden elegido
 * se recuerda en el navegador (ver el caso 'ninos' en public/index.php).
 */
$titulo = 'Niños registrados';
require __DIR__ . '/../layouts/encabezado.php';

function edadTexto(string $fechaNac): string {
    $nac = new DateTime($fechaNac);
    $hoy = new DateTime('now');
    $d = $nac->diff($hoy);
    if ($d->y > 0) return $d->y . ($d->y === 1 ? ' año' : ' años');
    return $d->m . ($d->m === 1 ? ' mes' : ' meses');
}

/** Dirección de la lista con un orden dado, conservando los filtros de búsqueda. */
function urlOrden(string $columna, string $dir, array $filtros): string {
    $parametros = array_filter([
        'accion'    => 'ninos',
        'texto'     => $filtros['texto'] ?? '',
        'id_sector' => $filtros['id_sector'] ?? '',
        'estado'    => $filtros['estado'] ?? '',
        'orden'     => $columna,
        'dir'       => $dir,
    ], fn($valor) => $valor !== '');
    return APP_URL . '/index.php?' . http_build_query($parametros);
}

/**
 * Encabezado de una columna ordenable. Si la columna ya es la activa, el
 * enlace invierte el orden; si no, empieza con su dirección inicial.
 */
function encabezadoOrden(string $titulo, string $columna, string $ordenActual, string $dirActual, array $filtros): string {
    $activa = $columna === $ordenActual;
    $dir = $activa ? ($dirActual === 'asc' ? 'desc' : 'asc') : Nino::direccionInicial($columna);
    $url = urlOrden($columna, $dir, $filtros);

    $icono = $activa ? Icono::ver($dirActual === 'asc' ? 'orden-asc' : 'orden-desc', 16)
                     : Icono::ver('ordenar', 16);
    return '<a href="' . htmlspecialchars($url) . '" class="orden' . ($activa ? ' activa' : '') . '"'
         . ' title="Ordenar por ' . htmlspecialchars(mb_strtolower($titulo)) . '">'
         . htmlspecialchars($titulo) . ' ' . $icono . '</a>';
}

/** Atributo aria-sort, para que los lectores de pantalla anuncien el orden. */
function ariaOrden(string $columna, string $ordenActual, string $dirActual): string {
    if ($columna !== $ordenActual) return '';
    return ' aria-sort="' . ($dirActual === 'asc' ? 'ascending' : 'descending') . '"';
}

// Descripción de cada orden posible: para el pie de la tabla y para la
// lista "Ordenar" del teléfono.
$descripciones = [
    'nombre'     => ['asc' => 'por nombre, de la A a la Z',               'desc' => 'por nombre, de la Z a la A'],
    'edad'       => ['asc' => 'por edad, de menor a mayor',               'desc' => 'por edad, de mayor a menor'],
    'territorio' => ['asc' => 'por territorio y sector, de menor a mayor', 'desc' => 'por territorio y sector, de mayor a menor'],
    'paraje'     => ['asc' => 'por paraje, de la A a la Z',               'desc' => 'por paraje, de la Z a la A'],
    'estado'     => ['asc' => 'por estado, activos primero',              'desc' => 'por estado, egresados primero'],
    'registro'   => ['asc' => 'por fecha de registro, más antiguos primero', 'desc' => 'por fecha de registro, más recientes primero'],
];
$descripcionOrden = $descripciones[$orden][$direccion];
?>

<h1 class="titulo-pagina">Niños registrados</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="ninos">
    <input type="text" name="texto" placeholder="Buscar por nombre"
           value="<?= htmlspecialchars($filtros['texto'] ?? '') ?>">
    <select name="id_sector">
        <option value="">Todos los sectores</option>
        <?php foreach ($sectores as $s): ?>
            <option value="<?= $s['id_sector'] ?>"
                <?= (($filtros['id_sector'] ?? '') == $s['id_sector']) ? 'selected' : '' ?>>
                Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos</option>
        <option value="activo"   <?= (($filtros['estado'] ?? '') === 'activo') ? 'selected' : '' ?>>Activos</option>
        <option value="egresado" <?= (($filtros['estado'] ?? '') === 'egresado') ? 'selected' : '' ?>>Egresados</option>
    </select>
    <button type="submit" class="btn-primario"><?= Icono::ver('buscar') ?> Buscar</button>
</form>

<?php if (empty($ninos)): ?>
    <p class="vacio">No se encontraron niños con los filtros seleccionados.</p>
<?php else: ?>
    <p class="ayuda-tabla">Toque el título de una columna para ordenar la tabla. Si lo toca otra vez, invierte el orden.</p>

    <!-- En el teléfono no hay encabezados que tocar: el orden se elige aquí. -->
    <div class="orden-tel">
        <label for="orden-tel">Ordenar</label>
        <select id="orden-tel" onchange="window.location.href = this.value">
            <?php foreach ($descripciones as $columna => $direcciones): ?>
                <?php foreach ($direcciones as $dir => $texto): ?>
                    <option value="<?= htmlspecialchars(urlOrden($columna, $dir, $filtros)) ?>"
                        <?= ($columna === $orden && $dir === $direccion) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst(substr($texto, 4))) ?>
                    </option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tabla-scroll">
    <table class="tabla tabla-compacta tabla-tarjetas">
        <thead>
            <tr>
                <th<?= ariaOrden('nombre', $orden, $direccion) ?>><?= encabezadoOrden('Nombre', 'nombre', $orden, $direccion, $filtros) ?></th>
                <th<?= ariaOrden('edad', $orden, $direccion) ?>><?= encabezadoOrden('Edad', 'edad', $orden, $direccion, $filtros) ?></th>
                <th>Sexo</th>
                <th<?= ariaOrden('territorio', $orden, $direccion) ?>><?= encabezadoOrden('Territorio / Sector', 'territorio', $orden, $direccion, $filtros) ?></th>
                <th<?= ariaOrden('paraje', $orden, $direccion) ?>><?= encabezadoOrden('Paraje', 'paraje', $orden, $direccion, $filtros) ?></th>
                <th<?= ariaOrden('estado', $orden, $direccion) ?>><?= encabezadoOrden('Estado', 'estado', $orden, $direccion, $filtros) ?></th>
                <th<?= ariaOrden('registro', $orden, $direccion) ?>><?= encabezadoOrden('Registrado', 'registro', $orden, $direccion, $filtros) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ninos as $n): ?>
                <tr>
                    <td class="celda-titulo"><?= htmlspecialchars($n['apellidos'] . ', ' . $n['nombres']) ?></td>
                    <td data-label="Edad"><?= edadTexto($n['fecha_nacimiento']) ?></td>
                    <td data-label="Sexo"><?= $n['sexo'] === 'M' ? 'M' : 'F' ?></td>
                    <td data-label="Territorio / Sector">T<?= htmlspecialchars($n['territorio_numero']) ?> · <?= htmlspecialchars($n['sector_letra']) ?></td>
                    <td data-label="Paraje"><?= Formato::dato($n['paraje_nombre']) ?></td>
                    <td data-label="Estado">
                        <span class="etiqueta etiqueta-<?= $n['estado'] === 'activo' ? 'activo' : 'egresado' ?>">
                            <?= htmlspecialchars(ucfirst($n['estado'])) ?>
                        </span>
                    </td>
                    <td data-label="Registrado"><?= Formato::fecha($n['fecha_registro']) ?></td>
                    <td class="acciones-fila">
                        <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$n['id_nino'] ?>"><?= Icono::ver('ojo', 18) ?> Ver</a>
                        <?php if (Auth::tieneRol('enfermeria')): ?>
                            <a href="<?= APP_URL ?>/index.php?accion=nino_editar&id=<?= (int)$n['id_nino'] ?>"><?= Icono::ver('editar', 18) ?> Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="conteo"><?= count($ninos) ?> niño(s) mostrados, ordenados <?= $descripcionOrden ?>.</p>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
