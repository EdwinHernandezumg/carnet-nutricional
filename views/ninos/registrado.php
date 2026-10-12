<?php
/**
 * Pantalla que aparece justo después de registrar un niño. Muestra a quién se
 * registró y ofrece el siguiente paso, para no tener que buscarlo en la lista.
 * Pensada primero para el teléfono: botones grandes, uno debajo del otro.
 * Variables: $nino, $mensaje.
 */
$titulo = 'Niño registrado';
require __DIR__ . '/../layouts/encabezado.php';

$idN = (int)$nino['id_nino'];
$url = APP_URL . '/index.php?accion=';
?>

<div class="registro-listo">
    <div class="registro-listo-icono"><?= Icono::ver('correcto', 44) ?></div>
    <p class="registro-listo-mensaje"><?= htmlspecialchars($mensaje) ?></p>
    <h1 class="registro-listo-nombre"><?= htmlspecialchars($nino['nombres'] . ' ' . $nino['apellidos']) ?></h1>
    <p class="registro-listo-datos">
        <?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?>
        · <?= Formato::edad($nino['fecha_nacimiento']) ?>
        · Nac. <?= Formato::fecha($nino['fecha_nacimiento']) ?>
        · T<?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?>
    </p>
</div>

<h2 class="opciones-titulo">¿Qué desea hacer ahora?</h2>

<nav class="opciones-siguientes" aria-label="Siguiente paso">
    <a href="<?= $url ?>medicion_nueva&id_nino=<?= $idN ?>" class="btn-primario">
        <?= Icono::ver('medicion') ?> Registrar medición
    </a>
    <a href="<?= $url ?>vacuna_nueva&id_nino=<?= $idN ?>" class="btn-secundario">
        <?= Icono::ver('vacuna') ?> Registrar vacuna
    </a>
    <a href="<?= $url ?>suplemento_nuevo&id_nino=<?= $idN ?>" class="btn-secundario">
        <?= Icono::ver('suplemento') ?> Registrar suplemento
    </a>
    <a href="<?= $url ?>cita_nueva&id_nino=<?= $idN ?>" class="btn-secundario">
        <?= Icono::ver('citas') ?> Programar cita
    </a>
    <a href="<?= $url ?>nino_historial&id=<?= $idN ?>" class="btn-secundario">
        <?= Icono::ver('historial') ?> Ver historial del niño
    </a>
    <a href="<?= $url ?>ninos" class="btn-secundario">
        <?= Icono::ver('volver') ?> Terminar y volver a la lista
    </a>
</nav>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
