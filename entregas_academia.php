<?php
ob_start();
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];
$id_tarea_academia = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_tarea_academia == 0) {
    header("Location: academias.php");
    exit;
}

// Obtener información de la tarea y verificar permisos
if ($rol == 2) { // PROFESOR - solo si es presidente
    $query_tarea = $conexion->prepare("
        SELECT ta.*, a.nombre as academia_nombre, a.id_presidente,
               CONCAT(u.nombre, ' ', u.apellidos) as asignador_nombre,
               p.id_profesor as id_profesor_actual
        FROM tareas_academia ta
        INNER JOIN academia a ON ta.id_academia = a.id_academia
        INNER JOIN profesor p_asigna ON ta.id_profesor_asigna = p_asigna.id_profesor
        INNER JOIN usuario u ON p_asigna.id_usuario = u.id_usuario
        INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
        INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
        WHERE ta.id_tarea_academia = ? AND p.id_usuario = ? AND pa.activo = 1
        AND a.id_presidente = p.id_profesor
    ");
    $query_tarea->bind_param("ii", $id_tarea_academia, $id_usuario);
} else { // COORDINADOR
    $query_tarea = $conexion->prepare("
        SELECT ta.*, a.nombre as academia_nombre,
               CONCAT(u.nombre, ' ', u.apellidos) as asignador_nombre
        FROM tareas_academia ta
        INNER JOIN academia a ON ta.id_academia = a.id_academia
        INNER JOIN profesor p_asigna ON ta.id_profesor_asigna = p_asigna.id_profesor
        INNER JOIN usuario u ON p_asigna.id_usuario = u.id_usuario
        WHERE ta.id_tarea_academia = ?
    ");
    $query_tarea->bind_param("i", $id_tarea_academia);
}

$query_tarea->execute();
$tarea = $query_tarea->get_result()->fetch_assoc();

if (!$tarea) {
    header("Location: academias.php");
    exit;
}

// Obtener entregas de la tarea
$query_entregas = $conexion->prepare("
    SELECT eta.*, CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
           u.correo as profesor_email
    FROM entregas_tareas_academia eta
    INNER JOIN profesor p ON eta.id_profesor_entrega = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE eta.id_tarea_academia = ?
    ORDER BY eta.fecha_entrega DESC
");
$query_entregas->bind_param("i", $id_tarea_academia);
$query_entregas->execute();
$entregas = $query_entregas->get_result()->fetch_all(MYSQLI_ASSOC);

$mensaje = '';
$tipo_mensaje = '';

// Procesar calificación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'calificar') {
    $id_entrega_academia = intval($_POST['id_entrega_academia']);
    $calificacion = floatval($_POST['calificacion']);
    $comentario_evaluador = trim($_POST['comentario_evaluador']);
    
    if ($calificacion < 0 || $calificacion > 100) {
        $mensaje = "La calificación debe estar entre 0 y 100";
        $tipo_mensaje = 'error';
    } else {
        $stmt = $conexion->prepare("
            UPDATE entregas_tareas_academia 
            SET calificacion = ?, comentario_evaluador = ?, fecha_calificacion = NOW(), estado = 'calificado'
            WHERE id_entrega_academia = ?
        ");
        $stmt->bind_param("dsi", $calificacion, $comentario_evaluador, $id_entrega_academia);
        
        if ($stmt->execute()) {
            $mensaje = "Calificación guardada exitosamente";
            $tipo_mensaje = 'success';
            header("refresh:2;url=entregas_academia.php?id=" . $id_tarea_academia);
        } else {
            $mensaje = "Error al guardar la calificación";
            $tipo_mensaje = 'error';
        }
    }
}
?>

<style>
.entregas-container {
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

.card-body {
    padding: 30px;
}

.info-tarea {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    border-left: 4px solid #1565c0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.info-item {
    padding: 10px;
}

.info-label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.info-value {
    color: #333;
}

.estadisticas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.estadistica-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.estadistica-numero {
    font-size: 2em;
    font-weight: 700;
    color: #1565c0;
    margin-bottom: 5px;
}

.estadistica-label {
    color: #666;
    font-size: 0.9em;
}

.entrega-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.entrega-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.entrega-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.entrega-profesor {
    font-weight: 600;
    color: #333;
    font-size: 1.2em;
}

.entrega-email {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 10px;
}

.entrega-fecha {
    color: #666;
    margin-bottom: 10px;
}

.entrega-archivo {
    margin-bottom: 15px;
}

.btn-descargar {
    background: #1565c0;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9em;
    transition: all 0.3s ease;
}

.btn-descargar:hover {
    background: #1976d2;
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
}

.entrega-comentario {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 3px solid #1565c0;
}

.calificacion-section {
    background: #e8f5e8;
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
    border: 1px solid #c8e6c9;
}

.calificacion-form {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 15px;
    align-items: end;
}

.form-group {
    margin-bottom: 0;
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

.btn-calificar {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-calificar:hover {
    background: #218838;
}

.calificacion-actual {
    font-size: 1.5em;
    font-weight: 700;
    color: #28a745;
    text-align: center;
}

.estado-entrega {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
}

.estado-entregado { background: #fff3e0; color: #f57c00; }
.estado-calificado { background: #e8f5e8; color: #2e7d32; }

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state h3 {
    color: #999;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .entregas-container {
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
    
    .calificacion-form {
        grid-template-columns: 1fr;
    }
    
    .entrega-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="entregas-container">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h1>Entregas de Tarea</h1>
            <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 0.9em; font-weight: 600;">
                <?php echo count($entregas); ?> entregas
            </span>
        </div>
        
        <div class="card-body">
            <!-- Información de la tarea -->
            <div class="info-tarea">
                <h3 style="margin-top: 0; color: #1565c0;">Información de la Tarea</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Academia:</div>
                        <div class="info-value"><?php echo htmlspecialchars($tarea['academia_nombre']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Título:</div>
                        <div class="info-value"><?php echo htmlspecialchars($tarea['titulo']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Asignada por:</div>
                        <div class="info-value"><?php echo htmlspecialchars($tarea['asignador_nombre']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fecha límite:</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="estadisticas">
                <?php
                $total_entregas = count($entregas);
                $entregas_calificadas = 0;
                $promedio_calificaciones = 0;
                
                foreach ($entregas as $entrega) {
                    if ($entrega['estado'] == 'calificado' && $entrega['calificacion'] !== null) {
                        $entregas_calificadas++;
                        $promedio_calificaciones += $entrega['calificacion'];
                    }
                }
                
                $promedio_calificaciones = $entregas_calificadas > 0 ? $promedio_calificaciones / $entregas_calificadas : 0;
                ?>
                <div class="estadistica-card">
                    <div class="estadistica-numero"><?php echo $total_entregas; ?></div>
                    <div class="estadistica-label">Total Entregas</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero"><?php echo $entregas_calificadas; ?></div>
                    <div class="estadistica-label">Calificadas</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero"><?php echo number_format($promedio_calificaciones, 1); ?></div>
                    <div class="estadistica-label">Promedio</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero"><?php echo $total_entregas - $entregas_calificadas; ?></div>
                    <div class="estadistica-label">Pendientes</div>
                </div>
            </div>
            
            <!-- Lista de entregas -->
            <?php if (count($entregas) > 0): ?>
                <?php foreach($entregas as $entrega): ?>
                    <div class="entrega-card">
                        <div class="entrega-header">
                            <div>
                                <div class="entrega-profesor"><?php echo htmlspecialchars($entrega['profesor_nombre']); ?></div>
                                <div class="entrega-email"><?php echo htmlspecialchars($entrega['profesor_email']); ?></div>
                            </div>
                            <span class="estado-entrega estado-<?php echo $entrega['estado']; ?>">
                                <?php echo ucfirst($entrega['estado']); ?>
                            </span>
                        </div>
                        
                        <div class="entrega-fecha">
                            <strong>Fecha de entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                            <?php if ($entrega['fecha_calificacion']): ?>
                                | <strong>Calificada:</strong> <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_calificacion'])); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="entrega-archivo">
                            <strong>Archivo:</strong> 
                            <a href="<?php echo $entrega['archivo_entrega']; ?>" class="btn-descargar" download>
                                <i class="fas fa-download me-1"></i>Descargar <?php echo htmlspecialchars($entrega['nombre_archivo_original']); ?>
                            </a>
                        </div>
                        
                        <?php if ($entrega['comentario_entrega']): ?>
                            <div class="entrega-comentario">
                                <strong>Comentario del profesor:</strong><br>
                                <?php echo nl2br(htmlspecialchars($entrega['comentario_entrega'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($entrega['estado'] == 'calificado' && $entrega['calificacion'] !== null): ?>
                            <div class="calificacion-section">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                                    <div>
                                        <strong>Calificación:</strong>
                                        <div class="calificacion-actual"><?php echo number_format($entrega['calificacion'], 1); ?>/100</div>
                                    </div>
                                    <?php if ($entrega['comentario_evaluador']): ?>
                                        <div style="flex: 1;">
                                            <strong>Comentario del evaluador:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($entrega['comentario_evaluador'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($rol == 3 || ($rol == 2 && isset($tarea['id_presidente']) && $tarea['id_presidente'] == $tarea['id_profesor_actual'])): ?>
                            <!-- Formulario para calificar -->
                            <div class="calificacion-section">
                                <h4 style="margin-top: 0; color: #2e7d32;">Calificar Entrega</h4>
                                <form method="POST" class="calificacion-form">
                                    <input type="hidden" name="accion" value="calificar">
                                    <input type="hidden" name="id_entrega_academia" value="<?php echo $entrega['id_entrega_academia']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="calificacion_<?php echo $entrega['id_entrega_academia']; ?>">Calificación (0-100)</label>
                                        <input type="number" id="calificacion_<?php echo $entrega['id_entrega_academia']; ?>" 
                                               name="calificacion" class="form-control" min="0" max="100" step="0.1" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="comentario_<?php echo $entrega['id_entrega_academia']; ?>">Comentario (opcional)</label>
                                        <input type="text" id="comentario_<?php echo $entrega['id_entrega_academia']; ?>" 
                                               name="comentario_evaluador" class="form-control" 
                                               placeholder="Agrega un comentario sobre la entrega...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn-calificar">
                                            <i class="fas fa-check me-1"></i>Calificar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay entregas para esta tarea</h3>
                    <p>Los profesores aún no han entregado esta tarea.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="detalle_academia.php?id=<?php echo $tarea['id_academia']; ?>&seccion=tareas" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la Academia
        </a>
    </div>
</div>

<script>
// Validar calificaciones
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const calificacion = this.querySelector('input[name="calificacion"]');
        if (calificacion) {
            const valor = parseFloat(calificacion.value);
            if (valor < 0 || valor > 100) {
                e.preventDefault();
                alert('La calificación debe estar entre 0 y 100');
                return false;
            }
        }
    });
});
</script>

<?php include "footer.php"; ?>