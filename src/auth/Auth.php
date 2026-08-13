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
                       u.activo, u.debe_cambiar_pass, u.id_rol, u.id_sector, r.nombre AS rol
                FROM usuario u
                JOIN rol r ON r.id_rol = u.id_rol
                WHERE u.usuario = :usuario
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $u = $stmt->fetch();

        if (!$u) {
            return false;
        }
        if (!$u['activo']) {
            return false;
        }
        if (!password_verify($contrasena, $u['contrasena_hash'])) {
            return false;
        }

        // Regeneramos el ID de sesión al autenticar (evita fijación de sesión).
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'        => $u['id_usuario'],
            'nombre'    => $u['nombre_completo'],
            'usuario'   => $u['usuario'],
            'rol'       => $u['rol'],
            'id_rol'    => $u['id_rol'],
            'id_sector' => $u['id_sector'],
            'debe_cambiar_pass' => (bool)$u['debe_cambiar_pass'],
        ];
        $_SESSION['ultima_actividad'] = time();

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

    /**
     * Exige que haya un usuario autenticado y que la sesión no haya expirado.
     * Si algo falla, redirige al login. Se llama al inicio de cada acción protegida.
     */
    public static function exigirAutenticacion(): void
    {
        if (Sesion::expiroPorInactividad()) {
            header('Location: ' . APP_URL . '/index.php?accion=inicio&expiro=1');
            exit;
        }
        if (!self::autenticado()) {
            header('Location: ' . APP_URL . '/index.php?accion=inicio');
            exit;
        }
    }

    /**
     * Exige que el usuario tenga uno de los roles indicados.
     * Si no, muestra un mensaje de acceso denegado.
     */
    public static function exigirRol(array $roles): void
    {
        self::exigirAutenticacion();
        if (!in_array($_SESSION['usuario']['rol'], $roles, true)) {
            http_response_code(403);
            echo '<p style="font-family:sans-serif;padding:2rem;">Acceso denegado: no tiene permisos para esta acción.</p>';
            exit;
        }
    }

    /** Cierra la sesión. */
    public static function logout(): void
    {
        Sesion::cerrar();
    }

    /** Marca en la sesión que el usuario ya cambió su contraseña. */
    public static function marcarPassCambiada(): void
    {
        if (isset($_SESSION['usuario'])) {
            $_SESSION['usuario']['debe_cambiar_pass'] = false;
        }
    }

    /** Indica si el usuario actual debe cambiar su contraseña. */
    public static function debeCambiarPass(): bool
    {
        return !empty($_SESSION['usuario']['debe_cambiar_pass']);
    }

}
