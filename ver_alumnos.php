<?php
include "conexion.php";

$id_clase = $_GET['id_clase'] ?? 0;
if(!$id_clase) exit("Clase no válida");

// Obtener alumnos inscritos
$sql = "
SELECT a.id_alumno, u.nombre, u.apellidos
FROM asignacion asg
INNER JOIN alumno a ON asg.id_alumno = a.id_alumno
INNER JOIN usuario u ON a.id_usuario = u.id_usuario
WHERE asg.id_clase = $id_clase
";
$res = $conexion->query($sql);

echo "<h3>👨‍🎓 ALUMNOS INSCRITOS EN LA CLASE</h3>";

if($res->num_rows == 0){
    echo "<p>No hay alumnos asignados.</p>";
    exit;
}

echo "<form method='POST' action='guardar_calificaciones.php'>";
echo "<input type='hidden' name='id_clase' value='$id_clase'>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Alumno</th><th>Unidad 1</th><th>Unidad 2</th><th>Unidad 3</th><th>Unidad 4</th><th>Unidad 5</th></tr>";

while($al = $res->fetch_assoc()){
    $id_alumno = $al['id_alumno'];
    echo "<tr>";
    echo "<td>{$al['nombre']} {$al['apellidos']}</td>";
    for($u = 1; $u <= 5; $u++){
        echo "<td><input type='number' name='calificaciones[$id_alumno][$u]' min='0' max='100' style='width:60px;'></td>";
    }
    echo "</tr>";
}

echo "</table><br>";
echo "<button type='submit'>💾 GUARDAR CALIFICACIONES</button>";
echo "</form>";
