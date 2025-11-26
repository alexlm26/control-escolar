<?php
ob_start();
include "conexion.php";
include "header.php";
if($_SESSION['rol'] != '1'){
    header("Location: index.php");
    exit;
}

// Obtener id_alumno y id_carrera del usuario
$id_usuario = $_SESSION['id_usuario'];
$res_alumno = $conexion->query("
    SELECT a.id_alumno, u.id_carrera
    FROM alumno a
    INNER JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE a.id_usuario = $id_usuario
");
if($res_alumno->num_rows == 0){
    die("Alumno no encontrado");
}
$alumno = $res_alumno->fetch_assoc();
$id_alumno = $alumno['id_alumno'];
$id_carrera = $alumno['id_carrera'];

// Obtener materias cursadas - SOLO LA CALIFICACIÓN MÁS ALTA POR MATERIA
$sql = "
SELECT 
    m.nombre AS materia,
    m.creditos,
    CONCAT(u.nombre,' ',u.apellidos) AS maestro,
    mc.cal_final,
    mc.oportunidad,
    mc.periodo,
    mc.aprobado
FROM materia_cursada mc
INNER JOIN materia m ON mc.id_materia = m.id_materia
INNER JOIN clase c ON mc.id_clase = c.id_clase
INNER JOIN profesor p ON c.id_profesor = p.id_profesor
INNER JOIN usuario u ON p.id_usuario = u.id_usuario
WHERE mc.id_alumno = $id_alumno
AND mc.cal_final = (
    SELECT MAX(mc2.cal_final) 
    FROM materia_cursada mc2 
    WHERE mc2.id_alumno = mc.id_alumno 
    AND mc2.id_materia = mc.id_materia
)
ORDER BY m.nombre ASC, mc.periodo ASC
";
$result = $conexion->query($sql);

// Créditos totales de la carrera
$res_carrera = $conexion->query("SELECT creditos FROM carrera WHERE id_carrera = $id_carrera");
$row_carrera = $res_carrera->fetch_assoc();
$creditos_totales = $row_carrera['creditos'] ?? 0;

// Variables acumuladoras
$total_creditos = 0;
$cal_total = 0;
$materias_count = 0;

// Calcular promedios y porcentajes con los datos filtrados
if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if($row['aprobado']){
            $total_creditos += $row['creditos'];
        }
        $cal_total += $row['cal_final'];
        $materias_count++;
    }
    
    // Reset pointer para usar el resultado nuevamente en la tabla
    $result->data_seek(0);
}

