<?php
ob_start();
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}
if (basename($_SERVER['PHP_SELF']) == 'header.php') {
    header("Location: index.php");
    exit();
}

include "conexion.php";

$rol = isset($_SESSION["rol"]) ? $_SESSION["rol"] : null;
$id_usuario = $_SESSION['id_usuario'];

// CONSULTA DE NOTIFICACIONES
$sql = "SELECT id, mensaje, fecha 
        FROM notificaciones 
        WHERE id_usuario = ? AND leido = 0 
        ORDER BY fecha DESC LIMIT 10";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

$notificaciones = [];
while ($row = $result->fetch_assoc()) {
    $notificaciones[] = $row;
}
$totalNotif = count($notificaciones);

// Obtener nombre de la página actual para mostrar en móvil
$pagina_actual = basename($_SERVER['PHP_SELF']);
$nombres_paginas = [
    'index.php' => ['nombre' => 'Inicio', 'icono' => 'fas fa-home'],
    'clases.php' => ['nombre' => 'Clases', 'icono' => 'fas fa-book'],
    'alumno.php' => ['nombre' => 'Panel Alumno', 'icono' => 'fas fa-tachometer-alt'],
    'profesor.php' => ['nombre' => 'Panel Profesor', 'icono' => 'fas fa-tachometer-alt'],
    'coordinador.php' => ['nombre' => 'Panel Coordinador', 'icono' => 'fas fa-tachometer-alt'],
    'kardex.php' => ['nombre' => 'Kardex', 'icono' => 'fas fa-file-alt'],
    'perfil.php' => ['nombre' => 'Perfil', 'icono' => 'fas fa-user'],
    'estadisticas.php' => ['nombre' => 'Estadisticas', 'icono' => 'fas fa-chart-line fa-lg text-gray-300'],
    'chat.php' => ['nombre' => 'Mensajes', 'icono' => 'fas fa-comments'],
    'monitoreo_grupos.php' => ['nombre' => 'Monitoreo de Grupos', 'icono' => 'fa-solid fa-eye'],
    'academias.php' => ['nombre' => 'Academias', 'icono' => 'fa-solid fa-landmark'],
    'grupos.php' => ['nombre' => 'Grupos', 'icono' => 'fa-solid fa-people-roof'],
    'calendario.php' => ['nombre' => 'Calendario', 'icono' => 'fa-solid fa-calendar'],
        'tutorias.php' => ['nombre' => 'Tutorias', 'icono' => 'fa-solid fa-people-roof']
]; 

