<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/auth/Auth.php';

$accion = $_GET['accion'] ?? 'inicio';

switch ($accion) {

    case 'login':
        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if (Auth::login($usuario, $contrasena)) {
            header('Location: ' . APP_URL . '/index.php?accion=panel');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
        require __DIR__ . '/../views/auth/login.php';
        break;

    case 'logout':
        Auth::logout();
        header('Location: ' . APP_URL . '/index.php');
        exit;

    case 'panel':
        if (!Auth::autenticado()) {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        $u = Auth::usuario();
        echo "<h1>Bienvenido, " . htmlspecialchars($u['nombre']) . "</h1>";
        echo "<p>Rol: " . htmlspecialchars($u['rol']) . "</p>";
        echo '<a href="' . APP_URL . '/index.php?accion=logout">Cerrar sesión</a>';
        break;

    case 'inicio':
    default:
        if (Auth::autenticado()) {
            header('Location: ' . APP_URL . '/index.php?accion=panel');
            exit;
        }
        require __DIR__ . '/../views/auth/login.php';
        break;
}