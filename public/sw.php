<?php
/**
 * Service worker de la aplicación web (PWA): Chrome lo pide para ofrecer
 * "Instalar". Hace lo mínimo, a propósito:
 *   - NO guarda en caché las páginas del sistema: llevan datos de salud de
 *     niños y el teléfono puede ser compartido;
 *   - no interviene en el uso normal (formularios, descargas de Excel y CSV,
 *     impresiones);
 *   - solo cuando el teléfono está sin conexión y se abre una página, muestra
 *     el aviso "Sin conexión" en lugar del error del navegador.
 *
 * Es PHP para que el CDN de Hostinger no lo guarde 7 días como al resto del
 * JavaScript: así un cambio aquí llega a los teléfonos de inmediato.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
?>
// Al cambiar la página sin conexión, subir la versión para que se descargue de nuevo.
const VERSION = 'carnet-1';
const SIN_CONEXION = 'sin-conexion.html';

self.addEventListener('install', (evento) => {
  evento.waitUntil(caches.open(VERSION).then((cache) => cache.add(SIN_CONEXION)));
  self.skipWaiting();
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((nombres) => Promise.all(nombres.filter((n) => n !== VERSION).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

// Solo actúa sin conexión y al abrir una página; lo demás sigue su camino normal.
self.addEventListener('fetch', (evento) => {
  const peticion = evento.request;
  if (peticion.mode === 'navigate' && peticion.method === 'GET' && !self.navigator.onLine) {
    evento.respondWith(caches.match(SIN_CONEXION));
  }
});
