<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// OBTENER VARIABLES GLOBALES
$sql_variables = "SELECT nombre, valor FROM variables_globales WHERE nombre IN ('calificacion_aprobatoria', 'semestre_asignacion_especialidad', 'semestres_maximos')";
$result_variables = $conexion->query($sql_variables);
$variables_globales = [];
while ($row = $result_variables->fetch_assoc()) {
    $variables_globales[$row['nombre']] = $row['valor'];
}

// Verificar configuración de acciones
$sql_acciones = "SELECT id_accion, activo FROM acciones WHERE id_accion IN (3, 4)";
$result_acciones = $conexion->query($sql_acciones);
$acciones_config = [];
while ($row = $result_acciones->fetch_assoc()) {
    $acciones_config[$row['id_accion']] = $row['activo'];
}

$reprobacion_por_unidad = ($acciones_config[3] ?? 0) == 1;
$verano_activo = ($acciones_config[4] ?? 0) == 1;

// CORRECCIÓN: Usar la misma calificación aprobatoria para ambos modos
$calificacion_aprobatoria = $variables_globales['calificacion_aprobatoria'] ?? 70;

// Obtener semestres máximos
$semestres_maximos = $variables_globales['semestres_maximos'] ?? 30;

// Verificar si es una confirmación final
if ($_POST['confirmacion_final'] ?? false) {
    avanzarSemestre($reprobacion_por_unidad, $verano_activo, $calificacion_aprobatoria, $semestres_maximos);
} else {
    mostrarConfirmacion($reprobacion_por_unidad, $verano_activo, $calificacion_aprobatoria, $semestres_maximos);
}

