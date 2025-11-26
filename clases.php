<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre'];

// Consulta según el rol
if ($rol == 1) { // ALUMNO
    $query = $conexion->prepare("
        SELECT 
            c.id_clase,
            c.grupo,
            m.nombre as materia_nombre,
            m.creditos,
            m.unidades,
            CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
            s.nombre as salon,
            s.edificio,
            car.nombre as carrera_nombre,
            a.id_asignacion,
            (SELECT COUNT(*) FROM asignacion WHERE id_clase = c.id_clase) as alumnos_inscritos,
            c.capacidad
        FROM asignacion a
        INNER JOIN clase c ON a.id_clase = c.id_clase
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        INNER JOIN usuario prof ON p.id_usuario = prof.id_usuario
        INNER JOIN salon s ON c.id_salon = s.id_salon
        INNER JOIN carrera car ON m.id_carrera = car.id_carrera
        INNER JOIN alumno al ON a.id_alumno = al.id_alumno
        WHERE al.id_usuario = ? And c.activo = 1
        ORDER BY m.nombre
    ");
    $query->bind_param("i", $id_usuario);
    
} elseif ($rol == 2) { // PROFESOR
    $query = $conexion->prepare("
        SELECT 
            c.id_clase,
            c.grupo,
            m.nombre as materia_nombre,
            m.creditos,
            m.unidades,
            s.nombre as salon,
            s.edificio,
            car.nombre as carrera_nombre,
            c.periodo,
            c.capacidad,
            (SELECT COUNT(*) FROM asignacion WHERE id_clase = c.id_clase) as alumnos_inscritos,
            COUNT(DISTINCT hc.dia) as dias_clase
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN salon s ON c.id_salon = s.id_salon
        INNER JOIN carrera car ON m.id_carrera = car.id_carrera
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        LEFT JOIN horarios_clase hc ON c.id_clase = hc.id_clase
        WHERE p.id_usuario = ? and c.activo=1
        GROUP BY c.id_clase
        ORDER BY m.nombre
    ");
    $query->bind_param("i", $id_usuario);
    
} else { // COORDINADOR - ve todas las clases
    $query = $conexion->prepare("
        SELECT 
            c.id_clase,
            c.grupo,
            m.nombre as materia_nombre,
            m.creditos,
            m.unidades,
            CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
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
        ORDER BY m.nombre
    ");
    $query->execute();
}
$query->execute();
$clases = $query->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener tareas según el rol
$tareas = [];
if ($rol == 1) { // ALUMNO - Obtener todas sus tareas
    $query_tareas = $conexion->prepare("
        SELECT 
            t.*,
            m.nombre as materia_nombre,
            c.id_clase,
            et.id_entrega,
            et.calificacion,
            et.fecha_entrega,
            CASE 
                WHEN t.estado = 'cancelada' THEN 'cancelada'
                WHEN t.fecha_limite < NOW() AND et.id_entrega IS NULL THEN 'vencida'
                WHEN et.id_entrega IS NULL THEN 'pendiente'
                WHEN et.calificacion IS NOT NULL THEN 'calificada'
                ELSE 'entregada'
            END as estado_entrega
        FROM tareas t
        INNER JOIN clase c ON t.id_clase = c.id_clase
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN asignacion a ON c.id_clase = a.id_clase
        INNER JOIN alumno al ON a.id_alumno = al.id_alumno
        LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea AND et.id_alumno = al.id_alumno
        WHERE al.id_usuario = ? and c.activo=1 and t.puntos_maximos>0
        ORDER BY t.fecha_limite ASC
    ");
    $query_tareas->bind_param("i", $id_usuario);
    $query_tareas->execute();
    $tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($rol == 2) { // PROFESOR - Obtener todas las tareas que ha creado
    $query_tareas = $conexion->prepare("
        SELECT 
            t.*,
            m.nombre as materia_nombre,
            c.id_clase,
            COUNT(et.id_entrega) as total_entregas,
            COUNT(CASE WHEN et.calificacion IS NOT NULL THEN 1 END) as total_calificadas,
            CASE 
                WHEN t.estado = 'cancelada' THEN 'cancelada'
                WHEN t.fecha_limite < NOW() THEN 'vencida'
                ELSE t.estado
            END as estado_tarea
        FROM tareas t
        INNER JOIN clase c ON t.id_clase = c.id_clase
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON t.id_profesor = p.id_profesor
        LEFT JOIN entregas_tareas et ON t.id_tarea = et.id_tarea
        WHERE p.id_usuario = ? and c.activo=1 and t.puntos_maximos>0
        GROUP BY t.id_tarea
        ORDER BY t.fecha_limite DESC
    ");
    $query_tareas->bind_param("i", $id_usuario);
    $query_tareas->execute();
    $tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);
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
  max-width: 1200px;
  margin: auto;
}

h2 {
  color: var(--color-primario);
  margin-bottom: 15px;
  text-align: center;
  font-weight: 600;
  letter-spacing: 1px;
}

/* BANNER */
.banner-bienvenida {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 60px 20px;
  text-align: center;
  overflow: hidden;
  position: relative;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  margin-bottom: 40px;
}

.banner-bienvenida::after {
  content: "";
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: radial-gradient(circle at center, rgba(255,255,255,0.15), transparent 60%);
  animation: moverLuz 5s linear infinite;
}

@keyframes moverLuz {
  0% { transform: translateX(-100%); }
  50% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}

.banner-texto {
  position: relative;
  z-index: 2;
  max-width: 900px;
  margin: 0 auto;
}

.banner-bienvenida h1 {
  font-size: 2.4em;
  font-weight: 700;
  letter-spacing: 2px;
  margin-bottom: 15px;
  opacity: 0;
  transform: translateY(-30px);
  animation: aparecerTitulo 1s ease-out forwards;
}

.banner-bienvenida p {
  font-size: 1.1em;
  font-weight: 400;
  opacity: 0;
  transform: translateY(30px);
  animation: aparecerSubtitulo 1.5s ease-out forwards;
  animation-delay: 0.5s;
}

@keyframes aparecerTitulo {
  to { opacity: 1; transform: translateY(0); }
}

@keyframes aparecerSubtitulo {
  to { opacity: 1; transform: translateY(0); }
}

/* BOTONES PRINCIPALES */
.botones-principales {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 30px;
  flex-wrap: wrap;
}

.btn-principal {
  background: var(--color-primario);
  color: white;
  border: none;
  padding: 15px 30px;
  border-radius: var(--radio-borde);
  font-size: 1.1em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: var(--sombra-suave);
}

.btn-principal:hover {
  background: var(--color-secundario);
  transform: translateY(-3px);
  box-shadow: var(--sombra-hover);
}

.btn-principal.active {
  background: var(--color-secundario);
  box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
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

/* FILTROS */
.filtros-tareas {
  background: white;
  padding: 25px;
  border-radius: var(--radio-borde);
  margin-bottom: 25px;
  box-shadow: var(--sombra-suave);
}

.filtros-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  align-items: end;
}

.form-group {
  margin-bottom: 0;
}

.form-group label {
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
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primario);
}

.btn-filtrar {
  background: var(--color-primario);
  color: white;
  border: none;
  padding: 12px 25px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-filtrar:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
}

/* GRID DE CLASES */
.grid-clases {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 25px;
  margin-top: 30px;
}

/* TARJETA DE CLASE */
.tarjeta-clase {
  background: var(--color-blanco);
  border-radius: var(--radio-borde);
  overflow: hidden;
  box-shadow: var(--sombra-suave);
  transition: all 0.3s ease;
  cursor: pointer;
  border-left: 5px solid var(--color-primario);
}

.tarjeta-clase:hover {
  transform: translateY(-5px);
  box-shadow: var(--sombra-hover);
}

.tarjeta-clase-header {
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  color: white;
  padding: 20px;
}

.tarjeta-clase-header h3 {
  margin: 0 0 10px 0;
  font-size: 1.3em;
  font-weight: 700;
}

.tarjeta-clase-header .creditos {
  background: rgba(255,255,255,0.2);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.9em;
  display: inline-block;
}

.tarjeta-clase-body {
  padding: 20px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #eee;
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
  color: var(--color-primario);
  font-weight: 500;
}

/* GRID DE TAREAS */
.grid-tareas {
  display: grid;
  gap: 20px;
}

.tarjeta-tarea {
  background: white;
  border-radius: var(--radio-borde);
  padding: 25px;
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

.tarea-materia {
  background: #e3f2fd;
  color: var(--color-primario);
  padding: 8px 15px;
  border-radius: 20px;
  font-size: 0.9em;
  font-weight: 600;
}

.tarea-stats {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.stat {
  background: #e3f2fd;
  padding: 8px 15px;
  border-radius: 20px;
  font-size: 0.9em;
  color: var(--color-primario);
  font-weight: 600;
}

.tarea-fecha {
  color: #666;
  margin-bottom: 10px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.tarea-descripcion {
  color: #555;
  line-height: 1.5;
  margin-bottom: 15px;
}

/* ESTADOS DE TAREAS */
.estado-tarea {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85em;
  font-weight: 600;
  text-transform: uppercase;
}

.estado-pendiente {
  background: #fff3e0;
  color: #f57c00;
}

.estado-entregada {
  background: #e8f5e8;
  color: #2e7d32;
}

.estado-calificada {
  background: #e3f2fd;
  color: #1976d2;
}

.estado-vencida {
  background: #ffebee;
  color: #c62828;
}

.estado-cancelada {
  background: #f5f5f5;
  color: #757575;
  text-decoration: line-through;
}

/* Para tareas vencidas - estilo adicional */
.tarea-vencida {
  border-left: 5px solid #c62828;
  opacity: 0.8;
}

.tarea-cancelada {
  border-left: 5px solid #757575;
  opacity: 0.7;
}

/* ACCIONES */
.acciones-tarea {
  display: flex;
  gap: 10px;
  margin-top: 15px;
  flex-wrap: wrap;
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

.btn:disabled {
  background: #cccccc;
  cursor: not-allowed;
  transform: none;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .grid-clases, .grid-tareas {
    grid-template-columns: 1fr;
  }
  
  .botones-principales {
    flex-direction: column;
    align-items: center;
  }
  
  .btn-principal {
    width: 100%;
    max-width: 300px;
  }
  
  .tarjeta-tarea-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .filtros-grid {
    grid-template-columns: 1fr;
  }
  
  .banner-bienvenida {
    padding: 40px 15px;
  }
  
  .banner-bienvenida h1 {
    font-size: 1.8em;
  }
  
    .banner-bienvenida p {
    font-size: 1em;
  }
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.empty-state h3 {
  color: #999;
  margin-bottom: 10px;
}
        /* BOTÓN CALENDARIO */
.btn-calendario {
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  border: none;
  padding: 14px 30px;
  border-radius: 50px;
  font-size: 1.1em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
  margin-top: 15px;
}

.btn-calendario:hover {
  background: linear-gradient(135deg, #218838, #1e9e8a);
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.btn-calendario:active {
  transform: translateY(-1px);
}
</style>
<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
  <div class="banner-texto">
    <h1 class="animar-titulo">
      MIS CLASES - <?php echo strtoupper($rol == 1 ? 'ALUMNO' : ($rol == 2 ? 'PROFESOR' : 'COORDINADOR')); ?>
    </h1>
    <p class="animar-subtitulo">
      <?php 
        if ($rol == 1) {
          echo "AQUI PUEDES VER TODAS TUS CLASES ASIGNADAS Y ACCEDER A SUS DETALLES";
        } elseif ($rol == 2) {
          echo "GESTIONA TUS CLASES IMPARTIDAS Y EL ACCESO A LOS MATERIALES";
        } else {
          echo "VISION GENERAL DE TODAS LAS CLASES DEL SISTEMA";
        }
      ?>
    </p>
              <div class="mt-4">
      <button class="btn-calendario" onclick="abrirCalendario()">
        <i class="fas fa-calendar-alt me-2"></i> Ver Calendario de Clases
      </button>
    </div>
  </div>
</section>

<main class="content">
<!-- En la sección de botones principales, para alumnos -->
<div class="botones-principales">
    <button class="btn-principal active" onclick="mostrarSeccion('clases')">
         Mis Clases
    </button>
    <?php
    if ($rol != 3)
      {
        echo '<button class="btn-principal" onclick="mostrarSeccion(';
        echo "'tareas'";
        echo ')">
        Todas las Tareas
    </button>';
    }
    
    //if($rol== 1)
    //{
    //echo '<a href="inscribirse_clase.php" class="btn-principal" style="text-decoration: none;">
    //    Inscribirse a Clase
    //</a>';
    //}
    ?>


</div>

  <!-- SECCION DE CLASES -->
  <div id="seccion-clases" class="seccion activa">
    <div class="grid-clases">
      <?php if (count($clases) > 0): ?>
        <?php foreach($clases as $clase): ?>
          <div class="tarjeta-clase" onclick="verClase(<?php echo $clase['id_clase']; ?>)">
            <div class="tarjeta-clase-header">
              <h3><?php echo htmlspecialchars($clase['materia_nombre']); ?></h3>
              <span class="creditos"><?php echo $clase['creditos']; ?> créditos</span>
            </div>
            
            <div class="tarjeta-clase-body">
              <?php if ($rol == 1): ?>
                <!-- VISTA ALUMNO -->
                <div class="info-item">
                  <span class="info-label">Profesor:</span>
                  <span class="info-value"><?php echo htmlspecialchars($clase['profesor_nombre']); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Salón:</span>
                  <span class="info-value"><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></span>
                </div>
                                <div class="info-item">
                  <span class="info-label">Grupo:</span>
                  <span class="info-value"><?php echo $clase['grupo']; ?></span>
                </div>
                
                <div class="info-item">
                  <span class="info-label">Unidades:</span>
                  <span class="info-value"><?php echo $clase['unidades']; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Carrera:</span>
                  <span class="info-value"><?php echo htmlspecialchars($clase['carrera_nombre']); ?></span>
                </div>
                
              <?php elseif ($rol == 2): ?>
                <!-- VISTA PROFESOR -->
                <div class="info-item">
                  <span class="info-label">Salón:</span>
                  <span class="info-value"><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Alumnos:</span>
                  <span class="info-value"><?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?></span>
                </div>
                                                    <div class="info-item">
                  <span class="info-label">Grupo:</span>
                  <span class="info-value"><?php echo $clase['grupo']; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Días clase:</span>
                  <span class="info-value"><?php echo $clase['dias_clase']; ?> días</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Carrera:</span>
                  <span class="info-value"><?php echo htmlspecialchars($clase['carrera_nombre']); ?></span>
                </div>
                
              <?php else: ?>
                <!-- VISTA COORDINADOR -->
                <div class="info-item">
                  <span class="info-label">Profesor:</span>
                  <span class="info-value"><?php echo htmlspecialchars($clase['profesor_nombre']); ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Salón:</span>
                  <span class="info-value"><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></span>
                </div>
                                                    <div class="info-item">
                  <span class="info-label">Grupo:</span>
                  <span class="info-value"><?php echo $clase['grupo']; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Alumnos:</span>
                  <span class="info-value"><?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?></span>
                </div>
                <div class="info-item">
                  <span class="info-label">Carrera:</span>
                  <span class="info-value"><?php echo htmlspecialchars($clase['carrera_nombre']); ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <h3>No tienes clases asignadas</h3>
          <p>Contacta con tu coordinador para más información</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN DE TAREAS -->
  <?php if ($rol == 1 || $rol == 2): ?>
    <div id="seccion-tareas" class="seccion">
      <!-- FILTROS -->
      <div class="filtros-tareas">
        <div class="filtros-grid">
            <div class="form-group">
              <label for="filtro-clase">Filtrar por clase:</label>
              <select id="filtro-clase" class="form-control">
                <option value="">Todas las clases</option>
                <?php foreach($clases as $clase): ?>
                  <option value="<?php echo $clase['id_clase']; ?>">
                    <?php echo htmlspecialchars($clase['materia_nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          
          <?php if ($rol == 1): ?>
            <div class="form-group">
              <label for="filtro-estado">Filtrar por estado:</label>
              <select id="filtro-estado" class="form-control">
                <option value="">Todas las tareas</option>
                <option value="pendiente">Pendientes</option>
                <option value="entregada">Entregadas</option>
                <option value="calificada">Calificadas</option>
                <option value="vencida">Vencidas</option>
                <option value="cancelada">Canceladas</option>
              </select>
            </div>
          <?php else: ?>
            <div class="form-group">
              <label for="filtro-estado">Filtrar por estado:</label>
              <select id="filtro-estado" class="form-control">
                <option value="">Todas las tareas</option>
                <option value="activa">Activas</option>
                <option value="cerrada">Cerradas</option>
                <option value="cancelada">Canceladas</option>
                <option value="vencida">Vencidas</option>
              </select>
            </div>
          <?php endif; ?>
          
          <div class="form-group">
            <label for="filtro-fecha">Ordenar por fecha:</label>
            <select id="filtro-fecha" class="form-control">
              <option value="asc">Próximas a vencer</option>
              <option value="desc">Más recientes primero</option>
            </select>
          </div>
          
          <div class="form-group">
            <button class="btn-filtrar" onclick="aplicarFiltros()">Aplicar Filtros</button>
          </div>
        </div>
      </div>

      <!-- LISTA DE TAREAS -->
      <div class="grid-tareas" id="lista-tareas">
        <?php if (count($tareas) > 0): ?>
          <?php foreach($tareas as $tarea): ?>
            <?php 
              // Determinar clase CSS adicional según el estado
              $clase_adicional = '';
              if ($rol == 1) {
                if ($tarea['estado_entrega'] == 'vencida') {
                  $clase_adicional = 'tarea-vencida';
                } elseif ($tarea['estado_entrega'] == 'cancelada') {
                  $clase_adicional = 'tarea-cancelada';
                }
              } else {
                if ($tarea['estado_tarea'] == 'vencida') {
                  $clase_adicional = 'tarea-vencida';
                } elseif ($tarea['estado_tarea'] == 'cancelada') {
                  $clase_adicional = 'tarea-cancelada';
                }
              }
            ?>
            <div class="tarjeta-tarea <?php echo $clase_adicional; ?>" 
                 data-clase="<?php echo $tarea['id_clase']; ?>"
                 data-estado="<?php echo $rol == 1 ? $tarea['estado_entrega'] : $tarea['estado_tarea']; ?>"
                 data-fecha="<?php echo $tarea['fecha_limite']; ?>">
              
              <div class="tarjeta-tarea-header">
                <h3><?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                <div class="tarea-materia"><?php echo htmlspecialchars($tarea['materia_nombre']); ?></div>
                <?php if ($rol == 1): ?>
                  <span class="estado-tarea estado-<?php echo $tarea['estado_entrega']; ?>">
                    <?php 
                      switch($tarea['estado_entrega']) {
                        case 'pendiente': echo 'Pendiente'; break;
                        case 'entregada': echo 'Entregada'; break;
                        case 'calificada': echo 'Calificada'; break;
                        case 'vencida': echo 'Vencida'; break;
                        case 'cancelada': echo 'Cancelada'; break;
                        default: echo 'Activa';
                      }
                    ?>
                  </span>
                <?php else: ?>
                  <span class="estado-tarea estado-<?php echo $tarea['estado_tarea']; ?>">
                    <?php 
                      switch($tarea['estado_tarea']) {
                        case 'activa': echo 'Activa'; break;
                        case 'cerrada': echo 'Cerrada'; break;
                        case 'cancelada': echo 'Cancelada'; break;
                        case 'vencida': echo 'Vencida'; break;
                        default: echo $tarea['estado_tarea'];
                      }
                    ?>
                  </span>
                  <div class="tarea-stats">
                    <div class="stat"><?php echo $tarea['total_entregas']; ?> entregas</div>
                    <div class="stat"><?php echo $tarea['total_calificadas']; ?> calificadas</div>
                  </div>
                <?php endif; ?>
              </div>
              
              <div class="tarea-fecha">
                <strong>Fecha límite:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?>
                | <strong>Puntos:</strong> <?php echo $tarea['puntos_maximos']; ?>
                <?php if ($rol == 1 && isset($tarea['calificacion'])): ?>
                  | <strong>Tu calificación:</strong> <?php echo $tarea['calificacion']; ?>/<?php echo $tarea['puntos_maximos']; ?>
                <?php endif; ?>
                
                <!-- Mostrar si la tarea está vencida -->
                <?php if (($rol == 1 && $tarea['estado_entrega'] == 'vencida') || ($rol == 2 && $tarea['estado_tarea'] == 'vencida')): ?>
                  | <strong style="color: #c62828;">⚠️ TAREA VENCIDA</strong>
                <?php endif; ?>
                
                <!-- Mostrar si la tarea está cancelada -->
                <?php if (($rol == 1 && $tarea['estado_entrega'] == 'cancelada') || ($rol == 2 && $tarea['estado_tarea'] == 'cancelada')): ?>
                  | <strong style="color: #757575;">❌ TAREA CANCELADA</strong>
                <?php endif; ?>
              </div>
              
              <?php if ($tarea['descripcion']): ?>
                <div class="tarea-descripcion">
                  <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                </div>
              <?php endif; ?>
              
              <div class="acciones-tarea">
                <?php if ($rol == 2): ?>
                  <a href="detalle_clase.php?id=<?php echo $tarea['id_clase']; ?>&tarea_id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary">
                    Ver Entregas
                  </a>
                  <a href="detalle_clase.php?id=<?php echo $tarea['id_clase']; ?>" class="btn btn-secondary">
                    Ir a la Clase
                  </a>
                <?php else: ?>
                  <?php if ($tarea['estado_entrega'] == 'pendiente'): ?>
                    <button onclick="entregarTarea(<?php echo $tarea['id_tarea']; ?>)" class="btn btn-primary">
                      Entregar Tarea
                    </button>
                  <?php elseif ($tarea['estado_entrega'] == 'vencida'): ?>
                    <button class="btn btn-danger" disabled>
                      Tarea Vencida
                    </button>
                  <?php elseif ($tarea['estado_entrega'] == 'cancelada'): ?>
                    <button class="btn btn-secondary" disabled>
                      Tarea Cancelada
                    </button>
                  <?php elseif ($tarea['estado_entrega'] == 'entregada'): ?>
                    <span class="estado-tarea estado-entregada">Esperando calificación</span>
                  <?php elseif ($tarea['estado_entrega'] == 'calificada'): ?>
                    <span class="estado-tarea estado-calificada">Calificada: <?php echo $tarea['calificacion']; ?>/<?php echo $tarea['puntos_maximos']; ?></span>
                  <?php endif; ?>
                  <a href="detalle_clase.php?id=<?php echo $tarea['id_clase']; ?>" class="btn btn-secondary">
                    Ir a la Clase
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <h3>No hay tareas</h3>
            <p><?php echo $rol == 1 ? 'No tienes tareas asignadas' : 'No has creado ninguna tarea'; ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
function verClase(idClase) {
  window.location.href = `detalle_clase.php?id=${idClase}`;
}

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

function aplicarFiltros() {
  const filtroClase = document.getElementById('filtro-clase')?.value || '';
  const filtroEstado = document.getElementById('filtro-estado').value;
  const filtroFecha = document.getElementById('filtro-fecha').value;
  
  const tareas = document.querySelectorAll('.tarjeta-tarea');
  
  tareas.forEach(tarea => {
    let mostrar = true;
    
    // Filtrar por clase
    if (filtroClase && tarea.dataset.clase !== filtroClase) {
      mostrar = false;
    }
    
    // Filtrar por estado
    if (filtroEstado && tarea.dataset.estado !== filtroEstado) {
      mostrar = false;
    }
    
    // Ordenar por fecha (se maneja con CSS order)
    if (filtroFecha === 'asc') {
      tarea.style.order = '0';
    } else {
      tarea.style.order = '1';
    }
    
    // Mostrar/ocultar
    tarea.style.display = mostrar ? 'block' : 'none';
  });
}

function entregarTarea(tareaId) {
  window.location.href = `detalle_clase.php?entregar_tarea=${tareaId}`;
}

// Hacer las tarjetas de clase clickeables
document.querySelectorAll('.tarjeta-clase').forEach(card => {
  card.style.cursor = 'pointer';
});

// Aplicar filtros por defecto al cargar
document.addEventListener('DOMContentLoaded', function() {
  aplicarFiltros();
});
        function abrirCalendario() {
  // Abrir calendario.php en una nueva pestaña
  window.open('calendario.php', '_blank');
}
</script>

<?php include "footer.php"; ?>