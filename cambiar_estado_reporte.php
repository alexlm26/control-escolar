<?php
session_start();
include "../conexion.php";

// Verificar permisos - solo coordinadores y prefectos
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != '3' && $_SESSION['rol'] != '5')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_reporte = intval($_POST['id_reporte']);
$tipo_reporte = $_POST['tipo_reporte'];
$nuevo_estado = $_POST['nuevo_estado'];

// Validar estado
$estados_permitidos = ['activo', 'resuelto', 'archivado'];
if (!in_array($nuevo_estado, $estados_permitidos)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Estado no válido']);
    exit;
}

// Actualizar según el tipo de reporte
if ($tipo_reporte == 'individual') {
    $sql = "UPDATE reportes_conducta_individual SET estado = ? WHERE id_reporte_individual = ?";
} else {
    $sql = "UPDATE reportes_conducta_grupal SET estado = ? WHERE id_reporte_grupal = ?";
}

$stmt = $conexion->prepare($sql);
$stmt->bind_param("si", $nuevo_estado, $id_reporte);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $conexion->error]);
}

exit;
?>