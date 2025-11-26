<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 2) {
    header("Location: login.php");
    exit;
}

$id_clase = $_GET['id'] ?? 0;
$id_usuario = $_SESSION['id_usuario'];

// Verificar que el profesor tiene acceso a esta clase
$stmt = $conexion->prepare("
    SELECT c.id_clase 
    FROM clase c 
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor 
    WHERE p.id_usuario = ? AND c.id_clase = ?
");
$stmt->bind_param("ii", $id_usuario, $id_clase);
$stmt->execute();
$tiene_acceso = $stmt->get_result()->num_rows > 0;

if (!$tiene_acceso || $id_clase == 0) {
    header("Location: clases.php");
    exit;
}

// Obtener información básica de la clase - CORREGIDO: incluir unidades
$query_clase = $conexion->prepare("
    SELECT 
        c.id_clase,
        m.nombre as materia_nombre,
        m.unidades,
        CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
        car.nombre as carrera_nombre
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN carrera car ON m.id_carrera = car.id_carrera
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario prof ON p.id_usuario = prof.id_usuario
    WHERE c.id_clase = ?
");
$query_clase->bind_param("i", $id_clase);
$query_clase->execute();
$clase_info = $query_clase->get_result()->fetch_assoc();

// Si no se encontraron unidades, establecer un valor por defecto
if (!isset($clase_info['unidades']) || empty($clase_info['unidades'])) {
    $clase_info['unidades'] = 5; // Valor por defecto
}

// Obtener lista de alumnos de la clase
$query_alumnos = $conexion->prepare("
    SELECT 
        a.id_alumno,
        u.id_usuario,
        u.clave as numero_control,
        u.nombre,
        u.apellidos,
        al.semestre,
        al.promedio as promedio_general
    FROM asignacion asig
    INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    INNER JOIN alumno al ON a.id_alumno = al.id_alumno
    WHERE asig.id_clase = ?
    ORDER BY u.apellidos, u.nombre
");
$query_alumnos->bind_param("i", $id_clase);
$query_alumnos->execute();
$alumnos = $query_alumnos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener todas las tareas de la clase agrupadas por unidad (EXCLUYENDO AVISOS)
$query_tareas = $conexion->prepare("
    SELECT 
        id_tarea,
        titulo,
        puntos_maximos,
        fecha_limite,
        unidad
    FROM tareas 
    WHERE id_clase = ? AND puntos_maximos > 0
    ORDER BY unidad ASC, fecha_limite ASC
");
$query_tareas->bind_param("i", $id_clase);
$query_tareas->execute();
$tareas = $query_tareas->get_result()->fetch_all(MYSQLI_ASSOC);
$tareas_por_unidad = [];
foreach ($tareas as $tarea) {
    $unidad = $tarea['unidad'];
    if (!isset($tareas_por_unidad[$unidad])) {
        $tareas_por_unidad[$unidad] = [];
    }
    $tareas_por_unidad[$unidad][] = $tarea;
}

// INICIALIZAR $tareas_por_unidad SI NO HAY TAREAS
if (empty($tareas_por_unidad)) {
    $tareas_por_unidad = [];
    // Crear unidades vacías basadas en las unidades de la materia
    for ($i = 1; $i <= $clase_info['unidades']; $i++) {
        $tareas_por_unidad[$i] = [];
    }
}

// Obtener calificaciones de todos los alumnos
$calificaciones = [];
if (count($alumnos) > 0 && count($tareas) > 0) {
    $alumnos_ids = array_column($alumnos, 'id_alumno');
    $tareas_ids = array_column($tareas, 'id_tarea');
    
    $placeholders_alumnos = implode(',', array_fill(0, count($alumnos_ids), '?'));
    $placeholders_tareas = implode(',', array_fill(0, count($tareas_ids), '?'));
    
    $query_calificaciones = $conexion->prepare("
        SELECT 
            et.id_alumno,
            et.id_tarea,
            et.calificacion,
            et.fecha_entrega
        FROM entregas_tareas et
        WHERE et.id_alumno IN ($placeholders_alumnos) 
        AND et.id_tarea IN ($placeholders_tareas)
    ");
    
    // Combinar parámetros
    $tipos = str_repeat('i', count($alumnos_ids) + count($tareas_ids));
    $parametros = array_merge($alumnos_ids, $tareas_ids);
    
    $query_calificaciones->bind_param($tipos, ...$parametros);
    $query_calificaciones->execute();
    $result_calificaciones = $query_calificaciones->get_result();
    
    while ($calif = $result_calificaciones->fetch_assoc()) {
        $calificaciones[$calif['id_alumno']][$calif['id_tarea']] = $calif;
    }
}

// Calcular promedios por alumno
$promedios_alumnos = [];
$promedios_por_unidad = []; // Array para promedios por unidad

foreach ($alumnos as $alumno) {
    $total_puntos = 0;
    $tareas_calificadas = 0;
    $total_alumno = 0;
    $tareas_calificadas_alumno = 0;
    $puntos_por_unidad = [];

    // Inicializar array para promedios por unidad
    foreach ($tareas_por_unidad as $unidad_num => $tareas_unidad) {
        $puntos_por_unidad[$unidad_num] = ['total' => 0, 'count' => 0];
    }

    // Calcular calificaciones para cada tarea
    foreach ($tareas as $tarea) {
        $calificacion = null;
        
        if (isset($calificaciones[$alumno['id_alumno']][$tarea['id_tarea']])) {
            $calif_data = $calificaciones[$alumno['id_alumno']][$tarea['id_tarea']];
            if ($calif_data['calificacion'] !== null) {
                $calificacion = $calif_data['calificacion'];
                $total_alumno += $calificacion;
                $tareas_calificadas_alumno++;
                
                // Acumular para promedio por unidad
                $puntos_por_unidad[$tarea['unidad']]['total'] += $calificacion;
                $puntos_por_unidad[$tarea['unidad']]['count']++;
            }
        }
    }
    
    $promedios_alumnos[$alumno['id_alumno']] = $tareas_calificadas_alumno > 0 ? 
        round($total_alumno / $tareas_calificadas_alumno, 2) : 0;
    
    // Guardar promedios por unidad para este alumno
    $promedios_por_unidad[$alumno['id_alumno']] = $puntos_por_unidad;
}
?>
<style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
  --color-fondo: #f4f6f8;
  --color-texto: #333;
  --color-blanco: #fff;
  --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
  --radio-borde: 14px;
}

body {
  background: var(--color-fondo);
  font-family: "Poppins", "Segoe UI", sans-serif;
  color: var(--color-texto);
}

.content {
  padding: 40px 5%;
  max-width: 1400px;
  margin: auto;
}

/* HEADER */
.clase-header {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 30px;
  border-radius: var(--radio-borde);
  margin-bottom: 30px;
  box-shadow: var(--sombra-suave);
  text-align: center;
}

.clase-header h1 {
  margin: 0 0 10px 0;
  font-size: 2em;
  font-weight: 700;
}

.clase-header p {
  margin: 5px 0;
  opacity: 0.9;
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

/* TABLA DE CALIFICACIONES */
.tabla-calificaciones-container {
  background: white;
  border-radius: var(--radio-borde);
  overflow: hidden;
  box-shadow: var(--sombra-suave);
  margin-bottom: 30px;
  overflow-x: auto;
}

.tabla-calificaciones {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
  font-size: 0.95em;
}

.tabla-calificaciones th,
.tabla-calificaciones td {
  padding: 12px 8px;
  text-align: center;
  border-bottom: 1px solid #eee;
}

.tabla-calificaciones th {
  background: #f8f9fa;
  font-weight: 600;
  color: var(--color-primario);
  position: sticky;
  top: 0;
}

.tabla-calificaciones thead th {
  background: var(--color-primario);
  color: white;
  border: none;
}

.tabla-calificaciones tbody tr:hover {
  background: #f8f9fa;
}

/* ESTILOS PARA CALIFICACIONES */
.calificacion {
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 15px;
  font-size: 0.85em;
  display: inline-block;
  min-width: 40px;
}

.calificacion-alta {
  background: #e3f2fd;
  color: #1565c0;
  border: 1px solid #bbdefb;
}

.calificacion-media {
  background: #fff3e0;
  color: #f57c00;
  border: 1px solid #ffe0b2;
}

.calificacion-baja {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

.calificacion-sin {
  background: #f5f5f5;
  color: #757575;
  font-style: italic;
  border: 1px solid #e0e0e0;
}

/* INFO ALUMNO */
.info-alumno {
  text-align: left;
  padding-left: 15px !important;
  max-width: 180px;
}

.nombre-alumno {
  font-weight: 600;
  color: #333;
  font-size: 0.9em;
  line-height: 1.3;
}

.detalles-alumno {
  font-size: 0.75em;
  color: #666;
  margin-top: 2px;
  line-height: 1.2;
}

/* PROMEDIOS */
.promedio-final {
  font-weight: 700;
  font-size: 1em;
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 6px 12px;
  border-radius: 15px;
}

/* HEADER TAREAS */
.header-tarea {
  background: #e3f2fd;
  font-weight: 600;
  max-width: 100px;
  min-width: 80px;
  word-wrap: break-word;
}

.tarea-titulo {
  font-size: 0.8em;
  margin-bottom: 3px;
  line-height: 1.2;
}

.tarea-puntos {
  font-size: 0.7em;
  color: #666;
}

/* ESTADÍSTICAS */
.estadisticas-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.estadistica-card {
  background: white;
  padding: 25px;
  border-radius: var(--radio-borde);
  box-shadow: var(--sombra-suave);
  text-align: center;
}

.estadistica-numero {
  font-size: 2.5em;
  font-weight: 700;
  color: var(--color-primario);
  margin-bottom: 10px;
}

.estadistica-label {
  color: #666;
  font-size: 0.9em;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .content {
    padding: 20px 10px;
  }
  
  .clase-header {
    padding: 20px;
  }
  
  .clase-header h1 {
    font-size: 1.5em;
  }
  
  .estadisticas-container {
    grid-template-columns: 1fr;
  }
  
  .tabla-calificaciones-container {
    border-radius: 8px;
  }
  
  .tabla-calificaciones {
    font-size: 0.85em;
  }
}

/* BOTÓN IMPRIMIR */
.btn-imprimir {
  background: #17a2b8;
  color: white;
  margin-left: 10px;
}

.btn-imprimir:hover {
  background: #138496;
}

/* ========== ESTILOS PARA IMPRESIÓN ========== */
@media print {
  @page {
    margin: 0.5cm;
    size: landscape;
  }
  
  body {
    background: white !important;
    font-size: 10pt;
    font-family: "Arial", sans-serif;
    color: black !important;
    margin: 0;
    padding: 0;
  }
  
  .no-print {
    display: none !important;
  }
  
  .content {
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
  }
  
  .clase-header {
    background: white !important;
    color: black !important;
    padding: 15px 0 !important;
    margin-bottom: 15px !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    border-bottom: 2px solid #1565c0;
  }
  
  .clase-header h1 {
    font-size: 16pt !important;
    color: black !important;
    margin-bottom: 5px !important;
  }
  
  .clase-header p {
    font-size: 10pt !important;
    color: #666 !important;
    margin: 2px 0 !important;
  }
  
  .tabla-calificaciones-container {
    box-shadow: none !important;
    border: 1px solid #ddd !important;
    border-radius: 0 !important;
    margin-bottom: 20px !important;
    overflow: visible !important;
  }
  
  .tabla-calificaciones {
    width: 100% !important;
    min-width: auto !important;
    font-size: 8pt !important;
    border-collapse: collapse;
  }
  
  .tabla-calificaciones th,
  .tabla-calificaciones td {
    padding: 6px 4px !important;
    border: 1px solid #ddd !important;
    font-size: 8pt !important;
    line-height: 1.2;
  }
  
  .tabla-calificaciones th {
    background: #f8f9fa !important;
    color: #333 !important;
    font-weight: bold !important;
    border-bottom: 2px solid #1565c0 !important;
  }
  
  .tabla-calificaciones thead th {
    background: #f8f9fa !important;
    color: #333 !important;
    border: 1px solid #ddd !important;
  }
  
  .info-alumno {
    max-width: 120px !important;
    padding-left: 8px !important;
  }
  
  .nombre-alumno {
    font-size: 8pt !important;
    font-weight: bold;
  }
  
  .detalles-alumno {
    font-size: 7pt !important;
  }
  
  .calificacion {
    font-size: 7pt !important;
    padding: 2px 4px !important;
    min-width: 30px !important;
    border: 1px solid #ccc !important;
  }
  
  .header-tarea {
    max-width: 70px !important;
    min-width: 60px !important;
    background: #f8f9fa !important;
  }
  
  .tarea-titulo {
    font-size: 7pt !important;
    font-weight: bold;
  }
  
  .tarea-puntos {
    font-size: 6pt !important;
  }
  
  /* Mantener colores en impresión pero más suaves */
  .calificacion-alta {
    background: #f0f7ff !important;
    color: #1565c0 !important;
  }
  
  .calificacion-media {
    background: #fff8e1 !important;
    color: #f57c00 !important;
  }
  
  .calificacion-baja {
    background: #ffebee !important;
    color: #c62828 !important;
  }
  
  .calificacion-sin {
    background: #fafafa !important;
    color: #757575 !important;
  }
  
  /* Evitar saltos de página dentro de filas */
  .tabla-calificaciones tr {
    page-break-inside: avoid;
    break-inside: avoid;
  }
  
  /* Encabezado de tabla en cada página */
  .tabla-calificaciones thead {
    display: table-header-group;
  }
  
  .tabla-calificaciones th {
    break-inside: avoid;
  }
  
  /* Ajustar altura de filas */
  .tabla-calificaciones tbody tr {
    height: auto !important;
    min-height: 25px;
  }
}

/* Leyenda mejorada para impresión */
.leyenda-impresion {
  display: none;
}

@media print {
  .leyenda-impresion {
    display: block;
    font-size: 7pt;
    text-align: center;
    margin-top: 10px;
    color: #666;
    border-top: 1px solid #ddd;
    padding-top: 5px;
  }
  
  .leyenda-impresion strong {
    font-weight: bold;
  }
  /* ESTILOS PARA PROMEDIOS */
.promedio-final {
    font-size: 1.1em !important;
    font-weight: 700 !important;
    padding: 8px 12px !important;
}

.promedio-unidad {
    background: #f8f9fa !important;
    border-top: 2px solid #1565c0 !important;
}

/* MEJORAR VISUALIZACIÓN DE LA TABLA */
.tabla-calificaciones tr:nth-child(even) {
    background: #fafafa;
}

.tabla-calificaciones tr:hover {
    background: #f0f7ff !important;
}

/* ESTILOS PARA FILAS DE PROMEDIO */
.fila-promedio-unidad {
    background: linear-gradient(135deg, #f8f9fa, #e3f2fd) !important;
    font-weight: 600;
}
}
</style>
<!-- HEADER DE LA CLASE -->
<div class="clase-header">
  <h1>Calificaciones - <?php echo htmlspecialchars($clase_info['materia_nombre']); ?></h1>
  <p>Profesor: <?php echo htmlspecialchars($clase_info['profesor_nombre']); ?></p>
  <p>Carrera: <?php echo htmlspecialchars($clase_info['carrera_nombre']); ?></p>
  
  <div style="margin-top: 20px;" class="no-print">
    <a href="detalle_clase.php?id=<?php echo $id_clase; ?>" class="btn btn-secondary">
       Volver a la Clase
    </a>
    <button onclick="window.print()" class="btn btn-imprimir">
       Imprimir Calificaciones
    </button>
  </div>
</div>

<main class="content">
  <!-- ESTADÍSTICAS RÁPIDAS -->
  <div class="estadisticas-container no-print">
    <div class="estadistica-card">
      <div class="estadistica-numero"><?php echo count($alumnos); ?></div>
      <div class="estadistica-label">Total de Alumnos</div>
    </div>
    <div class="estadistica-card">
      <div class="estadistica-numero"><?php echo count($tareas); ?></div>
      <div class="estadistica-label">Tareas Asignadas</div>
    </div>
    <div class="estadistica-card">
      <div class="estadistica-numero">
        <?php 
          $total_calificadas = 0;
          foreach ($calificaciones as $alumno_califs) {
            $total_calificadas += count($alumno_califs);
          }
          echo $total_calificadas;
        ?>
      </div>
      <div class="estadistica-label">Entregas Calificadas</div>
    </div>
    <div class="estadistica-card">
      <div class="estadistica-numero">
        <?php 
          $promedio_general = count($promedios_alumnos) > 0 ? 
            round(array_sum($promedios_alumnos) / count($promedios_alumnos), 2) : 0;
          echo $promedio_general;
        ?>
      </div>
      <div class="estadistica-label">Promedio General</div>
    </div>
  </div>

  <!-- TABLA DE CALIFICACIONES -->
  <div class="tabla-calificaciones-container">
    <table class="tabla-calificaciones">
<thead>
    <tr>
        <th style="width: 200px;">Alumno</th>
        <?php 
        if (isset($tareas_por_unidad) && is_array($tareas_por_unidad) && count($tareas_por_unidad) > 0): 
            foreach ($tareas_por_unidad as $unidad_num => $tareas_unidad): 
                if (count($tareas_unidad) > 0): ?>
                    <th colspan="<?php echo count($tareas_unidad); ?>" style="background: #0d47a1; color: white; text-align: center;">
                        Unidad <?php echo $unidad_num; ?>
                    </th>
                <?php endif; 
            endforeach; 
        endif; ?>
        <th style="width: 100px;" rowspan="3">Promedio Final</th>
    </tr>
    <tr>
        <th>Alumno</th>
        <?php foreach ($tareas as $tarea): ?>
            <th class="header-tarea">
                <div class="tarea-titulo">U<?php echo $tarea['unidad']; ?>: <?php echo htmlspecialchars($tarea['titulo']); ?></div>
                <div class="tarea-puntos"><?php echo $tarea['puntos_maximos']; ?> pts</div>
            </th>
        <?php endforeach; ?>
    </tr>
    <!-- NUEVA FILA PARA PROMEDIOS POR UNIDAD -->
    <tr>
        <th style="background: #e3f2fd; font-weight: bold;">Prom. Unidad</th>
        <?php 
        if (isset($tareas_por_unidad) && is_array($tareas_por_unidad)):
            foreach ($tareas_por_unidad as $unidad_num => $tareas_unidad): 
                if (count($tareas_unidad) > 0): ?>
                    <th colspan="<?php echo count($tareas_unidad); ?>" style="background: #bbdefb; text-align: center; font-weight: bold;">
                        Prom. U<?php echo $unidad_num; ?>
                    </th>
                <?php endif;
            endforeach;
        endif; ?>
    </tr>
</thead>
    <tbody>
    <?php if (count($alumnos) > 0): ?>
        <?php foreach ($alumnos as $alumno): ?>
            <tr>
                <td class="info-alumno">
                    <div class="nombre-alumno">
                        <?php echo htmlspecialchars($alumno['apellidos'] . ' ' . $alumno['nombre']); ?>
                    </div>
                    <div class="detalles-alumno">
                        <?php echo $alumno['numero_control']; ?> | Semestre: <?php echo $alumno['semestre']; ?>
                    </div>
                </td>
                
                <?php 
                $total_alumno = 0;
                $tareas_calificadas_alumno = 0;
                $puntos_por_unidad_alumno = [];
                
                // Inicializar array para este alumno
                if (isset($tareas_por_unidad) && is_array($tareas_por_unidad)) {
                    foreach ($tareas_por_unidad as $unidad_num => $tareas_unidad) {
                        $puntos_por_unidad_alumno[$unidad_num] = ['total' => 0, 'count' => 0];
                    }
                }
                ?>
                
                <!-- CALIFICACIONES INDIVIDUALES -->
                <?php foreach ($tareas as $tarea): ?>
                    <?php
                    $calificacion = null;
                    $clase_calificacion = 'calificacion-sin';
                    $texto_calificacion = 'Sin calificar';
                    
                    if (isset($calificaciones[$alumno['id_alumno']][$tarea['id_tarea']])) {
                        $calif_data = $calificaciones[$alumno['id_alumno']][$tarea['id_tarea']];
                        if ($calif_data['calificacion'] !== null) {
                            $calificacion = $calif_data['calificacion'];
                            $total_alumno += $calificacion;
                            $tareas_calificadas_alumno++;
                            
                            // Acumular para promedio por unidad
                            if (isset($puntos_por_unidad_alumno[$tarea['unidad']])) {
                                $puntos_por_unidad_alumno[$tarea['unidad']]['total'] += $calificacion;
                                $puntos_por_unidad_alumno[$tarea['unidad']]['count']++;
                            }
                            
                            // Determinar color según calificación
                            $porcentaje = ($calificacion / $tarea['puntos_maximos']) * 100;
                            if ($porcentaje >= 80) {
                                $clase_calificacion = 'calificacion-alta';
                            } elseif ($porcentaje >= 60) {
                                $clase_calificacion = 'calificacion-media';
                            } else {
                                $clase_calificacion = 'calificacion-baja';
                            }
                            
                            $texto_calificacion = $calificacion;
                        } else {
                            $texto_calificacion = 'Entregada';
                            $clase_calificacion = 'calificacion-sin';
                        }
                    }
                    ?>
                    <td>
                        <span class="calificacion <?php echo $clase_calificacion; ?>">
                            <?php echo $texto_calificacion; ?>
                        </span>
                    </td>
                <?php endforeach; ?>
                
                <!-- PROMEDIO FINAL -->
                <td>
                    <?php if ($tareas_calificadas_alumno > 0): ?>
                        <?php 
                        $promedio_alumno = round($total_alumno / $tareas_calificadas_alumno, 2);
                        $clase_promedio = 'calificacion-media';
                        if ($promedio_alumno >= 80) $clase_promedio = 'calificacion-alta';
                        if ($promedio_alumno < 60) $clase_promedio = 'calificacion-baja';
                        ?>
                        <span class="calificacion <?php echo $clase_promedio; ?> promedio-final">
                            <?php echo $promedio_alumno; ?>
                        </span>
                    <?php else: ?>
                        <span class="calificacion calificacion-sin">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- NUEVA FILA PARA PROMEDIOS POR UNIDAD -->
            <tr style="background: #f8f9fa;">
                <td style="font-weight: bold; color: #1565c0; text-align: right;">
                    Promedios:
                </td>
                
                <!-- CELDAS DE PROMEDIOS POR UNIDAD -->
                <?php 
                if (isset($tareas_por_unidad) && is_array($tareas_por_unidad)):
                    foreach ($tareas_por_unidad as $unidad_num => $tareas_unidad): 
                        if (count($tareas_unidad) > 0): 
                            $promedio_unidad = 0;
                            $clase_promedio_unidad = 'calificacion-sin';
                            
                            if (isset($puntos_por_unidad_alumno[$unidad_num]) && 
                                $puntos_por_unidad_alumno[$unidad_num]['count'] > 0) {
                                $promedio_unidad = round(
                                    $puntos_por_unidad_alumno[$unidad_num]['total'] / 
                                    $puntos_por_unidad_alumno[$unidad_num]['count'], 
                                    2
                                );
                                
                                if ($promedio_unidad >= 80) {
                                    $clase_promedio_unidad = 'calificacion-alta';
                                } elseif ($promedio_unidad >= 60) {
                                    $clase_promedio_unidad = 'calificacion-media';
                                } else {
                                    $clase_promedio_unidad = 'calificacion-baja';
                                }
                            }
                            ?>
                            <td colspan="<?php echo count($tareas_unidad); ?>" style="text-align: center; border-left: 2px solid #1565c0; border-right: 2px solid #1565c0;">
                                <?php if ($puntos_por_unidad_alumno[$unidad_num]['count'] > 0): ?>
                                    <span class="calificacion <?php echo $clase_promedio_unidad; ?>" style="font-weight: bold;">
                                        <?php echo $promedio_unidad; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="calificacion calificacion-sin">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif;
                    endforeach;
                endif; ?>
                
                <!-- CELDA VACÍA PARA ALINEAR CON PROMEDIO FINAL -->
                <td></td>
            </tr>
            
            <!-- SEPARADOR ENTRE ALUMNOS -->
            <tr>
                <td colspan="<?php echo count($tareas) + 2; ?>" style="padding: 5px; background: #f0f0f0;"></td>
            </tr>
            
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="<?php echo count($tareas) + 2; ?>" style="text-align: center; padding: 40px; color: #666;">
                <h3>No hay alumnos inscritos en esta clase</h3>
                <p>Los alumnos aparecerán aquí una vez que se inscriban a la clase.</p>
            </td>
        </tr>
    <?php endif; ?>
</tbody>
    </table>
  </div>
    <!-- LEYENDA PARA PANTALLA -->
  <div class="no-print" style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9em;">
    <strong>Leyenda:</strong>
    <span class="calificacion calificacion-alta" style="margin: 0 10px;">80-100%</span>
    <span class="calificacion calificacion-media" style="margin: 0 10px;">60-79%</span>
    <span class="calificacion calificacion-baja" style="margin: 0 10px;">0-59%</span>
    <span class="calificacion calificacion-sin" style="margin: 0 10px;">Sin calificar/Entregada</span>
  </div>

  <!-- LEYENDA PARA IMPRESIÓN (solo visible al imprimir) -->
  <div class="leyenda-impresion">
    <strong>Leyenda:</strong> 
    <span style="background:#f0f7ff; color:#1565c0; padding:1px 4px; margin:0 5px; border:1px solid #ccc; font-size:7pt;">80-100%</span>
    <span style="background:#fff8e1; color:#f57c00; padding:1px 4px; margin:0 5px; border:1px solid #ccc; font-size:7pt;">60-79%</span>
    <span style="background:#ffebee; color:#c62828; padding:1px 4px; margin:0 5px; border:1px solid #ccc; font-size:7pt;">0-59%</span>
    <span style="background:#fafafa; color:#757575; padding:1px 4px; margin:0 5px; border:1px solid #ccc; font-size:7pt;">Sin calificar</span>
  </div>

  
</main>

<script>
// Función para exportar a Excel (puedes implementarla después)
function exportarExcel() {
  alert('Función de exportación a Excel será implementada próximamente');
}
// Configuración para impresión
document.addEventListener('DOMContentLoaded', function() {
  // Agregar información de fecha en el header al imprimir
  const originalTitle = document.title;
  
  window.addEventListener('beforeprint', function() {
    document.title = 'Calificaciones - ' + document.querySelector('.clase-header h1').textContent + ' - ' + new Date().toLocaleDateString();
  });
  
  window.addEventListener('afterprint', function() {
    document.title = originalTitle;
  });
});

// Función para exportar a Excel
function exportarExcel() {
  alert('Función de exportación a Excel será implementada próximamente');
}
</script>

<?php include "footer.php"; ?>