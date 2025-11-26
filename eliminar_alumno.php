<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_alumno = $_GET['id_alumno'] ?? '';

if ($id_alumno) {
    // Por ahora solo cambia el estado
    $conexion->query("UPDATE alumno SET estado = '4' WHERE id_alumno = $id_alumno");
    header("Location: ../coordinador.php?seccion=alumnos&exito=alumno_eliminado");
} else {
    header("Location: ../coordinador.php?seccion=alumnos&error=sin_alumno");
}
?>