<?php
ob_start(); // INICIA BUFFER DE SALIDA
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}
include "conexion.php";


if (!isset($_POST['id']) || !isset($_POST['id_usuario'])) {
  echo "sin_datos";
  exit;
}

$id_noticia = intval($_POST['id']);
$id_usuario = intval($_POST['id_usuario']);

$q = $conexion->query("SELECT * FROM likes_usuarios WHERE id_usuario = $id_usuario AND id_noticia = $id_noticia");
if ($q->num_rows > 0) {
  echo "ya_liked";
  exit;
}

$conexion->query("INSERT INTO likes_usuarios (id_usuario, id_noticia) VALUES ($id_usuario, $id_noticia)");
$conexion->query("UPDATE noticias SET likes = likes + 1 WHERE id_noticia = $id_noticia");

echo "liked";
?>
