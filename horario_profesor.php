<?php
session_start();
include "conexion.php";
require_once('tcpdf/tcpdf.php');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SESSION['rol'] != '2') exit;

// Obtener horario del profesor con manejo de clases múltiples
$id_usuario = $_SESSION['id_usuario'];
$queryProfesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario=?");
$queryProfesor->bind_param("i",$id_usuario);
$queryProfesor->execute();
$resultProfesor = $queryProfesor->get_result();
$id_profesor = $resultProfesor->fetch_assoc()['id_profesor'];

$queryHorario = $conexion->prepare("
    SELECT 
        m.nombre AS materia, 
        s.nombre AS salon, 
        s.edificio,
        h.dia, 
        h.hora,
        c.grupo,
        COUNT(a.id_alumno) as total_alumnos,
        c.capacidad
    FROM clase c
    JOIN materia m ON c.id_materia = m.id_materia
    JOIN salon s ON c.id_salon = s.id_salon
    JOIN horarios_clase h ON c.id_clase = h.id_clase
    LEFT JOIN asignacion a ON c.id_clase = a.id_clase
    WHERE c.activo = 1 AND c.id_profesor = ? 
    GROUP BY c.id_clase, h.dia, h.hora
    ORDER BY h.dia, h.hora, m.nombre
");
$queryHorario->bind_param("i", $id_profesor);
$queryHorario->execute();
$resultHorario = $queryHorario->get_result();

// Estructura para manejar múltiples clases por día
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
        'grupo' => $row['grupo'] ? 'Gpo ' . $row['grupo'] : '',
        'alumnos' => $row['total_alumnos'] . '/' . $row['capacidad']
    ];
    
    $horario[$row['dia']][$row['hora']][] = $info_clase;
    $horas_ocupadas[$row['hora']] = true;
}

// Ordenar las horas ocupadas
ksort($horas_ocupadas);
$horas_filtradas = array_keys($horas_ocupadas);

// Crear PDF con tabla centrada para profesor
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema ITSUR');
$pdf->SetAuthor('ITSUR');
$pdf->SetTitle('Horario del Profesor');
$pdf->SetSubject('Horario del Profesor');

$pdf->AddPage();

// Configurar márgenes para centrado
$page_width = $pdf->getPageWidth();
$left_margin = $pdf->getOriginalMargins()['left'];
$right_margin = $pdf->getOriginalMargins()['right'];

// Encabezado centrado para profesor
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'HORARIO DEL PROFESOR', 0, 1, 'C', 1);

