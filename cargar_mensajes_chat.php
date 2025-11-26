<?php
include "conexion.php";
session_start();
$id_usuario = $_SESSION['id_usuario'];
$id_chat = $_GET['id_chat'];

$stmt = $conexion->prepare("
    SELECT m.id_usuario_envia, m.mensaje, m.fecha_envio, u.nombre
    FROM mensajes m
    JOIN usuario u ON m.id_usuario_envia=u.id_usuario
    WHERE id_chat=?
    ORDER BY fecha_envio
");
$stmt->bind_param("i",$id_chat);
$stmt->execute();
$res = $stmt->get_result();
$mensajes_raw = $res->fetch_all(MYSQLI_ASSOC);

// Formatear fecha antes de enviar
$mensajes = [];
foreach($mensajes_raw as $m){
    $m['fecha_envio'] = date("d/m/Y H:i", strtotime($m['fecha_envio']));
    $mensajes[] = $m;
}

// Marcar como leídos
$conexion->query("UPDATE mensajes SET leido=1 WHERE id_chat=$id_chat AND id_usuario_envia<>$id_usuario");

echo json_encode($mensajes);
