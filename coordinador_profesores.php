<?php
// Obtener profesores de la carrera del coordinador
$sql_profesores = "
    SELECT p.id_profesor, u.nombre, u.apellidos, u.correo, u.foto, p.sueldo, p.estado,
           COUNT(DISTINCT c.id_clase) as total_clases,
           COUNT(DISTINCT CASE WHEN c.activo = 1 THEN c.id_clase END) as clases_activas
    FROM profesor p
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    LEFT JOIN clase c ON p.id_profesor = c.id_profesor
    WHERE p.id_coordinador = $id_coordinador
    GROUP BY p.id_profesor
    ORDER BY u.nombre, u.apellidos
";
$profesores = $conexion->query($sql_profesores);

// Si se seleccionó un profesor, mostrar sus clases
if ($id_profesor_seleccionado) {
    $sql_profesor_info = "
        SELECT u.nombre, u.apellidos, u.foto 
        FROM profesor p 
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario 
        WHERE p.id_profesor = $id_profesor_seleccionado
    ";
    $profesor_info = $conexion->query($sql_profesor_info)->fetch_assoc();
    
    $sql_clases_profesor = "
        SELECT c.id_clase, m.nombre as materia, s.nombre as salon, s.edificio, 
               c.periodo, c.capacidad, c.activo,
               COUNT(a.id_asignacion) as alumnos_inscritos
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN salon s ON c.id_salon = s.id_salon
        LEFT JOIN asignacion a ON c.id_clase = a.id_clase
        WHERE c.id_profesor = $id_profesor_seleccionado
        GROUP BY c.id_clase
        ORDER BY c.activo DESC, m.nombre
    ";
    $clases_profesor = $conexion->query($sql_clases_profesor);
}
?>

<div class="seccion-profesores">
    <?php if (!$id_profesor_seleccionado): ?>
        <div class="seccion-header">
            <h2>Profesores de <?php echo htmlspecialchars($carrera_nombre); ?></h2>
            <button class="btn btn-primary" onclick="abrirModal('modalCrearProfesor')">
                ➕ Crear Nuevo Profesor
            </button>
        </div>

        <div class="grid-profesores">
            <?php while($profesor = $profesores->fetch_assoc()): ?>
                <div class="tarjeta-profesor" onclick="window.location.href='?seccion=profesores&id_profesor=<?php echo $profesor['id_profesor']; ?>'">
                    <img src="uploads/<?php echo $profesor['foto'] ?: 'default.jpg'; ?>" 
                         alt="Foto" class="foto-profesor">
                    <div class="nombre-profesor">
                        <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?>
                    </div>
                    <div class="info-profesor">
                        <?php echo htmlspecialchars($profesor['correo']); ?>
                    </div>
                    <div class="info-profesor">
                        Clases: <?php echo $profesor['clases_activas']; ?> activas de <?php echo $profesor['total_clases']; ?> totales
                    </div>
                    <div class="info-profesor">
                        Sueldo: $<?php echo number_format($profesor['sueldo'], 2); ?>
                    </div>
                    <div class="<?php echo $profesor['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>">
                        <?php echo $profesor['estado'] == '1' ? 'Activo' : 'Inactivo'; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- DETALLE DEL PROFESOR SELECCIONADO -->
        <div class="seccion-header">
            <h2>Clases del Profesor: <?php echo htmlspecialchars($profesor_info['nombre'] . ' ' . $profesor_info['apellidos']); ?></h2>
            <a href="?seccion=profesores" class="btn btn-secondary">← Volver a Profesores</a>
        </div>

        <div class="grid-clases">
            <?php while($clase = $clases_profesor->fetch_assoc()): ?>
                <div class="tarjeta-clase" onclick="window.location.href='?seccion=profesores&id_profesor=<?php echo $id_profesor_seleccionado; ?>&id_clase=<?php echo $clase['id_clase']; ?>'">
                    <div class="tarjeta-clase-header">
                        <h3><?php echo htmlspecialchars($clase['materia']); ?></h3>
                        <span class="creditos-clase"><?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?> alumnos</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Salón:</span>
                        <span class="info-value"><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Período:</span>
                        <span class="info-value"><?php echo htmlspecialchars($clase['periodo']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Estado:</span>
                        <span class="info-value <?php echo $clase['activo'] ? 'estado-activo' : 'estado-inactivo'; ?>">
                            <?php echo $clase['activo'] ? 'Activa' : 'Cerrada'; ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($id_clase_seleccionada): ?>
            <?php
            // Mostrar alumnos de la clase seleccionada
            $sql_alumnos_clase = "
                SELECT a.id_alumno, u.nombre, u.apellidos, u.correo,
                       GROUP_CONCAT(CONCAT('U', cc.unidad, ': ', cc.calificacion) ORDER BY cc.unidad SEPARATOR ' | ') as calificaciones
                FROM asignacion asig
                INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
                INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                LEFT JOIN calificacion_clase cc ON asig.id_asignacion = cc.id_asignacion
                WHERE asig.id_clase = $id_clase_seleccionada
                GROUP BY a.id_alumno
                ORDER BY u.apellidos, u.nombre
            ";
            $alumnos_clase = $conexion->query($sql_alumnos_clase);
            ?>

            <div class="tabla-alumnos">
                <h3>Alumnos Inscritos en la Clase</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Correo</th>
                            <th>Calificaciones por Unidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($alumno = $alumnos_clase->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($alumno['correo']); ?></td>
                                <td><?php echo $alumno['calificaciones'] ?: 'Sin calificaciones'; ?></td>
                                <td>
                                    <a href="acciones/eliminar_alumno_clase.php?id_clase=<?php echo $id_clase_seleccionada; ?>&id_alumno=<?php echo $alumno['id_alumno']; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirmarEliminacion('¿Estás seguro de eliminar este alumno de la clase?')">
                                        Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>