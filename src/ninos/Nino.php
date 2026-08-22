<?php

/**
 * Gestión de los niños: registro, edición, consulta.
 * Incluye la regla de permisos por sector definida en el levantamiento:
 * cada enfermera escribe en su sector; todas pueden consultar todos.
 */

class Nino
{
    /**
     * Registra un niño nuevo. Devuelve el id del niño creado.
     */
    public static function crear(array $datos, int $idUsuario): int
    {
        $db = Database::obtener();

        $sql = "INSERT INTO nino
                (id_sector, id_paraje, cui, nombres, apellidos, fecha_nacimiento,
                 sexo, etnia, nombre_madre, nombre_padre, telefono, direccion, estado)
                VALUES
                (:id_sector, :id_paraje, :cui, :nombres, :apellidos, :fecha_nacimiento,
                 :sexo, :etnia, :nombre_madre, :nombre_padre, :telefono, :direccion, 'activo')";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_sector'        => $datos['id_sector'],
            ':id_paraje'        => $datos['id_paraje'] ?: null,
            ':cui'              => $datos['cui'] ?: null,
            ':nombres'          => $datos['nombres'],
            ':apellidos'        => $datos['apellidos'],
            ':fecha_nacimiento' => $datos['fecha_nacimiento'],
            ':sexo'             => $datos['sexo'],
            ':etnia'            => $datos['etnia'] ?: null,
            ':nombre_madre'     => $datos['nombre_madre'] ?: null,
            ':nombre_padre'     => $datos['nombre_padre'] ?: null,
            ':telefono'         => $datos['telefono'] ?: null,
            ':direccion'        => $datos['direccion'] ?: null,
        ]);

        $idNino = (int) $db->lastInsertId();

        Bitacora::registrar(
            $idUsuario,
            'crear',
            'nino',
            $idNino,
            "Registro del niño {$datos['nombres']} {$datos['apellidos']}"
        );

        return $idNino;
    }

    /**
     * Actualiza los datos de un niño existente.
     */
    public static function actualizar(int $idNino, array $datos, int $idUsuario): void
    {
        $db = Database::obtener();

        $sql = "UPDATE nino SET
                    id_sector = :id_sector,
                    id_paraje = :id_paraje,
                    cui = :cui,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo,
                    etnia = :etnia,
                    nombre_madre = :nombre_madre,
                    nombre_padre = :nombre_padre,
                    telefono = :telefono,
                    direccion = :direccion
                WHERE id_nino = :id_nino";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_sector'        => $datos['id_sector'],
            ':id_paraje'        => $datos['id_paraje'] ?: null,
            ':cui'              => $datos['cui'] ?: null,
            ':nombres'          => $datos['nombres'],
            ':apellidos'        => $datos['apellidos'],
            ':fecha_nacimiento' => $datos['fecha_nacimiento'],
            ':sexo'             => $datos['sexo'],
            ':etnia'            => $datos['etnia'] ?: null,
            ':nombre_madre'     => $datos['nombre_madre'] ?: null,
            ':nombre_padre'     => $datos['nombre_padre'] ?: null,
            ':telefono'         => $datos['telefono'] ?: null,
            ':direccion'        => $datos['direccion'] ?: null,
            ':id_nino'          => $idNino,
        ]);

        Bitacora::registrar(
            $idUsuario,
            'editar',
            'nino',
            $idNino,
            "Edición del niño {$datos['nombres']} {$datos['apellidos']}"
        );
    }

    /**
     * Busca un niño por su id. Devuelve el registro o null.
     */
    public static function obtener(int $idNino): ?array
    {
        $db = Database::obtener();
        $sql = "SELECT n.*, s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre
                FROM nino n
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE n.id_nino = :id_nino
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_nino' => $idNino]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    /**
     * Lista niños con filtros opcionales por texto, territorio, sector y estado.
     * La consulta (lectura) no restringe por sector: todas ven a todos.
     */
    public static function listar(array $filtros = []): array
    {
        $db = Database::obtener();

        $where = ["1=1"];
        $params = [];

        if (!empty($filtros['texto'])) {
            $where[] = "(n.nombres LIKE :texto1 OR n.apellidos LIKE :texto2 OR CONCAT(n.nombres, ' ', n.apellidos) LIKE :texto3)";
            $params[':texto1'] = '%' . $filtros['texto'] . '%';
            $params[':texto2'] = '%' . $filtros['texto'] . '%';
            $params[':texto3'] = '%' . $filtros['texto'] . '%';
        }
        if (!empty($filtros['id_sector'])) {
            $where[] = "n.id_sector = :id_sector";
            $params[':id_sector'] = $filtros['id_sector'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = "n.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        $sql = "SELECT n.id_nino, n.nombres, n.apellidos, n.cui, n.fecha_nacimiento,
                       n.sexo, n.estado, s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre
                FROM nino n
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE " . implode(' AND ', $where) . "
                ORDER BY n.apellidos, n.nombres
                LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Determina si el usuario puede ESCRIBIR (crear/editar) en un sector dado.
     * Administrador: no captura datos clínicos.
     * Enfermería: solo su sector asignado; otros sectores se permiten con advertencia.
     * Consulta: nunca escribe.
     *
     * Devuelve: 'permitido', 'advertencia' o 'denegado'.
     */
    public static function permisoEscritura(array $usuario, int $idSectorDestino): string
    {
        if ($usuario['rol'] === 'consulta') {
            return 'denegado';
        }
        if ($usuario['rol'] === 'administrador') {
            // El administrador no registra niños en este diseño.
            return 'denegado';
        }
        if ($usuario['rol'] === 'enfermeria') {
            if ((int) $usuario['id_sector'] === $idSectorDestino) {
                return 'permitido';
            }
            // Sector distinto: se permite pero se advierte y queda en bitácora.
            return 'advertencia';
        }
        return 'denegado';
    }

    /**
     * Calcula la edad en meses a una fecha dada, a partir de la fecha de nacimiento.
     */
    public static function edadEnMeses(string $fechaNacimiento, ?string $fechaReferencia = null): int
    {
        $nac = new DateTime($fechaNacimiento);
        $ref = new DateTime($fechaReferencia ?? 'now');
        $diff = $nac->diff($ref);
        return $diff->y * 12 + $diff->m;
    }
}
