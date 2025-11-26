<?php
include "../conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que los parámetros existen
if (!isset($_GET['id']) || !isset($_GET['id_usuario'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros faltantes']);
    exit;
}

$id_noticia = intval($_GET['id']);
$id_usuario = intval($_GET['id_usuario']);

// Preparar y ejecutar la consulta
$query = $conexion->prepare("
    SELECT n.*, 
           u.nombre AS nombre_usuario, 
           u.apellidos AS apellidos_usuario, 
           u.rol AS rol_usuario,
           IF(l.id_usuario IS NULL, 0, 1) AS dio_like
    FROM noticias n
    JOIN usuario u ON n.id_usuario = u.id_usuario
    LEFT JOIN likes_usuarios l 
           ON l.id_noticia = n.id_noticia AND l.id_usuario = ?
    WHERE n.id_noticia = ?
");

if (!$query) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la preparación de la consulta: ' . $conexion->error]);
    exit;
}

$query->bind_param("ii", $id_usuario, $id_noticia);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Noticia no encontrada']);
    exit;
}

$noticia = $result->fetch_assoc();

// Determinar nombre del rol
$rol_nombre = 'Desconocido';
switch($noticia['rol_usuario']){
    case 1: $rol_nombre = 'Alumno'; break;
    case 2: $rol_nombre = 'Profesor'; break;
    case 3: $rol_nombre = 'Coordinador'; break;
}

header('Content-Type: application/json');
echo json_encode([
    'titulo' => $noticia['titulo'],
    'imagen' => $noticia['imagen'],
    'info' => $noticia['info'],
    'publicacion' => $noticia['publicacion'],
    'nombre_usuario' => $noticia['nombre_usuario'],
    'apellidos_usuario' => $noticia['apellidos_usuario'],
    'rol_nombre' => $rol_nombre,
    'likes' => $noticia['likes'],
    'dio_like' => $noticia['dio_like']
]);

$query->close();
?>