<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

// Obtener información del usuario
$id_usuario = $_SESSION['id_usuario'];
$es_coordinador_general = false;

if ($_SESSION['rol'] == 3) { // Coordinador
    $queryCoordinador = $conexion->prepare("
        SELECT id_carrera 
        FROM coordinador 
        WHERE id_usuario = ?
    ");
    $queryCoordinador->bind_param("i", $id_usuario);
    $queryCoordinador->execute();
    $resultCoordinador = $queryCoordinador->get_result();
    
    if ($resultCoordinador->num_rows > 0) {
        $coordinador_data = $resultCoordinador->fetch_assoc();
        $id_carrera_coordinador = $coordinador_data['id_carrera'];
        $es_coordinador_general = ($id_carrera_coordinador == 0);
    }
}

// Obtener clases disponibles según el rol
$clases_disponibles = [];
if ($_SESSION['rol'] == 3) { // Coordinador
    if ($es_coordinador_general) {
        // Coordinador general (id_carrera = 0) ve todos los grupos
        $queryClases = $conexion->prepare("
            SELECT DISTINCT c.id_clase, m.nombre AS materia, c.grupo, 
                   COUNT(a.id_alumno) as total_alumnos,
                   ca.nombre as carrera_nombre
            FROM clase c
            JOIN materia m ON c.id_materia = m.id_materia
            JOIN carrera ca ON m.id_carrera = ca.id_carrera
            LEFT JOIN asignacion a ON c.id_clase = a.id_clase
            WHERE c.activo = 1
            GROUP BY c.id_clase, m.nombre, c.grupo, ca.nombre
            ORDER BY ca.nombre, m.nombre, c.grupo
        ");
    } else {
        // Coordinador de carrera específica ve solo sus grupos
        $queryClases = $conexion->prepare("
            SELECT DISTINCT c.id_clase, m.nombre AS materia, c.grupo, 
                   COUNT(a.id_alumno) as total_alumnos,
                   ca.nombre as carrera_nombre
            FROM clase c
            JOIN materia m ON c.id_materia = m.id_materia
            JOIN carrera ca ON m.id_carrera = ca.id_carrera
            LEFT JOIN asignacion a ON c.id_clase = a.id_clase
            WHERE c.activo = 1 AND m.id_carrera = ?
            GROUP BY c.id_clase, m.nombre, c.grupo, ca.nombre
            ORDER BY m.nombre, c.grupo
        ");
        $queryClases->bind_param("i", $id_carrera_coordinador);
    }
} else { // Profesor
    $queryProfesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
    $queryProfesor->bind_param("i", $id_usuario);
    $queryProfesor->execute();
    $resultProfesor = $queryProfesor->get_result();
    $id_profesor = $resultProfesor->fetch_assoc()['id_profesor'];
    
    $queryClases = $conexion->prepare("
        SELECT DISTINCT c.id_clase, m.nombre AS materia, c.grupo, 
               COUNT(a.id_alumno) as total_alumnos,
               ca.nombre as carrera_nombre
        FROM clase c
        JOIN materia m ON c.id_materia = m.id_materia
        JOIN carrera ca ON m.id_carrera = ca.id_carrera
        LEFT JOIN asignacion a ON c.id_clase = a.id_clase
        WHERE c.activo = 1 AND c.id_profesor = ?
        GROUP BY c.id_clase, m.nombre, c.grupo, ca.nombre
        ORDER BY m.nombre, c.grupo
    ");
    $queryClases->bind_param("i", $id_profesor);
}

$queryClases->execute();
$resultClases = $queryClases->get_result();

while ($clase = $resultClases->fetch_assoc()) {
    $clases_disponibles[] = $clase;
}

// Procesar generación de boletas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once('tcpdf/tcpdf.php');
    
    $claves_alumnos = [];
    $metodo = '';
    
    if (isset($_POST['id_clase']) && !empty($_POST['id_clase'])) {
        // Método: Selección de clase
        $metodo = 'clase';
        $id_clase = $_POST['id_clase'];
        
        // Obtener información de la clase seleccionada
        $queryInfoClase = $conexion->prepare("
            SELECT m.nombre AS materia, c.grupo, ca.nombre as carrera_nombre
            FROM clase c
            JOIN materia m ON c.id_materia = m.id_materia
            JOIN carrera ca ON m.id_carrera = ca.id_carrera
            WHERE c.id_clase = ?
        ");
        $queryInfoClase->bind_param("i", $id_clase);
        $queryInfoClase->execute();
        $info_clase = $queryInfoClase->get_result()->fetch_assoc();
        
        // Obtener claves de alumnos de la clase
        $queryAlumnos = $conexion->prepare("
            SELECT u.clave
            FROM asignacion a
            JOIN alumno al ON a.id_alumno = al.id_alumno
            JOIN usuario u ON al.id_usuario = u.id_usuario
            WHERE a.id_clase = ?
        ");
        $queryAlumnos->bind_param("i", $id_clase);
        $queryAlumnos->execute();
        $resultAlumnos = $queryAlumnos->get_result();
        
        while ($alumno = $resultAlumnos->fetch_assoc()) {
            $claves_alumnos[] = $alumno['clave'];
        }
        
        if (empty($claves_alumnos)) {
            $mensaje = "<div class='alert alert-error'>❌ No hay alumnos inscritos en esta clase.</div>";
        }
        
    } elseif (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
        // Método: Archivo CSV
        $metodo = 'archivo';
        $archivo_tmp = $_FILES['archivo_excel']['tmp_name'];
        $nombre_archivo = $_FILES['archivo_excel']['name'];
        
        // Validaciones del archivo
        if (!file_exists($archivo_tmp)) {
            $mensaje = "<div class='alert alert-error'>❌ El archivo temporal no existe</div>";
        } elseif ($_FILES['archivo_excel']['size'] == 0) {
            $mensaje = "<div class='alert alert-error'>❌ El archivo está vacío</div>";
        } else {
            $claves_alumnos = leerArchivoSinDependencias($archivo_tmp, $nombre_archivo);
            
            if (empty($claves_alumnos)) {
                $mensaje = "<div class='alert alert-error'>❌ No se encontraron números de control válidos en el archivo.</div>";
            }
        }
    } else {
        $mensaje = "<div class='alert alert-error'>❌ Debes seleccionar una clase o subir un archivo.</div>";
    }
    
    // Generar boletas si hay claves
    if (!empty($claves_alumnos)) {
        $resultado = generarBoletasEnMemoria($claves_alumnos, $conexion);
        
        if ($resultado !== false) {
            list($zip_data, $boletas_generadas, $boletas_fallidas) = $resultado;
            
            if ($boletas_generadas > 0) {
                // Crear directorio si no existe
                $directorio_boletas = 'uploads/boletas/';
                if (!is_dir($directorio_boletas)) {
                    mkdir($directorio_boletas, 0777, true);
                }
                
                // Generar nombre único para el archivo
                if ($metodo === 'clase') {
                    $nombre_archivo = 'boletas_' . str_replace(' ', '_', $info_clase['materia']) . 
                                     '_' . ($info_clase['grupo'] ? 'Gpo_' . $info_clase['grupo'] : '') . 
                                     '_' . date('Y-m-d_H-i-s') . '.zip';
                } else {
                    $nombre_archivo = 'boletas_archivo_' . date('Y-m-d_H-i-s') . '.zip';
                }
                
                $ruta_completa = $directorio_boletas . $nombre_archivo;
                
                // Guardar archivo ZIP
                if (file_put_contents($ruta_completa, $zip_data)) {
                    $mensaje = "<div class='alert alert-success'>
                        ✅ Se generaron $boletas_generadas boletas exitosamente.<br>
                        <a href='$ruta_completa' class='btn-descargar' download>
                            📥 Descargar Archivo ZIP
                        </a>
                    </div>";
                } else {
                    $mensaje = "<div class='alert alert-error'>❌ Error al guardar el archivo ZIP.</div>";
                }
            } else {
                $mensaje = "<div class='alert alert-error'>❌ No se pudieron generar boletas. Las siguientes claves no existen: " . implode(', ', $boletas_fallidas) . "</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-error'>❌ Error al crear el archivo ZIP.</div>";
        }
    }
}

// FUNCIÓN PARA LEER ARCHIVOS SIN DEPENDENCIAS EXTERNAS
function leerArchivoSinDependencias($archivo_tmp, $nombre_archivo) {
    $claves_alumnos = [];
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    
    if ($extension === 'csv') {
        // LEER ARCHIVO CSV
        if (($handle = fopen($archivo_tmp, 'r')) !== FALSE) {
            $fila_numero = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $fila_numero++;
                if (!empty($data[0])) {
                    $valor = trim($data[0]);
                    // Ignorar encabezados en la primera fila
                    if ($fila_numero > 1 || !in_array(strtolower($valor), ['clave', 'número de control', 'matrícula', 'no. control', 'matricula'])) {
                        if (!empty($valor)) {
                            $claves_alumnos[] = $valor;
                        }
                    }
                }
            }
            fclose($handle);
        }
    } else {
        // PARA ARCHIVOS DE TEXTO
        $contenido = file_get_contents($archivo_tmp);
        $lineas = explode("\n", $contenido);
        $fila_numero = 0;
        foreach ($lineas as $linea) {
            $fila_numero++;
            $linea = trim($linea);
            if (!empty($linea)) {
                // Ignorar encabezados en la primera fila
                if ($fila_numero > 1 || !in_array(strtolower($linea), ['clave', 'número de control', 'matrícula', 'no. control', 'matricula'])) {
                    $claves_alumnos[] = $linea;
                }
            }
        }
    }
    
    // Eliminar duplicados y valores vacíos
    $claves_alumnos = array_unique($claves_alumnos);
    $claves_alumnos = array_filter($claves_alumnos);
    
    return $claves_alumnos;
}

// FUNCIÓN PRINCIPAL PARA GENERAR BOLETAS EN MEMORIA
function generarBoletasEnMemoria($claves_alumnos, $conexion) {
    // Crear ZIP en memoria usando un archivo temporal
    $temp_file = tempnam(sys_get_temp_dir(), 'zip');
    
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    
    $boletas_generadas = 0;
    $boletas_fallidas = [];
    
    foreach ($claves_alumnos as $clave_usuario) {
        $pdf_content = generarBoletaIndividualEnMemoria($clave_usuario, $conexion);
        if ($pdf_content !== false && !empty($pdf_content)) {
            $zip->addFromString("boleta_{$clave_usuario}.pdf", $pdf_content);
            $boletas_generadas++;
            error_log("Boleta generada para: $clave_usuario");
        } else {
            $boletas_fallidas[] = $clave_usuario;
            error_log("Fallo al generar boleta para: $clave_usuario");
        }
    }
    
    $zip->close();
    
    if ($boletas_generadas > 0) {
        $zip_data = file_get_contents($temp_file);
        unlink($temp_file); // Limpiar archivo temporal
        return [$zip_data, $boletas_generadas, $boletas_fallidas];
    } else {
        unlink($temp_file); // Limpiar archivo temporal
        return false;
    }
}

// FUNCIÓN PARA GENERAR BOLETA INDIVIDUAL EN MEMORIA (CON TABLA CENTRADA Y ANCHO CORREGIDO)
function generarBoletaIndividualEnMemoria($clave_usuario, $conexion) {
    // Obtener ID del alumno basado en la clave
    $queryUsuario = $conexion->prepare("SELECT id_usuario, nombre, apellidos FROM usuario WHERE clave = ?");
    $queryUsuario->bind_param("s", $clave_usuario);
    $queryUsuario->execute();
    $resultUsuario = $queryUsuario->get_result();
    
    if ($resultUsuario->num_rows === 0) {
        error_log("Usuario no encontrado: $clave_usuario");
        return false;
    }
    
    $usuario_data = $resultUsuario->fetch_assoc();
    $id_usuario = $usuario_data['id_usuario'];
    $nombre_alumno = $usuario_data['nombre'] . ' ' . $usuario_data['apellidos'];
    
    // Obtener ID del alumno
    $queryAlumno = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
    $queryAlumno->bind_param("i", $id_usuario);
    $queryAlumno->execute();
    $resultAlumno = $queryAlumno->get_result();
    
    if ($resultAlumno->num_rows === 0) {
        error_log("Alumno no encontrado para usuario: $id_usuario");
        return false;
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
        error_log("Alumno $clave_usuario no tiene materias asignadas");
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
            if ($key !== 'final' && $val > 0) {
                $suma += $val;
                $cont++;
            }
        }
        $unis['final'] = $cont > 0 ? round($suma / $cont, 2) : 0;
    }
    unset($unis);
    
    /* ---------------------------------------------------------
       GENERAR PDF EN MEMORIA
    --------------------------------------------------------- */
    $pdf = new TCPDF('P', 'mm', 'Letter', true, 'UTF-8', false);
    
    // Configurar para generar en memoria
    $pdf->SetCreator('Sistema Escolar');
    $pdf->SetAuthor('Sistema Escolar');
    $pdf->SetTitle("Boleta de $clave_usuario");
    $pdf->SetSubject('Boleta Oficial');
    
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();
    
    /* ---------------------------------------------------------
       ENCABEZADO PROFESIONAL
    --------------------------------------------------------- */
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->Cell(0, 7, "INSTITUTO TECNOLÓGICO SUPERIOR DEL SUR DE GUANAJUATO", 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 6, "Boleta Oficial de Calificaciones", 0, 1, 'C');
    $pdf->Ln(6);
    
    /* ---------------------------------------------------------
       DATOS DEL ALUMNO
    --------------------------------------------------------- */
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(245, 245, 245);
    
    $pdf->Cell(0, 7, "Alumno: $nombre_alumno", 0, 1, 'L', true);
    $pdf->Cell(0, 7, "Número de Control: $clave_usuario", 0, 1, 'L', true);
    $pdf->Ln(3);
    
    /* ---------------------------------------------------------
       CALCULAR ANCHOS DINÁMICOS (MANTENIENDO EL ANCHO ORIGINAL)
    --------------------------------------------------------- */
    $pageWidth = 190; // Ancho fijo para mantener consistencia
    $ratioMateria = 0.40;
    $ratioFinal   = 0.15;
    
    $widthMateria = $pageWidth * $ratioMateria;
    $widthFinal = $pageWidth * $ratioFinal;
    $widthUnidadesTotal = $pageWidth - ($widthMateria + $widthFinal);
    $widthUnidad = $maxUnidadesGlobal > 0 ? $widthUnidadesTotal / $maxUnidadesGlobal *4.3 : 20;
    
    /* ---------------------------------------------------------
       TABLA HTML CENTRADA
    --------------------------------------------------------- */
    $tbl = '
    <style>
        table {
            border-collapse: collapse;
            font-size: 9px;
            width: 100%;
            margin: 0 auto;
        }
        th {
            background-color: #003366;
            color: white;
            text-align: center;
            font-weight: bold;
            padding: 4px;
        }
        td {
            text-align: center;
            padding: 3px;
            border: 1px solid #ddd;
        }
        .materia {
            text-align: left;
            font-weight: bold;
            background-color: #f3f3f3;
        }
    </style>
    
    <table border="1" cellpadding="3" width="'.$pageWidth.'">
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
            $calif = isset($unis[$i]) ? $unis[$i] : 0;
            $tbl .= '<td width="'.$widthUnidad.'">'.$calif.'</td>';
        }
    
        $color = $unis["final"] >= 6 ? "#006600" : "#cc0000";
        $tbl .= '<td width="'.$widthFinal.'" style="color:'.$color.'; font-weight: bold;">'.$unis["final"].'</td>';
        $tbl .= '</tr>';
    }
    
    $tbl .= '</table>';
    
    $pdf->writeHTML($tbl, true, false, false, false, '');
    
    /* ---------------------------------------------------------
       NOTAS INFORMATIVAS
    --------------------------------------------------------- */
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 4,
    "• Esta boleta es un documento informativo emitido por el Sistema Escolar.\n".
    "• Las calificaciones finales están sujetas a validación institucional.\n".
    "• Para aclaraciones, comuníquese con el Departamento Académico.\n".
    "• La alteración de este documento incurre en un delito.",
    0, 'L', false);
    
    $pdf->Ln(8);
    
    /* ---------------------------------------------------------
       FIRMA
    --------------------------------------------------------- */
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "_____________________________________", 0, 1, 'C');
    $pdf->Cell(0, 5, "Firma de Enterado del Tutor", 0, 1, 'C');
    
    $pdf->Ln(6);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 6, 'Documento generado automáticamente - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    // Devolver el contenido del PDF como string
    return $pdf->Output('', 'S');
}
?>

