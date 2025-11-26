<?php
session_start();

// Permitir acceso a cualquier rol (alumno, profesor, coordinador)
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != '1' && $_SESSION['rol'] != '2' && $_SESSION['rol'] != '3')) {
    header("Location: index.php");
    exit;
}

include "conexion.php";
include "header.php";

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

// Obtener información del usuario y su carrera
if ($rol == '3') { // Coordinador
    $sql_usuario = "SELECT u.id_carrera, car.nombre as carrera_nombre 
                   FROM usuario u 
                   LEFT JOIN carrera car ON u.id_carrera = car.id_carrera 
                   WHERE u.id_usuario = $id_usuario";
} elseif ($rol == '2') { // Profesor
    $sql_usuario = "SELECT u.id_carrera, car.nombre as carrera_nombre 
                   FROM usuario u 
                   LEFT JOIN carrera car ON u.id_carrera = car.id_carrera 
                   WHERE u.id_usuario = $id_usuario";
} else { // Alumno - CORREGIDO: usar id_especialidad en lugar de especialidad
    $sql_usuario = "SELECT u.id_carrera, car.nombre as carrera_nombre, a.id_alumno, 
                           a.id_especialidad, e.nombre as especialidad_nombre
                   FROM usuario u 
                   INNER JOIN alumno a ON u.id_usuario = a.id_usuario
                   LEFT JOIN carrera car ON u.id_carrera = car.id_carrera 
                   LEFT JOIN especialidad e ON a.id_especialidad = e.id_especialidad
                   WHERE u.id_usuario = $id_usuario";
}

$usuario_info = $conexion->query($sql_usuario)->fetch_assoc();
$id_carrera_usuario = $usuario_info['id_carrera'] ?? null;
$carrera_nombre = $usuario_info['carrera_nombre'] ?? 'Sin carrera asignada';
$id_alumno = $usuario_info['id_alumno'] ?? null;
$id_especialidad_alumno = $usuario_info['id_especialidad'] ?? null;
$especialidad_alumno_nombre = $usuario_info['especialidad_nombre'] ?? 'Sin especialidad';

// Verificar si es coordinador ADMINISTRADOR (puede ver todo)
$es_admin = ($id_carrera_usuario == 0 && $rol == '3');

