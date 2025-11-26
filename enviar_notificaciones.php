<?php
ob_start(); // INICIA BUFFER DE SALIDA
session_start();
include 'conexion.php'; // Esto probablemente usa MySQLi

// Verificar que sea coordinador
if ($_SESSION['rol'] != '3') { 
    header("Location: index.php");
    exit;
}
$id_usuario = $_SESSION['id_usuario'];
$id_coordinador = $_SESSION['id_coordinador'] ?? null;

// Obtener información del coordinador y su carrera desde la tabla usuario
$sql_coordinador = "SELECT u.id_carrera, car.nombre as carrera_nombre 
                   FROM usuario u 
                   LEFT JOIN carrera car ON u.id_carrera = car.id_carrera 
                   WHERE u.id_usuario = $id_usuario";
$coordinador = $conexion->query($sql_coordinador)->fetch_assoc();
$id_carrera_coordinador = $coordinador['id_carrera'] ?? null;
$carrera_nombre = $coordinador['carrera_nombre'] ?? 'Sin carrera asignada';

// Verificar si es coordinador ADMINISTRADOR (puede ver todo)
$es_admin = ($id_carrera_coordinador == 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = trim($_POST['titulo']);
    $mensaje = trim($_POST['mensaje']);
    $destinatario = $_POST['destinatario'];
    
    // Validar campos
    if (empty($titulo) || empty($mensaje) || empty($destinatario)) {
        $_SESSION['error'] = "❌ Todos los campos son obligatorios";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if (strlen($titulo) > 100) {
        $_SESSION['error'] = "❌ El título no puede tener más de 100 caracteres";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if (strlen($mensaje) > 500) {
        $_SESSION['error'] = "❌ El mensaje no puede tener más de 500 caracteres";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    try {
        // Obtener usuarios según el destinatario - USANDO MySQLi
       if($es_admin) {
    $sql = "";
    if ($destinatario == 'alumnos') {
        $sql = "SELECT u.id_usuario 
                FROM usuario u 
                INNER JOIN alumno a ON u.id_usuario = a.id_usuario 
                WHERE u.rol = '1' AND a.estado = '1'";
    } elseif ($destinatario == 'profesores') {
        $sql = "SELECT u.id_usuario 
                FROM usuario u 
                INNER JOIN profesor p ON u.id_usuario = p.id_usuario 
                WHERE u.rol = '2' AND p.estado = '1'";
    } else { // ambos
        $sql = "SELECT u.id_usuario 
                FROM usuario u 
                LEFT JOIN alumno a ON u.id_usuario = a.id_usuario 
                LEFT JOIN profesor p ON u.id_usuario = p.id_usuario 
                WHERE u.rol IN ('1', '2') 
                AND (a.estado = '1' OR p.estado = '1')";
    }
    $result = $conexion->query($sql);
    $usuarios = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
    
    if (empty($usuarios)) {
        $_SESSION['error'] = "❌ No se encontraron destinatarios activos";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
} else {
    $sql = "";
    if ($destinatario == 'alumnos') {
        $sql = "SELECT u.id_usuario 
                FROM usuario u 
                INNER JOIN alumno a ON u.id_usuario = a.id_usuario 
                WHERE u.rol = '1' AND a.estado = '1' AND u.id_carrera = ?";
    } elseif ($destinatario == 'profesores') {
        $sql = "SELECT u.id_usuario 
                FROM usuario u 
                INNER JOIN profesor p ON u.id_usuario = p.id_usuario 
                WHERE u.rol = '2' AND p.estado = '1' AND u.id_carrera = ?";
    } else { // ambos
        $sql = "SELECT u.id_usuario 
        FROM usuario u 
        LEFT JOIN alumno a ON u.id_usuario = a.id_usuario 
        LEFT JOIN profesor p ON u.id_usuario = p.id_usuario 
        WHERE u.rol IN ('1', '2') 
        AND (a.estado = '1' OR p.estado = '1') 
        AND u.id_carrera = ?";
    }
    
    // Preparar y ejecutar la consulta con parámetros
    $stmt = $conexion->prepare($sql);
    if ($destinatario == 'ambos') {
        $stmt->bind_param("i", $id_carrera_coordinador);
    } else {
        $stmt->bind_param("i", $id_carrera_coordinador);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $usuarios = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
    
    if (empty($usuarios)) {
        $_SESSION['error'] = "❌ No se encontraron destinatarios activos en tu carrera";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
        

        
        // Insertar notificaciones para cada usuario - USANDO MySQLi
        $insertStmt = $conexion->prepare("INSERT INTO notificaciones (id_usuario, titulo, mensaje, fecha) VALUES (?, ?, ?, NOW())");
        $contador = 0;
        
        foreach ($usuarios as $usuario) {
            $insertStmt->bind_param("iss", $usuario['id_usuario'], $titulo, $mensaje);
            $insertStmt->execute();
            $contador++;
        }
        
        $_SESSION['success'] = "✅ Notificación enviada exitosamente a $contador usuarios";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error al enviar la notificación: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>