$pagina_info = $nombres_paginas[$pagina_actual] ?? ['nombre' => 'SICENET', 'icono' => 'fas fa-bars'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SICENET</title>
    <link rel="icon" type="image/png" href="logo.png">
    <!-- FONT AWESOME -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <!-- GOOGLE FONTS -->
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --zq9p-primary: #1976d2;
            --zq9p-primary-dark: #1565c0;
            --zq9p-primary-light: #42a5f5;
            --zq9p-surface: #ffffff;
            --zq9p-text-primary: #212121;
            --zq9p-text-secondary: #757575;
            --zq9p-divider: #e0e0e0;
            --zq9p-error: #d32f2f;
            --zq9p-success: #388e3c;
            --zq9p-shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            --zq9p-shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --zq9p-shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 5px 10px rgba(0,0,0,0.05);
            --zq9p-radius-sm: 6px;
            --zq9p-radius-md: 8px;
            --zq9p-radius-lg: 12px;
            --zq9p-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f8fafc;
            padding-top: 120px;
            color: var(--zq9p-text-primary);
        }

        /* HEADER PRINCIPAL */
        .zq9p_header {
            background: linear-gradient(135deg, var(--zq9p-primary-dark), var(--zq9p-primary));
            color: white;
            display: flex;
            flex-direction: column;
            padding: 0;
            box-shadow: var(--zq9p-shadow-lg);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        /* CORRECCIÓN PARA SAFE AREA EN MÓVIL */
        .zq9p_header {
            padding-top: env(safe-area-inset-top);
        }

        /* PRIMERA FILA: Logo + Nombre + Notificaciones */
        .zq9p_header_top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            width: 100%;
        }

        /* LOGO + NOMBRE */
        .zq9p_header_left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .zq9p_logo_link {
            display: flex;
            align-items: center;
            transition: var(--zq9p-transition);
            border-radius: var(--zq9p-radius-md);
            padding: 4px;
        }

        .zq9p_logo_link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .zq9p_header_left img {
            height: 42px;
            width: auto;
            border-radius: var(--zq9p-radius-sm);
            box-shadow: var(--zq9p-shadow-sm);
            transition: var(--zq9p-transition);
        }

        .zq9p_logo_link:hover img {
            transform: scale(1.05);
        }

        .zq9p_header_left h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: -0.25px;
        }

        /* CONTENEDOR DERECHO (NOTIFICACIONES) */
        .zq9p_header_right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* NOTIFICACIONES */
        .zq9p_notifications_container {
            position: relative;
            cursor: pointer;
        }

        .zq9p_bell_icon {
            font-size: 22px;
            color: white;
            padding: 10px;
            border-radius: 50%;
            transition: var(--zq9p-transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            position: relative;
        }

        .zq9p_bell_icon:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }

        .zq9p_notifications_container:not(.zq9p_dropdown_open) .zq9p_bell_icon {
            animation: zq9p_bell_animation 3s infinite;
        }

        @keyframes zq9p_bell_animation {
            0%, 90%, 100% { transform: rotate(0deg); }
            2%, 6% { transform: rotate(-8deg); }
            4%, 8% { transform: rotate(8deg); }
        }

        .zq9p_notification_counter {
            position: absolute;
            top: 6px;
            right: 6px;
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--zq9p-shadow-md);
            border: 2px solid var(--zq9p-primary-dark);
            animation: zq9p_pulse 2s infinite;
        }

        @keyframes zq9p_pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* DROPDOWN DE NOTIFICACIONES */
        .zq9p_notifications_list {
            display: none;
            position: absolute;
            right: 0;
            top: 56px;
            background: var(--zq9p-surface);
            color: var(--zq9p-text-primary);
            border-radius: var(--zq9p-radius-lg);
            width: 360px;
            max-height: 480px;
            overflow-y: auto;
            box-shadow: var(--zq9p-shadow-lg);
            border: 1px solid var(--zq9p-divider);
            z-index: 1001;
            transform-origin: top right;
            animation: zq9p_dropdown_appear 0.2s ease-out;
        }

        @keyframes zq9p_dropdown_appear {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .zq9p_notifications_list::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 16px;
            height: 16px;
            background: var(--zq9p-surface);
            transform: rotate(45deg);
            border-top: 1px solid var(--zq9p-divider);
            border-left: 1px solid var(--zq9p-divider);
        }

        .zq9p_notifications_header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--zq9p-divider);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .zq9p_notifications_header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--zq9p-text-primary);
        }

        .zq9p_mark_all_read {
            background: none;
            border: none;
            color: var(--zq9p-primary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--zq9p-transition);
            padding: 4px 8px;
            border-radius: var(--zq9p-radius-sm);
        }

        .zq9p_mark_all_read:hover {
            background: rgba(25, 118, 210, 0.08);
        }

        .zq9p_notification_item {
            padding: 14px 20px;
            border-bottom: 1px solid var(--zq9p-divider);
            transition: var(--zq9p-transition);
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .zq9p_notification_item:hover {
            background: #f5f9ff;
        }

        .zq9p_notification_item.zq9p_unread {
            background: #f0f7ff;
            border-left: 3px solid var(--zq9p-primary);
        }

        .zq9p_notification_icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(25, 118, 210, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--zq9p-primary);
            flex-shrink: 0;
        }

        .zq9p_notification_content {
            flex: 1;
        }

        .zq9p_notification_message {
            color: var(--zq9p-text-primary);
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .zq9p_notification_time {
            color: var(--zq9p-text-secondary);
            font-size: 12px;
        }

        .zq9p_empty_notifications {
            padding: 40px 20px;
            text-align: center;
            color: var(--zq9p-text-secondary);
        }

        .zq9p_empty_notifications i {
            font-size: 48px;
            color: #e0e0e0;
            margin-bottom: 16px;
        }

        .zq9p_empty_notifications p {
            margin: 0;
            font-size: 14px;
        }

        /* SEGUNDA FILA: PESTAÑAS DE NAVEGACIÓN + LOGOUT */
        .zq9p_nav_container {
            background: rgba(255,255,255,0.08);
            width: 100%;
            padding: 0 16px;
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        /* PESTAÑAS PARA ESCRITORIO */
        .zq9p_nav_tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: max-content;
            padding: 12px 0;
        }

        .zq9p_nav_tabs a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: var(--zq9p-radius-md);
            transition: var(--zq9p-transition);
            white-space: nowrap;
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .zq9p_nav_tabs a:hover {
            background: rgba(255,255,255,0.12);
            color: white;
        }

        .zq9p_nav_tabs a.zq9p_active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
        }

        .zq9p_nav_tabs a.zq9p_active::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 100%;
            height: 3px;
            background: white;
            border-radius: 2px 2px 0 0;
            box-shadow: 0 2px 8px rgba(255,255,255,0.3);
        }

        /* MENÚ MÓVIL */
        .zq9p_mobile_menu {
            display: none;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 12px 0;
        }

        .zq9p_current_page {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 16px;
            color: white;
        }

        .zq9p_current_page_icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .zq9p_current_page_text {
            font-weight: 600;
        }

        .zq9p_menu_toggle {
            background: rgba(255,255,255,0.12);
            border: none;
            border-radius: var(--zq9p-radius-md);
            color: white;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--zq9p-transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zq9p_menu_toggle:hover {
            background: rgba(255,255,255,0.2);
        }

        .zq9p_mobile_dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--zq9p-primary-dark);
            box-shadow: var(--zq9p-shadow-lg);
            z-index: 999;
            border-top: 1px solid rgba(255,255,255,0.1);
            max-height: 70vh;
            overflow-y: auto;
        }

        .zq9p_mobile_dropdown.zq9p_open {
            display: block;
            animation: zq9p_slide_down 0.3s ease-out;
        }

        @keyframes zq9p_slide_down {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .zq9p_mobile_tabs {
            display: flex;
            flex-direction: column;
            padding: 8px 0;
        }

        .zq9p_mobile_tabs a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 12px 20px;
            transition: var(--zq9p-transition);
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid transparent;
        }

        .zq9p_mobile_tabs a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .zq9p_mobile_tabs a.zq9p_active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border-left-color: white;
        }

        /* BOTÓN LOGOUT EN NAVEGACIÓN */
        .zq9p_nav_logout {
            display: flex;
            align-items: center;
            margin-left: auto;
            padding: 0 16px;
            flex-shrink: 0;
        }

        .zq9p_logout_btn {
            background: rgba(255,255,255,0.12);
            border: none;
            border-radius: var(--zq9p-radius-md);
            color: white;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--zq9p-transition);
            font-size: 14px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zq9p_logout_btn:hover {
            background: white;
            color: var(--zq9p-primary);
            box-shadow: var(--zq9p-shadow-sm);
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .zq9p_header_top {
                padding: 12px 20px;
            }
            
            .zq9p_notifications_list {
                width: 320px;
            }
        }

        /* MÓVIL */
        @media (max-width: 768px) {
            body {
                padding-top: 110px;
            }
            
            .zq9p_header_top {
                padding: 10px 16px;
            }
            
            .zq9p_header_left h4 {
                font-size: 16px;
            }
            
            .zq9p_header_left img {
                height: 38px;
            }
            
            .zq9p_notifications_list {
                width: 300px;
                right: 0;
                top: 52px;
            }
            
            .zq9p_nav_container {
                padding: 0 12px;
            }
            
            /* OCULTAR PESTAÑAS DE ESCRITORIO EN MÓVIL */
            .zq9p_nav_tabs {
                display: none;
            }
            
            /* MOSTRAR MENÚ MÓVIL */
            .zq9p_mobile_menu {
                display: flex;
            }
            
            .zq9p_logout_btn span {
                display: none;
            }
            
            .zq9p_logout_btn {
                padding: 8px;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                justify-content: center;
            }
        }

        /* MÓVIL PEQUEÑO */
        @media (max-width: 480px) {
            body {
                padding-top: 100px;
            }
            
            .zq9p_header_top {
                padding: 8px 12px;
            }
            
            .zq9p_header_left h4 {
                font-size: 14px;
            }
            
            .zq9p_header_left img {
                height: 34px;
            }
            
            .zq9p_notifications_list {
                width: 280px;
            }
            
            .zq9p_nav_container {
                padding: 0 8px;
            }
            
            .zq9p_current_page {
                font-size: 14px;
                gap: 8px;
            }
            
            .zq9p_current_page_text {
                font-size: 14px;
            }
            
            .zq9p_menu_toggle {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .zq9p_header_right {
                gap: 8px;
            }
            
            .zq9p_bell_icon {
                width: 42px;
                height: 42px;
                font-size: 20px;
            }
            
            .zq9p_logout_btn {
                width: 38px;
                height: 38px;
            }
            
            .zq9p_header_left {
                gap: 12px;
            }
        }

        /* MÓVIL MUY PEQUEÑO */
        @media (max-width: 360px) {
            .zq9p_header_left h4 {
                display: none;
            }
            
            .zq9p_notifications_list {
                width: 260px;
            }
            
            .zq9p_current_page_text {
                font-size: 13px;
            }
            
            .zq9p_menu_toggle span {
                display: none;
            }
            
            .zq9p_menu_toggle {
                padding: 8px;
                width: 40px;
                height: 40px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<header class="zq9p_header">
    <!-- Primera fila: Logo + Nombre + Notificaciones -->
    <div class="zq9p_header_top">
        <!-- Logo y nombre -->
        <div class="zq9p_header_left">
            <a href="https://surguanajuato.tecnm.mx/" target="_blank" class="zq9p_logo_link">
                <img src="logo.png" alt="Logo ITSUR">
            </a>
            <h4>SICENET</h4>
        </div>

        <!-- Contenedor derecho (notificaciones) -->
        <div class="zq9p_header_right">
            <!-- Contenedor de notificaciones -->
            <div class="zq9p_notifications_container">
                <i class="zq9p_bell_icon fas fa-bell"></i>
                <span class="zq9p_notification_counter"></span>
                <div class="zq9p_notifications_list">
                    <div class="zq9p_notifications_header">
                        <h5>Notificaciones</h5>
                        <button class="zq9p_mark_all_read">Marcar todas como leídas</button>
                    </div>
                    <div class="zq9p_notifications_content">
                        <!-- Las notificaciones se cargarán aquí dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Segunda fila: Pestañas de navegación + Logout -->
    <div class="zq9p_nav_container">
        <!-- Menú para escritorio -->
        <nav class="zq9p_nav_tabs">
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'zq9p_active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Inicio</span>
            </a>

            <?php if ($rol === "1"): ?>
                <a href="clases.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clases.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Clases</span>
                </a>
                <a href="alumno.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'alumno.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Panel</span>
                </a>
                <a href="kardex.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kardex.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Kardex</span>
                </a>
                <a href="materias_carrera.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materias_carrera.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reticula</span>
                </a>
                <a href="monitoreo_grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'monitoreo_grupos.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-eye"></i>
                    <span>Monitoreo de Grupos</span>
                </a>
                
            <?php endif; ?>

            <?php if ($rol === "2"): ?>
                <a href="clases.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clases.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Clases</span>
                </a>
                                     <a href="tutorias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tutorias.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Tutorias</span>
                </a>     
                <a href="academias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'academias.php' ? 'zq9p_active' : ''; ?>">
                   <i class="fa-solid fa-landmark"></i>
                    <span>Academias</span>
                </a>
                <a href="profesor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profesor.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Panel</span>
                </a>
                <a href="materias_carrera.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materias_carrera.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reticula</span>
                </a>  
            <?php endif; ?>

            <?php if ($rol === "3"): ?>
                                <a href="academias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'academias.php' ? 'zq9p_active' : ''; ?>">
                   <i class="fa-solid fa-landmark"></i>
                    <span>Academias</span> 
                </a>
                <a href="grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grupos.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Grupos</span>
                </a>
                <a href="coordinador.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'coordinador.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Panel</span>
                </a>
                <a href="estadisticas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'estadisticas.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-chart-line fa-lg text-gray-300"></i>    
                    <span>Estadisticas</span>
                </a>
            <?php endif; ?> 
                     <?php if ($rol === "5"): ?>
                                  
                     <a href="grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grupos.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Grupos</span>
                </a>     
                <?php endif; ?>
            <a href="calendario.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'zq9p_active' : ''; ?>">
                <i class="fa-solid fa-calendar"></i>
                <span>Calendario</span>
                        </a>
            <a href="perfil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'zq9p_active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Perfil</span>
            </a>
        </nav>

        <!-- Menú para móvil -->
        <div class="zq9p_mobile_menu">
            <div class="zq9p_current_page">
                <i class="zq9p_current_page_icon <?php echo $pagina_info['icono']; ?>"></i>
                <span class="zq9p_current_page_text"><?php echo $pagina_info['nombre']; ?></span>
            </div>
            
            <button class="zq9p_menu_toggle">
                <i class="fas fa-chevron-down"></i>
                <span>Menú</span>
            </button>
        </div>

        <!-- Dropdown para móvil -->
        <div class="zq9p_mobile_dropdown">
            <nav class="zq9p_mobile_tabs">
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Inicio</span>
                </a>

                <?php if ($rol === "1"): ?>
                    <a href="clases.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clases.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>Clases</span>
                    </a>
                    <a href="alumno.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'alumno.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Panel Alumno</span>
                    </a>
                    <a href="kardex.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kardex.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Kardex</span>
                    </a>
                    <a href="materias_carrera.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materias_carrera.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Reticula</span>
                    </a>
                    <a href="monitoreo_grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'monitoreo_grupos.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fa-solid fa-eye"></i>
                        <span>Monitoreo de Grupos</span>
                    </a>
                
                <?php endif; ?>

                <?php if ($rol === "2"): ?>
                    <a href="clases.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clases.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>Clases</span>
                    </a>
                                                        <a href="tutorias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tutorias.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Tutorias</span>
                </a> 
                                    <a href="academias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'academias.php' ? 'zq9p_active' : ''; ?>">
                   <i class="fa-solid fa-landmark"></i>
                    <span>Academias</span>
                </a>
                    <a href="profesor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profesor.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Panel Profesor</span>
                    </a>
                    <a href="materias_carrera.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materias_carrera.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Reticula</span>
                    </a>
                <?php endif; ?>

                <?php if ($rol === "3"): ?>
                                    <a href="academias.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'academias.php' ? 'zq9p_active' : ''; ?>">
                   <i class="fa-solid fa-landmark"></i>
                    <span>Academias</span>
                </a>
                     <a href="grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grupos.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Grupos</span>
                </a>
                    <a href="coordinador.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'coordinador.php' ? 'zq9p_active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Panel Coordinador</span>
                    </a>
                    
                    <a href="estadisticas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'estadisticas.php' ? 'zq9p_active' : ''; ?>">
                       <i class="fas fa-chart-line fa-lg text-gray-300"></i>
                        <span>Estadisticas</span>
                    </a>
                    
                <?php endif; ?>
                     <?php if ($rol === "5"): ?>
                                  
                     <a href="grupos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grupos.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fa-solid fa-people-roof"></i>
                    <span>Grupos</span>
                </a>     
                <?php endif; ?>
               <a href="chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'zq9p_active' : ''; ?>">
                     <i class="fas fa-comments"></i>
                    <span>Mensajes</span>
                </a>
                    <a href="calendario.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'zq9p_active' : ''; ?>">
                <i class="fa-solid fa-calendar"></i>
                <span>Calendario</span>
                        </a>
                <a href="perfil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'zq9p_active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Perfil</span>
                </a>
            </nav>
        </div>

        <!-- Botón cerrar sesión en la barra de navegación -->
        <?php if ($rol): ?>
            <div class="zq9p_nav_logout">
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit" class="zq9p_logout_btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>
<br>

<script>
// Notificaciones
function zq9p_loadNotifications() {
    fetch('get_notificaciones.php')
    .then(r => r.json())
    .then(data => {
        const container = document.querySelector('.zq9p_notifications_content');
        const counter = document.querySelector('.zq9p_notification_counter');
        const markAllReadBtn = document.querySelector('.zq9p_mark_all_read');
        
        container.innerHTML = '';

        if (data.length === 0) {
            container.innerHTML = `
                <div class="zq9p_empty_notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>No hay notificaciones nuevas</p>
                </div>
            `;
            counter.textContent = '';
            markAllReadBtn.style.display = 'none';
            return;
        }

        let unreadCount = 0;
        let notificationsHTML = '';

        data.forEach(n => {
            const isUnread = n.leido == 0;
            if (isUnread) unreadCount++;
            
            // Determinar icono según el tipo de notificación
            let icon = 'fas fa-info-circle';
            if (n.mensaje.toLowerCase().includes('calificacion') || n.mensaje.toLowerCase().includes('nota')) {
                icon = 'fas fa-chart-line';
            } else if (n.mensaje.toLowerCase().includes('clase') || n.mensaje.toLowerCase().includes('curso')) {
                icon = 'fas fa-book';
            } else if (n.mensaje.toLowerCase().includes('mensaje') || n.mensaje.toLowerCase().includes('comentario')) {
                icon = 'fas fa-comment';
            }
            
            notificationsHTML += `
                <div class="zq9p_notification_item ${isUnread ? 'zq9p_unread' : ''}" data-id="${n.id}">
                    <div class="zq9p_notification_icon">
                        <i class="${icon}"></i>
                    </div>
                    <div class="zq9p_notification_content">
                        <div class="zq9p_notification_message">${n.mensaje}</div>
                        <div class="zq9p_notification_time">${n.fecha}</div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = notificationsHTML;
        counter.textContent = unreadCount > 0 ? unreadCount : '';
        counter.style.display = unreadCount > 0 ? 'flex' : 'none';
        markAllReadBtn.style.display = unreadCount > 0 ? 'block' : 'none';
        
        // Agregar event listeners a las notificaciones individuales
        document.querySelectorAll('.zq9p_notification_item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                zq9p_markAsRead(notificationId);
                this.classList.remove('zq9p_unread');
                
                // Actualizar contador
                const currentCount = parseInt(counter.textContent) || 0;
                if (currentCount > 1) {
                    counter.textContent = currentCount - 1;
                } else {
                    counter.textContent = '';
                    counter.style.display = 'none';
                }
            });
        });
    });
}

function zq9p_markAsRead(notificationId) {
    fetch('marcar_leida.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${notificationId}`
    });
}

function zq9p_markAllAsRead() {
    fetch('marcar_todas_leidas.php')
    .then(() => {
        zq9p_loadNotifications();
    });
}

// Toggle dropdown de notificaciones
document.querySelector('.zq9p_bell_icon').addEventListener('click', (e) => {
    e.stopPropagation();
    const list = document.querySelector('.zq9p_notifications_list');
    const container = document.querySelector('.zq9p_notifications_container');
    
    if (list.style.display === 'none' || !list.style.display) {
        // Abrir dropdown
        list.style.display = 'block';
        container.classList.add('zq9p_dropdown_open');
    } else {
        // Cerrar dropdown
        list.style.display = 'none';
        container.classList.remove('zq9p_dropdown_open');
    }
});

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', (e) => {
    const notifContainer = document.querySelector('.zq9p_notifications_container');
    const list = document.querySelector('.zq9p_notifications_list');
    
    if (!notifContainer.contains(e.target)) {
        list.style.display = 'none';
        notifContainer.classList.remove('zq9p_dropdown_open');
    }
});

// Event listener para el botón "Marcar todas como leídas"
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('zq9p_mark_all_read')) {
        zq9p_markAllAsRead();
    }
});

