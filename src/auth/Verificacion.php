<?php
/**
 * Enlaces de recuperación de contraseña, códigos de ingreso y dispositivos
 * de confianza (RF-13 de la tesis).
 *
 * - Recuperación: un enlace al correo de la persona, de un solo uso, que
 *   vence en 30 minutos.
 * - Código de ingreso: 6 números al correo cuando se entra desde un teléfono
 *   o una computadora nuevos. Con el cierre de sesión a los 20 minutos, pedir
 *   un código en cada ingreso obligaría a las enfermeras a abrir el correo
 *   varias veces al día; por eso el dispositivo queda de confianza 30 días. Al
 *   administrador se le pide siempre, porque su cuenta puede crear usuarios.
 *
 * En la base se guarda solo el resumen SHA-256 de cada enlace, código y
 * dispositivo: quien llegara a leer la base no podría usarlos.
 */

class Verificacion
{
    const MINUTOS_ENLACE = 30;
    const MINUTOS_CODIGO = 10;
    const INTENTOS_CODIGO = 5;
    const DIAS_DISPOSITIVO = 30;

    /** Límites contra el abuso: enlaces y códigos por hora, y espera entre códigos. */
    const ENLACES_POR_HORA = 3;
    const CODIGOS_POR_HORA = 6;
    const SEGUNDOS_ENTRE_CODIGOS = 60;

    /** Nombre de la cookie que identifica al dispositivo de confianza. */
    const COOKIE_DISPOSITIVO = 'dispositivo_confianza';

    private static function resumen(string $valor): string
    {
        return hash('sha256', $valor);
    }

