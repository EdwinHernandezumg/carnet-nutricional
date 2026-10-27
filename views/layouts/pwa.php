<?php
/**
 * Lo que hace instalable el sistema en el teléfono (PWA): manifiesto, íconos
 * y registro del service worker. Va dentro del <head> de las páginas que se
 * usan en la aplicación (no en las de impresión). En el ambiente de pruebas,
 * el ícono es naranja.
 */
$sufijoIcono = APP_ENTORNO === 'demostracion' ? '-pruebas' : '';
?>
    <link rel="manifest" href="<?= APP_URL ?>/manifest.php">
    <meta name="theme-color" content="#14304a">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Recurso::url('assets/pwa/favicon-32' . $sufijoIcono . '.png') ?>">
    <link rel="apple-touch-icon" href="<?= Recurso::url('assets/pwa/apple-touch-icon' . $sufijoIcono . '.png') ?>">
    <meta name="apple-mobile-web-app-title" content="<?= $sufijoIcono ? 'Carnet pruebas' : 'Carnet Digital' ?>">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= APP_URL ?>/sw.php').catch(function () {});
        }
    </script>
