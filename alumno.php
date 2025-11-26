<?php
ob_start(); // INICIA BUFFER DE SALIDA
include "header.php";
include "conexion.php";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if ($_SESSION['rol'] != '1') {
    header("Location: index.php");
    exit;
}
$id_usuario = $_SESSION['id_usuario'];


// LISTA DE CHATS CON INFORMACIÓN DE MENSAJES
$queryChats = $conexion->prepare("
    SELECT c.id_chat,
           u.id_usuario,
           u.nombre,
           u.apellidos,
           (SELECT mensaje FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) AS ultimo_mensaje,
           (SELECT COUNT(*) FROM mensajes WHERE id_chat=c.id_chat AND id_usuario_envia!=? AND leido=0) AS sin_leer
    FROM chats c
    JOIN usuario u ON (u.id_usuario = IF(c.usuario1 = ?, c.usuario2, c.usuario1))
    WHERE c.usuario1=? OR c.usuario2=?
    ORDER BY (SELECT fecha_envio FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) DESC
");
$queryChats->bind_param("iiii",$id_usuario,$id_usuario,$id_usuario,$id_usuario);
$queryChats->execute();
$resultChats = $queryChats->get_result();



// Obtener info del alumno
$queryAlumnoInfo = $conexion->prepare("
    SELECT a.id_alumno, a.semestre, a.especialidad, u.clave, u.nombre, u.apellidos, c.nombre AS carrera
    FROM alumno a
    JOIN usuario u ON a.id_usuario = u.id_usuario
    JOIN carrera c ON u.id_carrera = c.id_carrera
    WHERE u.id_usuario = ?
");
$queryAlumnoInfo->bind_param("i", $id_usuario);
$queryAlumnoInfo->execute();
$resultAlumnoInfo = $queryAlumnoInfo->get_result();
$alumnoInfo = $resultAlumnoInfo->fetch_assoc();
$id_alumno = $alumnoInfo['id_alumno'];

// HORARIO - MODIFICADO PARA MULTIPLES HORAS POR DÍA
$queryHorario = $conexion->prepare("
    SELECT 
        m.nombre AS materia,
        s.nombre AS salon,
        s.edificio,
        h.dia,
        h.hora
    FROM asignacion a
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    JOIN salon s ON c.id_salon = s.id_salon
    JOIN horarios_clase h ON c.id_clase = h.id_clase
    WHERE a.id_alumno = ? AND c.activo = 1
    ORDER BY m.nombre, h.dia, h.hora
");

$queryHorario->bind_param("i", $id_alumno);
$queryHorario->execute();
$resultHorario = $queryHorario->get_result();

$horario = [];
while ($row = $resultHorario->fetch_assoc()) {
    $materia = $row['materia'];
    $dia = $row['dia'];
    $celda = $row['salon'] . " Ed." . $row['edificio'] . " " . $row['hora'] . ":00";
    
    // Si ya existe la materia y el día, agregamos la nueva hora
    if (isset($horario[$materia][$dia])) {
        // Si ya es un array, agregamos la nueva hora
        if (is_array($horario[$materia][$dia])) {
            $horario[$materia][$dia][] = $celda;
        } else {
            // Convertimos a array y agregamos ambas horas
            $horario[$materia][$dia] = [$horario[$materia][$dia], $celda];
        }
    } else {
        $horario[$materia][$dia] = $celda;
    }
}

// CALIFICACIONES - MODIFICADO PARA PROMEDIO SOLO CON CALIFICACIONES EXISTENTES
$queryMaterias = $conexion->prepare("
    SELECT DISTINCT m.id_materia, m.nombre, m.unidades
    FROM asignacion a
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    WHERE a.id_alumno = ? AND c.activo = 1
");

$queryMaterias->bind_param("i", $id_alumno);
$queryMaterias->execute();
$resultMaterias = $queryMaterias->get_result();

$calificaciones = [];
while ($row = $resultMaterias->fetch_assoc()) {
    $materia = $row['nombre'];
    $unidadesMateria = $row['unidades'];
    for ($i = 1; $i <= $unidadesMateria; $i++) {
        $calificaciones[$materia][$i] = null; // Usamos null para indicar que no hay calificación
    }
    $calificaciones[$materia]['final'] = 0;
}

$queryCalificaciones = $conexion->prepare("
    SELECT 
        m.nombre AS materia,
        ccl.unidad,
        ccl.calificacion
    FROM calificacion_clase ccl
    JOIN asignacion a ON ccl.id_asignacion = a.id_asignacion
    JOIN clase c ON a.id_clase = c.id_clase
    JOIN materia m ON c.id_materia = m.id_materia
    WHERE a.id_alumno = ? AND c.activo = 1
");

$queryCalificaciones->bind_param("i", $id_alumno);
$queryCalificaciones->execute();
$resultCalificaciones = $queryCalificaciones->get_result();

// Llenar calificaciones
while ($row = $resultCalificaciones->fetch_assoc()) {
    $materia = $row['materia'];
    $unidad = $row['unidad'];
    $calificacion = $row['calificacion'];
    $calificaciones[$materia][$unidad] = $calificacion;
}

// Calcular final - SOLO CON CALIFICACIONES EXISTENTES
foreach ($calificaciones as $materia => &$unidades) {
    $suma = 0;
    $contador = 0;
    foreach ($unidades as $key => $valor) {
        if ($key !== 'final' && $valor !== null) {
            $suma += $valor;
            $contador++;
        }
    }
    $unidades['final'] = $contador > 0 ? round($suma / $contador, 2) : 0;
}
unset($unidades);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Alumno</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ======== VARIABLES Y RESET ======== */
    :root {
        --color-primario: #1565c0;
        --color-secundario: #1976d2;
        --color-exito: #28a745;
        --color-error: #dc3545;
        --color-advertencia: #ffc107;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --sombra: 0 2px 6px rgba(0,0,0,0.1);
        --sombra-hover: 0 4px 12px rgba(0,0,0,0.15);
        --border-radius: 12px;
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background: var(--color-fondo);
        color: var(--color-texto);
        line-height: 1.6;
    }

    /* ======== CONTENEDOR PRINCIPAL ======== */
    .content { 
        padding: 20px; 
        max-width: 1400px;
        margin: 0 auto;
    }

    /* ======== BANNER DE BIENVENIDA ======== */
    .banner-bienvenida {
        background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
        color: white;
        padding: 90px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
        margin-bottom: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra-hover);
    }

    .banner-bienvenida::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: radial-gradient(circle at center, rgba(255,255,255,0.1), transparent 70%);
        animation: moverLuz 8s linear infinite;
    }

    @keyframes moverLuz {
        0%, 100% { transform: translateX(-100%); }
        50% { transform: translateX(100%); }
    }

    .banner-texto {
        position: relative;
        z-index: 2;
    }

    .banner-bienvenida h1 {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 15px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .banner-bienvenida p {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto;
    }

    /* ======== BOTONES DE DESCARGA ======== */
    .botones-descarga {
        display: flex;
        gap: 15px;
        margin-bottom: 30fpx;
        flex-wrap: wrap;
    }

    .btn-descarga {
        background: var(--color-primario);
        color: white;
        padding: 15px 25px;
        border-radius: var(--border-radius);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: var(--transition);
        font-weight: 600;
        box-shadow: var(--sombra);
        flex: 1;
        min-width: 200px;
        justify-content: center;
        text-align: center;
    }

    .btn-descarga:hover {
        background: var(--color-secundario);
        transform: translateY(-2px);
        box-shadow: var(--sombra-hover);
    }

    /* ======== SECCIONES ======== */
    .seccion {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .seccion-header {
        background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
        color: white;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .seccion-header h3 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 600;
    }

    .seccion-header i {
        font-size: 1.2rem;
    }

    .seccion-body {
        padding: 0;
    }

    /* ======== TABLAS RESPONSIVAS ======== */
    .table-container {
        overflow-x: auto;
        position: relative;
    }

    .tabla {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }

    .tabla thead {
        background: #f8f9fa;
    }

    .tabla th {
        padding: 15px 12px;
        text-align: center;
        font-weight: 600;
        color: var(--color-texto);
        border-bottom: 2px solid #dee2e6;
        font-size: 0.9rem;
        background: #e9ecef;
    }

    .tabla td {
        padding: 12px 10px;
        text-align: center;
        border-bottom: 1px solid #eee;
        font-size: 0.9rem;
    }

    .tabla tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Colores para calificaciones */
    .calificacion-roja {
        background-color: #ff6b6b;
        color: white;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    .calificacion-amarilla {
        background-color: #fff176;
        color: #333;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    .calificacion-verde {
        background-color: #64b5f6;
        color: white;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    /* Estilos para múltiples horarios */
    .horario-multiple {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .horario-item {
        font-size: 0.85rem;
        padding: 3px 6px;
        background: #f8f9fa;
        border-radius: 4px;
        border-left: 3px solid var(--color-primario);
    }

    /* ======== TARJETA DE INFORMACIÓN ======== */
    .tarjeta-info {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra);
        padding: 25px;
        margin-bottom: 30px;
        border-left: 4px solid var(--color-primario);
    }

    .tarjeta-info h3 {
        color: var(--color-primario);
        margin-bottom: 20px;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .info-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
    }

    .info-value {
        font-size: 1rem;
        color: var(--color-texto);
        font-weight: 500;
    }

    /* ======== RESPONSIVE DESIGN ======== */
    @media (max-width: 1200px) {
        .content {
            padding: 20px;
        }
    }

    @media (max-width: 768px) {
        .content {
            padding: 60px;
        }

        .banner-bienvenida {
            padding: 70px 40px;
            margin-bottom: 25px;
        }

        .banner-bienvenida h1 {
            font-size: 1.8rem;
        }

        .banner-bienvenida p {
            font-size: 1rem;
        }

        .botones-descarga {
            flex-direction: column;
        }

        .btn-descarga {
            min-width: auto;
            justify-content: center;
        }

        .seccion-header {
            padding: 120px;
        }

        .seccion-header h3 {
            font-size: 1.2rem;
        }

        .tarjeta-info {
            padding: 20px;
        }

        .info-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .tabla th,
        .tabla td {
            padding: 10px 8px;
            font-size: 0.85rem;
        }

        .horario-item {
            font-size: 0.8rem;
            padding: 2px 4px;
        }
    }

    @media (max-width: 480px) {
        .content {
            padding: 10px;
        }

        .banner-bienvenida {
            padding: 25px 10px;
            margin-bottom: 20px;
        }

        .banner-bienvenida h1 {
            font-size: 1.5rem;
        }

        .banner-bienvenida p {
            font-size: 0.9rem;
        }

        .btn-descarga {
            padding: 12px 20px;
            font-size: 0.9rem;
        }

        .seccion-header {
            padding: 12px 15px;
        }

        .seccion-header h3 {
            font-size: 1.1rem;
        }

        .tarjeta-info {
            padding: 15px;
        }

        .info-value {
            font-size: 0.9rem;
        }

        .tabla {
            min-width: 500px;
        }

        .tabla th,
        .tabla td {
            padding: 8px 6px;
            font-size: 0.8rem;
        }
    }

    /* Scroll personalizado para tablas */
    .table-container::-webkit-scrollbar {
        height: 8px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Indicador de scroll para móviles */
    .table-container {
        position: relative;
    }

    .table-container::after {
        content: "← Desliza →";
        position: absolute;
        right: 10px;
        top: 10px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
    }

    @media (max-width: 768px) {
        .table-container::after {
            opacity: 1;
        }
    }

    /* Estados de hover mejorados */
    @media (hover: hover) {
        .tabla tbody tr {
            transition: var(--transition);
        }
        
        .tabla tbody tr:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    }
    </style>
</head>
<body>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
    <div class="banner-texto">
        <h1>¡PANEL DEL ALUMNO!</h1>
        <p>AQUÍ PUEDES CONSULTAR TU HORARIO, CALIFICACIONES Y MENSAJES</p>
    </div>
</section>

<main class="content">

    <!-- TARJETA DE INFORMACIÓN PERSONAL -->
    <div class="tarjeta-info">
        <h3><i class="fas fa-user-graduate"></i> Información Personal</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nombre completo</span>
                <span class="info-value"><?php echo htmlspecialchars($alumnoInfo['nombre'] . ' ' . $alumnoInfo['apellidos']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Clave</span>
                <span class="info-value"><?php echo htmlspecialchars($alumnoInfo['clave']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Carrera</span>
                <span class="info-value"><?php echo htmlspecialchars($alumnoInfo['carrera']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Semestre</span>
                <span class="info-value"><?php echo htmlspecialchars($alumnoInfo['semestre']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Especialidad</span>
                <span class="info-value"><?php echo htmlspecialchars($alumnoInfo['especialidad']); ?></span>
            </div>
        </div>
    </div>

    <!-- BOTONES DE DESCARGA -->
    <div class="botones-descarga">
        <a href="descargar_horario.php" class="btn-descarga" target="_blank">
            <i class="fas fa-download"></i> Descargar Horario PDF
        </a>
        <a href="descargar_calificaciones.php" class="btn-descarga" target="_blank">
            <i class="fas fa-file-pdf"></i> Descargar Calificaciones PDF
        </a>
    </div>

    <!-- SECCIÓN HORARIO -->
    <div class="seccion">
        <div class="seccion-header">
            <i class="fas fa-calendar-alt"></i>
            <h3>Horario de Clases</h3>
        </div>
        <div class="seccion-body">
            <div class="table-container">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Lunes</th>
                            <th>Martes</th>
                            <th>Miércoles</th>
                            <th>Jueves</th>
                            <th>Viernes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horario as $materia => $dias): ?>
                        <tr>
                            <td style="text-align: left; font-weight: 600;"><?php echo htmlspecialchars($materia); ?></td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <td>
                                    <?php 
                                    if (isset($dias[$i])) {
                                        if (is_array($dias[$i])) {
                                            // Múltiples horarios para el mismo día
                                            echo '<div class="horario-multiple">';
                                            foreach ($dias[$i] as $horarioItem) {
                                                echo '<div class="horario-item">' . htmlspecialchars($horarioItem) . '</div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            // Un solo horario
                                            echo htmlspecialchars($dias[$i]);
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SECCIÓN CALIFICACIONES -->
    <div class="seccion">
        <div class="seccion-header">
            <i class="fas fa-chart-bar"></i>
            <h3>Calificaciones por Unidades</h3>
        </div>
        <div class="seccion-body">
            <div class="table-container">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <?php
                            $maxUnidadesGlobal = 0;
                            foreach ($calificaciones as $mat => $unis) {
                                $uniCount = count($unis) - 1;
                                if ($uniCount > $maxUnidadesGlobal) $maxUnidadesGlobal = $uniCount;
                            }
                            for ($i=1; $i<=$maxUnidadesGlobal; $i++): 
                            ?>
                                <th>U<?php echo $i; ?></th>
                            <?php endfor; ?>
                            <th>Calificación Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calificaciones as $materia => $unidades): ?>
                        <tr>
                            <td style="text-align: left; font-weight: 600;"><?php echo htmlspecialchars($materia); ?></td>
                            <?php for ($i=1; $i<=$maxUnidadesGlobal; $i++): ?>
                                <td>
                                    <?php 
                                    if (isset($unidades[$i]) && $unidades[$i] !== null) {
                                        echo $unidades[$i];
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <?php
                            $final = $unidades['final'];
                            $colorClase = $final < 70 ? 'calificacion-roja' : ($final < 80 ? 'calificacion-amarilla' : 'calificacion-verde');
                            ?>
                            <td class="<?php echo $colorClase; ?>"><?php echo $final; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<script>
// Mejorar experiencia en móviles
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll para tablas en móviles
    const tableContainers = document.querySelectorAll('.table-container');
    
    tableContainers.forEach(container => {
        let isDown = false;
        let startX;
        let scrollLeft;

        container.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
            container.style.cursor = 'grabbing';
        });

        container.addEventListener('mouseleave', () => {
            isDown = false;
            container.style.cursor = 'grab';
        });

        container.addEventListener('mouseup', () => {
            isDown = false;
            container.style.cursor = 'grab';
        });

        container.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 2;
            container.scrollLeft = scrollLeft - walk;
        });

        // Touch events para móviles
        container.addEventListener('touchstart', (e) => {
            isDown = true;
            startX = e.touches[0].pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        });

        container.addEventListener('touchend', () => {
            isDown = false;
        });

        container.addEventListener('touchmove', (e) => {
            if (!isDown) return;
            const x = e.touches[0].pageX - container.offsetLeft;
            const walk = (x - startX) * 2;
            container.scrollLeft = scrollLeft - walk;
        });
    });

    // Mostrar indicador de scroll solo en móviles
    function checkScreenSize() {
        const indicators = document.querySelectorAll('.table-container::after');
        if (window.innerWidth <= 768) {
            document.body.classList.add('mobile-view');
        } else {
            document.body.classList.remove('mobile-view');
        }
    }

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
});
</script>

</body>
</html>