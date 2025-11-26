<?php
session_start();
include "conexion.php";
include "header.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != 2 && $_SESSION['rol'] != 3)) {
    header("Location: login.php");
    exit;
}

$id_clase = $_GET['id_clase'] ?? 0;

// Verificar que el profesor tiene acceso a esta clase
$id_usuario = $_SESSION['id_usuario'];
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

// Obtener información de la clase
$query_clase = $conexion->prepare("
    SELECT c.id_clase, c.grupo, m.nombre as materia_nombre
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    WHERE c.id_clase = ?
");
$query_clase->bind_param("i", $id_clase);
$query_clase->execute();
$clase_info = $query_clase->get_result()->fetch_assoc();

// Obtener todos los justificantes de la clase
$query_justificantes = $conexion->prepare("
    SELECT 
        ja.*,
        u.nombre as alumno_nombre,
        u.apellidos as alumno_apellidos,
        u.clave as numero_control,
        CONCAT(up.nombre, ' ', up.apellidos) as prefecto_nombre
    FROM justificantes_asistencia ja
    INNER JOIN alumno a ON ja.id_alumno = a.id_alumno
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    INNER JOIN asignacion asig ON a.id_alumno = asig.id_alumno
    LEFT JOIN prefecto p ON ja.id_prefecto = p.id_prefecto
    LEFT JOIN usuario up ON p.id_usuario = up.id_usuario
    WHERE asig.id_clase = ? 
    ORDER BY ja.fecha_inicio DESC, ja.estado
");
$query_justificantes->bind_param("i", $id_clase);
$query_justificantes->execute();
$justificantes = $query_justificantes->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justificantes - <?php echo htmlspecialchars($clase_info['materia_nombre']); ?></title>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .justificante-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #28a745;
        }
        .justificante-card.pendiente {
            border-left-color: #ffc107;
        }
        .justificante-card.rechazado {
            border-left-color: #dc3545;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .badge.aprobado { background: #d4edda; color: #155724; }
        .badge.pendiente { background: #fff3cd; color: #856404; }
        .badge.rechazado { background: #f8d7da; color: #721c24; }
        .filtros {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-light {
            background: white;
            color: #1565c0;
        }
        .btn-light:hover {
            background: #f8f9fa;
        }
        .btn-primary {
            background: #1565c0;
            color: white;
        }
        .btn-primary:hover {
            background: #1976d2;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1>Justificantes de Asistencia</h1>
                    <p><?php echo htmlspecialchars($clase_info['materia_nombre']); ?> - Grupo <?php echo $clase_info['grupo']; ?></p>
                </div>
                <a href="detalle_clase.php?id=<?php echo $id_clase; ?>" class="btn btn-light">← Volver a la Clase</a>
            </div>
        </div>

        <div class="filtros">
            <h3>Filtros</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <select id="filtroEstado" onchange="filtrarJustificantes()" class="form-control" style="width: auto;">
                    <option value="todos">Todos los estados</option>
                    <option value="aprobado">Aprobados</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="rechazado">Rechazados</option>
                </select>
                <select id="filtroTipo" onchange="filtrarJustificantes()" class="form-control" style="width: auto;">
                    <option value="todos">Todos los tipos</option>
                    <option value="medico">Médico</option>
                    <option value="personal">Personal</option>
                    <option value="familiar">Familiar</option>
                    <option value="oficial">Oficial</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
        </div>

        <?php if (count($justificantes) > 0): ?>
            <div id="lista-justificantes">
                <?php foreach($justificantes as $justificante): ?>
                    <div class="justificante-card <?php echo $justificante['estado']; ?>" 
                         data-estado="<?php echo $justificante['estado']; ?>"
                         data-tipo="<?php echo $justificante['tipo_justificante']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 5px 0;">
                                    <?php echo htmlspecialchars($justificante['alumno_nombre'] . ' ' . $justificante['alumno_apellidos']); ?>
                                </h3>
                                <p style="margin: 0; color: #666;">
                                    <?php echo $justificante['numero_control']; ?> | 
                                    <?php echo date('d/m/Y', strtotime($justificante['fecha_inicio'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($justificante['fecha_fin'])); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge <?php echo $justificante['estado']; ?>">
                                    <?php echo ucfirst($justificante['estado']); ?>
                                </span>
                                <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                    <?php echo ucfirst($justificante['tipo_justificante']); ?>
                                </div>
                            </div>
                        </div>

                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>Motivo:</strong>
                            <p style="margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($justificante['motivo'])); ?></p>
                        </div>

                        <?php if ($justificante['comentario_prefecto']): ?>
                            <div style="background: #e3f2fd; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                                <strong>Comentario del Prefecto:</strong>
                                <p style="margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($justificante['comentario_prefecto'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9em; color: #666; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong>Registrado por:</strong> <?php echo $justificante['prefecto_nombre'] ?? 'Sistema'; ?>
                            </div>
                            <div>
                                <strong>Última modificación:</strong> 
                                <?php echo date('d/m/Y H:i', strtotime($justificante['fecha_modificacion'])); ?>
                            </div>
                        </div>

                        <?php if ($justificante['nombre_archivo_original']): ?>
                            <div style="margin-top: 10px;">
                                <a href="uploads/justificantes/<?php echo $justificante['ruta_justificante']; ?>" 
                                   download 
                                   class="btn btn-primary btn-sm">
                                    📎 Descargar: <?php echo htmlspecialchars($justificante['nombre_archivo_original']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                <h3>No hay justificantes registrados</h3>
                <p>No se han encontrado justificantes de asistencia para esta clase.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filtrarJustificantes() {
            const estado = document.getElementById('filtroEstado').value;
            const tipo = document.getElementById('filtroTipo').value;
            const justificantes = document.querySelectorAll('.justificante-card');
            
            justificantes.forEach(card => {
                const cardEstado = card.getAttribute('data-estado');
                const cardTipo = card.getAttribute('data-tipo');
                
                const mostrarEstado = (estado === 'todos' || cardEstado === estado);
                const mostrarTipo = (tipo === 'todos' || cardTipo === tipo);
                
                if (mostrarEstado && mostrarTipo) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

<?php include "footer.php"; ?>