// Menú móvil
const menuToggle = document.querySelector('.zq9p_menu_toggle');
const mobileDropdown = document.querySelector('.zq9p_mobile_dropdown');
const mobileIcon = menuToggle.querySelector('i');

menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    mobileDropdown.classList.toggle('zq9p_open');
    
    if (mobileDropdown.classList.contains('zq9p_open')) {
        mobileIcon.className = 'fas fa-chevron-up';
        menuToggle.querySelector('span').textContent = 'Cerrar';
    } else {
        mobileIcon.className = 'fas fa-chevron-down';
        menuToggle.querySelector('span').textContent = 'Menú';
    }
});

// Cerrar menú móvil al hacer clic fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.zq9p_mobile_menu') && !e.target.closest('.zq9p_mobile_dropdown')) {
        mobileDropdown.classList.remove('zq9p_open');
        mobileIcon.className = 'fas fa-chevron-down';
        menuToggle.querySelector('span').textContent = 'Menú';
    }
});

// Cerrar menú móvil al seleccionar una opción
document.querySelectorAll('.zq9p_mobile_tabs a').forEach(link => {
    link.addEventListener('click', () => {
        mobileDropdown.classList.remove('zq9p_open');
        mobileIcon.className = 'fas fa-chevron-down';
        menuToggle.querySelector('span').textContent = 'Menú';
        
        // Actualizar el nombre de la página actual en móvil
        const pageName = link.querySelector('span').textContent;
        const pageIcon = link.querySelector('i').className;
        document.querySelector('.zq9p_current_page_text').textContent = pageName;
        document.querySelector('.zq9p_current_page_icon').className = 'zq9p_current_page_icon ' + pageIcon;
    });
});

// Cargar notificaciones al iniciar y cada 15 segundos
document.addEventListener('DOMContentLoaded', function() {
    zq9p_loadNotifications();
    setInterval(zq9p_loadNotifications, 15000);
});
</script>

</body>
</html>