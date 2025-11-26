<?php
// actualizar_clases.php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener información básica del alumno
$sql_alumno = "SELECT a.id_alumno, a.semestre, a.id_especialidad, c.id_carrera 
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
    exit();
}

// Obtener historial de reprobadas
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

// CORRECCIÓN: Solo excluir materias aprobadas y actualmente inscritas
// PERO permitir materias reprobadas (para repetición/especial)
$materias_no_permitidas = array_merge($materias_aprobadas, $materias_inscritas);

// Obtener clases disponibles con verificación de prerrequisitos
$sql_clases = "SELECT c.id_clase, m.nombre as materia, m.creditos, m.semestre_sugerido, 
                      m.id_materia, c.capacidad, c.asignado, c.grupo,
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

// CORRECCIÓN: Solo excluir materias aprobadas, no las reprobadas
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

// ... el resto del código para generar HTML ...

// ... el resto del código para generar HTML ...


// Generar HTML para desktop
foreach ($clases_disponibles as $clase) {
    // Obtener horarios
    $sql_horarios = "SELECT dia, hora FROM horarios_clase WHERE id_clase = ? ORDER BY dia, hora";
    $stmt_horarios = $conexion->prepare($sql_horarios);
    $stmt_horarios->bind_param("i", $clase['id_clase']);
    $stmt_horarios->execute();
    $result_horarios = $stmt_horarios->get_result();
    $horarios = [];
    while ($row = $result_horarios->fetch_assoc()) {
        $horarios[] = $row;
    }
    
    $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
    $horarios_por_dia = [];
    
    foreach ($horarios as $horario) {
        $dia_nombre = $dias_semana[$horario['dia'] - 1] ?? 'Desconocido';
        $hora_formateada = date('H:i', strtotime($horario['hora'] . ':00'));
        $horarios_por_dia[$dia_nombre][] = $hora_formateada;
    }
    
    // HTML para desktop - COMPACTO
    echo '<tr>';
    echo '<td><input type="checkbox" name="clases_seleccionadas[]" value="' . $clase['id_clase'] . '" data-creditos="' . $clase['creditos'] . '" onchange="validarCreditos()"></td>';
    echo '<td title="' . htmlspecialchars($clase['materia']) . '">' . substr(htmlspecialchars($clase['materia']), 0, 25) . (strlen($clase['materia']) > 25 ? '...' : '') . '</td>';
    echo '<td>' . $clase['creditos'] . '</td>';
    echo '<td>' . htmlspecialchars($clase['grupo']) . '</td>';
    echo '<td><span class="oportunidad ' . $clase['clase_oportunidad'] . '">' . $clase['oportunidad'] . '</span></td>';
    echo '<td>' . $clase['semestre_sugerido'] . '</td>';
    echo '<td class="' . (($clase['asignado'] < $clase['capacidad']) ? 'cupo' : 'lleno') . '">' . $clase['asignado'] . '/' . $clase['capacidad'] . '</td>';
    
    foreach ($dias_semana as $dia) {
        echo '<td class="horario-dia">';
        if (isset($horarios_por_dia[$dia])) {
            echo implode('<br>', $horarios_por_dia[$dia]);
        }
        echo '</td>';
    }
    
    echo '</tr>';
}

// Separador para móvil
echo '<!-- SEPARADOR MOVIL -->';

// Generar HTML para móvil - ESTRUCTURA NUEVA
foreach ($clases_disponibles as $clase) {
    // Obtener horarios para móvil
    $sql_horarios = "SELECT dia, hora FROM horarios_clase WHERE id_clase = ? ORDER BY dia, hora";
    $stmt_horarios = $conexion->prepare($sql_horarios);
    $stmt_horarios->bind_param("i", $clase['id_clase']);
    $stmt_horarios->execute();
    $result_horarios = $stmt_horarios->get_result();
    $horarios = [];
    while ($row = $result_horarios->fetch_assoc()) {
        $horarios[] = $row;
    }
    
    echo '<div class="tarjeta-clase" data-clase-id="' . $clase['id_clase'] . '">';
    
    // Nombre de la clase
    echo '<div class="tarjeta-header">';
    echo '<div class="tarjeta-materia">' . htmlspecialchars($clase['materia']) . '</div>';
    echo '<div class="cupo ' . (($clase['asignado'] < $clase['capacidad']) ? 'cupo' : 'lleno') . '">' . $clase['asignado'] . '/' . $clase['capacidad'] . '</div>';
    echo '</div>';
    
    // Línea de información
    echo '<div class="info-linea">';
    echo '<div class="info-item">';
    echo '<span class="info-label">Créditos:</span>';
    echo '<span class="info-valor">' . $clase['creditos'] . '</span>';
    echo '</div>';
    echo '<div class="info-item">';
    echo '<span class="info-label">Grupo:</span>';
    echo '<span class="info-valor">' . htmlspecialchars($clase['grupo']) . '</span>';
    echo '</div>';
    echo '<div class="info-item">';
    echo '<span class="info-label">Semestre:</span>';
    echo '<span class="info-valor">' . $clase['semestre_sugerido'] . '</span>';
    echo '</div>';
    echo '<div class="info-item">';
    echo '<span class="info-label">Oportunidad:</span>';
    echo '<span class="info-valor oportunidad ' . $clase['clase_oportunidad'] . '">' . $clase['oportunidad'] . '</span>';
    echo '</div>';
    echo '</div>';

    // Grid de horarios
    echo '<div class="horarios-grid">';
    $dias_semana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];
    
    foreach ($dias_semana as $index => $dia) {
        $dia_numero = $index + 1;
        $horarios_dia = array_filter($horarios, function($h) use ($dia_numero) {
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
    }
    echo '</div>';

    // Checkbox
    echo '<div class="checkbox-container">';
    echo '<input type="checkbox" name="clases_seleccionadas[]" value="' . $clase['id_clase'] . '" data-creditos="' . $clase['creditos'] . '" onchange="validarCreditos()">';
    echo '<label>Seleccionar</label>';
    echo '</div>';
    echo '</div>';
}