<?php

/**
 * Gestión de los niños: registro, edición, consulta.
 * Incluye la regla de permisos por sector definida en el levantamiento:
 * cada enfermera escribe en su sector; todas pueden consultar todos.
 */

class Nino
{
    /**
     * Edad que cubre el carnet: el sistema registra niños menores de 7 años.
     * Al cumplirla, el niño pasa a estado egresado y sus datos se conservan.
     */
    const EDAD_MAXIMA_ANIOS = 7;

    /**
     * Fecha de nacimiento a partir de la cual un niño ya cumplió hoy la edad
     * máxima. Un niño nacido en esa fecha o antes tiene 7 años o más.
     */
    public static function fechaLimiteNacimiento(): string
    {
        return (new DateTime('today'))
            ->modify('-' . self::EDAD_MAXIMA_ANIOS . ' years')
            ->format('Y-m-d');
    }

    /**
     * Pasa a estado egresado a los niños activos que ya cumplieron la edad
     * máxima. Sus datos se conservan; solo dejan de aparecer en el panel de
     * seguimiento, las citas y los reportes, que muestran niños activos.
     * Se ejecuta al iniciar sesión, porque los hosting gratuitos no suelen
     * permitir tareas programadas. Cada egreso queda en la bitácora a nombre
     * del usuario cuyo inicio de sesión lo ejecutó.
     * Devuelve la cantidad de niños egresados.
     */
    public static function egresarPorEdad(int $idUsuario): int
    {
        $db = Database::obtener();

        $stmt = $db->prepare(
            "SELECT id_nino, nombres, apellidos FROM nino
             WHERE estado = 'activo' AND fecha_nacimiento <= :limite"
        );
        $stmt->execute([':limite' => self::fechaLimiteNacimiento()]);
        $ninos = $stmt->fetchAll();

        $actualizar = $db->prepare(
            "UPDATE nino SET estado = 'egresado', fecha_egreso = CURDATE()
             WHERE id_nino = :id AND estado = 'activo'"
        );
        $egresados = 0;
        foreach ($ninos as $n) {
            $actualizar->execute([':id' => $n['id_nino']]);
            if ($actualizar->rowCount() === 0) {
                continue; // Otro inicio de sesión simultáneo ya lo egresó.
            }
            Bitacora::registrar(
                $idUsuario,
                'egresar',
                'nino',
                (int) $n['id_nino'],
                "Egreso automático de {$n['nombres']} {$n['apellidos']} por cumplir "
                    . self::EDAD_MAXIMA_ANIOS . ' años'
            );
            $egresados++;
        }
        return $egresados;
    }

    /**
     * Registra un niño nuevo. Devuelve el id del niño creado.
     */
    public static function crear(array $datos, int $idUsuario): int
    {
        $db = Database::obtener();

        $sql = "INSERT INTO nino
                (id_sector, id_paraje, cui, nombres, apellidos, fecha_nacimiento,
                 sexo, pueblo, comunidad_linguistica, nombre_madre, nombre_padre, telefono, direccion, estado)
                VALUES
                (:id_sector, :id_paraje, :cui, :nombres, :apellidos, :fecha_nacimiento,
                 :sexo, :pueblo, :comunidad_linguistica, :nombre_madre, :nombre_padre, :telefono, :direccion, 'activo')";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_sector'        => $datos['id_sector'],
            ':id_paraje'        => $datos['id_paraje'] ?: null,
            ':cui'              => $datos['cui'] ?: null,
            ':nombres'          => $datos['nombres'],
            ':apellidos'        => $datos['apellidos'],
            ':fecha_nacimiento' => $datos['fecha_nacimiento'],
            ':sexo'             => $datos['sexo'],
            ':pueblo'           => $datos['pueblo'] ?: null,
            ':comunidad_linguistica' => $datos['comunidad_linguistica'] ?: null,
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
                    pueblo = :pueblo,
                    comunidad_linguistica = :comunidad_linguistica,
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
            ':pueblo'           => $datos['pueblo'] ?: null,
            ':comunidad_linguistica' => $datos['comunidad_linguistica'] ?: null,
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
     * Columnas por las que se puede ordenar la lista de niños (lista blanca).
     * El ORDER BY no admite marcadores de posición de PDO, así que nunca se
     * arma con lo que llega del navegador: solo se acepta una de estas claves.
     */
    const ORDENES = ['nombre', 'edad', 'territorio', 'paraje', 'estado', 'registro'];

    /** Dirección con la que empieza cada columna al tocarla por primera vez. */
    public static function direccionInicial(string $orden): string
    {
        // Los registrados recientemente se ven primero; el resto, de menor a mayor.
        return $orden === 'registro' ? 'desc' : 'asc';
    }

    /**
     * Cláusula ORDER BY de la lista. Solo usa textos fijos escritos aquí;
     * si la clave no está en la lista blanca, ordena por nombre.
     */
    private static function ordenSql(string $orden, string $direccion): string
    {
        $dir = $direccion === 'desc' ? 'DESC' : 'ASC';
        $inv = $direccion === 'desc' ? 'ASC' : 'DESC';

        switch ($orden) {
            case 'edad':
                // De menor a mayor edad equivale a nacimiento más reciente primero.
                $sql = "n.fecha_nacimiento {$inv}";
                break;
            case 'territorio':
                $sql = "t.numero {$dir}, s.letra {$dir}";
                break;
            case 'paraje':
                // Los niños sin paraje van siempre al final.
                $sql = "p.nombre IS NULL, p.nombre {$dir}";
                break;
            case 'estado':
                $sql = "n.estado {$dir}";
                break;
            case 'registro':
                $sql = "n.fecha_registro {$dir}, n.id_nino {$dir}";
                break;
            default:
                $sql = "n.apellidos {$dir}, n.nombres {$dir}";
        }
        // Desempate por nombre, para que el orden sea siempre el mismo.
        return $sql . ', n.apellidos ASC, n.nombres ASC';
    }

    /**
     * Lista niños con filtros opcionales por texto, territorio, sector y estado,
     * en el orden elegido. La consulta (lectura) no restringe por sector:
     * todas ven a todos.
     */
    public static function listar(array $filtros = [], string $orden = 'nombre', string $direccion = 'asc'): array
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
                       n.sexo, n.estado, n.fecha_registro,
                       s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre
                FROM nino n
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . self::ordenSql($orden, $direccion) . "
                LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Determina si el usuario puede ESCRIBIR (crear/editar) en un sector dado.
     * Administrador: no captura datos clínicos.
     * Enfermería: su sector asignado; otros sectores se permiten con advertencia.
     * Enfermería sin sector (de apoyo): todos los sectores, sin advertencia.
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
            // Sin sector asignado es una enfermera de apoyo: trabaja en todos
            // los sectores sin advertencia (la bitácora registra todo igual).
            if (empty($usuario['id_sector']) || (int) $usuario['id_sector'] === $idSectorDestino) {
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
