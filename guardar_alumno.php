<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibir datos del formulario
    $id_alumno = $_POST['id_alumno'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $semestre = $_POST['semestre'] ?? '';
    $especialidad = trim($_POST['especialidad'] ?? '');
    $estado = $_POST['estado'] ?? '';

    // Validaciones básicas
    if (empty($id_alumno) || empty($nombre) || empty($apellidos) || empty($correo)) {
        header("Location: ../coordinador.php?seccion=alumnos&error=campos_vacios");
        exit;
    }

    // Convertir tipos
    $id_alumno = intval($id_alumno);
    $semestre = intval($semestre);
    $estado = intval($estado);

    // Si especialidad está vacío, establecer valor por defecto
    if (empty($especialidad)) {
        $especialidad = "Sin especificar";
    }

    try {
        // Iniciar transacción
        $conexion->begin_transaction();

        // 1. Obtener el id_usuario del alumno
        $sql_get_user = "SELECT id_usuario FROM alumno WHERE id_alumno = ?";
        $stmt_get_user = $conexion->prepare($sql_get_user);
        $stmt_get_user->bind_param("i", $id_alumno);
        $stmt_get_user->execute();
        $result_user = $stmt_get_user->get_result();
        
        if ($result_user->num_rows === 0) {
            throw new Exception("Alumno no encontrado");
        }
        
        $alumno_data = $result_user->fetch_assoc();
        $id_usuario = $alumno_data['id_usuario'];
        $stmt_get_user->close();

        // 2. Actualizar datos en la tabla usuario
        $sql_usuario = "UPDATE usuario SET nombre = ?, apellidos = ?, correo = ? WHERE id_usuario = ?";
        $stmt_usuario = $conexion->prepare($sql_usuario);
        $stmt_usuario->bind_param("sssi", $nombre, $apellidos, $correo, $id_usuario);
        
        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al actualizar datos del usuario: " . $stmt_usuario->error);
        }
        $stmt_usuario->close();

        // 3. Actualizar datos en la tabla alumno (sin promedio)
        $sql_alumno = "UPDATE alumno SET semestre = ?, especialidad = ?, estado = ? WHERE id_alumno = ?";
        $stmt_alumno = $conexion->prepare($sql_alumno);
        
        // CORRECCIÓN: La cadena de tipos debe ser "isii" (integer, string, integer, integer)
        $stmt_alumno->bind_param("isii", $semestre, $especialidad, $estado, $id_alumno);
        
        if (!$stmt_alumno->execute()) {
            throw new Exception("Error al actualizar datos del alumno: " . $stmt_alumno->error);
        }
        $stmt_alumno->close();

        // Confirmar transacción
        $conexion->commit();

        // Redirigir con mensaje de éxito
        header("Location: ../coordinador.php?seccion=alumnos&success=alumno_actualizado");
        exit;

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        // Registrar error
        error_log("Error al actualizar alumno: " . $e->getMessage());
        
        // Redirigir con mensaje de error
        header("Location: ../coordinador.php?seccion=alumnos&error=actualizacion_fallida");
        exit;
    }

} else {
    // Si no es POST, redirigir
    header("Location: ../coordinador.php?seccion=alumnos");
    exit;
}
?>