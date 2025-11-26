<?php
ob_start();
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$id_tarea_academia = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_tarea_academia == 0) {
    header("Location: academias.php");
    exit;
}

// Obtener información de la tarea y verificar permisos
$query_tarea = $conexion->prepare("
    SELECT ta.*, a.nombre as academia_nombre, 
           CONCAT(u.nombre, ' ', u.apellidos) as asignador_nombre,
           p.id_profesor as id_profesor_actual
    FROM tareas_academia ta
    INNER JOIN academia a ON ta.id_academia = a.id_academia
    INNER JOIN profesor p_asigna ON ta.id_profesor_asigna = p_asigna.id_profesor
    INNER JOIN usuario u ON p_asigna.id_usuario = u.id_usuario
    INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
    INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
    WHERE ta.id_tarea_academia = ? AND p.id_usuario = ? AND pa.activo = 1
");
$query_tarea->bind_param("ii", $id_tarea_academia, $id_usuario);
$query_tarea->execute();
$tarea = $query_tarea->get_result()->fetch_assoc();

if (!$tarea) {
    header("Location: academias.php");
    exit;
}

// Verificar si ya existe una entrega
$query_entrega = $conexion->prepare("
    SELECT * FROM entregas_tareas_academia 
    WHERE id_tarea_academia = ? AND id_profesor_entrega = ?
");
$query_entrega->bind_param("ii", $id_tarea_academia, $tarea['id_profesor_actual']);
$query_entrega->execute();
$entrega_existente = $query_entrega->get_result()->fetch_assoc();

$mensaje = '';
$tipo_mensaje = '';

// Procesar entrega
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comentario = trim($_POST['comentario']);
    
    // Validar archivo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
        $archivo = $_FILES['archivo'];
        $nombre_original = $archivo['name'];
        $tipo_archivo = $archivo['type'];
        $tamaño = $archivo['size'];
        $tmp_name = $archivo['tmp_name'];
        
        // Validar tipo de archivo
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $mensaje = "Tipo de archivo no permitido. Extensiones permitidas: " . implode(', ', $extensiones_permitidas);
            $tipo_mensaje = 'error';
        } elseif ($tamaño > 10 * 1024 * 1024) { // 10MB
            $mensaje = "El archivo es demasiado grande. Tamaño máximo: 10MB";
            $tipo_mensaje = 'error';
        } else {
            // Generar nombre único para el archivo
            $nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
            $ruta_destino = "uploads/academias/" . $nombre_archivo;
            
            // Crear directorio si no existe
            if (!is_dir("uploads/academias")) {
                mkdir("uploads/academias", 0777, true);
            }
            
            if (move_uploaded_file($tmp_name, $ruta_destino)) {
                if ($entrega_existente) {
                    // Actualizar entrega existente
                    $stmt = $conexion->prepare("
                        UPDATE entregas_tareas_academia 
                        SET archivo_entrega = ?, nombre_archivo_original = ?, 
                            comentario_entrega = ?, fecha_entrega = NOW(), estado = 'entregado'
                        WHERE id_entrega_academia = ?
                    ");
                    $stmt->bind_param("sssi", $ruta_destino, $nombre_original, $comentario, $entrega_existente['id_entrega_academia']);
                } else {
                    // Crear nueva entrega
                    $stmt = $conexion->prepare("
                        INSERT INTO entregas_tareas_academia 
                        (id_tarea_academia, id_profesor_entrega, archivo_entrega, nombre_archivo_original, comentario_entrega, estado) 
                        VALUES (?, ?, ?, ?, ?, 'entregado')
                    ");
                    $stmt->bind_param("iisss", $id_tarea_academia, $tarea['id_profesor_actual'], $ruta_destino, $nombre_original, $comentario);
                }
                
                if ($stmt->execute()) {
                    $mensaje = $entrega_existente ? "Entrega actualizada exitosamente" : "Tarea entregada exitosamente";
                    $tipo_mensaje = 'success';
                    header("refresh:2;url=detalle_academia.php?id=" . $tarea['id_academia'] . "&seccion=tareas");
                } else {
                    $mensaje = "Error al guardar la entrega";
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = "Error al subir el archivo";
                $tipo_mensaje = 'error';
            }
        }
    } else {
        $mensaje = "Debes seleccionar un archivo para entregar";
        $tipo_mensaje = 'error';
    }
}
?>

<style>
.entregar-tarea-container {
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

.file-input-container {
    position: relative;
}

.file-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px dashed #e0e0e0;
    border-radius: 8px;
    background: #fafafa;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-input:hover {
    border-color: #1565c0;
    background: #f0f7ff;
}

.file-name {
    margin-top: 8px;
    font-size: 0.9em;
    color: #666;
}

.btn-submit {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
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
    background: linear-gradient(135deg, #218838, #1e9e8a);
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

.entrega-existente {
    background: #fff3e0;
    border: 1px solid #ffb74d;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.entrega-existente h4 {
    margin: 0 0 10px 0;
    color: #f57c00;
}

@media (max-width: 768px) {
    .entregar-tarea-container {
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

<div class="entregar-tarea-container">
    <div class="card">
        <div class="card-header">
            <h1>
                <?php echo $entrega_existente ? 'Actualizar Entrega' : 'Entregar Tarea'; ?>
            </h1>
        </div>
        
        <div class="card-body">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'error'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($entrega_existente): ?>
                <div class="entrega-existente">
                    <h4><i class="fas fa-info-circle me-2"></i>Ya tienes una entrega existente</h4>
                    <p><strong>Archivo actual:</strong> <?php echo htmlspecialchars($entrega_existente['nombre_archivo_original']); ?></p>
                    <p><strong>Fecha de entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($entrega_existente['fecha_entrega'])); ?></p>
                    <p>Puedes actualizar tu entrega subiendo un nuevo archivo.</p>
                </div>
            <?php endif; ?>
            
            <div class="info-tarea">
                <h3 style="margin-top: 0; color: #1565c0;">Información de la Tarea</h3>
                <div class="info-item">
                    <span class="info-label">Academia:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tarea['academia_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Título:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tarea['titulo']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Asignada por:</span>
                    <span class="info-value"><?php echo htmlspecialchars($tarea['asignador_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha límite:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?></span>
                </div>
                <?php if ($tarea['descripcion']): ?>
                    <div class="info-item">
                        <span class="info-label">Descripción:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="archivo">Archivo a entregar *</label>
                    <div class="file-input-container">
                        <input type="file" id="archivo" name="archivo" class="form-control" required 
                               accept=".pdf,.doc,.docx,.txt,.zip,.rar,.jpg,.jpeg,.png">
                        <div class="file-name" id="fileName">No se ha seleccionado ningún archivo</div>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Formatos permitidos: PDF, DOC, DOCX, TXT, ZIP, RAR, JPG, JPEG, PNG (Máximo 10MB)
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="comentario">Comentario (opcional)</label>
                    <textarea id="comentario" name="comentario" class="form-control" 
                              placeholder="Agrega algún comentario sobre tu entrega..."><?php echo isset($_POST['comentario']) ? htmlspecialchars($_POST['comentario']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>
                    <?php echo $entrega_existente ? 'Actualizar Entrega' : 'Entregar Tarea'; ?>
                </button>
                
                <a href="detalle_academia.php?id=<?php echo $tarea['id_academia']; ?>&seccion=tareas" class="btn-cancel">
                    <i class="fas fa-arrow-left me-2"></i>Cancelar y Volver
                </a>
            </form>
        </div>
    </div>
</div>

<script>
// Mostrar nombre del archivo seleccionado
document.getElementById('archivo').addEventListener('change', function(e) {
    const fileName = document.getElementById('fileName');
    if (this.files.length > 0) {
        fileName.textContent = this.files[0].name;
        fileName.style.color = '#1565c0';
        fileName.style.fontWeight = '600';
    } else {
        fileName.textContent = 'No se ha seleccionado ningún archivo';
        fileName.style.color = '#666';
        fileName.style.fontWeight = 'normal';
    }
});

// Validar formulario antes de enviar
document.querySelector('form').addEventListener('submit', function(e) {
    const archivo = document.getElementById('archivo');
    if (archivo.files.length === 0) {
        e.preventDefault();
        alert('Debes seleccionar un archivo para entregar');
        return false;
    }
});
</script>

<?php include "footer.php"; ?>