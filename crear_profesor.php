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
    header("Location: ../coordinador.php?seccion=profesores&error=No se encontró información del coordinador");
    exit;
}

$coordinador_data = $result_coordinador->fetch_assoc();
$id_coordinador = $coordinador_data['id_coordinador'];

// Obtener id_carrera del coordinador desde la tabla usuario
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$carrera = $conexion->query($sql_carrera)->fetch_assoc();
$id_carrera = $carrera['id_carrera'];

// Si el coordinador es de carrera 0, obtener lista de carreras para el select
$carreras_disponibles = [];
if ($id_carrera == 0) {
    $sql_carreras = "SELECT id_carrera, nombre FROM carrera WHERE id_carrera != 0";
    $result_carreras = $conexion->query($sql_carreras);
    while ($row = $result_carreras->fetch_assoc()) {
        $carreras_disponibles[$row['id_carrera']] = $row['nombre'];
    }
}

if ($_POST) {
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $contraseña = $_POST['contraseña'];
    $sueldo = $_POST['sueldo'];
        $contraseña_hash = password_hash($contraseña, PASSWORD_BCRYPT)
    
    // Determinar la carrera a usar
    if ($id_carrera == 0 && isset($_POST['id_carrera']) && !empty($_POST['id_carrera'])) {
        $carrera_seleccionada = intval($_POST['id_carrera']);
    } else {
        $carrera_seleccionada = $id_carrera;
    }
    
    // Generar clave única para profesor (prof + número secuencial)
    $sql_ultimo_prof = "SELECT MAX(CAST(SUBSTRING(clave, 5) AS UNSIGNED)) as ultimo_num FROM usuario WHERE clave LIKE 'prof%'";
    $result = $conexion->query($sql_ultimo_prof);
    $ultimo_num = $result->fetch_assoc()['ultimo_num'] ?? 0;
    $nuevo_num = str_pad($ultimo_num + 1, 2, '0', STR_PAD_LEFT);
    $clave = "prof" . $nuevo_num;
    
    // Generar correo automáticamente
    $correo = $clave . '@itsur.edu.mx';
    
    // Obtener el próximo id_profesor disponible
    $sql_max_id = "SELECT MAX(id_profesor) as max_id FROM profesor";
    $result_max = $conexion->query($sql_max_id);
    $max_data = $result_max->fetch_assoc();
    $nuevo_id_profesor = ($max_data['max_id'] ?? 0) + 1;
    
    // Iniciar transacción para asegurar la integridad de los datos
    $conexion->begin_transaction();
    
    try {
        // Primero crear el usuario
        $sql_usuario = "INSERT INTO usuario (nombre, apellidos, correo, contraseña, rol, id_carrera, clave, fecha_nacimiento) 
                       VALUES ('$nombre', '$apellidos', '$correo', '$contraseña_hash', '2', $carrera_seleccionada, '$clave', '1980-01-01 00:00:00')";
        
        if (!$conexion->query($sql_usuario)) {
            throw new Exception("Error al crear usuario: " . $conexion->error);
        }
        
        $id_usuario_nuevo = $conexion->insert_id;
        
        // Luego crear el profesor con el ID específico
        $sql_profesor = "INSERT INTO profesor (id_profesor, id_usuario, id_coordinador, sueldo, estado) 
                        VALUES ($nuevo_id_profesor, $id_usuario_nuevo, $id_coordinador, $sueldo, '1')";
        
        if (!$conexion->query($sql_profesor)) {
            throw new Exception("Error al crear profesor: " . $conexion->error);
        }
        
        $conexion->commit();
        
        // Mensaje de éxito con información de la carrera si es coordinador global
        $mensaje_exito = "Profesor creado correctamente. Clave: $clave - Correo: $correo";
        if ($id_carrera == 0) {
            $nombre_carrera = $carreras_disponibles[$carrera_seleccionada] ?? 'Desconocida';
            $mensaje_exito .= " - Carrera: $nombre_carrera";
        }
        
        header("Location: ../coordinador.php?seccion=profesores&mensaje=" . urlencode($mensaje_exito));
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: ../coordinador.php?seccion=profesores&error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Profesor</title>
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
        .coordinador-global {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .coordinador-global p {
            margin: 0;
            font-weight: 600;
            color: #2e7d32;
        }
        .select-carrera {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #e9ecef;
        }
        .select-carrera h4 {
            margin: 0 0 15px 0;
            color: #1565c0;
            font-size: 1.1em;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nuevo Profesor</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- INFO DEL COORDINADOR GLOBAL -->
        <?php if ($id_carrera == 0): ?>
        <div class="coordinador-global">
            <p>🌐 Eres Coordinador Global - Puedes asignar profesores a cualquier carrera</p>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>📧 El correo electrónico se generará automáticamente con el formato: [clave]@itsur.edu.mx</p>
            <?php if ($id_carrera == 0): ?>
            <p style="margin-top: 10px;">🎓 Puedes asignar el profesor a la carrera que desees</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="">
            
            <?php if ($id_carrera == 0): ?>
            <!-- SELECTOR DE CARRERA PARA COORDINADOR GLOBAL -->
            <div class="select-carrera">
                <h4>🎓 Seleccionar Carrera del Profesor</h4>
                <div class="form-group">
                    <label for="id_carrera">Carrera:</label>
                    <select id="id_carrera" name="id_carrera" class="form-control" required>
                        <option value="">-- Seleccione una carrera --</option>
                        <?php foreach ($carreras_disponibles as $id => $nombre): ?>
                            <option value="<?php echo $id; ?>" <?php echo (isset($_POST['id_carrera']) && $_POST['id_carrera'] == $id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre" class="form-control" required 
                       value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Apellidos:</label>
                <input type="text" name="apellidos" class="form-control" required
                       value="<?php echo isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Contraseña:</label>
                <input type="password" name="contraseña" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Sueldo:</label>
                <input type="number" name="sueldo" class="form-control" step="0.01" min="0" required
                       value="<?php echo isset($_POST['sueldo']) ? htmlspecialchars($_POST['sueldo']) : ''; ?>">
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Profesor</button>
                <a href="../coordinador.php?seccion=profesores" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
    // Validación del formulario
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', function(e) {
            const nombre = document.querySelector('input[name="nombre"]').value.trim();
            const apellidos = document.querySelector('input[name="apellidos"]').value.trim();
            const contraseña = document.querySelector('input[name="contraseña"]').value;
            const sueldo = document.querySelector('input[name="sueldo"]').value;
            
            // Validar selector de carrera para coordinador global
            const carreraSelect = document.getElementById('id_carrera');
            if (carreraSelect && carreraSelect.value === '') {
                e.preventDefault();
                alert('Por favor, seleccione una carrera para el profesor.');
                return;
            }
            
            if (nombre === '' || apellidos === '' || contraseña === '' || sueldo === '') {
                e.preventDefault();
                alert('Por favor, complete todos los campos requeridos.');
                return;
            }
            
            if (contraseña.length < 4) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 4 caracteres.');
                return;
            }
            
            if (parseFloat(sueldo) <= 0) {
                e.preventDefault();
                alert('El sueldo debe ser mayor a 0.');
                return;
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ Creando profesor...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    });
    </script>
</body>
</html>