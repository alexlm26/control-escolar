<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != '3') {
    header("Location: login.php");
    exit;
}

// Obtener carreras disponibles (excluyendo id_carrera = 0)
$carreras = $conexion->query("
    SELECT id_carrera, nombre
    FROM carrera 
    WHERE id_carrera != 0 
    ORDER BY nombre
");

$id_carrera_seleccionada = $_POST['id_carrera'] ?? '';
$materias_por_semestre = [];
$carrera_info = null;

if ($id_carrera_seleccionada) {
    // Obtener información de la carrera seleccionada
    $stmt = $conexion->prepare("SELECT nombre FROM carrera WHERE id_carrera = ?");
    $stmt->bind_param("i", $id_carrera_seleccionada);
    $stmt->execute();
    $carrera_info = $stmt->get_result()->fetch_assoc();

    // Obtener todas las materias de la carrera ordenadas por semestre_sugerido
    $stmt = $conexion->prepare("
        SELECT m.id_materia, m.nombre, m.semestre_sugerido as semestre, m.unidades, m.creditos, 
               m.id_prerrequisito, prerreq.nombre as nombre_prerrequisito,
               prerreq.semestre_sugerido as semestre_prerrequisito
        FROM materia m
        LEFT JOIN materia prerreq ON m.id_prerrequisito = prerreq.id_materia
        WHERE m.id_carrera = ?
        ORDER BY m.semestre_sugerido, m.nombre
    ");
    $stmt->bind_param("i", $id_carrera_seleccionada);
    $stmt->execute();
    $result = $stmt->get_result();

    // Organizar materias por semestre_sugerido
    while ($materia = $result->fetch_assoc()) {
        $semestre = $materia['semestre'];
        if (!isset($materias_por_semestre[$semestre])) {
            $materias_por_semestre[$semestre] = [];
        }
        // Inicializar cadena_dependientes como array vacío
        $materia['cadena_dependientes'] = [];
        $materias_por_semestre[$semestre][] = $materia;
    }

    // Función CORREGIDA para encontrar todas las materias que dependen de una materia específica
    function obtenerMateriasDependientes($conexion, $id_materia, $id_carrera, $profundidad = 0) {
        // Prevenir recursión infinita
        if ($profundidad > 10) {
            return [];
        }
        
        $dependientes = [];
        $stmt = $conexion->prepare("
            SELECT id_materia, nombre, semestre_sugerido as semestre 
            FROM materia 
            WHERE id_prerrequisito = ? AND id_carrera = ?
        ");
        $stmt->bind_param("ii", $id_materia, $id_carrera);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($materia = $result->fetch_assoc()) {
            // Agregar la materia dependiente directa
            $dependientes[] = $materia;
            
            // Recursivamente buscar dependientes de los dependientes (cadena completa)
            $dependientes_indirectas = obtenerMateriasDependientes($conexion, $materia['id_materia'], $id_carrera, $profundidad + 1);
            
            // Combinar todas las dependientes
            foreach ($dependientes_indirectas as $indirecta) {
                // Evitar duplicados
                $existe = false;
                foreach ($dependientes as $existente) {
                    if ($existente['id_materia'] == $indirecta['id_materia']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $dependientes[] = $indirecta;
                }
            }
        }
        
        return $dependientes;
    }

    // Para cada materia, encontrar su cadena completa
    foreach ($materias_por_semestre as $semestre => &$materias) {
        foreach ($materias as &$materia) {
            $materia['cadena_dependientes'] = obtenerMateriasDependientes($conexion, $materia['id_materia'], $id_carrera_seleccionada);
        }
    }
    unset($materias); // Liberar referencia
}

// Función para generar PDF
function generarPDFKardex($materias_por_semestre, $carrera_info, $conexion) {
    require_once('tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurar documento
    $pdf->SetCreator('Sistema Escolar ITSUR');
    $pdf->SetAuthor('Coordinador');
    $pdf->SetTitle('Kardex Académico - ' . $carrera_info['nombre']);
    $pdf->SetSubject('Plan de Estudios');
    
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Primera página - Encabezado y resumen
    $pdf->AddPage();
    
    /* ---------------------------------------------------------
       ENCABEZADO PROFESIONAL
    --------------------------------------------------------- */
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, "INSTITUTO TECNOLÓGICO SUPERIOR DEL SUR DE GUANAJUATO", 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, "KARDEX ACADÉMICO - PLAN DE ESTUDIOS", 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, $carrera_info['nombre'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "Documento generado el: " . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    /* ---------------------------------------------------------
       RESUMEN DE LA CARRERA
    --------------------------------------------------------- */
    $total_materias = 0;
    $total_creditos = 0;
    $total_semestres = count($materias_por_semestre);
    
    foreach ($materias_por_semestre as $semestre => $materias) {
        $total_materias += count($materias);
        foreach ($materias as $materia) {
            $total_creditos += $materia['creditos'];
        }
    }
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'RESUMEN DEL PLAN DE ESTUDIOS', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    
    $resumen_data = [
        'Total de Semestres' => $total_semestres,
        'Total de Materias' => $total_materias,
        'Total de Créditos' => $total_creditos,
        'Fecha de Generación' => date('d/m/Y H:i:s')
    ];
    
    foreach ($resumen_data as $label => $value) {
        $pdf->Cell(60, 6, $label . ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $value, 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    /* ---------------------------------------------------------
       LEYENDA DE SÍMBOLOS
    --------------------------------------------------------- */
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'LEYENDA DE SÍMBOLOS', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    
    $leyenda = [
        '*' => 'Tiene prerrequisito',
        '+' => 'Es prerrequisito de otras materias',
        '<>' => 'Cadena larga de dependencias',
        '@' => 'Prerrequisito recursivo'
    ];
    
    foreach ($leyenda as $simbolo => $descripcion) {
        $pdf->Cell(15, 5, $simbolo, 0, 0, 'L');
        $pdf->Cell(0, 5, $descripcion, 0, 1, 'L');
    }
    
    $pdf->Ln(15);
    
    /* ---------------------------------------------------------
       MATERIAS POR SEMESTRE
    --------------------------------------------------------- */
    ksort($materias_por_semestre);
    
    foreach ($materias_por_semestre as $semestre => $materias) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 400) {
            $pdf->AddPage();
        }
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetFillColor(21, 101, 192);
        $pdf->SetTextColor(255);
        $pdf->Cell(0, 8, "SEMESTRE $semestre", 0, 1, 'C', true);
        $pdf->SetTextColor(0);
        $pdf->Ln(5);
        
        // Cabecera de la tabla
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(15, 6, 'ID', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'MATERIA', 1, 0, 'C', true);
        $pdf->Cell(15, 6, 'UNID.', 1, 0, 'C', true);
        $pdf->Cell(15, 6, 'CRED.', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'PRERREQUISITO', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'CADENA DE DEPENDENCIAS', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 8);
        
        foreach ($materias as $materia) {
            // Determinar símbolos según las relaciones
            $simbolos = '';
            
            if ($materia['id_prerrequisito']) {
                $simbolos .= '*';
            }
            
            // Verificar si cadena_dependientes existe y no está vacío
            $cadena_count = isset($materia['cadena_dependientes']) ? count($materia['cadena_dependientes']) : 0;
            if ($cadena_count > 0) {
                if ($cadena_count > 2) {
                    $simbolos .= '<> ';
                } else {
                    $simbolos .= '+ ';
                }
            }
            
            // Verificar si es prerrequisito recursivo (cadena circular)
            if ($materia['id_prerrequisito'] && isset($materia['cadena_dependientes'])) {
                $dependientes = $materia['cadena_dependientes'];
                foreach ($dependientes as $dep) {
                    if ($dep['id_materia'] == $materia['id_prerrequisito']) {
                        $simbolos .= '@ ';
                        break;
                    }
                }
            }
            
            $pdf->Cell(15, 6, $materia['id_materia'], 1, 0, 'C');
            $pdf->Cell(50, 6, $simbolos . substr($materia['nombre'], 0, 45), 1, 0, 'L');
            $pdf->Cell(15, 6, $materia['unidades'], 1, 0, 'C');
            $pdf->Cell(15, 6, $materia['creditos'], 1, 0, 'C');
            
            // Prerrequisito
            if ($materia['id_prerrequisito']) {
                $pdf->Cell(30, 6, 'Sem ' . $materia['semestre_prerrequisito'], 1, 0, 'C');
            } else {
                $pdf->Cell(30, 6, 'Ninguno', 1, 0, 'C');
            }
            
            // Cadena de dependencias
            $dependencias_text = '';
            if (isset($materia['cadena_dependientes']) && !empty($materia['cadena_dependientes'])) {
                $count = count($materia['cadena_dependientes']);
                $dependencias_text = "$count materia(s)";
                
                // Mostrar las primeras 2 materias dependientes
                $nombres_cortos = [];
                foreach (array_slice($materia['cadena_dependientes'], 0, 2) as $dep) {
                    $nombres_cortos[] = 'Sem ' . $dep['semestre'];
                }
                if ($count > 2) {
                    $nombres_cortos[] = '...';
                }
                $dependencias_text .= ' [' . implode(', ', $nombres_cortos) . ']';
            } else {
                $dependencias_text = 'Ninguna';
            }
            
            $pdf->Cell(50, 6, substr($dependencias_text, 0, 40), 1, 1, 'L');
        }
        
        $pdf->Ln(8);
    }
    
    // Pie de página
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
    
    // Descargar PDF
    $filename = 'kardex_' . preg_replace('/[^a-zA-Z0-9]/', '_', $carrera_info['nombre']) . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// Generar PDF si se solicita
if (isset($_POST['generar_pdf']) && $id_carrera_seleccionada) {
    generarPDFKardex($materias_por_semestre, $carrera_info, $conexion);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex Académico - Sistema Escolar</title>
    <style>
:root {
    --color-primario: #1565c0;
    --color-secundario: #1976d2;
    --color-fondo: #f4f6f8;
    --color-texto: #333;
}

* {
    box-sizing: border-box;
}

body {
    background: var(--color-fondo);
    font-family: "Poppins", "Segoe UI", sans-serif;
    margin: 0;
    padding: 20px;
    color: var(--color-texto);
}

.content {
    max-width: 1200px;
    margin: 0 auto;
}

/* HEADER */
.header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.header h1 {
    margin: 0 0 15px 0;
    font-size: 2.2em;
}

.header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

/* FORMULARIO */
.form-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
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

.form-control {
    width: 100%;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1.1em;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--color-primario);
    outline: none;
    box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
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

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
}

/* RESULTADOS */
.resultados-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.semestre-header {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 20px;
    margin: 0;
    font-size: 1.4em;
}

.materias-grid {
    display: grid;
    gap: 0;
}

.materia-card {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.materia-card:hover {
    background: #f8f9fa;
}

.materia-card:last-child {
    border-bottom: none;
}

.materia-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    gap: 15px;
}

.materia-nombre {
    font-weight: 600;
    font-size: 1.1em;
    color: #333;
    flex: 1;
}

.materia-info {
    display: flex;
    gap: 15px;
    font-size: 0.9em;
    color: #666;
}

.materia-datos {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 0.9em;
}

.materia-datos span {
    margin-right: 15px;
}

.materia-datos strong {
    color: #333;
}

/* SÍMBOLOS Y CADENAS */
.simbolos {
    margin-right: 10px;
}

.cadena-dependencias {
    margin-top: 10px;
    padding: 10px;
    background: #e3f2fd;
    border-radius: 8px;
    border-left: 4px solid #1976d2;
}

.cadena-dependencias strong {
    color: #1565c0;
}

.prerrequisito-info {
    background: #fff3e0;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 8px;
    border-left: 4px solid #ff9800;
}

.dependientes-list {
    margin-top: 5px;
    font-size: 0.9em;
}

.dependiente-item {
    padding: 2px 0;
    color: #555;
}

/* LEYENDA */
.leyenda {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.leyenda h3 {
    margin: 0 0 15px 0;
    color: #28a745;
}

.leyenda-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.leyenda-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.leyenda-simbolo {
    font-size: 1.2em;
}

/* ESTADÍSTICAS */
.estadisticas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.estadistica-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.estadistica-valor {
    font-size: 2em;
    font-weight: bold;
    color: var(--color-primario);
    margin-bottom: 5px;
}

.estadistica-label {
    color: #666;
    font-size: 0.9em;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .content {
        padding: 10px;
    }
    
    .header {
        padding: 20px;
    }
    
    .header h1 {
        font-size: 1.8em;
    }
    
    .form-container {
        padding: 20px;
    }
    
    .materia-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .materia-info {
        flex-wrap: wrap;
    }
    
    .estadisticas {
        grid-template-columns: 1fr;
    }
}

.no-resultados {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-resultados .icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

.debug-info {
    background: #fff3e0;
    padding: 15px;
    border-radius: 8px;
    margin: 10px 0;
    border-left: 4px solid #ff9800;
    font-size: 0.9em;
}
    </style>
</head>
<body>
    <div class="content">
        <!-- HEADER -->
        <div class="header">
            <h1>📚 Kardex Académico</h1>
            <p>Visualiza el plan de estudios completo con cadenas de prerrequisitos</p>
        </div>

        <!-- FORMULARIO DE SELECCIÓN -->
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="id_carrera">Seleccionar Carrera:</label>
                    <select name="id_carrera" id="id_carrera" class="form-control" required>
                        <option value="">-- Selecciona una carrera --</option>
                        <?php while($carrera = $carreras->fetch_assoc()): ?>
                            <option value="<?php echo $carrera['id_carrera']; ?>" 
                                <?php echo ($id_carrera_seleccionada == $carrera['id_carrera']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($carrera['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <button type="submit" class="btn btn-primary">
                        🔍 Ver Kardex
                    </button>
                    
                    <?php if ($id_carrera_seleccionada): ?>
                    <button type="submit" name="generar_pdf" value="1" class="btn btn-success">
                        📄 Generar PDF
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($id_carrera_seleccionada && $carrera_info): ?>
        
        <!-- LEYENDA -->
        <div class="leyenda">
            <h3>📋 Leyenda de Símbolos</h3>
            <div class="leyenda-grid">
                <div class="leyenda-item">
                    <span class="leyenda-simbolo">🔗</span>
                    <span>Tiene prerrequisito</span>
                </div>
                <div class="leyenda-item">
                    <span class="leyenda-simbolo">📚</span>
                    <span>Es prerrequisito de otras materias</span>
                </div>
                <div class="leyenda-item">
                    <span class="leyenda-simbolo">⛓️</span>
                    <span>Cadena larga de dependencias</span>
                </div>
                <div class="leyenda-item">
                    <span class="leyenda-simbolo">🔄</span>
                    <span>Prerrequisito recursivo</span>
                </div>
            </div>
        </div>

        <!-- ESTADÍSTICAS -->
        <?php
        $total_materias = 0;
        $total_creditos = 0;
        $materias_con_prerrequisito = 0;
        $cadenas_largas = 0;
        
        foreach ($materias_por_semestre as $materias) {
            $total_materias += count($materias);
            foreach ($materias as $materia) {
                $total_creditos += $materia['creditos'];
                if ($materia['id_prerrequisito']) {
                    $materias_con_prerrequisito++;
                }
                $cadena_count = isset($materia['cadena_dependientes']) ? count($materia['cadena_dependientes']) : 0;
                if ($cadena_count > 2) {
                    $cadenas_largas++;
                }
            }
        }
        ?>
        
        <div class="estadisticas">
            <div class="estadistica-card">
                <div class="estadistica-valor"><?php echo count($materias_por_semestre); ?></div>
                <div class="estadistica-label">Semestres</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor"><?php echo $total_materias; ?></div>
                <div class="estadistica-label">Total Materias</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor"><?php echo $total_creditos; ?></div>
                <div class="estadistica-label">Créditos Totales</div>
            </div>
            <div class="estadistica-card">
                <div class="estadistica-valor"><?php echo $materias_con_prerrequisito; ?></div>
                <div class="estadistica-label">Con Prerrequisitos</div>
            </div>
        </div>

        <!-- RESULTADOS POR SEMESTRE -->
        <div class="resultados-container">
            <?php ksort($materias_por_semestre); ?>
            <?php foreach ($materias_por_semestre as $semestre => $materias): ?>
                <h2 class="semestre-header">Semestre <?php echo $semestre; ?></h2>
                <div class="materias-grid">
                    <?php foreach ($materias as $materia): ?>
                        <div class="materia-card">
                            <div class="materia-header">
                                <div class="materia-nombre">
                                    <span class="simbolos">
                                        <?php if ($materia['id_prerrequisito']): ?>🔗<?php endif; ?>
                                        <?php 
                                        $cadena_count = isset($materia['cadena_dependientes']) ? count($materia['cadena_dependientes']) : 0;
                                        if ($cadena_count > 0): ?>
                                            <?php if ($cadena_count > 2): ?>⛓️<?php else: ?>📚<?php endif; ?>
                                        <?php endif; ?>
                                        <?php 
                                        // Verificar si es prerrequisito recursivo
                                        if ($materia['id_prerrequisito'] && isset($materia['cadena_dependientes'])) {
                                            foreach ($materia['cadena_dependientes'] as $dep) {
                                                if ($dep['id_materia'] == $materia['id_prerrequisito']) {
                                                    echo '🔄';
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                    </span>
                                    <?php echo htmlspecialchars($materia['nombre']); ?>
                                </div>
                                <div class="materia-info">
                                    <span><strong>ID:</strong> <?php echo $materia['id_materia']; ?></span>
                                    <span><strong>Unid:</strong> <?php echo $materia['unidades']; ?></span>
                                    <span><strong>Cred:</strong> <?php echo $materia['creditos']; ?></span>
                                </div>
                            </div>
                            
                            <div class="materia-datos">
                                <?php if ($materia['id_prerrequisito']): ?>
                                <div class="prerrequisito-info">
                                    <strong>Prerrequisito:</strong> 
                                    <?php echo htmlspecialchars($materia['nombre_prerrequisito']); ?>
                                    (Semestre <?php echo $materia['semestre_prerrequisito']; ?>)
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($materia['cadena_dependientes']) && !empty($materia['cadena_dependientes'])): ?>
                                <div class="cadena-dependencias">
                                    <strong>Es prerrequisito de <?php echo count($materia['cadena_dependientes']); ?> materia(s):</strong>
                                    <div class="dependientes-list">
                                        <?php 
                                        $grupo_semestre = [];
                                        foreach ($materia['cadena_dependientes'] as $dep) {
                                            $sem = $dep['semestre'];
                                            if (!isset($grupo_semestre[$sem])) {
                                                $grupo_semestre[$sem] = [];
                                            }
                                            $grupo_semestre[$sem][] = $dep['nombre'];
                                        }
                                        ksort($grupo_semestre);
                                        foreach ($grupo_semestre as $sem => $nombres): 
                                        ?>
                                            <div class="dependiente-item">
                                                <strong>Sem <?php echo $sem; ?>:</strong> 
                                                <?php echo implode(', ', array_map(function($nombre) {
                                                    return htmlspecialchars($nombre);
                                                }, $nombres)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php elseif ($id_carrera_seleccionada): ?>
        
        <div class="no-resultados">
            <div class="icon">📚</div>
            <h3>No se encontraron materias para esta carrera</h3>
            <p>La carrera seleccionada no tiene materias registradas en el sistema.</p>
        </div>
        
        <?php else: ?>
        
        <div class="no-resultados">
            <div class="icon">🔍</div>
            <h3>Selecciona una carrera</h3>
            <p>Elige una carrera del menú desplegable para visualizar su kardex académico.</p>
        </div>
        
        <?php endif; ?>
    </div>

    <script>
    // Mejoras de UX
    document.addEventListener('DOMContentLoaded', function() {
        const selectCarrera = document.getElementById('id_carrera');
        const btnPDF = document.querySelector('button[name="generar_pdf"]');
        
        if (selectCarrera && btnPDF) {
            selectCarrera.addEventListener('change', function() {
                // Deshabilitar botón PDF hasta que se cargue la nueva carrera
                btnPDF.disabled = true;
                btnPDF.innerHTML = '⏳ Cargando...';
                
                setTimeout(() => {
                    btnPDF.disabled = false;
                    btnPDF.innerHTML = '📄 Generar PDF';
                }, 1000);
            });
        }
        
        // Smooth scroll para materias
        const materiaCards = document.querySelectorAll('.materia-card');
        materiaCards.forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    });
    </script>
</body>
</html>