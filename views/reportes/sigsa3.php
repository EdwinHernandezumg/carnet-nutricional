<?php
/**
 * Formato SIGSA 3 (FOR-SIGSA-3CS v3.0) con las atenciones a niños ya llenas.
 * Réplica en hoja en blanco, con las mismas columnas y proporciones que el
 * formato oficial (docs/formatos-sigsa/SIGSA-3CS-v3.0-2017.xlsx), pensado
 * para hoja oficio horizontal. Las filas sin datos quedan en blanco para los
 * demás pacientes del día.
 * Variables: $filtros, $puesto, $filas
 */
$hojas = FormatoSigsa3::hojas($filas);
$papel = FormatoSigsa3::PAPELES[$filtros['papel']];

// Anchos de las columnas del formato oficial (en puntos, tomados del Excel del
// MSPAS). Se pasan a porcentaje para que la réplica guarde las proporciones.
$anchos = [84.6, 84.6, 291, 148.8, 22.2, 22.2, 22.2, 28.8, 28.8, 28.8, 22.2, 22.2, 22.2, 22.2,
           223.8, 187.2, 22.2, 22.2, 22.2, 22.2, 22.2, 22.2, 79.2, 266.4, 120, 160.8, 147, 95.4,
           22.2, 68.4, 328.2];
$total = array_sum($anchos);

// Varias líneas dentro de una celda.
$lineas = fn(array $valores) => implode('<br>', array_map('htmlspecialchars', $valores));
$celda = fn($valor) => htmlspecialchars((string)$valor);

