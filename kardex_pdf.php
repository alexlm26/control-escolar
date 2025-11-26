<?php
ob_start();
session_start();
include "conexion.php";

if($_SESSION['rol'] != '1'){
    header("Location: index.php");
    exit;
}

require_once('tcpdf/tcpdf.php'); // Asegúrate de que la ruta sea correcta

// Obtener id_alumno y id_carrera del usuario
$id_usuario = $_SESSION['id_usuario'];
$res_alumno = $conexion->query("
    SELECT a.id_alumno, u.id_carrera
    FROM alumno a
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE a.id_usuario = $id_usuario
");
if($res_alumno->num_rows == 0){
    die("Alumno no encontrado");
}
$alumno = $res_alumno->fetch_assoc();
$id_alumno = $alumno['id_alumno'];
$id_carrera = $alumno['id_carrera'];

// Obtener materias cursadas junto con profesor
$sql = "
SELECT 
    m.nombre AS materia,
    m.creditos,
    CONCAT(u.nombre,' ',u.apellidos) AS maestro,
    mc.cal_final,
    mc.oportunidad,
    mc.periodo,
    mc.aprobado
FROM materia_cursada mc
INNER JOIN materia m ON mc.id_materia = m.id_materia
INNER JOIN clase c ON mc.id_clase = c.id_clase
INNER JOIN profesor p ON c.id_profesor = p.id_profesor
INNER JOIN usuario u ON p.id_usuario = u.id_usuario
WHERE mc.id_alumno = $id_alumno
ORDER BY mc.periodo ASC
";

$result = $conexion->query($sql);

// Créditos totales de la carrera
$res_carrera = $conexion->query("SELECT creditos FROM carrera WHERE id_carrera = $id_carrera");
$row_carrera = $res_carrera->fetch_assoc();
$creditos_totales = $row_carrera['creditos'] ?? 0;

// Crear objeto TCPDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SICENET');
$pdf->SetTitle('Kardex');
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

// Título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'KARDEX DEL ALUMNO', 0, 1, 'C');
$pdf->Ln(5);

// Tabla
$pdf->SetFont('helvetica', '', 8);

$html = '<table border="1" cellpadding="4">
<tr style="background-color:#2e7d32;color:white;">
<th>MATERIA</th>
<th>MAESTRO</th>
<th>CALIFICACIÓN</th>
<th>OPORTUNIDAD</th>
<th>PERIODO</th>
<th>APROBADO</th>
<th>CREDITOS</th>
</tr>';

$total_creditos = 0;
$cal_total = 0;
$materias_count = 0;

if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $final = $row['cal_final'];
        $aprobado = $row['aprobado'] ? 'Sí' : 'No';

        $html .= '<tr>
        <td>'.$row['materia'].'</td>
        <td>'.$row['maestro'].'</td>
        <td>'.$final.'</td>
        <td>'.$row['oportunidad'].'</td>
        <td>'.$row['periodo'].'</td>
        <td>'.$aprobado.'</td>
        <td>'.$row['creditos'].'</td>
        </tr>';
        if($aprobado == 'Sí')
        {
             $total_creditos += $row['creditos'];
        }
        $cal_total += $final;
        $materias_count++;
    }

    $promedio = $materias_count > 0 ? round($cal_total / $materias_count,2) : 0;
    $porcentaje = $creditos_totales > 0 ? round(($total_creditos / $creditos_totales) * 100,2) : 0;

    $html .= '<tr style="font-weight:bold;">
    <td colspan="6" align="right">Total Créditos</td>
    <td>'.$total_creditos.'/260</td>
    </tr>';

    $html .= '<tr style="font-weight:bold;">
    <td colspan="6" align="right">Promedio / % Créditos</td>
    <td>'.$promedio.' / '.$porcentaje.'%</td>
    </tr>';

}else{
    $html .= '<tr><td colspan="7" align="center">No hay materias cursadas</td></tr>';
}

$html .= '</table>';

// Generar tabla
$pdf->writeHTML($html, true, false, true, false, '');

// Salida PDF
$pdf->Output('kardex.pdf', 'I');
