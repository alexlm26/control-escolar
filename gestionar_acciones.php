<?php
session_start();
include "conexion.php";
include "header.php";
if ($_SESSION['rol'] != '3') { 
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Procesar acciones
    if (isset($_POST['acciones'])) {
        $sql_todas_acciones = "SELECT id_accion FROM acciones";
        $res_todas_acciones = $conexion->query($sql_todas_acciones);
        
        $actualizaciones = 0;
        $stmt = $conexion->prepare("UPDATE acciones SET activo = ? WHERE id_accion = ?");
        
        while($accion = $res_todas_acciones->fetch_assoc()) {
            $id_accion = $accion['id_accion'];
            $activo = isset($_POST['acciones'][$id_accion]) ? 1 : 0;
            
            $stmt->bind_param("ii", $activo, $id_accion);
            
            if ($stmt->execute()) {
                $actualizaciones++;
            }
        }
        $stmt->close();
    }
    if (isset($_POST['variables_globales'])) {
        $actualizaciones_variables = 0;
        $stmt_var = $conexion->prepare("UPDATE variables_globales SET valor = ? WHERE nombre = ?");
        
        foreach ($_POST['variables_globales'] as $nombre => $valor) {
            // Validar que el valor sea numérico y positivo
            if (is_numeric($valor) && $valor >= 0) {
                $valor_int = intval($valor);
                $stmt_var->bind_param("is", $valor_int, $nombre);
                
                if ($stmt_var->execute()) {
                    $actualizaciones_variables++;
                }
            }
        }
        $stmt_var->close();
    }
    
    // Mostrar mensaje de éxito
    $mensajes = [];
    if (isset($actualizaciones) && $actualizaciones > 0) {
        $mensajes[] = "Permisos actualizados correctamente ($actualizaciones acciones modificadas)";
    }
    if (isset($actualizaciones_variables) && $actualizaciones_variables > 0) {
        $mensajes[] = "Variables globales actualizadas ($actualizaciones_variables variables modificadas)";
    }
    
    if (!empty($mensajes)) {
        $_SESSION['success'] = implode(" | ", $mensajes);
    } else {
        $_SESSION['error'] = "No se realizaron cambios";
    }
    
    header("Location: gestionar_acciones.php");
    exit;
}

// Obtener todas las acciones para mostrar
$sql_acciones = "SELECT * FROM acciones ORDER BY id_accion";
$res_acciones = $conexion->query($sql_acciones);

// Obtener todas las variables globales
$sql_variables = "SELECT * FROM variables_globales ORDER BY id";
$res_variables = $conexion->query($sql_variables);
$variables_globales = [];
while ($var = $res_variables->fetch_assoc()) {
    $variables_globales[$var['nombre']] = $var;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Configuraciones</title>
<style>
/* Estilos base responsivos */
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #f5f5f5;
    line-height: 1.6;
}

.container {
    max-width: 100%;
    margin: 0;
    background: white;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h1 {
    color: #333;
    text-align: center;
    margin-bottom: 20px;
    font-size: 1.5rem;
    padding: 0 10px;
}

h2 {
    color: #444;
    border-bottom: 2px solid #007bff;
    padding-bottom: 8px;
    margin-top: 30px;
    margin-bottom: 15px;
    font-size: 1.3rem;
}

.config-section {
    margin-bottom: 30px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fafafa;
}

/* Tablas responsivas */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    font-size: 0.9rem;
    min-width: 600px; /* Mínimo ancho para tablas complejas */
}

th, td {
    padding: 10px 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #f8f9fa;
    font-weight: bold;
}

.btn-actualizar {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    display: block;
    margin: 20px auto;
    width: 100%;
    max-width: 300px;
}

.btn-actualizar:hover {
    background: #218838;
}

/* Switch responsivo */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #28a745;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Alertas */
.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-size: 0.9rem;
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

.estado-activo {
    color: #28a745;
    font-weight: bold;
}

.estado-inactivo {
    color: #dc3545;
    font-weight: bold;
}

/* Inputs responsivos */
.input-variable {
    width: 100%;
    max-width: 120px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
    font-size: 14px;
}

.input-variable:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 5px rgba(0,123,255,0.3);
}

