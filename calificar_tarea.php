<?php
ob_start();
include "conexion.php";
session_start();

if ($_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit;
}

if ($_POST) {
    $entrega_id = $_POST['entrega_id'];
    $calificacion = $_POST['calificacion'];
    $comentario = $_POST['comentario_profesor'];
    
    $stmt = $conexion->prepare("UPDATE entregas_tareas SET calificacion = ?, comentario_profesor = ?, fecha_calificacion = NOW(), estado = 'calificado' WHERE id_entrega = ?");
    $stmt->bind_param("dsi", $calificacion, $comentario, $entrega_id);
    
    if ($stmt->execute()) {
        // Redirigir a la página anterior (desde donde vino el formulario)
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&calificado=1");
        exit;
    } else {
        // Si hay error, también redirigir atrás
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&error=1");
        exit;
    }
}
?>