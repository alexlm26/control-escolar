<?php
ob_start();
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 3) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener información del coordinador
$query_coordinador = $conexion->prepare("
    SELECT co.id_coordinador, co.id_carrera, car.nombre as carrera_nombre 
    FROM coordinador co 
    LEFT JOIN carrera car ON co.id_carrera = car.id_carrera
    INNER JOIN usuario u ON co.id_usuario = u.id_usuario 
    WHERE u.id_usuario = ?
");
$query_coordinador->bind_param("i", $id_usuario);
$query_coordinador->execute();
$coordinador = $query_coordinador->get_result()->fetch_assoc();

// Obtener carreras y especialidades disponibles
$carreras = $conexion->query("SELECT * FROM carrera WHERE id_carrera > 0 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$especialidades = $conexion->query("SELECT * FROM especialidad WHERE activo = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Obtener profesores para seleccionar presidente
$profesores = $conexion->query("
    SELECT p.id_profesor, u.nombre, u.apellidos 
    FROM profesor p 
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
    WHERE p.estado = '1'
    ORDER BY u.nombre, u.apellidos
")->fetch_all(MYSQLI_ASSOC);

// Procesar formulario de creación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $tipo_vinculacion = $_POST['tipo_vinculacion'];
    $id_carrera = $tipo_vinculacion == 'carrera' ? $_POST['id_carrera'] : NULL;
    $id_especialidad = $tipo_vinculacion == 'especialidad' ? $_POST['id_especialidad'] : NULL;
    $id_presidente = $_POST['id_presidente'];
    
    // Validaciones
    if (empty($nombre)) {
        $mensaje = "El nombre de la academia es obligatorio";
        $tipo_mensaje = 'error';
    } elseif (empty($id_presidente)) {
        $mensaje = "Debes seleccionar un presidente para la academia";
        $tipo_mensaje = 'error';
    } else {
        try {
            $conexion->begin_transaction();
            
            // Insertar academia
            $stmt = $conexion->prepare("
                INSERT INTO academia (nombre, descripcion, id_carrera, id_especialidad, id_presidente, fecha_creacion, activo) 
                VALUES (?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->bind_param("ssiii", $nombre, $descripcion, $id_carrera, $id_especialidad, $id_presidente);
            
            if ($stmt->execute()) {
                $id_academia = $conexion->insert_id;
                
                // Agregar al presidente como miembro con rol 'presidente'
                $stmt_miembro = $conexion->prepare("
                    INSERT INTO profesor_academia (id_profesor, id_academia, rol, fecha_ingreso, activo) 
                    VALUES (?, ?, 'presidente', NOW(), 1)
                ");
                $stmt_miembro->bind_param("ii", $id_presidente, $id_academia);
                $stmt_miembro->execute();
                
                // Si la academia está vinculada a una carrera, agregar automáticamente a todos los profesores de esa carrera
                if ($tipo_vinculacion == 'carrera' && $id_carrera) {
                    $profesores_agregados = agregarProfesoresCarrera($conexion, $id_academia, $id_carrera, $id_presidente);
                    
                    if ($profesores_agregados > 0) {
                        $mensaje = "Academia creada exitosamente. Se agregaron automáticamente $profesores_agregados profesores de la carrera.";
                    } else {
                        $mensaje = "Academia creada exitosamente. No se encontraron profesores en esta carrera para agregar automáticamente.";
                    }
                } else {
                    $mensaje = "Academia creada exitosamente";
                }
                
                $tipo_mensaje = 'success';
                $conexion->commit();
                
                // Redirigir después de 3 segundos para mostrar el mensaje
                header("refresh:3;url=academias.php");
                
            } else {
                throw new Exception("Error al crear la academia");
            }
            
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "Error al crear la academia: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

/**
 * Función para agregar automáticamente todos los profesores de una carrera a la academia
 */
function agregarProfesoresCarrera($conexion, $id_academia, $id_carrera, $id_presidente) {
    $profesores_agregados = 0;
    
    // Obtener todos los profesores activos de la carrera específica
    $sql_profesores = "
        SELECT p.id_profesor 
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE u.id_carrera = ? 
        AND p.estado = '1'
        AND p.id_profesor != ?  -- Excluir al presidente que ya fue agregado
    ";
    
    $stmt = $conexion->prepare($sql_profesores);
    $stmt->bind_param("ii", $id_carrera, $id_presidente);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Insertar cada profesor como miembro de la academia
    $stmt_insert = $conexion->prepare("
        INSERT INTO profesor_academia (id_profesor, id_academia, rol, fecha_ingreso, activo) 
        VALUES (?, ?, 'miembro', NOW(), 1)
    ");
    
    while ($profesor = $result->fetch_assoc()) {
        $stmt_insert->bind_param("ii", $profesor['id_profesor'], $id_academia);
        if ($stmt_insert->execute()) {
            $profesores_agregados++;
        }
    }
    
    return $profesores_agregados;
}
?>

<style>
.crear-academia-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 25px;
    text-align: center;
}

.card-header h1 {
    margin: 0;
    font-size: 1.8em;
    font-weight: 600;
}

.card-body {
    padding: 30px;
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

.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

.radio-option input[type="radio"] {
    margin: 0;
}

.vinculacion-options {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #1565c0;
}

.feature-badge {
    display: inline-block;
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-left: 8px;
    font-weight: normal;
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
    margin-top: 10px;
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

.info-box {
    background: #e3f2fd;
    border: 1px solid #1565c0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-box h4 {
    margin: 0 0 10px 0;
    color: #1565c0;
}

.auto-add-info {
    background: #d4edda;
    border: 1px solid #28a745;
    border-radius: 8px;
    padding: 12px;
    margin-top: 10px;
    font-size: 0.9em;
}

.auto-add-info i {
    color: #28a745;
    margin-right: 5px;
}

@media (max-width: 768px) {
    .crear-academia-container {
        margin: 20px auto;
        padding: 0 15px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<div class="crear-academia-container">
    <div class="card">
        <div class="card-header">
            <h1>Crear Nueva Academia</h1>
        </div>
        
        <div class="card-body">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'error'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4>Información del Coordinador</h4>
                <p><strong>Carrera:</strong> <?php echo $coordinador['id_carrera'] == 0 ? 'Coordinador General' : htmlspecialchars($coordinador['carrera_nombre']); ?></p>
                <p>Solo podrás crear academias vinculadas a tu carrera o sus especialidades.</p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nombre">Nombre de la Academia *</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required 
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Vinculación *</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="vinculacion_carrera" name="tipo_vinculacion" value="carrera" checked 
                                   onchange="toggleVinculacionOptions()">
                            <label for="vinculacion_carrera">
                                Vincular a Carrera 
                                <span class="feature-badge">Auto-agregar profesores</span>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="vinculacion_especialidad" name="tipo_vinculacion" value="especialidad"
                                   onchange="toggleVinculacionOptions()">
                            <label for="vinculacion_especialidad">Vincular a Especialidad</label>
                        </div>
                    </div>
                    
                    <div id="opciones_vinculacion">
                        <div class="vinculacion-options" id="opcion_carrera">
                            <label for="id_carrera">Seleccionar Carrera</label>
                            <select id="id_carrera" name="id_carrera" class="form-control">
                                <?php if ($coordinador['id_carrera'] == 0): ?>
                                    <!-- Coordinador general puede seleccionar cualquier carrera -->
                                    <?php foreach($carreras as $carrera): ?>
                                        <option value="<?php echo $carrera['id_carrera']; ?>">
                                            <?php echo htmlspecialchars($carrera['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Coordinador específico solo ve su carrera -->
                                    <option value="<?php echo $coordinador['id_carrera']; ?>">
                                        <?php echo htmlspecialchars($coordinador['carrera_nombre']); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <div class="auto-add-info">
                                <i>💡</i> Todos los profesores de esta carrera serán agregados automáticamente como miembros de la academia.
                            </div>
                        </div>
                        
                        <div class="vinculacion-options" id="opcion_especialidad" style="display: none;">
                            <label for="id_especialidad">Seleccionar Especialidad</label>
                            <select id="id_especialidad" name="id_especialidad" class="form-control">
                                <?php if ($coordinador['id_carrera'] == 0): ?>
                                    <!-- Coordinador general puede seleccionar cualquier especialidad -->
                                    <?php foreach($especialidades as $especialidad): ?>
                                        <option value="<?php echo $especialidad['id_especialidad']; ?>">
                                            <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Coordinador específico solo ve especialidades de su carrera -->
                                    <?php foreach($especialidades as $especialidad): ?>
                                        <?php if ($especialidad['id_carrera'] == $coordinador['id_carrera']): ?>
                                            <option value="<?php echo $especialidad['id_especialidad']; ?>">
                                                <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="auto-add-info">
                                <i>💡</i> Los profesores se agregarán manualmente o por invitación.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="id_presidente">Presidente de la Academia *</label>
                    <select id="id_presidente" name="id_presidente" class="form-control" required>
                        <option value="">Seleccionar presidente...</option>
                        <?php foreach($profesores as $profesor): ?>
                            <option value="<?php echo $profesor['id_profesor']; ?>" 
                                <?php echo (isset($_POST['id_presidente']) && $_POST['id_presidente'] == $profesor['id_profesor']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        El presidente podrá crear tareas y ver miembros, pero no podrá asignar roles.
                    </small>
                </div>
                
                <button type="submit" class="btn-submit">
                    Crear Academia
                </button>
                
                <a href="academias.php" class="btn-cancel" style="width: 100%;">
                    Cancelar y Volver
                </a>
            </form>
        </div>
    </div>
</div>

<script>
function toggleVinculacionOptions() {
    const tipoCarrera = document.getElementById('vinculacion_carrera').checked;
    const opcionCarrera = document.getElementById('opcion_carrera');
    const opcionEspecialidad = document.getElementById('opcion_especialidad');
    
    if (tipoCarrera) {
        opcionCarrera.style.display = 'block';
        opcionEspecialidad.style.display = 'none';
        document.getElementById('id_especialidad').disabled = true;
        document.getElementById('id_carrera').disabled = false;
    } else {
        opcionCarrera.style.display = 'none';
        opcionEspecialidad.style.display = 'block';
        document.getElementById('id_carrera').disabled = true;
        document.getElementById('id_especialidad').disabled = false;
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    toggleVinculacionOptions();
});
</script>

<?php include "footer.php"; ?>