.descripcion-variable {
    font-size: 12px;
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

.variable-info {
    display: flex;
    flex-direction: column;
}

.variable-name {
    font-weight: bold;
    color: #333;
}

.config-description {
    background: #e9ecef;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
    font-size: 0.9rem;
}

.config-description h4 {
    margin-top: 0;
    color: #007bff;
    font-size: 1.1rem;
}

.config-description ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.config-description li {
    margin-bottom: 5px;
}

/* Estilos para móviles pequeños */
@media (max-width: 480px) {
    .container {
        padding: 10px;
    }
    
    h1 {
        font-size: 1.3rem;
    }
    
    h2 {
        font-size: 1.1rem;
    }
    
    .config-section {
        padding: 10px;
    }
    
    table {
        font-size: 0.8rem;
        min-width: 500px;
    }
    
    th, td {
        padding: 8px 6px;
    }
    
    .btn-actualizar {
        padding: 10px 20px;
        font-size: 14px;
    }
    
    .config-description {
        padding: 10px;
    }
    
    .switch {
        width: 45px;
        height: 22px;
    }
    
    .slider:before {
        height: 14px;
        width: 14px;
    }
    
    input:checked + .slider:before {
        transform: translateX(23px);
    }
}

/* Estilos para tablets */
@media (min-width: 768px) {
    .container {
        max-width: 95%;
        margin: 20px auto;
        padding: 20px;
    }
    
    table {
        min-width: auto;
    }
}

/* Estilos para escritorio */
@media (min-width: 1024px) {
    .container {
        max-width: 1000px;
    }
    
    table {
        font-size: 1rem;
    }
}

/* Mejoras de accesibilidad */
@media (prefers-reduced-motion: reduce) {
    .slider, .slider:before {
        transition: none;
    }
}

/* Alto contraste para mejor legibilidad */
@media (prefers-contrast: high) {
    .config-section {
        border: 2px solid #000;
    }
    
    th {
        background-color: #000;
        color: #fff;
    }
}
</style>
</head>
<body>

<div class="container">
    <h1>Gestión de Configuraciones del Sistema</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <!-- Sección de Variables Globales -->
        <div class="config-section">
            <h2>Variables Globales del Sistema</h2>
            
            <div class="config-description">
                <h4>¿Qué son las Variables Globales?</h4>
                <p>Estas variables controlan los parámetros fundamentales del sistema educativo:</p>
                <ul>
                    <li><strong>Calificación Aprobatoria:</strong> Nota mínima requerida para aprobar una materia</li>
                    <li><strong>Semestres Máximos:</strong> Número máximo de semestres permitidos para terminar la carrera</li>
                    <li><strong>Semestre Asignación Especialidad:</strong> Semestre en el que los alumnos eligen especialidad</li>
                </ul>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Variable</th>
                            <th>Descripción</th>
                            <th>Valor Actual</th>
                            <th>Nuevo Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $variables_info = [
                            'calificacion_aprobatoria' => [
                                'descripcion' => 'Calificación mínima para aprobar (0-100)',
                                'min' => 0,
                                'max' => 100
                            ],
                            'semestres_maximos' => [
                                'descripcion' => 'Máximo de semestres permitidos',
                                'min' => 1,
                                'max' => 20
                            ],
                            'semestre_asignacion_especialidad' => [
                                'descripcion' => 'Semestre para elegir especialidad',
                                'min' => 1,
                                'max' => 10
                            ]
                        ];
                        
                        foreach ($variables_info as $nombre => $info): 
                            $valor_actual = $variables_globales[$nombre]['valor'] ?? 0;
                        ?>
                            <tr>
                                <td class="variable-name">
                                    <?php 
                                    $nombres_bonitos = [
                                        'calificacion_aprobatoria' => 'Calificación Aprobatoria',
                                        'semestres_maximos' => 'Semestres Máximos',
                                        'semestre_asignacion_especialidad' => 'Semestre para Especialidad'
                                    ];
                                    echo $nombres_bonitos[$nombre] ?? $nombre; 
                                    ?>
                                </td>
                                <td>
                                    <div class="variable-info">
                                        <span><?php echo $info['descripcion']; ?></span>
                                        <span class="descripcion-variable">Rango: <?php echo $info['min']; ?> - <?php echo $info['max']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo $valor_actual; ?></strong>
                                </td>
                                <td>
                                    <input type="number" 
                                           class="input-variable"
                                           name="variables_globales[<?php echo $nombre; ?>]" 
                                           value="<?php echo $valor_actual; ?>"
                                           min="<?php echo $info['min']; ?>"
                                           max="<?php echo $info['max']; ?>"
                                           required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección de Acciones -->
        <div class="config-section">
            <h2>Configuración de Acciones del Sistema</h2>
            
            <div class="config-description">
                <h4>¿Qué son las Acciones del Sistema?</h4>
                <p>Estas opciones controlan el comportamiento específico del sistema durante el avance de semestre:</p>
                <ul>
                    <li><strong>Aprobación Estricta:</strong> Los alumnos reprueban si tienen al menos una unidad por debajo de la calificación aprobatoria</li>
                    <li><strong>Verano:</strong> Activa el modo verano (no se avanza de semestre al procesar calificaciones)</li>
                </ul>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Acción</th>
                            <th>Descripción</th>
                            <th>Estado Actual</th>
                            <th>Activar/Desactivar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        
                        $res_acciones->data_seek(0);
                        
                        $descripciones_acciones = [
                            'modificar_calificaci' => 'Permitir modificación de calificaciones',
                            'subir_calificaciones' => 'Permitir subir nuevas calificaciones',
                            'aprobacion_estricta' => 'Reprobación por unidad (aprobación estricta)',
                            'verano' => 'Periodo de verano'
                        ];
                        
                        if ($res_acciones && $res_acciones->num_rows > 0): ?>
                            <?php while ($accion = $res_acciones->fetch_assoc()): ?>
                                <tr>
                                    <td class="variable-name">
                                        <?php 
                                        $nombre_bonito = $descripciones_acciones[$accion['accion']] ?? ucwords(str_replace('_', ' ', $accion['accion']));
                                        echo $nombre_bonito; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $descripciones_detalladas = [
                                            'modificar_calificaci' => 'Habilita la edición manual de calificaciones por parte de profesores',
                                            'subir_calificaciones' => 'Permite al profesor subir tareas de sus unidades',
                                            'aprobacion_estricta' => 'REPROBACIÓN POR UNIDAD: El alumno reprueba si tiene al menos una unidad por debajo del mínimo',
                                            'verano' => 'MODO VERANO: Al procesar calificaciones, los alumnos NO avanzan de semestre'
                                        ];
                                        echo $descripciones_detalladas[$accion['accion']] ?? 'Configuración del sistema';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $accion['activo'] ? 'estado-activo' : 'estado-inactivo'; ?>">
                                            <?php echo $accion['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   name="acciones[<?php echo $accion['id_accion']; ?>]" 
                                                   value="1" 
                                                   <?php echo $accion['activo'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No hay acciones configuradas</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="btn-actualizar">Guardar Todas las Configuraciones</button>
    </form>
</div>

</body>
</html>