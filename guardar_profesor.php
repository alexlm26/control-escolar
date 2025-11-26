<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibir datos del formulario
    $id_profesor = $_POST['id_profesor'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $sueldo = $_POST['sueldo'] ?? '';
    $estado = $_POST['estado'] ?? '';

    // Validaciones básicas
    if (empty($id_profesor) || empty($nombre) || empty($apellidos) || empty($correo) || empty($sueldo)) {
        header("Location: ../coordinador.php?seccion=profesores&error=campos_vacios");
        exit;
    }

    // Convertir tipos
    $id_profesor = intval($id_profesor);
    $sueldo = floatval($sueldo);
    $estado = intval($estado);

    try {
        // Iniciar transacción
        $conexion->begin_transaction();

        // 1. Obtener el id_usuario del profesor
        $sql_get_user = "SELECT id_usuario FROM profesor WHERE id_profesor = ?";
        $stmt_get_user = $conexion->prepare($sql_get_user);
        $stmt_get_user->bind_param("i", $id_profesor);
        $stmt_get_user->execute();
        $result_user = $stmt_get_user->get_result();
        
        if ($result_user->num_rows === 0) {
            throw new Exception("Profesor no encontrado");
        }
        
        $profesor_data = $result_user->fetch_assoc();
        $id_usuario = $profesor_data['id_usuario'];
        $stmt_get_user->close();

        // 2. Actualizar datos en la tabla usuario
        $sql_usuario = "UPDATE usuario SET nombre = ?, apellidos = ?, correo = ? WHERE id_usuario = ?";
        $stmt_usuario = $conexion->prepare($sql_usuario);
        $stmt_usuario->bind_param("sssi", $nombre, $apellidos, $correo, $id_usuario);
        
        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al actualizar datos del usuario: " . $stmt_usuario->error);
        }
        $stmt_usuario->close();

        // 3. Actualizar datos en la tabla profesor
        $sql_profesor = "UPDATE profesor SET sueldo = ?, estado = ? WHERE id_profesor = ?";
        $stmt_profesor = $conexion->prepare($sql_profesor);
        $stmt_profesor->bind_param("dii", $sueldo, $estado, $id_profesor);
        
        if (!$stmt_profesor->execute()) {
            throw new Exception("Error al actualizar datos del profesor: " . $stmt_profesor->error);
        }
        $stmt_profesor->close();

        // Confirmar transacción
        $conexion->commit();

        // Redirigir con mensaje de éxito
        header("Location: ../coordinador.php?seccion=profesores&success=profesor_actualizado");
        exit;

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        // Registrar error
        error_log("Error al actualizar profesor: " . $e->getMessage());
        
        // Redirigir con mensaje de error
        header("Location: ../coordinador.php?seccion=profesores&error=actualizacion_fallida");
        exit;
    }

} else {
    // Si no es POST, redirigir
    header("Location: ../coordinador.php?seccion=profesores");
    exit;
}
?>