$periodo = $filtros['mes_completo']
    ? 'todo ' . $filtros['mes'] . ' de ' . $filtros['anio']
    : 'el ' . Formato::fechaTexto($filtros['fecha']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGSA 3 · <?= htmlspecialchars($periodo) ?> · Carnet Digital</title>
    <style>
        @page { size: <?= $papel[1] ?>; margin: 0.45cm; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #e9eef2;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        /* Barra de pantalla: no se imprime. */
        .barra { max-width: 1000px; margin: 0 auto; padding: 14px 16px; font-size: 15px; color: #1f2d3a; }
        .barra form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .barra label { display: flex; flex-direction: column; gap: 4px; font-weight: 700; font-size: 14px; }
        .barra label.check { flex-direction: row; align-items: center; gap: 6px; min-height: 44px; }
        .barra input[type=date], .barra select { font: inherit; padding: 8px; min-height: 44px; border: 1.5px solid #b5c4d2; border-radius: 8px; }
        .barra button, .barra a.boton {
            font: inherit; font-weight: 700; border-radius: 8px; padding: 10px 16px; min-height: 44px;
            border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center;
        }
        .barra .principal { background: #1d9e75; color: #fff; }
        .barra .secundario { background: #fff; color: #0f6e56; border: 1.5px solid #b5ddce; }
        .barra .acciones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .barra p { margin: 10px 0 0; font-size: 13.5px; color: #44556a; }
        .barra .resumen { font-weight: 700; color: #14304a; font-size: 15px; }

        .vista { overflow-x: auto; padding: 0 16px 24px; }
        .hoja-sigsa {
            width: 13in; margin: 0 auto 18px; background: #fff; padding: 0.45cm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.12);
        }

        /* Encabezado del formato. */
        .enc { display: grid; grid-template-columns: 16% 1fr 14%; border: 1px solid #000; align-items: stretch; }
        .enc > div { padding: 2px 6px; }
        .enc .ministerio { font-size: 6.5pt; line-height: 1.15; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: center; }
        .enc .titulo { text-align: center; }
        .enc .titulo small { display: block; font-size: 6pt; border-bottom: 1px solid #000; margin: 0 -6px; }
        .enc .titulo h1 { font-size: 12pt; margin: 4px 0 2px; }
        .enc .codigo { border-left: 1px solid #000; font-size: 6pt; text-align: center; line-height: 1.25; }
        .enc .codigo strong { display: block; font-size: 9pt; }
        .datos { display: flex; gap: 10px; font-size: 7pt; margin-top: 4px; }
        .datos span { flex: 1; white-space: nowrap; overflow: hidden; }
        .datos b { font-weight: normal; border-bottom: 1px solid #000; padding: 0 4px; }
        .datos .linea { display: inline-block; border-bottom: 1px solid #000; width: 60%; }

        /* Tabla del registro. */
        table.registro { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        .registro th, .registro td { border: 0.6pt solid #000; padding: 0 1.5px; overflow: hidden; }
        .registro th { font-size: 5.3pt; font-weight: bold; text-align: center; background: #f2f2f2; line-height: 1.1; }
        .registro th.v { height: 1.8cm; padding: 1px 0; font-size: 4.8pt; }
        .registro th.v span { writing-mode: vertical-rl; transform: rotate(180deg); display: inline-block; max-height: 1.75cm; white-space: nowrap; }
        .registro td { height: 0.68cm; font-size: 6.3pt; line-height: 1.12; vertical-align: middle; }
        .registro td.c { text-align: center; }
        .registro td.chico { font-size: 5.4pt; }
        /* Columnas de código: muy angostas, como en el formato oficial. */
        .registro td.cod { text-align: center; font-size: 5.6pt; padding: 0; letter-spacing: -0.2pt; }

        /* Leyenda de códigos, al pie, como en el formato: las listas largas
           van en columnas para que todo quepa en la misma hoja. */
        .leyenda { display: flex; gap: 5px; font-size: 4.6pt; line-height: 1.15; margin-top: 3px; }
        .leyenda > div { flex: 1 1 0; min-width: 0; }
        .leyenda b { display: block; }
        .leyenda .c2 { columns: 2; column-gap: 3px; }
        .leyenda .c4 { columns: 4; column-gap: 3px; }
        /* En carta, la hoja de oficio se reduce completa, como "ajustar a una página". */
        .hoja-sigsa.carta { zoom: 0.846; }
        .pie { display: flex; justify-content: space-between; font-size: 5.5pt; color: #333; margin-top: 2px; }

        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .vista { padding: 0; overflow: visible; }
            /* Ancho útil de la hoja oficio (33.02 cm menos márgenes). */
            .hoja-sigsa { width: 32.12cm; margin: 0; padding: 0; box-shadow: none; }
            .hoja-sigsa.carta { zoom: 0.8418; }
            .hoja-sigsa + .hoja-sigsa { break-before: page; }
        }
    </style>
</head>
<body>

<div class="barra">
    <form method="get" action="<?= APP_URL ?>/index.php">
        <input type="hidden" name="accion" value="sigsa3">
        <label>Día de consulta
            <input type="date" name="fecha" value="<?= htmlspecialchars($filtros['fecha']) ?>" max="<?= date('Y-m-d') ?>">
        </label>
        <label class="check">
            <input type="checkbox" name="mes_completo" value="1" <?= $filtros['mes_completo'] ? 'checked' : '' ?>>
            Todo el mes
        </label>
        <label>Papel
            <select name="papel">
                <?php foreach (FormatoSigsa3::PAPELES as $clave => $p): ?>
                    <option value="<?= $clave ?>" <?= $filtros['papel'] === $clave ? 'selected' : '' ?>><?= htmlspecialchars($p[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="secundario">Ver hoja</button>
    </form>
    <p class="resumen"><?= count($filas) ?> <?= count($filas) === 1 ? 'atención' : 'atenciones' ?> a niños en <?= htmlspecialchars($periodo) ?>,
        en <?= count($hojas) ?> hoja<?= count($hojas) === 1 ? '' : 's' ?>.</p>
    <div class="acciones">
        <button type="button" class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
        <a class="boton secundario" href="<?= APP_URL ?>/index.php?accion=reportes">Volver a Reportes</a>
    </div>
    <p>La hoja se imprime en papel <?= htmlspecialchars(mb_strtolower($papel[0])) ?>, horizontal. Quedan en blanco para
       llenar a mano: discapacidad, tipo de consulta (primera o reconsulta), referencia, el número de
       historia clínica de los niños sin número de carnet y el código CIE-10 del control de crecimiento,
       porque el sistema no los registra. En la vacunación, esa columna lleva la vacuna y la dosis. Las filas sin datos son para los demás
       pacientes del día.</p>
</div>

<div class="vista">
<?php foreach ($hojas as $numero => $hoja): ?>
    <section class="hoja-sigsa <?= $filtros['papel'] ?>">
        <div class="enc">
            <div class="ministerio">Ministerio de Salud Pública<br>y Asistencia Social<br><strong>SIGSA</strong></div>
            <div class="titulo">
                <small>Formulario</small>
                <h1>REGISTRO DIARIO DE CONSULTA EN CENTRO DE SALUD</h1>
            </div>
            <div class="codigo">Código<strong>FOR-SIGSA-3CS</strong>Versión 3.0<br>Vigente a partir de:<br>Noviembre 2017</div>
        </div>
        <div class="datos">
            <span>Área de Salud: <b><?= $celda($puesto['area_salud']) ?></b></span>
            <span>Distrito de Salud: <b><?= $celda($puesto['distrito']) ?></b></span>
            <span>Municipio: <b><?= $celda($puesto['distrito']) ?></b></span>
            <span>Servicio de Salud: <b><?= $celda($puesto['nombre']) ?></b></span>
        </div>
        <div class="datos">
            <span>Responsable de la Información: <b><?= $celda(Auth::usuario()['nombre']) ?></b></span>
            <span>Cargo: <i class="linea"></i></span>
            <span>Firma: <i class="linea"></i></span>
            <span>Mes: <b><?= $celda($filtros['mes']) ?></b> &nbsp; Año: <b><?= $celda($filtros['anio']) ?></b></span>
        </div>

        <table class="registro">
            <colgroup>
                <?php foreach ($anchos as $ancho): ?>
                    <col style="width: <?= number_format($ancho * 100 / $total, 3, '.', '') ?>%">
                <?php endforeach; ?>
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">Día de la Consulta</th>
                    <th rowspan="2">No. Historia clínica</th>
                    <th rowspan="2">Nombres y apellidos del paciente</th>
                    <th rowspan="2">CUI<br>Código Único de Identificación</th>
                    <th rowspan="2" class="v"><span>1/ Sexo</span></th>
                    <th rowspan="2" class="v"><span>2/ Pueblo</span></th>
                    <th rowspan="2" class="v"><span>3/ Com. lingüística</span></th>
                    <th colspan="3">4/ Fecha de nacimiento o edad</th>
                    <th rowspan="2" class="v"><span>5/ Discapacidad</span></th>
                    <th rowspan="2" class="v"><span>6/ Orient. sexual</span></th>
                    <th rowspan="2" class="v"><span>7/ Escolaridad</span></th>
                    <th rowspan="2" class="v"><span>8/ Profesión u oficio</span></th>
                    <th colspan="2">Residencia</th>
                    <th rowspan="2" class="v"><span>9/ Agrícola migrante</span></th>
                    <th rowspan="2" class="v"><span>10/ Consulta</span></th>
                    <th rowspan="2" class="v"><span>11/ Control</span></th>
                    <th rowspan="2" class="v"><span>12/ Sem. gestación</span></th>
                    <th colspan="3">Referencia</th>
                    <th colspan="2">Motivo de consulta y/o clasificación</th>
                    <th colspan="3">Tratamiento y/o medicamento formulado</th>
                    <th colspan="2">Notificación</th>
                    <th rowspan="2">Nombres y apellidos del acompañante</th>
                </tr>
                <tr>
                    <th class="v"><span>Día (s)</span></th>
                    <th class="v"><span>Mes (es)</span></th>
                    <th class="v"><span>Año (s)</span></th>
                    <th>Comunidad y/o dirección exacta</th>
                    <th>Municipio</th>
                    <th class="v"><span>13/ Viene</span></th>
                    <th class="v"><span>14/ Fue</span></th>
                    <th>Referido a</th>
                    <th>Descripción de diagnóstico/control</th>
                    <th>Código CIE-10</th>
                    <th>Descripción</th>
                    <th>Presentación</th>
                    <th>Cantidad Recetada</th>
                    <th class="v"><span>15/ Lugar</span></th>
                    <th>Número</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hoja as $f): ?>
                    <?php if ($f === null): ?>
                        <tr><?= str_repeat('<td></td>', count($anchos)) ?></tr>
                        <?php continue; ?>
                    <?php endif; ?>
                    <tr>
                        <td class="c"><?= $f['dia'] ?></td>
                        <td class="c chico"><?= $celda($f['historia']) ?></td>
                        <td><?= $celda($f['nombre']) ?></td>
                        <td class="c chico"><?= $celda($f['cui']) ?></td>
                        <td class="cod"><?= $celda($f['sexo']) ?></td>
                        <td class="cod"><?= $celda($f['pueblo']) ?></td>
                        <td class="cod"><?= $celda($f['comunidad']) ?></td>
                        <td class="cod"><?= $celda($f['edad_dias']) ?></td>
                        <td class="cod"><?= $celda($f['edad_meses']) ?></td>
                        <td class="cod"><?= $celda($f['edad_anios']) ?></td>
                        <td></td>
                        <td class="cod"><?= $celda($f['orientacion']) ?></td>
                        <td class="cod"><?= $celda($f['escolaridad']) ?></td>
                        <td class="cod"><?= $celda($f['profesion']) ?></td>
                        <td class="chico"><?= $celda($f['direccion']) ?></td>
                        <td class="chico"><?= $celda($f['municipio']) ?></td>
                        <td></td>
                        <td></td>
                        <td class="cod"><?= $celda($f['control']) ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="chico"><?= $lineas($f['diagnostico']) ?></td>
                        <td class="c chico"><?= $lineas($f['cie10']) ?></td>
                        <td class="chico"><?= $lineas($f['tratamiento']) ?></td>
                        <td class="chico"><?= $lineas($f['presentacion']) ?></td>
                        <td class="chico"><?= $lineas($f['cantidad']) ?></td>
                        <td></td>
                        <td></td>
                        <td><?= $celda($f['acompanante']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Leyenda de códigos. El número de "flex-grow" reparte el ancho según
             cuánto texto tiene cada bloque, para que la leyenda quede baja. -->
        <div class="leyenda">
            <div style="flex-grow: 0.7"><b>1/ Sexo:</b>M = Masculino<br>F = Femenino</div>
            <div style="flex-grow: 1.7"><b>2/ Pueblo:</b><div class="c2">1 Mestizo, Ladino<br>2 Maya<br>3 Garífuna<br>4 Xinca<br>5 Otros<br>6 No indica</div></div>
            <div style="flex-grow: 3.4"><b>3/ Comunidad lingüística (si es pueblo 2, Maya):</b><div class="c4"><?php foreach (Catalogo::COMUNIDADES_LINGUISTICAS as $codigo => $nombre): ?><?= $codigo ?> <?= htmlspecialchars($nombre) ?><br><?php endforeach; ?></div></div>
            <div style="flex-grow: 1.4"><b>4/ Fecha de nacimiento o edad:</b>dd/mm/aaaa, o la edad (excluyentes): menor de un mes, en días; menor de un año, en meses; mayor de un año, en años.</div>
            <div style="flex-grow: 1.2"><b>5/ Discapacidad:</b><div class="c2">0 No aplica<br>1 Física<br>2 Mental<br>3 Visual<br>4 Auditiva<br>5 Otro</div></div>
            <div style="flex-grow: 1.6"><b>6/ Orientación sexual:</b><div class="c2">0 No aplica<br>1 Heterosexual<br>2 Bisexual<br>3 Homosexual<br>4 Trans<br>5 Otro</div></div>
            <div style="flex-grow: 1.8"><b>7/ Escolaridad:</b><div class="c2">1 No aplica<br>2 Pre primaria<br>3 Primaria<br>4 Básicos<br>5 Diversificado<br>6 Universidad<br>7 Ninguno<br>8 Otro<br>9 No indica</div></div>
            <div style="flex-grow: 2.4"><b>8/ Profesión u oficio o condición:</b><div class="c2">0 No aplica<br>1 Ama de casa<br>2 Mujer trab. sexual<br>3 Hombre trab. sexual<br>4 Privada de libertad<br>5 Joven en riesgo social<br>6 Uniformados<br>7 Migrantes<br>8 Otros</div></div>
            <div style="flex-grow: 1.1"><b>9/ Agrícola migrante:</b>Si es trabajador agrícola migrante, marque con una "X".</div>
            <div style="flex-grow: 1.1"><b>10/ Consulta:</b>1 Primera consulta<br>2 Reconsulta<br>3 Interconsulta<br>4 Emergencia</div>
            <div style="flex-grow: 2"><b>11/ Control:</b><div class="c2">0 No aplica<br>1 Prenatal<br>2 Puerperio<br>3 Planif. familiar<br>4 Profilaxia sexual<br>5 Papanicolaou<br>6 IVAA</div></div>
            <div style="flex-grow: 1"><b>12/ Semana de gestación:</b>Número de semana (1, 2, 3 ... 42).</div>
            <div style="flex-grow: 1.3"><b>13/ Viene:</b>0 No aplica<br>1 Viene referido<br>2 Viene contra referido</div>
            <div style="flex-grow: 1.2"><b>14/ Fue:</b>0 No aplica<br>1 Fue referido<br>2 Fue contra referido</div>
            <div style="flex-grow: 1.6"><b>15/ Lugar de notificación:</b>0 No aplica<br>1 Ministerio Público<br>2 Procuraduría General de la Nación<br>3 Policía Nacional Civil</div>
        </div>
        <div class="pie">
            <span>Hoja generada por el Carnet Digital del <?= $celda($puesto['nombre']) ?> el <?= date('d-m-Y') ?>,
                con las atenciones a niños registradas en el sistema. Las filas en blanco son para las demás consultas del día.</span>
            <span>Hoja <?= $numero + 1 ?> de <?= count($hojas) ?></span>
        </div>
    </section>
<?php endforeach; ?>
</div>

</body>
</html>
