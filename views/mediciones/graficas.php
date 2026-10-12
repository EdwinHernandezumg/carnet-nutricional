<?php
/**
 * Vista de gráficas de crecimiento de un niño.
 * Variables: $nino, $graficas (arreglo con las 3, cada una con sus datos JSON)
 *
 * Primero el teléfono: una gráfica debajo de otra. Cada una se puede ampliar a
 * pantalla completa, y de entrada muestran solo el tramo de edad o talla del
 * niño, para que sus puntos no queden amontonados (ver graficas-crecimiento.js).
 */
$titulo = 'Gráficas de crecimiento';
require __DIR__ . '/../layouts/encabezado.php';

$tarjetas = [
    'peso_edad'  => 'grafica_peso_edad',
    'talla_edad' => 'grafica_talla_edad',
    'peso_talla' => 'grafica_peso_talla',
];
?>

<h1 class="titulo-pagina">Gráficas de crecimiento</h1>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span>Edad: <?= Formato::edad($nino['fecha_nacimiento']) ?></span>
    <span>Nac. <?= Formato::fecha($nino['fecha_nacimiento']) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
</div>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$nino['id_nino'] ?>" class="btn-secundario">
        <?= Icono::ver('volver') ?> Volver al historial
    </a>
</div>

<p class="intro-panel">Las líneas muestran la referencia de la OMS: <strong>-2 DE</strong>
   (límite inferior), <strong>mediana</strong> y <strong>+2 DE</strong> (límite superior).
   Los puntos son las mediciones del niño; toque uno para ver la fecha del control.
   Si el recorrido se acerca o cruza la línea inferior, indica riesgo nutricional.</p>

<!-- Qué tramo de la curva se muestra: el del niño (sus puntos se ven separados) o toda. -->
<div class="vista-graficas" role="group" aria-label="Tramo de la curva que se muestra">
    <button type="button" class="activa" data-vista="nino" aria-pressed="true">Edad del niño</button>
    <button type="button" data-vista="completa" aria-pressed="false" title="De 0 a 5 años">Curva completa</button>
</div>

<div class="grid-graficas">
    <?php foreach ($tarjetas as $clave => $idCanvas): ?>
        <?php if (empty($graficas[$clave])) continue; ?>
        <section class="tarjeta-grafica" aria-labelledby="titulo_<?= $idCanvas ?>">
            <div class="tarjeta-grafica-cabecera">
                <h3 id="titulo_<?= $idCanvas ?>"><?= htmlspecialchars($graficas[$clave]['titulo']) ?></h3>
                <button type="button" class="btn-secundario boton-ampliar" data-ampliar>
                    <span class="icono-ampliar"><?= Icono::ver('ampliar', 20) ?></span>
                    <span class="icono-cerrar"><?= Icono::ver('cancelar', 20) ?></span>
                    <span data-texto>Ampliar</span>
                </button>
            </div>
            <p class="aviso-girar">Gire el teléfono para ver la gráfica más ancha.</p>
            <div class="lienzo-grafica">
                <canvas id="<?= $idCanvas ?>"
                        aria-label="<?= htmlspecialchars($graficas[$clave]['titulo']) ?> de <?= htmlspecialchars($nino['nombres']) ?>"
                        role="img"></canvas>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<!-- Chart.js desde CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    // Datos enviados desde PHP (ya en formato JSON).
    const datosGraficas = <?= json_encode($graficas) ?>;
</script>
<script src="<?= Recurso::url('js/graficas-crecimiento.js') ?>"></script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
