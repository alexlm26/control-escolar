<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Obtener datos del coordinador
$id_usuario = $_SESSION['id_usuario'];

// Obtener id_carrera del coordinador desde la tabla usuario
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$result_carrera = $conexion->query($sql_carrera);

if (!$result_carrera || $result_carrera->num_rows === 0) {
    header("Location: ../coordinador.php?seccion=clases&error=No se pudo obtener la carrera del coordinador");
    exit;
}

$carrera_data = $result_carrera->fetch_assoc();
$id_carrera = $carrera_data['id_carrera'];

// Obtener id_coordinador desde la tabla coordinador
$sql_coordinador = "SELECT id_coordinador FROM coordinador WHERE id_usuario = $id_usuario";
$result_coordinador = $conexion->query($sql_coordinador);

if (!$result_coordinador || $result_coordinador->num_rows === 0) {
    header("Location: ../coordinador.php?seccion=clases&error=No se pudo obtener información del coordinador");
    exit;
}

$coordinador_data = $result_coordinador->fetch_assoc();
$id_coordinador = $coordinador_data['id_coordinador'];

/* ---------------------------------------------------------
   FUNCIONES PARA VALIDACIÓN DE ALUMNOS
--------------------------------------------------------- */

// Función recursiva para verificar cadena de prerrequisitos
function verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia_actual, &$pendientes, $nivel = 0) {
    if ($nivel > 10) return;
    
    $sql_prerreq = "SELECT id_prerrequisito FROM materia WHERE id_materia = ?";
    $stmt = $conexion->prepare($sql_prerreq);
    $stmt->bind_param("i", $id_materia_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $materia = $result->fetch_assoc();
        $id_prerrequisito = $materia['id_prerrequisito'];
        
        if ($id_prerrequisito) {
            $sql_aprobado = "SELECT 1 FROM materia_cursada 
                            WHERE id_alumno = ? AND id_materia = ? AND aprobado = 1";
            $stmt_aprob = $conexion->prepare($sql_aprobado);
            $stmt_aprob->bind_param("ii", $id_alumno, $id_prerrequisito);
            $stmt_aprob->execute();
            $aprobado = $stmt_aprob->get_result()->num_rows > 0;
            
            if (!$aprobado) {
                $sql_nombre = "SELECT nombre FROM materia WHERE id_materia = ?";
                $stmt_nombre = $conexion->prepare($sql_nombre);
                $stmt_nombre->bind_param("i", $id_prerrequisito);
                $stmt_nombre->execute();
                $nombre_materia = $stmt_nombre->get_result()->fetch_assoc()['nombre'];
                
                $pendientes[] = $nombre_materia;
            }
            
            verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_prerrequisito, $pendientes, $nivel + 1);
        }
    }
}

function verificarCadenaPrerrequisitos($conexion, $id_alumno, $id_materia) {
    $prerrequisitos_pendientes = [];
    verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia, $prerrequisitos_pendientes);
    return $prerrequisitos_pendientes;
}

// Función para verificar compatibilidad de especialidades
function verificarEspecialidadAlumno($conexion, $id_alumno, $id_materia) {
    // Obtener información de la materia (especialidad)
    $sql_materia = "SELECT m.id_especialidad, e.nombre as especialidad_nombre 
                   FROM materia m 
                   LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad 
                   WHERE m.id_materia = ?";
    $stmt = $conexion->prepare($sql_materia);
    $stmt->bind_param("i", $id_materia);
    $stmt->execute();
    $materia_data = $stmt->get_result()->fetch_assoc();
    
    // Si la materia es de especialidad general (id=1), cualquier alumno puede tomarla
    if ($materia_data['id_especialidad'] == 1) {
        return ['compatible' => true, 'razon' => ''];
    }
    
    // Obtener especialidad del alumno
    $sql_alumno = "SELECT a.id_especialidad, e.nombre as especialidad_nombre 
                  FROM alumno a 
                  LEFT JOIN especialidad e ON a.id_especialidad = e.id_especialidad 
                  WHERE a.id_alumno = ?";
    $stmt_alumno = $conexion->prepare($sql_alumno);
    $stmt_alumno->bind_param("i", $id_alumno);
    $stmt_alumno->execute();
    $alumno_data = $stmt_alumno->get_result()->fetch_assoc();
    
    // Verificar si el alumno tiene la misma especialidad que la materia
    if ($alumno_data['id_especialidad'] == $materia_data['id_especialidad']) {
        return ['compatible' => true, 'razon' => ''];
    }
    
    // Si no son compatibles, retornar razón
    return [
        'compatible' => false,
        'razon' => "El alumno tiene especialidad '{$alumno_data['especialidad_nombre']}' pero la materia es de especialidad '{$materia_data['especialidad_nombre']}'"
    ];
}

