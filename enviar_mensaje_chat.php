<?php
include "conexion.php";
session_start();
$id_usuario = $_SESSION['id_usuario'];
$id_chat = $_POST['id_chat'];
$mensaje = $_POST['mensaje'];

$stmt = $conexion->prepare("INSERT INTO mensajes (id_chat, id_usuario_envia, mensaje) VALUES (?,?,?)");
$stmt->bind_param("iis",$id_chat,$id_usuario,$mensaje);
$stmt->execute();
echo 'ok';
