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

// Obtener ID de academia
$id_academia = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_academia == 0) {
    header("Location: academias.php");
    exit;
}

// Verificar permisos y obtener datos de la academia
if ($rol == 2) { // PROFESOR
    $query_academia = $conexion->prepare("
        SELECT 
            a.*, 
            car.nombre as carrera_nombre, 
            esp.nombre as especialidad_nombre,
            CONCAT(u_pres.nombre, ' ', u_pres.apellidos) as presidente_nombre,
            CASE 
                WHEN a.id_presidente = p.id_profesor THEN 'presidente'
                ELSE pa.rol
            END as rol_usuario,
            p.id_profesor as id_profesor_actual
        FROM academia a
        INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
        INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        INNER JOIN profesor p_pres ON a.id_presidente = p_pres.id_profesor
        INNER JOIN usuario u_pres ON p_pres.id_usuario = u_pres.id_usuario
        LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
        LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
        WHERE a.id_academia = ? AND u.id_usuario = ? AND pa.activo = 1 AND a.activo = 1
    ");
    $query_academia->bind_param("ii", $id_academia, $id_usuario);
} else { // COORDINADOR
    // Verificar que el coordinador tenga acceso a esta academia
    $query_coordinador = $conexion->prepare("
        SELECT id_carrera FROM coordinador WHERE id_usuario = ?
    ");
    $query_coordinador->bind_param("i", $id_usuario);
    $query_coordinador->execute();
    $coordinador = $query_coordinador->get_result()->fetch_assoc();
    
    if ($coordinador['id_carrera'] == 0) {
        // Coordinador general - acceso a todas
        $query_academia = $conexion->prepare("
            SELECT 
                a.*, 
                car.nombre as carrera_nombre, 
                esp.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as presidente_nombre,
                'coordinador' as rol_usuario,
                0 as id_profesor_actual
            FROM academia a
            LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
            LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
            INNER JOIN profesor p ON a.id_presidente = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE a.id_academia = ? AND a.activo = 1
        ");
        $query_academia->bind_param("i", $id_academia);
    } else {
        // Coordinador específico - solo academias de su carrera
        $query_academia = $conexion->prepare("
            SELECT 
                a.*, 
                car.nombre as carrera_nombre, 
                esp.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as presidente_nombre,
                'coordinador' as rol_usuario,
                0 as id_profesor_actual
            FROM academia a
            LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
            LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
            INNER JOIN profesor p ON a.id_presidente = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE a.id_academia = ? AND a.activo = 1 
            AND (a.id_carrera = ? OR esp.id_carrera = ?)
        ");
        $query_academia->bind_param("iii", $id_academia, $coordinador['id_carrera'], $coordinador['id_carrera']);
    }
}

$query_academia->execute();
$academia = $query_academia->get_result()->fetch_assoc();

if (!$academia) {
    header("Location: academias.php");
    exit;
}

// Obtener miembros de la academia
$query_miembros = $conexion->prepare("
    SELECT pa.*, CONCAT(u.nombre, ' ', u.apellidos) as nombre_completo, 
           p.id_profesor, u.correo,
           CASE 
               WHEN pa.rol = 'presidente' THEN 'presidente'
               ELSE pa.rol
           END as rol_mostrar
    FROM profesor_academia pa
    INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE pa.id_academia = ? AND pa.activo = 1
    ORDER BY 
        CASE pa.rol 
            WHEN 'presidente' THEN 1
            WHEN 'vicepresidente' THEN 2
            WHEN 'secretario' THEN 3
            ELSE 4
        END,
        u.nombre
");
$query_miembros->bind_param("i", $id_academia);
$query_miembros->execute();
$miembros = $query_miembros->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener tareas de la academia
$query_tareas = $conexion->prepare("
    SELECT ta.*, 
           CONCAT(u.nombre, ' ', u.apellidos) as asignador_nombre,
           COUNT(eta.id_entrega_academia) as total_entregas,
           COUNT(CASE WHEN eta.calificacion IS NOT NULL THEN 1 END) as total_calificadas,
           CASE 
               WHEN ta.tipo_tarea = 'otro' THEN 'junta'
               ELSE ta.tipo_tarea
           END as tipo_tarea_mostrar
    FROM tareas_academia ta
    INNER JOIN profesor p ON ta.id_profesor_asigna = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    LEFT JOIN entregas_tareas_academia eta ON ta.id_tarea_academia = eta.id_tarea_academia
    WHERE ta.id_academia = ?
    GROUP BY ta.id_tarea_academia
    ORDER BY ta.fecha_limite DESC
");
$query_tareas->bind_param("i", $id_academia);
$query_tareas->execute();
$tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if (isset($_POST['accion'])) {
    if ($_POST['accion'] == 'agregar_profesor' && $rol == 3) {
        $id_profesor = intval($_POST['id_profesor']);
        $rol_profesor = $_POST['rol_profesor'];
        
        // Verificar si ya es miembro
        $check = $conexion->prepare("SELECT id_profesor_academia FROM profesor_academia WHERE id_profesor = ? AND id_academia = ? AND activo = 1");
        $check->bind_param("ii", $id_profesor, $id_academia);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $mensaje = "El profesor ya es miembro de esta academia";
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conexion->prepare("INSERT INTO profesor_academia (id_profesor, id_academia, rol, fecha_ingreso, activo) VALUES (?, ?, ?, NOW(), 1)");
            $stmt->bind_param("iis", $id_profesor, $id_academia, $rol_profesor);
            
            if ($stmt->execute()) {
                $mensaje = "Profesor agregado exitosamente";
                $tipo_mensaje = 'success';
                header("refresh:2;url=detalle_academia.php?id=" . $id_academia);
            } else {
                $mensaje = "Error al agregar profesor";
                $tipo_mensaje = 'error';
            }
        }
    }
    
    elseif ($_POST['accion'] == 'cambiar_rol' && $rol == 3) {
        $id_profesor_academia = intval($_POST['id_profesor_academia']);
        $nuevo_rol = $_POST['nuevo_rol'];
        
        $stmt = $conexion->prepare("UPDATE profesor_academia SET rol = ? WHERE id_profesor_academia = ?");
        $stmt->bind_param("si", $nuevo_rol, $id_profesor_academia);
        
        if ($stmt->execute()) {
            $mensaje = "Rol actualizado exitosamente";
            $tipo_mensaje = 'success';
            header("refresh:2;url=detalle_academia.php?id=" . $id_academia);
        } else {
            $mensaje = "Error al actualizar rol";
            $tipo_mensaje = 'error';
        }
    }
    
    elseif ($_POST['accion'] == 'eliminar_miembro' && $rol == 3) {
        $id_profesor_academia = intval($_POST['id_profesor_academia']);
        
        $stmt = $conexion->prepare("UPDATE profesor_academia SET activo = 0 WHERE id_profesor_academia = ?");
        $stmt->bind_param("i", $id_profesor_academia);
        
        if ($stmt->execute()) {
            $mensaje = "Miembro eliminado exitosamente";
            $tipo_mensaje = 'success';
            header("refresh:2;url=detalle_academia.php?id=" . $id_academia);
        } else {
            $mensaje = "Error al eliminar miembro";
            $tipo_mensaje = 'error';
        }
    }
    
    elseif ($_POST['accion'] == 'cambiar_presidente' && $rol == 3) {
        $nuevo_presidente = intval($_POST['nuevo_presidente']);
        
        $conexion->begin_transaction();
        try {
            // Quitar presidente anterior
            $stmt1 = $conexion->prepare("UPDATE profesor_academia SET rol = 'miembro' WHERE id_academia = ? AND rol = 'presidente'");
            $stmt1->bind_param("i", $id_academia);
            $stmt1->execute();
            
            // Poner nuevo presidente
            $stmt2 = $conexion->prepare("UPDATE profesor_academia SET rol = 'presidente' WHERE id_academia = ? AND id_profesor = ?");
            $stmt2->bind_param("ii", $id_academia, $nuevo_presidente);
            $stmt2->execute();
            
            // Actualizar academia
            $stmt3 = $conexion->prepare("UPDATE academia SET id_presidente = ? WHERE id_academia = ?");
            $stmt3->bind_param("ii", $nuevo_presidente, $id_academia);
            $stmt3->execute();
            
            $conexion->commit();
            $mensaje = "Presidente actualizado exitosamente";
            $tipo_mensaje = 'success';
            header("refresh:2;url=detalle_academia.php?id=" . $id_academia);
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "Error al cambiar presidente";
            $tipo_mensaje = 'error';
        }
    }
    
    // Crear nueva tarea (presidente o coordinador)
    elseif ($_POST['accion'] == 'crear_tarea' && ($rol == 3 || ($rol == 2 && $academia['rol_usuario'] == 'presidente'))) {
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $tipo_tarea = $_POST['tipo_tarea'];
        $fecha_limite = $_POST['fecha_limite'];
        
        // Obtener ID del profesor que asigna
        if ($rol == 2) {
            $id_profesor_asigna = $academia['id_profesor_actual'];
        } else {
            // Para coordinador, obtener su ID de profesor
            $query_profesor_coord = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
            $query_profesor_coord->bind_param("i", $id_usuario);
            $query_profesor_coord->execute();
            $profesor_coord = $query_profesor_coord->get_result()->fetch_assoc();
            $id_profesor_asigna = $profesor_coord['id_profesor'];
        }
        
        if (empty($titulo) || empty($fecha_limite)) {
            $mensaje = "El título y fecha límite son obligatorios";
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conexion->prepare("
                INSERT INTO tareas_academia (id_academia, id_profesor_asigna, titulo, descripcion, tipo_tarea, fecha_limite, estado) 
                VALUES (?, ?, ?, ?, ?, ?, 'activa')
            ");
            $stmt->bind_param("iissss", $id_academia, $id_profesor_asigna, $titulo, $descripcion, $tipo_tarea, $fecha_limite);
            
            if ($stmt->execute()) {
                $mensaje = "Tarea creada exitosamente";
                $tipo_mensaje = 'success';
                header("refresh:2;url=detalle_academia.php?id=" . $id_academia);
            } else {
                $mensaje = "Error al crear tarea";
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener profesores disponibles para agregar
$profesores_disponibles = [];
if ($rol == 3) {
    $query_profesores = $conexion->prepare("
        SELECT p.id_profesor, CONCAT(u.nombre, ' ', u.apellidos) as nombre_completo
        FROM profesor p
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE p.estado = '1' AND p.id_profesor NOT IN (
            SELECT pa.id_profesor 
            FROM profesor_academia pa 
            WHERE pa.id_academia = ? AND pa.activo = 1
        )
        ORDER BY u.nombre, u.apellidos
    ");
    $query_profesores->bind_param("i", $id_academia);
    $query_profesores->execute();
    $profesores_disponibles = $query_profesores->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Determinar sección activa por defecto
$seccion_activa = isset($_GET['seccion']) ? $_GET['seccion'] : 'miembros';
?>

<style>
.detalle-academia-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 25px;
}

.card-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.card-header h1 {
    margin: 0;
    font-size: 1.8em;
    font-weight: 600;
}

.rol-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
}

.card-body {
    padding: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #1565c0;
}

.info-label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.info-value {
    color: #333;
    font-size: 1.1em;
}

/* Switch de secciones */
.switch-secciones {
    display: flex;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 5px;
    margin-bottom: 25px;
    border: 2px solid #e9ecef;
}

.btn-seccion {
    flex: 1;
    padding: 12px 20px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #6c757d;
}

.btn-seccion.active {
    background: #1565c0;
    color: white;
    box-shadow: 0 2px 8px rgba(21, 101, 192, 0.3);
}

.btn-seccion:hover:not(.active) {
    background: #e9ecef;
    color: #495057;
}

/* Estilos para miembros */
.miembros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.miembro-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.3s ease;
}

.miembro-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.miembro-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.miembro-nombre {
    font-weight: 600;
    color: #333;
    margin: 0;
}

.rol-badge-miembro {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: 600;
    color: white;
}

.rol-presidente { 
    background: linear-gradient(135deg, #ff6b35, #ff8e53); 
}
.rol-vicepresidente { 
    background: linear-gradient(135deg, #4ecdc4, #44a08d); 
}
.rol-secretario { 
    background: linear-gradient(135deg, #45b7d1, #96c93d); 
}
.rol-miembro { 
    background: linear-gradient(135deg, #a8a8a8, #7a7a7a); 
}

.miembro-email {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 10px;
}

.acciones-miembro {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85em;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8em;
}

.btn-primary { background: #1565c0; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

/* Formularios */
.form-inline {
    display: flex;
    gap: 10px;
    align-items: end;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 150px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 0.9em;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.9em;
}

/* Tareas */
.tareas-container {
    margin-top: 20px;
}

.tarea-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.tarea-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.tarea-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.tarea-titulo {
    font-weight: 600;
    color: #1565c0;
    margin: 0;
    flex: 1;
}

.tarea-asignador {
    color: #666;
    font-size: 0.9em;
}

.tarea-fecha {
    color: #666;
    margin-bottom: 10px;
}

.tarea-descripcion {
    color: #555;
    line-height: 1.5;
    margin-bottom: 15px;
}

.estado-tarea {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
}

.estado-activa { background: #e8f5e8; color: #2e7d32; }
.estado-cerrada { background: #e3f2fd; color: #1565c0; }
.estado-cancelada { background: #f5f5f5; color: #757575; }

.tipo-tarea {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
    background: #e3f2fd;
    color: #1565c0;
}

.tipo-junta {
    background: #fff3e0;
    color: #f57c00;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

/* Estilos para presidente */
.btn-presidente {
    background: linear-gradient(135deg, #ff6b35, #ff8e53);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-presidente:hover {
    background: linear-gradient(135deg, #e55a2b, #e57a43);
    transform: translateY(-2px);
}

.permisos-info {
    background: #e8f5e8;
    border: 1px solid #2e7d32;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.permisos-info h4 {
    margin: 0 0 10px 0;
    color: #2e7d32;
}

.permisos-info ul {
    margin: 0;
    padding-left: 20px;
}

/* Modal crear tarea */
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
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 20px;
    border-radius: 15px 15px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.4em;
}

.modal-body {
    padding: 25px;
}

.close {
    color: white;
    float: right;
    font-size: 1.5em;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #ffebee;
}

@media (max-width: 768px) {
    .detalle-academia-container {
        margin: 20px auto;
        padding: 0 15px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-inline {
        flex-direction: column;
        align-items: stretch;
    }
    
    .miembros-grid {
        grid-template-columns: 1fr;
    }
    
    .tarea-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .switch-secciones {
        flex-direction: column;
    }
}
</style>

<div class="detalle-academia-container">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Información de permisos para presidente -->
    <?php if ($rol == 2 && $academia['rol_usuario'] == 'presidente'): ?>
        <div class="permisos-info">
            <h4>Eres el Presidente de esta Academia</h4>
            <p><strong>Tus permisos:</strong></p>
            <ul>
                <li>Crear y gestionar tareas para la academia</li>
                <li>Calificar entregas de tareas</li>
                <li>Ver todos los miembros de la academia</li>
                <li><strong>No puedes</strong> asignar roles a otros profesores</li>
                <li><strong>No puedes</strong> cambiar al presidente</li>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h1><?php echo htmlspecialchars($academia['nombre']); ?></h1>
            <span class="rol-badge rol-<?php echo strtolower($academia['rol_usuario']); ?>">
                <?php 
                    if ($academia['rol_usuario'] == 'presidente') {
                        echo 'Eres el Presidente';
                    } elseif ($academia['rol_usuario'] == 'coordinador') {
                        echo 'Coordinador';
                    } else {
                        echo ucfirst($academia['rol_usuario']);
                    }
                ?>
            </span>
        </div>
        
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Descripción</div>
                    <div class="info-value"><?php echo $academia['descripcion'] ? htmlspecialchars($academia['descripcion']) : 'Sin descripción'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Presidente</div>
                    <div class="info-value"><?php echo htmlspecialchars($academia['presidente_nombre']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Vinculación</div>
                    <div class="info-value">
                        <?php 
                            if ($academia['carrera_nombre']) {
                                echo "Carrera: " . htmlspecialchars($academia['carrera_nombre']);
                            } elseif ($academia['especialidad_nombre']) {
                                echo "Especialidad: " . htmlspecialchars($academia['especialidad_nombre']);
                            } else {
                                echo 'General';
                            }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Fecha de Creación</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($academia['fecha_creacion'])); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Switch de secciones -->
    <div class="switch-secciones">
        <button class="btn-seccion <?php echo $seccion_activa == 'miembros' ? 'active' : ''; ?>" 
                onclick="cambiarSeccion('miembros')">
            <i class="fas fa-users me-2"></i>Miembros
        </button>
        <button class="btn-seccion <?php echo $seccion_activa == 'tareas' ? 'active' : ''; ?>" 
                onclick="cambiarSeccion('tareas')">
            <i class="fas fa-tasks me-2"></i>Tareas
        </button>
    </div>

    <!-- SECCIÓN DE MIEMBROS -->
    <div id="seccion-miembros" class="seccion" style="<?php echo $seccion_activa == 'miembros' ? 'display: block;' : 'display: none;' ?>">
        <div class="card">
            <div class="card-header">
                <h2>Miembros de la Academia</h2>
                <span class="rol-badge"><?php echo count($miembros); ?> miembros</span>
            </div>
            
            <div class="card-body">
                <?php if ($rol == 3): ?>
                    <!-- Formulario para agregar profesor (solo coordinadores) -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <h3 style="margin-top: 0; color: #1565c0;">Agregar Nuevo Miembro</h3>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="accion" value="agregar_profesor">
                            
                            <div class="form-group">
                                <label for="id_profesor">Profesor</label>
                                <select id="id_profesor" name="id_profesor" class="form-control" required>
                                    <option value="">Seleccionar profesor...</option>
                                    <?php foreach($profesores_disponibles as $profesor): ?>
                                        <option value="<?php echo $profesor['id_profesor']; ?>">
                                            <?php echo htmlspecialchars($profesor['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="rol_profesor">Rol</label>
                                <select id="rol_profesor" name="rol_profesor" class="form-control" required>
                                    <option value="miembro">Miembro</option>
                                    <option value="secretario">Secretario</option>
                                    <option value="vicepresidente">Vicepresidente</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-success">Agregar Miembro</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Formulario para cambiar presidente (solo coordinadores) -->
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <h3 style="margin-top: 0; color: #f57c00;">Cambiar Presidente</h3>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="accion" value="cambiar_presidente">
                            
                            <div class="form-group">
                                <label for="nuevo_presidente">Nuevo Presidente</label>
                                <select id="nuevo_presidente" name="nuevo_presidente" class="form-control" required>
                                    <?php foreach($miembros as $miembro): ?>
                                        <option value="<?php echo $miembro['id_profesor']; ?>" 
                                            <?php echo $miembro['rol'] == 'presidente' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($miembro['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-warning">Cambiar Presidente</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Lista de miembros -->
                <div class="miembros-grid">
                    <?php if (count($miembros) > 0): ?>
                        <?php foreach($miembros as $miembro): ?>
                            <div class="miembro-card">
                                <div class="miembro-header">
                                    <h3 class="miembro-nombre"><?php echo htmlspecialchars($miembro['nombre_completo']); ?></h3>
                                    <span class="rol-badge-miembro rol-<?php echo $miembro['rol_mostrar']; ?>">
                                        <?php 
                                            if ($miembro['rol_mostrar'] == 'presidente') {
                                                echo '<i class="fas fa-crown me-1"></i>Presidente';
                                            } else {
                                                echo ucfirst($miembro['rol_mostrar']);
                                            }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="miembro-email"><?php echo htmlspecialchars($miembro['correo']); ?></div>
                                
                                <?php if ($rol == 3): ?>
                                    <div class="acciones-miembro">
                                        <?php if ($miembro['rol'] != 'presidente'): ?>
                                            <!-- Cambiar rol -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="cambiar_rol">
                                                <input type="hidden" name="id_profesor_academia" value="<?php echo $miembro['id_profesor_academia']; ?>">
                                                <select name="nuevo_rol" onchange="this.form.submit()" class="form-control" style="display: inline; width: auto; margin-right: 5px;">
                                                    <option value="miembro" <?php echo $miembro['rol'] == 'miembro' ? 'selected' : ''; ?>>Miembro</option>
                                                    <option value="secretario" <?php echo $miembro['rol'] == 'secretario' ? 'selected' : ''; ?>>Secretario</option>
                                                    <option value="vicepresidente" <?php echo $miembro['rol'] == 'vicepresidente' ? 'selected' : ''; ?>>Vicepresidente</option>
                                                </select>
                                            </form>
                                            
                                            <!-- Eliminar miembro -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar_miembro">
                                                <input type="hidden" name="id_profesor_academia" value="<?php echo $miembro['id_profesor_academia']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('¿Estás seguro de eliminar a este miembro?')">
                                                    Eliminar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-presidente btn-sm" style="cursor: default;">
                                                <i class="fas fa-crown me-1"></i>Presidente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No hay miembros en esta academia</h3>
                            <p>Agrega profesores para comenzar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE TAREAS -->
    <div id="seccion-tareas" class="seccion" style="<?php echo $seccion_activa == 'tareas' ? 'display: block;' : 'display: none;' ?>">
        <div class="card">
            <div class="card-header">
                <h2>Tareas de la Academia</h2>
                <span class="rol-badge"><?php echo count($tareas); ?> tareas</span>
            </div>
            
            <div class="card-body">
                <?php if ($rol == 3 || ($rol == 2 && $academia['rol_usuario'] == 'presidente')): ?>
                    <!-- Botón para crear tarea (solo presidente y coordinador) -->
                    <div style="text-align: right; margin-bottom: 20px;">
                        <button class="btn-presidente" onclick="abrirModalTarea()">
                            <i class="fas fa-plus me-2"></i>Crear Nueva Tarea
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="tareas-container">
                    <?php if (count($tareas) > 0): ?>
                        <?php foreach($tareas as $tarea): ?>
                            <div class="tarea-card">
                                <div class="tarea-header">
                                    <h3 class="tarea-titulo"><?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span class="tipo-tarea <?php echo $tarea['tipo_tarea_mostrar'] == 'junta' ? 'tipo-junta' : ''; ?>">
                                            <?php 
                                                if ($tarea['tipo_tarea_mostrar'] == 'junta') {
                                                    echo '<i class="fas fa-users me-1"></i>Junta';
                                                } else {
                                                    echo ucfirst($tarea['tipo_tarea_mostrar']);
                                                }
                                            ?>
                                        </span>
                                        <span class="estado-tarea estado-<?php echo $tarea['estado']; ?>">
                                            <?php echo ucfirst($tarea['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="tarea-asignador">
                                    Asignada por: <?php echo htmlspecialchars($tarea['asignador_nombre']); ?>
                                </div>
                                
                                <div class="tarea-fecha">
                                    <strong>Fecha límite:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?>
                                    | <strong>Entregas:</strong> <?php echo $tarea['total_entregas']; ?> 
                                    | <strong>Calificadas:</strong> <?php echo $tarea['total_calificadas']; ?>
                                </div>
                                
                                <?php if ($tarea['descripcion']): ?>
                                    <div class="tarea-descripcion">
                                        <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="acciones-miembro">
                                    <?php if ($rol == 2): ?>
                                        <?php if ($tarea['tipo_tarea'] != 'otro'): ?>
                                            <button class="btn btn-primary" onclick="entregarTarea(<?php echo $tarea['id_tarea_academia']; ?>)">
                                                Entregar Tarea
                                            </button>
                                        <?php else: ?>
                                            <span class="btn btn-secondary" style="cursor: default;">
                                                <i class="fas fa-info-circle me-1"></i>Es una junta
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($rol == 3 || ($rol == 2 && $academia['rol_usuario'] == 'presidente')): ?>
                                        <button class="btn btn-success" onclick="verEntregas(<?php echo $tarea['id_tarea_academia']; ?>)">
                                            Ver Entregas
                                        </button>
                                        <?php if ($rol == 3 || ($tarea['id_profesor_asigna'] == $academia['id_profesor_actual'])): ?>
                                            <button class="btn btn-warning" onclick="editarTarea(<?php echo $tarea['id_tarea_academia']; ?>)">
                                                Editar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No hay tareas en esta academia</h3>
                            <p>Las tareas asignadas aparecerán aquí</p>
                            <?php if ($rol == 3 || ($rol == 2 && $academia['rol_usuario'] == 'presidente')): ?>
                                <button class="btn-presidente" onclick="abrirModalTarea()" style="margin-top: 20px;">
                                    <i class="fas fa-plus me-2"></i>Crear Primera Tarea
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="academias.php" class="btn btn-secondary">Volver a Academias</a>
    </div>
</div>

<!-- Modal para crear tarea -->
<div id="modalTarea" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus me-2"></i>Crear Nueva Tarea</h3>
            <span class="close" onclick="cerrarModalTarea()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="formTarea">
                <input type="hidden" name="accion" value="crear_tarea">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="titulo">Título de la Tarea *</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="tipo_tarea">Tipo de Tarea *</label>
                    <select id="tipo_tarea" name="tipo_tarea" class="form-control" required onchange="toggleJuntaInfo()">
                        <option value="avance_grupo">Avance de Grupo</option>
                        <option value="informe">Informe</option>
                        <option value="revision">Revisión</option>
                        <option value="otro">Junta/Reunión</option>
                    </select>
                </div>
                
                <div id="junta-info" style="display: none; background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong><i class="fas fa-info-circle me-2"></i>Información sobre juntas:</strong></p>
                    <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                        <li>Las juntas son para anunciar reuniones de academia</li>
                        <li>No se pueden subir archivos en este tipo de tarea</li>
                        <li>Los profesores solo podrán confirmar asistencia</li>
                    </ul>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="fecha_limite">Fecha Límite *</label>
                    <input type="datetime-local" id="fecha_limite" name="fecha_limite" class="form-control" required>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalTarea()">Cancelar</button>
                    <button type="submit" class="btn btn-presidente">Crear Tarea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cambiarSeccion(seccion) {
    // Ocultar todas las secciones
    document.getElementById('seccion-miembros').style.display = 'none';
    document.getElementById('seccion-tareas').style.display = 'none';
    
    // Mostrar la sección seleccionada
    document.getElementById('seccion-' + seccion).style.display = 'block';
    
    // Actualizar botones activos
    document.querySelectorAll('.btn-seccion').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Actualizar URL sin recargar la página
    const url = new URL(window.location);
    url.searchParams.set('seccion', seccion);
    window.history.replaceState({}, '', url);
}

function abrirModalTarea() {
    document.getElementById('modalTarea').style.display = 'block';
}

function cerrarModalTarea() {
    document.getElementById('modalTarea').style.display = 'none';
}

function toggleJuntaInfo() {
    const tipoTarea = document.getElementById('tipo_tarea').value;
    const juntaInfo = document.getElementById('junta-info');
    
    if (tipoTarea === 'otro') {
        juntaInfo.style.display = 'block';
    } else {
        juntaInfo.style.display = 'none';
    }
}

function entregarTarea(idTarea) {
    window.location.href = `entregar_tarea_profesor.php?id=${idTarea}`;
}

function verEntregas(idTarea) {
    window.location.href = `entregas_academia.php?id=${idTarea}`;
}

function editarTarea(idTarea) {
    window.location.href = `editar_tarea_academia.php?id=${idTarea}`;
}

// Cerrar modal si se hace clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('modalTarea');
    if (event.target == modal) {
        cerrarModalTarea();
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    toggleJuntaInfo();
});
</script>

<?php include "footer.php"; ?>