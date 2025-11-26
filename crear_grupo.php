<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

// Obtener datos del coordinador
$id_usuario = $_SESSION['id_usuario'];

// Obtener información del coordinador
$sql_coordinador = "SELECT c.id_coordinador, u.id_carrera 
                   FROM coordinador c 
                   INNER JOIN usuario u ON c.id_usuario = u.id_usuario 
                   WHERE c.id_usuario = ?";
$stmt = $conexion->prepare($sql_coordinador);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$coordinador_data = $stmt->get_result()->fetch_assoc();

$id_coordinador = $coordinador_data['id_coordinador'];
$id_carrera_coordinador = $coordinador_data['id_carrera'];

$mensaje = "";

if ($_POST) {
    $nombre_grupo = trim($_POST['nombre_grupo']);
    $id_carrera = $_POST['id_carrera'];
    $id_especialidad = $_POST['id_especialidad'] ?: NULL;
    $semestre = intval($_POST['semestre']);
    $capacidad_maxima = intval($_POST['capacidad_maxima']);
    $tutor_asignado = $_POST['tutor_asignado'] ?: NULL;
    
    // Validaciones básicas
    if (empty($nombre_grupo) || empty($id_carrera) || empty($semestre)) {
        $mensaje = "<div class='alert alert-error'>❌ Todos los campos obligatorios son requeridos</div>";
    } elseif ($id_carrera_coordinador != 0 && $id_carrera != $id_carrera_coordinador) {
        $mensaje = "<div class='alert alert-error'>❌ No tienes permisos para crear grupos en esta carrera</div>";
    } else {
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Verificar si ya existe un grupo con el mismo nombre en la misma carrera
            $sql_verificar = "SELECT id_grupo FROM grupo WHERE nombre = ? AND id_carrera = ?";
            $stmt_verificar = $conexion->prepare($sql_verificar);
            $stmt_verificar->bind_param("si", $nombre_grupo, $id_carrera);
            $stmt_verificar->execute();
            
            if ($stmt_verificar->get_result()->num_rows > 0) {
                throw new Exception("Ya existe un grupo con el nombre '$nombre_grupo' en esta carrera.");
            }
            
            // Crear el grupo
            $sql_grupo = "INSERT INTO grupo (nombre, id_carrera, id_especialidad, semestre, capacidad_maxima, tutor_asignado, activo) 
                         VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt_grupo = $conexion->prepare($sql_grupo);
            $stmt_grupo->bind_param("siiiii", $nombre_grupo, $id_carrera, $id_especialidad, $semestre, $capacidad_maxima, $tutor_asignado);
            
            if (!$stmt_grupo->execute()) {
                throw new Exception("Error al crear el grupo: " . $conexion->error);
            }
            
            $id_grupo = $conexion->insert_id;
            $alumnos_agregados = 0;
            $alumnos_omitidos = 0;
            $detalles_omision = [];
            
            // Procesar archivo CSV si se subió
            if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
                $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
                $matriculas = leerArchivoCSV($archivo_tmp);
                
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
            }
            
            $conexion->commit();
            
            // Mensaje de éxito
            $mensaje_success = "✅ Grupo '$nombre_grupo' creado correctamente";
            if ($alumnos_agregados > 0) {
                $mensaje_success .= " con $alumnos_agregados alumnos agregados";
            }
            if ($alumnos_omitidos > 0) {
                $mensaje_success .= " ($alumnos_omitidos omitidos)";
                
                // Guardar detalles de omisión en sesión
                $_SESSION['detalles_omision_grupo'] = $detalles_omision;
            }
            
            header("Location: ../grupos.php");
            exit;
            
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "<div class='alert alert-error'>❌ " . $e->getMessage() . "</div>";
        }
    }
}

// Función para leer archivo CSV
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

// Si hay mensaje de error, mostrar y redirigir
if (!empty($mensaje)) {
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error al crear grupo</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .alert { padding: 15px; border-radius: 5px; margin: 20px 0; }
            .alert-error { background: #ffebee; color: #c62828; border: 1px solid #c62828; }
        </style>
    </head>
    <body>
        $mensaje
        <br>
        <a href='../grupos.php'>Volver a Grupos</a>
    </body>
    </html>";
    exit;
}
?>