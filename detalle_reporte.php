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

$id_reporte = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'individual'; // 'individual' o 'grupal'

if ($id_reporte == 0) {
    header("Location: " . ($_SESSION['rol'] == '3' ? 'coordinador.php' : ($_SESSION['rol'] == '2' ? 'profesor.php' : 'prefecto.php')));
    exit;
}

// Obtener información del reporte según el tipo
if ($tipo_reporte == 'individual') {
    $sql_reporte = "
        SELECT 
            rci.*,
            u_alumno.nombre as alumno_nombre,
            u_alumno.apellidos as alumno_apellidos,
            u_alumno.clave as alumno_matricula,
            m.nombre as materia_nombre,
            c.grupo as clase_grupo,
            CONCAT(u_profesor.nombre, ' ', u_profesor.apellidos) as profesor_nombre,
            CONCAT(u_coordinador.nombre, ' ', u_coordinador.apellidos) as coordinador_nombre,
            CONCAT(u_prefecto.nombre, ' ', u_prefecto.apellidos) as prefecto_nombre,
            g.nombre as grupo_nombre,
            car.nombre as carrera_nombre
        FROM reportes_conducta_individual rci
        INNER JOIN alumno a ON rci.id_alumno = a.id_alumno
        INNER JOIN usuario u_alumno ON a.id_usuario = u_alumno.id_usuario
        LEFT JOIN clase c ON rci.id_clase = c.id_clase
        LEFT JOIN materia m ON c.id_materia = m.id_materia
        LEFT JOIN profesor p ON rci.id_profesor = p.id_profesor
        LEFT JOIN usuario u_profesor ON p.id_usuario = u_profesor.id_usuario
        LEFT JOIN coordinador coord ON rci.id_coordinador = coord.id_coordinador
        LEFT JOIN usuario u_coordinador ON coord.id_usuario = u_coordinador.id_usuario
        LEFT JOIN prefecto pref ON rci.tipo_reportador = 'prefecto' AND rci.id_reportador = pref.id_prefecto
        LEFT JOIN usuario u_prefecto ON pref.id_usuario = u_prefecto.id_usuario
        LEFT JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno AND ag.activo = 1
        LEFT JOIN grupo g ON ag.id_grupo = g.id_grupo
        LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
        WHERE rci.id_reporte_individual = ?
    ";
} else {
    $sql_reporte = "
        SELECT 
            rcg.*,
            g.nombre as grupo_nombre,
            car.nombre as carrera_nombre,
            m.nombre as materia_nombre,
            c.grupo as clase_grupo,
            CONCAT(u_profesor.nombre, ' ', u_profesor.apellidos) as profesor_nombre,
            CONCAT(u_coordinador.nombre, ' ', u_coordinador.apellidos) as coordinador_nombre,
            CONCAT(u_prefecto.nombre, ' ', u_prefecto.apellidos) as prefecto_nombre
        FROM reportes_conducta_grupal rcg
        INNER JOIN grupo g ON rcg.id_grupo = g.id_grupo
        INNER JOIN carrera car ON g.id_carrera = car.id_carrera
        LEFT JOIN clase c ON rcg.id_clase = c.id_clase
        LEFT JOIN materia m ON c.id_materia = m.id_materia
        LEFT JOIN profesor p ON rcg.id_profesor = p.id_profesor
        LEFT JOIN usuario u_profesor ON p.id_usuario = u_profesor.id_usuario
        LEFT JOIN coordinador coord ON rcg.id_coordinador = coord.id_coordinador
        LEFT JOIN usuario u_coordinador ON coord.id_usuario = u_coordinador.id_usuario
        LEFT JOIN prefecto pref ON rcg.tipo_reportador = 'prefecto' AND rcg.id_reportador = pref.id_prefecto
        LEFT JOIN usuario u_prefecto ON pref.id_usuario = u_prefecto.id_usuario
        WHERE rcg.id_reporte_grupal = ?
    ";
}

$stmt_reporte = $conexion->prepare($sql_reporte);
$stmt_reporte->bind_param("i", $id_reporte);
$stmt_reporte->execute();
$reporte = $stmt_reporte->get_result()->fetch_assoc();

