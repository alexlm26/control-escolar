<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Permitir acceso a coordinadores (rol 3), profesores tutores (rol 2) y prefectos (rol 5)
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != '3' && $_SESSION['rol'] != '2' && $_SESSION['rol'] != '5')) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol_usuario = $_SESSION['rol'];
$id_grupo = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_grupo == 0) {
    if ($rol_usuario == '3') {
        header("Location: coordinador.php?seccion=grupos");
    } else if ($rol_usuario == '5') {
        header("Location: prefecto.php?seccion=grupos");
    } else {
        header("Location: profesor.php");
    }
    exit;
}

// Verificar permisos según el rol
$es_coordinador = ($rol_usuario == '3');
$es_tutor = false;
$es_prefecto = ($rol_usuario == '5');

if ($rol_usuario == '2') {
    // Verificar si el profesor es tutor de este grupo específico
    $sql_verificar_tutor = "
        SELECT COUNT(*) as es_tutor 
        FROM grupo 
        WHERE id_grupo = ? AND tutor_asignado IN (
            SELECT id_profesor FROM profesor WHERE id_usuario = ?
        ) AND activo = 1
    ";
    $stmt_tutor = $conexion->prepare($sql_verificar_tutor);
    $stmt_tutor->bind_param("ii", $id_grupo, $id_usuario);
    $stmt_tutor->execute();
    $result_tutor = $stmt_tutor->get_result()->fetch_assoc();
    
    $es_tutor = ($result_tutor['es_tutor'] > 0);
    
    if (!$es_tutor) {
        header("Location: profesor.php?error=No tienes permisos para acceder a este grupo");
        exit;
    }
}

// Para prefectos, verificar que el grupo existe
if ($es_prefecto) {
    $sql_verificar_grupo = "SELECT COUNT(*) as existe FROM grupo WHERE id_grupo = ? AND activo = 1";
    $stmt_grupo_verif = $conexion->prepare($sql_verificar_grupo);
    $stmt_grupo_verif->bind_param("i", $id_grupo);
    $stmt_grupo_verif->execute();
    $result_grupo_verif = $stmt_grupo_verif->get_result()->fetch_assoc();
    
    if ($result_grupo_verif['existe'] == 0) {
        header("Location: prefecto.php?seccion=grupos&error=Grupo no encontrado");
        exit;
    }
}

// Obtener información del grupo
$sql_grupo = "
    SELECT 
        g.*,
        car.nombre as carrera_nombre,
        e.nombre as especialidad_nombre,
        CONCAT(u_tutor.nombre, ' ', u_tutor.apellidos) as tutor_nombre,
        u_tutor.clave as tutor_clave,
        p.id_profesor as tutor_id
    FROM grupo g
    LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
    LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
    LEFT JOIN profesor p ON g.tutor_asignado = p.id_profesor
    LEFT JOIN usuario u_tutor ON p.id_usuario = u_tutor.id_usuario
    WHERE g.id_grupo = ?
";

$stmt_grupo = $conexion->prepare($sql_grupo);
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$grupo = $stmt_grupo->get_result()->fetch_assoc();

if (!$grupo) {
    if ($rol_usuario == '3') {
        header("Location: coordinador.php?seccion=grupos&error=Grupo no encontrado");
    } else if ($rol_usuario == '5') {
        header("Location: prefecto.php?seccion=grupos&error=Grupo no encontrado");
    } else {
        header("Location: profesor.php?error=Grupo no encontrado");
    }
    exit;
}

// Obtener alumnos del grupo
$sql_alumnos = "
    SELECT 
        a.id_alumno,
        u.clave as matricula,
        u.nombre,
        u.apellidos,
        a.promedio,
        a.semestre,
        a.especialidad,
        a.estado,
        ag.fecha_ingreso
    FROM alumno_grupo ag
    INNER JOIN alumno a ON ag.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE ag.id_grupo = ? AND ag.activo = 1
    ORDER BY u.apellidos, u.nombre
";

