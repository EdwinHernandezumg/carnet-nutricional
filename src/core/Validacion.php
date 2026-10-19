<?php
/**
 * Reglas de validación de datos de entrada, reutilizables en todo el sistema.
 *
 * El navegador también valida (y no deja escribir números en un nombre, por
 * ejemplo), pero eso es solo comodidad: alguien puede saltarse el navegador y
 * enviar cualquier cosa. La validación que protege los datos es esta, en el
 * servidor. Además, como la base usa el modo estricto de MySQL, un texto más
 * largo que su columna daría error: aquí se detecta antes y se avisa con un
 * mensaje claro.
 *
 * Cada función devuelve el mensaje de error, o null si el dato es válido.
 *
 * Uso en el controlador:
 *   $errores[] = Validacion::nombrePersona($d['nombres'], 'Los nombres', 100, true);
 *   $errores = array_values(array_filter($errores));   // quita los null
 */

class Validacion
{
    /**
     * Nombre de persona: solo letras (con tildes y eñe), espacios, apóstrofo
     * (Tz'unun), guion (Ana-Lucía) y punto. Sin números ni otros símbolos.
     */
    public static function nombrePersona(string $valor, string $campo, int $maximo, bool $obligatorio): ?string
    {
        if ($valor === '') {
            return $obligatorio ? "$campo: es un dato obligatorio." : null;
        }
        if (!preg_match("/^\p{L}[\p{L}\s'.\-]*$/u", $valor)) {
            return "$campo: solo se aceptan letras, espacios, apóstrofo (') o guion (-), sin números ni otros símbolos.";
        }
        return self::largo($valor, $campo, $maximo);
    }

    /**
     * Palabras sin números (pueblo o comunidad lingüística): como un nombre,
     * pero admite también comas, por ejemplo "Maya k'iche', mestizo".
     */
    public static function palabras(string $valor, string $campo, int $maximo): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (!preg_match("/^\p{L}[\p{L}\s'.,\-]*$/u", $valor)) {
            return "$campo: solo se aceptan letras, espacios y los signos ' , . -";
        }
        return self::largo($valor, $campo, $maximo);
    }

    /** Texto libre (dirección, lote, observaciones): solo se limita el largo. */
    public static function texto(string $valor, string $campo, int $maximo, bool $obligatorio = false): ?string
    {
        if ($valor === '') {
            return $obligatorio ? "$campo: es un dato obligatorio." : null;
        }
        return self::largo($valor, $campo, $maximo);
    }

    /**
     * Código o número de documento (número de carnet): letras sin tilde,
     * números, guion y barra, sin espacios. Por ejemplo "125", "T8-045" o
     * "045/2026".
     */
    public static function codigo(string $valor, string $campo, int $maximo): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9\/\-]*$/', $valor)) {
            return "$campo: solo se aceptan letras sin tilde, números, guion (-) y barra (/), sin espacios.";
        }
        return self::largo($valor, $campo, $maximo);
    }

    /** Correo electrónico (opcional): con @ y dominio, sin espacios. */
    public static function correo(string $valor, string $campo, int $maximo): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            return "$campo: revise que esté completo, por ejemplo nombre@gmail.com.";
        }
        return self::largo($valor, $campo, $maximo);
    }

    /** Solo dígitos, exactamente $cantidad (vacío permitido si no es obligatorio). */
    public static function digitos(string $valor, string $campo, int $cantidad, bool $obligatorio = false): ?string
    {
        if ($valor === '') {
            return $obligatorio ? "$campo: es un dato obligatorio." : null;
        }
        if (!preg_match('/^\d{' . $cantidad . '}$/', $valor)) {
            return "$campo: deben ser exactamente $cantidad dígitos, sin letras ni otros signos.";
        }
        return null;
    }

    /**
     * CUI (Código Único de Identificación de RENAP): 13 dígitos. Los cuatro
     * últimos son el departamento (01 a 22) y el municipio (desde 01).
     * Es opcional: hay niños sin inscripción en RENAP.
     */
    public static function cui(string $valor): ?string
    {
        $error = self::digitos($valor, 'CUI', 13);
        if ($error !== null || $valor === '') {
            return $error;
        }
        $departamento = (int) substr($valor, 9, 2);
        $municipio    = (int) substr($valor, 11, 2);
        if ($departamento < 1 || $departamento > 22 || $municipio < 1) {
            return 'CUI: no es válido; los últimos cuatro dígitos deben indicar un departamento (01 a 22) y un municipio.';
        }
        return null;
    }

    /**
     * Fecha real en formato AAAA-MM-DD, opcionalmente dentro de un rango.
     * Rechaza fechas inexistentes como el 30 de febrero.
     */
    public static function fecha(string $valor, string $campo, ?string $minima = null, ?string $maxima = null,
                                 string $textoMinima = '', string $textoMaxima = ''): ?string
    {
        if ($valor === '') {
            return "$campo: es un dato obligatorio.";
        }
        $d = DateTime::createFromFormat('!Y-m-d', $valor);
        if (!$d || $d->format('Y-m-d') !== $valor) {
            return "$campo: no es una fecha válida.";
        }
        if ($minima !== null && $valor < $minima) {
            return "$campo: no puede ser " . ($textoMinima ?: 'anterior al ' . Formato::fechaTexto($minima)) . '.';
        }
        if ($maxima !== null && $valor > $maxima) {
            return "$campo: no puede ser " . ($textoMaxima ?: 'posterior al ' . Formato::fechaTexto($maxima)) . '.';
        }
        return null;
    }

    /** Valor que debe ser uno de los de una lista (por ejemplo, de un catálogo). */
    public static function enLista($valor, array $permitidos, string $mensaje): ?string
    {
        return in_array((string) $valor, array_map('strval', $permitidos), true) ? null : $mensaje;
    }

    /** Largo máximo en caracteres (no en bytes: la ñ y las tildes cuentan uno). */
    private static function largo(string $valor, string $campo, int $maximo): ?string
    {
        if (mb_strlen($valor) > $maximo) {
            return "$campo: admite como máximo $maximo caracteres.";
        }
        return null;
    }
}