if (!$reporte) {
    header("Location: " . ($_SESSION['rol'] == '3' ? 'coordinador.php' : ($_SESSION['rol'] == '2' ? 'profesor.php' : 'prefecto.php')) . "?error=Reporte no encontrado");
    exit;
}

// Obtener nombre del reportador según el tipo
$reportador_nombre = '';
if ($reporte['tipo_reportador'] == 'profesor') {
    $reportador_nombre = $reporte['profesor_nombre'];
} elseif ($reporte['tipo_reportador'] == 'coordinador') {
    $reportador_nombre = $reporte['coordinador_nombre'];
} else {
    $reportador_nombre = $reporte['prefecto_nombre'];
}

// Verificar permisos para ver este reporte
$tiene_permisos = false;
$id_usuario = $_SESSION['id_usuario'];
$rol_usuario = $_SESSION['rol'];

if ($rol_usuario == '3') { // Coordinador
    $tiene_permisos = true;
} elseif ($rol_usuario == '2') { // Profesor
    // Verificar si es tutor del grupo
    if ($tipo_reporte == 'individual') {
        $sql_permiso = "
            SELECT COUNT(*) as es_tutor 
            FROM alumno_grupo ag 
            INNER JOIN grupo g ON ag.id_grupo = g.id_grupo 
            INNER JOIN profesor p ON g.tutor_asignado = p.id_profesor 
            WHERE ag.id_alumno = ? AND p.id_usuario = ? AND ag.activo = 1
        ";
        $stmt_permiso = $conexion->prepare($sql_permiso);
        $stmt_permiso->bind_param("ii", $reporte['id_alumno'], $id_usuario);
        $stmt_permiso->execute();
        $result_permiso = $stmt_permiso->get_result()->fetch_assoc();
        $tiene_permisos = ($result_permiso && $result_permiso['es_tutor'] > 0);
    } else {
        $sql_permiso = "
            SELECT COUNT(*) as es_tutor 
            FROM grupo g 
            INNER JOIN profesor p ON g.tutor_asignado = p.id_profesor 
            WHERE g.id_grupo = ? AND p.id_usuario = ?
        ";
        $stmt_permiso = $conexion->prepare($sql_permiso);
        $stmt_permiso->bind_param("ii", $reporte['id_grupo'], $id_usuario);
        $stmt_permiso->execute();
        $result_permiso = $stmt_permiso->get_result()->fetch_assoc();
        $tiene_permisos = ($result_permiso && $result_permiso['es_tutor'] > 0);
    }
} elseif ($rol_usuario == '5') { // Prefecto
    // Prefectos pueden ver todos los reportes
    $tiene_permisos = true;
}

if (!$tiene_permisos) {
    header("Location: " . ($_SESSION['rol'] == '3' ? 'coordinador.php' : ($_SESSION['rol'] == '2' ? 'profesor.php' : 'prefecto.php')) . "?error=No tienes permisos para ver este reporte");
    exit;
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
  --radio-borde: 14px;
}

body {
  background: var(--color-fondo);
  font-family: "Poppins", "Segoe UI", sans-serif;
  color: var(--color-texto);
}

.content {
  padding: 40px 5%;
  max-width: 1000px;
  margin: auto;
}

/* TARJETA DE REPORTE */
.tarjeta-reporte {
  background: var(--color-blanco);
  border-radius: var(--radio-borde);
  padding: 30px;
  box-shadow: var(--sombra-suave);
  margin-bottom: 25px;
}

.tarjeta-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 25px;
  padding-bottom: 20px;
  border-bottom: 2px solid #e0e0e0;
}

.tarjeta-header h1 {
  margin: 0;
  color: var(--color-primario);
  font-size: 1.8em;
}

.info-badge {
  background: #e3f2fd;
  color: #1565c0;
  padding: 8px 16px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.9em;
}

/* GRID DE INFORMACIÓN */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 25px;
}

.info-group {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 10px;
  border-left: 4px solid var(--color-primario);
}

