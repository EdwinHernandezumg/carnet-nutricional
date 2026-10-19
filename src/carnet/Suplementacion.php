<?php
/**
 * Registro de suplementación entregada (vitamina A, suplemento nutricional
 * lipídico, micronutrientes, etc.) y su catálogo.
 * Digitaliza la sección correspondiente del carnet físico.
 *
 * El catálogo lo mantiene el administrador desde el sistema (pedido del
 * puesto, 16 de octubre de 2026): agrega suplementos nuevos y desactiva los
 * que ya no se entregan. Nunca se borra uno: las entregas guardan su nombre.
 */

class Suplementacion
{
    /** Largos máximos: los de las columnas de catalogo_suplemento. */
    public const NOMBRE_MAX = 80;
    public const PRESENTACION_MAX = 60;
    public const INDICACION_MAX = 100;

    /** Solo se registran niños menores de 7 años: 83 meses como máximo. */
    public const EDAD_MAX_MESES = 83;

    /** Suplementos activos, los que se ofrecen al registrar una entrega. */
    public static function catalogo(): array
    {
        $db = Database::obtener();
        return $db->query(
            "SELECT id_catalogo_suplemento, nombre, presentacion, indicacion,
                    edad_desde_meses, edad_hasta_meses
             FROM catalogo_suplemento WHERE activo = 1 ORDER BY orden, nombre"
        )->fetchAll();
    }

