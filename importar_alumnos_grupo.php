<?php
ob_start();
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_POST['id_grupo']) || !isset($_FILES['archivo_csv'])) {
    header("Location: ../detalle_grupo.php?id=" . ($_POST['id_grupo'] ?? '') . "&error=Datos incompletos");
    exit;
}

$id_grupo = intval($_POST['id_grupo']);

if ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al subir el archivo");
    exit;
}

// Verificar extensión del archivo
$archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
$extension = strtolower(pathinfo($_FILES['archivo_csv']['name'], PATHINFO_EXTENSION));

if ($extension !== 'csv') {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=El archivo debe ser CSV");
    exit;
}

// Leer archivo CSV
function leerArchivoCSV($archivo_tmp) {
    $matriculas = [];
    
    if (($handle = fopen($archivo_tmp, 'r')) !== FALSE) {
        $fila_numero = 0;
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $fila_numero++;
            
            // Saltar encabezado
            if ($fila_numero === 1) {
                continue;
            }
            
            $matricula = trim($data[0] ?? '');
            if (!empty($matricula)) {
                $matriculas[] = $matricula;
            }
        }
        
        fclose($handle);
    }
    
    return $matriculas;
}

$matriculas = leerArchivoCSV($archivo_tmp);

if (empty($matriculas)) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=No se encontraron matrículas en el archivo");
    exit;
}

// Procesar importación
$conexion->begin_transaction();

try {
    $alumnos_agregados = 0;
    $alumnos_omitidos = 0;
    $detalles_omision = [];
    
    foreach ($matriculas as $matricula) {
        // Buscar alumno por matrícula
        $sql_alumno = "
            SELECT a.id_alumno, u.nombre, u.apellidos
            FROM alumno a 
            INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
            WHERE u.clave = ? AND a.estado = '1'
        ";
        $stmt = $conexion->prepare($sql_alumno);
        $stmt->bind_param("s", $matricula);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $alumno_data = $result->fetch_assoc();
            $id_alumno = $alumno_data['id_alumno'];
            $nombre_alumno = $alumno_data['nombre'] . ' ' . $alumno_data['apellidos'];
            
            // Verificar si el alumno ya está en un grupo activo
            $sql_grupo_activo = "
                SELECT 1 FROM alumno_grupo 
                WHERE id_alumno = ? AND activo = 1
            ";
            $stmt_activo = $conexion->prepare($sql_grupo_activo);
            $stmt_activo->bind_param("i", $id_alumno);
            $stmt_activo->execute();
            
            if ($stmt_activo->get_result()->num_rows > 0) {
                $alumnos_omitidos++;
                $detalles_omision[] = "$matricula - $nombre_alumno: Ya está en otro grupo activo";
            } else {
                // Insertar alumno en el grupo
                $sql_insert = "INSERT INTO alumno_grupo (id_alumno, id_grupo, activo) VALUES (?, ?, 1)";
                $stmt_insert = $conexion->prepare($sql_insert);
                $stmt_insert->bind_param("ii", $id_alumno, $id_grupo);
                
                if ($stmt_insert->execute()) {
                    $alumnos_agregados++;
                } else {
                    $alumnos_omitidos++;
                    $detalles_omision[] = "$matricula - $nombre_alumno: Error al insertar en la base de datos";
                }
            }
        } else {
            $alumnos_omitidos++;
            $detalles_omision[] = "$matricula: No encontrado o inactivo";
        }
    }
    
    $conexion->commit();
    
    // Mensaje de resultado
    $mensaje = "Importación completada: $alumnos_agregados alumnos agregados";
    if ($alumnos_omitidos > 0) {
        $mensaje .= ", $alumnos_omitidos omitidos";
        $_SESSION['detalles_importacion'] = $detalles_omision;
    }
    
    header("Location: ../detalle_grupo.php?id=$id_grupo&mensaje=" . urlencode($mensaje));
    
} catch (Exception $e) {
    $conexion->rollback();
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error en la importación: " . $e->getMessage());
}
?>