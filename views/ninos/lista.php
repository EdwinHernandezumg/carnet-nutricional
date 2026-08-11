<?php
/**
 * Listado de niños. Variables: $ninos, $sectores, $filtros, $mensaje (opcional).
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
?>

<h1 class="titulo-pagina">Niños registrados</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="get" action="<?= APP_URL ?>/index.php" class="filtros">
    <input type="hidden" name="accion" value="ninos">
    <input type="text" name="texto" placeholder="Buscar por nombre o CUI"
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
    <button type="submit" class="btn-primario">Buscar</button>
</form>

<?php if (empty($ninos)): ?>
    <p class="vacio">No se encontraron niños con los filtros seleccionados.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Edad</th>
                <th>Sexo</th>
                <th>Territorio / Sector</th>
                <th>Paraje</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ninos as $n): ?>
                <tr>
                    <td><?= htmlspecialchars($n['apellidos'] . ', ' . $n['nombres']) ?></td>
                    <td><?= edadTexto($n['fecha_nacimiento']) ?></td>
                    <td><?= $n['sexo'] === 'M' ? 'M' : 'F' ?></td>
                    <td>T<?= htmlspecialchars($n['territorio_numero']) ?> · <?= htmlspecialchars($n['sector_letra']) ?></td>
                    <td><?= htmlspecialchars($n['paraje_nombre'] ?? '—') ?></td>
                    <td>
                        <span class="etiqueta etiqueta-<?= $n['estado'] === 'activo' ? 'activo' : 'egresado' ?>">
                            <?= htmlspecialchars($n['estado']) ?>
                        </span>
                    </td>
                      <td class="acciones-fila">
                        <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$n['id_nino'] ?>">Ver</a>
                        <?php if (Auth::tieneRol('enfermeria')): ?>
                            <a href="<?= APP_URL ?>/index.php?accion=nino_editar&id=<?= (int)$n['id_nino'] ?>">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="conteo"><?= count($ninos) ?> niño(s) mostrados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
