<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ../coordinador.php?seccion=grupos&error=Datos incompletos");
    exit;
}

$id_grupo = intval($_GET['id']);

// Verificar si el grupo tiene alumnos activos
$sql_alumnos = "SELECT COUNT(*) as total FROM alumno_grupo WHERE id_grupo = ? AND activo = 1";
$stmt_alumnos = $conexion->prepare($sql_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$total_alumnos = $stmt_alumnos->get_result()->fetch_assoc()['total'];

if ($total_alumnos > 0) {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=No se puede eliminar el grupo porque tiene alumnos activos. Primero elimine o transfiera los alumnos.");
    exit;
}

// Eliminar grupo (soft delete - marcarlo como inactivo)
$sql_delete = "UPDATE grupo SET activo = 0, fecha_modificacion = NOW() WHERE id_grupo = ?";
$stmt_delete = $conexion->prepare($sql_delete);
$stmt_delete->bind_param("i", $id_grupo);

if ($stmt_delete->execute()) {
    header("Location: ../coordinador.php?seccion=grupos&mensaje=Grupo eliminado correctamente");
} else {
    header("Location: ../detalle_grupo.php?id=$id_grupo&error=Error al eliminar el grupo");
}
?>