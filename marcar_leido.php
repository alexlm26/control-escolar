<?php
include 'conexion.php';
session_start();

$id = $_POST['id'];

$sql = "UPDATE notificaciones SET leido = 1 WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

echo "OK";
?>