// Información del profesor centrada
$queryProfesorInfo = $conexion->prepare("
    SELECT u.nombre, u.apellidos, u.clave, p.sueldo, 
           co.nombre as coordinador_nombre, co.apellidos as coordinador_apellidos
    FROM profesor p 
    JOIN usuario u ON p.id_usuario = u.id_usuario 
    JOIN coordinador c ON p.id_coordinador = c.id_coordinador
    JOIN usuario co ON c.id_usuario = co.id_usuario
    WHERE p.id_profesor = ?
");
$queryProfesorInfo->bind_param("i", $id_profesor);
$queryProfesorInfo->execute();
$profesorInfo = $queryProfesorInfo->get_result()->fetch_assoc();

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Prof. ' . $profesorInfo['nombre'] . ' ' . $profesorInfo['apellidos'], 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, 'Clave: ' . $profesorInfo['clave'] . ' | Coordinador: ' . $profesorInfo['coordinador_nombre'] . ' ' . $profesorInfo['coordinador_apellidos'], 0, 1, 'C');
$pdf->Ln(5);

// CALCULAR ANCHO DE TABLA PARA CENTRAR - AJUSTADO
$col_widths = [14]; // Columna de horas un poco más ancha
for($i = 0; $i < 6; $i++) {
    $col_widths[] = 30; // Columnas de días ajustadas
}

$total_table_width = array_sum($col_widths);
$start_x = ($page_width - $total_table_width) / 2;

// Colores para materias
$colors = [
    [255, 230, 230], [230, 255, 230], [230, 230, 255],
    [255, 255, 200], [255, 230, 255], [230, 255, 255],
    [240, 240, 240], [255, 220, 200],
];

$materias_colores = [];
$color_index = 0;

// DIBUJAR ENCABEZADO DE TABLA CENTRADO
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($start_x);

$header = ['HORA'];
foreach($dias_semana as $dia_nombre) {
    $header[] = $dia_nombre;
}

// Dibujar encabezado centrado
$pdf->SetFillColor(180, 200, 255);
foreach($header as $i => $col) {
    $pdf->Cell($col_widths[$i], 7, $col, 1, 0, 'C', 1);
}
$pdf->Ln();

// CONTENIDO DE LA TABLA CENTRADO - CORREGIDO
$pdf->SetFont('helvetica', '', 7);

foreach($horas_filtradas as $hora) {
    $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
    
    // Posicionar fila en el centro
    $pdf->SetX($start_x);
    
    // Celda de hora
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($col_widths[0], 18, $hora_str, 1, 0, 'C', 1);
    
    // Celdas para cada día - CORREGIDO: mantener posición X
    for($dia = 1; $dia <= 6; $dia++) {
        $current_x = $pdf->GetX(); // Guardar posición X actual
        $current_y = $pdf->GetY(); // Guardar posición Y actual
        
        $cell_content = '';
        $fill_color = [255, 255, 255];
        
        if(isset($horario[$dia][$hora])) {
            $clases = $horario[$dia][$hora];
            
            foreach($clases as $index => $clase) {
                if(!isset($materias_colores[$clase['materia']])) {
                    $materias_colores[$clase['materia']] = $colors[$color_index % count($colors)];
                    $color_index++;
                }
                $fill_color = $materias_colores[$clase['materia']];
                
                if($index > 0) $cell_content .= "\n---\n";
                
                // Texto más compacto para evitar desbordamiento
                $nombre_materia = strlen($clase['materia']) > 20 ? 
                    substr($clase['materia'], 0, 17) . '...' : $clase['materia'];
                
                $cell_content .= $nombre_materia . "\n";
                $cell_content .= $clase['salon'] . $clase['edificio'];
                if($clase['grupo']) $cell_content .= $clase['grupo'];
                $cell_content .= "\n" . $clase['alumnos'];
            }
        }
        
        $pdf->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
        
        // Dibujar celda en la posición correcta
        $pdf->SetXY($current_x, $current_y);
        $pdf->MultiCell($col_widths[$dia], 18, $cell_content, 1, 'C', 1);
        
        // Mover a la siguiente posición X
        $pdf->SetXY($current_x + $col_widths[$dia], $current_y);
    }
    
    $pdf->Ln(); // Solo un salto de línea al final de la fila
}

// LEYENDA CENTRADA - CORREGIDA PARA CABER EN UNA PÁGINA
if(!empty($materias_colores) && $pdf->GetY() < 180) { // Solo mostrar si hay espacio
    $pdf->Ln(8);
    
    $leyenda_width = 150; // Ancho reducido
    $leyenda_start_x = ($page_width - $leyenda_width) / 2;
    
    $pdf->SetX($leyenda_start_x);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell($leyenda_width, 6, 'LEYENDA DE MATERIAS:', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 6); // Fuente más pequeña
    
    $col_count = 0;
    $max_cols = 3; // Tres columnas para ahorrar espacio
    
    foreach($materias_colores as $materia => $color) {
        if($col_count % $max_cols == 0) {
            $pdf->SetX($leyenda_start_x);
            if($col_count > 0) {
                $pdf->Ln(4); // Espacio entre filas
            }
        }
        
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Cell(4, 4, '', 1, 0, 'C', 1);
        $pdf->Cell(2, 4, '', 0, 0);
        
        // Texto truncado si es muy largo
        $texto_leyenda = strlen($materia) > 25 ? substr($materia, 0, 22) . '...' : $materia;
        $pdf->Cell(40, 4, $texto_leyenda, 0, 0);
        
        $col_count++;
    }
    
    // Salto de línea final si no completó la última fila
    if($col_count % $max_cols != 0) {
        $pdf->Ln(4);
    }
}

$pdf->Output('horario_profesor_' . $profesorInfo['clave'] . '.pdf', 'I');
?>