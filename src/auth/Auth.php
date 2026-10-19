<?php
/**
 * Manejo de autenticación y sesión de usuario.
 */

class Auth
{
    /**
     * Datos de un usuario activo para el ingreso, por su nombre de usuario o
     * por su id. Devuelve null si no existe o está dado de baja.
     */
    public static function buscarParaIngreso(string $usuario = '', int $idUsuario = 0): ?array
    {
        $db = Database::obtener();

        $sql = "SELECT u.id_usuario, u.nombre_completo, u.usuario, u.correo, u.contrasena_hash,
                       u.activo, u.debe_cambiar_pass, u.id_rol, u.id_sector, r.nombre AS rol
                FROM usuario u
                JOIN rol r ON r.id_rol = u.id_rol
                WHERE (u.usuario = :usuario OR u.id_usuario = :id) AND u.activo = 1
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario' => $usuario, ':id' => $idUsuario]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica el usuario y la contraseña. Devuelve los datos del usuario si
     * son correctos, o null. No inicia la sesión: antes puede faltar el código
     * enviado al correo (ver necesitaCodigo).
     */
    public static function verificarCredenciales(string $usuario, string $contrasena): ?array
    {
        if ($usuario === '') {
            return null;
        }
        $u = self::buscarParaIngreso($usuario);
        if (!$u || !password_verify($contrasena, $u['contrasena_hash'])) {
            return null;
        }
        return $u;
    }

    /**
     * Indica si hay que pedir el código enviado al correo antes de entrar:
     * siempre al administrador y, a los demás, en un dispositivo nuevo. Si el
     * sistema no tiene correo configurado o la persona no tiene correo
     * registrado, no se pide (el ingreso queda en la bitácora igual).
     */
    public static function necesitaCodigo(array $u): bool
    {
        if (!Correo::activo() || empty($u['correo'])) {
            return false;
        }
        return $u['rol'] === 'administrador'
            || !Verificacion::esDispositivoDeConfianza((int)$u['id_usuario']);
    }

    /** Inicia la sesión del usuario ya verificado. */
    public static function iniciarSesion(array $u): void
    {
        // Regeneramos el ID de sesión al autenticar (evita fijación de sesión).
        session_regenerate_id(true);
        unset($_SESSION['ingreso_pendiente']);

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

    /**
     * Dirección de la página principal según el rol. Enfermería abre en la
     * lista de niños, porque lo primero que hace es buscar al niño que llegó
     * (lo pidió la jefa del puesto en la visita del 16 de octubre de 2026).
     * Consulta abre en el panel de seguimiento, con los niños en riesgo. El
     * administrador no captura datos clínicos y abre en la gestión de usuarios.
     */
    public static function paginaInicio(): string
    {
        $accion = match (true) {
            self::tieneRol('administrador') => 'usuarios',
            self::tieneRol('enfermeria')    => 'ninos',
            default                         => 'panel_alertas',
        };
        return APP_URL . '/index.php?accion=' . $accion;
    }

    /** Indica si el usuario actual tiene un rol dado. */
    public static function tieneRol(string $rol): bool
    {
        return self::autenticado() && $_SESSION['usuario']['rol'] === $rol;
    }

    /**
     * Exige que haya un usuario autenticado y que la sesión no haya expirado.
     * Si algo falla, redirige al login. Se llama al inicio de cada acción protegida.
     *
     * Con una contraseña temporal, la única pantalla permitida es la de
     * cambiarla. Antes solo se pedía al iniciar sesión, y con el botón "atrás"
     * del teléfono se podía entrar sin cambiarla (visita del 16 de octubre de 2026).
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
        $accion = $_GET['accion'] ?? '';
        if (self::debeCambiarPass() && !in_array($accion, ['cambiar_pass', 'cambiar_pass_guardar'], true)) {
            header('Location: ' . APP_URL . '/index.php?accion=cambiar_pass');
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
