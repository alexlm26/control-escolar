<?php
session_start();
include "../conexion.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != '3') {
    die('Acceso denegado');
}

$id_alumno = $_GET['id_alumno'] ?? 0;

// Obtener materias actuales con calificaciones por unidad
$sql_materias_actuales = "
    SELECT m.nombre as materia, c.id_clase, m.unidades,
           (SELECT AVG(cal.calificacion) 
            FROM calificacion_clase cal 
            INNER JOIN asignacion asi ON cal.id_asignacion = asi.id_asignacion 
            WHERE asi.id_clase = c.id_clase AND asi.id_alumno = $id_alumno) as promedio_actual
    FROM asignacion a
    INNER JOIN clase c ON a.id_clase = c.id_clase
    INNER JOIN materia m ON c.id_materia = m.id_materia
    WHERE a.id_alumno = $id_alumno AND c.activo = 1
    ORDER BY m.nombre
";

$materias_actuales = $conexion->query($sql_materias_actuales);

if ($materias_actuales && $materias_actuales->num_rows > 0) {
    echo '<div class="mb-4">';
    echo '<h6>Materias Actuales:</h6>';
    
    while($materia = $materias_actuales->fetch_assoc()) {
        echo '<div class="materia-item">';
        echo '<strong>' . htmlspecialchars($materia['materia']) . '</strong>';
        
        // Obtener calificaciones por unidad
        $sql_calificaciones = "
            SELECT cal.unidad, cal.calificacion
            FROM calificacion_clase cal
            INNER JOIN asignacion asi ON cal.id_asignacion = asi.id_asignacion
            WHERE asi.id_clase = {$materia['id_clase']} AND asi.id_alumno = $id_alumno
            ORDER BY cal.unidad
        ";
        
        $calificaciones = $conexion->query($sql_calificaciones);
        
        if ($calificaciones && $calificaciones->num_rows > 0) {
            echo '<div class="mt-2">';
            echo '<table class="modal-table">';
            echo '<thead><tr><th>Unidad</th><th>Calificación</th></tr></thead>';
            echo '<tbody>';
            
            while($calif = $calificaciones->fetch_assoc()) {
                $clase_css = 'calificacion-media';
                if ($calif['calificacion'] >= 80) $clase_css = 'calificacion-alta';
                if ($calif['calificacion'] < 60) $clase_css = 'calificacion-baja';
                
                echo '<tr>';
                echo '<td>Unidad ' . $calif['unidad'] . '</td>';
                echo '<td><span class="calificacion-badge ' . $clase_css . '">' . number_format($calif['calificacion'], 1) . '</span></td>';
                echo '</tr>';
            }
            
            // Mostrar promedio actual
            $promedio = $materia['promedio_actual'] ? number_format($materia['promedio_actual'], 2) : 'N/A';
            $clase_promedio = 'calificacion-media';
            if ($materia['promedio_actual'] >= 80) $clase_promedio = 'calificacion-alta';
            if ($materia['promedio_actual'] < 60) $clase_promedio = 'calificacion-baja';
            
            echo '<tr style="background: #f8f9fa;">';
            echo '<td><strong>Promedio Actual</strong></td>';
            echo '<td><strong><span class="calificacion-badge ' . $clase_promedio . '">' . $promedio . '</span></strong></td>';
            echo '</tr>';
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="mt-1"><small class="text-muted">Sin calificaciones registradas</small></div>';
        }
        
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="alert alert-info mb-4">El alumno no está cursando materias actualmente.</div>';
}

// Obtener historial de materias cursadas
$sql_historial = "
    SELECT m.nombre as materia, mc.cal_final, mc.oportunidad, mc.aprobado, mc.periodo
    FROM materia_cursada mc
    INNER JOIN materia m ON mc.id_materia = m.id_materia
    WHERE mc.id_alumno = $id_alumno
    ORDER BY mc.periodo DESC
    LIMIT 15
";

$historial = $conexion->query($sql_historial);

if ($historial && $historial->num_rows > 0) {
    echo '<div class="mt-4">';
    echo '<h6>Historial Académico:</h6>';
    echo '<table class="modal-table">';
    echo '<thead><tr><th>Materia</th><th>Calificación</th><th>Oportunidad</th><th>Estado</th><th>Periodo</th></tr></thead>';
    echo '<tbody>';
    
    while($materia = $historial->fetch_assoc()) {
        $clase_css = $materia['aprobado'] ? 'calificacion-alta' : 'calificacion-baja';
        $estado = $materia['aprobado'] ? 'Aprobado' : 'Reprobado';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($materia['materia']) . '</td>';
        echo '<td><span class="calificacion-badge ' . $clase_css . '">' . number_format($materia['cal_final'], 1) . '</span></td>';
        echo '<td>' . htmlspecialchars(ucfirst($materia['oportunidad'])) . '</td>';
        echo '<td>' . $estado . '</td>';
        echo '<td><small>' . ($materia['periodo'] ? date('M Y', strtotime($materia['periodo'])) : 'N/A') . '</small></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// Obtener promedio global
$sql_promedio_global = "
    SELECT AVG(mc.cal_final) as promedio_global, COUNT(*) as materias_aprobadas
    FROM materia_cursada mc
    WHERE mc.id_alumno = $id_alumno AND mc.aprobado = 1
";

$promedio_result = $conexion->query($sql_promedio_global);
$promedio_data = $promedio_result->fetch_assoc();

echo '<div class="mt-4 p-3 bg-light rounded">';
echo '<h6>Resumen Académico:</h6>';
echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<strong>Promedio Global:</strong> ' . number_format($promedio_data['promedio_global'] ?? 0, 2);
echo '</div>';
echo '<div class="col-md-6">';
echo '<strong>Materias Aprobadas:</strong> ' . ($promedio_data['materias_aprobadas'] ?? 0);
echo '</div>';
echo '</div>';
echo '</div>';
?>