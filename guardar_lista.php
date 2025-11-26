<?php
// Iniciar sesión al principio del archivo
session_start();

include "conexion.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_clase = $_POST['id_clase'] ?? 0;
    $fecha_hoy = $_POST['fecha_hoy'] ?? date('Y-m-d');
    $asistencias = $_POST['asistencia'] ?? [];
    
    // Validar que el profesor tiene acceso a esta clase
    $id_usuario = $_SESSION['id_usuario'];
    $stmt = $conexion->prepare("
        SELECT c.id_clase 
        FROM clase c 
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor 
        WHERE p.id_usuario = ? AND c.id_clase = ?
    ");
    $stmt->bind_param("ii", $id_usuario, $id_clase);
    $stmt->execute();
    $tiene_acceso = $stmt->get_result()->num_rows > 0;
    
    if (!$tiene_acceso || $id_clase == 0) {
        $_SESSION['error'] = "No tienes acceso a esta clase";
        header("Location: detalle_clase.php?id=" . $id_clase . "&seccion=lista");
        exit;
    }
    
    // DEBUG: Mostrar datos recibidos
    error_log("=== GUARDAR_LISTA DEBUG ===");
    error_log("ID Clase: " . $id_clase);
    error_log("Fecha: " . $fecha_hoy);
    error_log("Total asistencias recibidas: " . count($asistencias));
    
    $contador_guardados = 0;
    $errores = [];
    
    // Preparar statement para insertar/actualizar asistencias
    $stmt_insert = $conexion->prepare("
        INSERT INTO asistencia (id_clase, id_alumno, fecha, estado_asistencia, comentario) 
        VALUES (?, ?, ?, ?, '') 
        ON DUPLICATE KEY UPDATE 
        estado_asistencia = VALUES(estado_asistencia),
        comentario = VALUES(comentario)
    ");
    
    foreach ($asistencias as $id_alumno => $valor) {
        // Solo procesar si el valor no está vacío
        if ($valor === '') {
            continue;
        }
        
        // Convertir valor numérico a estado de texto
        $estado = '';
        switch ($valor) {
            case '1': 
                $estado = 'presente'; 
                break;
            case '0': 
                $estado = 'ausente'; 
                break;
            case '2': 
                $estado = 'justificado'; 
                break;
            default: 
                // Usar break en lugar de continue para evitar el warning
                break;
        }
        
        // Si no se asignó un estado válido, saltar a la siguiente iteración
        if ($estado === '') {
            continue;
        }
        
        error_log("Procesando alumno $id_alumno: valor=$valor, estado=$estado");
        
        $stmt_insert->bind_param("iiss", $id_clase, $id_alumno, $fecha_hoy, $estado);
        
        if ($stmt_insert->execute()) {
            $contador_guardados++;
            error_log("✅ Alumno $id_alumno guardado correctamente");
        } else {
            $errores[] = "Error al guardar alumno $id_alumno: " . $stmt_insert->error;
            error_log("❌ Error alumno $id_alumno: " . $stmt_insert->error);
        }
    }
    
    $stmt_insert->close();
    
    // Verificar resultados
    error_log("Total guardados: $contador_guardados");
    error_log("Total errores: " . count($errores));
    
    if (empty($errores)) {
        if ($contador_guardados > 0) {
            $_SESSION['success'] = "Lista guardada correctamente para $contador_guardados alumnos";
        } else {
            $_SESSION['info'] = "No se guardaron asistencias (todos los campos estaban vacíos)";
        }
    } else {
        $_SESSION['error'] = "Errores al guardar: " . implode(", ", $errores);
    }
    
    // DEBUG: Verificar qué hay en la base de datos después de guardar
    error_log("=== VERIFICACIÓN POST-GUARDADO ===");
    $stmt_verify = $conexion->prepare("
        SELECT id_alumno, estado_asistencia 
        FROM asistencia 
        WHERE id_clase = ? AND fecha = ?
    ");
    $stmt_verify->bind_param("is", $id_clase, $fecha_hoy);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    while ($row = $result_verify->fetch_assoc()) {
        error_log("BD - Alumno {$row['id_alumno']}: {$row['estado_asistencia']}");
    }
    $stmt_verify->close();
    
    // Redirigir de vuelta
    header("Location: detalle_clase.php?id=" . $id_clase . "&seccion=lista&guardado=exitoso");
    exit;
    
} else {
    header("Location: clases.php");
    exit;
}
?>