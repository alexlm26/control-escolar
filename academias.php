<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre'];

$mensaje_exito = '';
$mensaje_error = '';

if ($_POST && isset($_POST['unirse_academia']) && $rol == 2) {
    
    if (!isset($_POST['id_academia']) || empty($_POST['id_academia'])) {
        $mensaje_error = "Por favor ingresa un ID de academia válido";
    } else {
        $id_academia = intval($_POST['id_academia']);
        
        if ($id_academia > 0) {
            $query_profesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
            $query_profesor->bind_param("i", $id_usuario);
            $query_profesor->execute();
            $result_profesor = $query_profesor->get_result();
            
            if ($result_profesor->num_rows > 0) {
                $profesor = $result_profesor->fetch_assoc();
                $id_profesor = $profesor['id_profesor'];
                
                $query_verificar = $conexion->prepare("SELECT id_academia, nombre FROM academia WHERE id_academia = ? AND activo = 1");
                $query_verificar->bind_param("i", $id_academia);
                $query_verificar->execute();
                $result_verificar = $query_verificar->get_result();
                
                if ($result_verificar->num_rows > 0) {
                    $academia_data = $result_verificar->fetch_assoc();
                    
                    $query_miembro = $conexion->prepare("SELECT id_profesor_academia FROM profesor_academia WHERE id_profesor = ? AND id_academia = ? AND activo = 1");
                    $query_miembro->bind_param("ii", $id_profesor, $id_academia);
                    $query_miembro->execute();
                    $result_miembro = $query_miembro->get_result();
                    
                    if ($result_miembro->num_rows == 0) {
                        $query_unirse = $conexion->prepare("INSERT INTO profesor_academia (id_profesor, id_academia, rol, activo, fecha_union) VALUES (?, ?, 'miembro', 1, NOW())");
                        $query_unirse->bind_param("ii", $id_profesor, $id_academia);
                        
                        if ($query_unirse->execute()) {
                            $mensaje_exito = "¡Te has unido exitosamente a la academia: " . $academia_data['nombre'] . "!";
                            echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
                        } else {
                            $mensaje_error = "Error al unirse a la academia: " . $conexion->error;
                        }
                    } else {
                        $mensaje_error = "Ya eres miembro de esta academia";
                    }
                } else {
                    $mensaje_error = "No se encontró la academia con ID: $id_academia o está inactiva";
                }
            } else {
                $mensaje_error = "Error: No se encontró tu perfil de profesor";
            }
        } else {
            $mensaje_error = "ID de academia inválido";
        }
    }
}

if ($_POST && isset($_POST['marcar_asistencia']) && $rol == 2) {
    $id_tarea_academia = intval($_POST['id_tarea_academia']);
    
    $query_profesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
    $query_profesor->bind_param("i", $id_usuario);
    $query_profesor->execute();
    $profesor = $query_profesor->get_result()->fetch_assoc();
    $id_profesor = $profesor['id_profesor'];
    
    $query_verificar = $conexion->prepare("SELECT id_entrega_academia FROM entregas_tareas_academia WHERE id_tarea_academia = ? AND id_profesor_entrega = ?");
    $query_verificar->bind_param("ii", $id_tarea_academia, $id_profesor);
    $query_verificar->execute();
    $result_verificar = $query_verificar->get_result();
    
    if ($result_verificar->num_rows == 0) {
        $query_asistencia = $conexion->prepare("INSERT INTO entregas_tareas_academia (id_tarea_academia, id_profesor_entrega, archivo_entrega, nombre_archivo_original, comentario_entrega, estado) VALUES (?, ?, 'asistencia_automatica.pdf', 'Asistencia registrada', 'Asistencia marcada automáticamente para la junta', 'entregado')");
        $query_asistencia->bind_param("ii", $id_tarea_academia, $id_profesor);
        
        if ($query_asistencia->execute()) {
            $mensaje_exito = "Asistencia marcada exitosamente";
            echo "<script>setTimeout(function(){ window.location.reload(); }, 1500);</script>";
        } else {
            $mensaje_error = "Error al marcar asistencia";
        }
    } else {
        $mensaje_error = "Ya has marcado asistencia para esta junta";
    }
}

