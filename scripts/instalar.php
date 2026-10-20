<?php
/**
 * Instalación del sistema en una base de datos vacía.
 *
 *   php scripts/instalar.php
 *
 * 1. Revisa que la base de config/config.php esté vacía. Nunca instala encima
 *    de otra instalación, para no borrar datos.
 * 2. Crea las tablas (sql/esquema.sql), carga los datos iniciales
 *    (sql/datos_iniciales.sql) y las tablas de la OMS (sql/oms_lms.sql).
 * 3. Verifica que todo quedó cargado.
 * 4. Crea la cuenta del primer administrador, con la contraseña que se
 *    escribe en ese momento. Ninguna contraseña viaja en los archivos.
 *    Si el servidor no permite ocultar lo que se escribe (Hostinger), o con
 *    --temporal, genera una contraseña temporal que se cambia al primer ingreso.
 *
 * Para probar la instalación en otra base vacía sin tocar la de config.php:
 *   php scripts/instalar.php --base=nombre_de_la_base
 *
 * Si el hosting no tiene consola, ver docs/instalacion.md (importar los tres
 * archivos con phpMyAdmin y crear el administrador con crear_administrador.php).
 */

require __DIR__ . '/consola.php';
soloConsola();

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/core/Formato.php';
require __DIR__ . '/../src/core/Validacion.php';
require __DIR__ . '/../src/core/Contrasena.php';

$base = opcion('base') ?? DB_NAME;
$carpetaSql = __DIR__ . '/../sql/';
$archivos = ['esquema.sql', 'datos_iniciales.sql', 'oms_lms.sql'];

echo "\nInstalación del Carnet Digital de Monitoreo Nutricional Infantil\n";
echo "Base de datos: $base (servidor " . DB_HOST . ")\n\n";

try {
    $db = conectar($base);
} catch (PDOException $e) {
    exit("No se pudo conectar a la base '$base': " . $e->getMessage() . "\n"
       . "Revise DB_HOST, DB_NAME, DB_USER y DB_PASS en config/config.php.\n");
}

// 1. Solo en una base vacía.
$existentes = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($existentes) {
    exit("La base '$base' ya tiene " . count($existentes) . " tablas. La instalación solo se hace en una\n"
       . "base vacía, para no borrar datos. Si es una instalación nueva que falló a medias,\n"
       . "borre las tablas desde phpMyAdmin y vuelva a ejecutar este script.\n");
}
foreach ($archivos as $archivo) {
    if (!is_file($carpetaSql . $archivo)) {
        exit("Falta el archivo sql/$archivo. Genérelo en la computadora con: php scripts/exportar_esquema.php\n");
    }
}

// 2. Tablas, datos iniciales y tablas de la OMS.
echo "Creando las tablas y cargando los datos:\n";
try {
    foreach ($archivos as $archivo) {
        $cantidad = ejecutarArchivoSql($db, $carpetaSql . $archivo);
        echo "  sql/$archivo: $cantidad sentencias\n";
    }
} catch (PDOException $e) {
    exit("\nError al ejecutar sql/$archivo: " . $e->getMessage() . "\n"
       . "La base quedó a medias: borre sus tablas y vuelva a ejecutar el script.\n");
}

// 3. Verificación: lo mínimo que el sistema necesita para funcionar.
$esperado = [
    'tablas'              => [count($db->query('SHOW TABLES')->fetchAll()), 19],
    'roles'               => [(int)$db->query('SELECT COUNT(*) FROM rol')->fetchColumn(), 3],
    'parajes'             => [(int)$db->query('SELECT COUNT(*) FROM paraje')->fetchColumn(), 29],
    'dosis de vacunas'    => [(int)$db->query('SELECT COUNT(*) FROM catalogo_vacuna')->fetchColumn(), 30],
    'filas OMS (L, M, S)' => [(int)$db->query('SELECT COUNT(*) FROM oms_lms')->fetchColumn(), 728],
];
echo "\nVerificación:\n";
$todoBien = true;
foreach ($esperado as $nombre => [$hay, $debe]) {
    $bien = $hay === $debe;
    $todoBien = $todoBien && $bien;
    printf("  %-20s %4d de %4d  %s\n", $nombre, $hay, $debe, $bien ? 'correcto' : 'NO COINCIDE');
}
if (!$todoBien) {
    exit("\nLa instalación no quedó completa. Revise los archivos de sql/ y vuelva a instalar en una base vacía.\n");
}

// 4. Primer administrador.
echo "\nAhora se crea la cuenta del administrador. Con ella se crean después\n";
echo "las cuentas de las enfermeras desde Gestión de usuarios.\n\n";
$admin = pedirDatosAdministrador();

// Mientras se escriben los datos, el servidor de la base puede cerrar la
// conexión por inactividad ("MySQL server has gone away": le pasó a la
// instalación de la demostración en Hostinger, 19 de octubre de 2026).
// Por eso se abre una conexión nueva antes de guardar.
$db = conectar($base);

$idRol    = (int)$db->query("SELECT id_rol FROM rol WHERE nombre = 'administrador'")->fetchColumn();
$idPuesto = (int)$db->query('SELECT id_puesto FROM puesto_salud ORDER BY id_puesto LIMIT 1')->fetchColumn();
$db->prepare("INSERT INTO usuario (id_rol, id_puesto, nombre_completo, usuario, contrasena_hash, debe_cambiar_pass)
              VALUES (:rol, :puesto, :nombre, :usuario, :hash, :cambiar)")
   ->execute([':rol' => $idRol, ':puesto' => $idPuesto, ':nombre' => $admin['nombre'],
              ':usuario' => $admin['usuario'], ':hash' => $admin['hash'],
              ':cambiar' => $admin['temporal'] === null ? 0 : 1]);
$idAdmin = (int)$db->lastInsertId();
registrarEnBitacora($db, $idAdmin, 'crear', $idAdmin, 'Instalación del sistema: cuenta del primer administrador');

echo "\nInstalación terminada. Ya puede ingresar con el usuario \"{$admin['usuario']}\".\n";
mostrarContrasenaTemporal($admin);
if (APP_ENTORNO === 'desarrollo') {
    echo "\nRecuerde: en el servidor, APP_ENTORNO debe ser 'produccion' (o 'demostracion') en config/config.php,\n";
    echo "para que no se muestren errores técnicos en pantalla.\n";
}
