<?php
/**
 * Gestión de citas y próximas citas.
 *
 * El aviso de cita se resuelve mediante el enlace "click to chat" de WhatsApp,
 * que abre la aplicación con el mensaje ya redactado para que el personal lo
 * envíe con un toque. NO utiliza la API de pago de WhatsApp: el costo es cero
 * y la solución es sostenible más allá del periodo piloto.
 *
 * Consideración ética: el mensaje contiene únicamente el recordatorio de la
 * cita. No incluye diagnósticos ni datos nutricionales del menor.
 */

class Cita
{
    /**
     * Límites de carga diaria, tomados del levantamiento en el puesto:
     * de 1 a 8 atenciones en un día normal y hasta 15 en alta afluencia.
     * No impiden programar una cita; solo orientan al personal. Se
     * ajustan aquí si la prueba piloto muestra otros valores.
     */
    const CARGA_NORMAL = 8;
    const CARGA_ALTA   = 15;

    /** Motivos de cita: los usa el formulario y la validación del servidor. */
    const MOTIVOS = [
        'Control de crecimiento',
        'Vacunación',
        'Entrega de suplemento',
        'Seguimiento nutricional',
        'Otro',
    ];

    /** Nombres de los meses, para mostrar fechas en español. */
    const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                   'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** Programa una cita. */
    public static function crear(array $d, int $idUsuario): int
    {
        $db = Database::obtener();
        $sql = "INSERT INTO cita
                (id_nino, id_usuario, fecha_programada, estado, motivo, observaciones)
                VALUES (:id_nino, :id_usuario, :fecha, 'programada', :motivo, :obs)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_nino'    => $d['id_nino'],
            ':id_usuario' => $idUsuario,
            ':fecha'      => $d['fecha_programada'],
            ':motivo'     => $d['motivo'] ?: null,
            ':obs'        => $d['observaciones'] ?: null,
        ]);
        $id = (int)$db->lastInsertId();

        Bitacora::registrar($idUsuario, 'crear', 'cita', $id,
            "Cita programada para el niño id {$d['id_nino']} el {$d['fecha_programada']}");
        return $id;
    }

    /** Cambia el estado de una cita (cumplida, inasistencia, reprogramada). */
    public static function cambiarEstado(int $idCita, string $estado, int $idUsuario): void
    {
        $db = Database::obtener();
        $fechaAsistencia = ($estado === 'cumplida') ? date('Y-m-d') : null;
        $stmt = $db->prepare(
            "UPDATE cita SET estado = :estado, fecha_asistencia = :fa WHERE id_cita = :id"
        );
        $stmt->execute([':estado' => $estado, ':fa' => $fechaAsistencia, ':id' => $idCita]);

        Bitacora::registrar($idUsuario, 'editar', 'cita', $idCita,
            "Cita marcada como {$estado}");
    }

    /** Marca que ya se envió el aviso de una cita. */
    public static function marcarAvisoEnviado(int $idCita, int $idUsuario): void
    {
        $db = Database::obtener();
        $stmt = $db->prepare("UPDATE cita SET aviso_enviado = TRUE WHERE id_cita = :id");
        $stmt->execute([':id' => $idCita]);
        Bitacora::registrar($idUsuario, 'editar', 'cita', $idCita, 'Aviso de cita enviado');
    }

    /** Citas de un niño. */
    public static function listarPorNino(int $idNino): array
    {
        $db = Database::obtener();
        $sql = "SELECT c.*, u.nombre_completo AS programada_por
                FROM cita c
                LEFT JOIN usuario u ON u.id_usuario = c.id_usuario
                WHERE c.id_nino = :id
                ORDER BY c.fecha_programada DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idNino]);
        return $stmt->fetchAll();
    }

    /**
     * Próximas citas dentro de los siguientes N días (por defecto 7),
     * con los datos necesarios para generar el aviso.
     */
    public static function proximas(int $dias = 7, array $filtros = []): array
    {
        $db = Database::obtener();

        $where = [
            "c.estado = 'programada'",
            "n.estado = 'activo'",
            "c.fecha_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)",
        ];
        $params = [':dias' => $dias];

        if (!empty($filtros['id_sector'])) {
            $where[] = "n.id_sector = :id_sector";
            $params[':id_sector'] = $filtros['id_sector'];
        }

        $sql = "SELECT c.id_cita, c.fecha_programada, c.motivo, c.aviso_enviado,
                       n.id_nino, n.nombres, n.apellidos, n.telefono, n.nombre_madre,
                       s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre,
                       DATEDIFF(c.fecha_programada, CURDATE()) AS dias_faltantes
                FROM cita c
                JOIN nino n ON n.id_nino = c.id_nino
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.fecha_programada, n.apellidos";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Citas vencidas sin marcar (para dar seguimiento a inasistencias). */
    public static function vencidas(): array
    {
        $db = Database::obtener();
        $sql = "SELECT c.id_cita, c.fecha_programada, c.motivo,
                       n.id_nino, n.nombres, n.apellidos, n.telefono,
                       s.letra AS sector_letra, t.numero AS territorio_numero
                FROM cita c
                JOIN nino n ON n.id_nino = c.id_nino
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                WHERE c.estado = 'programada'
                  AND c.fecha_programada < CURDATE()
                  AND n.estado = 'activo'
                ORDER BY c.fecha_programada";
        return $db->query($sql)->fetchAll();
    }

    /**
     * Cantidad de citas por día entre dos fechas, para el calendario y para
     * el aviso al programar. Devuelve un arreglo fecha => cantidad.
     * No cuenta las reprogramadas, porque esas ya se movieron a otro día.
     * Si no se indica la fecha final, cuenta todas desde la inicial.
     */
    public static function conteoPorDia(string $desde, ?string $hasta = null): array
    {
        $db = Database::obtener();
        $sql = "SELECT fecha_programada, COUNT(*) AS cantidad
                FROM cita
                WHERE estado <> 'reprogramada'
                  AND fecha_programada >= :desde";
        $params = [':desde' => $desde];
        if ($hasta !== null) {
            $sql .= " AND fecha_programada <= :hasta";
            $params[':hasta'] = $hasta;
        }
        $sql .= " GROUP BY fecha_programada";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $conteo = [];
        foreach ($stmt->fetchAll() as $fila) {
            $conteo[$fila['fecha_programada']] = (int)$fila['cantidad'];
        }
        return $conteo;
    }

    /** Citas de un día, con los datos necesarios para el aviso por WhatsApp. */
    public static function delDia(string $fecha): array
    {
        $db = Database::obtener();
        $sql = "SELECT c.id_cita, c.fecha_programada, c.motivo, c.estado, c.aviso_enviado,
                       n.id_nino, n.nombres, n.apellidos, n.telefono,
                       s.letra AS sector_letra, t.numero AS territorio_numero,
                       p.nombre AS paraje_nombre
                FROM cita c
                JOIN nino n ON n.id_nino = c.id_nino
                JOIN sector s ON s.id_sector = n.id_sector
                JOIN territorio t ON t.id_territorio = s.id_territorio
                LEFT JOIN paraje p ON p.id_paraje = n.id_paraje
                WHERE c.fecha_programada = :fecha
                  AND c.estado <> 'reprogramada'
                ORDER BY n.apellidos, n.nombres";
        $stmt = $db->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /** Nivel de carga de un día según la cantidad de citas: normal, alta o excedida. */
    public static function nivelCarga(int $cantidad): string
    {
        if ($cantidad > self::CARGA_ALTA)   return 'excedida';
        if ($cantidad > self::CARGA_NORMAL) return 'alta';
        return 'normal';
    }

    /** Clase CSS de la etiqueta de cada estado de cita. */
    public static function claseEstado(string $estado): string
    {
        return [
            'programada'   => 'etiqueta-aviso',
            'cumplida'     => 'etiqueta-activo',
            'inasistencia' => 'nivel-riesgo',
            'reprogramada' => 'etiqueta-egresado',
        ][$estado] ?? 'etiqueta-egresado';
    }

    /**
     * Construye el enlace de WhatsApp con el mensaje ya redactado.
     * Usa el formato wa.me, gratuito, que abre la aplicación sin costo alguno.
     * Devuelve cadena vacía si el niño no tiene teléfono registrado.
     */
    public static function enlaceWhatsApp(array $cita): string
    {
        $telefono = preg_replace('/\D/', '', $cita['telefono'] ?? '');
        if (strlen($telefono) !== 8) {
            return ''; // Sin teléfono válido de Guatemala.
        }

        $numero = '502' . $telefono; // Código de país de Guatemala.

        $nombreNino = trim(($cita['nombres'] ?? '') . ' ' . ($cita['apellidos'] ?? ''));
        $fecha = self::fechaLegible($cita['fecha_programada']);

        $mensaje = "Buenos días. Le saludamos del Puesto de Salud de Chivarreto. "
                 . "Le recordamos la cita de control de {$nombreNino} "
                 . "programada para el {$fecha}. "
                 . "Le agradecemos presentarse con el carnet del niño. ¡Gracias!";

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
    }

    /** Convierte una fecha a texto legible en español. */
    public static function fechaLegible(string $fecha): string
    {
        $d = new DateTime($fecha);
        return $d->format('j') . ' de ' . self::MESES[(int)$d->format('n')] . ' de ' . $d->format('Y');
    }

    /** Nombre del mes y año, por ejemplo "Octubre 2026", para el calendario. */
    public static function mesLegible(DateTime $mes): string
    {
        return ucfirst(self::MESES[(int)$mes->format('n')]) . ' ' . $mes->format('Y');
    }
}
