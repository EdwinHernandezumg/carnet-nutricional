<?php
/**
 * Manejo de la sesión y del cierre por inactividad.
 *
 * Regla de seguridad: si el usuario no realiza ninguna acción durante
 * el tiempo definido en SESION_INACTIVIDAD_MINUTOS, la sesión se cierra
 * automáticamente y debe volver a autenticarse. Protege los datos del
 * menor cuando una computadora queda desatendida (RNF-04, RS).
 */

class Sesion
{
    // Minutos de inactividad permitidos antes de cerrar la sesión.
    const INACTIVIDAD_MINUTOS = 20;

    /**
     * Inicia la sesión de PHP si aún no está iniciada.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Protecciones de la cookie de sesión, sin depender del php.ini del
            // hosting: httponly impide que un script la lea; samesite impide que
            // otro sitio envíe formularios con la sesión de la enfermera; secure
            // hace que solo viaje cifrada cuando el sitio usa HTTPS.
            $usaHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            ini_set('session.use_strict_mode', '1');
            session_set_cookie_params([
                'path'     => parse_url(APP_URL, PHP_URL_PATH) ?: '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $usaHttps,
            ]);
            session_start();
        }
    }

    /**
     * Verifica si la sesión expiró por inactividad.
     * Devuelve true si expiró (y la cierra), false si sigue vigente.
     * Si sigue vigente, actualiza la marca de última actividad.
     */
    public static function expiroPorInactividad(): bool
    {
        if (!isset($_SESSION['ultima_actividad'])) {
            $_SESSION['ultima_actividad'] = time();
            return false;
        }

        $limite = self::INACTIVIDAD_MINUTOS * 60; // en segundos
        $inactivo = time() - $_SESSION['ultima_actividad'];

        if ($inactivo > $limite) {
            self::cerrar();
            return true;
        }

        // Sigue activo: renovamos la marca de tiempo.
        $_SESSION['ultima_actividad'] = time();
        return false;
    }

    /**
     * Cierra la sesión por completo.
     */
    public static function cerrar(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Segundos restantes antes de la expiración (útil para avisos en pantalla).
     */
    public static function segundosRestantes(): int
    {
        if (!isset($_SESSION['ultima_actividad'])) {
            return self::INACTIVIDAD_MINUTOS * 60;
        }
        $limite = self::INACTIVIDAD_MINUTOS * 60;
        $restante = $limite - (time() - $_SESSION['ultima_actividad']);
        return max(0, $restante);
    }
}
