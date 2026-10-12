<?php

require_once __DIR__ . '/../config/config.php';

// Fuera del ambiente de desarrollo no se muestran errores técnicos en pantalla:
// pueden revelar rutas del servidor y consultas. Quedan en el registro de errores.
if (APP_ENTORNO !== 'desarrollo') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
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
require_once __DIR__ . '/../src/alertas/Alerta.php';
require_once __DIR__ . '/../src/calculo/GraficaCrecimiento.php';
require_once __DIR__ . '/../src/reportes/Reporte.php';
require_once __DIR__ . '/../src/carnet/Vacuna.php';
require_once __DIR__ . '/../src/carnet/Suplementacion.php';
require_once __DIR__ . '/../src/carnet/Cita.php';
require_once __DIR__ . '/../src/core/Icono.php';
require_once __DIR__ . '/../src/core/Formato.php';
require_once __DIR__ . '/../src/core/Recurso.php';





Sesion::iniciar();

$accion = $_GET['accion'] ?? 'inicio';

switch ($accion) {

    // ---------- Autenticación ----------
    case 'login':
        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (Auth::login($usuario, $contrasena)) {
            Bitacora::registrar(Auth::usuario()['id'], 'login', 'usuario', Auth::usuario()['id'], 'Inicio de sesión');
            // Los niños que ya cumplieron 7 años pasan a egresado (queda en bitácora).
            Nino::egresarPorEdad(Auth::usuario()['id']);
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

        $db = Database::obtener();

        $indicadoresPorMedicion = [];
        foreach ($mediciones as $m) {
            $stmt = $db->prepare("SELECT tipo, puntuacion_z, clasificacion
                                  FROM indicador WHERE id_medicion = :id");
            $stmt->execute([':id' => $m['id_medicion']]);
            $indicadoresPorMedicion[$m['id_medicion']] = $stmt->fetchAll();
        }
        $stmtA = $db->prepare(
            "SELECT a.nivel, a.descripcion FROM alerta a
             JOIN medicion m ON m.id_medicion = a.id_medicion
             WHERE a.id_nino = :id AND a.estado = 'activa'
             ORDER BY m.fecha_toma DESC, a.id_alerta DESC LIMIT 1"
        );
        $stmtA->execute([':id' => $idNino]);
        $alertaActual = $stmtA->fetch();

        // Datos del carnet: vacunas, suplementos y citas
        $vacunas        = Vacuna::listarPorNino($idNino);
        $esquemaVacunas = Vacuna::esquemaDelNino($idNino);
        $suplementos    = Suplementacion::listarPorNino($idNino);
        $citas          = Cita::listarPorNino($idNino);

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
        ];
        $errores = [];
        if ($datos['nombre_completo'] === '') $errores[] = 'El nombre es obligatorio.';
        if ($datos['usuario'] === '')         $errores[] = 'El nombre de usuario es obligatorio.';
        if ($datos['id_rol'] === '')          $errores[] = 'Debe seleccionar un rol.';
        if ($datos['usuario'] !== '' && Usuario::usuarioExiste($datos['usuario']))
            $errores[] = 'Ese nombre de usuario ya existe.';

        if ($errores) {
            $roles    = Catalogo::roles();
            $sectores = Catalogo::sectores();
            require __DIR__ . '/../views/usuarios/formulario.php';
            break;
        }

        $temporal = Usuario::crear($datos, Auth::usuario()['id']);
        $_SESSION['temporal_generada'] = $temporal;
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

        // Panel de seguimiento de alertas
    case 'panel_alertas':
        Auth::exigirAutenticacion();
        $filtros = [
            'id_sector' => $_GET['id_sector'] ?? '',
            'nivel'     => $_GET['nivel'] ?? '',
        ];
        $ninos    = Alerta::panelSeguimiento($filtros);
        $resumen  = Alerta::resumenNiveles();
        $sectores = Catalogo::sectores();
        require __DIR__ . '/../views/alertas/panel.php';
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

    // ---------- Reportes consolidados ----------
    case 'reportes':
        Auth::exigirRol(['administrador', 'consulta', 'enfermeria']);
        $filtros = [
            'id_territorio' => $_GET['id_territorio'] ?? '',
            'fecha_desde'   => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'   => $_GET['fecha_hasta'] ?? '',
        ];
        $filas       = Reporte::resumenPorSector($filtros);
        $totales     = Reporte::totales($filas);
        $territorios = Reporte::territorios();
        require __DIR__ . '/../views/reportes/consolidado.php';
        break;

    case 'reporte_csv':
        Auth::exigirRol(['administrador', 'consulta', 'enfermeria']);
        $filtros = [
            'id_territorio' => $_GET['id_territorio'] ?? '',
            'fecha_desde'   => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'   => $_GET['fecha_hasta'] ?? '',
        ];
        $filas   = Reporte::resumenPorSector($filtros);
        $totales = Reporte::totales($filas);
        Bitacora::registrar(
            Auth::usuario()['id'],
            'exportar',
            'reporte',
            null,
            'Descarga de reporte consolidado en CSV'
        );
        Reporte::descargarCSV($filas, $totales, 'reporte_nutricional_' . date('Y-m-d'));
        break;

    case 'reporte_imprimir':
        Auth::exigirRol(['administrador', 'consulta', 'enfermeria']);
        $filtros = [
            'id_territorio' => $_GET['id_territorio'] ?? '',
            'fecha_desde'   => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'   => $_GET['fecha_hasta'] ?? '',
        ];
        $filas   = Reporte::resumenPorSector($filtros);
        $totales = Reporte::totales($filas);

        // Texto descriptivo del alcance para el encabezado del documento.
        $tituloFiltro = 'Todos los territorios';
        if (!empty($filtros['id_territorio'])) {
            foreach (Reporte::territorios() as $t) {
                if ($t['id_territorio'] == $filtros['id_territorio']) {
                    $tituloFiltro = 'Territorio ' . $t['numero'] . ' — ' . $t['nombre'];
                }
            }
        }
        if (!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])) {
            $tituloFiltro .= ' | Periodo: '
                . ($filtros['fecha_desde'] ? Formato::fechaTexto($filtros['fecha_desde']) : 'inicio')
                . ' a ' . ($filtros['fecha_hasta'] ? Formato::fechaTexto($filtros['fecha_hasta']) : 'hoy');
        }

        Bitacora::registrar(
            Auth::usuario()['id'],
            'exportar',
            'reporte',
            null,
            'Vista de impresión de reporte consolidado'
        );
        require __DIR__ . '/../views/reportes/imprimir.php';
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

        $sel = $_POST['vacuna_sel'] ?? '';
        $partes = explode('|', $sel);
        $errores = [];
        if (count($partes) !== 2 || $partes[0] === '') $errores[] = 'Debe seleccionar la vacuna.';
        if (empty($_POST['fecha_aplicacion'])) $errores[] = 'La fecha de aplicación es obligatoria.';

        if ($errores) {
            $tipo = 'vacuna';
            $catalogo = Vacuna::catalogo();
            require __DIR__ . '/../views/carnet/formulario.php';
            break;
        }

        Vacuna::crear([
            'id_nino'          => $idNino,
            'nombre_vacuna'    => $partes[0],
            'dosis'            => $partes[1],
            'fecha_aplicacion' => $_POST['fecha_aplicacion'],
            'lote'             => trim($_POST['lote'] ?? ''),
            'lugar'            => trim($_POST['lugar'] ?? ''),
        ], Auth::usuario()['id']);

        header('Location: ' . APP_URL . '/index.php?accion=nino_historial&id=' . $idNino
            . '&ok=' . urlencode('Vacuna registrada correctamente.'));
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

        $errores = [];
        if (empty($_POST['tipo_sup']))      $errores[] = 'Debe seleccionar el suplemento.';
        if (empty($_POST['fecha_entrega'])) $errores[] = 'La fecha de entrega es obligatoria.';

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

        $errores = [];
        if (empty($_POST['fecha_programada'])) {
            $errores[] = 'La fecha de la cita es obligatoria.';
        } elseif ($_POST['fecha_programada'] < date('Y-m-d')) {
            $errores[] = 'La fecha de la cita no puede ser anterior a hoy.';
        }

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
            'motivo'           => $_POST['motivo'] ?? 'Control de crecimiento',
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