.info-group h3 {
  margin: 0 0 15px 0;
  color: var(--color-primario);
  font-size: 1.1em;
}

.info-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e0e0e0;
}

.info-item:last-child {
  border-bottom: none;
  margin-bottom: 0;
}

.info-label {
  font-weight: 600;
  color: #555;
}

.info-value {
  color: var(--color-texto);
  text-align: right;
}

/* CONTENIDO DEL REPORTE */
.contenido-reporte {
  background: #f8f9fa;
  padding: 25px;
  border-radius: 10px;
  margin-bottom: 20px;
}

.descripcion-box {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border-left: 4px solid #1565c0;
  margin-bottom: 20px;
}

.descripcion-box h4 {
  margin: 0 0 10px 0;
  color: #1565c0;
}

/* BADGES DE ESTADO */
.badge {
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 0.8em;
  font-weight: 600;
  text-transform: uppercase;
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

.badge-activo {
  background: #e8f5e8;
  color: #2e7d32;
}

.badge-resuelto {
  background: #e3f2fd;
  color: #1565c0;
}

.badge-archivado {
  background: #f5f5f5;
  color: #666;
}

/* ACCIONES */
.acciones {
  display: flex;
  gap: 10px;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 2px solid #e0e0e0;
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
  font-size: 0.9em;
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

.btn-success {
  background: #28a745;
  color: white;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .tarjeta-header {
    flex-direction: column;
    gap: 15px;
  }
  
  .acciones {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    text-align: center;
  }
}
</style>

<!-- CONTENIDO PRINCIPAL -->
<section class="content">
  <div class="tarjeta-reporte">
    <div class="tarjeta-header">
      <h1>
        <?php echo $tipo_reporte == 'individual' ? 'Reporte Individual de Conducta' : 'Reporte Grupal de Conducta'; ?>
      </h1>
      <div class="info-badge">
        ID: <?php echo $id_reporte; ?> | 
        <?php echo strtoupper($tipo_reporte); ?>
      </div>
    </div>

    <!-- INFORMACIÓN BÁSICA -->
    <div class="info-grid">
      <div class="info-group">
        <h3>Información del Reporte</h3>
        <div class="info-item">
          <span class="info-label">Estado:</span>
          <span class="info-value">
            <span class="badge badge-<?php echo $reporte['estado']; ?>">
              <?php echo ucfirst($reporte['estado']); ?>
            </span>
          </span>
        </div>
        <div class="info-item">
          <span class="info-label">Tipo de Incidencia:</span>
          <span class="info-value">
            <span class="badge badge-<?php echo str_replace('_', '-', $reporte['tipo_incidencia']); ?>">
              <?php echo ucfirst(str_replace('_', ' ', $reporte['tipo_incidencia'])); ?>
            </span>
          </span>
        </div>
        <div class="info-item">
          <span class="info-label">Categoría:</span>
          <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $reporte['categoria'])); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Fecha del Incidente:</span>
          <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_incidente'])); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Reportado por:</span>
          <span class="info-value">
            <?php echo htmlspecialchars($reportador_nombre); ?>
            <br><small>(<?php echo ucfirst($reporte['tipo_reportador']); ?>)</small>
          </span>
        </div>
      </div>

      <?php if ($tipo_reporte == 'individual'): ?>
      <div class="info-group">
        <h3>Información del Alumno</h3>
        <div class="info-item">
          <span class="info-label">Alumno:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['alumno_nombre'] . ' ' . $reporte['alumno_apellidos']); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Matrícula:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['alumno_matricula']); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Grupo:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['grupo_nombre']); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Carrera:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['carrera_nombre']); ?></span>
        </div>
        <?php if ($reporte['materia_nombre']): ?>
        <div class="info-item">
          <span class="info-label">Materia/Clase:</span>
          <span class="info-value">
            <?php echo htmlspecialchars($reporte['materia_nombre']); ?>
            <?php if ($reporte['clase_grupo']): ?>
              (Grupo <?php echo $reporte['clase_grupo']; ?>)
            <?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="info-group">
        <h3>Información del Grupo</h3>
        <div class="info-item">
          <span class="info-label">Grupo:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['grupo_nombre']); ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Carrera:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['carrera_nombre']); ?></span>
        </div>
        <?php if ($reporte['materia_nombre']): ?>
        <div class="info-item">
          <span class="info-label">Materia/Clase:</span>
          <span class="info-value">
            <?php echo htmlspecialchars($reporte['materia_nombre']); ?>
            <?php if ($reporte['clase_grupo']): ?>
              (Grupo <?php echo $reporte['clase_grupo']; ?>)
            <?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
        <?php if ($reporte['alumnos_involucrados']): ?>
        <div class="info-item">
          <span class="info-label">Alumnos Involucrados:</span>
          <span class="info-value"><?php echo htmlspecialchars($reporte['alumnos_involucrados']); ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- DESCRIPCIÓN Y MEDIDAS -->
    <div class="contenido-reporte">
      <div class="descripcion-box">
        <h4>Descripción del Incidente</h4>
        <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($reporte['descripcion'])); ?></p>
      </div>

      <?php if ($reporte['medidas_tomadas']): ?>
      <div class="descripcion-box" style="border-left-color: #28a745;">
        <h4>Medidas Tomadas</h4>
        <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($reporte['medidas_tomadas'])); ?></p>
      </div>
      <?php endif; ?>

      <?php if ($reporte['ruta_evidencia']): ?>
      <div class="descripcion-box" style="border-left-color: #ffc107;">
        <h4>Evidencia Adjunta</h4>
        <p style="margin: 0;">
          <strong>Archivo:</strong> <?php echo htmlspecialchars($reporte['nombre_archivo_original']); ?><br>
          <a href="../uploads/<?php echo htmlspecialchars($reporte['ruta_evidencia']); ?>" target="_blank" class="btn btn-primary btn-sm" style="margin-top: 10px;">
          Ver Archivo Adjunto
          </a>
        </p>
      </div>
      <?php endif; ?>
    </div>

    <!-- ACCIONES -->
    <div class="acciones">
      <a href="javascript:history.back()" class="btn btn-secondary">
        ← Volver
      </a>
      
      <?php if ($rol_usuario == '3' || $rol_usuario == '5'): // Coordinador o Prefecto ?>
      <button class="btn btn-success" onclick="cambiarEstadoReporte('resuelto')">
        Marcar como Resuelto
      </button>
      <button class="btn btn-danger" onclick="cambiarEstadoReporte('archivado')">
        🗄Archivar Reporte
      </button>
      <?php endif; ?>
      
      <?php if (($rol_usuario == '3' && $reporte['tipo_reportador'] == 'coordinador') || 
                ($rol_usuario == '2' && $reporte['tipo_reportador'] == 'profesor') ||
                ($rol_usuario == '5' && $reporte['tipo_reportador'] == 'prefecto')): ?>
      <button class="btn btn-primary" onclick="editarReporte()">
        Editar Reporte
      </button>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
function cambiarEstadoReporte(nuevoEstado) {
  if (confirm(`¿Estás seguro de que quieres marcar este reporte como ${nuevoEstado}?`)) {
    const formData = new FormData();
    formData.append('id_reporte', <?php echo $id_reporte; ?>);
    formData.append('tipo_reporte', '<?php echo $tipo_reporte; ?>');
    formData.append('nuevo_estado', nuevoEstado);
    
    fetch('acciones/cambiar_estado_reporte.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Estado del reporte actualizado correctamente');
        location.reload();
      } else {
        alert('Error: ' + data.error);
      }
    })
    .catch(error => {
      alert('Error al actualizar el estado del reporte');
      console.error('Error:', error);
    });
  }
}

function editarReporte() {
  alert('Funcionalidad de edición en desarrollo');
  // Aquí podrías redirigir a un formulario de edición
  // window.location.href = `editar_reporte.php?id=<?php echo $id_reporte; ?>&tipo=<?php echo $tipo_reporte; ?>`;
}
</script>

<?php include "footer.php"; ?>