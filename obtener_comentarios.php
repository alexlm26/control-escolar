<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isset($_GET['entrega_id'])) {
    $entrega_id = $_GET['entrega_id'];
    
    // Obtener los comentarios de la entrega
    $query = $conexion->prepare("
        SELECT comentario_alumno, comentario_profesor 
        FROM entregas_tareas 
        WHERE id_entrega = ?
    ");
    $query->bind_param("i", $entrega_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($entrega = $result->fetch_assoc()) {
        echo json_encode([
            'comentario_alumno' => $entrega['comentario_alumno'],
            'comentario_profesor' => $entrega['comentario_profesor']
        ]);
    } else {
        echo json_encode([
            'comentario_alumno' => null,
            'comentario_profesor' => null
        ]);
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'ID de entrega no proporcionado']);
}
?>