function mostrarConfirmacion($reprobacion_por_unidad, $verano_activo, $calificacion_aprobatoria, $semestres_maximos) {
    global $conexion, $variables_globales;
    
    // Obtener estadísticas
    $sql_estadisticas = "
        SELECT 
            (SELECT COUNT(*) FROM clase WHERE activo = 1) as clases_activas,
            (SELECT COUNT(*) FROM alumno WHERE estado = '1') as alumnos_activos
    ";
    $estadisticas = $conexion->query($sql_estadisticas)->fetch_assoc();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Avanzar Semestre</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h4 class="mb-0">⚠️ Confirmar Avance de Semestre</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Resumen:</strong><br>
                                - Clases activas: <?php echo $estadisticas['clases_activas']; ?><br>
                                - Alumnos activos: <?php echo $estadisticas['alumnos_activos']; ?><br>
                                - <strong>Configuración actual:</strong><br>
                                <?php if ($reprobacion_por_unidad): ?>
                                    <span class="text-danger">❌ REPROBACIÓN POR UNIDAD ACTIVA</span><br>
                                    <small>Los alumnos reprobarán si tienen al menos 1 unidad &lt;<?php echo $calificacion_aprobatoria; ?></small><br>
                                <?php else: ?>
                                    <span class="text-success">✅ PROMEDIO SIMPLE</span><br>
                                    <small>Los alumnos aprueban con promedio ≥<?php echo $calificacion_aprobatoria; ?></small><br>
                                <?php endif; ?>
                                <?php if ($verano_activo): ?>
                                    <span class="text-warning">☀️ PERIODO DE VERANO ACTIVO</span><br>
                                    <small>Los alumnos NO avanzarán de semestre</small>
                                <?php else: ?>
                                    <span class="text-success">✅ AVANCE DE SEMESTRE ACTIVO</span><br>
                                    <small>Los alumnos avanzarán al siguiente semestre</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="alert alert-danger">
                                <strong>❌ ACCIÓN IRREVERSIBLE</strong><br>
                                Esta acción cerrará TODAS las clases y procesará las calificaciones.<br>
                                <?php if ($verano_activo): ?>
                                    <strong>Los alumnos NO avanzarán de semestre (periodo de verano).</strong><br>
                                <?php else: ?>
                                    <strong>Los alumnos aprobados avanzarán al siguiente semestre.</strong><br>
                                <?php endif; ?>
                                <strong>Los alumnos que reprueben en "especial" serán dados de baja.</strong>
                            </div>

                            <div class="alert alert-warning">
                                <strong>📝 Información importante:</strong><br>
                                - Calificación aprobatoria: <strong><?php echo $calificacion_aprobatoria; ?>/100</strong><br>
                                - Semestres máximos permitidos: <strong><?php echo $semestres_maximos; ?></strong><br>
                                - Semestre para asignación de especialidad: <strong><?php echo $variables_globales['semestre_asignacion_especialidad'] ?? 5; ?></strong><br>
                                <?php if ($verano_activo): ?>
                                    - <strong>Periodo de verano activo - no se avanzará de semestre</strong><br>
                                <?php endif; ?>
                                - Los alumnos en semestre <?php echo $semestres_maximos; ?> serán evaluados para egreso<br>
                                - El sistema verificará materias generales y de especialidad para el egreso
                                - Los alumnos en semestre <?php echo $variables_globales['semestre_asignacion_especialidad'] ?? 5; ?> recibirán asignación automática de especialidad
                                - <strong>Se actualizarán los promedios de todos los alumnos</strong>
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="confirmacion_final" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Contraseña de confirmación:</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="confirmarPeligro" required>
                                    <label class="form-check-label" for="confirmarPeligro">
                                        <strong>He leído y comprendo el peligro de esta acción</strong>
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-danger">
                                        <?php echo $verano_activo ? 'Procesar Calificaciones (Verano)' : 'Avanzar Semestre'; ?>
                                    </button>
                                    <a href="../coordinador.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function verificarEgresoAlumno($id_alumno) {
    global $conexion;
    
    // Obtener información del alumno
    $sql_alumno = "SELECT u.id_carrera, a.id_especialidad 
                   FROM alumno a 
                   INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                   WHERE a.id_alumno = ?";
    $stmt = $conexion->prepare($sql_alumno);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $alumno_data = $stmt->get_result()->fetch_assoc();
    
    if (!$alumno_data) {
        return false;
    }
    
    $id_carrera = $alumno_data['id_carrera'];
    $id_especialidad = $alumno_data['id_especialidad'];
    
    // Obtener todas las materias obligatorias (generales + de su especialidad)
    $sql_materias_obligatorias = "
        SELECT m.id_materia, m.nombre 
        FROM materia m 
        WHERE m.id_carrera = ? 
        AND (m.id_especialidad = 1 OR m.id_especialidad = ?)
        AND m.id_materia NOT IN (
            SELECT m2.id_prerrequisito 
            FROM materia m2 
            WHERE m2.id_prerrequisito IS NOT NULL
        )
    ";
    $stmt = $conexion->prepare($sql_materias_obligatorias);
    $stmt->bind_param("ii", $id_carrera, $id_especialidad);
    $stmt->execute();
    $materias_obligatorias = $stmt->get_result();
    
    // Verificar si el alumno ha aprobado todas las materias obligatorias
    while ($materia = $materias_obligatorias->fetch_assoc()) {
        $id_materia = $materia['id_materia'];
        
        // Verificar si el alumno ha aprobado esta materia
        $sql_aprobada = "SELECT 1 FROM materia_cursada 
                        WHERE id_alumno = ? AND id_materia = ? AND aprobado = 1";
        $stmt_aprob = $conexion->prepare($sql_aprobada);
        $stmt_aprob->bind_param("ii", $id_alumno, $id_materia);
        $stmt_aprob->execute();
        $aprobada = $stmt_aprob->get_result()->num_rows > 0;
        
        if (!$aprobada) {
            return false; // No ha aprobado todas las materias obligatorias
        }
    }
    
    return true; // Ha aprobado todas las materias obligatorias
}

