<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Sesion.php';
require_once __DIR__ . '/../src/core/Bitacora.php';
require_once __DIR__ . '/../src/core/Catalogo.php';
require_once __DIR__ . '/../src/auth/Auth.php';
require_once __DIR__ . '/../src/ninos/Nino.php';
require_once __DIR__ . '/../src/mediciones/Medicion.php';
require_once __DIR__ . '/../src/usuarios/Usuario.php';
require_once __DIR__ . '/../src/core/Contrasena.php';
require_once __DIR__ . '/../src/calculo/Calculo.php';


Sesion::iniciar();

$accion = $_GET['accion'] ?? 'inicio';

switch ($accion) {

    // ---------- Autenticación ----------
    case 'login':
        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (Auth::login($usuario, $contrasena)) {
            Bitacora::registrar(Auth::usuario()['id'], 'login', 'usuario', Auth::usuario()['id'], 'Inicio de sesión');
            if (Auth::debeCambiarPass()) {
                header('Location: ' . APP_URL . '/index.php?accion=cambiar_pass');
                exit;
            }
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
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $sectores = Catalogo::sectores();
        $parajes  = Catalogo::parajes();
        require __DIR__ . '/../views/ninos/formulario.php';
        break;

    case 'nino_actualizar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

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

        // ---------- Historial y mediciones ----------
    case 'nino_historial':
        Auth::exigirAutenticacion();
        $idNino = (int)($_GET['id'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $mediciones = Medicion::listarPorNino($idNino);
        $mensaje = $_GET['ok'] ?? '';
        // Trae los indicadores calculados de cada medición
        $db = Database::obtener();
        $indicadoresPorMedicion = [];
        foreach ($mediciones as $m) {
            $stmt = $db->prepare("SELECT tipo, puntuacion_z, clasificacion
                                  FROM indicador WHERE id_medicion = :id");
            $stmt->execute([':id' => $m['id_medicion']]);
            $indicadoresPorMedicion[$m['id_medicion']] = $stmt->fetchAll();
        }
        require __DIR__ . '/../views/mediciones/historial.php';
        break;

    case 'medicion_nueva':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $edadMeses    = Nino::edadEnMeses($nino['fecha_nacimiento']);
        $tipoSugerido = Medicion::tipoTallaSugerido($edadMeses);
        $instrumento  = Medicion::instrumentoSugerido($edadMeses);
        require __DIR__ . '/../views/mediciones/formulario.php';
        break;

    case 'medicion_guardar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

        if ($nino['estado'] !== 'activo') {
            echo 'No se pueden registrar mediciones para un niño no activo.';
            break;
        }

        $fechaToma = $_POST['fecha_toma'] ?? date('Y-m-d');
        $edadMeses = Nino::edadEnMeses($nino['fecha_nacimiento'], $fechaToma);

        $datos = [
            'id_nino'      => $idNino,
            'fecha_toma'   => $fechaToma,
            'peso_kg'      => $_POST['peso_kg'] ?? '',
            'talla_cm'     => $_POST['talla_cm'] ?? '',
            'tipo_talla'   => $_POST['tipo_talla'] ?? '',
            'edad_meses'   => $edadMeses,
            'observaciones' => trim($_POST['observaciones'] ?? ''),
        ];

        $errores = validarMedicion($datos, $nino['fecha_nacimiento']);

        if ($errores) {
            $tipoSugerido = Medicion::tipoTallaSugerido($edadMeses);
            $instrumento  = Medicion::instrumentoSugerido($edadMeses);
            require __DIR__ . '/../views/mediciones/formulario.php';
            break;
        }

        Medicion::crear($datos, Auth::usuario()['id']);
        $msg = 'Medición registrada correctamente.';
        header('Location: ' . APP_URL . '/index.php?accion=nino_historial&id=' . $idNino . '&ok=' . urlencode($msg));
        exit;

        // ---------- Cambio de contraseña obligatorio ----------
    case 'cambiar_pass':
        Auth::exigirAutenticacion();
        require __DIR__ . '/../views/auth/cambiar_pass.php';
        break;

    case 'cambiar_pass_guardar':
        Auth::exigirAutenticacion();
        $nueva   = $_POST['nueva'] ?? '';
        $repetir = $_POST['repetir'] ?? '';

        $erroresPass = Contrasena::validar($nueva);
        if ($erroresPass) {
            $error = implode(' ', $erroresPass);
            require __DIR__ . '/../views/auth/cambiar_pass.php';
            break;
        }
        if ($nueva !== $repetir) {
            $error = 'Las contraseñas no coinciden.';
            require __DIR__ . '/../views/auth/cambiar_pass.php';
            break;
        }

        Usuario::cambiarContrasenaPropia(Auth::usuario()['id'], $nueva);
        Auth::marcarPassCambiada();
        header('Location: ' . APP_URL . '/index.php?accion=ninos');
        exit;

        // ---------- Gestión de usuarios (solo administrador) ----------
    case 'usuarios':
        Auth::exigirRol(['administrador']);
        $usuarios = Usuario::listar();
        $mensaje  = $_GET['ok'] ?? '';
        $temporal = $_SESSION['temporal_generada'] ?? '';
        unset($_SESSION['temporal_generada']);
        require __DIR__ . '/../views/usuarios/lista.php';
        break;

    case 'usuario_nuevo':
        Auth::exigirRol(['administrador']);
        $roles    = Catalogo::roles();
        $sectores = Catalogo::sectores();
        require __DIR__ . '/../views/usuarios/formulario.php';
        break;

    case 'usuario_guardar':
        Auth::exigirRol(['administrador']);
        $datos = [
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'usuario'         => trim($_POST['usuario'] ?? ''),
            'id_rol'          => $_POST['id_rol'] ?? '',
            'id_sector'       => $_POST['id_sector'] ?? '',
            'contrasena'      => $_POST['contrasena'] ?? '',
        ];
        $errores = [];
        if ($datos['nombre_completo'] === '') $errores[] = 'El nombre es obligatorio.';
        if ($datos['usuario'] === '')         $errores[] = 'El nombre de usuario es obligatorio.';
        if ($datos['id_rol'] === '')          $errores[] = 'Debe seleccionar un rol.';
        $errores = array_merge($errores, Contrasena::validar($datos['contrasena']));
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($datos['usuario'] !== '' && Usuario::usuarioExiste($datos['usuario']))
            $errores[] = 'Ese nombre de usuario ya existe.';

        if ($errores) {
            $roles    = Catalogo::roles();
            $sectores = Catalogo::sectores();
            require __DIR__ . '/../views/usuarios/formulario.php';
            break;
        }

        Usuario::crear($datos, Auth::usuario()['id']);
        header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('Usuario creado correctamente.'));
        exit;

    case 'usuario_restablecer':
        Auth::exigirRol(['administrador']);
        $idU = (int)($_POST['id'] ?? 0);
        if ($idU > 0) {
            $temporal = Usuario::restablecerContrasena($idU, Auth::usuario()['id']);
            $_SESSION['temporal_generada'] = $temporal;
        }
        header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('Contraseña restablecida.'));
        exit;

    case 'usuario_estado':
        Auth::exigirRol(['administrador']);
        $idU = (int)($_POST['id'] ?? 0);
        $activo = ($_POST['activo'] ?? '1') === '1';
        // Evita que el administrador se desactive a sí mismo.
        if ($idU === Auth::usuario()['id']) {
            header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('No puede cambiar su propio estado.'));
            exit;
        }
        if ($idU > 0) {
            Usuario::cambiarEstado($idU, $activo, Auth::usuario()['id']);
        }
        header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('Estado del usuario actualizado.'));
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
    if ($d['telefono'] !== '' && !preg_match('/^\d{8}$/', $d['telefono'])) {
        $errores[] = 'El teléfono debe tener 8 dígitos numéricos, o dejarse vacío.';
    }
    return $errores;
}


