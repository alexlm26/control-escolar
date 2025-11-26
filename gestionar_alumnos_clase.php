<?php
session_start();
include "../conexion.php";

// Restringir acceso a rol 3 (coordinador)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != '3') {
    header("Location: ../index.php");
    exit;
}

$id_clase = isset($_GET['id_clase']) ? intval($_GET['id_clase']) : 0;
if (!$id_clase) {
    header("Location: ../coordinador.php?seccion=clases&error=sin_clase");
    exit;
}

/* ---------------------------------------------------------
   CONSULTAS BASE
--------------------------------------------------------- */

// Obtener información de la clase (INCLUYE id_materia)
$sql_clase = "
    SELECT c.id_materia, m.nombre as materia, m.id_especialidad,s.nombre as salon, s.edificio 
    FROM clase c 
    INNER JOIN materia m ON c.id_materia = m.id_materia 
    INNER JOIN salon s ON c.id_salon = s.id_salon 
    WHERE c.id_clase = ?
";
$stmt = $conexion->prepare($sql_clase);
$stmt->bind_param("i", $id_clase);
$stmt->execute();
$clase_info = $stmt->get_result()->fetch_assoc();

$id_materia = $clase_info['id_materia'] ?? null;

/* ---------------------------------------------------------
   FUNCIÓN RECURSIVA PARA VERIFICAR CADENA DE PRERREQUISITOS
--------------------------------------------------------- */
function verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia_actual, &$pendientes, $nivel = 0) {
    if ($nivel > 10) return; // Prevenir recursión infinita
    
    // Obtener prerrequisito de la materia actual
    $sql_prerreq = "SELECT id_prerrequisito FROM materia WHERE id_materia = ?";
    $stmt = $conexion->prepare($sql_prerreq);
    $stmt->bind_param("i", $id_materia_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $materia = $result->fetch_assoc();
        $id_prerrequisito = $materia['id_prerrequisito'];
        
        if ($id_prerrequisito) {
            // Verificar si el alumno aprobó este prerrequisito
            $sql_aprobado = "SELECT 1 FROM materia_cursada 
                            WHERE id_alumno = ? AND id_materia = ? AND aprobado = 1";
            $stmt_aprob = $conexion->prepare($sql_aprobado);
            $stmt_aprob->bind_param("ii", $id_alumno, $id_prerrequisito);
            $stmt_aprob->execute();
            $aprobado = $stmt_aprob->get_result()->num_rows > 0;
            
            if (!$aprobado) {
                // Obtener nombre de la materia pendiente
                $sql_nombre = "SELECT nombre FROM materia WHERE id_materia = ?";
                $stmt_nombre = $conexion->prepare($sql_nombre);
                $stmt_nombre->bind_param("i", $id_prerrequisito);
                $stmt_nombre->execute();
                $nombre_materia = $stmt_nombre->get_result()->fetch_assoc()['nombre'];
                
                $pendientes[] = $nombre_materia;
            }
            
            // Verificar recursivamente el prerrequisito del prerrequisito
            verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_prerrequisito, $pendientes, $nivel + 1);
        }
    }
}

function verificarCadenaPrerrequisitos($conexion, $id_alumno, $id_materia) {
    $prerrequisitos_pendientes = [];
    verificarPrerrequisitoRecursivo($conexion, $id_alumno, $id_materia, $prerrequisitos_pendientes);
    return $prerrequisitos_pendientes;
}

/* ---------------------------------------------------------
   Alumnos en la clase
--------------------------------------------------------- */
$sql_alumnos_clase = "
    SELECT a.id_alumno, u.nombre, u.apellidos, u.clave ,asig.oportunidad
    FROM asignacion asig 
    INNER JOIN alumno a ON asig.id_alumno = a.id_alumno 
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
    WHERE asig.id_clase = ?
    ORDER BY u.apellidos, u.nombre
";
$stmt = $conexion->prepare($sql_alumnos_clase);
$stmt->bind_param("i", $id_clase);
$stmt->execute();
$alumnos_clase = $stmt->get_result();

