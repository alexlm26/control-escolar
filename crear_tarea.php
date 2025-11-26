<?php
ob_start();
include "conexion.php";
session_start();

if ($_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit;
}

if ($_POST) {
    // Recibir todos los datos
    $id_clase = $_POST['id_clase'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $unidad = $_POST['unidad'];
    $tipo = $_POST['tipo'];
    
    // Determinar fecha_limite y puntos_maximos según el tipo
    if ($tipo == 'aviso') {
        // Para avisos: usar valores automáticos
        $puntos_maximos = 0;
        $fecha_limite = date('Y-m-d H:i:s');
    } else {
        // Para tareas: usar los valores del formulario
        $puntos_maximos = floatval($_POST['puntos_maximos']);
        $fecha_input = $_POST['fecha_limite'];
        
        // Validar y formatear fecha
        if (!empty($fecha_input)) {
            $fecha_limite = date('Y-m-d H:i:s', strtotime($fecha_input));
        } else {
            // Si no hay fecha, usar 7 días desde ahora
            $fecha_limite = date('Y-m-d H:i:s', strtotime('+7 days'));
        }
        
        // Validar que los puntos no sean 0 para tareas
        if ($puntos_maximos <= 0) {
            $puntos_maximos = 100;
        }
    }
    
    // Obtener id_profesor
    $stmt = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    $profesor = $stmt->get_result()->fetch_assoc();
    
    // Manejar archivo subido
    $nombre_archivo = null;
    if (isset($_FILES['archivo_profesor']) && $_FILES['archivo_profesor']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo_profesor'];
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'profesor_' . time() . '_' . uniqid() . '.' . $extension;
        $directorio_destino = 'uploads/tareas/profesor/';
        
        if (!is_dir($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }
        
        $ruta_completa = $directorio_destino . $nombre_archivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
            // Archivo subido correctamente
        } else {
            $nombre_archivo = null;
        }
    }
    
    // Insertar tarea/aviso
    $stmt = $conexion->prepare("INSERT INTO tareas (id_clase, unidad, id_profesor, titulo, descripcion, fecha_limite, puntos_maximos, archivo_profesor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssds", $id_clase, $unidad, $profesor['id_profesor'], $titulo, $descripcion, $fecha_limite, $puntos_maximos, $nombre_archivo);
    
    if ($stmt->execute()) {
        $mensaje = $tipo == 'aviso' ? 'Aviso creado correctamente' : 'Tarea creada correctamente';
        header("Location: detalle_clase.php?id=$id_clase&success=1&mensaje=" . urlencode($mensaje));
    } else {
        header("Location: detalle_clase.php?id=$id_clase&error=1&mensaje=" . urlencode($stmt->error));
    }
    exit;
}
?>