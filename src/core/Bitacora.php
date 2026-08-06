<?php
/**
 * Bitácora de auditoría.
 * Registra cada operación relevante con usuario, acción y marca de tiempo.
 * Sustituye a la restricción por horario: el sistema no bloquea el uso
 * fuera de la jornada, pero deja rastro de todo (RNF-04, RS).
 */

class Bitacora
{
    /**
     * Registra una acción en la bitácora.
     *
     * @param int    $idUsuario   Usuario que ejecuta la acción.
     * @param string $accion      crear | editar | eliminar | login | logout | consultar
     * @param string $tabla       Tabla afectada.
     * @param int|null $idRegistro Id del registro afectado (si aplica).
     * @param string $detalle     Descripción breve.
     */
    public static function registrar(
        int $idUsuario,
        string $accion,
        string $tabla,
        ?int $idRegistro = null,
        string $detalle = ''
    ): void {
        $db = Database::obtener();

        $sql = "INSERT INTO bitacora
                (id_usuario, accion, tabla_afectada, id_registro_afectado, detalle, ip_origen)
                VALUES
                (:id_usuario, :accion, :tabla, :id_registro, :detalle, :ip)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':accion'      => $accion,
            ':tabla'       => $tabla,
            ':id_registro' => $idRegistro,
            ':detalle'     => $detalle,
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
