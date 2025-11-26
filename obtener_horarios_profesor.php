<?php
session_start();
include "conexion.php";

header('Content-Type: application/json');

if (!isset($_GET['id_profesor'])) {
    echo json_encode([]);
    exit;
}

$id_profesor = intval($_GET['id_profesor']);

// Obtener horarios ocupados del profesor por día
$sql = "
    SELECT hc.dia, hc.hora 
    FROM horarios_clase hc
    INNER JOIN clase c ON hc.id_clase = c.id_clase
    WHERE c.id_profesor = ? AND c.activo = 1
    ORDER BY hc.dia, hc.hora
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_profesor);
$stmt->execute();
$result = $stmt->get_result();

$horarios_ocupados = [
    '1' => [], '2' => [], '3' => [], '4' => [], '5' => []
];

while ($row = $result->fetch_assoc()) {
    $horarios_ocupados[$row['dia']][] = $row['hora'];
}

// Calcular horarios disponibles por día
$horarios_disponibles = [];

for ($dia = 1; $dia <= 5; $dia++) {
    $ocupados_dia = $horarios_ocupados[$dia];
    $disponibles_dia = [];
    
    // Horarios posibles de 7:00 a 21:00
    for ($hora = 7; $hora <= 21; $hora++) {
        if (!in_array($hora, $ocupados_dia)) {
            $disponibles_dia[] = $hora;
        }
    }
    
    $horarios_disponibles[$dia] = $disponibles_dia;
}

echo json_encode($horarios_disponibles);
?>