/* ---------------------------------------------------------
   Alumnos disponibles (NO han aprobado la materia y CUMPLEN con prerrequisitos y ESPECIALIDAD)
--------------------------------------------------------- */
$sql_alumnos_disponibles = "
    SELECT 
        a.id_alumno, 
        u.nombre, 
        u.apellidos, 
        u.clave,
        a.id_especialidad,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM materia_cursada mc 
                WHERE mc.id_alumno = a.id_alumno 
                AND mc.id_materia = ? 
                AND mc.aprobado = 1
            ) THEN 'aprobado'
            WHEN EXISTS (
                SELECT 1 FROM materia_cursada mc 
                WHERE mc.id_alumno = a.id_alumno 
                AND mc.id_materia = ? 
                AND mc.aprobado = 0
            ) THEN 'reprobado'
            ELSE 'nunca_cursado'
        END as estado_materia,
        (
            SELECT mc.oportunidad 
            FROM materia_cursada mc 
            WHERE mc.id_alumno = a.id_alumno 
            AND mc.id_materia = ? 
            ORDER BY mc.id_materia_cursada DESC 
            LIMIT 1
        ) as ultima_oportunidad
    FROM alumno a 
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario 
    WHERE a.id_alumno NOT IN (SELECT id_alumno FROM asignacion WHERE id_clase = ?)
    AND u.id_carrera = (SELECT id_carrera FROM usuario WHERE id_usuario = ?)
    AND a.estado = '1'
    AND NOT EXISTS (
        SELECT 1 FROM materia_cursada mc 
        WHERE mc.id_alumno = a.id_alumno 
        AND mc.id_materia = ? 
        AND mc.aprobado = 1
    )
    AND (
        -- El alumno puede tomar la materia si:
        -- 1. La materia es de especialidad general (id_especialidad = 1)
        (SELECT id_especialidad FROM materia WHERE id_materia = ?) = 1
        OR 
        -- 2. El alumno tiene la misma especialidad que la materia
        a.id_especialidad = (SELECT id_especialidad FROM materia WHERE id_materia = ?)
    )
    ORDER BY u.apellidos, u.nombre
";
$stmt = $conexion->prepare($sql_alumnos_disponibles);
$stmt->bind_param(
    "iiiiiiii",
    $id_materia,    // estado_materia (aprobado)
    $id_materia,    // estado_materia (reprobado)
    $id_materia,    // ultima_oportunidad
    $id_clase,      // NOT IN asignacion
    $_SESSION['id_usuario'], // misma carrera
    $id_materia,    // NOT EXISTS aprobado
    $id_materia,    // especialidad materia = 1
    $id_materia     // especialidad alumno = especialidad materia
);
$stmt->execute();
$alumnos_disponibles_rs = $stmt->get_result();

/* Construir array asociativo por 'clave' con validación de prerrequisitos */
$alumnos_disponibles = [];
while ($row = $alumnos_disponibles_rs->fetch_assoc()) {
    $clave = trim($row['clave']);
    
    // Verificar cadena de prerrequisitos
    $prerrequisitos_pendientes = verificarCadenaPrerrequisitos($conexion, $row['id_alumno'], $id_materia);
    
    $alumnos_disponibles[$clave] = [
        'id_alumno' => $row['id_alumno'],
        'nombre' => $row['nombre'],
        'apellidos' => $row['apellidos'],
        'estado_materia' => $row['estado_materia'],
        'ultima_oportunidad' => $row['ultima_oportunidad'],
        'prerrequisitos_pendientes' => $prerrequisitos_pendientes,
        'cumple_prerrequisitos' => empty($prerrequisitos_pendientes)
    ];
}

/* ---------------------------------------------------------
   Función recursiva para determinar la siguiente oportunidad
--------------------------------------------------------- */
function obtenerSiguienteOportunidad($ultima_oportunidad, $nivel = 0) {
    // Caso base: si es la primera vez o no hay oportunidad previa
    if (empty($ultima_oportunidad) || $ultima_oportunidad === 'null' || $ultima_oportunidad === '') {
        return 'Ordinario';
    }
    
    $ultima = strtolower(trim((string)$ultima_oportunidad));
    
    // Límite de recursión para evitar bucles infinitos
    if ($nivel > 10) {
        return 'Global'; // Caso de seguridad
    }
    
    // Mapeo de oportunidades
    $oportunidades = [
        'ordinario' => 'Recurse',
        'recurse' => 'Especial', 
        'recursamiento' => 'Especial',
        'especial' => 'Global',
        'global' => 'Global' // Caso terminal
    ];
    
    $siguiente = $oportunidades[$ultima] ?? 'Ordinario';
    
    // Si la siguiente es la misma que la actual, retornamos (caso terminal)
    if ($siguiente === $ultima_oportunidad) {
        return $siguiente;
    }
    
    // Llamada recursiva para casos más complejos
    return obtenerSiguienteOportunidad($siguiente, $nivel + 1);
}

