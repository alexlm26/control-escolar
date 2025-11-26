<?php
session_start();
require_once('tcpdf/tcpdf.php');

if (!isset($_SESSION['pdf_data'])) {
    header("Location: clases.php");
    exit;
}

$data = $_SESSION['pdf_data'];
unset($_SESSION['pdf_data']);
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Crear nuevo PDF
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator('Sistema Académico');
$pdf->SetAuthor($data['clase_info']['profesor_nombre']);
$pdf->SetTitle('Calificaciones - ' . $data['clase_info']['materia_nombre']);
$pdf->SetSubject('Reporte de Calificaciones');

// Agregar página
$pdf->AddPage();

// Contenido del PDF
$html = '
<style>
    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
    .info-clase { margin-bottom: 15px; padding: 10px; background-color: #f5f5f5; border-radius: 5px; }
    .table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
    .table th { background-color: #2c3e50; color: white; padding: 6px; text-align: left; font-weight: bold; }
    .table td { padding: 6px; border-bottom: 1px solid #ddd; }
    .calificacion-final { font-weight: bold; color: #2c3e50; }
    .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    .formula { background-color: #f8f9fa; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 9px; }
</style>

<div class="header">
    <h1 style="font-size: 16px; margin: 0; color: #2c3e50;">REPORTE DE CALIFICACIONES</h1>
    <h2 style="font-size: 14px; margin: 5px 0; color: #34495e;">' . htmlspecialchars($data['clase_info']['materia_nombre']) . ' - Unidad ' . $data['unidad'] . '</h2>
</div>

<div class="info-clase">
    <table style="width: 100%; font-size: 9px;">
        <tr>
            <td><strong>Grupo:</strong> ' . $data['clase_info']['grupo'] . '</td>
            <td><strong>Periodo:</strong> ' . $data['clase_info']['periodo'] . '</td>
            <td><strong>Profesor:</strong> ' . htmlspecialchars($data['clase_info']['profesor_nombre']) . '</td>
        </tr>
        <tr>
            <td><strong>Fecha generación:</strong> ' . date('d/m/Y H:i') . '</td>
            <td><strong>Intervalo asistencia:</strong> ' . date('d/m/Y', strtotime($data['fecha_inicio'])) . ' - ' . date('d/m/Y', strtotime($data['fecha_fin'])) . '</td>
            <td><strong>Asistencia promedio:</strong> ' . $data['porcentaje_promedio_asistencia'] . '%</td>
        </tr>
    </table>
</div>

<div class="formula">
    <strong>Fórmula de calificación:</strong> Calificación Final = (Promedio Tareas × ' . (100 - $data['porcentaje_asistencia']) . '%) + (Asistencia × ' . $data['porcentaje_asistencia'] . '%)
</div>

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="25%">Alumno</th>
            <th width="15%">N° Control</th>
            <th width="15%">Asistencia</th>
            <th width="15%">Asist./Total</th>
            <th width="25%">Calificación Final</th>
        </tr>
    </thead>
    <tbody>';

$contador = 1;
foreach ($data['alumnos_clase'] as $alumno) {
    $calificacion_final = $data['calificaciones_finales'][$alumno['id_alumno']];
    $asistencia = $data['asistencias_alumnos'][$alumno['id_alumno']];
    
    $html .= '
        <tr>
            <td>' . $contador . '</td>
            <td>' . htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']) . '</td>
            <td>' . htmlspecialchars($alumno['numero_control']) . '</td>
            <td>' . $asistencia['porcentaje'] . '%</td>
            <td>' . $asistencia['asistencias'] . '/' . $asistencia['total_clases'] . '</td>
            <td class="calificacion-final">' . number_format($calificacion_final, 1) . '%</td>
        </tr>';
    $contador++;
}

$html .= '
    </tbody>
</table>

<div class="footer">
    <p><strong>Distribución de porcentajes:</strong> Tareas: ' . (100 - $data['porcentaje_asistencia']) . '% | Asistencia: ' . $data['porcentaje_asistencia'] . '%</p>
    <p>Sistema Académico - ' . date('d/m/Y H:i:s') . '</p>
</div>';

// Escribir contenido
$pdf->writeHTML($html, true, false, true, false, '');

// Salida del PDF
$nombre_archivo = 'calificaciones_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data['clase_info']['materia_nombre']) . '_unidad' . $data['unidad'] . '.pdf';
$pdf->Output($nombre_archivo, 'D');
?>