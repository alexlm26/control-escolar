<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Permitir acceso a coordinadores (rol 1), profesores (rol 2) y rol 5
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != '3' && $_SESSION['rol'] != '2' && $_SESSION['rol'] != '5')) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Inicializar variables
$id_coordinador = null;
$id_carrera_coordinador = null;
$id_profesor_tutor = null;
$es_coordinador = false;
$es_tutor = false;
$es_rol5 = false;

if ($rol_usuario == '3') {
    // ES COORDINADOR - Obtener información del coordinador
    $sql_coordinador = "SELECT c.id_coordinador, u.id_carrera 
                       FROM coordinador c 
                       INNER JOIN usuario u ON c.id_usuario = u.id_usuario 
                       WHERE c.id_usuario = ?";
    $stmt = $conexion->prepare($sql_coordinador);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $coordinador_data = $stmt->get_result()->fetch_assoc();

    if ($coordinador_data) {
        $id_coordinador = $coordinador_data['id_coordinador'];
        $id_carrera_coordinador = $coordinador_data['id_carrera'];
        $es_coordinador = true;
    }
} else if ($rol_usuario == '2') {
    // ES PROFESOR - Verificar si es tutor de algún grupo
    $sql_profesor = "SELECT p.id_profesor 
                    FROM profesor p 
                    WHERE p.id_usuario = ?";
    $stmt = $conexion->prepare($sql_profesor);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $profesor_data = $stmt->get_result()->fetch_assoc();

    if ($profesor_data) {
        $id_profesor_tutor = $profesor_data['id_profesor'];
        
        // Verificar si es tutor de algún grupo
        $sql_tutor = "SELECT COUNT(*) as total_grupos 
                     FROM grupo 
                     WHERE tutor_asignado = ? AND activo = 1";
        $stmt = $conexion->prepare($sql_tutor);
        $stmt->bind_param("i", $id_profesor_tutor);
        $stmt->execute();
        $tutor_data = $stmt->get_result()->fetch_assoc();
        
        $es_tutor = ($tutor_data['total_grupos'] > 0);
    }
} else if ($rol_usuario == '5') {
    // ES ROL 5
    $es_rol5 = true;
}

// Consulta de grupos según el tipo de usuario
$grupos = [];
$query = null;

