<?php
include "conexion.php";
session_start();

if (!isset($_SESSION['id_usuario'])) exit;

$id_usuario = $_SESSION['id_usuario'];

$sql = "UPDATE notificaciones SET leido = 1 WHERE id_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();

echo "OK";
?>