$promedio = $materias_count > 0 ? round($cal_total / $materias_count, 2) : 0;
$porcentaje = $creditos_totales > 0 ? round(($total_creditos / $creditos_totales) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex del Alumno</title>
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
        padding: 40px 20px;
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
        margin-bottom: 20px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .banner-bienvenida p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 20px;
    }

    /* ======== BOTÓN DESCARGA PDF ======== */
    .btn-descarga {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 2px solid white;
        padding: 12px 25px;
        border-radius: var(--border-radius);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: var(--transition);
        font-weight: 600;
        backdrop-filter: blur(10px);
        cursor: pointer;
    }

    .btn-descarga:hover {
        background: white;
        color: var(--color-primario);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }

    /* ======== TABLA KARDEX ======== */
    .kardex-container {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .table-container {
        overflow-x: auto;
        position: relative;
    }

    .tabla-kardex {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .tabla-kardex thead {
        background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
    }

    .tabla-kardex th {
        padding: 15px 12px;
        text-align: center;
        font-weight: 600;
        color: white;
        border-bottom: 2px solid #dee2e6;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .tabla-kardex td {
        padding: 12px 10px;
        text-align: center;
        border-bottom: 1px solid #eee;
        font-size: 0.9rem;
    }

    .tabla-kardex tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Colores para calificaciones */
    .calificacion-baja {
        background-color: #ff6b6b;
        color: white;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    .calificacion-media {
        background-color: #fff176;
        color: #333;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    .calificacion-alta {
        background-color: #64b5f6;
        color: white;
        font-weight: bold;
        border-radius: 4px;
        padding: 4px 8px;
    }

    /* Estados de aprobación */
    .si {
        color: var(--color-exito);
        font-weight: bold;
    }

    .no {
        color: var(--color-error);
        font-weight: bold;
    }

    /* Filas de resumen */
    .fila-resumen {
        background: #e8f5e8 !important;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .fila-resumen td {
        border-bottom: 2px solid #c8e6c9;
    }

    /* ======== TARJETA RESUMEN ======== */
    .resumen-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .tarjeta-resumen {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra);
        padding: 25px;
        text-align: center;
        border-top: 4px solid var(--color-primario);
        transition: var(--transition);
    }

    .tarjeta-resumen:hover {
        transform: translateY(-5px);
        box-shadow: var(--sombra-hover);
    }

    .tarjeta-resumen h3 {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .tarjeta-resumen .valor {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-texto);
        margin-bottom: 5px;
    }

    .tarjeta-resumen .etiqueta {
        font-size: 0.9rem;
        color: #666;
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
            padding: 15px;
        }

        .banner-bienvenida {
            padding: 30px 15px;
            margin-bottom: 25px;
        }

        .banner-bienvenida h1 {
            font-size: 1.8rem;
        }

        .banner-bienvenida p {
            font-size: 1rem;
        }

        .btn-descarga {
            padding: 10px 20px;
            font-size: 0.9rem;
        }

        .resumen-container {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .tarjeta-resumen {
            padding: 20px;
        }

        .tarjeta-resumen .valor {
            font-size: 1.8rem;
        }

        .tabla-kardex th,
        .tabla-kardex td {
            padding: 10px 8px;
            font-size: 0.85rem;
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
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .tarjeta-resumen {
            padding: 15px;
        }

        .tarjeta-resumen .valor {
            font-size: 1.5rem;
        }

        .tabla-kardex {
            min-width: 700px;
        }

        .tabla-kardex th,
        .tabla-kardex td {
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
        .tabla-kardex tbody tr {
            transition: var(--transition);
        }
        
        .tabla-kardex tbody tr:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    }

    /* Mensaje cuando no hay datos */
    .mensaje-vacio {
        text-align: center;
        padding: 40px 20px;
        color: #666;
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--sombra);
    }

    .mensaje-vacio i {
        font-size: 3rem;
        color: #ddd;
        margin-bottom: 15px;
    }
    </style>
</head>
<body>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
    <div class="banner-texto">
        <h1>KARDEX DEL ALUMNO</h1>
        <p>Consulta tu historial académico completo</p>
        <form action="kardex_pdf.php" method="post" target="_blank">
            <button type="submit" class="btn-descarga">
                <i class="fas fa-file-pdf"></i> Descargar KARDEX en PDF
            </button>
        </form>
    </div>
</section>

<main class="content">

    <?php if($result->num_rows > 0): ?>
    
    <!-- TARJETAS DE RESUMEN -->
    <div class="resumen-container">
        <div class="tarjeta-resumen">
            <h3><i class="fas fa-book"></i> Materias Cursadas</h3>
            <div class="valor"><?php echo $materias_count; ?></div>
            <div class="etiqueta">Total de asignaturas</div>
        </div>
        
        <div class="tarjeta-resumen">
            <h3><i class="fas fa-chart-line"></i> Promedio General</h3>
            <div class="valor"><?php echo $promedio; ?></div>
            <div class="etiqueta">Calificación promedio</div>
        </div>
        
        <div class="tarjeta-resumen">
            <h3><i class="fas fa-trophy"></i> Créditos Obtenidos</h3>
            <div class="valor"><?php echo $total_creditos; ?>/<?php echo $creditos_totales; ?></div>
            <div class="etiqueta"><?php echo $porcentaje; ?>% completado</div>
        </div>
    </div>

    <!-- TABLA KARDEX -->
    <div class="kardex-container">
        <div class="table-container">
            <table class="tabla-kardex">
                <thead>
                    <tr>
                        <th>Materia</th>
                        <th>Maestro</th>
                        <th>Calificación Final</th>
                        <th>Oportunidad</th>
                        <th>Periodo</th>
                        <th>Aprobado</th>
                        <th>Créditos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): 
                        $final = $row['cal_final'];
                        $clase_calificacion = $final < 70 ? 'calificacion-baja' : ($final < 80 ? 'calificacion-media' : 'calificacion-alta');
                        $aprobado = $row['aprobado'] ? 'Sí' : 'No';
                        $clase_aprobado = $row['aprobado'] ? 'si' : 'no';
                    ?>
                    <tr>
                        <td style="text-align: left; font-weight: 500;"><?php echo htmlspecialchars($row['materia']); ?></td>
                        <td style="text-align: left;"><?php echo htmlspecialchars($row['maestro']); ?></td>
                        <td><span class="<?php echo $clase_calificacion; ?>"><?php echo $final; ?></span></td>
                        <td><?php echo htmlspecialchars($row['oportunidad']); ?></td>
                        <td><?php echo htmlspecialchars($row['periodo']); ?></td>
                        <td class="<?php echo $clase_aprobado; ?>"><?php echo $aprobado; ?></td>
                        <td style="font-weight: 500;"><?php echo $row['creditos']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <!-- FILAS DE RESUMEN -->
                    <tr class="fila-resumen">
                        <td colspan="6" style="text-align: right;">Total Créditos Acumulados</td>
                        <td style="font-weight: 700;"><?php echo $total_creditos; ?>/<?php echo $creditos_totales; ?></td>
                    </tr>
                    <tr class="fila-resumen">
                        <td colspan="6" style="text-align: right;">Promedio General / Porcentaje</td>
                        <td style="font-weight: 700;"><?php echo $promedio; ?> / <?php echo $porcentaje; ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    
    <!-- MENSAJE CUANDO NO HAY DATOS -->
    <div class="mensaje-vacio">
        <i class="fas fa-inbox"></i>
        <h3>No hay materias cursadas</h3>
        <p>Tu historial académico aparecerá aquí cuando curses materias.</p>
    </div>

    <?php endif; ?>

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