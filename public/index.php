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

        if (Auth::login($usuario, $contrasena)) {
            Bitacora::registrar(Auth::usuario()['id'], 'login', 'usuario', Auth::usuario()['id'], 'Inicio de sesión');
            // Los niños que ya cumplieron 7 años pasan a egresado (queda en bitácora).
            Nino::egresarPorEdad(Auth::usuario()['id']);
            if (Auth::debeCambiarPass()) {
                header('Location: ' . APP_URL . '/index.php?accion=cambiar_pass');
                exit;
            }
            header('Location: ' . Auth::paginaInicio());
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
        // Alerta del ÚLTIMO CONTROL, igual que en el panel de seguimiento: si
        // el niño se recuperó y su último control salió normal, no hay aviso.
        $stmtA = $db->prepare(
            "SELECT a.nivel, a.descripcion FROM alerta a
             WHERE a.estado = 'activa'
               AND a.id_medicion = (
                   SELECT m.id_medicion FROM medicion m
                   WHERE m.id_nino = :id
                   ORDER BY m.fecha_toma DESC, m.id_medicion DESC LIMIT 1
               )"
        );
        $stmtA->execute([':id' => $idNino]);
        $alertaActual = $stmtA->fetch();

        // Datos del carnet: vacunas, suplementos y citas
        $vacunas        = Vacuna::listarPorNino($idNino);
        $esquemaVacunas = Vacuna::esquemaDelNino($idNino, $nino['fecha_nacimiento']);
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
            'nombre_completo' => Formato::nombrePropio($_POST['nombre_completo'] ?? ''),
            'usuario'         => trim($_POST['usuario'] ?? ''),
            'id_rol'          => $_POST['id_rol'] ?? '',
            'id_sector'       => $_POST['id_sector'] ?? '',
        ];
        // Rol y sector deben existir; enfermería necesita un sector, porque los
        // permisos de escritura dependen de él (Nino::permisoEscritura).
        $roles = array_column(Catalogo::roles(), 'nombre', 'id_rol');
        $errores = array_values(array_filter([
            Validacion::nombrePersona($datos['nombre_completo'], 'Nombre completo', 150, true),
            Validacion::enLista($datos['id_rol'], array_keys($roles), 'Debe seleccionar un rol de la lista.'),
        ]));
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $datos['usuario'])) {
            $errores[] = 'El nombre de usuario debe tener de 3 a 50 caracteres: letras sin tilde, números,'
                       . ' punto, guion o guion bajo. Sin espacios.';
        } elseif (Usuario::usuarioExiste($datos['usuario'])) {
            $errores[] = 'Ese nombre de usuario ya existe.';
        }
        if ($datos['id_sector'] !== '') {
            $errores[] = Validacion::enLista($datos['id_sector'], array_column(Catalogo::sectores(), 'id_sector'),
                'Debe seleccionar un sector de la lista.');
        } elseif (($roles[$datos['id_rol']] ?? '') === 'enfermeria') {
            $errores[] = 'Un usuario de enfermería debe tener un sector asignado.';
        }
        $errores = array_values(array_filter($errores));

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

        // La vacuna debe venir del catálogo, y la fecha estar entre el nacimiento y hoy.
        $sel = $_POST['vacuna_sel'] ?? '';
        $partes = explode('|', $sel);
        $opcionesVacuna = array_map(fn($c) => $c['nombre'] . '|' . $c['dosis'], Vacuna::catalogo());
        $errores = array_values(array_filter([
            Validacion::enLista($sel, $opcionesVacuna, 'Debe seleccionar la vacuna de la lista.'),
            Validacion::fecha($_POST['fecha_aplicacion'] ?? '', 'Fecha de aplicación',
                $nino['fecha_nacimiento'], date('Y-m-d'), 'anterior al nacimiento', 'posterior a hoy'),
            Validacion::texto(trim($_POST['lote'] ?? ''), 'Lote', 50),
            Validacion::enLista($_POST['lugar_vacunacion'] ?? '', array_keys(Vacuna::LUGARES),
                'Debe seleccionar el lugar de vacunación.'),
        ]));

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
            'lugar_vacunacion' => (int)$_POST['lugar_vacunacion'],
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


        // ---------- Inicio / login ----------
    case 'inicio':
    default:
        if (Auth::autenticado() && !Sesion::expiroPorInactividad()) {
            header('Location: ' . Auth::paginaInicio());
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
        // El CUI y el teléfono se aceptan con espacios o guiones (5555-1234),
        // como se acostumbra escribirlos: se quitan antes de validar.
        'cui'              => str_replace([' ', '-'], '', $_POST['cui'] ?? ''),
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
 */
function validarNino(array $d, bool $esNuevo = false): array
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
