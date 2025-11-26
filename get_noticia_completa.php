<?php
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID de noticia no proporcionado']);
    exit;
}

$id_noticia = $_GET['id'];
$id_usuario = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 0;

$query = $conexion->prepare("
    SELECT n.*, 
           u.nombre AS nombre_usuario, 
           u.apellidos AS apellidos_usuario, 
           u.rol AS rol_usuario,
           CONCAT(u.nombre, ' ', u.apellidos) AS nombre_completo,
           DATE_FORMAT(n.publicacion, '%d/%m/%Y a las %H:%i') AS fecha_formateada,
           IF(l.id_usuario IS NULL, 0, 1) AS dio_like
    FROM noticias n
    JOIN usuario u ON n.id_usuario = u.id_usuario
    LEFT JOIN likes_usuarios l 
           ON l.id_noticia = n.id_noticia AND l.id_usuario = ?
    WHERE n.id_noticia = ?
");

$query->bind_param("ii", $id_usuario, $id_noticia);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) {
    $noticia = $result->fetch_assoc();
    
    // Formatear datos para la respuesta
    $respuesta = [
        'titulo' => $noticia['titulo'],
        'imagen' => $noticia['imagen'],
        'info' => $noticia['info'],
        'fecha' => $noticia['fecha_formateada'],
        'autor' => $noticia['nombre_usuario'] . ' ' . $noticia['apellidos_usuario'],
        'nombre_completo' => $noticia['nombre_completo'],
        'rol' => $noticia['rol_usuario'] == 1 ? 'Alumno' : ($noticia['rol_usuario'] == 2 ? 'Profesor' : 'Coordinador'),
        'visitas' => $noticia['visitas'],
        'likes' => $noticia['likes'],
        'dio_like' => $noticia['dio_like']
    ];
    
    echo json_encode($respuesta);
} else {
    echo json_encode(['error' => 'Noticia no encontrada']);
}
?>