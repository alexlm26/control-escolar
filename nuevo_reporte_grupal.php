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
$id_grupo = intval($_POST['id_grupo']);
$id_clase = isset($_POST['id_clase']) && !empty($_POST['id_clase']) ? intval($_POST['id_clase']) : 'NULL';
$tipo_incidencia = $conexion->real_escape_string($_POST['tipo_incidencia']);
$categoria = $conexion->real_escape_string($_POST['categoria']);
$fecha_incidente = $conexion->real_escape_string($_POST['fecha_incidente']);
$descripcion = $conexion->real_escape_string(trim($_POST['descripcion']));
$alumnos_involucrados = $conexion->real_escape_string(trim($_POST['alumnos_involucrados']));
$medidas_tomadas = $conexion->real_escape_string(trim($_POST['medidas_tomadas']));

// Validaciones básicas
if (empty($tipo_incidencia) || empty($categoria) || empty($fecha_incidente) || empty($descripcion)) {
    $_SESSION['error'] = "Todos los campos obligatorios deben ser completados";
    if ($_SESSION['rol'] == '2') {
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-grupales");
    } else {
        header("Location: ../detalle_grupo.php");
    }
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
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-grupales");
        exit;
    }

    $id_reportador = $usuario['id_profesor'];
    $tipo_reportador = 'profesor';

    // Verificar que el profesor es tutor del grupo
    $sql_verificar = "
        SELECT COUNT(*) as es_tutor 
        FROM grupo 
        WHERE id_grupo = $id_grupo AND tutor_asignado = $id_reportador AND activo = 1
    ";
    $result_verificar = $conexion->query($sql_verificar);
    $verificar = $result_verificar->fetch_assoc();

    if (!$verificar || $verificar['es_tutor'] == 0) {
        $_SESSION['error'] = "No tienes permisos para crear reportes sobre este grupo";
        header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-grupales");
        exit;
    }

    // Insertar el reporte grupal para PROFESOR
    $sql_insert = "
        INSERT INTO reportes_conducta_grupal (
            id_reportador,
            tipo_reportador,
            id_profesor,
            id_coordinador,
            id_prefecto,
            id_grupo,
            id_clase,
            tipo_incidencia,
            categoria,
            fecha_incidente,
            descripcion,
            alumnos_involucrados,
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
            $id_grupo,
            $id_clase,
            '$tipo_incidencia',
            '$categoria',
            '$fecha_incidente',
            '$descripcion',
            '$alumnos_involucrados',
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

    // Verificar que el grupo existe (prefectos pueden reportar cualquier grupo)
    $sql_verificar_grupo = "SELECT COUNT(*) as existe FROM grupo WHERE id_grupo = $id_grupo AND activo = 1";
    $result_verificar = $conexion->query($sql_verificar_grupo);
    $verificar = $result_verificar->fetch_assoc();

    if (!$verificar || $verificar['existe'] == 0) {
        $_SESSION['error'] = "El grupo no existe o está inactivo";
        header("Location: ../prefecto.php?error=Grupo no encontrado");
        exit;
    }

    // Insertar el reporte grupal para PREFECTO
    $sql_insert = "
        INSERT INTO reportes_conducta_grupal (
            id_reportador,
            tipo_reportador,
            id_profesor,
            id_coordinador,
            id_prefecto,
            id_grupo,
            id_clase,
            tipo_incidencia,
            categoria,
            fecha_incidente,
            descripcion,
            alumnos_involucrados,
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
            $id_grupo,
            $id_clase,
            '$tipo_incidencia',
            '$categoria',
            '$fecha_incidente',
            '$descripcion',
            '$alumnos_involucrados',
            '$medidas_tomadas',
            'activo',
            1,
            1
        )
    ";
}

// Ejecutar la inserción
if ($conexion->query($sql_insert)) {
    $_SESSION['exito'] = "Reporte grupal creado correctamente";
    
    // Obtener información para el log y notificaciones
    if ($rol_usuario == '2') {
        // Para profesores - obtener info del grupo
        $sql_info = "
            SELECT 
                g.nombre as grupo_nombre,
                u_profesor.nombre as profesor_nombre,
                u_profesor.apellidos as profesor_apellidos,
                car.nombre as carrera_nombre
            FROM grupo g
            INNER JOIN profesor p ON g.tutor_asignado = p.id_profesor
            INNER JOIN usuario u_profesor ON p.id_usuario = u_profesor.id_usuario
            INNER JOIN carrera car ON g.id_carrera = car.id_carrera
            WHERE g.id_grupo = $id_grupo
        ";
        
        $result_info = $conexion->query($sql_info);
        if ($result_info && $info = $result_info->fetch_assoc()) {
            error_log("REPORTE GRUPAL CREADO - Profesor: " . $info['profesor_nombre'] . " " . $info['profesor_apellidos'] . 
                      " - Grupo: " . $info['grupo_nombre'] . " - Tipo: " . $tipo_incidencia);
            
            // Notificar al coordinador
            $sql_notificacion = "
                INSERT INTO notificaciones (id_usuario, titulo, mensaje)
                SELECT 
                    u.id_usuario,
                    'Nuevo Reporte Grupal - Tutor',
                    CONCAT('El tutor ', ?, ' ', ?, 
                           ' ha creado un reporte grupal para el grupo ', ?,
                           ' de la carrera ', ?, '. Tipo: ', ?)
                FROM coordinador c
                INNER JOIN usuario u ON c.id_usuario = u.id_usuario
                WHERE c.id_carrera = $info['id_carrera'] OR c.id_carrera = 0
            ";
            
            $stmt_notif = $conexion->prepare($sql_notificacion);
            $tipo_display = ucfirst(str_replace('_', ' ', $tipo_incidencia));
            $stmt_notif->bind_param("sssss", 
                $info['profesor_nombre'], $info['profesor_apellidos'],
                $info['grupo_nombre'], $info['carrera_nombre'],
                $tipo_display
            );
            $stmt_notif->execute();
        }
    } else {
        // Para prefectos - obtener info básica
        $sql_info = "
            SELECT 
                g.nombre as grupo_nombre,
                u_prefecto.nombre as prefecto_nombre,
                u_prefecto.apellidos as prefecto_apellidos
            FROM grupo g
            INNER JOIN prefecto p ON p.id_prefecto = $id_reportador
            INNER JOIN usuario u_prefecto ON p.id_usuario = u_prefecto.id_usuario
            WHERE g.id_grupo = $id_grupo
        ";
        
        $result_info = $conexion->query($sql_info);
        if ($result_info && $info = $result_info->fetch_assoc()) {
            error_log("REPORTE GRUPAL CREADO - Prefecto: " . $info['prefecto_nombre'] . " " . $info['prefecto_apellidos'] . 
                      " - Grupo: " . $info['grupo_nombre'] . " - Tipo: " . $tipo_incidencia);
        }
    }
    
} else {
    $_SESSION['error'] = "Error al crear el reporte: " . $conexion->error;
    error_log("ERROR AL CREAR REPORTE GRUPAL: " . $conexion->error);
}

    header("Location: ../detalle_grupo.php?id=" . $id_grupo . "&seccion=reportes-grupales");

exit;
?>