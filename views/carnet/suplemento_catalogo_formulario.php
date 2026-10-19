<?php
/**
 * Formulario de un suplemento del catálogo: agregar uno nuevo o editar uno.
 * Variables: $datos, $errores, $suplemento (al editar), $nombreFijo (el
 * nombre no cambia si ya tiene entregas registradas).
 */
$errores = $errores ?? [];
$esEdicion = !empty($suplemento);
$titulo = $esEdicion ? 'Editar suplemento' : 'Agregar suplemento';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina"><?= $titulo ?></h1>

<?php if ($errores): ?>
    <div class="mensaje-error">
        <ul>
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post"
    action="<?= APP_URL ?>/index.php?accion=suplemento_catalogo_guardar<?= $esEdicion ? '&id=' . (int)$suplemento['id_catalogo_suplemento'] : '' ?>"
    class="formulario">

    <div class="campo">
        <label for="nombre">Nombre del suplemento *</label>
        <input type="text" id="nombre" name="nombre" required maxlength="<?= Suplementacion::NOMBRE_MAX ?>"
            placeholder="Por ejemplo: Suplemento nutricional lipídico (SNL)"
            value="<?= htmlspecialchars($datos['nombre']) ?>" <?= $nombreFijo ? 'readonly' : '' ?>>
        <?php if ($nombreFijo): ?>
            <small>El nombre no se puede cambiar porque ya tiene <?= (int)$suplemento['entregas'] ?>
                <?= (int)$suplemento['entregas'] === 1 ? 'entrega registrada' : 'entregas registradas' ?>.
                Si cambió el producto, desactive este y agregue el nuevo.</small>
        <?php endif; ?>
    </div>

    <div class="fila">
        <div class="campo">
            <label for="presentacion">Presentación</label>
            <input type="text" id="presentacion" name="presentacion" maxlength="<?= Suplementacion::PRESENTACION_MAX ?>"
                placeholder="Por ejemplo: Sobre de 20 g"
                value="<?= htmlspecialchars($datos['presentacion']) ?>">
        </div>
        <div class="campo">
            <label for="indicacion">Indicación</label>
            <input type="text" id="indicacion" name="indicacion" maxlength="<?= Suplementacion::INDICACION_MAX ?>"
                placeholder="Por ejemplo: 1 sobre al día; entrega mensual"
                value="<?= htmlspecialchars($datos['indicacion']) ?>">
        </div>
    </div>

    <div class="fila">
        <div class="campo">
            <label for="edad_desde_meses">Edad desde (meses)</label>
            <input type="text" id="edad_desde_meses" name="edad_desde_meses" inputmode="numeric" maxlength="2"
                data-filtro="digitos"
                value="<?= htmlspecialchars($datos['edad_desde_meses']) ?>">
        </div>
        <div class="campo">
            <label for="edad_hasta_meses">Edad hasta (meses)</label>
            <input type="text" id="edad_hasta_meses" name="edad_hasta_meses" inputmode="numeric" maxlength="2"
                data-filtro="digitos"
                value="<?= htmlspecialchars($datos['edad_hasta_meses']) ?>">
        </div>
    </div>
    <p class="ayuda-grupo">
        Opcional. Si se llena, al registrar una entrega el sistema avisa cuando el niño
        está fuera de esa edad, sin impedir el registro. Por ejemplo, de 6 a 23 meses.
    </p>

    <div class="acciones">
        <a href="<?= APP_URL ?>/index.php?accion=suplementos_catalogo" class="btn-secundario">Cancelar</a>
        <button type="submit" class="btn-primario"><?= $esEdicion ? 'Guardar cambios' : 'Agregar' ?></button>
    </div>
</form>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
