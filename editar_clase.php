<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_clase = $_GET['id_clase'] ?? '';

if ($id_clase) {
    // Obtener datos de la clase
    $sql_clase = "
        SELECT c.id_clase, c.id_materia, c.id_profesor, c.id_salon, c.periodo, c.capacidad, c.activo,
               m.nombre as materia_nombre
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        WHERE c.id_clase = $id_clase
    ";
    $clase = $conexion->query($sql_clase)->fetch_assoc();
    
    // Obtener opciones para selects
    $materias = $conexion->query("SELECT id_materia, nombre FROM materia WHERE id_carrera = (SELECT id_carrera FROM usuario WHERE id_usuario = {$_SESSION['id_usuario']}) ORDER BY nombre");
    $profesores = $conexion->query("SELECT p.id_profesor, u.nombre, u.apellidos FROM profesor p INNER JOIN usuario u ON p.id_usuario = u.id_usuario WHERE u.id_carrera = (SELECT id_carrera FROM usuario WHERE id_usuario = {$_SESSION['id_usuario']}) ORDER BY u.nombre");
    $salones = $conexion->query("SELECT id_salon, nombre, edificio FROM salon ORDER BY edificio, nombre");
    
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Clase - Sistema Escolar</title>
        <style>
:root {
    --color-primario: #1565c0;
    --color-secundario: #1976d2;
    --color-fondo: #f4f6f8;
    --color-texto: #333;
    --color-blanco: #fff;
    --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
    --sombra-hover: 0 8px 18px rgba(0,0,0,0.15);
    --radio-borde: 14px;
}

* {
    box-sizing: border-box;
}

body {
    background: var(--color-fondo);
    font-family: "Poppins", "Segoe UI", sans-serif;
    color: var(--color-texto);
    margin: 0;
    padding: 0;
}

.container {
    padding: 20px 5%;
    max-width: 600px;
    margin: auto;
}

/* HEADER DE PÁGINA */
.page-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 25px;
    border-radius: var(--radio-borde);
    margin-bottom: 25px;
    box-shadow: var(--sombra-suave);
    text-align: center;
}

.page-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
    font-weight: 700;
}

.page-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1em;
}

/* BOTONES */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-size: 0.95em;
    min-width: 140px;
}

.btn-primary {
    background: var(--color-primario);
    color: white;
}

.btn-primary:hover {
    background: var(--color-secundario);
    transform: translateY(-2px);
    box-shadow: var(--sombra-hover);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* TARJETAS Y CONTENEDORES */
.form-container {
    background: white;
    border-radius: var(--radio-borde);
    padding: 25px;
    box-shadow: var(--sombra-suave);
    margin-bottom: 25px;
    transition: all 0.3s ease;
}

.form-container:hover {
    box-shadow: var(--sombra-hover);
}

/* FORMULARIOS */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 0.95em;
}

.form-control {
    width: 100%;
    padding: 14px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
    transition: all 0.3s ease;
    font-family: inherit;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--color-primario);
    box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' fill=\'%23666\' viewBox=\'0 0 16 16\'%3E%3Cpath d=\'M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z\'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 12px;
    padding-right: 40px;
}

/* GRUPO DE BOTONES */
.btn-group {
    display: flex;
    gap: 15px;
    justify-content: flex-start;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

/* BACK LINK */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--color-primario);
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    padding: 10px 15px;
    border-radius: 8px;
    background: white;
    box-shadow: var(--sombra-suave);
    transition: all 0.3s ease;
}

.back-link:hover {
    background: var(--color-primario);
    color: white;
    transform: translateX(-5px);
}

/* ESTADOS */
.status-active {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.status-inactive {
    background: #ffebee;
    color: #c62828;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .container {
        padding: 15px 3%;
    }
    
    .page-header {
        padding: 20px;
    }
    
    .page-header h1 {
        font-size: 1.5em;
    }
    
    .form-container {
        padding: 20px;
    }
    
    .btn-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .back-link {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 10px 2%;
    }
    
    .page-header {
        padding: 15px;
    }
    
    .page-header h1 {
        font-size: 1.3em;
    }
    
    .form-container {
        padding: 15px;
    }
    
    .form-control {
        padding: 12px;
    }
    
    .btn {
        padding: 10px 20px;
        font-size: 0.9em;
    }
}

/* MEJORAS PARA SELECTS EN MÓVIL */
@media (max-width: 768px) {
    select.form-control {
        font-size: 16px; /* Previene zoom en iOS */
    }
    
    .form-control {
        font-size: 16px; /* Previene zoom en iOS */
    }
}

/* ANIMACIONES */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-container {
    animation: fadeIn 0.5s ease-out;
}

/* ESTADOS DE FORMULARIO */
.form-group:focus-within label {
    color: var(--color-primario);
}

/* LOADING STATE */
.btn.loading {
    position: relative;
    color: transparent;
}

