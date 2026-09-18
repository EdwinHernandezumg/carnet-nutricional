<?php
$u = Auth::usuario();
$segundos = Sesion::segundosRestantes();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo ?? APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/estilos.css">
</head>

<body>
    <header class="barra-superior">
        <div class="barra-titulo">
            <strong>Carnet <span class="marca-2">Digital</span></strong>
            <span class="barra-sub">Monitoreo Nutricional Infantil</span>
        </div>
        <nav class="barra-nav">
            <a href="<?= APP_URL ?>/index.php?accion=panel_alertas">Seguimiento</a>
            <a href="<?= APP_URL ?>/index.php?accion=ninos">Niños</a>
            <a href="<?= APP_URL ?>/index.php?accion=reportes">Reportes</a>
            <a href="<?= APP_URL ?>/index.php?accion=citas_proximas">Citas</a>

            <?php if (Auth::tieneRol('enfermeria')): ?>
                <a href="<?= APP_URL ?>/index.php?accion=nino_nuevo">Registrar niño</a>
            <?php endif; ?>
            <?php if (Auth::tieneRol('administrador')): ?>
                <a href="<?= APP_URL ?>/index.php?accion=usuarios">Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="barra-usuario">
            <span><?= htmlspecialchars($u['nombre']) ?> · <?= htmlspecialchars($u['rol']) ?></span>
            <a href="<?= APP_URL ?>/index.php?accion=logout" class="btn-salir">Salir</a>
        </div>
    </header>

    <!-- Aviso de cierre por inactividad -->
    <script>
        // Cierra la sesión en el navegador cuando se agota el tiempo de inactividad.
        (function() {
            var restante = <?= $segundos ?>; // segundos
            if (restante <= 0) {
                return;
            }
            setTimeout(function() {
                alert('La sesión se cerró por inactividad. Debe ingresar nuevamente.');
                window.location = '<?= APP_URL ?>/index.php?accion=logout';
            }, restante * 1000);
        })();
    </script>

    <main class="contenido">