$stmt_alumnos = $conexion->prepare($sql_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$alumnos = $stmt_alumnos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener reportes individuales del grupo (CORREGIDO - usando prefecto en lugar de rol5)
$sql_reportes_individuales = "
    SELECT 
        rci.*,
        u.nombre as alumno_nombre,
        u.apellidos as alumno_apellidos,
        m.nombre as materia_nombre,
        CASE 
            WHEN rci.tipo_reportador = 'profesor' THEN CONCAT(u_prof.nombre, ' ', u_prof.apellidos)
            WHEN rci.tipo_reportador = 'coordinador' THEN CONCAT(u_coord.nombre, ' ', u_coord.apellidos)
            WHEN rci.tipo_reportador = 'prefecto' THEN CONCAT(u_pref.nombre, ' ', u_pref.apellidos)
            ELSE 'Sistema'
        END as reportador_nombre,
        rci.tipo_reportador,
        rci.ruta_evidencia
    FROM reportes_conducta_individual rci
    INNER JOIN alumno a ON rci.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    LEFT JOIN clase c ON rci.id_clase = c.id_clase
    LEFT JOIN materia m ON c.id_materia = m.id_materia
    LEFT JOIN profesor p ON rci.id_profesor = p.id_profesor
    LEFT JOIN usuario u_prof ON p.id_usuario = u_prof.id_usuario
    LEFT JOIN coordinador coord ON rci.id_coordinador = coord.id_coordinador
    LEFT JOIN usuario u_coord ON coord.id_usuario = u_coord.id_usuario
    LEFT JOIN prefecto pref ON rci.tipo_reportador = 'prefecto' AND rci.id_reportador = pref.id_prefecto
    LEFT JOIN usuario u_pref ON pref.id_usuario = u_pref.id_usuario
    WHERE rci.id_alumno IN (
        SELECT id_alumno FROM alumno_grupo WHERE id_grupo = ? AND activo = 1
    )
    ORDER BY rci.fecha_incidente DESC
    LIMIT 50
";

$stmt_reportes_ind = $conexion->prepare($sql_reportes_individuales);
$stmt_reportes_ind->bind_param("i", $id_grupo);
$stmt_reportes_ind->execute();
$reportes_individuales = $stmt_reportes_ind->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener reportes grupales (CORREGIDO - usando prefecto en lugar de rol5)
$sql_reportes_grupales = "
    SELECT 
        rcg.*,
        CASE 
            WHEN rcg.tipo_reportador = 'profesor' THEN CONCAT(u_prof.nombre, ' ', u_prof.apellidos)
            WHEN rcg.tipo_reportador = 'coordinador' THEN CONCAT(u_coord.nombre, ' ', u_coord.apellidos)
            WHEN rcg.tipo_reportador = 'prefecto' THEN CONCAT(u_pref.nombre, ' ', u_pref.apellidos)
            ELSE 'Sistema'
        END as reportador_nombre,
        rcg.tipo_reportador,
        m.nombre as materia_nombre,
        rcg.ruta_evidencia
    FROM reportes_conducta_grupal rcg
    LEFT JOIN clase c ON rcg.id_clase = c.id_clase
    LEFT JOIN materia m ON c.id_materia = m.id_materia
    LEFT JOIN profesor p ON rcg.id_profesor = p.id_profesor
    LEFT JOIN usuario u_prof ON p.id_usuario = u_prof.id_usuario
    LEFT JOIN coordinador coord ON rcg.id_coordinador = coord.id_coordinador
    LEFT JOIN usuario u_coord ON coord.id_usuario = u_coord.id_usuario
    LEFT JOIN prefecto pref ON rcg.tipo_reportador = 'prefecto' AND rcg.id_reportador = pref.id_prefecto
    LEFT JOIN usuario u_pref ON pref.id_usuario = u_pref.id_usuario
    WHERE rcg.id_grupo = ?
    ORDER BY rcg.fecha_incidente DESC
    LIMIT 20
";

$stmt_reportes_grp = $conexion->prepare($sql_reportes_grupales);
$stmt_reportes_grp->bind_param("i", $id_grupo);
$stmt_reportes_grp->execute();
$reportes_grupales = $stmt_reportes_grp->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener clases asignadas al grupo CON CONTEO DE ALUMNOS DEL GRUPO
$sql_clases_grupo = "
    SELECT 
        c.id_clase,
        m.nombre as materia_nombre,
        CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
        c.grupo as clase_grupo,
        s.nombre as salon,
        s.edificio,
        COUNT(DISTINCT hc.dia) as dias_semana,
        c.periodo,
        c.capacidad,
        COUNT(DISTINCT ag.id_alumno) as alumnos_grupo_asignados
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    INNER JOIN salon s ON c.id_salon = s.id_salon
    INNER JOIN horarios_clase hc ON c.id_clase = hc.id_clase
    INNER JOIN asignacion a ON c.id_clase = a.id_clase
    INNER JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno AND ag.id_grupo = ? AND ag.activo = 1
    WHERE c.activo = 1
    GROUP BY c.id_clase
    ORDER BY m.nombre
";

$stmt_clases = $conexion->prepare($sql_clases_grupo);
$stmt_clases->bind_param("i", $id_grupo);
$stmt_clases->execute();
$clases_grupo = $stmt_clases->get_result()->fetch_all(MYSQLI_ASSOC);

// Solo coordinadores pueden ver clases disponibles y asignar
$clases_disponibles = [];
if ($es_coordinador) {
    $sql_clases_disponibles = "
        SELECT 
            c.id_clase,
            m.nombre as materia_nombre,
            CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
            c.grupo,
            s.nombre as salon,
            s.edificio,
            c.capacidad,
            (SELECT COUNT(*) FROM asignacion WHERE id_clase = c.id_clase) as alumnos_inscritos,
            c.periodo,
            m.id_especialidad,
            e.nombre as especialidad_nombre
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        INNER JOIN salon s ON c.id_salon = s.id_salon
        LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        WHERE c.activo = 1 
        AND m.id_carrera = ?
        AND (m.id_especialidad = ? OR m.id_especialidad = 1 OR ? IS NULL)
        AND c.id_clase NOT IN (
            SELECT DISTINCT a.id_clase 
            FROM asignacion a 
            INNER JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno 
            WHERE ag.id_grupo = ? AND ag.activo = 1
        )
        ORDER BY m.nombre
    ";

    $stmt_clases_disp = $conexion->prepare($sql_clases_disponibles);
    $stmt_clases_disp->bind_param("iiii", $grupo['id_carrera'], $grupo['id_especialidad'], $grupo['id_especialidad'], $id_grupo);
    $stmt_clases_disp->execute();
    $clases_disponibles = $stmt_clases_disp->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener materias reprobadas de un alumno
function obtenerMateriasReprobadas($conexion, $id_alumno) {
    $sql = "
        SELECT COUNT(*) as total
        FROM materia_cursada 
        WHERE id_alumno = ? AND aprobado = 0
        AND id_materia NOT IN (
            SELECT id_materia FROM materia_cursada 
            WHERE id_alumno = ? AND aprobado = 1
        )
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id_alumno, $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'];
}

// Verificar estado de cada alumno
foreach ($alumnos as &$alumno) {
    $materias_reprobadas = obtenerMateriasReprobadas($conexion, $alumno['id_alumno']);
    $alumno['materias_reprobadas'] = $materias_reprobadas;
    $alumno['atrasado'] = $materias_reprobadas >= 1;
    $alumno['rezagado'] = $materias_reprobadas >= 4;
}
unset($alumno); // Romper la referencia

// Estadísticas del grupo
$total_alumnos = count($alumnos);
$alumnos_atrasados = 0;
$alumnos_rezagados = 0;

foreach ($alumnos as $alumno) {
    if ($alumno['rezagado']) {
        $alumnos_rezagados++;
    } elseif ($alumno['atrasado']) {
        $alumnos_atrasados++;
    }
}

$alumnos_regulares = $total_alumnos - $alumnos_atrasados - $alumnos_rezagados;

// Solo coordinadores pueden obtener estos datos
$profesores = [];
$alumnos_disponibles = [];

if ($es_coordinador) {
    // Obtener profesores para asignar tutor
    $profesores_query = $conexion->query("
        SELECT p.id_profesor, u.nombre, u.apellidos, u.clave 
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.estado = '1'
        ORDER BY u.nombre, u.apellidos
    ");
    $profesores = $profesores_query->fetch_all(MYSQLI_ASSOC);

    // Obtener alumnos disponibles para agregar al grupo
    $sql_alumnos_disponibles = "
        SELECT 
            a.id_alumno,
            u.clave as matricula,
            u.nombre,
            u.apellidos,
            a.semestre,
            a.especialidad
        FROM alumno a
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE a.estado = '1'
        AND a.id_alumno NOT IN (
            SELECT id_alumno FROM alumno_grupo WHERE activo = 1
        )
        AND u.id_carrera = ?
        ORDER BY u.apellidos, u.nombre
    ";

    $stmt_alumnos_disp = $conexion->prepare($sql_alumnos_disponibles);
    $stmt_alumnos_disp->bind_param("i", $grupo['id_carrera']);
    $stmt_alumnos_disp->execute();
    $alumnos_disponibles = $stmt_alumnos_disp->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Para prefectos, obtener información del prefecto
$id_prefecto = null;
if ($es_prefecto) {
    $sql_prefecto = "SELECT id_prefecto FROM prefecto WHERE id_usuario = ?";
    $stmt_prefecto = $conexion->prepare($sql_prefecto);
    $stmt_prefecto->bind_param("i", $id_usuario);
    $stmt_prefecto->execute();
    $prefecto_data = $stmt_prefecto->get_result()->fetch_assoc();
    if ($prefecto_data) {
        $id_prefecto = $prefecto_data['id_prefecto'];
    }
}
?>

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

body {
  background: var(--color-fondo);
  font-family: "Poppins", "Segoe UI", sans-serif;
  color: var(--color-texto);
}

.content {
  padding: 40px 5%;
  max-width: 1400px;
  margin: auto;
}

/* BANNER INFORMACIÓN DEL GRUPO */
.banner-grupo {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 40px 30px;
  border-radius: var(--radio-borde);
  box-shadow: var(--sombra-suave);
  margin-bottom: 30px;
  position: relative;
  overflow: hidden;
}

.banner-grupo::before {
  content: "";
  position: absolute;
  top: -50%;
  right: -50%;
  width: 100%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
  background-size: 20px 20px;
  transform: rotate(30deg);
}

.banner-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  position: relative;
  z-index: 2;
}

.banner-titulo h1 {
  margin: 0 0 10px 0;
  font-size: 2.2em;
  font-weight: 700;
}

.banner-titulo .grupo-info {
  font-size: 1.1em;
  opacity: 0.9;
}

.banner-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 20px;
  position: relative;
  z-index: 2;
}

.stat-card {
  background: rgba(255,255,255,0.15);
  padding: 15px;
  border-radius: 10px;
  text-align: center;
  backdrop-filter: blur(10px);
}

.stat-number {
  font-size: 2em;
  font-weight: 700;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 0.9em;
  opacity: 0.9;
}

.indicador-rol {
  position: absolute;
  top: 15px;
  right: 15px;
  background: rgba(255,255,255,0.9);
  padding: 5px 12px;
  border-radius: 15px;
  font-size: 0.8em;
  font-weight: 600;
  z-index: 10;
}

.indicador-coordinador {
  color: #1565c0;
  border: 1px solid #1565c0;
}

.indicador-tutor {
  color: #28a745;
  border: 1px solid #28a745;
}

.indicador-prefecto {
  color: #9c27b0;
  border: 1px solid #9c27b0;
}

/* BARRA DE BÚSQUEDA */
.barra-busqueda-container {
  display: flex;
  justify-content: center;
  margin: 20px 0 30px 0;
}

.barra-busqueda {
  width: 100%;
  max-width: 500px;
  position: relative;
}

.barra-busqueda input {
  width: 100%;
  padding: 15px 50px 15px 20px;
  border: 2px solid #e0e0e0;
  border-radius: var(--radio-borde);
  font-size: 1em;
  transition: all 0.3s ease;
  box-shadow: var(--sombra-suave);
  box-sizing: border-box;
}

.barra-busqueda input:focus {
  outline: none;
  border-color: var(--color-primario);
  box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
}

.barra-busqueda .icono-busqueda {
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  color: #666;
  font-size: 1.2em;
}

/* BOTONES PRINCIPALES */
.botones-principales {
  display: flex;
  gap: 15px;
  margin-bottom: 30px;
  flex-wrap: wrap;
}

.btn-principal {
  background: var(--color-primario);
  color: white;
  border: none;
  padding: 12px 25px;
  border-radius: var(--radio-borde);
  font-size: 1em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: var(--sombra-suave);
}

.btn-principal:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
  box-shadow: var(--sombra-hover);
}

.btn-principal.active {
  background: var(--color-secundario);
}

/* SECCIONES */
.seccion {
  display: none;
  animation: fadeIn 0.5s ease;
}

.seccion.activa {
  display: block;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* TARJETAS DE CONTENIDO */
.tarjeta-contenido {
  background: var(--color-blanco);
  border-radius: var(--radio-borde);
  padding: 25px;
  box-shadow: var(--sombra-suave);
  margin-bottom: 25px;
}

.tarjeta-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e0e0e0;
}

.tarjeta-header h3 {
  margin: 0;
  color: var(--color-primario);
  font-size: 1.3em;
}

/* TABLAS */
.tabla-contenedor {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e0e0e0;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

.tabla th {
  background: #f8f9fa;
  padding: 12px 15px;
  text-align: left;
  font-weight: 600;
  color: #555;
  border-bottom: 2px solid #e0e0e0;
}

.tabla td {
  padding: 12px 15px;
  border-bottom: 1px solid #e0e0e0;
}

.tabla tr:hover {
  background: #f8f9fa;
}

/* ESTADOS Y BADGES */
.badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.8em;
  font-weight: 600;
  text-transform: uppercase;
}

.badge-regular {
  background: #e8f5e8;
  color: #2e7d32;
}

.badge-atrasado {
  background: #fff3e0;
  color: #f57c00;
}

.badge-rezagado {
  background: #ffebee;
  color: #c62828;
}

.badge-leve {
  background: #fff3e0;
  color: #f57c00;
}

.badge-grave {
  background: #ffebee;
  color: #c62828;
}

.badge-muy-grave {
  background: #fce4ec;
  color: #ad1457;
}

.estado-activo {
  color: #28a745;
  font-weight: 600;
}

.estado-inactivo {
  color: #dc3545;
  font-weight: 600;
}

/* ACCIONES */
.acciones-tabla {
  display: flex;
  gap: 5px;
}

.btn-accion {
  padding: 6px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85em;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 0.8em;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-warning {
  background: #ffc107;
  color: #212529;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-info {
  background: #17a2b8;
  color: white;
}

.btn-prefecto {
  background: #9c27b0;
  color: white;
}

.btn-prefecto:hover {
  background: #7b1fa2;
}

/* FORMULARIOS */
.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: #555;
}

.form-control {
  width: 100%;
  padding: 10px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 1em;
  transition: border-color 0.3s ease;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primario);
}