if ($es_coordinador) {
    // COORDINADOR - ve grupos según su nivel de acceso
    if ($id_carrera_coordinador == 0) {
        // Coordinador general - ve todos los grupos
        $query = $conexion->prepare("
            SELECT 
                g.id_grupo,
                g.nombre,
                g.semestre,
                g.capacidad_maxima,
                g.fecha_creacion,
                g.activo,
                car.nombre as carrera_nombre,
                e.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as tutor_nombre,
                p.id_profesor as tutor_id,
                COUNT(ag.id_alumno_grupo) as total_alumnos
            FROM grupo g
            LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
            LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
            LEFT JOIN profesor p ON g.tutor_asignado = p.id_profesor
            LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN alumno_grupo ag ON g.id_grupo = ag.id_grupo AND ag.activo = 1
            GROUP BY g.id_grupo
            ORDER BY g.semestre, g.nombre
        ");
    } else {
        // Coordinador de carrera específica - solo ve grupos de su carrera
        $query = $conexion->prepare("
            SELECT 
                g.id_grupo,
                g.nombre,
                g.semestre,
                g.capacidad_maxima,
                g.fecha_creacion,
                g.activo,
                car.nombre as carrera_nombre,
                e.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as tutor_nombre,
                p.id_profesor as tutor_id,
                COUNT(ag.id_alumno_grupo) as total_alumnos
            FROM grupo g
            LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
            LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
            LEFT JOIN profesor p ON g.tutor_asignado = p.id_profesor
            LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN alumno_grupo ag ON g.id_grupo = ag.id_grupo AND ag.activo = 1
            WHERE g.id_carrera = ?
            GROUP BY g.id_grupo
            ORDER BY g.semestre, g.nombre
        ");
        $query->bind_param("i", $id_carrera_coordinador);
    }
} else if ($es_tutor) {
    // PROFESOR TUTOR - solo ve los grupos donde es tutor
    $query = $conexion->prepare("
        SELECT 
            g.id_grupo,
            g.nombre,
            g.semestre,
            g.capacidad_maxima,
            g.fecha_creacion,
            g.activo,
            car.nombre as carrera_nombre,
            e.nombre as especialidad_nombre,
            CONCAT(u.nombre, ' ', u.apellidos) as tutor_nombre,
            p.id_profesor as tutor_id,
            COUNT(ag.id_alumno_grupo) as total_alumnos
        FROM grupo g
        LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
        LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
        LEFT JOIN profesor p ON g.tutor_asignado = p.id_profesor
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN alumno_grupo ag ON g.id_grupo = ag.id_grupo AND ag.activo = 1
        WHERE g.tutor_asignado = ? AND g.activo = 1
        GROUP BY g.id_grupo
        ORDER BY g.semestre, g.nombre
    ");
    $query->bind_param("i", $id_profesor_tutor);
} else if ($es_rol5) {
    // ROL 5 - ve todos los grupos
    $query = $conexion->prepare("
        SELECT 
            g.id_grupo,
            g.nombre,
            g.semestre,
            g.capacidad_maxima,
            g.fecha_creacion,
            g.activo,
            car.nombre as carrera_nombre,
            e.nombre as especialidad_nombre,
            CONCAT(u.nombre, ' ', u.apellidos) as tutor_nombre,
            p.id_profesor as tutor_id,
            COUNT(ag.id_alumno_grupo) as total_alumnos
        FROM grupo g
        LEFT JOIN carrera car ON g.id_carrera = car.id_carrera
        LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
        LEFT JOIN profesor p ON g.tutor_asignado = p.id_profesor
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN alumno_grupo ag ON g.id_grupo = ag.id_grupo AND ag.activo = 1
        GROUP BY g.id_grupo
        ORDER BY g.semestre, g.nombre
    ");
}

// Ejecutar consulta y obtener resultados
if ($query && $query->execute()) {
    $result = $query->get_result();
    $grupos = $result->fetch_all(MYSQLI_ASSOC);
}

// Obtener datos para formularios (solo para coordinadores)
$carreras = [];
$especialidades = [];
$profesores = [];

if ($es_coordinador) {
    // Obtener carreras para el formulario de crear grupo
    $carreras_query = $conexion->query("SELECT id_carrera, nombre FROM carrera ORDER BY nombre");
    $carreras = $carreras_query->fetch_all(MYSQLI_ASSOC);

    // Obtener especialidades para el formulario
    $especialidades_query = $conexion->query("SELECT id_especialidad, nombre FROM especialidad WHERE activo = 1 ORDER BY nombre");
    $especialidades = $especialidades_query->fetch_all(MYSQLI_ASSOC);

    // Obtener profesores para tutores
    $profesores_query = $conexion->query("
        SELECT p.id_profesor, u.nombre, u.apellidos, u.clave 
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.estado = '1'
        ORDER BY u.nombre, u.apellidos
    ");
    $profesores = $profesores_query->fetch_all(MYSQLI_ASSOC);
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

.banner-bienvenida .rol-indicador {
  background: rgba(255,255,255,0.2);
  padding: 8px 20px;
  border-radius: 25px;
  font-size: 0.9em;
  margin-top: 15px;
  display: inline-block;
  font-weight: 600;
}

@keyframes aparecerTitulo {
  to { opacity: 1; transform: translateY(0); }
}

@keyframes aparecerSubtitulo {
  to { opacity: 1; transform: translateY(0); }
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

/* GRID DE GRUPOS */
.grid-grupos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 25px;
  margin-top: 30px;
}

/* TARJETA DE GRUPO */
.tarjeta-grupo {
  background: var(--color-blanco);
  border-radius: var(--radio-borde);
  overflow: hidden;
  box-shadow: var(--sombra-suave);
  transition: all 0.3s ease;
  cursor: pointer;
  border-left: 5px solid var(--color-primario);
}

.tarjeta-grupo:hover {
  transform: translateY(-5px);
  box-shadow: var(--sombra-hover);
}

.tarjeta-grupo.mi-grupo {
  border-left: 5px solid #28a745;
  box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
}

.tarjeta-grupo.mi-grupo:hover {
  box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
}

.tarjeta-grupo-header {
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  color: white;
  padding: 20px;
  position: relative;
}

.tarjeta-grupo.mi-grupo .tarjeta-grupo-header {
  background: linear-gradient(135deg, #28a745, #20c997);
}

.tarjeta-grupo-header h3 {
  margin: 0 0 10px 0;
  font-size: 1.3em;
  font-weight: 700;
}

.tarjeta-grupo-header .semestre {
  background: rgba(255,255,255,0.2);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.9em;
  display: inline-block;
}

.etiqueta-tutor {
  position: absolute;
  top: 15px;
  right: 15px;
  background: rgba(255,255,255,0.9);
  color: #28a745;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.8em;
  font-weight: 600;
}

.tarjeta-grupo-body {
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

/* ESTADOS */
.estado-activo {
  color: #28a745;
  font-weight: 600;
}

.estado-inactivo {
  color: #dc3545;
  font-weight: 600;
}

/* BOTÓN CREAR GRUPO */
.btn-crear-grupo {
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  border: none;
  padding: 15px 30px;
  border-radius: var(--radio-borde);
  font-size: 1.1em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
  margin-bottom: 20px;
}

.btn-crear-grupo:hover {
  background: linear-gradient(135deg, #218838, #1e9e8a);
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
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
  max-width: 600px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  animation: modalAppear 0.3s ease;
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

/* FORMULARIOS */
.form-group {
  margin-bottom: 20px;
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
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primario);
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

.acciones {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  justify-content: flex-end;
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

.upload-area p {
  margin: 0 0 10px 0;
  font-weight: 600;
}

.info-text {
  font-size: 0.9em;
  color: #6c757d;
  margin-top: 5px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .grid-grupos {
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
  
  .modal-content {
    width: 95%;
    margin: 10% auto;
    padding: 20px;
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
  
  .barra-busqueda {
    max-width: 100%;
  }
}

.debug-info {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 20px;
  font-family: monospace;
  font-size: 0.9em;
}
</style>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
  <div class="banner-texto">
    <h1 class="animar-titulo">
      <?php
        if ($es_coordinador) {
          echo "GESTIÓN DE GRUPOS - COORDINADOR";
        } else if ($es_tutor) {
          echo "GRUPOS ASIGNADOS - TUTOR";
        } else if ($es_rol5) {
          echo "CONSULTA DE GRUPOS - ROL 5";
        } else {
          echo "GRUPOS DEL SISTEMA";
        }
      ?>
    </h1>
    <p class="animar-subtitulo">
      <?php 
        if ($es_coordinador) {
          if ($id_carrera_coordinador == 0) {
            echo "ADMINISTRA TODOS LOS GRUPOS DEL SISTEMA";
          } else {
            echo "ADMINISTRA LOS GRUPOS DE TU CARRERA";
          }
        } else if ($es_tutor) {
          echo "CONSULTA Y GESTIONA LOS GRUPOS DONDE ERES TUTOR";
        } else if ($es_rol5) {
          echo "VISUALIZO LOS GRUPOS PARA ENVIAR REPORTES Y JUSTIFICANTES";
        } else {
          echo "VISUALIZACIÓN DE GRUPOS DEL SISTEMA";
        }
      ?>
    </p>
    <div class="rol-indicador">
      <?php
        if ($es_coordinador) {
          echo "COORDINADOR " . ($id_carrera_coordinador == 0 ? "GENERAL" : "DE CARRERA");
        } else if ($es_tutor) {
          echo "PROFESOR TUTOR";
        } else if ($es_rol5) {
          echo "PREFECTO";
        } else {
          echo "USUARIO";
        }
      ?>
    </div>
  </div>
</section>

<main class="content">

  <!-- BARRA DE BÚSQUEDA (para rol 3 y rol 5) -->
  <?php if ($es_coordinador || $es_rol5): ?>
  <div class="barra-busqueda-container">
    <div class="barra-busqueda">
      <input type="text" id="buscarGrupo" placeholder="Buscar grupo por nombre, carrera, semestre o tutor...">
      <div class="icono-busqueda"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- BOTÓN PARA CREAR NUEVO GRUPO (solo para coordinadores) -->
  <?php if ($es_coordinador): ?>
  <div style="text-align: center; margin-bottom: 30px;">
    <button class="btn-crear-grupo" onclick="abrirModalCrear()">
      <i class="fas fa-plus-circle me-2"></i> Crear Nuevo Grupo
    </button>
  </div>
  <?php endif; ?>

  <!-- SECCIÓN DE GRUPOS -->
  <div id="seccion-grupos" class="seccion activa">
    <div class="grid-grupos" id="lista-grupos">
      <?php if (count($grupos) > 0): ?>
        <?php foreach($grupos as $grupo): ?>
          <?php 
            $esMiGrupo = ($es_tutor && $grupo['tutor_id'] == $id_profesor_tutor);
            $claseTarjeta = $esMiGrupo ? 'tarjeta-grupo mi-grupo' : 'tarjeta-grupo';
          ?>
          <div class="<?php echo $claseTarjeta; ?>" onclick="verGrupo(<?php echo $grupo['id_grupo']; ?>)" data-grupo-info="<?php echo htmlspecialchars(strtolower($grupo['nombre'] . ' ' . $grupo['carrera_nombre'] . ' ' . $grupo['semestre'] . ' ' . $grupo['tutor_nombre'])); ?>">
            <div class="tarjeta-grupo-header">
              <h3><?php echo htmlspecialchars($grupo['nombre']); ?></h3>
              <span class="semestre">Semestre <?php echo $grupo['semestre']; ?></span>
              <?php if ($esMiGrupo): ?>
                <div class="etiqueta-tutor">TU GRUPO</div>
              <?php endif; ?>
            </div>
            
            <div class="tarjeta-grupo-body">
              <div class="info-item">
                <span class="info-label">Carrera:</span>
                <span class="info-value"><?php echo htmlspecialchars($grupo['carrera_nombre']); ?></span>
              </div>
              
              <?php if ($grupo['especialidad_nombre']): ?>
                <div class="info-item">
                  <span class="info-label">Especialidad:</span>
                  <span class="info-value"><?php echo htmlspecialchars($grupo['especialidad_nombre']); ?></span>
                </div>
              <?php endif; ?>
              
              <div class="info-item">
                <span class="info-label">Tutor:</span>
                <span class="info-value">
                  <?php echo $grupo['tutor_nombre'] ? htmlspecialchars($grupo['tutor_nombre']) : 'Sin asignar'; ?>
                </span>
              </div>
              
              <div class="info-item">
                <span class="info-label">Alumnos:</span>
                <span class="info-value"><?php echo $grupo['total_alumnos']; ?>/<?php echo $grupo['capacidad_maxima']; ?></span>
              </div>
              
              <div class="info-item">
                <span class="info-label">Estado:</span>
                <span class="info-value <?php echo $grupo['activo'] ? 'estado-activo' : 'estado-inactivo'; ?>">
                  <?php echo $grupo['activo'] ? 'Activo' : 'Inactivo'; ?>
                </span>
              </div>
              
              <div class="info-item">
                <span class="info-label">Creado:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($grupo['fecha_creacion'])); ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
          <div style="font-size: 4em; margin-bottom: 20px; color: #ccc;">
       
          </div>
          <h3 style="color: #666; margin-bottom: 15px;">
            <?php
              if ($es_tutor) {
                echo "No tienes grupos asignados como tutor";
              } else if ($es_coordinador) {
                echo "No hay grupos registrados";
              } else if ($es_rol5) {
                echo "No hay grupos en el sistema";
              } else {
                echo "No hay grupos disponibles";
              }
            ?>
          </h3>
          <p style="color: #888; margin-bottom: 25px; font-size: 1.1em;">
            <?php 
            if ($es_tutor) {
              echo "Actualmente no estás asignado como tutor de ningún grupo.";
            } else if ($es_coordinador) {
              if ($id_carrera_coordinador == 0) {
                echo "No se han creado grupos en el sistema aún.";
              } else {
                echo "No hay grupos creados para tu carrera.";
              }
            } else if ($es_rol5) {
              echo "No hay grupos disponibles en el sistema en este momento.";
            } else {
              echo "No hay grupos disponibles para visualizar.";
            }
            ?>
          </p>
          <?php if ($es_coordinador): ?>
          <button class="btn-crear-grupo" onclick="abrirModalCrear()" style="margin: 0 auto;">
            <i class="fas fa-plus-circle me-2"></i> Crear el Primer Grupo
          </button>
          <?php endif; ?>
          <div style="margin-top: 20px; color: #999; font-size: 0.9em;">
            <p>
              <?php
                if ($es_tutor) {
                  echo "Contacta al coordinador para ser asignado como tutor de un grupo.";
                } else if ($es_rol5) {
                  echo "Los grupos aparecerán aquí una vez que sean creados en el sistema.";
                } else {
                  echo "Aqui solo aparecen grupos de los que eres tutor";
                }
              ?>
            </p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- MODAL PARA CREAR GRUPO (solo para coordinadores) -->
<?php if ($es_coordinador): ?>
<div id="modalCrearGrupo" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Crear Nuevo Grupo</h3>
      <span class="close" onclick="cerrarModalCrear()">&times;</span>
    </div>
    
    <form id="formCrearGrupo" method="POST" action="acciones/crear_grupo.php" enctype="multipart/form-data">
      <div class="form-group">
        <label>Nombre del Grupo:</label>
        <input type="text" name="nombre_grupo" class="form-control" placeholder="Ej: 101, 201, A, B, etc." required>
        <div class="info-text">Ejemplos: 101, 201, A, B, MAT-101, etc.</div>
      </div>
      
      <div class="form-group">
        <label>Carrera:</label>
        <select name="id_carrera" class="form-control" required 
                <?php echo $id_carrera_coordinador != 0 ? 'disabled' : ''; ?>>
          <option value="">Seleccionar carrera</option>
          <?php foreach($carreras as $carrera): ?>
            <option value="<?php echo $carrera['id_carrera']; ?>" 
                    <?php echo $id_carrera_coordinador == $carrera['id_carrera'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($carrera['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($id_carrera_coordinador != 0): ?>
          <input type="hidden" name="id_carrera" value="<?php echo $id_carrera_coordinador; ?>">
          <div class="info-text">Solo puedes crear grupos para tu carrera: <?php echo htmlspecialchars($carreras[array_search($id_carrera_coordinador, array_column($carreras, 'id_carrera'))]['nombre']); ?></div>
        <?php endif; ?>
      </div>
      
      <div class="form-group">
        <label>Especialidad (Opcional):</label>
        <select name="id_especialidad" class="form-control">
          <option value="">Sin especialidad (General)</option>
          <?php foreach($especialidades as $especialidad): ?>
            <option value="<?php echo $especialidad['id_especialidad']; ?>">
              <?php echo htmlspecialchars($especialidad['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="info-text">Solo selecciona una especialidad si el grupo es específico para una</div>
      </div>
      
      <div class="form-group">
        <label>Semestre:</label>
        <select name="semestre" class="form-control" required>
          <option value="">Seleccionar semestre</option>
          <?php for($i = 1; $i <= 12; $i++): ?>
            <option value="<?php echo $i; ?>">Semestre <?php echo $i; ?></option>
          <?php endfor; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label>Capacidad Máxima:</label>
        <input type="number" name="capacidad_maxima" class="form-control" value="40" min="1" max="100" required>
      </div>
      
      <div class="form-group">
        <label>Tutor Asignado (Opcional):</label>
        <select name="tutor_asignado" class="form-control">
          <option value="">Sin tutor asignado</option>
          <?php foreach($profesores as $profesor): ?>
            <option value="<?php echo $profesor['id_profesor']; ?>">
              <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos'] . ' (' . $profesor['clave'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- SECCIÓN PARA AGREGAR ALUMNOS MEDIANTE CSV -->
      <div class="form-group">
        <label>Agregar Alumnos (Opcional):</label>
        <div class="upload-area">
          <p>📋Subir archivo CSV con matrículas</p>
          <input type="file" name="archivo_csv" accept=".csv">
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
        <button type="submit" class="btn btn-primary">Crear Grupo</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModalCrear()">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function verGrupo(idGrupo) {
  window.location.href = `detalle_grupo.php?id=${idGrupo}`;
}

<?php if ($es_coordinador): ?>
function abrirModalCrear() {
  document.getElementById('modalCrearGrupo').style.display = 'block';
}

function cerrarModalCrear() {
  document.getElementById('modalCrearGrupo').style.display = 'none';
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
  const modal = document.getElementById('modalCrearGrupo');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
}

// Validar formulario antes de enviar
document.getElementById('formCrearGrupo').addEventListener('submit', function(e) {
  const nombre = document.querySelector('input[name="nombre_grupo"]').value;
  const semestre = document.querySelector('select[name="semestre"]').value;
  
  if (!nombre || !semestre) {
    e.preventDefault();
    alert('Por favor, complete todos los campos obligatorios.');
    return false;
  }
  
  // Validar archivo CSV si se subió
  const archivoInput = document.querySelector('input[name="archivo_csv"]');
  if (archivoInput.files.length > 0) {
    const archivo = archivoInput.files[0];
    if (!archivo.name.toLowerCase().endsWith('.csv')) {
      e.preventDefault();
      alert('Por favor, suba un archivo con formato CSV.');
      return false;
    }
  }
});
<?php endif; ?>

// FUNCIONALIDAD DE BÚSQUEDA (para rol 3 y rol 5)
<?php if ($es_coordinador || $es_rol5): ?>
document.addEventListener('DOMContentLoaded', function() {
  const barraBusqueda = document.getElementById('buscarGrupo');
  const grupos = document.querySelectorAll('.tarjeta-grupo');
  
  if (barraBusqueda) {
    barraBusqueda.addEventListener('input', function() {
      const terminoBusqueda = this.value.toLowerCase().trim();
      
      grupos.forEach(function(grupo) {
        const grupoInfo = grupo.getAttribute('data-grupo-info');
        
        if (grupoInfo.includes(terminoBusqueda)) {
          grupo.style.display = 'block';
        } else {
          grupo.style.display = 'none';
        }
      });
      
      // Mostrar mensaje si no hay resultados
      const gruposVisibles = document.querySelectorAll('.tarjeta-grupo[style="display: block"]');
      const listaGrupos = document.getElementById('lista-grupos');
      
      if (terminoBusqueda !== '' && gruposVisibles.length === 0) {
        if (!document.getElementById('sin-resultados')) {
          const sinResultados = document.createElement('div');
          sinResultados.id = 'sin-resultados';
          sinResultados.className = 'empty-state';
          sinResultados.style.gridColumn = '1 / -1';
          sinResultados.innerHTML = `
            <div style="font-size: 4em; margin-bottom: 20px; color: #ccc;"></div>
            <h3 style="color: #666; margin-bottom: 15px;">No se encontraron grupos</h3>
            <p style="color: #888; margin-bottom: 25px; font-size: 1.1em;">
              No hay grupos que coincidan con "<strong>${terminoBusqueda}</strong>"
            </p>
            <button onclick="document.getElementById('buscarGrupo').value = ''; document.getElementById('buscarGrupo').dispatchEvent(new Event('input'));" 
                    class="btn btn-primary" style="margin: 0 auto;">
              Mostrar todos los grupos
            </button>
          `;
          listaGrupos.appendChild(sinResultados);
        }
      } else {
        const sinResultados = document.getElementById('sin-resultados');
        if (sinResultados) {
          sinResultados.remove();
        }
      }
    });
  }
});
<?php endif; ?>
</script>

<?php include "footer.php"; ?>