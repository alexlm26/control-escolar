<?php
session_start();
include "conexion.php";
require_once('tcpdf/tcpdf.php');
require_once('PhpSpreadsheet/IOFactory.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SESSION['rol'] != '1' && $_SESSION['rol'] != '2') {
    die("Acceso denegado");
}

// Verificar si se subió un archivo y no hay errores
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    die("Error: No se subió ningún archivo o hubo un error en la subida. Código de error: " . $_FILES['archivo_excel']['error']);
}

$archivo_tmp = $_FILES['archivo_excel']['tmp_name'];

// Verificar que el archivo temporal existe
if (!file_exists($archivo_tmp)) {
    die("Error: El archivo temporal no existe");
}

// Verificar que el archivo no esté vacío
if ($_FILES['archivo_excel']['size'] == 0) {
    die("Error: El archivo está vacío");
}

try {
    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($archivo_tmp);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Obtener todas las claves de usuario (números de control)
    $claves_alumnos = [];
    
    foreach ($worksheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        $fila = [];
        foreach ($cellIterator as $cell) {
            $fila[] = $cell->getValue();
        }
        
        // Saltar filas vacías
        if (empty($fila[0]) || trim($fila[0]) === '') {
            continue;
        }
        
        // Asumimos que la primera columna contiene las claves
        // Ignorar encabezados comunes
        $valor = trim($fila[0]);
        if ($valor != 'Clave' && $valor != 'Número de Control' && $valor != 'Matrícula') {
            $claves_alumnos[] = $valor;
        }
    }
    
    // Eliminar duplicados y valores vacíos
    $claves_alumnos = array_unique($claves_alumnos);
    $claves_alumnos = array_filter($claves_alumnos);
    
    if (empty($claves_alumnos)) {
        die("No se encontraron números de control válidos en el archivo Excel");
    }
    
    echo "Números de control encontrados: " . implode(', ', $claves_alumnos) . "<br>";
    
    // Crear un ZIP para todas las boletas
    $zip = new ZipArchive();
    $zip_filename = 'boletas_grupo_' . date('Y-m-d_H-i-s') . '.zip';
    
    if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
        die("No se pudo crear el archivo ZIP");
    }
    
    $boletas_generadas = 0;
    $boletas_fallidas = [];
    
    // Generar boleta para cada alumno
    foreach ($claves_alumnos as $clave_usuario) {
        if (generarBoletaIndividual($clave_usuario, $conexion, $zip)) {
            $boletas_generadas++;
        } else {
            $boletas_fallidas[] = $clave_usuario;
        }
    }
    
    $zip->close();
    
    // Mostrar resultados
    echo "<h3>Resultado de la generación de boletas:</h3>";
    echo "<p>Boletas generadas exitosamente: $boletas_generadas de " . count($claves_alumnos) . "</p>";
    
    if (!empty($boletas_fallidas)) {
        echo "<p>Boletas que no se pudieron generar: " . implode(', ', $boletas_fallidas) . "</p>";
        echo "<p>Estas claves no existen en la base de datos o no tienen calificaciones.</p>";
    }
    
    // Descargar el ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_filename));
    readfile($zip_filename);
    
    // Eliminar el archivo temporal
    unlink($zip_filename);
    exit;
    
} catch (Exception $e) {
    die("Error al procesar el archivo Excel: " . $e->getMessage());
}

// Función para generar boleta individual (MODIFICADA para aceptar $zip como parámetro)
function generarBoletaIndividual($clave_usuario, $conexion, $zip) {
    // Obtener ID del alumno basado en la clave
    $queryUsuario = $conexion->prepare("SELECT id_usuario FROM usuario WHERE clave = ?");
    $queryUsuario->bind_param("s", $clave_usuario);
    $queryUsuario->execute();
    $resultUsuario = $queryUsuario->get_result();
    
    if ($resultUsuario->num_rows === 0) {
        return false; // Usuario no encontrado
    }
    
    $id_usuario = $resultUsuario->fetch_assoc()['id_usuario'];
    
    // Obtener ID del alumno
    $queryAlumno = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
    $queryAlumno->bind_param("i", $id_usuario);
    $queryAlumno->execute();
    $resultAlumno = $queryAlumno->get_result();
    
    if ($resultAlumno->num_rows === 0) {
        return false; // Alumno no encontrado
    }
    
    $id_alumno = $resultAlumno->fetch_assoc()['id_alumno'];
    
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
    
    // Si no tiene materias, no generar boleta
    if (empty($calificaciones)) {
        return false;
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
    
    $ratioMateria = 0.35;
    $ratioFinal   = 0.10;
    
    $widthMateria = $usableWidth * $ratioMateria;
    $widthFinal = $usableWidth * $ratioFinal;
    $widthUnidadesTotal = $usableWidth - ($widthMateria + $widthFinal);
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
    "• Para aclaraciones, comuníquese con el Departamento Académico.\n".
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
    
    // Guardar PDF en archivo temporal
    $pdf_filename = "boleta_{$clave_usuario}.pdf";
    $pdf->Output($pdf_filename, 'F');
    
    // Agregar al ZIP
    $zip->addFile($pdf_filename, $pdf_filename);
    
    // Eliminar archivo temporal después de agregarlo al ZIP
    unlink($pdf_filename);
    
    return true;
}
?>