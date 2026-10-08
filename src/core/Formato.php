<?php
/**
 * Formato de datos para mostrar en pantalla.
 *
 * Centraliza cómo se indica un dato que no fue registrado, para que todas
 * las tablas del sistema lo muestren igual. Si el personal prefiere otra
 * palabra, se cambia solo aquí.
 *
 * Uso en una vista:
 *   <?= Formato::dato($v['lote']) ?>                          (Sin dato)
 *   <?= Formato::dato($c['fecha_asistencia'], 'Pendiente') ?>  (texto propio)
 *   <?= Formato::sinDato('Sin sector') ?>                     (solo el aviso)
 */

class Formato
{
    /** Texto por omisión cuando no hay dato registrado. */
    const SIN_DATO = 'Sin dato';

    /**
     * Devuelve el valor protegido con htmlspecialchars. Si el valor es nulo
     * o está vacío, devuelve el aviso en gris.
     */
    public static function dato(?string $valor, string $textoVacio = self::SIN_DATO): string
    {
        if ($valor === null || trim($valor) === '') {
            return self::sinDato($textoVacio);
        }
        return htmlspecialchars($valor);
    }

    /**
     * Devuelve solo el aviso en gris, para los casos en que la vista ya
     * sabe que no hay dato.
     */
    public static function sinDato(string $texto = self::SIN_DATO): string
    {
        return '<span class="sin-dato">' . htmlspecialchars($texto) . '</span>';
    }
}
