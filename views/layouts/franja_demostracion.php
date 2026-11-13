<?php
/**
 * Marca del ambiente de pruebas (pruebas.carnetchivarreto.com), para que
 * nadie lo confunda con el sistema del puesto. Solo aparece con APP_ENTORNO
 * en 'demostracion'; en producción y en la computadora del desarrollador, no.
 *
 * Hasta el 13 de noviembre de 2026 era una franja amarilla con texto. Desde
 * entonces el ambiente se distingue por el color naranja de la marca (el
 * emblema y la palabra "Digital"), el mismo del ícono de su aplicación
 * instalable, y por una etiqueta "Pruebas" junto al nombre del sistema.
 *
 * Las hojas de impresión no muestran el nombre del sistema como marca: antes
 * de incluir este archivo definen $marcaImpresion = true, y llevan arriba una
 * línea pequeña, para que una hoja impresa en pruebas no pase por una del
 * puesto.
 *
 * Los estilos van aquí mismo porque las páginas de impresión no cargan
 * estilos.css.
 */
if (APP_ENTORNO === 'demostracion'): ?>
    <style>
        /* Marca en naranja, como el ícono de la aplicación de pruebas. */
        .marca-2 { color: #d9730d !important; }
        .ingreso-emblema {
            background: #d9730d !important;
            box-shadow: 0 10px 24px rgba(217, 115, 13, 0.30) !important;
        }
        /* Etiqueta "Pruebas" junto al nombre del sistema y en los títulos de
           las pantallas de ingreso, código y contraseña. */
        .barra-titulo strong::after,
        .ingreso-nombre::after,
        .tarjeta-login h1::after {
            content: "Pruebas";
            display: inline-block;
            vertical-align: middle;
            margin-left: 0.45rem;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            background: #d9730d;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.25;
        }
        /* En la pantalla de ingreso el nombre es grande: la etiqueta, también. */
        .ingreso-nombre::after {
            font-size: 0.95rem;
            padding: 0.2rem 0.8rem;
            letter-spacing: 0.5px;
        }
        .marca-impresion-pruebas {
            text-align: right;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            font-weight: 700;
            color: #d9730d;
            margin-bottom: 4px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    </style>
    <?php if (!empty($marcaImpresion)): ?>
        <div class="marca-impresion-pruebas">Ambiente de pruebas</div>
    <?php endif; ?>
<?php endif; ?>
