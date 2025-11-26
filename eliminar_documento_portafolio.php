<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] == '1') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_portafolio = $_POST['id_portafolio'] ?? 0;
    
    if ($id_portafolio <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    // Primero obtener la ruta del archivo
    $sql_select = "SELECT ruta_archivo FROM portafolio_profesor WHERE id_portafolio = ?";
    $stmt = $conexion->prepare($sql_select);
    $stmt->bind_param("i", $id_portafolio);
    $stmt->execute();
    $result = $stmt->get_result();
    $documento = $result->fetch_assoc();
    
    if ($documento) {
        // Eliminar archivo físico
        if (file_exists($documento['ruta_archivo'])) {
            unlink($documento['ruta_archivo']);
        }
        
        // Eliminar registro de la base de datos
        $sql_delete = "DELETE FROM portafolio_profesor WHERE id_portafolio = ?";
        $stmt = $conexion->prepare($sql_delete);
        $stmt->bind_param("i", $id_portafolio);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Documento eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar de la base de datos']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
    }
}
?>