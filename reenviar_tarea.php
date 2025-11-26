<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 1) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tarea_id = $_POST['tarea_id'] ?? 0;
    $entrega_id = $_POST['entrega_id'] ?? 0;
    $comentario_alumno = $_POST['comentario_alumno'] ?? '';
    $id_usuario = $_SESSION['id_usuario'];

    // Verificar que el archivo se subió correctamente
    if (!isset($_FILES['archivo_alumno']) || $_FILES['archivo_alumno']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Debes seleccionar un archivo para reenviar.";
        header("Location: detalle_clase.php?id=" . ($_POST['id_clase'] ?? 0));
        exit;
    }

    // Obtener información de la tarea y verificar permisos - CONSULTA CORREGIDA
    $stmt_tarea = $conexion->prepare("
        SELECT t.*, c.id_clase 
        FROM tareas t 
        INNER JOIN clase c ON t.id_clase = c.id_clase 
        INNER JOIN asignacion a ON c.id_clase = a.id_clase 
        INNER JOIN alumno al ON a.id_alumno = al.id_alumno 
        WHERE t.id_tarea = ? AND al.id_usuario = ?
    ");
    $stmt_tarea->bind_param("ii", $tarea_id, $id_usuario);
    $stmt_tarea->execute();
    $tarea_info = $stmt_tarea->get_result()->fetch_assoc();

    if (!$tarea_info) {
        $_SESSION['error'] = "No tienes permiso para reenviar esta tarea o la tarea no existe.";
        header("Location: clases.php");
        exit;
    }

    // Verificar que la entrega pertenece al alumno - CONSULTA CORREGIDA
    $stmt_entrega = $conexion->prepare("
        SELECT et.* 
        FROM entregas_tareas et 
        INNER JOIN alumno a ON et.id_alumno = a.id_alumno 
        WHERE et.id_entrega = ? AND a.id_usuario = ?
    ");
    $stmt_entrega->bind_param("ii", $entrega_id, $id_usuario);
    $stmt_entrega->execute();
    $entrega_info = $stmt_entrega->get_result()->fetch_assoc();

    if (!$entrega_info) {
        $_SESSION['error'] = "No tienes permiso para modificar esta entrega.";
        header("Location: detalle_clase.php?id=" . $tarea_info['id_clase']);
        exit;
    }

    // Configuración para subida de archivos
    $directorio_uploads = "uploads/tareas/alumno/";
    if (!is_dir($directorio_uploads)) {
        mkdir($directorio_uploads, 0777, true);
    }

    $archivo = $_FILES['archivo_alumno'];
    $nombre_archivo_original = $archivo['name'];
    $extension = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));
    
    // Generar nombre único para el archivo
    $nuevo_nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
    $ruta_archivo = $directorio_uploads . $nuevo_nombre_archivo;

    // Extensiones permitidas
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        $_SESSION['error'] = "Tipo de archivo no permitido. Formatos aceptados: " . implode(', ', $extensiones_permitidas);
        header("Location: detalle_clase.php?id=" . $tarea_info['id_clase']);
        exit;
    }

    // Tamaño máximo: 50MB
    if ($archivo['size'] > 50 * 1024 * 1024) {
        $_SESSION['error'] = "El archivo es demasiado grande. Tamaño máximo: 50MB";
        header("Location: detalle_clase.php?id=" . $tarea_info['id_clase']);
        exit;
    }

    // Mover archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
        $_SESSION['error'] = "Error al subir el archivo.";
        header("Location: detalle_clase.php?id=" . $tarea_info['id_clase']);
        exit;
    }

    // Eliminar archivo anterior si existe
    if (!empty($entrega_info['archivo_alumno']) && file_exists($directorio_uploads . $entrega_info['archivo_alumno'])) {
        unlink($directorio_uploads . $entrega_info['archivo_alumno']);
    }

    // Actualizar la entrega en la base de datos
    $conexion->begin_transaction();

    try {
        $stmt_update = $conexion->prepare("
            UPDATE entregas_tareas 
            SET archivo_alumno = ?, 
                nombre_archivo_original = ?, 
                fecha_entrega = NOW(), 
                comentario_alumno = ?,
                calificacion = NULL,
                comentario_profesor = NULL,
                fecha_calificacion = NULL,
                estado = 'entregado'
            WHERE id_entrega = ?
        ");
        $stmt_update->bind_param("sssi", $nuevo_nombre_archivo, $nombre_archivo_original, $comentario_alumno, $entrega_id);
        $stmt_update->execute();

        // Crear notificación para el profesor
        $stmt_notificacion = $conexion->prepare("
            INSERT INTO notificaciones (id_usuario, titulo, mensaje) 
            SELECT 
                u.id_usuario,
                'Tarea reenviada',
                CONCAT('El alumno ', alumno_user.nombre, ' ', alumno_user.apellidos, ' ha reenviado la tarea \"', t.titulo, '\"')
            FROM tareas t
            INNER JOIN clase c ON t.id_clase = c.id_clase
            INNER JOIN profesor p ON c.id_profesor = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            INNER JOIN alumno a ON ? = a.id_alumno
            INNER JOIN usuario alumno_user ON a.id_usuario = alumno_user.id_usuario
            WHERE t.id_tarea = ?
        ");
        $stmt_notificacion->bind_param("ii", $entrega_info['id_alumno'], $tarea_id);
        $stmt_notificacion->execute();

        $conexion->commit();
        
        $_SESSION['success'] = "Tarea reenviada exitosamente. El profesor ha sido notificado.";
        
    } catch (Exception $e) {
        $conexion->rollback();
        
        // Eliminar archivo subido si hubo error
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }
        
        $_SESSION['error'] = "Error al reenviar la tarea: " . $e->getMessage();
    }

    header("Location: detalle_clase.php?id=" . $tarea_info['id_clase']);
    exit;

} else {
    header("Location: clases.php");
    exit;
}
?>