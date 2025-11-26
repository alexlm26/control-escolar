<?php
session_start();
include "conexion.php";
require_once('tcpdf/tcpdf.php');

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SESSION['rol'] != '1') exit;

$id_usuario = $_SESSION['id_usuario'];
$queryAlumno = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario=?");
$queryAlumno->bind_param("i", $id_usuario);
$queryAlumno->execute();
$id_alumno = $queryAlumno->get_result()->fetch_assoc()['id_alumno'];

/* ---------------------------------------------------------
   OBTENER MATERIAS Y UNIDADES
--------------------------------------------------------- */
$queryMaterias = $conexion->prepare("
    SELECT DISTINCT m.id_materia, m.nombre, m.unidades
    FROM asignacion a
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    WHERE c.activo = 1 AND a.id_alumno = ?
");
$queryMaterias->bind_param("i", $id_alumno);
$queryMaterias->execute();
$resultMaterias = $queryMaterias->get_result();

$calificaciones = [];
$maxUnidadesGlobal = 0;

while ($row = $resultMaterias->fetch_assoc()) {
    $materia = $row['nombre'];
    $unidades = $row['unidades'];

    for ($i = 1; $i <= $unidades; $i++) $calificaciones[$materia][$i] = 0;
    $calificaciones[$materia]['final'] = 0;

    if ($unidades > $maxUnidadesGlobal) $maxUnidadesGlobal = $unidades;
}

/* ---------------------------------------------------------
   OBTENER CALIFICACIONES
--------------------------------------------------------- */
$queryCalif = $conexion->prepare("
    SELECT m.nombre AS materia, ccl.unidad, ccl.calificacion
    FROM calificacion_clase ccl
    JOIN asignacion a ON ccl.id_asignacion = a.id_asignacion
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    WHERE c.activo = 1 AND a.id_alumno = ?
");
$queryCalif->bind_param("i", $id_alumno);
$queryCalif->execute();
$resultCalif = $queryCalif->get_result();

while ($row = $resultCalif->fetch_assoc()) {
    $materia = $row['materia'];
    $unidad = $row['unidad'];
    $calificaciones[$materia][$unidad] = $row['calificacion'];
}

/* ---------------------------------------------------------
   CALCULAR PROMEDIO FINAL
--------------------------------------------------------- */
foreach ($calificaciones as $materia => &$unis) {
    $suma = 0;
    $cont = 0;
    foreach ($unis as $key => $val) {
        if ($key !== 'final') {
            $suma += $val;
            $cont++;
        }
    }
    $unis['final'] = $cont > 0 ? round($suma / $cont, 2) : 0;
}
unset($unis);

$clave_usuario = $_SESSION['clave'];

/* ---------------------------------------------------------
   GENERAR PDF
--------------------------------------------------------- */
$pdf = new TCPDF('P', 'mm', 'Letter', true, 'UTF-8', false);
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

/* ---------------------------------------------------------
   ENCABEZADO PROFESIONAL
--------------------------------------------------------- */
$logo = "logo.png";

if (file_exists($logo)) {
    $pdf->Image($logo, 15, 10, 22);
}

$pdf->SetFont('helvetica', 'B', 15);
$pdf->Cell(0, 7, "INSTITUCIÓN EDUCATIVA", 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 6, "Boleta Oficial de Calificaciones", 0, 1, 'C');
$pdf->Ln(6);

/* ---------------------------------------------------------
   DATOS DEL ALUMNO
--------------------------------------------------------- */
$pdf->SetFont('helvetica', '', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 7, "Alumno: $clave_usuario", 0, 1, 'L', true);
$pdf->Ln(3);

/* ---------------------------------------------------------
   CALCULAR ANCHOS DINÁMICOS 100% EXACTOS
--------------------------------------------------------- */

$pageWidth = $pdf->getPageWidth();
$margins = $pdf->getMargins();
$usableWidth = $pageWidth - $margins['left'] - $margins['right'];

/* proporciones internas
   Puedes cambiarlas si quieres:
   - ratioMateria => qué parte de la tabla ocupa la columna MATERIA
   - ratioFinal   => qué parte ocupa FINAL
*/
$ratioMateria = 0.35;
$ratioFinal   = 0.10;

// ancho columna materia
$widthMateria = $usableWidth * $ratioMateria;
// ancho final
$widthFinal = $usableWidth * $ratioFinal;

// lo que queda para unidades
$widthUnidadesTotal = $usableWidth - ($widthMateria + $widthFinal);

// ancho por unidad
$widthUnidad = $widthUnidadesTotal / $maxUnidadesGlobal * 4.3;

/* ---------------------------------------------------------
   TABLA HTML
--------------------------------------------------------- */
$tbl = '
<style>
    table {
        border-collapse: collapse;
        font-size: 9px;
    }
    th {
        background-color: #003366;
        color: white;
        text-align: center;
        font-weight: bold;
    }
    td {
        text-align: center;
        padding: 3px;
    }
    .materia {
        text-align: left;
        font-weight: bold;
        background-color: #f3f3f3;
    }
</style>

<table border="1" cellpadding="3">
<tr>
    <th width="'.$widthMateria.'">MATERIA</th>';

for ($i = 1; $i <= $maxUnidadesGlobal; $i++) {
    $tbl .= '<th width="'.$widthUnidad.'">U'.$i.'</th>';
}

$tbl .= '<th width="'.$widthFinal.'">FINAL</th></tr>';

foreach ($calificaciones as $materia => $unis) {

    $tbl .= '<tr>';
    $tbl .= '<td class="materia" width="'.$widthMateria.'">'.$materia.'</td>';

    for ($i = 1; $i <= $maxUnidadesGlobal; $i++) {
        $tbl .= '<td width="'.$widthUnidad.'">'.($unis[$i] ?? 0).'</td>';
    }

    $color = $unis["final"] >= 6 ? "#006600" : "#cc0000";
    $tbl .= '<td width="'.$widthFinal.'" style="color:'.$color.'">'.$unis["final"].'</td>';

    $tbl .= '</tr>';
}

$tbl .= '</table>';

$pdf->writeHTML($tbl, true, false, false, false, '');

/* ---------------------------------------------------------
   NOTAS PEQUEÑAS
--------------------------------------------------------- */

$pdf->Ln(5);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->MultiCell(0, 5,
"• Esta boleta es un documento informativo emitido por el Sistema Escolar.\n".
"• Las calificaciones finales están sujetas a validación institucional.\n".
"• Para aclaraciones, comuníquese con el Departamento Académico.\n", 
"• La alteración de este documento incurre en un delito.",
0, 'L', false);

$pdf->Ln(6);

/* ---------------------------------------------------------
   FIRMA
--------------------------------------------------------- */
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, "_____________________________________", 0, 1, 'C');
$pdf->Cell(0, 5, "Firma de Enterado del Tutor", 0, 1, 'C');

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 6, 'Documento generado automáticamente - Sistema Escolar', 0, 1, 'C');

$pdf->Output('calificaciones.pdf', 'I');
?>
