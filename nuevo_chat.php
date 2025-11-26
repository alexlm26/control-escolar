<?php
include "conexion.php";
session_start();

$id_usuario = $_SESSION['id_usuario'];
$usuario_destino = trim($_POST['usuario_destino'] ?? '');

if(empty($usuario_destino)){
    echo json_encode(["error"=>"Campo vacío"]);
    exit;
}

// Buscar usuario destino por clave o correo
$stmt = $conexion->prepare("SELECT id_usuario, nombre, apellidos, foto FROM usuario WHERE clave=? OR correo=? LIMIT 1");
$stmt->bind_param("ss",$usuario_destino,$usuario_destino);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows===0){
    echo json_encode(["error"=>"Usuario no encontrado"]);
    exit;
}
$row = $result->fetch_assoc();
$id_destino = $row['id_usuario'];

// No permitir chat consigo mismo
if($id_destino == $id_usuario){
    echo json_encode(["error"=>"No puedes crear chat contigo mismo"]);
    exit;
}

// Verificar si ya existe chat
$check = $conexion->prepare("SELECT id_chat FROM chats WHERE (usuario1=? AND usuario2=?) OR (usuario1=? AND usuario2=?) LIMIT 1");
$check->bind_param("iiii",$id_usuario,$id_destino,$id_destino,$id_usuario);
$check->execute();
$resCheck = $check->get_result();
if($resCheck->num_rows>0){
    $chat = $resCheck->fetch_assoc();
    echo json_encode([
        "id_chat"=>$chat['id_chat'],
        "nombre"=>$row['nombre'],
        "apellidos"=>$row['apellidos'],
        "foto"=>$row['foto']
    ]);
    exit;
}

// Crear nuevo chat
$insert = $conexion->prepare("INSERT INTO chats (usuario1, usuario2) VALUES (?,?)");
$insert->bind_param("ii",$id_usuario,$id_destino);
if($insert->execute()){
    echo json_encode([
        "id_chat"=>$insert->insert_id,
        "nombre"=>$row['nombre'],
        "apellidos"=>$row['apellidos'],
        "foto"=>$row['foto']
    ]);
} else {
    echo json_encode(["error"=>"No se pudo crear chat"]);
}
?>
