<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Obtener el id_coordinador del usuario actual
$id_usuario = $_SESSION['id_usuario'];

// Primero obtener el id_coordinador desde la tabla coordinador
$sql_coordinador = "SELECT id_coordinador FROM coordinador WHERE id_usuario = $id_usuario";
$result_coordinador = $conexion->query($sql_coordinador);

if (!$result_coordinador || $result_coordinador->num_rows === 0) {
    header("Location: ../coordinador.php?seccion=especialidades&error=No se encontró información del coordinador");
    exit;
}

$coordinador_data = $result_coordinador->fetch_assoc();
$id_coordinador = $coordinador_data['id_coordinador'];

// Obtener id_carrera del coordinador desde la tabla usuario
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$carrera = $conexion->query($sql_carrera)->fetch_assoc();
$id_carrera = $carrera['id_carrera'];

// Obtener lista de carreras (solo para coordinadores administradores)
if ($id_carrera == 0) {
    // CORREGIDO: Eliminar la condición WHERE activo = 1
    $sql_carreras = "SELECT id_carrera, nombre FROM carrera ORDER BY nombre";
    $carreras = $conexion->query($sql_carreras);
} else {
    // CORREGIDO: Eliminar la condición AND activo = 1
    $sql_carreras = "SELECT id_carrera, nombre FROM carrera WHERE id_carrera = $id_carrera";
    $carreras = $conexion->query($sql_carreras);
}

if ($_POST) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_carrera_seleccionada = $_POST['id_carrera'];
    
    // Validar que el coordinador tenga permisos para crear en esta carrera
    if ($id_carrera != 0 && $id_carrera_seleccionada != $id_carrera) {
        header("Location: ../coordinador.php?seccion=especialidades&error=No tienes permisos para crear especialidades en esta carrera");
        exit;
    }
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Verificar si ya existe una especialidad con el mismo nombre en la misma carrera
        $sql_verificar = "SELECT id_especialidad FROM especialidad WHERE nombre = '$nombre' AND id_carrera = $id_carrera_seleccionada";
        $result_verificar = $conexion->query($sql_verificar);
        
        if ($result_verificar->num_rows > 0) {
            throw new Exception("Ya existe una especialidad con el nombre '$nombre' en esta carrera");
        }
        
        // Insertar la nueva especialidad
        $sql_especialidad = "INSERT INTO especialidad (id_carrera, nombre, descripcion, activo) 
                           VALUES ($id_carrera_seleccionada, '$nombre', " . 
                           ($descripcion ? "'$descripcion'" : "NULL") . ", 1)";
        
        if (!$conexion->query($sql_especialidad)) {
            throw new Exception("Error al crear especialidad: " . $conexion->error);
        }
        
        $conexion->commit();
        
        $mensaje = "Especialidad '$nombre' creada correctamente";
        header("Location: ../coordinador.php?seccion=especialidades&mensaje=" . urlencode($mensaje));
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: ../coordinador.php?seccion=especialidades&error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Especialidad</title>
    <style>
        body {
            font-family: "Poppins", "Segoe UI", sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1565c0;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
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
            border-color: #1565c0;
        }
        .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
            background: white;
        }
        .form-select:focus {
            outline: none;
            border-color: #1565c0;
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
            background: #1565c0;
            color: white;
        }
        .btn-primary:hover {
            background: #1976d2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #1565c0;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 0;
            color: #1565c0;
            font-weight: 600;
        }
        .carrera-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #2e7d32;
        }
        .nombre-info {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #e65100;
        }
        .descripcion-info {
            background: #f3e5f5;
            border: 1px solid #9c27b0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #7b1fa2;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nueva Especialidad</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>
                <?php if ($id_carrera == 0): ?>
                    Como coordinador administrador, puedes crear especialidades para cualquier carrera.
                <?php else: ?>
                    La especialidad se asociará automáticamente a tu carrera actual.
                <?php endif; ?>
            </p>
        </div>
        
        <form method="POST" action="">
            <?php if ($id_carrera == 0): ?>
                <div class="form-group">
                    <label>Carrera:</label>
                    <select name="id_carrera" class="form-select" required>
                        <option value="">-- Seleccionar carrera --</option>
                        <?php while($carrera_option = $carreras->fetch_assoc()): ?>
                            <option value="<?php echo $carrera_option['id_carrera']; ?>">
                                <?php echo htmlspecialchars($carrera_option['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="carrera-info">
                        Selecciona la carrera a la que pertenecerá esta especialidad
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="id_carrera" value="<?php echo $id_carrera; ?>">
                <div class="form-group">
                    <label>Carrera:</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($carreras->fetch_assoc()['nombre']); ?>" readonly>
                    <div class="carrera-info">
                        Esta especialidad se creará para tu carrera actual
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nombre de la Especialidad:</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Desarrollo de Software, Redes y Telecomunicaciones, Ciberseguridad" required>
                <div class="nombre-info">
                    El nombre debe ser único dentro de la misma carrera
                </div>
            </div>
            
            <div class="form-group">
                <label>Descripción (Opcional):</label>
                <textarea name="descripcion" class="form-control" placeholder="Describe el enfoque o características principales de esta especialidad..."></textarea>
                <div class="descripcion-info">
                    Esta descripción ayudará a los estudiantes a entender el enfoque de la especialidad
                </div>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Especialidad</button>
                <a href="../coordinador.php?seccion=materias" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        // Validación en el cliente para el formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const nombreInput = document.querySelector('input[name="nombre"]');
            
            form.addEventListener('submit', function(e) {
                const nombre = nombreInput.value.trim();
                
                if (nombre.length < 3) {
                    e.preventDefault();
                    alert('El nombre de la especialidad debe tener al menos 3 caracteres');
                    nombreInput.focus();
                    return;
                }
                
                if (nombre.length > 50) {
                    e.preventDefault();
                    alert('El nombre de la especialidad no puede exceder los 50 caracteres');
                    nombreInput.focus();
                    return;
                }
            });
        });
    </script>
</body>
</html>