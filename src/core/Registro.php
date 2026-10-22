<?php

/**
 * Anular y corregir registros (mediciones, vacunas, suplementos y citas).
 *
 * Nada se borra: lo anulado queda en la base, tachado en el historial, con el
 * motivo, quién y cuándo, y deja de contar en cálculos, alertas y reportes.
 * Es la misma lógica del puesto en papel (línea roja en el carnet y un
 * "conocimiento" en el cuaderno SIGSA 5a) y de SIGSA Web: durante el mes de la
 * atención basta el motivo; si la atención es de un mes anterior, además se
 * pide la referencia del conocimiento. Lo pidió el personal en la visita del 16
 * de octubre de 2026.
 *
 * La medición no se corrige: de ella dependen la puntuación Z y las alertas,
 * así que se anula y se registra de nuevo.
 */
class Registro
{
    const MOTIVO_MAX = 200;
    const REFERENCIA_MAX = 60;

    /** Tipos de registro: tabla, llave, fecha de la atención y nombre en pantalla. */
    const TIPOS = [
        'medicion'   => ['tabla' => 'medicion', 'id' => 'id_medicion', 'fecha' => 'fecha_toma',
                         'nombre' => 'medición', 'articulo' => 'la medición'],
        'vacuna'     => ['tabla' => 'vacuna_aplicada', 'id' => 'id_vacuna_aplicada', 'fecha' => 'fecha_aplicacion',
                         'nombre' => 'vacuna', 'articulo' => 'la vacuna'],
        'suplemento' => ['tabla' => 'suplementacion', 'id' => 'id_suplementacion', 'fecha' => 'fecha_entrega',
                         'nombre' => 'entrega de suplemento', 'articulo' => 'la entrega del suplemento'],
        'cita'       => ['tabla' => 'cita', 'id' => 'id_cita', 'fecha' => 'fecha_programada',
                         'nombre' => 'cita', 'articulo' => 'la cita'],
    ];

    /**
     * Campos que se pueden corregir de cada tipo. Para cambiar la vacuna, la
     * dosis o el suplemento se anula el registro y se registra el correcto.
     * Fechas: 'nacimiento', 'hoy' o 'un_anio' (un año adelante).
     */
    const CORREGIBLES = [
        'vacuna' => [
            'fecha_aplicacion' => ['etiqueta' => 'Fecha de aplicación', 'tipo' => 'fecha', 'desde' => 'nacimiento', 'hasta' => 'hoy'],
            'lote'             => ['etiqueta' => 'Lote', 'tipo' => 'texto', 'maximo' => 50],
            'lugar_vacunacion' => ['etiqueta' => 'Lugar de vacunación', 'tipo' => 'lista', 'opciones' => Vacuna::LUGARES],
        ],
        'suplemento' => [
            'fecha_entrega' => ['etiqueta' => 'Fecha de entrega', 'tipo' => 'fecha', 'desde' => 'nacimiento', 'hasta' => 'hoy'],
            'cantidad'      => ['etiqueta' => 'Cantidad entregada', 'tipo' => 'texto', 'maximo' => 50],
        ],
        'cita' => [
            'fecha_programada' => ['etiqueta' => 'Fecha de la cita', 'tipo' => 'fecha', 'desde' => 'nacimiento', 'hasta' => 'un_anio'],
            'motivo'           => ['etiqueta' => 'Motivo', 'tipo' => 'lista', 'opciones' => Cita::MOTIVOS],
            'observaciones'    => ['etiqueta' => 'Observaciones', 'tipo' => 'texto', 'maximo' => 300],
        ],
    ];

    /** Indica si el tipo existe y, si se pide, si se puede corregir. */
    public static function tipoValido(string $tipo, bool $paraCorregir = false): bool
    {
        return isset(self::TIPOS[$tipo]) && (!$paraCorregir || isset(self::CORREGIBLES[$tipo]));
    }

