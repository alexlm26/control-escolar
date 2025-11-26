<?php
include 'conexion.php';
session_start();

$id_usuario = $_SESSION['id_usuario'];

$sql = "SELECT id, mensaje, fecha, leido
        FROM notificaciones
        WHERE id_usuario = ?
        ORDER BY fecha DESC
        LIMIT 10";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

$notificaciones = [];
while ($row = $result->fetch_assoc()) {
    $notificaciones[] = $row;
}

echo json_encode($notificaciones);
?>
