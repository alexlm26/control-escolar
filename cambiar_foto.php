<?php
include "conexion.php";

if(isset($_FILES['foto']) && isset($_POST['id_usuario'])){
    $id = $_POST['id_usuario'];
    $foto = $_FILES['foto'];

    // Primero, obtener el nombre de la foto actual del usuario
    $stmt_select = $conexion->prepare("SELECT foto FROM usuario WHERE id_usuario = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $stmt_select->bind_result($foto_actual);
    $stmt_select->fetch();
    $stmt_select->close();

    $nombreArchivo = "user_".$id."_".time().".jpg";
    $rutaDestino = "img/usuarios/".$nombreArchivo;

    if(move_uploaded_file($foto['tmp_name'], $rutaDestino)){
        // Actualizar la base de datos con la nueva foto
        $stmt = $conexion->prepare("UPDATE usuario SET foto=? WHERE id_usuario=?");
        $stmt->bind_param("si", $nombreArchivo, $id);
        $stmt->execute();
        $stmt->close();

        // Eliminar la foto anterior si existe y no es default.jpg
        if($foto_actual && $foto_actual !== "default.jpg" && file_exists("img/usuarios/".$foto_actual)){
            unlink("img/usuarios/".$foto_actual);
        }
    }
}

header("Location: ".$_SERVER['HTTP_REFERER']);
exit;
?>