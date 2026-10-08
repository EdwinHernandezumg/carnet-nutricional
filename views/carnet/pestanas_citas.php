<?php
/**
 * Pestañas de la sección Citas, compartidas por el calendario y la lista.
 * Variable: $pestanaActiva ('calendario' o 'lista').
 */
?>
<nav class="pestanas" aria-label="Vistas de citas">
    <a href="<?= APP_URL ?>/index.php?accion=citas_calendario"
       <?= $pestanaActiva === 'calendario' ? 'class="activa" aria-current="page"' : '' ?>>
        <?= Icono::ver('calendario', 20) ?> Calendario
    </a>
    <a href="<?= APP_URL ?>/index.php?accion=citas_proximas"
       <?= $pestanaActiva === 'lista' ? 'class="activa" aria-current="page"' : '' ?>>
        <?= Icono::ver('lista', 20) ?> Lista
    </a>
</nav>
