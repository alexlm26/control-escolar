<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// Obtener id_carrera del coordinador
$id_usuario = $_SESSION['id_usuario'];
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$result_carrera = $conexion->query($sql_carrera);
$carrera_data = $result_carrera->fetch_assoc();
$id_carrera_coordinador = $carrera_data['id_carrera'];

// Obtener academias relacionadas con la carrera del coordinador
$sql = "
    SELECT a.id_academia, a.nombre, 
           COALESCE(e.nombre, 'Carrera General') as especialidad_nombre,
           a.id_carrera, a.id_especialidad
    FROM academia a
    LEFT JOIN especialidad e ON a.id_especialidad = e.id_especialidad
    WHERE (a.id_carrera = ? OR e.id_carrera = ? OR a.id_carrera IS NULL)
    AND a.activo = 1
    ORDER BY a.nombre
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id_carrera_coordinador, $id_carrera_coordinador);
$stmt->execute();
$result = $stmt->get_result();

$academias = [];
while ($row = $result->fetch_assoc()) {
    $academias[] = $row;
}

header('Content-Type: application/json');
echo json_encode($academias);
?>