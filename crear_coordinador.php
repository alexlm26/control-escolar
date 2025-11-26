<?php
session_start();
include "../conexion.php";

// Verificar que sea administrador (coordinador de carrera 0)
if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Obtener información del usuario actual para verificar si es administrador
$id_usuario = $_SESSION['id_usuario'];
$sql_usuario_actual = "SELECT u.id_carrera FROM usuario u WHERE u.id_usuario = $id_usuario";
$usuario_actual = $conexion->query($sql_usuario_actual)->fetch_assoc();

// Solo permitir acceso si es administrador (carrera 0)
if ($usuario_actual['id_carrera'] != 0) {
    header("Location: ../coordinador.php?seccion=profesores&error=No tienes permisos para crear coordinadores");
    exit;
}

if ($_POST) {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $contraseña = $_POST['contraseña'];
    $sueldo = $_POST['sueldo'];
    $id_carrera = $_POST['id_carrera']; // Carrera a la que será asignado
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($contraseña)) {
        header("Location: crear_coordinador.php?error=Todos los campos son obligatorios");
        exit;
    }
    
    // Generar clave única para coordinador (cor + número secuencial)
    $sql_ultimo_cor = "SELECT MAX(CAST(SUBSTRING(clave, 4) AS UNSIGNED)) as ultimo_num FROM usuario WHERE clave LIKE 'cor%'";
    $result = $conexion->query($sql_ultimo_cor);
    $ultimo_num = $result->fetch_assoc()['ultimo_num'] ?? 0;
    $nuevo_num = str_pad($ultimo_num + 1, 2, '0', STR_PAD_LEFT);
    $clave = "cor" . $nuevo_num;
    
    // Generar correo automáticamente
    $correo = $clave . '@itsur.edu.mx';
    
    // Obtener el próximo id_coordinador disponible
    $sql_max_id = "SELECT MAX(id_coordinador) as max_id FROM coordinador";
    $result_max = $conexion->query($sql_max_id);
    $max_data = $result_max->fetch_assoc();
    $nuevo_id_coordinador = ($max_data['max_id'] ?? 0) + 1;
    
    // Iniciar transacción para asegurar la integridad de los datos
    $conexion->begin_transaction();
    
    try {
        // Primero crear el usuario
        $sql_usuario = "INSERT INTO usuario (nombre, apellidos, correo, contraseña, rol, id_carrera, clave, fecha_nacimiento) 
                       VALUES ('" . $conexion->real_escape_string($nombre) . "', 
                               '" . $conexion->real_escape_string($apellidos) . "', 
                               '$correo', 
                               '$contraseña', 
                               '3', 
                               $id_carrera, 
                               '$clave', 
                               '1980-01-01 00:00:00')";
        
        if (!$conexion->query($sql_usuario)) {
            throw new Exception("Error al crear usuario: " . $conexion->error);
        }
        
        $id_usuario_nuevo = $conexion->insert_id;
        
        // Luego crear el coordinador con el ID específico
        $sql_coordinador = "INSERT INTO coordinador (id_coordinador, id_usuario, id_carrera, sueldo, estado) 
                           VALUES ($nuevo_id_coordinador, $id_usuario_nuevo, $id_carrera, $sueldo, '1')";
        
        if (!$conexion->query($sql_coordinador)) {
            throw new Exception("Error al crear coordinador: " . $conexion->error);
        }
        
        $conexion->commit();
        header("Location: ../coordinador.php?seccion=coordinadores&mensaje=Coordinador creado correctamente. Clave: $clave - Correo: $correo");
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: crear_coordinador.php?error=" . urlencode($e->getMessage()));
    }
    exit;
}

// Obtener lista de carreras para el select
$sql_carreras = "SELECT id_carrera, nombre FROM carrera ORDER BY nombre";
$carreras = $conexion->query($sql_carreras);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Coordinador</title>
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
            background-color: white;
            cursor: pointer;
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
            transform: translateY(-2px);
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
        .admin-warning {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .admin-warning p {
            margin: 0;
            color: #e65100;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nuevo Coordinador</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['mensaje']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>📧 El correo electrónico se generará automáticamente con el formato: [clave]@itsur.edu.mx</p>
            <p>🔑 La clave se generará automáticamente con el formato: cor##</p>
        </div>
        
        <div class="admin-warning">
            <p>⚠️ Solo los administradores del sistema pueden crear coordinadores</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre" class="form-control" required maxlength="50">
            </div>
            
            <div class="form-group">
                <label>Apellidos:</label>
                <input type="text" name="apellidos" class="form-control" required maxlength="60">
            </div>
            
            <div class="form-group">
                <label>Contraseña:</label>
                <input type="password" name="contraseña" class="form-control" required minlength="4">
            </div>
            
            <div class="form-group">
                <label>Carrera a Coordinar:</label>
                <select name="id_carrera" class="form-select" required>
                    <option value="">Selecciona una carrera</option>
                    <?php while($carrera = $carreras->fetch_assoc()): ?>
                        <option value="<?php echo $carrera['id_carrera']; ?>">
                            <?php echo htmlspecialchars($carrera['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Sueldo:</label>
                <input type="number" name="sueldo" class="form-control" step="0.01" min="0" value="25000.00" required>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Crear Coordinador</button>
                <a href="../coordinador.php?seccion=coordinadores" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        // Validación adicional del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const nombre = document.querySelector('input[name="nombre"]').value.trim();
            const apellidos = document.querySelector('input[name="apellidos"]').value.trim();
            const contraseña = document.querySelector('input[name="contraseña"]').value;
            const carrera = document.querySelector('select[name="id_carrera"]').value;
            
            if (nombre === '' || apellidos === '' || contraseña === '' || carrera === '') {
                e.preventDefault();
                alert('Por favor, completa todos los campos obligatorios.');
                return false;
            }
            
            if (contraseña.length < 4) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 4 caracteres.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>