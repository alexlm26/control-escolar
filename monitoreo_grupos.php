<?php
// monitoreo_grupos.php
session_start();
require_once 'conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Verificar si es alumno y obtener sus datos
$sql_alumno = "SELECT a.id_alumno, a.semestre, a.id_especialidad, a.promedio, c.id_carrera 
               FROM alumno a 
               INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
               INNER JOIN carrera c ON u.id_carrera = c.id_carrera 
               WHERE u.id_usuario = ?";
$stmt_alumno = $conexion->prepare($sql_alumno);
$stmt_alumno->bind_param("i", $id_usuario);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();
$alumno = $result_alumno->fetch_assoc();

if (!$alumno) {
    die("No se encontró información del alumno");
}

// Verificar si el monitoreo está activo
$sql_accion = "SELECT activo FROM acciones WHERE id_accion = 5";
$result_accion = $conexion->query($sql_accion);
$monitoreo_activo = $result_accion->fetch_assoc()['activo'];

if (!$monitoreo_activo) {
    include 'header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo No Disponible</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #1565c0;
            --color-secundario: #1976d2;
            --color-advertencia: #ff9800;
            --color-texto: #333;
            --color-fondo: #f8f9fa;
            --border-radius: 12px;
            --sombra: 0 8px 32px rgba(0,0,0,0.1);
        }

        .monitoreo-inactivo {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .tarjeta-inactiva {
            background: white;
            padding: 50px 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--sombra);
            text-align: center;
            max-width: 500px;
            width: 100%;
            border-top: 6px solid var(--color-advertencia);
        }

        .icono-inactivo {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            color: var(--color-advertencia);
        }

        .tarjeta-inactiva h1 {
            color: var(--color-advertencia);
            font-size: 2rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .tarjeta-inactiva p {
            color: var(--color-texto);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="monitoreo-inactivo">
        <div class="tarjeta-inactiva">
            <div class="icono-inactivo">
                <i class="fas fa-clock"></i>
            </div>
            <h1>Monitoreo No Disponible</h1>
            <p>El sistema de monitoreo de grupos se encuentra en mantenimiento programado.</p>
        </div>
    </div>
</body>
</html>
<?php
    exit();
}

// Verificar si el monitoreo está activo
$sql_accion = "SELECT activo FROM acciones WHERE id_accion = 5";
$result_accion = $conexion->query($sql_accion);
$monitoreo_activo = $result_accion->fetch_assoc()['activo'];

if (!$monitoreo_activo) {
    include 'header.php';
    // ... código de monitoreo inactivo ...
    exit();
}

// Determinar oportunidad y créditos máximos
$sql_historial = "SELECT m.id_materia, COUNT(*) as intentos 
                  FROM materia_cursada mc 
                  INNER JOIN materia m ON mc.id_materia = m.id_materia 
                  WHERE mc.id_alumno = ? AND mc.cal_final < 70 
                  GROUP BY m.id_materia";
$stmt_historial = $conexion->prepare($sql_historial);
$stmt_historial->bind_param("i", $alumno['id_alumno']);
$stmt_historial->execute();
$result_historial = $stmt_historial->get_result();
$materias_reprobadas = [];
while ($row = $result_historial->fetch_assoc()) {
    $materias_reprobadas[$row['id_materia']] = $row['intentos'];
}

// Calcular créditos disponibles
$max_reprobadas = 0;
foreach ($materias_reprobadas as $intentos) {
    if ($intentos > $max_reprobadas) {
        $max_reprobadas = $intentos;
    }
}

if ($max_reprobadas >= 2) {
    $creditos_maximos = 24;
    $tipo_alumno = "Alumno con Materias Especiales";
    $oportunidad_base = "Especial";
} elseif ($max_reprobadas >= 1) {
    $creditos_maximos = 28;
    $tipo_alumno = "Alumno con Repeticiones";
    $oportunidad_base = "Repetición";
} else {
    $creditos_maximos = 35;
    $tipo_alumno = "Alumno Regular";
    $oportunidad_base = "Ordinario";
}

// Obtener créditos actualmente inscritos
$sql_creditos_actuales = "SELECT COALESCE(SUM(m.creditos), 0) as creditos_actuales 
                          FROM asignacion a 
                          INNER JOIN clase c ON a.id_clase = c.id_clase 
                          INNER JOIN materia m ON c.id_materia = m.id_materia 
                          WHERE a.id_alumno = ? AND c.activo = 1";
$stmt_creditos = $conexion->prepare($sql_creditos_actuales);
$stmt_creditos->bind_param("i", $alumno['id_alumno']);
$stmt_creditos->execute();
$result_creditos = $stmt_creditos->get_result();
$creditos_actuales = $result_creditos->fetch_assoc()['creditos_actuales'];

$creditos_disponibles = $creditos_maximos - $creditos_actuales;

// Obtener materias ya cursadas o en curso del alumno
$sql_materias_cursadas = "SELECT DISTINCT m.id_materia 
                          FROM materia_cursada mc 
                          INNER JOIN materia m ON mc.id_materia = m.id_materia 
                          WHERE mc.id_alumno = ?";
$stmt_cursadas = $conexion->prepare($sql_materias_cursadas);
$stmt_cursadas->bind_param("i", $alumno['id_alumno']);
$stmt_cursadas->execute();
$result_cursadas = $stmt_cursadas->get_result();
$materias_cursadas = [];
while ($row = $result_cursadas->fetch_assoc()) {
    $materias_cursadas[] = $row['id_materia'];
}

// Obtener materias actualmente inscritas
$sql_materias_inscritas = "SELECT DISTINCT m.id_materia 
                           FROM asignacion a 
                           INNER JOIN clase c ON a.id_clase = c.id_clase 
                           INNER JOIN materia m ON c.id_materia = m.id_materia 
                           WHERE a.id_alumno = ? AND c.activo = 1";
$stmt_inscritas = $conexion->prepare($sql_materias_inscritas);
$stmt_inscritas->bind_param("i", $alumno['id_alumno']);
$stmt_inscritas->execute();
$result_inscritas = $stmt_inscritas->get_result();
$materias_inscritas = [];
while ($row = $result_inscritas->fetch_assoc()) {
    $materias_inscritas[] = $row['id_materia'];
}

// Obtener materias aprobadas (cal_final >= 70)
$sql_materias_aprobadas = "SELECT DISTINCT m.id_materia 
                          FROM materia_cursada mc 
                          INNER JOIN materia m ON mc.id_materia = m.id_materia 
                          WHERE mc.id_alumno = ? AND mc.cal_final >= 70";
$stmt_aprobadas = $conexion->prepare($sql_materias_aprobadas);
$stmt_aprobadas->bind_param("i", $alumno['id_alumno']);
$stmt_aprobadas->execute();
$result_aprobadas = $stmt_aprobadas->get_result();
$materias_aprobadas = [];
while ($row = $result_aprobadas->fetch_assoc()) {
    $materias_aprobadas[] = $row['id_materia'];
}

// Combinar materias que no puede tomar
$materias_no_permitidas = array_merge($materias_aprobadas, $materias_inscritas);

// Procesar inscripción si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clases_seleccionadas'])) {
    // Verificar si la inscripción está activa
    $sql_inscripcion_activa = "SELECT activo FROM acciones WHERE id_accion = 6";
    $result_inscripcion = $conexion->query($sql_inscripcion_activa);
    $inscripcion_activa = $result_inscripcion->fetch_assoc()['activo'];
    
    if (!$inscripcion_activa) {
        $mensaje_error = "La inscripción no está disponible en este momento";
        $tipo_mensaje_error = "error";
    } else {
        $clases_seleccionadas = $_POST['clases_seleccionadas'];
        $errores = [];
        $inscripciones_exitosas = 0;
        
        // VALIDACIÓN 1: Verificar materias duplicadas
        $materias_seleccionadas = [];
        foreach ($clases_seleccionadas as $id_clase) {
            $sql_materia_clase = "SELECT m.id_materia, m.nombre 
                                  FROM clase c 
                                  INNER JOIN materia m ON c.id_materia = m.id_materia 
                                  WHERE c.id_clase = ?";
            $stmt_materia = $conexion->prepare($sql_materia_clase);
            $stmt_materia->bind_param("i", $id_clase);
            $stmt_materia->execute();
            $result_materia = $stmt_materia->get_result();
            $clase_info = $result_materia->fetch_assoc();
            
            if ($clase_info) {
                $materias_seleccionadas[$clase_info['id_materia']][] = [
                    'id_clase' => $id_clase,
                    'nombre_materia' => $clase_info['nombre']
                ];
            }
        }
        
        // Verificar si hay materias duplicadas
        foreach ($materias_seleccionadas as $id_materia => $clases) {
            if (count($clases) > 1) {
                $nombres_clases = array_map(function($clase) {
                    return $clase['nombre_materia'];
                }, $clases);
                
                $errores[] = "Tienes la materia '{$clases[0]['nombre_materia']}' seleccionada " . 
                            count($clases) . " veces. Elige solo una clase e inscríbete.";
                
                // Remover todas las clases de esta materia duplicada
                $clases_seleccionadas = array_filter($clases_seleccionadas, function($id_clase) use ($clases) {
                    foreach ($clases as $clase) {
                        if ($clase['id_clase'] == $id_clase) {
                            return false;
                        }
                    }
                    return true;
                });
            }
        }
        
        // Si después de filtrar duplicados no quedan clases, mostrar error
        if (empty($clases_seleccionadas)) {
            $errores[] = "No se puede proceder con la inscripción debido a materias duplicadas.";
        }
        
        foreach ($clases_seleccionadas as $id_clase) {
            // Iniciar transacción para cada inscripción
            $conexion->begin_transaction();
            
            try {
                // VALIDACIÓN 2: Verificar que la clase existe y tiene cupo (con LOCK para evitar condiciones de carrera)
                $sql_verificar_clase = "SELECT c.capacidad, c.asignado, m.creditos, m.nombre, m.id_materia 
                                        FROM clase c 
                                        INNER JOIN materia m ON c.id_materia = m.id_materia 
                                        WHERE c.id_clase = ? AND c.activo = 1 FOR UPDATE";
                $stmt_verificar = $conexion->prepare($sql_verificar_clase);
                $stmt_verificar->bind_param("i", $id_clase);
                $stmt_verificar->execute();
                $result_verificar = $stmt_verificar->get_result();
                $clase = $result_verificar->fetch_assoc();
                
                if (!$clase) {
                    $errores[] = "La clase seleccionada no existe o no está activa";
                    $conexion->rollback();
                    continue;
                }
                
                // Verificar cupo con LOCK para evitar condiciones de carrera
                if ($clase['asignado'] >= $clase['capacidad']) {
                    $errores[] = "La clase {$clase['nombre']} ya está llena";
                    $conexion->rollback();
                    continue;
                }
                
                if ($clase['creditos'] > $creditos_disponibles) {
                    $errores[] = "No tienes créditos suficientes para {$clase['nombre']}";
                    $conexion->rollback();
                    continue;
                }
                
                // Determinar oportunidad para esta materia
                $intentos_materia = $materias_reprobadas[$clase['id_materia']] ?? 0;
                $oportunidad_materia = "Ordinario";
                if ($intentos_materia == 1) {
                    $oportunidad_materia = "Repetición";
                } elseif ($intentos_materia >= 2) {
                    $oportunidad_materia = "Especial";
                }
                
                // Verificar horarios
                if (!verificarHorarios($conexion, $alumno['id_alumno'], $id_clase)) {
                    $errores[] = "La clase {$clase['nombre']} tiene conflicto de horarios con tus clases actuales";
                    $conexion->rollback();
                    continue;
                }
                
                // Realizar la inscripción
                $sql_inscribir = "INSERT INTO asignacion (id_clase, id_alumno, oportunidad, semestre) 
                                  VALUES (?, ?, ?, ?)";
                $stmt_inscribir = $conexion->prepare($sql_inscribir);
                $stmt_inscribir->bind_param("iisi", $id_clase, $alumno['id_alumno'], $oportunidad_materia, $alumno['semestre']);
                $stmt_inscribir->execute();
                
                // Actualizar contador de asignados
                $sql_actualizar_asignados = "UPDATE clase SET asignado = asignado + 1 WHERE id_clase = ?";
                $stmt_actualizar = $conexion->prepare($sql_actualizar_asignados);
                $stmt_actualizar->bind_param("i", $id_clase);
                $stmt_actualizar->execute();
                
                $conexion->commit();
                
                $inscripciones_exitosas++;
                $creditos_disponibles -= $clase['creditos'];
                
            } catch (Exception $e) {
                $conexion->rollback();
                $errores[] = "Error al inscribir en {$clase['nombre']}: " . $e->getMessage();
            }
        }
        
        if ($inscripciones_exitosas > 0) {
            $mensaje = "Se realizaron {$inscripciones_exitosas} inscripción(es) exitosas";
            $tipo_mensaje = "success";
            // Recargar datos
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        if (!empty($errores)) {
            $mensaje_error = implode("<br>", $errores);
            $tipo_mensaje_error = "error";
        }
    }
}

// Función para verificar conflictos de horarios
function verificarHorarios($conexion, $id_alumno, $id_clase_nueva) {
    // Obtener horarios de la nueva clase
    $sql_horarios_nueva = "SELECT dia, hora FROM horarios_clase WHERE id_clase = ?";
    $stmt_nueva = $conexion->prepare($sql_horarios_nueva);
    $stmt_nueva->bind_param("i", $id_clase_nueva);
    $stmt_nueva->execute();
    $result_nueva = $stmt_nueva->get_result();
    $horarios_nueva = [];
    while ($row = $result_nueva->fetch_assoc()) {
        $horarios_nueva[] = $row;
    }
    
    // Obtener horarios de las clases actuales del alumno
    $sql_horarios_actuales = "SELECT hc.dia, hc.hora 
                              FROM horarios_clase hc 
                              INNER JOIN clase c ON hc.id_clase = c.id_clase 
                              INNER JOIN asignacion a ON c.id_clase = a.id_clase 
                              WHERE a.id_alumno = ? AND c.activo = 1";
    $stmt_actuales = $conexion->prepare($sql_horarios_actuales);
    $stmt_actuales->bind_param("i", $id_alumno);
    $stmt_actuales->execute();
    $result_actuales = $stmt_actuales->get_result();
    $horarios_actuales = [];
    while ($row = $result_actuales->fetch_assoc()) {
        $horarios_actuales[] = $row;
    }
    
    // Verificar conflictos
    foreach ($horarios_nueva as $horario_nuevo) {
        foreach ($horarios_actuales as $horario_actual) {
            if ($horario_nuevo['dia'] == $horario_actual['dia'] && $horario_nuevo['hora'] == $horario_actual['hora']) {
                return false;
            }
        }
    }
    
    return true;
}

// Función para verificar si una materia tiene prerrequisitos cumplidos
function verificarPrerrequisitos($conexion, $id_materia, $materias_aprobadas) {
    // Obtener el prerrequisito directo de la materia
    $sql_prerrequisito = "SELECT id_prerrequisito FROM materia WHERE id_materia = ?";
    $stmt_prerrequisito = $conexion->prepare($sql_prerrequisito);
    $stmt_prerrequisito->bind_param("i", $id_materia);
    $stmt_prerrequisito->execute();
    $result_prerrequisito = $stmt_prerrequisito->get_result();
    $prerrequisito = $result_prerrequisito->fetch_assoc();
    
    if (!$prerrequisito || $prerrequisito['id_prerrequisito'] === null) {
        return true; // No tiene prerrequisitos
    }
    
    $id_prerrequisito = $prerrequisito['id_prerrequisito'];
    
    // Verificar si el prerrequisito directo está aprobado
    if (!in_array($id_prerrequisito, $materias_aprobadas)) {
        return false;
    }
    
    // Verificar recursivamente la cadena completa de prerrequisitos
    return verificarPrerrequisitos($conexion, $id_prerrequisito, $materias_aprobadas);
}

// Obtener clases disponibles con verificación de prerrequisitos
$sql_clases = "SELECT c.id_clase, m.nombre as materia, m.creditos, m.semestre_sugerido, 
                      m.id_materia, m.id_especialidad, c.capacidad, c.asignado, c.grupo,
                      s.nombre as salon, s.edificio, m.id_prerrequisito
               FROM clase c
               INNER JOIN materia m ON c.id_materia = m.id_materia
               INNER JOIN salon s ON c.id_salon = s.id_salon
               INNER JOIN profesor p ON c.id_profesor = p.id_profesor
               WHERE c.activo = 1 
               AND c.asignado < c.capacidad
               AND m.semestre_sugerido <= ?
               AND m.id_carrera = ?";
               
if ($alumno['id_especialidad'] == 1) {
    $sql_clases .= " AND (m.id_especialidad = 1 OR m.id_especialidad IS NULL)";
} else {
    $sql_clases .= " AND (m.id_especialidad = 1 OR m.id_especialidad = ?)";
}

// Excluir materias ya cursadas o inscritas
if (!empty($materias_no_permitidas)) {
    $placeholders = str_repeat('?,', count($materias_no_permitidas) - 1) . '?';
    $sql_clases .= " AND m.id_materia NOT IN ($placeholders)";
}

$sql_clases .= " ORDER BY m.semestre_sugerido, m.nombre";

$stmt_clases = $conexion->prepare($sql_clases);

// Preparar parámetros
if ($alumno['id_especialidad'] == 1) {
    if (empty($materias_no_permitidas)) {
        $stmt_clases->bind_param("ii", $alumno['semestre'], $alumno['id_carrera']);
    } else {
        $tipos = "ii" . str_repeat("i", count($materias_no_permitidas));
        $params = array_merge([$alumno['semestre'], $alumno['id_carrera']], $materias_no_permitidas);
        $stmt_clases->bind_param($tipos, ...$params);
    }
} else {
    if (empty($materias_no_permitidas)) {
        $stmt_clases->bind_param("iii", $alumno['semestre'], $alumno['id_carrera'], $alumno['id_especialidad']);
    } else {
        $tipos = "iii" . str_repeat("i", count($materias_no_permitidas));
        $params = array_merge([$alumno['semestre'], $alumno['id_carrera'], $alumno['id_especialidad']], $materias_no_permitidas);
        $stmt_clases->bind_param($tipos, ...$params);
    }
}

$stmt_clases->execute();
$result_clases = $stmt_clases->get_result();
$clases_disponibles = [];

while ($row = $result_clases->fetch_assoc()) {
    // Verificar prerrequisitos - SOLO si la materia tiene prerrequisito
    if ($row['id_prerrequisito'] !== null) {
        // Solo mostrar la materia si tiene el prerrequisito aprobado
        if (in_array($row['id_prerrequisito'], $materias_aprobadas)) {
            // Determinar oportunidad para cada materia
            $intentos_materia = $materias_reprobadas[$row['id_materia']] ?? 0;
            $row['oportunidad'] = "Ordinario";
            $row['clase_oportunidad'] = "ordinario";
            if ($intentos_materia == 1) {
                $row['oportunidad'] = "Repetición";
                $row['clase_oportunidad'] = "repeticion";
            } elseif ($intentos_materia >= 2) {
                $row['oportunidad'] = "Especial";
                $row['clase_oportunidad'] = "especial";
            }
            $clases_disponibles[] = $row;
        }
    } else {
        // La materia no tiene prerrequisitos, siempre se muestra
        $intentos_materia = $materias_reprobadas[$row['id_materia']] ?? 0;
        $row['oportunidad'] = "Ordinario";
        $row['clase_oportunidad'] = "ordinario";
        if ($intentos_materia == 1) {
            $row['oportunidad'] = "Repetición";
            $row['clase_oportunidad'] = "repeticion";
        } elseif ($intentos_materia >= 2) {
            $row['oportunidad'] = "Especial";
            $row['clase_oportunidad'] = "especial";
        }
        $clases_disponibles[] = $row;
    }
}

// Obtener horarios para cada clase
foreach ($clases_disponibles as &$clase) {
    $sql_horarios = "SELECT dia, hora FROM horarios_clase WHERE id_clase = ? ORDER BY dia, hora";
    $stmt_horarios = $conexion->prepare($sql_horarios);
    $stmt_horarios->bind_param("i", $clase['id_clase']);
    $stmt_horarios->execute();
    $result_horarios = $stmt_horarios->get_result();
    $clase['horarios'] = [];
    while ($row = $result_horarios->fetch_assoc()) {
        $clase['horarios'][] = $row;
    }
}
unset($clase);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de Grupos</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #1565c0;
            --color-secundario: #1976d2;
            --color-exito: #28a745;
            --color-error: #dc3545;
            --color-advertencia: #ffc107;
            --color-texto: #333;
            --color-fondo: #f8f9fa;
            --sombra: 0 2px 6px rgba(0,0,0,0.1);
            --sombra-hover: 0 4px 12px rgba(0,0,0,0.15);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--color-fondo);
            color: var(--color-texto);
            line-height: 1.4;
            font-size: 14px;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
        }

        .header {
            background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--sombra);
        }

        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        /* PANEL INFORMACIÓN OPTIMIZADO PARA PC */
        .panel-informacion {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        .info-card {
            background: white;
            padding: 12px;
            border-radius: var(--border-radius);
            box-shadow: var(--sombra);
            border-left: 4px solid var(--color-primario);
        }

        .info-card.creditos-info {
            background: linear-gradient(135deg, var(--color-exito), #20c997);
            color: white;
            border-left: 4px solid var(--color-exito);
        }

        .info-card h3 {
            font-size: 0.8rem;
            color: var(--color-primario);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .creditos-info h3 {
            color: white;
        }

        .info-card .valor {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--color-texto);
            line-height: 1.2;
        }

        .creditos-info .valor {
            color: white;
        }

        .info-card .detalle {
            font-size: 0.75rem;
            color: #666;
            margin-top: 3px;
        }

        .creditos-info .detalle {
            color: rgba(255,255,255,0.9);
        }

        .mensaje {
            padding: 10px;
            margin: 12px 0;
            border-radius: var(--border-radius);
            box-shadow: var(--sombra);
            font-size: 0.85rem;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--color-exito);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--color-error);
        }

        .btn-actualizar {
            background: var(--color-primario);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--sombra);
            margin-bottom: 12px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-actualizar:hover {
            background: var(--color-secundario);
            box-shadow: var(--sombra-hover);
        }

        /* Estilos para tabla en desktop - COMPACTA (MANTENIDOS) */
        .tabla-desktop {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--sombra);
            font-size: 0.8rem;
        }

        .tabla-desktop th,
        .tabla-desktop td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .tabla-desktop th {
            background: var(--color-primario);
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
            position: sticky;
            top: 0;
        }

        .tabla-desktop tr:hover {
            background: #f5f5f5;
        }

        .clase-seleccionada {
            background: #e8f5e8 !important;
        }

        .cupo {
            color: var(--color-exito);
            font-weight: bold;
            font-size: 0.75rem;
        }

        .lleno {
            color: var(--color-error);
            font-weight: bold;
            font-size: 0.75rem;
        }

        .horario-dia {
            font-size: 0.7rem;
            line-height: 1.2;
        }

        .oportunidad {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-align: center;
        }

        .oportunidad.ordinario {
            background: #e8f5e8;
            color: var(--color-exito);
        }

        .oportunidad.repeticion {
            background: #fff3cd;
            color: #856404;
        }

        .oportunidad.especial {
            background: #f8d7da;
            color: var(--color-error);
        }

        .btn-inscribir {
            background: var(--color-exito);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--sombra);
            margin-top: 15px;
            width: 100%;
        }

        .btn-inscribir:hover:not(:disabled) {
            background: #218838;
            box-shadow: var(--sombra-hover);
        }

        .btn-inscribir:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* ESTILOS PARA MÓVIL - ULTRA COMPACTOS */
        .tarjetas-movil {
            display: none;
        }

        .tarjeta-clase {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: var(--transition);
        }

        .tarjeta-clase:hover {
            border-color: var(--color-primario);
        }

        .tarjeta-clase.seleccionada {
            border-color: var(--color-exito);
            background: #f8fff8;
        }

        .tarjeta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .tarjeta-materia {
            font-weight: bold;
            font-size: 0.8rem;
            color: var(--color-primario);
            line-height: 1.1;
            flex: 1;
        }

        .info-linea {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.75rem;
            gap: 8px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .info-label {
            color: #666;
            font-size: 0.7rem;
        }

        .info-valor {
            font-weight: 600;
            font-size: 0.75rem;
        }

        .horarios-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            margin: 8px 0;
            font-size: 0.65rem;
        }

        .horario-item {
            background: var(--color-fondo);
            padding: 4px;
            border-radius: 3px;
            text-align: center;
            min-height: 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .horario-dia-nombre {
            font-weight: bold;
            font-size: 0.6rem;
            margin-bottom: 1px;
        }

        .horario-horas {
            font-size: 0.6rem;
            line-height: 1.1;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 0.75rem;
        }

        .checkbox-container input[type="checkbox"] {
            width: 14px;
            height: 14px;
            accent-color: var(--color-exito);
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .panel-informacion {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabla-desktop th:nth-child(5),
            .tabla-desktop td:nth-child(5) {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 8px;
            }

            .header {
                padding: 12px;
                margin-bottom: 12px;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .panel-informacion {
                grid-template-columns: 1fr;
                gap: 8px;
                margin-bottom: 10px;
            }

            .info-card {
                padding: 10px;
            }

            .tabla-desktop {
                display: none;
            }

            .tarjetas-movil {
                display: block;
            }

            .info-linea {
                flex-wrap: wrap;
                gap: 6px;
            }

            .info-item {
                flex: 1;
                min-width: 45%;
            }

            .horarios-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .tarjeta-clase {
                padding: 6px;
            }

            .info-item {
                min-width: 48%;
            }

            .horarios-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .horario-item {
                min-height: 26px;
            }
        }

        @media (max-width: 360px) {
            .info-item {
                min-width: 100%;
            }
            
            .horarios-grid {
                grid-template-columns: 1fr;
            }
        }

        .sin-clases {
            text-align: center;
            padding: 30px 15px;
            color: #666;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--sombra);
            font-size: 0.9rem;
        }

        .loading {
            text-align: center;
            padding: 15px;
            color: var(--color-primario);
        }
    </style>
</head>
<body>
    <?php if (file_exists('header.php')) include 'header.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Monitoreo de Grupos</h1>
            <p>Selecciona las clases a las que deseas inscribirte</p>
        </div>
        
        <!-- PANEL INFORMACIÓN OPTIMIZADO -->
        <div class="panel-informacion">
            <div class="info-card">
                <h3><i class="fas fa-user-graduate"></i> Tipo de Alumno</h3>
                <div class="valor"><?php echo $tipo_alumno; ?></div>
                <div class="detalle">
                    <?php if ($creditos_maximos == 35): ?>
                        Regular: 35 créditos
                    <?php elseif ($creditos_maximos == 28): ?>
                        Repetición: 28 créditos
                    <?php else: ?>
                        Especial: 24 créditos
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-calendar-alt"></i> Semestre</h3>
                <div class="valor"><?php echo $alumno['semestre']; ?></div>
                <div class="detalle">Actual</div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-book"></i> Créditos</h3>
                <div class="valor"><?php echo $creditos_actuales; ?></div>
                <div class="detalle">Inscritos</div>
            </div>
            
            <div class="info-card creditos-info">
                <h3><i class="fas fa-wallet"></i> Disponibles</h3>
                <div class="valor"><?php echo $creditos_disponibles; ?>/<?php echo $creditos_maximos; ?></div>
                <div class="detalle">Límite: <?php echo $creditos_maximos; ?> créditos</div>
            </div>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="mensaje success">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error)): ?>
            <div class="mensaje error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error; ?>
            </div>
        <?php endif; ?>

        <button class="btn-actualizar" onclick="actualizarClases()">
            <i class="fas fa-sync-alt"></i> Actualizar Lista
        </button>

        <form id="formInscripcion" method="POST">
            <!-- Vista Desktop - TABLA COMPACTA (MANTENIDA) -->
            <div class="clases-container">
                <table class="tabla-desktop">
                    <thead>
                        <tr>
                            <th style="width: 30px;">Sel</th>
                            <th>Materia</th>
                            <th style="width: 50px;">Cred</th>
                            <th style="width: 50px;">Grupo</th>
                            <th style="width: 80px;">Oportunidad</th>
                            <th style="width: 50px;">Sem</th>
                            <th style="width: 50px;">Cupo</th>
                            <th style="width: 80px;">Lunes</th>
                            <th style="width: 80px;">Martes</th>
                            <th style="width: 80px;">Miércoles</th>
                            <th style="width: 80px;">Jueves</th>
                            <th style="width: 80px;">Viernes</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-clases-desktop">
                        <?php foreach ($clases_disponibles as $clase): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="clases_seleccionadas[]" value="<?php echo $clase['id_clase']; ?>"
                                        data-creditos="<?php echo $clase['creditos']; ?>"
                                        onchange="validarCreditos()">
                                </td>
                                <td title="<?php echo htmlspecialchars($clase['materia']); ?>">
                                    <?php echo substr(htmlspecialchars($clase['materia']), 0, 25); ?><?php echo strlen($clase['materia']) > 25 ? '...' : ''; ?>
                                </td>
                                <td><?php echo $clase['creditos']; ?></td>
                                <td><?php echo htmlspecialchars($clase['grupo']); ?></td>
                                <td>
                                    <span class="oportunidad <?php echo $clase['clase_oportunidad']; ?>">
                                        <?php echo $clase['oportunidad']; ?>
                                    </span>
                                </td>
                                <td><?php echo $clase['semestre_sugerido']; ?></td>
                                <td class="<?php echo ($clase['asignado'] < $clase['capacidad']) ? 'cupo' : 'lleno'; ?>">
                                    <?php echo $clase['asignado'] . '/' . $clase['capacidad']; ?>
                                </td>
                                <?php
                                $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                                $horarios_por_dia = [];
                                
                                foreach ($clase['horarios'] as $horario) {
                                    $dia_nombre = $dias_semana[$horario['dia'] - 1] ?? 'Desconocido';
                                    $hora_formateada = date('H:i', strtotime($horario['hora'] . ':00'));
                                    $horarios_por_dia[$dia_nombre][] = $hora_formateada;
                                }
                                
                                foreach ($dias_semana as $dia) {
                                    echo '<td class="horario-dia">';
                                    if (isset($horarios_por_dia[$dia])) {
                                        echo implode('<br>', $horarios_por_dia[$dia]);
                                    }
                                    echo '</td>';
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Vista Móvil - TARJETAS ULTRA COMPACTAS -->
            <div class="tarjetas-movil" id="tarjetas-movil">
                <?php foreach ($clases_disponibles as $clase): ?>
                    <div class="tarjeta-clase" data-clase-id="<?php echo $clase['id_clase']; ?>">
                        <!-- Nombre de la clase -->
                        <div class="tarjeta-header">
                            <div class="tarjeta-materia"><?php echo htmlspecialchars($clase['materia']); ?></div>
                            <div class="cupo <?php echo ($clase['asignado'] < $clase['capacidad']) ? 'cupo' : 'lleno'; ?>">
                                <?php echo $clase['asignado'] . '/' . $clase['capacidad']; ?>
                            </div>
                        </div>
                        
                        <!-- Línea de información: Créditos - Grupo - Semestre - Oportunidad -->
                        <div class="info-linea">
                            <div class="info-item">
                                <span class="info-label">Créditos:</span>
                                <span class="info-valor"><?php echo $clase['creditos']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Grupo:</span>
                                <span class="info-valor"><?php echo htmlspecialchars($clase['grupo']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Semestre:</span>
                                <span class="info-valor"><?php echo $clase['semestre_sugerido']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Oportunidad:</span>
                                <span class="info-valor oportunidad <?php echo $clase['clase_oportunidad']; ?>">
                                    <?php echo $clase['oportunidad']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Grid de horarios: 3 columnas -->
                        <div class="horarios-grid">
                            <?php
                            $dias_semana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];
                            $horarios_mostrados = 0;
                            
                            foreach ($dias_semana as $index => $dia) {
                                $dia_numero = $index + 1;
                                $horarios_dia = array_filter($clase['horarios'], function($h) use ($dia_numero) {
                                    return $h['dia'] == $dia_numero;
                                });
                                
                                echo '<div class="horario-item">';
                                echo '<div class="horario-dia-nombre">' . $dia . '</div>';
                                echo '<div class="horario-horas">';
                                if (!empty($horarios_dia)) {
                                    $horas = [];
                                    foreach ($horarios_dia as $horario) {
                                        $horas[] = date('H:i', strtotime($horario['hora'] . ':00'));
                                    }
                                    echo implode('<br>', $horas);
                                } else {
                                    echo '-';
                                }
                                echo '</div>';
                                echo '</div>';
                                
                                $horarios_mostrados++;
                            }
                            ?>
                        </div>

                        <!-- Checkbox de selección -->
                        <div class="checkbox-container">
                            <input type="checkbox" name="clases_seleccionadas[]" 
                                   value="<?php echo $clase['id_clase']; ?>"
                                   data-creditos="<?php echo $clase['creditos']; ?>"
                                   onchange="validarCreditos()">
                            <label>Seleccionar</label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($clases_disponibles)): ?>
                <button type="submit" class="btn-inscribir" id="btnInscribir">
                    <i class="fas fa-paper-plane"></i> Inscribir Clases Seleccionadas
                </button>
            <?php else: ?>
                <div class="sin-clases">
                    <h3><i class="fas fa-inbox"></i> No hay clases disponibles</h3>
                    <p>No se encontraron clases que coincidan con tus criterios.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function actualizarClases() {
            const btn = $('.btn-actualizar');
            btn.html('<i class="fas fa-spinner fa-spin"></i> Actualizando...');
            btn.prop('disabled', true);

            $.ajax({
                url: 'actualizar_clases.php',
                type: 'GET',
                success: function(response) {
                    const partes = response.split('<!-- SEPARADOR MOVIL -->');
                    if (partes.length === 2) {
                        $('#tabla-clases-desktop').html(partes[0]);
                        $('#tarjetas-movil').html(partes[1]);
                    } else {
                        $('#tabla-clases-desktop').html(response);
                        $('#tarjetas-movil').html(response);
                    }
                    btn.html('<i class="fas fa-sync-alt"></i> Actualizar Lista');
                    btn.prop('disabled', false);
                    validarCreditos();
                    
                    $('.tarjeta-clase').on('click', function(e) {
                        if (!$(e.target).is('input[type="checkbox"]')) {
                            const checkbox = $(this).find('input[type="checkbox"]');
                            checkbox.prop('checked', !checkbox.prop('checked'));
                            validarCreditos();
                        }
                    });
                },
                error: function() {
                    alert('Error al actualizar las clases');
                    btn.html('<i class="fas fa-sync-alt"></i> Actualizar Lista');
                    btn.prop('disabled', false);
                }
            });
        }

        function validarCreditos() {
            let creditosSeleccionados = 0;
            const creditosDisponibles = <?php echo $creditos_disponibles; ?>;
            
            $('input[name="clases_seleccionadas[]"]:checked').each(function() {
                creditosSeleccionados += parseInt($(this).data('creditos'));
                $(this).closest('.tarjeta-clase').toggleClass('seleccionada', true);
            });
            
            $('input[name="clases_seleccionadas[]"]:not(:checked)').each(function() {
                $(this).closest('.tarjeta-clase').removeClass('seleccionada');
            });
            
            const btnInscribir = $('#btnInscribir');
            if (creditosSeleccionados > creditosDisponibles) {
                btnInscribir.prop('disabled', true);
                btnInscribir.html(`<i class="fas fa-exclamation-triangle"></i> Créditos excedidos: ${creditosSeleccionados}/${creditosDisponibles}`);
            } else {
                btnInscribir.prop('disabled', false);
                btnInscribir.html(`<i class="fas fa-paper-plane"></i> Inscribir (${creditosSeleccionados}/${creditosDisponibles} créditos)`);
            }
        }

        $(document).ready(function() {
            validarCreditos();
            
            $('.tarjeta-clase').on('click', function(e) {
                if (!$(e.target).is('input[type="checkbox"]')) {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked'));
                    validarCreditos();
                }
            });
        });
    </script>
</body>
</html>