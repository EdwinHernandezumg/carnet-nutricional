<?php
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso — <?= APP_NOMBRE ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>

<body class="pagina-login">
    <div class="tarjeta-login">
        <h1>Carnet <span class="marca-2">Digital</span></h1>

        <p class="subtitulo">Monitoreo Nutricional Infantil</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/index.php?accion=login">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autofocus>

            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required>

            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>

</html>