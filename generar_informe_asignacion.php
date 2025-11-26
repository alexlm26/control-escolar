<?php
session_start();
include "conexion.php";
require_once('tcpdf/tcpdf.php');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SESSION['rol'] != '3' || !isset($_SESSION['resultado_asignacion'])) {
    header("Location: coordinador.php?seccion=grupos");
    exit;
}

$resultados = $_SESSION['resultado_asignacion'];
$id_grupo = $_SESSION['id_grupo_informe'];

// Obtener información del grupo
$sql_grupo = "
    SELECT g.*, c.nombre as carrera_nombre, e.nombre as especialidad_nombre
    FROM grupo g
    LEFT JOIN carrera c ON g.id_carrera = c.id_carrera
    LEFT JOIN especialidad e ON g.id_especialidad = e.id_especialidad
    WHERE g.id_grupo = ?";
$stmt_grupo = $conexion->prepare($sql_grupo);
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$grupo = $stmt_grupo->get_result()->fetch_assoc();

// Obtener horario del grupo después de la asignación
$sql_horario = "
    SELECT DISTINCT
        m.nombre AS materia, 
        s.nombre AS salon, 
        s.edificio,
        h.dia, 
        h.hora,
        CONCAT(u.nombre, ' ', u.apellidos) AS profesor_nombre,
        c.grupo as clase_grupo
    FROM asignacion a
    JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    JOIN salon s ON c.id_salon = s.id_salon
    JOIN horarios_clase h ON c.id_clase = h.id_clase
    JOIN profesor p ON c.id_profesor = p.id_profesor
    JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE ag.id_grupo = ? AND ag.activo = 1 AND c.activo = 1
    ORDER BY h.dia, h.hora, m.nombre";

$stmt_horario = $conexion->prepare($sql_horario);
$stmt_horario->bind_param("i", $id_grupo);
$stmt_horario->execute();
$result_horario = $stmt_horario->get_result();

// Estructura para horario
$horario = [];
$horas_ocupadas = [];

$dias_semana = [
    1 => 'LUNES',
    2 => 'MARTES', 
    3 => 'MIÉRCOLES',
    4 => 'JUEVES',
    5 => 'VIERNES'
];

while($row = $result_horario->fetch_assoc()){
    $info_clase = [
        'materia' => $row['materia'],
        'salon' => $row['salon'],
        'edificio' => $row['edificio'],
        'hora' => $row['hora'],
        'profesor' => $row['profesor_nombre'],
        'grupo' => $row['clase_grupo'] ? 'Gpo ' . $row['clase_grupo'] : ''
    ];
    
    $horario[$row['dia']][$row['hora']][] = $info_clase;
    $horas_ocupadas[$row['hora']] = true;
}

// Ordenar horas
ksort($horas_ocupadas);
$horas_filtradas = array_keys($horas_ocupadas);

// Crear PDF
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema ITSUR');
$pdf->SetAuthor('ITSUR');
$pdf->SetTitle('Informe de Asignación de Clases');
$pdf->SetSubject('Resultado de Asignación');

// Configuración general
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();

// ENCABEZADO DEL INFORME
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 10, 'INFORME DE ASIGNACIÓN DE CLASES', 0, 1, 'C', 1);
$pdf->Ln(5);

// Información del grupo
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'GRUPO: ' . $grupo['nombre'], 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Carrera: ' . $grupo['carrera_nombre'], 0, 1, 'C');
if ($grupo['especialidad_nombre']) {
    $pdf->Cell(0, 6, 'Especialidad: ' . $grupo['especialidad_nombre'], 0, 1, 'C');
}
$pdf->Cell(0, 6, 'Semestre: ' . $grupo['semestre'], 0, 1, 'C');
$pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Ln(10);

// RESUMEN EJECUTIVO
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(0, 8, 'RESUMEN EJECUTIVO', 0, 1, 'L', 1);

$total_alumnos = count($resultados['alumnos_agregados']) + count($resultados['alumnos_fallidos']);
$alumnos_exitosos = count($resultados['alumnos_agregados']);
$alumnos_con_errores = count($resultados['alumnos_fallidos']);
$total_clases = count($resultados['clases_asignadas']);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, "Total de alumnos en el grupo: " . $total_alumnos, 0, 1);
$pdf->Cell(0, 6, "Alumnos asignados exitosamente: " . $alumnos_exitosos, 0, 1);
$pdf->Cell(0, 6, "Alumnos con errores en alguna clase: " . $alumnos_con_errores, 0, 1);
$pdf->Cell(0, 6, "Clases asignadas al grupo: " . $total_clases, 0, 1);
$pdf->Ln(8);