<style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
}

body {
  background: #f4f6f8;
  font-family: "Poppins", sans-serif;
}

.content {
  padding: 40px 5%;
  max-width: 900px;
  margin: auto;
}

.header-boletas {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 30px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.header-boletas h1 {
  margin: 0 0 15px 0;
  font-size: 2.2em;
}

.form-container {
  background: white;
  padding: 40px;
  border-radius: 20px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  font-weight: 600;
  color: #333;
  font-size: 1.1em;
}

.select-clase {
  width: 100%;
  padding: 15px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 1.1em;
  background: white;
  transition: all 0.3s ease;
}

.select-clase:focus {
  border-color: var(--color-primario);
  outline: none;
  box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
}

.file-input {
  width: 100%;
  padding: 20px;
  border: 2px dashed #e0e0e0;
  border-radius: 10px;
  font-size: 1.1em;
  text-align: center;
  transition: all 0.3s ease;
  background: #fafafa;
  cursor: pointer;
}

.file-input:hover {
  border-color: var(--color-primario);
  background: #f0f7ff;
}

.file-input input[type="file"] {
  width: 100%;
  padding: 10px;
}

.btn {
  padding: 15px 30px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  font-size: 1.1em;
  width: 100%;
}

.btn-primary {
  background: var(--color-primario);
  color: white;
}

.btn-primary:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(21, 101, 192, 0.3);
}

