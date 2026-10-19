<?php

require_once __DIR__ . '/../config/config.php';

// Fuera del ambiente de desarrollo no se muestran errores técnicos en pantalla:
// pueden revelar rutas del servidor y consultas. Quedan en el registro de errores,
// fuera de public/: Hostinger lo guardaba en un archivo error_log junto a este
// index.php, y cualquiera habría podido abrirlo desde el navegador.
if (APP_ENTORNO !== 'desarrollo') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../errores_php.log');
}
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Sesion.php';
require_once __DIR__ . '/../src/core/Bitacora.php';
require_once __DIR__ . '/../src/core/Catalogo.php';
require_once __DIR__ . '/../src/auth/Auth.php';
require_once __DIR__ . '/../src/auth/Verificacion.php';
require_once __DIR__ . '/../src/core/Correo.php';
require_once __DIR__ . '/../src/ninos/Nino.php';
require_once __DIR__ . '/../src/mediciones/Medicion.php';
require_once __DIR__ . '/../src/usuarios/Usuario.php';
require_once __DIR__ . '/../src/core/Contrasena.php';
require_once __DIR__ . '/../src/calculo/Calculo.php';
require_once __DIR__ . '/../src/alertas/Alerta.php';
require_once __DIR__ . '/../src/calculo/GraficaCrecimiento.php';
require_once __DIR__ . '/../src/reportes/Reporte.php';
require_once __DIR__ . '/../src/reportes/LibroExcel.php';
require_once __DIR__ . '/../src/reportes/FormatoSigsa3.php';
require_once __DIR__ . '/../src/carnet/Vacuna.php';
require_once __DIR__ . '/../src/carnet/Suplementacion.php';
require_once __DIR__ . '/../src/carnet/Cita.php';
require_once __DIR__ . '/../src/carnet/CarnetFamilia.php';
require_once __DIR__ . '/../src/core/Icono.php';
require_once __DIR__ . '/../src/core/Formato.php';
require_once __DIR__ . '/../src/core/Recurso.php';
require_once __DIR__ . '/../src/core/Validacion.php';
require_once __DIR__ . '/../src/core/Registro.php';

// Ningún formulario del sistema envía listas (campo[]). Si un parámetro llega
// como lista, alguien alteró la dirección o el formulario: se descarta aquí,
// antes de que llegue a una validación que espera texto y la haga fallar.
foreach (['_GET', '_POST'] as $origen) {
    foreach ($GLOBALS[$origen] as $clave => $valor) {
        if (is_array($valor)) {
            unset($GLOBALS[$origen][$clave]);
        }
    }
}

Sesion::iniciar();

$accion = $_GET['accion'] ?? 'inicio';