/* ---------------------------------------------------------
   PROCESAR FORMULARIO
--------------------------------------------------------- */
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ELIMINAR alumno
    if (isset($_POST['eliminar_alumno'])) {
        $id_alumno = intval($_POST['id_alumno'] ?? 0);
        if ($id_alumno) {
            $del = $conexion->prepare("DELETE FROM asignacion WHERE id_clase = ? AND id_alumno = ?");
            $del->bind_param("ii", $id_clase, $id_alumno);
            if ($del->execute()) {
                $_SESSION['success'] = "Alumno eliminado correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar alumno.";
            }
            header("Location: ?id_clase=" . $id_clase);
            exit;
        }
    }

    // AGREGAR alumno manual
    if (isset($_POST['agregar_alumno'])) {
        $id_alumno = intval($_POST['id_alumno'] ?? 0);
        $oportunidad = $_POST['oportunidad'] ?? 'Ordinario';
        if ($id_alumno) {
            $alumno_valido = false;
            foreach ($alumnos_disponibles as $clave => $info) {
                if ($info['id_alumno'] == $id_alumno && $info['cumple_prerrequisitos']) {
                    $alumno_valido = true;
                    break;
                }
            }
            
            if ($alumno_valido) {
                $ins = $conexion->prepare("INSERT INTO asignacion (id_clase, id_alumno, oportunidad) VALUES (?, ?, ?)");
                $ins->bind_param("iis", $id_clase, $id_alumno, $oportunidad);
                if ($ins->execute()) {
                    $_SESSION['success'] = "Alumno agregado correctamente.";
                } else {
                    $_SESSION['error'] = "Error al agregar alumno.";
                }
            } else {
                $_SESSION['error'] = "El alumno no cumple con los prerrequisitos necesarios.";
            }
            header("Location: ?id_clase=" . $id_clase);
            exit;
        }
    }

    /* ---------------------------------------------------------
       IMPORTAR desde EXCEL
    --------------------------------------------------------- */
    if (isset($_POST['importar_excel'])) {
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Error al subir el archivo.";
            header("Location: ?id_clase=" . $id_clase);
            exit;
        } else {
            require_once('../PhpSpreadsheet/IOFactory.php');

            $tmp = $_FILES['archivo_excel']['tmp_name'];

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);

                $insertados = 0;
                $omitidos_no_en_lista = 0;
                $omitidos_ya_asignados = 0;
                $omitidos_prerrequisitos = 0;
                $errores_insert = 0;

                $ins_stmt = $conexion->prepare("INSERT INTO asignacion (id_clase, id_alumno, oportunidad) VALUES (?, ?, ?)");

                foreach ($rows as $r) {
                    $clave_raw = $r['A'] ?? null;
                    if (is_null($clave_raw)) continue;
                    $clave = trim((string)$clave_raw);
                    if ($clave === '') continue;
                    $clave_norm = preg_replace('/\s+/', '', $clave);

                    if (!is_numeric($clave_norm)) {
                        $omitidos_no_en_lista++;
                        continue;
                    }

                    if (!isset($alumnos_disponibles[$clave_norm])) {
                        $omitidos_no_en_lista++;
                        continue;
                    }

                    // Verificar prerrequisitos
                    if (!$alumnos_disponibles[$clave_norm]['cumple_prerrequisitos']) {
                        $omitidos_prerrequisitos++;
                        continue;
                    }

                    $id_alumno_para_insert = $alumnos_disponibles[$clave_norm]['id_alumno'];

                    $chk = $conexion->prepare("SELECT 1 FROM asignacion WHERE id_clase = ? AND id_alumno = ?");
                    $chk->bind_param("ii", $id_clase, $id_alumno_para_insert);
                    $chk->execute();
                    $chk->store_result();
                    if ($chk->num_rows > 0) {
                        $omitidos_ya_asignados++;
                        continue;
                    }

                    $ultima_opp = $alumnos_disponibles[$clave_norm]['ultima_oportunidad'];
                    $oportunidad_final = obtenerSiguienteOportunidad($ultima_opp);

                    $ins_stmt->bind_param("iis", $id_clase, $id_alumno_para_insert, $oportunidad_final);
                    if ($ins_stmt->execute()) {
                        $insertados++;
                        unset($alumnos_disponibles[$clave_norm]);
                    } else {
                        $errores_insert++;
                    }
                }

                $_SESSION['success'] = "Importación finalizada. Insertados: $insertados";
                if ($omitidos_no_en_lista > 0) {
                    $_SESSION['info'] = "Omitidos (no en lista): $omitidos_no_en_lista";
                }
                if ($omitidos_prerrequisitos > 0) {
                    $_SESSION['warning'] = "Omitidos (prerrequisitos pendientes): $omitidos_prerrequisitos";
                }
                if ($errores_insert > 0) {
                    $_SESSION['error'] = "Errores al insertar: $errores_insert";
                }

               header("Location: ?id_clase=" . $id_clase);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = "Error procesando Excel: " . $e->getMessage();
                header("Location: ?id_clase=" . $id_clase);
                exit;
            }
        }
    }
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
if (isset($_SESSION['info'])) {
    $mensaje .= '<div class="alert alert-info">' . $_SESSION['info'] . '</div>';
    unset($_SESSION['info']);
}
if (isset($_SESSION['warning'])) {
    $mensaje .= '<div class="alert alert-warning">' . $_SESSION['warning'] . '</div>';
    unset($_SESSION['warning']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Alumnos - Sistema Escolar</title>
    <style>
:root {
    --color-primario: #1565c0;
    --color-secundario: #1976d2;
    --color-fondo: #f4f6f8;
    --color-texto: #333;
    --color-blanco: #fff;
    --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
    --sombra-hover: 0 8px 18px rgba(0,0,0,0.15);
    --radio-borde: 14px;
}

* {
    box-sizing: border-box;
}

body {
    background: var(--color-fondo);
    font-family: "Poppins", "Segoe UI", sans-serif;
    color: var(--color-texto);
    margin: 0;
    padding: 0;
}

.content {
    padding: 20px 5%;
    max-width: 1200px;
    margin: auto;
}

/* ALERTAS */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: var(--radio-borde);
    font-weight: 600;
    box-shadow: var(--sombra-suave);
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

.alert-info {
    background: #e3f2fd;
    color: #1565c0;
    border-left: 4px solid #2196f3;
}

/* BOTONES */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-size: 0.9em;
}

.btn-primary {
    background: var(--color-primario);
    color: white;
}

.btn-primary:hover {
    background: var(--color-secundario);
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875em;
}

/* TARJETAS Y CONTENEDORES */
.card {
    background: white;
    border-radius: var(--radio-borde);
    padding: 25px;
    box-shadow: var(--sombra-suave);
    margin-bottom: 25px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: var(--sombra-hover);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.card-header h2 {
    margin: 0;
    color: var(--color-primario);
    font-size: 1.5em;
}

/* TABLAS */
.table-container {
    overflow-x: auto;
    border-radius: var(--radio-borde);
    box-shadow: var(--sombra-suave);
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 600px;
}

.table th,
.table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.table th {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
}

.table tr:hover {
    background: #f8f9fa;
}

/* BADGES DE OPORTUNIDAD */
.oportunidad-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.oportunidad-ordinario {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #1976d2;
}

.oportunidad-recurse {
    background: #fff3e0;
    color: #f57c00;
    border: 1px solid #f57c00;
}

.oportunidad-especial {
    background: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #7b1fa2;
}

.oportunidad-global {
    background: #ffebee;
    color: #d32f2f;
    border: 1px solid #d32f2f;
}

/* FORMULARIOS */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--color-primario);
}