.btn-descargar {
  background: #28a745;
  color: white;
  padding: 12px 25px;
  border-radius: 8px;
  text-decoration: none;
  display: inline-block;
  margin-top: 10px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-descargar:hover {
  background: #218838;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.alert {
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 25px;
  text-align: center;
  border-left: 4px solid;
}

.alert-success {
  background: #e3f2fd;
  color: #1565c0;
  border-color: #1565c0;
}

.alert-error {
  background: #ffebee;
  color: #c62828;
  border-color: #c62828;
}

.info-box {
  background: #e3f2fd;
  padding: 25px;
  border-radius: 15px;
  margin-top: 25px;
  border-left: 4px solid var(--color-primario);
}

.info-box h3 {
  margin: 0 0 15px 0;
  color: var(--color-primario);
  font-size: 1.3em;
}

.metodo-seleccion {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 25px;
}

.metodo-option {
  background: white;
  padding: 25px;
  border-radius: 15px;
  border: 2px solid #e0e0e0;
  transition: all 0.3s ease;
  cursor: pointer;
}

.metodo-option:hover {
  border-color: var(--color-primario);
  transform: translateY(-2px);
}

.metodo-option.active {
  border-color: var(--color-primario);
  background: #f0f7ff;
}

.metodo-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
}

.metodo-icon {
  font-size: 1.5em;
  color: var(--color-primario);
}

