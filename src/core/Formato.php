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

    /** Formato de fecha usado en Guatemala: día-mes-año, por ejemplo 07-10-2026. */
    const FORMATO_FECHA = 'd-m-Y';

    /**
     * Fecha como texto simple en día-mes-año. Acepta la fecha como viene de la
     * base (2026-10-07) o con hora (2026-10-07 14:30:00). Si no es una fecha
     * válida, devuelve el texto tal cual. Para pantallas, usar fecha().
     */
    public static function fechaTexto(string $fecha): string
    {
        $soloFecha = substr(trim($fecha), 0, 10);
        $d = DateTime::createFromFormat('!Y-m-d', $soloFecha);
        // Se compara de vuelta para no aceptar fechas inexistentes (30 de febrero).
        if (!$d || $d->format('Y-m-d') !== $soloFecha) {
            return $fecha;
        }
        return $d->format(self::FORMATO_FECHA);
    }

    /**
     * Fecha lista para mostrar en pantalla, en día-mes-año y protegida con
     * htmlspecialchars. Si está vacía, muestra el aviso en gris ("Sin dato"
     * o el texto indicado).
     */
    /**
     * Fecha escrita en pantalla como día/mes/año (25/02/2024, también con guion
     * o punto) convertida a año-mes-día para guardarla. Si ya viene como
     * año-mes-día, o no es una fecha real, se devuelve tal cual y la validación
     * (Validacion::fecha) da el mensaje.
     */
    public static function fechaIso(string $texto): string
    {
        $texto = trim($texto);
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $texto, $p)
            && checkdate((int) $p[2], (int) $p[1], (int) $p[3])) {
            return sprintf('%04d-%02d-%02d', $p[3], $p[2], $p[1]);
        }
        return $texto;
    }

    public static function fecha(?string $fecha, string $textoVacio = self::SIN_DATO): string
    {
        if ($fecha === null || trim($fecha) === '') {
            return self::sinDato($textoVacio);
        }
        return htmlspecialchars(self::fechaTexto($fecha));
    }

    /**
     * Edad a hoy a partir de la fecha de nacimiento, en años y meses:
     * "1 año 2 meses", "8 meses" o "menos de 1 mes". Es el mismo formato que
     * usa el formulario de medición.
     */
    public static function edad(string $fechaNacimiento): string
    {
        $d = (new DateTime($fechaNacimiento))->diff(new DateTime('today'));
        $partes = [];
        if ($d->y > 0) $partes[] = $d->y . ($d->y === 1 ? ' año' : ' años');
        if ($d->m > 0) $partes[] = $d->m . ($d->m === 1 ? ' mes' : ' meses');
        return $partes ? implode(' ', $partes) : 'menos de 1 mes';
    }

    /**
     * Edad a partir de meses cumplidos: "8 meses", "2 años", "1 año 3 meses".
     * Sirve para la edad que tenía el niño en un control pasado.
     */
    public static function edadEnMeses(int $meses): string
    {
        $anios = intdiv($meses, 12);
        $resto = $meses % 12;
        $partes = [];
        if ($anios > 0) $partes[] = $anios . ($anios === 1 ? ' año' : ' años');
        if ($resto > 0) $partes[] = $resto . ($resto === 1 ? ' mes' : ' meses');
        return $partes ? implode(' ', $partes) : 'menos de 1 mes';
    }

    /**
     * Peso en libras y onzas, como lo entienden muchas familias: "18 lb 12 oz".
     * Usa la misma regla que la tabla de conversión del cuaderno SIGSA 5a
     * (1 kg = 2.2 lb, onzas redondeadas), para que el sistema y el cuaderno
     * den siempre el mismo número. Por ejemplo, 2.00 kg da 4 lb 6 oz.
     */
    public static function libras(float $kg): string
    {
        $onzasTotales = (int) round($kg * 2.2 * 16);
        return intdiv($onzasTotales, 16) . ' lb ' . ($onzasTotales % 16) . ' oz';
    }

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
