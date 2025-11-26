<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_POST['clases']) || !isset($_POST['id_grupo'])) {
    header("Location: ../detalle_grupo.php?id=" . $_POST['id_grupo'] . "&error=No se seleccionaron clases");
    exit;
}

$id_grupo = intval($_POST['id_grupo']);
$clases_seleccionadas = $_POST['clases'];

/* ---------------------------------------------------------
   FUNCIONES PARA VALIDACIÓN DE ALUMNOS
--------------------------------------------------------- */

// Función recursiva para verificar cadena de prerrequisitos
function verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia_actual, &$pendientes, $nivel = 0) {
    if ($nivel > 10) return;
    
    $sql_prerreq = "SELECT id_prerrequisito FROM materia WHERE id_materia = ?";
    $stmt = $conexion->prepare($sql_prerreq);
    $stmt->bind_param("i", $id_materia_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $materia = $result->fetch_assoc();
        $id_prerrequisito = $materia['id_prerrequisito'];
        
        if ($id_prerrequisito) {
            // Verificar si el prerrequisito está APROBADO en materia_cursada
            $sql_aprobado = "SELECT 1 FROM materia_cursada 
                            WHERE id_alumno = ? AND id_materia = ? AND aprobado = 1";
            $stmt_aprob = $conexion->prepare($sql_aprobado);
            $stmt_aprob->bind_param("ii", $id_alumno, $id_prerrequisito);
            $stmt_aprob->execute();
            $aprobado = $stmt_aprob->get_result()->num_rows > 0;
            
            if (!$aprobado) {
                $sql_nombre = "SELECT nombre FROM materia WHERE id_materia = ?";
                $stmt_nombre = $conexion->prepare($sql_nombre);
                $stmt_nombre->bind_param("i", $id_prerrequisito);
                $stmt_nombre->execute();
                $nombre_materia = $stmt_nombre->get_result()->fetch_assoc()['nombre'];
                
                $pendientes[] = $nombre_materia;
            }
            
            // Verificar recursivamente los prerrequisitos del prerrequisito
            verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_prerrequisito, $pendientes, $nivel + 1);
        }
    }
}

function verificarCadenaPrerrequisitos($conexion, $id_alumno, $id_materia) {
    $prerrequisitos_pendientes = [];
    verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia, $prerrequisitos_pendientes);
    return $prerrequisitos_pendientes;
}

// Función para verificar compatibilidad de especialidades
function verificarEspecialidadAlumno($conexion, $id_alumno, $id_materia) {
    // Obtener información de la materia (especialidad)
    $sql_materia = "SELECT m.id_especialidad, e.nombre as especialidad_nombre 
                   FROM materia m 
                   LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad 
                   WHERE m.id_materia = ?";
    $stmt = $conexion->prepare($sql_materia);
    $stmt->bind_param("i", $id_materia);
    $stmt->execute();
    $materia_data = $stmt->get_result()->fetch_assoc();
    
    // Si la materia es de especialidad general (id=1), cualquier alumno puede tomarla
    if ($materia_data['id_especialidad'] == 1) {
        return ['compatible' => true, 'razon' => ''];
    }
    
    // Obtener especialidad del alumno
    $sql_alumno = "SELECT a.id_especialidad, e.nombre as especialidad_nombre 
                  FROM alumno a 
                  LEFT JOIN especialidad e ON a.id_especialidad = e.id_especialidad 
                  WHERE a.id_alumno = ?";
    $stmt_alumno = $conexion->prepare($sql_alumno);
    $stmt_alumno->bind_param("i", $id_alumno);
    $stmt_alumno->execute();
    $alumno_data = $stmt_alumno->get_result()->fetch_assoc();
    
    // Verificar si el alumno tiene la misma especialidad que la materia
    if ($alumno_data['id_especialidad'] == $materia_data['id_especialidad']) {
        return ['compatible' => true, 'razon' => ''];
    }
    
    // Si no son compatibles, retornar razón
    return [
        'compatible' => false,
        'razon' => "El alumno tiene especialidad '{$alumno_data['especialidad_nombre']}' pero la materia es de especialidad '{$materia_data['especialidad_nombre']}'"
    ];
}

