<?php
/**
 * Recuperar la contraseña con un enlace enviado al correo (RF-13).
 * Variables: $enviado (ya se pidió el enlace) y $error (opcional, por ejemplo
 * un enlace vencido).
 *
 * El mensaje después de pedirlo es el mismo exista o no el usuario, para no
 * revelar qué usuarios tiene el sistema.
 */
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · <?= htmlspecialchars(APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>

<body class="pagina-login">
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>
    <div class="tarjeta-login">
        <h1>Recuperar contraseña</h1>

        <?php if ($enviado): ?>
            <div class="mensaje-ok">
                Si el usuario existe y tiene un correo registrado, le enviamos un enlace para crear una
                contraseña nueva. Revise su correo, también la carpeta de spam. El enlace vence en
                <?= Verificacion::MINUTOS_ENLACE ?> minutos.
            </div>
            <p class="subtitulo">Si no le llega, pida al administrador que le restablezca la contraseña.</p>
        <?php else: ?>
            <p class="subtitulo">Escriba su nombre de usuario. Le enviaremos un enlace al correo que tiene
                registrado en el sistema.</p>

            <?php if ($error): ?>
                <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= APP_URL ?>/index.php?accion=recuperar_enviar">
                <label for="usuario">Usuario</label>
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="usuario" name="usuario" required autofocus
                           autocomplete="username" autocapitalize="none" spellcheck="false">
                </div>
                <button type="submit">Enviar enlace</button>
            </form>
            <p class="subtitulo nota-recuperar">Si no tiene correo registrado, el administrador puede
                restablecerle la contraseña.</p>
        <?php endif; ?>

        <p class="salir-cambio"><a href="<?= APP_URL ?>/index.php?accion=inicio">Volver al ingreso</a></p>
    </div>
</body>

</html>
