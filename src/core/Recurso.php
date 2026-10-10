<?php
/**
 * Direcciones de los archivos estáticos (CSS y JavaScript) de public/.
 *
 * Agrega al final de la dirección un número de versión, por ejemplo
 * css/estilos.css?v=1791658064. Ese número es la fecha de la última
 * modificación del archivo, así que cambia solo cada vez que se edita.
 * Para el navegador es una dirección nueva y descarga el archivo
 * actualizado, sin que el personal tenga que recargar con Ctrl+Shift+R.
 *
 * Uso en una vista:
 *   <link rel="stylesheet" href="<?= Recurso::url('css/estilos.css') ?>">
 */

class Recurso
{
    public static function url(string $ruta): string
    {
        $archivo = __DIR__ . '/../../public/' . $ruta;
        $version = is_file($archivo) ? filemtime($archivo) : 0;
        return APP_URL . '/' . $ruta . '?v=' . $version;
    }
}
