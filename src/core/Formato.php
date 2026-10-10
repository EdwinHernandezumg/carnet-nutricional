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

    /** Palabras que van en minúscula dentro de un nombre, salvo al inicio. */
    const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y'];

    /**
     * Escribe un nombre propio con la inicial de cada palabra en mayúscula,
     * sin importar cómo se haya tecleado: "maría de los ángeles" o
     * "MARÍA DE LOS ÁNGELES" quedan "María de los Ángeles". Las partículas
     * (de, del, la, las, los, y) van en minúscula salvo al inicio, como en
     * "De León". Respeta tildes, eñe, guiones y apóstrofos ("Tz'unun"), y
     * quita los espacios repetidos.
     */
    public static function nombrePropio(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        if ($texto === '') {
            return '';
        }

        $palabras = explode(' ', mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8'));
        foreach ($palabras as $i => $palabra) {
            $minuscula = mb_strtolower($palabra, 'UTF-8');
            if ($i > 0 && in_array($minuscula, self::PARTICULAS, true)) {
                $palabras[$i] = $minuscula;
            }
        }
        return implode(' ', $palabras);
    }

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
