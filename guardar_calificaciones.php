<?php
ob_start();
session_start();
include "conexion.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar que el usuario sea profesor
    if ($_SESSION['rol'] != '2') {
        die("Acceso denegado");
    }

    $id_clase = $_POST['id_clase'] ?? null;
    $calificaciones = $_POST['calificaciones'] ?? [];
    $permiso_subir = $_POST['permiso_subir'] ?? 0;
    $permiso_modificar = $_POST['permiso_modificar'] ?? 0;

    if (!$id_clase) {
        die("ID de clase no especificado");
    }

    // OBTENER INFO DE LA CLASE Y MATERIA
    $sql_info = "
        SELECT m.nombre AS materia, p.id_profesor, u.nombre AS profesor, c.activo
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE c.id_clase = $id_clase
    ";
    $res_info = $conexion->query($sql_info);
    
    if (!$res_info || $res_info->num_rows == 0) {
        die("Clase no encontrada");
    }
    
    $info = $res_info->fetch_assoc();
    $materia = $info['materia'];
    $profesor = $info['profesor'];
    $curso_activo = $info['activo'];

    // Verificar si el curso está activo
    if (!$curso_activo) {
        die("Error: El curso está cerrado y no se pueden modificar calificaciones");
    }

    // OBTENER PERMISOS ACTUALES DESDE LA BASE DE DATOS (USANDO IDs ESPECÍFICOS)
    $sql_permisos = "SELECT id_accion, activo FROM acciones WHERE id_accion IN (1, 2)";
    $res_permisos = $conexion->query($sql_permisos);
    $permisos_db = [
        'modificar' => false,  // ID 1
        'subir' => false       // ID 2
    ];

    if ($res_permisos && $res_permisos->num_rows > 0) {
        while ($permiso = $res_permisos->fetch_assoc()) {
            if ($permiso['id_accion'] == 1) {
                $permisos_db['modificar'] = (bool)$permiso['activo'];
            } elseif ($permiso['id_accion'] == 2) {
                $permisos_db['subir'] = (bool)$permiso['activo'];
            }
        }
    }

    // SOBREESCRIBIR CON LOS PERMISOS ACTUALES DE LA BD
    $puede_subir = $permisos_db['subir'];
    $puede_modificar = $permisos_db['modificar'];

    // VERIFICAR CALIFICACIONES EXISTENTES PARA ESTA CLASE
    $sql_existentes = "
        SELECT COUNT(*) as total 
        FROM calificacion_clase cc
        INNER JOIN asignacion a ON cc.id_asignacion = a.id_asignacion
        WHERE a.id_clase = $id_clase
    ";
    $res_existentes = $conexion->query($sql_existentes);
    $total_calificaciones = $res_existentes->fetch_assoc()['total'];

    // DETERMINAR QUÉ ACCIÓN ESTÁ PERMITIDA
    $accion_permitida = false;

    if ($puede_subir) {
        $accion_permitida = 'subir';
    } elseif ($total_calificaciones > 0 && $puede_modificar) {
        $accion_permitida = 'modificar';
    }

    if (!$accion_permitida) {
        die("Error: No tiene permisos para realizar esta acción. Estado permisos - Subir: " . ($puede_subir ? 'SI' : 'NO') . ", Modificar: " . ($puede_modificar ? 'SI' : 'NO') . ", Calificaciones existentes: $total_calificaciones");
    }

    // CONTADORES PARA ESTADÍSTICAS
    $nuevas_calificaciones = 0;
    $calificaciones_actualizadas = 0;
    $alumnos_notificados = [];

    // RECORRER LAS CALIFICACIONES
    foreach ($calificaciones as $id_asignacion => $unidades) {
        $alumno_procesado = false;
        
        foreach ($unidades as $unidad => $cal) {
            if ($cal === "" || $cal === null) continue;
            
            $cal = floatval($cal);
            
            // Validar rango de calificación
            if ($cal < 0 || $cal > 100) {
                continue; // Saltar calificaciones fuera de rango
            }

            // VERIFICAR SI YA EXISTE LA CALIFICACIÓN
            $sql_existe = "SELECT calificacion FROM calificacion_clase WHERE id_asignacion = $id_asignacion AND unidad = $unidad";
            $res_existe = $conexion->query($sql_existe);

            if ($res_existe->num_rows > 0) {
                // CALIFICACIÓN EXISTENTE - VERIFICAR PERMISO DE MODIFICACIÓN (ID 1)
                if (!$puede_modificar) {
                    continue; // Saltar si no tiene permiso para modificar
                }
                
                $row = $res_existe->fetch_assoc();
                // SOLO ACTUALIZA SI CAMBIÓ LA CALIFICACIÓN
                if (floatval($row['calificacion']) != $cal) {
                    $conexion->query("UPDATE calificacion_clase SET calificacion = $cal WHERE id_asignacion = $id_asignacion AND unidad = $unidad");
                    $calificaciones_actualizadas++;
                    $alumno_procesado = true;
                }
            } else {
                // NUEVA CALIFICACIÓN - VERIFICAR PERMISO DE SUBIR (ID 2)
                if (!$puede_subir) {
                    continue; // Saltar si no tiene permiso para subir
                }
                
                $conexion->query("INSERT INTO calificacion_clase (id_asignacion, unidad, calificacion) VALUES ($id_asignacion, $unidad, $cal)");
                $nuevas_calificaciones++;
                $alumno_procesado = true;
            }
        }

        // ENVIAR NOTIFICACIÓN SOLO SI SE PROCESÓ ALGUNA CALIFICACIÓN PARA ESTE ALUMNO
        if ($alumno_procesado) {
            // OBTENER ID_USUARIO DEL ALUMNO PARA ENVIAR NOTIFICACIÓN
            $sql_alumno = "SELECT a.id_usuario, u.nombre, u.apellidos 
                           FROM asignacion asig
                           INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
                           INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                           WHERE asig.id_asignacion = $id_asignacion";
            $res_alumno = $conexion->query($sql_alumno);
            
            if ($res_alumno && $res_alumno->num_rows > 0) {
                $al = $res_alumno->fetch_assoc();
                $alumnos_notificados[] = $al['id_usuario'];

                // ENVIAR NOTIFICACIÓN AL ALUMNO
                $titulo = "CALIFICACIONES ACTUALIZADAS EN $materia";
                $mensaje = "EL PROFESOR $profesor ACTUALIZÓ TUS CALIFICACIONES EN LA MATERIA $materia.";
                
                // Escapar caracteres para SQL
                $titulo = $conexion->real_escape_string($titulo);
                $mensaje = $conexion->real_escape_string($mensaje);
                
                $conexion->query("INSERT INTO notificaciones (id_usuario, titulo, mensaje) VALUES ({$al['id_usuario']}, '$titulo', '$mensaje')");
            }
        }
    }

    // REGISTRAR LA ACCIÓN EN EL LOG (OPCIONAL)
    $accion_realizada = $accion_permitida == 'subir' ? 'Subida de calificaciones' : 'Modificación de calificaciones';
    $detalles = "Clase: $id_clase - Nuevas: $nuevas_calificaciones - Actualizadas: $calificaciones_actualizadas - Alumnos notificados: " . count($alumnos_notificados);
    
    // DEBUG: Registrar en logs del servidor
    error_log("GUARDAR_CALIFICACIONES - Acción: $accion_realizada, Detalles: $detalles, Permisos BD - Subir: " . ($permisos_db['subir'] ? 'SI' : 'NO') . ", Modificar: " . ($permisos_db['modificar'] ? 'SI' : 'NO'));

    // PREPARAR MENSAJE DE ÉXITO
    $mensaje_exito = "";
    if ($nuevas_calificaciones > 0 && $calificaciones_actualizadas > 0) {
        $mensaje_exito = "Se agregaron $nuevas_calificaciones nuevas calificaciones y se actualizaron $calificaciones_actualizadas existentes.";
    } elseif ($nuevas_calificaciones > 0) {
        $mensaje_exito = "Se agregaron $nuevas_calificaciones nuevas calificaciones correctamente.";
    } elseif ($calificaciones_actualizadas > 0) {
        $mensaje_exito = "Se actualizaron $calificaciones_actualizadas calificaciones correctamente.";
    } else {
        $mensaje_exito = "No se realizaron cambios en las calificaciones.";
    }

    // Redirigir con mensaje de éxito
    header("Location: profesor.php?exito=calificaciones&mensaje=" . urlencode($mensaje_exito));
    exit();

} else {
    // Si no es POST, redirigir
    header("Location: profesor.php");
    exit();
}
?>