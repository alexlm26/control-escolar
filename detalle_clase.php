<?php
ob_start(); 
// Mostrar mensajes de éxito/error
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_clase = $_GET['id'] ?? 0;
$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

// Verificar que el usuario tiene acceso a esta clase
if ($rol == 1) { // ALUMNO
    $stmt = $conexion->prepare("
        SELECT a.id_asignacion 
        FROM asignacion a 
        INNER JOIN alumno al ON a.id_alumno = al.id_alumno 
        WHERE al.id_usuario = ? AND a.id_clase = ?
    ");
    $stmt->bind_param("ii", $id_usuario, $id_clase);
    $stmt->execute();
    $tiene_acceso = $stmt->get_result()->num_rows > 0;
    
} elseif ($rol == 2) { // PROFESOR
    $stmt = $conexion->prepare("
        SELECT c.id_clase 
        FROM clase c 
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor 
        WHERE p.id_usuario = ? AND c.id_clase = ?
    ");
    $stmt->bind_param("ii", $id_usuario, $id_clase);
    $stmt->execute();
    $tiene_acceso = $stmt->get_result()->num_rows > 0;
    
} else { // COORDINADOR - acceso total
    $tiene_acceso = true;
}

if (!$tiene_acceso || $id_clase == 0) {
    header("Location: clases.php");
    exit;
}

// Obtener información básica de la clase
$query_clase = $conexion->prepare("
    SELECT 
        c.id_clase, c.grupo,
        m.nombre as materia_nombre,
        m.creditos,
        m.unidades,
        CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
        prof.clave as clave,
        prof.correo as correo,
        s.nombre as salon,
        s.edificio,
        car.nombre as carrera_nombre,
        c.periodo,
        c.capacidad,
        (SELECT COUNT(*) FROM asignacion WHERE id_clase = c.id_clase) as alumnos_inscritos
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN salon s ON c.id_salon = s.id_salon
    INNER JOIN carrera car ON m.id_carrera = car.id_carrera
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario prof ON p.id_usuario = prof.id_usuario
    WHERE c.id_clase = ?
");
$query_clase->bind_param("i", $id_clase);
$query_clase->execute();
$clase_info = $query_clase->get_result()->fetch_assoc();

// Obtener tareas de la clase agrupadas por unidad
$query_tareas = $conexion->prepare("
    SELECT 
        t.*,
        COUNT(et.id_entrega) as total_entregas,
        COUNT(CASE WHEN et.calificacion IS NOT NULL THEN 1 END) as total_calificadas
    FROM tareas t
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
    WHERE t.id_clase = ?
    GROUP BY t.id_tarea
    ORDER BY t.unidad ASC, t.fecha_limite DESC
");
$query_tareas->bind_param("i", $id_clase);
$query_tareas->execute();
$tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);

// ORGANIZAR TAREAS POR UNIDAD
$tareas_por_unidad = [];
foreach ($tareas as $tarea) {
    $unidad = $tarea['unidad'];
    if (!isset($tareas_por_unidad[$unidad])) {
        $tareas_por_unidad[$unidad] = [];
    }
    $tareas_por_unidad[$unidad][] = $tarea;
}

// Si es alumno, obtener sus entregas
$mis_entregas = [];
if ($rol == 1) {
    $query_mis_entregas = $conexion->prepare("
        SELECT et.*, t.titulo, t.fecha_limite, t.puntos_maximos, t.estado as estado_tarea
        FROM entregas_tareas et
        INNER JOIN tareas t ON et.id_tarea = t.id_tarea
        INNER JOIN alumno a ON et.id_alumno = a.id_alumno
        WHERE a.id_usuario = ? AND t.id_clase = ?
    ");
    $query_mis_entregas->bind_param("ii", $id_usuario, $id_clase);
    $query_mis_entregas->execute();
    $mis_entregas = $query_mis_entregas->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Si es profesor y se seleccionó una tarea
$tarea_seleccionada = null;
$entregas_tarea = [];
if (($rol == 2 || $rol == 3) && isset($_GET['tarea_id'])) {
    $tarea_id = $_GET['tarea_id'];
    
    // Obtener información de la tarea seleccionada
    $query_tarea = $conexion->prepare("SELECT * FROM tareas WHERE id_tarea = ? AND id_clase = ?");
    $query_tarea->bind_param("ii", $tarea_id, $id_clase);
    $query_tarea->execute();
    $tarea_seleccionada = $query_tarea->get_result()->fetch_assoc();
    
    // Obtener entregas de alumnos para esta tarea
    $query_entregas = $conexion->prepare("
        SELECT 
            et.*,
            a.id_alumno,
            u.nombre as alumno_nombre,
            u.apellidos as alumno_apellidos,
            u.clave as numero_control
        FROM entregas_tareas et
        INNER JOIN alumno a ON et.id_alumno = a.id_alumno
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE et.id_tarea = ?
        ORDER BY et.fecha_entrega DESC
    ");
    $query_entregas->bind_param("i", $tarea_id);
    $query_entregas->execute();
    $entregas_tarea = $query_entregas->get_result()->fetch_all(MYSQLI_ASSOC);
}

// OBTENER DATOS PARA LA LISTA DE ASISTENCIA (SOLO PROFESOR)
$alumnos_clase = [];
$asistencias_hoy = [];
$fecha_hoy = date('Y-m-d');

if ($rol == 2 || $rol == 3) {
    // Obtener alumnos de la clase
    $query_alumnos = $conexion->prepare("
        SELECT 
            a.id_alumno,
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

    // Obtener asistencias del día actual
    $query_asistencias_hoy = $conexion->prepare("
        SELECT id_alumno, estado_asistencia, comentario 
        FROM asistencia 
        WHERE id_clase = ? AND fecha = ?
    ");
    $query_asistencias_hoy->bind_param("is", $id_clase, $fecha_hoy);
    $query_asistencias_hoy->execute();
    $result_asistencias = $query_asistencias_hoy->get_result();
    
    while ($row = $result_asistencias->fetch_assoc()) {
        $asistencias_hoy[$row['id_alumno']] = $row;
    }
}

// Funciones auxiliares para el pase de lista
function obtenerFechasClase($conexion, $id_clase) {
    $fechas = [];
    $fecha_hoy = date('Y-m-d');
    
    // Obtener las últimas 15 fechas donde hubo clase (incluyendo hoy)
    $query = $conexion->prepare("
        SELECT DISTINCT fecha 
        FROM asistencia 
        WHERE id_clase = ? 
        ORDER BY fecha DESC 
        LIMIT 14
    ");
    $query->bind_param("i", $id_clase);
    $query->execute();
    $result = $query->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $fechas[] = $row['fecha'];
    }
    
    // Asegurar que hoy esté incluido
    if (!in_array($fecha_hoy, $fechas)) {
        array_unshift($fechas, $fecha_hoy);
    }
    
    // Ordenar fechas de más antigua a más reciente
    sort($fechas);
    
    return $fechas;
}

function obtenerAlumnosConAsistencia($conexion, $id_clase, $fechas) {
    $alumnos = [];
    
    // Obtener alumnos de la clase
    $query_alumnos = $conexion->prepare("
        SELECT 
            a.id_alumno,
            u.clave,
            CONCAT(u.apellidos, ' ', u.nombre) as nombre_completo
        FROM asignacion asig
        INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE asig.id_clase = ?
        ORDER BY u.apellidos, u.nombre
    ");
    $query_alumnos->bind_param("i", $id_clase);
    $query_alumnos->execute();
    $result_alumnos = $query_alumnos->get_result();
    
    while ($alumno = $result_alumnos->fetch_assoc()) {
        $alumno['asistencias'] = [];
        $alumnos[] = $alumno;
    }
    
    // Obtener asistencias para las fechas
    if (!empty($fechas)) {
        $placeholders = str_repeat('?,', count($fechas) - 1) . '?';
        $query_asistencias = $conexion->prepare("
            SELECT id_alumno, fecha, estado_asistencia 
            FROM asistencia 
            WHERE id_clase = ? AND fecha IN ($placeholders)
        ");
        
        $types = "i" . str_repeat("s", count($fechas));
        $params = array_merge([$id_clase], $fechas);
        $query_asistencias->bind_param($types, ...$params);
        $query_asistencias->execute();
        $result_asistencias = $query_asistencias->get_result();
        
        while ($asistencia = $result_asistencias->fetch_assoc()) {
            foreach ($alumnos as &$alumno) {
                if ($alumno['id_alumno'] == $asistencia['id_alumno']) {
                    $alumno['asistencias'][$asistencia['fecha']] = $asistencia['estado_asistencia'];
                    break;
                }
            }
        }
    }
    
    return $alumnos;
}

function calcularPorcentajeAsistencia($asistencias) {
    if (empty($asistencias)) return 0;
    
    $total_dias = count($asistencias);
    $dias_asistidos = 0;
    
    foreach ($asistencias as $estado) {
        if ($estado == 'presente' || $estado == 'justificado') {
            $dias_asistidos++;
        }
    }
    
    $porcentaje = ($dias_asistidos / $total_dias) * 100;
    return round($porcentaje);
}

$fechas = obtenerFechasClase($conexion, $id_clase);
$alumnos = obtenerAlumnosConAsistencia($conexion, $id_clase, $fechas);
// OBTENER JUSTIFICANTES DE LA CLASE
$justificantes_clase = [];
if ($rol == 2 || $rol == 3) {
    $query_justificantes = $conexion->prepare("
        SELECT 
            ja.*,
            u.nombre as alumno_nombre,
            u.apellidos as alumno_apellidos,
            u.clave as numero_control
        FROM justificantes_asistencia ja
        INNER JOIN alumno a ON ja.id_alumno = a.id_alumno
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario
        INNER JOIN asignacion asig ON a.id_alumno = asig.id_alumno
        WHERE asig.id_clase = ? 
        AND ja.estado = 'aprobado'
        AND ? BETWEEN ja.fecha_inicio AND ja.fecha_fin
        ORDER BY ja.fecha_inicio DESC
    ");
    $fecha_hoy = date('Y-m-d');
    $query_justificantes->bind_param("is", $id_clase, $fecha_hoy);
    $query_justificantes->execute();
    $justificantes_clase = $query_justificantes->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clase_info['materia_nombre']); ?> - Sistema de Clases</title>
    <style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
  --color-fondo: #f4f6f8;
  --color-texto: #333;
  --color-blanco: #fff;
  --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
  --sombra-hover: 0 8px 18px rgba(0,0,0,0.15);
  --radio-borde: 14px;
}

* {
  box-sizing: border-box;
}

body {
  background: var(--color-fondo);
  font-family: "Poppins", "Segoe UI", sans-serif;
  color: var(--color-texto);
  margin: 0;
  padding: 0;
}

.content {
  padding: 20px 5%;
  max-width: 1200px;
  margin: auto;
}

/* SECCIONES Y NAVEGACIÓN */
.seccion-activa {
    display: block !important;
    animation: fadeIn 0.3s ease;
}

.seccion-oculta {
    display: none !important;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.tabs {
  display: flex;
  background: white;
  border-radius: var(--radio-borde);
  padding: 5px;
  margin-bottom: 25px;
  box-shadow: var(--sombra-suave);
  overflow-x: auto;
}

.tab {
  flex: 1;
  padding: 15px;
  text-align: center;
  cursor: pointer;
  border-radius: 10px;
  transition: all 0.3s ease;
  font-weight: 600;
  min-width: 120px;
  white-space: nowrap;
}

.tab.active {
  background: var(--color-primario);
  color: white;
}
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-weight: 600;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}
/* HEADER DE CLASE */
.clase-header {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 25px 20px;
  border-radius: var(--radio-borde);
  margin-bottom: 25px;
  box-shadow: var(--sombra-suave);
}

.clase-header h1 {
  margin: 0 0 10px 0;
  font-size: 1.8em;
  font-weight: 700;
}

.clase-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 20px;
}

.info-card {
  background: rgba(255,255,255,0.1);
  padding: 15px;
  border-radius: 10px;
  backdrop-filter: blur(10px);
}

.info-card .label {
  font-size: 0.9em;
  opacity: 0.8;
  margin-bottom: 5px;
}

.info-card .value {
  font-size: 1.1em;
  font-weight: 600;
}

/* TAREAS */
.grid-tareas {
  display: grid;
  gap: 20px;
  margin-bottom: 30px;
}

.tarjeta-tarea {
  background: white;
  border-radius: var(--radio-borde);
  padding: 20px;
  box-shadow: var(--sombra-suave);
  transition: all 0.3s ease;
  border-left: 5px solid var(--color-primario);
}

.tarjeta-tarea:hover {
  transform: translateY(-3px);
  box-shadow: var(--sombra-hover);
}

.tarjeta-tarea-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.tarjeta-tarea-header h3 {
  margin: 0;
  color: var(--color-primario);
  flex: 1;
  min-width: 200px;
}

.tarea-stats {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.stat {
  background: #e3f2fd;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85em;
  color: var(--color-primario);
  font-weight: 600;
}

.tarea-fecha {
  color: #666;
  margin-bottom: 10px;
  font-size: 0.9em;
}

.tarea-descripcion {
  color: #555;
  line-height: 1.5;
  margin-bottom: 15px;
  font-size: 0.95em;
}

.acciones-tarea {
  display: flex;
  gap: 10px;
  margin-top: 15px;
  flex-wrap: wrap;
}

.archivo-subido {
  background: #e3f2fd;
  padding: 10px 15px;
  border-radius: 8px;
  margin-top: 10px;
  border-left: 4px solid var(--color-primario);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: var(--radio-borde);
    box-shadow: var(--sombra-suave);
}

.empty-state h3 {
    color: #6c757d;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
}

/* BOTONES */
.btn {
  padding: 10px 18px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  font-size: 0.9em;
}

.btn-primary {
  background: var(--color-primario);
  color: white;
}

.btn-primary:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-warning {
  background: #ffc107;
  color: #212529;
}
            /* Estilos para avisos de justificantes */
.aviso-justificantes {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.btn-ver-detalles {
    background: #ffc107;
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-ver-detalles:hover {
    background: #e0a800;
    transform: translateY(-2px);
}

.btn-warning:hover {
  background: #e0a800;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 0.875em;
}

.btn-flotante {
  position: fixed;
  bottom: 25px;
  right: 25px;
  background: var(--color-primario);
  color: white;
  border: none;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  font-size: 1.5em;
  cursor: pointer;
  box-shadow: var(--sombra-hover);
  transition: all 0.3s ease;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-flotante:hover {
  background: var(--color-secundario);
  transform: scale(1.1);
}

/* FORMULARIOS */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
}

.form-control {
  width: 100%;
  padding: 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 1em;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primario);
}

/* MODALES */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 20px;
}

.modal[style*="display: flex"] {
    display: flex !important;
}

.modal-content {
  background: white;
  border-radius: var(--radio-borde);
  padding: 25px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

/* TABLAS */
.tabla-entregas {
  width: 100%;
  background: white;
  border-radius: var(--radio-borde);
  overflow: hidden;
  box-shadow: var(--sombra-suave);
  margin-bottom: 20px;
  overflow-x: auto;
}

.tabla-entregas table {
  width: 100%;
  min-width: 600px;
}

.tabla-entregas th,
.tabla-entregas td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #eee;
}

.tabla-entregas th {
  background: #f8f9fa;
  font-weight: 600;
  color: var(--color-primario);
}

.tabla-entregas tr:hover {
  background: #f8f9fa;
}

.estado-entregado { color: #28a745; font-weight: 600; }
.estado-pendiente { color: #ffc107; font-weight: 600; }

/* PASE DE LISTA - ESTILOS PRINCIPALES */
.tabla-asistencia-container {
    background: white;
    border-radius: var(--radio-borde);
    padding: 20px;
    box-shadow: var(--sombra-suave);
    margin-bottom: 30px;
}

.controles-lista {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.selector-unidad {
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.9em;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

.tabla-asistencia {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
    font-size: 0.85em;
}

.tabla-asistencia th,
.tabla-asistencia td {
    padding: 8px 5px;
    text-align: center;
    border: 1px solid #e0e0e0;
}

.tabla-asistencia th {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    white-space: nowrap;
}

/* COLUMNAS COMPACTAS - NOMBRE MÁS ANGOSTO */
.columna-clave {
    min-width: 90px;
    max-width: 90px;
    font-weight: 600;
}

.columna-nombre {
    min-width: 120px;
    max-width: 120px;
    text-align: left !important;
    padding-left: 8px !important;
    font-size: 0.8em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tabla-asistencia th.fecha-header {
    writing-mode: vertical-lr;
    transform: rotate(180deg);
    height: 80px;
    min-width: 20px;
    max-width: 20px;
    font-size: 0.7em;
}

.tabla-asistencia th.fecha-hoy {
    background: linear-gradient(135deg, #1976d2, #2196f3);
}

.tabla-asistencia th.porcentaje-header {
    background: linear-gradient(135deg, #7b1fa2, #9c27b0);
    min-width: 50px;
}

/* CELDAS DE ASISTENCIA */
.celda-asistencia {
    width: 20px;
    height: 20px;
    font-weight: bold;
    cursor: default;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 0.75em;
}

.celda-asistencia.presente {
    background-color: #2196f3;
    color: white;
}

.celda-asistencia.ausente {
    background-color: #f44336;
    color: white;
}

.celda-asistencia.justificado {
    background-color: #ffeb3b;
    color: #333;
}

.selector-estado {
    width: 20px;
    height: 20px;
    border: 2px solid #ddd;
    border-radius: 4px;
    font-weight: bold;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 0.75em;
}

.selector-estado.presente {
    background-color: #2196f3 !important;
    color: white;
    border-color: #2196f3;
}

.selector-estado.ausente {
    background-color: #f44336 !important;
    color: white;
    border-color: #f44336;
}

.selector-estado.justificado {
    background-color: #ffeb3b !important;
    color: #333;
    border-color: #ffc107;
}

.selector-estado.vacio {
    background-color: #f8f9fa !important;
    color: #6c757d;
    border-color: #dee2e6;
}

.celda-editable {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.celda-editable:hover {
    transform: scale(1.2);
    box-shadow: 0 0 0 2px #2196f3;
    z-index: 10;
}

/* PORCENTAJE */
.porcentaje-asistencia {
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    min-width: 45px;
    display: inline-block;
}

.porcentaje-alto {
    background-color: #e3f2fd;
    color: #1565c0;
}

.porcentaje-medio {
    background-color: #fff3cd;
    color: #856404;
}

.porcentaje-bajo {
    background-color: #f8d7da;
    color: #721c24;
}

/* BOTÓN GUARDAR */
.btn-guardar-lista {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9em;
    transition: all 0.3s ease;
    margin-top: 20px;
}

.btn-guardar-lista:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(21, 101, 192, 0.3);
}

/* MODAL DE ASISTENCIA MEJORADO */
.modal-asistencia {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 15px;
}

.modal-contenido-asistencia {
    background: white;
    border-radius: 12px;
    padding: 20px;
    max-width: 400px;
    width: 100%;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.indicador-alumno {
    background: #e3f2fd;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 10px;
    font-size: 0.9em;
    color: #1976d2;
}

.info-alumno-modal {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #2196f3;
}

.info-alumno-modal h4 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 1em;
}

.info-alumno-modal p {
    margin: 0;
    color: #666;
    font-size: 0.85em;
}

.opciones-estado {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.opcion-estado {
    width: 60px;
    height: 60px;
    border: 3px solid transparent;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2em;
    cursor: pointer;
    transition: all 0.3s ease;
}

.opcion-estado:hover {
    transform: scale(1.1);
}

.opcion-estado.presente {
    background: #2196f3;
    color: white;
}

.opcion-estado.ausente {
    background: #f44336;
    color: white;
}

.opcion-estado.justificado {
    background: #ffeb3b;
    color: #333;
}

.opcion-estado.seleccionada {
    border-color: #2196f3;
    box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.3);
}
            /* ESTILOS MEJORADOS PARA VISTA MÓVIL */
.btn-estado-movil {
    flex: 1;
    padding: 12px 8px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85em;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-height: 60px;
}

.btn-estado-movil.activo {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-estado-movil.presente {
    background: #2196f3 !important;
    color: white;
    border-color: #2196f3;
}

.btn-estado-movil.ausente {
    background: #f44336 !important;
    color: white;
    border-color: #f44336;
}

.btn-estado-movil.justificado {
    background: #ffeb3b !important;
    color: #333;
    border-color: #ffc107;
}

.icono-estado {
    font-size: 1.2em;
    font-weight: bold;
}

.texto-estado {
    font-size: 0.75em;
    white-space: nowrap;
}

.tarjeta-alumno-movil {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: var(--sombra-suave);
    border-left: 4px solid #e0e0e0;
    transition: all 0.3s ease;
    position: relative;
}

.tarjeta-alumno-movil.presente {
    border-left-color: #2196f3;
    background: linear-gradient(135deg, #ffffff, #e3f2fd);
}

.tarjeta-alumno-movil.ausente {
    border-left-color: #f44336;
    background: linear-gradient(135deg, #ffffff, #ffebee);
}

.tarjeta-alumno-movil.justificado {
    border-left-color: #ffeb3b;
    background: linear-gradient(135deg, #ffffff, #fffde7);
}

.estado-actual-movil {
    margin-top: 10px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    text-align: center;
    font-size: 0.8em;
    border: 1px solid #e9ecef;
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(-10px); }
    20% { opacity: 1; transform: translateY(0); }
    80% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-10px); }
}

.feedback-movil {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #4caf50;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
    z-index: 10;
    animation: fadeInOut 2s ease-in-out;
}

/* MEJORAS RESPONSIVAS PARA MÓVIL */
@media (max-width: 480px) {
    .controles-movil {
        gap: 8px;
    }
    
    .btn-estado-movil {
        padding: 10px 6px;
        min-height: 55px;
        font-size: 0.8em;
    }
    
    .icono-estado {
        font-size: 1.1em;
    }
    
    .texto-estado {
        font-size: 0.7em;
    }
    
    .tarjeta-alumno-movil {
        padding: 12px;
    }
}

.instrucciones-teclado {
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 0.8em;
    color: #666;
}

.instrucciones-teclado kbd {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #ced4da;
    font-family: monospace;
}

/* VISTA MÓVIL PARA ASISTENCIA */
.vista-movil-asistencia {
    display: none;
}

.tarjeta-alumno-movil {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: var(--sombra-suave);
    border-left: 4px solid #e0e0e0;
    transition: all 0.3s ease;
}

.tarjeta-alumno-movil.presente {
    border-left-color: #2196f3;
}

.tarjeta-alumno-movil.ausente {
    border-left-color: #f44336;
}

.tarjeta-alumno-movil.justificado {
    border-left-color: #ffeb3b;
}

.info-alumno-movil {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.alumno-clave {
    font-weight: 600;
    color: var(--color-primario);
    font-size: 0.9em;
}

.alumno-nombre {
    font-weight: 600;
    flex: 1;
    margin-left: 15px;
    font-size: 0.95em;
}

.controles-movil {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-estado-movil {
    flex: 1;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9em;
}

.btn-estado-movil.presente {
    background: #2196f3;
    color: white;
    border-color: #2196f3;
}

.btn-estado-movil.ausente {
    background: #f44336;
    color: white;
    border-color: #f44336;
}

.btn-estado-movil.justificado {
    background: #ffeb3b;
    color: #333;
    border-color: #ffc107;
}

.indicador-movil {
    background: #e3f2fd;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
    border-left: 4px solid var(--color-primario);
}

.controles-navegacion {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    gap: 10px;
}

.btn-navegacion {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: var(--color-primario);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-navegacion:hover {
    background: var(--color-secundario);
}

.btn-navegacion:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

/* Spinner para carga de comentarios */
.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Mejora el estilo de los comentarios */
.comentario-texto {
    background: white;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    min-height: 60px;
    white-space: pre-wrap;
    line-height: 1.4;
}

.comentario-texto:empty::before {
    content: "No hay comentario.";
    color: #6c757d;
    font-style: italic;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .content {
    padding: 15px 3%;
  }
  
  .clase-header {
    padding: 20px 15px;
  }
  
  .clase-header h1 {
    font-size: 1.5em;
  }
  
  .clase-info-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  
  .tabs {
    flex-direction: row;
    overflow-x: auto;
    padding: 3px;
  }
  
  .tab {
    min-width: 100px;
    padding: 12px 8px;
    font-size: 0.9em;
  }
  
  .tarjeta-tarea-header {
    flex-direction: column;
    gap: 10px;
  }
  
  .tarea-stats {
    justify-content: flex-start;
  }

  .controles-lista {
    flex-direction: column;
    align-items: flex-start;
  }

  .columna-nombre {
    min-width: 100px;
    max-width: 100px;
  }
  
  .tabla-asistencia-container {
    padding: 15px;
    overflow-x: auto;
  }
  
  .modal-content {
    padding: 20px 15px;
  }
  
  .opciones-estado {
    gap: 10px;
  }
  
  .opcion-estado {
    width: 50px;
    height: 50px;
    font-size: 1em;
  }
  
  .btn-flotante {
    bottom: 20px;
    right: 20px;
    width: 55px;
    height: 55px;
    font-size: 1.3em;
  }
}

@media (max-width: 480px) {
  .content {
    padding: 10px 2%;
  }
  
  .clase-header {
    padding: 15px 10px;
  }
  
  .clase-header h1 {
    font-size: 1.3em;
  }
  
  .info-card {
    padding: 12px;
  }
  
  .tarjeta-tarea {
    padding: 15px;
  }
  
  .btn {
    padding: 8px 15px;
    font-size: 0.85em;
  }
  
  .modal-contenido-asistencia {
    padding: 15px;
  }
  
  .info-alumno-movil {
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
  }
  
  .alumno-nombre {
    margin-left: 0;
  }
}
            /* Estilos para avisos */
.tarjeta-aviso {
    border-left: 5px solid #ffc107 !important;
    background: linear-gradient(135deg, #fff, #fffbf0) !important;
}

.badge-aviso {
    background: #ffc107;
    color: #000;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.75em;
    font-weight: 600;
    margin-left: 10px;
}

/* Estilos para tareas vencidas */
.tarea-vencida {
    border-left: 5px solid #dc3545 !important;
    background: linear-gradient(135deg, #fff, #ffeaea) !important;
}

.badge-vencida {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    margin-left: 10px;
}

/* Estilos para tareas cerradas */
.tarea-cerrada {
    border-left: 5px solid #6c757d !important;
    background: linear-gradient(135deg, #fff, #f8f9fa) !important;
    opacity: 0.8;
}

.badge-cerrada {
    background: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    margin-left: 10px;
}

/* Estilos para tareas canceladas */
.tarea-cancelada {
    border-left: 5px solid #dc3545 !important;
    background: linear-gradient(135deg, #fff, #ffeaea) !important;
    opacity: 0.6;
}

.badge-cancelada {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    margin-left: 10px;
}

/* Botón de gestión para profesores */
.btn-gestion {
    background: #6c757d;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8em;
    cursor: pointer;
    margin-left: 5px;
}

.btn-gestion:hover {
    background: #5a6268;
}

/* Estilos para el modal de generar PDF */
.modal-pdf {
    max-width: 500px;
}

.form-porcentajes {
    max-height: 400px;
    overflow-y: auto;
}

.porcentaje-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.porcentaje-item:last-child {
    border-bottom: none;
}

.porcentaje-input {
    width: 80px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
}
</style>
</head>
<body>

<!-- HEADER DE LA CLASE -->
<div class="clase-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
        <div>
           <br>
                <br>
                <br>
                
           <h1><?php 
if (isset($clase_info['materia_nombre'])) {
    $materia = $clase_info['materia_nombre']." - Grupo: ".$clase_info['grupo'];
    echo "<br><br><br>" . htmlspecialchars($materia);
}
?></h1>
            <p>Profesor: <?php echo htmlspecialchars($clase_info['profesor_nombre']); ?> - Clave: <?php echo $clase_info['clave']; ?></p>
            <p>Correo: <?php echo $clase_info['correo']; ?></p>
        </div>
        <div style="text-align: right;">
            <div style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 10px; backdrop-filter: blur(10px);">
                <div style="font-size: 0.9em; opacity: 0.8;">CODIGO DE CLASE</div>
                <div style="font-size: 1.4em; font-weight: 700; letter-spacing: 2px;">#<?php echo $id_clase; ?></div>
            </div>
        </div>
    </div>
    
    <div class="clase-info-grid">
        <div class="info-card">
            <div class="label">Salon</div>
            <div class="value"><?php echo $clase_info['salon']; ?> - <?php echo $clase_info['edificio']; ?></div>
        </div>
        <div class="info-card">
            <div class="label">Creditos</div>
            <div class="value"><?php echo $clase_info['creditos']; ?></div>
        </div>
        <div class="info-card">
            <div class="label">Alumnos</div>
            <div class="value"><?php echo $clase_info['alumnos_inscritos']; ?>/<?php echo $clase_info['capacidad']; ?></div>
        </div>
        <div class="info-card">
            <div class="label">Periodo</div>
            <div class="value"><?= $clase_info['periodo'] ?></div>
        </div>
    </div>
    
    <!-- BOTONES PARA PROFESOR -->
    <?php if ($rol == 2): ?>
        <div style="text-align: center; margin-top: 20px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="exportar_calificaciones.php?id_clase=<?php echo $id_clase; ?>" class="btn btn-primary">
    Exportar calificaciones
</a>
        </div>
    <?php endif; ?>
</div>

<main class="content">
<!-- AVISO DE JUSTIFICANTES ACTIVOS -->
<?php if (($rol == 2 || $rol == 3) && count($justificantes_clase) > 0): ?>
<div class="aviso-justificantes" style="
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 2px solid #ffc107;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: var(--sombra-suave);
">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 2em;"></div>
            <div>
                <h3 style="margin: 0 0 5px 0; color: #856404;">
                    Justificantes Activos Hoy
                </h3>
                <p style="margin: 0; color: #856404;">
                    <?php echo count($justificantes_clase); ?> alumno(s) con justificante para hoy
                </p>
            </div>
        </div>
        <a href="ver_justificantes.php?id_clase=<?php echo $id_clase; ?>" 
           class="btn btn-warning" 
           style="text-decoration: none;">
            Ver Detalles
        </a>
    </div>
    
    <!-- LISTA RÁPIDA DE JUSTIFICANTES -->
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ffc107;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
            <?php foreach(array_slice($justificantes_clase, 0, 3) as $justificante): ?>
            <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 8px;">
                <div style="font-weight: 600; color: #333;">
                    <?php echo htmlspecialchars($justificante['alumno_nombre'] . ' ' . $justificante['alumno_apellidos']); ?>
                </div>
                <div style="font-size: 0.85em; color: #666;">
                    <?php echo date('d/m/Y', strtotime($justificante['fecha_inicio'])); ?> 
                    - <?php echo date('d/m/Y', strtotime($justificante['fecha_fin'])); ?>
                </div>
                <div style="font-size: 0.8em; color: #28a745; font-weight: 600;">
                    <?php echo ucfirst($justificante['tipo_justificante']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($justificantes_clase) > 3): ?>
        <div style="text-align: center; margin-top: 10px;">
            <small style="color: #856404;">
                + <?php echo count($justificantes_clase) - 3; ?> justificantes más...
            </small>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
    <!-- PESTAÑAS DE NAVEGACIÓN -->
<div class="tabs">
    <div class="tab active" onclick="mostrarSeccion('tareas')">Tareas</div>
    <?php if ($rol == 2 || $rol == 3): ?>
        <div class="tab" onclick="mostrarSeccion('lista')">Lista</div>
    <?php endif; ?>
</div>

<!-- SECCIÓN DE TAREAS -->
<div id="seccion-tareas" class="seccion-activa">
    <?php if (($rol == 2 || $rol == 3) && $tarea_seleccionada ): ?>
        <!-- VISTA PROFESOR - DETALLE DE TAREA ESPECÍFICA -->
<div style="margin-bottom: 30px;">
    <a href="detalle_clase.php?id=<?php echo $id_clase; ?>" class="btn btn-secondary">Volver a todas las tareas</a>
</div>

<h2><?php echo htmlspecialchars($tarea_seleccionada['titulo']); ?></h2>
<p><strong>Fecha limite:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea_seleccionada['fecha_limite'])); ?></p>
<p><strong>Puntos maximos:</strong> <?php echo $tarea_seleccionada['puntos_maximos']; ?></p>
<?php if ($tarea_seleccionada['descripcion']): ?>
    <p><strong>Descripcion:</strong> <?php echo nl2br(htmlspecialchars($tarea_seleccionada['descripcion'])); ?></p>
<?php endif; ?>

<h3 style="margin-top: 30px;">Entregas de Alumnos</h3>

<?php 
// Obtener alumnos que NO han entregado esta tarea
$query_alumnos_sin_entrega = $conexion->prepare("
    SELECT 
        a.id_alumno,
        u.nombre as alumno_nombre,
        u.apellidos as alumno_apellidos,
        u.clave as numero_control
    FROM asignacion asig
    INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE asig.id_clase = ?
    AND a.id_alumno NOT IN (
        SELECT id_alumno 
        FROM entregas_tareas 
        WHERE id_tarea = ?
    )
    ORDER BY u.apellidos, u.nombre
");
$query_alumnos_sin_entrega->bind_param("ii", $id_clase, $tarea_seleccionada['id_tarea']);
$query_alumnos_sin_entrega->execute();
$alumnos_sin_entrega = $query_alumnos_sin_entrega->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php if (count($entregas_tarea) > 0): ?>
    <div class="tabla-entregas">
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Número Control</th>
                    <th>Fecha Entrega</th>
                    <th>Archivo</th>
                    <th>Calificacion</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($entregas_tarea as $entrega): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entrega['alumno_nombre'] . ' ' . $entrega['alumno_apellidos']); ?></td>
                        <td><?php echo $entrega['numero_control']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?></td>
                        <td>
                            <?php if ($entrega['archivo_alumno']): ?>
                                <a href="uploads/tareas/alumno/<?php echo $entrega['archivo_alumno']; ?>" download class="btn btn-primary btn-sm">
                                    Descargar
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Sin archivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entrega['calificacion'] !== null): ?>
                                <strong><?php echo $entrega['calificacion']; ?>/<?php echo $tarea_seleccionada['puntos_maximos']; ?></strong>
                            <?php else: ?>
                                <span class="estado-pendiente">Sin calificar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="calificarEntrega(<?php echo $entrega['id_entrega']; ?>, <?php echo $tarea_seleccionada['puntos_maximos']; ?>)" class="btn btn-success btn-sm">
                                Calificar
                            </button>
                            <?php if ($entrega['comentario_alumno'] || $entrega['comentario_profesor']): ?>
                                <button onclick="verComentarios(<?php echo $entrega['id_entrega']; ?>)" class="btn btn-secondary btn-sm">
                                    Ver Comentarios
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>No hay entregas para esta tarea aun.</p>
<?php endif; ?>

<!-- SECCIÓN DE ALUMNOS SIN ENTREGA -->
<?php if (count($alumnos_sin_entrega) > 0): ?>
    <h3 style="margin-top: 40px; color: #dc3545;">Alumnos Sin Entrega</h3>
    <div class="alert alert-warning">
        <strong>Información:</strong> Los siguientes alumnos no han entregado la tarea. Puedes calificarlos con 0, el sistem alo hace automaticamente al generar calificaciones de la unidad </div>
    
    <div class="tabla-entregas">
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Número Control</th>
                    <th>Estado</th>
                    <th>Calificacion</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($alumnos_sin_entrega as $alumno): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($alumno['alumno_nombre'] . ' ' . $alumno['alumno_apellidos']); ?></td>
                        <td><?php echo $alumno['numero_control']; ?></td>
                        <td>
                            <span class="estado-pendiente" style="background-color: #dc3545; color: white;">Sin entregar</span>
                        </td>
                        <td>
                            <span class="estado-pendiente">No calificado</span>
                        </td>
                        <td>
                        
                            <button onclick="asignarCero(<?php echo $tarea_seleccionada['id_tarea']; ?>, <?php echo $alumno['id_alumno']; ?>, '<?php echo htmlspecialchars($alumno['alumno_nombre'] . ' ' . $alumno['alumno_apellidos']); ?>')" class="btn btn-danger btn-sm">
                                Asignar 0
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
    <?php else: ?>
        <!-- VISTA NORMAL DE TAREAS -->
        <h2><?php echo $rol == 1 ? 'Mis Tareas' : 'Tareas de la Clase'; ?></h2>
        
        <!-- FORMULARIO CREAR TAREA/AVISO (OCULTO INICIALMENTE) -->
        <?php if ($rol == 2): ?>
            <!-- FORMULARIO CREAR TAREA/AVISO CORREGIDO -->
            <div id="formulario-tarea" class="tarjeta-crear-tarea" style="display: none; position: relative;">
                <button class="btn-cerrar-form" onclick="ocultarFormularioTarea()">X</button>
                <h3>Crear Nueva Tarea o Aviso</h3>
                <form action="crear_tarea.php" method="POST" enctype="multipart/form-data" id="formTareaAviso">
                    <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                    
                    <!-- SWITCH TAREA/AVISO -->
                    <div class="form-group">
                        <label>Tipo *</label>
                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="tipo" value="tarea" id="radioTarea" checked onchange="toggleTipoFormulario('tarea')">
                                <span style="margin-left: 8px;">Tarea</span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="tipo" value="aviso" id="radioAviso" onchange="toggleTipoFormulario('aviso')">
                                <span style="margin-left: 8px;">Aviso</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="titulo">Título *</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="unidad">Unidad *</label>
                        <select id="unidad" name="unidad" class="form-control" required>
                            <option value="">Seleccionar unidad</option>
                            <?php for ($i = 1; $i <= $clase_info['unidades']; $i++): ?>
                                <option value="<?php echo $i; ?>">Unidad <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <!-- CAMPOS PARA TAREA (VISIBLES POR DEFECTO) -->
                    <div id="campos-tarea">
                        <div class="form-group">
                            <label for="fecha_limite">Fecha límite *</label>
                            <input type="datetime-local" id="fecha_limite" name="fecha_limite" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="puntos_maximos">Puntos máximos *</label>
                            <input type="number" id="puntos_maximos" name="puntos_maximos" class="form-control" value="100" step="0.01" min="1" required>
                        </div>
                    </div>
                    
                    <!-- CAMPOS PARA AVISO (OCULTOS POR DEFECTO) -->
                    <div id="campos-aviso" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Información:</strong> Los avisos no son entregables y no se mostrará fecha de entrega a los alumnos.
                        </div>
                        <!-- Inputs ocultos que se llenarán automáticamente -->
                        <input type="hidden" id="fecha_aviso" name="fecha_limite">
                        <input type="hidden" id="puntos_aviso" name="puntos_maximos" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="archivo_profesor">Archivo adjunto (opcional)</label>
                        <input type="file" id="archivo_profesor" name="archivo_profesor" class="form-control">
                    </div>
                    
                    <div class="acciones-tarea">
                        <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                            Crear Tarea
                        </button>
                        <button type="button" onclick="ocultarFormularioTarea()" class="btn btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if (count($tareas) > 0): ?>
            <?php for ($unidad = 1; $unidad <= $clase_info['unidades']; $unidad++): ?>
                <?php if (isset($tareas_por_unidad[$unidad]) && count($tareas_por_unidad[$unidad]) > 0): ?>
                    <!-- SECCIÓN POR UNIDAD -->
                    <div class="seccion-unidad" style="margin-bottom: 30px;">
                        <div class="header-unidad" style="
                            background: linear-gradient(135deg, #1565c0, #1976d2);
                            color: white;
                            padding: 15px 25px;
                            border-radius: var(--radio-borde);
                            margin-bottom: 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            box-shadow: var(--sombra-suave);
                        ">
                            <h3 style="margin: 0; font-size: 1.3em;">
                                Unidad <?php echo $unidad; ?>
                            </h3>
                            <span class="contador-tareas" style="
                                background: rgba(255,255,255,0.2);
                                padding: 5px 12px;
                                border-radius: 15px;
                                font-size: 0.9em;
                            ">
                                <?php echo count($tareas_por_unidad[$unidad]); ?> tarea(s)
                            </span>
                        </div>
                        
                        <div class="grid-tareas">
                            <?php foreach($tareas_por_unidad[$unidad] as $tarea): 
                                $fecha_limite = strtotime($tarea['fecha_limite']);
                                $ahora = time();
                                $esta_vencida = $fecha_limite < $ahora && $tarea['estado'] == 'activa' && $tarea['puntos_maximos'] > 0;
                                
                                // Determinar clase CSS según el estado
                                $clase_tarjeta = 'tarjeta-tarea';
                                $badge_estado = '';
                                
                                if ($tarea['puntos_maximos'] == 0) {
                                    $clase_tarjeta .= ' tarjeta-aviso';
                                    $badge_estado = '<span class="badge-aviso">AVISO</span>';
                                } elseif ($tarea['estado'] == 'cerrada') {
                                    $clase_tarjeta .= ' tarea-cerrada';
                                    $badge_estado = '<span class="badge-cerrada">CERRADA</span>';
                                } elseif ($tarea['estado'] == 'cancelada') {
                                    $clase_tarjeta .= ' tarea-cancelada';
                                    $badge_estado = '<span class="badge-cancelada">CANCELADA</span>';
                                } elseif ($esta_vencida) {
                                    $clase_tarjeta .= ' tarea-vencida';
                                    $badge_estado = '<span class="badge-vencida">VENCIDA</span>';
                                }
                            ?>
                                <div class="<?php echo $clase_tarjeta; ?>">
                                    <div class="tarjeta-tarea-header">
                                        <h3>
                                            <?php echo htmlspecialchars($tarea['titulo']); ?>
                                            <?php echo $badge_estado; ?>
                                        </h3>
                                        <div class="tarea-stats">
                                            <?php if ($rol == 2 || $rol==3): ?>
                                                <?php if ($tarea['puntos_maximos'] > 0): ?>
                                                    <div class="stat"><?php echo $tarea['total_entregas']; ?> entregas</div>
                                                    <div class="stat"><?php echo $tarea['total_calificadas']; ?> calificadas</div>
                                                <?php else: ?>
                                                    <div class="stat" style="background: #e3f2fd;">Aviso</div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($tarea['puntos_maximos'] > 0): ?>
                                        <!-- MOSTRAR FECHA LÍMITE SOLO PARA TAREAS -->
                                        <div class="tarea-fecha">
                                            <strong>Fecha límite:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?>
                                            <?php if ($esta_vencida && $rol == 1): ?>
                                                <span style="color: #dc3545; font-weight: bold;"> - ¡VENCIDA!</span>
                                            <?php endif; ?>
                                            | <strong>Puntos:</strong> <?php echo $tarea['puntos_maximos']; ?>
                                            | <strong>Unidad:</strong> <?php echo $tarea['unidad']; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- NO MOSTRAR FECHA PARA AVISOS -->
                                        <div class="tarea-fecha">
                                            <strong>Publicado:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?>
                                            | <strong>Unidad:</strong> <?php echo $tarea['unidad']; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($tarea['descripcion']): ?>
                                        <div class="tarea-descripcion">
                                            <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($tarea['archivo_profesor']): ?>
                                        <div class="archivo-profesor" style="margin-top: 10px;">
                                            <strong>Archivo adjunto:</strong>
                                            <a href="uploads/tareas/profesor/<?php echo $tarea['archivo_profesor']; ?>" 
                                               download 
                                               class="btn btn-outline-primary btn-sm" 
                                               style="margin-left: 10px; text-decoration: none;">
                                                Descargar Archivo
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="acciones-tarea">
                                        <?php if ($rol == 2): ?>
                                            <!-- BOTONES PROFESOR -->
                                            <?php if ($tarea['puntos_maximos'] > 0): ?>
                                                <a href="detalle_clase.php?id=<?php echo $id_clase; ?>&tarea_id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                                                    Ver Entregas
                                                </a>
                                                <button onclick="gestionarTarea(<?php echo $tarea['id_tarea']; ?>)" class="btn btn-gestion">
                                                    Gestionar
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary" disabled>Aviso - No entregable</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- BOTONES ALUMNO -->
                                            <?php if ($tarea['puntos_maximos'] > 0 && $tarea['estado'] != 'cancelada'): ?>
                                                <!-- MOSTRAR BOTONES DE ENTREGA SOLO PARA TAREAS NO CANCELADAS -->
                                                <?php 
                                                    $mi_entrega = null;
                                                    foreach($mis_entregas as $entrega) {
                                                        if ($entrega['id_tarea'] == $tarea['id_tarea']) {
                                                            $mi_entrega = $entrega;
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                
                                                <?php if ($mi_entrega): ?>
                                                    <div style="width: 100%;">
                                                        <div class="archivo-subido">
                                                            <strong>Ya entregaste esta tarea</strong><br>
                                                            <small>Entregado: <?php echo date('d/m/Y H:i', strtotime($mi_entrega['fecha_entrega'])); ?></small>
                                                            <?php if ($mi_entrega['calificacion'] !== null): ?>
                                                                <br><strong>Calificación: <?php echo $mi_entrega['calificacion']; ?>/<?php echo $tarea['puntos_maximos']; ?></strong>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- COMENTARIOS VISIBLES PARA ALUMNO -->
                                                        <?php if ($mi_entrega['comentario_alumno'] || $mi_entrega['comentario_profesor']): ?>
                                                            <div class="comentario-section">
                                                                <?php if ($mi_entrega['comentario_alumno']): ?>
                                                                    <div class="comentario-alumno">
                                                                        <div class="comentario-label">Tu comentario:</div>
                                                                        <div class="comentario-texto"><?php echo nl2br(htmlspecialchars($mi_entrega['comentario_alumno'])); ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($mi_entrega['comentario_profesor']): ?>
                                                                    <div class="comentario-profesor" style="margin-top: 10px;">
                                                                        <div class="comentario-label">Comentario del profesor:</div>
                                                                        <div class="comentario-texto"><?php echo nl2br(htmlspecialchars($mi_entrega['comentario_profesor'])); ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- BOTÓN REENVIAR (solo si la tarea no está cerrada) -->
                                                        <?php if ($tarea['estado'] == 'activa' && !$esta_vencida): ?>
                                                            <button onclick="mostrarModalReenvio(<?php echo $tarea['id_tarea']; ?>, <?php echo $mi_entrega['id_entrega']; ?>)" class="btn btn-warning" style="margin-top: 10px;">
                                                                Reenviar Tarea
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- MOSTRAR BOTÓN DE ENTREGA SOLO SI LA TAREA ESTÁ ACTIVA Y NO ESTÁ VENCIDA -->
                                                    <?php if ($tarea['estado'] == 'activa' && !$esta_vencida): ?>
                                                        <button onclick="mostrarModalEntrega(<?php echo $tarea['id_tarea']; ?>)" class="btn btn-primary">
                                                            Entregar Tarea
                                                        </button>
                                                    <?php elseif ($esta_vencida): ?>
                                                        <button class="btn btn-danger" disabled>Tarea Vencida</button>
                                                    <?php elseif ($tarea['estado'] == 'cerrada'): ?>
                                                        <button class="btn btn-secondary" disabled>Tarea Cerrada</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- PARA AVISOS, NO MOSTRAR BOTONES DE ENTREGA -->
                                                <button class="btn btn-outline-secondary" disabled>Aviso - No requiere entrega</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No hay tareas asignadas</h3>
                <p><?php echo $rol == 1 ? 'Aun no hay tareas para esta clase.' : 'Crea la primera tarea para esta clase.'; ?></p>
                <?php if ($rol == 2): ?>
                    <button onclick="mostrarFormularioTarea()" class="btn btn-primary" style="margin-top: 15px;">
                        Crear Primera Tarea
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<!-- SECCIÓN DE LISTA (SOLO PROFESOR) -->
<?php if ($rol == 2 || $rol==3): ?>
<div id="seccion-lista" class="seccion-oculta">
    <div class="tabla-asistencia-container">
        <div class="controles-lista">
            <h2>Pase de Lista - <?php echo $clase_info['materia_nombre']; ?></h2>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select id="selectorUnidad" class="selector-unidad" onchange="cambiarUnidad(this.value)">
                    <option value="todas">Todas las Unidades</option>
                    <?php for ($i = 1; $i <= $clase_info['unidades']; $i++): ?>
                        <option value="<?php echo $i; ?>">Unidad <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="button" class="btn btn-primary" onclick="iniciarPaseLista()" id="btnPasarLista">
                    📝Pasar Lista
                </button>
                <button type="button" class="btn btn-secondary" onclick="alternarVistaMovil()" id="btnVistaMovil">
                    Vista Móvil
                </button>
            </div>
        </div>
        
        <!-- INDICADOR MÓVIL -->
        <div class="indicador-movil" style="display: none; background: #e3f2fd; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center;">
            <p style="margin: 0; font-weight: 600; color: #1565c0;">Modo Pase de Lista Activo</p>
            <p style="margin: 5px 0 0 0; font-size: 0.9em;">Usa los botones o atajos de teclado para marcar asistencia</p>
        </div>
        
        <form id="formListaAsistencia" action="guardar_lista.php" method="POST">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            <input type="hidden" name="fecha_hoy" value="<?php echo $fecha_hoy; ?>">
            <input type="hidden" name="unidad" id="inputUnidad" value="todas">
            
            <!-- CONTENEDOR ÚNICO PARA TODOS LOS INPUTS -->
            <div id="contenedor-inputs-asistencia">
                <?php foreach ($alumnos as $alumno): 
                    $estado = $alumno['asistencias'][$fecha_hoy] ?? null;
                    $valor_input = '';
                    
                    if ($estado) {
                        switch ($estado) {
                            case 'presente': $valor_input = '1'; break;
                            case 'ausente': $valor_input = '0'; break;
                            case 'justificado': $valor_input = '2'; break;
                        }
                    }
                ?>
                <input type="hidden" 
                       name="asistencia[<?php echo $alumno['id_alumno']; ?>]" 
                       id="asistencia_<?php echo $alumno['id_alumno']; ?>" 
                       value="<?php echo $valor_input; ?>">
                <?php endforeach; ?>
            </div>
            
            <!-- VISTA MÓVIL COMPACTA -->
            <div class="vista-movil-asistencia" id="vistaMovilAsistencia" style="display: none;">
                <?php foreach ($alumnos as $index => $alumno): 
                    $estado = $alumno['asistencias'][$fecha_hoy] ?? null;
                    $clase_tarjeta = '';
                    
                    if ($estado) {
                        switch ($estado) {
                            case 'presente': $clase_tarjeta = 'presente'; break;
                            case 'ausente': $clase_tarjeta = 'ausente'; break;
                            case 'justificado': $clase_tarjeta = 'justificado'; break;
                        }
                    }
                ?>
                <div class="tarjeta-alumno-movil <?php echo $clase_tarjeta; ?>" data-alumno-id="<?php echo $alumno['id_alumno']; ?>">
                    <div class="info-alumno-movil">
                        <div class="alumno-clave"><?php echo $alumno['clave']; ?></div>
                        <div class="alumno-nombre"><?php echo $alumno['nombre_completo']; ?></div>
                    </div>
                    <div class="controles-movil">
                        <button type="button" class="btn-estado-movil <?php echo $estado == 'presente' ? 'presente activo' : ''; ?>" 
                                onclick="marcarAsistenciaMovil(<?php echo $alumno['id_alumno']; ?>, 'presente', this)">
                            <span class="icono-estado">✓</span>
                            <span class="texto-estado">Presente</span>
                        </button>
                        <button type="button" class="btn-estado-movil <?php echo $estado == 'ausente' ? 'ausente activo' : ''; ?>" 
                                onclick="marcarAsistenciaMovil(<?php echo $alumno['id_alumno']; ?>, 'ausente', this)">
                            <span class="icono-estado">✗</span>
                            <span class="texto-estado">Ausente</span>
                        </button>
                        <button type="button" class="btn-estado-movil <?php echo $estado == 'justificado' ? 'justificado activo' : ''; ?>" 
                                onclick="marcarAsistenciaMovil(<?php echo $alumno['id_alumno']; ?>, 'justificado', this)">
                            <span class="icono-estado">Ⓙ</span>
                            <span class="texto-estado">Justif.</span>
                        </button>
                    </div>
                    
                    <div class="estado-actual-movil" style="margin-top: 10px; text-align: center; font-size: 0.8em; color: #666;">
                        <?php if ($estado): ?>
                            Estado: <strong><?php echo ucfirst($estado); ?></strong>
                        <?php else: ?>
                            <em>Sin marcar</em>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- VISTA TABLA NORMAL -->
            <div id="vistaTablaNormal">
                <p>Haz clic en las casillas de hoy para marcar la asistencia</p>
                
                <table class="tabla-asistencia">
                    <thead>
                        <tr>
                            <th class="columna-clave">Clave</th>
                            <th class="columna-nombre">Alumno</th>
                            <?php
                            foreach ($fechas as $fecha) {
                                $es_hoy = $fecha == $fecha_hoy;
                                $clase_fecha = $es_hoy ? 'fecha-header fecha-hoy' : 'fecha-header';
                                $formato_fecha = date('d/m', strtotime($fecha));
                                echo "<th class='$clase_fecha' title='$fecha'>$formato_fecha</th>";
                            }
                            ?>
                            <th class="porcentaje-header">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($alumnos as $alumno):
                            $porcentaje = calcularPorcentajeAsistencia($alumno['asistencias']);
                        ?>
                        <tr>
                            <td class="columna-clave" style="font-weight: 600;"><?php echo $alumno['clave']; ?></td>
                            <td class="columna-nombre"><?php echo $alumno['nombre_completo']; ?></td>
                            
                            <?php foreach ($fechas as $fecha): 
                                $es_hoy = $fecha == $fecha_hoy;
                                $estado = $alumno['asistencias'][$fecha] ?? null;
                                $clase_celda = '';
                                $valor_mostrar = '';
                                
                                if ($estado) {
                                    switch ($estado) {
                                        case 'presente': $clase_celda = 'presente'; $valor_mostrar = '1'; break;
                                        case 'ausente': $clase_celda = 'ausente'; $valor_mostrar = '0'; break;
                                        case 'justificado': $clase_celda = 'justificado'; $valor_mostrar = '2'; break;
                                    }
                                }
                            ?>
                            
                            <td>
                                <?php if ($es_hoy): ?>
                                    <!-- Celda editable para hoy -->
                                    <div class="selector-estado celda-editable <?php echo $clase_celda; echo !$estado ? ' vacio' : ''; ?>"
                                         onclick="mostrarModalAsistencia(<?php echo $alumno['id_alumno']; ?>, '<?php echo $alumno['nombre_completo']; ?>', '<?php echo $alumno['clave']; ?>')"
                                         id="selector_<?php echo $alumno['id_alumno']; ?>">
                                        <?php echo $valor_mostrar ?: '?'; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Celda de días anteriores (solo lectura) -->
                                    <?php if ($estado): ?>
                                        <div class="celda-asistencia <?php echo $clase_celda; ?>">
                                            <?php echo $valor_mostrar; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 22px; height: 22px; background: #f8f9fa; border-radius: 3px; margin: 0 auto;"></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            
                            <?php endforeach; ?>
                            
                            <td>
                                <div class="porcentaje-asistencia <?php 
                                    echo $porcentaje >= 80 ? 'porcentaje-alto' : 
                                         ($porcentaje >= 60 ? 'porcentaje-medio' : 'porcentaje-bajo'); 
                                ?>">
                                    <?php echo $porcentaje; ?>%
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" class="btn-guardar-lista" id="btnGuardarLista">
                    Guardar Lista de Hoy
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</main>

<!-- BOTÓN FLOTANTE PARA CREAR TAREA (SOLO PROFESOR) -->
<?php if ($rol == 2): ?>
    <button class="btn-flotante" onclick="mostrarFormularioTarea()" title="Crear Nueva Tarea">
        +
    </button>
<?php endif; ?>

<!-- MODAL PARA ENTREGAR TAREA (ALUMNO) -->
<div id="modalEntrega" class="modal">
    <div class="modal-content">
        <h2>Entregar Tarea</h2>
        <form id="formEntrega" action="entregar_tarea.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="tarea_id" name="tarea_id">
            
            <div class="form-group">
                <label for="comentario_alumno">Comentario (opcional)</label>
                <textarea id="comentario_alumno" name="comentario_alumno" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="archivo_alumno">Archivo de la tarea *</label>
                <input type="file" id="archivo_alumno" name="archivo_alumno" class="form-control" required accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png">
                <small>Formatos permitidos: PDF, Word, TXT, ZIP, RAR, JPG, PNG (Máx. 50MB)</small>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-primary">Entregar Tarea</button>
                <button type="button" onclick="cerrarModalEntrega()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA REENVIAR TAREA (ALUMNO) -->
<div id="modalReenvio" class="modal">
    <div class="modal-content">
        <h2>Reenviar Tarea</h2>
        <p><strong>Nota:</strong> Al reenviar, se sobreescribirá tu entrega anterior.</p>
        <form id="formReenvio" action="reenviar_tarea.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="reenvio_tarea_id" name="tarea_id">
            <input type="hidden" id="reenvio_entrega_id" name="entrega_id">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            
            <div class="form-group">
                <label for="nuevo_comentario_alumno">Nuevo comentario (opcional)</label>
                <textarea id="nuevo_comentario_alumno" name="comentario_alumno" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="nuevo_archivo_alumno">Nuevo archivo de la tarea *</label>
                <input type="file" id="nuevo_archivo_alumno" name="archivo_alumno" class="form-control" required accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png">
                <small>Formatos permitidos: PDF, Word, TXT, ZIP, RAR, JPG, PNG (Máx. 50MB)</small>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-warning">Reenviar Tarea</button>
                <button type="button" onclick="cerrarModalReenvio()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA CALIFICAR (PROFESOR) -->
<div id="modalCalificar" class="modal">
    <div class="modal-content">
        <h2>Calificar Entrega</h2>
        <form id="formCalificar" action="calificar_tarea.php" method="POST">
            <input type="hidden" id="entrega_id" name="entrega_id">
            
            <div class="form-group">
                <label for="calificacion">Calificacion</label>
                <input type="number" id="calificacion" name="calificacion" class="form-control" step="0.01" min="0" required>
                <small>Puntos maximos: <span id="puntos_maximos">100</span></small>
            </div>
            
            <div class="form-group">
                <label for="comentario_profesor">Comentario para el alumno (opcional)</label>
                <textarea id="comentario_profesor" name="comentario_profesor" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-success">Guardar Calificacion</button>
                <button type="button" onclick="cerrarModalCalificar()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA CALIFICAR SIN ENTREGA (PROFESOR) -->
<div id="modalCalificarSinEntrega" class="modal">
    <div class="modal-content">
        <h2>Calificar Sin Entrega</h2>
        <p>Alumno: <strong id="nombre_alumno_sin_entrega"></strong></p>
        <form id="formCalificarSinEntrega" action="calificar_sin_entrega.php" method="POST">
            <input type="hidden" id="calificar_sin_tarea_id" name="tarea_id">
            <input type="hidden" id="calificar_sin_alumno_id" name="alumno_id">
            
            <div class="form-group">
                <label for="calificacion_sin_entrega">Calificacion</label>
                <input type="number" id="calificacion_sin_entrega" name="calificacion" class="form-control" step="0.01" min="0" required>
                <small>Puntos maximos: <span id="puntos_maximos_sin_entrega">100</span></small>
            </div>
            
            <div class="form-group">
                <label for="comentario_profesor_sin_entrega">Comentario para el alumno (opcional)</label>
                <textarea id="comentario_profesor_sin_entrega" name="comentario_profesor" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-success">Guardar Calificacion</button>
                <button type="button" onclick="cerrarModalCalificarSinEntrega()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA GESTIONAR TAREA (PROFESOR) -->
<div id="modalGestionTarea" class="modal">
    <div class="modal-content">
        <h2>Gestionar Tarea</h2>
        <form id="formGestionTarea" action="gestionar_tarea.php" method="POST">
            <input type="hidden" id="gestion_tarea_id" name="tarea_id">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            
            <div class="form-group">
                <label>Acción a realizar:</label>
                <div style="display: flex; gap: 15px; margin-top: 10px; flex-direction: column;">
                    <label style="display: flex; align-items: center;">
                        <input type="radio" name="accion" value="cerrar" checked onchange="toggleCamposReactivar()"> 
                        <span style="margin-left: 8px;">Cerrar tarea (no aceptar más entregas)</span>
                    </label>
                    <label style="display: flex; align-items: center;">
                        <input type="radio" name="accion" value="cancelar" onchange="toggleCamposReactivar()"> 
                        <span style="margin-left: 8px;">Cancelar tarea (eliminar de la vista de alumnos)</span>
                    </label>
                    <label style="display: flex; align-items: center;">
                        <input type="radio" name="accion" value="reactivar" onchange="toggleCamposReactivar()"> 
                        <span style="margin-left: 8px;">Reactivar tarea (volver a activa)</span>
                    </label>
                </div>
            </div>
            
            <!-- Campos adicionales para reactivar -->
            <div id="campos-reactivar" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">
                <h4 style="margin-top: 0;">Extender plazo de entrega</h4>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div>
                        <label for="dias_adicionales">Días adicionales:</label>
                        <input type="number" id="dias_adicionales" name="dias_adicionales" min="0" max="30" value="0" class="form-control" style="width: 80px;">
                    </div>
                    <div>
                        <label for="horas_adicionales">Horas adicionales:</label>
                        <input type="number" id="horas_adicionales" name="horas_adicionales" min="0" max="23" value="0" class="form-control" style="width: 80px;">
                    </div>
                </div>
                <small style="color: #666;">Selecciona cuánto tiempo adicional tendrán los alumnos para entregar.</small>
            </div>
            
            <div class="form-group">
                <label for="motivo_gestion">Motivo (opcional):</label>
                <textarea id="motivo_gestion" name="motivo" class="form-control" rows="3" placeholder="Explique el motivo del cambio..."></textarea>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-warning">Aplicar Cambios</button>
                <button type="button" onclick="cerrarModalGestionTarea()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA GENERAR PDF (PROFESOR) -->
<div id="modalGenerarPDF" class="modal">
    <div class="modal-content modal-pdf">
        <h2>Generar PDF de Calificaciones</h2>
        <form id="formGenerarPDF" action="generar_pdf_calificaciones.php" method="POST">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
            
            <div class="form-group">
                <label for="unidad_pdf">Seleccionar Unidad:</label>
                <select id="unidad_pdf" name="unidad" class="form-control" required onchange="cargarTareasUnidad(this.value)">
                    <option value="">Seleccionar unidad</option>
                    <?php for ($i = 1; $i <= $clase_info['unidades']; $i++): ?>
                        <option value="<?php echo $i; ?>">Unidad <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div id="contenedor-porcentajes" style="display: none;">
                <h4>Asignar Porcentajes a las Tareas:</h4>
                <p><small>Asigna el porcentaje que vale cada tarea para la calificación final. La suma total debe ser 100%.</small></p>
                <div class="form-porcentajes" id="lista-tareas-porcentajes">
                    <!-- Las tareas se cargarán aquí dinámicamente -->
                </div>
                <div id="total-porcentaje" style="text-align: center; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Total: <span id="valor-total">0</span>%</strong>
                </div>
            </div>
            
            <div class="acciones-tarea">
                <button type="submit" class="btn btn-primary" id="btnGenerarPDF" disabled>Generar PDF</button>
                <button type="button" onclick="cerrarModalGenerarPDF()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA VER COMENTARIOS -->
<div id="modalComentarios" class="modal">
    <div class="modal-content">
        <h2>Comentarios de la Entrega</h2>
        <div id="contenido-comentarios">
            <!-- Los comentarios se cargan aquí dinámicamente -->
        </div>
        <div class="acciones-tarea" style="margin-top: 20px;">
            <button type="button" onclick="cerrarModalComentarios()" class="btn btn-secondary">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL PARA EDITAR ASISTENCIA MEJORADO -->
<div id="modalAsistencia" class="modal-asistencia" tabindex="0">
    <div class="modal-contenido-asistencia">
        <h3>📝 Marcar Asistencia</h3>
        
        <div class="indicador-alumno">
            Alumno <span id="contadorAlumno">1</span> de <span id="totalAlumnos"><?php echo count($alumnos); ?></span>
        </div>
        
        <div class="info-alumno-modal">
            <h4 id="modalAlumnoNombre"></h4>
            <p>Clave: <strong id="modalAlumnoClave"></strong></p>
        </div>
        
        <p>Selecciona el estado de asistencia:</p>
        
        <div class="opciones-estado">
            <div class="opcion-estado presente" onclick="seleccionarEstado('presente')" title="Presente (Tecla 1)">1</div>
            <div class="opcion-estado ausente" onclick="seleccionarEstado('ausente')" title="Ausente (Tecla 0)">0</div>
            <div class="opcion-estado justificado" onclick="seleccionarEstado('justificado')" title="Justificado (Tecla 2)">2</div>
        </div>
        
        <div class="instrucciones-teclado">
            <strong>Atajos de teclado:</strong><br>
            <kbd>1</kbd> Presente • <kbd>0</kbd> Ausente • <kbd>2</kbd> Justificado<br>
            <kbd>Enter</kbd> Aplicar • <kbd>Esc</kbd> Cancelar
        </div>
        
        <div class="controles-navegacion">
            <button type="button" onclick="alumnoAnterior()" class="btn-navegacion" id="btnAnterior">
                ← Anterior
            </button>
            <button type="button" onclick="aplicarEstadoYSiguiente()" class="btn-navegacion btn-success">
                Aplicar y Siguiente (Enter)
            </button>
            <button type="button" onclick="siguienteAlumno()" class="btn-navegacion" id="btnSiguiente">
                Siguiente →
            </button>
        </div>
        
        <div style="margin-top: 15px;">
            <button type="button" onclick="cerrarModalAsistencia()" class="btn btn-secondary">
                Cancelar (Esc)
            </button>
        </div>
    </div>
</div>

<script>
// Variables globales para el modal de asistencia
let alumnoActualId = null;
let estadoSeleccionado = null;
let alumnosIds = [];
let alumnoActualIndex = -1;
let modoPaseLista = false;
let vistaMovilActiva = false;

// Funciones para navegación entre pestañas
function mostrarSeccion(seccion) {
    console.log('Mostrando sección:', seccion);
    
    // Remover clase active de todas las pestañas
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Agregar clase active a la pestaña clickeada
    event.target.classList.add('active');
    
    // Ocultar todas las secciones
    document.querySelectorAll('[id^="seccion-"]').forEach(div => {
        div.classList.remove('seccion-activa');
        div.classList.add('seccion-oculta');
    });
    
    // Mostrar la sección seleccionada
    const seccionMostrar = document.getElementById('seccion-' + seccion);
    if (seccionMostrar) {
        seccionMostrar.classList.remove('seccion-oculta');
        seccionMostrar.classList.add('seccion-activa');
    }
}

// Funciones para formulario de tarea
function mostrarFormularioTarea() {
    const formulario = document.getElementById('formulario-tarea');
    if (formulario) {
        formulario.style.display = 'block';
        formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const tituloInput = document.getElementById('titulo');
        if (tituloInput) tituloInput.focus();
    }
}

function ocultarFormularioTarea() {
    const formulario = document.getElementById('formulario-tarea');
    if (formulario) {
        formulario.style.display = 'none';
    }
}

// Funciones para modal de entrega
function mostrarModalEntrega(tareaId) {
    const tareaInput = document.getElementById('tarea_id');
    const modal = document.getElementById('modalEntrega');
    if (tareaInput && modal) {
        tareaInput.value = tareaId;
        modal.style.display = 'flex';
    }
}

function cerrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    if (modal) modal.style.display = 'none';
}

// Funciones para modal de reenvío
function mostrarModalReenvio(tareaId, entregaId) {
    const tareaInput = document.getElementById('reenvio_tarea_id');
    const entregaInput = document.getElementById('reenvio_entrega_id');
    const modal = document.getElementById('modalReenvio');
    
    if (tareaInput && entregaInput && modal) {
        tareaInput.value = tareaId;
        entregaInput.value = entregaId;
        modal.style.display = 'flex';
    }
}

function cerrarModalReenvio() {
    const modal = document.getElementById('modalReenvio');
    if (modal) modal.style.display = 'none';
}

// Funciones para modal de calificación
function calificarEntrega(entregaId, puntosMaximos) {
    const entregaInput = document.getElementById('entrega_id');
    const puntosSpan = document.getElementById('puntos_maximos');
    const calificacionInput = document.getElementById('calificacion');
    const modal = document.getElementById('modalCalificar');
    
    if (entregaInput && puntosSpan && calificacionInput && modal) {
        entregaInput.value = entregaId;
        puntosSpan.textContent = puntosMaximos;
        calificacionInput.max = puntosMaximos;
        calificacionInput.value = '';
        modal.style.display = 'flex';
    }
}

function cerrarModalCalificar() {
    const modal = document.getElementById('modalCalificar');
    if (modal) modal.style.display = 'none';
}

// Funciones para calificar sin entrega
function calificarSinEntrega(tareaId, alumnoId, nombreAlumno, puntosMaximos) {
    const tareaInput = document.getElementById('calificar_sin_tarea_id');
    const alumnoInput = document.getElementById('calificar_sin_alumno_id');
    const nombreSpan = document.getElementById('nombre_alumno_sin_entrega');
    const puntosSpan = document.getElementById('puntos_maximos_sin_entrega');
    const calificacionInput = document.getElementById('calificacion_sin_entrega');
    const modal = document.getElementById('modalCalificarSinEntrega');
    
    if (tareaInput && alumnoInput && nombreSpan && puntosSpan && calificacionInput && modal) {
        tareaInput.value = tareaId;
        alumnoInput.value = alumnoId;
        nombreSpan.textContent = nombreAlumno;
        puntosSpan.textContent = puntosMaximos;
        calificacionInput.max = puntosMaximos;
        calificacionInput.value = '';
        modal.style.display = 'flex';
    }
}

function cerrarModalCalificarSinEntrega() {
    const modal = document.getElementById('modalCalificarSinEntrega');
    if (modal) modal.style.display = 'none';
}

// Funciones para gestionar tarea
function gestionarTarea(tareaId) {
    const tareaInput = document.getElementById('gestion_tarea_id');
    const modal = document.getElementById('modalGestionTarea');
    
    if (tareaInput && modal) {
        tareaInput.value = tareaId;
        modal.style.display = 'flex';
    }
}

function cerrarModalGestionTarea() {
    const modal = document.getElementById('modalGestionTarea');
    if (modal) modal.style.display = 'none';
}

// Funciones para generar PDF
function mostrarModalGenerarPDF() {
    const modal = document.getElementById('modalGenerarPDF');
    if (modal) {
        modal.style.display = 'flex';
        // Resetear el formulario
        document.getElementById('unidad_pdf').value = '';
        document.getElementById('contenedor-porcentajes').style.display = 'none';
        document.getElementById('btnGenerarPDF').disabled = true;
    }
}

function cerrarModalGenerarPDF() {
    const modal = document.getElementById('modalGenerarPDF');
    if (modal) modal.style.display = 'none';
}

function cargarTareasUnidad(unidad) {
    const contenedor = document.getElementById('contenedor-porcentajes');
    const listaTareas = document.getElementById('lista-tareas-porcentajes');
    const btnGenerar = document.getElementById('btnGenerarPDF');
    
    if (!unidad) {
        contenedor.style.display = 'none';
        btnGenerar.disabled = true;
        return;
    }
    
    // Obtener las tareas del array PHP que ya está cargado
    const tareasUnidad = <?php echo json_encode($tareas_por_unidad); ?>[unidad];
    
    if (!tareasUnidad || tareasUnidad.length === 0) {
        listaTareas.innerHTML = '<div style="text-align: center; padding: 20px; color: #6c757d;">No hay tareas en esta unidad.</div>';
        contenedor.style.display = 'block';
        btnGenerar.disabled = true;
        return;
    }
    
    listaTareas.innerHTML = '';
    let totalPorcentaje = 0;
    
    // Filtrar solo tareas (excluir avisos donde puntos_maximos = 0)
    const tareasValidas = tareasUnidad.filter(tarea => tarea.puntos_maximos > 0);
    
    if (tareasValidas.length === 0) {
        listaTareas.innerHTML = '<div style="text-align: center; padding: 20px; color: #6c757d;">No hay tareas calificables en esta unidad (solo avisos).</div>';
        contenedor.style.display = 'block';
        btnGenerar.disabled = true;
        return;
    }
    
    tareasValidas.forEach(tarea => {
        if(tarea.estado != 'cancelada')
                {
        const item = document.createElement('div');
        item.className = 'porcentaje-item';
        item.innerHTML = `
            <div>
                <strong>${tarea.titulo}</strong><br>
                <small>Puntos: ${tarea.puntos_maximos}</small>
            </div>
            <div>
                <input type="number" 
                       name="porcentajes[${tarea.id_tarea}]" 
                       class="porcentaje-input" 
                       min="0" 
                       max="100" 
                       value="0" 
                       onchange="actualizarTotalPorcentaje()"
                       oninput="actualizarTotalPorcentaje()">
                <span>%</span>
            </div>
        `;
        listaTareas.appendChild(item);}
    });
    
    contenedor.style.display = 'block';
    actualizarTotalPorcentaje();
    btnGenerar.disabled = false;
}

function actualizarTotalPorcentaje() {
    const inputs = document.querySelectorAll('.porcentaje-input');
    let total = 0;
    
    inputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    document.getElementById('valor-total').textContent = total.toFixed(1);
    
    // Validar que el total sea 100%
    const btnGenerar = document.getElementById('btnGenerarPDF');
    if (total === 100) {
        btnGenerar.disabled = false;
        document.getElementById('total-porcentaje').style.background = '#d4edda';
        document.getElementById('total-porcentaje').style.color = '#155724';
    } else {
        btnGenerar.disabled = true;
        document.getElementById('total-porcentaje').style.background = '#f8d7da';
        document.getElementById('total-porcentaje').style.color = '#721c24';
    }
}

// Funciones para modal de comentarios
function verComentarios(entregaId) {
    const contenido = document.getElementById('contenido-comentarios');
    const modal = document.getElementById('modalComentarios');
    
    if (contenido && modal) {
        // Mostrar loading
        contenido.innerHTML = `
            <div class="comentario-section">
                <div class="comentario-alumno">
                    <div class="comentario-label">Comentario del alumno:</div>
                    <div class="comentario-texto">
                        <div style="text-align: center; padding: 10px;">
                            <div class="spinner"></div>
                            <p>Cargando comentarios...</p>
                        </div>
                    </div>
                </div>
                <div class="comentario-profesor" style="margin-top: 15px;">
                    <div class="comentario-label">Comentario del profesor:</div>
                    <div class="comentario-texto">
                        <div style="text-align: center; padding: 10px;">
                            <div class="spinner"></div>
                            <p>Cargando comentarios...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        modal.style.display = 'flex';
        
        // Cargar comentarios via AJAX
        fetch(`obtener_comentarios.php?entrega_id=${entregaId}`)
            .then(response => response.json())
            .then(data => {
                let comentarioAlumno = data.comentario_alumno || 'No hay comentario del alumno.';
                let comentarioProfesor = data.comentario_profesor || 'No hay comentario del profesor.';
                
                contenido.innerHTML = `
                    <div class="comentario-section">
                        <div class="comentario-alumno">
                            <div class="comentario-label">Comentario del alumno:</div>
                            <div class="comentario-texto">${comentarioAlumno}</div>
                        </div>
                        <div class="comentario-profesor" style="margin-top: 15px;">
                            <div class="comentario-label">Comentario del profesor:</div>
                            <div class="comentario-texto">${comentarioProfesor}</div>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Error al cargar comentarios:', error);
                contenido.innerHTML = `
                    <div class="comentario-section">
                        <div class="comentario-alumno">
                            <div class="comentario-label">Comentario del alumno:</div>
                            <div class="comentario-texto" style="color: #dc3545;">Error al cargar los comentarios.</div>
                        </div>
                        <div class="comentario-profesor" style="margin-top: 15px;">
                            <div class="comentario-label">Comentario del profesor:</div>
                            <div class="comentario-texto" style="color: #dc3545;">Error al cargar los comentarios.</div>
                        </div>
                    </div>
                `;
            });
    }
}

function cerrarModalComentarios() {
    const modal = document.getElementById('modalComentarios');
    if (modal) modal.style.display = 'none';
}

// FUNCIONES MEJORADAS PARA EL PASE DE LISTA
function inicializarListaAlumnos() {
    // Obtener todos los IDs de alumnos de la tabla
    alumnosIds = [];
    const filas = document.querySelectorAll('.tabla-asistencia tbody tr');
    filas.forEach(fila => {
        const selector = fila.querySelector('.selector-estado');
        if (selector) {
            const id = selector.id.replace('selector_', '');
            alumnosIds.push(id);
        }
    });
    console.log('Alumnos inicializados:', alumnosIds);
}

function iniciarPaseLista() {
    modoPaseLista = true;
    document.querySelector('.indicador-movil').style.display = 'block';
    
    // Si estamos en vista móvil, empezar desde el primer alumno
    if (vistaMovilActiva) {
        const primerAlumno = document.querySelector('.tarjeta-alumno-movil');
        if (primerAlumno) {
            primerAlumno.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } else {
        // En vista normal, abrir modal con el primer alumno
        if (alumnosIds.length > 0) {
            const primerId = alumnosIds[0];
            const fila = document.querySelector(`#selector_${primerId}`).closest('tr');
            const nombre = fila.querySelector('.columna-nombre').textContent;
            const clave = fila.querySelector('.columna-clave').textContent;
            mostrarModalAsistencia(primerId, nombre, clave);
        }
    }
}

function alternarVistaMovil() {
    vistaMovilActiva = !vistaMovilActiva;
    const vistaMovil = document.getElementById('vistaMovilAsistencia');
    const vistaNormal = document.getElementById('vistaTablaNormal');
    const btnVista = document.getElementById('btnVistaMovil');
    
    if (vistaMovilActiva) {
        vistaMovil.style.display = 'block';
        vistaNormal.style.display = 'none';
        btnVista.textContent = '🖥️ Vista Normal';
        btnVista.classList.add('btn-primary');
        btnVista.classList.remove('btn-secondary');
    } else {
        vistaMovil.style.display = 'none';
        vistaNormal.style.display = 'block';
        btnVista.textContent = '📱 Vista Móvil';
        btnVista.classList.remove('btn-primary');
        btnVista.classList.add('btn-secondary');
    }
}

// FUNCIÓN CORREGIDA - Esta es la principal corrección
function sincronizarInputsAsistencia() {
    console.log('Sincronizando todos los inputs de asistencia...');
    
    // Sincronizar desde vista móvil a inputs
    if (vistaMovilActiva) {
        const tarjetas = document.querySelectorAll('.tarjeta-alumno-movil');
        tarjetas.forEach(tarjeta => {
            const alumnoId = tarjeta.getAttribute('data-alumno-id');
            const botonActivo = tarjeta.querySelector('.btn-estado-movil.activo');
            const inputHidden = document.getElementById('asistencia_' + alumnoId);
            
            if (botonActivo && inputHidden) {
                let nuevoValor = '';
                if (botonActivo.classList.contains('presente')) nuevoValor = '1';
                else if (botonActivo.classList.contains('ausente')) nuevoValor = '0';
                else if (botonActivo.classList.contains('justificado')) nuevoValor = '2';
                
                if (nuevoValor !== '' && inputHidden.value !== nuevoValor) {
                    console.log(`🔄 Sincronizando móvil: ${alumnoId} = ${nuevoValor}`);
                    inputHidden.value = nuevoValor;
                }
            }
        });
    }
    
    // Sincronizar desde vista PC a inputs
    const selectoresPC = document.querySelectorAll('.selector-estado.celda-editable');
    selectoresPC.forEach(selector => {
        const alumnoId = selector.id.replace('selector_', '');
        const inputHidden = document.getElementById('asistencia_' + alumnoId);
        
        if (selector && inputHidden) {
            let nuevoValor = '';
            if (selector.classList.contains('presente')) nuevoValor = '1';
            else if (selector.classList.contains('ausente')) nuevoValor = '0';
            else if (selector.classList.contains('justificado')) nuevoValor = '2';
            
            if (nuevoValor !== '' && inputHidden.value !== nuevoValor) {
                console.log(`🔄 Sincronizando PC: ${alumnoId} = ${nuevoValor}`);
                inputHidden.value = nuevoValor;
            }
        }
    });
}

// Función corregida para marcar asistencia en móvil
function marcarAsistenciaMovil(alumnoId, estado, botonClickeado) {
    console.log('Marcando asistencia móvil:', alumnoId, estado);
    
    const inputHidden = document.getElementById('asistencia_' + alumnoId);
    const tarjeta = document.querySelector(`.tarjeta-alumno-movil[data-alumno-id="${alumnoId}"]`);
    const botones = tarjeta.querySelectorAll('.btn-estado-movil');
    const indicadorEstado = tarjeta.querySelector('.estado-actual-movil');
    
    if (!inputHidden) {
        console.error('No se encontró el input hidden para el alumno:', alumnoId);
        return;
    }

    // Actualizar valores
    let valor, clase, textoEstado;
    switch(estado) {
        case 'presente':
            valor = '1';
            clase = 'presente';
            textoEstado = 'Presente';
            break;
        case 'ausente':
            valor = '0';
            clase = 'ausente';
            textoEstado = 'Ausente';
            break;
        case 'justificado':
            valor = '2';
            clase = 'justificado';
            textoEstado = 'Justificado';
            break;
    }
    
    // ACTUALIZACIÓN DIRECTA - sin verificar valores anteriores
    inputHidden.value = valor;
    console.log('Input hidden actualizado:', inputHidden.name, '=', inputHidden.value);
    
    // Actualizar UI
    tarjeta.className = 'tarjeta-alumno-movil ' + clase;
    
    // Remover clases activas de todos los botones
    botones.forEach(btn => {
        btn.classList.remove('presente', 'ausente', 'justificado', 'activo');
    });
    
    // Agregar clase activa al botón clickeado
    botonClickeado.classList.add('activo', clase);
    
    // Actualizar indicador de estado
    if (indicadorEstado) {
        indicadorEstado.innerHTML = `Estado: <strong>${textoEstado}</strong>`;
    }
    
    // También actualizar la vista PC si está visible
    const selectorPC = document.getElementById('selector_' + alumnoId);
    if (selectorPC) {
        selectorPC.textContent = estado === 'presente' ? '1' : (estado === 'ausente' ? '0' : '2');
        selectorPC.className = 'selector-estado celda-editable ' + clase;
    }
    
    mostrarFeedbackMovil(tarjeta, '✓ Actualizado');
    
    if (modoPaseLista) {
        setTimeout(() => {
            const siguienteTarjeta = tarjeta.nextElementSibling;
            if (siguienteTarjeta && siguienteTarjeta.classList.contains('tarjeta-alumno-movil')) {
                siguienteTarjeta.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center',
                    inline: 'nearest'
                });
                
                siguienteTarjeta.style.boxShadow = '0 0 0 3px #2196f3';
                setTimeout(() => {
                    siguienteTarjeta.style.boxShadow = '';
                }, 1000);
            } else {
                mostrarFeedbackMovil(tarjeta, '🎉 ¡Lista completada!');
            }
        }, 800);
    }
    
    setTimeout(recalcularPorcentajes, 100);
}

function asignarCero(id_tarea, id_alumno, nombre_alumno) {
    if (confirm(`¿Estás seguro de asignar 0 puntos a ${nombre_alumno}?`)) {
        // Crear un formulario dinámico para enviar la calificación 0
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'calificar_sin_entrega.php';
        
        const inputTarea = document.createElement('input');
        inputTarea.type = 'hidden';
        inputTarea.name = 'id_tarea';
        inputTarea.value = id_tarea;
        
        const inputAlumno = document.createElement('input');
        inputAlumno.type = 'hidden';
        inputAlumno.name = 'id_alumno';
        inputAlumno.value = id_alumno;
        
        const inputCalificacion = document.createElement('input');
        inputCalificacion.type = 'hidden';
        inputCalificacion.name = 'calificacion';
        inputCalificacion.value = '0';
        
        const inputComentario = document.createElement('input');
        inputComentario.type = 'hidden';
        inputComentario.name = 'comentario_profesor';
        inputComentario.value = 'Calificación automática: 0 por no entregar';
        
        form.appendChild(inputTarea);
        form.appendChild(inputAlumno);
        form.appendChild(inputCalificacion);
        form.appendChild(inputComentario);
        
        document.body.appendChild(form);
        form.submit();
    }
}
function mostrarFeedbackMovil(elemento, mensaje) {
    const feedback = document.createElement('div');
    feedback.className = 'feedback-movil';
    feedback.textContent = mensaje;
    feedback.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        background: #4caf50;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
        z-index: 10;
        animation: fadeInOut 2s ease-in-out;
    `;
    
    elemento.style.position = 'relative';
    elemento.appendChild(feedback);
    
    setTimeout(() => {
        if (feedback.parentElement) {
            feedback.remove();
        }
    }, 2000);
}
function toggleCamposReactivar() {
    const camposReactivar = document.getElementById('campos-reactivar');
    const accionSeleccionada = document.querySelector('input[name="accion"]:checked').value;
    
    if (accionSeleccionada === 'reactivar') {
        camposReactivar.style.display = 'block';
    } else {
        camposReactivar.style.display = 'none';
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    toggleCamposReactivar();
});
function mostrarModalAsistencia(alumnoId, nombreAlumno, claveAlumno) {
    alumnoActualId = alumnoId;
    estadoSeleccionado = null;
    
    // Encontrar el índice del alumno actual
    alumnoActualIndex = alumnosIds.indexOf(alumnoId.toString());
    if (alumnoActualIndex === -1) {
        console.error('Alumno no encontrado en la lista:', alumnoId);
        return;
    }
    
    // Actualizar información del alumno en el modal
    document.getElementById('modalAlumnoNombre').textContent = nombreAlumno;
    document.getElementById('modalAlumnoClave').textContent = claveAlumno;
    document.getElementById('contadorAlumno').textContent = alumnoActualIndex + 1;
    document.getElementById('totalAlumnos').textContent = alumnosIds.length;
    
    // Actualizar estado de botones de navegación
    document.getElementById('btnAnterior').disabled = alumnoActualIndex === 0;
    document.getElementById('btnSiguiente').disabled = alumnoActualIndex === alumnosIds.length - 1;
    
    // Obtener estado actual para preseleccionar
    const inputHidden = document.getElementById('asistencia_' + alumnoId);
    const selector = document.getElementById('selector_' + alumnoId);
    let estadoActual = null;
    
    if (selector.classList.contains('presente')) estadoActual = 'presente';
    else if (selector.classList.contains('ausente')) estadoActual = 'ausente';
    else if (selector.classList.contains('justificado')) estadoActual = 'justificado';
    
    // Resetear y preseleccionar si existe estado
    document.querySelectorAll('.opcion-estado').forEach(opcion => {
        opcion.classList.remove('seleccionada');
        if (estadoActual && opcion.classList.contains(estadoActual)) {
            opcion.classList.add('seleccionada');
            estadoSeleccionado = estadoActual;
        }
    });
    
    // Mostrar modal
    document.getElementById('modalAsistencia').style.display = 'flex';
    
    // Enfocar el modal para capturar teclas
    setTimeout(() => {
        document.getElementById('modalAsistencia').focus();
    }, 100);
}

function seleccionarEstado(estado) {
    estadoSeleccionado = estado;
    
    // Actualizar UI
    document.querySelectorAll('.opcion-estado').forEach(opcion => {
        opcion.classList.remove('seleccionada');
    });
    event.target.classList.add('seleccionada');
}

function aplicarEstadoYSiguiente() {
    if (!estadoSeleccionado || !alumnoActualId) return;
    
    const inputHidden = document.getElementById('asistencia_' + alumnoActualId);
    const selector = document.getElementById('selector_' + alumnoActualId);
    
    // Actualizar valores
    let valor, texto, clase;
    switch(estadoSeleccionado) {
        case 'presente':
            valor = '1';
            texto = '1';
            clase = 'presente';
            break;
        case 'ausente':
            valor = '0';
            texto = '0';
            clase = 'ausente';
            break;
        case 'justificado':
            valor = '2';
            texto = '2';
            clase = 'justificado';
            break;
    }
    
    // ACTUALIZACIÓN DIRECTA
    inputHidden.value = valor;
    selector.textContent = texto;
    selector.className = 'selector-estado celda-editable ' + clase;
    
    // También actualizar vista móvil si está activa
    if (vistaMovilActiva) {
        const tarjeta = document.querySelector(`.tarjeta-alumno-movil[data-alumno-id="${alumnoActualId}"]`);
        if (tarjeta) {
            tarjeta.className = 'tarjeta-alumno-movil ' + clase;
            const botones = tarjeta.querySelectorAll('.btn-estado-movil');
            botones.forEach(btn => {
                btn.classList.remove('presente', 'ausente', 'justificado', 'activo');
                if (btn.classList.contains(clase)) {
                    btn.classList.add('activo');
                }
            });
        }
    }
    
    recalcularPorcentajes();
    
    setTimeout(() => {
        siguienteAlumno();
    }, 100);
}

function alumnoAnterior() {
    if (alumnoActualIndex <= 0) return;
    
    const anteriorId = alumnosIds[alumnoActualIndex - 1];
    const fila = document.querySelector(`#selector_${anteriorId}`).closest('tr');
    const nombre = fila.querySelector('.columna-nombre').textContent;
    const clave = fila.querySelector('.columna-clave').textContent;
    
    mostrarModalAsistencia(anteriorId, nombre, clave);
}

function siguienteAlumno() {
    if (alumnoActualIndex >= alumnosIds.length - 1) {
        cerrarModalAsistencia();
        return;
    }
    
    const siguienteId = alumnosIds[alumnoActualIndex + 1];
    const fila = document.querySelector(`#selector_${siguienteId}`).closest('tr');
    const nombre = fila.querySelector('.columna-nombre').textContent;
    const clave = fila.querySelector('.columna-clave').textContent;
    
    mostrarModalAsistencia(siguienteId, nombre, clave);
}

function manejarTeclado(event) {
    // Solo procesar si el modal de asistencia está abierto
    if (document.getElementById('modalAsistencia').style.display !== 'flex') return;
    
    const tecla = event.key;
    
    // Procesar teclas numéricas
    if (tecla === '1' || tecla === '2' || tecla === '0') {
        event.preventDefault();
        
        let estado;
        switch(tecla) {
            case '1': estado = 'presente'; break;
            case '0': estado = 'ausente'; break;
            case '2': estado = 'justificado'; break;
        }
        
        // Seleccionar estado visualmente
        document.querySelectorAll('.opcion-estado').forEach(opcion => {
            opcion.classList.remove('seleccionada');
            if (opcion.classList.contains(estado)) {
                opcion.classList.add('seleccionada');
            }
        });
        
        estadoSeleccionado = estado;
    }
    
    // Enter para aplicar estado seleccionado
    if (tecla === 'Enter' && estadoSeleccionado) {
        event.preventDefault();
        aplicarEstadoYSiguiente();
    }
    
    // Escape para cerrar
    if (tecla === 'Escape') {
        event.preventDefault();
        cerrarModalAsistencia();
    }
    
    // Flechas para navegación
    if (tecla === 'ArrowLeft') {
        event.preventDefault();
        alumnoAnterior();
    }
    
    if (tecla === 'ArrowRight') {
        event.preventDefault();
        siguienteAlumno();
    }
}

// Función para alternar entre formulario de tarea y aviso
function toggleTipoFormulario(tipo) {
    const camposTarea = document.getElementById('campos-tarea');
    const camposAviso = document.getElementById('campos-aviso');
    const btnSubmit = document.getElementById('btnSubmitForm');
    const form = document.getElementById('formTareaAviso');
    
    if (tipo === 'tarea') {
        camposTarea.style.display = 'block';
        camposAviso.style.display = 'none';
        btnSubmit.textContent = 'Crear Tarea';
        
        // Hacer requeridos los campos de tarea
        document.getElementById('fecha_limite').required = true;
        document.getElementById('puntos_maximos').required = true;
        
        // Establecer valores por defecto para tareas
        const fechaLimite = new Date();
        fechaLimite.setDate(fechaLimite.getDate() + 7); // 7 días desde hoy
        document.getElementById('fecha_limite').value = fechaLimite.toISOString().slice(0, 16);
        document.getElementById('puntos_maximos').value = '100';
        
    } else {
        camposTarea.style.display = 'none';
        camposAviso.style.display = 'block';
        btnSubmit.textContent = 'Crear Aviso';
        
        // Quitar requeridos de campos de tarea
        document.getElementById('fecha_limite').required = false;
        document.getElementById('puntos_maximos').required = false;
        
        // Para avisos, establecer valores automáticamente en formato correcto
        const ahora = new Date();
        const fechaFormateada = ahora.toISOString().slice(0, 19).replace('T', ' ');
        
        document.getElementById('fecha_aviso').value = fechaFormateada;
        document.getElementById('puntos_aviso').value = '0';
        
        console.log('Aviso configurado - Fecha:', fechaFormateada, 'Puntos: 0');
    }
}

// Llamar a la función al cargar la página para establecer valores iniciales
document.addEventListener('DOMContentLoaded', function() {
    toggleTipoFormulario('tarea');
});
function recalcularPorcentajes() {
    const filas = document.querySelectorAll('.tabla-asistencia tbody tr');
    
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        const totalDias = celdas.length - 3; // Restar clave, nombre y porcentaje
        
        let diasAsistidos = 0;
        let diasConRegistro = 0;
        
        // Contar asistencias (celdas 2 hasta n-1)
        for (let i = 2; i < celdas.length - 1; i++) {
            const celda = celdas[i];
            const selector = celda.querySelector('.selector-estado');
            const celdaLectura = celda.querySelector('.celda-asistencia');
            const celdaVacia = celda.querySelector('div:not(.selector-estado):not(.celda-asistencia)');
            
            if (selector) {
                // Celda editable de hoy
                diasConRegistro++;
                if (selector.classList.contains('presente') || selector.classList.contains('justificado')) {
                    diasAsistidos++;
                }
            } else if (celdaLectura) {
                // Celda de lectura de días anteriores
                diasConRegistro++;
                if (celdaLectura.classList.contains('presente') || celdaLectura.classList.contains('justificado')) {
                    diasAsistidos++;
                }
            }
            // Las celdas vacías no cuentan para el porcentaje
        }
        
        const porcentaje = diasConRegistro > 0 ? Math.round((diasAsistidos / diasConRegistro) * 100) : 0;
        const porcentajeCell = fila.querySelector('.porcentaje-asistencia');
        
        // Actualizar porcentaje y clase
        if (porcentajeCell) {
            porcentajeCell.textContent = porcentaje + '%';
            porcentajeCell.className = 'porcentaje-asistencia ' + 
                (porcentaje >= 80 ? 'porcentaje-alto' : 
                 porcentaje >= 60 ? 'porcentaje-medio' : 'porcentaje-bajo');
        }
    });
}

function cerrarModalAsistencia() {
    document.getElementById('modalAsistencia').style.display = 'none';
    alumnoActualId = null;
    estadoSeleccionado = null;
    alumnoActualIndex = -1;
}

function cambiarUnidad(unidad) {
    document.getElementById('inputUnidad').value = unidad;
    console.log('Unidad seleccionada:', unidad);
}

// Cerrar modales al hacer click fuera
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal, .modal-asistencia');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Event listener del formulario CORREGIDO - esta es la clave
const formLista = document.getElementById('formListaAsistencia');
if (formLista) {
    formLista.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnGuardar = document.getElementById('btnGuardarLista');
        const originalText = btnGuardar.innerHTML;
        
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '⏳ Guardando...';
        
        console.log('=== VERIFICANDO Y SINCRONIZANDO DATOS ANTES DE ENVIAR ===');
        
        // SINCRONIZACIÓN CRÍTICA - asegurar que todos los inputs estén actualizados
        sincronizarInputsAsistencia();
        
        // Verificar qué datos se van a enviar
        const todosLosInputs = document.querySelectorAll('#contenedor-inputs-asistencia input[name^="asistencia["]');
        let asistenciasMarcadas = 0;
        
        todosLosInputs.forEach(input => {
            if (input.value !== '') {
                asistenciasMarcadas++;
                console.log(`✅ ${input.name} = ${input.value}`);
            } else {
                console.log(`❌ ${input.name} = VACÍO`);
            }
        });
        
        console.log('Total asistencias a guardar:', asistenciasMarcadas);
        
        if (asistenciasMarcadas === 0) {
            if (!confirm('No has marcado ninguna asistencia. ¿Estás seguro de que quieres guardar la lista vacía?')) {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = originalText;
                return;
            }
        }
        
        if (asistenciasMarcadas > 0) {
            alert(`✅ Guardando ${asistenciasMarcadas} asistencias`);
        }
        
        console.log('Enviando formulario...');
        
        // Crear un FormData para verificar los datos que se envían
        const formData = new FormData(this);
        for (let [key, value] of formData.entries()) {
            console.log(`📤 Enviando: ${key} = ${value}`);
        }
        
        this.submit();
    });
}

// Inicialización corregida
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM cargado, inicializando funcionalidades...');
    
    // Inicializar lista de alumnos para el pase de lista
    inicializarListaAlumnos();
    
    // Verificar si hay una sección específica en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const seccionParam = urlParams.get('seccion');
    
    if (seccionParam && (seccionParam === 'lista' || seccionParam === 'tareas')) {
        mostrarSeccionDesdeURL(seccionParam);
    }
    
    // Agregar event listener para teclado
    document.addEventListener('keydown', manejarTeclado);
    
    // Detectar si es dispositivo móvil
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        document.getElementById('btnVistaMovil').click();
    }
    
    // Inicializar el formulario de tarea/aviso
    const fechaLimite = new Date();
    fechaLimite.setDate(fechaLimite.getDate() + 7);
    document.getElementById('fecha_limite').value = fechaLimite.toISOString().slice(0, 16);
});

function mostrarSeccionDesdeURL(seccion) {
    console.log('Mostrando sección desde URL:', seccion);
    
    // Remover clase active de todas las pestañas
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Ocultar todas las secciones
    document.querySelectorAll('[id^="seccion-"]').forEach(div => {
        div.classList.remove('seccion-activa');
        div.classList.add('seccion-oculta');
    });
    
    // Activar la pestaña correspondiente y mostrar la sección
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        if (tab.textContent.toLowerCase().includes(seccion)) {
            tab.classList.add('active');
        }
    });
    
    const seccionMostrar = document.getElementById('seccion-' + seccion);
    if (seccionMostrar) {
        seccionMostrar.classList.remove('seccion-oculta');
        seccionMostrar.classList.add('seccion-activa');
    }
}
</script>
</body>
</html>
<?php include "footer.php"; ?>