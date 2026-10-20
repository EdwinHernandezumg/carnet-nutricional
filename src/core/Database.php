<?php

class Database
{

    private static ?PDO $conexion = null;

    /** Base distinta a la de config.php (solo para scripts de consola con --base=). */
    private static ?string $base = null;

    /**
     * Hace que la conexión use otra base. Lo usan los scripts de consola con la
     * opción --base=, para probar sin tocar la base de config.php. Se llama
     * antes de la primera consulta.
     */
    public static function usarBase(string $base): void
    {
        self::$base = $base;
        self::$conexion = null;
    }

    public static function obtener(): PDO
    {

        if (self::$conexion === null) {

            $dsn = 'mysql:host=' . DB_HOST
                . ';dbname=' . (self::$base ?? DB_NAME)
                . ';charset=' . DB_CHARSET;

            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones);

                // MySQL usa la misma hora que PHP (la de Guatemala, definida en
                // config.php). En la nube el servidor suele estar en hora
                // universal: sin esto, CURDATE() y NOW() irían 6 horas adelante
                // y, después de las 18:00, creerían que ya es el día siguiente
                // (citas de hoy, citas vencidas, egresos y hora de la bitácora).
                self::$conexion->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");

                // Modo estricto en todos los ambientes. Los servidores en la nube
                // suelen usarlo; XAMPP no. Sin esto, un dato demasiado largo se
                // corta en silencio en la computadora y en la nube da error: así
                // cualquier problema aparece primero aquí, al desarrollar.
                self::$conexion->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,"
                    . "NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $exception) {
                if (APP_ENTORNO === 'desarrollo') {
                    die('Error de conexión a la base de datos: ' . $exception->getMessage());
                }
                error_log('Error de conexión a la base de datos: ' . $exception->getMessage());
                die('No fue posible conectar a la base de datos. Por favor, inténtelo más tarde.');
            }
        }
        return self::$conexion;
    }
}
