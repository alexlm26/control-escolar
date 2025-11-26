<?php
include "conexion.php";
include "header.php";

$id_usuario_envia = $_SESSION['id_usuario'];
$destinatario = $_POST['destinatario'] ?? '';
$mensaje = $_POST['mensaje'] ?? '';

if(!$destinatario || !$mensaje) exit('Faltan datos');

// Buscar usuario destinatario por correo o clave
$stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = ? OR clave = ?");
$stmt->bind_param("ss", $destinatario, $destinatario);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows == 0) exit('Destinatario no encontrado');
$usuario = $res->fetch_assoc();
$id_usuario_recibe = $usuario['id_usuario'];

// Insertar mensaje
$stmt = $conexion->prepare("INSERT INTO mensajes (id_usuario_envia, id_usuario_recibe, mensaje, fecha_envio) VALUES (?,?,?,NOW())");
$stmt->bind_param("iis", $id_usuario_envia, $id_usuario_recibe, $mensaje);
if($stmt->execute()) echo 'ok';
else echo 'Error al enviar mensaje';
?>
