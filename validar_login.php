<?php
session_start();
include "conexion.php";

$usuario = $_POST['usuario'];
$contraseñaIngresada = $_POST['contraseña'];

// Guardar el usuario intentado para mantenerlo en el formulario
$_SESSION['usuario_intentado'] = $usuario;

$stmt = $conexion->prepare("
    SELECT 
        u.*, 
        c.nombre AS carrera_nombre,
        CASE 
            WHEN u.rol = '1' THEN (SELECT estado FROM alumno WHERE id_usuario = u.id_usuario)
            WHEN u.rol = '2' THEN (SELECT estado FROM profesor WHERE id_usuario = u.id_usuario)
            WHEN u.rol = '3' THEN (SELECT estado FROM coordinador WHERE id_usuario = u.id_usuario)
            ELSE '1'
        END AS estado_usuario
    FROM usuario u 
    INNER JOIN carrera c ON u.id_carrera = c.id_carrera 
    WHERE (u.correo = ? OR u.clave = ?)
");

if ($stmt === false) {
    die("Error en la preparación: " . $conexion->error);
}

$stmt->bind_param("ss", $usuario, $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['login_error'] = "USUARIO O CONTRASEÑA INCORRECTOS";
    header("Location: login.php");
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();




$pwdGuardada = $data['contraseña'];

$loginValido = false;

if (preg_match('/^\$2y\$/', $pwdGuardada)) {
    // Está hasheada → usar password_verify
    if (password_verify($contraseñaIngresada, $pwdGuardada)) {
        $loginValido = true;
    }
} else {
    // Está en texto plano (transición) → comparar directo
    if ($contraseñaIngresada === $pwdGuardada) {
        $loginValido = true;

        // Y de una vez la migramos a hash
        $nuevoHash = password_hash($contraseñaIngresada, PASSWORD_BCRYPT);

        $update = $conexion->prepare("UPDATE usuario SET contraseña = ? WHERE id_usuario = ?");
        $update->bind_param("si", $nuevoHash, $data['id_usuario']);
        $update->execute();
        $update->close();
    }
}

if (!$loginValido) {
    $_SESSION['login_error'] = "USUARIO O CONTRASEÑA INCORRECTOS";
    header("Location: login.php");
    exit;
}


$estado_usuario = $data['estado_usuario'];

if ($estado_usuario != '1') {
    $mensaje = "Cuenta inactiva";

    if ($estado_usuario == '2') $mensaje = "Cuenta suspendida temporalmente";
    if ($estado_usuario == '3') $mensaje = "Cuenta dada de baja";
    if ($estado_usuario == '4') $mensaje = "Cuenta de egresado";

    $_SESSION['login_error'] = "ACCESO DENEGADO: $mensaje";
    header("Location: login.php");
    exit;
}

$_SESSION['id_usuario'] = $data['id_usuario'];
$_SESSION['nombre'] = $data['nombre'];
$_SESSION['apellidos'] = $data['apellidos'];
$_SESSION['rol'] = $data['rol'];
$_SESSION['carrera'] = $data['carrera_nombre'];
$_SESSION['clave'] = $data['clave'];

unset($_SESSION['login_error']);
unset($_SESSION['usuario_intentado']);

// Cargar ID según rol
switch ($data['rol']) {
    case '1':
        $q = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
        $q->bind_param("i", $data['id_usuario']);
        $q->execute();
        $r = $q->get_result();
        if ($f = $r->fetch_assoc()) $_SESSION['id_alumno'] = $f['id_alumno'];
        $q->close();
        break;

    case '2':
        $q = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
        $q->bind_param("i", $data['id_usuario']);
        $q->execute();
        $r = $q->get_result();
        if ($f = $r->fetch_assoc()) $_SESSION['id_profesor'] = $f['id_profesor'];
        $q->close();
        break;

    case '3':
        $q = $conexion->prepare("SELECT id_coordinador FROM coordinador WHERE id_usuario = ?");
        $q->bind_param("i", $data['id_usuario']);
        $q->execute();
        $r = $q->get_result();
        if ($f = $r->fetch_assoc()) $_SESSION['id_coordinador'] = $f['id_coordinador'];
        $q->close();
        break;
}

header("Location: animacion.php");
exit;
?>