.select-oportunidad {
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    background: white;
    font-size: 0.9em;
    cursor: pointer;
    min-width: 120px;
}

.estado-materia {
    font-size: 0.85em;
    color: #666;
    font-style: italic;
}

/* HEADER DE CLASE */
.clase-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 25px;
    border-radius: var(--radio-borde);
    margin-bottom: 25px;
    box-shadow: var(--sombra-suave);
}

.clase-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
    font-weight: 700;
}

.clase-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.info-card {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.info-card .label {
    font-size: 0.9em;
    opacity: 0.8;
    margin-bottom: 5px;
}

.info-card .value {
    font-size: 1.1em;
    font-weight: 600;
}

/* UPLOAD STYLES */
.upload-area {
    border: 2px dashed #e0e0e0;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    margin: 20px 0;
    transition: all 0.3s ease;
    background: #fafafa;
}

.upload-area:hover {
    border-color: var(--color-primario);
    background: #f0f8ff;
}

.upload-area input[type="file"] {
    margin: 15px 0;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    width: 100%;
    max-width: 400px;
}

/* ESTILOS PARA PRERREQUISITOS */
.prerrequisito-pendiente {
    background: #fff3e0;
    color: #e65100;
    padding: 8px 12px;
    border-radius: 6px;
    margin: 5px 0;
    font-size: 0.85em;
    border-left: 3px solid #ff9800;
}

.prerrequisito-cumplido {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 8px 12px;
    border-radius: 6px;
    margin: 5px 0;
    font-size: 0.85em;
    border-left: 3px solid #4caf50;
}

.btn-disabled {
    background: #b0bec5 !important;
    color: #78909c !important;
    cursor: not-allowed !important;
    transform: none !important;
}

.btn-disabled:hover {
    background: #b0bec5 !important;
    transform: none !important;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .content {
        padding: 15px 3%;
    }
    
    .clase-header {
        padding: 20px;
    }
    
    .clase-header h1 {
        font-size: 1.5em;
    }
    
    .card {
        padding: 20px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .table th,
    .table td {
        padding: 8px 10px;
        font-size: 0.9em;
    }
    
    .btn {
        padding: 8px 16px;
        font-size: 0.85em;
    }
}

@media (max-width: 480px) {
    .content {
        padding: 10px 2%;
    }
    
    .clase-header {
        padding: 15px;
    }
    
    .clase-info {
        grid-template-columns: 1fr;
    }
    
    .card {
        padding: 15px;
    }
    
    .table {
        min-width: 500px;
    }
}
    </style>
</head>
<body>

<div class="content">
    <!-- HEADER DE LA CLASE -->
    <div class="clase-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1>Gestionar Alumnos</h1>
                <p><?php echo htmlspecialchars($clase_info['materia'] ?? 'Clase'); ?></p>
            </div>
            <div style="text-align: right;">
                <a href="../coordinador.php?seccion=clases" class="btn btn-secondary">
                    ← Volver al Panel
                </a>
            </div>
        </div>
        
        <div class="clase-info">
            <div class="info-card">
                <div class="label">Materia</div>
                <div class="value"><?php echo htmlspecialchars($clase_info['materia'] ?? ''); ?></div>
            </div>
            <div class="info-card">
                <div class="label">Salón</div>
                <div class="value"><?php echo htmlspecialchars($clase_info['salon'] ?? ''); ?></div>
            </div>
            <div class="info-card">
                <div class="label">Edificio</div>
                <div class="value"><?php echo htmlspecialchars($clase_info['edificio'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <?php echo $mensaje; ?>

    <!-- ALUMNOS EN LA CLASE -->
    <div class="card">
        <div class="card-header">
            <h2>Alumnos en la Clase</h2>
            <span class="badge" style="background: #e3f2fd; color: #1976d2; padding: 8px 15px; border-radius: 20px; font-weight: 600;">
                <?php echo $alumnos_clase->num_rows; ?> alumnos
            </span>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Matrícula</th>
                        <th>Oportunidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alumnos_clase->num_rows > 0): ?>
                        <?php while($a = $alumnos_clase->fetch_assoc()): 
                            $oportunidad_class = 'oportunidad-' . strtolower($a['oportunidad']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['nombre'] . ' ' . $a['apellidos']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($a['clave']); ?></td>
                                <td>
                                    <span class="oportunidad-badge <?php echo $oportunidad_class; ?>">
                                        <?php echo htmlspecialchars($a['oportunidad']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_alumno" value="<?php echo intval($a['id_alumno']); ?>">
                                        <button type="submit" name="eliminar_alumno" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('¿Estás seguro de eliminar a este alumno de la clase?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #666;">
                                <p style="margin: 0; font-size: 1.1em;">No hay alumnos en esta clase</p>
                                <p style="margin: 10px 0 0 0; font-size: 0.9em;">Agrega alumnos usando las opciones de abajo</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AGREGAR ALUMNOS -->
    <div class="card">
        <div class="card-header">
            <h2>Agregar Alumnos</h2>
            <small style="color: #666; font-style: italic;">Solo se muestran alumnos que cumplen con la cadena de prerrequisitos</small>
        </div>

        <!-- IMPORTAR DESDE EXCEL -->
        <div style="margin-bottom: 30px;">
            <h3 style="color: var(--color-primario); margin-bottom: 15px;">Importar</h3>
            <div class="upload-area">
                <form method="POST" enctype="multipart/form-data">
                    <p style="margin: 0 0 15px 0; font-weight: 600;">Sube un archivo Excel con las matrículas de los alumnos</p>
                    <p style="margin: 0 0 20px 0; font-size: 0.9em; color: #666;">
                        <strong>Formato requerido:</strong> Columna A = Matrícula (solo números)<br>
                        <strong>Solo se importarán alumnos que cumplan con los prerrequisitos</strong>
                    </p>
                    <input type="file" name="archivo_excel" accept=".xlsx,.xls,.csv" required 
                           style="margin: 15px auto; display: block;">
                    <button type="submit" name="importar_excel" class="btn btn-primary">
                        Importar desde .CVS
                    </button>
                </form>
            </div>
        </div>

        <!-- AGREGAR MANUALMENTE -->
        <div>
            <h3 style="color: var(--color-primario); margin-bottom: 15px;">Agregar Manualmente</h3>
            
            <?php 
            $alumnos_cumplen_prerrequisitos = array_filter($alumnos_disponibles, function($alumno) {
                return $alumno['cumple_prerrequisitos'];
            });
            ?>
            
            <?php if (count($alumnos_cumplen_prerrequisitos) > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Matrícula</th>
                                <th>Estado</th>
                                <th>Prerrequisitos</th>
                                <th>Oportunidad</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos_cumplen_prerrequisitos as $clave => $info): 
                                $estado_text = '';
                                $oportunidad_default = 'Ordinario';
                                
                                if ($info['estado_materia'] == 'reprobado') {
                                    $estado_text = '<span class="estado-materia">Reprobó anteriormente</span>';
                                    $oportunidad_default = obtenerSiguienteOportunidad($info['ultima_oportunidad']);
                                } elseif ($info['estado_materia'] == 'nunca_cursado') {
                                    $estado_text = '<span class="estado-materia" style="color: #4caf50;">Primera vez</span>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($info['nombre'] . ' ' . $info['apellidos']); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($clave); ?></td>
                                    <td><?php echo $estado_text; ?></td>
                                    <td>
                                        <div class="prerrequisito-cumplido">
                                            Cumple con todos los prerrequisitos
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <select name="oportunidad" class="select-oportunidad">
                                                <option value="Ordinario" <?php echo ($oportunidad_default == 'Ordinario' ? 'selected' : ''); ?>>Ordinario</option>
                                                <option value="Recurse" <?php echo ($oportunidad_default == 'Recurse' ? 'selected' : ''); ?>>Recurse</option>
                                                <option value="Especial" <?php echo ($oportunidad_default == 'Especial' ? 'selected' : ''); ?>>Especial</option>
                                                <option value="Global" <?php echo ($oportunidad_default == 'Global' ? 'selected' : ''); ?>>Global</option>
                                            </select>
                                    </td>
                                    <td>
                                            <input type="hidden" name="id_alumno" value="<?php echo intval($info['id_alumno']); ?>">
                                            <button type="submit" name="agregar_alumno" class="btn btn-success btn-sm">
                                                Agregar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p style="margin: 0; font-size: 1.1em;">No hay alumnos disponibles que cumplan con los prerrequisitos</p>
                    <p style="margin: 10px 0 0 0; font-size: 0.9em;">Todos los alumnos tienen prerrequisitos pendientes o ya están en la clase</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ALUMNOS CON PRERREQUISITOS PENDIENTES (SOLO INFORMATIVO) -->
        <?php 
        $alumnos_pendientes = array_filter($alumnos_disponibles, function($alumno) {
            return !$alumno['cumple_prerrequisitos'];
        });
        ?>
        
        <?php if (count($alumnos_pendientes) > 0): ?>
        <div style="margin-top: 30px;">
            <h3 style="color: #f57c00; margin-bottom: 15px;">⚠️ Alumnos con Prerrequisitos Pendientes</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Matrícula</th>
                            <th>Prerrequisitos Pendientes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos_pendientes as $clave => $info): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($info['nombre'] . ' ' . $info['apellidos']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($clave); ?></td>
                                <td>
                                    <?php foreach ($info['prerrequisitos_pendientes'] as $prerreq): ?>
                                        <div class="prerrequisito-pendiente">
                                            ❌ <?php echo htmlspecialchars($prerreq); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Mejoras de UX
document.addEventListener('DOMContentLoaded', function() {
    // Confirmación mejorada para eliminaciones
    const deleteButtons = document.querySelectorAll('button[name="eliminar_alumno"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres eliminar a este alumno de la clase?\n\nEsta acción no se puede deshacer.')) {
                e.preventDefault();
            }
        });
    });
    
    // Feedback visual para importación
    const importForm = document.querySelector('form[enctype="multipart/form-data"]');
    if (importForm) {
        importForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ Procesando...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });
    }
});
</script>
</body>
</html>