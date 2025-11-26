<?php
ob_start(); // Inicia el buffer de salida
session_start();
include "../conexion.php";

// Solo coordinador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != '3') { 
    header("Location: ../index.php"); 
    exit;
}

// Obtener id_carrera del coordinador
$id_usuario = $_SESSION['id_usuario'];
$sql_carrera = "SELECT id_carrera FROM usuario WHERE id_usuario = $id_usuario";
$carrera = $conexion->query($sql_carrera)->fetch_assoc();
$id_carrera = $carrera['id_carrera'];

// Mapeo de carreras a letras
$letras_carrera = [
    1 => 'S', 2 => 'G', 3 => 'T', 4 => 'A', 
    5 => 'I', 6 => 'E', 7 => 'R'
];

// Si el coordinador es de carrera 0, obtener lista de carreras para el select
$carreras_disponibles = [];
if ($id_carrera == 0) {
    $sql_carreras = "SELECT id_carrera, nombre FROM carrera WHERE id_carrera != 0";
    $result_carreras = $conexion->query($sql_carreras);
    while ($row = $result_carreras->fetch_assoc()) {
        $carreras_disponibles[$row['id_carrera']] = $row['nombre'];
    }
}

// Año dinámico (últimos 2 dígitos del año actual)
$anio_actual = date('y');

// ========================================================
// FUNCIONES 
// ========================================================

function obtenerUltimoNumero($conexion, $letra_carrera, $anio_actual) {
    $sql = "SELECT clave FROM usuario 
            WHERE clave LIKE '{$letra_carrera}{$anio_actual}12%' 
            ORDER BY clave DESC 
            LIMIT 1";
    
    $result = $conexion->query($sql);
    
    if ($result->num_rows > 0) {
        $ultima_clave = $result->fetch_assoc()['clave'];
        $ultimo_numero = intval(substr($ultima_clave, -4));
        return $ultimo_numero;
    }
    
    return 0;
}

function generarClave($conexion, $letra_carrera, $anio_actual) {
    $ultimo_numero = obtenerUltimoNumero($conexion, $letra_carrera, $anio_actual);
    $nuevo_numero = $ultimo_numero + 1;
    $numero_formateado = str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);
    $clave = $letra_carrera . $anio_actual . '12' . $numero_formateado;
    
    $sql_v = "SELECT id_usuario FROM usuario WHERE clave = '$clave'";
    $v = $conexion->query($sql_v);
    
    if ($v->num_rows == 0) {
        return $clave;
    }
    
    $intentos = 1;
    do {
        $nuevo_numero_alt = $ultimo_numero + $intentos + 1;
        $numero_formateado_alt = str_pad($nuevo_numero_alt, 4, '0', STR_PAD_LEFT);
        $clave_alt = $letra_carrera . $anio_actual . '12' . $numero_formateado_alt;
        
        $sql2 = "SELECT id_usuario FROM usuario WHERE clave = '$clave_alt'";
        $r2 = $conexion->query($sql2);
        $intentos++;
    } while ($r2->num_rows > 0 && $intentos < 100);
    
    return $clave_alt;
}

