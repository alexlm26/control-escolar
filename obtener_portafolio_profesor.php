<?php
session_start();
if ($_SESSION['rol'] == '1') { 
    header("Location: ../index.php");
    exit;
}
include "../conexion.php";

$id_profesor = $_GET['id_profesor'] ?? 0;

if ($id_profesor <= 0) {
    echo '<div class="alert alert-danger">ID de profesor inválido</div>';
    exit;
}

// Obtener información del profesor
$sql_profesor = "SELECT u.nombre, u.apellidos 
                 FROM profesor p 
                 INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
                 WHERE p.id_profesor = ?";
$stmt = $conexion->prepare($sql_profesor);
$stmt->bind_param("i", $id_profesor);
$stmt->execute();
$profesor = $stmt->get_result()->fetch_assoc();

if (!$profesor) {
    echo '<div class="alert alert-danger">Profesor no encontrado</div>';
    exit;
}

// Obtener documentos del portafolio
$sql_portafolio = "
    SELECT pp.*, 
           CASE 
               WHEN pp.tipo_documento = 'certificado_universitario' THEN 'Certificado Universitario'
               WHEN pp.tipo_documento = 'preparatoria' THEN 'Preparatoria/Bachillerato'
               WHEN pp.tipo_documento = 'curso' THEN 'Curso'
               WHEN pp.tipo_documento = 'diploma' THEN 'Diploma'
               WHEN pp.tipo_documento = 'otro' THEN 'Otro Documento'
           END as tipo_documento_nombre
    FROM portafolio_profesor pp
    WHERE pp.id_profesor = ? AND pp.activo = 1
    ORDER BY pp.fecha_emision DESC, pp.tipo_documento
";
$stmt = $conexion->prepare($sql_portafolio);
$stmt->bind_param("i", $id_profesor);
$stmt->execute();
$documentos = $stmt->get_result();

// Mapeo de colores para tipos de documento
$colores_tipos = [
    'certificado_universitario' => ['color' => '#4caf50', 'nombre' => 'Certificados Universitarios'],
    'preparatoria' => ['color' => '#2196f3', 'nombre' => 'Preparatoria/Bachillerato'],
    'curso' => ['color' => '#9c27b0', 'nombre' => 'Cursos'],
    'diploma' => ['color' => '#ff9800', 'nombre' => 'Diplomas'],
    'otro' => ['color' => '#f44336', 'nombre' => 'Otros Documentos']
];
?>

<div class="portafolio-profesor">
    <div class="alert alert-info">
        <h6>Portafolio de <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?></h6>
        <p class="mb-0">Documentos académicos y profesionales del profesor</p>
    </div>

    <!-- Botón para agregar nuevo documento -->
    <div class="text-end mb-3">
        <button type="button" class="btn btn-success btn-sm" 
                onclick="agregarDocumentoPortafolio(<?php echo $id_profesor; ?>)">
            <i class="fas fa-plus me-1"></i> Agregar Documento
        </button>
    </div>

    <?php if ($documentos && $documentos->num_rows > 0): ?>
        
        <!-- Leyenda de tipos de documentos -->
        <div class="leyenda-especialidades mb-4">
            <h6 style="margin-bottom: 15px; color: #555;">Tipos de Documentos:</h6>
            <?php foreach ($colores_tipos as $tipo => $data): ?>
                <div class="leyenda-item">
                    <div class="color-muestra" style="background-color: <?php echo $data['color']; ?>;"></div>
                    <span><?php echo htmlspecialchars($data['nombre']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Mostrar documentos organizados por tipo -->
        <?php 
        // Organizar documentos por tipo
        $documentos_por_tipo = [];
        while ($doc = $documentos->fetch_assoc()) {
            $tipo = $doc['tipo_documento'];
            if (!isset($documentos_por_tipo[$tipo])) {
                $documentos_por_tipo[$tipo] = [];
            }
            $documentos_por_tipo[$tipo][] = $doc;
        }
        ?>
        
        <?php foreach ($colores_tipos as $tipo => $data): ?>
            <?php if (isset($documentos_por_tipo[$tipo])): ?>
                <div class="grupo-tipo-documento mb-4">
                    <div class="titulo-semestre" style="background: linear-gradient(135deg, <?php echo $data['color']; ?>, <?php echo $data['color']; ?>dd);">
                        <?php echo htmlspecialchars($data['nombre']); ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo count($documentos_por_tipo[$tipo]); ?> documentos</span>
                    </div>
                    
                    <div class="grid-materias">
                        <?php foreach ($documentos_por_tipo[$tipo] as $doc): ?>
                            <div class="tarjeta-materia" style="border-left: 5px solid <?php echo $data['color']; ?>;">
                                <div class="tarjeta-materia-header">
                                    <h3 style="color: <?php echo $data['color']; ?>;">
                                        <?php echo htmlspecialchars($doc['nombre_documento']); ?>
                                    </h3>
                                    <span class="creditos-materia">
                                        <?php echo $doc['fecha_emision'] ? date('d/m/Y', strtotime($doc['fecha_emision'])) : 'Sin fecha'; ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Institución:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($doc['institucion'] ?? 'No especificada'); ?></span>
                                </div>
                                
                                <?php if ($doc['descripcion']): ?>
                                <div class="info-item">
                                    <span class="info-label">Descripción:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($doc['descripcion']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-label">Fecha de subida:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($doc['fecha_subida'])); ?></span>
                                </div>

                                <div class="acciones" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                    <?php if ($doc['ruta_archivo']): ?>
                                        <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                           target="_blank" 
                                           class="btn btn-primary btn-sm" 
                                           style="font-size: 0.8em;">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                        
                                        <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                           download 
                                           class="btn btn-success btn-sm" 
                                           style="font-size: 0.8em;">
                                            <i class="fas fa-download me-1"></i> Descargar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No hay archivo disponible</span>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            style="font-size: 0.8em;"
                                            onclick="eliminarDocumento(<?php echo $doc['id_portafolio']; ?>, '<?php echo htmlspecialchars($doc['nombre_documento']); ?>')">
                                        <i class="fas fa-trash me-1"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No hay documentos en el portafolio</h5>
            <p class="text-muted">El profesor aún no ha agregado documentos a su portafolio.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.grupo-tipo-documento {
    margin-bottom: 30px;
}

.titulo-semestre {
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: 600;
    font-size: 1.1em;
}

.portafolio-profesor .grid-materias {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.portafolio-profesor .tarjeta-materia {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.portafolio-profesor .tarjeta-materia:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.15);
}
</style>