// Variables de búsqueda y filtros
$busqueda_materia = $_GET['busqueda_materia'] ?? '';
$filtro_especialidad = $_GET['filtro_especialidad'] ?? '';
$filtro_semestre = $_GET['filtro_semestre'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materias de la Carrera - <?php echo htmlspecialchars($carrera_nombre); ?></title>
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
        
        body {
            background: var(--color-fondo);
            font-family: "Poppins", "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* BANNER */
        .banner-bienvenida {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: white;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .banner-texto h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 700;
        }
        
        .banner-texto p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .especialidad-alumno {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
            display: inline-block;
        }
        
        /* CONTENEDOR PRINCIPAL */
        .contenedor-principal {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ESTILOS PARA MATERIAS */
        .grid-materias {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .tarjeta-materia {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            box-shadow: var(--sombra-suave);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .tarjeta-materia:hover {
            transform: translateY(-3px);
            box-shadow: var(--sombra-hover);
        }
        
        .tarjeta-materia-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .tarjeta-materia-header h3 {
            margin: 0;
            flex: 1;
        }
        
        .creditos-materia {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .prerrequisito-info {
            background: #fff3e0;
            color: #e65100;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85em;
            border-left: 3px solid #ff9800;
        }
        
        .sin-prerrequisito {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85em;
            border-left: 3px solid #2196f3;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: var(--color-primario);
        }
        
        /* ESTADOS DE MATERIA PARA ALUMNOS */
        .estado-materia {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .estado-cursando {
            background: #e3f2fd;
            color: #1565c0;
            border: 2px solid #1565c0;
        }
        
        .estado-pasada {
            background: #e8f5e8;
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }
        
        .estado-reprobada {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #c62828;
        }
        
        .estado-pendiente {
            background: #fff3e0;
            color: #ef6c00;
            border: 2px solid #ef6c00;
        }
        
        .estado-recursamiento {
            background: #e8f5e8;
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }
        
        .estado-especial {
            background: #e8f5e8;
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }
        
        .calificacion-info {
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9em;
            text-align: center;
            font-weight: 600;
        }
        
        .calificacion-aprobada {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .calificacion-reprobada {
            background: #ffebee;
            color: #c62828;
        }
        
        .info-intentos {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
        
        /* BOTONES */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }
        
        .btn-primary {
            background: var(--color-primario);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--color-secundario);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
            border: none;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        /* LEYENDA DE ESPECIALIDADES */
        .leyenda-especialidades {
            background: white;
            padding: 15px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra-suave);
        }
        
        .leyenda-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            margin-bottom: 8px;
        }
        
        .color-muestra {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            margin-right: 8px;
        }
        
        /* GRUPOS POR SEMESTRE */
        .grupo-semestre {
            margin-bottom: 30px;
        }
        
        .titulo-semestre {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        /* BADGE ESPECIALIDAD */
        .badge-especialidad {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 8px;
        }
        
        /* BUSCADORES */
        .buscador-container {
            background: white;
            padding: 20px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra-suave);
        }
        
        .buscador-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .buscador-input {
            flex: 1;
            min-width: 200px;
        }
        
        .buscador-select {
            min-width: 200px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .grid-materias {
                grid-template-columns: 1fr;
            }
            
            .buscador-form {
                flex-direction: column;
            }
            
            .buscador-input, .buscador-select {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
    <div class="banner-texto">
        <br><br><br><br>
        <h1>MATERIAS DE LA CARRERA</h1>
        <p><?php echo htmlspecialchars($carrera_nombre); ?></p>
        <p>
            <?php 
            if ($rol == '1') {
                echo 'Vista de Alumno';
                echo '<div class="especialidad-alumno">Especialidad: ' . htmlspecialchars($especialidad_alumno_nombre) . ' (ID: ' . $id_especialidad_alumno . ')</div>';
            } elseif ($rol == '2') echo 'Vista de Profesor';
            else echo 'Vista de Coordinador';
            ?>
        </p>
    </div>
</section>

<div class="contenedor-principal">
    
    <!-- LEYENDA DE ESTADOS (SOLO PARA ALUMNOS) -->
    <?php if ($rol == '1'): ?>
    <div class="leyenda-especialidades">
        <h6 style="margin-bottom: 15px; color: #555;">Estados de Materia:</h6>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #1565c0; border: 2px solid #1565c0;"></div>
            <span>Cursando Actualmente</span>
        </div>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #2e7d32; border: 2px solid #2e7d32;"></div>
            <span>Materia Aprobada</span>
        </div>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #c62828; border: 2px solid #c62828;"></div>
            <span>Materia Reprobada</span>
        </div>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #ef6c00; border: 2px solid #ef6c00;"></div>
            <span>Materia Pendiente</span>
        </div>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #2e7d32; border: 2px solid #2e7d32;"></div>
            <span>Aprobado en Recursamiento</span>
        </div>
        <div class="leyenda-item">
            <div class="color-muestra" style="background-color: #2e7d32; border: 2px solid #2e7d32;"></div>
            <span>Aprobado en Especial</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- BUSCADOR DE MATERIAS -->
    <div class="buscador-container">
        <form method="GET" class="buscador-form">
            <div class="buscador-input">
                <label class="form-label">Buscar materia:</label>
                <input type="text" class="form-control" name="busqueda_materia" value="<?php echo htmlspecialchars($busqueda_materia); ?>" 
                       placeholder="Nombre de la materia...">
            </div>
            <div class="buscador-select">
                <label class="form-label">Filtrar por semestre:</label>
                <select class="form-select" name="filtro_semestre">
                    <option value="">Todos los semestres</option>
                    <?php for($i = 1; $i <= 9; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $filtro_semestre == $i ? 'selected' : ''; ?>>
                            Semestre <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Filtro de especialidad solo para coordinadores y profesores -->
            <?php if ($rol == '3' || $rol == '2'): ?>
            <div class="buscador-select">
                <label class="form-label">Filtrar por especialidad:</label>
                <select class="form-select" name="filtro_especialidad">
                    <option value="">Todas las especialidades</option>
                    <?php 
                    // Obtener especialidades para el filtro
                    $where_especialidad = $es_admin ? "1=1" : "e.id_carrera = $id_carrera_usuario OR e.id_especialidad = 1";
                    $sql_especialidades_filtro = "
                        SELECT e.id_especialidad, e.nombre
                        FROM especialidad e
                        WHERE $where_especialidad AND e.activo = 1
                        ORDER BY e.id_especialidad = 1 DESC, e.nombre
                    ";
                    $especialidades_filtro = $conexion->query($sql_especialidades_filtro);
                    while($esp = $especialidades_filtro->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $esp['id_especialidad']; ?>" 
                            <?php echo $filtro_especialidad == $esp['id_especialidad'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($esp['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <!-- Filtro de estado solo para alumnos -->
            <?php if ($rol == '1'): ?>
            <div class="buscador-select">
                <label class="form-label">Filtrar por estado:</label>
                <select class="form-select" name="filtro_estado">
                    <option value="">Todos los estados</option>
                    <option value="cursando" <?php echo $filtro_estado == 'cursando' ? 'selected' : ''; ?>>Cursando</option>
                    <option value="pasada" <?php echo $filtro_estado == 'pasada' ? 'selected' : ''; ?>>Aprobadas</option>
                    <option value="reprobada" <?php echo $filtro_estado == 'reprobada' ? 'selected' : ''; ?>>Reprobadas</option>
                    <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="recursamiento" <?php echo $filtro_estado == 'recursamiento' ? 'selected' : ''; ?>>Aprobadas en Recursamiento</option>
                    <option value="especial" <?php echo $filtro_estado == 'especial' ? 'selected' : ''; ?>>Aprobadas en Especial</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="materias_carrera.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <?php
    // Obtener especialidades para colores
    $where_especialidad = $es_admin ? "1=1" : "e.id_carrera = $id_carrera_usuario OR e.id_especialidad = 1";
    $sql_especialidades = "
        SELECT e.id_especialidad, e.nombre, e.descripcion
        FROM especialidad e
        WHERE $where_especialidad AND e.activo = 1
        ORDER BY e.id_especialidad = 1 DESC, e.nombre
    ";
    $especialidades = $conexion->query($sql_especialidades);
    
    // Crear mapeo de colores para especialidades
    $colores_especialidades = [
        1 => ['color' => '#4caf50', 'nombre' => 'General']
    ];
    
    $paleta_colores = [
        '#2196f3', '#9c27b0', '#ff9800', '#f44336', '#00bcd4',
        '#8bc34a', '#ff5722', '#795548', '#607d8b', '#e91e63'
    ];
    
    $especialidades_data = $especialidades->fetch_all(MYSQLI_ASSOC);
    $color_index = 0;
    
    foreach ($especialidades_data as $esp) {
        if ($esp['id_especialidad'] != 1) {
            $colores_especialidades[$esp['id_especialidad']] = [
                'color' => $paleta_colores[$color_index % count($paleta_colores)],
                'nombre' => $esp['nombre']
            ];
            $color_index++;
        }
    }

    // Obtener materias de la carrera con filtros - CONSULTA CORREGIDA
    $where_conditions = [];
    
    if (!$es_admin) {
        $where_conditions[] = "m.id_carrera = " . intval($id_carrera_usuario);
    }
    
    // Para alumnos: mostrar solo materias generales y de su especialidad - CORREGIDO
    if ($rol == '1' && $id_especialidad_alumno) {
        $where_conditions[] = "(m.id_especialidad = 1 OR m.id_especialidad = " . intval($id_especialidad_alumno) . ")";
    }
    
    // Para coordinadores y profesores: aplicar filtro de especialidad si está seleccionado
    if (($rol == '3' || $rol == '2') && !empty($filtro_especialidad)) {
        $where_conditions[] = "m.id_especialidad = " . intval($filtro_especialidad);
    }
    
    if (!empty($busqueda_materia)) {
        $busqueda_like = $conexion->real_escape_string($busqueda_materia);
        $where_conditions[] = "(m.nombre LIKE '%$busqueda_like%')";
    }
    
    if (!empty($filtro_semestre)) {
        $where_conditions[] = "m.semestre_sugerido = " . intval($filtro_semestre);
    }
    
    // Construir la cláusula WHERE
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    // CONSULTA CORREGIDA
    $sql_materias = "
        SELECT m.id_materia, m.nombre, m.creditos, m.unidades, m.semestre_sugerido,
               m.id_prerrequisito, m.id_especialidad,
               prerreq.nombre as prerrequisito_nombre,
               e.nombre as especialidad_nombre,
               car.nombre as carrera_nombre
        FROM materia m
        LEFT JOIN materia prerreq ON m.id_prerrequisito = prerreq.id_materia
        LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        LEFT JOIN carrera car ON m.id_carrera = car.id_carrera
        $where_clause
        ORDER BY m.semestre_sugerido, m.nombre
    ";
    
    $materias = $conexion->query($sql_materias);
    
    // Para alumnos: obtener información de materias cursadas y asignaciones
    $materias_alumno = [];
    $intentos_materias = [];
    $historial_materias = [];
    
    if ($rol == '1' && $id_alumno) {
        // Obtener TODAS las calificaciones por materia (historial completo) usando el campo 'oportunidad'
        $sql_historial_calificaciones = "
            SELECT mc.id_materia, mc.cal_final, mc.aprobado, mc.periodo, mc.oportunidad,
                   (SELECT COUNT(*) FROM materia_cursada mc2 WHERE mc2.id_materia = mc.id_materia AND mc2.id_alumno = $id_alumno) as intentos
            FROM materia_cursada mc
            WHERE mc.id_alumno = $id_alumno 
            ORDER BY mc.id_materia, mc.periodo DESC
        ";
        $historial_result = $conexion->query($sql_historial_calificaciones);
        
        while($row = $historial_result->fetch_assoc()) {
            $id_materia = $row['id_materia'];
            
            // Guardar historial completo por materia
            if (!isset($historial_materias[$id_materia])) {
                $historial_materias[$id_materia] = [];
            }
            $historial_materias[$id_materia][] = [
                'calificacion' => $row['cal_final'],
                'aprobado' => $row['aprobado'],
                'periodo' => $row['periodo'],
                'oportunidad' => $row['oportunidad'],
                'intentos' => $row['intentos']
            ];
            
            // Para la última calificación (comportamiento actual)
            if (!isset($materias_alumno[$id_materia])) {
                $materias_alumno[$id_materia] = [
                    'tipo' => 'cursada',
                    'calificacion' => $row['cal_final'],
                    'aprobado' => $row['aprobado'],
                    'periodo' => $row['periodo'],
                    'oportunidad' => $row['oportunidad']
                ];
                $intentos_materias[$id_materia] = $row['intentos'];
            }
        }
        
        // Materias actualmente cursando (asignaciones)
        $sql_materias_actuales = "
            SELECT c.id_materia
            FROM asignacion a
            INNER JOIN clase c ON a.id_clase = c.id_clase
            WHERE a.id_alumno = $id_alumno AND c.activo = 1
        ";
        $materias_actuales_result = $conexion->query($sql_materias_actuales);
        while($row = $materias_actuales_result->fetch_assoc()) {
            $materias_alumno[$row['id_materia']] = [
                'tipo' => 'cursando',
                'calificacion' => null,
                'aprobado' => null,
                'periodo' => null,
                'oportunidad' => null
            ];
        }
    }
    
    // Organizar materias por semestre
    $materias_por_semestre = [];
    if ($materias) {
        while($materia = $materias->fetch_assoc()) {
            $semestre = $materia['semestre_sugerido'];
            
            // Determinar estado para alumnos
            $estado_materia = 'pendiente';
            $calificacion = null;
            $intentos = 0;
            $oportunidad_actual = 'ordinario';
            $mostrar_materia = true;
            
            if ($rol == '1' && isset($materias_alumno[$materia['id_materia']])) {
                $info_materia = $materias_alumno[$materia['id_materia']];
                
                if ($info_materia['tipo'] == 'cursando') {
                    $estado_materia = 'cursando';
                } elseif ($info_materia['tipo'] == 'cursada') {
                    $calificacion = $info_materia['calificacion'];
                    $intentos = $intentos_materias[$materia['id_materia']] ?? 1;
                    $oportunidad_actual = $info_materia['oportunidad'] ?? 'ordinario';
                    
                    // VERIFICAR SI LA MATERIA ESTÁ APROBADA
                    if ($info_materia['aprobado']) {
                        // Si está aprobada, determinar el tipo de aprobación basado en la oportunidad
                        if ($oportunidad_actual == 'recursamiento') {
                            $estado_materia = 'recursamiento';
                        } elseif ($oportunidad_actual == 'especial') {
                            $estado_materia = 'especial';
                        } elseif ($oportunidad_actual == 'global') {
                            $estado_materia = 'pasada';
                        } else {
                            $estado_materia = 'pasada';
                        }
                    } else {
                        $estado_materia = 'reprobada';
                    }
                }
            }
            
            // Aplicar filtro de estado para alumnos
            if ($rol == '1' && !empty($filtro_estado)) {
                $mostrar_materia = false;
                switch($filtro_estado) {
                    case 'cursando':
                        $mostrar_materia = ($estado_materia == 'cursando');
                        break;
                    case 'pasada':
                        $mostrar_materia = ($estado_materia == 'pasada');
                        break;
                    case 'reprobada':
                        $mostrar_materia = ($estado_materia == 'reprobada');
                        break;
                    case 'pendiente':
                        $mostrar_materia = ($estado_materia == 'pendiente');
                        break;
                    case 'recursamiento':
                        $mostrar_materia = ($estado_materia == 'recursamiento');
                        break;
                    case 'especial':
                        $mostrar_materia = ($estado_materia == 'especial');
                        break;
                }
            }
            
            if ($mostrar_materia) {
                if (!isset($materias_por_semestre[$semestre])) {
                    $materias_por_semestre[$semestre] = [];
                }
                $materia['estado'] = $estado_materia;
                $materia['calificacion'] = $calificacion;
                $materia['intentos'] = $intentos;
                $materia['oportunidad'] = $oportunidad_actual;
                $materias_por_semestre[$semestre][] = $materia;
            }
        }
    }
    ?>
    
    <div class="seccion-materias">
        <?php if (!empty($materias_por_semestre)): ?>
            <?php foreach($materias_por_semestre as $semestre => $materias_del_semestre): ?>
                <div class="grupo-semestre">
                    <div class="titulo-semestre">
                        Semestre <?php echo $semestre; ?>
                    </div>
                    <div class="grid-materias">
                        <?php foreach($materias_del_semestre as $materia): 
                            $color_especialidad = $colores_especialidades[$materia['id_especialidad']]['color'] ?? '#4caf50';
                            $nombre_especialidad = $materia['especialidad_nombre'];
                        ?>
                            <div class="tarjeta-materia" style="border-left: 5px solid <?php echo $color_especialidad; ?>;">
                                
                                <!-- Estado de la materia (solo para alumnos) -->
                                <?php if ($rol == '1'): ?>
                                    <div class="estado-materia estado-<?php echo $materia['estado']; ?>">
                                        <?php 
                                        switch($materia['estado']) {
                                            case 'cursando': echo 'Cursando'; break;
                                            case 'pasada': echo 'Aprobada'; break;
                                            case 'reprobada': echo 'Reprobada'; break;
                                            case 'recursamiento': echo 'Aprobado en Recursamiento'; break;
                                            case 'especial': echo 'Aprobado en Especial'; break;
                                            default: echo 'Pendiente';
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="tarjeta-materia-header">
                                    <h3 style="color: <?php echo $color_especialidad; ?>;">
                                        <?php echo htmlspecialchars($materia['nombre']); ?>
                                        <span class="badge-especialidad" style="background: <?php echo $color_especialidad; ?>20; color: <?php echo $color_especialidad; ?>; border: 1px solid <?php echo $color_especialidad; ?>30;">
                                            <?php echo htmlspecialchars($nombre_especialidad); ?>
                                        </span>
                                    </h3>
                                    <span class="creditos-materia"><?php echo $materia['creditos']; ?> créditos</span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Unidades:</span>
                                    <span class="info-value"><?php echo $materia['unidades']; ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Semestre sugerido:</span>
                                    <span class="info-value"><?php echo $materia['semestre_sugerido']; ?></span>
                                </div>
                                
                                <!-- Información de calificación e intentos para alumnos -->
                                <?php if ($rol == '1' && $materia['calificacion'] !== null): ?>
                                    <div class="calificacion-info <?php echo in_array($materia['estado'], ['pasada', 'recursamiento', 'especial']) ? 'calificacion-aprobada' : 'calificacion-reprobada'; ?>">
                                        Calificación: <?php echo number_format($materia['calificacion'], 2); ?>
                                        <?php if ($materia['intentos'] > 1): ?>
                                            <div class="info-intentos">
                                                Cursada <?php echo $materia['intentos']; ?> veces
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($materia['estado'] == 'recursamiento'): ?>
                                            <div class="info-intentos">
                                                Aprobada en recursamiento
                                            </div>
                                        <?php elseif ($materia['estado'] == 'especial'): ?>
                                            <div class="info-intentos">
                                                Aprobada en examen especial
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($materia['id_prerrequisito']): ?>
                                    <div class="prerrequisito-info">
                                        <strong>Prerrequisito:</strong> <?php echo htmlspecialchars($materia['prerrequisito_nombre']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sin-prerrequisito">
                                        <strong>Sin prerrequisitos</strong>
                                    </div>
                                <?php endif; ?>

                                <!-- Botón Ver Cadena disponible para TODOS -->
                                <div class="acciones" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                    <button type="button" class="btn btn-warning" style="font-size: 0.8em;" 
                                            onclick="cargarCadenaPrerrequisitos(<?php echo $materia['id_materia']; ?>, '<?php echo htmlspecialchars($materia['nombre']); ?>')">
                                        Ver Cadena
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>No hay materias que coincidan con la búsqueda</h3>
                <p>Intenta con otros términos de búsqueda o ajusta los filtros</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL PARA CADENA DE PRERREQUISITOS (disponible para todos) -->
<div class="modal fade" id="modalCadenaPrerrequisitos" tabindex="-1" aria-labelledby="modalCadenaPrerrequisitosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCadenaPrerrequisitosLabel">Cadena de Prerrequisitos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoCadenaPrerrequisitos">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Función para cargar cadena de prerrequisitos (disponible para todos)
function cargarCadenaPrerrequisitos(idMateria, nombreMateria) {
    document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalCadenaPrerrequisitosLabel').textContent = 'Cadena de prerrequisitos: ' + nombreMateria;
    
    var modal = new bootstrap.Modal(document.getElementById('modalCadenaPrerrequisitos'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_cadena_prerrequisitos.php?id_materia=' + idMateria, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = '<div class="alert alert-danger">Error al cargar la cadena de prerrequisitos</div>';
        }
    };
    xhr.send();
}
</script>
        
</body>
</html>
<?php include 'footer.php' ?>