<?php
/**
 * Envío de correo por SMTP, sin librerías externas.
 *
 * Igual que el generador de Excel (LibroExcel), se escribió el protocolo a
 * mano en lugar de instalar una librería: son pocos pasos (saludo, usuario y
 * contraseña, remitente, destinatario, mensaje), corre en cualquier hosting
 * y deja ver la autoría. La conexión va cifrada: puerto 465 con SSL desde el
 * inicio, o 587 con STARTTLS.
 *
 * Los datos del buzón van en config/config.php (SMTP_HOST, SMTP_PUERTO,
 * SMTP_USUARIO, SMTP_CONTRASENA, SMTP_REMITENTE_NOMBRE), para cambiar de
 * servicio sin tocar el código. Con SMTP_HOST vacío o sin definir, el sistema
 * funciona sin correo: no ofrece recuperar la contraseña ni pide código.
 */

class Correo
{
    /** Segundos de espera por cada respuesta del servidor de correo. */
    const ESPERA_SEGUNDOS = 20;

    /** Último error, para la consola de prueba (scripts/probar_correo.php). */
    public static string $ultimoError = '';

    /** Indica si el envío de correo está configurado en este ambiente. */
    public static function activo(): bool
    {
        return defined('SMTP_HOST') && SMTP_HOST !== ''
            && defined('SMTP_USUARIO') && SMTP_USUARIO !== ''
            && defined('SMTP_CONTRASENA') && SMTP_CONTRASENA !== '';
    }

    /**
     * Envía un mensaje de texto. Devuelve true si el servidor lo aceptó. Si
     * falla, el detalle queda en el registro de errores (nunca en pantalla).
     */
    public static function enviar(string $para, string $asunto, string $texto): bool
    {
        self::$ultimoError = '';
        if (!self::activo()) {
            self::$ultimoError = 'El correo no está configurado (SMTP_HOST en config.php).';
            return false;
        }
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            self::$ultimoError = 'La dirección de destino no es válida.';
            return false;
        }

        try {
            self::conversar($para, self::armarMensaje($para, $asunto, $texto));
            return true;
        } catch (RuntimeException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('Correo no enviado a ' . $para . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Encabezados y cuerpo del mensaje. El asunto, el nombre y el cuerpo van
     * en base64 (UTF-8), así las tildes llegan bien a cualquier programa de
     * correo y ninguna línea empieza con un punto (que en SMTP marca el final).
     */
    private static function armarMensaje(string $para, string $asunto, string $texto): string
    {
        $nombre = defined('SMTP_REMITENTE_NOMBRE') ? SMTP_REMITENTE_NOMBRE : 'Carnet Digital';
        $dominio = substr(strrchr(SMTP_USUARIO, '@') ?: '@localhost', 1);
        $codificar = fn(string $t) => '=?UTF-8?B?' . base64_encode($t) . '?=';

        $encabezados = [
            'Date: ' . date('r'),
            'From: ' . $codificar($nombre) . ' <' . SMTP_USUARIO . '>',
            'To: <' . $para . '>',
            'Subject: ' . $codificar($asunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $cuerpo = str_replace(["\r\n", "\r"], "\n", $texto);
        return implode("\r\n", $encabezados) . "\r\n\r\n"
            . chunk_split(base64_encode(str_replace("\n", "\r\n", $cuerpo)), 76, "\r\n");
    }

    /** Conversación con el servidor de correo, paso a paso. */
    private static function conversar(string $para, string $mensaje): void
    {
        $puerto = defined('SMTP_PUERTO') ? (int)SMTP_PUERTO : 465;
        $esLocal = in_array(SMTP_HOST, ['localhost', '127.0.0.1'], true);
        $direccion = ($puerto === 465 ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . $puerto;

        $conexion = @stream_socket_client($direccion, $numero, $error, self::ESPERA_SEGUNDOS);
        if (!$conexion) {
            throw new RuntimeException("No se pudo conectar con $direccion: $error");
        }
        stream_set_timeout($conexion, self::ESPERA_SEGUNDOS);

        try {
            self::esperar($conexion, 220);
            $nombreEquipo = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
            $capacidades = self::orden($conexion, "EHLO $nombreEquipo", 250);

            // En el puerto 587 la conexión empieza sin cifrar y se cifra con
            // STARTTLS antes de enviar el usuario y la contraseña.
            if ($puerto !== 465) {
                if (stripos($capacidades, 'STARTTLS') !== false) {
                    self::orden($conexion, 'STARTTLS', 220);
                    if (!stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new RuntimeException('No se pudo cifrar la conexión (STARTTLS).');
                    }
                    self::orden($conexion, "EHLO $nombreEquipo", 250);
                } elseif (!$esLocal) {
                    throw new RuntimeException('El servidor no ofrece una conexión cifrada.');
                }
            }

            self::orden($conexion, 'AUTH LOGIN', 334);
            self::orden($conexion, base64_encode(SMTP_USUARIO), 334);
            self::orden($conexion, base64_encode(defined('SMTP_CONTRASENA') ? SMTP_CONTRASENA : ''), 235,
                'El servidor no aceptó el usuario o la contraseña del buzón');
            self::orden($conexion, 'MAIL FROM:<' . SMTP_USUARIO . '>', 250);
            self::orden($conexion, "RCPT TO:<$para>", [250, 251]);
            self::orden($conexion, 'DATA', 354);
            self::orden($conexion, $mensaje . "\r\n.", 250);
            fwrite($conexion, "QUIT\r\n");
        } finally {
            fclose($conexion);
        }
    }

    /**
     * Envía una orden y comprueba el código de la respuesta. Devuelve la
     * respuesta completa (EHLO la usa para saber si hay STARTTLS).
     */
    private static function orden($conexion, string $linea, int|array $esperado, string $siFalla = ''): string
    {
        fwrite($conexion, $linea . "\r\n");
        return self::esperar($conexion, $esperado, $siFalla);
    }

    /**
     * Lee una respuesta del servidor. Puede tener varias líneas ("250-..."):
     * termina en la que lleva un espacio después del código ("250 ...").
     */
    private static function esperar($conexion, int|array $esperado, string $siFalla = ''): string
    {
        $respuesta = '';
        while (($linea = fgets($conexion, 1024)) !== false) {
            $respuesta .= $linea;
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                break;
            }
        }
        $codigo = (int)substr($respuesta, 0, 3);
        if (!in_array($codigo, (array)$esperado, true)) {
            $detalle = trim($respuesta) !== '' ? trim($respuesta) : 'sin respuesta (tiempo agotado)';
            throw new RuntimeException(($siFalla !== '' ? $siFalla . ': ' : 'Respuesta inesperada: ') . $detalle);
        }
        return $respuesta;
    }
}
