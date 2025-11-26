<?php
include "conexion.php";

if (!isset($_GET['id_clase'])) {
    die("FALTA ID DE CLASE");
}

$id_clase = intval($_GET['id_clase']);

// OBTENER INFORMACIÓN DE LA CLASE
$sql_info = "
SELECT c.id_materia, c.periodo
FROM clase c
WHERE c.id_clase = $id_clase
";
$res_info = $conexion->query($sql_info);
if ($res_info->num_rows == 0) {
    die("CLASE NO ENCONTRADA");
}
$info = $res_info->fetch_assoc();
$id_materia = $info['id_materia'];
$periodo = $info['periodo'];

// OBTENER ALUMNOS ASIGNADOS A ESA CLASE
$sql_asig = "SELECT id_asignacion, id_alumno FROM asignacion WHERE id_clase = $id_clase";
$res_asig = $conexion->query($sql_asig);

while ($asig = $res_asig->fetch_assoc()) {
    $id_asignacion = $asig['id_asignacion'];
    $id_alumno = $asig['id_alumno'];

    // CALCULAR PROMEDIO FINAL
    $res_prom = $conexion->query("SELECT AVG(calificacion) AS prom FROM calificacion_clase WHERE id_asignacion = $id_asignacion");
    $prom = floatval($res_prom->fetch_assoc()['prom']);
    $aprobado = ($prom >= 60) ? 1 : 0;

    // DETERMINAR OPORTUNIDAD SEGÚN VECES CURSADAS
    $res_oportunidades = $conexion->query("SELECT COUNT(*) AS veces, SUM(aprobado=0) AS reprobadas FROM materia_cursada WHERE id_materia=$id_materia AND id_alumno=$id_alumno");
    $data_oport = $res_oportunidades->fetch_assoc();
    $veces = intval($data_oport['veces']);
    $reprobadas = intval($data_oport['reprobadas']);

    if ($veces == 0) {
        $oportunidad = 'ordinario';
    } elseif ($reprobadas == 1) {
        $oportunidad = 'recursamiento';
    } elseif ($reprobadas >= 2) {
        $oportunidad = 'especial';
    } else {
        $oportunidad = 'ordinario';
    }

    // INSERTAR REGISTRO EN materia_cursada
    $conexion->query("
        INSERT INTO materia_cursada (id_materia, id_clase, id_alumno, cal_final, oportunidad, periodo, aprobado)
        VALUES ($id_materia, $id_clase, $id_alumno, $prom, '$oportunidad', '$periodo', $aprobado)
    ");
}

// DESACTIVAR LA CLASE
$conexion->query("UPDATE clase SET activo = 0 WHERE id_clase = $id_clase");

// Después de cerrar exitosamente el curso
header("Location: profesor.php?exito=curso_cerrado");
exit();
?>
