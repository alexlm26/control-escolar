<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_POST['id_grupo'])) {
    header("Location: ../coordinador.php?seccion=grupos&error=Datos incompletos");
    exit;
}

$id_grupo = intval($_POST['id_grupo']);
$nombre = trim($_POST['nombre']);
$semestre = intval($_POST['semestre']);
$capacidad_maxima = intval($_POST['capacidad_maxima']);
$tutor_asignado = !empty($_POST['tutor_asignado']) ? intval($_POST['tutor_asignado']) : NULL;
$activo = intval($_POST['activo']);

// Validaciones básicas
if (empty($nombre) || $semestre < 1 || $semestre > 12 || $capacidad_maxima < 1) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Datos inválidos");
    exit;
}

// Verificar si el nombre ya existe (excluyendo el grupo actual)
$sql_verificar = "SELECT id_grupo FROM grupo WHERE nombre = ? AND id_grupo != ?";
$stmt_verificar = $conexion->prepare($sql_verificar);
$stmt_verificar->bind_param("si", $nombre, $id_grupo);
$stmt_verificar->execute();

if ($stmt_verificar->get_result()->num_rows > 0) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Ya existe un grupo con ese nombre");
    exit;
}

// Actualizar grupo
$sql_update = "UPDATE grupo SET nombre = ?, semestre = ?, capacidad_maxima = ?, tutor_asignado = ?, activo = ?, fecha_modificacion = NOW() WHERE id_grupo = ?";
$stmt_update = $conexion->prepare($sql_update);
$stmt_update->bind_param("siiiii", $nombre, $semestre, $capacidad_maxima, $tutor_asignado, $activo, $id_grupo);

if ($stmt_update->execute()) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&mensaje=Grupo actualizado correctamente");
} else {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al actualizar el grupo");
}
?>