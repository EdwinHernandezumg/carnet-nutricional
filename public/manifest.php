<?php
/**
 * Manifiesto de la aplicación web (PWA). Con él, Chrome permite "Instalar" el
 * sistema en el teléfono, con su ícono y sin la barra del navegador, como una
 * aplicación más. Lo pidió la jefa del puesto en la entrega del 22 de octubre
 * de 2026.
 *
 * Es PHP y no un archivo .json por dos razones: el ambiente de pruebas lleva
 * otro nombre y un ícono de otro color, para que nadie confunda las dos
 * aplicaciones; y el CDN de Hostinger no guarda en caché las páginas PHP, así
 * que un cambio aquí llega de inmediato.
 */

require __DIR__ . '/../config/config.php';

$pruebas = APP_ENTORNO === 'demostracion';
$sufijo  = $pruebas ? '-pruebas' : '';
$icono   = fn(string $nombre, string $tamano, string $proposito) => [
    'src'     => 'assets/pwa/' . $nombre . $sufijo . '.png',
    'sizes'   => $tamano,
    'type'    => 'image/png',
    'purpose' => $proposito,
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');

echo json_encode([
    'id'               => './',
    'name'             => $pruebas ? 'Carnet Digital (pruebas)' : 'Carnet Digital',
    'short_name'       => $pruebas ? 'Carnet pruebas' : 'Carnet Digital',
    'description'      => 'Monitoreo nutricional infantil del Puesto de Salud de Chivarreto',
    'lang'             => 'es-GT',
    'start_url'        => 'index.php',
    'scope'            => './',
    'display'          => 'standalone',
    'background_color' => '#f3f6fa',
    'theme_color'      => '#14304a',
    'icons'            => [
        $icono('icono-192', '192x192', 'any'),
        $icono('icono-512', '512x512', 'any'),
        $icono('icono-maskable-512', '512x512', 'maskable'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
