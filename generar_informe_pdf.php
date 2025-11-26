<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3' || !isset($_SESSION['resumen_proceso'])) {
    header("Location: ../index.php");
    exit;
}

$resumen = $_SESSION['resumen_proceso'];

require_once('../tcpdf/tcpdf.php');

class PDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'INFORME DE AVANCE DE SEMESTRE', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->SetCreator('Sistema Académico');
$pdf->SetAuthor('Coordinación');
$pdf->SetTitle('Informe Avance de Semestre');
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();

// RESUMEN GENERAL
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'RESUMEN GENERAL DEL PROCESO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

// CORRECCIÓN: Usar configuracion_verano en lugar de configuracion_avance
$resumen_texto = "Clases cerradas: " . $resumen['clases_cerradas'] . "\n" .
                 "Materias registradas en kardex: " . $resumen['materias_registradas'] . "\n" .
                 "Configuración reprobación: " . $resumen['configuracion_reprobacion'] . "\n" .
                 "Configuración periodo: " . $resumen['configuracion_verano'] . "\n";

// CORRECCIÓN: Usar configuracion_verano en lugar de configuracion_avance
if ($resumen['configuracion_verano'] == 'Verano inactivo') {
    $resumen_texto .= "Alumnos que avanzaron semestre: " . $resumen['alumnos_procesados'] . "\n";
} else {
    $resumen_texto .= "Alumnos procesados (sin avance - verano): " . $resumen['alumnos_sin_avance'] . "\n";
}

$resumen_texto .= "Calificación aprobatoria: " . $resumen['calificacion_aprobatoria'] . "/100\n";

$pdf->MultiCell(0, 8, $resumen_texto, 0, 'L');
$pdf->Ln(5);

// ALUMNOS REPROBADOS (NUEVA SECCIÓN)
if (!empty($resumen['alumnos_reprobados'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ALUMNOS REPROBADOS', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $count = 0;
    foreach($resumen['alumnos_reprobados'] as $reprobado) {
        if ($count < 15) { // Mostrar máximo 15 en el resumen
            $pdf->Cell(0, 6, '• ' . $reprobado, 0, 1);
            $count++;
        }
    }
    if (count($resumen['alumnos_reprobados']) > 15) {
        $pdf->Cell(0, 6, '... y ' . (count($resumen['alumnos_reprobados']) - 15) . ' más', 0, 1);
    }
    $pdf->Ln(5);
}

// DETALLES DE ALUMNOS
if (!empty($resumen['detalles_alumnos'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DETALLES POR ALUMNO', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    
    // Cabecera de la tabla
    $header = array('Alumno', 'Clave', 'Carrera', 'Sem', 'Materia', 'Promedio', 'Estado', 'Oportunidad');
    $w = array(40, 25, 35, 15, 45, 20, 20, 25);
    
    // Cabecera
    for($i=0; $i<count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Datos
    foreach($resumen['detalles_alumnos'] as $alumno) {
        // Verificar si está reprobado para resaltar
        if ($alumno['aprobado'] == 0) {
            $pdf->SetFillColor(255, 200, 200); // Fondo rojo claro para reprobados
        } else {
            $pdf->SetFillColor(255, 255, 255); // Fondo blanco para aprobados
        }
        
        $pdf->Cell($w[0], 6, substr($alumno['nombre'], 0, 25), 1, 0, 'L', true);
        $pdf->Cell($w[1], 6, $alumno['clave'], 1, 0, 'C', true);
        $pdf->Cell($w[2], 6, substr($alumno['carrera'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell($w[3], 6, $alumno['semestre'], 1, 0, 'C', true);
        $pdf->Cell($w[4], 6, substr($alumno['materia'], 0, 25), 1, 0, 'L', true);
        $pdf->Cell($w[5], 6, $alumno['promedio'], 1, 0, 'C', true);
        $pdf->Cell($w[6], 6, $alumno['aprobado'] ? 'Aprobado' : 'Reprobado', 1, 0, 'C', true);
        $pdf->Cell($w[7], 6, ucfirst($alumno['oportunidad']), 1, 0, 'C', true);
        $pdf->Ln();
        
        // Si reprobó por unidad, mostrar detalles
        if (!empty($alumno['unidades_reprobadas'])) {
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->Cell(0, 4, '   Unidades reprobadas: ' . implode(', ', $alumno['unidades_reprobadas']), 0, 1);
            $pdf->SetFont('helvetica', '', 8);
        }
        
        // Si no tiene calificaciones o todas son cero
        if (isset($alumno['sin_calificaciones']) && $alumno['sin_calificaciones']) {
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->Cell(0, 4, '   Sin calificaciones registradas', 0, 1);
            $pdf->SetFont('helvetica', '', 8);
        } elseif (isset($alumno['todas_cero']) && $alumno['todas_cero']) {
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->Cell(0, 4, '   Todas las calificaciones son 0', 0, 1);
            $pdf->SetFont('helvetica', '', 8);
        }
    }
    $pdf->Ln(10);
}

// ALUMNOS EGRESADOS
if (!empty($resumen['alumnos_egresados'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ALUMNOS EGRESADOS', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    foreach($resumen['alumnos_egresados'] as $egresado) {
        $pdf->Cell(0, 6, '• ' . $egresado, 0, 1);
    }
    $pdf->Ln(5);
}

// ALUMNOS SEMESTRE 12 SIN CRÉDITOS
if (!empty($resumen['alumnos_semestre_12'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ALUMNOS EN SEMESTRE 12 (SIN COMPLETAR MATERIAS OBLIGATORIAS)', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    foreach($resumen['alumnos_semestre_12'] as $alumno) {
        $pdf->Cell(0, 6, '• ' . $alumno, 0, 1);
    }
    $pdf->Ln(5);
}

// ALUMNOS REPROBADOS EN ESPECIAL
if (!empty($resumen['alumnos_reprobados_especial'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ALUMNOS DADOS DE BAJA POR REPROBAR EN "ESPECIAL"', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    foreach($resumen['alumnos_reprobados_especial'] as $alumno) {
        $pdf->Cell(0, 6, '• ' . $alumno, 0, 1);
    }
}

// ESTADÍSTICAS FINALES
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'ESTADÍSTICAS FINALES', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 12);
$total_alumnos_reprobados = count($resumen['alumnos_reprobados'] ?? []);
$total_materias = $resumen['materias_registradas'];
$tasa_reprobacion = $total_materias > 0 ? round(($total_alumnos_reprobados / $total_materias) * 100, 2) : 0;

$estadisticas = "Total de materias procesadas: " . $total_materias . "\n" .
                "Total de alumnos reprobados: " . $total_alumnos_reprobados . "\n" .
                "Tasa de reprobación: " . $tasa_reprobacion . "%\n" .
                "Alumnos egresados: " . count($resumen['alumnos_egresados'] ?? []) . "\n" .
                "Alumnos dados de baja: " . count($resumen['alumnos_reprobados_especial'] ?? []) . "\n" .
                "Alumnos en semestre 12 sin completar: " . count($resumen['alumnos_semestre_12'] ?? []) . "\n";

$pdf->MultiCell(0, 10, $estadisticas, 0, 'L');

$pdf->Output('informe_avance_semestre_' . date('Y-m-d') . '.pdf', 'I');

// Limpiar sesión después de generar el PDF
unset($_SESSION['resumen_proceso']);
?>