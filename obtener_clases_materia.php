<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_materia = isset($_GET['id_materia']) ? intval($_GET['id_materia']) : 0;
if (!$id_materia) {
    echo '<div class="alert alert-danger">No se especificó la materia</div>';
    exit;
}

// Obtener información de la materia
$sql_materia = "SELECT nombre FROM materia WHERE id_materia = ?";
$stmt = $conexion->prepare($sql_materia);
$stmt->bind_param("i", $id_materia);
$stmt->execute();
$materia = $stmt->get_result()->fetch_assoc();

if (!$materia) {
    echo '<div class="alert alert-danger">Materia no encontrada</div>';
    exit;
}

// Obtener clases de esta materia
$sql_clases = "
    SELECT c.id_clase, 
           CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
           s.nombre as salon, s.edificio,
           c.periodo, c.capacidad, c.activo,
           (SELECT COUNT(*) FROM asignacion a WHERE a.id_clase = c.id_clase) as alumnos_inscritos
    FROM clase c
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    INNER JOIN salon s ON c.id_salon = s.id_salon
    WHERE c.id_materia = ?
    ORDER BY c.activo DESC, c.periodo DESC
";
$stmt = $conexion->prepare($sql_clases);
$stmt->bind_param("i", $id_materia);
$stmt->execute();
$clases = $stmt->get_result();

?>

<div class="container-fluid">
    <h4 class="mb-3">Clases de: <?php echo htmlspecialchars($materia['nombre']); ?></h4>
    
    <?php if ($clases->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Profesor</th>
                        <th>Salón</th>
                        <th>Período</th>
                        <th>Alumnos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($clase = $clases->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($clase['profesor_nombre']); ?></td>
                            <td><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></td>
                            <td><?php echo htmlspecialchars($clase['periodo']); ?></td>
                            <td><?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?></td>
                            <td>
                                <span class="badge <?php echo $clase['activo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $clase['activo'] ? 'Activa' : 'Cerrada'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="../acciones/gestionar_alumnos_clase.php?id_clase=<?php echo $clase['id_clase']; ?>" 
                                   class="btn btn-sm btn-success" title="Gestionar Alumnos">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="../acciones/editar_clase.php?id_clase=<?php echo $clase['id_clase']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar Clase">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <div class="alert alert-info">
                <strong>Total de clases:</strong> <?php echo $clases->num_rows; ?> 
                (<?php echo $clases->num_rows > 0 ? $clases->num_rows : '0'; ?> activas)
            </div>
        </div>
        
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
            <h5>No hay clases para esta materia</h5>
            <p class="mb-0">Esta materia no tiene clases asignadas actualmente.</p>
            <a href="../acciones/crear_clase.php" class="btn btn-primary mt-2">
                <i class="fas fa-plus"></i> Crear Nueva Clase
            </a>
        </div>
    <?php endif; ?>
</div>