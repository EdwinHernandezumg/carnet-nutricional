<?php
/**
 * Catálogo de suplementos (área de administración).
 * Variables: $suplementos, $mensaje (opcional)
 */
$titulo = 'Catálogo de suplementos';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina">Catálogo de suplementos</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<p class="intro-panel">
    Son los suplementos que enfermería puede registrar como entregados. Un suplemento
    que ya no se entrega se desactiva: deja de aparecer en el formulario y las entregas
    anteriores se conservan en el historial de cada niño.
</p>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=suplemento_catalogo_nuevo" class="btn-primario">
        <?= Icono::ver('agregar', 20) ?> Agregar suplemento
    </a>
    <a href="<?= APP_URL ?>/index.php?accion=usuarios" class="btn-secundario">Volver a usuarios</a>
</div>

<table class="tabla tabla-tarjetas">
    <thead>
        <tr>
            <th>Suplemento</th>
            <th>Presentación</th>
            <th>Indicación</th>
            <th>Edad</th>
            <th>Entregas</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($suplementos as $s): ?>
            <tr>
                <td class="celda-titulo"><?= htmlspecialchars($s['nombre']) ?></td>
                <td data-label="Presentación"><?= Formato::dato($s['presentacion']) ?></td>
                <td data-label="Indicación"><?= Formato::dato($s['indicacion']) ?></td>
                <td data-label="Edad"><?= Formato::dato(Suplementacion::etiquetaEdades($s['edad_desde_meses'], $s['edad_hasta_meses']), 'Todas') ?></td>
                <td data-label="Entregas"><?= (int)$s['entregas'] ?></td>
                <td data-label="Estado">
                    <span class="etiqueta etiqueta-<?= $s['activo'] ? 'activo' : 'egresado' ?>">
                        <?= $s['activo'] ? 'Activo' : 'Desactivado' ?>
                    </span>
                </td>
                <td class="acciones-fila">
                    <a href="<?= APP_URL ?>/index.php?accion=suplemento_catalogo_editar&id=<?= (int)$s['id_catalogo_suplemento'] ?>"
                       class="enlace-boton">Editar</a>
                    <form method="post" action="<?= APP_URL ?>/index.php?accion=suplemento_catalogo_estado"
                          <?= $s['activo'] ? 'onsubmit="return confirm(\'¿Desactivar este suplemento? Ya no aparecerá al registrar entregas.\');"' : '' ?>
                          style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$s['id_catalogo_suplemento'] ?>">
                        <input type="hidden" name="activo" value="<?= $s['activo'] ? '0' : '1' ?>">
                        <button type="submit" class="enlace-boton<?= $s['activo'] ? ' enlace-anular' : '' ?>">
                            <?= $s['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
