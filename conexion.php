<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = array();
$rol_nombre = 'Desconocido';

// Credenciales por defecto
$host = "pdb1049.awardspace.net";
$user = "4701135_control";
$pass = "3elemento";
$db = "4701135_control";

if(isset($_SESSION['rol'])){
    switch($_SESSION['rol']){
        case 1: 
            $usuario[0] = $host;
            $usuario[1] = $user;
            $usuario[2] = $pass;
            $usuario[3] = $db;
            break;
        case 2:
            $usuario[0] = $host;
            $usuario[1] = $user;
            $usuario[2] = $pass;
            $usuario[3] = $db;
            break;
        case 3:
            $usuario[0] = $host;
            $usuario[1] = $user;
            $usuario[2] = $pass;
            $usuario[3] = $db;
            break;
        default:
            $usuario[0] = $host;
            $usuario[1] = $user;
            $usuario[2] = $pass;
            $usuario[3] = $db;
            break;
    }
} else {
    // Si no hay sesión de rol, usar credenciales por defecto
    $usuario[0] = $host;
    $usuario[1] = $user;
    $usuario[2] = $pass;
    $usuario[3] = $db;
}

// Verificar que el array esté completo antes de conectar
if (!empty($usuario[0]) && !empty($usuario[1]) && !empty($usuario[2]) && !empty($usuario[3])) {
    $conexion = new mysqli($usuario[0], $usuario[1], $usuario[2], $usuario[3]);
    
    if ($conexion->connect_error) {
        die("ERROR DE CONEXIÓN: " . $conexion->connect_error);
    }
    
    $conexion->query("SET time_zone = '-06:00'");
} else {
    die("ERROR: Credenciales de base de datos incompletas");
}
?>