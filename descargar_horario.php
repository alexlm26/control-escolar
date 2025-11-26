<?php
session_start();
include "conexion.php";
require_once('tcpdf/tcpdf.php');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SESSION['rol'] != '1') exit;

// Obtener horario con manejo de clases múltiples en el mismo día
$id_usuario = $_SESSION['id_usuario'];
$queryAlumno = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario=?");
$queryAlumno->bind_param("i",$id_usuario);
$queryAlumno->execute();
$resultAlumno = $queryAlumno->get_result();
$id_alumno = $resultAlumno->fetch_assoc()['id_alumno'];

$queryHorario = $conexion->prepare("
    SELECT 
        m.nombre AS materia, 
        s.nombre AS salon, 
        s.edificio,
        h.dia, 
        h.hora,
        p.nombre AS profesor_nombre,
        p.apellidos AS profesor_apellidos,
        c.grupo
    FROM asignacion a
    JOIN clase c ON a.id_clase=c.id_clase
    JOIN materia m ON c.id_materia=m.id_materia
    JOIN salon s ON c.id_salon=s.id_salon
    JOIN horarios_clase h ON c.id_clase=h.id_clase
    JOIN profesor pr ON c.id_profesor=pr.id_profesor
    JOIN usuario p ON pr.id_usuario=p.id_usuario
    WHERE c.activo=1 AND a.id_alumno=? 
    ORDER BY h.dia, h.hora, m.nombre
");
$queryHorario->bind_param("i",$id_alumno);
$queryHorario->execute();
$resultHorario = $queryHorario->get_result();

// Estructura mejorada para manejar múltiples clases por día
$horario = [];
$horas_ocupadas = [];

$dias_semana = [
    1 => 'LUNES',
    2 => 'MARTES', 
    3 => 'MIÉRCOLES',
    4 => 'JUEVES',
    5 => 'VIERNES',
    6 => 'SÁBADO'
];

while($row = $resultHorario->fetch_assoc()){
    $hora_formateada = str_pad($row['hora'], 2, '0', STR_PAD_LEFT) . ":00";
    $info_clase = [
        'materia' => $row['materia'],
        'salon' => $row['salon'],
        'edificio' => $row['edificio'],
        'hora' => $hora_formateada,
        'profesor' => $row['profesor_nombre'] . ' ' . $row['profesor_apellidos'],
        'grupo' => $row['grupo'] ? 'Gpo ' . $row['grupo'] : ''
    ];
    
    $horario[$row['dia']][$row['hora']][] = $info_clase;
    $horas_ocupadas[$row['hora']] = true;
}

// Ordenar las horas ocupadas
ksort($horas_ocupadas);
$horas_filtradas = array_keys($horas_ocupadas);

// Crear PDF con tabla centrada
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema ITSUR');
$pdf->SetAuthor('ITSUR');
$pdf->SetTitle('Horario Escolar');
$pdf->SetSubject('Horario del Alumno');

$pdf->AddPage();

// Configurar márgenes para centrado
$page_width = $pdf->getPageWidth();
$left_margin = $pdf->getOriginalMargins()['left'];
$right_margin = $pdf->getOriginalMargins()['right'];
$usable_width = $page_width - $left_margin - $right_margin;

// Encabezado centrado
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'HORARIO ESCOLAR', 0, 1, 'C', 1);

// Información del alumno centrada
$queryAlumnoInfo = $conexion->prepare("
    SELECT u.nombre, u.apellidos, u.clave, a.semestre, c.nombre as carrera
    FROM alumno a 
    JOIN usuario u ON a.id_usuario = u.id_usuario 
    JOIN carrera c ON u.id_carrera = c.id_carrera 
    WHERE a.id_alumno = ?
");
$queryAlumnoInfo->bind_param("i", $id_alumno);
$queryAlumnoInfo->execute();
$alumnoInfo = $queryAlumnoInfo->get_result()->fetch_assoc();

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $alumnoInfo['nombre'] . ' ' . $alumnoInfo['apellidos'], 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, 'Matrícula: ' . $alumnoInfo['clave'] . ' | Semestre: ' . $alumnoInfo['semestre'] . ' | ' . $alumnoInfo['carrera'], 0, 1, 'C');
$pdf->Ln(5);

