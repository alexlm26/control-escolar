<?php
include "conexion.php";
session_start();

$id_noticia = intval($_GET['id']);
$id_usuario = intval($_GET['id_usuario']);

$q = $conexion->query("SELECT likes FROM noticias WHERE id_noticia = $id_noticia");
$row = $q->fetch_assoc();

$q2 = $conexion->query("SELECT COUNT(*) AS total FROM likes_usuarios WHERE id_usuario = $id_usuario AND id_noticia = $id_noticia");
$l = $q2->fetch_assoc();

echo json_encode([
  'likes' => $row['likes'],
  'dio_like' => $l['total'] > 0 ? 1 : 0
]);
?>
