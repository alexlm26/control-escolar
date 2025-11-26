<?php
session_start();
include "conexion.php";
include "header.php";

if (!isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

// Obtener mes y año actuales
$mes_actual = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$anio_actual = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Calcular mes anterior y siguiente
$mes_anterior = $mes_actual - 1;
$anio_anterior = $anio_actual;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $anio_anterior = $anio_actual - 1;
}

$mes_siguiente = $mes_actual + 1;
$anio_siguiente = $anio_actual;
if ($mes_siguiente > 12) {
    $mes_siguiente = 1;
    $anio_siguiente = $anio_actual + 1;
}

// Obtener primer día del mes y número de días
$primer_dia = date('N', strtotime("$anio_actual-$mes_actual-01"));
$dias_en_mes = date('t', strtotime("$anio_actual-$mes_actual-01"));

// Nombres de meses
$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Obtener ID del coordinador si es el caso
$id_coordinador = null;
if ($rol == '3') {
    $sql_coordinador = "SELECT id_coordinador FROM coordinador WHERE id_usuario = $id_usuario";
    $result_coordinador = $conexion->query($sql_coordinador);
    if ($result_coordinador->num_rows > 0) {
        $coordinador = $result_coordinador->fetch_assoc();
        $id_coordinador = $coordinador['id_coordinador'];
    }
}

// Obtener academias para el coordinador
$academias = [];
if ($rol == '3' && $id_coordinador) {
    $sql_academias = "SELECT id_academia, nombre FROM academia WHERE activo = 1";
    $result_academias = $conexion->query($sql_academias);
    while ($academia = $result_academias->fetch_assoc()) {
        $academias[] = $academia;
    }
}

// Obtener información de materias para las unidades
$materias_info = [];
if ($rol == '2') {
    $sql_materias = "SELECT id_materia, nombre, unidades FROM materia";
    $result_materias = $conexion->query($sql_materias);
    while ($materia = $result_materias->fetch_assoc()) {
        $materias_info[$materia['id_materia']] = $materia;
    }
}

