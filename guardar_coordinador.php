<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Verificar que sea administrador (coordinador de carrera 0)
$id_usuario = $_SESSION['id_usuario'];
$sql_usuario_actual = "SELECT u.id_carrera FROM usuario u WHERE u.id_usuario = $id_usuario";
$usuario_actual = $conexion->query($sql_usuario_actual)->fetch_assoc();

// Solo permitir acceso si es administrador (carrera 0)
if ($usuario_actual['id_carrera'] != 0) {
    header("Location: ../coordinador.php?seccion=coordinadores&error=No tienes permisos para editar coordinadores");
    exit;
}

if ($_POST) {
    $id_coordinador = $_POST['id_coordinador'];
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $correo = trim($_POST['correo']);
    $sueldo = $_POST['sueldo'];
    $estado = $_POST['estado'];
    $id_carrera = $_POST['id_carrera'];
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($correo) || empty($id_carrera)) {
        header("Location: editar_coordinador.php?id_coordinador=$id_coordinador&error=Todos los campos son obligatorios");
        exit;
    }
    
    // Validar formato de email
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header("Location: editar_coordinador.php?id_coordinador=$id_coordinador&error=El correo electrónico no tiene un formato válido");
        exit;
    }
    
    // Verificar si el correo ya existe en otro usuario
    $sql_check_email = "SELECT u.id_usuario 
                       FROM usuario u 
                       INNER JOIN coordinador c ON u.id_usuario = c.id_usuario 
                       WHERE u.correo = '" . $conexion->real_escape_string($correo) . "' 
                       AND c.id_coordinador != $id_coordinador";
    $result_check = $conexion->query($sql_check_email);
    
    if ($result_check && $result_check->num_rows > 0) {
        header("Location: editar_coordinador.php?id_coordinador=$id_coordinador&error=El correo electrónico ya está en uso por otro coordinador");
        exit;
    }
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Obtener el id_usuario del coordinador
        $sql_get_user = "SELECT id_usuario FROM coordinador WHERE id_coordinador = $id_coordinador";
        $result_user = $conexion->query($sql_get_user);
        
        if (!$result_user || $result_user->num_rows === 0) {
            throw new Exception("Coordinador no encontrado");
        }
        
        $coordinador_data = $result_user->fetch_assoc();
        $id_usuario_coordinador = $coordinador_data['id_usuario'];
        
        // Actualizar datos del usuario
        $sql_update_usuario = "UPDATE usuario SET 
                              nombre = '" . $conexion->real_escape_string($nombre) . "',
                              apellidos = '" . $conexion->real_escape_string($apellidos) . "',
                              correo = '" . $conexion->real_escape_string($correo) . "',
                              id_carrera = $id_carrera
                              WHERE id_usuario = $id_usuario_coordinador";
        
        if (!$conexion->query($sql_update_usuario)) {
            throw new Exception("Error al actualizar usuario: " . $conexion->error);
        }
        
        // Actualizar datos del coordinador
        $sql_update_coordinador = "UPDATE coordinador SET 
                                  sueldo = $sueldo,
                                  estado = '$estado',
                                  id_carrera = $id_carrera
                                  WHERE id_coordinador = $id_coordinador";
        
        if (!$conexion->query($sql_update_coordinador)) {
            throw new Exception("Error al actualizar coordinador: " . $conexion->error);
        }
        
        $conexion->commit();
        header("Location: ../coordinador.php?seccion=coordinadores&mensaje=Coordinador actualizado correctamente");
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: editar_coordinador.php?id_coordinador=$id_coordinador&error=" . urlencode($e->getMessage()));
    }
    exit;
} else {
    header("Location: ../coordinador.php?seccion=coordinadores");
}
?>