// CALCULAR ANCHO DE TABLA PARA CENTRAR
$col_widths = [12]; // Columna de horas
for($i = 0; $i < 6; $i++) {
    $col_widths[] = 30; // Columnas de días
}

$total_table_width = array_sum($col_widths);
$start_x = ($page_width - $total_table_width) / 2;

// Colores para materias
$colors = [
    [255, 255, 200], // Amarillo claro
    [200, 255, 200], // Verde claro
    [255, 200, 200], // Rojo claro
    [200, 200, 255], // Azul claro
    [255, 200, 255], // Rosa claro
    [200, 255, 255], // Cyan claro
];

$materias_colores = [];
$color_index = 0;

// DIBUJAR ENCABEZADO DE TABLA CENTRADO
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($start_x); // Posicionar en el centro

$header = ['HORA'];
foreach($dias_semana as $dia_nombre) {
    $header[] = $dia_nombre;
}

// Dibujar encabezado centrado
$pdf->SetFillColor(200, 220, 255);
foreach($header as $i => $col) {
    $pdf->Cell($col_widths[$i], 7, $col, 1, 0, 'C', 1);
}
$pdf->Ln();

// CONTENIDO DE LA TABLA CENTRADO
$pdf->SetFont('helvetica', '', 7);

foreach($horas_filtradas as $hora) {
    $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
    
    // Posicionar fila en el centro
    $pdf->SetX($start_x);
    
    // Celda de hora
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($col_widths[0], 18, $hora_str, 1, 0, 'C', 1);
    
    // Celdas para cada día
    for($dia = 1; $dia <= 6; $dia++) {
        $cell_content = '';
        $fill_color = [255, 255, 255]; // Blanco por defecto
        
        if(isset($horario[$dia][$hora])) {
            $clases = $horario[$dia][$hora];
            
            // Asignar color consistente por materia
            $materia_principal = $clases[0]['materia'];
            if(!isset($materias_colores[$materia_principal])) {
                $materias_colores[$materia_principal] = $colors[$color_index % count($colors)];
                $color_index++;
            }
            $fill_color = $materias_colores[$materia_principal];
            
            foreach($clases as $index => $clase) {
                if($index > 0) $cell_content .= " / ";
                // Texto compacto pero legible
                $cell_content .= $clase['materia'] . "\n";
                $cell_content .= $clase['salon'] . " - ".$clase['edificio'];
                if($clase['grupo']) $cell_content .= " " . $clase['grupo'];
            }
        }
        
        $pdf->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
        $pdf->MultiCell($col_widths[$dia], 18, $cell_content, 1, 'C', 1, 0);
    }
    
    $pdf->Ln();
}

// LEYENDA CENTRADA (si hay materias)
if(!empty($materias_colores)) {
    $pdf->Ln(8);
    
    // Calcular ancho de leyenda
    $leyenda_width = 150; // Ancho fijo para la leyenda
    $leyenda_start_x = ($page_width - $leyenda_width) / 2;
    
    $pdf->SetX($leyenda_start_x);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell($leyenda_width, 6, 'LEYENDA DE MATERIAS:', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 7);
    $col_count = 0;
    $max_cols = 2; // 2 columnas en la leyenda
    
    foreach($materias_colores as $materia => $color) {
        if($col_count % $max_cols == 0) {
            $pdf->SetX($leyenda_start_x);
        }
        
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Cell(5, 5, '', 1, 0, 'C', 1);
        $pdf->Cell(2, 5, '', 0, 0);
        $pdf->Cell(65, 5, substr($materia, 0, 30), 0, 0);
        
        $col_count++;
        
        // Si es la segunda columna, salto de línea
        if($col_count % $max_cols == 0) {
            $pdf->Ln(5);
        }
    }
    
    // Si el último renglón no está completo, hacer salto de línea
    if($col_count % $max_cols != 0) {
        $pdf->Ln(5);
    }
}

// Pie de página centrado

$pdf->Output('horario_' . $alumnoInfo['clave'] . '.pdf', 'I');
?>