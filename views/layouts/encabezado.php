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

    <!--
        Menú principal. En la computadora va arriba, junto al nombre del sistema.
        En el teléfono se convierte en una barra fija abajo, al alcance del pulgar
        (ver "Teléfono" en estilos.css). data-orden fija el orden en esa barra,
        con Registrar al centro y destacado; texto-pc y texto-tel cambian la
        etiqueta larga por una corta donde no cabe.
    -->
    <nav class="barra-nav" aria-label="Menú principal">

        <a<?= nav_activo($accionActual, ['panel_alertas']) ?>
           href="<?= APP_URL ?>/index.php?accion=panel_alertas" data-orden="1">
            <?= Icono::ver('seguimiento') ?> <span>Seguimiento</span>
        </a>

        <a<?= nav_activo($accionActual, ['ninos','nino_historial','nino_editar','nino_graficas','nino_registrado']) ?>
           href="<?= APP_URL ?>/index.php?accion=ninos" data-orden="2">
            <?= Icono::ver('ninos') ?> <span>Niños</span>
        </a>

        <?php if (Auth::tieneRol('enfermeria')): ?>
            <a<?= nav_activo($accionActual, ['nino_nuevo']) ?>
               href="<?= APP_URL ?>/index.php?accion=nino_nuevo" data-orden="3" data-destacado="1">
                <?= Icono::ver('agregar') ?>
                <span class="texto-pc">Registrar niño</span><span class="texto-tel">Registrar</span>
            </a>
        <?php endif; ?>

        <?php if (Auth::tieneRol('administrador')): ?>
            <a<?= nav_activo($accionActual, ['usuarios','usuario_nuevo']) ?>
               href="<?= APP_URL ?>/index.php?accion=usuarios" data-orden="3">
                <?= Icono::ver('usuarios') ?> <span>Usuarios</span>
            </a>
        <?php endif; ?>

        <a<?= nav_activo($accionActual, ['citas_calendario','citas_proximas','cita_nueva']) ?>
           href="<?= APP_URL ?>/index.php?accion=citas_calendario" data-orden="4">
            <?= Icono::ver('citas') ?> <span>Citas</span>
        </a>

        <!-- Reportes al final: se consulta cada mes, no en el trabajo diario. -->
        <a<?= nav_activo($accionActual, ['reportes']) ?>
           href="<?= APP_URL ?>/index.php?accion=reportes" data-orden="5">
            <?= Icono::ver('reportes') ?> <span>Reportes</span>
        </a>

    </nav>

    <div class="barra-usuario">
        <span class="usuario-datos">
            <span class="usuario-nombre"><?= htmlspecialchars($u['nombre']) ?></span>
            <span class="usuario-rol"> · <?= htmlspecialchars($u['rol']) ?></span>
        </span>
        <a href="<?= APP_URL ?>/index.php?accion=logout" class="btn-salir" aria-label="Salir del sistema">
            <?= Icono::ver('salir', 20) ?> <span class="texto-pc">Salir</span>
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
