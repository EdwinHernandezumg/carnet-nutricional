<?php
/**
 * Configuración del sistema. Copiar este archivo como config/config.php y
 * llenarlo con los datos de cada ambiente. config.php no se guarda en Git
 * (está en .gitignore) porque lleva la contraseña de la base de datos.
 *
 * Los valores de abajo son los de la computadora con XAMPP. En el servidor:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS  los que da el panel del hosting
 *   APP_URL      https://su-dominio.com  (sin barra al final y sin /public)
 *   APP_ENTORNO  'produccion'            (no muestra errores técnicos), o
 *                'demostracion' en el ambiente con datos ficticios (además
 *                muestra arriba la franja "Ambiente de demostración")
 *   SMTP_...     los del buzón del dominio (ver abajo)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'carnet_nutricional');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NOMBRE', 'Carnet Digital de Monitoreo Nutricional Infantil');
define('APP_URL', 'http://localhost/carnet-nutricional/public');
define('APP_ENTORNO', 'desarrollo'); // desarrollo, demostracion, produccion

// Correo saliente: enlace para recuperar la contraseña y código de ingreso
// desde un dispositivo nuevo. Con SMTP_HOST vacío el sistema funciona sin
// correo (no ofrece recuperar la contraseña ni pide código). Para probarlo:
//   php scripts/probar_correo.php destino@gmail.com
// En Hostinger: smtp.hostinger.com, puerto 465, la dirección completa del
// buzón y su contraseña (la que se puso al crearlo en el hPanel).
define('SMTP_HOST', '');
define('SMTP_PUERTO', 465);          // 465 (SSL) o 587 (STARTTLS)
define('SMTP_USUARIO', '');          // la dirección completa del buzón
define('SMTP_CONTRASENA', '');
define('SMTP_REMITENTE_NOMBRE', 'Carnet Digital Chivarreto');

date_default_timezone_set('America/Guatemala');
