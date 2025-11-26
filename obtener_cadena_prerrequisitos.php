<?php
session_start();
include "../conexion.php";

if (!$_SESSION['rol']) { 
    header("Location: ../index.php");
    exit;
}

$id_materia = isset($_GET['id_materia']) ? intval($_GET['id_materia']) : 0;
if (!$id_materia) {
    echo '<div class="alert alert-danger">No se especificó la materia</div>';
    exit;
}

// Función recursiva para obtener la cadena completa de prerrequisitos
function obtenerCadenaPrerrequisitos($conexion, $id_materia, $nivel = 0) {
    if ($nivel > 10) return []; // Prevenir recursión infinita
    
    $cadena = [];
    
    // Obtener información de la materia actual
    $sql_materia = "SELECT m.*, prerreq.nombre as prerrequisito_nombre 
                    FROM materia m 
                    LEFT JOIN materia prerreq ON m.id_prerrequisito = prerreq.id_materia 
                    WHERE m.id_materia = ?";
    $stmt = $conexion->prepare($sql_materia);
    $stmt->bind_param("i", $id_materia);
    $stmt->execute();
    $materia = $stmt->get_result()->fetch_assoc();
    
    if ($materia) {
        $cadena[] = [
            'id_materia' => $materia['id_materia'],
            'nombre' => $materia['nombre'],
            'nivel' => $nivel,
            'creditos' => $materia['creditos'],
            'semestre_sugerido' => $materia['semestre_sugerido'],
            'prerrequisito_nombre' => $materia['prerrequisito_nombre']
        ];
        
        // Si tiene prerrequisito, obtener recursivamente
        if ($materia['id_prerrequisito']) {
            $subcadena = obtenerCadenaPrerrequisitos($conexion, $materia['id_prerrequisito'], $nivel + 1);
            $cadena = array_merge($cadena, $subcadena);
        }
    }
    
    return $cadena;
}

// Obtener la cadena completa
$cadena = obtenerCadenaPrerrequisitos($conexion, $id_materia);

// Invertir el array para mostrar desde el inicio hasta la materia actual
$cadena = array_reverse($cadena);

?>

<div class="container-fluid">
    <h4 class="mb-3">Cadena de Prerrequisitos</h4>
    
    <?php if (count($cadena) > 0): ?>
        <div class="card">
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($cadena as $index => $materia): ?>
                        <div class="timeline-item <?php echo $index === 0 ? 'current' : ''; ?>">
                            <div class="timeline-marker 
                                <?php echo $index === 0 ? 'bg-primary' : 'bg-success'; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="timeline-content">
                                <div class="card <?php echo $index === 0 ? 'border-primary' : 'border-success'; ?>">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php if ($index === 0): ?>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($materia['nombre']); ?>
                                        </h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-graduation-cap"></i> 
                                                    <?php echo $materia['creditos']; ?> créditos
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> 
                                                    Semestre <?php echo $materia['semestre_sugerido']; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if ($index < count($cadena) - 1): ?>
                                            <div class="mt-2">
                                                <small class="text-success">
                                                    <i class="fas fa-arrow-down"></i>
                                                    <strong>Prerrequisito para:</strong> 
                                                    <?php echo htmlspecialchars($cadena[$index + 1]['nombre']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($index < count($cadena) - 1): ?>
                            <div class="timeline-connector">
                                <i class="fas fa-arrow-down text-success"></i>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <div class="alert alert-info">
                <strong>Resumen de la cadena:</strong> 
                <?php echo count($cadena); ?> materias en total
                <?php if (count($cadena) > 1): ?>
                    (desde <?php echo htmlspecialchars(end($cadena)['nombre']); ?> 
                    hasta <?php echo htmlspecialchars($cadena[0]['nombre']); ?>)
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <h5>No se pudo cargar la cadena de prerrequisitos</h5>
            <p class="mb-0">La materia no existe o no tiene prerrequisitos definidos.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -15px;
    top: 15px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    z-index: 2;
}
.timeline-content {
    margin-left: 30px;
}
.timeline-connector {
    position: relative;
    height: 30px;
    margin-left: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-connector i {
    font-size: 1.5em;
}
.timeline-item.current .card {
    border-left: 4px solid #1565c0 !important;
}
</style>