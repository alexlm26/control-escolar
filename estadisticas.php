<?php
session_start();
if ($_SESSION['rol'] != '3') { 
    header("Location: index.php");
    exit;
}
include "conexion.php";
include "header.php";

$id_usuario = $_SESSION['id_usuario'];
$id_coordinador = $_SESSION['id_coordinador'] ?? null;

// Obtener información del coordinador
$query_coordinador = "SELECT c.id_carrera, car.nombre as carrera_nombre 
                      FROM coordinador c 
                      LEFT JOIN carrera car ON c.id_carrera = car.id_carrera 
                      WHERE c.id_coordinador = ?";
$stmt = $conexion->prepare($query_coordinador);
$stmt->bind_param("i", $id_coordinador);
$stmt->execute();
$result_coordinador = $stmt->get_result();
$coordinador = $result_coordinador->fetch_assoc();

$id_carrera_coordinador = $coordinador['id_carrera'] ?? 0;
$carrera_nombre = $coordinador['carrera_nombre'] ?? 'Todas las Carreras';
?>

<div class="container-fluid">
    <!-- Header de Estadísticas -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Panel de Estadísticas</h1>
        <div class="d-flex flex-column flex-md-row mt-2 mt-md-0">
            <button id="exportarReporte" class="btn btn-success mb-2 mb-md-0 mr-md-2">
                <i class="fas fa-file-export"></i> <span class="d-none d-md-inline">Exportar Reporte</span>
            </button>
            <button id="actualizarDatos" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> <span class="d-none d-md-inline">Actualizar</span>
            </button>
        </div>
    </div>

    <!-- Información del Coordinador -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle"></i> 
        <strong>Coordinador:</strong> <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellidos']; ?> | 
        <strong>Vista:</strong> <?php echo $carrera_nombre; ?>
    </div>

    <!-- Filtros Responsivos -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
            <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Filtros</h6>
            <div class="d-flex flex-wrap">
                <button id="aplicarFiltros" class="btn btn-primary btn-sm mr-2 mb-1">
                    <i class="fas fa-filter"></i> <span class="d-none d-sm-inline">Aplicar</span>
                </button>
                <button id="limpiarFiltros" class="btn btn-secondary btn-sm mb-1">
                    <i class="fas fa-broom"></i> <span class="d-none d-sm-inline">Limpiar</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($id_carrera_coordinador == 0): ?>
                <!-- Solo mostrar filtro de carrera para coordinador general -->
                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="form-group">
                        <label for="filtroCarrera" class="small font-weight-bold">Carrera</label>
                        <select class="form-control form-control-sm" id="filtroCarrera">
                            <option value="">Todas las carreras</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="form-group">
                        <label for="filtroPeriodo" class="small font-weight-bold">Periodo</label>
                        <select class="form-control form-control-sm" id="filtroPeriodo">
                            <option value="">Todos los periodos</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="form-group">
                        <label for="filtroSemestre" class="small font-weight-bold">Semestre</label>
                        <select class="form-control form-control-sm" id="filtroSemestre">
                            <option value="">Todos los semestres</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="form-group">
                        <label for="filtroAnio" class="small font-weight-bold">Año</label>
                        <select class="form-control form-control-sm" id="filtroAnio">
                            <option value="">Todos los años</option>
                            <?php 
                            $currentYear = date('Y');
                            for($i = $currentYear; $i >= $currentYear - 5; $i--): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen General - Responsivo -->
    <div class="row">
        <!-- Alumnos Activos -->
        <div class="col-6 col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Alumnos Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalAlumnos">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-lg text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profesores Activos -->
        <div class="col-6 col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Profesores Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalProfesores">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-lg text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promedio General -->
        <div class="col-6 col-md-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Promedio General</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="promedioGeneral">0.00</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-lg text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promedio Materias Activas -->
        <div class="col-6 col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Prom. Materias Activas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="promedioMateriasActivas">0.00</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-lg text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasa de Aprobación -->
    <div class="row">
        <div class="col-12 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Tasa de Aprobación</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="tasaAprobacion">0%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-lg text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficas Responsivas -->
    <div class="row">
        <!-- Distribución de Alumnos por Carrera -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $id_carrera_coordinador == 0 ? 'Distribución de Alumnos por Carrera' : 'Alumnos por Semestre'; ?>
                    </h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate1">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="carreraChart" width="100%" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rendimiento por Semestre -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Rendimiento por Semestre</h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate2">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-bar pt-4 pb-2">
                        <canvas id="rendimientoChart" width="100%" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Segunda Fila de Gráficas -->
    <div class="row">
        <!-- Asistencia por Materia -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Asistencia por Materia</h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate3">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-bar pt-4 pb-2">
                        <canvas id="asistenciaChart" width="100%" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de Alumnos -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Estado de Alumnos</h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate4">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="estadoAlumnosChart" width="100%" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tercera Fila de Gráficas -->
    <div class="row">
        <!-- Eficiencia de Profesores -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Eficiencia de Profesores</h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate5">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-bar pt-4 pb-2">
                        <canvas id="profesoresChart" width="100%" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tendencias de Aprobación -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Tendencias de Aprobación</h6>
                    <small class="text-muted mt-1 mt-md-0">Actualizado: <span id="lastUpdate6">--</span></small>
                </div>
                <div class="card-body">
                    <div class="chart-line pt-4 pb-2">
                        <canvas id="tendenciasChart" width="100%" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Datos Detallados -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary" id="tituloTabla">
                        <?php echo $id_carrera_coordinador == 0 ? 'Progreso de Alumnos por Carrera y Semestre' : 'Clases Activas - Detalles'; ?>
                    </h6>
                    <div class="mt-2 mt-md-0">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" placeholder="Buscar..." id="searchTable">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm" id="dataTable" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <?php if ($id_carrera_coordinador == 0): ?>
                                        <th>Carrera</th>
                                        <th>Semestre</th>
                                        <th>Alumnos</th>
                                        <th>Promedio</th>
                                        <th>Aprobación</th>
                                        <th>Asistencia</th>
                                    <?php else: ?>
                                        <th>Materia</th>
                                        <th>Grupo</th>
                                        <th>Semestre</th>
                                        <th>Alumnos</th>
                                        <th>Promedio</th>
                                        <th>Aprobación</th>
                                        <th>Asistencia</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="tablaDatos">
                                <!-- Los datos se cargarán via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p class="mb-0">Cargando estadísticas...</p>
            </div>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>

