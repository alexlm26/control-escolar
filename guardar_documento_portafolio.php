<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] == '1') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_profesor = $_POST['id_profesor'] ?? 0;
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    $nombre_documento = $_POST['nombre_documento'] ?? '';
    $institucion = $_POST['institucion'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $fecha_emision = $_POST['fecha_emision'] ?? null;

    // Validaciones básicas
    if (empty($tipo_documento) || empty($nombre_documento)) {
        echo json_encode(['success' => false, 'message' => 'Los campos tipo y nombre son obligatorios']);
        exit;
    }

    // Procesar archivo
    if (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo_documento'];
        
        // Validar tipo de archivo
        $extensiones_permitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensiones_permitidas)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
            exit;
        }
        
        // Validar tamaño (10MB máximo)
        if ($archivo['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande']);
            exit;
        }
        
        // Crear directorio si no existe
        $directorio = '../documentos/portafolio/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }
        
        // Generar nombre único
        $nombre_archivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo['name']);
        $ruta_archivo = $directorio . $nombre_archivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
            // Insertar en la base de datos
            $sql = "INSERT INTO portafolio_profesor 
                    (id_profesor, tipo_documento, nombre_documento, ruta_archivo, fecha_emision, institucion, descripcion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conexion->prepare($sql);
            $fecha_emision_sql = $fecha_emision ? $fecha_emision : null;
            $stmt->bind_param("issssss", $id_profesor, $tipo_documento, $nombre_documento, $ruta_archivo, $fecha_emision_sql, $institucion, $descripcion);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Documento guardado correctamente']);
            } else {
                // Eliminar archivo si falla la inserción
                unlink($ruta_archivo);
                echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error con el archivo']);
    }
}
?>