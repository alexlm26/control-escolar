<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

if ($_POST) {
    $id_clase = $_POST['id_clase'] ?? '';
    $id_materia = $_POST['id_materia'] ?? '';
    $id_profesor = $_POST['id_profesor'] ?? '';
    $id_salon = $_POST['id_salon'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    $capacidad = $_POST['capacidad'] ?? '';
    $activo = $_POST['activo'] ?? '';
    
    if ($id_clase) {
        // Actualizar clase
        $sql_clase = "UPDATE clase SET 
                     id_materia = $id_materia, 
                     id_profesor = $id_profesor, 
                     id_salon = $id_salon, 
                     periodo = '$periodo', 
                     capacidad = $capacidad, 
                     activo = $activo 
                     WHERE id_clase = $id_clase";
        
        if ($conexion->query($sql_clase)) {
            header("Location: ../coordinador.php?seccion=clases&exito=clase_editada");
        } else {
            header("Location: ../coordinador.php?seccion=clases&error=error_editar");
        }
    } else {
        header("Location: ../coordinador.php?seccion=clases&error=sin_clase");
    }
} else {
    header("Location: ../coordinador.php");
}
?>