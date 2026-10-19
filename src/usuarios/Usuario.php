<?php

/**
 * Gestión de usuarios por el administrador (RF-12).
 * Incluye la recuperación de contraseña gestionada por el administrador:
 * genera una contraseña temporal que el usuario debe cambiar al ingresar.
 */

class Usuario
{
    /**
     * Lista todos los usuarios con su rol y sector.
     */
    public static function listar(): array
    {
        $db = Database::obtener();
        $sql = "SELECT u.id_usuario, u.nombre_completo, u.usuario, u.activo,
                       u.debe_cambiar_pass, r.nombre AS rol,
                       s.letra AS sector_letra, t.numero AS territorio_numero
                FROM usuario u
                JOIN rol r ON r.id_rol = u.id_rol
                LEFT JOIN sector s ON s.id_sector = u.id_sector
                LEFT JOIN territorio t ON t.id_territorio = s.id_territorio
                ORDER BY u.activo DESC, u.nombre_completo";
        return $db->query($sql)->fetchAll();
    }

    /** Obtiene un usuario por id. */
    public static function obtener(int $id): ?array
    {
        $db = Database::obtener();
        $stmt = $db->prepare("SELECT * FROM usuario WHERE id_usuario = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    /**
     * Crea un usuario nuevo. Devuelve el id.
     * La contraseña se recibe en texto y se guarda hasheada.
     */
    /**
     * Crea un usuario nuevo con una contraseña TEMPORAL generada automáticamente.
     * Marca que debe cambiarla al primer ingreso. Devuelve la contraseña temporal
     * en texto (para mostrarla una vez al administrador).
     */
    public static function crear(array $d, int $idAdmin): string
    {
        $db = Database::obtener();

        $temporal = Contrasena::generarTemporal();
        $hash = password_hash($temporal, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuario
                (id_rol, id_puesto, id_sector, nombre_completo, usuario, contrasena_hash, debe_cambiar_pass)
                VALUES (:id_rol, 1, :id_sector, :nombre, :usuario, :hash, TRUE)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_rol'    => $d['id_rol'],
            ':id_sector' => $d['id_sector'] ?: null,
            ':nombre'    => $d['nombre_completo'],
            ':usuario'   => $d['usuario'],
            ':hash'      => $hash,
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar(
            $idAdmin,
            'crear',
            'usuario',
            $id,
            "Alta de usuario {$d['usuario']} ({$d['nombre_completo']})"
        );

        return $temporal;
    }

    /**
     * Restablece la contraseña de un usuario con una TEMPORAL generada.
     * Marca que debe cambiarla al ingresar. Devuelve la contraseña temporal
     * en texto (se muestra una sola vez al administrador).
     */
    public static function restablecerContrasena(int $idUsuario, int $idAdmin): string
    {
        $temporal = Contrasena::generarTemporal();
        $hash = password_hash($temporal, PASSWORD_DEFAULT);

        $db = Database::obtener();
        $stmt = $db->prepare(
            "UPDATE usuario SET contrasena_hash = :hash, debe_cambiar_pass = TRUE
             WHERE id_usuario = :id"
        );
        $stmt->execute([':hash' => $hash, ':id' => $idUsuario]);

        Bitacora::registrar(
            $idAdmin,
            'restablecer_pass',
            'usuario',
            $idUsuario,
            'Restablecimiento de contraseña (temporal generada)'
        );

        return $temporal;
    }

    /**
     * Cambia la contraseña del propio usuario (primer ingreso o cambio voluntario).
     * Quita la marca de cambio obligatorio.
     */
    public static function cambiarContrasenaPropia(int $idUsuario, string $nueva): void
    {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $db = Database::obtener();
        $stmt = $db->prepare(
            "UPDATE usuario SET contrasena_hash = :hash, debe_cambiar_pass = FALSE
             WHERE id_usuario = :id"
        );
        $stmt->execute([':hash' => $hash, ':id' => $idUsuario]);

        Bitacora::registrar(
            $idUsuario,
            'cambiar_pass',
            'usuario',
            $idUsuario,
            'Cambio de contraseña propia'
        );
    }

    /**
     * Da de baja o reactiva un usuario.
     */
    public static function cambiarEstado(int $idUsuario, bool $activo, int $idAdmin): void
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "UPDATE usuario SET activo = :activo,
                    fecha_baja = " . ($activo ? "NULL" : "NOW()") . "
             WHERE id_usuario = :id"
        );
        $stmt->execute([':activo' => $activo ? 1 : 0, ':id' => $idUsuario]);

        Bitacora::registrar(
            $idAdmin,
            $activo ? 'reactivar' : 'dar_baja',
            'usuario',
            $idUsuario,
            $activo ? 'Reactivación de usuario' : 'Baja de usuario'
        );
    }

    /**
     * Verifica si un nombre de usuario ya existe (para validación).
     */
    public static function usuarioExiste(string $usuario, ?int $idExcepto = null): bool
    {
        $db = Database::obtener();
        $stmt = $db->prepare("SELECT 1 FROM usuario WHERE usuario = :u AND id_usuario <> :excepto LIMIT 1");
        $stmt->execute([':u' => $usuario, ':excepto' => $idExcepto ?? 0]);
        return (bool)$stmt->fetch();
    }

    /**
     * Corrige el nombre, el usuario, el rol o el sector de una cuenta. Deja en
     * la bitácora qué cambió, con el valor anterior y el nuevo.
     */
    public static function actualizar(int $id, array $d, int $idAdmin): void
    {
        $antes = self::obtener($id);
        $db = Database::obtener();
        $db->prepare("UPDATE usuario SET nombre_completo = :nombre, usuario = :usuario,
                             id_rol = :id_rol, id_sector = :id_sector
                      WHERE id_usuario = :id")
           ->execute([
               ':nombre'    => $d['nombre_completo'],
               ':usuario'   => $d['usuario'],
               ':id_rol'    => $d['id_rol'],
               ':id_sector' => $d['id_sector'] ?: null,
               ':id'        => $id,
           ]);

        $cambios = [];
        $campos = ['nombre_completo' => 'nombre', 'usuario' => 'usuario', 'id_rol' => 'rol', 'id_sector' => 'sector'];
        foreach ($campos as $columna => $etiqueta) {
            $anterior = (string)($antes[$columna] ?? '');
            $nuevo    = (string)($d[$columna] ?? '');
            if ($anterior !== $nuevo) {
                $cambios[] = "$etiqueta de \"" . ($anterior === '' ? 'ninguno' : $anterior)
                           . "\" a \"" . ($nuevo === '' ? 'ninguno' : $nuevo) . '"';
            }
        }
        Bitacora::registrar($idAdmin, 'actualizar', 'usuario', $id,
            'Edición de usuario ' . $d['usuario'] . ': ' . ($cambios ? implode('; ', $cambios) : 'sin cambios'));
    }
}