    /**
     * Catálogo completo para el administrador: activos primero, y cuántas
     * entregas tiene registradas cada suplemento.
     */
    public static function catalogoCompleto(): array
    {
        $db = Database::obtener();
        return $db->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM suplementacion s WHERE s.tipo = c.nombre) AS entregas
             FROM catalogo_suplemento c
             ORDER BY c.activo DESC, c.orden, c.nombre"
        )->fetchAll();
    }

    /** Un suplemento del catálogo, con sus entregas registradas. */
    public static function obtenerDelCatalogo(int $id): ?array
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM suplementacion s WHERE s.tipo = c.nombre) AS entregas
             FROM catalogo_suplemento c WHERE c.id_catalogo_suplemento = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Rango de edad en palabras: "De 6 a 23 meses", "Desde 6 meses",
     * "Hasta 59 meses", o "" si no tiene.
     */
    public static function etiquetaEdades($desde, $hasta): string
    {
        $desde = $desde === null || $desde === '' ? null : (int)$desde;
        $hasta = $hasta === null || $hasta === '' ? null : (int)$hasta;
        if ($desde !== null && $hasta !== null) return "De $desde a $hasta meses";
        if ($desde !== null) return "Desde $desde " . ($desde === 1 ? 'mes' : 'meses');
        if ($hasta !== null) return "Hasta $hasta " . ($hasta === 1 ? 'mes' : 'meses');
        return '';
    }

    /**
     * Valida los datos de un suplemento del catálogo. El nombre no puede
     * repetirse; $idExcepto es el suplemento que se está editando.
     */
    public static function validarCatalogo(array $d, ?int $idExcepto = null): array
    {
        $errores = [
            Validacion::texto($d['nombre'], 'Nombre', self::NOMBRE_MAX, true),
            Validacion::texto($d['presentacion'], 'Presentación', self::PRESENTACION_MAX),
            Validacion::texto($d['indicacion'], 'Indicación', self::INDICACION_MAX),
        ];
        foreach (['edad_desde_meses' => 'Edad desde', 'edad_hasta_meses' => 'Edad hasta'] as $campo => $etiqueta) {
            $valor = $d[$campo];
            if ($valor !== '' && (!ctype_digit($valor) || (int)$valor > self::EDAD_MAX_MESES)) {
                $errores[] = "$etiqueta: escriba los meses, de 0 a " . self::EDAD_MAX_MESES . '.';
            }
        }
        if (ctype_digit($d['edad_desde_meses']) && ctype_digit($d['edad_hasta_meses'])
            && (int)$d['edad_hasta_meses'] < (int)$d['edad_desde_meses']) {
            $errores[] = 'Edad hasta: no puede ser menor que la edad desde.';
        }

        if ($d['nombre'] !== '') {
            $db = Database::obtener();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM catalogo_suplemento
                 WHERE nombre = :nombre AND id_catalogo_suplemento <> :id"
            );
            $stmt->execute([':nombre' => $d['nombre'], ':id' => $idExcepto ?? 0]);
            if ($stmt->fetchColumn() > 0) {
                $errores[] = 'Nombre: ya hay un suplemento con ese nombre en el catálogo.';
            }
        }
        return array_values(array_filter($errores));
    }

    /** Agrega un suplemento al final de la lista. */
    public static function crearEnCatalogo(array $d, int $idUsuario): int
    {
        $db = Database::obtener();
        $orden = (int)$db->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM catalogo_suplemento")->fetchColumn();
        $db->prepare(
            "INSERT INTO catalogo_suplemento
             (nombre, presentacion, indicacion, edad_desde_meses, edad_hasta_meses, orden, activo)
             VALUES (:nombre, :presentacion, :indicacion, :desde, :hasta, :orden, 1)"
        )->execute([
            ':nombre'       => $d['nombre'],
            ':presentacion' => $d['presentacion'] ?: null,
            ':indicacion'   => $d['indicacion'] ?: null,
            ':desde'        => $d['edad_desde_meses'] === '' ? null : (int)$d['edad_desde_meses'],
            ':hasta'        => $d['edad_hasta_meses'] === '' ? null : (int)$d['edad_hasta_meses'],
            ':orden'        => $orden,
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar($idUsuario, 'crear', 'catalogo_suplemento', $id,
            'Suplemento agregado al catálogo: ' . $d['nombre']);
        return $id;
    }

    /**
     * Actualiza un suplemento del catálogo. El nombre solo cambia si todavía
     * no tiene entregas: las entregas guardan el nombre, y cambiarlo dejaría
     * las anteriores con otro. Para un producto distinto, se desactiva este y
     * se agrega el nuevo.
     */
    public static function actualizarEnCatalogo(int $id, array $d, int $idUsuario): void
    {
        $antes = self::obtenerDelCatalogo($id);
        if ((int)$antes['entregas'] > 0) {
            $d['nombre'] = $antes['nombre'];
        }
        $db = Database::obtener();
        $db->prepare(
            "UPDATE catalogo_suplemento
             SET nombre = :nombre, presentacion = :presentacion, indicacion = :indicacion,
                 edad_desde_meses = :desde, edad_hasta_meses = :hasta
             WHERE id_catalogo_suplemento = :id"
        )->execute([
            ':nombre'       => $d['nombre'],
            ':presentacion' => $d['presentacion'] ?: null,
            ':indicacion'   => $d['indicacion'] ?: null,
            ':desde'        => $d['edad_desde_meses'] === '' ? null : (int)$d['edad_desde_meses'],
            ':hasta'        => $d['edad_hasta_meses'] === '' ? null : (int)$d['edad_hasta_meses'],
            ':id'           => $id,
        ]);

        $cambios = [];
        $campos = ['nombre' => 'nombre', 'presentacion' => 'presentación', 'indicacion' => 'indicación',
                   'edad_desde_meses' => 'edad desde', 'edad_hasta_meses' => 'edad hasta'];
        foreach ($campos as $columna => $etiqueta) {
            $anterior = (string)($antes[$columna] ?? '');
            $nuevo    = (string)($d[$columna] ?? '');
            if ($anterior !== $nuevo) {
                $cambios[] = "$etiqueta de \"" . ($anterior === '' ? 'ninguno' : $anterior)
                           . "\" a \"" . ($nuevo === '' ? 'ninguno' : $nuevo) . '"';
            }
        }
        Bitacora::registrar($idUsuario, 'actualizar', 'catalogo_suplemento', $id,
            'Edición del suplemento ' . $d['nombre'] . ': ' . ($cambios ? implode('; ', $cambios) : 'sin cambios'));
    }

    /** Activa o desactiva un suplemento. Desactivado, no aparece al registrar entregas. */
    public static function cambiarEstadoCatalogo(int $id, bool $activo, int $idUsuario): void
    {
        $suplemento = self::obtenerDelCatalogo($id);
        if (!$suplemento) {
            return;
        }
        $db = Database::obtener();
        $db->prepare("UPDATE catalogo_suplemento SET activo = :activo WHERE id_catalogo_suplemento = :id")
           ->execute([':activo' => $activo ? 1 : 0, ':id' => $id]);

        Bitacora::registrar($idUsuario, $activo ? 'activar' : 'desactivar', 'catalogo_suplemento', $id,
            ($activo ? 'Suplemento activado: ' : 'Suplemento desactivado: ') . $suplemento['nombre']);
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
