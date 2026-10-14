<?php
/**
 * Panel de indicadores (inteligencia de negocios del puesto).
 * Variables: $filtros, $ninos, $resumen, $tendencia, $territorios, $sectores, $parajes
 *
 * Las gráficas y la lista de niños las dibuja public/js/panel-indicadores.js
 * con los datos de $datosPanel. Tocar un sector o un paraje filtra todo el
 * panel; tocar una tarjeta, una porción o una barra de condición muestra la
 * lista de los niños que hay detrás de esa cifra.
 */
$titulo = 'Panel de indicadores';
require __DIR__ . '/../layouts/encabezado.php';

$t   = $resumen['tarjetas'];
$qs  = http_build_query(Reporte::parametros($filtros));
$url = APP_URL . '/index.php?accion=';

$etiquetasNivel = [];
foreach (Reporte::NIVELES as $nivel) {
    $etiquetasNivel[$nivel] = Reporte::etiquetaNivel($nivel);
}

// Datos para el JavaScript. JSON_HEX_* evita que un texto como "</script>"
// dentro de un nombre pueda romper o alterar la página.
$datosPanel = [
    'urlPanel'        => $url . 'reportes',
    'urlHistorial'    => $url . 'nino_historial&id=',
    'parametros'      => Reporte::parametros($filtros),
    'resumen'         => $resumen,
    'tendencia'       => $tendencia,
    'etiquetasNivel'  => $etiquetasNivel,
    'condiciones'     => Reporte::CONDICIONES,
    'ninos'           => array_map(fn($n) => [
        'id'          => $n['id_nino'],
        'nombre'      => $n['nombre'],
        'sector'      => $n['sector'],
        'paraje'      => $n['paraje'],
        'edad'        => $n['edad_texto'],
        'control'     => $n['fecha_control'] ? Formato::fechaTexto($n['fecha_control']) : null,
        'nivel'       => $n['nivel'],
        'condiciones' => $n['condiciones'],
        'atrasado'    => $n['atrasado'],
    ], $ninos),
];
?>

<h1 class="titulo-pagina">Panel de indicadores</h1>
<p class="intro-panel">Análisis del estado nutricional para tomar decisiones: cuántos
   niños, dónde están y si se está mejorando. Cada niño se cuenta una vez, por su
   condición más grave, igual que en el <a href="<?= $url ?>panel_alertas<?= $filtros['id_sector'] !== '' ? '&id_sector=' . (int)$filtros['id_sector'] : '' ?>">panel de seguimiento</a>.
   Toque una tarjeta, una barra o una porción para ver qué niños hay detrás de la cifra.</p>

<!-- Exportar: los tres formatos con los filtros aplicados -->
<div class="barra-exportar">
    <span>Exportar:</span>
    <a href="<?= $url ?>reporte_imprimir&<?= htmlspecialchars($qs) ?>" target="_blank" class="btn-secundario">
        <?= Icono::ver('imprimir', 20) ?> PDF
    </a>
    <a href="<?= $url ?>reporte_excel&<?= htmlspecialchars($qs) ?>" class="btn-secundario">
        <?= Icono::ver('descargar', 20) ?> Excel
    </a>
    <a href="<?= $url ?>reporte_csv&<?= htmlspecialchars($qs) ?>" class="btn-secundario">
        <?= Icono::ver('descargar', 20) ?> CSV
    </a>
</div>

<!-- Formatos oficiales del MSPAS llenos con los datos del sistema -->
<div class="barra-exportar">
    <span>Formatos SIGSA:</span>
    <a href="<?= $url ?>sigsa3" target="_blank" class="btn-secundario">
        <?= Icono::ver('documento', 20) ?> SIGSA 3 del día
    </a>
</div>

