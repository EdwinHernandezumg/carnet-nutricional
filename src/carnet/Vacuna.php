<?php
/**
 * Registro de vacunación aplicada.
 * Digitaliza la sección de vacunas del carnet físico del niño.
 */

class Vacuna
{
    /** Catálogo de vacunas del esquema nacional (editable en base de datos). */
    public static function catalogo(): array
    {
        $db = Database::obtener();
        return $db->query(
            "SELECT id_catalogo_vacuna, nombre, dosis, edad_sugerida
             FROM catalogo_vacuna WHERE activo = 1 ORDER BY orden"
        )->fetchAll();
    }

    /** Registra una vacuna aplicada. */
    public static function crear(array $d, int $idUsuario): int
    {
        $db = Database::obtener();
        $sql = "INSERT INTO vacuna_aplicada
                (id_nino, id_usuario, nombre_vacuna, dosis, fecha_aplicacion, lote, lugar)
                VALUES (:id_nino, :id_usuario, :nombre, :dosis, :fecha, :lote, :lugar)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'    => $d['id_nino'],
            ':id_usuario' => $idUsuario,
            ':nombre'     => $d['nombre_vacuna'],
            ':dosis'      => $d['dosis'],
            ':fecha'      => $d['fecha_aplicacion'],
            ':lote'       => $d['lote'] ?: null,
            ':lugar'      => $d['lugar'] ?: null,
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar($idUsuario, 'crear', 'vacuna_aplicada', $id,
            "Vacuna {$d['nombre_vacuna']} ({$d['dosis']}) al niño id {$d['id_nino']}");
        return $id;
    }

    /** Vacunas aplicadas a un niño, de la más reciente a la más antigua. */
    public static function listarPorNino(int $idNino): array
    {
        $db = Database::obtener();
        $sql = "SELECT v.*, u.nombre_completo AS registrado_por
                FROM vacuna_aplicada v
                LEFT JOIN usuario u ON u.id_usuario = v.id_usuario
                WHERE v.id_nino = :id
                ORDER BY v.fecha_aplicacion DESC, v.id_vacuna_aplicada DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idNino]);
        return $stmt->fetchAll();
    }

    /**
     * Compara lo aplicado contra el catálogo para mostrar el esquema del niño:
     * qué tiene y qué le falta. Útil para la vista de seguimiento.
     */
    public static function esquemaDelNino(int $idNino): array
    {
        $aplicadas = self::listarPorNino($idNino);
        $puestas = [];
        foreach ($aplicadas as $a) {
            $puestas[$a['nombre_vacuna'] . '|' . $a['dosis']] = $a['fecha_aplicacion'];
        }

        $esquema = [];
        foreach (self::catalogo() as $c) {
            $clave = $c['nombre'] . '|' . $c['dosis'];
            $esquema[] = [
                'nombre'        => $c['nombre'],
                'dosis'         => $c['dosis'],
                'edad_sugerida' => $c['edad_sugerida'],
                'aplicada'      => isset($puestas[$clave]),
                'fecha'         => $puestas[$clave] ?? null,
            ];
        }
        return $esquema;
    }
}
