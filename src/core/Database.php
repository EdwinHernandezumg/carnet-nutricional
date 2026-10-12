<?php

class Database
{

    private static ?PDO $conexion = null;

    public static function obtener(): PDO
    {

        if (self::$conexion === null) {

            $dsn = 'mysql:host=' . DB_HOST
                . ';dbname=' . DB_NAME
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
