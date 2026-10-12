<?php

/**
 * Gestión de mediciones antropométricas (CU-03).
 *
 * Este módulo registra y consulta mediciones. El cálculo de puntuaciones Z
 * y la generación de alertas los hacen CU-04 (Calculo) y CU-05 (Alerta),
 * que se invocan al guardar cada medición.
 *
 * Decisiones de diseño:
 * - Cualquier enfermería puede medir a cualquier niño, sin advertencia
 *   (atención de salud inmediata). La bitácora deja constancia silenciosa.
 * - El tipo de talla (longitud/talla) lo sugiere el sistema según la edad,
 *   pero la enfermera puede cambiarlo.
 */

class Medicion
{
    /**
     * Registra una medición. Devuelve el id de la medición creada.
     */
    public static function crear(array $datos, int $idUsuario): int
    {
        $db = Database::obtener();

        // La medición, su cálculo y su alerta se guardan juntos en una
        // transacción: si algo falla, no queda una medición a medias (sin
        // puntuaciones Z o sin alerta). Si ya hay una transacción abierta (por
        // ejemplo, en un script), se trabaja dentro de ella.
        $transaccionPropia = !$db->inTransaction();
        if ($transaccionPropia) {
            $db->beginTransaction();
        }
        try {
            $idMedicion = self::insertar($db, $datos, $idUsuario);
            Calculo::procesar($idMedicion);
            Alerta::generar($idMedicion, (int) $datos['id_nino']);
            if ($transaccionPropia) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($transaccionPropia) {
                $db->rollBack();
            }
            throw $e;
        }
        return $idMedicion;
    }

    /** Inserta la medición y la registra en la bitácora. */
    private static function insertar(PDO $db, array $datos, int $idUsuario): int
    {
        $sql = "INSERT INTO medicion
                (id_nino, id_usuario, fecha_toma, peso_kg, talla_cm, tipo_talla, edad_meses, observaciones)
                VALUES
                (:id_nino, :id_usuario, :fecha_toma, :peso_kg, :talla_cm, :tipo_talla, :edad_meses, :observaciones)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'      => $datos['id_nino'],
            ':id_usuario'   => $idUsuario,
            ':fecha_toma'   => $datos['fecha_toma'],
            ':peso_kg'      => $datos['peso_kg'],
            ':talla_cm'     => $datos['talla_cm'],
            ':tipo_talla'   => $datos['tipo_talla'],
            ':edad_meses'   => $datos['edad_meses'],
            ':observaciones' => $datos['observaciones'] ?: null,
        ]);

        $idMedicion = (int) $db->lastInsertId();

        Bitacora::registrar(
            $idUsuario,
            'crear',
            'medicion',
            $idMedicion,
            "Medición del niño id {$datos['id_nino']}: {$datos['peso_kg']} kg, {$datos['talla_cm']} cm"
                . (!empty($datos['valores_confirmados'])
                    ? ' (valores fuera de los límites de la OMS, confirmados por quien registró)'
                    : '')
        );
        return $idMedicion;
    }

    /**
     * Lista las mediciones de un niño, de la más reciente a la más antigua.
     */
    public static function listarPorNino(int $idNino): array
    {
        $db = Database::obtener();
        $sql = "SELECT m.*, u.nombre_completo AS registrado_por
                FROM medicion m
                JOIN usuario u ON u.id_usuario = m.id_usuario
                WHERE m.id_nino = :id_nino
                ORDER BY m.fecha_toma DESC, m.id_medicion DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_nino' => $idNino]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una medición por su id.
     */
    public static function obtener(int $idMedicion): ?array
    {
        $db = Database::obtener();
        $sql = "SELECT * FROM medicion WHERE id_medicion = :id LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idMedicion]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    /**
     * Sugiere el tipo de talla según la edad en meses.
     * Menores de 24 meses: longitud (acostado, infantómetro).
     * 24 meses o más: talla (de pie, tallímetro).
     */
    public static function tipoTallaSugerido(int $edadMeses): string
    {
        return $edadMeses < 24 ? 'longitud' : 'talla';
    }

    /**
     * Texto de ayuda del instrumento según el tipo de talla.
     */
    public static function instrumentoSugerido(int $edadMeses): string
    {
        return $edadMeses < 24
            ? 'Menor de 2 años: usar balanza pediátrica e infantómetro (longitud acostado).'
            : 'Mayor de 2 años: usar pesa de pie y tallímetro (talla de pie).';
    }
}
