<?php
/**
 * Registro de vacunación aplicada.
 * Digitaliza la sección de vacunas del carnet físico del niño.
 *
 * El catálogo sigue el esquema nacional vigente (cuaderno SIGSA 5a 2026 del
 * MSPAS). También trae las dosis del esquema anterior (carnet de 2018), marcadas
 * como no vigentes, para registrar las que ya tiene el carnet físico de los
 * niños mayores. Cada una indica qué dosis del esquema vigente cubre.
 */

class Vacuna
{
    /** Lugar de vacunación, con el código "LV" del cuaderno SIGSA 5a. */
    const LUGARES = [
        1 => 'Establecimiento de salud',
        2 => 'Establecimiento educativo',
        3 => 'Miniconcentración',
        4 => 'Brigada móvil',
        5 => 'Casa',
    ];

    /** El lugar más común, para que la enfermera no tenga que elegirlo. */
    const LUGAR_PREDETERMINADO = 1;

    /**
     * Catálogo completo: primero el esquema vigente y luego el anterior.
     * Editable en la base de datos (tabla catalogo_vacuna).
     */
    public static function catalogo(): array
    {
        $db = Database::obtener();
        return $db->query(
            "SELECT id_catalogo_vacuna, nombre, dosis, edad_sugerida, edad_meses, vigente, equivale_a
             FROM catalogo_vacuna WHERE activo = 1
             ORDER BY vigente DESC, orden"
        )->fetchAll();
    }

    /** Nombre del lugar de vacunación a partir de su código. */
    public static function etiquetaLugar(?int $codigo): ?string
    {
        return $codigo === null ? null : (self::LUGARES[$codigo] ?? null);
    }

    /** Registra una vacuna aplicada. */
    public static function crear(array $d, int $idUsuario): int
    {
        $db = Database::obtener();
        $sql = "INSERT INTO vacuna_aplicada
                (id_nino, id_usuario, nombre_vacuna, dosis, fecha_aplicacion, lote, lugar_vacunacion)
                VALUES (:id_nino, :id_usuario, :nombre, :dosis, :fecha, :lote, :lugar)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'    => $d['id_nino'],
            ':id_usuario' => $idUsuario,
            ':nombre'     => $d['nombre_vacuna'],
            ':dosis'      => $d['dosis'],
            ':fecha'      => $d['fecha_aplicacion'],
            ':lote'       => $d['lote'] ?: null,
            ':lugar'      => $d['lugar_vacunacion'],
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar($idUsuario, 'crear', 'vacuna_aplicada', $id,
            "Vacuna {$d['nombre_vacuna']} ({$d['dosis']}) al niño id {$d['id_nino']}");
        return $id;
    }

    /**
     * Vacunas aplicadas a un niño, de la más reciente a la más antigua. Las
     * anuladas solo se incluyen si se piden (el historial las muestra tachadas).
     */
    public static function listarPorNino(int $idNino, bool $incluirAnuladas = false): array
    {
        $db = Database::obtener();
        $sql = "SELECT v.*, u.nombre_completo AS registrado_por, ua.nombre_completo AS anulado_por_nombre
                FROM vacuna_aplicada v
                LEFT JOIN usuario u ON u.id_usuario = v.id_usuario
                LEFT JOIN usuario ua ON ua.id_usuario = v.anulado_por
                WHERE v.id_nino = :id"
             . ($incluirAnuladas ? '' : ' AND v.anulado = 0') . "
                ORDER BY v.fecha_aplicacion DESC, v.id_vacuna_aplicada DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idNino]);
        return $stmt->fetchAll();
    }

    /**
     * Esquema vigente del niño: cada dosis con su estado.
     *   aplicada:  ya la tiene (o una dosis equivalente del esquema anterior).
     *   pendiente: por su edad ya le tocaba y no está registrada.
     *   proxima:   todavía no le toca.
     */
    public static function esquemaDelNino(int $idNino, string $fechaNacimiento): array
    {
        $catalogo = self::catalogo();

        // Dosis registradas, por "nombre|dosis" (la más antigua si se repite).
        $puestas = [];
        foreach (array_reverse(self::listarPorNino($idNino)) as $a) {
            $puestas[$a['nombre_vacuna'] . '|' . $a['dosis']] ??= $a['fecha_aplicacion'];
        }

        // Dosis del esquema anterior que cubren una del vigente.
        $cubiertas = [];
        foreach ($catalogo as $c) {
            $clave = $c['nombre'] . '|' . $c['dosis'];
            if (!$c['vigente'] && $c['equivale_a'] !== null && isset($puestas[$clave])) {
                $cubiertas[(int)$c['equivale_a']] = [
                    'fecha' => $puestas[$clave],
                    'texto' => $c['nombre'] . ' ' . $c['dosis'],
                ];
            }
        }

        $d = (new DateTime($fechaNacimiento))->diff(new DateTime('today'));
        $edadMeses = $d->y * 12 + $d->m;

        $esquema = [];
        foreach ($catalogo as $c) {
            if (!$c['vigente']) {
                continue;
            }
            $id    = (int)$c['id_catalogo_vacuna'];
            $clave = $c['nombre'] . '|' . $c['dosis'];
            $fecha = $puestas[$clave] ?? ($cubiertas[$id]['fecha'] ?? null);

            // "Cubierta": no se puso esta dosis, sino la del esquema anterior que
            // la reemplaza. No se muestra como aplicada (la jefa del puesto pidió
            // no darla por hecha) ni como pendiente (no se debe repetir).
            if (isset($puestas[$clave])) {
                $estado = 'aplicada';
            } elseif ($fecha !== null) {
                $estado = 'cubierta';
            } elseif ($c['edad_meses'] !== null && $edadMeses >= (int)$c['edad_meses']) {
                $estado = 'pendiente';
            } else {
                $estado = 'proxima';
            }

            $esquema[] = [
                'nombre'        => $c['nombre'],
                'dosis'         => $c['dosis'],
                'edad_sugerida' => $c['edad_sugerida'],
                'estado'        => $estado,
                'fecha'         => $fecha,
                // Si la cubre una dosis del esquema anterior, cuál fue.
                'equivalente'   => isset($puestas[$clave]) ? null : ($cubiertas[$id]['texto'] ?? null),
            ];
        }
        return $esquema;
    }
}
