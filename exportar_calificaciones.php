<?php
session_start();
include "conexion.php";
include "header.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    header("Location: login.php");
    exit;
}

$id_clase = $_POST['id_clase'] ?? $_GET['id_clase'] ?? 0;
$unidad = $_POST['unidad'] ?? $_GET['unidad'] ?? 0;

// Verificar que el profesor tiene acceso a esta clase
$id_usuario = $_SESSION['id_usuario'];
$stmt = $conexion->prepare("
    SELECT c.id_clase 
    FROM clase c 
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor 
    WHERE p.id_usuario = ? AND c.id_clase = ?
");
$stmt->bind_param("ii", $id_usuario, $id_clase);
$stmt->execute();
$tiene_acceso = $stmt->get_result()->num_rows > 0;

if (!$tiene_acceso || $id_clase == 0) {
    header("Location: clases.php");
    exit;
}

// Obtener permisos del sistema
$sql_permisos = "SELECT id_accion, activo FROM acciones WHERE id_accion IN (1, 2)";
$res_permisos = $conexion->query($sql_permisos);
$permisos = [
    'modificar' => false,
    'subir' => false
];

if ($res_permisos && $res_permisos->num_rows > 0) {
    while ($permiso = $res_permisos->fetch_assoc()) {
        if ($permiso['id_accion'] == 1) {
            $permisos['modificar'] = (bool)$permiso['activo'];
        } elseif ($permiso['id_accion'] == 2) {
            $permisos['subir'] = (bool)$permiso['activo'];
        }
    }
}

// Inicializar variables de estado por unidad
$calificaciones_existentes_unidad = false;
$puede_subir = false;
$puede_modificar = false;
$mostrar_boton_subir = false;

// Verificar si ya existen calificaciones para esta clase y unidad específica
if ($unidad > 0) {
    $query_existentes_unidad = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM calificacion_clase cc
        INNER JOIN asignacion a ON cc.id_asignacion = a.id_asignacion
        WHERE a.id_clase = ? AND cc.unidad = ?
    ");
    $query_existentes_unidad->bind_param("ii", $id_clase, $unidad);
    $query_existentes_unidad->execute();
    $result_existentes_unidad = $query_existentes_unidad->get_result()->fetch_assoc();
    $calificaciones_existentes_unidad = $result_existentes_unidad['total'] > 0;
    
    // Determinar permisos basados en la unidad específica
    $puede_subir = $permisos['subir'] && !$calificaciones_existentes_unidad;
    $puede_modificar = $permisos['modificar'] && $calificaciones_existentes_unidad;
    $mostrar_boton_subir = $puede_subir || $puede_modificar;
}

// Obtener información de la clase
$query_clase = $conexion->prepare("
    SELECT 
        c.id_clase, c.grupo, c.periodo,
        m.nombre as materia_nombre, m.creditos, m.unidades,
        CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
        car.nombre as carrera_nombre
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN carrera car ON m.id_carrera = car.id_carrera
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario prof ON p.id_usuario = prof.id_usuario
    WHERE c.id_clase = ?
");
$query_clase->bind_param("i", $id_clase);
$query_clase->execute();
$clase_info = $query_clase->get_result()->fetch_assoc();

// Obtener tareas de la clase
$query_tareas = $conexion->prepare("
    SELECT 
        t.*,
        COUNT(et.id_entrega) as total_entregas,
        COUNT(CASE WHEN et.calificacion IS NOT NULL THEN 1 END) as total_calificadas
    FROM tareas t
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
    WHERE t.id_clase = ? AND t.estado != 'cancelada'
    GROUP BY t.id_tarea
    ORDER BY t.unidad ASC, t.fecha_limite DESC
");
$query_tareas->bind_param("i", $id_clase);
$query_tareas->execute();
$tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);

// Organizar tareas por unidad
$tareas_por_unidad = [];
foreach ($tareas as $tarea) {
    $unidad_num = $tarea['unidad'];
    if (!isset($tareas_por_unidad[$unidad_num])) {
        $tareas_por_unidad[$unidad_num] = [];
    }
    $tareas_por_unidad[$unidad_num][] = $tarea;
}

// Obtener alumnos de la clase
$query_alumnos = $conexion->prepare("
    SELECT 
        a.id_alumno,
        asig.id_asignacion,
        u.nombre,
        u.apellidos,
        u.clave as numero_control
    FROM asignacion asig
    INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE asig.id_clase = ?
    ORDER BY u.apellidos, u.nombre
");
$query_alumnos->bind_param("i", $id_clase);
$query_alumnos->execute();
$alumnos_clase = $query_alumnos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener calificaciones de los alumnos
$calificaciones_alumnos = [];
foreach ($alumnos_clase as $alumno) {
    $query_calificaciones = $conexion->prepare("
        SELECT 
            t.id_tarea,
            t.titulo,
            t.unidad,
            t.puntos_maximos,
            et.calificacion,
            et.fecha_entrega
        FROM tareas t
        LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_alumno = ?
        WHERE t.id_clase = ? AND t.estado != 'cancelada'
        ORDER BY t.unidad, t.id_tarea
    ");
    $query_calificaciones->bind_param("ii", $alumno['id_alumno'], $id_clase);
    $query_calificaciones->execute();
    $calificaciones = $query_calificaciones->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $calificaciones_alumnos[$alumno['id_alumno']] = $calificaciones;
}

// Función para obtener porcentaje de asistencia en intervalo de fechas
function obtenerPorcentajeAsistencia($conexion, $id_alumno, $id_clase, $fecha_inicio, $fecha_fin) {
    $query = $conexion->prepare("
        SELECT 
            COUNT(*) as total_clases,
            COUNT(CASE WHEN estado_asistencia = 'presente' OR estado_asistencia = 'justificado' THEN 1 END) as asistencias
        FROM asistencia 
        WHERE id_alumno = ? AND id_clase = ? AND fecha BETWEEN ? AND ?
    ");
    $query->bind_param("iiss", $id_alumno, $id_clase, $fecha_inicio, $fecha_fin);
    $query->execute();
    $result = $query->get_result()->fetch_assoc();
    
    if ($result['total_clases'] > 0) {
        $porcentaje = round(($result['asistencias'] / $result['total_clases']) * 100, 2);
        return [
            'porcentaje' => $porcentaje,
            'total_clases' => $result['total_clases'],
            'asistencias' => $result['asistencias'],
            'inasistencias' => $result['total_clases'] - $result['asistencias'],
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ];
    }
    
    return [
        'porcentaje' => 0,
        'total_clases' => 0,
        'asistencias' => 0,
        'inasistencias' => 0,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin
    ];
}

// Obtener fechas disponibles de asistencia
$query_fechas_asistencia = $conexion->prepare("
    SELECT MIN(fecha) as primera_fecha, MAX(fecha) as ultima_fecha 
    FROM asistencia 
    WHERE id_clase = ?
");
$query_fechas_asistencia->bind_param("i", $id_clase);
$query_fechas_asistencia->execute();
$rango_fechas = $query_fechas_asistencia->get_result()->fetch_assoc();

// Fechas por defecto
$fecha_inicio_default = $rango_fechas['primera_fecha'] ?? date('Y-m-d');
$fecha_fin_default = $rango_fechas['ultima_fecha'] ?? date('Y-m-d');

$fecha_inicio = $_POST['fecha_inicio'] ?? $fecha_inicio_default;
$fecha_fin = $_POST['fecha_fin'] ?? $fecha_fin_default;

// Obtener asistencias con el intervalo seleccionado
$asistencias_alumnos = [];
foreach ($alumnos_clase as $alumno) {
    $asistencias_alumnos[$alumno['id_alumno']] = obtenerPorcentajeAsistencia(
        $conexion, 
        $alumno['id_alumno'], 
        $id_clase, 
        $fecha_inicio, 
        $fecha_fin
    );
}

// Calcular estadísticas generales de asistencia
$total_alumnos = count($alumnos_clase);
$asistencias_totales = 0;
$clases_totales = 0;

foreach ($asistencias_alumnos as $asistencia) {
    $asistencias_totales += $asistencia['asistencias'];
    $clases_totales += $asistencia['total_clases'];
}

$porcentaje_promedio_asistencia = $clases_totales > 0 ? round(($asistencias_totales / $clases_totales) * 100, 2) : 0;

// Procesar actualización de fechas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fecha_inicio']) && !isset($_POST['porcentajes'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $unidad = $_POST['unidad'] ?? $unidad;
    
    // Recalcular asistencias con el nuevo intervalo
    $asistencias_alumnos = [];
    foreach ($alumnos_clase as $alumno) {
        $asistencias_alumnos[$alumno['id_alumno']] = obtenerPorcentajeAsistencia(
            $conexion, 
            $alumno['id_alumno'], 
            $id_clase, 
            $fecha_inicio, 
            $fecha_fin
        );
    }
    
    // Recalcular estadísticas
    $asistencias_totales = 0;
    $clases_totales = 0;
    foreach ($asistencias_alumnos as $asistencia) {
        $asistencias_totales += $asistencia['asistencias'];
        $clases_totales += $asistencia['total_clases'];
    }
    $porcentaje_promedio_asistencia = $clases_totales > 0 ? round(($asistencias_totales / $clases_totales) * 100, 2) : 0;
}

// Función para calcular calificación final REAL
function calcularCalificacionFinalReal($id_alumno, $unidad, $porcentajes, $porcentaje_asistencia, $calificaciones_alumnos, $asistencias_alumnos) {
    $calificaciones = $calificaciones_alumnos[$id_alumno] ?? [];
    $asistencia = $asistencias_alumnos[$id_alumno] ?? ['porcentaje' => 0];
    
    $total_ponderado_tareas = 0;
    $total_porcentaje_tareas = 0;
    
    // Calcular la parte de tareas
    foreach ($porcentajes as $tarea_id => $porcentaje_tarea) {
        $porcentaje_tarea = floatval($porcentaje_tarea);
        if ($porcentaje_tarea > 0) {
            $calificacion_tarea = null;
            foreach ($calificaciones as $cal) {
                if ($cal['id_tarea'] == $tarea_id) {
                    $calificacion_tarea = $cal;
                    break;
                }
            }
            
            $puntaje = $calificacion_tarea && $calificacion_tarea['calificacion'] !== null ? 
                      floatval($calificacion_tarea['calificacion']) : 0;
            $puntos_maximos = $calificacion_tarea ? floatval($calificacion_tarea['puntos_maximos']) : 10;
            
            $porcentaje_obtenido = $puntos_maximos > 0 ? ($puntaje / $puntos_maximos) * 100 : 0;
            $total_ponderado_tareas += $porcentaje_obtenido * ($porcentaje_tarea / 100);
            $total_porcentaje_tareas += $porcentaje_tarea;
        }
    }
    
    // Calcular promedio de tareas (escalado al porcentaje que representan)
    $promedio_tareas = $total_porcentaje_tareas > 0 ? 
        ($total_ponderado_tareas / $total_porcentaje_tareas) * 100 : 0;
    
    // Calcular contribución de la asistencia (VALOR REAL)
    $contribucion_asistencia = ($asistencia['porcentaje'] * $porcentaje_asistencia) / 100;
    
    // Calcular calificación final CORRECTA
    $calificacion_final = $promedio_tareas * ((100 - $porcentaje_asistencia) / 100) + $contribucion_asistencia;
    
    return $calificacion_final;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones - <?php echo htmlspecialchars($clase_info['materia_nombre']); ?></title>
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1565c0, #1976d2); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
        .info-clase { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .form-porcentajes { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .porcentaje-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #e0e0e0; }
        .porcentaje-input { width: 100px; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; text-align: center; font-weight: 600; }
        .porcentaje-input:focus { border-color: #1565c0; outline: none; }
        .total-porcentaje { background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0; font-weight: 600; }
        .total-porcentaje.valido { background: #d4edda; color: #155724; }
        .total-porcentaje.invalido { background: #f8d7da; color: #721c24; }
        .asistencia-section { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .fecha-corte-section { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4caf50; }
        .estadisticas-asistencia { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #2196f3; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1565c0; color: white; }
        .btn-primary:disabled { background: #6c757d; cursor: not-allowed; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .preview-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 30px; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .preview-table th, .preview-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .estadistica-item { display: inline-block; margin: 0 15px; text-align: center; }
        .estadistica-valor { font-size: 1.5em; font-weight: bold; color: #1565c0; }
        .fecha-selector { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .fecha-group { display: flex; flex-direction: column; }
        .fecha-group label { font-weight: 600; margin-bottom: 5px; }
        .calificacion-final { font-weight: bold; color: #1565c0; }
        .contribucion { font-size: 0.8em; color: #666; }
        .permisos-info { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #4caf50; }
        .acciones-section { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1>Calificaciones</h1>
                    <p><?php echo htmlspecialchars($clase_info['materia_nombre']); ?> - Grupo <?php echo $clase_info['grupo']; ?></p>
                </div>
                <a href="detalle_clase.php?id=<?php echo $id_clase; ?>" class="btn btn-secondary">Volver a la Clase</a>
            </div>
        </div>

        <div class="info-clase">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div><strong>Materia:</strong><br><?php echo htmlspecialchars($clase_info['materia_nombre']); ?></div>
                <div><strong>Grupo:</strong><br><?php echo $clase_info['grupo']; ?></div>
                <div><strong>Periodo:</strong><br><?php echo $clase_info['periodo']; ?></div>
                <div><strong>Creditos:</strong><br><?php echo $clase_info['creditos']; ?></div>
                <div><strong>Alumnos:</strong><br><?php echo count($alumnos_clase); ?> inscritos</div>
            </div>
        </div>

        <!-- Información de permisos -->
        <div class="permisos-info">
            <h4 style="margin-top: 0; color: #2e7d32;">Permisos del Sistema</h4>
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div>
                    <strong>Subir calificaciones:</strong> 
                    <span style="color: <?php echo $permisos['subir'] ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                        <?php echo $permisos['subir'] ? 'PERMITIDO' : 'NO PERMITIDO'; ?>
                    </span>
                </div>
                <div>
                    <strong>Modificar calificaciones:</strong> 
                    <span style="color: <?php echo $permisos['modificar'] ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                        <?php echo $permisos['modificar'] ? 'PERMITIDO' : 'NO PERMITIDO'; ?>
                    </span>
                </div>
               
            </div>
        </div>

        <form id="formCalificaciones" method="POST" action="">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            
            <div class="form-porcentajes">
                <div class="form-group">
                    <label for="unidad" style="font-weight: 600; margin-bottom: 10px; display: block;">Seleccionar Unidad:</label>
                    <select id="unidad" name="unidad" class="form-control" required onchange="cargarTareasUnidad(this.value)" style="width: 200px; padding: 10px;">
                        <option value="">Seleccionar unidad</option>
                        <?php for ($i = 1; $i <= $clase_info['unidades']; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $unidad == $i ? 'selected' : ''; ?>>Unidad <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="fecha-corte-section">
                    <h4 style="margin-top: 0; color: #2e7d32;">Intervalo de Fechas para Asistencia</h4>
                    <p style="margin-bottom: 15px; color: #2e7d32;">
                        Selecciona el intervalo de fechas para calcular la asistencia.
                    </p>
                    <div class="fecha-selector">
                        <div class="fecha-group">
                            <label for="fecha_inicio">Fecha de inicio:</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" 
                                   min="<?php echo $rango_fechas['primera_fecha'] ?? ''; ?>" 
                                   max="<?php echo $rango_fechas['ultima_fecha'] ?? ''; ?>"
                                   style="padding: 10px; width: 200px;">
                        </div>
                        <div class="fecha-group">
                            <label for="fecha_fin">Fecha de fin:</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" 
                                   min="<?php echo $rango_fechas['primera_fecha'] ?? ''; ?>" 
                                   max="<?php echo $rango_fechas['ultima_fecha'] ?? ''; ?>"
                                   style="padding: 10px; width: 200px;">
                        </div>
                        <div style="display: flex; align-items: end;">
                            <button type="button" onclick="actualizarAsistencia()" class="btn btn-success" id="btnActualizarAsistencia">
                                Actualizar Asistencia
                            </button>
                        </div>
                    </div>
                    <div class="fecha-info" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <strong>Intervalo seleccionado:</strong> 
                        <span id="fecha_seleccionada"><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></span>
                    </div>
                </div>
                
                <div id="estadisticas-asistencia-actual" class="estadisticas-asistencia">
                    <h4 style="margin-top: 0; color: #1565c0;">Estadisticas de Asistencia 
                        <small>(<?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>)</small>
                    </h4>
                    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px;">
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $porcentaje_promedio_asistencia; ?>%</div>
                            <div class="estadistica-label">Asistencia Promedio</div>
                        </div>
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $clases_totales; ?></div>
                            <div class="estadistica-label">Total de Clases</div>
                        </div>
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $asistencias_totales; ?></div>
                            <div class="estadistica-label">Asistencias Totales</div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>Instrucciones:</strong> Asigna el porcentaje que vale cada tarea para la calificación final de la unidad. 
                    La suma total debe ser exactamente 100%. Las tareas sin entrega se calificarán automaticamente con 0.
                </div>
                
                <div id="contenedor-porcentajes" style="display: none;">
                    <h3 style="margin-bottom: 20px;">Asignar Porcentajes a las Tareas</h3>
                    
                    <div id="lista-tareas-porcentajes"></div>
                    
                    <div class="asistencia-section">
                        <h4 style="margin-top: 0; color: #856404;">Porcentaje de Asistencia</h4>
                        <p style="margin-bottom: 15px; color: #856404;">
                            Define qué porcentaje del total vale la asistencia.
                        </p>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <label for="porcentaje_asistencia" style="font-weight: 600;">Valor de la asistencia:</label>
                            <input type="number" id="porcentaje_asistencia" name="porcentaje_asistencia" 
                                   class="porcentaje-input" min="0" max="100" value="0"
                                   onchange="actualizarTotalPorcentaje()" oninput="actualizarTotalPorcentaje()"
                                   step="0.1" style="width: 120px;">
                            <span style="font-weight: 600;">%</span>
                        </div>
                    </div>
                    
                    <div id="total-porcentaje" class="total-porcentaje">
                        <strong>Total Tareas + Asistencia: <span id="valor-total">0</span>%</strong>
                        <div id="detalle-total" style="font-size: 0.9em; margin-top: 5px;"></div>
                    </div>
                </div>
            </div>
        </form>

        <div id="preview-section" class="preview-section" style="display: none;">
            <h3>Vista Previa de Calificaciones</h3>
            <div id="preview-content"></div>
            
            <!-- Sección de acciones -->
<div id="acciones-section" class="acciones-section" style="display: none;">
    <h4 style="margin-top: 0; color: #856404;">Acciones Disponibles</h4>
    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <form id="formSubirCalificaciones" method="POST" action="guardar_calificaciones.php" style="margin: 0;">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            <input type="hidden" name="permiso_subir" value="<?php echo $permisos['subir'] ? '1' : '0'; ?>">
            <input type="hidden" name="permiso_modificar" value="<?php echo $permisos['modificar'] ? '1' : '0'; ?>">
            <input type="hidden" name="unidad_seleccionada" id="unidadSeleccionada" value="">
            <div id="hidden-calificaciones"></div>
            <button type="button" onclick="prepararYEnviarCalificaciones()" class="btn btn-warning" id="btnSubirCalificaciones">
                <?php 
                if ($unidad > 0) {
                    echo $calificaciones_existentes_unidad ? 'Actualizar Calificaciones' : 'Subir Calificaciones';
                } else {
                    echo 'Subir Calificaciones';
                }
                ?>
            </button>
        </form>
        
        <!-- BOTÓN NUEVO PARA GENERAR PDF -->
        <button type="button" onclick="generarPDF()" class="btn btn-primary" id="btnGenerarPDF" style="display: none;">
            <i class="fas fa-file-pdf"></i> Generar PDF
        </button>
        
        <div id="info-accion" style="font-size: 0.9em; color: #856404;">
            <?php 
            if ($unidad > 0) {
                if ($puede_subir) {
                    echo '<strong>Accion:</strong> Subir nuevas calificaciones para la unidad ' . $unidad;
                } elseif ($puede_modificar) {
                    echo '<strong>Accion:</strong> Modificar calificaciones existentes de la unidad ' . $unidad;
                } else {
                    echo '<strong>Accion:</strong> No tiene permisos para esta accion';
                }
            } else {
                echo '<strong>Accion:</strong> Seleccione una unidad para ver las acciones disponibles';
            }
            ?>
        </div>
    </div>
</div>
        </div>
    </div>

    <script>
        // Datos globales
        const asistenciasAlumnosGlobal = <?php echo json_encode($asistencias_alumnos); ?>;
        const alumnosClaseGlobal = <?php echo json_encode($alumnos_clase); ?>;
        const calificacionesAlumnosGlobal = <?php echo json_encode($calificaciones_alumnos); ?>;
        const tareasPorUnidadGlobal = <?php echo json_encode($tareas_por_unidad); ?>;
        const permisosGlobal = {
            subir: <?php echo $permisos['subir'] ? 'true' : 'false'; ?>,
            modificar: <?php echo $permisos['modificar'] ? 'true' : 'false'; ?>
        };

        // Variables que se actualizarán dinámicamente
        let calificacionesExistentesUnidad = <?php echo $calificaciones_existentes_unidad ? 'true' : 'false'; ?>;
        let puedeSubir = <?php echo $puede_subir ? 'true' : 'false'; ?>;
        let puedeModificar = <?php echo $puede_modificar ? 'true' : 'false'; ?>;
        let mostrarBotonSubir = <?php echo $mostrar_boton_subir ? 'true' : 'false'; ?>;

        function cargarTareasUnidad(unidad) {
            const contenedor = document.getElementById('contenedor-porcentajes');
            const listaTareas = document.getElementById('lista-tareas-porcentajes');
            const previewSection = document.getElementById('preview-section');
            const accionesSection = document.getElementById('acciones-section');
            const unidadSeleccionada = document.getElementById('unidadSeleccionada');
            
            if (!unidad) {
                contenedor.style.display = 'none';
                previewSection.style.display = 'none';
                accionesSection.style.display = 'none';
                return;
            }
            
            // Actualizar unidad seleccionada
            unidadSeleccionada.value = unidad;
            
            // Verificar calificaciones existentes para esta unidad específica
            verificarCalificacionesUnidad(unidad);
            
            const tareasUnidad = tareasPorUnidadGlobal[unidad] || [];
            
            if (tareasUnidad.length === 0) {
                listaTareas.innerHTML = '<div style="text-align: center; padding: 40px; color: #6c757d;">No hay tareas calificables en esta unidad.</div>';
                contenedor.style.display = 'block';
                previewSection.style.display = 'none';
                accionesSection.style.display = 'none';
                return;
            }
            
            listaTareas.innerHTML = '';
            
            tareasUnidad.forEach(tarea => {
                const item = document.createElement('div');
                item.className = 'porcentaje-item';
                item.innerHTML = `
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 5px;">${tarea.titulo}</div>
                        <div style="color: #666; font-size: 0.9em;">
                            Puntos maximos: ${tarea.puntos_maximos} | 
                            Entregas: ${tarea.total_entregas} | 
                            Calificadas: ${tarea.total_calificadas}
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="porcentajes[${tarea.id_tarea}]" 
                               class="porcentaje-input" min="0" max="100" value="0" 
                               onchange="actualizarTotalPorcentaje()" oninput="actualizarTotalPorcentaje()"
                               step="0.1">
                        <span style="font-weight: 600;">%</span>
                    </div>
                `;
                listaTareas.appendChild(item);
            });
            
            contenedor.style.display = 'block';
            previewSection.style.display = 'block';
            
            if (mostrarBotonSubir) {
                accionesSection.style.display = 'block';
            } else {
                accionesSection.style.display = 'none';
            }
            
            actualizarTotalPorcentaje();
        }

        function verificarCalificacionesUnidad(unidad) {
            // Hacer una petición AJAX para verificar calificaciones existentes en esta unidad
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'verificar_calificaciones_unidad.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    calificacionesExistentesUnidad = response.calificaciones_existentes;
                    
                    // Recalcular permisos basados en la unidad específica
                    puedeSubir = permisosGlobal.subir && !calificacionesExistentesUnidad;
                    puedeModificar = permisosGlobal.modificar && calificacionesExistentesUnidad;
                    mostrarBotonSubir = puedeSubir || puedeModificar;
                    
                    // Actualizar interfaz
                    actualizarInterfazAcciones(unidad);
                }
            };
            
            xhr.send('id_clase=<?php echo $id_clase; ?>&unidad=' + unidad);
        }

        function actualizarInterfazAcciones(unidad) {
            const btnSubir = document.getElementById('btnSubirCalificaciones');
            const infoAccion = document.getElementById('info-accion');
            
            if (puedeSubir) {
                btnSubir.innerHTML = 'Subir Calificaciones';
                infoAccion.innerHTML = '<strong>Accion:</strong> Subir nuevas calificaciones para la unidad ' + unidad;
                btnSubir.disabled = false;
            } else if (puedeModificar) {
                btnSubir.innerHTML = 'Actualizar Calificaciones';
                infoAccion.innerHTML = '<strong>Accion:</strong> Modificar calificaciones existentes de la unidad ' + unidad;
                btnSubir.disabled = false;
            } else {
                btnSubir.innerHTML = 'Subir Calificaciones';
                infoAccion.innerHTML = '<strong>Accion:</strong> No tiene permisos para esta accion';
                btnSubir.disabled = true;
            }
        }

        function actualizarTotalPorcentaje() {
            const inputsTareas = document.querySelectorAll('.porcentaje-input:not(#porcentaje_asistencia)');
            const inputAsistencia = document.getElementById('porcentaje_asistencia');
            
            let totalTareas = 0;
            inputsTareas.forEach(input => {
                totalTareas += parseFloat(input.value) || 0;
            });
            
            const porcentajeAsistencia = parseFloat(inputAsistencia.value) || 0;
            const totalGeneral = totalTareas + porcentajeAsistencia;
            
            document.getElementById('valor-total').textContent = totalGeneral.toFixed(1);
            
            const totalElement = document.getElementById('total-porcentaje');
            const detalleElement = document.getElementById('detalle-total');
            
            detalleElement.innerHTML = `
                Tareas: ${totalTareas.toFixed(1)}% + 
                Asistencia: ${porcentajeAsistencia.toFixed(1)}% = 
                Total: ${totalGeneral.toFixed(1)}%
            `;
            
            if (totalGeneral === 100) {
                totalElement.className = 'total-porcentaje valido';
                generarVistaPrevia(document.getElementById('unidad').value);
            } else {
                totalElement.className = 'total-porcentaje invalido';
                document.getElementById('preview-content').innerHTML = `
                    <div class="alert alert-warning">
                        <strong>Ajusta los porcentajes:</strong> La suma debe ser exactamente 100%.
                    </div>
                `;
            }
        }

        function actualizarAsistencia() {
            const unidad = document.getElementById('unidad').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            if (!unidad) {
                alert('Por favor, selecciona una unidad primero.');
                return;
            }
            
            if (!fechaInicio || !fechaFin) {
                alert('Por favor, selecciona ambas fechas.');
                return;
            }
            
            if (fechaInicio > fechaFin) {
                alert('La fecha de inicio no puede ser mayor que la fecha de fin.');
                return;
            }
            
            // Deshabilitar botón temporalmente
            const btn = document.getElementById('btnActualizarAsistencia');
            btn.disabled = true;
            btn.innerHTML = 'Actualizando...';
            
            // Crear un formulario temporal para enviar por POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            // Agregar campos necesarios
            const idClase = document.createElement('input');
            idClase.type = 'hidden';
            idClase.name = 'id_clase';
            idClase.value = '<?php echo $id_clase; ?>';
            form.appendChild(idClase);
            
            const unidadInput = document.createElement('input');
            unidadInput.type = 'hidden';
            unidadInput.name = 'unidad';
            unidadInput.value = unidad;
            form.appendChild(unidadInput);
            
            const fechaInicioInput = document.createElement('input');
            fechaInicioInput.type = 'hidden';
            fechaInicioInput.name = 'fecha_inicio';
            fechaInicioInput.value = fechaInicio;
            form.appendChild(fechaInicioInput);
            
            const fechaFinInput = document.createElement('input');
            fechaFinInput.type = 'hidden';
            fechaFinInput.name = 'fecha_fin';
            fechaFinInput.value = fechaFin;
            form.appendChild(fechaFinInput);
            
            // Enviar formulario
            document.body.appendChild(form);
            form.submit();
        }
function generarPDF() {
    if (!window.calificacionesParaEnvio) {
        alert('No hay calificaciones calculadas para generar el PDF.');
        return;
    }
    
    // Recolectar datos necesarios para el PDF
    const unidad = document.getElementById('unidad').value;
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    const porcentajeAsistencia = parseFloat(document.getElementById('porcentaje_asistencia').value) || 0;
    
    // Recolectar porcentajes de tareas
    const inputsTareas = document.querySelectorAll('.porcentaje-input:not(#porcentaje_asistencia)');
    const porcentajesTareas = {};
    inputsTareas.forEach(input => {
        const tareaId = input.name.match(/\[(\d+)\]/)[1];
        porcentajesTareas[tareaId] = parseFloat(input.value) || 0;
    });
    
    // Crear formulario temporal para enviar al generador de PDF
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generar_pdf_calificaciones.php';
    form.target = '_blank'; // Abrir en nueva pestaña
    
    // Agregar campos necesarios
    const campos = {
        'id_clase': '<?php echo $id_clase; ?>',
        'unidad': unidad,
        'porcentaje_asistencia': porcentajeAsistencia,
        'fecha_inicio': fechaInicio,
        'fecha_fin': fechaFin
    };
    
    // Agregar porcentajes de tareas
    Object.keys(porcentajesTareas).forEach(tareaId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `porcentajes_tareas[${tareaId}]`;
        input.value = porcentajesTareas[tareaId];
        form.appendChild(input);
    });
    
    // Agregar campos básicos
    Object.keys(campos).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = campos[key];
        form.appendChild(input);
    });
    
    // Enviar formulario
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Modificar la función generarVistaPrevia para mostrar el botón de PDF
function generarVistaPrevia(unidad) {
    const previewContent = document.getElementById('preview-content');
    const inputsTareas = document.querySelectorAll('.porcentaje-input:not(#porcentaje_asistencia)');
    const porcentajeAsistencia = parseFloat(document.getElementById('porcentaje_asistencia').value) || 0;
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    // Recolectar porcentajes de tareas
    const porcentajes = {};
    inputsTareas.forEach(input => {
        const tareaId = input.name.match(/\[(\d+)\]/)[1];
        porcentajes[tareaId] = parseFloat(input.value) || 0;
    });
    
    let totalTareas = Object.values(porcentajes).reduce((sum, val) => sum + val, 0);
    const totalGeneral = totalTareas + porcentajeAsistencia;
    
    if (totalGeneral === 100) {
        const fechaInicioTexto = formatFecha(fechaInicio);
        const fechaFinTexto = formatFecha(fechaFin);
        
        let html = `
            <div class="alert alert-success">
                <strong>Configuracion correcta:</strong> Unidad ${unidad}
                <br><small>Asistencia: ${porcentajeAsistencia}% | Tareas: ${totalTareas}% | Intervalo: ${fechaInicioTexto} - ${fechaFinTexto}</small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4>Formula de calificacion:</h4>
                <p><strong>Calificacion Final = (Promedio Tareas × ${100 - porcentajeAsistencia}%) + (Asistencia × ${porcentajeAsistencia}%)</strong></p>
                <p><em>Donde: Promedio Tareas = promedio ponderado de todas las tareas</em></p>
            </div>
            
            <h4>Calificaciones Calculadas:</h4>
            <div style="overflow-x: auto;">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>% Asistencia</th>
                            <th>Calificacion Final</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        // Calcular calificaciones para todos los alumnos
        const calificacionesParaEnvio = {};
        
        alumnosClaseGlobal.forEach(alumno => {
            const calificacionFinal = calcularCalificacionFinal(alumno.id_alumno, unidad, porcentajes, porcentajeAsistencia);
            const asistencia = asistenciasAlumnosGlobal[alumno.id_alumno] || { porcentaje: 0 };
            
            // Preparar datos para envío
            if (!calificacionesParaEnvio[alumno.id_asignacion]) {
                calificacionesParaEnvio[alumno.id_asignacion] = {};
            }
            calificacionesParaEnvio[alumno.id_asignacion][unidad] = calificacionFinal.toFixed(1);
            
            html += `
                <tr>
                    <td>${alumno.nombre} ${alumno.apellidos}</td>
                    <td style="text-align: center;">${asistencia.porcentaje}%</td>
                    <td style="text-align: center;" class="calificacion-final">${calificacionFinal.toFixed(1)}%</td>
                </tr>
            `;
        });
        
        // Guardar calificaciones en variable global para envío
        window.calificacionesParaEnvio = calificacionesParaEnvio;
        
        html += `
                    </tbody>
                </table>
            </div>
            
            <!-- Sección de acciones adicionales -->
            <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 8px;">
                <h5 style="margin-top: 0; color: #2e7d32;">Acciones Adicionales</h5>
                <p>Puede generar un reporte en PDF con las calificaciones y la ponderación utilizada.</p>
                <button type="button" onclick="generarPDF()" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Generar Reporte PDF
                </button>
            </div>
        `;
        
        previewContent.innerHTML = html;
        
        // Mostrar botón de PDF en la sección de acciones principales
        document.getElementById('btnGenerarPDF').style.display = 'inline-block';
        
        // Actualizar información de la acción
        actualizarInterfazAcciones(unidad);
    }
}
        function calcularCalificacionFinal(alumnoId, unidad, porcentajes, porcentajeAsistencia) {
            const calificaciones = calificacionesAlumnosGlobal[alumnoId] || [];
            const asistencia = asistenciasAlumnosGlobal[alumnoId] || { porcentaje: 0 };
            
            let totalPonderadoTareas = 0;
            let totalPorcentajeTareas = 0;
            
            // Calcular la parte de tareas
            Object.keys(porcentajes).forEach(tareaId => {
                const porcentajeTarea = parseFloat(porcentajes[tareaId]);
                if (porcentajeTarea > 0) {
                    const calificacionTarea = calificaciones.find(c => c.id_tarea == tareaId);
                    const puntaje = calificacionTarea && calificacionTarea.calificacion !== null ? 
                                   parseFloat(calificacionTarea.calificacion) : 0;
                    const puntosMaximos = calificacionTarea ? parseFloat(calificacionTarea.puntos_maximos) : 10;
                    
                    const porcentajeObtenido = puntosMaximos > 0 ? (puntaje / puntosMaximos) * 100 : 0;
                    totalPonderadoTareas += porcentajeObtenido * (porcentajeTarea / 100);
                    totalPorcentajeTareas += porcentajeTarea;
                }
            });
            
            // Calcular promedio de tareas (escalado al porcentaje que representan)
            const promedioTareas = totalPorcentajeTareas > 0 ? 
                (totalPonderadoTareas / totalPorcentajeTareas) * 100 : 0;
            
            // Calcular contribución de la asistencia (VALOR REAL)
            const contribucionAsistencia = (asistencia.porcentaje * porcentajeAsistencia) / 100;
            
            // Calcular calificación final CORRECTA
            const calificacionFinal = promedioTareas * ((100 - porcentajeAsistencia) / 100) + contribucionAsistencia;
            
            return calificacionFinal;
        }

        function generarVistaPrevia(unidad) {
            const previewContent = document.getElementById('preview-content');
            const inputsTareas = document.querySelectorAll('.porcentaje-input:not(#porcentaje_asistencia)');
            const porcentajeAsistencia = parseFloat(document.getElementById('porcentaje_asistencia').value) || 0;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            // Recolectar porcentajes de tareas
            const porcentajes = {};
            inputsTareas.forEach(input => {
                const tareaId = input.name.match(/\[(\d+)\]/)[1];
                porcentajes[tareaId] = parseFloat(input.value) || 0;
            });
            
            let totalTareas = Object.values(porcentajes).reduce((sum, val) => sum + val, 0);
            const totalGeneral = totalTareas + porcentajeAsistencia;
            
            if (totalGeneral === 100) {
                const fechaInicioTexto = formatFecha(fechaInicio);
                const fechaFinTexto = formatFecha(fechaFin);
                
                let html = `
                    <div class="alert alert-success">
                        <strong>Configuracion correcta:</strong> Unidad ${unidad}
                        <br><small>Asistencia: ${porcentajeAsistencia}% | Tareas: ${totalTareas}% | Intervalo: ${fechaInicioTexto} - ${fechaFinTexto}</small>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h4>Formula de calificacion:</h4>
                        <p><strong>Calificacion Final = (Promedio Tareas × ${100 - porcentajeAsistencia}%) + (Asistencia × ${porcentajeAsistencia}%)</strong></p>
                        <p><em>Donde: Promedio Tareas = promedio ponderado de todas las tareas</em></p>
                    </div>
                    
                    <h4>Calificaciones Calculadas:</h4>
                    <div style="overflow-x: auto;">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>% Asistencia</th>
                                    <th>Calificacion Final</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                // Calcular calificaciones para todos los alumnos
                const calificacionesParaEnvio = {};
                
                alumnosClaseGlobal.forEach(alumno => {
                    const calificacionFinal = calcularCalificacionFinal(alumno.id_alumno, unidad, porcentajes, porcentajeAsistencia);
                    const asistencia = asistenciasAlumnosGlobal[alumno.id_alumno] || { porcentaje: 0 };
                    
                    // Preparar datos para envío
                    if (!calificacionesParaEnvio[alumno.id_asignacion]) {
                        calificacionesParaEnvio[alumno.id_asignacion] = {};
                    }
                    calificacionesParaEnvio[alumno.id_asignacion][unidad] = calificacionFinal.toFixed(1);
                    
                    html += `
                        <tr>
                            <td>${alumno.nombre} ${alumno.apellidos}</td>
                            <td style="text-align: center;">${asistencia.porcentaje}%</td>
                            <td style="text-align: center;" class="calificacion-final">${calificacionFinal.toFixed(1)}%</td>
                        </tr>
                    `;
                });
                
                // Guardar calificaciones en variable global para envío
                window.calificacionesParaEnvio = calificacionesParaEnvio;
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                previewContent.innerHTML = html;
                
                // Actualizar información de la acción
                actualizarInterfazAcciones(unidad);
            }
        }

        function prepararYEnviarCalificaciones() {
            if (!window.calificacionesParaEnvio) {
                alert('No hay calificaciones calculadas para enviar.');
                return;
            }
            
            if (!puedeSubir && !puedeModificar) {
                alert('No tiene permisos para realizar esta accion.');
                return;
            }
            
            // Confirmar envío
            const accion = puedeSubir ? 'subir' : 'modificar';
            const confirmacion = confirm(`¿Esta seguro de que desea ${accion} estas calificaciones?`);
            
            if (!confirmacion) {
                return;
            }
            
            // Crear inputs hidden para cada calificación
            const hiddenCalificaciones = document.getElementById('hidden-calificaciones');
            hiddenCalificaciones.innerHTML = '';
            
            Object.keys(window.calificacionesParaEnvio).forEach(idAsignacion => {
                Object.keys(window.calificacionesParaEnvio[idAsignacion]).forEach(unidad => {
                    const calificacion = window.calificacionesParaEnvio[idAsignacion][unidad];
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `calificaciones[${idAsignacion}][${unidad}]`;
                    input.value = calificacion;
                    hiddenCalificaciones.appendChild(input);
                });
            });
            
            // Enviar formulario
            document.getElementById('formSubirCalificaciones').submit();
        }

        function formatFecha(fecha) {
            const partes = fecha.split('-');
            return `${partes[2]}/${partes[1]}/${partes[0]}`;
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            const unidadParam = <?php echo $unidad ?: 'null'; ?>;
            if (unidadParam) {
                document.getElementById('unidad').value = unidadParam;
                cargarTareasUnidad(unidadParam);
            }
        });
    </script>
</body>
</html>

<?php
include "footer.php";
?>