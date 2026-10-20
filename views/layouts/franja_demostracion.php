<?php
/**
 * Franja del ambiente de demostración (pruebas.carnetchivarreto.com): se ve
 * arriba de toda pantalla, también al imprimir, para que nadie confunda los
 * datos ficticios con los del puesto. Solo aparece con APP_ENTORNO en
 * 'demostracion'; en producción y en la computadora del desarrollador, no.
 * Los estilos van aquí mismo porque las páginas de impresión no cargan
 * estilos.css.
 */
if (APP_ENTORNO === 'demostracion'): ?>
    <div role="note" style="background: #f2b705; color: #3a2c00; text-align: center; font-weight: 700;
        font-size: 0.9rem; padding: 0.4rem 0.75rem; letter-spacing: 0.2px; line-height: 1.35;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;">
        Ambiente de demostración: todos los datos son ficticios
    </div>
<?php endif; ?>
