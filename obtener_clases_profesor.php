<?php
session_start();
include "../conexion.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != '3') {
    die('Acceso denegado');
}

$id_profesor = $_GET['id_profesor'] ?? 0;

// Obtener clases actuales del profesor
$sql_clases = "
    SELECT c.id_clase, m.nombre as materia, s.nombre as salon, s.edificio,
           m.creditos, c.periodo, c.capacidad,
           (SELECT COUNT(*) FROM asignacion a WHERE a.id_clase = c.id_clase) as alumnos_inscritos
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN salon s ON c.id_salon = s.id_salon
    WHERE c.id_profesor = $id_profesor AND c.activo = 1
    ORDER BY m.nombre
";

$clases = $conexion->query($sql_clases);

if ($clases && $clases->num_rows > 0) {
    echo '<div class="clases-actuales">';
    echo '<h6>Clases Activas:</h6>';
    
    while($clase = $clases->fetch_assoc()) {
        echo '<div class="clase-item">';
        echo '<strong>' . htmlspecialchars($clase['materia']) . '</strong>';
        echo '<div class="mt-2">';
        echo '<small class="text-muted">';
        echo 'Salón: ' . $clase['salon'] . ' ' . $clase['edificio'] . ' | ';
        echo 'Créditos: ' . $clase['creditos'] . ' | ';
        echo 'Alumnos: ' . $clase['alumnos_inscritos'] . '/' . $clase['capacidad'] . ' | ';
        echo 'Periodo: ' . htmlspecialchars($clase['periodo']);
        echo '</small>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="alert alert-info">El profesor no tiene clases activas actualmente.</div>';
}

// Obtener clases históricas (inactivas)
$sql_clases_historicas = "
    SELECT m.nombre as materia, s.nombre as salon, s.edificio, c.periodo
    FROM clase c
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN salon s ON c.id_salon = s.id_salon
    WHERE c.id_profesor = $id_profesor AND c.activo = 0
    ORDER BY c.periodo DESC
    LIMIT 10
";

$clases_historicas = $conexion->query($sql_clases_historicas);

if ($clases_historicas && $clases_historicas->num_rows > 0) {
    echo '<div class="mt-4">';
    echo '<h6>Últimas Clases Impartidas:</h6>';
    echo '<div style="max-height: 200px; overflow-y: auto;">';
    
    while($clase = $clases_historicas->fetch_assoc()) {
        echo '<div class="clase-item">';
        echo '<strong>' . htmlspecialchars($clase['materia']) . '</strong>';
        echo '<div><small class="text-muted">';
        echo $clase['salon'] . ' ' . $clase['edificio'] . ' | ' . htmlspecialchars($clase['periodo']);
        echo '</small></div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
?>