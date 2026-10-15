<?php
/**
 * Validación de contraseñas del lado del servidor.
 *
 * La barra de fuerza en el navegador es una AYUDA visual, pero puede saltarse.
 * Esta clase es la barrera real: valida el mínimo obligatorio antes de guardar.
 *
 * Regla mínima obligatoria (coherente con fuerza-contrasena.js):
 *   - al menos 8 caracteres
 *   - al menos una letra y un número
 * Mayúsculas y símbolos suman fuerza pero NO son obligatorios.
 */

class Contrasena
{
    const LARGO_MINIMO = 8;

    /**
     * Valida el mínimo obligatorio. Devuelve un arreglo de errores (vacío si es válida).
     */
    public static function validar(string $pass): array
    {
        $errores = [];

        if (strlen($pass) < self::LARGO_MINIMO) {
            $errores[] = 'La contraseña debe tener al menos ' . self::LARGO_MINIMO . ' caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $pass)) {
            $errores[] = 'La contraseña debe incluir al menos una letra.';
        }
        if (!preg_match('/\d/', $pass)) {
            $errores[] = 'La contraseña debe incluir al menos un número.';
        }

        return $errores;
    }

    /**
     * Indica si la contraseña cumple el mínimo obligatorio.
     */
    public static function esValida(string $pass): bool
    {
        return empty(self::validar($pass));
    }

    /**
     * Genera una contraseña temporal legible pero razonablemente segura.
     * Formato: 2 letras mayúsculas + 4 dígitos + 2 minúsculas (ej. "KM4821tz").
     * La usan la recuperación por el administrador y los scripts de consola.
     */
    public static function generarTemporal(): string
    {
        $may = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // sin I ni O (se confunden)
        $min = 'abcdefghijkmnpqrstuvwxyz';   // sin l
        $num = '23456789';                   // sin 0 ni 1
        $r = '';
        for ($i = 0; $i < 2; $i++) $r .= $may[random_int(0, strlen($may) - 1)];
        for ($i = 0; $i < 4; $i++) $r .= $num[random_int(0, strlen($num) - 1)];
        for ($i = 0; $i < 2; $i++) $r .= $min[random_int(0, strlen($min) - 1)];
        return $r;
    }
}
