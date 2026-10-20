<?php
/**
 * Código de ingreso enviado al correo (RF-13): se pide en un teléfono o una
 * computadora nuevos, y siempre al administrador.
 * Variables: $u (usuario que espera el código), $error, $estadoEnvio
 * ('enviado', 'espera', 'error' o null) y $segundosEspera.
 */
$error = $error ?? null;
$esAdministrador = $u['rol'] === 'administrador';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código de ingreso · <?= htmlspecialchars(APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>

<body class="pagina-login">
<?php require __DIR__ . '/../layouts/franja_demostracion.php'; ?>
    <div class="tarjeta-login">
        <h1>Código de ingreso</h1>
        <p class="subtitulo">
            <?php if ($esAdministrador): ?>
                Por ser administrador, cada ingreso se confirma con un código.
            <?php else: ?>
                Es la primera vez que entra desde este teléfono o computadora.
            <?php endif; ?>
            Le enviamos un código de 6 números a <strong><?= htmlspecialchars(Verificacion::ocultarCorreo($u['correo'])) ?></strong>.
        </p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($estadoEnvio === 'error'): ?>
            <div class="mensaje-error">No se pudo enviar el correo. Toque "Enviar otro código" en un momento;
                si sigue sin llegar, avise al administrador.</div>
        <?php elseif ($estadoEnvio === 'espera'): ?>
            <div class="mensaje-error">Ya se le envió un código hace poco. Revise su correo, también la
                carpeta de spam, o espere un minuto para pedir otro.</div>
        <?php elseif ($estadoEnvio === 'enviado'): ?>
            <div class="mensaje-ok">Código enviado. Puede tardar un minuto en llegar; revise también la carpeta de spam.</div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/index.php?accion=verificar_codigo_guardar">
            <label for="codigo">Código</label>
            <input type="text" id="codigo" name="codigo" class="campo-codigo" required autofocus
                   inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                   title="Los 6 números del código" data-filtro="digitos" placeholder="000000">

            <?php if (!$esAdministrador): ?>
                <label class="casilla-recordar">
                    <input type="checkbox" name="recordar" value="1" checked>
                    <span>No volver a pedirlo en este dispositivo por <?= Verificacion::DIAS_DISPOSITIVO ?> días.
                        Desmárquelo si el teléfono o la computadora no son suyos.</span>
                </label>
            <?php endif; ?>

            <button type="submit">Confirmar</button>
        </form>

        <!-- Otro código: se puede pedir un minuto después del anterior. -->
        <form method="post" action="<?= APP_URL ?>/index.php?accion=reenviar_codigo" class="form-reenviar">
            <button type="submit" class="boton-enlace" id="reenviar"
                    data-espera="<?= (int)$segundosEspera ?>">Enviar otro código</button>
        </form>
        <p class="salir-cambio"><a href="<?= APP_URL ?>/index.php?accion=logout">Cancelar</a></p>
    </div>

    <script>
        // Mientras no se puede pedir otro código, el botón muestra cuánto falta.
        (function () {
            var boton = document.getElementById('reenviar');
            var faltan = parseInt(boton.dataset.espera, 10) || 0;
            function contar() {
                if (faltan <= 0) {
                    boton.disabled = false;
                    boton.textContent = 'Enviar otro código';
                    return;
                }
                boton.disabled = true;
                boton.textContent = 'Enviar otro código (' + faltan + ' s)';
                faltan--;
                setTimeout(contar, 1000);
            }
            contar();
        })();
    </script>
    <script src="<?= Recurso::url('js/validacion.js') ?>"></script>
</body>

</html>
