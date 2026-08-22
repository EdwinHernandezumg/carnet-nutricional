<?php

/**
 * Formulario de creación de usuario.
 * Variables: $roles, $sectores, $errores, $datos
 */
$errores = $errores ?? [];
$datos = $datos ?? [];
$titulo = 'Nuevo usuario';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina">Nuevo usuario</h1>

<?php if ($errores): ?>
    <div class="mensaje-error">
        <ul>
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= APP_URL ?>/index.php?accion=usuario_guardar" class="formulario">

    <div class="campo">
        <label for="nombre_completo">Nombre completo *</label>
        <input type="text" id="nombre_completo" name="nombre_completo" required
            value="<?= htmlspecialchars($datos['nombre_completo'] ?? '') ?>">
    </div>

    <div class="fila">
        <div class="campo">
            <label for="usuario">Nombre de usuario *</label>
            <input type="text" id="usuario" name="usuario" required
                value="<?= htmlspecialchars($datos['usuario'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="id_rol">Rol *</label>
            <select id="id_rol" name="id_rol" required>
                <option value="">Seleccione</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id_rol'] ?>"
                        <?= (($datos['id_rol'] ?? '') == $r['id_rol']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="campo">
        <label for="id_sector">Sector (solo para enfermería)</label>
        <select id="id_sector" name="id_sector">
            <option value="">Sin sector (administrador / consulta)</option>
            <?php foreach ($sectores as $s): ?>
                <option value="<?= $s['id_sector'] ?>"
                    <?= (($datos['id_sector'] ?? '') == $s['id_sector']) ? 'selected' : '' ?>>
                    Territorio <?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="aviso-instrumento">
        El sistema generará una contraseña temporal automáticamente. Se mostrará
        una vez creada, para comunicarla al usuario. Deberá cambiarla en su primer ingreso.
    </div>

    <div class="acciones">
        <a href="<?= APP_URL ?>/index.php?accion=usuarios" class="btn-secundario">Cancelar</a>
        <button type="submit" class="btn-primario">Crear usuario</button>
    </div>
</form>

<?php require __DIR__ . '/../layouts/pie.php'; ?>