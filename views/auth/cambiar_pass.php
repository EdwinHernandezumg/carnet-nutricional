<?php

/**
 * Pantalla de cambio de contraseña obligatorio (primer ingreso tras reseteo).
 * También sirve para la contraseña nueva con el enlace enviado al correo: en
 * ese caso llegan $claveEnlace (va oculta en el formulario) y $nombreEnlace.
 * Variables: $error (opcional)
 */
$error = $error ?? null;
$claveEnlace = $claveEnlace ?? null;
$porEnlace = $claveEnlace !== null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar contraseña · <?= htmlspecialchars(APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
    <?php require __DIR__ . '/../layouts/pwa.php'; ?>
</head>

<script src="<?= Recurso::url('js/fuerza-contrasena.js') ?>"></script>

<body class="pagina-login">
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>
    <div class="tarjeta-login">
        <h1><?= $porEnlace ? 'Contraseña nueva' : 'Cambiar contraseña' ?></h1>
        <p class="subtitulo">
            <?= $porEnlace
                ? 'Hola, ' . htmlspecialchars($nombreEnlace) . '. Escriba su contraseña nueva.'
                : 'Por seguridad, defina una nueva contraseña personal.' ?>
        </p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/index.php?accion=<?= $porEnlace ? 'restablecer_guardar' : 'cambiar_pass_guardar' ?>">
            <?php if ($porEnlace): ?>
                <input type="hidden" name="clave" value="<?= htmlspecialchars($claveEnlace) ?>">
            <?php endif; ?>
            <label for="nueva">Nueva contraseña</label>
            <div class="medidor-fuerza" data-para="nueva"></div>

            <div class="con-icono con-boton">
                <?= Icono::ver('candado', 20) ?>
                <input type="password" id="nueva" name="nueva" required minlength="8" autofocus
                       autocomplete="new-password">
                <button type="button" class="boton-ver" data-para="nueva"
                        aria-label="Mostrar contraseña" aria-pressed="false">
                    <?= Icono::ver('ojo', 20) ?>
                </button>
            </div>

            <label for="repetir">Repetir contraseña</label>
            <div class="con-icono con-boton">
                <?= Icono::ver('candado', 20) ?>
                <input type="password" id="repetir" name="repetir" required minlength="8"
                       autocomplete="new-password">
                <button type="button" class="boton-ver" data-para="repetir"
                        aria-label="Mostrar contraseña" aria-pressed="false">
                    <?= Icono::ver('ojo', 20) ?>
                </button>
            </div>
            <p class="coincidencia" id="coincidencia" aria-live="polite"></p>

            <button type="submit"><?= $porEnlace ? 'Guardar contraseña' : 'Guardar y continuar' ?></button>
        </form>
        <?php if ($porEnlace): ?>
            <p class="salir-cambio"><a href="<?= APP_URL ?>/index.php?accion=inicio">Volver al ingreso</a></p>
        <?php else: ?>
            <!-- Para salir sin cambiarla: al volver a entrar, se pedirá de nuevo. -->
            <p class="salir-cambio"><a href="<?= APP_URL ?>/index.php?accion=logout">Salir sin cambiarla</a></p>
        <?php endif; ?>
    </div>

    <script>
        // Botones del ojo: muestran u ocultan cada contraseña mientras se escribe.
        document.querySelectorAll('.boton-ver').forEach(function (boton) {
            var campo = document.getElementById(boton.dataset.para);
            boton.addEventListener('click', function () {
                var mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                campo.focus();
            });
        });

        // Avisa mientras se escribe si las dos contraseñas coinciden, y no deja
        // enviar el formulario si no coinciden (el servidor también lo revisa).
        (function () {
            var nueva = document.getElementById('nueva');
            var repetir = document.getElementById('repetir');
            var aviso = document.getElementById('coincidencia');
            function revisar() {
                if (repetir.value === '') {
                    aviso.textContent = '';
                    aviso.className = 'coincidencia';
                    repetir.setCustomValidity('');
                    return;
                }
                var iguales = nueva.value === repetir.value;
                aviso.textContent = iguales ? 'Las contraseñas coinciden.' : 'Las contraseñas no coinciden.';
                aviso.className = 'coincidencia ' + (iguales ? 'coincidencia-ok' : 'coincidencia-mal');
                repetir.setCustomValidity(iguales ? '' : 'Las contraseñas no coinciden.');
            }
            nueva.addEventListener('input', revisar);
            repetir.addEventListener('input', revisar);
        })();
    </script>
</body>

</html>
