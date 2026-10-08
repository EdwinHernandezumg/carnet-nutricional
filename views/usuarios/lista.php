<?php
/**
 * Listado de usuarios (área de administración).
 * Variables: $usuarios, $mensaje (opcional), $temporal (opcional)
 */
$titulo = 'Gestión de usuarios';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina">Gestión de usuarios</h1>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if (!empty($temporal)): ?>
    <div class="caja-temporal">
        <strong>Contraseña temporal generada:</strong>
        <code class="pass-temporal"><?= htmlspecialchars($temporal) ?></code>
        <p>Comuníquela al usuario. Se le pedirá cambiarla al ingresar.
           Esta contraseña no se volverá a mostrar.</p>
    </div>
<?php endif; ?>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=usuario_nuevo" class="btn-primario">Nuevo usuario</a>
</div>

<table class="tabla">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Sector</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                <td><?= htmlspecialchars($u['usuario']) ?></td>
                <td><?= htmlspecialchars($u['rol']) ?></td>
                <td>
                    <?php if ($u['sector_letra']): ?>
                        T<?= htmlspecialchars($u['territorio_numero']) ?> · <?= htmlspecialchars($u['sector_letra']) ?>
                    <?php else: ?>
                        <?= Formato::sinDato('Sin sector') ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="etiqueta etiqueta-<?= $u['activo'] ? 'activo' : 'egresado' ?>">
                        <?= $u['activo'] ? 'activo' : 'inactivo' ?>
                    </span>
                    <?php if ($u['debe_cambiar_pass']): ?>
                        <span class="etiqueta etiqueta-aviso">pass temporal</span>
                    <?php endif; ?>
                </td>
                <td class="acciones-fila">
                    <!-- Restablecer contraseña -->
                    <form method="post" action="<?= APP_URL ?>/index.php?accion=usuario_restablecer"
                          onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');"
                          style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>">
                        <button type="submit" class="enlace-boton">Restablecer contraseña</button>
                    </form>

                    <!-- Baja / reactivar -->
                    <form method="post" action="<?= APP_URL ?>/index.php?accion=usuario_estado"
                          style="display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>">
                        <input type="hidden" name="activo" value="<?= $u['activo'] ? '0' : '1' ?>">
                        <button type="submit" class="enlace-boton">
                            <?= $u['activo'] ? 'Dar de baja' : 'Reactivar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
