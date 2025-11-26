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
    header("Location: ../coordinador.php?seccion=materias&error=No se encontró información del coordinador");
    exit;
}

$coordinador_data = $result_coordinador->fetch_assoc();
$id_coordinador = $coordinador_data['id_coordinador'];

// Obtener id_carrera del coordinador desde la tabla usuario
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$carrera = $conexion->query($sql_carrera)->fetch_assoc();
$id_carrera = $carrera['id_carrera'];

// Obtener lista de materias para prerrequisitos (incluyendo información de especialidad)
$sql_materias = "SELECT m.id_materia, m.nombre, m.id_especialidad, e.nombre as especialidad_nombre 
                 FROM materia m 
                 LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad 
                 WHERE m.id_carrera = $id_carrera 
                 ORDER BY m.nombre";
$materias = $conexion->query($sql_materias);

// Obtener especialidades según el tipo de coordinador
if ($id_carrera == 0) {
    // Coordinador administrador - puede ver todas las especialidades separadas por carrera
    $sql_especialidades = "SELECT e.*, c.nombre as carrera_nombre 
                          FROM especialidad e 
                          JOIN carrera c ON e.id_carrera = c.id_carrera 
                          WHERE e.activo = 1 
                          ORDER BY c.nombre, e.nombre";
} else {
    // Coordinador de carrera específica - ve especialidad general + las de su carrera
    $sql_especialidades = "SELECT e.*, c.nombre as carrera_nombre 
                          FROM especialidad e 
                          JOIN carrera c ON e.id_carrera = c.id_carrera 
                          WHERE (e.id_carrera = $id_carrera OR e.id_especialidad = 1) 
                          AND e.activo = 1 
                          ORDER BY e.id_especialidad = 1 DESC, e.nombre";
}
$especialidades = $conexion->query($sql_especialidades);