// Función para determinar siguiente oportunidad
function obtenerSiguienteOportunidad($ultima_oportunidad, $nivel = 0) {
    if (empty($ultima_oportunidad) || $ultima_oportunidad === 'null' || $ultima_oportunidad === '') {
        return 'Ordinario';
    }
    
    $ultima = strtolower(trim((string)$ultima_oportunidad));
    
    if ($nivel > 10) {
        return 'Global';
    }
    
    $oportunidades = [
        'ordinario' => 'Recurse',
        'recurse' => 'Especial', 
        'recursamiento' => 'Especial',
        'especial' => 'Global',
        'global' => 'Global'
    ];
    
    $siguiente = $oportunidades[$ultima] ?? 'Ordinario';
    
    if ($siguiente === $ultima_oportunidad) {
        return $siguiente;
    }
    
    return obtenerSiguienteOportunidad($siguiente, $nivel + 1);
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

/* ---------------------------------------------------------
   FUNCIÓN PARA OBTENER HORARIOS DISPONIBLES DEL PROFESOR
--------------------------------------------------------- */

function obtenerHorariosDisponiblesProfesor($conexion, $id_profesor, $dia) {
    $horarios_ocupados = [];
    $horarios_disponibles = [];
    
    // Obtener horarios ocupados del profesor en el día específico
    $sql_ocupados = "
        SELECT DISTINCT hc.hora 
        FROM horarios_clase hc
        INNER JOIN clase c ON hc.id_clase = c.id_clase
        WHERE c.id_profesor = ? AND hc.dia = ? AND c.activo = 1
    ";
    
    $stmt = $conexion->prepare($sql_ocupados);
    $stmt->bind_param("ii", $id_profesor, $dia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $horarios_ocupados[] = $row['hora'];
    }
    
    // Generar lista de todos los horarios posibles (7:00 a 21:00)
    $horarios_totales = range(7, 21);
    
    // Filtrar horarios disponibles
    foreach ($horarios_totales as $hora) {
        if (!in_array($hora, $horarios_ocupados)) {
            $horarios_disponibles[] = $hora;
        }
    }
    
    return $horarios_disponibles;
}

/* ---------------------------------------------------------
   PROCESAR FORMULARIO
--------------------------------------------------------- */

$mensaje = "";

if ($_POST) {
    $id_materia = $_POST['id_materia'];
    $id_profesor = $_POST['id_profesor'];
    $id_salon = $_POST['id_salon'];
    $periodo = $_POST['periodo'];
    $capacidad = $_POST['capacidad'];
    $grupo = $_POST['grupo'];
    $horarios = $_POST['horarios'] ?? [];
    
    // DEBUG: Verificar datos recibidos
    error_log("DEBUG - Datos recibidos:");
    error_log("id_materia: " . $id_materia);
    error_log("id_profesor: " . $id_profesor);
    error_log("id_salon: " . $id_salon);
    error_log("grupo_existente: " . ($_POST['grupo_existente'] ?? 'NO'));
    error_log("id_clase_origen: " . ($_POST['id_clase_origen'] ?? 'NO'));
    error_log("archivo_csv: " . (isset($_FILES['archivo_csv']) ? 'SÍ' : 'NO'));
    
    // Validar datos básicos
    if (empty($id_materia) || empty($id_profesor) || empty($id_salon) || empty($periodo) || empty($capacidad) || empty($grupo)) {
        $mensaje = "<div class='alert alert-error'>❌ Todos los campos son obligatorios</div>";
    } elseif (empty($horarios)) {
        $mensaje = "<div class='alert alert-error'>❌ Debe agregar al menos un horario</div>";
    } else {
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Crear la clase
            $sql_clase = "INSERT INTO clase (id_materia, id_profesor, id_salon, periodo, capacidad, grupo, activo) 
                         VALUES ($id_materia, $id_profesor, $id_salon, '$periodo', $capacidad, '$grupo', 1)";
            
            if (!$conexion->query($sql_clase)) {
                throw new Exception("Error al crear clase: " . $conexion->error);
            }
            
            $id_clase = $conexion->insert_id;
            
            // Insertar horarios
            foreach ($horarios as $horario) {
                $dia = intval($horario['dia']);
                $hora = intval($horario['hora']);
                
                if ($dia < 1 || $dia > 5 || $hora < 1 || $hora > 21) {
                    throw new Exception("Horario inválido: Día $dia, Hora $hora");
                }
                
                $sql_horario = "INSERT INTO horarios_clase (id_clase, dia, hora) 
                               VALUES ($id_clase, $dia, $hora)";
                
                if (!$conexion->query($sql_horario)) {
                    throw new Exception("Error al crear horario: " . $conexion->error);
                }
            }
            
            /* ---------------------------------------------------------
               PROCESAR ALUMNOS - GRUPO EXISTENTE O CSV
            --------------------------------------------------------- */
            
            $alumnos_agregados = 0;
            $alumnos_omitidos = 0;
            $detalles_omision = [];
            
            // OPCIÓN 1: Agregar desde grupo existente
            if (isset($_POST['grupo_existente']) && !empty($_POST['id_clase_origen'])) {
                $id_clase_origen = intval($_POST['id_clase_origen']);
                
                // Obtener alumnos del grupo origen
                $sql_alumnos_origen = "
                    SELECT a.id_alumno, asig.oportunidad, u.clave, u.nombre, u.apellidos
                    FROM asignacion asig 
                    INNER JOIN alumno a ON asig.id_alumno = a.id_alumno 
                    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                    WHERE asig.id_clase = ?
                ";
                $stmt = $conexion->prepare($sql_alumnos_origen);
                $stmt->bind_param("i", $id_clase_origen);
                $stmt->execute();
                $alumnos_origen = $stmt->get_result();
                
                while ($alumno = $alumnos_origen->fetch_assoc()) {
                    $id_alumno = $alumno['id_alumno'];
                    $oportunidad_origen = $alumno['oportunidad'];
                    $nombre_alumno = $alumno['nombre'] . ' ' . $alumno['apellidos'];
                    $matricula = $alumno['clave'];
                    
                    // Verificar prerrequisitos
                    $prerrequisitos_pendientes = verificarCadenaPrerrequisitos($conexion, $id_alumno, $id_materia);
                    
                    // Verificar especialidad
                    $validacion_especialidad = verificarEspecialidadAlumno($conexion, $id_alumno, $id_materia);
                    
                    if (empty($prerrequisitos_pendientes) && $validacion_especialidad['compatible']) {
                        // Insertar alumno en nueva clase
                        $sql_insert = "INSERT INTO asignacion (id_clase, id_alumno, oportunidad) VALUES (?, ?, ?)";
                        $stmt_insert = $conexion->prepare($sql_insert);
                        $stmt_insert->bind_param("iis", $id_clase, $id_alumno, $oportunidad_origen);
                        
                        if ($stmt_insert->execute()) {
                            $alumnos_agregados++;
                        } else {
                            $alumnos_omitidos++;
                            $detalles_omision[] = "$matricula - $nombre_alumno: Error al insertar en la base de datos";
                        }
                    } else {
                        $alumnos_omitidos++;
                        $razones = [];
                        
                        if (!empty($prerrequisitos_pendientes)) {
                            $razones[] = "Prerrequisitos pendientes: " . implode(', ', $prerrequisitos_pendientes);
                        }
                        
                        if (!$validacion_especialidad['compatible']) {
                            $razones[] = $validacion_especialidad['razon'];
                        }
                        
                        $detalles_omision[] = "$matricula - $nombre_alumno: " . implode('; ', $razones);
                    }
                }
            }
            
            // OPCIÓN 2: Agregar desde CSV - CORREGIDO
            if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
                error_log("DEBUG - Procesando archivo CSV");
                
                $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
                $matriculas = leerArchivoCSV($archivo_tmp);
                
                error_log("DEBUG - Matrículas encontradas en CSV: " . count($matriculas));
                
                foreach ($matriculas as $matricula) {
                    // Buscar alumno por matrícula
                    $sql_alumno = "
                        SELECT a.id_alumno, u.nombre, u.apellidos,
                               (SELECT mc.oportunidad FROM materia_cursada mc 
                                WHERE mc.id_alumno = a.id_alumno 
                                AND mc.id_materia = ? 
                                ORDER BY mc.id_materia_cursada DESC LIMIT 1) as ultima_oportunidad
                        FROM alumno a 
                        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                        WHERE u.clave = ? AND a.estado = '1'
                    ";
                    $stmt = $conexion->prepare($sql_alumno);
                    $stmt->bind_param("is", $id_materia, $matricula);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $alumno_data = $result->fetch_assoc();
                        $id_alumno = $alumno_data['id_alumno'];
                        $ultima_oportunidad = $alumno_data['ultima_oportunidad'];
                        $nombre_alumno = $alumno_data['nombre'] . ' ' . $alumno_data['apellidos'];
                        
                        // Verificar prerrequisitos
                        $prerrequisitos_pendientes = verificarCadenaPrerrequisitos($conexion, $id_alumno, $id_materia);
                        
                        // Verificar especialidad
                        $validacion_especialidad = verificarEspecialidadAlumno($conexion, $id_alumno, $id_materia);
                        
                        if (empty($prerrequisitos_pendientes) && $validacion_especialidad['compatible']) {
                            // Determinar oportunidad
                            $oportunidad = obtenerSiguienteOportunidad($ultima_oportunidad);
                            
                            // Insertar alumno
                            $sql_insert = "INSERT INTO asignacion (id_clase, id_alumno, oportunidad) VALUES (?, ?, ?)";
                            $stmt_insert = $conexion->prepare($sql_insert);
                            $stmt_insert->bind_param("iis", $id_clase, $id_alumno, $oportunidad);
                            
                            if ($stmt_insert->execute()) {
                                $alumnos_agregados++;
                            } else {
                                $alumnos_omitidos++;
                                $detalles_omision[] = "$matricula - $nombre_alumno: Error al insertar en la base de datos";
                            }
                        } else {
                            $alumnos_omitidos++;
                            $razones = [];
                            
                            if (!empty($prerrequisitos_pendientes)) {
                                $razones[] = "Prerrequisitos pendientes: " . implode(', ', $prerrequisitos_pendientes);
                            }
                            
                            if (!$validacion_especialidad['compatible']) {
                                $razones[] = $validacion_especialidad['razon'];
                            }
                            
                            $detalles_omision[] = "$matricula - $nombre_alumno: " . implode('; ', $razones);
                        }
                    } else {
                        $alumnos_omitidos++;
                        $detalles_omision[] = "$matricula: No encontrado o inactivo";
                    }
                }
            }
            
            $conexion->commit();
            
            // Mensaje de éxito
            $mensaje_success = "✅ Clase creada correctamente con horarios";
            if ($alumnos_agregados > 0) {
                $mensaje_success .= " y $alumnos_agregados alumnos agregados";
            }
            if ($alumnos_omitidos > 0) {
                $mensaje_success .= " ($alumnos_omitidos omitidos)";
                
                // Guardar detalles de omisión en sesión para mostrar en la página de destino
                $_SESSION['detalles_omision_alumnos'] = $detalles_omision;
            }
            
            header("Location: ../coordinador.php?seccion=clases&mensaje=" . urlencode($mensaje_success));
            exit;
            
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "<div class='alert alert-error'>❌ " . $e->getMessage() . "</div>";
        }
    }
}