switch ($accion) {

    // ---------- Autenticación ----------
    case 'login':
        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        $u = Auth::verificarCredenciales($usuario, $contrasena);
        if (!$u) {
            $error = 'Usuario o contraseña incorrectos.';
            require __DIR__ . '/../views/auth/login.php';
            break;
        }
        // Dispositivo nuevo, o administrador: antes de entrar, el código
        // enviado a su correo (RF-13). La sesión todavía no se inicia.
        if (Auth::necesitaCodigo($u)) {
            session_regenerate_id(true);
            $_SESSION['ingreso_pendiente'] = ['id' => (int)$u['id_usuario'], 'desde' => time()];
            $_SESSION['ingreso_pendiente']['envio'] = enviarCodigoIngreso($u);
            header('Location: ' . APP_URL . '/index.php?accion=verificar_codigo');
            exit;
        }
        $forma = '';
        if (Correo::activo()) {
            $forma = empty($u['correo']) ? 'sin correo registrado: no se pidió código' : 'dispositivo de confianza';
        }
        completarIngreso($u, $forma);
        break;

    // Código de ingreso enviado al correo: pantalla, revisión y reenvío.
    case 'verificar_codigo':
    case 'verificar_codigo_guardar':
    case 'reenviar_codigo':
        $u = ingresoPendiente();
        if (!$u) {
            header('Location: ' . APP_URL . '/index.php?accion=inicio&codigo_vencido=1');
            exit;
        }
        $idPendiente = (int)$u['id_usuario'];
        $error = null;

        if ($accion === 'reenviar_codigo') {
            $_SESSION['ingreso_pendiente']['envio'] = enviarCodigoIngreso($u);
            header('Location: ' . APP_URL . '/index.php?accion=verificar_codigo');
            exit;
        }

        if ($accion === 'verificar_codigo_guardar') {
            unset($_SESSION['ingreso_pendiente']['envio']);
            [$resultado, $quedan] = Verificacion::verificarCodigo($idPendiente, preg_replace('/\D/', '', $_POST['codigo'] ?? ''));
            if ($resultado === 'correcto') {
                // El administrador recibe el código siempre: su dispositivo no se recuerda.
                if ($u['rol'] !== 'administrador' && ($_POST['recordar'] ?? '') === '1') {
                    Verificacion::recordarDispositivo($idPendiente);
                }
                completarIngreso($u, 'con el código enviado al correo');
            }
            if ($resultado === 'bloqueado') {
                Bitacora::registrar($idPendiente, 'codigo_bloqueado', 'usuario', $idPendiente,
                    'Código de ingreso anulado por intentos fallidos');
            }
            $error = match ($resultado) {
                'incorrecto' => 'El código no es correcto. ' . ($quedan === 1 ? 'Le queda 1 intento.' : "Le quedan $quedan intentos."),
                'vencido'    => 'Ese código ya venció. Toque "Enviar otro código".',
                default      => 'Ese código ya no sirve. Toque "Enviar otro código".',
            };
        }

        $estadoEnvio = $_SESSION['ingreso_pendiente']['envio'] ?? null;
        $segundosEspera = Verificacion::segundosParaReenviar($idPendiente);
        require __DIR__ . '/../views/auth/verificar_codigo.php';
        break;

    // Recuperar la contraseña con un enlace enviado al correo (RF-13). La
    // respuesta es la misma exista o no el usuario: así no se puede averiguar
    // qué usuarios tiene el sistema.
    case 'recuperar':
    case 'recuperar_enviar':
        if (!Correo::activo()) {
            header('Location: ' . APP_URL . '/index.php?accion=inicio');
            exit;
        }
        $enviado = false;
        $error = null;
        if ($accion === 'recuperar_enviar') {
            $u = Auth::buscarParaIngreso(trim($_POST['usuario'] ?? ''));
            if ($u && !empty($u['correo'])) {
                enviarEnlaceRecuperacion($u);
            }
            $enviado = true;
        }
        require __DIR__ . '/../views/auth/recuperar.php';
        break;

    case 'restablecer':
    case 'restablecer_guardar':
        $claveEnlace = (string)($_GET['clave'] ?? $_POST['clave'] ?? '');
        $enlace = Verificacion::enlaceVigente($claveEnlace);
        if (!$enlace) {
            $enviado = false;
            $error = 'El enlace no es válido, ya se usó o ya venció. Pida uno nuevo.';
            require __DIR__ . '/../views/auth/recuperar.php';
            break;
        }
        $error = null;
        if ($accion === 'restablecer_guardar') {
            $nueva   = $_POST['nueva'] ?? '';
            $repetir = $_POST['repetir'] ?? '';
            $erroresPass = Contrasena::validar($nueva);
            if ($erroresPass) {
                $error = implode(' ', $erroresPass);
            } elseif ($nueva !== $repetir) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $idEnlace = (int)$enlace['id_usuario'];
                Verificacion::marcarUsado((int)$enlace['id_codigo']);
                Usuario::cambiarContrasenaPropia($idEnlace, $nueva, 'Contraseña nueva con el enlace enviado al correo');
                // Por si alguien más conocía la contraseña: se vuelve a pedir el código en todos sus dispositivos.
                Verificacion::olvidarDispositivos($idEnlace);
                header('Location: ' . APP_URL . '/index.php?accion=inicio&restablecida=1');
                exit;
            }
        }
        $nombreEnlace = $enlace['nombre_completo'];
        require __DIR__ . '/../views/auth/cambiar_pass.php';
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

        // Orden de la tabla. Si se tocó un encabezado, viene en la dirección y
        // se guarda en una cookie por un año, para recordarlo en este navegador
        // aunque se cierre la sesión. Si no, se usa el guardado en la cookie.
        if (isset($_GET['orden'])) {
            $orden     = $_GET['orden'];
            $direccion = $_GET['dir'] ?? '';
        } else {
            [$orden, $direccion] = array_pad(explode(',', $_COOKIE['orden_ninos'] ?? ''), 2, '');
        }
        if (!in_array($orden, Nino::ORDENES, true)) {
            $orden = 'nombre';
        }
        if (!in_array($direccion, ['asc', 'desc'], true)) {
            $direccion = Nino::direccionInicial($orden);
        }
        if (isset($_GET['orden'])) {
            setcookie('orden_ninos', $orden . ',' . $direccion, [
                'expires'  => time() + 365 * 24 * 60 * 60,
                'path'     => parse_url(APP_URL, PHP_URL_PATH) ?: '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $ninos    = Nino::listar($filtros, $orden, $direccion);
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
        $errores = validarNino($datos, true);

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
        // Muestra una pantalla con el nombre del niño recién registrado y el
        // siguiente paso (medición, vacuna, suplemento, cita o historial), para
        // no tener que buscarlo en la lista.
        header('Location: ' . APP_URL . '/index.php?accion=nino_registrado&id=' . $idNino
            . '&ok=' . urlencode($msg));
        exit;

    case 'nino_registrado':
        Auth::exigirRol(['enfermeria']);
        $nino = Nino::obtener((int)($_GET['id'] ?? 0));
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $mensaje = $_GET['ok'] ?? 'Niño registrado correctamente.';
        require __DIR__ . '/../views/ninos/registrado.php';
        break;

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
        $errores = validarNino($datos, false, $idNino);
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
        // El historial muestra también lo anulado, tachado y con su motivo.
        $mediciones = Medicion::listarPorNino($idNino, true);

        $db = Database::obtener();

        $indicadoresPorMedicion = [];
        foreach ($mediciones as $m) {
            $stmt = $db->prepare("SELECT tipo, puntuacion_z, clasificacion
                                  FROM indicador WHERE id_medicion = :id");
            $stmt->execute([':id' => $m['id_medicion']]);
            $indicadoresPorMedicion[$m['id_medicion']] = $stmt->fetchAll();
        }
        // Alerta del ÚLTIMO CONTROL, igual que en el panel de seguimiento: si
        // el niño se recuperó y su último control salió normal, no hay aviso.
        $stmtA = $db->prepare(
            "SELECT a.nivel, a.descripcion FROM alerta a
             WHERE a.estado = 'activa'
               AND a.id_medicion = (
                   SELECT m.id_medicion FROM medicion m
                   WHERE m.id_nino = :id AND m.anulado = 0
                   ORDER BY m.fecha_toma DESC, m.id_medicion DESC LIMIT 1
               )"
        );
        $stmtA->execute([':id' => $idNino]);
        $alertaActual = $stmtA->fetch();

        // Datos del carnet: vacunas, suplementos y citas (con lo anulado, tachado)
        $vacunas        = Vacuna::listarPorNino($idNino, true);
        $esquemaVacunas = Vacuna::esquemaDelNino($idNino, $nino['fecha_nacimiento']);
        $suplementos    = Suplementacion::listarPorNino($idNino, true);
        $citas          = Cita::listarPorNino($idNino, true);

        $mensaje = $_GET['ok'] ?? '';
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
        // La edad solo se calcula con una fecha válida; si no lo es, se usa la de
        // hoy para mostrar el formulario y la validación avisa del error.
        $fechaValida = Validacion::fecha($fechaToma, 'La fecha') === null;
        $edadMeses = Nino::edadEnMeses($nino['fecha_nacimiento'], $fechaValida ? $fechaToma : null);

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

        // Valores que la OMS considera imposibles para la edad: casi siempre son
        // un error de digitación. Se pide confirmarlos antes de guardar.
        $pedirConfirmacion = false;
        if (!$errores) {
            $edadDias = (new DateTime($nino['fecha_nacimiento']))->diff(new DateTime($fechaToma))->days;
            $fuera = Calculo::valoresImposibles($nino['sexo'], $edadDias, $datos['tipo_talla'],
                (float) $datos['peso_kg'], (float) $datos['talla_cm']);
            if ($fuera && empty($_POST['confirmar_valores'])) {
                $pedirConfirmacion = true;
                $errores[] = 'Revise el peso y la talla: ' . implode(' y ', $fuera)
                    . (count($fuera) > 1 ? ' quedan' : ' queda')
                    . ' fuera de lo que la OMS considera posible para un niño de esta edad.'
                    . ' Casi siempre es un error de digitación. Si los datos son correctos,'
                    . ' marque la casilla de confirmación y guarde de nuevo.';
            }
            $datos['valores_confirmados'] = (bool) $fuera;
        }

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
        header('Location: ' . Auth::paginaInicio());
        exit;

        // ---------- Gestión de usuarios (solo administrador) ----------
    case 'usuarios':
        Auth::exigirRol(['administrador']);
        $usuarios = Usuario::listar();
        $mensaje  = $_GET['ok'] ?? '';
        $temporal = $_SESSION['temporal_generada'] ?? '';
        $usuarioTemporal = $_SESSION['temporal_usuario'] ?? '';
        unset($_SESSION['temporal_generada'], $_SESSION['temporal_usuario']);
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
        $datos   = obtenerDatosUsuario();
        $errores = validarUsuario($datos);

        if ($errores) {
            $roles    = Catalogo::roles();
            $sectores = Catalogo::sectores();
            require __DIR__ . '/../views/usuarios/formulario.php';
            break;
        }

        $temporal = Usuario::crear($datos, Auth::usuario()['id']);
        $_SESSION['temporal_generada'] = $temporal;
        $_SESSION['temporal_usuario']  = $datos['usuario'];
        header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('Usuario creado correctamente.'));
        exit;

    // Corregir el nombre, el usuario, el rol o el sector de una cuenta (por
    // ejemplo, un nombre cambiado por el autocorrector). Nunca se borra una
    // cuenta: lo que registró y la bitácora quedan ligados a ella.
    case 'usuario_editar':
    case 'usuario_actualizar':
        Auth::exigirRol(['administrador']);
        $usuarioEditado = Usuario::obtener((int)($_GET['id'] ?? 0));
        if (!$usuarioEditado) {
            header('Location: ' . APP_URL . '/index.php?accion=usuarios');
            exit;
        }
        $idEditado = (int)$usuarioEditado['id_usuario'];
        $esPropio  = $idEditado === (int)Auth::usuario()['id'];

        if ($accion === 'usuario_editar') {
            $datos = [
                'nombre_completo' => $usuarioEditado['nombre_completo'],
                'usuario'         => $usuarioEditado['usuario'],
                'correo'          => $usuarioEditado['correo'] ?? '',
                'id_rol'          => (string)$usuarioEditado['id_rol'],
                'id_sector'       => (string)($usuarioEditado['id_sector'] ?? ''),
            ];
            $roles    = Catalogo::roles();
            $sectores = Catalogo::sectores();
            require __DIR__ . '/../views/usuarios/formulario.php';
            break;
        }

        $datos = obtenerDatosUsuario();
        if ($esPropio) {
            // El administrador no puede quitarse a sí mismo el rol: se quedaría
            // sin acceso a la gestión de usuarios.
            $datos['id_rol'] = (string)$usuarioEditado['id_rol'];
        }
        $errores = validarUsuario($datos, $idEditado);
        if ($errores) {
            $roles    = Catalogo::roles();
            $sectores = Catalogo::sectores();
            require __DIR__ . '/../views/usuarios/formulario.php';
            break;
        }

        Usuario::actualizar($idEditado, $datos, Auth::usuario()['id']);
        if ($esPropio) {
            $_SESSION['usuario']['nombre']  = $datos['nombre_completo'];
            $_SESSION['usuario']['usuario'] = $datos['usuario'];
        }
        header('Location: ' . APP_URL . '/index.php?accion=usuarios&ok=' . urlencode('Usuario actualizado correctamente.'));
        exit;

    case 'usuario_restablecer':
        Auth::exigirRol(['administrador']);
        $idU = (int)($_POST['id'] ?? 0);
        if ($idU > 0) {
            $temporal = Usuario::restablecerContrasena($idU, Auth::usuario()['id']);
            // Como en la recuperación por correo: si perdió el teléfono, ese
            // teléfono deja de ser de confianza y se vuelve a pedir el código.
            Verificacion::olvidarDispositivos($idU);
            $_SESSION['temporal_generada'] = $temporal;
            $_SESSION['temporal_usuario']  = Usuario::obtener($idU)['usuario'] ?? '';
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

        // Panel de seguimiento de alertas
    case 'panel_alertas':
        Auth::exigirAutenticacion();
        $sectores = Catalogo::sectores();
        // Solo valores de las listas; cualquier otro se ignora.
        $niveles = ['desnutricion_aguda', 'desnutricion_cronica', 'riesgo', 'sobrepeso', 'cerca_umbral'];
        $filtros = [
            'id_sector' => in_array($_GET['id_sector'] ?? '', array_map('strval', array_column($sectores, 'id_sector')), true)
                           ? $_GET['id_sector'] : '',
            'nivel'     => in_array($_GET['nivel'] ?? '', $niveles, true) ? $_GET['nivel'] : '',
        ];
        $ninos    = Alerta::panelSeguimiento($filtros);
        $resumen  = Alerta::resumenNiveles($filtros['id_sector']);

        // Texto de los filtros para la bitácora y para el encabezado impreso.
        $textoFiltros = 'Todos los sectores';
        foreach ($sectores as $s) {
            if ((string)$s['id_sector'] === $filtros['id_sector']) {
                $textoFiltros = 'Territorio ' . $s['territorio'] . ', sector ' . $s['letra'];
            }
        }
        $textoFiltros .= $filtros['nivel'] !== '' ? '; nivel: ' . Alerta::etiquetaNivel($filtros['nivel']) : '; todos los niveles';

        // "Imprimir lista": hoja para imprimir o guardar como PDF, por ejemplo
        // para llevarla en las visitas domiciliarias.
        if (isset($_GET['imprimir'])) {
            Bitacora::registrar(Auth::usuario()['id'], 'exportar', 'alerta', null,
                'Impresión de la lista de seguimiento (' . count($ninos) . ' niños). ' . $textoFiltros);
            require __DIR__ . '/../views/alertas/imprimir.php';
            break;
        }
        require __DIR__ . '/../views/alertas/panel.php';
        break;

    case 'carnet_familia':
        // Copia del carnet para la madre, el padre o el responsable: se
        // imprime o se guarda como PDF desde el navegador.
        Auth::exigirAutenticacion();
        $carnet = CarnetFamilia::datos((int)($_GET['id'] ?? 0));
        if (!$carnet) {
            echo 'Niño no encontrado.';
            break;
        }
        $nino = $carnet['nino'];
        Bitacora::registrar(Auth::usuario()['id'], 'exportar', 'nino', (int)$nino['id_nino'],
            'Carnet para la familia de ' . $nino['nombres'] . ' ' . $nino['apellidos']);
        require __DIR__ . '/../views/carnet/familia.php';
        break;

    case 'nino_graficas':
        Auth::exigirAutenticacion();
        $idNino = (int)($_GET['id'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

        $graficas = [
            'peso_edad'  => GraficaCrecimiento::datos($idNino, 'peso_edad'),
            'talla_edad' => GraficaCrecimiento::datos($idNino, 'talla_edad'),
            'peso_talla' => GraficaCrecimiento::datos($idNino, 'peso_talla'),
        ];
        require __DIR__ . '/../views/mediciones/graficas.php';
        break;

    // ---------- Formato SIGSA 3 (registro diario de consulta) ----------
    case 'sigsa3':
        // La hoja oficial con las atenciones a niños ya llenas, para no
        // escribirlas a mano otra vez. Se imprime o se guarda como PDF.
        Auth::exigirRol(['administrador', 'consulta', 'enfermeria']);
        $filtros = FormatoSigsa3::filtros($_GET);
        $puesto  = FormatoSigsa3::puestoDelUsuario(Auth::usuario()['id']);
        $filas   = FormatoSigsa3::filas($filtros, $puesto);
        Bitacora::registrar(Auth::usuario()['id'], 'exportar', 'reporte', null,
            'Formato SIGSA 3 ' . ($filtros['mes_completo']
                ? 'de ' . $filtros['mes'] . ' de ' . $filtros['anio']
                : 'del ' . Formato::fechaTexto($filtros['fecha']))
            . ' (' . count($filas) . ' atenciones a niños)');
        require __DIR__ . '/../views/reportes/sigsa3.php';
        break;

    // ---------- Reportes consolidados ----------
    case 'reportes':
    case 'reporte_csv':
    case 'reporte_excel':
    case 'reporte_imprimir':
        // Panel de indicadores y sus tres exportaciones. Todas usan los mismos
        // filtros y los mismos datos, así las cifras coinciden en todo formato.
        Auth::exigirRol(['administrador', 'consulta', 'enfermeria']);
        $filtros   = Reporte::filtros($_GET);
        $ninos     = Reporte::ninos($filtros);
        $resumen   = Reporte::resumen($ninos, $filtros);
        $tendencia = Reporte::tendencia($ninos, $filtros);

        if ($accion === 'reportes') {
            $territorios = Reporte::territorios();
            $sectores    = Catalogo::sectores();
            $parajes     = Catalogo::parajes();
            require __DIR__ . '/../views/reportes/panel.php';
            break;
        }

        $formatos = ['reporte_csv' => 'CSV', 'reporte_excel' => 'Excel', 'reporte_imprimir' => 'PDF (impresión)'];
        Bitacora::registrar(Auth::usuario()['id'], 'exportar', 'reporte', null,
            'Exportación del panel de indicadores en ' . $formatos[$accion] . '. '
            . Reporte::descripcionFiltros($filtros));

        if ($accion === 'reporte_csv') {
            Reporte::descargarCsv($ninos, $filtros);
        } elseif ($accion === 'reporte_excel') {
            Reporte::descargarExcel($ninos, $resumen, $tendencia, $filtros);
        } else {
            require __DIR__ . '/../views/reportes/imprimir.php';
        }
        break;

    // ---------- Vacunación ----------
    case 'vacuna_nueva':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $tipo = 'vacuna';
        $catalogo = Vacuna::catalogo();
        $esquema = Vacuna::esquemaDelNino($idNino, $nino['fecha_nacimiento']);
        $registradas = Vacuna::dosisRegistradas($idNino);
        require __DIR__ . '/../views/carnet/formulario.php';
        break;

    case 'vacuna_guardar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

        // Varias dosis a la vez: cada casilla marcada se llama dosis_<id del
        // catálogo> y su lote, lote_<id>. Se recorren las del catálogo, así que
        // solo cuentan dosis que existen. No se aceptan nombres con [], porque
        // este archivo descarta los parámetros que llegan como lista.
        $catalogo = Vacuna::catalogo();
        $registradas = Vacuna::dosisRegistradas($idNino);
        $elegidas = [];
        $errores = [];
        foreach ($catalogo as $c) {
            $id = (int)$c['id_catalogo_vacuna'];
            if (($_POST['dosis_' . $id] ?? '') !== '1') {
                continue;
            }
            $nombreDosis = $c['nombre'] . ' ' . $c['dosis'];
            $lote = trim($_POST['lote_' . $id] ?? '');
            $errores[] = Validacion::texto($lote, 'Lote de ' . $nombreDosis, 50);
            // Evita registrarla dos veces (por ejemplo, al tocar Guardar dos veces).
            $yaPuesta = $registradas[$c['nombre'] . '|' . $c['dosis']] ?? null;
            if ($yaPuesta !== null) {
                $errores[] = $nombreDosis . ': ya está registrada el ' . Formato::fechaTexto($yaPuesta)
                    . '. Si ese registro está mal, anúlelo en el historial.';
            }
            $elegidas[] = ['nombre_vacuna' => $c['nombre'], 'dosis' => $c['dosis'], 'lote' => $lote];
        }
        if (!$elegidas) {
            $errores[] = 'Dosis: marque al menos una vacuna aplicada.';
        }
        $errores[] = Validacion::fecha($_POST['fecha_aplicacion'] ?? '', 'Fecha de aplicación',
            $nino['fecha_nacimiento'], date('Y-m-d'), 'anterior al nacimiento', 'posterior a hoy');
        $errores[] = Validacion::enLista($_POST['lugar_vacunacion'] ?? '', array_keys(Vacuna::LUGARES),
            'Debe seleccionar el lugar de vacunación.');
        $errores = array_values(array_filter($errores));

        if ($errores) {
            $tipo = 'vacuna';
            $esquema = Vacuna::esquemaDelNino($idNino, $nino['fecha_nacimiento']);
            require __DIR__ . '/../views/carnet/formulario.php';
            break;
        }

        Vacuna::crearVarias($idNino, $elegidas, $_POST['fecha_aplicacion'],
            (int)$_POST['lugar_vacunacion'], Auth::usuario()['id']);

        $nombres = array_map(fn($e) => $e['nombre_vacuna'] . ' ' . $e['dosis'], $elegidas);
        $mensajeOk = count($elegidas) === 1
            ? 'Vacuna registrada: ' . $nombres[0] . '.'
            : 'Se registraron ' . count($elegidas) . ' vacunas: ' . implode(', ', $nombres) . '.';
        header('Location: ' . APP_URL . '/index.php?accion=nino_historial&id=' . $idNino
            . '&ok=' . urlencode($mensajeOk));
        exit;

        // ---------- Suplementación ----------
    case 'suplemento_nuevo':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $tipo = 'suplemento';
        $catalogo = Suplementacion::catalogo();
        require __DIR__ . '/../views/carnet/formulario.php';
        break;

    case 'suplemento_guardar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

        // El suplemento debe venir del catálogo, y la fecha estar entre el nacimiento y hoy.
        $errores = array_values(array_filter([
            Validacion::enLista($_POST['tipo_sup'] ?? '', array_column(Suplementacion::catalogo(), 'nombre'),
                'Debe seleccionar el suplemento de la lista.'),
            Validacion::fecha($_POST['fecha_entrega'] ?? '', 'Fecha de entrega',
                $nino['fecha_nacimiento'], date('Y-m-d'), 'anterior al nacimiento', 'posterior a hoy'),
            Validacion::texto(trim($_POST['cantidad'] ?? ''), 'Cantidad entregada', 50),
        ]));

        if ($errores) {
            $tipo = 'suplemento';
            $catalogo = Suplementacion::catalogo();
            require __DIR__ . '/../views/carnet/formulario.php';
            break;
        }

        Suplementacion::crear([
            'id_nino'       => $idNino,
            'tipo'          => $_POST['tipo_sup'],
            'fecha_entrega' => $_POST['fecha_entrega'],
            'cantidad'      => trim($_POST['cantidad'] ?? ''),
        ], Auth::usuario()['id']);

        header('Location: ' . APP_URL . '/index.php?accion=nino_historial&id=' . $idNino
            . '&ok=' . urlencode('Suplemento registrado correctamente.'));
        exit;

        // ---------- Citas ----------
    case 'cita_nueva':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }
        $tipo = 'cita';
        $catalogo = [];
        $cargaPorDia = Cita::conteoPorDia(date('Y-m-d'));
        require __DIR__ . '/../views/carnet/formulario.php';
        break;

    case 'cita_guardar':
        Auth::exigirRol(['enfermeria']);
        $idNino = (int)($_GET['id_nino'] ?? 0);
        $nino = Nino::obtener($idNino);
        if (!$nino) {
            echo 'Niño no encontrado.';
            break;
        }

        // Fecha desde hoy y hasta un año adelante (el control más espaciado de la
        // normativa es cada 6 meses); el motivo debe ser uno de la lista.
        $errores = array_values(array_filter([
            Validacion::fecha($_POST['fecha_programada'] ?? '', 'Fecha de la cita',
                date('Y-m-d'), date('Y-m-d', strtotime('+1 year')), 'anterior a hoy', 'de más de un año adelante'),
            Validacion::enLista($_POST['motivo'] ?? '', Cita::MOTIVOS, 'Debe seleccionar el motivo de la lista.'),
            Validacion::texto(trim($_POST['observaciones'] ?? ''), 'Observaciones', 300),
        ]));

        if ($errores) {
            $tipo = 'cita';
            $catalogo = [];
            $cargaPorDia = Cita::conteoPorDia(date('Y-m-d'));
            require __DIR__ . '/../views/carnet/formulario.php';
            break;
        }

        Cita::crear([
            'id_nino'          => $idNino,
            'fecha_programada' => $_POST['fecha_programada'],
            'motivo'           => $_POST['motivo'],
            'observaciones'    => trim($_POST['observaciones'] ?? ''),
        ], Auth::usuario()['id']);

        header('Location: ' . APP_URL . '/index.php?accion=nino_historial&id=' . $idNino
            . '&ok=' . urlencode('Cita programada correctamente.'));
        exit;

    case 'cita_estado':
        Auth::exigirRol(['enfermeria']);
        $idCita = (int)($_POST['id_cita'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($idCita > 0 && in_array($estado, ['cumplida', 'inasistencia', 'reprogramada'], true)) {
            Cita::cambiarEstado($idCita, $estado, Auth::usuario()['id']);
        }
        $volver = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/index.php?accion=citas_proximas');
        header('Location: ' . $volver);
        exit;

    case 'citas_proximas':
        Auth::exigirAutenticacion();
        $dias = (int)($_GET['dias'] ?? 7);
        if (!in_array($dias, [3, 7, 15, 30], true)) $dias = 7;
        $filtros  = ['id_sector' => $_GET['id_sector'] ?? ''];
        $citas    = Cita::proximas($dias, $filtros);
        $vencidas = Cita::vencidas();
        $sectores = Catalogo::sectores();
        require __DIR__ . '/../views/carnet/proximas_citas.php';
        break;

    case 'citas_calendario':
        Auth::exigirAutenticacion();

        // Día elegido (AAAA-MM-DD). Se valida que sea una fecha real.
        $diaTexto = $_GET['dia'] ?? '';
        $dia = DateTime::createFromFormat('!Y-m-d', $diaTexto);
        if (!$dia || $dia->format('Y-m-d') !== $diaTexto) {
            $dia = null;
        }

        // Mes a mostrar (AAAA-MM). Si no viene, el del día elegido o el actual.
        $mesTexto = $_GET['mes'] ?? '';
        $mes = DateTime::createFromFormat('!Y-m', $mesTexto);
        if (!$mes || $mes->format('Y-m') !== $mesTexto) {
            $mes = new DateTime(($dia ? $dia->format('Y-m') : date('Y-m')) . '-01');
        }

        // Al abrir el mes actual sin elegir día, se muestran las citas de hoy.
        if (!$dia && $mes->format('Y-m') === date('Y-m')) {
            $dia = new DateTime('today');
        }

        // La cuadrícula va del lunes de la primera semana al domingo de la última.
        $inicio = (clone $mes)->modify('monday this week');
        $fin    = (clone $mes)->modify('last day of this month')->modify('sunday this week');

        $conteo   = Cita::conteoPorDia($inicio->format('Y-m-d'), $fin->format('Y-m-d'));
        $citasDia = $dia ? Cita::delDia($dia->format('Y-m-d')) : [];
        require __DIR__ . '/../views/carnet/calendario_citas.php';
        break;

    case 'cita_aviso_enviado':
        // Marcar el aviso es un cambio en la base: solo enfermería (consulta es de solo lectura).
        Auth::exigirRol(['enfermeria']);
        $idCita = (int)($_GET['id'] ?? 0);
        if ($idCita > 0) {
            Cita::marcarAvisoEnviado($idCita, Auth::usuario()['id']);
        }
        http_response_code(204); // Sin contenido; se llama desde JavaScript.
        exit;

        // ---------- Anular y corregir registros ----------
        // Nada se borra: lo anulado queda tachado en el historial con su motivo.
        // Durante el mes de la atención basta el motivo; si es de un mes
        // anterior, se pide además la referencia del conocimiento (Registro).
    case 'registro_anular':
    case 'registro_anular_guardar':
    case 'registro_corregir':
    case 'registro_corregir_guardar':
        Auth::exigirRol(['enfermeria']);
        $esCorreccion = in_array($accion, ['registro_corregir', 'registro_corregir_guardar'], true);
        $tipo = $_GET['tipo'] ?? '';
        $idRegistro = (int)($_GET['id'] ?? 0);
        $registro = Registro::tipoValido($tipo, $esCorreccion) ? Registro::obtener($tipo, $idRegistro) : null;
        if (!$registro) {
            echo 'Registro no encontrado.';
            break;
        }
        $idNino = (int)$registro['id_nino'];
        $urlHistorial = APP_URL . '/index.php?accion=nino_historial&id=' . $idNino;
        if ($registro['anulado']) {
            header('Location: ' . $urlHistorial . '&ok=' . urlencode('Ese registro ya estaba anulado.'));
            exit;
        }
        $fueraDelMes = Registro::fueraDelMes(Registro::fechaAtencion($tipo, $registro));
        $errores = [];
        $datos = $esCorreccion
            ? array_intersect_key($registro, Registro::CORREGIBLES[$tipo])
            : [];
        // El campo se llama motivo_cambio: la cita ya tiene su propio campo "motivo".
        $motivo = trim($_POST['motivo_cambio'] ?? '');
        $referencia = trim($_POST['referencia'] ?? '');

        if (in_array($accion, ['registro_anular_guardar', 'registro_corregir_guardar'], true)) {
            $errores = Registro::validarMotivo($motivo, $referencia, $fueraDelMes);
            if ($esCorreccion) {
                [$datos, $erroresDatos] = Registro::validarCorreccion($tipo, $_POST, $registro['fecha_nacimiento']);
                $errores = array_merge($erroresDatos, $errores);
            }
            if (!$errores) {
                $idUsuario = Auth::usuario()['id'];
                if ($esCorreccion) {
                    Registro::corregir($tipo, $idRegistro, $datos, $motivo, $referencia, $idUsuario);
                    $mensajeOk = 'Se corrigió ' . Registro::TIPOS[$tipo]['articulo'] . '.';
                } else {
                    Registro::anular($tipo, $idRegistro, $motivo, $referencia, $idUsuario);
                    $mensajeOk = 'Se anuló ' . Registro::TIPOS[$tipo]['articulo'] . '. Queda tachada en el historial.';
                }
                header('Location: ' . $urlHistorial . '&ok=' . urlencode($mensajeOk));
                exit;
            }
        }
        require __DIR__ . '/../views/carnet/registro.php';
        break;

        // ---------- Catálogo de suplementos (solo administrador) ----------
    // El puesto agrega los suplementos nuevos y desactiva los que ya no
    // entrega (visita del 16 de octubre de 2026: dejó el hierro y agregó el
    // suplemento nutricional lipídico). Nunca se borra uno.
    case 'suplementos_catalogo':
        Auth::exigirRol(['administrador']);
        $suplementos = Suplementacion::catalogoCompleto();
        $mensaje = $_GET['ok'] ?? '';
        require __DIR__ . '/../views/carnet/suplementos_catalogo.php';
        break;

    case 'suplemento_catalogo_nuevo':
    case 'suplemento_catalogo_editar':
    case 'suplemento_catalogo_guardar':
        Auth::exigirRol(['administrador']);
        $idSuplemento = (int)($_GET['id'] ?? 0);
        $suplemento = $idSuplemento > 0 ? Suplementacion::obtenerDelCatalogo($idSuplemento) : null;
        if ($idSuplemento > 0 && !$suplemento) {
            header('Location: ' . APP_URL . '/index.php?accion=suplementos_catalogo');
            exit;
        }
        // Con entregas registradas, el nombre queda fijo (ver Suplementacion::actualizarEnCatalogo).
        $nombreFijo = $suplemento && (int)$suplemento['entregas'] > 0;
        $errores = [];

        if ($accion !== 'suplemento_catalogo_guardar') {
            $datos = [
                'nombre'           => $suplemento['nombre'] ?? '',
                'presentacion'     => $suplemento['presentacion'] ?? '',
                'indicacion'       => $suplemento['indicacion'] ?? '',
                'edad_desde_meses' => (string)($suplemento['edad_desde_meses'] ?? ''),
                'edad_hasta_meses' => (string)($suplemento['edad_hasta_meses'] ?? ''),
            ];
            require __DIR__ . '/../views/carnet/suplemento_catalogo_formulario.php';
            break;
        }

        $limpiar = fn(string $campo) => trim(preg_replace('/\s+/u', ' ', (string)($_POST[$campo] ?? '')));
        $datos = [
            'nombre'           => $nombreFijo ? $suplemento['nombre'] : $limpiar('nombre'),
            'presentacion'     => $limpiar('presentacion'),
            'indicacion'       => $limpiar('indicacion'),
            'edad_desde_meses' => $limpiar('edad_desde_meses'),
            'edad_hasta_meses' => $limpiar('edad_hasta_meses'),
        ];
        $errores = Suplementacion::validarCatalogo($datos, $suplemento ? $idSuplemento : null);
        if ($errores) {
            require __DIR__ . '/../views/carnet/suplemento_catalogo_formulario.php';
            break;
        }

        $idUsuario = Auth::usuario()['id'];
        if ($suplemento) {
            Suplementacion::actualizarEnCatalogo($idSuplemento, $datos, $idUsuario);
            $mensajeOk = 'Suplemento actualizado.';
        } else {
            Suplementacion::crearEnCatalogo($datos, $idUsuario);
            $mensajeOk = 'Suplemento agregado. Ya aparece al registrar entregas.';
        }
        header('Location: ' . APP_URL . '/index.php?accion=suplementos_catalogo&ok=' . urlencode($mensajeOk));
        exit;

    case 'suplemento_catalogo_estado':
        Auth::exigirRol(['administrador']);
        $activo = ($_POST['activo'] ?? '1') === '1';
        Suplementacion::cambiarEstadoCatalogo((int)($_POST['id'] ?? 0), $activo, Auth::usuario()['id']);
        $mensajeOk = $activo ? 'Suplemento activado.' : 'Suplemento desactivado. Ya no aparece al registrar entregas.';
        header('Location: ' . APP_URL . '/index.php?accion=suplementos_catalogo&ok=' . urlencode($mensajeOk));
        exit;

        // ---------- Inicio / login ----------
    case 'inicio':
    default:
        if (Auth::autenticado() && !Sesion::expiroPorInactividad()) {
            header('Location: ' . Auth::paginaInicio());
            exit;
        }
        $error = match (true) {
            isset($_GET['expiro'])         => 'La sesión se cerró por inactividad. Ingrese nuevamente.',
            isset($_GET['codigo_vencido']) => 'Pasó el tiempo para escribir el código. Ingrese nuevamente.',
            default                        => null,
        };
        $aviso = isset($_GET['restablecida']) ? 'Su contraseña se cambió. Ya puede ingresar con la nueva.' : null;
        require __DIR__ . '/../views/auth/login.php';
        break;
}

// =====================================================
// Funciones auxiliares del controlador
// =====================================================

/**
 * Lee los datos del formulario del niño. Los nombres se guardan con la inicial
 * de cada palabra en mayúscula, aunque se tecleen en minúscula o en mayúscula.
 */
/**
 * Inicia la sesión de un usuario ya verificado, lo deja en la bitácora con la
 * forma de ingreso y lo lleva a su página principal (o a cambiar la
 * contraseña temporal).
 */
function completarIngreso(array $u, string $forma): void
{
    Auth::iniciarSesion($u);
    $id = (int)$u['id_usuario'];
    Bitacora::registrar($id, 'login', 'usuario', $id, 'Inicio de sesión' . ($forma !== '' ? " ($forma)" : ''));
    // Los niños que ya cumplieron 7 años pasan a egresado (queda en bitácora).
    Nino::egresarPorEdad($id);
    header('Location: ' . (Auth::debeCambiarPass() ? APP_URL . '/index.php?accion=cambiar_pass' : Auth::paginaInicio()));
    exit;
}

/**
 * Usuario que ya escribió bien su contraseña y espera el código del correo,
 * o null si no hay ninguno o pasaron más de 15 minutos.
 */
function ingresoPendiente(): ?array
{
    $pendiente = $_SESSION['ingreso_pendiente'] ?? null;
    if (!$pendiente || time() - (int)$pendiente['desde'] > 15 * 60) {
        unset($_SESSION['ingreso_pendiente']);
        return null;
    }
    return Auth::buscarParaIngreso('', (int)$pendiente['id']);
}

/**
 * Crea un código de ingreso y lo envía al correo del usuario. Devuelve
 * 'enviado', 'espera' (debe esperar para pedir otro) o 'error'.
 */
function enviarCodigoIngreso(array $u): string
{
    $id = (int)$u['id_usuario'];
    $codigo = Verificacion::crearCodigo($id);
    if ($codigo === null) {
        return 'espera';
    }
    $texto = "Hola, {$u['nombre_completo']}:\n\n"
        . "Su código para ingresar al Carnet Digital es:\n\n"
        . "    $codigo\n\n"
        . 'Vence en ' . Verificacion::MINUTOS_CODIGO . " minutos.\n\n"
        . "Si usted no está ingresando al sistema, alguien conoce su contraseña: "
        . "cámbiela y avise al administrador.\n";
    // El código va también en el asunto: en el teléfono se lee en el aviso, sin abrir el correo.
    if (!Correo::enviar($u['correo'], "Su código de ingreso: $codigo", $texto)) {
        Bitacora::registrar($id, 'error_correo', 'usuario', $id, 'No se pudo enviar el código de ingreso');
        return 'error';
    }
    return 'enviado';
}

/** Crea el enlace de recuperación y lo envía al correo del usuario. */
function enviarEnlaceRecuperacion(array $u): void
{
    $id = (int)$u['id_usuario'];
    $clave = Verificacion::crearEnlace($id);
    if ($clave === null) {
        return;
    }
    $texto = "Hola, {$u['nombre_completo']}:\n\n"
        . "Se pidió crear una contraseña nueva para su usuario {$u['usuario']} en el "
        . "Carnet Digital del Puesto de Salud Chivarreto.\n\n"
        . 'Para hacerlo, abra este enlace. Sirve una sola vez y vence en '
        . Verificacion::MINUTOS_ENLACE . " minutos:\n\n"
        . APP_URL . '/index.php?accion=restablecer&clave=' . $clave . "\n\n"
        . "Si usted no lo pidió, no haga nada: su contraseña no cambia.\n";
    $enviado = Correo::enviar($u['correo'], 'Recuperar su contraseña del Carnet Digital', $texto);
    Bitacora::registrar($id, $enviado ? 'recuperar_pass' : 'error_correo', 'usuario', $id,
        $enviado ? 'Enlace de recuperación enviado al correo' : 'No se pudo enviar el enlace de recuperación');
}

/** Lee los datos del formulario de usuario (crear o editar). */
function obtenerDatosUsuario(): array
{
    return [
        'nombre_completo' => Formato::nombrePropio($_POST['nombre_completo'] ?? ''),
        'usuario'         => trim($_POST['usuario'] ?? ''),
        // Correo personal: las enfermeras no tienen correo institucional.
        'correo'          => mb_strtolower(trim($_POST['correo'] ?? '')),
        'id_rol'          => $_POST['id_rol'] ?? '',
        'id_sector'       => $_POST['id_sector'] ?? '',
    ];
}

/**
 * Valida los datos de un usuario. Al editar, se pasa su id para que su propio
 * nombre de usuario no cuente como repetido. El rol y el sector deben existir;
 * enfermería sin sector es una enfermera de apoyo, que registra en todos los
 * sectores (Nino::permisoEscritura).
 */
function validarUsuario(array $datos, ?int $idExcepto = null): array
{
    $roles = array_column(Catalogo::roles(), 'nombre', 'id_rol');
    $errores = [
        Validacion::nombrePersona($datos['nombre_completo'], 'Nombre completo', 150, true),
        Validacion::enLista($datos['id_rol'], array_keys($roles), 'Rol: debe seleccionar un rol de la lista.'),
        Validacion::correo($datos['correo'], 'Correo electrónico', 150),
    ];
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $datos['usuario'])) {
        $errores[] = 'Nombre de usuario: debe tener de 3 a 50 caracteres: letras sin tilde, números,'
                   . ' punto, guion o guion bajo. Sin espacios.';
    } elseif (Usuario::usuarioExiste($datos['usuario'], $idExcepto)) {
        $errores[] = 'Nombre de usuario: ya existe.';
    }
    if ($datos['id_sector'] !== '') {
        $errores[] = Validacion::enLista($datos['id_sector'], array_column(Catalogo::sectores(), 'id_sector'),
            'Sector: debe seleccionar un sector de la lista.');
    }
    return array_values(array_filter($errores));
}

function obtenerDatosFormulario(): array
{
    return [
        'nombres'          => Formato::nombrePropio($_POST['nombres'] ?? ''),
        'apellidos'        => Formato::nombrePropio($_POST['apellidos'] ?? ''),
        // Se escribe como día/mes/año; se guarda como año-mes-día.
        'fecha_nacimiento' => Formato::fechaIso($_POST['fecha_nacimiento'] ?? ''),
        'sexo'             => $_POST['sexo'] ?? '',
        // El CUI y el teléfono se aceptan con espacios o guiones (5555-1234),
        // como se acostumbra escribirlos: se quitan antes de validar.
        'cui'              => str_replace([' ', '-'], '', $_POST['cui'] ?? ''),
        // Número de carnet del puesto (columna "No. Historia clínica" del SIGSA 3).
        'numero_carnet'    => strtoupper(trim($_POST['numero_carnet'] ?? '')),
        'pueblo'           => $_POST['pueblo'] ?? '',
        // La comunidad lingüística solo se guarda si el pueblo es maya.
        'comunidad_linguistica' => ($_POST['pueblo'] ?? '') === (string) Catalogo::PUEBLO_MAYA
                                   ? ($_POST['comunidad_linguistica'] ?? '') : '',
        'id_sector'        => $_POST['id_sector'] ?? '',
        'id_paraje'        => $_POST['id_paraje'] ?? '',
        'direccion'        => trim(preg_replace('/\s+/u', ' ', $_POST['direccion'] ?? '')),
        'nombre_madre'     => Formato::nombrePropio($_POST['nombre_madre'] ?? ''),
        'nombre_padre'     => Formato::nombrePropio($_POST['nombre_padre'] ?? ''),
        'telefono'         => str_replace([' ', '-'], '', $_POST['telefono'] ?? ''),
    ];
}

/**
 * Valida los datos del niño. $esNuevo indica un registro nuevo: solo ahí se
 * exige que sea menor de 7 años. Al editar no se aplica, porque un niño
 * registrado a tiempo puede cumplir 7 y aún hay que poder corregir sus datos.
 * $idNino es el niño que se edita, para que su propio número de carnet no
 * cuente como repetido.
 */
function validarNino(array $d, bool $esNuevo = false, ?int $idNino = null): array
{
    // Los largos máximos son los de cada columna de la tabla nino.
    $errores = [
        Validacion::nombrePersona($d['nombres'], 'Nombres', 100, true),
        Validacion::nombrePersona($d['apellidos'], 'Apellidos', 100, true),
        Validacion::nombrePersona($d['nombre_madre'], 'Nombre de la madre o responsable', 150, false),
        Validacion::nombrePersona($d['nombre_padre'], 'Nombre del padre', 150, false),
        // Pueblo y comunidad lingüística son opcionales, pero si vienen deben ser de la lista.
        Validacion::enLista($d['pueblo'], array_merge([''], array_keys(Catalogo::PUEBLOS)),
            'Pueblo: elija una opción de la lista.'),
        Validacion::enLista($d['comunidad_linguistica'], array_merge([''], array_keys(Catalogo::COMUNIDADES_LINGUISTICAS)),
            'Comunidad lingüística: elija una opción de la lista.'),
        Validacion::texto($d['direccion'], 'Dirección', 200),
        Validacion::cui($d['cui']),
        Validacion::codigo($d['numero_carnet'], 'Número de carnet', 20),
        Validacion::digitos($d['telefono'], 'Teléfono', 8),
        Validacion::enLista($d['sexo'], ['M', 'F'], 'Debe seleccionar el sexo.'),
    ];

    $errorFecha = Validacion::fecha($d['fecha_nacimiento'], 'Fecha de nacimiento',
        null, date('Y-m-d'), '', 'posterior a hoy');
    if ($errorFecha !== null) {
        $errores[] = $errorFecha;
    } elseif ($esNuevo && $d['fecha_nacimiento'] <= Nino::fechaLimiteNacimiento()) {
        // Fechas sin hora, para que un niño que cumple 7 hoy no aparezca con 6.
        $anios = (new DateTime($d['fecha_nacimiento']))->diff(new DateTime('today'))->y;
        $errores[] = 'El sistema registra niños menores de ' . Nino::EDAD_MAXIMA_ANIOS
                   . ' años. Según la fecha de nacimiento, este niño tiene ' . $anios . ' años.';
    }

    // El sector y el paraje deben existir, y el paraje debe ser de ese sector.
    $sectores = array_column(Catalogo::sectores(), 'id_sector');
    if ($d['id_sector'] === '' || !in_array((string) $d['id_sector'], array_map('strval', $sectores), true)) {
        $errores[] = 'Debe seleccionar el sector.';
    } elseif ($d['id_paraje'] !== '') {
        $sectorDelParaje = array_column(Catalogo::parajes(), 'id_sector', 'id_paraje')[$d['id_paraje']] ?? null;
        if ((string) $sectorDelParaje !== (string) $d['id_sector']) {
            $errores[] = 'El paraje elegido no pertenece al sector seleccionado.';
        }
    }

    // El número de carnet no se repite dentro del puesto de salud.
    if ($d['numero_carnet'] !== '' && ctype_digit((string) $d['id_sector'])) {
        $otro = Nino::carnetRepetido($d['numero_carnet'], (int) $d['id_sector'], $idNino);
        if ($otro) {
            $errores[] = 'Número de carnet: ya lo tiene ' . $otro['nombres'] . ' ' . $otro['apellidos']
                       . '. Revise el número en el carnet.';
        }
    }

    // Al menos un responsable del niño: la madre (o quien lo cuide) o el padre.
    if ($d['nombre_madre'] === '' && $d['nombre_padre'] === '') {
        $errores[] = 'Debe ingresar el nombre de la madre o responsable, o el del padre. Al menos uno es obligatorio.';
    }

    return array_values(array_filter($errores));
}


function validarMedicion(array $d, string $fechaNacimiento): array
{
    $errores = [
        Validacion::fecha($d['fecha_toma'], 'Fecha de la toma', $fechaNacimiento, date('Y-m-d'),
            'anterior al nacimiento', 'posterior a hoy'),
        Validacion::enLista($d['tipo_talla'], ['longitud', 'talla'], 'Debe seleccionar el tipo de medición.'),
        Validacion::texto($d['observaciones'], 'Observaciones', 300),
    ];

    // Peso y talla: números positivos en rangos posibles para menores de 7 años.
    // (La revisión fina según la edad la hace Calculo::valoresImposibles.)
    if (!is_numeric($d['peso_kg'])) {
        $errores[] = 'El peso es obligatorio y debe ser un número, por ejemplo 11.40.';
    } elseif ($d['peso_kg'] < 0.5 || $d['peso_kg'] > 60) {
        $errores[] = 'El peso debe estar entre 0.5 y 60 kg.';
    }
    if (!is_numeric($d['talla_cm'])) {
        $errores[] = 'La talla es obligatoria y debe ser un número, por ejemplo 88.5.';
    } elseif ($d['talla_cm'] < 30 || $d['talla_cm'] > 150) {
        $errores[] = 'La talla debe estar entre 30 y 150 cm.';
    }

    return array_values(array_filter($errores));
}
