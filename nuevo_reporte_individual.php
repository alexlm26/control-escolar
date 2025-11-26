<?php
session_start();
include "../conexion.php";

// Verificar permisos - profesores tutores (rol 2) y prefectos (rol 5)
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] != '2' && $_SESSION['rol'] != '5')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: " . ($_SESSION['rol'] == '2' ? '../profesor.php' : '../prefecto.php'));
    exit;
}

// Obtener datos del formulario
$id_alumno = intval($_POST['id_alumno']);
$id_grupo = intval($_POST['id_grupo']);
$id_clase = isset($_POST['id_clase']) && !empty($_POST['id_clase']) ? intval($_POST['id_clase']) : 'NULL';
$tipo_incidencia = $conexion->real_escape_string($_POST['tipo_incidencia']);
$categoria = $conexion->real_escape_string($_POST['categoria']);
$fecha_incidente = $conexion->real_escape_string($_POST['fecha_incidente']);
$descripcion = $conexion->real_escape_string(trim($_POST['descripcion']));
$medidas_tomadas = $conexion->real_escape_string(trim($_POST['medidas_tomadas']));

// Validaciones básicas
if (empty($id_alumno) || empty($tipo_incidencia) || empty($categoria) || empty($fecha_incidente) || empty($descripcion)) {
    $_SESSION['error'] = "Todos los campos obligatorios deben ser completados";
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-individuales");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol_usuario = $_SESSION['rol'];

// Verificar permisos según el rol
if ($rol_usuario == '2') { // PROFESOR TUTOR
    // Obtener ID del profesor tutor
    $sql_usuario = "SELECT p.id_profesor FROM profesor p WHERE p.id_usuario = $id_usuario";
    $result_usuario = $conexion->query($sql_usuario);
    $usuario = $result_usuario->fetch_assoc();

    if (!$usuario) {
        $_SESSION['error'] = "No se pudo identificar al profesor";
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-individuales");
        exit;
    }

    $id_reportador = $usuario['id_profesor'];
    $tipo_reportador = 'profesor';

    // Verificar permisos sobre el alumno
    $sql_verificar = "
        SELECT COUNT(*) as tiene_acceso 
        FROM alumno_grupo ag 
        INNER JOIN grupo g ON ag.id_grupo = g.id_grupo 
        WHERE ag.id_alumno = $id_alumno AND g.id_grupo = $id_grupo AND g.tutor_asignado = $id_reportador AND ag.activo = 1
    ";
    $result_verificar = $conexion->query($sql_verificar);
    $verificar = $result_verificar->fetch_assoc();

    if (!$verificar || $verificar['tiene_acceso'] == 0) {
        $_SESSION['error'] = "No tienes permisos para crear reportes sobre este alumno";
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-individuales");
        exit;
    }

    // Insertar el reporte individual para PROFESOR
    $sql_insert = "
        INSERT INTO reportes_conducta_individual (
            id_reportador,
            tipo_reportador,
            id_profesor,
            id_coordinador,
            id_prefecto,
            id_alumno,
            id_clase,
            tipo_incidencia,
            categoria,
            fecha_incidente,
            descripcion,
            medidas_tomadas,
            estado,
            se_notifico_profesor,
            se_notifico_tutor
        ) VALUES (
            $id_reportador,
            '$tipo_reportador',
            $id_reportador,
            NULL,
            NULL,
            $id_alumno,
            $id_clase,
            '$tipo_incidencia',
            '$categoria',
            '$fecha_incidente',
            '$descripcion',
            '$medidas_tomadas',
            'activo',
            0,
            0
        )
    ";

} elseif ($rol_usuario == '5') { // PREFECTO
    // Obtener ID del prefecto
    $sql_usuario = "SELECT p.id_prefecto FROM prefecto p WHERE p.id_usuario = $id_usuario";
    $result_usuario = $conexion->query($sql_usuario);
    $usuario = $result_usuario->fetch_assoc();

    if (!$usuario) {
        $_SESSION['error'] = "No se pudo identificar al prefecto";
        header("Location: ../prefecto.php?error=No se pudo identificar al prefecto");
        exit;
    }

    $id_reportador = $usuario['id_prefecto'];
    $tipo_reportador = 'prefecto';

    // Verificar que el alumno existe (prefectos pueden reportar cualquier alumno)
    $sql_verificar_alumno = "SELECT COUNT(*) as existe FROM alumno WHERE id_alumno = $id_alumno";
    $result_verificar = $conexion->query($sql_verificar_alumno);
    $verificar = $result_verificar->fetch_assoc();

    if (!$verificar || $verificar['existe'] == 0) {
        $_SESSION['error'] = "El alumno no existe";
        header("Location: ../prefecto.php?error=Alumno no encontrado");
        exit;
    }

    // Insertar el reporte individual para PREFECTO
    $sql_insert = "
        INSERT INTO reportes_conducta_individual (
            id_reportador,
            tipo_reportador,
            id_profesor,
            id_coordinador,
            id_prefecto,
            id_alumno,
            id_clase,
            tipo_incidencia,
            categoria,
            fecha_incidente,
            descripcion,
            medidas_tomadas,
            estado,
            se_notifico_profesor,
            se_notifico_tutor
        ) VALUES (
            $id_reportador,
            '$tipo_reportador',
            NULL,
            NULL,
            $id_reportador,
            $id_alumno,
            $id_clase,
            '$tipo_incidencia',
            '$categoria',
            '$fecha_incidente',
            '$descripcion',
            '$medidas_tomadas',
            'activo',
            1,
            1
        )
    ";
}

// Ejecutar la inserción
if ($conexion->query($sql_insert)) {
    $_SESSION['exito'] = "Reporte individual creado correctamente";
    
    // Obtener información para el log y notificaciones
    if ($rol_usuario == '2') {
        // Para profesores - obtener info del alumno y grupo
        $sql_info = "
            SELECT 
                u_alumno.nombre as alumno_nombre,
                u_alumno.apellidos as alumno_apellidos,
                u_profesor.nombre as profesor_nombre,
                u_profesor.apellidos as profesor_apellidos,
                g.nombre as grupo_nombre
            FROM alumno a
            INNER JOIN usuario u_alumno ON a.id_usuario = u_alumno.id_usuario
            INNER JOIN alumno_grupo ag ON a.id_alumno = ag.id_alumno
            INNER JOIN grupo g ON ag.id_grupo = g.id_grupo
            INNER JOIN profesor p ON g.tutor_asignado = p.id_profesor
            INNER JOIN usuario u_profesor ON p.id_usuario = u_profesor.id_usuario
            WHERE a.id_alumno = $id_alumno AND g.id_grupo = $id_grupo
        ";
        
        $result_info = $conexion->query($sql_info);
        if ($result_info && $info = $result_info->fetch_assoc()) {
            error_log("REPORTE INDIVIDUAL CREADO - Profesor: " . $info['profesor_nombre'] . " " . $info['profesor_apellidos'] . 
                      " - Alumno: " . $info['alumno_nombre'] . " " . $info['alumno_apellidos'] . 
                      " - Grupo: " . $info['grupo_nombre']);
            
            // Notificar al coordinador
            $sql_notificacion = "
                INSERT INTO notificaciones (id_usuario, titulo, mensaje)
                SELECT 
                    u.id_usuario,
                    'Nuevo Reporte Individual - Tutor',
                    CONCAT('El tutor ', ?, ' ', ?, 
                           ' ha creado un reporte individual para el alumno ', 
                           ?, ' ', ?,
                           ' en el grupo ', ?)
                FROM coordinador c
                INNER JOIN usuario u ON c.id_usuario = u.id_usuario
                WHERE c.id_carrera = (SELECT id_carrera FROM grupo WHERE id_grupo = $id_grupo) 
                   OR c.id_carrera = 0
            ";
            
            $stmt_notif = $conexion->prepare($sql_notificacion);
            $stmt_notif->bind_param("sssss", 
                $info['profesor_nombre'], $info['profesor_apellidos'],
                $info['alumno_nombre'], $info['alumno_apellidos'],
                $info['grupo_nombre']
            );
            $stmt_notif->execute();
        }
    } else {
        // Para prefectos - obtener info básica
        $sql_info = "
            SELECT 
                u_alumno.nombre as alumno_nombre,
                u_alumno.apellidos as alumno_apellidos,
                u_prefecto.nombre as prefecto_nombre,
                u_prefecto.apellidos as prefecto_apellidos
            FROM alumno a
            INNER JOIN usuario u_alumno ON a.id_usuario = u_alumno.id_usuario
            INNER JOIN prefecto p ON p.id_prefecto = $id_reportador
            INNER JOIN usuario u_prefecto ON p.id_usuario = u_prefecto.id_usuario
            WHERE a.id_alumno = $id_alumno
        ";
        
        $result_info = $conexion->query($sql_info);
        if ($result_info && $info = $result_info->fetch_assoc()) {
            error_log("REPORTE INDIVIDUAL CREADO - Prefecto: " . $info['prefecto_nombre'] . " " . $info['prefecto_apellidos'] . 
                      " - Alumno: " . $info['alumno_nombre'] . " " . $info['alumno_apellidos']);
        }
    }
    
} else {
    $_SESSION['error'] = "Error al crear el reporte: " . $conexion->error;
    error_log("ERROR AL CREAR REPORTE INDIVIDUAL: " . $conexion->error);
}

// Redirigir según el rol
if ($rol_usuario == '2') {
    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-individuales");
} else {
    header("Location: ../prefecto.php?exito=Reporte individual creado correctamente");
}
exit;
?>