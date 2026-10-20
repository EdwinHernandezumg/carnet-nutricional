<?php
/**
 * Pantalla de ingreso. Como el puesto de salud no tiene logo, el nombre del
 * sistema hace de logo: aparece arriba, con un emblema y un leve movimiento
 * de flotación (ver "Pantalla de ingreso" en estilos.css).
 * Variables: $error y $aviso (opcionales).
 */
$error = $error ?? null;
$aviso = $aviso ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso · <?= htmlspecialchars(APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>

<body class="pagina-login">
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>
    <main class="ingreso">

        <!-- El nombre del sistema en lugar del logo, flotando suavemente -->
        <div class="ingreso-logo">
            <span class="ingreso-emblema"><?= Icono::ver('seguimiento', 40) ?></span>
            <h1 class="ingreso-nombre">Carnet <span class="marca-2">Digital</span></h1>
            <p class="ingreso-lema">Monitoreo Nutricional Infantil</p>
        </div>

        <p class="ingreso-puesto">
            <span>Puesto de Salud Chivarreto</span>
            <span>San Francisco El Alto, Totonicapán</span>
        </p>

        <div class="tarjeta-login">
            <?php if ($error): ?>
                <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($aviso): ?>
                <div class="mensaje-ok"><?= htmlspecialchars($aviso) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= APP_URL ?>/index.php?accion=login">
                <label for="usuario">Usuario</label>
                <div class="con-icono">
                    <?= Icono::ver('persona', 20) ?>
                    <input type="text" id="usuario" name="usuario" required autofocus
                           autocomplete="username" autocapitalize="none" spellcheck="false">
                </div>

                <label for="contrasena">Contraseña</label>
                <div class="con-icono con-boton">
                    <?= Icono::ver('candado', 20) ?>
                    <input type="password" id="contrasena" name="contrasena" required
                           autocomplete="current-password">
                    <button type="button" id="ver-contrasena" class="boton-ver"
                            aria-label="Mostrar contraseña" aria-pressed="false">
                        <?= Icono::ver('ojo', 20) ?>
                    </button>
                </div>

                <button type="submit"><?= Icono::ver('entrar', 20) ?> Ingresar</button>
            </form>
            <?php if (Correo::activo()): ?>
                <!-- Recuperación por correo; sin correo configurado, la hace el administrador. -->
                <p class="salir-cambio"><a href="<?= APP_URL ?>/index.php?accion=recuperar">¿Olvidó su contraseña?</a></p>
            <?php endif; ?>
        </div>

    </main>

    <footer class="ingreso-pie"><?= htmlspecialchars(APP_NOMBRE) ?></footer>

    <script>
        // Botón del ojo: muestra u oculta la contraseña mientras se escribe.
        (function () {
            var campo = document.getElementById('contrasena');
            var boton = document.getElementById('ver-contrasena');
            boton.addEventListener('click', function () {
                var mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                campo.focus();
            });
        })();
    </script>
</body>

</html>