.metodo-title {
  font-weight: 600;
  color: #333;
  font-size: 1.1em;
}

.metodo-desc {
  color: #666;
  font-size: 0.9em;
}

.back-btn {
  text-align: center;
  margin-top: 25px;
}

.back-btn .btn {
  width: auto;
  padding: 12px 25px;
  background: #6c757d;
}

.back-btn .btn:hover {
  background: #5a6268;
  transform: translateY(-2px);
}

.option-group {
  padding: 8px 12px;
  font-weight: 600;
  color: var(--color-primario);
  background: #f0f7ff;
  border-bottom: 1px solid #e0e0e0;
}

.option-item {
  padding: 8px 20px;
  border-bottom: 1px solid #f5f5f5;
}

.option-item:last-child {
  border-bottom: none;
}

@media (max-width: 768px) {
  .metodo-seleccion {
    grid-template-columns: 1fr;
  }
}
</style>

<!-- HEADER -->
<div class="header-boletas">
  <h1>Generar Boletas</h1>
  <p>Selecciona un método para generar boletas de alumnos</p>
</div>

<main class="content">
  <?php echo $mensaje; ?>
  
  <div class="form-container">
    <form method="POST" enctype="multipart/form-data">
      <div class="metodo-seleccion">
        <!-- Método 1: Selección de Clase -->
        <div class="metodo-option active" onclick="seleccionarMetodo('clase')">
          <div class="metodo-header">
            <div class="metodo-icon">🎓</div>
            <div class="metodo-title">Por Clase</div>
          </div>
          <div class="metodo-desc">
            Genera boletas para todos los alumnos de una clase específica
          </div>
          <div class="form-group" id="grupo-clase">
            <select name="id_clase" class="select-clase">
              <option value="">-- Selecciona una clase --</option>
              <?php 
              $carrera_actual = '';
              foreach ($clases_disponibles as $clase): 
                if ($_SESSION['rol'] == 3 && $es_coordinador_general && $clase['carrera_nombre'] != $carrera_actual):
                  $carrera_actual = $clase['carrera_nombre'];
              ?>
                <optgroup label="🎓 <?php echo $clase['carrera_nombre']; ?>">
              <?php endif; ?>
              
              <option value="<?php echo $clase['id_clase']; ?>">
                <?php 
                echo $clase['materia'];
                if ($clase['grupo']) {
                    echo ' - Grupo ' . $clase['grupo'];
                }
                echo ' (' . $clase['total_alumnos'] . ' alumnos)';
                if ($_SESSION['rol'] == 3 && $es_coordinador_general) {
                    echo ' - ' . $clase['carrera_nombre'];
                }
                ?>
              </option>
              
              <?php endforeach; ?>
              
              <?php if ($_SESSION['rol'] == 3 && $es_coordinador_general): ?>
                </optgroup>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <!-- Método 2: Archivo CSV -->
        <div class="metodo-option" onclick="seleccionarMetodo('archivo')">
          <div class="metodo-header">
            <div class="metodo-icon">📁</div>
            <div class="metodo-title">Por Archivo</div>
          </div>
          <div class="metodo-desc">
            Sube un archivo CSV o TXT con los números de control
          </div>
          <div class="form-group" id="grupo-archivo" style="display: none;">
            <div class="file-input">
              <input type="file" name="archivo_excel" accept=".csv,.txt">
              <p style="margin: 10px 0 0 0; color: #666;">📁 Archivo CSV o TXT con números de control</p>
            </div>
          </div>
        </div>
      </div>
      
      <button type="submit" class="btn btn-primary">
        Generar Boletas
      </button>
    </form>
    
    <div class="info-box">
      <h3>Información Importante</h3>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div>
          <h4>📋 Formato del Archivo</h4>
          <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 0.9em;">
