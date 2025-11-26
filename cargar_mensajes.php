<?php
include "conexion.php";
session_start();
$id_usuario = $_SESSION['id_usuario'];

// Traer todos los mensajes enviados o recibidos
$query = $conexion->prepare("
    SELECT m.*, u.nombre, u.apellidos
    FROM mensajes m
    JOIN usuario u ON m.id_usuario_envia = u.id_usuario
    WHERE m.id_usuario_envia = ? OR m.id_usuario_recibe = ?
    ORDER BY m.fecha_envio ASC
");
$query->bind_param("ii", $id_usuario, $id_usuario);
$query->execute();
$result = $query->get_result();

$mensajes = [];
while($row = $result->fetch_assoc()){
    $mensajes[] = [
        'id_usuario_envia' => $row['id_usuario_envia'],
        'id_usuario_recibe' => $row['id_usuario_recibe'],
        'mensaje' => $row['mensaje'],
        'fecha' => $row['fecha_envio'],
        'nombre' => $row['nombre'] . ' ' . $row['apellidos']
    ];
}
echo json_encode($mensajes);
?>
