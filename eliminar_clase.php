<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_clase = $_GET['id_clase'] ?? '';

if ($id_clase) {
    // Eliminar asignaciones primero
    $conexion->query("DELETE FROM asignacion WHERE id_clase = $id_clase");
    // Eliminar horarios
    $conexion->query("DELETE FROM horarios_clase WHERE id_clase = $id_clase");
    // Eliminar clase
    $conexion->query("DELETE FROM clase WHERE id_clase = $id_clase");
    
    header("Location: ../coordinador.php?seccion=clases&exito=clase_eliminada");
} else {
    header("Location: ../coordinador.php?seccion=clases&error=sin_clase");
}
?>