S25120001
S25120002
G25120015
T25120025</pre>
        </div>
        <div>
          <h4>Resultados</h4>
          <p>• Se genera un archivo ZIP descargable</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="back-btn">
    <a href="clases.php" class="btn">
      ← Volver al Sistema
    </a>
  </div>
</main>

<script>
function seleccionarMetodo(metodo) {
    // Remover clase active de todos los métodos
    document.querySelectorAll('.metodo-option').forEach(option => {
        option.classList.remove('active');
    });
    
    // Ocultar todos los grupos de formulario
    document.getElementById('grupo-clase').style.display = 'none';
    document.getElementById('grupo-archivo').style.display = 'none';
    
    // Activar el método seleccionado
    const metodoSeleccionado = event.currentTarget;
    metodoSeleccionado.classList.add('active');
    
    // Mostrar el grupo de formulario correspondiente
    if (metodo === 'clase') {
        document.getElementById('grupo-clase').style.display = 'block';
        // Limpiar archivo si estaba seleccionado
        document.querySelector('input[name="archivo_excel"]').value = '';
    } else {
        document.getElementById('grupo-archivo').style.display = 'block';
        // Limpiar selección de clase si estaba seleccionada
        document.querySelector('select[name="id_clase"]').value = '';
    }
}

// Inicializar con el método de clase activo
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('grupo-clase').style.display = 'block';
});
</script>

<?php include "footer.php"; ?>