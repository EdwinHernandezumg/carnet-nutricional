<?php
/**
 * Prueba el envío de correo con los datos SMTP de config/config.php.
 *
 *   php scripts/probar_correo.php destino@gmail.com
 *
 * Envía un mensaje de prueba y, si falla, muestra la causa (por ejemplo, la
 * contraseña del buzón mal escrita). Sirve después de instalar el sistema o
 * de cambiar el buzón, antes de que las enfermeras lo necesiten para
 * recuperar su contraseña o recibir el código de ingreso.
 */

require __DIR__ . '/consola.php';
soloConsola();

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Correo.php';

$destino = $argv[1] ?? '';
if ($destino === '') {
    echo "Uso: php scripts/probar_correo.php destino@gmail.com\n";
    exit(1);
}
if (!Correo::activo()) {
    echo "El correo no está configurado: llene SMTP_HOST, SMTP_PUERTO, SMTP_USUARIO y SMTP_CONTRASENA\n"
       . "en config/config.php (ver config/config.ejemplo.php).\n";
    exit(1);
}

echo "Enviando desde " . SMTP_USUARIO . " por " . SMTP_HOST . ':' . (defined('SMTP_PUERTO') ? SMTP_PUERTO : 465) . "...\n";
$texto = "Este es un correo de prueba del " . APP_NOMBRE . ".\n\n"
       . "Si lo recibió, el sistema ya puede enviar los enlaces para recuperar la\n"
       . "contraseña y los códigos de ingreso.\n\n"
       . 'Enviado el ' . date('d-m-Y') . ' a las ' . date('H:i') . ".\n";

if (Correo::enviar($destino, 'Prueba de correo del Carnet Digital', $texto)) {
    echo "Listo: el servidor aceptó el mensaje. Revise la bandeja de $destino (también spam).\n";
    exit(0);
}
echo "No se pudo enviar: " . Correo::$ultimoError . "\n";
exit(1);
