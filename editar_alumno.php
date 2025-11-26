<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_alumno = $_GET['id_alumno'] ?? '';

if ($id_alumno) {
    // Obtener datos del alumno
    $sql_alumno = "
        SELECT a.id_alumno, u.nombre, u.apellidos, u.correo, a.semestre, a.especialidad, a.estado 
        FROM alumno a 
        INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE a.id_alumno = $id_alumno
    ";
    $alumno = $conexion->query($sql_alumno)->fetch_assoc();
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Alumno - Sistema Escolar</title>
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
            <a href="../coordinador.php?seccion=alumnos" class="back-link">← Volver al Panel</a>
            
            <div class="header">
                <h1>Editar Alumno</h1>
                <p>Modifica la información del alumno</p>
            </div>
            
            <div class="form-container">
                <form method="POST" action="guardar_alumno.php">
                    <input type="hidden" name="id_alumno" value="' . $alumno['id_alumno'] . '">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="' . htmlspecialchars($alumno['nombre']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" value="' . htmlspecialchars($alumno['apellidos']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="correo">Correo electrónico:</label>
                        <input type="email" id="correo" name="correo" class="form-control" value="' . htmlspecialchars($alumno['correo']) . '" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="semestre">Semestre:</label>
                        <select id="semestre" name="semestre" class="form-control" required>
                            <option value="1" ' . ($alumno['semestre'] == 1 ? 'selected' : '') . '>1° Semestre</option>
                            <option value="2" ' . ($alumno['semestre'] == 2 ? 'selected' : '') . '>2° Semestre</option>
                            <option value="3" ' . ($alumno['semestre'] == 3 ? 'selected' : '') . '>3° Semestre</option>
                            <option value="4" ' . ($alumno['semestre'] == 4 ? 'selected' : '') . '>4° Semestre</option>
                            <option value="5" ' . ($alumno['semestre'] == 5 ? 'selected' : '') . '>5° Semestre</option>
                            <option value="6" ' . ($alumno['semestre'] == 6 ? 'selected' : '') . '>6° Semestre</option>
                            <option value="7" ' . ($alumno['semestre'] == 7 ? 'selected' : '') . '>7° Semestre</option>
                            <option value="8" ' . ($alumno['semestre'] == 8 ? 'selected' : '') . '>8° Semestre</option>
                            <option value="9" ' . ($alumno['semestre'] == 9 ? 'selected' : '') . '>9° Semestre</option>
                            <option value="10" ' . ($alumno['semestre'] == 10 ? 'selected' : '') . '>10° Semestre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="especialidad">Especialidad:</label>
                        <select id="especialidad" name="especialidad" class="form-control" required>
                            <option value="Sin especificar" ' . ($alumno['especialidad'] == 'Sin especificar' ? 'selected' : '') . '>Sin Especialidad</option>
                            <option value="sistemas" ' . ($alumno['especialidad'] == 'sistemas' ? 'selected' : '') . '>Sistemas Computacionales</option>
                            <option value="software" ' . ($alumno['especialidad'] == 'software' ? 'selected' : '') . '>Desarrollo de Software</option>
                            <option value="redes" ' . ($alumno['especialidad'] == 'redes' ? 'selected' : '') . '>Redes y Telecomunicaciones</option>
                            <option value="electrica" ' . ($alumno['especialidad'] == 'electrica' ? 'selected' : '') . '>Ingeniería Eléctrica</option>
                            <option value="electronica" ' . ($alumno['especialidad'] == 'electronica' ? 'selected' : '') . '>Ingeniería Electrónica</option>
                            <option value="mecanica" ' . ($alumno['especialidad'] == 'mecanica' ? 'selected' : '') . '>Ingeniería Mecánica</option>
                            <option value="industrial" ' . ($alumno['especialidad'] == 'industrial' ? 'selected' : '') . '>Ingeniería Industrial</option>
                            <option value="gestion" ' . ($alumno['especialidad'] == 'gestion' ? 'selected' : '') . '>Gestión Empresarial</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="1" ' . ($alumno['estado'] == '1' ? 'selected' : '') . '>Activo</option>
                            <option value="2" ' . ($alumno['estado'] == '2' ? 'selected' : '') . '>Inactivo</option>
                            <option value="3" ' . ($alumno['estado'] == '3' ? 'selected' : '') . '>Suspendido</option>
                            <option value="4" ' . ($alumno['estado'] == '4' ? 'selected' : '') . '>Egresado</option>
                        </select>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="../coordinador.php?seccion=alumnos" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>';
} else {
    header("Location: ../coordinador.php?seccion=alumnos&error=sin_alumno");
}
?>