// DETALLE POR CLASE
if (!empty($resultados['clases_asignadas'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(0, 8, 'DETALLE POR CLASE ASIGNADA', 0, 1, 'L', 1);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(100, 7, 'MATERIA', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'EXITOS', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'FALLIDOS', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 9);
    foreach ($resultados['clases_asignadas'] as $clase) {
        $pdf->Cell(100, 7, $clase['materia'], 1, 0);
        $pdf->Cell(30, 7, $clase['alumnos_agregados'], 1, 0, 'C');
        $pdf->Cell(30, 7, $clase['alumnos_fallidos'], 1, 1, 'C');
    }
    $pdf->Ln(10);
}

// HORARIO DEL GRUPO
if (!empty($horas_filtradas)) {
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, 'HORARIO DEL GRUPO ' . $grupo['nombre'], 0, 1, 'C', 1);
    $pdf->Ln(5);

    // Calcular ancho de tabla
    $col_widths = [15]; // Columna de horas
    for($i = 0; $i < 5; $i++) {
        $col_widths[] = 35; // Columnas de días
    }

    $total_table_width = array_sum($col_widths);
    $page_width = $pdf->getPageWidth();
    $left_margin = $pdf->getOriginalMargins()['left'];
    $start_x = $left_margin; // Alinear a la izquierda

    // Encabezado de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetX($start_x);

    $header = ['HORA'];
    foreach($dias_semana as $dia_nombre) {
        $header[] = $dia_nombre;
    }

    $pdf->SetFillColor(200, 220, 255);
    foreach($header as $i => $col) {
        $pdf->Cell($col_widths[$i], 7, $col, 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Colores para materias
    $colors = [
        [255, 255, 200], [200, 255, 200], [255, 200, 200],
        [200, 200, 255], [255, 200, 255], [200, 255, 255]
    ];
    $materias_colores = [];
    $color_index = 0;

    // Contenido del horario
    $pdf->SetFont('helvetica', '', 7);
    foreach($horas_filtradas as $hora) {
        $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
        
        $pdf->SetX($start_x);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($col_widths[0], 20, $hora_str, 1, 0, 'C', 1);
        
        for($dia = 1; $dia <= 5; $dia++) {
            $cell_content = '';
            $fill_color = [255, 255, 255];
            
            if(isset($horario[$dia][$hora])) {
                $clases = $horario[$dia][$hora];
                
                $materia_principal = $clases[0]['materia'];
                if(!isset($materias_colores[$materia_principal])) {
                    $materias_colores[$materia_principal] = $colors[$color_index % count($colors)];
                    $color_index++;
                }
                $fill_color = $materias_colores[$materia_principal];
                
                foreach($clases as $index => $clase) {
                    if($index > 0) $cell_content .= " / ";
                    $cell_content .= $clase['materia'] . "\n";
                    $cell_content .= $clase['salon'] . " - " . $clase['edificio'];
                    if($clase['grupo']) $cell_content .= " " . $clase['grupo'];
                }
            }
            
            $pdf->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
            $pdf->MultiCell($col_widths[$dia], 20, $cell_content, 1, 'C', 1, 0);
        }
        $pdf->Ln();
    }

    // Leyenda
    if(!empty($materias_colores)) {
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 6, 'LEYENDA DE MATERIAS:', 0, 1);
        
        $pdf->SetFont('helvetica', '', 7);
        $col_count = 0;
        foreach($materias_colores as $materia => $color) {
            if($col_count % 2 == 0 && $col_count > 0) {
                $pdf->Ln(4);
            }
            
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->Cell(5, 4, '', 1, 0, 'C', 1);
            $pdf->Cell(2, 4, '', 0, 0);
            $pdf->Cell(80, 4, substr($materia, 0, 35), 0, 0);
            
            $col_count++;
        }
        $pdf->Ln(10);
    }
}

// ALUMNOS CON ERRORES (si los hay)
if (!empty($resultados['alumnos_fallidos'])) {
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(255, 200, 200);
    $pdf->Cell(0, 10, 'ALUMNOS CON ERRORES EN ASIGNACIÓN', 0, 1, 'C', 1);
    $pdf->Ln(5);

    foreach ($resultados['alumnos_fallidos'] as $alumno_data) {
        $alumno = $alumno_data['alumno'];
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 7, $alumno['clave'] . ' - ' . $alumno['nombre'] . ' ' . $alumno['apellidos'], 1, 1, 'L', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        foreach ($alumno_data['errores'] as $materia => $error) {
            $pdf->Cell(10, 6, '', 0, 0);
            $pdf->Cell(80, 6, $materia, 0, 0);
            $pdf->Cell(0, 6, $error, 0, 1);
        }
        $pdf->Ln(3);
    }
}

// ALUMNOS EXITOSOS (resumen)
if (!empty($resultados['alumnos_agregados'])) {
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(200, 255, 200);
    $pdf->Cell(0, 10, 'ALUMNOS ASIGNADOS EXITOSAMENTE', 0, 1, 'C', 1);
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(30, 7, 'MATRÍCULA', 1, 0, 'C', 1);
    $pdf->Cell(60, 7, 'NOMBRE', 1, 0, 'C', 1);
    $pdf->Cell(0, 7, 'CLASES ASIGNADAS', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($resultados['alumnos_agregados'] as $alumno_data) {
        $alumno = $alumno_data['alumno'];
        $clases_str = implode(', ', $alumno_data['clases_exitosas']);
        
        $pdf->Cell(30, 6, $alumno['clave'], 1, 0);
        $pdf->Cell(60, 6, $alumno['nombre'] . ' ' . $alumno['apellidos'], 1, 0);
        $pdf->MultiCell(0, 6, $clases_str, 1, 'L');
    }
}

// Limpiar sesión
unset($_SESSION['resultado_asignacion']);
unset($_SESSION['id_grupo_informe']);

// Generar PDF
$pdf->Output('informe_asignacion_' . $grupo['nombre'] . '_' . date('Y-m-d') . '.pdf', 'I');
?>