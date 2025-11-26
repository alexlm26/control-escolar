<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$filtro = $_GET['filtro'] ?? ''; // 'academia' o 'carrera'
$id_filtro = $_GET['id_filtro'] ?? 0;

// Obtener id_carrera del coordinador
$id_usuario = $_SESSION['id_usuario'];
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$result_carrera = $conexion->query($sql_carrera);
$carrera_data = $result_carrera->fetch_assoc();
$id_carrera_coordinador = $carrera_data['id_carrera'];

// Obtener id_coordinador
$sql_coordinador = "SELECT id_coordinador FROM coordinador WHERE id_usuario = $id_usuario";
$result_coordinador = $conexion->query($sql_coordinador);
$coordinador_data = $result_coordinador->fetch_assoc();
$id_coordinador = $coordinador_data['id_coordinador'];

$profesores = [];

if ($filtro === 'academia' && $id_filtro > 0) {
    // Filtrar por academia
    $sql = "
        SELECT DISTINCT p.id_profesor, u.nombre, u.apellidos, u.clave
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        INNER JOIN profesor_academia pa ON p.id_profesor = pa.id_profesor
        WHERE pa.id_academia = ? 
        AND p.estado = '1'
        AND p.id_coordinador = ?
        ORDER BY u.nombre, u.apellidos
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id_filtro, $id_coordinador);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $profesores[] = $row;
    }
} elseif ($filtro === 'carrera') {
    // Filtrar por carrera (todos los profesores del coordinador)
    $sql = "
        SELECT p.id_profesor, u.nombre, u.apellidos, u.clave
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.id_coordinador = ? 
        AND p.estado = '1'
        ORDER BY u.nombre, u.apellidos
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_coordinador);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $profesores[] = $row;
    }
} else {
    // Sin filtro - todos los profesores del coordinador
    $sql = "
        SELECT p.id_profesor, u.nombre, u.apellidos, u.clave
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.id_coordinador = ? 
        AND p.estado = '1'
        ORDER BY u.nombre, u.apellidos
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_coordinador);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $profesores[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($profesores);
?>