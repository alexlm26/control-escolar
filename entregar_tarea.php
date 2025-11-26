<?php
include "conexion.php";
session_start();

if ($_SESSION['rol'] != 1) {
    header("Location: login.php");
    exit;
}

if ($_POST && $_FILES) {
    $tarea_id = $_POST['tarea_id'];
    $comentario = $_POST['comentario_alumno'];
    
    // Obtener id_alumno
    $stmt = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();
    
    // Subir archivo
    $archivo = $_FILES['archivo_alumno'];
    $nombre_archivo = uniqid() . '_' . time() . '_' . $archivo['name'];
    $ruta = "uploads/tareas/alumno/" . $nombre_archivo;
    
    if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
        $stmt = $conexion->prepare("INSERT INTO entregas_tareas (id_tarea, id_alumno, archivo_alumno, nombre_archivo_original, comentario_alumno) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $tarea_id, $alumno['id_alumno'], $nombre_archivo, $archivo['name'], $comentario);
        
        if ($stmt->execute()) {
            // Obtener id_clase para redirección
            $stmt = $conexion->prepare("SELECT id_clase FROM tareas WHERE id_tarea = ?");
            $stmt->bind_param("i", $tarea_id);
            $stmt->execute();
            $tarea = $stmt->get_result()->fetch_assoc();
            
            header("Location: detalle_clase.php?id=" . $tarea['id_clase'] . "&success=1");
        }
    }
}
?>