$id_carrera_coordinador = 0;
if ($rol == 3) {
    $query_coordinador = $conexion->prepare("
        SELECT id_carrera 
        FROM coordinador 
        WHERE id_usuario = ?
    ");
    $query_coordinador->bind_param("i", $id_usuario);
    $query_coordinador->execute();
    $result_coordinador = $query_coordinador->get_result();
    if ($coordinador = $result_coordinador->fetch_assoc()) {
        $id_carrera_coordinador = $coordinador['id_carrera'];
    }
}

if ($rol == 2) {
    $query_academias = $conexion->prepare("
        SELECT 
            a.id_academia,
            a.nombre,
            a.descripcion,
            a.fecha_creacion,
            car.nombre as carrera_nombre,
            esp.nombre as especialidad_nombre,
            CONCAT(u_pres.nombre, ' ', u_pres.apellidos) as presidente_nombre,
            pa.rol as rol_profesor,
            (SELECT COUNT(*) FROM profesor_academia WHERE id_academia = a.id_academia AND activo = 1) as total_miembros,
            CASE 
                WHEN a.id_presidente = p.id_profesor THEN 'presidente'
                ELSE pa.rol
            END as rol_actual
        FROM academia a
        INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
        INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
        INNER JOIN usuario u ON p.id_usuario = u.id_usuario
        INNER JOIN profesor p_pres ON a.id_presidente = p_pres.id_profesor
        INNER JOIN usuario u_pres ON p_pres.id_usuario = u_pres.id_usuario
        LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
        LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
        WHERE u.id_usuario = ? AND pa.activo = 1 AND a.activo = 1
        GROUP BY a.id_academia
        ORDER BY a.nombre
    ");
    $query_academias->bind_param("i", $id_usuario);
    
} else {
    if ($id_carrera_coordinador == 0) {
        $query_academias = $conexion->prepare("
            SELECT 
                a.id_academia,
                a.nombre,
                a.descripcion,
                a.fecha_creacion,
                car.nombre as carrera_nombre,
                esp.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as presidente_nombre,
                (SELECT COUNT(*) FROM profesor_academia WHERE id_academia = a.id_academia AND activo = 1) as total_miembros,
                'coordinador' as rol_profesor,
                'coordinador' as rol_actual
            FROM academia a
            LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
            LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
            INNER JOIN profesor p ON a.id_presidente = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE a.activo = 1
            ORDER BY a.nombre
        ");
    } else {
        $query_academias = $conexion->prepare("
            SELECT 
                a.id_academia,
                a.nombre,
                a.descripcion,
                a.fecha_creacion,
                car.nombre as carrera_nombre,
                esp.nombre as especialidad_nombre,
                CONCAT(u.nombre, ' ', u.apellidos) as presidente_nombre,
                (SELECT COUNT(*) FROM profesor_academia WHERE id_academia = a.id_academia AND activo = 1) as total_miembros,
                'coordinador' as rol_profesor,
                'coordinador' as rol_actual
            FROM academia a
            LEFT JOIN carrera car ON a.id_carrera = car.id_carrera
            LEFT JOIN especialidad esp ON a.id_especialidad = esp.id_especialidad
            INNER JOIN profesor p ON a.id_presidente = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE a.activo = 1 AND (a.id_carrera = ? OR esp.id_carrera = ?)
            ORDER BY a.nombre
        ");
        $query_academias->bind_param("ii", $id_carrera_coordinador, $id_carrera_coordinador);
    }
}

$query_academias->execute();
$result_academias = $query_academias->get_result();
$academias = $result_academias ? $result_academias->fetch_all(MYSQLI_ASSOC) : [];

$tareas_academia = [];
if ($rol == 2 && count($academias) > 0) {
    $query_profesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
    $query_profesor->bind_param("i", $id_usuario);
    $query_profesor->execute();
    $profesor = $query_profesor->get_result()->fetch_assoc();
    $id_profesor = $profesor['id_profesor'];
    
    $query_tareas = $conexion->prepare("
        SELECT 
            ta.*,
            a.nombre as academia_nombre,
            CONCAT(u.nombre, ' ', u.apellidos) as asignador_nombre,
            eta.id_entrega_academia,
            eta.estado as estado_entrega,
            eta.calificacion
        FROM tareas_academia ta
        INNER JOIN academia a ON ta.id_academia = a.id_academia
        INNER JOIN profesor p_asigna ON ta.id_profesor_asigna = p_asigna.id_profesor
        INNER JOIN usuario u ON p_asigna.id_usuario = u.id_usuario
        INNER JOIN profesor_academia pa ON a.id_academia = pa.id_academia
        LEFT JOIN entregas_tareas_academia eta ON ta.id_tarea_academia = eta.id_tarea_academia 
            AND eta.id_profesor_entrega = ?
        WHERE pa.id_profesor = ? AND pa.activo = 1 AND a.activo = 1
        ORDER BY ta.fecha_limite ASC
    ");
    $query_tareas->bind_param("ii", $id_profesor, $id_profesor);
    $query_tareas->execute();
    $result_tareas = $query_tareas->get_result();
    $tareas_academia = $result_tareas ? $result_tareas->fetch_all(MYSQLI_ASSOC) : [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academias - <?php echo $rol == 2 ? 'Profesor' : 'Coordinador'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --color-primario: #1565c0;
        --color-secundario: #1976d2;
        --color-terciario: #42a5f5;
        --color-fondo: #f4f6f8;
        --color-texto: #333;
        --color-blanco: #fff;
        --color-gris-claro: #f1f5f9;
        --color-gris-medio: #e2e8f0;
        --color-gris-oscuro: #64748b;
        --color-exito: #10b981;
        --color-error: #ef4444;
        --color-advertencia: #f59e0b;
        --sombra-suave: 0 4px 12px rgba(0,0,0,0.08);
        --sombra-media: 0 6px 20px rgba(0,0,0,0.12);
        --sombra-hover: 0 10px 25px rgba(0,0,0,0.15);
        --radio-borde: 14px;
        --transicion-rapida: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: var(--color-fondo);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--color-texto);
        line-height: 1.6;
    }

    .content {
        padding: 30px 5%;
        max-width: 1400px;
        margin: 0 auto;
    }

    h2 {
        color: var(--color-primario);
        margin-bottom: 20px;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.5px;
        font-size: 2.2rem;
    }

    .banner-bienvenida {
        background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
        color: white;
        padding: 70px 20px;
        text-align: center;
        overflow: hidden;
        position: relative;
        box-shadow: var(--sombra-media);
        margin-bottom: 40px;
    }

    .banner-bienvenida::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle at center, rgba(255,255,255,0.15), transparent 60%);
        animation: moverLuz 5s linear infinite;
    }

    @keyframes moverLuz {
        0% { transform: translateX(-100%); }
        50% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }

    .banner-texto {
        position: relative;
        z-index: 2;
        max-width: 900px;
        margin: 0 auto;
    }

    .banner-bienvenida h1 {
        font-size: 2.8rem;
        font-weight: 800;
        letter-spacing: 1px;
        margin-bottom: 15px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        opacity: 0;
        transform: translateY(-30px);
        animation: aparecerTitulo 1s ease-out forwards;
    }

    .banner-bienvenida p {
        font-size: 1.3rem;
        font-weight: 400;
        opacity: 0.9;
        max-width: 700px;
        margin: 0 auto;
        opacity: 0;
        transform: translateY(30px);
        animation: aparecerSubtitulo 1.5s ease-out forwards;
        animation-delay: 0.5s;
    }

    @keyframes aparecerTitulo {
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes aparecerSubtitulo {
        to { opacity: 1; transform: translateY(0); }
    }

    .botones-principales {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .btn-principal {
        background: var(--color-blanco);
        color: var(--color-primario);
        border: 2px solid var(--color-primario);
        padding: 14px 28px;
        border-radius: var(--radio-borde);
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transicion-rapida);
        box-shadow: var(--sombra-suave);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-principal:hover {
        background: var(--color-primario);
        color: var(--color-blanco);
        transform: translateY(-3px);
        box-shadow: var(--sombra-hover);
    }

    .btn-principal.active {
        background: var(--color-primario);
        color: var(--color-blanco);
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
    }

    .btn-crear {
        background: linear-gradient(135deg, var(--color-exito), #34d399);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: var(--radio-borde);
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transicion-rapida);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-crear:hover {
        background: linear-gradient(135deg, #0da271, #2bbd8e);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        color: white;
        text-decoration: none;
    }

    .btn-unirse {
        background: linear-gradient(135deg, var(--color-advertencia), #fbbf24);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: var(--radio-borde);
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transicion-rapida);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-unirse:hover {
        background: linear-gradient(135deg, #d97706, #f59e0b);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        color: white;
    }

    .seccion {
        display: none;
        animation: fadeIn 0.5s ease;
    }

    .seccion.activa {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .filtros-tareas {
        background: white;
        padding: 25px;
        border-radius: var(--radio-borde);
        margin-bottom: 25px;
        box-shadow: var(--sombra-suave);
    }

    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--color-gris-oscuro);
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid var(--color-gris-medio);
        border-radius: 10px;
        font-size: 1rem;
        transition: var(--transicion-rapida);
        background: var(--color-gris-claro);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--color-primario);
        background: var(--color-blanco);
        box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.2);
    }

    .btn-filtrar {
        background: var(--color-primario);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transicion-rapida);
    }

    .btn-filtrar:hover {
        background: var(--color-secundario);
        transform: translateY(-2px);
        box-shadow: var(--sombra-suave);
    }

    .grid-academias {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 30px;
        margin-top: 30px;
    }

    .tarjeta-academia {
        background: var(--color-blanco);
        border-radius: var(--radio-borde);
        overflow: hidden;
        box-shadow: var(--sombra-suave);
        transition: var(--transicion-rapida);
        cursor: pointer;
        border-left: 5px solid var(--color-primario);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .tarjeta-academia:hover {
        transform: translateY(-8px);
        box-shadow: var(--sombra-hover);
    }

    .tarjeta-academia-header {
        background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
        color: white;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }

    .tarjeta-academia-header::after {
        content: "";
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.1);
        transform: rotate(30deg);
    }

    .tarjeta-academia-header h3 {
        margin: 0 0 10px 0;
        font-size: 1.5rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .tarjeta-academia-header .rol {
        background: rgba(255,255,255,0.2);
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        display: inline-block;
        position: relative;
        z-index: 1;
        backdrop-filter: blur(5px);
    }

    .tarjeta-academia-body {
        padding: 25px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--color-gris-medio);
    }

    .info-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .info-label {
        font-weight: 600;
        color: var(--color-gris-oscuro);
        flex: 1;
    }

    .info-value {
        color: var(--color-primario);
        font-weight: 500;
        text-align: right;
        flex: 1;
    }

    .academia-actions {
        margin-top: auto;
        padding-top: 20px;
        display: flex;
        gap: 10px;
    }

    .grid-tareas {
        display: grid;
        gap: 25px;
    }

    .tarjeta-tarea {
        background: white;
        border-radius: var(--radio-borde);
        padding: 25px;
        box-shadow: var(--sombra-suave);
        transition: var(--transicion-rapida);
        border-left: 5px solid var(--color-primario);
    }

    .tarjeta-tarea:hover {
        transform: translateY(-5px);
        box-shadow: var(--sombra-hover);
    }

    .tarjeta-tarea-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tarjeta-tarea-header h3 {
        margin: 0;
        color: var(--color-primario);
        flex: 1;
        min-width: 200px;
        font-size: 1.3rem;
    }

    .tarea-academia {
        background: #e3f2fd;
        color: var(--color-primario);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .tipo-tarea {
        background: #e8f5e8;
        color: var(--color-exito);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .tarea-stats {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .stat {
        background: #e3f2fd;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        color: var(--color-primario);
        font-weight: 600;
    }

    .tarea-fecha {
        color: var(--color-gris-oscuro);
        margin-bottom: 15px;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 0.95rem;
    }

    .tarea-descripcion {
        color: var(--color-texto);
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .estado-tarea {
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .estado-pendiente {
        background: #fff3e0;
        color: #f57c00;
    }

    .estado-entregada {
        background: #e8f5e8;
        color: #2e7d32;
    }

    .estado-calificada {
        background: #e3f2fd;
        color: #1976d2;
    }

    .estado-vencida {
        background: #ffebee;
        color: #c62828;
    }

    .estado-cancelada {
        background: #f5f5f5;
        color: #757575;
        text-decoration: line-through;
    }

    .tarea-vencida {
        border-left: 5px solid #c62828;
        opacity: 0.9;
    }

    .tarea-cancelada {
        border-left: 5px solid #757575;
        opacity: 0.8;
    }

    .acciones-tarea {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transicion-rapida);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: var(--color-primario);
        color: white;
    }

    .btn-primary:hover {
        background: var(--color-secundario);
        transform: translateY(-2px);
        box-shadow: var(--sombra-suave);
    }

    .btn-secondary {
        background: var(--color-gris-oscuro);
        color: white;
    }

    .btn-secondary:hover {
        background: #475569;
        transform: translateY(-2px);
    }

    .btn-success {
        background: var(--color-exito);
        color: white;
    }

    .btn-success:hover {
        background: #0da271;
        transform: translateY(-2px);
    }

    .btn-warning {
        background: var(--color-advertencia);
        color: white;
    }

    .btn-warning:hover {
        background: #d97706;
        transform: translateY(-2px);
    }

    .btn-danger {
        background: var(--color-error);
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .btn:disabled {
        background: #cccccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .rol-presidente {
        background: linear-gradient(135deg, #ff6b35, #ff8e53);
        color: white;
    }

    .rol-vicepresidente {
        background: linear-gradient(135deg, #4ecdc4, #44a08d);
        color: white;
    }

    .rol-secretario {
        background: linear-gradient(135deg, #45b7d1, #96c93d);
        color: white;
    }

    .rol-miembro {
        background: linear-gradient(135deg, #a8a8a8, #7a7a7a);
        color: white;
    }

    .rol-coordinador {
        background: linear-gradient(135deg, #1565c0, #1976d2);
        color: white;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal {
        background: white;
        border-radius: var(--radio-borde);
        padding: 30px;
        width: 90%;
        max-width: 500px;
        box-shadow: var(--sombra-hover);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--color-gris-medio);
    }

    .modal-header h3 {
        margin: 0;
        color: var(--color-primario);
        font-size: 1.5rem;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--color-gris-oscuro);
        transition: var(--transicion-rapida);
    }

    .close-modal:hover {
        color: var(--color-error);
        transform: rotate(90deg);
    }

    .modal-body {
        margin-bottom: 25px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .alert {
        padding: 15px 20px;
        border-radius: var(--radio-borde);
        margin-bottom: 25px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: var(--sombra-suave);
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 5px solid var(--color-exito);
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 5px solid var(--color-error);
    }

    @media (max-width: 1024px) {
        .grid-academias {
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .grid-academias, .grid-tareas {
            grid-template-columns: 1fr;
        }
        
        .botones-principales {
            flex-direction: column;
            align-items: center;
        }
        
        .btn-principal, .btn-crear, .btn-unirse {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
        
        .tarjeta-tarea-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .filtros-grid {
            grid-template-columns: 1fr;
        }
        
        .banner-bienvenida {
            padding: 50px 15px;
        }
        
        .banner-bienvenida h1 {
            font-size: 2.2rem;
        }
        
        .banner-bienvenida p {
            font-size: 1.1rem;
        }

        .content {
            padding: 20px 4%;
        }

        .academia-actions {
            flex-direction: column;
        }

        .header-actions {
            flex-direction: column;
            align-items: flex-start;
        }

        .action-buttons {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .grid-academias {
            grid-template-columns: 1fr;
        }
        
        .banner-bienvenida h1 {
            font-size: 1.8rem;
        }
        
        .banner-bienvenida p {
            font-size: 1rem;
        }

        .tarjeta-academia-header h3 {
            font-size: 1.3rem;
        }

        .modal {
            padding: 20px;
            width: 95%;
        }
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--color-gris-oscuro);
        background: white;
        border-radius: var(--radio-borde);
        box-shadow: var(--sombra-suave);
    }

    .empty-state h3 {
        color: var(--color-gris-oscuro);
        margin-bottom: 15px;
        font-size: 1.5rem;
    }

    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 25px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
        color: var(--color-primario);
    }

    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    </style>
</head>
<body>

<section class="banner-bienvenida">
    <div class="banner-texto">
        <h1>
            ACADEMIAS - <?php echo strtoupper($rol == 2 ? 'PROFESOR' : 'COORDINADOR'); ?>
        </h1>
        <p>
            <?php 
                if ($rol == 2) {
                    echo "Gestiona tus academias y mantente al día con las tareas asignadas";
                } else {
                    echo "Supervisa y gestiona las academias de tu carrera y especialidades";
                }
            ?>
        </p>
    </div>
</section>

<main class="content">
    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($mensaje_exito); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
    <?php endif; ?>

    <div class="header-actions">
        <div class="botones-principales">
            <button class="btn-principal active" onclick="mostrarSeccion('academias')">
                <i class="fas fa-users"></i> Mis Academias
            </button>
            <?php if ($rol == 2 && count($tareas_academia) > 0): ?>
                <button class="btn-principal" onclick="mostrarSeccion('tareas')">
                    <i class="fas fa-tasks"></i> Tareas de Academia
                </button>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <?php if ($rol == 3): ?>
                <a href="crear_academia.php" class="btn-crear">
                    <i class="fas fa-plus"></i> Crear Nueva Academia
                </a>
            <?php endif; ?>
            
            <?php if ($rol == 2): ?>
               
            <?php endif; ?>
        </div>
    </div>

    <div id="seccion-academias" class="seccion activa">
        <div class="grid-academias">
            <?php if (count($academias) > 0): ?>
                <?php foreach($academias as $academia): ?>
                    <div class="tarjeta-academia" onclick="verAcademia(<?php echo $academia['id_academia']; ?>)">
                        <div class="tarjeta-academia-header">
                            <h3><?php echo htmlspecialchars($academia['nombre']); ?></h3>
                            <span class="rol rol-<?php echo strtolower($academia['rol_actual']); ?>">
                                <?php 
                                    if ($academia['rol_actual'] == 'presidente') {
                                        echo 'Eres el Presidente';
                                    } elseif ($academia['rol_actual'] == 'coordinador') {
                                        echo 'Coordinador';
                                    } else {
                                        echo ucfirst($academia['rol_actual']);
                                    }
                                ?>
                            </span>
                        </div>
                        
                        <div class="tarjeta-academia-body">
                            <?php if ($academia['descripcion']): ?>
                                <div class="info-item">
                                    <span class="info-label">Descripción:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($academia['descripcion']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <span class="info-label">Presidente:</span>
                                <span class="info-value"><?php echo htmlspecialchars($academia['presidente_nombre']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Miembros:</span>
                                <span class="info-value"><?php echo $academia['total_miembros']; ?> profesores</span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Vinculación:</span>
                                <span class="info-value">
                                    <?php 
                                        if ($academia['carrera_nombre']) {
                                            echo htmlspecialchars($academia['carrera_nombre']);
                                        } elseif ($academia['especialidad_nombre']) {
                                            echo htmlspecialchars($academia['especialidad_nombre']);
                                        } else {
                                            echo 'General';
                                        }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Fecha creación:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($academia['fecha_creacion'])); ?></span>
                            </div>
                            
                           
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>
                        <?php 
                            if ($rol == 2) {
                                echo "NO ESTÁS EN NINGUNA ACADEMIA";
                            } else {
                                echo "NO HAY ACADEMIAS CREADAS";
                            }
                        ?>
                    </h3>
                    <p>
                        <?php 
                            if ($rol == 2) {
                                echo "Actualmente no perteneces a ninguna academia. Puedes unirte a una existente usando el botón 'Unirse a Academia'.";
                            } else {
                                echo "Aún no se han creado academias para tu carrera o especialidad. Puedes crear una nueva academia usando el botón superior.";
                            }
                        ?>
                    </p>
                    <?php if ($rol == 3): ?>
                        <a href="crear_academia.php" class="btn-crear" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Crear Primera Academia
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($rol == 2): ?>
        <div id="seccion-tareas" class="seccion">
            <?php if (count($tareas_academia) > 0): ?>
                <div class="filtros-tareas">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label for="filtro-academia">Filtrar por academia:</label>
                            <select id="filtro-academia" class="form-control">
                                <option value="">Todas las academias</option>
                                <?php foreach($academias as $academia): ?>
                                    <option value="<?php echo $academia['id_academia']; ?>">
                                        <?php echo htmlspecialchars($academia['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filtro-estado">Filtrar por estado:</label>
                            <select id="filtro-estado" class="form-control">
                                <option value="">Todas las tareas</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="entregada">Entregadas</option>
                                <option value="calificada">Calificadas</option>
                                <option value="vencida">Vencidas</option>
                                <option value="cancelada">Canceladas</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filtro-fecha">Ordenar por fecha:</label>
                            <select id="filtro-fecha" class="form-control">
                                <option value="asc">Próximas a vencer</option>
                                <option value="desc">Más recientes primero</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button class="btn-filtrar" onclick="aplicarFiltros()">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid-tareas" id="lista-tareas">
                    <?php foreach($tareas_academia as $tarea): ?>
                        <?php 
                            $estado = $tarea['estado_entrega'] ?: 'pendiente';
                            $clase_adicional = '';
                            if ($estado == 'vencida') {
                                $clase_adicional = 'tarea-vencida';
                            } elseif ($estado == 'cancelada') {
                                $clase_adicional = 'tarea-cancelada';
                            }
                            
                            $es_junta = ($tarea['tipo_tarea'] == 'otro');
                        ?>
                        <div class="tarjeta-tarea <?php echo $clase_adicional; ?>" 
                             data-academia="<?php echo $tarea['id_academia']; ?>"
                             data-estado="<?php echo $estado; ?>"
                             data-fecha="<?php echo $tarea['fecha_limite']; ?>">
                            
                            <div class="tarjeta-tarea-header">
                                <h3><?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                                <div class="tarea-academia"><?php echo htmlspecialchars($tarea['academia_nombre']); ?></div>
                                <span class="tipo-tarea">
                                    <?php 
                                        switch($tarea['tipo_tarea']) {
                                            case 'avance_grupo': echo 'Avance de Grupo'; break;
                                            case 'informe': echo 'Informe'; break;
                                            case 'revision': echo 'Revisión'; break;
                                            case 'otro': echo 'Junta'; break;
                                            default: echo ucfirst($tarea['tipo_tarea']);
                                        }
                                    ?>
                                </span>
                                <span class="estado-tarea estado-<?php echo $estado; ?>">
                                    <?php 
                                        switch($estado) {
                                            case 'pendiente': echo 'Pendiente'; break;
                                            case 'entregada': echo 'Entregada'; break;
                                            case 'calificada': echo 'Calificada'; break;
                                            case 'vencida': echo 'Vencida'; break;
                                            case 'cancelada': echo 'Cancelada'; break;
                                            default: echo 'Activa';
                                        }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="tarea-fecha">
                                <strong>Asignada por:</strong> <?php echo htmlspecialchars($tarea['asignador_nombre']); ?>
                                | <strong>Fecha límite:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_limite'])); ?>
                                <?php if (isset($tarea['calificacion'])): ?>
                                    | <strong>Calificación:</strong> <?php echo $tarea['calificacion']; ?>/100
                                <?php endif; ?>
                                
                                <?php if ($estado == 'vencida'): ?>
                                    | <strong style="color: #c62828;">⚠️ TAREA VENCIDA</strong>
                                <?php endif; ?>
                                
                                <?php if ($estado == 'cancelada'): ?>
                                    | <strong style="color: #757575;">❌ TAREA CANCELADA</strong>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($tarea['descripcion']): ?>
                                <div class="tarea-descripcion">
                                    <?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="acciones-tarea">
                                <?php if ($estado == 'pendiente'): ?>
                                    <?php if ($es_junta): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="marcar_asistencia" value="1">
                                            <input type="hidden" name="id_tarea_academia" value="<?php echo $tarea['id_tarea_academia']; ?>">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-user-check"></i> Marcar Asistencia
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button onclick="entregarTareaAcademia(<?php echo $tarea['id_tarea_academia']; ?>)" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Entregar Tarea
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($estado == 'vencida'): ?>
                                    <button class="btn btn-danger" disabled>
                                        <i class="fas fa-exclamation-triangle"></i> Tarea Vencida
                                    </button>
                                <?php elseif ($estado == 'cancelada'): ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-ban"></i> Tarea Cancelada
                                    </button>
                                <?php elseif ($estado == 'entregada'): ?>
                                    <span class="estado-tarea estado-entregada">
                                        <i class="fas fa-clock"></i> Esperando calificación
                                    </span>
                                <?php elseif ($estado == 'calificada'): ?>
                                    <span class="estado-tarea estado-calificada">
                                        <i class="fas fa-check-circle"></i> Calificada: <?php echo $tarea['calificacion']; ?>/100
                                    </span>
                                <?php endif; ?>
                                
                                <button onclick="verAcademia(<?php echo $tarea['id_academia']; ?>)" class="btn btn-secondary">
                                    <i class="fas fa-external-link-alt"></i> Ir a la Academia
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>NO HAY TAREAS DE ACADEMIA</h3>
                    <p>No tienes tareas asignadas en tus academias actualmente.</p>
                    <button class="btn-principal" onclick="mostrarSeccion('academias')" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Volver a Mis Academias
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<div id="modalUnirse" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 14px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); position: relative;">
        
        <form method="POST" action="">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; color: #1565c0; font-size: 1.5rem;">Unirse a Academia</h3>
                <button type="button" onclick="cerrarModalUnirse()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; padding: 5px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div style="margin-bottom: 25px;">
                <p>Ingresa el ID de la academia a la que deseas unirte. Te unirás directamente como miembro.</p>
                
                <?php if (!empty($mensaje_exito)): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #10b981;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($mensaje_error)): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensaje_error); ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #64748b;">ID de Academia:</label>
                    <input type="number" name="id_academia" style="width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; background: #f1f5f9;" placeholder="Ejemplo: 123" required min="1" value="<?php echo isset($_POST['id_academia']) ? htmlspecialchars($_POST['id_academia']) : ''; ?>">
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="cerrarModalUnirse()" style="padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; background: #64748b; color: white; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="unirse_academia" value="1" style="padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; background: #10b981; color: white; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-plus"></i> Unirse
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalUnirse() {
    const modal = document.getElementById('modalUnirse');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function cerrarModalUnirse() {
    const modal = document.getElementById('modalUnirse');
    if (modal) {
        modal.style.display = 'none';
    }
}

function verAcademia(idAcademia) {
    window.location.href = `detalle_academia.php?id=${idAcademia}`;
}

function gestionarAcademia(idAcademia) {
    window.location.href = `gestionar_academia.php?id=${idAcademia}`;
}

function mostrarSeccion(seccion) {
    document.querySelectorAll('.seccion').forEach(sec => {
        sec.classList.remove('activa');
    });
    
    const seccionActiva = document.getElementById(`seccion-${seccion}`);
    if (seccionActiva) {
        seccionActiva.classList.add('activa');
    }
    
    document.querySelectorAll('.btn-principal').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
}

function aplicarFiltros() {
    const filtroAcademia = document.getElementById('filtro-academia')?.value || '';
    const filtroEstado = document.getElementById('filtro-estado').value;
    const filtroFecha = document.getElementById('filtro-fecha').value;
    
    const tareas = document.querySelectorAll('.tarjeta-tarea');
    
    let tareasMostradas = 0;
    
    tareas.forEach(tarea => {
        let mostrar = true;
        
        if (filtroAcademia && tarea.dataset.academia !== filtroAcademia) {
            mostrar = false;
        }
        
        if (filtroEstado && tarea.dataset.estado !== filtroEstado) {
            mostrar = false;
        }
        
        if (filtroFecha === 'asc') {
            tarea.style.order = '0';
        } else {
            tarea.style.order = '1';
        }
        
        if (mostrar) {
            tarea.style.display = 'block';
            tareasMostradas++;
        } else {
            tarea.style.display = 'none';
        }
    });
}

function entregarTareaAcademia(tareaId) {
    window.location.href = `detalle_academia.php?entregar_tarea=${tareaId}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const btnUnirse = document.querySelector('.btn-unirse');
    if (btnUnirse) {
        btnUnirse.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            abrirModalUnirse();
            return false;
        };
        
        btnUnirse.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    }
    
    const modal = document.getElementById('modalUnirse');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalUnirse();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalUnirse();
        }
    });
    
    const tarjetasAcademia = document.querySelectorAll('.tarjeta-academia');
    tarjetasAcademia.forEach((card, index) => {
        card.style.cursor = 'pointer';
    });
    
    <?php if ($rol == 2 && count($tareas_academia) > 0): ?>
        setTimeout(() => {
            aplicarFiltros();
        }, 100);
    <?php endif; ?>
    
    <?php if (!empty($mensaje_exito) || !empty($mensaje_error)): ?>
        setTimeout(() => {
            abrirModalUnirse();
        }, 300);
    <?php endif; ?>
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Unirse';
                }, 5000);
            }
        });
    });
});
</script>

<style>
#modalUnirse {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0,0,0,0.5) !important;
    z-index: 9999 !important;
    justify-content: center !important;
    align-items: center !important;
}

#modalUnirse[style*="display: flex"] {
    display: flex !important;
}

.fa-spin {
    animation: fa-spin 1s linear infinite;
}

@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.tarjeta-academia {
    transition: all 0.3s ease !important;
}

.tarjeta-academia:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

button:disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
    transform: none !important;
}

@media (max-width: 768px) {
    #modalUnirse > div {
        width: 95% !important;
        margin: 20px !important;
        padding: 20px !important;
    }
}

@media (max-width: 480px) {
    #modalUnirse > div {
        width: 98% !important;
        margin: 10px !important;
        padding: 15px !important;
    }
}
</style>

<?php include "footer.php"; ?>