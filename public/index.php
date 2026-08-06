<?php
/**
 * Punto de entrada único del sistema (front controller).
 * Todas las peticiones pasan por aquí y se enrutan según la acción.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Sesion.php';
require_once __DIR__ . '/../src/core/Bitacora.php';
require_once __DIR__ . '/../src/core/Catalogo.php';
require_once __DIR__ . '/../src/auth/Auth.php';
require_once __DIR__ . '/../src/ninos/Nino.php';

Sesion::iniciar();

$accion = $_GET['accion'] ?? 'inicio';

switch ($accion) {

    // ---------- Autenticación ----------
    case 'login':
        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (Auth::login($usuario, $contrasena)) {
            Bitacora::registrar(Auth::usuario()['id'], 'login', 'usuario', Auth::usuario()['id'], 'Inicio de sesión');
            header('Location: ' . APP_URL . '/index.php?accion=ninos');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
        require __DIR__ . '/../views/auth/login.php';
        break;

    case 'logout':
        if (Auth::autenticado()) {
            Bitacora::registrar(Auth::usuario()['id'], 'logout', 'usuario', Auth::usuario()['id'], 'Cierre de sesión');
        }
        Auth::logout();
        header('Location: ' . APP_URL . '/index.php?accion=inicio');
        exit;

    // ---------- Niños ----------
    case 'ninos':
        Auth::exigirAutenticacion();
        $filtros = [
            'texto'     => trim($_GET['texto'] ?? ''),
            'id_sector' => $_GET['id_sector'] ?? '',
            'estado'    => $_GET['estado'] ?? '',
        ];
        $ninos    = Nino::listar($filtros);
        $sectores = Catalogo::sectores();
        $mensaje  = $_GET['ok'] ?? '';
        require __DIR__ . '/../views/ninos/lista.php';
        break;

    case 'nino_nuevo':
        Auth::exigirRol(['enfermeria']);
        $sectores = Catalogo::sectores();
        $parajes  = Catalogo::parajes();
        $nino = null;
        require __DIR__ . '/../views/ninos/formulario.php';
        break;

    case 'nino_guardar':
        Auth::exigirRol(['enfermeria']);
        $datos = obtenerDatosFormulario();
        $errores = validarNino($datos);

        if ($errores) {
            $sectores = Catalogo::sectores();
            $parajes  = Catalogo::parajes();
            $nino = null;
            require __DIR__ . '/../views/ninos/formulario.php';
            break;
        }

        $usuario = Auth::usuario();
        $permiso = Nino::permisoEscritura($usuario, (int)$datos['id_sector']);

        if ($permiso === 'denegado') {
            http_response_code(403);
            echo 'No tiene permiso para registrar en ese sector.';
            break;
        }

        $idNino = Nino::crear($datos, $usuario['id']);

        $msg = 'Niño registrado correctamente.';
        if ($permiso === 'advertencia') {
            $msg .= ' Nota: el niño pertenece a un sector distinto al suyo; la acción quedó registrada en la bitácora.';
        }
        header('Location: ' . APP_URL . '/index.php?accion=ninos&ok=' . urlencode($msg));
        exit;

    case 'nino_editar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) { echo 'Niño no encontrado.'; break; }
        $sectores = Catalogo::sectores();
        $parajes  = Catalogo::parajes();
        require __DIR__ . '/../views/ninos/formulario.php';
        break;

    case 'nino_actualizar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) { echo 'Niño no encontrado.'; break; }

        $datos = obtenerDatosFormulario();
        $errores = validarNino($datos);
        if ($errores) {
            $sectores = Catalogo::sectores();
            $parajes  = Catalogo::parajes();
            require __DIR__ . '/../views/ninos/formulario.php';
            break;
        }

        $usuario = Auth::usuario();
        $permiso = Nino::permisoEscritura($usuario, (int)$datos['id_sector']);
        if ($permiso === 'denegado') {
            http_response_code(403);
            echo 'No tiene permiso para editar en ese sector.';
            break;
        }

        Nino::actualizar($idNino, $datos, $usuario['id']);
        $msg = 'Datos del niño actualizados.';
        if ($permiso === 'advertencia') {
            $msg .= ' Nota: sector distinto al suyo; quedó registrado en la bitácora.';
        }
        header('Location: ' . APP_URL . '/index.php?accion=ninos&ok=' . urlencode($msg));
        exit;

    // ---------- Inicio / login ----------
    case 'inicio':
    default:
        if (Auth::autenticado() && !Sesion::expiroPorInactividad()) {
            header('Location: ' . APP_URL . '/index.php?accion=ninos');
            exit;
        }
        $error = isset($_GET['expiro']) ? 'La sesión se cerró por inactividad. Ingrese nuevamente.' : null;
        require __DIR__ . '/../views/auth/login.php';
        break;
}

// =====================================================
// Funciones auxiliares del controlador
// =====================================================

function obtenerDatosFormulario(): array
{
    return [
        'nombres'          => trim($_POST['nombres'] ?? ''),
        'apellidos'        => trim($_POST['apellidos'] ?? ''),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'sexo'             => $_POST['sexo'] ?? '',
        'cui'              => trim($_POST['cui'] ?? ''),
        'etnia'            => trim($_POST['etnia'] ?? ''),
        'id_sector'        => $_POST['id_sector'] ?? '',
        'id_paraje'        => $_POST['id_paraje'] ?? '',
        'direccion'        => trim($_POST['direccion'] ?? ''),
        'nombre_madre'     => trim($_POST['nombre_madre'] ?? ''),
        'nombre_padre'     => trim($_POST['nombre_padre'] ?? ''),
        'telefono'         => trim($_POST['telefono'] ?? ''),
    ];
}

function validarNino(array $d): array
{
    $errores = [];

    if ($d['nombres'] === '')   $errores[] = 'Los nombres son obligatorios.';
    if ($d['apellidos'] === '') $errores[] = 'Los apellidos son obligatorios.';
    if ($d['fecha_nacimiento'] === '') {
        $errores[] = 'La fecha de nacimiento es obligatoria.';
    } else {
        $nac = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
        if (!$nac || $nac > new DateTime('now')) {
            $errores[] = 'La fecha de nacimiento no es válida.';
        }
    }
    if (!in_array($d['sexo'], ['M', 'F'], true)) $errores[] = 'Debe seleccionar el sexo.';
    if ($d['id_sector'] === '') $errores[] = 'Debe seleccionar el sector.';

    if ($d['cui'] !== '' && !preg_match('/^\d{13}$/', $d['cui'])) {
        $errores[] = 'El CUI debe tener 13 dígitos numéricos, o dejarse vacío.';
    }
    return $errores;
}
