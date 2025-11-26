<?php
ob_start();
session_start();

if ($_SESSION['rol'] != '3') { 
    header("Location: index.php");
    exit;
}
include "conexion.php";
include "header.php";

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

// Variables para las secciones
$seccion = $_GET['seccion'] ?? 'profesores';
$id_profesor_seleccionado = $_GET['id_profesor'] ?? null;
$id_clase_seleccionada = $_GET['id_clase'] ?? null;

// Variables de búsqueda
$busqueda_alumno = $_GET['busqueda_alumno'] ?? '';
$busqueda_profesor = $_GET['busqueda_profesor'] ?? '';
$busqueda_prefecto = $_GET['busqueda_prefecto'] ?? ''; // Agregar esta línea
$busqueda_clase = $_GET['busqueda_clase'] ?? '';
$busqueda_materia = $_GET['busqueda_materia'] ?? '';
$filtro_maestro = $_GET['filtro_maestro'] ?? '';
$filtro_especialidad = $_GET['filtro_especialidad'] ?? '';
$filtro_semestre = $_GET['filtro_semestre'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Coordinador</title>
    <style>
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .btn-primary {
            background-color: #1565c0;
            border-color: #1565c0;
        }
        .form-check {
            margin-bottom: 8px;
        }
        .form-check-input:checked {
            background-color: #1565c0;
            border-color: #1565c0;
        }
        :root {
            --color-primario: #1565c0;
            --color-secundario: #1976d2;
            --color-fondo: #f4f6f8;
            --color-texto: #333;
            --color-blanco: #fff;
            --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
            --sombra-hover: 0 8px 18px rgba(0,0,0,0.15);
            --radio-borde: 14px;
        }
        /* MEJORAS RESPONSIVE PARA TABLAS */
        @media (max-width: 768px) {
            .tabla-alumnos {
                border: none;
                box-shadow: none;
            }
            
            .tabla-alumnos table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .tabla-alumnos th,
            .tabla-alumnos td {
                padding: 10px 8px;
                font-size: 0.85em;
            }
            
            /* Ocultar columnas menos importantes en móvil */
            .tabla-alumnos th:nth-child(4), /* Correo */
            .tabla-alumnos td:nth-child(4) {
                display: none;
            }
            
            /* Botones más compactos */
            .btn-group-vertical .btn {
                font-size: 0.75em;
                padding: 6px 10px;
                margin-bottom: 4px;
            }
            
            /* Foto más pequeña en móvil */
            .tabla-alumnos td img {
                width: 40px;
                height: 40px;
            }
        }

        /* Para pantallas muy pequeñas */
        @media (max-width: 576px) {
            .tabla-alumnos th:nth-child(3), /* Matrícula */
            .tabla-alumnos td:nth-child(3),
            .tabla-alumnos th:nth-child(6), /* Promedio */
            .tabla-alumnos td:nth-child(6) {
                display: none;
            }
            
            .buscador-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .buscador-input,
            .buscador-select {
                min-width: 100%;
                margin-bottom: 10px;
            }
        }
            /* Estilos para el portafolio */
.portafolio-profesor .grid-materias {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.portafolio-profesor .tarjeta-materia {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.portafolio-profesor .tarjeta-materia:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.15);
}

.grupo-tipo-documento {
    margin-bottom: 30px;
}
        .btn-primary {
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(21, 101, 192, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 101, 192, 0.4);
        }
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
        }
        .modal-header {
            border-radius: 15px 15px 0 0;
        }
        .form-control:focus {
            border-color: #1565c0;
            box-shadow: 0 0 0 0.2rem rgba(21, 101, 192, 0.25);
        }
        .form-check-input:checked {
            background-color: #1565c0;
            border-color: #1565c0;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        #contadorCaracteres {
            font-weight: bold;
        }
        body {
            background: var(--color-fondo);
            font-family: "Poppins", "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
        }
        /* BANNER */
        .banner-bienvenida {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: white;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .banner-texto h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 700;
        }
        .banner-texto p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        /* NAVEGACIÓN PRINCIPAL */
        .navegacion-principal {
            background: white;
            padding: 20px;
            margin: 20px auto;
            max-width: 1200px;
            border-radius: var(--radio-borde);
            box-shadow: var(--sombra-suave);
        }
        .botones-principales {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-principal {
            background: var(--color-primario);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-principal:hover, .btn-principal.active {
            background: var(--color-secundario);
            transform: translateY(-2px);
        }
        /* CONTENEDOR PRINCIPAL */
        .contenedor-principal {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        /* GRID DE PROFESORES */
        .grid-profesores {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tarjeta-profesor {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            box-shadow: var(--sombra-suave);
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
        }
        .tarjeta-profesor:hover {
            transform: translateY(-5px);
            box-shadow: var(--sombra-hover);
        }
        .foto-profesor {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 4px solid var(--color-primario);
        }
        .nombre-profesor {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--color-primario);
            margin-bottom: 5px;
        }
        .info-profesor {
            color: #666;
            font-size: 0.9em;
        }
        /* GRID DE CLASES */
        .grid-clases {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tarjeta-clase {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            box-shadow: var(--sombra-suave);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 5px solid var(--color-primario);
        }
        .tarjeta-clase:hover {
            transform: translateY(-3px);
            box-shadow: var(--sombra-hover);
        }
        .tarjeta-clase-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .tarjeta-clase-header h3 {
            margin: 0;
            color: var(--color-primario);
            flex: 1;
        }
        .creditos-clase {
            background: #e3f2fd;
            color: var(--color-primario);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: var(--color-primario);
        }
        /* TABLA DE ALUMNOS */
        .tabla-alumnos {
            background: white;
            border-radius: var(--radio-borde);
            overflow: hidden;
            box-shadow: var(--sombra-suave);
            margin-top: 20px;
        }
        .tabla-alumnos table {
            width: 100%;
            border-collapse: collapse;
        }
        .tabla-alumnos th {
            background: var(--color-primario);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .tabla-alumnos td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .tabla-alumnos tr:hover {
            background: #f9f9f9;
        }
        /* BOTONES DE ACCIÓN */
        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }
        .btn-primary {
            background: var(--color-primario);
            color: white;
        }
        .btn-primary:hover {
            background: var(--color-secundario);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
            border: none;
        }
        .btn-info:hover {
            background: #138496;
        }
        /* ESTADOS */
        .estado-activo {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .estado-inactivo {
            background: #ffebee;
            color: #c62828;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .grid-profesores, .grid-clases {
                grid-template-columns: 1fr;
            }
            .botones-principales {
                flex-direction: column;
            }
            .btn-principal {
                text-align: center;
            }
        }
        .ruta-navegacion {
            background: white;
            padding: 15px 20px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra-suave);
        }
        .ruta-navegacion a {
            color: var(--color-primario);
            text-decoration: none;
            font-weight: 600;
        }
        .ruta-navegacion a:hover {
            text-decoration: underline;
        }
        .separador {
            margin: 0 10px;
            color: #999;
        }
        /* BUSCADORES */
        .buscador-container {
            background: white;
            padding: 20px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra-suave);
        }
        .buscador-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .buscador-input {
            flex: 1;
            min-width: 200px;
        }
        .buscador-select {
            min-width: 200px;
        }
        /* CALIFICACIONES */
        .calificacion-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin: 1px;
        }
        .calificacion-alta { background: #e3f2fd; color: #1565c0; }
        .calificacion-media { background: #fff3e0; color: #f57c00; }
        .calificacion-baja { background: #ffebee; color: #c62828; }
        .promedio-global {
            font-weight: bold;
            font-size: 1.1em;
            color: var(--color-primario);
        }
        .materia-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .materia-item:last-child {
            border-bottom: none;
        }
        .clase-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .clase-item:last-child {
            border-bottom: none;
        }
        .unidad-calificacion {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 3px 0;
        }
        .unidad-label {
            font-weight: 500;
        }
        .modal-table {
            width: 100%;
            margin-top: 15px;
        }
        .modal-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .modal-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #dee2e6;
        }
        /* ESTILOS PARA MATERIAS */
        .grid-materias {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tarjeta-materia {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            box-shadow: var(--sombra-suave);
            transition: all 0.3s ease;
        }
        .tarjeta-materia:hover {
            transform: translateY(-3px);
            box-shadow: var(--sombra-hover);
        }
        .tarjeta-materia-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .tarjeta-materia-header h3 {
            margin: 0;
            flex: 1;
        }
        .creditos-materia {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .prerrequisito-info {
            background: #fff3e0;
            color: #e65100;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85em;
            border-left: 3px solid #ff9800;
        }
        .sin-prerrequisito {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85em;
            border-left: 3px solid #2196f3;
        }
        /* Nuevo estilo para indicar que es administrador */
        .badge-admin {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin-left: 10px;
        }
        /* Estilos para coordinadores */
        .grid-coordinadores {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tarjeta-coordinador {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            box-shadow: var(--sombra-suave);
            transition: all 0.3s ease;
            text-align: center;
            border-left: 5px solid #9c27b0;
        }
        .tarjeta-coordinador:hover {
            transform: translateY(-3px);
            box-shadow: var(--sombra-hover);
        }
        .carrera-coordinador {
            background: #f3e5f5;
            color: #7b1fa2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin-top: 10px;
        }
        /* ESTILOS PARA ESPECIALIDADES DE MATERIAS */
        .materia-general {
            border-left: 5px solid #4caf50;
        }
        .materia-general .tarjeta-materia-header h3 {
            color: #2e7d32;
        }
        .materia-especialidad-2 {
            border-left: 5px solid #2196f3;
        }
        .materia-especialidad-2 .tarjeta-materia-header h3 {
            color: #1565c0;
        }
        .materia-especialidad-3 {
            border-left: 5px solid #9c27b0;
        }
        .materia-especialidad-3 .tarjeta-materia-header h3 {
            color: #7b1fa2;
        }
        .materia-especialidad-4 {
            border-left: 5px solid #ff9800;
        }
        .materia-especialidad-4 .tarjeta-materia-header h3 {
            color: #ef6c00;
        }
        .materia-especialidad-5 {
            border-left: 5px solid #f44336;
        }
        .materia-especialidad-5 .tarjeta-materia-header h3 {
            color: #c62828;
        }
        .badge-especialidad {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-general {
            background: #e8f5e8;
            color: #2e7d32;
        }
        .badge-especialidad-2 {
            background: #e3f2fd;
            color: #1565c0;
        }
        .badge-especialidad-3 {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .badge-especialidad-4 {
            background: #fff3e0;
            color: #ef6c00;
        }
        .badge-especialidad-5 {
            background: #ffebee;
            color: #c62828;
        }
        .grupo-semestre {
            margin-bottom: 30px;
        }
        .titulo-semestre {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .leyenda-especialidades {
            background: white;
            padding: 15px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra-suave);
        }
        .leyenda-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            margin-bottom: 8px;
        }
        .color-muestra {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            margin-right: 8px;
        }
            /* ESTILOS PARA LA SECCIÓN AZUL NO DESPLEGABLE */
.quantumNavigator {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

.nebulaPanel {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    overflow: hidden;
    padding: 24px;
}

.galaxyGrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.cosmosLink {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 20px;
    border-radius: 10px;
    text-decoration: none;
    color: white;
    font-weight: 500;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-height: 60px;
    font-size: 16px;
}

.cosmosLink:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    border-color: rgba(255, 255, 255, 0.4);
}

.cosmosLink.active {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
}

.actionCluster {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.stellarAction {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
    color: white;
    border: none;
    padding: 16px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 60px;
    text-align: center;
    font-size: 16px;
}

.stellarAction:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,107,107,0.4);
    background: linear-gradient(135deg, #ff5252 0%, #e53935 100%);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .nebulaPanel {
        padding: 16px;
    }
    
    .galaxyGrid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .actionCluster {
        grid-template-columns: 1fr;
        padding-top: 16px;
        gap: 12px;
    }
    
    .cosmosLink, .stellarAction {
        padding: 16px 20px;
        min-height: 55px;
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .quantumNavigator {
        margin-bottom: 15px;
    }
    
    .nebulaPanel {
        padding: 12px;
        border-radius: 10px;
    }
    
    .cosmosLink, .stellarAction {
        padding: 14px 16px;
        min-height: 50px;
    }
}
            
    </style>
</head>
<body>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
    <div class="banner-texto">
        <br /><br /><br /><br />
        <h1>PANEL DEL <?php echo $es_admin ? 'ADMINISTRADOR' : 'COORDINADOR'; ?></h1>
<p>
    <?php if ($es_admin): ?>
        <span class="badge-admin">ADMINISTRADOR DEL SISTEMA</span>
    <?php else: ?>
        <?php echo htmlspecialchars($carrera_nombre ?? 'Sin carrera asignada'); ?>
    <?php endif; ?>
</p>
    </div>
</section>

<!-- NEBULA NAVIGATION SYSTEM -->
<div class="quantumNavigator">
    <div class="nebulaPanel">
        <div class="galaxyGrid">
            <a href="?seccion=profesores" class="cosmosLink <?php echo $seccion == 'profesores' ? 'active' : ''; ?>">
                Gestión de Profesores
            </a>
            <a href="?seccion=alumnos" class="cosmosLink <?php echo $seccion == 'alumnos' ? 'active' : ''; ?>">
                 Gestión de Alumnos
            </a>
                <a href="?seccion=prefectos" class="cosmosLink <?php echo $seccion == 'prefectos' ? 'active' : ''; ?>">
        Gestión de Prefectos
    </a>
            <a href="?seccion=clases" class="cosmosLink <?php echo $seccion == 'clases' ? 'active' : ''; ?>">
                 Gestión de Clases
            </a>
            <a href="?seccion=materias" class="cosmosLink <?php echo $seccion == 'materias' ? 'active' : ''; ?>">
                 Gestión de Materias
            </a>
            <a href="generar_boletas_grupo_excel.php" class="cosmosLink">
                Generar boletas
            </a>
            
            <?php if ($es_admin): ?>
            <a href="?seccion=coordinadores" class="cosmosLink <?php echo $seccion == 'coordinadores' ? 'active' : ''; ?>">
                Gestión de Coordinadores
            </a>
            <?php endif; ?>
        </div>
        
        <?php if ($es_admin): ?>
        <div class="actionCluster">
            <a href="acciones/avanzar_semestre.php" class="stellarAction" onclick="return confirm('¿Estás seguro de avanzar el semestre? Esta acción no se puede deshacer.')">
                Avanzar Semestre
            </a>
            <a href="gestionar_acciones.php" class="stellarAction">
                Gestionar Acciones
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- Contenedor centrado para el botón -->
<div class="d-flex justify-content-center my-4">
    <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalNotificacion">
        <i class="fas fa-bell me-2"></i> Enviar Notificación Masiva
    </button>
</div>

<div class="modal fade" id="modalNotificacion" tabindex="-1" aria-labelledby="modalNotificacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mx-auto">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNotificacionLabel">
                    <i class="fas fa-bell me-2"></i>Enviar Notificación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="enviar_notificaciones.php" method="POST">
                <div class="modal-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-bold">Título de la notificación *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100" placeholder="Ingresa el título de la notificación">
                    </div>
                    
                    <div class="mb-3">
                        <label for="mensaje" class="form-label fw-bold">Mensaje *</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required maxlength="500" placeholder="Escribe el mensaje de la notificación..."></textarea>
                        <div class="form-text">
                            <span id="contadorCaracteres">0</span>/500 caracteres
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Destinatarios *</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="destinatario" id="alumnos" value="alumnos" checked>
                                <label class="form-check-label fw-medium" for="alumnos">
                                    <i class="fas fa-user-graduate me-2"></i>Solo Alumnos
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="destinatario" id="profesores" value="profesores">
                                <label class="form-check-label fw-medium" for="profesores">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Solo Profesores
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="destinatario" id="ambos" value="ambos">
                                <label class="form-check-label fw-medium" for="ambos">
                                    <i class="fas fa-users me-2"></i>Ambos (Alumnos y Profesores)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Notificación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="contenedor-principal">
    
    <!-- RUTA DE NAVEGACIÓN -->
    <div class="ruta-navegacion">
        <a href="?seccion=profesores">Inicio</a>
        <?php if ($id_profesor_seleccionado): ?>
            <span class="separador">></span>
            <a href="?seccion=profesores">Profesores</a>
        <?php endif; ?>
        <?php if ($id_clase_seleccionada): ?>
            <span class="separador">></span>
            <a href="?seccion=clases">Clases</a>
        <?php endif; ?>
    </div>

    <?php
    // SECCIÓN DE PROFESORES
    if ($seccion == 'profesores') {
        // Obtener profesores - si es admin, ver todos; si no, solo de su carrera
        $where_profesor = $es_admin ? "1=1" : "u.id_carrera = $id_carrera_coordinador";
        if (!empty($busqueda_profesor)) {
            $busqueda_like = $conexion->real_escape_string($busqueda_profesor);
            $where_profesor .= " AND (u.nombre LIKE '%$busqueda_like%' OR u.apellidos LIKE '%$busqueda_like%' OR u.clave LIKE '%$busqueda_like%')";
        }
        
        $sql_profesores = "
            SELECT p.id_profesor, u.nombre, u.apellidos, u.correo, u.foto, p.sueldo, p.estado,
                   (SELECT COUNT(*) FROM clase c WHERE c.id_profesor = p.id_profesor) as total_clases,
                   (SELECT COUNT(*) FROM clase c WHERE c.id_profesor = p.id_profesor AND c.activo = 1) as clases_activas,
                   car.nombre as carrera_nombre
            FROM profesor p
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN carrera car ON u.id_carrera = car.id_carrera
            WHERE $where_profesor
            ORDER BY u.nombre, u.apellidos
        ";
        $profesores = $conexion->query($sql_profesores);
        ?>
        
        <div class="seccion-profesores">
                
            <?php if($es_admin) : ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo $es_admin ? 'Todos los Profesores del Sistema' : 'Profesores de ' . htmlspecialchars($carrera_nombre); ?></h2>
                <a href="acciones/crear_profesor.php" class="btn btn-primary">
                     Crear Nuevo Profesor
                </a>
            </div>
			 <?php endif; ?>
            <!-- BUSCADOR DE PROFESORES -->
            <div class="buscador-container">
                <form method="GET" class="buscador-form">
                    <input type="hidden" name="seccion" value="profesores">
                    <div class="buscador-input">
                        <label class="form-label">Buscar profesor:</label>
                        <input type="text" class="form-control" name="busqueda_profesor" value="<?php echo htmlspecialchars($busqueda_profesor); ?>" 
                               placeholder="Nombre, apellidos o clave...">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="?seccion=profesores" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <?php if ($profesores && $profesores->num_rows > 0): ?>
                <div class="grid-profesores">
                    <?php while($profesor = $profesores->fetch_assoc()): ?>
                        <div class="tarjeta-profesor">
                            <img src="img/usuarios/<?php echo $profesor['foto'] ?: 'default.jpg'; ?>" 
                                 class="foto-profesor"
                                 onerror="this.src='img/articulo/default.png'">
                            <div class="nombre-profesor">
                                <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?>
                            </div>
                            <div class="info-profesor">
                                <?php echo htmlspecialchars($profesor['correo']); ?>
                            </div>
                            <?php if ($es_admin): ?>
                            <div class="info-profesor">
                                Carrera: <?php echo htmlspecialchars($profesor['carrera_nombre'] ?? 'Sin carrera'); ?>
                            </div>
                            <?php endif; ?>
                            <div class="info-profesor">
                                Clases: <?php echo $profesor['clases_activas']; ?> activas de <?php echo $profesor['total_clases']; ?> totales
                            </div>
                            <div class="info-profesor">
                                Sueldo: $<?php echo number_format($profesor['sueldo'], 2); ?>
                            </div>
                            
                            <div class="<?php echo $profesor['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>" style="margin-top: 10px;">
                                <?php echo $profesor['estado'] == '1' ? 'Activo' : 'Inactivo'; ?>
                            </div>
                            <div class="acciones" style="margin-top: 15px;">
    <a href="acciones/editar_profesor.php?id_profesor=<?php echo $profesor['id_profesor']; ?>" class="btn btn-primary" style="font-size: 0.8em;">
        Editar
    </a>
    <button type="button" class="btn btn-info" style="font-size: 0.8em;" 
            onclick="cargarClasesProfesor(<?php echo $profesor['id_profesor']; ?>, '<?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?>')">
        Ver Clases
    </button>
    <!-- AGREGAR ESTE BOTÓN -->
    <button type="button" class="btn btn-warning" style="font-size: 0.8em;" 
            onclick="cargarPortafolioProfesor(<?php echo $profesor['id_profesor']; ?>, '<?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellidos']); ?>')">
        Ver Portafolio
    </button>
</div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay profesores que coincidan con la búsqueda</h3>
                    <p><?php echo empty($busqueda_profesor) ? 'Crea el primer profesor usando el botón superior' : 'Intenta con otros términos de búsqueda'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
        
         // SECCIÓN DE PREFECTOS
    elseif ($seccion == 'prefectos') {
        // Obtener prefectos - si es admin, ver todos; si no, solo de su carrera
        $where_prefecto = $es_admin ? "1=1" : "u.id_carrera = $id_carrera_coordinador";
        if (!empty($busqueda_prefecto)) {
            $busqueda_like = $conexion->real_escape_string($busqueda_prefecto);
            $where_prefecto .= " AND (u.nombre LIKE '%$busqueda_like%' OR u.apellidos LIKE '%$busqueda_like%' OR u.clave LIKE '%$busqueda_like%')";
        }
        
        $sql_prefectos = "
            SELECT pf.id_prefecto, u.nombre, u.apellidos, u.correo, u.foto, pf.sueldo, pf.estado,
                   car.nombre as carrera_nombre
            FROM prefecto pf
            INNER JOIN usuario u ON pf.id_usuario = u.id_usuario
            LEFT JOIN carrera car ON u.id_carrera = car.id_carrera
            WHERE $where_prefecto
            ORDER BY u.nombre, u.apellidos
        ";
        $prefectos = $conexion->query($sql_prefectos);
        ?>
        
        <div class="seccion-prefectos">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo $es_admin ? 'Todos los Prefectos del Sistema' : 'Prefectos de ' . htmlspecialchars($carrera_nombre); ?></h2>
                <a href="acciones/crear_prefecto.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Crear Nuevo Prefecto
                </a>
            </div>

            <!-- BUSCADOR DE PREFECTOS -->
            <div class="buscador-container">
                <form method="GET" class="buscador-form">
                    <input type="hidden" name="seccion" value="prefectos">
                    <div class="buscador-input">
                        <label class="form-label">Buscar prefecto:</label>
                        <input type="text" class="form-control" name="busqueda_prefecto" value="<?php echo htmlspecialchars($busqueda_prefecto ?? ''); ?>" 
                               placeholder="Nombre, apellidos o clave...">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="?seccion=prefectos" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <?php if ($prefectos && $prefectos->num_rows > 0): ?>
                <div class="grid-profesores">
                    <?php while($prefecto = $prefectos->fetch_assoc()): ?>
                        <div class="tarjeta-profesor">
                            <div class="prefecto-badge" style="margin-bottom: 15px; padding: 8px 12px; border-radius: 20px; background: linear-gradient(135deg, #ff9800, #f57c00); color: white; font-weight: 600; font-size: 0.9em;">
                                PREFECTO
                            </div>
                            <img src="img/usuarios/<?php echo $prefecto['foto'] ?: 'default.jpg'; ?>" 
                                 class="foto-profesor"
                                 onerror="this.src='img/articulo/default.png'">
                            <div class="nombre-profesor">
                                <?php echo htmlspecialchars($prefecto['nombre'] . ' ' . $prefecto['apellidos']); ?>
                            </div>
                            <div class="info-profesor">
                                <?php echo htmlspecialchars($prefecto['correo']); ?>
                            </div>
                            <?php if ($es_admin): ?>
                            <div class="info-profesor">
                                Carrera: <?php echo htmlspecialchars($prefecto['carrera_nombre'] ?? 'Sin carrera'); ?>
                            </div>
                            <?php endif; ?>
                            <div class="info-profesor">
                                Sueldo: $<?php echo number_format($prefecto['sueldo'], 2); ?>
                            </div>
                            
                            <div class="<?php echo $prefecto['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>" style="margin-top: 10px;">
                                <?php echo $prefecto['estado'] == '1' ? 'Activo' : 'Inactivo'; ?>
                            </div>
                            <div class="acciones" style="margin-top: 15px;">
                                <a href="acciones/editar_prefecto.php?id_prefecto=<?php echo $prefecto['id_prefecto']; ?>" class="btn btn-primary" style="font-size: 0.8em;">
                                    Editar
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay prefectos que coincidan con la búsqueda</h3>
                    <p><?php echo empty($busqueda_prefecto) ? 'Crea el primer prefecto usando el botón superior' : 'Intenta con otros términos de búsqueda'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // SECCIÓN DE ALUMNOS
    elseif ($seccion == 'alumnos') {
        // Obtener alumnos - si es admin, ver todos; si no, solo de su carrera
        $where_alumno = $es_admin ? "1=1" : "u.id_carrera = $id_carrera_coordinador";
        if (!empty($busqueda_alumno)) {
            $busqueda_like = $conexion->real_escape_string($busqueda_alumno);
            $where_alumno .= " AND (u.nombre LIKE '%$busqueda_like%' OR u.apellidos LIKE '%$busqueda_like%' OR u.clave LIKE '%$busqueda_like%')";
        }
        
        $sql_alumnos = "
            SELECT a.id_alumno, u.nombre, u.apellidos, u.correo, u.clave, u.foto, 
                   a.semestre, a.promedio, a.estado, a.especialidad,
                   car.nombre as carrera_nombre
            FROM alumno a
            INNER JOIN usuario u ON a.id_usuario = u.id_usuario
            LEFT JOIN carrera car ON u.id_carrera = car.id_carrera
            WHERE $where_alumno
            ORDER BY u.apellidos, u.nombre
        ";
        $alumnos = $conexion->query($sql_alumnos);
        ?>
        
        <div class="seccion-alumnos">
                <?php if($es_admin) : ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo $es_admin ? 'Todos los Alumnos del Sistema' : 'Alumnos de ' . htmlspecialchars($carrera_nombre); ?></h2>
                <a href="acciones/crear_alumno.php" class="btn btn-primary">
                     Crear Nuevo Alumno
                </a>
            </div>
            <?php endif; ?>
            <!-- BUSCADOR DE ALUMNOS -->
            <div class="buscador-container">
                <form method="GET" class="buscador-form">
                    <input type="hidden" name="seccion" value="alumnos">
                    <div class="buscador-input">
                        <label class="form-label">Buscar alumno:</label>
                        <input type="text" class="form-control" name="busqueda_alumno" value="<?php echo htmlspecialchars($busqueda_alumno); ?>" 
                               placeholder="Nombre, apellidos o matrícula...">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="?seccion=alumnos" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <?php if ($alumnos && $alumnos->num_rows > 0): ?>
                <div class="tabla-alumnos">
                    <table>
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Alumno</th>
                                <th>Matrícula</th>
                                <?php if ($es_admin): ?>
                                <th>Carrera</th>
                                <?php endif; ?>
                                <th>Correo</th>
                                <th>Semestre</th>
                                <th>Promedio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($alumno = $alumnos->fetch_assoc()): 
                                // Obtener promedio global (solo materias aprobadas)
                                $sql_promedio_global = "
                                    SELECT AVG(mc.cal_final) as promedio_global
                                    FROM materia_cursada mc
                                    WHERE mc.id_alumno = {$alumno['id_alumno']} AND mc.aprobado = 1
                                ";
                                $promedio_global_result = $conexion->query($sql_promedio_global);
                                $promedio_global = $promedio_global_result->fetch_assoc()['promedio_global'] ?? 0;
                            ?>
                                <tr>
                                    <td>
                                        <img src="img/usuarios/<?php echo $alumno['foto'] ?: 'default.jpg'; ?>" 
                                             style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;"
                                             onerror="this.src='img/articulo/default.png'">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></strong>
                                        <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                            Promedio global: <span class="promedio-global"><?php echo number_format($promedio_global, 2); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($alumno['clave']); ?></td>
                                    <?php if ($es_admin): ?>
                                    <td><?php echo htmlspecialchars($alumno['carrera_nombre'] ?? 'Sin carrera'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($alumno['correo']); ?></td>
                                    <td><?php echo $alumno['semestre']; ?></td>
                                    <td><?php echo number_format($alumno['promedio'], 2); ?></td>
                                    <td>
                                        <span class="<?php echo $alumno['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>">
                                            <?php echo $alumno['estado'] == '1' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="acciones/editar_alumno.php?id_alumno=<?php echo $alumno['id_alumno']; ?>" class="btn btn-primary" style="font-size: 0.8em;">
                                            Editar
                                        </a>
                                        <button type="button" class="btn btn-info" style="font-size: 0.8em;" 
                                                onclick="cargarCalificacionesAlumno(<?php echo $alumno['id_alumno']; ?>, '<?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellidos']); ?>')">
                                            Ver Calificaciones
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay alumnos que coincidan con la búsqueda</h3>
                    <p><?php echo empty($busqueda_alumno) ? 'Crea el primer alumno usando el botón superior' : 'Intenta con otros términos de búsqueda'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // SECCIÓN DE CLASES
    elseif ($seccion == 'clases') {
        // Obtener maestros para el filtro
        $where_maestro = $es_admin ? "1=1" : "u.id_carrera = $id_carrera_coordinador";
        $sql_maestros = "
            SELECT p.id_profesor, CONCAT(u.nombre, ' ', u.apellidos) as nombre_completo
            FROM profesor p
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE $where_maestro
            ORDER BY u.nombre, u.apellidos
        ";
        $maestros = $conexion->query($sql_maestros);
        
        // Obtener todas las clases - si es admin, ver todas; si no, solo de su carrera
        $where_clase = $es_admin ? "1=1" : "m.id_carrera = $id_carrera_coordinador";
        if (!empty($busqueda_clase)) {
            $busqueda_like = $conexion->real_escape_string($busqueda_clase);
            $where_clase .= " AND (m.nombre LIKE '%$busqueda_like%' OR s.nombre LIKE '%$busqueda_like%')";
        }
        if (!empty($filtro_maestro)) {
            $where_clase .= " AND c.id_profesor = " . intval($filtro_maestro);
        }
        
        $sql_clases = "
            SELECT c.id_clase, m.nombre as materia, m.creditos, c.grupo,
                   CONCAT(u.nombre, ' ', u.apellidos) as profesor_nombre,
                   s.nombre as salon, s.edificio, 
                   c.periodo, c.capacidad, c.activo,
                   (SELECT COUNT(*) FROM asignacion a WHERE a.id_clase = c.id_clase) as alumnos_inscritos,
                   car.nombre as carrera_nombre
            FROM clase c
            INNER JOIN materia m ON c.id_materia = m.id_materia
            INNER JOIN profesor p ON c.id_profesor = p.id_profesor
            INNER JOIN usuario u ON p.id_usuario = u.id_usuario
            INNER JOIN salon s ON c.id_salon = s.id_salon
            LEFT JOIN carrera car ON m.id_carrera = car.id_carrera
            WHERE $where_clase and c.activo=1
            ORDER BY c.activo DESC, m.nombre
        ";
        $clases = $conexion->query($sql_clases);
        ?>
        
        <div class="seccion-clases">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo $es_admin ? 'Todas las Clases del Sistema' : 'Clases de ' . htmlspecialchars($carrera_nombre); ?></h2>
                <a href="acciones/crear_clase.php" class="btn btn-primary">
                     Crear Nueva Clase
                </a>
            </div>

            <!-- BUSCADOR DE CLASES -->
            <div class="buscador-container">
                <form method="GET" class="buscador-form">
                    <input type="hidden" name="seccion" value="clases">
                    <div class="buscador-input">
                        <label class="form-label">Buscar clase:</label>
                        <input type="text" class="form-control" name="busqueda_clase" value="<?php echo htmlspecialchars($busqueda_clase); ?>" 
                               placeholder="Nombre de materia o salón...">
                    </div>
                    <div class="buscador-select">
                        <label class="form-label">Filtrar por maestro:</label>
                        <select class="form-select" name="filtro_maestro">
                            <option value="">Todos los maestros</option>
                            <?php while($maestro = $maestros->fetch_assoc()): ?>
                                <option value="<?php echo $maestro['id_profesor']; ?>" 
                                    <?php echo $filtro_maestro == $maestro['id_profesor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($maestro['nombre_completo']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="?seccion=clases" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <?php if ($clases && $clases->num_rows > 0): ?>
                <div class="grid-clases">
                    <?php while($clase = $clases->fetch_assoc()): ?>
                        <div class="tarjeta-clase">
                            <div class="tarjeta-clase-header">
                                <h3><?php echo htmlspecialchars($clase['materia']); ?></h3>
                                <span class="creditos-clase"><?php echo $clase['creditos']; ?> créditos</span>
                            </div>
                            
                            <?php if ($es_admin): ?>
                            <div class="info-item">
                                <span class="info-label">Carrera:</span>
                                <span class="info-value"><?php echo htmlspecialchars($clase['carrera_nombre']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <span class="info-label">Profesor:</span>
                                <span class="info-value"><?php echo htmlspecialchars($clase['profesor_nombre']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Salón:</span>
                                <span class="info-value"><?php echo $clase['salon']; ?> - <?php echo $clase['edificio']; ?></span>
                            </div>
                                                        <div class="info-item">
                                <span class="info-label">Grupo: </span>
                                <span class="info-value"><?php echo $clase['grupo']; ?></span>
                            </div>                            
                            <div class="info-item">
                                <span class="info-label">Alumnos:</span>
                                <span class="info-value"><?php echo $clase['alumnos_inscritos']; ?>/<?php echo $clase['capacidad']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Período:</span>
                                <span class="info-value"><?php echo htmlspecialchars($clase['periodo']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Estado:</span>
                                <span class="<?php echo $clase['activo'] ? 'estado-activo' : 'estado-inactivo'; ?>">
                                    <?php echo $clase['activo'] ? 'Activa' : 'Cerrada'; ?>
                                </span>
                            </div>

                            <div class="acciones" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; display: flex; justify-content: space-between;">
                                <div>
                                    <a href="acciones/editar_clase.php?id_clase=<?php echo $clase['id_clase']; ?>" 
                                       class="btn btn-primary" 
                                       style="font-size: 0.8em;">
                                        Editar
                                    </a>
                                    
                                    <a href="acciones/gestionar_alumnos_clase.php?id_clase=<?php echo $clase['id_clase']; ?>" 
                                       class="btn btn-success" 
                                       style="font-size: 0.8em;">
                                        Gestionar Alumnos
                                    </a>
                                </div>
                                
                                <a href="detalle_clase.php?id=<?php echo $clase['id_clase']; ?>" 
                                   class="btn btn-info" 
                                   style="font-size: 0.8em;">
                                    <i class="fas fa-eye me-1"></i> Ver Clase
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay clases que coincidan con la búsqueda</h3>
                    <p><?php echo (empty($busqueda_clase) && empty($filtro_maestro)) ? 'Crea la primera clase usando el botón superior' : 'Intenta con otros términos de búsqueda'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

   // SECCIÓN DE MATERIAS
elseif ($seccion == 'materias') {
    // Obtener especialidades para el filtro
    $where_especialidad = $es_admin ? "1=1" : "e.id_carrera = $id_carrera_coordinador OR e.id_especialidad = 1";
    $sql_especialidades = "
        SELECT e.id_especialidad, e.nombre, e.descripcion
        FROM especialidad e
        WHERE $where_especialidad AND e.activo = 1
        ORDER BY e.id_especialidad = 1 DESC, e.nombre
    ";
    $especialidades = $conexion->query($sql_especialidades);
    
    // Crear mapeo dinámico de colores para especialidades
    $colores_especialidades = [
        1 => ['color' => '#4caf50', 'nombre' => 'General'] // Color fijo para General
    ];
    
    // Paleta de colores extensa para especialidades
    $paleta_colores = [
        '#2196f3', '#9c27b0', '#ff9800', '#f44336', '#00bcd4',
        '#8bc34a', '#ff5722', '#795548', '#607d8b', '#e91e63',
        '#3f51b5', '#009688', '#ffc107', '#cddc39', '#9c27b0',
        '#673ab7', '#03a9f4', '#4caf50', '#ffeb3b', '#ff9800'
    ];
    
    // Asignar colores dinámicamente a las especialidades
    $especialidades_data = $especialidades->fetch_all(MYSQLI_ASSOC);
    $color_index = 0;
    
    foreach ($especialidades_data as $esp) {
        if ($esp['id_especialidad'] != 1) { // Saltar General que ya tiene color
            $colores_especialidades[$esp['id_especialidad']] = [
                'color' => $paleta_colores[$color_index % count($paleta_colores)],
                'nombre' => $esp['nombre']
            ];
            $color_index++;
        }
    }
    
    // Obtener materias - si es admin, ver todas; si no, solo de su carrera
    $where_materia = $es_admin ? "1=1" : "m.id_carrera = $id_carrera_coordinador";
    if (!empty($busqueda_materia)) {
        $busqueda_like = $conexion->real_escape_string($busqueda_materia);
        $where_materia .= " AND (m.nombre LIKE '%$busqueda_like%')";
    }
    if (!empty($filtro_especialidad)) {
        $where_materia .= " AND m.id_especialidad = " . intval($filtro_especialidad);
    }
    if (!empty($filtro_semestre)) {
        $where_materia .= " AND m.semestre_sugerido = " . intval($filtro_semestre);
    }
    
    $sql_materias = "
        SELECT m.id_materia, m.nombre, m.creditos, m.unidades, m.semestre_sugerido,
               m.id_prerrequisito, m.id_especialidad,
               prerreq.nombre as prerrequisito_nombre,
               e.nombre as especialidad_nombre,
               (SELECT COUNT(*) FROM clase c WHERE c.id_materia = m.id_materia) as total_clases,
               (SELECT COUNT(*) FROM clase c WHERE c.id_materia = m.id_materia AND c.activo = 1) as clases_activas,
               car.nombre as carrera_nombre
        FROM materia m
        LEFT JOIN materia prerreq ON m.id_prerrequisito = prerreq.id_materia
        LEFT JOIN especialidad e ON m.id_especialidad = e.id_especialidad
        LEFT JOIN carrera car ON m.id_carrera = car.id_carrera
        WHERE $where_materia
        ORDER BY m.semestre_sugerido, m.nombre
    ";
    $materias = $conexion->query($sql_materias);
    
    // Organizar materias por semestre
    $materias_por_semestre = [];
    while($materia = $materias->fetch_assoc()) {
        $semestre = $materia['semestre_sugerido'];
        if (!isset($materias_por_semestre[$semestre])) {
            $materias_por_semestre[$semestre] = [];
        }
        $materias_por_semestre[$semestre][] = $materia;
    }
    ?>
    
    <div class="seccion-materias">
            <?php if($es_admin) : ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><?php echo $es_admin ? 'Todas las Materias del Sistema' : 'Materias de ' . htmlspecialchars($carrera_nombre); ?></h2>
            <a href="acciones/crear_materia.php" class="btn btn-primary">
                 Crear Nueva Materia
            </a>
                <a href="acciones/crear_especialidad.php" class="btn btn-primary">
                 Crear Nueva Especialidad
            </a>
        </div>
                        <?php endif; ?>

        <!-- LEYENDA DE ESPECIALIDADES DINÁMICA -->
        <div class="leyenda-especialidades">
            <h6 style="margin-bottom: 15px; color: #555;">Color de Especialidades:</h6>
            <?php foreach ($colores_especialidades as $id_esp => $data): ?>
                <div class="leyenda-item">
                    <div class="color-muestra" style="background-color: <?php echo $data['color']; ?>;"></div>
                    <span><?php echo htmlspecialchars($data['nombre']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- BUSCADOR DE MATERIAS -->
        <div class="buscador-container">
            <form method="GET" class="buscador-form">
                <input type="hidden" name="seccion" value="materias">
                <div class="buscador-input">
                    <label class="form-label">Buscar materia:</label>
                    <input type="text" class="form-control" name="busqueda_materia" value="<?php echo htmlspecialchars($busqueda_materia); ?>" 
                           placeholder="Nombre de la materia...">
                </div>
                <div class="buscador-select">
                    <label class="form-label">Filtrar por especialidad:</label>
                    <select class="form-select" name="filtro_especialidad">
                        <option value="">Todas las especialidades</option>
                        <?php foreach ($colores_especialidades as $id_esp => $data): ?>
                            <option value="<?php echo $id_esp; ?>" 
                                <?php echo $filtro_especialidad == $id_esp ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($data['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="buscador-select">
                    <label class="form-label">Filtrar por semestre:</label>
                    <select class="form-select" name="filtro_semestre">
                        <option value="">Todos los semestres</option>
                        <?php for($i = 1; $i <= 9; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $filtro_semestre == $i ? 'selected' : ''; ?>>
                                Semestre <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="?seccion=materias" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <?php if (!empty($materias_por_semestre)): ?>
            <?php foreach($materias_por_semestre as $semestre => $materias_del_semestre): ?>
                <div class="grupo-semestre">
                    <div class="titulo-semestre">
                        Semestre <?php echo $semestre; ?>
                    </div>
                    <div class="grid-materias">
                        <?php foreach($materias_del_semestre as $materia): 
                            // Obtener color de la especialidad
                            $color_especialidad = $colores_especialidades[$materia['id_especialidad']]['color'] ?? '#4caf50';
                            $nombre_especialidad = $materia['especialidad_nombre'];
                        ?>
                            <div class="tarjeta-materia" style="border-left: 5px solid <?php echo $color_especialidad; ?>;">
                                <div class="tarjeta-materia-header">
                                    <h3 style="color: <?php echo $color_especialidad; ?>;">
                                        <?php echo htmlspecialchars($materia['nombre']); ?>
                                        <span class="badge-especialidad" style="background: <?php echo $color_especialidad; ?>20; color: <?php echo $color_especialidad; ?>; border: 1px solid <?php echo $color_especialidad; ?>30;">
                                            <?php echo htmlspecialchars($nombre_especialidad); ?>
                                        </span>
                                    </h3>
                                    <span class="creditos-materia"><?php echo $materia['creditos']; ?> créditos</span>
                                </div>
                                
                                <?php if ($es_admin): ?>
                                <div class="info-item">
                                    <span class="info-label">Carrera:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($materia['carrera_nombre']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-label">Unidades:</span>
                                    <span class="info-value"><?php echo $materia['unidades']; ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Semestre sugerido:</span>
                                    <span class="info-value"><?php echo $materia['semestre_sugerido']; ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Clases activas:</span>
                                    <span class="info-value"><?php echo $materia['clases_activas']; ?> de <?php echo $materia['total_clases']; ?></span>
                                </div>
                                
                                <?php if ($materia['id_prerrequisito']): ?>
                                    <div class="prerrequisito-info">
                                        <strong>Prerrequisito:</strong> <?php echo htmlspecialchars($materia['prerrequisito_nombre']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sin-prerrequisito">
                                        <strong>Sin prerrequisitos</strong>
                                    </div>
                                <?php endif; ?>

                                <div class="acciones" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                    <a href="acciones/editar_materia.php?id_materia=<?php echo $materia['id_materia']; ?>" 
                                       class="btn btn-primary" 
                                       style="font-size: 0.8em;">
                                        Editar
                                    </a>
                                    
                                    <button type="button" class="btn btn-info" style="font-size: 0.8em;" 
                                            onclick="cargarClasesMateria(<?php echo $materia['id_materia']; ?>, '<?php echo htmlspecialchars($materia['nombre']); ?>')">
                                        Ver Clases
                                    </button>
                                    
                                    <button type="button" class="btn btn-warning" style="font-size: 0.8em;" 
                                            onclick="cargarCadenaPrerrequisitos(<?php echo $materia['id_materia']; ?>, '<?php echo htmlspecialchars($materia['nombre']); ?>')">
                                        Ver Cadena
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>No hay materias que coincidan con la búsqueda</h3>
                <p><?php echo (empty($busqueda_materia) && empty($filtro_especialidad) && empty($filtro_semestre)) ? 'Crea la primera materia usando el botón superior' : 'Intenta con otros términos de búsqueda'; ?></p>
            </div>
        <?php endif; ?>
    </div>
        <?php
    }

    // SECCIÓN DE COORDINADORES (SOLO PARA ADMIN)
    elseif ($seccion == 'coordinadores' && $es_admin) {
        // Obtener todos los coordinadores
        $sql_coordinadores = "
            SELECT c.id_coordinador, u.nombre, u.apellidos, u.correo, u.foto, c.sueldo, c.estado,
                   car.nombre as carrera_nombre
            FROM coordinador c
            INNER JOIN usuario u ON c.id_usuario = u.id_usuario
            LEFT JOIN carrera car ON c.id_carrera = car.id_carrera
            ORDER BY u.nombre, u.apellidos
        ";
        $coordinadores = $conexion->query($sql_coordinadores);
        ?>
        
        <div class="seccion-coordinadores">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Gestión de Coordinadores</h2>
                <a href="acciones/crear_coordinador.php" class="btn btn-primary">
                     Crear Nuevo Coordinador
                </a>
            </div>

            <?php if ($coordinadores && $coordinadores->num_rows > 0): ?>
                <div class="grid-coordinadores">
                    <?php while($coordinador = $coordinadores->fetch_assoc()): ?>
                        <div class="tarjeta-coordinador">
                            <img src="img/usuarios/<?php echo $coordinador['foto'] ?: 'default.jpg'; ?>" 
                                 class="foto-profesor"
                                 onerror="this.src='img/articulo/default.png'">
                            <div class="nombre-profesor">
                                <?php echo htmlspecialchars($coordinador['nombre'] . ' ' . $coordinador['apellidos']); ?>
                            </div>
                            <div class="info-profesor">
                                <?php echo htmlspecialchars($coordinador['correo']); ?>
                            </div>
                            <div class="carrera-coordinador">
                                <?php echo htmlspecialchars($coordinador['carrera_nombre'] ?? 'ADMINISTRADOR'); ?>
                            </div>
                            <div class="info-profesor">
                                Sueldo: $<?php echo number_format($coordinador['sueldo'], 2); ?>
                            </div>
                            
                            <div class="<?php echo $coordinador['estado'] == '1' ? 'estado-activo' : 'estado-inactivo'; ?>" style="margin-top: 10px;">
                                <?php echo $coordinador['estado'] == '1' ? 'Activo' : 'Inactivo'; ?>
                            </div>
                            <div class="acciones" style="margin-top: 15px;">
                                <a href="acciones/editar_coordinador.php?id_coordinador=<?php echo $coordinador['id_coordinador']; ?>" class="btn btn-primary" style="font-size: 0.8em;">
                                    Editar
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay coordinadores en el sistema</h3>
                    <p>Crea el primer coordinador usando el botón superior</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
</div>
<!-- MODAL PARA AGREGAR DOCUMENTO AL PORTAFOLIO -->
<div class="modal fade" id="modalAgregarDocumento" tabindex="-1" aria-labelledby="modalAgregarDocumentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalAgregarDocumentoLabel">Agregar Documento al Portafolio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAgregarDocumento" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="id_profesor_documento" name="id_profesor">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_documento" class="form-label">Tipo de Documento *</label>
                                <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="certificado_universitario">Certificado Universitario</option>
                                    <option value="preparatoria">Preparatoria/Bachillerato</option>
                                    <option value="curso">Curso</option>
                                    <option value="diploma">Diploma</option>
                                    <option value="otro">Otro Documento</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de Emisión</label>
                                <input type="date" class="form-control" id="fecha_emision" name="fecha_emision">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre_documento" class="form-label">Nombre del Documento *</label>
                        <input type="text" class="form-control" id="nombre_documento" name="nombre_documento" required 
                               placeholder="Ej: Certificado de Maestría en Informática">
                    </div>
                    
                    <div class="mb-3">
                        <label for="institucion" class="form-label">Institución</label>
                        <input type="text" class="form-control" id="institucion" name="institucion" 
                               placeholder="Ej: Universidad Nacional Autónoma de México">
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                                  placeholder="Descripción adicional del documento..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="archivo_documento" class="form-label">Archivo del Documento *</label>
                        <input type="file" class="form-control" id="archivo_documento" name="archivo_documento" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">
                            Formatos permitidos: PDF, Word, JPG, PNG. Tamaño máximo: 10MB
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="volverAlPortafolio()">
                        <i class="fas fa-arrow-left me-1"></i> Volver al Portafolio
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Guardar Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- MODAL PARA CLASES DE PROFESOR -->
<div class="modal fade" id="modalClasesProfesor" tabindex="-1" aria-labelledby="modalClasesProfesorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalClasesProfesorLabel">Clases del Profesor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoClasesProfesor">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA CALIFICACIONES DE ALUMNO -->
<div class="modal fade" id="modalCalificacionesAlumno" tabindex="-1" aria-labelledby="modalCalificacionesAlumnoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalCalificacionesAlumnoLabel">Calificaciones del Alumno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoCalificacionesAlumno">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- MODAL PARA PORTAFOLIO DE PROFESOR -->
<div class="modal fade" id="modalPortafolioProfesor" tabindex="-1" aria-labelledby="modalPortafolioProfesorLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalPortafolioProfesorLabel">Portafolio del Profesor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoPortafolioProfesor">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- MODAL PARA CLASES DE MATERIA -->
<div class="modal fade" id="modalClasesMateria" tabindex="-1" aria-labelledby="modalClasesMateriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalClasesMateriaLabel">Clases de la Materia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoClasesMateria">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA CADENA DE PRERREQUISITOS -->
<div class="modal fade" id="modalCadenaPrerrequisitos" tabindex="-1" aria-labelledby="modalCadenaPrerrequisitosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCadenaPrerrequisitosLabel">Cadena de Prerrequisitos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoCadenaPrerrequisitos">
                <!-- El contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarEliminacion(mensaje) {
    return confirm(mensaje || '¿Estás seguro de que deseas eliminar este elemento?');
}
        // Función para abrir modal de agregar documento
// Función para abrir modal de agregar documento
function agregarDocumentoPortafolio(idProfesor) {
    // Cerrar primero el modal del portafolio
    var modalPortafolio = bootstrap.Modal.getInstance(document.getElementById('modalPortafolioProfesor'));
    if (modalPortafolio) {
        modalPortafolio.hide();
    }
    
    // Esperar a que se cierre el modal del portafolio
    setTimeout(function() {
        document.getElementById('id_profesor_documento').value = idProfesor;
        document.getElementById('formAgregarDocumento').reset();
        
        var modalAgregar = new bootstrap.Modal(document.getElementById('modalAgregarDocumento'));
        modalAgregar.show();
    }, 500);
}
// Función para eliminar documento
function eliminarDocumento(idPortafolio, nombreDocumento) {
    if (confirm('¿Estás seguro de que deseas eliminar el documento "' + nombreDocumento + '"? Esta acción no se puede deshacer.')) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'acciones/eliminar_documento_portafolio.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('Documento eliminado correctamente');
                    // Recargar el portafolio
                    var modalPortafolio = bootstrap.Modal.getInstance(document.getElementById('modalPortafolioProfesor'));
                    if (modalPortafolio) {
                        var idProfesor = document.querySelector('#contenidoPortafolioProfesor .alert-info h6').textContent.match(/Portafolio de (.+)/);
                        if (idProfesor) {
                            // Recargar contenido
                            cargarPortafolioProfesor(document.getElementById('id_profesor_documento').value, idProfesor[1]);
                        }
                    }
                } else {
                    alert('Error al eliminar el documento: ' + response.message);
                }
            }
        };
        xhr.send('id_portafolio=' + idPortafolio);
    }
}

// Manejar envío del formulario de documento
document.getElementById('formAgregarDocumento').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'acciones/guardar_documento_portafolio.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('Documento guardado correctamente');
                // Cerrar modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregarDocumento'));
                modal.hide();
                
                // Recargar el portafolio
                var idProfesor = formData.get('id_profesor');
                var nombreProfesor = document.getElementById('modalPortafolioProfesorLabel').textContent.replace('Portafolio de ', '');
                cargarPortafolioProfesor(idProfesor, nombreProfesor);
            } else {
                alert('Error al guardar el documento: ' + response.message);
            }
        }
    };
    xhr.send(formData);
});
   // Función para volver al modal del portafolio
function volverAlPortafolio() {
    // Cerrar modal de agregar
    var modalAgregar = bootstrap.Modal.getInstance(document.getElementById('modalAgregarDocumento'));
    if (modalAgregar) {
        modalAgregar.hide();
    }
    
    // Reabrir modal del portafolio después de un breve delay
    setTimeout(function() {
        var idProfesor = document.getElementById('id_profesor_documento').value;
        // Necesitamos recuperar el nombre del profesor de alguna manera
        // Puedes guardarlo en una variable global o en un campo hidden
        
        var modalPortafolio = new bootstrap.Modal(document.getElementById('modalPortafolioProfesor'));
        modalPortafolio.show();
        
        // Recargar el contenido del portafolio
        cargarPortafolioProfesor(idProfesor, 'Profesor'); // Puedes mejorar esto
    }, 500);
}
        // Función para cargar portafolio del profesor
function cargarPortafolioProfesor(idProfesor, nombreProfesor) {
    document.getElementById('contenidoPortafolioProfesor').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalPortafolioProfesorLabel').textContent = 'Portafolio de ' + nombreProfesor;
    
    var modal = new bootstrap.Modal(document.getElementById('modalPortafolioProfesor'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_portafolio_profesor.php?id_profesor=' + idProfesor, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoPortafolioProfesor').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoPortafolioProfesor').innerHTML = '<div class="alert alert-danger">Error al cargar el portafolio</div>';
        }
    };
    xhr.send();
}

// Función para cargar clases del profesor
function cargarClasesProfesor(idProfesor, nombreProfesor) {
    document.getElementById('contenidoClasesProfesor').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalClasesProfesorLabel').textContent = 'Clases de ' + nombreProfesor;
    
    var modal = new bootstrap.Modal(document.getElementById('modalClasesProfesor'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_clases_profesor.php?id_profesor=' + idProfesor, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoClasesProfesor').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoClasesProfesor').innerHTML = '<div class="alert alert-danger">Error al cargar las clases</div>';
        }
    };
    xhr.send();
}

// Función para cargar calificaciones del alumno
function cargarCalificacionesAlumno(idAlumno, nombreAlumno) {
    document.getElementById('contenidoCalificacionesAlumno').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalCalificacionesAlumnoLabel').textContent = 'Calificaciones de ' + nombreAlumno;
    
    var modal = new bootstrap.Modal(document.getElementById('modalCalificacionesAlumno'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_calificaciones_alumno.php?id_alumno=' + idAlumno, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoCalificacionesAlumno').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoCalificacionesAlumno').innerHTML = '<div class="alert alert-danger">Error al cargar las calificaciones</div>';
        }
    };
    xhr.send();
}

// Función para cargar clases de una materia
function cargarClasesMateria(idMateria, nombreMateria) {
    document.getElementById('contenidoClasesMateria').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalClasesMateriaLabel').textContent = 'Clases de ' + nombreMateria;
    
    var modal = new bootstrap.Modal(document.getElementById('modalClasesMateria'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_clases_materia.php?id_materia=' + idMateria, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoClasesMateria').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoClasesMateria').innerHTML = '<div class="alert alert-danger">Error al cargar las clases</div>';
        }
    };
    xhr.send();
}

// Función para cargar cadena de prerrequisitos
function cargarCadenaPrerrequisitos(idMateria, nombreMateria) {
    document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalCadenaPrerrequisitosLabel').textContent = 'Cadena de prerrequisitos: ' + nombreMateria;
    
    var modal = new bootstrap.Modal(document.getElementById('modalCadenaPrerrequisitos'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_cadena_prerrequisitos.php?id_materia=' + idMateria, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoCadenaPrerrequisitos').innerHTML = '<div class="alert alert-danger">Error al cargar la cadena de prerrequisitos</div>';
        }
    };
    xhr.send();
}

document.addEventListener('DOMContentLoaded', function() {
    const mensajeTextarea = document.getElementById('mensaje');
    const contadorCaracteres = document.getElementById('contadorCaracteres');
    
    // Contador de caracteres en tiempo real
    mensajeTextarea.addEventListener('input', function() {
        const caracteres = this.value.length;
        contadorCaracteres.textContent = caracteres;
        
        // Cambiar color si se acerca al límite
        if (caracteres > 450) {
            contadorCaracteres.classList.add('text-warning');
            contadorCaracteres.classList.remove('text-success');
        } else if (caracteres > 0) {
            contadorCaracteres.classList.add('text-success');
            contadorCaracteres.classList.remove('text-warning');
        } else {
            contadorCaracteres.classList.remove('text-warning', 'text-success');
        }
    });
    
    // Limpiar formulario cuando se cierra el modal
    const modal = document.getElementById('modalNotificacion');
    modal.addEventListener('hidden.bs.modal', function () {
        document.getElementById('titulo').value = '';
        document.getElementById('mensaje').value = '';
        contadorCaracteres.textContent = '0';
        contadorCaracteres.classList.remove('text-warning', 'text-success');
    });
});
 
</script>
<script>
// Función simple y directa para clic en tarjetas de clase
function hacerClickeablesClases() {
    // Esperar un momento para asegurar que el DOM esté listo
    setTimeout(function() {
        const tarjetas = document.querySelectorAll('.tarjeta-clase');
        
        tarjetas.forEach(tarjeta => {
            // Buscar el primer enlace que tenga id_clase
            const enlace = tarjeta.querySelector('a[href*="id_clase="]');
            if (enlace) {
                const href = enlace.getAttribute('href');
                const idClase = href.split('id_clase=')[1]?.split('&')[0];
                
                if (idClase) {
                    // Hacer la tarjeta clickeable
                    tarjeta.style.cursor = 'pointer';
                    tarjeta.title = 'Ver detalles de la clase';
                    
                    tarjeta.addEventListener('click', function(e) {
                        // Evitar redirección si se hace clic en un botón o enlace
                        if (!e.target.tagName === 'A' && !e.target.tagName === 'BUTTON' && 
                            !e.target.closest('a') && !e.target.closest('button')) {
                            window.location.href = `detalle_clase.php?id_clase=${idClase}`;
                        }
                    });
                    
                    // Efecto hover
                    tarjeta.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-3px)';
                        this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.12)';
                    });
                    
                    tarjeta.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = 'var(--sombra-suave)';
                    });
                }
            }
        });
    }, 100);
}

// Ejecutar cuando la página cargue
document.addEventListener('DOMContentLoaded', hacerClickeablesClases);

// Re-ejecutar después de 1 segundo por si el contenido se carga dinámicamente
setTimeout(hacerClickeablesClases, 1000);
</script>
        
</body>
</html>
<?php include 'footer.php' ?>