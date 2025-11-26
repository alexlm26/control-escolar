<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id_alumno']) || !isset($_GET['id_grupo'])) {
    header("Location: ../coordinador.php?seccion=grupos&error=Datos incompletos");
    exit;
}

$id_alumno = intval($_GET['id_alumno']);
$id_grupo = intval($_GET['id_grupo']);

// Actualizar el registro para marcarlo como inactivo (soft delete)
$sql_update = "UPDATE alumno_grupo SET activo = 0, fecha_salida = NOW() WHERE id_alumno = ? AND id_grupo = ? AND activo = 1";
$stmt_update = $conexion->prepare($sql_update);
$stmt_update->bind_param("ii", $id_alumno, $id_grupo);

if ($stmt_update->execute()) {
    if ($stmt_update->affected_rows > 0) {
        header("Location: ../detalle_grupo.php?id=$id_grupo&mensaje=Alumno eliminado del grupo correctamente");
    } else {
        header("Location: ../detalle_grupo.php?id=$id_grupo&error=El alumno no estaba en este grupo");
    }
} else {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al eliminar alumno del grupo");
}
?>