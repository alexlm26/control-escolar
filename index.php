<?php
ob_start(); 
include "conexion.php";   

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$rol_nombre = 'Desconocido';
if(isset($_SESSION['rol'])){
    switch($_SESSION['rol']){
        case 1: $rol_nombre = 'Alumno'; break;
        case 2: $rol_nombre = 'Profesor'; break;
        case 3: $rol_nombre = 'Coordinador'; break;
    }
}
else{ 
    $rol_nombre = "ADMINISTRADOR";
}

$id_usuario = $_SESSION['id_usuario'];

$query = $conexion->query("
    SELECT n.*, 
           u.nombre AS nombre_usuario, 
           u.apellidos AS apellidos_usuario, 
           u.rol AS rol_usuario,
           IF(l.id_usuario IS NULL, 0, 1) AS dio_like
    FROM noticias n
    JOIN usuario u ON n.id_usuario = u.id_usuario
    LEFT JOIN likes_usuarios l 
           ON l.id_noticia = n.id_noticia AND l.id_usuario = $id_usuario
    ORDER BY n.publicacion DESC
    LIMIT 12
");

$noticias = $query->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sicenet - Noticias</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary-blue: #0ea5e9;
            --accent-blue: #06b6d4;
            --light-blue: #dbeafe;
            --text-dark: #1e293b;
            --text-medium: #475569;
            --text-light: #64748b;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-light: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s ease;
            --transition-slow: all 0.5s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ===== HEADER STYLES ===== */
        .news-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 30px 0;
            position: relative;
            overflow: hidden;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 50px;
            position: relative;
            z-index: 2;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-role {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .welcome-section {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 15px;
            animation: slideUp 0.8s ease-out;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 25px;
            animation: slideUp 0.8s ease-out 0.2s both;
        }

        .stats-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            min-width: 140px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            animation: slideUp 0.8s ease-out 0.4s both;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* ===== BOTÓN CREAR NOTICIA ===== */
        .boton-crear-noticia {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            padding: 0 20px;
            width: 100%;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1em;
            font-family: 'Inter', sans-serif;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #1e3a8a;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 8px;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-dark);
            position: relative;
            padding-bottom: 10px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-blue);
            border-radius: 2px;
        }

        .view-all {
            color: var(--primary-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            text-decoration: none;
        }

        .view-all:hover {
            color: var(--primary-dark);
            gap: 8px;
        }

        /* ===== FEATURED NEWS ===== */
        .featured-news {
            margin-bottom: 50px;
        }

        .featured-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .main-featured {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            cursor: pointer;
            animation: slideUp 0.8s ease-out;
        }

        .main-featured:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .featured-image {
            height: 280px;
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition-slow);
            display: block;
        }

        .main-featured:hover .featured-image img {
            transform: scale(1.05);
        }

        .featured-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary-blue);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }

        .featured-content {
            padding: 25px;
        }

        .featured-title {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .featured-excerpt {
            color: var(--text-medium);
            line-height: 1.6;
            margin-bottom: 18px;
            font-size: 0.95rem;
        }

        .featured-meta {
            display: flex;
            gap: 15px;
            color: var(--text-light);
            font-size: 0.85rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .side-featured {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .side-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            cursor: pointer;
            animation: slideUp 0.8s ease-out 0.2s both;
        }

        .side-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .side-card h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
            line-height: 1.4;
        }

        .side-card p {
            font-size: 0.85rem;
            color: var(--text-medium);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .side-meta {
            display: flex;
            justify-content: space-between;
            color: var(--text-light);
            font-size: 0.75rem;
        }

        /* ===== NEWS GRID ===== */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .news-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            cursor: pointer;
            animation: slideUp 0.8s ease-out;
            opacity: 0;
            transform: translateY(30px);
            animation-fill-mode: forwards;
        }

        .news-card:nth-child(1) { animation-delay: 0.1s; }
        .news-card:nth-child(2) { animation-delay: 0.2s; }
        .news-card:nth-child(3) { animation-delay: 0.3s; }
        .news-card:nth-child(4) { animation-delay: 0.4s; }
        .news-card:nth-child(5) { animation-delay: 0.5s; }
        .news-card:nth-child(6) { animation-delay: 0.6s; }

        .news-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .card-image {
            position: relative;
            height: 180px;
            overflow: hidden;
            width: 100%;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition-slow);
            display: block;
        }

        .news-card:hover .card-image img {
            transform: scale(1.1);
        }

        .card-category {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--primary-blue);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }

        .card-content {
            padding: 20px;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        .card-excerpt {
            color: var(--text-medium);
            font-size: 0.85rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-light);
            flex-wrap: wrap;
            gap: 10px;
        }

        .author {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .author-avatar {
            width: 28px;
            height: 28px;
            background: var(--light-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: var(--primary-blue);
        }

        .card-stats {
            display: flex;
            gap: 12px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            padding: 15px;
        }

        .modal.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            transform: scale(0.9) translateY(20px);
            opacity: 0;
            transition: var(--transition-slow);
            box-shadow: var(--shadow-xl);
        }

        .modal.active .modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary-blue);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            z-index: 10;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        .modal-close:hover {
            background: var(--primary-dark);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px 20px;
        }

        .modal-header {
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 15px;
        }

        .modal-title {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .modal-meta {
            display: flex;
            gap: 20px;
            color: var(--text-light);
            flex-wrap: wrap;
            font-size: 0.9rem;
        }

        .modal-image {
            width: 100%;
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            display: block;
        }

        .modal-text {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-medium);
            margin-bottom: 30px;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            margin: 25px 0;
        }

        .like-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        .like-btn:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .like-btn:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }

        .like-btn.liked {
            background: var(--accent-blue);
            animation: pulse 0.5s ease;
        }

        /* ===== NOTIFICATION ===== */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 18px;
            border-radius: var(--border-radius);
            color: white;
            font-weight: 600;
            z-index: 10001;
            box-shadow: var(--shadow-lg);
            transform: translateX(400px);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            max-width: 300px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: var(--accent-blue);
        }

        .notification.error {
            background: #ef4444;
        }

        .notification.info {
            background: var(--primary-blue);
        }

        /* ===== LOADER ===== */
        .loader {
            display: none;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 30px auto;
        }

        /* ===== ANIMACIONES ===== */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 1024px) {
            .featured-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .news-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
            
            .main-content {
                padding: 30px 20px;
            }
        }

        @media (max-width: 768px) {
            .news-header {
                padding: 50px 0;
                margin-top: 0;
            }
            
            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }
            
            .user-details {
                text-align: center;
            }
            
            .welcome-title {
                font-size: 1.6rem;
            }
            
            .welcome-subtitle {
                font-size: 1rem;
            }
            
            .stats-container {
                gap: 15px;
            }
            
            .stat-card {
                min-width: 120px;
                padding: 12px;
            }
            
            .stat-value {
                font-size: 1.3rem;
            }
            
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .side-featured {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 10px;
            }
            
            .side-card {
                min-width: 280px;
                flex-shrink: 0;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .featured-image {
                height: 220px;
            }
            
            .card-image {
                height: 160px;
            }
            
            .featured-content {
                padding: 20px;
            }
            
            .featured-title {
                font-size: 1.2rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .modal-body {
                padding: 20px 15px;
            }
            
            .modal-title {
                font-size: 1.3rem;
            }
            
            .modal-meta {
                gap: 15px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                padding: 0 15px;
            }
            
            .main-content {
                padding: 20px 15px;
            }
            
            .welcome-title {
                font-size: 1.4rem;
            }
            
            .stat-card {
                min-width: 100px;
                padding: 10px;
            }
            
            .stat-value {
                font-size: 1.1rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .featured-image {
                height: 200px;
            }
            
            .card-image {
                height: 150px;
            }
            
            .featured-content {
                padding: 15px;
            }
            
            .card-content {
                padding: 15px;
            }
            
            .featured-meta {
                gap: 10px;
                font-size: 0.8rem;
            }
            
            .modal {
                padding: 10px;
            }
            
            .modal-content {
                max-height: 95vh;
            }
            
            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        @media (max-width: 360px) {
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .featured-title {
                font-size: 1.1rem;
            }
            
            .card-title {
                font-size: 1rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* ===== MEJORAS DE ACCESIBILIDAD ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus styles para accesibilidad */
        button:focus,
        .btn:focus,
        .news-card:focus {
            outline: 2px solid var(--primary-blue);
            outline-offset: 2px;
        }

       
    </style>
</head>
              
<body>

    <div class="news-header">
              <?php include 'header.php';?>
        <div class="header-content">
            <div class="welcome-section">
                <h1 class="welcome-title">Bienvenido a tu control escolar</h1>
                <p class="welcome-subtitle">Portal de noticias del plantel</p>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-value">ITSUR</div>
                        <div class="stat-label">Instituto Tecnológico del Sur de Guanajuato</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <?php if($_SESSION['rol'] == 3) : ?>
    <div class="boton-crear-noticia">
        <a href="/acciones/crear_noticia.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Crear Nueva Noticia
        </a>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content">
            
        <!-- FEATURED NEWS -->
        <section class="featured-news">
            <div class="section-header">
                <h2 class="section-title">Noticia Destacada</h2>
                <a href="#" class="view-all">
                    Ver todas <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="featured-grid">
                <?php if (!empty($noticias)): ?>
                <div class="main-featured" data-id="<?php echo $noticias[0]['id_noticia']; ?>">
                    <div class="featured-image">
                        <img src="img/articulo/<?php echo $noticias[0]['imagen']; ?>" alt="<?php echo htmlspecialchars($noticias[0]['titulo']); ?>">
                        <div class="featured-badge">Destacado</div>
                    </div>
                    <div class="featured-content">
                        <h3 class="featured-title"><?php echo htmlspecialchars($noticias[0]['titulo']); ?></h3>
                        <p class="featured-excerpt">
                            <?php 
                                $texto = strip_tags($noticias[0]['info']);
                                echo mb_strimwidth($texto, 0, 150, "...");
                            ?>
                        </p>
                        <div class="featured-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($noticias[0]['nombre_usuario'] . ' ' . $noticias[0]['apellidos_usuario']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($noticias[0]['publicacion'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-eye"></i>
                                <span><?php echo $noticias[0]['visitas']; ?> visitas</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="side-featured">
                    <?php for ($i = 1; $i <= 2 && $i < count($noticias); $i++): ?>
                    <div class="side-card" data-id="<?php echo $noticias[$i]['id_noticia']; ?>">
                        <h4><?php echo htmlspecialchars($noticias[$i]['titulo']); ?></h4>
                        <p>
                            <?php 
                                $texto = strip_tags($noticias[$i]['info']);
                                echo mb_strimwidth($texto, 0, 100, "...");
                            ?>
                        </p>
                        <div class="side-meta">
                            <span><?php echo date('d/m/Y', strtotime($noticias[$i]['publicacion'])); ?></span>
                            <span><i class="fas fa-heart"></i> <?php echo $noticias[$i]['likes']; ?></span>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- NEWS GRID -->
        <section>
            <div class="section-header">
                <h2 class="section-title">Últimas Noticias</h2>
                <a href="#" class="view-all">
                    Ver más <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="news-grid">
                <?php foreach(array_slice($noticias, 3) as $noticia): ?>
                <div class="news-card" data-id="<?php echo $noticia['id_noticia']; ?>">
                    <div class="card-image">
                        <img src="img/articulo/<?php echo $noticia['imagen']; ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>">
                        <div class="card-category">EDUCACIÓN</div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($noticia['titulo']); ?></h3>
                        <p class="card-excerpt">
                            <?php 
                                $texto = strip_tags($noticia['info']);
                                echo mb_strimwidth($texto, 0, 120, "...");
                            ?>
                        </p>
                        <div class="card-meta">
                            <div class="author">
                                <div class="author-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span><?php echo htmlspecialchars($noticia['nombre_usuario']); ?></span>
                            </div>
                            <div class="card-stats">
                                <div class="stat">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo $noticia['visitas']; ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-heart"></i>
                                    <span><?php echo $noticia['likes']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- MODAL -->
    <div id="newsModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-body">
                <div class="loader" id="modalLoader"></div>
                <div class="modal-header">
                    <h2 id="modalTitle" class="modal-title"></h2>
                    <div class="modal-meta">
                        <span id="modalDate"><i class="fas fa-calendar"></i></span>
                        <span id="modalAuthor"><i class="fas fa-user"></i></span>
                        <span id="modalViews"><i class="fas fa-eye"></i></span>
                        <span id="modalLikes"><i class="fas fa-heart"></i></span>
                    </div>
                </div>
                <img id="modalImage" class="modal-image" src="" alt="">
                <div id="modalContent" class="modal-text"></div>
                <div class="modal-actions">
                    <button id="likeButton" class="like-btn">
                        <i class="fas fa-heart"></i>
                        <span id="likeText">Me gusta</span>
                        (<span id="likeCount">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION -->
    <div id="notification" class="notification">
        <i class="fas fa-bell"></i>
        <span id="notificationText"></span>
    </div>

    <script>
        // Variables globales
        let currentNewsId = null;
        const userId = <?php echo json_encode($id_usuario); ?>;
        const modal = document.getElementById('newsModal');
        const notification = document.getElementById('notification');

        // Mostrar notificación
        function showNotification(message, type = 'info') {
            const notificationText = document.getElementById('notificationText');
            notificationText.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 4000);
        }

        // Animación de entrada para elementos al hacer scroll
        function initScrollAnimations() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
        }

        // Cargar noticia completa
        async function loadFullNews(id) {
            try {
                document.getElementById('modalLoader').style.display = 'block';
                
                const response = await fetch(`get_noticia_completa.php?id=${id}`);
                const news = await response.json();
                
                if (news.error) {
                    throw new Error(news.error);
                }
                
                // Actualizar modal con datos
                document.getElementById('modalTitle').textContent = news.titulo;
                document.getElementById('modalImage').src = `img/articulo/${news.imagen}`;
                document.getElementById('modalContent').innerHTML = news.info;
                document.getElementById('modalDate').innerHTML = `<i class="fas fa-calendar"></i> ${news.fecha}`;
                document.getElementById('modalAuthor').innerHTML = `<i class="fas fa-user"></i> ${news.autor}`;
                document.getElementById('modalViews').innerHTML = `<i class="fas fa-eye"></i> ${news.visitas} visitas`;
                document.getElementById('modalLikes').innerHTML = `<i class="fas fa-heart"></i> ${news.likes} likes`;
                
                // Configurar botón de like
                const likeButton = document.getElementById('likeButton');
                const likeCount = document.getElementById('likeCount');
                const likeText = document.getElementById('likeText');
                
                likeCount.textContent = news.likes;
                
                if (news.dio_like) {
                    likeButton.disabled = true;
                    likeButton.classList.add('liked');
                    likeText.textContent = 'Te gusta';
                } else {
                    likeButton.disabled = false;
                    likeButton.classList.remove('liked');
                    likeText.textContent = 'Me gusta';
                }
                
                document.getElementById('modalLoader').style.display = 'none';
                
            } catch (error) {
                console.error('Error loading news:', error);
                showNotification('Error al cargar la noticia', 'error');
                document.getElementById('modalLoader').style.display = 'none';
            }
        }

        // Abrir noticia en modal
        async function openNews(id) {
            currentNewsId = id;
            
            // Actualizar contador de visitas
            try {
                await fetch('actualizar_visitas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
            } catch (error) {
                console.error('Error updating views:', error);
            }
            
            // Cargar y mostrar noticia
            await loadFullNews(id);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Cerrar modal
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Dar like a noticia
        async function likeNews() {
            if (!currentNewsId) return;
            
            try {
                const response = await fetch('actualizar_likes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${currentNewsId}&id_usuario=${userId}`
                });
                
                const result = await response.text();
                const likeButton = document.getElementById('likeButton');
                const likeCount = document.getElementById('likeCount');
                const likeText = document.getElementById('likeText');
                
                if (result.trim() === 'liked') {
                    const newCount = parseInt(likeCount.textContent) + 1;
                    likeCount.textContent = newCount;
                    likeButton.disabled = true;
                    likeButton.classList.add('liked');
                    likeText.textContent = 'Te gusta';
                    showNotification('¡Te gusta esta noticia!', 'success');
                    
                    // Actualizar tarjeta en la lista
                    const card = document.querySelector(`[data-id="${currentNewsId}"]`);
                    if (card) {
                        const likeElement = card.querySelector('.fa-heart').parentElement;
                        if (likeElement) {
                            likeElement.innerHTML = `<i class="fas fa-heart"></i> ${newCount}`;
                        }
                    }
                } else if (result.trim() === 'ya_liked') {
                    likeButton.disabled = true;
                    likeButton.classList.add('liked');
                    likeText.textContent = 'Te gusta';
                    showNotification('Ya te gusta esta noticia', 'info');
                } else if (result.trim() === 'sin_sesion') {
                    showNotification('Debes iniciar sesión para dar like', 'error');
                }
            } catch (error) {
                console.error('Error liking news:', error);
                showNotification('Error al dar like', 'error');
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar animaciones de scroll
            initScrollAnimations();
            
            // Abrir noticia al hacer clic
            document.querySelectorAll('.news-card, .main-featured, .side-card').forEach(card => {
                card.addEventListener('click', () => {
                    const id = card.dataset.id;
                    openNews(id);
                });
            });

            // Cerrar modal
            document.querySelector('.modal-close').addEventListener('click', closeModal);
            
            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Dar like
            document.getElementById('likeButton').addEventListener('click', likeNews);
            
            // Cerrar con tecla Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeModal();
                }
            });

            // Mejorar accesibilidad - navegación por teclado
            document.querySelectorAll('.news-card, .main-featured, .side-card').forEach(card => {
                card.setAttribute('tabindex', '0');
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const id = card.dataset.id;
                        openNews(id);
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php include 'footer.php' ?>