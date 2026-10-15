<?php
/**
 * Funciones comunes de los scripts de línea de comandos (instalar.php y
 * crear_administrador.php): preguntar datos, leer una contraseña, conectar a
 * la base y ejecutar un archivo SQL.
 */

/** Detiene el script si alguien intenta abrirlo desde el navegador. */
function soloConsola(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Este script solo se ejecuta desde la línea de comandos.');
    }
}

/** Valor de una opción "--nombre=valor" de la línea de comandos, o null. */
function opcion(string $nombre): ?string
{
    foreach ($GLOBALS['argv'] as $argumento) {
        if (str_starts_with($argumento, "--$nombre=")) {
            return substr($argumento, strlen("--$nombre="));
        }
    }
    return null;
}

/** Indica si se pasó la opción "--nombre" (sin valor). */
function tieneOpcion(string $nombre): bool
{
    return in_array("--$nombre", $GLOBALS['argv'], true);
}

/** Hace una pregunta y devuelve la respuesta (o el valor por omisión). */
function preguntar(string $texto, string $porOmision = ''): string
{
    echo $texto . ($porOmision !== '' ? " [$porOmision]" : '') . ': ';
    $respuesta = trim((string)fgets(STDIN));
    return $respuesta === '' ? $porOmision : $respuesta;
}

/**
 * Ejecuta el comando "stty" de Linux, que oculta o vuelve a mostrar lo que se
 * escribe en la consola. Los hosting deshabilitan algunas funciones que
 * ejecutan comandos (Hostinger deshabilita shell_exec), por eso prueba varias.
 * Devuelve false si ninguna está disponible.
 */
function ejecutarStty(string $opcion): bool
{
    $comando = 'stty ' . $opcion;
    if (function_exists('shell_exec')) {
        shell_exec($comando);
        return true;
    }
    if (function_exists('exec')) {
        exec($comando);
        return true;
    }
    if (function_exists('system')) {
        system($comando);
        return true;
    }
    if (function_exists('passthru')) {
        passthru($comando);
        return true;
    }
    return false;
}

/** Indica si se está escribiendo en una consola de Linux (el servidor). */
function esTerminalLinux(): bool
{
    return DIRECTORY_SEPARATOR === '/' && function_exists('stream_isatty') && stream_isatty(STDIN);
}

/**
 * Indica si conviene generar una contraseña temporal en lugar de pedirla:
 * cuando se pide con --temporal, o cuando el servidor no permite ocultar lo
 * que se escribe (Hostinger deshabilita las funciones que ejecutan comandos).
 * Así la contraseña definitiva nunca queda a la vista en la pantalla.
 */
function usarContrasenaTemporal(): bool
{
    if (tieneOpcion('temporal')) {
        return true;
    }
    // "stty echo" deja la consola como ya está: solo prueba si se puede ejecutar.
    return DIRECTORY_SEPARATOR === '/' && !(esTerminalLinux() && ejecutarStty('echo'));
}

/**
 * Lee una contraseña. En Linux (el servidor) no se muestra mientras se
 * escribe; en la consola de Windows no hay forma sencilla de ocultarla.
 */
function preguntarContrasena(string $texto): string
{
    $ocultar = esTerminalLinux() && ejecutarStty('-echo');
    echo $texto . ': ';
    $respuesta = rtrim((string)fgets(STDIN), "\r\n");
    if ($ocultar) {
        ejecutarStty('echo');
        echo "\n";
    }
    return $respuesta;
}

/**
 * Conexión a una base, con las mismas reglas que src/core/Database.php
 * (hora de Guatemala y modo estricto). Se recibe el nombre de la base para
 * poder probar la instalación en una base distinta a la de config.php.
 */
function conectar(string $base): PDO
{
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $base . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $db->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    $db->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,"
        . "NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $db;
}

/**
 * Ejecuta un archivo SQL sentencia por sentencia y devuelve cuántas ejecutó.
 * Cada sentencia termina con ";" al final de una línea (así los escribe
 * scripts/exportar_esquema.php); las líneas que empiezan con "--" son
 * comentarios.
 */
function ejecutarArchivoSql(PDO $db, string $archivo): int
{
    $sentencia = '';
    $cantidad = 0;
    foreach (file($archivo) as $linea) {
        $limpia = trim($linea);
        if ($limpia === '' || str_starts_with($limpia, '--')) {
            continue;
        }
        $sentencia .= $linea;
        if (str_ends_with($limpia, ';')) {
            $db->exec($sentencia);
            $sentencia = '';
            $cantidad++;
        }
    }
    return $cantidad;
}

/**
 * Pide usuario, nombre y contraseña del administrador, con las mismas reglas
 * que el formulario de Gestión de usuarios. Repite cada pregunta hasta que la
 * respuesta sea válida. Si usarContrasenaTemporal(), no pide la contraseña:
 * genera una temporal (en $datos['temporal']) que el sistema obliga a cambiar
 * en el primer ingreso.
 */
function pedirDatosAdministrador(bool $pedirUsuario = true, bool $pedirNombre = true): array
{
    $datos = [];
    if ($pedirUsuario) {
        do {
            $datos['usuario'] = preguntar('Usuario para ingresar (letras, números, punto, guion; de 3 a 50)', 'admin');
            $valido = preg_match('/^[A-Za-z0-9._-]{3,50}$/', $datos['usuario']) === 1;
            if (!$valido) {
                echo "  El usuario solo admite letras sin tilde, números, punto, guion y guion bajo.\n";
            }
        } while (!$valido);
    }

    while ($pedirNombre) {
        $datos['nombre'] = Formato::nombrePropio(preguntar('Nombre completo de la persona'));
        $error = Validacion::nombrePersona($datos['nombre'], 'Nombre completo', 150, true);
        if ($error === null) {
            break;
        }
        echo "  $error\n";
    }

    if (usarContrasenaTemporal()) {
        $datos['temporal'] = Contrasena::generarTemporal();
        $datos['hash'] = password_hash($datos['temporal'], PASSWORD_DEFAULT);
        return $datos;
    }

    while (true) {
        $contrasena = preguntarContrasena('Contraseña (mínimo ' . Contrasena::LARGO_MINIMO . ' caracteres, con letra y número)');
        $errores = Contrasena::validar($contrasena);
        if ($errores) {
            echo '  ' . implode("\n  ", $errores) . "\n";
            continue;
        }
        if (preguntarContrasena('Repita la contraseña') !== $contrasena) {
            echo "  Las contraseñas no coinciden. Intente de nuevo.\n";
            continue;
        }
        break;
    }
    $datos['temporal'] = null;
    $datos['hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
    return $datos;
}

/** Muestra la contraseña temporal del administrador, si se generó una. */
function mostrarContrasenaTemporal(array $datos): void
{
    if ($datos['temporal'] === null) {
        return;
    }
    echo "\nContraseña temporal: {$datos['temporal']}\n"
       . "Anótela. Al ingresar por primera vez, el sistema pedirá cambiarla por una propia.\n"
       . "Cuando la haya anotado, escriba clear para borrar la pantalla.\n";
}

/** Deja constancia en la bitácora de lo que hizo un script de consola. */
function registrarEnBitacora(PDO $db, int $idUsuario, string $accion, int $idRegistro, string $detalle): void
{
    $db->prepare("INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_registro_afectado, detalle, ip_origen)
                  VALUES (:id_usuario, :accion, 'usuario', :id_registro, :detalle, 'consola')")
       ->execute([':id_usuario' => $idUsuario, ':accion' => $accion, ':id_registro' => $idRegistro,
                  ':detalle' => mb_substr($detalle, 0, 300)]);
}