    /** Cuántos enlaces o códigos se crearon para el usuario en la última hora. */
    private static function creadosUltimaHora(int $idUsuario, string $tipo): int
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM codigo_acceso
             WHERE id_usuario = :id AND tipo = :tipo AND creado > NOW() - INTERVAL 1 HOUR"
        );
        $stmt->execute([':id' => $idUsuario, ':tipo' => $tipo]);
        return (int)$stmt->fetchColumn();
    }

    /** Guarda un enlace o código nuevo y deja sin efecto los anteriores del mismo tipo. */
    private static function guardar(int $idUsuario, string $tipo, string $secreto, int $minutos): void
    {
        $db = Database::obtener();
        $db->prepare(
            "UPDATE codigo_acceso SET usado = NOW()
             WHERE id_usuario = :id AND tipo = :tipo AND usado IS NULL"
        )->execute([':id' => $idUsuario, ':tipo' => $tipo]);

        $db->prepare(
            "INSERT INTO codigo_acceso (id_usuario, tipo, codigo_hash, vence)
             VALUES (:id, :tipo, :hash, NOW() + INTERVAL $minutos MINUTE)"
        )->execute([':id' => $idUsuario, ':tipo' => $tipo, ':hash' => self::resumen($secreto)]);
    }

    // ---------- Recuperación de contraseña ----------

    /**
     * Crea el enlace de recuperación y devuelve su clave (va en la dirección
     * del enlace), o null si ya se pidieron demasiados en la última hora.
     */
    public static function crearEnlace(int $idUsuario): ?string
    {
        if (self::creadosUltimaHora($idUsuario, 'recuperacion') >= self::ENLACES_POR_HORA) {
            return null;
        }
        $clave = bin2hex(random_bytes(32));
        self::guardar($idUsuario, 'recuperacion', $clave, self::MINUTOS_ENLACE);
        return $clave;
    }

    /**
     * Enlace vigente (sin usar y sin vencer) de un usuario activo, o null.
     * Devuelve id_codigo, id_usuario, usuario y nombre_completo.
     */
    public static function enlaceVigente(string $clave): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $clave)) {
            return null;
        }
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT c.id_codigo, c.id_usuario, u.usuario, u.nombre_completo
             FROM codigo_acceso c JOIN usuario u ON u.id_usuario = c.id_usuario
             WHERE c.codigo_hash = :hash AND c.tipo = 'recuperacion'
               AND c.usado IS NULL AND c.vence > NOW() AND u.activo = 1
             LIMIT 1"
        );
        $stmt->execute([':hash' => self::resumen($clave)]);
        return $stmt->fetch() ?: null;
    }

    /** Marca el enlace o el código como usado: ya no sirve otra vez. */
    public static function marcarUsado(int $idCodigo): void
    {
        $db = Database::obtener();
        $db->prepare("UPDATE codigo_acceso SET usado = NOW() WHERE id_codigo = :id")
           ->execute([':id' => $idCodigo]);
    }

    // ---------- Código de ingreso ----------

    /**
     * Crea un código de 6 números y lo devuelve, o null si hay que esperar
     * (menos de un minuto desde el anterior o demasiados en la última hora).
     */
    public static function crearCodigo(int $idUsuario): ?string
    {
        if (self::segundosParaReenviar($idUsuario) > 0
            || self::creadosUltimaHora($idUsuario, 'ingreso') >= self::CODIGOS_POR_HORA) {
            return null;
        }
        $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        self::guardar($idUsuario, 'ingreso', $codigo, self::MINUTOS_CODIGO);
        return $codigo;
    }

    /**
     * Segundos que faltan para poder pedir otro código (0 si ya se puede).
     * Solo cuenta un código que todavía sirve: si ya se usó (por ejemplo,
     * quien sale y vuelve a entrar enseguida), se puede pedir otro de una vez.
     */
    public static function segundosParaReenviar(int $idUsuario): int
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, MAX(creado), NOW()) FROM codigo_acceso
             WHERE id_usuario = :id AND tipo = 'ingreso' AND usado IS NULL"
        );
        $stmt->execute([':id' => $idUsuario]);
        $transcurridos = $stmt->fetchColumn();
        return $transcurridos === null ? 0 : max(0, self::SEGUNDOS_ENTRE_CODIGOS - (int)$transcurridos);
    }

    /**
     * Revisa el código escrito. Devuelve 'correcto', 'incorrecto', 'vencido'
     * o 'bloqueado' (demasiados intentos: hay que pedir otro). Junto con el
     * resultado, los intentos que quedan.
     */
    public static function verificarCodigo(int $idUsuario, string $codigo): array
    {
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT id_codigo, codigo_hash, intentos, vence > NOW() AS vigente
             FROM codigo_acceso
             WHERE id_usuario = :id AND tipo = 'ingreso' AND usado IS NULL
             ORDER BY id_codigo DESC LIMIT 1"
        );
        $stmt->execute([':id' => $idUsuario]);
        $vigente = $stmt->fetch();

        if (!$vigente) {
            return ['bloqueado', 0];
        }
        if (!$vigente['vigente']) {
            return ['vencido', 0];
        }
        if (hash_equals($vigente['codigo_hash'], self::resumen($codigo))) {
            self::marcarUsado((int)$vigente['id_codigo']);
            return ['correcto', 0];
        }

        $intentos = (int)$vigente['intentos'] + 1;
        $db->prepare(
            "UPDATE codigo_acceso SET intentos = :intentos,
                    usado = IF(:intentos2 >= :maximo, NOW(), NULL)
             WHERE id_codigo = :id"
        )->execute([':intentos' => $intentos, ':intentos2' => $intentos,
                    ':maximo' => self::INTENTOS_CODIGO, ':id' => $vigente['id_codigo']]);
        $quedan = self::INTENTOS_CODIGO - $intentos;
        return [$quedan > 0 ? 'incorrecto' : 'bloqueado', $quedan];
    }

    // ---------- Dispositivos de confianza ----------

    /** Indica si este navegador ya es de confianza para el usuario. */
    public static function esDispositivoDeConfianza(int $idUsuario): bool
    {
        $token = $_COOKIE[self::COOKIE_DISPOSITIVO] ?? '';
        if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
            return false;
        }
        $db = Database::obtener();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM dispositivo_confianza
             WHERE id_usuario = :id AND token_hash = :hash AND vence > NOW()"
        );
        $stmt->execute([':id' => $idUsuario, ':hash' => self::resumen($token)]);
        return $stmt->fetchColumn() > 0;
    }

    /** Recuerda este navegador por 30 días para el usuario. */
    public static function recordarDispositivo(int $idUsuario): void
    {
        $token = bin2hex(random_bytes(32));
        $dias = self::DIAS_DISPOSITIVO;
        $db = Database::obtener();
        $db->prepare(
            "INSERT INTO dispositivo_confianza (id_usuario, token_hash, descripcion, vence)
             VALUES (:id, :hash, :descripcion, NOW() + INTERVAL $dias DAY)"
        )->execute([
            ':id'          => $idUsuario,
            ':hash'        => self::resumen($token),
            ':descripcion' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 150),
        ]);

        setcookie(self::COOKIE_DISPOSITIVO, $token, [
            'expires'  => time() + $dias * 86400,
            'path'     => parse_url(APP_URL, PHP_URL_PATH) ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    /**
     * Olvida todos los dispositivos del usuario: al recuperar la contraseña se
     * vuelve a pedir el código en todos, por si alguien más la conocía.
     */
    public static function olvidarDispositivos(int $idUsuario): void
    {
        $db = Database::obtener();
        $db->prepare("DELETE FROM dispositivo_confianza WHERE id_usuario = :id")
           ->execute([':id' => $idUsuario]);
    }

    /** Correo con parte oculta, para mostrar a dónde se envió: "we******@gmail.com". */
    public static function ocultarCorreo(string $correo): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $correo, 2), 2, '');
        $visible = mb_substr($nombre, 0, min(2, mb_strlen($nombre)));
        return $visible . str_repeat('*', max(3, mb_strlen($nombre) - 2)) . '@' . $dominio;
    }
}
