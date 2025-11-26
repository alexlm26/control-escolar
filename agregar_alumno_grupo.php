<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_POST['id_alumno']) || !isset($_POST['id_grupo'])) {
    header("Location: ../detalle_grupo.php?id=" . ($_POST['id_grupo'] ?? '') . "&error=Datos incompletos");
    exit;
}

$id_alumno = intval($_POST['id_alumno']);
$id_grupo = intval($_POST['id_grupo']);

// Verificar si el alumno ya está en un grupo activo
$sql_verificar = "SELECT 1 FROM alumno_grupo WHERE id_alumno = ? AND activo = 1";
$stmt_verificar = $conexion->prepare($sql_verificar);
$stmt_verificar->bind_param("i", $id_alumno);
$stmt_verificar->execute();

if ($stmt_verificar->get_result()->num_rows > 0) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=El alumno ya está en otro grupo activo");
    exit;
}

// Verificar que el alumno existe y está activo
$sql_alumno = "SELECT 1 FROM alumno WHERE id_alumno = ? AND estado = '1'";
$stmt_alumno = $conexion->prepare($sql_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();

if ($stmt_alumno->get_result()->num_rows === 0) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Alumno no encontrado o inactivo");
    exit;
}

// Insertar alumno en el grupo
$sql_insert = "INSERT INTO alumno_grupo (id_alumno, id_grupo, activo) VALUES (?, ?, 1)";
$stmt_insert = $conexion->prepare($sql_insert);
$stmt_insert->bind_param("ii", $id_alumno, $id_grupo);

if ($stmt_insert->execute()) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&mensaje=Alumno agregado correctamente al grupo");
} else {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al agregar alumno al grupo");
}
?>