<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id_clase']) || !isset($_GET['id_grupo'])) {
    header("Location: ../coordinador.php?seccion=grupos&error=Datos incompletos");
    exit;
}

$id_clase = intval($_GET['id_clase']);
$id_grupo = intval($_GET['id_grupo']);

// Obtener información de la clase para el mensaje
$sql_clase = "SELECT m.nombre FROM clase c INNER JOIN materia m ON c.id_materia = m.id_materia WHERE c.id_clase = ?";
$stmt_clase = $conexion->prepare($sql_clase);
$stmt_clase->bind_param("i", $id_clase);
$stmt_clase->execute();
$clase_nombre = $stmt_clase->get_result()->fetch_assoc()['nombre'];

// Eliminar asignaciones de alumnos del grupo a esta clase
$sql_delete = "
    DELETE a 
    FROM asignacion a 
    INNER JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno 
    WHERE a.id_clase = ? AND ag.id_grupo = ? AND ag.activo = 1";

$stmt_delete = $conexion->prepare($sql_delete);
$stmt_delete->bind_param("ii", $id_clase, $id_grupo);

if ($stmt_delete->execute()) {
    $filas_afectadas = $stmt_delete->affected_rows;
    header("Location: ../detalle_grupo.php?id=$id_grupo&mensaje=Clase '$clase_nombre' desasignada correctamente ($filas_afectadas alumnos afectados)");
} else {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al desasignar la clase");
}
?>