.btn.loading::after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
        </style>
    </head>
    <body>
        <div class="container">
            <a href="../coordinador.php?seccion=clases" class="back-link">
                <span>←</span> Volver al Panel
            </a>
            
            <div class="page-header">
                <h1>Editar Clase</h1>
                <p>Modifica la información de: ' . htmlspecialchars($clase['materia_nombre']) . '</p>
            </div>
            
            <div class="form-container">
                <form method="POST" action="guardar_clase.php" id="editClassForm">
                    <input type="hidden" name="id_clase" value="' . $clase['id_clase'] . '">
                    
                    <div class="form-group">
                        <label for="id_materia">Materia:</label>
                        <select id="id_materia" name="id_materia" class="form-control" required>
                            ' . getSelectOptions($materias, $clase['id_materia'], 'id_materia', 'nombre') . '
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_profesor">Profesor:</label>
                        <select id="id_profesor" name="id_profesor" class="form-control" required>
                            ' . getSelectOptions($profesores, $clase['id_profesor'], 'id_profesor', 'nombre', 'apellidos') . '
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_salon">Salón:</label>
                        <select id="id_salon" name="id_salon" class="form-control" required>
                            ' . getSelectOptions($salones, $clase['id_salon'], 'id_salon', 'nombre', 'edificio') . '
                        </select>
                    </div>
                    
                    <div class="form-group">
    <label for="periodo">Período:</label>
    <select id="periodo" name="periodo" class="form-control" required>
        <option value="">Seleccionar período...</option>
        <option value="Enero-Julio ' . date('Y') . '" ' . ($clase['periodo'] == "Enero-Julio " . date('Y') ? 'selected' : '') . '>Enero - Julio ' . date('Y') . '</option>
        <option value="Agosto-Diciembre ' . date('Y') . '" ' . ($clase['periodo'] == "Agosto-Diciembre " . date('Y') ? 'selected' : '') . '>Agosto - Diciembre ' . date('Y') . '</option>
        <option value="Verano ' . date('Y') . '" ' . ($clase['periodo'] == "Verano " . date('Y') ? 'selected' : '') . '>Verano ' . date('Y') . '</option>
    </select>
</div>
                    
                    <div class="form-group">
                        <label for="capacidad">Capacidad:</label>
                        <input type="number" id="capacidad" name="capacidad" class="form-control" 
                               min="1" max="50" value="' . $clase['capacidad'] . '" 
                               placeholder="Número máximo de alumnos" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="activo">Estado:</label>
                        <select id="activo" name="activo" class="form-control" required>
                            <option value="1" ' . ($clase['activo'] ? 'selected' : '') . '>Activa</option>
                            <option value="0" ' . (!$clase['activo'] ? 'selected' : '') . '>Cerrada</option>
                        </select>
                        <div style="margin-top: 8px; font-size: 0.85em; color: #666;">
                            ' . ($clase['activo'] ? 
                                '<span class="status-active">● Clase activa - aceptando alumnos</span>' : 
                                '<span class="status-inactive">● Clase cerrada - no acepta alumnos</span>') . '
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            Guardar Cambios
                        </button>
                        <a href="../coordinador.php?seccion=clases" class="btn btn-secondary">
                            ↩️ Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("editClassForm");
            const submitBtn = document.getElementById("submitBtn");
            
            // Validación del formulario
            form.addEventListener("submit", function(e) {
                const periodo = document.getElementById("periodo").value.trim();
                const capacidad = document.getElementById("capacidad").value;
                
                if (!periodo) {
                    e.preventDefault();
                    alert("Por favor, ingresa el período académico.");
                    return;
                }
                
                if (!capacidad || capacidad < 1 || capacidad > 50) {
                    e.preventDefault();
                    alert("La capacidad debe ser un número entre 1 y 50.");
                    return;
                }
                
                // Mostrar loading
                submitBtn.classList.add("loading");
                submitBtn.disabled = true;
                
                // Simular tiempo de procesamiento
                setTimeout(() => {
                    submitBtn.classList.remove("loading");
                    submitBtn.disabled = false;
                }, 2000);
            });
            
            // Mejorar experiencia en móvil
            const selects = document.querySelectorAll("select.form-control");
            selects.forEach(select => {
                select.addEventListener("change", function() {
                    this.style.borderColor = "#1565c0";
                    this.style.backgroundColor = "#f8fbff";
                });
                
                select.addEventListener("focus", function() {
                    this.style.borderColor = "#1565c0";
                    this.style.boxShadow = "0 0 0 3px rgba(21, 101, 192, 0.1)";
                });
                
                select.addEventListener("blur", function() {
                    this.style.boxShadow = "none";
                });
            });
            
            // Efectos visuales para inputs
            const inputs = document.querySelectorAll("input.form-control");
            inputs.forEach(input => {
                input.addEventListener("focus", function() {
                    this.parentElement.querySelector("label").style.color = "#1565c0";
                });
                
                input.addEventListener("blur", function() {
                    this.parentElement.querySelector("label").style.color = "#333";
                });
            });
            
            // Prevenir envío accidental en móvil
            let formSubmitted = false;
            form.addEventListener("submit", function() {
                if (formSubmitted) {
                    return false;
                }
                formSubmitted = true;
                setTimeout(() => {
                    formSubmitted = false;
                }, 3000);
            });
        });
        </script>
    </body>
    </html>';
} else {
    header("Location: ../coordinador.php?seccion=clases&error=sin_clase");
}

function getSelectOptions($result, $selected, $id_field, $name_field, $second_field = null) {
    $options = '<option value="">Seleccionar...</option>';
    while($row = $result->fetch_assoc()) {
        $isSelected = $row[$id_field] == $selected ? 'selected' : '';
        $display = htmlspecialchars($row[$name_field]);
        if ($second_field && isset($row[$second_field])) {
            $display .= ' ' . htmlspecialchars($row[$second_field]);
        }
        $value = $row[$id_field];
        $options .= "<option value='$value' $isSelected>$display</option>";
    }
    return $options;
}