<?php
session_start();
include "../conexion.php";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != '5') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../prefecto.php");
    exit;
}

$id_prefecto = $_POST['id_prefecto'];
$id_grupo = $_POST['id_grupo'];
$tipo_justificante = $_POST['tipo_justificante'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];
$motivo = $_POST['motivo'];
$comentario_prefecto = $_POST['comentario_prefecto'] ?? '';

// Validar fechas
if ($fecha_fin < $fecha_inicio) {
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=La fecha de fin no puede ser anterior a la fecha de inicio");
    exit;
}

// Procesar archivo adjunto
$ruta_justificante = null;
$nombre_archivo_original = null;

if (isset($_FILES['documento_justificante']) && $_FILES['documento_justificante']['error'] == 0) {
    $archivo = $_FILES['documento_justificante'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    
    if (in_array($extension, $extensiones_permitidas)) {
        if ($archivo['size'] <= 5 * 1024 * 1024) { // 5MB máximo
            $nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
            $ruta_destino = '../uploads/justificantes/' . $nombre_archivo;
            
            if (!is_dir('../uploads/justificantes')) {
                mkdir('../uploads/justificantes', 0777, true);
            }
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                $ruta_justificante = 'uploads/justificantes/' . $nombre_archivo;
                $nombre_archivo_original = $archivo['name'];
            }
        }
    }
}

try {
    if ($_POST['tipo_justificante'] == 'individual') {
        // Justificante individual
        $id_alumno = $_POST['id_alumno'];
        
        $sql = "INSERT INTO justificantes_asistencia (
            id_prefecto, id_alumno, tipo_justificante, fecha_inicio, fecha_fin, 
            motivo, ruta_justificante, nombre_archivo_original, comentario_prefecto, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprobado')";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iisssssss", 
            $id_prefecto, $id_alumno, $tipo_justificante, $fecha_inicio, $fecha_fin,
            $motivo, $ruta_justificante, $nombre_archivo_original, $comentario_prefecto
        );
        
        if ($stmt->execute()) {
            header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&success=Justificante individual creado correctamente");
        } else {
            throw new Exception("Error al crear justificante individual: " . $stmt->error);
        }
        
    } else {
        // Justificantes múltiples
        if (!isset($_POST['alumnos_seleccionados'])) {
            header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=Debe seleccionar al menos un alumno");
            exit;
        }
        
        $alumnos_seleccionados = $_POST['alumnos_seleccionados'];
        $justificantes_creados = 0;
        
        $sql = "INSERT INTO justificantes_asistencia (
            id_prefecto, id_alumno, tipo_justificante, fecha_inicio, fecha_fin, 
            motivo, ruta_justificante, nombre_archivo_original, comentario_prefecto, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprobado')";
        
        $stmt = $conexion->prepare($sql);
        
        foreach ($alumnos_seleccionados as $id_alumno) {
            $stmt->bind_param("iisssssss", 
                $id_prefecto, $id_alumno, $tipo_justificante, $fecha_inicio, $fecha_fin,
                $motivo, $ruta_justificante, $nombre_archivo_original, $comentario_prefecto
            );
            
            if ($stmt->execute()) {
                $justificantes_creados++;
            }
        }
        
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&success=Se crearon " . $justificantes_creados . " justificantes correctamente");
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=Error al crear justificante: " . $e->getMessage());
}

$conexion->close();
?>