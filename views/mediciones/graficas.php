<?php
/**
 * Vista de gráficas de crecimiento de un niño.
 * Variables: $nino, $graficas (arreglo con las 3, cada una con sus datos JSON)
 */
$titulo = 'Gráficas de crecimiento';
require __DIR__ . '/../layouts/encabezado.php';
?>

<h1 class="titulo-pagina">Gráficas de crecimiento</h1>

<div class="ficha-nino">
    <strong><?= htmlspecialchars($nino['apellidos'] . ', ' . $nino['nombres']) ?></strong>
    <span><?= $nino['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?></span>
    <span>Nac. <?= htmlspecialchars($nino['fecha_nacimiento']) ?></span>
    <span>Territorio <?= htmlspecialchars($nino['territorio_numero']) ?> · Sector <?= htmlspecialchars($nino['sector_letra']) ?></span>
</div>

<div class="barra-acciones">
    <a href="<?= APP_URL ?>/index.php?accion=nino_historial&id=<?= (int)$nino['id_nino'] ?>" class="btn-secundario">Volver al historial</a>
</div>

<p class="intro-panel">Las líneas muestran la referencia de la OMS: <strong>−2 DE</strong>
   (límite inferior), <strong>mediana</strong> y <strong>+2 DE</strong> (límite superior).
   Los puntos son las mediciones del niño. Si el recorrido se acerca o cruza la línea
   inferior, indica riesgo nutricional.</p>

<div class="grid-graficas">
    <div class="tarjeta-grafica">
        <h3>Peso para la edad</h3>
        <canvas id="grafica_peso_edad"></canvas>
    </div>
    <div class="tarjeta-grafica">
        <h3>Talla para la edad</h3>
        <canvas id="grafica_talla_edad"></canvas>
    </div>
    <div class="tarjeta-grafica">
        <h3>Peso para la talla</h3>
        <canvas id="grafica_peso_talla"></canvas>
    </div>
</div>

<!-- Chart.js desde CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    // Datos enviados desde PHP (ya en formato JSON).
    const datosGraficas = <?= json_encode($graficas) ?>;
</script>
<script src="<?= Recurso::url('js/graficas-crecimiento.js') ?>"></script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
