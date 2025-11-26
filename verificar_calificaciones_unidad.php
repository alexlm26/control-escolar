<?php
session_start();
include "conexion.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_clase = $_POST['id_clase'] ?? 0;
    $unidad = $_POST['unidad'] ?? 0;
    
    if ($id_clase > 0 && $unidad > 0) {
        $query = $conexion->prepare("
            SELECT COUNT(*) as total 
            FROM calificacion_clase cc
            INNER JOIN asignacion a ON cc.id_asignacion = a.id_asignacion
            WHERE a.id_clase = ? AND cc.unidad = ?
        ");
        $query->bind_param("ii", $id_clase, $unidad);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        
        echo json_encode([
            'calificaciones_existentes' => $result['total'] > 0
        ]);
    } else {
        echo json_encode([
            'calificaciones_existentes' => false
        ]);
    }
}
?>