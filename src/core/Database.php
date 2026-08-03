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
