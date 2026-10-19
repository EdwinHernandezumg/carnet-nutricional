<?php
/**
 * Registro de suplementación entregada (vitamina A, hierro, micronutrientes, etc.).
 * Digitaliza la sección correspondiente del carnet físico.
 */

class Suplementacion
{
    /** Catálogo de suplementos (editable en base de datos). */
    public static function catalogo(): array
    {
        $db = Database::obtener();
        return $db->query(
            "SELECT id_catalogo_suplemento, nombre, presentacion
             FROM catalogo_suplemento WHERE activo = 1 ORDER BY orden"
        )->fetchAll();
    }

    /** Registra una entrega de suplemento. */
    public static function crear(array $d, int $idUsuario): int
    {
        $db = Database::obtener();
        $sql = "INSERT INTO suplementacion
                (id_nino, id_usuario, tipo, fecha_entrega, cantidad)
                VALUES (:id_nino, :id_usuario, :tipo, :fecha, :cantidad)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'    => $d['id_nino'],
            ':id_usuario' => $idUsuario,
            ':tipo'       => $d['tipo'],
            ':fecha'      => $d['fecha_entrega'],
            ':cantidad'   => $d['cantidad'] ?: null,
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar($idUsuario, 'crear', 'suplementacion', $id,
            "Suplemento {$d['tipo']} al niño id {$d['id_nino']}");
        return $id;
    }

    /**
     * Suplementos entregados a un niño. Los anulados solo se incluyen si se
     * piden (el historial los muestra tachados).
     */
    public static function listarPorNino(int $idNino, bool $incluirAnulados = false): array
    {
        $db = Database::obtener();
        $sql = "SELECT s.*, u.nombre_completo AS registrado_por, ua.nombre_completo AS anulado_por_nombre
                FROM suplementacion s
                LEFT JOIN usuario u ON u.id_usuario = s.id_usuario
                LEFT JOIN usuario ua ON ua.id_usuario = s.anulado_por
                WHERE s.id_nino = :id"
             . ($incluirAnulados ? '' : ' AND s.anulado = 0') . "
                ORDER BY s.fecha_entrega DESC, s.id_suplementacion DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idNino]);
        return $stmt->fetchAll();
    }
}