.form-check {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.form-check-input {
  margin-right: 8px;
}

/* MODAL */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
}

.modal-content {
  background-color: white;
  margin: 5% auto;
  padding: 30px;
  border-radius: var(--radio-borde);
  width: 90%;
  max-width: 800px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  animation: modalAppear 0.3s ease;
  max-height: 90vh;
  overflow-y: auto;
}

@keyframes modalAppear {
  from { opacity: 0; transform: translateY(-50px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e0e0e0;
}

.modal-header h3 {
  margin: 0;
  color: var(--color-primario);
}

.close {
  color: #aaa;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: #000;
}

.acciones {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  justify-content: flex-end;
}

.btn {
  padding: 10px 20px;
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
  background: var(--color-primario);
  color: white;
}

.btn-primary:hover {
  background: var(--color-secundario);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

/* UPLOAD AREA */
.upload-area {
  border: 2px dashed #e0e0e0;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  margin: 10px 0;
  background: #fafafa;
  transition: all 0.3s ease;
}

.upload-area:hover {
  border-color: var(--color-primario);
  background: #f0f8ff;
}

.upload-area-evidencias {
  border: 2px dashed #9c27b0;
  background: #f3e5f5;
}

.upload-area-evidencias:hover {
  border-color: #7b1fa2;
  background: #e1bee7;
}

.evidencia-preview {
  max-width: 200px;
  max-height: 150px;
  margin: 10px 0;
  border-radius: 8px;
  display: none;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #666;
}

.empty-state h4 {
  color: #999;
  margin-bottom: 10px;
}

/* GRID DE CLASES */
.grid-clases {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-top: 15px;
}

.tarjeta-clase {
  background: white;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  border-left: 4px solid #1565c0;
  transition: all 0.3s ease;
}

.tarjeta-clase:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

/* CHECKBOX GRID */
.checkbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 15px;
  max-height: 400px;
  overflow-y: auto;
  padding: 10px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
}

.checkbox-item {
  display: flex;
  align-items: flex-start;
  padding: 15px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background: #fafafa;
  transition: all 0.3s ease;
}

.checkbox-item:hover {
  background: #f0f8ff;
  border-color: #1565c0;
}

.checkbox-item input[type="checkbox"] {
  margin-right: 10px;
  margin-top: 3px;
}

.checkbox-info {
  flex: 1;
}

.checkbox-info h5 {
  margin: 0 0 5px 0;
  color: #1565c0;
}

.checkbox-info p {
  margin: 2px 0;
  font-size: 0.9em;
  color: #666;
}

/* CLASES PARA PERMISOS */
.permiso-coordinador {
  display: <?php echo $es_coordinador ? 'block' : 'none'; ?>;
}

.permiso-tutor {
  display: <?php echo $es_tutor ? 'block' : 'none'; ?>;
}

.permiso-prefecto {
  display: <?php echo $es_prefecto ? 'block' : 'none'; ?>;
}

.btn-deshabilitado {
  background: #6c757d !important;
  cursor: not-allowed !important;
  opacity: 0.6;
}

.btn-deshabilitado:hover {
  transform: none !important;
  box-shadow: none !important;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .banner-header {
    flex-direction: column;
    gap: 20px;
  }
  
  .banner-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .botones-principales {
    flex-direction: column;
  }
  
  .btn-principal {
    width: 100%;
    text-align: center;
  }
  
  .modal-content {
    width: 95%;
    margin: 10% auto;
    padding: 20px;
  }
  
  .acciones-tabla {
    flex-direction: column;
    gap: 3px;
  }
  
  .btn-accion {
    width: 100%;
    text-align: center;
  }
  
  .checkbox-grid {
    grid-template-columns: 1fr;
  }
  
  .barra-busqueda {
    max-width: 100%;
  }
}
        
        
        
        
        
        
        
        
        
        .btn-success {
  background: #28a745;
  color: white;
}

.btn-warning {
  background: #ffc107;
  color: #212529;
}

.btn-success:hover {
  background: #218838;
}

.btn-warning:hover {
  background: #e0a800;
}
</style>

<!-- BANNER INFORMACIÓN DEL GRUPO -->
<section class="banner-grupo">
  <div class="indicador-rol <?php echo $es_coordinador ? 'indicador-coordinador' : ($es_tutor ? 'indicador-tutor' : 'indicador-prefecto'); ?>">
    <?php echo $es_coordinador ? 'COORDINADOR' : ($es_tutor ? 'TUTOR' : 'PREFECTO'); ?>
  </div>
  
  <div class="banner-header">
    <div class="banner-titulo">
      <h1><?php echo htmlspecialchars($grupo['nombre']); ?></h1>
      <div class="grupo-info">
        <strong>Carrera:</strong> <?php echo htmlspecialchars($grupo['carrera_nombre']); ?>
        <?php if ($grupo['especialidad_nombre']): ?>
          | <strong>Especialidad:</strong> <?php echo htmlspecialchars($grupo['especialidad_nombre']); ?>
        <?php endif; ?>
        | <strong>Semestre:</strong> <?php echo $grupo['semestre']; ?>
        <?php if ($grupo['tutor_nombre']): ?>
          | <strong>Tutor:</strong> <?php echo htmlspecialchars($grupo['tutor_nombre']); ?>
        <?php endif; ?>
      </div>
    </div>
    <a href="grupos.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
      ← Volver
    </a>
  </div>
  
  <div class="banner-stats">
    <div class="stat-card">
      <div class="stat-number"><?php echo $total_alumnos; ?></div>
      <div class="stat-label">Total Alumnos</div>
    </div>
    <div class="stat-card">
      <div class="stat-number <?php echo $alumnos_regulares > 0 ? 'estado-activo' : ''; ?>"><?php echo $alumnos_regulares; ?></div>
      <div class="stat-label">Alumnos Regulares</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #f57c00;"><?php echo $alumnos_atrasados; ?></div>
      <div class="stat-label">Alumnos Atrasados</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #c62828;"><?php echo $alumnos_rezagados; ?></div>
      <div class="stat-label">Alumnos Rezagados</div>
    </div>
  </div>
</section>

<main class="content">
  <!-- BOTONES PRINCIPALES -->
  <div class="botones-principales">
    <button class="btn-principal active" onclick="mostrarSeccion('alumnos')">
      Alumnos del Grupo
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('reportes-individuales')">
      Reportes Individuales
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('reportes-grupales')">
      Reportes Grupales
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('clases')">
      Clases Asignadas
    </button>
    <?php if ($es_coordinador): ?>
    <button class="btn-principal" onclick="mostrarSeccion('configuracion')">
      Configuración
    </button>
    <?php endif; ?>
          <?php if ($es_prefecto): ?>
                          <!-- AGREGAR ESTOS BOTONES NUEVOS -->
  <button class="btn btn-warning" onclick="abrirModalJustificanteMultiple()">
    Crear justificantes
  </button>
          <?php endif; ?>
          
  </div>

  <!-- BARRA DE BÚSQUEDA PARA CLASES (solo coordinadores) -->
  <?php if ($es_coordinador && count($clases_disponibles) > 0): ?>
  <div class="barra-busqueda-container permiso-coordinador" id="barra-busqueda-clases" style="display: none;">
    <div class="barra-busqueda">
      <input type="text" id="buscarClase" placeholder="Buscar clase por nombre de materia, profesor o salón...">
      <div class="icono-busqueda"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- SECCIÓN DE ALUMNOS -->
  <div id="seccion-alumnos" class="seccion activa">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Alumnos del Grupo (<?php echo $total_alumnos; ?>)</h3>
        <div class="permiso-coordinador">
          <button class="btn btn-primary" onclick="abrirModalAgregarAlumno()">
            + Agregar Alumno
          </button>
          <button class="btn btn-success" onclick="abrirModalImportarAlumnos()">
            📁 Importar CSV
          </button>
        </div>
        <div class="permiso-tutor">
          <button class="btn btn-primary" onclick="abrirModalNuevoReporteIndividual()">
            + Nuevo Reporte Individual
          </button>
        </div>
        <div class="permiso-prefecto">
          <button class="btn btn-prefecto" onclick="abrirModalNuevoReporteIndividualPrefecto()">
            + Nuevo Reporte Individual
          </button>
        </div>
      </div>

      <?php if ($total_alumnos > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Matrícula</th>
                <th>Nombre Completo</th>
                <th>Semestre</th>
                <th>Promedio</th>
                <th>Especialidad</th>
                <th>Estado Académico</th>
                <th>Fecha Ingreso</th>
                <?php if ($es_coordinador): ?>
                <th>Acciones</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($alumnos as $alumno): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($alumno['matricula']); ?></strong></td>
                  <td><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></td>
                  <td><?php echo $alumno['semestre']; ?></td>
                  <td><strong><?php echo number_format($alumno['promedio'], 2); ?></strong></td>
                  <td><?php echo htmlspecialchars($alumno['especialidad']); ?></td>
                  <td>
                    <?php if ($alumno['rezagado']): ?>
                      <span class="badge badge-rezagado">Rezagado (<?php echo $alumno['materias_reprobadas']; ?> materias)</span>
                    <?php elseif ($alumno['atrasado']): ?>
                      <span class="badge badge-atrasado">Atrasado (<?php echo $alumno['materias_reprobadas']; ?> materias)</span>
                    <?php else: ?>
                      <span class="badge badge-regular">Regular</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo date('d/m/Y', strtotime($alumno['fecha_ingreso'])); ?></td>
                  <?php if ($es_coordinador): ?>
                  <td>
                    <div class="acciones-tabla">
                      <button class="btn-accion btn-info btn-sm" onclick="verDetalleAlumno(<?php echo $alumno['id_alumno']; ?>)">
                        👁️
                      </button>
                      <button class="btn-accion btn-danger btn-sm" onclick="eliminarAlumnoGrupo(<?php echo $alumno['id_alumno']; ?>, '<?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?>')">
                        🗑️
                      </button>
                    </div>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay alumnos en este grupo</h4>
          <p><?php echo $es_coordinador ? 'Agrega alumnos utilizando los botones de arriba' : 'No hay alumnos asignados a este grupo'; ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE REPORTES INDIVIDUALES -->
  <div id="seccion-reportes-individuales" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Reportes de Conducta Individual</h3>
        <div class="permiso-tutor">
          <button class="btn btn-primary" onclick="abrirModalNuevoReporteIndividual()">
            + Nuevo Reporte Individual
          </button>
        </div>
        <div class="permiso-prefecto">
          <button class="btn btn-prefecto" onclick="abrirModalNuevoReporteIndividualPrefecto()">
            + Nuevo Reporte Individual
          </button>
        </div>
      </div>

      <?php if (count($reportes_individuales) > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Alumno</th>
                <th>Fecha Incidente</th>
                <th>Reportado por</th>
                <th>Tipo</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Evidencia</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reportes_individuales as $reporte): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($reporte['alumno_nombre'] . ' ' . $reporte['alumno_apellidos']); ?></strong></td>
                  <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_incidente'])); ?></td>
                  <td>
                    <strong><?php echo htmlspecialchars($reporte['reportador_nombre']); ?></strong>
                    <br><small class="text-muted">(<?php echo ucfirst($reporte['tipo_reportador']); ?>)</small>
                  </td>
                  <td>
                    <span class="badge badge-<?php echo str_replace('_', '-', $reporte['tipo_incidencia']); ?>">
                      <?php echo ucfirst(str_replace('_', ' ', $reporte['tipo_incidencia'])); ?>
                    </span>
                  </td>
                  <td><?php echo ucfirst(str_replace('_', ' ', $reporte['categoria'])); ?></td>
                  <td>
                    <a href="detalle_reporte.php?id=<?php echo $reporte['id_reporte_individual']; ?>&tipo=individual" 
                       style="text-decoration: none; color: inherit;"
                       title="Ver reporte detallado">
                        <?php echo htmlspecialchars(mb_strimwidth($reporte['descripcion'], 0, 50, '...')); ?>
                    </a>
                  </td>
                  <td>
                    <?php if ($reporte['ruta_evidencia']): ?>
                      <a href="<?php echo htmlspecialchars($reporte['ruta_evidencia']); ?>" target="_blank" class="btn-accion btn-info btn-sm">
                        📎 Ver
                      </a>
                    <?php else: ?>
                      <span class="text-muted">Sin evidencia</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="<?php echo $reporte['estado'] == 'activo' ? 'estado-activo' : 'estado-inactivo'; ?>">
                      <?php echo ucfirst($reporte['estado']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay reportes individuales</h4>
          <p>No se han registrado reportes de conducta individual para este grupo</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE REPORTES GRUPALES -->
  <div id="seccion-reportes-grupales" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Reportes de Conducta Grupal</h3>
        <div class="permiso-tutor">
          <button class="btn btn-primary" onclick="abrirModalNuevoReporteGrupal()">
            + Nuevo Reporte Grupal
          </button>
        </div>
        <div class="permiso-prefecto">
          <button class="btn btn-prefecto" onclick="abrirModalNuevoReporteGrupalPrefecto()">
            + Nuevo Reporte Grupal
          </button>
        </div>
      </div>

      <?php if (count($reportes_grupales) > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Fecha Incidente</th>
                <th>Reportado por</th>
                <th>Tipo</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Evidencia</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reportes_grupales as $reporte): ?>
                <tr>
                  <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_incidente'])); ?></td>
                  <td>
                    <strong><?php echo htmlspecialchars($reporte['reportador_nombre']); ?></strong>
                    <br><small class="text-muted">(<?php echo ucfirst($reporte['tipo_reportador']); ?>)</small>
                  </td>
                  <td>
                    <span class="badge badge-<?php echo str_replace('_', '-', $reporte['tipo_incidencia']); ?>">
                      <?php echo ucfirst(str_replace('_', ' ', $reporte['tipo_incidencia'])); ?>
                    </span>
                  </td>
                  <td><?php echo ucfirst(str_replace('_', ' ', $reporte['categoria'])); ?></td>
                  <td>
                    <a href="detalle_reporte.php?id=<?php echo $reporte['id_reporte_grupal']; ?>&tipo=grupal" 
                       style="text-decoration: none; color: inherit;"
                       title="Ver reporte detallado">
                        <?php echo htmlspecialchars(mb_strimwidth($reporte['descripcion'], 0, 50, '...')); ?>
                    </a>
                  </td>
                  <td>
                    <?php if ($reporte['ruta_evidencia']): ?>
                      <a href="<?php echo htmlspecialchars($reporte['ruta_evidencia']); ?>" target="_blank" class="btn-accion btn-info btn-sm">
                        📎 Ver
                      </a>
                    <?php else: ?>
                      <span class="text-muted">Sin evidencia</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="<?php echo $reporte['estado'] == 'activo' ? 'estado-activo' : 'estado-inactivo'; ?>">
                      <?php echo ucfirst($reporte['estado']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay reportes grupales</h4>
          <p>No se han registrado reportes de conducta grupal para este grupo</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE CLASES ASIGNADAS -->
  <div id="seccion-clases" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Clases Asignadas al Grupo</h3>
        <div class="permiso-coordinador">
          <button class="btn btn-primary" onclick="abrirModalAsignarClases()">
            + Asignar Clases
          </button>
        </div>
      </div>

      <?php if (count($clases_grupo) > 0): ?>
        <div class="grid-clases">
          <?php foreach($clases_grupo as $clase): ?>
            <div class="tarjeta-clase">
              <h4 style="margin: 0 0 10px 0; color: #1565c0;"><?php echo htmlspecialchars($clase['materia_nombre']); ?></h4>
              <div style="font-size: 0.9em; color: #666;">
                <div><strong>Profesor:</strong> <?php echo htmlspecialchars($clase['profesor_nombre']); ?></div>
                <div><strong>Salón:</strong> <?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></div>
                <div><strong>Grupo:</strong> <?php echo $clase['clase_grupo']; ?></div>
                <div><strong>Días por semana:</strong> <?php echo $clase['dias_semana']; ?></div>
                <div><strong>Periodo:</strong> <?php echo htmlspecialchars($clase['periodo']); ?></div>
                <div><strong>Alumnos del grupo:</strong> 
                  <span style="font-weight: bold; color: #1565c0;">
                    <?php echo $clase['alumnos_grupo_asignados']; ?>/<?php echo $total_alumnos; ?>
                  </span>
                </div>
              </div>
              <?php if ($es_coordinador): ?>
              <div style="margin-top: 15px;">
                <button class="btn-accion btn-info btn-sm" onclick="verDetalleClase(<?php echo $clase['id_clase']; ?>)">
                  Ver Detalles
                </button>
                <button class="btn-accion btn-danger btn-sm" onclick="desasignarClase(<?php echo $clase['id_clase']; ?>, '<?php echo htmlspecialchars($clase['materia_nombre']); ?>')">
                  Desasignar
                </button>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay clases asignadas</h4>
          <p><?php echo $es_coordinador ? 'Asigna clases al grupo utilizando el botón de arriba' : 'No hay clases asignadas a este grupo'; ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE CONFIGURACIÓN (SOLO COORDINADORES) -->
  <?php if ($es_coordinador): ?>
  <div id="seccion-configuracion" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Configuración del Grupo</h3>
      </div>

      <form id="formConfiguracion" method="POST" action="acciones/actualizar_grupo.php">
        <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
        
        <div class="form-group">
          <label>Nombre del Grupo:</label>
          <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($grupo['nombre']); ?>" required>
        </div>

        <div class="form-group">
          <label>Semestre:</label>
          <select name="semestre" class="form-control" required>
            <?php for($i = 1; $i <= 12; $i++): ?>
              <option value="<?php echo $i; ?>" <?php echo $grupo['semestre'] == $i ? 'selected' : ''; ?>>
                Semestre <?php echo $i; ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Capacidad Máxima:</label>
          <input type="number" name="capacidad_maxima" class="form-control" value="<?php echo $grupo['capacidad_maxima']; ?>" min="1" max="100" required>
        </div>

        <div class="form-group">
          <label>Tutor Asignado:</label>
          <select name="tutor_asignado" class="form-control">
            <option value="">Sin tutor asignado</option>
            <?php foreach($profesores as $profesor): ?>
              <option value="<?php echo $profesor['id_profesor']; ?>" 
                <?php echo $grupo['tutor_asignado'] == $profesor['id_profesor'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos'] . ' (' . $profesor['clave'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Estado del Grupo:</label>
          <select name="activo" class="form-control" required>
            <option value="1" <?php echo $grupo['activo'] ? 'selected' : ''; ?>>Activo</option>
            <option value="0" <?php echo !$grupo['activo'] ? 'selected' : ''; ?>>Inactivo</option>
          </select>
        </div>

        <div class="acciones">
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
          <button type="button" class="btn btn-danger" onclick="confirmarEliminarGrupo()">
            Eliminar Grupo
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</main>

<!-- MODALES PARA COORDINADORES -->
<?php if ($es_coordinador): ?>
<!-- Modal para agregar alumno individual -->
<div id="modalAgregarAlumno" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Agregar Alumno al Grupo</h3>
      <span class="close" onclick="cerrarModal('modalAgregarAlumno')">&times;</span>
    </div>
    <form method="POST" action="acciones/agregar_alumno_grupo.php">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      
      <div class="form-group">
        <label>Seleccionar Alumno:</label>
        <select name="id_alumno" class="form-control" required>
          <option value="">Seleccionar alumno...</option>
          <?php foreach($alumnos_disponibles as $alumno): ?>
            <option value="<?php echo $alumno['id_alumno']; ?>">
              <?php echo htmlspecialchars($alumno['matricula'] . ' - ' . $alumno['nombre'] . ' ' . $alumno['apellidos'] . ' (Sem ' . $alumno['semestre'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-primary">Agregar Alumno</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalAgregarAlumno')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para importar alumnos desde CSV -->
<div id="modalImportarAlumnos" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Importar Alumnos desde CSV</h3>
      <span class="close" onclick="cerrarModal('modalImportarAlumnos')">&times;</span>
    </div>
    <form method="POST" action="acciones/importar_alumnos_grupo.php" enctype="multipart/form-data">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      
      <div class="form-group">
        <label>Archivo CSV:</label>
        <div class="upload-area">
          <p>📋 Subir archivo CSV con matrículas</p>
          <input type="file" name="archivo_csv" accept=".csv" required>
          <div class="info-text">
            <strong>Formato del archivo CSV:</strong><br>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 0.8em; margin: 10px 0;">
matricula
S25120001
S25120002
G25120015
T25120025</pre>
            Solo archivos CSV con matrículas en la primera columna
          </div>
        </div>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-primary">Importar Alumnos</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalImportarAlumnos')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para asignar clases -->
<div id="modalAsignarClases" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Asignar Clases al Grupo</h3>
      <span class="close" onclick="cerrarModal('modalAsignarClases')">&times;</span>
    </div>
    
    <?php if (count($clases_disponibles) > 0): ?>
      <form method="POST" action="acciones/asignar_clases_grupo.php">
        <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
        
        <div class="form-group">
          <label>Seleccionar Clases:</label>
          <div class="checkbox-grid" id="lista-clases">
            <?php foreach($clases_disponibles as $clase): ?>
              <div class="checkbox-item" data-clase-info="<?php echo htmlspecialchars(strtolower($clase['materia_nombre'] . ' ' . $clase['profesor_nombre'] . ' ' . $clase['salon'])); ?>">
                <input type="checkbox" name="clases[]" value="<?php echo $clase['id_clase']; ?>" id="clase_<?php echo $clase['id_clase']; ?>">
                <div class="checkbox-info">
                  <h5><?php echo htmlspecialchars($clase['materia_nombre']); ?></h5>
                  <p><strong>Profesor:</strong> <?php echo htmlspecialchars($clase['profesor_nombre']); ?></p>
                  <p><strong>Grupo:</strong> <?php echo $clase['grupo']; ?> | <strong>Salón:</strong> <?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></p>
                  <p><strong>Alumnos:</strong> <?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?> | <strong>Periodo:</strong> <?php echo htmlspecialchars($clase['periodo']); ?></p>
                  <?php if ($clase['especialidad_nombre']): ?>
                    <p><strong>Especialidad:</strong> <?php echo htmlspecialchars($clase['especialidad_nombre']); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="acciones">
          <button type="submit" class="btn btn-primary">Asignar Clases Seleccionadas</button>
          <button type="button" class="btn btn-info" onclick="seleccionarTodosCheckboxes(true)">
              ☑️ Seleccionar Todos
          </button>
          <button type="button" class="btn btn-secondary" onclick="seleccionarTodosCheckboxes(false)">
              ❌ Deseleccionar Todos
          </button>
          <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalAsignarClases')">Cancelar</button>
        </div>
      </form>
    <?php else: ?>
      <div class="empty-state">
        <h4>No hay clases disponibles</h4>
        <p>Todas las clases compatibles con este grupo ya están asignadas o no hay clases de la misma carrera y especialidad.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<!-- Modal para Justificante Individual -->
<div id="modalJustificanteIndividual" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Crear Justificante Individual</h3>
      <span class="close" onclick="cerrarModal('modalJustificanteIndividual')">&times;</span>
    </div>
    <form method="POST" action="acciones/crear_justificante.php" enctype="multipart/form-data">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      <input type="hidden" name="id_prefecto" value="<?php echo $id_prefecto; ?>">
      <input type="hidden" name="tipo_justificante" value="individual">
      
      <div class="form-group">
        <label>Alumno:</label>
        <select name="id_alumno" class="form-control" required id="selectAlumnoIndividual">
          <option value="">Seleccionar alumno...</option>
          <?php foreach($alumnos as $alumno): ?>
            <option value="<?php echo $alumno['id_alumno']; ?>">
              <?php echo htmlspecialchars($alumno['matricula'] . ' - ' . $alumno['nombre'] . ' ' . $alumno['apellidos']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de Justificante:</label>
        <select name="tipo_justificante" class="form-control" required>
          <option value="medico">Médico</option>
          <option value="personal">Personal</option>
          <option value="familiar">Familiar</option>
          <option value="oficial">Oficial</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Fecha de Fin:</label>
        <input type="date" name="fecha_fin" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Motivo:</label>
        <textarea name="motivo" class="form-control" rows="4" required placeholder="Describa el motivo del justificante..."></textarea>
      </div>

      <div class="form-group">
        <label>Comentario del Prefecto (Opcional):</label>
        <textarea name="comentario_prefecto" class="form-control" rows="3" placeholder="Comentarios adicionales..."></textarea>
      </div>

      <div class="form-group">
        <label>Documento de Justificante (Opcional):</label>
        <div class="upload-area">
          <p>📎 Adjuntar documento del justificante</p>
          <input type="file" name="documento_justificante" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
          <div class="info-text">
            Formatos permitidos: JPG, PNG, PDF, DOC (Máx. 5MB)
          </div>
        </div>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-prefecto">Crear Justificante</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalJustificanteIndividual')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para Justificantes Múltiples -->
<div id="modalJustificanteMultiple" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Crear Justificantes Múltiples</h3>
      <span class="close" onclick="cerrarModal('modalJustificanteMultiple')">&times;</span>
    </div>
    <form method="POST" action="acciones/crear_justificante.php" enctype="multipart/form-data">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      <input type="hidden" name="id_prefecto" value="<?php echo $id_prefecto; ?>">
      <input type="hidden" name="tipo_justificante" value="multiple">
      
      <div class="form-group">
        <label>Seleccionar Alumnos:</label>
        <div class="checkbox-grid" style="max-height: 300px;">
          <?php foreach($alumnos as $alumno): ?>
            <div class="checkbox-item">
              <input type="checkbox" name="alumnos_seleccionados[]" value="<?php echo $alumno['id_alumno']; ?>" id="alumno_<?php echo $alumno['id_alumno']; ?>">
              <div class="checkbox-info">
                <h5><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></h5>
                <p><strong>Matrícula:</strong> <?php echo htmlspecialchars($alumno['matricula']); ?></p>
                <p><strong>Semestre:</strong> <?php echo $alumno['semestre']; ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top: 10px;">
          <button type="button" class="btn btn-sm btn-info" onclick="seleccionarTodosAlumnos(true)">☑️ Seleccionar Todos</button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="seleccionarTodosAlumnos(false)">❌ Deseleccionar Todos</button>
        </div>
      </div>

      <div class="form-group">
        <label>Tipo de Justificante:</label>
        <select name="tipo_justificante" class="form-control" required>
          <option value="medico">Médico</option>
          <option value="personal">Personal</option>
          <option value="familiar">Familiar</option>
          <option value="oficial">Oficial</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Fecha de Fin:</label>
        <input type="date" name="fecha_fin" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Motivo (aplicará a todos los alumnos seleccionados):</label>
        <textarea name="motivo" class="form-control" rows="4" required placeholder="Describa el motivo del justificante..."></textarea>
      </div>

      <div class="form-group">
        <label>Comentario del Prefecto (Opcional):</label>
        <textarea name="comentario_prefecto" class="form-control" rows="3" placeholder="Comentarios adicionales..."></textarea>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-warning">Crear Justificantes</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalJustificanteMultiple')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<!-- MODALES PARA REPORTES DE TUTORES -->
<?php if ($es_tutor): ?>
<!-- Modal para nuevo reporte individual -->
<div id="modalNuevoReporteIndividual" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Nuevo Reporte Individual</h3>
      <span class="close" onclick="cerrarModal('modalNuevoReporteIndividual')">&times;</span>
    </div>
    <form method="POST" action="acciones/nuevo_reporte_individual.php">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      
      <div class="form-group">
        <label>Alumno:</label>
        <select name="id_alumno" class="form-control" required>
          <option value="">Seleccionar alumno...</option>
          <?php foreach($alumnos as $alumno): ?>
            <option value="<?php echo $alumno['id_alumno']; ?>">
              <?php echo htmlspecialchars($alumno['matricula'] . ' - ' . $alumno['nombre'] . ' ' . $alumno['apellidos']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Clase (Opcional):</label>
        <select name="id_clase" class="form-control">
          <option value="">Sin clase específica</option>
          <?php foreach($clases_grupo as $clase): ?>
            <option value="<?php echo $clase['id_clase']; ?>">
              <?php echo htmlspecialchars($clase['materia_nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de Incidencia:</label>
        <select name="tipo_incidencia" class="form-control" required>
          <option value="leve">Leve</option>
          <option value="grave">Grave</option>
          <option value="muy_grave">Muy Grave</option>
        </select>
      </div>

      <div class="form-group">
        <label>Categoría:</label>
        <select name="categoria" class="form-control" required>
          <option value="comportamiento">Comportamiento</option>
          <option value="disciplina">Disciplina</option>
          <option value="respeto">Respeto</option>
          <option value="responsabilidad">Responsabilidad</option>
          <option value="convivencia">Convivencia</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha y Hora del Incidente:</label>
        <input type="datetime-local" name="fecha_incidente" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Descripción:</label>
        <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describa detalladamente el incidente..."></textarea>
      </div>

      <div class="form-group">
        <label>Medidas Tomadas:</label>
        <textarea name="medidas_tomadas" class="form-control" rows="3" placeholder="Describa las medidas tomadas..."></textarea>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-primary">Guardar Reporte</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevoReporteIndividual')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para nuevo reporte grupal -->
<div id="modalNuevoReporteGrupal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Nuevo Reporte Grupal</h3>
      <span class="close" onclick="cerrarModal('modalNuevoReporteGrupal')">&times;</span>
    </div>
    <form method="POST" action="acciones/nuevo_reporte_grupal.php">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      
      <div class="form-group">
        <label>Clase (Opcional):</label>
        <select name="id_clase" class="form-control">
          <option value="">Sin clase específica</option>
          <?php foreach($clases_grupo as $clase): ?>
            <option value="<?php echo $clase['id_clase']; ?>">
              <?php echo htmlspecialchars($clase['materia_nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de Incidencia:</label>
        <select name="tipo_incidencia" class="form-control" required>
          <option value="leve">Leve</option>
          <option value="grave">Grave</option>
          <option value="muy_grave">Muy Grave</option>
        </select>
      </div>

      <div class="form-group">
        <label>Categoría:</label>
        <select name="categoria" class="form-control" required>
          <option value="comportamiento_grupal">Comportamiento Grupal</option>
          <option value="disciplina">Disciplina</option>
          <option value="convivencia">Convivencia</option>
          <option value="responsabilidad">Responsabilidad</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha y Hora del Incidente:</label>
        <input type="datetime-local" name="fecha_incidente" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Descripción:</label>
        <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describa detalladamente el incidente grupal..."></textarea>
      </div>

      <div class="form-group">
        <label>Alumnos Involucrados (opcional):</label>
        <textarea name="alumnos_involucrados" class="form-control" rows="3" placeholder="Liste los alumnos involucrados, separados por coma..."></textarea>
      </div>

      <div class="form-group">
        <label>Medidas Tomadas:</label>
        <textarea name="medidas_tomadas" class="form-control" rows="3" placeholder="Describa las medidas tomadas..."></textarea>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-primary">Guardar Reporte</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevoReporteGrupal')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODALES PARA REPORTES DE PREFECTOS -->
<?php if ($es_prefecto): ?>
<!-- Modal para nuevo reporte individual (Prefecto) -->
<div id="modalNuevoReporteIndividualPrefecto" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Nuevo Reporte Individual - Prefecto</h3>
      <span class="close" onclick="cerrarModal('modalNuevoReporteIndividualPrefecto')">&times;</span>
    </div>
    <form method="POST" action="acciones/nuevo_reporte_individual.php" enctype="multipart/form-data">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      <input type="hidden" name="id_prefecto" value="<?php echo $id_prefecto; ?>">
      
      <div class="form-group">
        <label>Alumno:</label>
        <select name="id_alumno" class="form-control" required>
          <option value="">Seleccionar alumno...</option>
          <?php foreach($alumnos as $alumno): ?>
            <option value="<?php echo $alumno['id_alumno']; ?>">
              <?php echo htmlspecialchars($alumno['matricula'] . ' - ' . $alumno['nombre'] . ' ' . $alumno['apellidos']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Clase (Opcional):</label>
        <select name="id_clase" class="form-control">
          <option value="">Sin clase específica</option>
          <?php foreach($clases_grupo as $clase): ?>
            <option value="<?php echo $clase['id_clase']; ?>">
              <?php echo htmlspecialchars($clase['materia_nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de Incidencia:</label>
        <select name="tipo_incidencia" class="form-control" required>
          <option value="leve">Leve</option>
          <option value="grave">Grave</option>
          <option value="muy_grave">Muy Grave</option>
        </select>
      </div>

      <div class="form-group">
        <label>Categoría:</label>
        <select name="categoria" class="form-control" required>
          <option value="comportamiento">Comportamiento</option>
          <option value="disciplina">Disciplina</option>
          <option value="respeto">Respeto</option>
          <option value="responsabilidad">Responsabilidad</option>
          <option value="convivencia">Convivencia</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha y Hora del Incidente:</label>
        <input type="datetime-local" name="fecha_incidente" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Descripción:</label>
        <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describa detalladamente el incidente..."></textarea>
      </div>

      <div class="form-group">
        <label>Medidas Tomadas:</label>
        <textarea name="medidas_tomadas" class="form-control" rows="3" placeholder="Describa las medidas tomadas..."></textarea>
      </div>

      <div class="form-group">
        <label>Evidencia (Opcional):</label>
        <div class="upload-area upload-area-evidencias">
          <p>📎 Adjuntar evidencia (imágenes, documentos, etc.)</p>
          <input type="file" name="evidencia" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" id="evidenciaIndividual">
          <div class="info-text">
            Formatos permitidos: JPG, PNG, PDF, DOC (Máx. 5MB)
          </div>
          <img id="previewEvidenciaIndividual" class="evidencia-preview" alt="Vista previa de evidencia">
        </div>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-prefecto">Guardar Reporte</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevoReporteIndividualPrefecto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para nuevo reporte grupal (Prefecto) -->
<div id="modalNuevoReporteGrupalPrefecto" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Nuevo Reporte Grupal - Prefecto</h3>
      <span class="close" onclick="cerrarModal('modalNuevoReporteGrupalPrefecto')">&times;</span>
    </div>
    <form method="POST" action="acciones/nuevo_reporte_grupal.php" enctype="multipart/form-data">
      <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
      <input type="hidden" name="id_prefecto" value="<?php echo $id_prefecto; ?>">
      
      <div class="form-group">
        <label>Clase (Opcional):</label>
        <select name="id_clase" class="form-control">
          <option value="">Sin clase específica</option>
          <?php foreach($clases_grupo as $clase): ?>
            <option value="<?php echo $clase['id_clase']; ?>">
              <?php echo htmlspecialchars($clase['materia_nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de Incidencia:</label>
        <select name="tipo_incidencia" class="form-control" required>
          <option value="leve">Leve</option>
          <option value="grave">Grave</option>
          <option value="muy_grave">Muy Grave</option>
        </select>
      </div>

      <div class="form-group">
        <label>Categoría:</label>
        <select name="categoria" class="form-control" required>
          <option value="comportamiento_grupal">Comportamiento Grupal</option>
          <option value="disciplina">Disciplina</option>
          <option value="convivencia">Convivencia</option>
          <option value="responsabilidad">Responsabilidad</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha y Hora del Incidente:</label>
        <input type="datetime-local" name="fecha_incidente" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Descripción:</label>
        <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describa detalladamente el incidente grupal..."></textarea>
      </div>

      <div class="form-group">
        <label>Alumnos Involucrados (opcional):</label>
        <textarea name="alumnos_involucrados" class="form-control" rows="3" placeholder="Liste los alumnos involucrados, separados por coma..."></textarea>
      </div>

      <div class="form-group">
        <label>Medidas Tomadas:</label>
        <textarea name="medidas_tomadas" class="form-control" rows="3" placeholder="Describa las medidas tomadas..."></textarea>
      </div>

      <div class="form-group">
        <label>Evidencia (Opcional):</label>
        <div class="upload-area upload-area-evidencias">
          <p>📎 Adjuntar evidencia (imágenes, documentos, etc.)</p>
          <input type="file" name="evidencia" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" id="evidenciaGrupal">
          <div class="info-text">
            Formatos permitidos: JPG, PNG, PDF, DOC (Máx. 5MB)
          </div>
          <img id="previewEvidenciaGrupal" class="evidencia-preview" alt="Vista previa de evidencia">
        </div>
      </div>

      <div class="acciones">
        <button type="submit" class="btn btn-prefecto">Guardar Reporte</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevoReporteGrupalPrefecto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
        // Funciones para prefectos
<?php if ($es_prefecto): ?>
function abrirModalNuevoReporteIndividualPrefecto() {
  document.getElementById('modalNuevoReporteIndividualPrefecto').style.display = 'block';
}

function abrirModalNuevoReporteGrupalPrefecto() {
  document.getElementById('modalNuevoReporteGrupalPrefecto').style.display = 'block';
}

// NUEVAS FUNCIONES PARA JUSTIFICANTES
function abrirModalJustificanteIndividual() {
  document.getElementById('modalJustificanteIndividual').style.display = 'block';
}

function abrirModalJustificanteMultiple() {
  document.getElementById('modalJustificanteMultiple').style.display = 'block';
}

function seleccionarTodosAlumnos(selectAll) {
  const checkboxes = document.querySelectorAll('input[name="alumnos_seleccionados[]"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = selectAll;
  });
}
<?php endif; ?>
function mostrarSeccion(seccion) {
  // Ocultar todas las secciones
  document.querySelectorAll('.seccion').forEach(sec => {
    sec.classList.remove('activa');
  });
  
  // Mostrar la sección seleccionada
  document.getElementById(`seccion-${seccion}`).classList.add('activa');
  
  // Actualizar botones activos
  document.querySelectorAll('.btn-principal').forEach(btn => {
    btn.classList.remove('active');
  });
  event.target.classList.add('active');

  // Mostrar/ocultar barra de búsqueda de clases
  const barraBusquedaClases = document.getElementById('barra-busqueda-clases');
  if (barraBusquedaClases) {
    if (seccion === 'clases') {
      barraBusquedaClases.style.display = 'flex';
    } else {
      barraBusquedaClases.style.display = 'none';
    }
  }
}

// Funciones para coordinadores
<?php if ($es_coordinador): ?>
function abrirModalAgregarAlumno() {
  document.getElementById('modalAgregarAlumno').style.display = 'block';
}

function abrirModalImportarAlumnos() {
  document.getElementById('modalImportarAlumnos').style.display = 'block';
}

function abrirModalAsignarClases() {
  document.getElementById('modalAsignarClases').style.display = 'block';
}

function eliminarAlumnoGrupo(idAlumno, nombreAlumno) {
  if (confirm(`¿Estás seguro de que quieres eliminar a ${nombreAlumno} del grupo?`)) {
    window.location.href = `acciones/eliminar_alumno_grupo.php?id_alumno=${idAlumno}&id_grupo=<?php echo $id_grupo; ?>`;
  }
}

function desasignarClase(idClase, nombreClase) {
  if (confirm(`¿Estás seguro de que quieres desasignar la clase "${nombreClase}" del grupo?`)) {
    window.location.href = `acciones/desasignar_clase_grupo.php?id_clase=${idClase}&id_grupo=<?php echo $id_grupo; ?>`;
  }
}

function confirmarEliminarGrupo() {
  if (confirm('¿Estás seguro de que quieres eliminar este grupo? Esta acción no se puede deshacer.')) {
    window.location.href = `acciones/eliminar_grupo.php?id=<?php echo $id_grupo; ?>`;
  }
}
<?php endif; ?>

// Funciones para tutores
<?php if ($es_tutor): ?>
function abrirModalNuevoReporteIndividual() {
  document.getElementById('modalNuevoReporteIndividual').style.display = 'block';
}

function abrirModalNuevoReporteGrupal() {
  document.getElementById('modalNuevoReporteGrupal').style.display = 'block';
}
<?php endif; ?>

// Funciones para prefectos
<?php if ($es_prefecto): ?>
function abrirModalNuevoReporteIndividualPrefecto() {
  document.getElementById('modalNuevoReporteIndividualPrefecto').style.display = 'block';
}

function abrirModalNuevoReporteGrupalPrefecto() {
  document.getElementById('modalNuevoReporteGrupalPrefecto').style.display = 'block';
}
<?php endif; ?>

// Funciones comunes
function cerrarModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}

function verDetalleAlumno(idAlumno) {
  window.open(`detalle_alumno.php?id=${idAlumno}`, '_blank');
}

function verDetalleClase(idClase) {
  window.open(`detalle_clase.php?id=${idClase}`, '_blank');
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
  document.querySelectorAll('.modal').forEach(modal => {
    if (event.target == modal) {
      modal.style.display = 'none';
    }
  });
}

// Seleccionar/deseleccionar todos los checkboxes
function seleccionarTodosCheckboxes(selectAll) {
  const checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = selectAll;
  });
}

// Búsqueda de clases para coordinadores
<?php if ($es_coordinador): ?>
document.addEventListener('DOMContentLoaded', function() {
  const barraBusquedaClases = document.getElementById('buscarClase');
  const clases = document.querySelectorAll('.checkbox-item');
  
  if (barraBusquedaClases && clases.length > 0) {
    barraBusquedaClases.addEventListener('input', function() {
      const terminoBusqueda = this.value.toLowerCase().trim();
      
      clases.forEach(function(clase) {
        const claseInfo = clase.getAttribute('data-clase-info');
        
        if (claseInfo.includes(terminoBusqueda)) {
          clase.style.display = 'flex';
        } else {
          clase.style.display = 'none';
        }
      });
    });
  }
});
<?php endif; ?>

// Vista previa de evidencias para prefectos
<?php if ($es_prefecto): ?>
document.addEventListener('DOMContentLoaded', function() {
  // Para reporte individual
  const evidenciaIndividual = document.getElementById('evidenciaIndividual');
  const previewIndividual = document.getElementById('previewEvidenciaIndividual');
  
  if (evidenciaIndividual && previewIndividual) {
    evidenciaIndividual.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          previewIndividual.src = e.target.result;
          previewIndividual.style.display = 'block';
        }
        reader.readAsDataURL(file);
      } else {
        previewIndividual.style.display = 'none';
      }
    });
  }

  // Para reporte grupal
  const evidenciaGrupal = document.getElementById('evidenciaGrupal');
  const previewGrupal = document.getElementById('previewEvidenciaGrupal');
  
  if (evidenciaGrupal && previewGrupal) {
    evidenciaGrupal.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          previewGrupal.src = e.target.result;
          previewGrupal.style.display = 'block';
        }
        reader.readAsDataURL(file);
      } else {
        previewGrupal.style.display = 'none';
      }
    });
  }
});
<?php endif; ?>
</script>

<?php include "footer.php"; ?>