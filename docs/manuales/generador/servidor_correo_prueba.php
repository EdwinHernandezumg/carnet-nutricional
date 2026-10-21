<?php
// Servidor SMTP de prueba, solo para las capturas del manual en la computadora:
// acepta todo y guarda cada mensaje decodificado en buzon.jsonl (una línea JSON
// por mensaje), sin enviar correos de verdad. capturas.js lee de ahí el código
// de ingreso. Se detiene con Ctrl+C.
//
//   php servidor_correo_prueba.php
$buzon = __DIR__ . '/buzon.jsonl';
$servidor = stream_socket_server('tcp://127.0.0.1:2525', $numero, $error);
if (!$servidor) { fwrite(STDERR, "No se pudo abrir el puerto 2525: $error\n"); exit(1); }
echo "Escuchando en 127.0.0.1:2525, buzón: $buzon\n";

while ($conexion = stream_socket_accept($servidor, -1)) {
    $responder = fn($linea) => fwrite($conexion, $linea . "\r\n");
    $responder('220 prueba ESMTP');
    $para = ''; $enDatos = false; $mensaje = ''; $paso = '';

    while (($linea = fgets($conexion)) !== false) {
        if ($enDatos) {
            if (rtrim($linea, "\r\n") === '.') {
                $enDatos = false;
                [$encabezados, $cuerpo] = explode("\r\n\r\n", $mensaje, 2) + [1 => ''];
                preg_match('/^Subject: =\?UTF-8\?B\?([^?]+)\?=/m', $encabezados, $asunto);
                file_put_contents($buzon, json_encode([
                    'para' => $para,
                    'asunto' => base64_decode($asunto[1] ?? ''),
                    'cuerpo' => base64_decode(str_replace("\r\n", '', $cuerpo)),
                    'encabezados' => $encabezados,
                ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                $responder('250 aceptado');
                $mensaje = '';
            } else {
                $mensaje .= $linea;
            }
            continue;
        }

        $orden = rtrim($linea, "\r\n");
        // AUTH LOGIN: acepta cualquier usuario y contraseña.
        if ($paso === 'usuario') { $paso = 'clave'; $responder('334 UGFzc3dvcmQ6'); continue; }
        if ($paso === 'clave') { $paso = ''; $responder('235 ok'); continue; }

        if (stripos($orden, 'EHLO') === 0) { fwrite($conexion, "250-prueba\r\n250 AUTH LOGIN\r\n"); }
        elseif ($orden === 'AUTH LOGIN') { $paso = 'usuario'; $responder('334 VXNlcm5hbWU6'); }
        elseif (stripos($orden, 'MAIL FROM') === 0) { $responder('250 ok'); }
        elseif (stripos($orden, 'RCPT TO') === 0) {
            preg_match('/<([^>]+)>/', $orden, $correo);
            $para = $correo[1] ?? '';
            $responder('250 ok');
        }
        elseif ($orden === 'DATA') { $enDatos = true; $responder('354 adelante'); }
        elseif ($orden === 'QUIT') { $responder('221 adios'); break; }
        else { $responder('500 ?'); }
    }
    fclose($conexion);
}
