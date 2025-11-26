<?php
ob_start();
include "conexion.php";
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}
include "conexion.php";

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $conexion->query("UPDATE noticias SET visitas = visitas + 1 WHERE id_noticia = $id");
}
?>