function generarContraseñaAleatoria($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $contraseña = '';
    for ($i = 0; $i < $longitud; $i++) {
        $contraseña .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $contraseña;
}

// FUNCIÓN PARA ENVIAR CORREO CON RESEND
function enviarCorreoContraseña($correo_destino, $numero_control, $contraseña, $nombre_alumno, $correo_institucional) {

    // Mensaje HTML
    $mensaje_html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1565c0; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .credenciales { background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #1565c0; margin: 15px 0; }
                .dato { margin: 10px 0; }
                .etiqueta { font-weight: bold; color: #555; }
                .valor { color: #1565c0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>¡Bienvenido al Instituto Tecnológico Superior del Sur de Guanajuato!</h1>
                </div>
                <div class='content'>
                    <p>Estimado <strong>$nombre_alumno</strong>,</p>

                    <p>A continuación encontrarás tus credenciales de acceso al sistema:</p>

                    <div class='credenciales'>
                        <div class='dato'><span class='etiqueta'>Número de Control:</span> <span class='valor'>$numero_control</span></div>
                        <div class='dato'><span class='etiqueta'>Correo Institucional:</span> <span class='valor'>$correo_institucional</span></div>
                        <div class='dato'><span class='etiqueta'>Contraseña:</span> <span class='valor'>$contraseña</span></div>
                    </div>

                    <div class='warning'>
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Guarda esta información en un lugar seguro.</li>
                            <li>Usa estas credenciales para acceder al sistema escolar.</li>
                            <li>Te recomendamos cambiar tu contraseña después del primer acceso.</li>
                            <li>Tu correo institucional es: <strong>$correo_institucional</strong></li>
                        </ul>
                    </div>

                    <p>Atentamente,<br><strong>Instituto Tecnológico Superior del Sur de Guanajuato</strong></p>
                </div>

                <div class='footer'>
                    <p>Este mensaje es automático, por favor no respondas.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    // Asunto
    $subject = "Correo de Envío de Contraseña - Bienvenida ITSUR";

    // 🔥 CABECERAS NECESARIAS PARA HTML EN AwardSpace
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    // From: DEBE SER correo creado en AwardSpace
    $headers .= "From: ITSUR Notificaciones <no-reply@TUDOMINIO.awardspace.info>\r\n";

    // Envío final
    $resultado = mail($correo_destino, $subject, $mensaje_html, $headers);

    return $resultado; // true si se envió, false si falló
}


function crearAlumno($conexion, $nombre, $apellidos, $contraseña, $id_carrera, $letra_carrera, $anio_actual, $semestre = 1, $correo_personal = '', $clave_existente = '') {
    // Si se proporciona una clave existente, usarla; de lo contrario, generar una nueva
        $contraseña_hash= password_hash($contraseña,PASSWORD_BCRYPT);
    if (!empty($clave_existente)) {
        $clave = $clave_existente;
        // Verificar si la clave ya existe en la base de datos
        $sql_verificar = "SELECT id_usuario FROM usuario WHERE clave = '$clave'";
        $resultado = $conexion->query($sql_verificar);
        if ($resultado->num_rows > 0) {
            return [false, "La clave '$clave' ya existe en el sistema"];
        }
    } else {
        $clave = generarClave($conexion, $letra_carrera, $anio_actual);
    }
    
    $correo_institucional = $clave . "@alumnos.itsur.edu.mx";

    $sql_usuario = "
        INSERT INTO usuario (nombre, apellidos, correo, contraseña, rol, id_carrera, clave, fecha_nacimiento)
        VALUES ('$nombre', '$apellidos', '$correo_institucional', '$contraseña_hash', '1', $id_carrera, '$clave', '2005-01-01 00:00:00')
    ";

    if (!$conexion->query($sql_usuario)) {
        return [false, "Error usuario: " . $conexion->error];
    }

    $id_usuario_nuevo = $conexion->insert_id;

    $sql_max = "SELECT MAX(id_alumno) AS max_a FROM alumno";
    $r = $conexion->query($sql_max);
    $max = $r->fetch_assoc()['max_a'] ?? 0;
    $nuevo_id_alumno = $max + 1;

    $sql_alumno = "
        INSERT INTO alumno (id_alumno, id_usuario, semestre, promedio, especialidad, año_inscripcion, estado)
        VALUES ($nuevo_id_alumno, $id_usuario_nuevo, $semestre, 0.00, 'sin especificar', NOW(), '1')
    ";

    if (!$conexion->query($sql_alumno)) {
        $conexion->query("DELETE FROM usuario WHERE id_usuario = $id_usuario_nuevo");
        return [false, "Error alumno: " . $conexion->error];
    }

    // Enviar correo si se proporcionó un correo personal
    $correo_enviado = false;
    if (!empty($correo_personal) && filter_var($correo_personal, FILTER_VALIDATE_EMAIL)) {
        $correo_enviado = enviarCorreoContraseña($correo_personal, $clave, $contraseña, $nombre . ' ' . $apellidos, $correo_institucional);
    }

    return [true, $clave, $correo_institucional, $correo_enviado, $correo_personal];
}

function generarPDF($alumnos_creados) {
    require_once('../tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema Escolar ITSUR');
    $pdf->SetAuthor('Coordinador');
    $pdf->SetTitle('Lista de Alumnos Creados');
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'INSTITUTO TECNOLÓGICO SUPERIOR DEL SUR DE GUANAJUATO', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'LISTA DE ALUMNOS REGISTRADOS', 0, 1, 'C');
    $pdf->Ln(10);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $header = array('No. Control', 'Nombre', 'Apellidos', 'Contraseña', 'Correo Institucional', 'Correo Personal', 'Semestre');
    $widths = array(30, 30, 35, 25, 50, 50, 20);
    
    $pdf->SetFillColor(21, 101, 192);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(21, 101, 192);
    $pdf->SetLineWidth(0.3);
    
    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    $pdf->SetFillColor(224, 235, 255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 8);
    
    $fill = false;
    foreach($alumnos_creados as $alumno) {
        $pdf->Cell($widths[0], 6, $alumno['clave'], 'LR', 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, $alumno['nombre'], 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $alumno['apellidos'], 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[3], 6, $alumno['contraseña'], 'LR', 0, 'C', $fill);
        $pdf->Cell($widths[4], 6, $alumno['correo_institucional'], 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[5], 6, $alumno['correo_personal'] ?? '', 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[6], 6, $alumno['semestre'], 'LR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
    
    $pdf->Cell(array_sum($widths), 0, '', 'T');
    $pdf->Ln(10);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Total de alumnos registrados: ' . count($alumnos_creados), 0, 1);
    
    $filename = 'alumnos_registrados_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

function leerArchivoCSV($archivo_tmp, $generarContraseñas = false) {
    $alumnos = [];
    
    if (($handle = fopen($archivo_tmp, 'r')) !== FALSE) {
        $fila_numero = 0;
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $fila_numero++;
            
            if ($fila_numero === 1) continue;
            if (count($data) < 2) continue;
            
            $nombre = trim($data[0] ?? '');
            $apellidos = trim($data[1] ?? '');
            $contraseña = isset($data[2]) ? trim($data[2]) : '';
            $correo_personal = isset($data[3]) ? trim($data[3]) : '';
            $semestre = isset($data[4]) ? trim($data[4]) : '';
            $clave = isset($data[5]) ? trim($data[5]) : ''; // Nueva columna para clave existente
            
            if (empty($nombre) || empty($apellidos)) continue;
            
            if ($generarContraseñas || empty($contraseña)) {
                $contraseña = generarContraseñaAleatoria(8);
            }
            
            $semestre_final = 1;
            if (!empty($semestre) && is_numeric($semestre)) {
                $semestre_int = intval($semestre);
                if ($semestre_int >= 1 && $semestre_int <= 9) {
                    $semestre_final = $semestre_int;
                }
            }
            
            $alumnos[] = [
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'contraseña' => $contraseña,
                'correo_personal' => $correo_personal,
                'semestre' => $semestre_final,
                'clave_existente' => $clave // Agregar la clave existente al array
            ];
        }
        
        fclose($handle);
    }
    
    return $alumnos;
}

// ========================================================
// PROCESAMIENTO PRINCIPAL
// ========================================================

$mensaje = "";
$alumno_creado = null;
$alumnos_creados_csv = null;
$total_insertados = 0;
$total_errores = 0;

// Procesar PDF
if (isset($_POST['generar_pdf_csv']) && isset($_SESSION['alumnos_creados_csv'])) {
    generarPDF($_SESSION['alumnos_creados_csv']);
}

// Procesar alumno individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_manual'])) {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $contraseña = $_POST['contraseña'];
    $correo_personal = trim($_POST['correo_personal'] ?? '');
    $semestre = intval($_POST['semestre']);
    $clave_existente = trim($_POST['clave_existente'] ?? '');
    
    if ($id_carrera == 0 && isset($_POST['id_carrera']) && !empty($_POST['id_carrera'])) {
        $carrera_seleccionada = intval($_POST['id_carrera']);
        $letra_carrera_usar = $letras_carrera[$carrera_seleccionada] ?? 'X';
    } else {
        $carrera_seleccionada = $id_carrera;
        $letra_carrera_usar = $letras_carrera[$id_carrera] ?? 'X';
    }

    list($ok, $clave, $correo_institucional, $correo_enviado, $correo_personal_used) = crearAlumno($conexion, $nombre, $apellidos, $contraseña, $carrera_seleccionada, $letra_carrera_usar, $anio_actual, $semestre, $correo_personal, $clave_existente);

    if ($ok) {
        $alumno_creado = [
            'clave' => $clave,
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'contraseña' => $contraseña,
            'correo_institucional' => $correo_institucional,
            'correo_personal' => $correo_personal_used,
            'carrera' => $carrera_seleccionada,
            'semestre' => $semestre,
            'correo_enviado' => $correo_enviado
        ];
        
        $mensaje_success = "✅ Alumno creado exitosamente";
        if (!empty($clave_existente)) {
            $mensaje_success .= " - Se utilizó la clave existente: $clave";
        }
        if ($correo_enviado) {
            $mensaje_success .= " - Correo enviado a: " . $correo_personal_used;
        } else if (!empty($correo_personal)) {
            $mensaje_success .= " - ❌ Correo no enviado (correo inválido)";
        }
        $mensaje = "<div class='alert alert-success'>$mensaje_success</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error: $clave</div>";
    }
}

// Procesar CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_csv'])) {
    
    // Validaciones básicas
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] != UPLOAD_ERR_OK) {
        $mensaje = "<div class='alert alert-danger'>❌ Error al subir el archivo.</div>";
    } else {
        $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
        $extension = strtolower(pathinfo($_FILES['archivo_csv']['name'], PATHINFO_EXTENSION));
        
        if ($extension !== 'csv') {
            $mensaje = "<div class='alert alert-danger'>❌ Solo se permiten archivos CSV.</div>";
        } else {
            // Determinar carrera
            $carrera_csv = $id_carrera;
            $letra_carrera_csv = $letras_carrera[$id_carrera] ?? 'X';
            
            if ($id_carrera == 0) {
                // Para coordinador global, obtener la carrera del formulario
                if (isset($_POST['id_carrera_csv']) && !empty($_POST['id_carrera_csv'])) {
                    $carrera_csv = intval($_POST['id_carrera_csv']);
                    $letra_carrera_csv = $letras_carrera[$carrera_csv] ?? 'X';
                } else {
                    $mensaje = "<div class='alert alert-danger'>❌ Debe seleccionar una carrera para los alumnos.</div>";
                    $carrera_csv = null;
                }
            }

            if ($carrera_csv && $carrera_csv > 0) {
                $generarContraseñas = isset($_POST['generar_contraseñas']) && $_POST['generar_contraseñas'] == '1';
                $alumnos_csv = leerArchivoCSV($archivo_tmp, $generarContraseñas);
                
                if (empty($alumnos_csv)) {
                    $mensaje = "<div class='alert alert-warning'>⚠️ No se encontraron alumnos válidos en el archivo CSV.</div>";
                } else {
                    $alumnos_creados = [];
                    $insertados = 0;
                    $errores = 0;
                    $correos_enviados = 0;

                    foreach ($alumnos_csv as $alumno) {
                        list($ok, $clave, $correo_institucional, $correo_enviado, $correo_personal) = crearAlumno(
                            $conexion, 
                            $alumno['nombre'], 
                            $alumno['apellidos'], 
                            $alumno['contraseña'], 
                            $carrera_csv, 
                            $letra_carrera_csv, 
                            $anio_actual, 
                            $alumno['semestre'],
                            $alumno['correo_personal'],
                            $alumno['clave_existente'] // Pasar la clave existente
                        );

                        if ($ok) {
                            $insertados++;
                            if ($correo_enviado) {
                                $correos_enviados++;
                            }
                            $alumnos_creados[] = [
                                'clave' => $clave,
                                'nombre' => $alumno['nombre'],
                                'apellidos' => $alumno['apellidos'],
                                'contraseña' => $alumno['contraseña'],
                                'correo_institucional' => $correo_institucional,
                                'correo_personal' => $alumno['correo_personal'],
                                'semestre' => $alumno['semestre'],
                                'correo_enviado' => $correo_enviado,
                                'clave_existente_usada' => !empty($alumno['clave_existente']) // Indicar si se usó clave existente
                            ];
                        } else {
                            $errores++;
                        }
                    }

                    if ($insertados > 0) {
                        $_SESSION['alumnos_creados_csv'] = $alumnos_creados;
                        $_SESSION['total_insertados'] = $insertados;
                        $_SESSION['total_errores'] = $errores;
                        $_SESSION['generar_contraseñas'] = $generarContraseñas;
                        $_SESSION['correos_enviados'] = $correos_enviados;
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?csv_success=1");
                        exit;
                    } else {
                        $mensaje = "<div class='alert alert-warning'>❌ No se pudieron crear alumnos. Errores: $errores</div>";
                    }
                }
            } else {
                $mensaje = "<div class='alert alert-danger'>❌ Carrera no válida seleccionada.</div>";
            }
        }
    }
}

// Mostrar resultados de importación CSV
if (isset($_GET['csv_success']) && isset($_SESSION['alumnos_creados_csv'])) {
    $alumnos_creados_csv = $_SESSION['alumnos_creados_csv'];
    $total_insertados = $_SESSION['total_insertados'];
    $total_errores = $_SESSION['total_errores'];
    $generarContraseñas = $_SESSION['generar_contraseñas'];
    $correos_enviados = $_SESSION['correos_enviados'];
    
    $mensaje = "<div class='alert alert-success'>✅ Se importaron $total_insertados alumnos exitosamente";
    if ($total_errores > 0) {
        $mensaje .= " (con $total_errores errores)";
    }
    if ($generarContraseñas) {
        $mensaje .= " - Se generaron contraseñas aleatorias";
    }
    $mensaje .= " - Se enviaron $correos_enviados correos de bienvenida";
    $mensaje .= "</div>";
}

// Mostrar mensajes de sesión
if (isset($_SESSION['success'])) {
    $mensaje = '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $mensaje = '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Alumno - Sistema Escolar</title>
    <style>
        body {
            background: #f4f6f8;
            font-family: "Segoe UI", sans-serif;
            margin: 0;
            padding: 20px;
        }
        .content {
            max-width: 900px;
            margin: auto;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .alert-warning {
            background: #fff8e1;
            color: #f57c00;
            border-left: 4px solid #ffc107;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            margin-bottom: 20px;
            text-align: center;
        }
        .card-header h2 {
            margin: 0 0 10px 0;
            color: #1565c0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        .form-control:focus {
            outline: none;
            border-color: #1565c0;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn-primary {
            background: #1565c0;
            color: white;
        }
        .btn-primary:hover {
            background: #1976d2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
            background: #fafafa;
        }
        .switch-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .switch-label {
            flex: 1;
        }
        .switch-label strong {
            display: block;
            margin-bottom: 5px;
        }
        .switch-label small {
            color: #666;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .slider.round {
            border-radius: 34px;
        }
        .slider.round:before {
            border-radius: 50%;
        }
        /* ESTILOS PARA MOSTRAR ALUMNO CREADO */
        .resultado-alumno {
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid #4caf50;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .resultado-alumno h3 {
            margin: 0 0 20px 0;
            color: #2e7d32;
            text-align: center;
            font-size: 1.4em;
        }
        .datos-alumno {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .dato-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }
        .dato-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .dato-valor {
            font-weight: 700;
            color: #2e7d32;
            font-size: 1.1em;
        }
        .correo-status {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .correo-enviado {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        .correo-no-enviado {
            background: #fff3e0;
            color: #f57c00;
            border: 1px solid #ffc107;
        }
        /* ESTILOS PARA RESULTADO CSV */
        .resultado-csv {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid #2196f3;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .csv-example {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .download-csv {
            text-align: center;
            margin: 20px 0;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .clave-existente {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-left: 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="card">
        <div class="card-header">
            <h1>Crear Nuevo Alumno</h1>
            <p>Sistema de Gestión Académica</p>
        </div>

        <?php if ($id_carrera == 0): ?>
        <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <p style="margin: 0; font-weight: 600; color: #2e7d32;">Eres Coordinador Global - Puedes asignar alumnos a cualquier carrera</p>
        </div>
        <?php endif; ?>

        <?php echo $mensaje; ?>

        <!-- MOSTRAR RESULTADO ALUMNO INDIVIDUAL CREADO -->
        <?php if ($alumno_creado): ?>
        <div class="resultado-alumno">
            <h3>✅ Alumno Creado Exitosamente</h3>
            
            <?php if ($alumno_creado['correo_enviado']): ?>
            <div class="correo-status correo-enviado">
                📧 Correo de bienvenida enviado a: <?php echo $alumno_creado['correo_personal']; ?>
            </div>
            <?php elseif (!empty($alumno_creado['correo_personal'])): ?>
            <div class="correo-status correo-no-enviado">
                ❌ Correo no enviado - Dirección inválida: <?php echo $alumno_creado['correo_personal']; ?>
            </div>
            <?php else: ?>
            <div class="correo-status correo-no-enviado">
                ℹ️ No se especificó correo personal para envío
            </div>
            <?php endif; ?>
            
            <div class="datos-alumno">
                <div class="dato-item">
                    <div class="dato-label">Número de Control</div>
                    <div class="dato-valor"><?php echo $alumno_creado['clave']; ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Nombre</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($alumno_creado['nombre']); ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Apellidos</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($alumno_creado['apellidos']); ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Contraseña</div>
                    <div class="dato-valor"><?php echo htmlspecialchars($alumno_creado['contraseña']); ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Correo Institucional</div>
                    <div class="dato-valor"><?php echo $alumno_creado['correo_institucional']; ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Correo Personal</div>
                    <div class="dato-valor"><?php echo !empty($alumno_creado['correo_personal']) ? $alumno_creado['correo_personal'] : 'No especificado'; ?></div>
                </div>
                <div class="dato-item">
                    <div class="dato-label">Semestre</div>
                    <div class="dato-valor"><?php echo $alumno_creado['semestre']; ?></div>
                </div>
                <?php if ($id_carrera == 0): ?>
                <div class="dato-item">
                    <div class="dato-label">Carrera Asignada</div>
                    <div class="dato-valor"><?php echo $carreras_disponibles[$alumno_creado['carrera']] ?? 'Desconocida'; ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="crear_alumno.php" class="btn btn-primary">➕ Crear Otro Alumno</a>
                <a href="../coordinador.php?seccion=alumnos" class="btn btn-secondary">📋 Ver Lista de Alumnos</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- FORMULARIO INDIVIDUAL -->
        <form method="POST">
            <input type="hidden" name="crear_manual" value="1">

            <?php if ($id_carrera == 0): ?>
            <div class="form-group">
                <label for="id_carrera">Carrera del Alumno:</label>
                <select id="id_carrera" name="id_carrera" class="form-control" required>
                    <option value="">-- Seleccione una carrera --</option>
                    <?php foreach ($carreras_disponibles as $id => $nombre): ?>
                        <option value="<?php echo $id; ?>">
                            <?php echo htmlspecialchars($nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="contraseña">Contraseña:</label>
                <input type="password" id="contraseña" name="contraseña" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="correo_personal">Correo Personal (para envío de credenciales):</label>
                <input type="email" id="correo_personal" name="correo_personal" class="form-control" 
                       placeholder="ejemplo@gmail.com">
                <small style="color: #666;">Opcional - Se enviarán las credenciales a este correo</small>
            </div>

            <div class="form-group">
                <label for="clave_existente">Clave Existente (opcional):</label>
                <input type="text" id="clave_existente" name="clave_existente" class="form-control" 
                       placeholder="Ej: S24120001">
                <small style="color: #666;">Opcional - Para migración de datos. Si se deja vacío, se generará una clave automáticamente</small>
            </div>

            <div class="form-group">
                <label for="semestre">Semestre:</label>
                <input type="number" id="semestre" name="semestre" value="1" min="1" max="9" class="form-control" required>
            </div>

            <div style="text-align: center;">
                <button type="submit" class="btn btn-primary">Crear Alumno</button>
                <a href="../coordinador.php?seccion=alumnos" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <div style="text-align: center; margin: 30px 0; color: #666;">O</div>

    <!-- FORMULARIO CSV -->
    <div class="card">
        <div class="card-header">
            <h2>Importar desde CSV</h2>
            <p>Cargue múltiples alumnos desde un archivo CSV</p>
        </div>

        <!-- EJEMPLO DE CSV -->
        <div class="csv-example">
            <h4>📋 Ejemplo de formato CSV:</h4>
            <p><strong>nombre,apellidos,contraseña,correo_personal,semestre,clave    ----- si estas en Excel estas son tus columnas</strong></p>
            <p>Juan,Pérez García,juan123,juan.perez@gmail.com,1,</p>
            <p>María,Fernández López,,maria.fernandez@hotmail.com,2,S24120001</p>
            <p>Carlos,Rodríguez Martínez,carlos456,carlos.rm@yahoo.com,1,</p>
            <p>Ana,González Silva,,,3,G24120015</p>
            <p><em>Nota: 
                <ul>
                    <li>Si dejas la contraseña vacía, se generará automáticamente</li>
                    <li>El correo personal es opcional</li>
                    <li>La <strong>clave</strong> es opcional - si se proporciona, se usará esa clave; si se deja vacío, se generará automáticamente</li>
                </ul>
            </em></p>
        </div>

        <div class="download-csv">
            <a href="data:text/csv;charset=utf-8,nombre,apellidos,contraseña,correo_personal,semestre,clave%0AJuan,Pérez García,juan123,juan.perez@gmail.com,1,%0AMaría,Fernández López,,maria.fernandez@hotmail.com,2,S24120001%0ACarlos,Rodríguez Martínez,carlos456,carlos.rm@yahoo.com,1,%0AAna,González Silva,,,3,G24120015" 
               download="ejemplo_alumnos.csv" class="btn btn-secondary">
               📥 Descargar Ejemplo CSV
            </a>
        </div>

        <div class="info-box">
            <h4>💡 Información importante:</h4>
            <ul>
                <li>El <strong>correo personal</strong> es opcional - si se proporciona, se enviarán las credenciales automáticamente</li>
                <li>El <strong>correo institucional</strong> se genera automáticamente con el número de control</li>
                <li>Si no se especifica contraseña, se generará una automáticamente de 8 caracteres</li>
                <li>La <strong>clave</strong> es opcional - útil para migración de datos existentes</li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?php if ($id_carrera == 0): ?>
            <div class="form-group">
                <label for="id_carrera_csv">Carrera para todos los alumnos:</label>
                <select id="id_carrera_csv" name="id_carrera_csv" class="form-control" required>
                    <option value="">-- Seleccione una carrera --</option>
                    <?php foreach ($carreras_disponibles as $id => $nombre): ?>
                        <option value="<?php echo $id; ?>">
                            <?php echo htmlspecialchars($nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- SWITCH PARA GENERAR CONTRASEÑAS -->
            <div class="switch-container">
                <div class="switch-label">
                    <strong>Generar contraseñas aleatorias</strong>
                    <small>Si se activa, se crearán contraseñas de 8 caracteres automáticamente para todos los alumnos</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="generar_contraseñas" value="1">
                    <span class="slider round"></span>
                </label>
            </div>

            <div class="upload-area">
                <p style="font-weight: 600; margin-bottom: 15px;">Seleccione el archivo CSV</p>
                <input type="file" name="archivo_csv" accept=".csv" required style="display: block; margin: 0 auto; padding: 10px;">
                <p style="color: #666; margin-top: 10px;">Solo archivos CSV con el formato: nombre,apellidos,contraseña,correo_personal,semestre,clave</p>
            </div>

            <div style="text-align: center;">
                <button type="submit" name="importar_csv" class="btn btn-primary">Importar desde CSV</button>
                <a href="../coordinador.php?seccion=alumnos" class="btn btn-secondary">Volver al Panel</a>
            </div>
        </form>
    </div>

    <!-- MOSTRAR RESULTADOS CSV -->
    <?php if (isset($alumnos_creados_csv) && !empty($alumnos_creados_csv)): ?>
    <div class="resultado-csv">
        <h3>📊 Resultado de Importación CSV</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 0.9em; color: #666;">Alumnos Creados</div>
                <div style="font-size: 2em; font-weight: bold; color: #2e7d32;"><?php echo $total_insertados; ?></div>
            </div>
            <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 0.9em; color: #666;">Errores</div>
                <div style="font-size: 2em; font-weight: bold; color: #c62828;"><?php echo $total_errores; ?></div>
            </div>
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 0.9em; color: #666;">Correos Enviados</div>
                <div style="font-size: 2em; font-weight: bold; color: #1565c0;"><?php echo $correos_enviados; ?></div>
            </div>
        </div>
        
        <!-- Mostrar algunos alumnos de ejemplo -->
        <div style="margin: 20px 0;">
            <h4 style="color: #1565c0; margin-bottom: 15px;">📝 Alumnos Creados (primeros 3):</h4>
            <?php for ($i = 0; $i < min(3, count($alumnos_creados_csv)); $i++): ?>
                <div style="background: white; padding: 10px; margin-bottom: 10px; border-radius: 5px; border-left: 4px solid #2196f3;">
                    <strong><?php echo $alumnos_creados_csv[$i]['clave']; ?></strong> 
                    <?php if ($alumnos_creados_csv[$i]['clave_existente_usada']): ?>
                        <span class="clave-existente">Clave existente</span>
                    <?php endif; ?>
                    - <?php echo $alumnos_creados_csv[$i]['nombre']; ?> <?php echo $alumnos_creados_csv[$i]['apellidos']; ?>
                    (Contraseña: <?php echo $alumnos_creados_csv[$i]['contraseña']; ?>)
                    <?php if (!empty($alumnos_creados_csv[$i]['correo_personal'])): ?>
                        <?php if ($alumnos_creados_csv[$i]['correo_enviado']): ?>
                            <span style="color: #4caf50; font-weight: bold;"> ✓ Correo enviado a: <?php echo $alumnos_creados_csv[$i]['correo_personal']; ?></span>
                        <?php else: ?>
                            <span style="color: #f57c00; font-weight: bold;"> ❌ Correo no enviado (inválido): <?php echo $alumnos_creados_csv[$i]['correo_personal']; ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #666; font-style: italic;"> ℹ️ Sin correo personal</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            <?php if (count($alumnos_creados_csv) > 3): ?>
                <div style="text-align: center; color: #666; font-style: italic;">
                    ... y <?php echo count($alumnos_creados_csv) - 3; ?> alumnos más
                </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="generar_pdf_csv" value="1">
                <button type="submit" class="btn btn-primary">📄 Descargar PDF Completo</button>
            </form>
            <a href="crear_alumno.php" class="btn btn-secondary">➕ Crear Más Alumnos</a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>