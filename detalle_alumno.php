<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != '3') {
    header("Location: login.php");
    exit;
}

$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_alumno == 0) {
    header("Location: coordinador.php?seccion=grupos");
    exit;
}

// Obtener información completa del alumno
$sql_alumno = "
    SELECT 
        a.*,
        u.clave,
        u.nombre,
        u.apellidos,
        u.fecha_nacimiento,
        u.correo,
        c.nombre as carrera_nombre,
        e.nombre as especialidad_nombre,
        ag.id_grupo,
        g.nombre as grupo_nombre
    FROM alumno a
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    INNER JOIN carrera c ON u.id_carrera = c.id_carrera
    LEFT JOIN especialidad e ON a.id_especialidad = e.id_especialidad
    LEFT JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno AND ag.activo = 1
    LEFT JOIN grupo g ON ag.id_grupo = g.id_grupo
    WHERE a.id_alumno = ?
";

$stmt_alumno = $conexion->prepare($sql_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$alumno = $stmt_alumno->get_result()->fetch_assoc();

if (!$alumno) {
    header("Location: coordinador.php?seccion=grupos&error=Alumno no encontrado");
    exit;
}

// Obtener materias cursadas del alumno
$sql_materias_cursadas = "
    SELECT 
        mc.*,
        m.nombre as materia_nombre,
        m.creditos,
        c.grupo as clase_grupo,
        CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre
    FROM materia_cursada mc
    INNER JOIN materia m ON mc.id_materia = m.id_materia
    LEFT JOIN clase c ON mc.id_clase = c.id_clase
    LEFT JOIN profesor p ON c.id_profesor = p.id_profesor
    LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE mc.id_alumno = ?
    ORDER BY mc.periodo DESC, m.nombre
";

$stmt_materias = $conexion->prepare($sql_materias_cursadas);
$stmt_materias->bind_param("i", $id_alumno);
$stmt_materias->execute();
$materias_cursadas = $stmt_materias->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener materias actuales (asignaciones activas)
$sql_materias_actuales = "
    SELECT 
        a.*,
        m.nombre as materia_nombre,
        m.creditos,
        m.unidades,
        c.grupo as clase_grupo,
        CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
        s.nombre as salon,
        s.edificio,
        c.periodo
    FROM asignacion a
    INNER JOIN clase c ON a.id_clase = c.id_clase
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    INNER JOIN salon s ON c.id_salon = s.id_salon
    WHERE a.id_alumno = ? AND c.activo = 1
    ORDER BY m.nombre
";

$stmt_actuales = $conexion->prepare($sql_materias_actuales);
$stmt_actuales->bind_param("i", $id_alumno);
$stmt_actuales->execute();
$materias_actuales = $stmt_actuales->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener reportes de conducta del alumno
$sql_reportes = "
    SELECT 
        rci.*,
        CONCAT(u.nombre, ' ', u.apellidos) as prefecto_nombre,
        m.nombre as materia_nombre,
        c.grupo as clase_grupo
    FROM reportes_conducta_individual rci
    INNER JOIN prefecto pre ON rci.id_prefecto = pre.id_prefecto
    INNER JOIN usuario u ON pre.id_usuario = u.id_usuario
    LEFT JOIN clase c ON rci.id_clase = c.id_clase
    LEFT JOIN materia m ON c.id_materia = m.id_materia
    WHERE rci.id_alumno = ?
    ORDER BY rci.fecha_incidente DESC
    LIMIT 20
";

$stmt_reportes = $conexion->prepare($sql_reportes);
$stmt_reportes->bind_param("i", $id_alumno);
$stmt_reportes->execute();
$reportes = $stmt_reportes->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener tareas del alumno
$sql_tareas = "
    SELECT 
        t.*,
        et.calificacion,
        et.fecha_entrega,
        et.estado as estado_entrega,
        m.nombre as materia_nombre,
        c.grupo as clase_grupo,
        CASE 
            WHEN t.estado = 'cancelada' THEN 'cancelada'
            WHEN t.fecha_limite < NOW() AND et.id_entrega IS NULL THEN 'vencida'
            WHEN et.id_entrega IS NULL THEN 'pendiente'
            WHEN et.calificacion IS NOT NULL THEN 'calificada'
            ELSE 'entregada'
        END as estado_tarea
    FROM tareas t
    INNER JOIN clase c ON t.id_clase = c.id_clase
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN asignacion a ON c.id_clase = a.id_clase
    LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_alumno = ?
    WHERE a.id_alumno = ? AND c.activo = 1
    ORDER BY t.fecha_limite DESC
    LIMIT 15
";

$stmt_tareas = $conexion->prepare($sql_tareas);
$stmt_tareas->bind_param("ii", $id_alumno, $id_alumno);
$stmt_tareas->execute();
$tareas = $stmt_tareas->get_result()->fetch_all(MYSQLI_ASSOC);

// Calcular estadísticas
$total_materias_cursadas = count($materias_cursadas);
$materias_aprobadas = count(array_filter($materias_cursadas, function($m) { return $m['aprobado'] == 1; }));
$materias_reprobadas = $total_materias_cursadas - $materias_aprobadas;
$materias_actuales_count = count($materias_actuales);
$tareas_pendientes = count(array_filter($tareas, function($t) { return $t['estado_tarea'] == 'pendiente'; }));
$reportes_activos = count(array_filter($reportes, function($r) { return $r['estado'] == 'activo'; }));

// Función para calcular edad
function calcularEdad($fecha_nacimiento) {
    $nacimiento = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
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

/* BANNER INFORMACIÓN DEL ALUMNO */
.banner-alumno {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 40px 30px;
  border-radius: var(--radio-borde);
  box-shadow: var(--sombra-suave);
  margin-bottom: 30px;
  position: relative;
  overflow: hidden;
}

.banner-alumno::before {
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

.banner-titulo .alumno-info {
  font-size: 1.1em;
  opacity: 0.9;
}

.banner-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 15px;
  position: relative;
  z-index: 2;
}

.stat-card {
  background: rgba(255,255,255,0.15);
  padding: 12px;
  border-radius: 10px;
  text-align: center;
  backdrop-filter: blur(10px);
}

.stat-number {
  font-size: 1.8em;
  font-weight: 700;
  margin-bottom: 3px;
}

.stat-label {
  font-size: 0.8em;
  opacity: 0.9;
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

.badge-aprobado {
  background: #e8f5e8;
  color: #2e7d32;
}

.badge-reprobado {
  background: #ffebee;
  color: #c62828;
}

.badge-cursando {
  background: #e3f2fd;
  color: #1565c0;
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

.badge-entregado {
  background: #e8f5e8;
  color: #2e7d32;
}

.badge-pendiente {
  background: #fff3e0;
  color: #f57c00;
}

.badge-vencida {
  background: #ffebee;
  color: #c62828;
}

.badge-calificada {
  background: #e3f2fd;
  color: #1565c0;
}

.estado-activo {
  color: #28a745;
  font-weight: 600;
}

.estado-inactivo {
  color: #dc3545;
  font-weight: 600;
}

/* INFORMACIÓN PERSONAL */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.info-item {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  border-left: 4px solid var(--color-primario);
}

.info-label {
  font-weight: 600;
  color: #555;
  font-size: 0.9em;
  margin-bottom: 5px;
}

.info-value {
  color: var(--color-texto);
  font-size: 1em;
}

/* PROGRESS BAR */
.progress-container {
  background: #e0e0e0;
  border-radius: 10px;
  height: 10px;
  margin: 10px 0;
}

.progress-bar {
  height: 100%;
  border-radius: 10px;
  background: linear-gradient(90deg, #28a745, #20c997);
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
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .acciones-tabla {
    flex-direction: column;
    gap: 3px;
  }
}
</style>

<!-- BANNER INFORMACIÓN DEL ALUMNO -->
<section class="banner-alumno">
  <div class="banner-header">
    <div class="banner-titulo">
      <h1><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></h1>
      <div class="alumno-info">
        <strong>Matrícula:</strong> <?php echo htmlspecialchars($alumno['clave']); ?>
        | <strong>Carrera:</strong> <?php echo htmlspecialchars($alumno['carrera_nombre']); ?>
        | <strong>Semestre:</strong> <?php echo $alumno['semestre']; ?>
        <?php if ($alumno['grupo_nombre']): ?>
          | <strong>Grupo:</strong> <?php echo htmlspecialchars($alumno['grupo_nombre']); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="banner-stats">
    <div class="stat-card">
      <div class="stat-number"><?php echo number_format($alumno['promedio'], 2); ?></div>
      <div class="stat-label">Promedio General</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $materias_aprobadas; ?></div>
      <div class="stat-label">Materias Aprobadas</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $materias_actuales_count; ?></div>
      <div class="stat-label">Materias Actuales</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $tareas_pendientes; ?></div>
      <div class="stat-label">Tareas Pendientes</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $reportes_activos; ?></div>
      <div class="stat-label">Reportes Activos</div>
    </div>
  </div>
</section>

<main class="content">
  <!-- BOTONES PRINCIPALES -->
  <div class="botones-principales">
    <button class="btn-principal active" onclick="mostrarSeccion('informacion')">
       Información Personal
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('materias-actuales')">
       Materias Actuales
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('historial-academico')">
       Historial Académico
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('tareas')">
       Tareas y Entregas
    </button>
    <button class="btn-principal" onclick="mostrarSeccion('reportes')">
       Reportes de Conducta
    </button>
  </div>

  <!-- SECCIÓN DE INFORMACIÓN PERSONAL -->
  <div id="seccion-informacion" class="seccion activa">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Información Personal</h3>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Matrícula</div>
          <div class="info-value"><?php echo htmlspecialchars($alumno['clave']); ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Nombre Completo</div>
          <div class="info-value"><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Correo Electrónico</div>
          <div class="info-value"><?php echo htmlspecialchars($alumno['correo']); ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Fecha de Nacimiento</div>
          <div class="info-value">
            <?php echo date('d/m/Y', strtotime($alumno['fecha_nacimiento'])); ?>
            (<?php echo calcularEdad($alumno['fecha_nacimiento']); ?> años)
          </div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Carrera</div>
          <div class="info-value"><?php echo htmlspecialchars($alumno['carrera_nombre']); ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Especialidad</div>
          <div class="info-value">
            <?php echo $alumno['especialidad_nombre'] ? htmlspecialchars($alumno['especialidad_nombre']) : 'Sin especialidad'; ?>
          </div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Semestre Actual</div>
          <div class="info-value"><?php echo $alumno['semestre']; ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Promedio General</div>
          <div class="info-value">
            <strong style="font-size: 1.2em;"><?php echo number_format($alumno['promedio'], 2); ?></strong>
            <div class="progress-container">
              <div class="progress-bar" style="width: <?php echo min($alumno['promedio'], 100); ?>%"></div>
            </div>
          </div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Grupo Actual</div>
          <div class="info-value">
            <?php echo $alumno['grupo_nombre'] ? htmlspecialchars($alumno['grupo_nombre']) : 'Sin grupo asignado'; ?>
          </div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Estado</div>
          <div class="info-value">
            <span class="<?php echo $alumno['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>">
              <?php 
              switch($alumno['estado']) {
                case '1': echo 'Activo'; break;
                case '2': echo 'Egresado'; break;
                case '3': echo 'Baja Temporal'; break;
                case '4': echo 'Baja Definitiva'; break;
                default: echo 'Desconocido';
              }
              ?>
            </span>
          </div>
        </div>
        
        <div class="info-item">
          <div class="info-label">Año de Inscripción</div>
          <div class="info-value"><?php echo date('Y', strtotime($alumno['año_inscripcion'])); ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- SECCIÓN DE MATERIAS ACTUALES -->
  <div id="seccion-materias-actuales" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Materias Actuales (<?php echo $materias_actuales_count; ?>)</h3>
      </div>

      <?php if ($materias_actuales_count > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Materia</th>
                <th>Grupo</th>
                <th>Profesor</th>
                <th>Salón</th>
                <th>Créditos</th>
                <th>Unidades</th>
                <th>Periodo</th>
                <th>Oportunidad</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($materias_actuales as $materia): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($materia['materia_nombre']); ?></strong></td>
                  <td><?php echo $materia['clase_grupo']; ?></td>
                  <td><?php echo htmlspecialchars($materia['profesor_nombre']); ?></td>
                  <td><?php echo $materia['salon'] . ' - ' . $materia['edificio']; ?></td>
                  <td><?php echo $materia['creditos']; ?></td>
                  <td><?php echo $materia['unidades']; ?></td>
                  <td><?php echo htmlspecialchars($materia['periodo']); ?></td>
                  <td>
                    <span class="badge badge-cursando">
                      <?php echo ucfirst($materia['oportunidad']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay materias actuales</h4>
          <p>El alumno no está inscrito en ninguna materia este periodo.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE HISTORIAL ACADÉMICO -->
  <div id="seccion-historial-academico" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Historial Académico (<?php echo $total_materias_cursadas; ?> materias)</h3>
      </div>

      <?php if ($total_materias_cursadas > 0): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
          <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #2e7d32;"><?php echo $materias_aprobadas; ?></div>
            <div style="color: #2e7d32;">Materias Aprobadas</div>
          </div>
          <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #c62828;"><?php echo $materias_reprobadas; ?></div>
            <div style="color: #c62828;">Materias Reprobadas</div>
          </div>
        </div>

        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Materia</th>
                <th>Calificación Final</th>
                <th>Oportunidad</th>
                <th>Periodo</th>
                <th>Grupo</th>
                <th>Profesor</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($materias_cursadas as $materia): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($materia['materia_nombre']); ?></strong></td>
                  <td>
                    <strong style="font-size: 1.1em;">
                      <?php echo number_format($materia['cal_final'], 2); ?>
                    </strong>
                  </td>
                  <td><?php echo ucfirst($materia['oportunidad']); ?></td>
                  <td>
                    <?php echo $materia['periodo'] ? date('m/Y', strtotime($materia['periodo'])) : 'N/A'; ?>
                  </td>
                  <td><?php echo $materia['clase_grupo'] ?: 'N/A'; ?></td>
                  <td><?php echo $materia['profesor_nombre'] ? htmlspecialchars($materia['profesor_nombre']) : 'N/A'; ?></td>
                  <td>
                    <?php if ($materia['aprobado']): ?>
                      <span class="badge badge-aprobado">Aprobado</span>
                    <?php else: ?>
                      <span class="badge badge-reprobado">Reprobado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay historial académico</h4>
          <p>El alumno no ha cursado ninguna materia aún.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE TAREAS -->
  <div id="seccion-tareas" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Tareas y Entregas (<?php echo count($tareas); ?>)</h3>
      </div>

      <?php if (count($tareas) > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Tarea</th>
                <th>Materia</th>
                <th>Grupo</th>
                <th>Fecha Límite</th>
                <th>Fecha Entrega</th>
                <th>Calificación</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($tareas as $tarea): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($tarea['titulo']); ?></strong></td>
                  <td><?php echo htmlspecialchars($tarea['materia_nombre']); ?></td>
                  <td><?php echo $tarea['clase_grupo']; ?></td>
                  <td><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?></td>
                  <td>
                    <?php echo $tarea['fecha_entrega'] ? date('d/m/Y H:i', strtotime($tarea['fecha_entrega'])) : 'No entregada'; ?>
                  </td>
                  <td>
                    <?php if ($tarea['calificacion'] !== null): ?>
                      <strong><?php echo $tarea['calificacion']; ?>/<?php echo $tarea['puntos_maximos']; ?></strong>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php 
                    $badge_class = '';
                    switch($tarea['estado_tarea']) {
                      case 'entregada': $badge_class = 'badge-entregado'; break;
                      case 'pendiente': $badge_class = 'badge-pendiente'; break;
                      case 'vencida': $badge_class = 'badge-vencida'; break;
                      case 'calificada': $badge_class = 'badge-calificada'; break;
                      case 'cancelada': $badge_class = 'badge-reprobado'; break;
                    }
                    ?>
                    <span class="badge <?php echo $badge_class; ?>">
                      <?php echo ucfirst($tarea['estado_tarea']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h4>No hay tareas registradas</h4>
          <p>No se han asignado tareas al alumno o no hay registros de entregas.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE REPORTES -->
  <div id="seccion-reportes" class="seccion">
    <div class="tarjeta-contenido">
      <div class="tarjeta-header">
        <h3>Reportes de Conducta (<?php echo count($reportes); ?>)</h3>
      </div>

      <?php if (count($reportes) > 0): ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead>
              <tr>
                <th>Fecha Incidente</th>
                <th>Prefecto</th>
                <th>Tipo</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Materia/Grupo</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reportes as $reporte): ?>
                <tr>
                  <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_incidente'])); ?></td>
                  <td><strong><?php echo htmlspecialchars($reporte['prefecto_nombre']); ?></strong></td>
                  <td>
                    <span class="badge badge-<?php echo str_replace('_', '-', $reporte['tipo_incidencia']); ?>">
                      <?php echo ucfirst(str_replace('_', ' ', $reporte['tipo_incidencia'])); ?>
                    </span>
                  </td>
                  <td><?php echo ucfirst(str_replace('_', ' ', $reporte['categoria'])); ?></td>
                  <td style="max-width: 200px;">
                    <?php echo htmlspecialchars($reporte['descripcion']); ?>
                  </td>
                  <td>
                    <?php 
                    if ($reporte['materia_nombre']) {
                      echo htmlspecialchars($reporte['materia_nombre']);
                      if ($reporte['clase_grupo']) {
                        echo ' (' . $reporte['clase_grupo'] . ')';
                      }
                    } else {
                      echo 'N/A';
                    }
                    ?>
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
          <h4>No hay reportes de conducta</h4>
          <p>No se han registrado reportes de conducta para este alumno.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
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
}
</script>

<?php include "footer.php"; ?>