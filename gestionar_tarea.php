<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar datos requeridos
    if (!isset($_POST['tarea_id']) || !isset($_POST['id_clase']) || !isset($_POST['accion'])) {
        $_SESSION['error'] = "Datos incompletos";
        header("Location: detalle_clase.php?id=" . $_POST['id_clase']);
        exit;
    }

    $tarea_id = intval($_POST['tarea_id']);
    $id_clase = intval($_POST['id_clase']);
    $accion = $_POST['accion'];
    $motivo = $_POST['motivo'] ?? '';

    // Verificar que la tarea existe y pertenece al profesor
    $stmt = $conexion->prepare("
        SELECT t.*, p.id_profesor 
        FROM tareas t 
        INNER JOIN profesor p ON t.id_profesor = p.id_profesor 
        WHERE t.id_tarea = ? AND p.id_usuario = ?
    ");
    $stmt->bind_param("ii", $tarea_id, $_SESSION['id_usuario']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "No tienes permisos para gestionar esta tarea";
        header("Location: detalle_clase.php?id=$id_clase");
        exit;
    }
    
    $tarea = $result->fetch_assoc();

    // Procesar según la acción
    switch ($accion) {
        case 'cerrar':
            $nuevo_estado = 'cerrada';
            $mensaje_exito = "Tarea cerrada correctamente. No se aceptarán más entregas.";
            
            $stmt = $conexion->prepare("UPDATE tareas SET estado = ? WHERE id_tarea = ?");
            $stmt->bind_param("si", $nuevo_estado, $tarea_id);
            break;
            
        case 'cancelar':
            $nuevo_estado = 'cancelada';
            $mensaje_exito = "Tarea cancelada correctamente. Ya no será visible para los alumnos.";
            
            $stmt = $conexion->prepare("UPDATE tareas SET estado = ? WHERE id_tarea = ?");
            $stmt->bind_param("si", $nuevo_estado, $tarea_id);
            break;
            
        case 'reactivar':
            // Validar campos requeridos para reactivar
            if (!isset($_POST['dias_adicionales']) || !isset($_POST['horas_adicionales'])) {
                $_SESSION['error'] = "Faltan los días u horas adicionales para reactivar";
                header("Location: detalle_clase.php?id=$id_clase");
                exit;
            }
            
            $dias_adicionales = intval($_POST['dias_adicionales']);
            $horas_adicionales = intval($_POST['horas_adicionales']);
            
            // Validar que al menos haya algún tiempo adicional
            if ($dias_adicionales <= 0 && $horas_adicionales <= 0) {
                $_SESSION['error'] = "Debes especificar al menos algún tiempo adicional (días u horas)";
                header("Location: detalle_clase.php?id=$id_clase");
                exit;
            }
            
            // Calcular nueva fecha límite desde AHORA (fecha actual)
            $nueva_fecha_limite = new DateTime(); // Fecha actual
            $nueva_fecha_limite->modify("+$dias_adicionales days +$horas_adicionales hours");
            $nueva_fecha_str = $nueva_fecha_limite->format('Y-m-d H:i:s');
            
            $nuevo_estado = 'activa';
            $mensaje_exito = "Tarea reactivada correctamente. Nueva fecha límite: " . $nueva_fecha_limite->format('d/m/Y H:i');
            
            // Actualizar fecha límite y estado
            $stmt = $conexion->prepare("UPDATE tareas SET estado = ?, fecha_limite = ? WHERE id_tarea = ?");
            $stmt->bind_param("ssi", $nuevo_estado, $nueva_fecha_str, $tarea_id);
            break;
            
        default:
            $_SESSION['error'] = "Acción no válida";
            header("Location: detalle_clase.php?id=$id_clase");
            exit;
    }
    
    // Ejecutar la actualización
    if ($stmt->execute()) {
        // Registrar la acción en logs si es necesario
        if (!empty($motivo)) {
            error_log("Tarea $tarea_id gestionada por profesor {$_SESSION['id_usuario']}: $accion - Motivo: $motivo");
        }
        
        $_SESSION['success'] = $mensaje_exito;
        header("Location: detalle_clase.php?id=$id_clase&success=1");
    } else {
        $_SESSION['error'] = "Error al actualizar la tarea: " . $stmt->error;
        header("Location: detalle_clase.php?id=$id_clase&error=1");
    }
    exit;
    
} else {
    header("Location: detalle_clase.php");
    exit;
}
?>