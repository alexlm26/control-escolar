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
if ($rol == 2) { // PROFESOR - solo si es el que asignó la tarea o presidente
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
        AND (ta.id_profesor_asigna = p.id_profesor OR a.id_presidente = p.id_profesor)
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

$mensaje = '';
$tipo_mensaje = '';

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $tipo_tarea = $_POST['tipo_tarea'];
    $fecha_limite = $_POST['fecha_limite'];
    $estado = $_POST['estado'];
    
    if (empty($titulo) || empty($fecha_limite)) {
        $mensaje = "El título y fecha límite son obligatorios";
        $tipo_mensaje = 'error';
    } else {
        $stmt = $conexion->prepare("
            UPDATE tareas_academia 
            SET titulo = ?, descripcion = ?, tipo_tarea = ?, fecha_limite = ?, estado = ?
            WHERE id_tarea_academia = ?
        ");
        $stmt->bind_param("sssssi", $titulo, $descripcion, $tipo_tarea, $fecha_limite, $estado, $id_tarea_academia);
        
        if ($stmt->execute()) {
            $mensaje = "Tarea actualizada exitosamente";
            $tipo_mensaje = 'success';
            header("refresh:2;url=detalle_academia.php?id=" . $tarea['id_academia'] . "&seccion=tareas");
        } else {
            $mensaje = "Error al actualizar la tarea";
            $tipo_mensaje = 'error';
        }
    }
}
?>

<style>
.editar-tarea-container {
    max-width: 800px;
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

.info-item {
    margin-bottom: 10px;
    display: flex;
}

.info-label {
    font-weight: 600;
    min-width: 120px;
    color: #555;
}

.info-value {
    color: #333;
    flex: 1;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #1565c0;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.btn-submit {
    background: linear-gradient(135deg, #ffc107, #ffb300);
    color: #212529;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #e0a800, #e6a200);
    transform: translateY(-2px);
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-size: 1em;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    margin-top: 10px;
    width: 100%;
}

.btn-cancel:hover {
    background: #5a6268;
    color: white;
    text-decoration: none;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.junta-info {
    background: #fff3e0;
    border: 1px solid #ffb74d;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.junta-info h4 {
    margin: 0 0 10px 0;
    color: #f57c00;
}

@media (max-width: 768px) {
    .editar-tarea-container {
        margin: 20px auto;
        padding: 0 15px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .info-item {
        flex-direction: column;
    }
    
    .info-label {
        min-width: auto;
        margin-bottom: 5px;
    }
}
</style>

<div class="editar-tarea-container">
    <div class="card">
        <div class="card-header">
            <h1>Editar Tarea</h1>
        </div>
        
        <div class="card-body">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'error'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-tarea">
                <h3 style="margin-top: 0; color: #1565c0;">Información Actual</h3>
                <div class="info-item">
                    <span class="info-label">Academia:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tarea['academia_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Asignada por:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tarea['asignador_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha de creación:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])); ?></span>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="titulo">Título de la Tarea *</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required 
                           value="<?php echo htmlspecialchars($tarea['titulo']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="4"><?php echo htmlspecialchars($tarea['descripcion']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="tipo_tarea">Tipo de Tarea *</label>
                    <select id="tipo_tarea" name="tipo_tarea" class="form-control" required onchange="toggleJuntaInfo()">
                        <option value="avance_grupo" <?php echo $tarea['tipo_tarea'] == 'avance_grupo' ? 'selected' : ''; ?>>Avance de Grupo</option>
                        <option value="informe" <?php echo $tarea['tipo_tarea'] == 'informe' ? 'selected' : ''; ?>>Informe</option>
                        <option value="revision" <?php echo $tarea['tipo_tarea'] == 'revision' ? 'selected' : ''; ?>>Revisión</option>
                        <option value="otro" <?php echo $tarea['tipo_tarea'] == 'otro' ? 'selected' : ''; ?>>Junta/Reunión</option>
                    </select>
                </div>
                
                <div id="junta-info" style="<?php echo $tarea['tipo_tarea'] == 'otro' ? 'display: block;' : 'display: none;'; ?> background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong><i class="fas fa-info-circle me-2"></i>Información sobre juntas:</strong></p>
                    <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                        <li>Las juntas son para anunciar reuniones de academia</li>
                        <li>No se pueden subir archivos en este tipo de tarea</li>
                        <li>Los profesores solo podrán confirmar asistencia</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <label for="fecha_limite">Fecha Límite *</label>
                    <input type="datetime-local" id="fecha_limite" name="fecha_limite" class="form-control" required 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($tarea['fecha_limite'])); ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado">Estado *</label>
                    <select id="estado" name="estado" class="form-control" required>
                        <option value="activa" <?php echo $tarea['estado'] == 'activa' ? 'selected' : ''; ?>>Activa</option>
                        <option value="cerrada" <?php echo $tarea['estado'] == 'cerrada' ? 'selected' : ''; ?>>Cerrada</option>
                        <option value="cancelada" <?php echo $tarea['estado'] == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
                
                <a href="detalle_academia.php?id=<?php echo $tarea['id_academia']; ?>&seccion=tareas" class="btn-cancel">
                    <i class="fas fa-arrow-left me-2"></i>Cancelar y Volver
                </a>
            </form>
        </div>
    </div>
</div>

<script>
function toggleJuntaInfo() {
    const tipoTarea = document.getElementById('tipo_tarea').value;
    const juntaInfo = document.getElementById('junta-info');
    
    if (tipoTarea === 'otro') {
        juntaInfo.style.display = 'block';
    } else {
        juntaInfo.style.display = 'none';
    }
}

// Validar fecha límite
document.getElementById('fecha_limite').addEventListener('change', function() {
    const fechaLimite = new Date(this.value);
    const ahora = new Date();
    
    if (fechaLimite < ahora) {
        alert('La fecha límite no puede ser en el pasado');
        this.value = '<?php echo date('Y-m-d\TH:i', strtotime($tarea['fecha_limite'])); ?>';
    }
});

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    toggleJuntaInfo();
});
</script>

<?php include "footer.php"; ?>