function verificarMaximoSemestres($id_alumno, $semestres_maximos) {
    global $conexion;
    
    $sql_semestre = "SELECT semestre FROM alumno WHERE id_alumno = ?";
    $stmt = $conexion->prepare($sql_semestre);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumno = $result->fetch_assoc();
    
    if ($alumno && $alumno['semestre'] >= $semestres_maximos) {
        return true; // Ha alcanzado el máximo de semestres
    }
    
    return false;
}

function asignarUltimaEspecialidad($id_alumno) {
    global $conexion;
    
    // Obtener la carrera del alumno
    $sql_carrera = "
        SELECT u.id_carrera 
        FROM alumno a 
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE a.id_alumno = ?
    ";
    $stmt = $conexion->prepare($sql_carrera);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false; // Alumno no encontrado
    }
    
    $alumno_data = $result->fetch_assoc();
    $id_carrera = $alumno_data['id_carrera'];
    
    // Obtener la última especialidad creada para esta carrera
    $sql_especialidad = "
        SELECT id_especialidad, nombre 
        FROM especialidad 
        WHERE id_carrera = ? 
        ORDER BY id_especialidad DESC 
        LIMIT 1
    ";
    $stmt_esp = $conexion->prepare($sql_especialidad);
    $stmt_esp->bind_param("i", $id_carrera);
    $stmt_esp->execute();
    $result_esp = $stmt_esp->get_result();
    
    if ($result_esp->num_rows === 0) {
        return false; // No hay especialidades para esta carrera
    }
    
    $especialidad = $result_esp->fetch_assoc();
    $id_especialidad = $especialidad['id_especialidad'];
    $nombre_especialidad = $especialidad['nombre'];
    
    // Actualizar la especialidad del alumno
    $sql_update = "UPDATE alumno SET id_especialidad = ?, especialidad = ? WHERE id_alumno = ?";
    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->bind_param("isi", $id_especialidad, $nombre_especialidad, $id_alumno);
    
    if ($stmt_update->execute()) {
        return [
            'id_especialidad' => $id_especialidad,
            'nombre_especialidad' => $nombre_especialidad,
            'id_carrera' => $id_carrera
        ];
    }
    
    return false;
}

function actualizarPromedioAlumno($id_alumno) {
    global $conexion;
    
    // Obtener la calificación más alta de cada materia que el alumno ha cursado
    $sql_calificaciones = "
        SELECT 
            mc.id_materia,
            MAX(mc.cal_final) as calificacion_maxima,
            m.nombre as materia_nombre
        FROM materia_cursada mc
        INNER JOIN materia m ON mc.id_materia = m.id_materia
        WHERE mc.id_alumno = ?
        AND mc.cal_final IS NOT NULL
        GROUP BY mc.id_materia
        ORDER BY m.nombre
    ";
    
    $stmt = $conexion->prepare($sql_calificaciones);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_calificaciones = 0;
    $total_materias = 0;
    $calificaciones = [];
    
    while ($row = $result->fetch_assoc()) {
        $calificaciones[] = [
            'materia' => $row['materia_nombre'],
            'calificacion' => $row['calificacion_maxima']
        ];
        $total_calificaciones += $row['calificacion_maxima'];
        $total_materias++;
    }
    
    // Calcular el promedio
    $promedio = 0;
    if ($total_materias > 0) {
        $promedio = round($total_calificaciones / $total_materias, 2);
    }
    
    // Actualizar el promedio en la tabla alumno
    $sql_update = "UPDATE alumno SET promedio = ? WHERE id_alumno = ?";
    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->bind_param("di", $promedio, $id_alumno);
    $stmt_update->execute();
    
    return [
        'promedio' => $promedio,
        'total_materias' => $total_materias,
        'calificaciones' => $calificaciones
    ];
}