if ($_POST) {
    $nombre = $_POST['nombre'];
    $creditos = $_POST['creditos'];
    $unidades = $_POST['unidades'];
    $semestre_sugerido = $_POST['semestre_sugerido'];
    $id_prerrequisito = $_POST['id_prerrequisito'] ?: NULL;
    $id_especialidad = $_POST['id_especialidad'] ?: 1; // Por defecto especialidad general
    
    // Iniciar transacción para asegurar la integridad de los datos
    $conexion->begin_transaction();
    
    try {
        // Verificar si ya existe una materia con el mismo nombre en la misma carrera
        $sql_verificar = "SELECT id_materia FROM materia WHERE nombre = '$nombre' AND id_carrera = $id_carrera";
        $result_verificar = $conexion->query($sql_verificar);
        
        if ($result_verificar->num_rows > 0) {
            throw new Exception("Ya existe una materia con el nombre '$nombre' en esta carrera");
        }
        
        // Validar que la especialidad pertenezca a la carrera del coordinador (si no es administrador)
        if ($id_carrera != 0) {
            $sql_validar_especialidad = "SELECT id_especialidad FROM especialidad 
                                        WHERE id_especialidad = $id_especialidad 
                                        AND (id_carrera = $id_carrera OR id_especialidad = 1)";
            $result_validar = $conexion->query($sql_validar_especialidad);
            
            if ($result_validar->num_rows === 0) {
                throw new Exception("La especialidad seleccionada no pertenece a tu carrera");
            }
        }
        
        // Validación adicional: si se selecciona prerrequisito y especialidad
        if ($id_prerrequisito && $id_especialidad != 1) {
            // Verificar que el prerrequisito sea de especialidad general (id_especialidad=1) 
            // o de la misma especialidad que se está seleccionando
            $sql_validar_prerrequisito = "SELECT id_especialidad FROM materia 
                                         WHERE id_materia = $id_prerrequisito 
                                         AND (id_especialidad = 1 OR id_especialidad = $id_especialidad)";
            $result_prerrequisito = $conexion->query($sql_validar_prerrequisito);
            
            if ($result_prerrequisito->num_rows === 0) {
                // Obtener información del prerrequisito para el mensaje de error
                $sql_info_prerreq = "SELECT m.nombre, e.nombre as especialidad_nombre 
                                   FROM materia m 
                                   LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad 
                                   WHERE m.id_materia = $id_prerrequisito";
                $info_prerreq = $conexion->query($sql_info_prerreq)->fetch_assoc();
                
                // Obtener nombre de la especialidad seleccionada
                $sql_info_especialidad = "SELECT nombre FROM especialidad WHERE id_especialidad = $id_especialidad";
                $info_especialidad = $conexion->query($sql_info_especialidad)->fetch_assoc();
                
                throw new Exception("El prerrequisito '{$info_prerreq['nombre']}' ({$info_prerreq['especialidad_nombre']}) no es compatible con la especialidad '{$info_especialidad['nombre']}'. Los prerrequisitos deben ser de especialidad general o de la misma especialidad.");
            }
        }
        
        // Insertar la nueva materia
        $sql_materia = "INSERT INTO materia (nombre, creditos, unidades, semestre_sugerido, id_carrera, id_prerrequisito, id_especialidad) 
                       VALUES ('$nombre', $creditos, $unidades, $semestre_sugerido, $id_carrera, " . 
                       ($id_prerrequisito ? $id_prerrequisito : 'NULL') . ", $id_especialidad)";
        
        if (!$conexion->query($sql_materia)) {
            throw new Exception("Error al crear materia: " . $conexion->error);
        }
        
        $id_materia_nueva = $conexion->insert_id;
        
        $conexion->commit();
        
        $mensaje = "Materia creada correctamente";
        if ($id_prerrequisito) {
            $materia_prerreq = $conexion->query("SELECT nombre FROM materia WHERE id_materia = $id_prerrequisito")->fetch_assoc();
            $mensaje .= ". Prerrequisito: " . $materia_prerreq['nombre'];
        }
        
        // Agregar información de especialidad al mensaje
        if ($id_especialidad != 1) {
            $especialidad_info = $conexion->query("SELECT nombre FROM especialidad WHERE id_especialidad = $id_especialidad")->fetch_assoc();
            $mensaje .= ". Especialidad: " . $especialidad_info['nombre'];
        }
        
        header("Location: ../coordinador.php?seccion=materias&mensaje=" . urlencode($mensaje));
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: ../coordinador.php?seccion=materias&error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Materia</title>
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
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .prerrequisito-info {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #e65100;
        }
        .semestre-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #2e7d32;
        }
        .especialidad-info {
            background: #f3e5f5;
            border: 1px solid #9c27b0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #7b1fa2;
        }
        .carrera-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7em;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nueva Materia</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>La materia se asociará automáticamente a tu carrera actual</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre de la Materia:</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Redes de Computadoras 1" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Créditos:</label>
                    <input type="number" name="creditos" class="form-control" min="1" max="10" value="5" required>
                </div>
                
                <div class="form-group">
                    <label>Unidades:</label>
                    <input type="number" name="unidades" class="form-control" min="1" max="15" value="5" required>
                </div>
                
                <div class="form-group">
                    <label>Semestre Sugerido:</label>
                    <select name="semestre_sugerido" class="form-select" required>
                        <option value="">-- Seleccionar semestre --</option>
                        <?php for($i = 1; $i <= 9; $i++): ?>
                            <option value="<?php echo $i; ?>">Semestre <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="semestre-info">
                        Semestre recomendado para cursar esta materia
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Especialidad:</label>
                <select name="id_especialidad" class="form-select" required>
                    <option value="1">General (para todas las especialidades)</option>
                    <?php while($especialidad = $especialidades->fetch_assoc()): ?>
                        <?php if ($especialidad['id_especialidad'] != 1): ?>
                            <option value="<?php echo $especialidad['id_especialidad']; ?>">
                                <?php echo htmlspecialchars($especialidad['nombre']); ?>
                                <?php if ($id_carrera == 0): ?>
                                    <span class="carrera-badge"><?php echo htmlspecialchars($especialidad['carrera_nombre']); ?></span>
                                <?php endif; ?>
                            </option>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </select>
                <div class="especialidad-info">
                    Selecciona la especialidad de esta materia. "General" significa que es común para todas las especialidades.
                </div>
            </div>
            
            <div class="form-group">
                <label>Prerrequisito (Opcional):</label>
                <select name="id_prerrequisito" class="form-select">
                    <option value="">-- Sin prerrequisito --</option>
                    <?php while($materia = $materias->fetch_assoc()): ?>
                        <option value="<?php echo $materia['id_materia']; ?>" 
                                data-especialidad="<?php echo $materia['id_especialidad']; ?>">
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                            <?php if ($materia['id_especialidad'] != 1): ?>
                                (<?php echo htmlspecialchars($materia['especialidad_nombre']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="prerrequisito-info">
                    Si seleccionas un prerrequisito, los alumnos deberán aprobar esa materia antes de poder inscribirse a esta.
                    <br><strong>Nota:</strong> Los prerrequisitos deben ser de especialidad general o de la misma especialidad que la materia actual.
                </div>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Materia</button>
                <a href="../coordinador.php?seccion=materias" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        // Validación en el cliente para prerrequisitos y especialidades
        document.addEventListener('DOMContentLoaded', function() {
            const selectEspecialidad = document.querySelector('select[name="id_especialidad"]');
            const selectPrerrequisito = document.querySelector('select[name="id_prerrequisito"]');
            
            function validarPrerrequisito() {
                const especialidadSeleccionada = selectEspecialidad.value;
                const prerrequisitoSeleccionado = selectPrerrequisito.value;
                
                if (prerrequisitoSeleccionado && especialidadSeleccionada != 1) {
                    const optionPrerreq = selectPrerrequisito.querySelector(`option[value="${prerrequisitoSeleccionado}"]`);
                    const especialidadPrerreq = optionPrerreq.getAttribute('data-especialidad');
                    
                    // El prerrequisito debe ser general (1) o de la misma especialidad
                    if (especialidadPrerreq != 1 && especialidadPrerreq != especialidadSeleccionada) {
                        alert('⚠Advertencia: El prerrequisito seleccionado no es compatible con la especialidad elegida. Los prerrequisitos deben ser de especialidad general o de la misma especialidad.');
                        return false;
                    }
                }
                return true;
            }
            
            selectPrerrequisito.addEventListener('change', validarPrerrequisito);
            selectEspecialidad.addEventListener('change', validarPrerrequisito);
            
            // Validar antes de enviar el formulario
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validarPrerrequisito()) {
                    e.preventDefault();
                    alert('Por favor, corrige la selección de prerrequisito antes de enviar el formulario.');
                }
            });
        });
    </script>
</body>
</html>