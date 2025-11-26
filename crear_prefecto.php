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
    header("Location: ../coordinador.php?seccion=prefectos&error=No se encontró información del coordinador");
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

$mensaje_exito = '';
$clave_generada = '';
$correo_generado = '';
$carrera_asignada = '';

if ($_POST) {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $contraseña = $_POST['contraseña'];
    $sueldo = $_POST['sueldo'];
    
    // Determinar la carrera a usar
    if ($id_carrera == 0 && isset($_POST['id_carrera']) && !empty($_POST['id_carrera'])) {
        $carrera_seleccionada = intval($_POST['id_carrera']);
        $carrera_asignada = $carreras_disponibles[$carrera_seleccionada] ?? 'Desconocida';
    } else {
        $carrera_seleccionada = $id_carrera;
        // Obtener nombre de la carrera del coordinador
        $sql_nombre_carrera = "SELECT nombre FROM carrera WHERE id_carrera = $id_carrera";
        $result_nombre = $conexion->query($sql_nombre_carrera);
        $carrera_asignada = $result_nombre->fetch_assoc()['nombre'] ?? 'Desconocida';
    }
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($contraseña) || empty($sueldo)) {
        $error = "Todos los campos son obligatorios";
    } else {
        // Obtener el próximo id_usuario disponible
        $sql_ultimo_id = "SELECT MAX(id_usuario) as ultimo_id FROM usuario";
        $result_ultimo = $conexion->query($sql_ultimo_id);
        $ultimo_data = $result_ultimo->fetch_assoc();
        $nuevo_id_usuario = ($ultimo_data['ultimo_id'] ?? 0) + 1;
        
        // Generar clave única para prefecto (pref + número secuencial)
        $sql_ultimo_pref = "SELECT MAX(CAST(SUBSTRING(clave, 5) AS UNSIGNED)) as ultimo_num FROM usuario WHERE clave LIKE 'pref%'";
        $result = $conexion->query($sql_ultimo_pref);
        $ultimo_num = $result->fetch_assoc()['ultimo_num'] ?? 0;
        $nuevo_num = str_pad($ultimo_num + 1, 2, '0', STR_PAD_LEFT);
        $clave = "pref" . $nuevo_num;
        
        // Generar correo automáticamente
        $correo = $clave . '@itsur.edu.mx';
        
        // Obtener el próximo id_prefecto disponible
        $sql_max_id = "SELECT MAX(id_prefecto) as max_id FROM prefecto";
        $result_max = $conexion->query($sql_max_id);
        $max_data = $result_max->fetch_assoc();
        $nuevo_id_prefecto = ($max_data['max_id'] ?? 0) + 1;
        
        // Iniciar transacción para asegurar la integridad de los datos
        $conexion->begin_transaction();
        
        try {
            // Primero crear el usuario (rol 5 para prefecto con carrera)
            $sql_usuario = "INSERT INTO usuario (id_usuario, nombre, apellidos, correo, contraseña, rol, id_carrera, clave, fecha_nacimiento) 
                           VALUES ($nuevo_id_usuario, '$nombre', '$apellidos', '$correo', '$contraseña', '5', $carrera_seleccionada, '$clave', '1980-01-01 00:00:00')";
            
            if (!$conexion->query($sql_usuario)) {
                throw new Exception("Error al crear usuario: " . $conexion->error);
            }
            
            // Luego crear el prefecto con el ID específico
            $sql_prefecto = "INSERT INTO prefecto (id_prefecto, id_usuario, sueldo, estado) 
                            VALUES ($nuevo_id_prefecto, $nuevo_id_usuario, $sueldo, '1')";
            
            if (!$conexion->query($sql_prefecto)) {
                throw new Exception("Error al crear prefecto: " . $conexion->error);
            }
            
            $conexion->commit();
            
            $mensaje_exito = "Prefecto creado correctamente. Clave: $clave - Correo: $correo - Carrera: $carrera_asignada";
            $clave_generada = $clave;
            $correo_generado = $correo;
            
            // Limpiar los campos del formulario
            $_POST = array();
            
        } catch (Exception $e) {
            $conexion->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Prefecto</title>
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
        .prefecto-badge {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
            text-align: center;
            width: 100%;
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
        .credenciales-box {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .credenciales-box h4 {
            color: #2e7d32;
            margin-bottom: 15px;
        }
        .credencial-item {
            background: white;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #4caf50;
            text-align: left;
        }
        .credencial-label {
            font-weight: 600;
            color: #555;
        }
        .credencial-value {
            color: #2e7d32;
            font-weight: 700;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="prefecto-badge">👮 PREFECTO - ROL 5</div>
        <h1>Crear Nuevo Prefecto</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($mensaje_exito): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
            
            <!-- Mostrar credenciales generadas -->
            <div class="credenciales-box">
                <h4>📋 Credenciales Generadas</h4>
                <div class="credencial-item">
                    <div class="credencial-label">Clave del Prefecto:</div>
                    <div class="credencial-value"><?php echo htmlspecialchars($clave_generada); ?></div>
                </div>
                <div class="credencial-item">
                    <div class="credencial-label">Correo Electrónico:</div>
                    <div class="credencial-value"><?php echo htmlspecialchars($correo_generado); ?></div>
                </div>
                <div class="credencial-item">
                    <div class="credencial-label">Carrera Asignada:</div>
                    <div class="credencial-value"><?php echo htmlspecialchars($carrera_asignada); ?></div>
                </div>
                <div class="credencial-item">
                    <div class="credencial-label">Contraseña:</div>
                    <div class="credencial-value">●●●●●●●●</div>
                </div>
                <p style="margin-top: 15px; color: #2e7d32; font-weight: 600;">
                    ✅ El prefecto ha sido registrado exitosamente en el sistema
                </p>
            </div>
        <?php endif; ?>
        
        <!-- INFO DEL COORDINADOR GLOBAL -->
        <?php if ($id_carrera == 0): ?>
        <div class="coordinador-global">
            <p>🌐 Eres Coordinador Global - Puedes asignar prefectos a cualquier carrera</p>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>📧 El correo electrónico se generará automáticamente con el formato: [clave]@itsur.edu.mx</p>
            <p style="margin-top: 10px;">🔑 El prefecto tendrá rol 5 en el sistema</p>
            <?php if ($id_carrera != 0): ?>
            <p style="margin-top: 10px;">🎓 El prefecto será asignado a tu misma carrera</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="">
            
            <?php if ($id_carrera == 0): ?>
            <!-- SELECTOR DE CARRERA PARA COORDINADOR GLOBAL -->
            <div class="select-carrera">
                <h4>🎓 Seleccionar Carrera del Prefecto</h4>
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
                <input type="password" name="contraseña" class="form-control" required
                       value="<?php echo isset($_POST['contraseña']) ? htmlspecialchars($_POST['contraseña']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Sueldo:</label>
                <input type="number" name="sueldo" class="form-control" step="0.01" min="0" required
                       value="<?php echo isset($_POST['sueldo']) ? htmlspecialchars($_POST['sueldo']) : ''; ?>">
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Prefecto</button>
                <a href="../coordinador.php?seccion=prefectos" class="btn btn-secondary">Volver a Prefectos</a>
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
                alert('Por favor, seleccione una carrera para el prefecto.');
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
            submitBtn.innerHTML = '⏳ Creando prefecto...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Si hay mensaje de éxito, hacer scroll hacia él
        <?php if ($mensaje_exito): ?>
            setTimeout(() => {
                document.querySelector('.alert-success')?.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);
        <?php endif; ?>
    });
    </script>
</body>
</html>