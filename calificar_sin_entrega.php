<?php
session_start();
include "conexion.php";

// Debug: Ver qué datos están llegando
error_log("=== DATOS RECIBIDOS EN calificar_sin_entrega.php ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    error_log("Acceso denegado - Usuario: " . ($_SESSION['id_usuario'] ?? 'No definido') . ", Rol: " . ($_SESSION['rol'] ?? 'No definido'));
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug de los datos recibidos
    error_log("id_tarea recibido: " . ($_POST['id_tarea'] ?? 'NO RECIBIDO'));
    error_log("id_alumno recibido: " . ($_POST['id_alumno'] ?? 'NO RECIBIDO'));
    error_log("calificacion recibida: " . ($_POST['calificacion'] ?? 'NO RECIBIDO'));

    // Validar que los datos requeridos estén presentes
    if (!isset($_POST['id_tarea']) || !isset($_POST['id_alumno']) || !isset($_POST['calificacion'])) {
        error_log("ERROR: Datos incompletos");
        $_SESSION['error'] = "Datos incompletos para la calificación. Faltan: " .
                            (!isset($_POST['id_tarea']) ? "id_tarea " : "") .
                            (!isset($_POST['id_alumno']) ? "id_alumno " : "") .
                            (!isset($_POST['calificacion']) ? "calificacion" : "");
        
        // Redirigir a la página anterior si es posible
        if (isset($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            header("Location: detalle_clase.php");
        }
        exit;
    }

    // Obtener y validar datos
    $id_tarea = intval($_POST['id_tarea']);
    $id_alumno = intval($_POST['id_alumno']);
    $calificacion = floatval($_POST['calificacion']);
    $comentario_profesor = $_POST['comentario_profesor'] ?? 'Calificación asignada sin entrega';
    
    error_log("Datos procesados - id_tarea: $id_tarea, id_alumno: $id_alumno, calificacion: $calificacion");

    // Validar que los IDs no sean cero
    if ($id_tarea <= 0 || $id_alumno <= 0) {
        error_log("ERROR: IDs inválidos - id_tarea: $id_tarea, id_alumno: $id_alumno");
        $_SESSION['error'] = "IDs de tarea o alumno inválidos";
        header("Location: detalle_clase.php");
        exit;
    }

    // Obtener información de la tarea para validación y redirección
    $stmt_tarea = $conexion->prepare("
        SELECT t.id_clase, t.puntos_maximos 
        FROM tareas t 
        WHERE t.id_tarea = ?
    ");
    
    if (!$stmt_tarea) {
        error_log("ERROR en preparar consulta de tarea: " . $conexion->error);
        $_SESSION['error'] = "Error interno del sistema";
        header("Location: detalle_clase.php");
        exit;
    }
    
    $stmt_tarea->bind_param("i", $id_tarea);
    
    if (!$stmt_tarea->execute()) {
        error_log("ERROR al ejecutar consulta de tarea: " . $stmt_tarea->error);
        $_SESSION['error'] = "Error al verificar la tarea";
        header("Location: detalle_clase.php");
        exit;
    }
    
    $result_tarea = $stmt_tarea->get_result();
    
    if ($result_tarea->num_rows === 0) {
        error_log("ERROR: Tarea no encontrada - id_tarea: $id_tarea");
        $_SESSION['error'] = "La tarea no existe";
        header("Location: detalle_clase.php");
        exit;
    }
    
    $tarea_info = $result_tarea->fetch_assoc();
    $id_clase = $tarea_info['id_clase'];
    $puntos_maximos = $tarea_info['puntos_maximos'];
    
    error_log("Información de tarea obtenida - id_clase: $id_clase, puntos_maximos: $puntos_maximos");

    // Validar que la calificación no exceda los puntos máximos
    if ($calificacion > $puntos_maximos) {
        error_log("ERROR: Calificación excede máximo - calificacion: $calificacion, max: $puntos_maximos");
        $_SESSION['error'] = "La calificación no puede exceder los $puntos_maximos puntos";
        header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea");
        exit;
    }
    
    // Verificar si ya existe una entrega para este alumno y tarea
    $stmt_check = $conexion->prepare("SELECT id_entrega FROM entregas_tareas WHERE id_tarea = ? AND id_alumno = ?");
    
    if (!$stmt_check) {
        error_log("ERROR en preparar consulta de verificación: " . $conexion->error);
        $_SESSION['error'] = "Error interno del sistema";
        header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea");
        exit;
    }
    
    $stmt_check->bind_param("ii", $id_tarea, $id_alumno);
    
    if (!$stmt_check->execute()) {
        error_log("ERROR al ejecutar consulta de verificación: " . $stmt_check->error);
        $_SESSION['error'] = "Error al verificar entrega existente";
        header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea");
        exit;
    }
    
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar entrega existente
        $entrega = $result->fetch_assoc();
        error_log("Actualizando entrega existente - id_entrega: " . $entrega['id_entrega']);
        
        $stmt = $conexion->prepare("
            UPDATE entregas_tareas 
            SET calificacion = ?, comentario_profesor = ?, fecha_calificacion = NOW() 
            WHERE id_entrega = ?
        ");
        
        if (!$stmt) {
            error_log("ERROR en preparar UPDATE: " . $conexion->error);
            $_SESSION['error'] = "Error interno al actualizar calificación";
            header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea");
            exit;
        }
        
        $stmt->bind_param("dsi", $calificacion, $comentario_profesor, $entrega['id_entrega']);
    } else {
        // Crear nueva entrega sin archivo
        error_log("Creando nueva entrega sin archivo");
        
        $stmt = $conexion->prepare("
            INSERT INTO entregas_tareas 
            (id_tarea, id_alumno, calificacion, comentario_profesor, fecha_entrega, fecha_calificacion, estado) 
            VALUES (?, ?, ?, ?, NOW(), NOW(), 'calificado')
        ");
        
        if (!$stmt) {
            error_log("ERROR en preparar INSERT: " . $conexion->error);
            $_SESSION['error'] = "Error interno al crear calificación";
            header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea");
            exit;
        }
        
        $stmt->bind_param("iids", $id_tarea, $id_alumno, $calificacion, $comentario_profesor);
    }
    
    // Ejecutar la consulta final
    if ($stmt->execute()) {
        error_log("SUCCESS: Calificación asignada correctamente");
        $_SESSION['success'] = "Calificación asignada correctamente";
        header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea&success=1");
    } else {
        error_log("ERROR en ejecutar consulta final: " . $stmt->error);
        $_SESSION['error'] = "Error al asignar calificación: " . $stmt->error;
        header("Location: detalle_clase.php?id=$id_clase&tarea_id=$id_tarea&error=1");
    }
    exit;
    
} else {
    error_log("ERROR: Método no permitido - " . $_SERVER['REQUEST_METHOD']);
    header("Location: detalle_clase.php");
    exit;
}
?>