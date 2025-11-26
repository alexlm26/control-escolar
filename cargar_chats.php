<?php
include "conexion.php";
session_start();
$id_usuario=$_SESSION['id_usuario'];

$queryChats=$conexion->prepare("
    SELECT c.id_chat,
           u.id_usuario,
           u.nombre,
           u.apellidos,
           u.foto,
           (SELECT mensaje FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) AS ultimo_mensaje,
           (SELECT COUNT(*) FROM mensajes WHERE id_chat=c.id_chat AND id_usuario_envia!=? AND leido=0) AS sin_leer
    FROM chats c
    JOIN usuario u ON (u.id_usuario = IF(c.usuario1 = ?, c.usuario2, c.usuario1))
    WHERE c.usuario1=? OR c.usuario2=?
    ORDER BY (SELECT fecha_envio FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) DESC
");
$queryChats->bind_param("iiii",$id_usuario,$id_usuario,$id_usuario,$id_usuario);
$queryChats->execute();
$res=$queryChats->get_result();
$chats=$res->fetch_all(MYSQLI_ASSOC);

echo json_encode($chats);
?>
