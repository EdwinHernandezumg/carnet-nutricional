<?php

/**
 * Pantalla de cambio de contraseña obligatorio (primer ingreso tras reseteo).
 * Variables: $error (opcional)
 */
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar contraseña — <?= htmlspecialchars(APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>

<script src="<?= Recurso::url('js/fuerza-contrasena.js') ?>"></script>

<body class="pagina-login">
    <div class="tarjeta-login">
        <h1>Cambiar contraseña</h1>
        <p class="subtitulo">Por seguridad, defina una nueva contraseña personal.</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/index.php?accion=cambiar_pass_guardar">
            <label for="nueva">Nueva contraseña</label>
            <div class="medidor-fuerza" data-para="nueva"></div>

            <input type="password" id="nueva" name="nueva" required minlength="8" autofocus>

            <label for="repetir">Repetir contraseña</label>
            <input type="password" id="repetir" name="repetir" required minlength="8">

            <button type="submit">Guardar y continuar</button>
        </form>
    </div>
</body>

</html>