/**
 * Lee los datos del formulario del niño. Los nombres se guardan con la inicial
 * de cada palabra en mayúscula, aunque se tecleen en minúscula o en mayúscula.
 */
function obtenerDatosFormulario(): array
{
    return [
        'nombres'          => Formato::nombrePropio($_POST['nombres'] ?? ''),
        'apellidos'        => Formato::nombrePropio($_POST['apellidos'] ?? ''),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'sexo'             => $_POST['sexo'] ?? '',
        'cui'              => trim($_POST['cui'] ?? ''),
        'etnia'            => trim($_POST['etnia'] ?? ''),
        'id_sector'        => $_POST['id_sector'] ?? '',
        'id_paraje'        => $_POST['id_paraje'] ?? '',
        'direccion'        => trim($_POST['direccion'] ?? ''),
        'nombre_madre'     => Formato::nombrePropio($_POST['nombre_madre'] ?? ''),
        'nombre_padre'     => Formato::nombrePropio($_POST['nombre_padre'] ?? ''),
        'telefono'         => trim($_POST['telefono'] ?? ''),
    ];
}

/**
 * Valida los datos del niño. $esNuevo indica un registro nuevo: solo ahí se
 * exige que sea menor de 7 años. Al editar no se aplica, porque un niño
 * registrado a tiempo puede cumplir 7 y aún hay que poder corregir sus datos.
 */
function validarNino(array $d, bool $esNuevo = false): array
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
        } elseif ($esNuevo && $d['fecha_nacimiento'] <= Nino::fechaLimiteNacimiento()) {
            // Fechas sin hora, para que un niño que cumple 7 hoy no aparezca con 6.
            $anios = (new DateTime($d['fecha_nacimiento']))->diff(new DateTime('today'))->y;
            $errores[] = 'El sistema registra niños menores de ' . Nino::EDAD_MAXIMA_ANIOS
                       . ' años. Según la fecha de nacimiento, este niño tiene ' . $anios . ' años.';
        }
    }
    if (!in_array($d['sexo'], ['M', 'F'], true)) $errores[] = 'Debe seleccionar el sexo.';
    if ($d['id_sector'] === '') $errores[] = 'Debe seleccionar el sector.';

    // Al menos un responsable del niño: la madre (o quien lo cuide) o el padre.
    if ($d['nombre_madre'] === '' && $d['nombre_padre'] === '') {
        $errores[] = 'Debe ingresar el nombre de la madre o responsable, o el del padre. Al menos uno es obligatorio.';
    }

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