// Obtener materias de la carrera (con información de especialidad)
$materias = $conexion->query("
    SELECT m.id_materia, m.nombre, e.nombre as especialidad_nombre 
    FROM materia m 
    LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad 
    WHERE m.id_carrera = $id_carrera 
    ORDER BY m.nombre
");

// Obtener todos los profesores del coordinador (para carga inicial)
$profesores_todos = $conexion->query("
    SELECT p.id_profesor, u.nombre, u.apellidos, u.clave 
    FROM profesor p 
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
    WHERE p.id_coordinador = $id_coordinador AND p.estado = '1'
    ORDER BY u.nombre, u.apellidos
");

// Obtener salones
$salones = $conexion->query("SELECT id_salon, nombre, edificio FROM salon ORDER BY edificio, nombre");

// Obtener clases existentes para copiar alumnos
$clases_existentes = $conexion->query("
    SELECT c.id_clase, m.nombre as materia, c.grupo 
    FROM clase c 
    INNER JOIN materia m ON c.id_materia = m.id_materia 
    WHERE c.activo = 1 AND m.id_carrera = $id_carrera
    ORDER BY m.nombre, c.grupo
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREAR CLASE</title>
    <style>
        body {
            font-family: "Poppins", "Segoe UI", sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1565c0;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #1565c0;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1em;
        }
        .btn-primary {
            background: #1565c0;
            color: white;
        }
        .btn-primary:hover {
            background: #1976d2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #1565c0;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }
        /* Estilos para horarios */
        .horarios-section {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .horarios-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .horario-item {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .horario-input {
            flex: 1;
        }
        .horario-input select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .sin-horarios {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        .info-text {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        /* Sección de alumnos */
        .alumnos-section {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .alumnos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .opcion-alumnos {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        .opcion-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 10px 0;
            background: #fafafa;
        }
        .upload-area:hover {
            border-color: #1565c0;
            background: #f0f8ff;
        }
        .metodo-seleccion {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .metodo-option {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .metodo-option:hover {
            border-color: #1565c0;
        }
        .metodo-option.active {
            border-color: #1565c0;
            background: #f0f7ff;
        }
        .metodo-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .horario-disponible {
            color: #28a745;
        }
        .horario-ocupado {
            color: #dc3545;
            text-decoration: line-through;
        }
        .filtro-profesor {
            background: #f0f7ff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filtro-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .filtro-icon {
            font-size: 1.5em;
            color: #1565c0;
        }
        .filtro-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .metodo-seleccion {
                grid-template-columns: 1fr;
            }
            .filtro-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nueva Clase</h1>
        
        <?php echo $mensaje; ?>
        
        <form method="POST" action="" id="formClase" enctype="multipart/form-data">
            <!-- INFORMACIÓN BÁSICA DE LA CLASE -->
            <div class="form-group">
                <label>Materia:</label>
                <select name="id_materia" class="form-control" required>
                    <option value="">Seleccionar materia</option>
                    <?php while($m = $materias->fetch_assoc()): ?>
                        <option value="<?php echo $m['id_materia']; ?>">
                            <?php echo htmlspecialchars($m['nombre']); ?>
                            <?php if ($m['especialidad_nombre'] && $m['especialidad_nombre'] != 'General'): ?>
                                (<?php echo htmlspecialchars($m['especialidad_nombre']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- NUEVA SECCIÓN DE FILTRO PARA PROFESORES -->
            <div class="filtro-profesor">
                <div class="filtro-header">
                    <div class="filtro-icon"></div>
                    <h3 style="margin: 0; color: #1565c0;">Filtrar Profesores</h3>
                </div>
                
                <div class="filtro-grid">
                    <div>
                        <label>Tipo de filtro:</label>
                        <select id="filtroTipo" class="form-control" onchange="cambiarFiltro()">
                            <option value="">-- Sin filtro --</option>
                            <option value="carrera">Todos los profesores de la carrera</option>
                            <option value="academia">Por academia</option>
                        </select>
                    </div>
                    <div>
                        <label>Filtro específico:</label>
                        <select id="filtroValor" class="form-control" style="display: none;" onchange="cargarProfesoresFiltrados()">
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Profesor:</label>
                <select name="id_profesor" id="selectProfesor" class="form-control" required onchange="actualizarHorariosDisponibles()">
                    <option value="">Seleccionar profesor</option>
                    <?php while($p = $profesores_todos->fetch_assoc()): ?>
                        <option value="<?php echo $p['id_profesor']; ?>">
                            <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos'] . ' (' . $p['clave'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="info-text" id="contadorProfesores">
                    <?php 
                    $total_profesores = $profesores_todos->num_rows;
                    echo "Total de profesores disponibles: $total_profesores";
                    ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Salón:</label>
                <select name="id_salon" class="form-control" required>
                    <option value="">Seleccionar salón</option>
                    <?php while($s = $salones->fetch_assoc()): ?>
                        <option value="<?php echo $s['id_salon']; ?>">
                            <?php echo htmlspecialchars($s['nombre'] . ' - ' . $s['edificio']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
           <div class="form-group">
    <label>Período:</label>
    <select name="periodo" class="form-control" required>
        <option value="">Seleccionar período...</option>
        <?php
        $current_year = date('Y');
        $next_year = $current_year + 1;
        
        // Opciones para el año actual
        echo '<option value="ENERO-JULIO ' . $current_year . '">ENERO - JULIO ' . $current_year . '</option>';
        echo '<option value="AGOSTO-DICIEMBRE ' . $current_year . '">AGOSTO - DICIEMBRE ' . $current_year . '</option>';
        echo '<option value="VERANO ' . $current_year . '">VERANO ' . $current_year . '</option>';
        
        // Opciones para el próximo año
        echo '<option value="ENERO-JULIO ' . $next_year . '">ENERO - JULIO ' . $next_year . '</option>';
        echo '<option value="AGOSTO-DICIEMBRE ' . $next_year . '">AGOSTO - DICIEMBRE ' . $next_year . '</option>';
        echo '<option value="VERANO ' . $next_year . '">VERANO ' . $next_year . '</option>';
        ?>
    </select>
</div>
            
            <div class="form-group">
                <label>Capacidad:</label>
                <input type="number" name="capacidad" class="form-control" value="35" min="1" max="50" required>
            </div>
            
            <div class="form-group">
                <label>Grupo:</label>
                <input type="text" name="grupo" class="form-control" placeholder="Ej: 101, 201, A, B, etc." maxlength="15" required>
                <div class="info-text">Ejemplos: 101, 201, A, B, MAT-101, etc.</div>
            </div>
            
            <!-- SECCIÓN DE HORARIOS -->
            <div class="horarios-section">
                <div class="horarios-header">
                    <h3 style="margin: 0; color: #1565c0;">Horarios de la Clase</h3>
                    <button type="button" class="btn btn-success" onclick="agregarHorario()">
                        + Agregar Horario
                    </button>
                </div>
                
                <div class="info-text">
                    <strong>Días:</strong> 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes<br>
                    <strong>Horas:</strong> 7:00-8:00, 8:00-9:00, ..., 19:00-20:00, 20:00-21:00, 21:00-22:00<br>
                    <strong>Nota:</strong> Los horarios se filtran automáticamente según la disponibilidad del profesor seleccionado
                </div>
                
                <div id="lista-horarios">
                    <div class="sin-horarios" id="sin-horarios">
                        No hay horarios agregados. Haz clic en "Agregar Horario" para comenzar.
                    </div>
                </div>
            </div>
            
            <!-- SECCIÓN DE ALUMNOS -->
            <div class="alumnos-section">
                <div class="alumnos-header">
                    <h3 style="margin: 0; color: #1565c0;">Agregar Alumnos a la Clase</h3>
                    <small style="color: #666;">Opcional - Puedes agregarlos después</small>
                </div>
                
                <div class="info-text" style="margin-bottom: 20px;">
                    <strong>Nota:</strong> Solo se agregarán alumnos que cumplan con:
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li>La cadena completa de prerrequisitos de la materia</li>
                        <li>La especialidad de la materia (o materia general)</li>
                    </ul>
                </div>
                
                <div class="metodo-seleccion">
                    <!-- OPCIÓN 1: Grupo Existente -->
                    <div class="metodo-option" onclick="seleccionarMetodo('grupo')">
                        <div class="opcion-header">
                            <div class="metodo-icon">👥</div>
                            <div>
                                <h4 style="margin: 0;">Copiar de Grupo Existente</h4>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">
                                    Copia todos los alumnos de otra clase
                                </p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Seleccionar clase origen:</label>
                            <select name="id_clase_origen" class="form-control" onchange="marcarGrupoExistente()">
                                <option value="">-- Seleccionar clase --</option>
                                <?php while($clase = $clases_existentes->fetch_assoc()): ?>
                                    <option value="<?php echo $clase['id_clase']; ?>">
                                        <?php echo htmlspecialchars($clase['materia'] . ' - Grupo ' . $clase['grupo']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <input type="hidden" name="grupo_existente" id="grupo_existente" value="">
                        </div>
                    </div>
                    
                    <!-- OPCIÓN 2: CSV -->
                    <div class="metodo-option" onclick="seleccionarMetodo('csv')">
                        <div class="opcion-header">
                            <div class="metodo-icon">📁</div>
                            <div>
                                <h4 style="margin: 0;">Importar desde CSV</h4>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">
                                    Sube un archivo con matrículas
                                </p>
                            </div>
                        </div>
                        <div class="upload-area">
                            <p style="margin: 0 0 10px 0; font-weight: 600;">📋 Formato del archivo CSV:</p>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 0.8em; margin: 10px 0;">
matricula
S25120001
S25120002
G25120015
T25120025</pre>
                            <input type="file" name="archivo_csv" accept=".csv" onchange="marcarCSV()">
                            <p style="margin: 10px 0 0 0; font-size: 0.8em; color: #666;">
                                Solo archivos CSV con matrículas en la primera columna
                            </p>
                            <input type="hidden" name="usar_csv" id="usar_csv" value="">
                        </div>
                    </div>
                </div>
                
                <div class="info-text" style="background: #e3f2fd; padding: 10px; border-radius: 5px;">
                    <strong>💡 Importante:</strong> Los alumnos se validarán automáticamente contra los prerrequisitos y especialidad de la materia.
                    Solo se agregarán aquellos que tengan toda la cadena de prerrequisitos aprobada y especialidad compatible.
                </div>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Clase y Agregar Alumnos</button>
                <a href="../coordinador.php?seccion=clases" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        let contadorHorarios = 0;
        let horariosDisponibles = {};
        
        // ========== FUNCIONES PARA FILTRO DE PROFESORES ==========
        
        // Obtener academias disponibles para la carrera del coordinador
        async function cargarAcademias() {
            try {
                const response = await fetch('obtener_academias.php');
                const academias = await response.json();
                
                const selectFiltro = document.getElementById('filtroValor');
                selectFiltro.innerHTML = '<option value="">Seleccionar academia...</option>';
                
                academias.forEach(academia => {
                    const option = document.createElement('option');
                    option.value = academia.id_academia;
                    option.textContent = academia.nombre + 
                        (academia.especialidad_nombre ? ` (${academia.especialidad_nombre})` : '');
                    selectFiltro.appendChild(option);
                });
                
            } catch (error) {
                console.error('Error al cargar academias:', error);
            }
        }
        
        // Cambiar tipo de filtro
        function cambiarFiltro() {
            const filtroTipo = document.getElementById('filtroTipo').value;
            const filtroValor = document.getElementById('filtroValor');
            
            if (filtroTipo === 'academia') {
                filtroValor.style.display = 'block';
                cargarAcademias();
            } else if (filtroTipo === 'carrera') {
                filtroValor.style.display = 'none';
                // Cargar todos los profesores de la carrera
                cargarProfesoresFiltrados('carrera', 0);
            } else {
                filtroValor.style.display = 'none';
                // Sin filtro - recargar todos los profesores
                cargarProfesoresFiltrados('', 0);
            }
        }
        
        // Cargar profesores según filtro
        async function cargarProfesoresFiltrados() {
            const filtroTipo = document.getElementById('filtroTipo').value;
            let idFiltro = 0;
            
            if (filtroTipo === 'academia') {
                idFiltro = document.getElementById('filtroValor').value;
                if (!idFiltro) return;
            }
            
            try {
                const response = await fetch(`obtener_profesores_filtrados.php?filtro=${filtroTipo}&id_filtro=${idFiltro}`);
                const profesores = await response.json();
                
                const selectProfesor = document.getElementById('selectProfesor');
                const contadorProfesores = document.getElementById('contadorProfesores');
                
                // Guardar selección actual
                const profesorSeleccionado = selectProfesor.value;
                
                // Limpiar y repoblar
                selectProfesor.innerHTML = '<option value="">Seleccionar profesor</option>';
                
                profesores.forEach(profesor => {
                    const option = document.createElement('option');
                    option.value = profesor.id_profesor;
                    option.textContent = `${profesor.nombre} ${profesor.apellidos} (${profesor.clave})`;
                    
                    // Restaurar selección si existe
                    if (profesor.id_profesor == profesorSeleccionado) {
                        option.selected = true;
                    }
                    
                    selectProfesor.appendChild(option);
                });
                
                // Actualizar contador
                contadorProfesores.textContent = `Profesores encontrados: ${profesores.length}`;
                
                // Actualizar horarios disponibles si hay un profesor seleccionado
                if (profesorSeleccionado) {
                    actualizarHorariosDisponibles();
                }
                
            } catch (error) {
                console.error('Error al cargar profesores:', error);
                alert('Error al cargar la lista de profesores');
            }
        }
        
        // ========== FUNCIONES EXISTENTES PARA HORARIOS ==========
        
        // Función para obtener horarios disponibles del profesor
        async function obtenerHorariosProfesor(idProfesor) {
            if (!idProfesor) return {};
            
            try {
                const response = await fetch(`obtener_horarios_profesor.php?id_profesor=${idProfesor}`);
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error al obtener horarios:', error);
                return {};
            }
        }
        
        // Función para actualizar horarios disponibles cuando cambia el profesor
        async function actualizarHorariosDisponibles() {
            const idProfesor = document.getElementById('selectProfesor').value;
            
            if (idProfesor) {
                horariosDisponibles = await obtenerHorariosProfesor(idProfesor);
                actualizarOpcionesHorarios();
            } else {
                horariosDisponibles = {};
                actualizarOpcionesHorarios();
            }
        }
        
        // Función para actualizar las opciones de horarios en todos los select existentes
        function actualizarOpcionesHorarios() {
            const selectsHora = document.querySelectorAll('select[name^="horarios"][name$="[hora]"]');
            
            selectsHora.forEach(select => {
                const diaSelect = select.closest('.horario-item').querySelector('select[name^="horarios"][name$="[dia]"]');
                const diaSeleccionado = diaSelect ? diaSelect.value : '';
                
                // Guardar la selección actual
                const horaSeleccionada = select.value;
                
                // Limpiar opciones
                select.innerHTML = '<option value="">Seleccionar hora</option>';
                
                // Agregar todas las horas posibles
                for (let hora = 7; hora <= 21; hora++) {
                    const horaFormateada = hora + ':00-' + (hora + 1) + ':00';
                    const option = document.createElement('option');
                    option.value = hora;
                    option.textContent = horaFormateada;
                    
                    // Marcar como ocupado si no está disponible para el día seleccionado
                    if (diaSeleccionado && horariosDisponibles[diaSeleccionado]) {
                        const disponible = horariosDisponibles[diaSeleccionado].includes(hora);
                        if (!disponible) {
                            option.disabled = true;
                            option.textContent += ' (OCUPADO)';
                            option.classList.add('horario-ocupado');
                        } else {
                            option.classList.add('horario-disponible');
                        }
                    }
                    
                    // Restaurar selección anterior si sigue disponible
                    if (hora == horaSeleccionada) {
                        option.selected = true;
                    }
                    
                    select.appendChild(option);
                }
            });
        }
        
        // Función para actualizar horas cuando cambia el día
        function actualizarHorasPorDia(diaSelect) {
            const horarioItem = diaSelect.closest('.horario-item');
            const horaSelect = horarioItem.querySelector('select[name^="horarios"][name$="[hora]"]');
            const diaSeleccionado = diaSelect.value;
            
            if (horaSelect) {
                // Guardar selección actual
                const horaSeleccionada = horaSelect.value;
                
                // Limpiar y repoblar
                horaSelect.innerHTML = '<option value="">Seleccionar hora</option>';
                
                for (let hora = 7; hora <= 21; hora++) {
                    const horaFormateada = hora + ':00-' + (hora + 1) + ':00';
                    const option = document.createElement('option');
                    option.value = hora;
                    option.textContent = horaFormateada;
                    
                    // Marcar disponibilidad
                    if (diaSeleccionado && horariosDisponibles[diaSeleccionado]) {
                        const disponible = horariosDisponibles[diaSeleccionado].includes(hora);
                        if (!disponible) {
                            option.disabled = true;
                            option.textContent += ' (OCUPADO)';
                            option.classList.add('horario-ocupado');
                        } else {
                            option.classList.add('horario-disponible');
                        }
                    }
                    
                    // Restaurar selección si es válida
                    if (hora == horaSeleccionada && !option.disabled) {
                        option.selected = true;
                    }
                    
                    horaSelect.appendChild(option);
                }
            }
        }
        
        function agregarHorario() {
            contadorHorarios++;
            
            document.getElementById('sin-horarios').style.display = 'none';
            
            const listaHorarios = document.getElementById('lista-horarios');
            
            const horarioDiv = document.createElement('div');
            horarioDiv.className = 'horario-item';
            horarioDiv.innerHTML = `
                <div class="horario-input">
                    <label>Día:</label>
                    <select name="horarios[${contadorHorarios}][dia]" required onchange="actualizarHorasPorDia(this)">
                        <option value="">Seleccionar día</option>
                        <option value="1">1 - Lunes</option>
                        <option value="2">2 - Martes</option>
                        <option value="3">3 - Miércoles</option>
                        <option value="4">4 - Jueves</option>
                        <option value="5">5 - Viernes</option>
                    </select>
                </div>
                <div class="horario-input">
                    <label>Hora:</label>
                    <select name="horarios[${contadorHorarios}][hora]" required>
                        <option value="">Seleccionar hora</option>
                        ${generarOpcionesHoras()}
                    </select>
                </div>
                <button type="button" class="btn btn-danger" onclick="eliminarHorario(this)">Eliminar</button>
            `;
            
            listaHorarios.appendChild(horarioDiv);
        }
        
        // Función auxiliar para generar opciones de horas
        function generarOpcionesHoras() {
            let options = '';
            for (let hora = 7; hora <= 21; hora++) {
                const horaFormateada = hora + ':00-' + (hora + 1) + ':00';
                options += `<option value="${hora}">${horaFormateada}</option>`;
            }
            return options;
        }
        
        function eliminarHorario(boton) {
            const horarioDiv = boton.parentElement;
            horarioDiv.remove();
            
            const listaHorarios = document.getElementById('lista-horarios');
            if (listaHorarios.children.length === 1) {
                document.getElementById('sin-horarios').style.display = 'block';
            }
        }
        
        // ========== FUNCIONES PARA ALUMNOS ==========
        
        function seleccionarMetodo(metodo) {
            // Remover clase active de todos
            document.querySelectorAll('.metodo-option').forEach(option => {
                option.classList.remove('active');
            });
            
            // Activar el seleccionado
            event.currentTarget.classList.add('active');
            
            // Limpiar el otro método si se selecciona uno
            if (metodo === 'grupo') {
                document.querySelector('input[name="archivo_csv"]').value = '';
                document.getElementById('usar_csv').value = '';
            } else {
                document.querySelector('select[name="id_clase_origen"]').value = '';
                document.getElementById('grupo_existente').value = '';
            }
        }
        
        function marcarGrupoExistente() {
            const selectClase = document.querySelector('select[name="id_clase_origen"]');
            if (selectClase.value) {
                document.getElementById('grupo_existente').value = '1';
                // Activar visualmente esta opción
                seleccionarMetodo('grupo');
            }
        }
        
        function marcarCSV() {
            const fileInput = document.querySelector('input[name="archivo_csv"]');
            if (fileInput.files.length > 0) {
                document.getElementById('usar_csv').value = '1';
                // Activar visualmente esta opción
                seleccionarMetodo('csv');
            }
        }
        
        // Validar formulario antes de enviar
        document.getElementById('formClase').addEventListener('submit', function(e) {
            const horarios = document.querySelectorAll('.horario-item');
            if (horarios.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un horario para la clase.');
                return false;
            }
            
            // Verificar que no haya horarios ocupados seleccionados
            let horariosOcupados = false;
            horarios.forEach(horario => {
                const horaSelect = horario.querySelector('select[name^="horarios"][name$="[hora]"]');
                if (horaSelect && horaSelect.options[horaSelect.selectedIndex].disabled) {
                    horariosOcupados = true;
                }
            });
            
            if (horariosOcupados) {
                e.preventDefault();
                alert('No puede seleccionar horarios marcados como OCUPADO. Por favor, elija horarios disponibles.');
                return false;
            }
            
            // Debug: mostrar qué métodos están activos
            console.log('Grupo existente:', document.getElementById('grupo_existente').value);
            console.log('Usar CSV:', document.getElementById('usar_csv').value);
            console.log('Archivo CSV:', document.querySelector('input[name="archivo_csv"]').files[0]?.name);
        });
        
        // Agregar 5 horarios por defecto al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('sin-horarios').style.display = 'none';
            
            for (let dia = 1; dia <= 5; dia++) {
                contadorHorarios++;
                
                const listaHorarios = document.getElementById('lista-horarios');
                
                const horarioDiv = document.createElement('div');
                horarioDiv.className = 'horario-item';
                horarioDiv.innerHTML = `
                    <div class="horario-input">
                        <label>Día:</label>
                        <select name="horarios[${contadorHorarios}][dia]" required onchange="actualizarHorasPorDia(this)">
                            <option value="">Seleccionar día</option>
                            <option value="1" ${dia === 1 ? 'selected' : ''}>1 - Lunes</option>
                            <option value="2" ${dia === 2 ? 'selected' : ''}>2 - Martes</option>
                            <option value="3" ${dia === 3 ? 'selected' : ''}>3 - Miércoles</option>
                            <option value="4" ${dia === 4 ? 'selected' : ''}>4 - Jueves</option>
                            <option value="5" ${dia === 5 ? 'selected' : ''}>5 - Viernes</option>
                        </select>
                    </div>
                    <div class="horario-input">
                        <label>Hora:</label>
                        <select name="horarios[${contadorHorarios}][hora]" required>
                            <option value="">Seleccionar hora</option>
                            ${generarOpcionesHoras()}
                        </select>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="eliminarHorario(this)">Eliminar</button>
                `;
                
                listaHorarios.appendChild(horarioDiv);
            }
        });
    </script>
</body>
</html>