<?php
/**
 * Configuración del sistema. Copiar este archivo como config/config.php y
 * llenarlo con los datos de cada ambiente. config.php no se guarda en Git
 * (está en .gitignore) porque lleva la contraseña de la base de datos.
 *
 * Los valores de abajo son los de la computadora con XAMPP. En el servidor:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS  los que da el panel del hosting
 *   APP_URL      https://su-dominio.com  (sin barra al final y sin /public)
 *   APP_ENTORNO  'produccion'            (no muestra errores técnicos)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'carnet_nutricional');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NOMBRE', 'Carnet Digital de Monitoreo Nutricional Infantil');
define('APP_URL', 'http://localhost/carnet-nutricional/public');
define('APP_ENTORNO', 'desarrollo'); // desarrollo, produccion

date_default_timezone_set('America/Guatemala');
