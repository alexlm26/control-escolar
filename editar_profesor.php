<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_profesor = $_GET['id_profesor'] ?? '';

if ($id_profesor) {
    // Obtener datos del profesor
    $sql_profesor = "
        SELECT p.id_profesor, u.nombre, u.apellidos, u.correo, p.sueldo, p.estado 
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.id_profesor = $id_profesor
    ";
    $profesor = $conexion->query($sql_profesor)->fetch_assoc();
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Profesor - Sistema Escolar</title>
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
            background: linear-gradient(135deg, #1565c0, #1976d2);
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
        }
        .btn-primary {
            background: #1565c0;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
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
            <a href="../coordinador.php?seccion=profesores" class="back-link">← Volver al Panel</a>
            
            <div class="header">
                <h1>Editar Profesor</h1>
                <p>Modifica la información del profesor</p>
            </div>
            
            <div class="form-container">
                <form method="POST" action="guardar_profesor.php">
                    <input type="hidden" name="id_profesor" value="' . $profesor['id_profesor'] . '">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="' . htmlspecialchars($profesor['nombre']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" value="' . htmlspecialchars($profesor['apellidos']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="correo">Correo electrónico:</label>
                        <input type="email" id="correo" name="correo" class="form-control" value="' . htmlspecialchars($profesor['correo']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sueldo">Sueldo:</label>
                        <input type="number" id="sueldo" name="sueldo" class="form-control" step="0.01" value="' . $profesor['sueldo'] . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="1" ' . ($profesor['estado'] == '1' ? 'selected' : '') . '>Activo</option>
                            <option value="2" ' . ($profesor['estado'] == '2' ? 'selected' : '') . '>Inactivo</option>
                            <option value="3" ' . ($profesor['estado'] == '3' ? 'selected' : '') . '>Suspendido</option>
                        </select>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="../coordinador.php?seccion=profesores" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>';
} else {
    header("Location: ../coordinador.php?seccion=profesores&error=sin_profesor");
}
?>