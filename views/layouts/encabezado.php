<?php
/**
 * Encabezado comun de todas las pantallas internas.
 * Incluye la barra de navegacion con iconos y el control de cierre de sesion
 * por inactividad.
 */

$u = Auth::usuario();
$segundos = Sesion::segundosRestantes();

// Accion que se esta mostrando, para resaltar el enlace correspondiente.
$accionActual = $_GET['accion'] ?? 'inicio';

// Devuelve el atributo class="activo" cuando la accion actual pertenece al grupo.
if (!function_exists('nav_activo')) {
    function nav_activo(string $accion, array $coincide): string {
        return in_array($accion, $coincide, true) ? ' class="activo"' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo ?? APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
</head>
<body>
<header class="barra-superior">

    <!-- El nombre del sistema lleva a la página principal, como el logo en otros sistemas -->
    <a class="barra-titulo" href="<?= APP_URL ?>/index.php?accion=inicio" title="Ir a la página principal">
        <strong>Carnet <span class="marca-2">Digital</span></strong>
        <span class="barra-sub">Monitoreo Nutricional Infantil</span>
    </a>

    <nav class="barra-nav">

        <a<?= nav_activo($accionActual, ['panel_alertas']) ?>
           href="<?= APP_URL ?>/index.php?accion=panel_alertas">
            <?= Icono::ver('seguimiento') ?> Seguimiento
        </a>

        <a<?= nav_activo($accionActual, ['ninos','nino_historial','nino_editar','nino_graficas']) ?>
           href="<?= APP_URL ?>/index.php?accion=ninos">
            <?= Icono::ver('ninos') ?> Niños
        </a>

        <a<?= nav_activo($accionActual, ['citas_calendario','citas_proximas','cita_nueva']) ?>
           href="<?= APP_URL ?>/index.php?accion=citas_calendario">
            <?= Icono::ver('citas') ?> Citas
        </a>

        <a<?= nav_activo($accionActual, ['reportes']) ?>
           href="<?= APP_URL ?>/index.php?accion=reportes">
            <?= Icono::ver('reportes') ?> Reportes
        </a>

        <?php if (Auth::tieneRol('enfermeria')): ?>
            <a<?= nav_activo($accionActual, ['nino_nuevo']) ?>
               href="<?= APP_URL ?>/index.php?accion=nino_nuevo">
                <?= Icono::ver('agregar') ?> Registrar niño
            </a>
        <?php endif; ?>

        <?php if (Auth::tieneRol('administrador')): ?>
            <a<?= nav_activo($accionActual, ['usuarios','usuario_nuevo']) ?>
               href="<?= APP_URL ?>/index.php?accion=usuarios">
                <?= Icono::ver('usuarios') ?> Usuarios
            </a>
        <?php endif; ?>

    </nav>

    <div class="barra-usuario">
        <span><?= htmlspecialchars($u['nombre']) ?> · <?= htmlspecialchars($u['rol']) ?></span>
        <a href="<?= APP_URL ?>/index.php?accion=logout" class="btn-salir">
            <?= Icono::ver('salir', 20) ?> Salir
        </a>
    </div>

</header>

<script>
    // Cierra la sesion en el navegador cuando se agota el tiempo de inactividad.
    (function () {
        var restante = <?= $segundos ?>; // segundos
        if (restante <= 0) { return; }
        setTimeout(function () {
            alert('La sesión se cerró por inactividad. Debe ingresar nuevamente.');
            window.location = '<?= APP_URL ?>/index.php?accion=logout';
        }, restante * 1000);
    })();
</script>

<main class="contenido">