    /** El registro con el nombre y la fecha de nacimiento del niño, o null. */
    public static function obtener(string $tipo, int $id): ?array
    {
        if (!self::tipoValido($tipo)) {
            return null;
        }
        $t = self::TIPOS[$tipo];
        $db = Database::obtener();
        $stmt = $db->prepare("SELECT r.*, n.nombres, n.apellidos, n.fecha_nacimiento, n.id_nino AS nino_id
                              FROM {$t['tabla']} r
                              JOIN nino n ON n.id_nino = r.id_nino
                              WHERE r.{$t['id']} = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** Fecha de la atención del registro (toma, aplicación, entrega o cita). */
    public static function fechaAtencion(string $tipo, array $registro): string
    {
        return (string) $registro[self::TIPOS[$tipo]['fecha']];
    }

    /**
     * La atención es de un mes anterior al actual: como en SIGSA Web, ya no
     * basta el motivo, hace falta la referencia del conocimiento.
     */
    public static function fueraDelMes(string $fechaAtencion): bool
    {
        return substr($fechaAtencion, 0, 7) < date('Y-m');
    }

    /** Valida el motivo y, si la atención es de un mes anterior, la referencia. */
    public static function validarMotivo(string $motivo, string $referencia, bool $fueraDelMes): array
    {
        $errores = [Validacion::texto($motivo, 'Motivo', self::MOTIVO_MAX, true)];
        if ($motivo !== '' && mb_strlen($motivo) < 5) {
            $errores[] = 'Motivo: escriba con más detalle por qué (al menos 5 letras).';
        }
        $errores[] = Validacion::texto($referencia, 'Referencia del conocimiento', self::REFERENCIA_MAX, $fueraDelMes);
        return array_values(array_filter($errores));
    }

    /**
     * Valida los campos corregidos. Devuelve [datos limpios, errores].
     */
    public static function validarCorreccion(string $tipo, array $entrada, string $fechaNacimiento): array
    {
        $datos = [];
        $errores = [];
        foreach (self::CORREGIBLES[$tipo] as $campo => $def) {
            $valor = trim((string) ($entrada[$campo] ?? ''));
            $datos[$campo] = $valor;
            if ($def['tipo'] === 'fecha') {
                $limite = ['nacimiento' => $fechaNacimiento, 'hoy' => date('Y-m-d'),
                           'un_anio' => date('Y-m-d', strtotime('+1 year'))];
                $errores[] = Validacion::fecha($valor, $def['etiqueta'], $limite[$def['desde']], $limite[$def['hasta']],
                    'anterior al nacimiento', $def['hasta'] === 'hoy' ? 'posterior a hoy' : 'de más de un año adelante');
            } elseif ($def['tipo'] === 'lista') {
                $permitidos = array_is_list($def['opciones']) ? $def['opciones'] : array_keys($def['opciones']);
                $errores[] = Validacion::enLista($valor, $permitidos, $def['etiqueta'] . ': debe elegir una opción de la lista.');
            } else {
                $errores[] = Validacion::texto($valor, $def['etiqueta'], $def['maximo']);
            }
        }
        return [$datos, array_values(array_filter($errores))];
    }

    /**
     * Anula un registro. Si es una medición, vuelve a generar las alertas de
     * las mediciones posteriores del niño, para que su evolución ("mejoró",
     * "empeoró") se compare con el control vigente anterior.
     */
    public static function anular(string $tipo, int $id, string $motivo, string $referencia, int $idUsuario): void
    {
        $t = self::TIPOS[$tipo];
        $registro = self::obtener($tipo, $id);
        $db = Database::obtener();
        $db->prepare("UPDATE {$t['tabla']}
                      SET anulado = 1, motivo_anulacion = :motivo, referencia_conocimiento = :referencia,
                          anulado_por = :usuario, fecha_anulacion = NOW()
                      WHERE {$t['id']} = :id AND anulado = 0")
           ->execute([':motivo' => $motivo, ':referencia' => $referencia !== '' ? $referencia : null,
                      ':usuario' => $idUsuario, ':id' => $id]);

        Bitacora::registrar($idUsuario, 'anular', $t['tabla'], $id,
            'Anulación de ' . $t['articulo'] . ' del ' . Formato::fechaTexto(self::fechaAtencion($tipo, $registro))
            . '. Motivo: ' . $motivo . ($referencia !== '' ? '. Conocimiento: ' . $referencia : ''));

        if ($tipo === 'medicion') {
            Alerta::regenerarPosteriores((int) $registro['id_nino'], $registro['fecha_toma'], $id);
        }
    }

    /**
     * Corrige los campos de una vacuna, un suplemento o una cita. Deja en la
     * bitácora el valor anterior, el nuevo, el motivo y la referencia.
     */
    public static function corregir(string $tipo, int $id, array $datos, string $motivo, string $referencia, int $idUsuario): void
    {
        $t = self::TIPOS[$tipo];
        $antes = self::obtener($tipo, $id);
        $asignaciones = [];
        $parametros = [':id' => $id];
        $cambios = [];
        foreach (self::CORREGIBLES[$tipo] as $campo => $def) {
            $nuevo = $datos[$campo];
            $asignaciones[] = "$campo = :$campo";
            $parametros[":$campo"] = $nuevo !== '' ? $nuevo : null;
            $anterior = (string) ($antes[$campo] ?? '');
            if ($anterior !== $nuevo) {
                $mostrar = fn($v) => $v === '' ? 'vacío'
                    : ($def['tipo'] === 'lista' && !array_is_list($def['opciones']) ? ($def['opciones'][$v] ?? $v) : $v);
                $cambios[] = mb_strtolower($def['etiqueta']) . ' de "' . $mostrar($anterior) . '" a "' . $mostrar($nuevo) . '"';
            }
        }
        $db = Database::obtener();
        $db->prepare("UPDATE {$t['tabla']} SET " . implode(', ', $asignaciones) . " WHERE {$t['id']} = :id AND anulado = 0")
           ->execute($parametros);

        Bitacora::registrar($idUsuario, 'corregir', $t['tabla'], $id,
            'Corrección de ' . $t['articulo'] . ': ' . ($cambios ? implode('; ', $cambios) : 'sin cambios')
            . '. Motivo: ' . $motivo . ($referencia !== '' ? '. Conocimiento: ' . $referencia : ''));
    }
}