// Obtener tareas según el rol
$tareas = [];
$eventos = [];
$eventos_multiples_dias = [];
if($rol == 5 || $rol == 4)
        {
        
         // Obtener eventos (coordinador puede ver y gestionar todos)
    $sql_eventos = "
        SELECT e.id_evento, e.titulo, e.descripcion, e.fecha_inicio, e.fecha_fin,
               e.tipo_evento, e.color, a.nombre as academia_nombre,
               'evento' as tipo, e.id_coordinador
        FROM eventos_calendario e
        LEFT JOIN academia a ON e.id_academia = a.id_academia
        WHERE e.activo = 1
        AND (
            (MONTH(e.fecha_inicio) = $mes_actual AND YEAR(e.fecha_inicio) = $anio_actual) OR
            (MONTH(e.fecha_fin) = $mes_actual AND YEAR(e.fecha_fin) = $anio_actual) OR
            (e.fecha_inicio <= '$anio_actual-$mes_actual-01' AND e.fecha_fin >= LAST_DAY('$anio_actual-$mes_actual-01'))
        )
        ORDER BY e.fecha_inicio
    ";
    
    $eventos_result = $conexion->query($sql_eventos);
    while ($evento = $eventos_result->fetch_assoc()) {
        // Procesar eventos de múltiples días
        $fecha_inicio = new DateTime($evento['fecha_inicio']);
        $fecha_fin = new DateTime($evento['fecha_fin']);
        
        // Si el evento dura más de un día, guardarlo para procesamiento especial
        if ($fecha_inicio->format('Y-m-d') != $fecha_fin->format('Y-m-d')) {
            $eventos_multiples_dias[] = $evento;
        }
        
        // Agregar evento a los días correspondientes
        $fecha_actual_evento = clone $fecha_inicio;
        while ($fecha_actual_evento <= $fecha_fin) {
            if ($fecha_actual_evento->format('n') == $mes_actual && $fecha_actual_evento->format('Y') == $anio_actual) {
                $dia = $fecha_actual_evento->format('j');
                if (!isset($eventos[$dia])) {
                    $eventos[$dia] = [];
                }
                $evento['es_multi_dia'] = true;
                $evento['es_primer_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_inicio->format('Y-m-d'));
                $evento['es_ultimo_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_fin->format('Y-m-d'));
                $eventos[$dia][] = $evento;
            }
            $fecha_actual_evento->modify('+1 day');
        }
    }
        }

if ($rol == '1') { // Alumno
    // Obtener ID del alumno
    $sql_alumno = "SELECT id_alumno FROM alumno WHERE id_usuario = $id_usuario";
    $result_alumno = $conexion->query($sql_alumno);
    
    if ($result_alumno->num_rows > 0) {
        $alumno = $result_alumno->fetch_assoc();
        $id_alumno = $alumno['id_alumno'];
        
        // Obtener tareas de las clases ACTIVAS donde está inscrito el alumno
        $sql_tareas = "
            SELECT t.id_tarea, t.titulo, t.descripcion, t.fecha_limite, 
                   t.estado, m.nombre as materia_nombre, c.grupo,
                   t.puntos_maximos, et.calificacion, t.archivo_profesor,
                   CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
                   'tarea' as tipo, t.unidad
            FROM tareas t
            INNER JOIN clase c ON t.id_clase = c.id_clase
            INNER JOIN materia m ON c.id_materia = m.id_materia
            INNER JOIN profesor p ON c.id_profesor = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_alumno = $id_alumno
            WHERE c.id_clase IN (
                SELECT id_clase FROM asignacion WHERE id_alumno = $id_alumno
            )
            AND t.estado = 'activa'
            AND c.activo = 1
            AND MONTH(t.fecha_limite) = $mes_actual 
            AND YEAR(t.fecha_limite) = $anio_actual
            ORDER BY t.fecha_limite
        ";
        
        $tareas_result = $conexion->query($sql_tareas);
        while ($tarea = $tareas_result->fetch_assoc()) {
            $dia = date('j', strtotime($tarea['fecha_limite']));
            if (!isset($tareas[$dia])) {
                $tareas[$dia] = [];
            }
            $tareas[$dia][] = $tarea;
        }
    }
    
    // Obtener eventos (visibles para todos)
    $sql_eventos = "
        SELECT e.id_evento, e.titulo, e.descripcion, e.fecha_inicio, e.fecha_fin,
               e.tipo_evento, e.color, a.nombre as academia_nombre,
               'evento' as tipo
        FROM eventos_calendario e
        LEFT JOIN academia a ON e.id_academia = a.id_academia
        WHERE e.activo = 1
        AND (
            (MONTH(e.fecha_inicio) = $mes_actual AND YEAR(e.fecha_inicio) = $anio_actual) OR
            (MONTH(e.fecha_fin) = $mes_actual AND YEAR(e.fecha_fin) = $anio_actual) OR
            (e.fecha_inicio <= '$anio_actual-$mes_actual-01' AND e.fecha_fin >= LAST_DAY('$anio_actual-$mes_actual-01'))
        )
        ORDER BY e.fecha_inicio
    ";
    
    $eventos_result = $conexion->query($sql_eventos);
    while ($evento = $eventos_result->fetch_assoc()) {
        // Procesar eventos de múltiples días
        $fecha_inicio = new DateTime($evento['fecha_inicio']);
        $fecha_fin = new DateTime($evento['fecha_fin']);
        
        // Si el evento dura más de un día, guardarlo para procesamiento especial
        if ($fecha_inicio->format('Y-m-d') != $fecha_fin->format('Y-m-d')) {
            $eventos_multiples_dias[] = $evento;
        }
        
        // Agregar evento a los días correspondientes
        $fecha_actual_evento = clone $fecha_inicio;
        while ($fecha_actual_evento <= $fecha_fin) {
            if ($fecha_actual_evento->format('n') == $mes_actual && $fecha_actual_evento->format('Y') == $anio_actual) {
                $dia = $fecha_actual_evento->format('j');
                if (!isset($eventos[$dia])) {
                    $eventos[$dia] = [];
                }
                $evento['es_multi_dia'] = true;
                $evento['es_primer_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_inicio->format('Y-m-d'));
                $evento['es_ultimo_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_fin->format('Y-m-d'));
                $eventos[$dia][] = $evento;
            }
            $fecha_actual_evento->modify('+1 day');
        }
    }
    
} elseif ($rol == '2') { // Profesor
    // Obtener ID del profesor
    $sql_profesor = "SELECT id_profesor FROM profesor WHERE id_usuario = $id_usuario";
    $result_profesor = $conexion->query($sql_profesor);
    
    if ($result_profesor->num_rows > 0) {
        $profesor = $result_profesor->fetch_assoc();
        $id_profesor = $profesor['id_profesor'];
        
        // Obtener tareas creadas por el profesor en clases ACTIVAS
        $sql_tareas = "
            SELECT t.id_tarea, t.titulo, t.descripcion, t.fecha_limite, 
                   t.estado, m.nombre as materia_nombre, c.grupo,
                   t.puntos_maximos, COUNT(et.id_entrega) as entregas_pendientes,
                   c.id_clase, 'tarea' as tipo, t.archivo_profesor, t.unidad
            FROM tareas t
            INNER JOIN clase c ON t.id_clase = c.id_clase
            INNER JOIN materia m ON c.id_materia = m.id_materia
            LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.estado = 'entregado'
            WHERE t.id_profesor = $id_profesor
            AND c.activo = 1
            AND MONTH(t.fecha_limite) = $mes_actual 
            AND YEAR(t.fecha_limite) = $anio_actual
            GROUP BY t.id_tarea
            ORDER BY t.fecha_limite
        ";
        
        $tareas_result = $conexion->query($sql_tareas);
        while ($tarea = $tareas_result->fetch_assoc()) {
            $dia = date('j', strtotime($tarea['fecha_limite']));
            if (!isset($tareas[$dia])) {
                $tareas[$dia] = [];
            }
            $tareas[$dia][] = $tarea;
        }
        
        // Obtener tareas de academia del profesor (solo academias ACTIVAS a las que pertenece)
        $sql_tareas_academia = "
            SELECT ta.id_tarea_academia as id_tarea, ta.titulo, ta.descripcion, ta.fecha_limite,
                   ta.estado, a.nombre as academia_nombre, '' as grupo,
                   0 as puntos_maximos, '' as entregas_pendientes,
                   'tarea_academia' as tipo, ta.tipo_tarea, '' as archivo_profesor, '' as unidad
            FROM tareas_academia ta
            INNER JOIN academia a ON ta.id_academia = a.id_academia
            INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
            WHERE pa.id_profesor = $id_profesor
            AND pa.activo = 1
            AND a.activo = 1
            AND ta.estado = 'activa'
            AND MONTH(ta.fecha_limite) = $mes_actual 
            AND YEAR(ta.fecha_limite) = $anio_actual
            ORDER BY ta.fecha_limite
        ";
        
        $tareas_academia_result = $conexion->query($sql_tareas_academia);
        while ($tarea_academia = $tareas_academia_result->fetch_assoc()) {
            $dia = date('j', strtotime($tarea_academia['fecha_limite']));
            if (!isset($tareas[$dia])) {
                $tareas[$dia] = [];
            }
            $tareas[$dia][] = $tarea_academia;
        }
        
        // Obtener clases ACTIVAS del profesor para crear nuevas tareas
        $sql_clases = "
            SELECT c.id_clase, m.nombre as materia_nombre, c.grupo, m.id_materia, m.unidades
            FROM clase c
            INNER JOIN materia m ON c.id_materia = m.id_materia
            WHERE c.id_profesor = $id_profesor 
            AND c.activo = 1
            ORDER BY m.nombre, c.grupo
        ";
        $clases_profesor = $conexion->query($sql_clases);
        
        // Obtener eventos (visibles para todos)
        $sql_eventos = "
            SELECT e.id_evento, e.titulo, e.descripcion, e.fecha_inicio, e.fecha_fin,
                   e.tipo_evento, e.color, a.nombre as academia_nombre,
                   'evento' as tipo
            FROM eventos_calendario e
            LEFT JOIN academia a ON e.id_academia = a.id_academia
            WHERE e.activo = 1
            AND (
                (MONTH(e.fecha_inicio) = $mes_actual AND YEAR(e.fecha_inicio) = $anio_actual) OR
                (MONTH(e.fecha_fin) = $mes_actual AND YEAR(e.fecha_fin) = $anio_actual) OR
                (e.fecha_inicio <= '$anio_actual-$mes_actual-01' AND e.fecha_fin >= LAST_DAY('$anio_actual-$mes_actual-01'))
            )
            ORDER BY e.fecha_inicio
        ";
        
        $eventos_result = $conexion->query($sql_eventos);
        while ($evento = $eventos_result->fetch_assoc()) {
            // Procesar eventos de múltiples días
            $fecha_inicio = new DateTime($evento['fecha_inicio']);
            $fecha_fin = new DateTime($evento['fecha_fin']);
            
            // Si el evento dura más de un día, guardarlo para procesamiento especial
            if ($fecha_inicio->format('Y-m-d') != $fecha_fin->format('Y-m-d')) {
                $eventos_multiples_dias[] = $evento;
            }
            
            // Agregar evento a los días correspondientes
            $fecha_actual_evento = clone $fecha_inicio;
            while ($fecha_actual_evento <= $fecha_fin) {
                if ($fecha_actual_evento->format('n') == $mes_actual && $fecha_actual_evento->format('Y') == $anio_actual) {
                    $dia = $fecha_actual_evento->format('j');
                    if (!isset($eventos[$dia])) {
                        $eventos[$dia] = [];
                    }
                    $evento['es_multi_dia'] = true;
                    $evento['es_primer_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_inicio->format('Y-m-d'));
                    $evento['es_ultimo_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_fin->format('Y-m-d'));
                    $eventos[$dia][] = $evento;
                }
                $fecha_actual_evento->modify('+1 day');
            }
        }
    }
} else if($rol == 3) { // Coordinador
    // Coordinador puede ver todas las tareas de clases ACTIVAS
    $sql_tareas = "
        SELECT t.id_tarea, t.titulo, t.descripcion, t.fecha_limite, 
               t.estado, m.nombre as materia_nombre, c.grupo,
               t.puntos_maximos, CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
               'tarea' as tipo, t.archivo_profesor, t.unidad
        FROM tareas t
        INNER JOIN clase c ON t.id_clase = c.id_clase
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE c.activo = 1
        AND MONTH(t.fecha_limite) = $mes_actual 
        AND YEAR(t.fecha_limite) = $anio_actual
        ORDER BY t.fecha_limite
    ";
    
    $tareas_result = $conexion->query($sql_tareas);
    while ($tarea = $tareas_result->fetch_assoc()) {
        $dia = date('j', strtotime($tarea['fecha_limite']));
        if (!isset($tareas[$dia])) {
            $tareas[$dia] = [];
        }
        $tareas[$dia][] = $tarea;
    }
    
    // Obtener todas las tareas de academia ACTIVAS
    $sql_tareas_academia = "
        SELECT ta.id_tarea_academia as id_tarea, ta.titulo, ta.descripcion, ta.fecha_limite,
               ta.estado, a.nombre as academia_nombre, '' as grupo,
               0 as puntos_maximos, '' as entregas_pendientes,
               'tarea_academia' as tipo, ta.tipo_tarea, '' as archivo_profesor, '' as unidad
        FROM tareas_academia ta
        INNER JOIN academia a ON ta.id_academia = a.id_academia
        WHERE ta.estado = 'activa'
        AND a.activo = 1
        AND MONTH(ta.fecha_limite) = $mes_actual 
        AND YEAR(ta.fecha_limite) = $anio_actual
        ORDER BY ta.fecha_limite
    ";
    
    $tareas_academia_result = $conexion->query($sql_tareas_academia);
    while ($tarea_academia = $tareas_academia_result->fetch_assoc()) {
        $dia = date('j', strtotime($tarea_academia['fecha_limite']));
        if (!isset($tareas[$dia])) {
            $tareas[$dia] = [];
        }
        $tareas[$dia][] = $tarea_academia;
    }
    
    // Obtener eventos (coordinador puede ver y gestionar todos)
    $sql_eventos = "
        SELECT e.id_evento, e.titulo, e.descripcion, e.fecha_inicio, e.fecha_fin,
               e.tipo_evento, e.color, a.nombre as academia_nombre,
               'evento' as tipo, e.id_coordinador
        FROM eventos_calendario e
        LEFT JOIN academia a ON e.id_academia = a.id_academia
        WHERE e.activo = 1
        AND (
            (MONTH(e.fecha_inicio) = $mes_actual AND YEAR(e.fecha_inicio) = $anio_actual) OR
            (MONTH(e.fecha_fin) = $mes_actual AND YEAR(e.fecha_fin) = $anio_actual) OR
            (e.fecha_inicio <= '$anio_actual-$mes_actual-01' AND e.fecha_fin >= LAST_DAY('$anio_actual-$mes_actual-01'))
        )
        ORDER BY e.fecha_inicio
    ";
    
    $eventos_result = $conexion->query($sql_eventos);
    while ($evento = $eventos_result->fetch_assoc()) {
        // Procesar eventos de múltiples días
        $fecha_inicio = new DateTime($evento['fecha_inicio']);
        $fecha_fin = new DateTime($evento['fecha_fin']);
        
        // Si el evento dura más de un día, guardarlo para procesamiento especial
        if ($fecha_inicio->format('Y-m-d') != $fecha_fin->format('Y-m-d')) {
            $eventos_multiples_dias[] = $evento;
        }
        
        // Agregar evento a los días correspondientes
        $fecha_actual_evento = clone $fecha_inicio;
        while ($fecha_actual_evento <= $fecha_fin) {
            if ($fecha_actual_evento->format('n') == $mes_actual && $fecha_actual_evento->format('Y') == $anio_actual) {
                $dia = $fecha_actual_evento->format('j');
                if (!isset($eventos[$dia])) {
                    $eventos[$dia] = [];
                }
                $evento['es_multi_dia'] = true;
                $evento['es_primer_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_inicio->format('Y-m-d'));
                $evento['es_ultimo_dia'] = ($fecha_actual_evento->format('Y-m-d') == $fecha_fin->format('Y-m-d'));
                $eventos[$dia][] = $evento;
            }
            $fecha_actual_evento->modify('+1 day');
        }
    }
}

// Procesar creación de nueva tarea (solo para profesores)
if ($_POST && $rol == '2' && isset($_POST['crear_tarea'])) {
    $id_clase = $_POST['id_clase'];
    $titulo = $conexion->real_escape_string($_POST['titulo']);
    $descripcion = $conexion->real_escape_string($_POST['descripcion']);
    $fecha_limite = $_POST['fecha_limite'];
    $puntos_maximos = $_POST['puntos_maximos'];
    $unidad = $_POST['unidad'];
    
    // Obtener ID del profesor
    $sql_profesor = "SELECT id_profesor FROM profesor WHERE id_usuario = $id_usuario";
    $result_profesor = $conexion->query($sql_profesor);
    $profesor = $result_profesor->fetch_assoc();
    $id_profesor = $profesor['id_profesor'];
    
    // Verificar que la clase pertenece al profesor y está activa
    $sql_verificar_clase = "
        SELECT id_clase FROM clase 
        WHERE id_clase = $id_clase 
        AND id_profesor = $id_profesor 
        AND activo = 1
    ";
    $result_verificar = $conexion->query($sql_verificar_clase);
    
    if ($result_verificar->num_rows === 0) {
        $mensaje_error = "No tienes permisos para crear tareas en esta clase o la clase no está activa.";
    } else {
        // Procesar archivo adjunto
        $archivo_profesor = null;
        if (isset($_FILES['archivo_profesor']) && $_FILES['archivo_profesor']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo_profesor'];
            $nombre_archivo = $archivo['name'];
            $tipo_archivo = $archivo['type'];
            $tamaño_archivo = $archivo['size'];
            $archivo_temporal = $archivo['tmp_name'];
            
            // Validar tipo de archivo
            $extensiones_permitidas = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            
            if (in_array($extension, $extensiones_permitidas)) {
                // Validar tamaño (20MB máximo)
                if ($tamaño_archivo <= 20 * 1024 * 1024) {
                    // Crear directorio si no existe
                    $directorio_destino = "uploads/tareas/profesor/";
                    if (!is_dir($directorio_destino)) {
                        mkdir($directorio_destino, 0777, true);
                    }
                    
                    // Generar nombre único para el archivo
                    $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
                    $ruta_destino = $directorio_destino . $nombre_unico;
                    
                    if (move_uploaded_file($archivo_temporal, $ruta_destino)) {
                        $archivo_profesor = $nombre_unico;
                    } else {
                        $mensaje_error = "Error al subir el archivo.";
                    }
                } else {
                    $mensaje_error = "El archivo es demasiado grande. Máximo 20MB.";
                }
            } else {
                $mensaje_error = "Tipo de archivo no permitido.";
            }
        }
        
        if (!isset($mensaje_error)) {
            $sql_insert = "
                INSERT INTO tareas (id_clase, id_profesor, titulo, descripcion, fecha_limite, puntos_maximos, unidad, estado, archivo_profesor)
                VALUES ($id_clase, $id_profesor, '$titulo', '$descripcion', '$fecha_limite', $puntos_maximos, $unidad, 'activa', " . 
                ($archivo_profesor ? "'$archivo_profesor'" : "NULL") . ")
            ";
            
            if ($conexion->query($sql_insert)) {
                $mensaje_exito = "Tarea creada exitosamente";
                header("Location: calendario.php?mes=$mes_actual&anio=$anio_actual&exito=1");
                exit;
            } else {
                $mensaje_error = "Error al crear la tarea: " . $conexion->error;
            }
        }
    }
}

// CORRECCIÓN: Procesar creación de evento (solo para coordinador)
if ($_POST && $rol == '3' && isset($_POST['crear_evento'])) {
    $titulo = $conexion->real_escape_string($_POST['titulo']);
    $descripcion = $conexion->real_escape_string($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ? $_POST['fecha_fin'] : $fecha_inicio;
    $tipo_evento = $_POST['tipo_evento'];
    $id_academia = $_POST['id_academia'] ? intval($_POST['id_academia']) : 'NULL';
    $color = $_POST['color'];
    
    // Verificar que el coordinador existe
    if (!$id_coordinador) {
        $mensaje_error = "Error: No se pudo identificar al coordinador.";
    } else {
        $sql_insert = "
            INSERT INTO eventos_calendario (titulo, descripcion, fecha_inicio, fecha_fin, tipo_evento, id_coordinador, id_academia, color, activo)
            VALUES ('$titulo', '$descripcion', '$fecha_inicio', '$fecha_fin', '$tipo_evento', $id_coordinador, $id_academia, '$color', 1)
        ";
        
        if ($conexion->query($sql_insert)) {
            $mensaje_exito = "Evento creado exitosamente";
            header("Location: calendario.php?mes=$mes_actual&anio=$anio_actual&exito=1");
            exit;
        } else {
            $mensaje_error = "Error al crear el evento: " . $conexion->error;
        }
    }
}

// CORRECCIÓN: Procesar actualización de evento (solo para coordinador)
if ($_POST && $rol == '3' && isset($_POST['actualizar_evento'])) {
    $id_evento = intval($_POST['id_evento']);
    $titulo = $conexion->real_escape_string($_POST['titulo']);
    $descripcion = $conexion->real_escape_string($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'] ? $_POST['fecha_fin'] : $fecha_inicio;
    $tipo_evento = $_POST['tipo_evento'];
    $id_academia = $_POST['id_academia'] ? intval($_POST['id_academia']) : 'NULL';
    $color = $_POST['color'];
    
    $sql_update = "
        UPDATE eventos_calendario 
        SET titulo = '$titulo', descripcion = '$descripcion', fecha_inicio = '$fecha_inicio', 
            fecha_fin = '$fecha_fin', tipo_evento = '$tipo_evento', id_academia = $id_academia, 
            color = '$color', fecha_modificacion = NOW()
        WHERE id_evento = $id_evento AND id_coordinador = $id_coordinador
    ";
    
    if ($conexion->query($sql_update)) {
        $mensaje_exito = "Evento actualizado exitosamente";
        header("Location: calendario.php?mes=$mes_actual&anio=$anio_actual&exito=1");
        exit;
    } else {
        $mensaje_error = "Error al actualizar el evento: " . $conexion->error;
    }
}

// CORRECCIÓN: Procesar eliminación de evento (solo para coordinador)
if ($_POST && $rol == '3' && isset($_POST['eliminar_evento'])) {
    $id_evento = intval($_POST['id_evento']);
    
    $sql_delete = "
        DELETE FROM eventos_calendario 
        WHERE id_evento = $id_evento AND id_coordinador = $id_coordinador
    ";
    
    if ($conexion->query($sql_delete)) {
        $mensaje_exito = "Evento eliminado exitosamente";
        header("Location: calendario.php?mes=$mes_actual&anio=$anio_actual&exito=1");
        exit;
    } else {
        $mensaje_error = "Error al eliminar el evento: " . $conexion->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #1565c0;
            --color-secundario: #1976d2;
            --color-fondo: #f8f9fa;
            --color-texto: #333;
            --color-blanco: #fff;
            --sombra-suave: 0 2px 10px rgba(0,0,0,0.1);
            --radio-borde: 12px;
        }
        
        body {
            background: var(--color-fondo);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .calendar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
            color: white;
            padding: 25px;
            border-radius: var(--radio-borde);
            margin-bottom: 25px;
            box-shadow: var(--sombra-suave);
        }
        
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .calendar-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-calendar {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-calendar:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border-radius: var(--radio-borde);
            overflow: hidden;
            box-shadow: var(--sombra-suave);
        }
        
        .calendar-day-header {
            background: var(--color-primario);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 10px;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
            transform: scale(1.02);
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #999;
        }
        
        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid var(--color-primario);
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .task-item {
            background: #fff3e0;
            border-left: 3px solid #ff9800;
            padding: 6px 8px;
            margin-bottom: 4px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .task-item:hover {
            background: #ffe0b2;
            transform: translateX(2px);
        }
        
        .task-item.vencida {
            background: #ffebee;
            border-left-color: #f44336;
            opacity: 0.7;
        }
        
        .task-item.aviso {
            background: #e8f5e8;
            border-left-color: #4caf50;
        }
        
        .task-item.archivo {
            border-left-color: #2196f3;
        }
        
        .event-item {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
            padding: 6px 8px;
            margin-bottom: 4px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .event-item:hover {
            background: #bbdefb;
            transform: translateX(2px);
        }
        
        .event-item.multi-day {
            border-left-width: 5px;
        }
        
        .event-item.first-day {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .event-item.last-day {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .event-item.middle-day {
            border-radius: 0;
        }
        
        .multi-day-indicator {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.6rem;
            background: rgba(0,0,0,0.1);
            border-radius: 3px;
            padding: 1px 3px;
        }
        
        .task-title, .event-title {
            font-weight: 600;
            margin-bottom: 2px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .task-meta, .event-meta {
            font-size: 0.7rem;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .task-badge {
            background: var(--color-primario);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.65rem;
            margin-top: 2px;
            display: inline-block;
        }
        
        .badge-archivo {
            background: #2196f3;
        }
        
        .badge-unidad {
            background: #9c27b0;
        }
        
        /* CORRECCIÓN: Estilos para expandir/contraer */
        .day-content {
            max-height: none;
            overflow: visible;
        }
        
        .day-content.collapsed {
            max-height: 80px;
            overflow: hidden;
        }
        
        .day-items {
            transition: all 0.3s ease;
        }
        
        .day-items.hidden {
            display: none;
        }
        
        .expand-toggle {
            text-align: center;
            padding: 4px;
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
            margin-top: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .expand-toggle:hover {
            background: rgba(0,0,0,0.1);
        }
        
        .items-count {
            font-size: 0.7rem;
            color: #999;
            margin-bottom: 3px;
        }

        /* Modal Styles */
        .modal-task {
            border-left: 4px solid var(--color-primario);
        }
        
        .task-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .calendar-container {
                padding: 10px;
            }
            
            .calendar-header {
                padding: 15px;
            }
            
            .calendar-title {
                font-size: 1.4rem;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 5px;
            }
            
            .task-item, .event-item {
                padding: 4px 6px;
                font-size: 0.7rem;
            }
            
            .task-title, .event-title {
                -webkit-line-clamp: 1;
            }
            
            .day-content.collapsed {
                max-height: 60px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar-day-header {
                display: none;
            }
            
            .calendar-day {
                min-height: auto;
                border-bottom: 1px solid #eee;
            }
            
            .day-number {
                font-size: 1rem;
                margin-bottom: 8px;
            }
        }
        
        /* Floating Action Buttons */
        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .fab {
            width: 60px;
            height: 60px;
            background: var(--color-primario);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        
        .fab:hover {
            background: var(--color-secundario);
            transform: scale(1.1);
        }
        
        .fab-secondary {
            background: #ff9800;
        }
        
        .fab-secondary:hover {
            background: #f57c00;
        }
        
        /* Task colors by type */
        .task-alumno {
            border-left-color: #4caf50;
            background: #e8f5e8;
        }
        
        .task-profesor {
            border-left-color: #2196f3;
            background: #e3f2fd;
        }
        
        .task-academia {
            border-left-color: #9c27b0;
            background: #f3e5f5;
        }
        
        .event-general {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        
        .color-preview {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid #ddd;
        }
        
        .file-link {
            text-decoration: none;
            color: #1976d2;
            transition: color 0.3s ease;
        }
        
        .file-link:hover {
            color: #1565c0;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="calendar-container">
        <!-- Header del Calendario -->
        <div class="calendar-header">
            <div class="calendar-nav">
                <div>
                    <h1 class="calendar-title">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Calendario - <?php echo $nombres_meses[$mes_actual] . ' ' . $anio_actual; ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        <?php 
                        if ($rol == '1') echo 'Vista de Alumno - Tus tareas pendientes';
                        elseif ($rol == '2') echo 'Vista de Profesor - Tus tareas y eventos';
                        else echo 'Vista de Coordinador - Gestión completa';
                        ?>
                    </p>
                </div>
                <div class="nav-buttons">
                    <a href="calendario.php?mes=<?php echo $mes_anterior; ?>&anio=<?php echo $anio_anterior; ?>" 
                       class="btn btn-calendar">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    <a href="calendario.php?mes=<?php echo date('n'); ?>&anio=<?php echo date('Y'); ?>" 
                       class="btn btn-calendar">
                        Hoy
                    </a>
                    <a href="calendario.php?mes=<?php echo $mes_siguiente; ?>&anio=<?php echo $anio_siguiente; ?>" 
                       class="btn btn-calendar">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Operación realizada exitosamente
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $mensaje_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Grid del Calendario -->
        <div class="calendar-grid">
            <!-- Encabezados de días -->
            <div class="calendar-day-header">Lunes</div>
            <div class="calendar-day-header">Martes</div>
            <div class="calendar-day-header">Miércoles</div>
            <div class="calendar-day-header">Jueves</div>
            <div class="calendar-day-header">Viernes</div>
            <div class="calendar-day-header">Sábado</div>
            <div class="calendar-day-header">Domingo</div>

            <!-- Días del calendario -->
            <?php
            // Espacios vacíos para alinear el primer día
            for ($i = 1; $i < $primer_dia; $i++) {
                echo '<div class="calendar-day other-month"></div>';
            }

            // Días del mes actual
            $hoy = date('j');
            $fecha_actual = date('Y-m-d H:i:s');
            
            for ($dia = 1; $dia <= $dias_en_mes; $dia++) {
                $es_hoy = ($dia == $hoy && $mes_actual == date('n') && $anio_actual == date('Y'));
                $clase_dia = $es_hoy ? 'calendar-day today' : 'calendar-day';
                
                // Contar total de items para este día
                $total_tareas = isset($tareas[$dia]) ? count($tareas[$dia]) : 0;
                $total_eventos = isset($eventos[$dia]) ? count($eventos[$dia]) : 0;
                $total_items = $total_tareas + $total_eventos;
                
                echo "<div class='$clase_dia'>";
                echo "<div class='day-number'>$dia</div>";
                
                if ($total_items > 0) {
                    echo "<div class='items-count'>$total_items " . ($total_items == 1 ? 'item' : 'items') . "</div>";
                }
                
                // CORRECCIÓN: Mostrar todos los items pero ocultar los que exceden el límite
                echo "<div class='day-content" . ($total_items > 3 ? " collapsed" : "") . "' id='day-content-$dia'>";
                
                $items_mostrados = 0;
                $items_ocultos = 0;
                
                // Mostrar tareas para este día
                if (isset($tareas[$dia])) {
                    foreach ($tareas[$dia] as $item) {
                        $items_mostrados++;
                        $es_vencida = strtotime($item['fecha_limite']) < strtotime($fecha_actual);
                        $es_aviso = $item['puntos_maximos'] == 0;
                        $tiene_archivo = !empty($item['archivo_profesor']);
                        
                        $clase_item = 'task-item ';
                        if ($item['tipo'] == 'tarea_academia') {
                            $clase_item .= 'task-academia';
                        } elseif ($rol == '1') {
                            $clase_item .= 'task-alumno';
                        } else {
                            $clase_item .= 'task-profesor';
                        }
                        
                        if ($es_vencida) $clase_item .= ' vencida';
                        if ($es_aviso) $clase_item .= ' aviso';
                        if ($tiene_archivo) $clase_item .= ' archivo';
                        
                        // CORRECCIÓN: Agregar clase para ocultar items cuando hay más de 3
                        if ($total_items > 3 && $items_mostrados > 3) {
                            $clase_item .= ' day-item-hidden';
                            $items_ocultos++;
                        }
                        
                        echo "<div class='$clase_item' data-bs-toggle='modal' data-bs-target='#taskModal' 
                              data-item='" . htmlspecialchars(json_encode($item), ENT_QUOTES) . "'>";
                        echo "<div class='task-title'>" . htmlspecialchars($item['titulo']) . "</div>";
                        
                        if ($item['tipo'] == 'tarea_academia') {
                            echo "<div class='task-meta'>Academia: " . htmlspecialchars($item['academia_nombre']) . "</div>";
                            if ($item['tipo_tarea'] == 'otro') {
                                echo "<div class='task-badge'>Junta</div>";
                            }
                        } else {
                            echo "<div class='task-meta'>" . htmlspecialchars($item['materia_nombre']) . " - " . htmlspecialchars($item['grupo']) . "</div>";
                        }
                        
                        // Mostrar badges
                        if ($tiene_archivo) {
                            echo "<div class='task-badge badge-archivo'><i class='fas fa-paperclip me-1'></i>Archivo</div>";
                        }
                        if (isset($item['unidad']) && $item['unidad']) {
                            echo "<div class='task-badge badge-unidad'>Unidad " . $item['unidad'] . "</div>";
                        }
                        if ($rol == '2' && isset($item['entregas_pendientes'])) {
                            echo "<div class='task-badge'>" . $item['entregas_pendientes'] . " entregas</div>";
                        }
                        
                        echo "</div>";
                    }
                }
                
                // Mostrar eventos para este día
                if (isset($eventos[$dia])) {
                    foreach ($eventos[$dia] as $evento) {
                        $items_mostrados++;
                        $color_style = $evento['color'] ? "border-left-color: {$evento['color']}; background: " . $evento['color'] . "20;" : "";
                        
                        $clase_evento = 'event-item';
                        if (isset($evento['es_multi_dia']) && $evento['es_multi_dia']) {
                            $clase_evento .= ' multi-day';
                            if (isset($evento['es_primer_dia']) && $evento['es_primer_dia']) {
                                $clase_evento .= ' first-day';
                            } elseif (isset($evento['es_ultimo_dia']) && $evento['es_ultimo_dia']) {
                                $clase_evento .= ' last-day';
                            } else {
                                $clase_evento .= ' middle-day';
                            }
                        }
                        
                        // CORRECCIÓN: Agregar clase para ocultar items cuando hay más de 3
                        if ($total_items > 3 && $items_mostrados > 3) {
                            $clase_evento .= ' day-item-hidden';
                            $items_ocultos++;
                        }
                        
                        echo "<div class='$clase_evento' style='$color_style' data-bs-toggle='modal' data-bs-target='#eventModal' 
                              data-evento='" . htmlspecialchars(json_encode($evento), ENT_QUOTES) . "'>";
                        
                        if (isset($evento['es_multi_dia']) && $evento['es_multi_dia']) {
                            echo "<div class='multi-day-indicator'><i class='fas fa-arrows-alt-h'></i></div>";
                        }
                        
                        echo "<div class='event-title'>" . htmlspecialchars($evento['titulo']) . "</div>";
                        echo "<div class='event-meta'>Evento" . ($evento['academia_nombre'] ? " - " . htmlspecialchars($evento['academia_nombre']) : "") . "</div>";
                        
                        // Mostrar rango de fechas para eventos de múltiples días
                        if (isset($evento['es_multi_dia']) && $evento['es_multi_dia']) {
                            $fecha_inicio = date('d/m', strtotime($evento['fecha_inicio']));
                            $fecha_fin = date('d/m', strtotime($evento['fecha_fin']));
                            echo "<div class='event-meta'><small>$fecha_inicio - $fecha_fin</small></div>";
                        }
                        
                        echo "</div>";
                    }
                }
                
                echo "</div>"; // Cierre de day-content
                
                // CORRECCIÓN: Botón para expandir/contraer si hay más de 3 items
                if ($total_items > 3) {
                    echo "<div class='expand-toggle' onclick='toggleDayContent($dia)'>";
                    echo "<i class='fas fa-chevron-down'></i> Ver más ($items_ocultos ocultos)";
                    echo "</div>";
                }
                
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Modal para ver detalles de tarea -->
    <div class="modal fade" id="taskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-task">
                    <h5 class="modal-title" id="taskModalTitle">Detalles de Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="taskModalBody">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalles de evento -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Detalles del Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody">
                    <!-- Contenido dinámico -->
                </div>
                <?php if ($rol == '3'): ?>
                <div class="modal-footer" id="eventModalFooter">
                    <!-- Botones de edición/eliminación para coordinador -->
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para crear nueva tarea (solo profesores) -->
    <?php if ($rol == '2'): ?>
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Crear Nueva Tarea</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data" id="taskForm">
                    <input type="hidden" name="crear_tarea" value="1">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Clase/Grupo:</label>
                            <select name="id_clase" class="form-select" id="id_clase" required onchange="actualizarUnidades()">
                                <option value="">Seleccionar clase...</option>
                                <?php 
                                // Reset el puntero del resultado
                                $clases_profesor->data_seek(0);
                                while($clase = $clases_profesor->fetch_assoc()): ?>
                                    <option value="<?php echo $clase['id_clase']; ?>" data-id-materia="<?php echo $clase['id_materia']; ?>">
                                        <?php echo htmlspecialchars($clase['materia_nombre'] . ' - ' . $clase['grupo']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Título de la tarea:</label>
                            <input type="text" name="titulo" class="form-control" required 
                                   placeholder="Ej: Investigación sobre...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción:</label>
                            <textarea name="descripcion" class="form-control" rows="3" 
                                      placeholder="Describe los detalles de la tarea..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fecha límite:</label>
                                    <input type="datetime-local" name="fecha_limite" class="form-control" required 
                                           min="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Unidad:</label>
                                    <select name="unidad" class="form-select" id="unidad_select" required>
                                        <option value="">Seleccionar unidad...</option>
                                        <!-- Las opciones se llenarán dinámicamente con JavaScript -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Puntos máx:</label>
                                    <input type="number" name="puntos_maximos" class="form-control" 
                                           value="100" min="0" max="1000">
                                    <small class="form-text text-muted">0 puntos = Aviso</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Archivo adjunto (opcional):</label>
                            <input type="file" name="archivo_profesor" class="form-control" 
                                   accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">
                                Formatos permitidos: PDF, Word, TXT, ZIP, RAR, JPG, PNG (Máx: 20MB)
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Tarea</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para crear/editar evento (solo coordinador) -->
    <?php if ($rol == '3'): ?>
    <div class="modal fade" id="createEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="eventModalTitle">Crear Nuevo Evento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="eventForm">
                    <input type="hidden" name="crear_evento" value="1" id="eventActionType">
                    <input type="hidden" name="id_evento" id="editEventId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Título del evento:</label>
                            <input type="text" name="titulo" class="form-control" required 
                                   placeholder="Ej: Junta de academia...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción:</label>
                            <textarea name="descripcion" class="form-control" rows="3" 
                                      placeholder="Describe los detalles del evento..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fecha de inicio:</label>
                                    <input type="datetime-local" name="fecha_inicio" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fecha de fin (opcional):</label>
                                    <input type="datetime-local" name="fecha_fin" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de evento:</label>
                                    <select name="tipo_evento" class="form-select" required>
                                        <option value="general">General</option>
                                        <option value="academia">Academia</option>
                                        <option value="junta">Junta</option>
                                        <option value="evaluacion">Evaluación</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Academia (opcional):</label>
                                    <select name="id_academia" class="form-select">
                                        <option value="">Seleccionar academia...</option>
                                        <?php foreach($academias as $academia): ?>
                                            <option value="<?php echo $academia['id_academia']; ?>">
                                                <?php echo htmlspecialchars($academia['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Color del evento:</label>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="color-preview" id="colorPreview" style="background-color: #007bff;"></span>
                                <input type="color" name="color" class="form-control form-control-color" value="#007bff" 
                                       title="Elige un color para el evento" id="colorPicker">
                                <span id="colorHex">#007bff</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="eventSubmitBtn">Crear Evento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar evento -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="deleteEventForm">
                    <input type="hidden" name="eliminar_evento" value="1">
                    <input type="hidden" name="id_evento" id="deleteEventId">
                    
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar este evento?</p>
                        <p class="fw-bold" id="deleteEventTitle"></p>
                        <p class="text-muted">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Evento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Floating Action Buttons -->
    <div class="fab-container">
        <?php if ($rol == '2'): ?>
            <!-- Botón para crear tarea (profesor) -->
            <div class="fab" data-bs-toggle="modal" data-bs-target="#createTaskModal" title="Crear nueva tarea">
                <i class="fas fa-plus"></i>
            </div>
        <?php elseif ($rol == '3'): ?>
            <!-- Botones para coordinador -->
            <div class="fab fab-secondary" data-bs-toggle="modal" data-bs-target="#createEventModal" title="Crear nuevo evento">
                <i class="fas fa-calendar-plus"></i>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos de las materias para las unidades
        const materiasInfo = <?php echo json_encode($materias_info); ?>;

        // CORRECCIÓN: Función para expandir/contraer el contenido del día
        function toggleDayContent(dia) {
            const content = document.getElementById('day-content-' + dia);
            const toggle = content.nextElementSibling;
            const hiddenItems = content.querySelectorAll('.day-item-hidden');
            
            if (content.classList.contains('collapsed')) {
                // Expandir: mostrar todos los items
                content.classList.remove('collapsed');
                hiddenItems.forEach(item => {
                    item.style.display = 'block';
                    item.style.opacity = '0';
                    setTimeout(() => {
                        item.style.opacity = '1';
                    }, 10);
                });
                toggle.innerHTML = '<i class="fas fa-chevron-up"></i> Ver menos';
            } else {
                // Contraer: ocultar items extras
                content.classList.add('collapsed');
                hiddenItems.forEach(item => {
                    item.style.opacity = '0';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                });
                toggle.innerHTML = '<i class="fas fa-chevron-down"></i> Ver más (' + hiddenItems.length + ' ocultos)';
            }
        }

        // Función para actualizar las unidades según la materia seleccionada
        function actualizarUnidades() {
            const claseSelect = document.getElementById('id_clase');
            const unidadSelect = document.getElementById('unidad_select');
            const selectedOption = claseSelect.options[claseSelect.selectedIndex];
            
            // Limpiar opciones actuales
            unidadSelect.innerHTML = '<option value="">Seleccionar unidad...</option>';
            
            if (selectedOption.value) {
                const idMateria = selectedOption.getAttribute('data-id-materia');
                if (idMateria && materiasInfo[idMateria]) {
                    const unidades = materiasInfo[idMateria].unidades;
                    
                    // Agregar opciones de unidades
                    for (let i = 1; i <= unidades; i++) {
                        const option = document.createElement('option');
                        option.value = i;
                        option.textContent = 'Unidad ' + i;
                        unidadSelect.appendChild(option);
                    }
                }
            }
        }

        // Configurar modal de tarea
        const taskModal = document.getElementById('taskModal');
        if (taskModal) {
            taskModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const itemData = JSON.parse(button.getAttribute('data-item'));
                
                const modalTitle = taskModal.querySelector('.modal-title');
                const modalBody = taskModal.querySelector('.modal-body');
                
                modalTitle.textContent = itemData.titulo;
                
                let contenido = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Tipo:</strong> ${itemData.tipo === 'tarea_academia' ? 'Tarea de Academia' : 'Tarea de Clase'}</p>
                `;
                
                if (itemData.tipo === 'tarea_academia') {
                    contenido += `
                        <p><strong>Academia:</strong> ${itemData.academia_nombre}</p>
                        <p><strong>Tipo:</strong> ${itemData.tipo_tarea === 'otro' ? 'Junta' : itemData.tipo_tarea}</p>
                    `;
                } else {
                    contenido += `
                        <p><strong>Materia:</strong> ${itemData.materia_nombre}</p>
                        <p><strong>Grupo:</strong> ${itemData.grupo}</p>
                        ${itemData.unidad ? `<p><strong>Unidad:</strong> ${itemData.unidad}</p>` : ''}
                    `;
                }
                
                contenido += `
                            <p><strong>Fecha límite:</strong> ${new Date(itemData.fecha_limite).toLocaleString()}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Estado:</strong> <span class="task-status status-active">${itemData.estado}</span></p>
                `;
                
                if (itemData.tipo !== 'tarea_academia') {
                    contenido += `<p><strong>Puntos máximos:</strong> ${itemData.puntos_maximos}</p>`;
                }
                
                // Mostrar archivo adjunto si existe
                if (itemData.archivo_profesor) {
                    contenido += `
                        <p><strong>Archivo adjunto:</strong> 
                            <a href="uploads/tareas/profesor/${itemData.archivo_profesor}" class="file-link" target="_blank" download>
                                <i class="fas fa-download me-1"></i>Descargar archivo
                            </a>
                        </p>
                    `;
                }
                
                <?php if ($rol == '1'): ?>
                    if (itemData.calificacion !== null) {
                        contenido += `<p><strong>Tu calificación:</strong> ${itemData.calificacion}/${itemData.puntos_maximos}</p>`;
                    } else {
                        contenido += `<p><strong>Estado:</strong> <span class="text-warning">Pendiente por entregar</span></p>`;
                    }
                <?php elseif ($rol == '2'): ?>
                    if (itemData.entregas_pendientes !== undefined) {
                        contenido += `<p><strong>Entregas pendientes:</strong> ${itemData.entregas_pendientes}</p>`;
                    }
                <?php else: ?>
                    if (itemData.profesor_nombre) {
                        contenido += `<p><strong>Profesor:</strong> ${itemData.profesor_nombre}</p>`;
                    }
                <?php endif; ?>
                
                // Verificar si está vencida
                const ahora = new Date();
                const fechaLimite = new Date(itemData.fecha_limite);
                if (fechaLimite < ahora) {
                    contenido += `<p><strong class="text-danger">⚠️ Tarea vencida</strong></p>`;
                }
                
                // Verificar si es aviso
                if (itemData.puntos_maximos == 0) {
                    contenido += `<p><strong class="text-info">📢 Este es un aviso informativo</strong></p>`;
                }
                
                contenido += `
                        </div>
                    </div>
                `;
                
                if (itemData.descripcion) {
                    contenido += `
                        <div class="mt-3">
                            <strong>Descripción:</strong>
                            <p class="mt-2">${itemData.descripcion}</p>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = contenido;
            });
        }

        // Configurar modal de evento
        const eventModal = document.getElementById('eventModal');
        if (eventModal) {
            eventModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const eventoData = JSON.parse(button.getAttribute('data-evento'));
                
                const modalTitle = eventModal.querySelector('.modal-title');
                const modalBody = eventModal.querySelector('.modal-body');
                const modalFooter = eventModal.querySelector('.modal-footer');
                
                modalTitle.textContent = eventoData.titulo;
                
                let contenido = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Fecha de inicio:</strong> ${new Date(eventoData.fecha_inicio).toLocaleString()}</p>
                `;
                
                if (eventoData.fecha_fin) {
                    contenido += `<p><strong>Fecha de fin:</strong> ${new Date(eventoData.fecha_fin).toLocaleString()}</p>`;
                }
                
                contenido += `
                            <p><strong>Tipo de evento:</strong> ${eventoData.tipo_evento}</p>
                        </div>
                        <div class="col-md-6">
                `;
                
                if (eventoData.academia_nombre) {
                    contenido += `<p><strong>Academia:</strong> ${eventoData.academia_nombre}</p>`;
                }
                
                contenido += `
                            <p><strong>Color:</strong> 
                                <span class="color-preview" style="background-color: ${eventoData.color || '#007bff'};"></span>
                                ${eventoData.color || '#007bff'}
                            </p>
                        </div>
                    </div>
                `;
                
                // Mostrar información de rango para eventos de múltiples días
                if (eventoData.es_multi_dia) {
                    const fechaInicio = new Date(eventoData.fecha_inicio);
                    const fechaFin = new Date(eventoData.fecha_fin);
                    const diasDuracion = Math.ceil((fechaFin - fechaInicio) / (1000 * 60 * 60 * 24)) + 1;
                    
                    contenido += `
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-arrows-alt-h me-2"></i>
                            <strong>Evento de ${diasDuracion} días:</strong> 
                            Desde ${fechaInicio.toLocaleDateString()} hasta ${fechaFin.toLocaleDateString()}
                        </div>
                    `;
                }
                
                if (eventoData.descripcion) {
                    contenido += `
                        <div class="mt-3">
                            <strong>Descripción:</strong>
                            <p class="mt-2">${eventoData.descripcion}</p>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = contenido;
                
                <?php if ($rol == '3'): ?>
                // Botones de edición y eliminación para coordinador
                if (modalFooter) {
                    modalFooter.innerHTML = `
                        <button type="button" class="btn btn-warning" onclick="editarEvento(${eventoData.id_evento})">
                            <i class="fas fa-edit me-1"></i> Editar
                        </button>
                        <button type="button" class="btn btn-danger" onclick="eliminarEvento(${eventoData.id_evento}, '${eventoData.titulo.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash me-1"></i> Eliminar
                        </button>
                    `;
                }
                <?php endif; ?>
            });
        }

        <?php if ($rol == '3'): ?>
        // Funciones para coordinador
        function editarEvento(idEvento) {
            // Cerrar modal actual
            const eventModal = bootstrap.Modal.getInstance(document.getElementById('eventModal'));
            eventModal.hide();
            
            // Buscar datos del evento
            const eventos = <?php echo json_encode(array_merge(...array_values($eventos))); ?>;
            let eventoData = eventos.find(evento => evento.id_evento == idEvento);
            
            if (eventoData) {
                // Llenar formulario de edición
                document.getElementById('eventModalTitle').textContent = 'Editar Evento';
                document.getElementById('eventActionType').value = 'actualizar_evento';
                document.getElementById('eventActionType').name = 'actualizar_evento';
                document.getElementById('editEventId').value = eventoData.id_evento;
                document.getElementById('eventSubmitBtn').textContent = 'Actualizar Evento';
                document.getElementById('eventSubmitBtn').className = 'btn btn-warning';
                
                // Llenar campos
                document.querySelector('input[name="titulo"]').value = eventoData.titulo;
                document.querySelector('textarea[name="descripcion"]').value = eventoData.descripcion || '';
                
                // Formatear fechas para input datetime-local
                const fechaInicio = new Date(eventoData.fecha_inicio);
                const fechaFin = eventoData.fecha_fin ? new Date(eventoData.fecha_fin) : null;
                
                document.querySelector('input[name="fecha_inicio"]').value = fechaInicio.toISOString().slice(0, 16);
                if (fechaFin) {
                    document.querySelector('input[name="fecha_fin"]').value = fechaFin.toISOString().slice(0, 16);
                }
                
                document.querySelector('select[name="tipo_evento"]').value = eventoData.tipo_evento;
                document.querySelector('select[name="id_academia"]').value = eventoData.id_academia || '';
                document.querySelector('input[name="color"]').value = eventoData.color || '#007bff';
                actualizarPreviewColor(eventoData.color || '#007bff');
                
                // Abrir modal de edición
                const createEventModal = new bootstrap.Modal(document.getElementById('createEventModal'));
                createEventModal.show();
            }
        }
        
        function eliminarEvento(idEvento, titulo) {
            document.getElementById('deleteEventId').value = idEvento;
            document.getElementById('deleteEventTitle').textContent = titulo;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
            deleteModal.show();
        }
        
        function resetEventForm() {
            document.getElementById('eventModalTitle').textContent = 'Crear Nuevo Evento';
            document.getElementById('eventActionType').value = 'crear_evento';
            document.getElementById('eventActionType').name = 'crear_evento';
            document.getElementById('editEventId').value = '';
            document.getElementById('eventSubmitBtn').textContent = 'Crear Evento';
            document.getElementById('eventSubmitBtn').className = 'btn btn-success';
            document.getElementById('eventForm').reset();
            actualizarPreviewColor('#007bff');
        }
        
        function actualizarPreviewColor(color) {
            document.getElementById('colorPreview').style.backgroundColor = color;
            document.getElementById('colorHex').textContent = color;
        }
        
        // Event listener para el color picker
        document.getElementById('colorPicker').addEventListener('input', function(e) {
            actualizarPreviewColor(e.target.value);
        });
        
        // Resetear formulario cuando se cierra el modal
        document.getElementById('createEventModal').addEventListener('hidden.bs.modal', function() {
            resetEventForm();
        });
        <?php endif; ?>

        // Auto-seleccionar fecha actual en los formularios
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.querySelector('input[name="fecha_limite"]');
            if (fechaInput) {
                // Establecer fecha y hora mínima como ahora
                const ahora = new Date();
                ahora.setMinutes(ahora.getMinutes() - ahora.getTimezoneOffset());
                fechaInput.min = ahora.toISOString().slice(0, 16);
                
                // Establecer fecha por defecto (7 días desde hoy a las 23:59)
                const enUnaSemana = new Date();
                enUnaSemana.setDate(enUnaSemana.getDate() + 7);
                enUnaSemana.setHours(23, 59, 0, 0);
                enUnaSemana.setMinutes(enUnaSemana.getMinutes() - enUnaSemana.getTimezoneOffset());
                fechaInput.value = enUnaSemana.toISOString().slice(0, 16);
            }
            
            const fechaInicioInput = document.querySelector('input[name="fecha_inicio"]');
            if (fechaInicioInput) {
                // Establecer fecha y hora actual como mínimo
                const ahora = new Date();
                ahora.setMinutes(ahora.getMinutes() - ahora.getTimezoneOffset());
                fechaInicioInput.min = ahora.toISOString().slice(0, 16);
                
                // Establecer fecha por defecto (mañana a las 9 AM)
                const manana = new Date();
                manana.setDate(manana.getDate() + 1);
                manana.setHours(9, 0, 0, 0);
                manana.setMinutes(manana.getMinutes() - manana.getTimezoneOffset());
                fechaInicioInput.value = manana.toISOString().slice(0, 16);
            }
            
            const fechaFinInput = document.querySelector('input[name="fecha_fin"]');
            if (fechaFinInput) {
                // Establecer misma fecha mínima que inicio
                if (fechaInicioInput) {
                    fechaFinInput.min = fechaInicioInput.min;
                }
            }
            
            // CORRECCIÓN: Inicializar items ocultos al cargar la página
            document.querySelectorAll('.day-content.collapsed').forEach(content => {
                const hiddenItems = content.querySelectorAll('.day-item-hidden');
                hiddenItems.forEach(item => {
                    item.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>
<?php include 'footer.php'; ?>