<!-- Cargar jQuery primero -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Cargar Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Cargar Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Loading Spinner -->
<div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p class="mb-0">Cargando estadísticas...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let charts = {};
let currentFilters = {
    carrera: '',
    periodo: '',
    semestre: '',
    anio: ''
};
const coordinadorCarreraId = <?php echo $id_carrera_coordinador; ?>;

// Esperar a que el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si jQuery está cargado
    if (typeof jQuery === 'undefined') {
        console.error('jQuery no está cargado. Cargando ahora...');
        // Cargar jQuery dinámicamente si no está disponible
        const script = document.createElement('script');
        script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
        script.onload = inicializarSistema;
        document.head.appendChild(script);
    } else {
        inicializarSistema();
    }
});

function inicializarSistema() {
    console.log('jQuery cargado, inicializando panel de estadísticas...');
    
    // Usar jQuery con el alias $
    const $ = jQuery;
    
    cargarFiltros();
    cargarEstadisticas();
    
    // Event Listeners
    $('#aplicarFiltros').click(function() {
        console.log('Aplicando filtros...');
        cargarEstadisticas();
    });
    
    $('#actualizarDatos').click(function() {
        console.log('Actualizando datos...');
        cargarEstadisticas();
    });
    
    $('#limpiarFiltros').click(function() {
        limpiarFiltros();
    });
    
    // Buscar en tabla
    $('#searchTable').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#tablaDatos tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    function cargarFiltros() {
        console.log('Cargando filtros...');
        $.ajax({
            url: 'estadisticas/get_filtros.php',
            type: 'GET',
            data: { id_carrera: coordinadorCarreraId },
            success: function(response) {
                console.log('Respuesta de filtros:', response);
                try {
                    const data = JSON.parse(response);
                    
                    if (data.error) {
                        console.error('Error en filtros:', data.error);
                        mostrarError('Error cargando filtros: ' + data.error);
                        return;
                    }
                    
                    // Cargar carreras solo si es coordinador general
                    if (coordinadorCarreraId == 0 && data.carreras) {
                        $('#filtroCarrera').html('<option value="">Todas las carreras</option>');
                        data.carreras.forEach(carrera => {
                            $('#filtroCarrera').append(`<option value="${carrera.id}">${carrera.nombre}</option>`);
                        });
                    }
                    
                    // Cargar periodos
                    if (data.periodos && data.periodos.length > 0) {
                        $('#filtroPeriodo').html('<option value="">Todos los periodos</option>');
                        data.periodos.forEach(periodo => {
                            $('#filtroPeriodo').append(`<option value="${periodo}">${periodo}</option>`);
                        });
                    } else {
                        $('#filtroPeriodo').html('<option value="">No hay periodos</option>');
                    }
                } catch (e) {
                    console.error('Error parseando JSON de filtros:', e);
                    mostrarError('Error procesando los filtros');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error cargando filtros:', error);
                mostrarError('Error al cargar los filtros: ' + error);
            }
        });
    }

    function cargarEstadisticas() {
        console.log('Cargando estadísticas...');
        mostrarLoading();
        
        // Obtener filtros actuales
        currentFilters = {
            carrera: coordinadorCarreraId == 0 ? $('#filtroCarrera').val() : coordinadorCarreraId,
            periodo: $('#filtroPeriodo').val(),
            semestre: $('#filtroSemestre').val(),
            anio: $('#filtroAnio').val()
        };
        
        console.log('Enviando filtros:', currentFilters);
        
        $.ajax({
            url: 'estadisticas/get_estadisticas.php',
            type: 'POST',
            data: currentFilters,
            success: function(response) {
                console.log('Respuesta de estadísticas:', response);
                try {
                    const data = JSON.parse(response);
                    
                    if (data.error) {
                        console.error('Error en estadísticas:', data.error);
                        mostrarError('Error: ' + data.error);
                        ocultarLoading();
                        return;
                    }
                    
                    actualizarResumen(data.resumen);
                    actualizarGraficas(data.graficas);
                    actualizarTabla(data.tabla);
                    actualizarTimestamps();
                    
                } catch (e) {
                    console.error('Error parseando JSON de estadísticas:', e);
                    mostrarError('Error al procesar los datos');
                }
                ocultarLoading();
            },
            error: function(xhr, status, error) {
                console.error('Error cargando estadísticas:', error);
                mostrarError('Error al cargar las estadísticas');
                ocultarLoading();
            }
        });
    }

    function actualizarResumen(resumen) {
        console.log('Actualizando resumen:', resumen);
        $('#totalAlumnos').text(resumen.totalAlumnos || 0);
        $('#totalProfesores').text(resumen.totalProfesores || 0);
        $('#promedioGeneral').text(resumen.promedioGeneral || '0.00');
        $('#promedioMateriasActivas').text(resumen.promedioMateriasActivas || '0.00');
        $('#tasaAprobacion').text((resumen.tasaAprobacion || 0) + '%');
    }

    function actualizarGraficas(graficas) {
        console.log('Actualizando gráficas:', graficas);
        
        // Destruir gráficas existentes
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
        
        // Crear nuevas gráficas
        if (graficas.carrera && graficas.carrera.labels && graficas.carrera.labels.length > 0) {
            crearGraficaCarrera(graficas.carrera);
        }
        
        if (graficas.rendimiento && graficas.rendimiento.labels && graficas.rendimiento.labels.length > 0) {
            crearGraficaRendimiento(graficas.rendimiento);
        }
        
        if (graficas.asistencia && graficas.asistencia.labels) {
            crearGraficaAsistencia(graficas.asistencia);
        }
        
        if (graficas.estadoAlumnos && graficas.estadoAlumnos.labels) {
            crearGraficaEstadoAlumnos(graficas.estadoAlumnos);
        }
        
        if (graficas.profesores && graficas.profesores.labels) {
            crearGraficaProfesores(graficas.profesores);
        }
        
        if (graficas.tendencias && graficas.tendencias.labels) {
            crearGraficaTendencias(graficas.tendencias);
        }
    }

    function crearGraficaCarrera(data) {
        const ctx = document.getElementById('carreraChart');
        if (!ctx) return;
        
        charts.carrera = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#e02d1b', '#707384'],
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: window.innerWidth < 768 ? 'bottom' : 'right',
                    }
                }
            }
        });
    }

    function crearGraficaRendimiento(data) {
        const ctx = document.getElementById('rendimientoChart');
        if (!ctx) return;
        
        charts.rendimiento = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Promedio',
                    data: data.values,
                    backgroundColor: '#4e73df',
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    function crearGraficaAsistencia(data) {
        const ctx = document.getElementById('asistenciaChart');
        if (!ctx) return;
        
        charts.asistencia = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Asistencia %',
                    data: data.values,
                    backgroundColor: '#1cc88a',
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    function crearGraficaEstadoAlumnos(data) {
        const ctx = document.getElementById('estadoAlumnosChart');
        if (!ctx) return;
        
        charts.estadoAlumnos = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b', '#858796'],
                }]
            },
            options: {
                maintainAspectRatio: false,
            }
        });
    }

    function crearGraficaProfesores(data) {
        const ctx = document.getElementById('profesoresChart');
        if (!ctx) return;
        
        charts.profesores = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Eficiencia %',
                    data: data.values,
                    backgroundColor: '#36b9cc',
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
            }
        });
    }

    function crearGraficaTendencias(data) {
        const ctx = document.getElementById('tendenciasChart');
        if (!ctx) return;
        
        charts.tendencias = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Aprobación %',
                    data: data.values,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231, 74, 59, 0.1)',
                    fill: true,
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    function actualizarTabla(datos) {
        console.log('Actualizando tabla:', datos);
        let html = '';
        if (datos && datos.length > 0) {
            datos.forEach(fila => {
                if (coordinadorCarreraId == 0) {
                    html += `
                        <tr>
                            <td>${fila.carrera || 'N/A'}</td>
                            <td>${fila.semestre || 'N/A'}</td>
                            <td>${fila.alumnos || 0}</td>
                            <td>${fila.promedio || '0.00'}</td>
                            <td>${fila.aprobacion || 0}%</td>
                            <td>${fila.asistencia || 0}%</td>
                        </tr>
                    `;
                } else {
                    html += `
                        <tr>
                            <td>${fila.materia || 'N/A'}</td>
                            <td>${fila.grupo || 'N/A'}</td>
                            <td>${fila.semestre || 'N/A'}</td>
                            <td>${fila.alumnos || 0}</td>
                            <td>${fila.promedio || '0.00'}</td>
                            <td>${fila.aprobacion || 0}%</td>
                            <td>${fila.asistencia || 0}%</td>
                        </tr>
                    `;
                }
            });
        } else {
            html = '<tr><td colspan="' + (coordinadorCarreraId == 0 ? '6' : '7') + '" class="text-center">No hay datos disponibles</td></tr>';
        }
        $('#tablaDatos').html(html);
    }

    function actualizarTimestamps() {
        const now = new Date().toLocaleTimeString();
        $('[id^="lastUpdate"]').text(now);
    }

    function limpiarFiltros() {
        if (coordinadorCarreraId == 0) {
            $('#filtroCarrera').val('');
        }
        $('#filtroPeriodo').val('');
        $('#filtroSemestre').val('');
        $('#filtroAnio').val('');
        cargarEstadisticas();
    }

    function mostrarLoading() {
        $('#loadingModal').modal('show');
    }

    function ocultarLoading() {
        $('#loadingModal').modal('hide');
    }

    function mostrarError(mensaje) {
        const alerta = $(`<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>`);
        
        $('.container-fluid').prepend(alerta);
        
        setTimeout(() => {
            alerta.alert('close');
        }, 5000);
    }

    // Ajustar gráficas en redimensionamiento
    $(window).on('resize', function() {
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    });
}
</script>