function validarMedicion(array $d, string $fechaNacimiento): array
{
    $errores = [];

    // Fecha de toma
    if ($d['fecha_toma'] === '') {
        $errores[] = 'La fecha de la toma es obligatoria.';
    } else {
        $toma = DateTime::createFromFormat('Y-m-d', $d['fecha_toma']);
        $nac  = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
        if (!$toma || $toma > new DateTime('now')) {
            $errores[] = 'La fecha de la toma no puede ser futura.';
        } elseif ($nac && $toma < $nac) {
            $errores[] = 'La fecha de la toma no puede ser anterior al nacimiento.';
        }
    }

    // Peso: rango plausible para menores de 7 años
    if ($d['peso_kg'] === '' || !is_numeric($d['peso_kg'])) {
        $errores[] = 'El peso es obligatorio y debe ser numérico.';
    } elseif ($d['peso_kg'] <= 0 || $d['peso_kg'] >= 60) {
        $errores[] = 'El peso debe estar entre 0.5 y 60 kg.';
    }

    // Talla: rango plausible
    if ($d['talla_cm'] === '' || !is_numeric($d['talla_cm'])) {
        $errores[] = 'La talla es obligatoria y debe ser numérica.';
    } elseif ($d['talla_cm'] <= 30 || $d['talla_cm'] >= 150) {
        $errores[] = 'La talla debe estar entre 30 y 150 cm.';
    }

    // Tipo de talla
    if (!in_array($d['tipo_talla'], ['longitud', 'talla'], true)) {
        $errores[] = 'Debe seleccionar el tipo de medición.';
    }

    return $errores;
}
