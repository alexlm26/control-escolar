<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Verificar que sea administrador (coordinador de carrera 0)
$id_usuario = $_SESSION['id_usuario'];
$sql_usuario_actual = "SELECT u.id_carrera FROM usuario u WHERE u.id_usuario = $id_usuario";
$usuario_actual = $conexion->query($sql_usuario_actual)->fetch_assoc();

// Solo permitir acceso si es administrador (carrera 0)
if ($usuario_actual['id_carrera'] != 0) {
    header("Location: ../coordinador.php?seccion=coordinadores&error=No tienes permisos para editar coordinadores");
    exit;
}

$id_coordinador = $_GET['id_coordinador'] ?? '';

if ($id_coordinador) {
    // Obtener datos del coordinador
    $sql_coordinador = "
        SELECT 
            c.id_coordinador, 
            u.nombre, 
            u.apellidos, 
            u.correo, 
            u.clave,
            c.sueldo, 
            c.estado,
            c.id_carrera,
            car.nombre as carrera_nombre
        FROM coordinador c 
        INNER JOIN usuario u ON c.id_usuario = u.id_usuario 
        LEFT JOIN carrera car ON c.id_carrera = car.id_carrera
        WHERE c.id_coordinador = $id_coordinador
    ";
    $coordinador = $conexion->query($sql_coordinador)->fetch_assoc();
    
    // Obtener lista de carreras para el select
    $sql_carreras = "SELECT id_carrera, nombre FROM carrera ORDER BY nombre";
    $carreras = $conexion->query($sql_carreras);
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Coordinador - Sistema Escolar</title>
        <style>
        * {
            box-sizing: border-box;
        }
        body {
            background: #f4f6f8;
            font-family: "Poppins", "Segoe UI", sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-block;
            color: #1565c0;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px;
        }
        .header {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
            color: white;
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 1.5em;
        }
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .info-box {
            background: #f3e5f5;
            border: 1px solid #7b1fa2;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 5px 0;
            color: #7b1fa2;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-control:read-only {
            background-color: #f5f5f5;
            color: #666;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            flex: 1;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #9c27b0;
            color: white;
        }
        .btn-primary:hover {
            background: #7b1fa2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        .btn-danger:hover {
            background: #b71c1c;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .header h1 {
                font-size: 1.3em;
            }
            .btn-group {
                flex-direction: column;
            }
            .form-control {
                font-size: 16px;
            }
        }
        </style>
    </head>
    <body>
        <div class="container">
            <a href="../coordinador.php?seccion=coordinadores" class="back-link">← Volver al Panel</a>
            
            <div class="header">
                <h1>Editar Coordinador</h1>
                <p>Modifica la información del coordinador</p>
            </div>';
            
            if (isset($_GET['error'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_GET['error']) . '</div>';
            }
            
            echo '
            <div class="form-container">
                <div class="info-box">
                    <p>🔑 Clave: ' . htmlspecialchars($coordinador['clave']) . '</p>
                    <p>📧 Correo: ' . htmlspecialchars($coordinador['correo']) . '</p>
                    <p>🎓 Carrera Actual: ' . htmlspecialchars($coordinador['carrera_nombre']) . '</p>
                </div>
                
                <form method="POST" action="guardar_coordinador.php">
                    <input type="hidden" name="id_coordinador" value="' . $coordinador['id_coordinador'] . '">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="' . htmlspecialchars($coordinador['nombre']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" value="' . htmlspecialchars($coordinador['apellidos']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="correo">Correo electrónico:</label>
                        <input type="email" id="correo" name="correo" class="form-control" value="' . htmlspecialchars($coordinador['correo']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="clave">Clave (solo lectura):</label>
                        <input type="text" id="clave" name="clave" class="form-control" value="' . htmlspecialchars($coordinador['clave']) . '" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_carrera">Carrera a Coordinar:</label>
                        <select id="id_carrera" name="id_carrera" class="form-control" required>
                            <option value="">Selecciona una carrera</option>';
                            
                            while($carrera = $carreras->fetch_assoc()) {
                                $selected = ($carrera['id_carrera'] == $coordinador['id_carrera']) ? 'selected' : '';
                                echo '<option value="' . $carrera['id_carrera'] . '" ' . $selected . '>' . htmlspecialchars($carrera['nombre']) . '</option>';
                            }
                            
                        echo '</select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sueldo">Sueldo:</label>
                        <input type="number" id="sueldo" name="sueldo" class="form-control" step="0.01" value="' . $coordinador['sueldo'] . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="1" ' . ($coordinador['estado'] == '1' ? 'selected' : '') . '>Activo</option>
                            <option value="2" ' . ($coordinador['estado'] == '2' ? 'selected' : '') . '>Inactivo</option>
                            <option value="3" ' . ($coordinador['estado'] == '3' ? 'selected' : '') . '>Suspendido</option>
                        </select>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="../coordinador.php?seccion=coordinadores" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h3 style="color: #d32f2f; margin-bottom: 15px;">Zona de Peligro</h3>
                    <a href="eliminar_coordinador.php?id_coordinador=' . $coordinador['id_coordinador'] . '" 
                       class="btn btn-danger" 
                       onclick="return confirm(\'¿Estás seguro de que deseas eliminar este coordinador? Esta acción no se puede deshacer.\')">
                       Eliminar Coordinador
                    </a>
                </div>
            </div>
        </div>
        
        <script>
            // Validación del formulario
            document.querySelector("form").addEventListener("submit", function(e) {
                const nombre = document.getElementById("nombre").value.trim();
                const apellidos = document.getElementById("apellidos").value.trim();
                const correo = document.getElementById("correo").value.trim();
                const carrera = document.getElementById("id_carrera").value;
                
                if (nombre === "" || apellidos === "" || correo === "" || carrera === "") {
                    e.preventDefault();
                    alert("Por favor, completa todos los campos obligatorios.");
                    return false;
                }
                
                // Validación básica de email
                const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
                if (!emailRegex.test(correo)) {
                    e.preventDefault();
                    alert("Por favor, ingresa un correo electrónico válido.");
                    return false;
                }
                
                return true;
            });
        </script>
    </body>
    </html>';
} else {
    header("Location: ../coordinador.php?seccion=coordinadores&error=sin_coordinador");
}
?>