<!-- Filtros: en el teléfono van plegados para no ocupar la pantalla -->
<details class="panel-filtros" id="panel-filtros">
    <summary>
        <?= Icono::ver('filtrar', 20) ?>
        <span><strong>Filtros:</strong> <?= htmlspecialchars(Reporte::descripcionFiltros($filtros)) ?></span>
    </summary>
    <form method="get" action="<?= APP_URL ?>/index.php" class="formulario-filtros">
        <input type="hidden" name="accion" value="reportes">
        <label>Período
            <select name="periodo" id="filtro-periodo">
                <?php foreach (Reporte::PERIODOS as $clave => $nombre): ?>
                    <option value="<?= $clave ?>" <?= $filtros['periodo'] === $clave ? 'selected' : '' ?>><?= $nombre ?></option>
                <?php endforeach; ?>
                <option value="" <?= $filtros['periodo'] === '' ? 'selected' : '' ?>>Elegir fechas</option>
            </select>
        </label>
        <label>Desde
            <input type="date" name="desde" id="filtro-desde" value="<?= htmlspecialchars($filtros['desde']) ?>" max="<?= date('Y-m-d') ?>">
        </label>
        <label>Hasta
            <input type="date" name="hasta" id="filtro-hasta" value="<?= htmlspecialchars($filtros['hasta']) ?>" max="<?= date('Y-m-d') ?>">
        </label>
        <label>Territorio
            <select name="id_territorio">
                <option value="">Todos</option>
                <?php foreach ($territorios as $ter): ?>
                    <option value="<?= (int)$ter['id_territorio'] ?>" <?= $filtros['id_territorio'] === (string)$ter['id_territorio'] ? 'selected' : '' ?>>
                        Territorio <?= htmlspecialchars($ter['numero']) ?> (<?= htmlspecialchars($ter['nombre']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sector
            <select name="id_sector" id="filtro-sector">
                <option value="">Todos</option>
                <?php foreach ($sectores as $s): ?>
                    <option value="<?= (int)$s['id_sector'] ?>" <?= $filtros['id_sector'] === (string)$s['id_sector'] ? 'selected' : '' ?>>
                        T<?= htmlspecialchars($s['territorio']) ?> · Sector <?= htmlspecialchars($s['letra']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Paraje
            <select name="id_paraje" id="filtro-paraje">
                <option value="">Todos</option>
                <?php foreach ($parajes as $p): ?>
                    <option value="<?= (int)$p['id_paraje'] ?>" data-sector="<?= (int)$p['id_sector'] ?>"
                        <?= $filtros['id_paraje'] === (string)$p['id_paraje'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sexo
            <select name="sexo">
                <option value="">Ambos</option>
                <option value="M" <?= $filtros['sexo'] === 'M' ? 'selected' : '' ?>>Niños</option>
                <option value="F" <?= $filtros['sexo'] === 'F' ? 'selected' : '' ?>>Niñas</option>
            </select>
        </label>
        <label>Edad
            <select name="grupo_edad">
                <option value="">Todas</option>
                <?php foreach (Reporte::GRUPOS_EDAD as $clave => [$nombre]): ?>
                    <option value="<?= $clave ?>" <?= $filtros['grupo_edad'] === $clave ? 'selected' : '' ?>><?= $nombre ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filtros-botones">
            <button type="submit" class="btn-primario"><?= Icono::ver('filtrar', 20) ?> Aplicar</button>
            <a href="<?= $url ?>reportes" class="btn-secundario">Quitar filtros</a>
        </div>
    </form>
</details>

<!-- Tarjetas: al tocarlas se muestran los niños de esa cifra. Las cuatro de
     estado nutricional usan la regla del panel de seguimiento (cada niño una
     vez, por su condición más grave), en el mismo orden. -->
<div class="tarjetas-panel">
    <button type="button" class="kpi" data-lista="todos">
        <span class="kpi-numero"><?= $t['activos'] ?></span>
        <span class="kpi-nombre">Niños activos</span>
    </button>
    <button type="button" class="kpi" data-lista="evaluados">
        <span class="kpi-numero"><?= $t['porcentaje_evaluados'] !== null ? $t['porcentaje_evaluados'] . ' %' : '0' ?></span>
        <span class="kpi-nombre"><?= $filtros['periodo'] === 'actual' ? 'Con control registrado' : 'Evaluados en el período' ?> (<?= $t['evaluados'] ?>)</span>
    </button>
    <button type="button" class="kpi kpi-alerta" data-lista="atrasados">
        <span class="kpi-numero"><?= $t['atrasados'] ?></span>
        <span class="kpi-nombre">Controles atrasados</span>
    </button>
    <div class="kpi kpi-fijo">
        <span class="kpi-numero"><?= $t['inasistencia'] !== null ? $t['inasistencia'] . ' %' : Formato::sinDato('Sin citas') ?></span>
        <span class="kpi-nombre">Inasistencia a citas</span>
    </div>
    <button type="button" class="kpi kpi-nivel tr-roja" data-lista="nivel:desnutricion">
        <span class="kpi-numero"><?= $t['desnutricion'] ?></span>
        <span class="kpi-nombre">Desnutrición</span>
    </button>
    <button type="button" class="kpi kpi-nivel tr-amarilla" data-lista="nivel:cerca_umbral">
        <span class="kpi-numero"><?= $t['cerca_umbral'] ?></span>
        <span class="kpi-nombre">Cerca del umbral</span>
    </button>
    <button type="button" class="kpi kpi-nivel tr-naranja" data-lista="nivel:riesgo">
        <span class="kpi-numero"><?= $t['riesgo'] ?></span>
        <span class="kpi-nombre">Riesgo (bajo peso)</span>
    </button>
    <button type="button" class="kpi kpi-nivel tr-azul" data-lista="nivel:sobrepeso">
        <span class="kpi-numero"><?= $t['sobrepeso'] ?></span>
        <span class="kpi-nombre">Sobrepeso</span>
    </button>
</div>

<div class="grid-panel">
    <section class="tarjeta-grafica">
        <h3>Estado nutricional por sector</h3>
        <p class="grafica-ayuda">Toque un sector para filtrar todo el panel.</p>
        <div class="lienzo-panel lienzo-alto"><canvas id="grafica_sector" role="img" aria-label="Estado nutricional por sector"></canvas></div>
    </section>
    <section class="tarjeta-grafica">
        <h3>Parajes con más casos</h3>
        <p class="grafica-ayuda">Niños con alguna alerta. Toque un paraje para filtrar el panel.</p>
        <div class="lienzo-panel lienzo-alto"><canvas id="grafica_paraje" role="img" aria-label="Parajes con más niños con alerta"></canvas></div>
    </section>
    <section class="tarjeta-grafica">
        <h3>Condición más grave de cada niño</h3>
        <p class="grafica-ayuda">Cada niño se cuenta una vez: la misma regla de las tarjetas y del panel de seguimiento. Toque una porción para ver quiénes son.</p>
        <div class="lienzo-panel"><canvas id="grafica_nivel" role="img" aria-label="Distribución por condición más grave"></canvas></div>
    </section>
    <section class="tarjeta-grafica">
        <h3>Cada condición por separado</h3>
        <p class="grafica-ayuda">Un niño puede contar en varias a la vez (por ejemplo, aguda y crónica). Por eso estas cifras no coinciden con las tarjetas ni se suman.</p>
        <div class="lienzo-panel"><canvas id="grafica_condicion" role="img" aria-label="Niños por cada condición"></canvas></div>
    </section>
    <section class="tarjeta-grafica tarjeta-ancha">
        <h3>Tendencia de los últimos 12 meses</h3>
        <p class="grafica-ayuda">Porcentaje de los niños medidos cada mes. ¿Estamos mejorando?</p>
        <div class="lienzo-panel"><canvas id="grafica_tendencia" role="img" aria-label="Tendencia mensual de alertas"></canvas></div>
    </section>
</div>

<!-- Detalle: los niños detrás de la cifra elegida -->
<section class="detalle-panel" id="detalle-panel" aria-live="polite">
    <div class="detalle-cabecera">
        <h2 class="subtitulo-seccion" id="detalle-titulo">Niños con alguna alerta</h2>
        <button type="button" class="btn-secundario" id="detalle-quitar" hidden>Volver a los niños con alerta</button>
    </div>
    <div id="detalle-lista"></div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    const datosPanel = <?= json_encode($datosPanel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= Recurso::url('js/panel-indicadores.js') ?>"></script>

<?php require __DIR__ . '/../layouts/pie.php'; ?>
