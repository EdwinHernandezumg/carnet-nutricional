<?php
/**
 * Manejo de autenticación y sesión de usuario.
 */

class Auth
{
    /**
     * Verifica las credenciales. Si son correctas, inicia la sesión.
     * Devuelve true si autentica, false si no.
     */
    public static function login(string $usuario, string $contrasena): bool
    {
        $db = Database::obtener();

        $sql = "SELECT u.id_usuario, u.nombre_completo, u.usuario, u.contrasena_hash,
                       u.activo, u.id_rol, u.id_sector, r.nombre AS rol
                FROM usuario u
                JOIN rol r ON r.id_rol = u.id_rol
                WHERE u.usuario = :usuario
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $u = $stmt->fetch();

        // Usuario no existe
        if (!$u) {
            return false;
        }

        // Usuario dado de baja
        if (!$u['activo']) {
            return false;
        }

        // Contraseña incorrecta
        if (!password_verify($contrasena, $u['contrasena_hash'])) {
            return false;
        }

        // Credenciales correctas: guardamos los datos en la sesión
        $_SESSION['usuario'] = [
            'id'        => $u['id_usuario'],
            'nombre'    => $u['nombre_completo'],
            'usuario'   => $u['usuario'],
            'rol'       => $u['rol'],
            'id_rol'    => $u['id_rol'],
            'id_sector' => $u['id_sector'],
        ];

        return true;
    }

    /** Indica si hay un usuario autenticado. */
    public static function autenticado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    /** Devuelve los datos del usuario actual, o null. */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    /** Indica si el usuario actual tiene un rol dado. */
    public static function tieneRol(string $rol): bool
    {
        return self::autenticado() && $_SESSION['usuario']['rol'] === $rol;
    }

    /** Cierra la sesión. */
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}