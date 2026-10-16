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
    <title>Cambiar contraseña · <?= htmlspecialchars(APP_NOMBRE) ?></title>
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

            <button type="submit">Guardar y continuar</button>
        </form>
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