function avanzarSemestre($reprobacion_por_unidad, $verano_activo, $calificacion_aprobatoria, $semestres_maximos) {
    global $conexion, $variables_globales;
    
    $id_usuario = $_SESSION['id_usuario'];
    $password_ingresada = $_POST['password'];
    
    // Verificar contraseña
    $sql_usuario = "SELECT contraseña FROM usuario WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql_usuario);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    
    if (!$usuario || !password_verify($password_ingresada, $usuario['contraseña'])) {
        header("Location: ../coordinador.php?error=Contraseña incorrecta");
        exit;
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        $resumen = [
            'clases_cerradas' => 0,
            'alumnos_procesados' => 0,
            'alumnos_sin_avance' => 0,
            'materias_registradas' => 0,
            'alumnos_egresados' => [],
            'alumnos_max_semestres' => [],
            'alumnos_reprobados_especial' => [],
            'alumnos_reprobados' => [],
            'alumnos_con_especialidad' => [],
            'alumnos_promedio_actualizado' => [],
            'configuracion_reprobacion' => $reprobacion_por_unidad ? 'Reprobación por unidad' : 'Promedio simple',
            'configuracion_verano' => $verano_activo ? 'Verano activo' : 'Verano inactivo',
            'calificacion_aprobatoria' => $calificacion_aprobatoria,
            'semestres_maximos' => $semestres_maximos,
            'detalles_alumnos' => []
        ];

        // 1. OBTENER TODAS LAS CLASES ACTIVAS
        $clases_activas = $conexion->query("SELECT id_clase, id_materia, periodo FROM clase WHERE activo = 1");
        
        while ($clase = $clases_activas->fetch_assoc()) {
            $id_clase = $clase['id_clase'];
            $id_materia = $clase['id_materia'];
            $periodo = $clase['periodo'];
            
            // Obtener nombre de la materia
            $materia_nombre = $conexion->query("SELECT nombre FROM materia WHERE id_materia = $id_materia")->fetch_assoc()['nombre'];
            
            // 2. PROCESAR ALUMNOS DE ESTA CLASE
            $alumnos_clase = $conexion->query("
                SELECT a.id_asignacion, a.id_alumno, al.semestre, u.nombre, u.apellidos, u.clave, car.nombre as carrera
                FROM asignacion a 
                INNER JOIN alumno al ON a.id_alumno = al.id_alumno
                INNER JOIN usuario u ON al.id_usuario = u.id_usuario
                INNER JOIN carrera car ON u.id_carrera = car.id_carrera
                WHERE a.id_clase = $id_clase
            ");
            
            while ($alumno = $alumnos_clase->fetch_assoc()) {
                $id_asignacion = $alumno['id_asignacion'];
                $id_alumno = $alumno['id_alumno'];
                $semestre_actual = $alumno['semestre'];

                // OBTENER CALIFICACIONES POR UNIDAD
                $calificaciones_result = $conexion->query("
                    SELECT unidad, calificacion 
                    FROM calificacion_clase 
                    WHERE id_asignacion = $id_asignacion 
                    ORDER BY unidad
                ");
                
                $calificaciones = [];
                $tiene_reprobada = false;
                $suma_calificaciones = 0;
                $contador_unidades = 0;
                $unidades_reprobadas = [];
                $todas_calificaciones_cero = true;
                
                while ($calif = $calificaciones_result->fetch_assoc()) {
                    $calificaciones[$calif['unidad']] = $calif['calificacion'];
                    $suma_calificaciones += $calif['calificacion'];
                    $contador_unidades++;
                    
                    // Verificar si tiene calificación mayor a 0
                    if ($calif['calificacion'] > 0) {
                        $todas_calificaciones_cero = false;
                    }
                    
                    // Verificar si tiene alguna unidad reprobada (< calificacion_aprobatoria)
                    if ($reprobacion_por_unidad && $calif['calificacion'] < $calificacion_aprobatoria) {
                        $tiene_reprobada = true;
                        $unidades_reprobadas[] = $calif['unidad'];
                    }
                }
                
                // **CORRECCIÓN CRÍTICA: Si no hay calificaciones registradas, REPROBAR**
                if ($contador_unidades == 0) {
                    $prom = 0;
                    $aprobado = 0;
                    $sin_calificaciones = true;
                } else {
                    $prom = round($suma_calificaciones / $contador_unidades, 2);
                    $sin_calificaciones = false;
                    
                    // **CORRECCIÓN: Si todas las calificaciones son 0, REPROBAR**
                    if ($todas_calificaciones_cero) {
                        $aprobado = 0;
                    } else if ($reprobacion_por_unidad) {
                        // MODO 1: REPROBACIÓN POR UNIDAD 
                        // Aprueba solo si TODAS las unidades son >= calificacion_aprobatoria
                        $aprobado = !$tiene_reprobada ? 1 : 0;
                    } else {
                        // MODO 2: PROMEDIO SIMPLE 
                        // Aprueba con promedio >= calificacion_aprobatoria
                        $aprobado = ($prom >= $calificacion_aprobatoria) ? 1 : 0;
                    }
                }

                // Determinar oportunidad
                $oportunidades_result = $conexion->query("
                    SELECT COUNT(*) AS veces, SUM(aprobado=0) AS reprobadas 
                    FROM materia_cursada 
                    WHERE id_materia=$id_materia AND id_alumno=$id_alumno
                ");
                $data_oport = $oportunidades_result->fetch_assoc();
                $veces = intval($data_oport['veces']);
                $reprobadas = intval($data_oport['reprobadas']);

                if ($veces == 0) {
                    $oportunidad = 'ordinario';
                } elseif ($reprobadas == 1) {
                    $oportunidad = 'recursamiento';
                } elseif ($reprobadas >= 2) {
                    $oportunidad = 'especial';
                } else {
                    $oportunidad = 'ordinario';
                }

                // Insertar en materia_cursada
                $conexion->query("
                    INSERT INTO materia_cursada (id_materia, id_clase, id_alumno, cal_final, oportunidad, periodo, aprobado)
                    VALUES ($id_materia, $id_clase, $id_alumno, $prom, '$oportunidad', '$periodo', $aprobado)
                ");
                
                $resumen['materias_registradas']++;
                
                // Guardar detalles para el PDF (incluyendo modo de evaluación)
                $resumen['detalles_alumnos'][] = [
                    'nombre' => $alumno['nombre'] . ' ' . $alumno['apellidos'],
                    'clave' => $alumno['clave'],
                    'carrera' => $alumno['carrera'],
                    'semestre' => $semestre_actual,
                    'materia' => $materia_nombre,
                    'promedio' => $prom,
                    'aprobado' => $aprobado,
                    'oportunidad' => $oportunidad,
                    'unidades_reprobadas' => $unidades_reprobadas,
                    'calificacion_aprobatoria' => $calificacion_aprobatoria,
                    'modo_evaluacion' => $reprobacion_por_unidad ? 'reprobacion_por_unidad' : 'promedio_simple',
                    'sin_calificaciones' => $sin_calificaciones ?? false,
                    'todas_cero' => $todas_calificaciones_cero ?? false
                ];
                
                // Registrar alumnos reprobados para el resumen
                if ($aprobado == 0) {
                    $resumen['alumnos_reprobados'][] = 
                        $alumno['nombre'] . ' ' . $alumno['apellidos'] . ' - ' . 
                        $materia_nombre . ' (Promedio: ' . $prom . ')';
                }
                
                // Verificar si reprobó en "especial"
                if ($oportunidad == 'especial' && $aprobado == 0) {
                    // Obtener información del alumno
                    $info_alumno = $conexion->query("
                        SELECT u.nombre, u.apellidos, car.nombre as carrera_nombre, m.nombre as materia_nombre
                        FROM alumno a 
                        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                        INNER JOIN carrera car ON u.id_carrera = car.id_carrera 
                        INNER JOIN materia m ON m.id_materia = $id_materia
                        WHERE a.id_alumno = $id_alumno
                    ")->fetch_assoc();
                    
                    // Inactivar al alumno por reprobar en especial
                    $conexion->query("UPDATE alumno SET estado = '3' WHERE id_alumno = $id_alumno");
                    
                    $detalle_calificaciones = "";
                    if ($reprobacion_por_unidad && $tiene_reprobada) {
                        $detalle_calificaciones = " (Reprobó por unidad)";
                    } else if (!$reprobacion_por_unidad) {
                        $detalle_calificaciones = " (Promedio: $prom)";
                    } else if ($sin_calificaciones) {
                        $detalle_calificaciones = " (Sin calificaciones registradas)";
                    } else if ($todas_calificaciones_cero) {
                        $detalle_calificaciones = " (Todas las calificaciones son 0)";
                    }
                    
                    $resumen['alumnos_reprobados_especial'][] = 
                        $info_alumno['nombre'] . ' ' . $info_alumno['apellidos'] . ' - ' . 
                        $info_alumno['carrera_nombre'] . ' (Materia: ' . $info_alumno['materia_nombre'] . $detalle_calificaciones . ')';
                }
            }
            
            // Cerrar la clase
            $conexion->query("UPDATE clase SET activo = 0 WHERE id_clase = $id_clase");
            $resumen['clases_cerradas']++;
        }

        // 3. AVANZAR SEMESTRE, ASIGNAR ESPECIALIDADES Y ACTUALIZAR PROMEDIOS
        if (!$verano_activo) {
            $alumnos = $conexion->query("SELECT id_alumno, semestre FROM alumno WHERE estado = '1'");
            
            while ($alumno = $alumnos->fetch_assoc()) {
                $id_alumno = $alumno['id_alumno'];
                $semestre_actual = $alumno['semestre'];
                
                // ASIGNAR ESPECIALIDAD si está en el semestre correspondiente
                $semestre_asignacion = $variables_globales['semestre_asignacion_especialidad'] ?? 5;
                if ($semestre_actual == $semestre_asignacion) {
                    $resultado_especialidad = asignarUltimaEspecialidad($id_alumno);
                    if ($resultado_especialidad) {
                        // Obtener información del alumno para el resumen
                        $info_alumno = $conexion->query("
                            SELECT u.nombre, u.apellidos, car.nombre as carrera_nombre 
                            FROM alumno a 
                            INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                            INNER JOIN carrera car ON u.id_carrera = car.id_carrera 
                            WHERE a.id_alumno = $id_alumno
                        ")->fetch_assoc();
                        
                        $resumen['alumnos_con_especialidad'][] = 
                            $info_alumno['nombre'] . ' ' . $info_alumno['apellidos'] . ' - ' . 
                            $info_alumno['carrera_nombre'] . ' (Especialidad: ' . $resultado_especialidad['nombre_especialidad'] . ')';
                    }
                }
                
                // ACTUALIZAR PROMEDIO DEL ALUMNO (para todos los alumnos activos)
                $resultado_promedio = actualizarPromedioAlumno($id_alumno);
                if ($resultado_promedio['total_materias'] > 0) {
                    // Obtener información del alumno para el resumen
                    $info_alumno = $conexion->query("
                        SELECT u.nombre, u.apellidos 
                        FROM alumno a 
                        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                        WHERE a.id_alumno = $id_alumno
                    ")->fetch_assoc();
                    
                    $resumen['alumnos_promedio_actualizado'][] = 
                        $info_alumno['nombre'] . ' ' . $info_alumno['apellidos'] . 
                        ' (Promedio: ' . $resultado_promedio['promedio'] . ' - ' . 
                        $resultado_promedio['total_materias'] . ' materias)';
                }
                
                // Verificar si el alumno ha alcanzado el máximo de semestres
                if (verificarMaximoSemestres($id_alumno, $semestres_maximos)) {
                    // Obtener información del alumno
                    $info_alumno = $conexion->query("
                        SELECT u.nombre, u.apellidos, car.nombre as carrera_nombre 
                        FROM alumno a 
                        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                        INNER JOIN carrera car ON u.id_carrera = car.id_carrera 
                        WHERE a.id_alumno = $id_alumno
                    ")->fetch_assoc();
                    
                    // Verificar si el alumno puede egresar (todas las materias obligatorias aprobadas)
                    $puede_egresar = verificarEgresoAlumno($id_alumno);
                    
                    if ($puede_egresar) {
                        // Egresar al alumno
                        $conexion->query("UPDATE alumno SET estado = '4' WHERE id_alumno = $id_alumno");
                        $resumen['alumnos_egresados'][] = $info_alumno['nombre'] . ' ' . $info_alumno['apellidos'] . ' - ' . $info_alumno['carrera_nombre'];
                    } else {
                        // Desactivar por máximo de semestres sin completar materias
                        $conexion->query("UPDATE alumno SET estado = '3' WHERE id_alumno = $id_alumno");
                        
                        // Obtener información adicional sobre las materias faltantes
                        $sql_materias_faltantes = "
                            SELECT m.nombre 
                            FROM materia m 
                            WHERE m.id_carrera = (
                                SELECT u.id_carrera FROM alumno a 
                                INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
                                WHERE a.id_alumno = $id_alumno
                            )
                            AND (m.id_especialidad = 1 OR m.id_especialidad = (
                                SELECT id_especialidad FROM alumno WHERE id_alumno = $id_alumno
                            ))
                            AND m.id_materia NOT IN (
                                SELECT mc.id_materia 
                                FROM materia_cursada mc 
                                WHERE mc.id_alumno = $id_alumno AND mc.aprobado = 1
                            )
                        ";
                        $materias_faltantes_result = $conexion->query($sql_materias_faltantes);
                        $materias_faltantes = [];
                        while ($materia = $materias_faltantes_result->fetch_assoc()) {
                            $materias_faltantes[] = $materia['nombre'];
                        }
                        
                        $detalle_faltantes = !empty($materias_faltantes) ? ' (Materias faltantes: ' . implode(', ', array_slice($materias_faltantes, 0, 3)) . '...)' : '';
                        $resumen['alumnos_max_semestres'][] = $info_alumno['nombre'] . ' ' . $info_alumno['apellidos'] . ' - ' . $info_alumno['carrera_nombre'] . $detalle_faltantes;
                    }
                } else {
                    // Avanzar semestre normal
                    $nuevo_semestre = $semestre_actual + 1;
                    $conexion->query("UPDATE alumno SET semestre = $nuevo_semestre WHERE id_alumno = $id_alumno");
                    $resumen['alumnos_procesados']++;
                }
            }
        } else {
            // Contar alumnos que no avanzaron por ser verano
            $resumen['alumnos_sin_avance'] = $conexion->query("SELECT COUNT(*) FROM alumno WHERE estado = '1'")->fetch_row()[0];
        }

        // 4. CONFIRMAR TRANSACCIÓN
        $conexion->commit();
        
        // 5. GUARDAR RESUMEN EN SESIÓN PARA EL PDF
        $_SESSION['resumen_proceso'] = $resumen;
        
        // 6. MOSTRAR RESUMEN
        mostrarResumen($resumen);
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: ../coordinador.php?error=Error: " . urlencode($e->getMessage()));
    }
}

function mostrarResumen($resumen) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Resumen Avance de Semestre</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">✅ Proceso Completado</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success">
                                <h5>Resumen del proceso:</h5>
                                <ul>
                                    <li>Clases cerradas: <?php echo $resumen['clases_cerradas']; ?></li>
                                    <li>Materias registradas en kardex: <?php echo $resumen['materias_registradas']; ?></li>
                                    <?php if ($resumen['configuracion_verano'] == 'Verano inactivo'): ?>
                                        <li>Alumnos que avanzaron semestre: <?php echo $resumen['alumnos_procesados']; ?></li>
                                    <?php else: ?>
                                        <li>Alumnos procesados (sin avance - verano): <?php echo $resumen['alumnos_sin_avance']; ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($resumen['alumnos_con_especialidad'])): ?>
                                        <li>Alumnos con especialidad asignada: <?php echo count($resumen['alumnos_con_especialidad']); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($resumen['alumnos_promedio_actualizado'])): ?>
                                        <li>Alumnos con promedio actualizado: <?php echo count($resumen['alumnos_promedio_actualizado']); ?></li>
                                    <?php endif; ?>
                                    <li><strong>Configuración reprobación:</strong> <?php echo $resumen['configuracion_reprobacion']; ?></li>
                                    <li><strong>Configuración periodo:</strong> <?php echo $resumen['configuracion_verano']; ?></li>
                                    <li><strong>Calificación aprobatoria:</strong> <?php echo $resumen['calificacion_aprobatoria']; ?>/100</li>
                                    <li><strong>Semestres máximos:</strong> <?php echo $resumen['semestres_maximos']; ?></li>
                                </ul>
                            </div>
                            
                            <?php if (!empty($resumen['alumnos_reprobados'])): ?>
                            <div class="alert alert-warning">
                                <h6>📊 Alumnos Reprobados:</h6>
                                <ul>
                                    <?php foreach(array_slice($resumen['alumnos_reprobados'], 0, 10) as $reprobado): ?>
                                        <li><?php echo $reprobado; ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($resumen['alumnos_reprobados']) > 10): ?>
                                        <li>... y <?php echo count($resumen['alumnos_reprobados']) - 10; ?> más</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resumen['alumnos_con_especialidad'])): ?>
                            <div class="alert alert-info">
                                <h6>🎯 Alumnos con Especialidad Asignada:</h6>
                                <ul>
                                    <?php foreach($resumen['alumnos_con_especialidad'] as $alumno): ?>
                                        <li><?php echo $alumno; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resumen['alumnos_promedio_actualizado'])): ?>
                            <div class="alert alert-primary">
                                <h6>📈 Alumnos con Promedio Actualizado:</h6>
                                <ul>
                                    <?php foreach(array_slice($resumen['alumnos_promedio_actualizado'], 0, 10) as $alumno): ?>
                                        <li><?php echo $alumno; ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($resumen['alumnos_promedio_actualizado']) > 10): ?>
                                        <li>... y <?php echo count($resumen['alumnos_promedio_actualizado']) - 10; ?> más</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resumen['alumnos_egresados'])): ?>
                            <div class="alert alert-info">
                                <h6>🎓 Alumnos Egresados:</h6>
                                <ul>
                                    <?php foreach($resumen['alumnos_egresados'] as $egresado): ?>
                                        <li><?php echo $egresado; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resumen['alumnos_max_semestres'])): ?>
                            <div class="alert alert-warning">
                                <h6>⚠️ Alumnos con máximo de semestres (<?php echo $resumen['semestres_maximos']; ?>) sin completar materias:</h6>
                                <ul>
                                    <?php foreach($resumen['alumnos_max_semestres'] as $alumno): ?>
                                        <li><?php echo $alumno; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resumen['alumnos_reprobados_especial'])): ?>
                            <div class="alert alert-danger">
                                <h6>❌ Alumnos dados de baja por reprobar en "especial":</h6>
                                <ul>
                                    <?php foreach($resumen['alumnos_reprobados_especial'] as $alumno): ?>
                                        <li><?php echo $alumno; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2">
                                <a href="generar_informe_pdf.php" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Descargar Informe PDF
                                </a>
                                <a href="../coordinador.php" class="btn btn-secondary">Volver al Panel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>