// Función para verificar si el alumno ya APROBÓ la materia
function verificarMateriaAprobada($conexion, $id_alumno, $id_materia) {
    $sql_aprobada = "SELECT 1 FROM materia_cursada 
                     WHERE id_alumno = ? AND id_materia = ? AND aprobado = 1";
    $stmt = $conexion->prepare($sql_aprobada);
    $stmt->bind_param("ii", $id_alumno, $id_materia);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Función para verificar si el alumno ya está cursando la materia en CUALQUIER clase activa
function verificarMateriaEnCurso($conexion, $id_alumno, $id_materia, $id_clase_actual = null) {
    $sql = "SELECT 1 FROM asignacion a 
            INNER JOIN clase c ON a.id_clase = c.id_clase 
            WHERE a.id_alumno = ? AND c.id_materia = ? AND c.activo = 1";
    
    $params = [$id_alumno, $id_materia];
    
    if ($id_clase_actual) {
        $sql .= " AND c.id_clase != ?";
        $params[] = $id_clase_actual;
    }
    
    $stmt = $conexion->prepare($sql);
    
    if ($id_clase_actual) {
        $stmt->bind_param("iii", ...$params);
    } else {
        $stmt->bind_param("ii", ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Función para determinar siguiente oportunidad
function obtenerSiguienteOportunidad($ultima_oportunidad, $nivel = 0) {
    if (empty($ultima_oportunidad) || $ultima_oportunidad === 'null' || $ultima_oportunidad === '') {
        return 'Ordinario';
    }
    
    $ultima = strtolower(trim((string)$ultima_oportunidad));
    
    if ($nivel > 10) {
        return 'Global';
    }
    
    $oportunidades = [
        'ordinario' => 'Recurse',
        'recurse' => 'Especial', 
        'recursamiento' => 'Especial',
        'especial' => 'Global',
        'global' => 'Global'
    ];
    
    $siguiente = $oportunidades[$ultima] ?? 'Ordinario';
    
    if ($siguiente === $ultima_oportunidad) {
        return $siguiente;
    }
    
    return obtenerSiguienteOportunidad($siguiente, $nivel + 1);
}

function obtenerSiguienteOportunidadAlumno($conexion, $id_alumno, $id_materia) {
    $sql_ultima = "SELECT oportunidad FROM materia_cursada 
                   WHERE id_alumno = ? AND id_materia = ? 
                   ORDER BY id_materia_cursada DESC LIMIT 1";
    $stmt = $conexion->prepare($sql_ultima);
    $stmt->bind_param("ii", $id_alumno, $id_materia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ultima_oportunidad = null;
    if ($result->num_rows > 0) {
        $ultima_oportunidad = $result->fetch_assoc()['oportunidad'];
    }
    
    return obtenerSiguienteOportunidad($ultima_oportunidad);
}

/* ---------------------------------------------------------
   PROCESAMIENTO PRINCIPAL
--------------------------------------------------------- */

// Obtener información del grupo
$sql_grupo = "SELECT * FROM grupo WHERE id_grupo = ?";
$stmt_grupo = $conexion->prepare($sql_grupo);
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$grupo = $stmt_grupo->get_result()->fetch_assoc();

if (!$grupo) {
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=Grupo no encontrado");
    exit;
}

// Obtener alumnos del grupo
$sql_alumnos = "
    SELECT a.id_alumno, u.clave, u.nombre, u.apellidos 
    FROM alumno_grupo ag 
    INNER JOIN alumno a ON ag.id_alumno = a.id_alumno 
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
    WHERE ag.id_grupo = ? AND ag.activo = 1";
$stmt_alumnos = $conexion->prepare($sql_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$alumnos = $stmt_alumnos->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($alumnos)) {
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=No hay alumnos en el grupo");
    exit;
}

$resultados = [
    'alumnos_agregados' => [],
    'alumnos_fallidos' => [],
    'clases_asignadas' => [],
    'errores_globales' => []
];

// Iniciar transacción
$conexion->begin_transaction();

try {
    foreach ($clases_seleccionadas as $id_clase) {
        $id_clase = intval($id_clase);
        
        // Obtener información de la clase
        $sql_clase = "
            SELECT c.*, m.nombre as materia_nombre, m.id_especialidad, m.id_materia
            FROM clase c 
            INNER JOIN materia m ON c.id_materia = m.id_materia 
            WHERE c.id_clase = ?";
        $stmt_clase = $conexion->prepare($sql_clase);
        $stmt_clase->bind_param("i", $id_clase);
        $stmt_clase->execute();
        $clase = $stmt_clase->get_result()->fetch_assoc();
        
        if (!$clase) {
            $resultados['errores_globales'][] = "Clase ID $id_clase no encontrada";
            continue;
        }
        
        $resultados['clases_asignadas'][] = [
            'id_clase' => $id_clase,
            'materia' => $clase['materia_nombre'],
            'alumnos_agregados' => 0,
            'alumnos_fallidos' => 0
        ];
        
        $indice_clase = count($resultados['clases_asignadas']) - 1;
        
        foreach ($alumnos as $alumno) {
            // 1. Verificar si el alumno ya está en ESTA MISMA clase
            $sql_verificar = "SELECT 1 FROM asignacion WHERE id_clase = ? AND id_alumno = ?";
            $stmt_verificar = $conexion->prepare($sql_verificar);
            $stmt_verificar->bind_param("ii", $id_clase, $alumno['id_alumno']);
            $stmt_verificar->execute();
            
            if ($stmt_verificar->get_result()->num_rows > 0) {
                // Ya está asignado a esta clase específica
                $resultados['clases_asignadas'][$indice_clase]['alumnos_fallidos']++;
                
                if (!isset($resultados['alumnos_fallidos'][$alumno['id_alumno']])) {
                    $resultados['alumnos_fallidos'][$alumno['id_alumno']] = [
                        'alumno' => $alumno,
                        'errores' => []
                    ];
                }
                
                $resultados['alumnos_fallidos'][$alumno['id_alumno']]['errores'][$clase['materia_nombre']] = "El alumno ya está asignado a esta clase";
                continue;
            }
            
            // 2. Verificar si el alumno ya está cursando la materia en OTRA clase
            if (verificarMateriaEnCurso($conexion, $alumno['id_alumno'], $clase['id_materia'], $id_clase)) {
                $resultados['clases_asignadas'][$indice_clase]['alumnos_fallidos']++;
                
                if (!isset($resultados['alumnos_fallidos'][$alumno['id_alumno']])) {
                    $resultados['alumnos_fallidos'][$alumno['id_alumno']] = [
                        'alumno' => $alumno,
                        'errores' => []
                    ];
                }
                
                $resultados['alumnos_fallidos'][$alumno['id_alumno']]['errores'][$clase['materia_nombre']] = "El alumno ya está cursando esta materia en otra clase";
                continue;
            }
            
            // 3. Verificar si el alumno ya APROBÓ la materia
            if (verificarMateriaAprobada($conexion, $alumno['id_alumno'], $clase['id_materia'])) {
                $resultados['clases_asignadas'][$indice_clase]['alumnos_fallidos']++;
                
                if (!isset($resultados['alumnos_fallidos'][$alumno['id_alumno']])) {
                    $resultados['alumnos_fallidos'][$alumno['id_alumno']] = [
                        'alumno' => $alumno,
                        'errores' => []
                    ];
                }
                
                $resultados['alumnos_fallidos'][$alumno['id_alumno']]['errores'][$clase['materia_nombre']] = "El alumno ya aprobó esta materia";
                continue;
            }
            
            // 4. Verificar prerrequisitos (solo si no ha aprobado la materia)
            $prerrequisitos_pendientes = verificarCadenaPrerrequisitos($conexion, $alumno['id_alumno'], $clase['id_materia']);
            
            // 5. Verificar especialidad
            $validacion_especialidad = verificarEspecialidadAlumno($conexion, $alumno['id_alumno'], $clase['id_materia']);
            
            // 6. Verificar capacidad de la clase
            $sql_capacidad = "SELECT COUNT(*) as total FROM asignacion WHERE id_clase = ?";
            $stmt_capacidad = $conexion->prepare($sql_capacidad);
            $stmt_capacidad->bind_param("i", $id_clase);
            $stmt_capacidad->execute();
            $capacidad_actual = $stmt_capacidad->get_result()->fetch_assoc()['total'];
            
            $capacidad_excedida = ($capacidad_actual >= $clase['capacidad']);
            
            if (empty($prerrequisitos_pendientes) && $validacion_especialidad['compatible'] && !$capacidad_excedida) {
                // Determinar oportunidad
                $oportunidad = obtenerSiguienteOportunidadAlumno($conexion, $alumno['id_alumno'], $clase['id_materia']);
                
                // Insertar asignación
                $sql_insert = "INSERT INTO asignacion (id_clase, id_alumno, oportunidad, semestre) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conexion->prepare($sql_insert);
                $stmt_insert->bind_param("iisi", $id_clase, $alumno['id_alumno'], $oportunidad, $grupo['semestre']);
                
                if ($stmt_insert->execute()) {
                    $resultados['clases_asignadas'][$indice_clase]['alumnos_agregados']++;
                    
                    if (!isset($resultados['alumnos_agregados'][$alumno['id_alumno']])) {
                        $resultados['alumnos_agregados'][$alumno['id_alumno']] = [
                            'alumno' => $alumno,
                            'clases_exitosas' => []
                        ];
                    }
                    $resultados['alumnos_agregados'][$alumno['id_alumno']]['clases_exitosas'][] = $clase['materia_nombre'];
                } else {
                    // Error al insertar
                    $resultados['clases_asignadas'][$indice_clase]['alumnos_fallidos']++;
                    
                    if (!isset($resultados['alumnos_fallidos'][$alumno['id_alumno']])) {
                        $resultados['alumnos_fallidos'][$alumno['id_alumno']] = [
                            'alumno' => $alumno,
                            'errores' => []
                        ];
                    }
                    $resultados['alumnos_fallidos'][$alumno['id_alumno']]['errores'][$clase['materia_nombre']] = "Error al insertar en la base de datos";
                }
            } else {
                $resultados['clases_asignadas'][$indice_clase]['alumnos_fallidos']++;
                
                if (!isset($resultados['alumnos_fallidos'][$alumno['id_alumno']])) {
                    $resultados['alumnos_fallidos'][$alumno['id_alumno']] = [
                        'alumno' => $alumno,
                        'errores' => []
                    ];
                }
                
                $errores = [];
                if (!empty($prerrequisitos_pendientes)) {
                    $errores[] = "Prerrequisitos pendientes: " . implode(', ', $prerrequisitos_pendientes);
                }
                if (!$validacion_especialidad['compatible']) {
                    $errores[] = $validacion_especialidad['razon'];
                }
                if ($capacidad_excedida) {
                    $errores[] = "Capacidad de clase excedida";
                }
                
                $resultados['alumnos_fallidos'][$alumno['id_alumno']]['errores'][$clase['materia_nombre']] = implode('; ', $errores);
            }
        }
    }
    
    $conexion->commit();
    
    // Guardar resultados en sesión para el informe
    $_SESSION['resultado_asignacion'] = $resultados;
    $_SESSION['id_grupo_informe'] = $id_grupo;
    
    header("Location: ../generar_informe_asignacion.php");
    exit;
    
} catch (Exception $e) {
    $conexion->rollback();
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&error=Error en la asignación: " . $e->getMessage());
    exit;
}
?>