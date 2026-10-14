<?php
/**
 * Crea una cuenta de administrador o recupera una existente.
 *
 *   php scripts/crear_administrador.php
 *
 * Es la salida de emergencia si el único administrador olvidó su contraseña
 * o su cuenta quedó desactivada: como solo se ejecuta desde la consola del
 * servidor, solo puede usarlo quien administra el hosting.
 *   - Si el usuario no existe, crea un administrador nuevo.
 *   - Si existe, le pone la contraseña nueva, lo reactiva y lo deja como
 *     administrador.
 * Todo queda en la bitácora.
 *
 * Si el hosting no tiene consola, se ejecuta en la computadora con:
 *   php scripts/crear_administrador.php --sql
 * y muestra la sentencia SQL para pegarla en phpMyAdmin. La sentencia lleva
 * la contraseña cifrada con password_hash, nunca la contraseña escrita.
 */

require __DIR__ . '/consola.php';
soloConsola();

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Formato.php';
require __DIR__ . '/../src/core/Validacion.php';
require __DIR__ . '/../src/core/Contrasena.php';

echo "\nCuenta de administrador del Carnet Digital\n\n";

// Sin conexión: solo genera el SQL para phpMyAdmin.
if (tieneOpcion('sql')) {
    $admin = pedirDatosAdministrador();
    $texto = fn(string $valor) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $valor) . "'";
    echo "\nPegue esta sentencia en la pestaña SQL de phpMyAdmin, con la base del sistema elegida:\n\n";
    echo "INSERT INTO usuario (id_rol, id_puesto, nombre_completo, usuario, contrasena_hash)\n";
    echo "SELECT r.id_rol, (SELECT MIN(id_puesto) FROM puesto_salud), {$texto($admin['nombre'])}, "
       . "{$texto($admin['usuario'])},\n       {$texto($admin['hash'])}\n";
    echo "FROM rol r WHERE r.nombre = 'administrador';\n\n";
    exit(0);
}

$base = opcion('base') ?? DB_NAME;
try {
    $db = conectar($base);
} catch (PDOException $e) {
    exit("No se pudo conectar a la base '$base': " . $e->getMessage() . "\n");
}

$usuario = '';
while (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario) !== 1) {
    $usuario = preguntar('Usuario del administrador (nuevo o existente)', 'admin');
}

$stmt = $db->prepare('SELECT id_usuario, nombre_completo, activo FROM usuario WHERE usuario = :usuario');
$stmt->execute([':usuario' => $usuario]);
$existente = $stmt->fetch();
$idRol = (int)$db->query("SELECT id_rol FROM rol WHERE nombre = 'administrador'")->fetchColumn();

if ($existente) {
    echo "\nEl usuario \"$usuario\" ya existe: {$existente['nombre_completo']}"
       . ($existente['activo'] ? '' : ' (desactivado)') . ".\n";
    if (strtolower(preguntar('¿Ponerle una contraseña nueva, reactivarlo y dejarlo como administrador? (s/n)', 'n')) !== 's') {
        exit("No se cambió nada.\n");
    }
    $datos = pedirDatosAdministrador(false, false);
    $db->prepare("UPDATE usuario SET contrasena_hash = :hash, id_rol = :rol, activo = 1,
                         fecha_baja = NULL, debe_cambiar_pass = 0
                  WHERE id_usuario = :id")
       ->execute([':hash' => $datos['hash'], ':rol' => $idRol, ':id' => $existente['id_usuario']]);
    registrarEnBitacora($db, (int)$existente['id_usuario'], 'restablecer_pass', (int)$existente['id_usuario'],
        "Recuperación de emergencia desde la consola del administrador \"$usuario\"");
    echo "\nListo: \"$usuario\" ya puede ingresar con la contraseña nueva.\n";
} else {
    echo "\nSe creará el administrador \"$usuario\".\n";
    $datos = pedirDatosAdministrador(false);
    $idPuesto = (int)$db->query('SELECT MIN(id_puesto) FROM puesto_salud')->fetchColumn();
    $db->prepare("INSERT INTO usuario (id_rol, id_puesto, nombre_completo, usuario, contrasena_hash)
                  VALUES (:rol, :puesto, :nombre, :usuario, :hash)")
       ->execute([':rol' => $idRol, ':puesto' => $idPuesto, ':nombre' => $datos['nombre'],
                  ':usuario' => $usuario, ':hash' => $datos['hash']]);
    $id = (int)$db->lastInsertId();
    registrarEnBitacora($db, $id, 'crear', $id, "Administrador \"$usuario\" creado desde la consola");
    echo "\nListo: ya puede ingresar con el usuario \"$usuario\".\n";
}
