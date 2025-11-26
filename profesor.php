<?php
session_start();
include "conexion.php";
include "header.php";

if ($_SESSION['rol'] != '2') { 
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'] ?? 1; 

// Obtener permisos del profesor usando IDs específicos
$sql_permisos = "SELECT id_accion, activo FROM acciones WHERE id_accion IN (1, 2)";
$res_permisos = $conexion->query($sql_permisos);
$permisos = [
    'modificar' => false,  // ID 1 - modificar_calificaciones
    'subir' => false       // ID 2 - subir_calificaciones
];

if ($res_permisos && $res_permisos->num_rows > 0) {
    while ($permiso = $res_permisos->fetch_assoc()) {
        // activo es BOOLEAN, se convierte directamente
        if ($permiso['id_accion'] == 1) {
            $permisos['modificar'] = (bool)$permiso['activo'];
        } elseif ($permiso['id_accion'] == 2) {
            $permisos['subir'] = (bool)$permiso['activo'];
        }
    }
}

// DEBUG: Verificar permisos obtenidos
error_log("Permisos desde BD - Modificar (ID 1): " . ($permisos['modificar'] ? 'ACTIVO' : 'INACTIVO') . ", Subir (ID 2): " . ($permisos['subir'] ? 'ACTIVO' : 'INACTIVO'));

$sql_prof = "SELECT id_profesor FROM profesor WHERE id_usuario = $id_usuario";
$res_prof = $conexion->query($sql_prof);
if (!$res_prof || $res_prof->num_rows == 0) {
    die("NO SE ENCONTRO EL PROFESOR");
}
$id_profesor = $res_prof->fetch_assoc()['id_profesor'];

$id_clase = $_GET['id_clase'] ?? null;
$mensaje_exito = $_GET['exito'] ?? '';
$mostrar_cerradas = $_GET['mostrar_cerradas'] ?? '0';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CLASES DEL PROFESOR</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
  --color-exito: #28a745;
  --color-error: #dc3545;
  --color-advertencia: #ffc107;
  --color-texto: #333;
  --color-fondo: #f8f9fa;
  --sombra: 0 4px 6px rgba(0,0,0,0.1);
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

.banner-bienvenida {
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  color: white;
  padding: 40px 20px;
  text-align: center;
  position: relative;
  overflow: hidden;
  margin-bottom: 30px;
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
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 10px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.container {
  width: 95%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
}

.filtro-clases {
  background: white;
  padding: 20px;
  border-radius: var(--border-radius);
  box-shadow: var(--sombra);
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 15px;
}

.switch-container {
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 600;
  color: var(--color-texto);
}

.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 30px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: var(--transition);
  border-radius: 34px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  transition: var(--transition);
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--color-primario);
}

input:checked + .slider:before {
  transform: translateX(30px);
}

.tabla-contenedor {
  background: white;
  border-radius: var(--border-radius);
  box-shadow: var(--sombra);
  overflow: hidden;
  margin-bottom: 30px;
}

.tabla-clases {
  width: 100%;
  border-collapse: collapse;
}

.tabla-clases thead {
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  color: white;
}

.tabla-clases th {
  padding: 15px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 0.9rem;
}

.tabla-clases td {
  padding: 12px;
  border-bottom: 1px solid #eee;
  font-size: 0.9rem;
}

.tabla-clases tr:hover {
  background-color: #f8f9fa;
}

.estado-activo {
  color: var(--color-exito);
  font-weight: 600;
}

.estado-cerrado {
  color: var(--color-error);
  font-weight: 600;
}

.btn-calificaciones {
  background: var(--color-primario);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.85rem;
  text-decoration: none;
  display: inline-block;
  text-align: center;
}

.btn-calificaciones:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(21, 101, 192, 0.3);
}

.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.7);
  z-index: 1000;
  backdrop-filter: blur(5px);
}

.modal-contenedor {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: white;
  border-radius: var(--border-radius);
  box-shadow: 0 20px 40px rgba(0,0,0,0.3);
  width: 95%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  z-index: 1001;
  animation: modalEntrada 0.4s ease-out;
}

@keyframes modalEntrada {
  from {
    opacity: 0;
    transform: translate(-50%, -60%);
  }
  to {
    opacity: 1;
    transform: translate(-50%, -50%);
  }
}

.modal-header {
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  color: white;
  padding: 20px;
  border-radius: var(--border-radius) var(--border-radius) 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.3rem;
}

.cerrar-modal {
  background: none;
  border: none;
  color: white;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 5px;
  transition: var(--transition);
}

.cerrar-modal:hover {
  transform: scale(1.1);
}

.modal-body {
  padding: 25px;
}

.animacion-exito {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, var(--color-primario), var(--color-secundario));
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: desaparecer 0.5s ease-out 2.5s forwards;
}
        /* ESTILOS PARA BOTÓN DESCARGAR HORARIO */
.btn-descargar-horario {
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.9rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.btn-descargar-horario:hover {
  background: linear-gradient(135deg, #218838, #1e9e6f);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
  color: white;
  text-decoration: none;
}

.btn-descargar-horario:active {
  transform: translateY(0);
}

/* Responsive para el botón */
@media (max-width: 768px) {
  .filtro-clases > div {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  
  .btn-descargar-horario {
    align-self: flex-start;
  }
}

.contenedor-exito {
  text-align: center;
  color: white;
  animation: aparecer 1s ease-out forwards;
}

@keyframes aparecer {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes desaparecer {
  to { opacity: 0; visibility: hidden; }
}

.logo-exito {
  width: 80px;
  height: 80px;
  margin-bottom: 15px;
  animation: flotar 3s ease-in-out infinite;
}

@keyframes flotar {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.barra-exito {
  width: 150px;
  height: 4px;
  background: rgba(255,255,255,0.3);
  border-radius: 2px;
  overflow: hidden;
  margin: 20px auto 0;
}

.barra-exito::before {
  content: "";
  display: block;
  width: 0%;
  height: 100%;
  background: white;
  animation: carga 2.5s ease-in-out forwards;
}

@keyframes carga {
  to { width: 100%; }
}

/* ESTILOS PARA PERMISOS */
.permisos-info {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: var(--border-radius);
  padding: 15px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.permisos-info i {
  color: #856404;
  font-size: 1.2rem;
}

.permisos-info p {
  margin: 0;
  color: #856404;
  font-weight: 500;
}

.btn-deshabilitado {
  background: #6c757d !important;
  cursor: not-allowed !important;
  opacity: 0.6;
}

.btn-deshabilitado:hover {
  transform: none !important;
  box-shadow: none !important;
  background: #6c757d !important;
}

.indicador-permisos {
  display: flex;
  gap: 15px;
  margin-top: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.permiso-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #e9ecef;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.permiso-activo {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.permiso-inactivo {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.mensaje-sin-permisos {
  background: #f8d7da;
  color: #721c24;
  padding: 20px;
  border-radius: var(--border-radius);
  text-align: center;
  margin: 20px 0;
  border-left: 4px solid var(--color-error);
}

@media (max-width: 768px) {
  .banner-bienvenida {
    padding: 30px 15px;
  }
  
  .banner-bienvenida h1 {
    font-size: 1.6rem;
  }
  
  .filtro-clases {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .tabla-contenedor {
    overflow-x: auto;
  }
  
  .tabla-clases {
    min-width: 600px;
  }
  
  .modal-contenedor {
    width: 98%;
    max-height: 95vh;
  }
  
  .modal-body {
    padding: 15px;
  }
}

@media (max-width: 480px) {
  .container {
    width: 100%;
    padding: 0 10px;
  }
  
  .banner-bienvenida h1 {
    font-size: 1.4rem;
  }
  
  .tabla-clases th,
  .tabla-clases td {
    padding: 8px 6px;
    font-size: 0.8rem;
  }
  
  .btn-calificaciones {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .modal-header {
    padding: 15px;
  }
  
  .modal-header h3 {
    font-size: 1.1rem;
  }
  
  .indicador-permisos {
    flex-direction: column;
    align-items: center;
  }
}
</style>
</head>
<body>

<?php if ($mensaje_exito): ?>
<div class="animacion-exito">
  <div class="contenedor-exito">
    <img src="img/articulo/default.png" class="logo-exito" alt="Logo SICENET">
    <h2>¡Éxito!</h2>
    <p>
      <?php 
      if ($mensaje_exito === 'calificaciones') {
        echo "CALIFICACIONES GUARDADAS CORRECTAMENTE";
      } elseif ($mensaje_exito === 'curso_cerrado') {
        echo "CURSO CERRADO EXITOSAMENTE";
      }
      ?>
    </p>
    <div class="barra-exito"></div>
  </div>
</div>

<script>
setTimeout(() => {
  const url = new URL(window.location.href);
  url.searchParams.delete('exito');
  window.location.href = url.toString();
}, 2700);
</script>
<?php endif; ?>

<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
  <div class="banner-texto">
    <h1>PANEL DEL PROFESOR</h1>
    <p>Gestión de clases y calificaciones</p>
    
    <!-- Indicador de permisos -->
    <div class="indicador-permisos">
      <div class="permiso-item <?php echo $permisos['modificar'] ? 'permiso-activo' : 'permiso-inactivo'; ?>">
        <i class="fas <?php echo $permisos['modificar'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
        Modificar Calificaciones (ID 1)
      </div>
      <div class="permiso-item <?php echo $permisos['subir'] ? 'permiso-activo' : 'permiso-inactivo'; ?>">
        <i class="fas <?php echo $permisos['subir'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
        Subir Calificaciones (ID 2)
      </div>
    </div>
  </div>
</section>

<div class="container">

<!-- Mostrar advertencia si algún permiso está desactivado -->
<?php if (!$permisos['modificar'] || !$permisos['subir']): ?>
<div class="permisos-info">
  <i class="fas fa-exclamation-triangle"></i>
  <p>
    <strong>Nota:</strong> 
    <?php if (!$permisos['subir'] && !$permisos['modificar']): ?>
      Las funciones de calificaciones están deshabilitadas. Contacte al administrador.
    <?php elseif (!$permisos['subir']): ?>
      La función de subir calificaciones está deshabilitada.
    <?php else: ?>
      La función de modificar calificaciones está deshabilitada.
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>

<!-- FILTRO DE CLASES -->
<div class="filtro-clases">
  <h2>CLASES ASIGNADAS</h2>
  <div class="switch-container">
    <span>Mostrar clases cerradas</span>
    <label class="switch">
      <input type="checkbox" id="toggleClasesCerradas" <?php echo $mostrar_cerradas === '1' ? 'checked' : ''; ?>>
      <span class="slider"></span>
    </label>
              <a href="horario_profesor.php" class="btn-descargar-horario" target="_blank">
      <i class="fas fa-download"></i>
      Descargar Horario
    </a>
  </div>
</div>

<!-- TABLA DE CLASES -->
<div class="tabla-contenedor">
  <table class="tabla-clases">
    <thead>
      <tr>
        <th>ID CLASE</th>
        <th>MATERIA</th>
        <th>SALÓN</th>
        <th>PERIODO</th>
        <th>ESTADO</th>
        <th>ACCIONES</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sql_clases = "
      SELECT c.id_clase, m.nombre AS materia, s.nombre AS salon, c.periodo, c.activo
      FROM clase c
      INNER JOIN materia m ON c.id_materia = m.id_materia
      INNER JOIN salon s ON c.id_salon = s.id_salon
      WHERE c.id_profesor = $id_profesor
      " . ($mostrar_cerradas === '0' ? " AND c.activo = 1" : "") . "
      ORDER BY c.activo DESC, c.periodo DESC
      ";
      
      $res_clases = $conexion->query($sql_clases);

      if ($res_clases && $res_clases->num_rows > 0) {
        while ($cl = $res_clases->fetch_assoc()) {
          $estado_clase = $cl['activo'] ? 'activo' : 'cerrado';
          $estado_texto = $cl['activo'] ? 'ACTIVO' : 'CERRADO';
          $estado_clase_css = $cl['activo'] ? 'estado-activo' : 'estado-cerrado';
          
          // Verificar permisos para mostrar botón
          $tiene_permisos_calificaciones = $permisos['subir'] || $permisos['modificar'];
          $boton_deshabilitado = !$tiene_permisos_calificaciones;
          $clase_boton = $boton_deshabilitado ? 'btn-calificaciones btn-deshabilitado' : 'btn-calificaciones';
          $onclick = $boton_deshabilitado ? '' : "onclick='abrirModalCalificaciones({$cl['id_clase']})'";
          $titulo = $boton_deshabilitado ? 'title="Funciones de calificaciones deshabilitadas"' : '';
          
          echo "<tr data-estado='$estado_clase'>";
          echo "<td>{$cl['id_clase']}</td>";
          echo "<td>{$cl['materia']}</td>";
          echo "<td>{$cl['salon']}</td>";
          echo "<td>{$cl['periodo']}</td>";
          echo "<td class='$estado_clase_css'>$estado_texto</td>";
          echo "<td>";
          
          if($cl['activo']) {
            echo "<button class='$clase_boton' $onclick $titulo>";
            echo "<i class='fas fa-edit'></i> Calificaciones";
            echo "</button>";
          } else {
            echo "<span style='color:#6c757d;font-size:0.85rem;'>No disponible</span>";
          }
          
          echo "</td>";
          echo "</tr>";
        }
      } else {
        echo "<tr><td colspan='6' style='text-align: center; padding: 40px; color: #666;'>No se encontraron clases asignadas</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

</div>

<!-- MODAL DE CALIFICACIONES -->
<div class="modal-overlay" id="modalCalificaciones">
  <div class="modal-contenedor">
    <div class="modal-header">
      <h3 id="modalTitulo">Gestionar Calificaciones</h3>
      <button class="cerrar-modal" onclick="cerrarModalCalificaciones()">&times;</button>
    </div>
    <div class="modal-body" id="modalBody">
      <!-- El contenido se carga dinámicamente -->
    </div>
  </div>
</div>

<script>
// Switch para mostrar/ocultar clases cerradas
document.getElementById('toggleClasesCerradas').addEventListener('change', function() {
  const url = new URL(window.location.href);
  url.searchParams.set('mostrar_cerradas', this.checked ? '1' : '0');
  window.location.href = url.toString();
});

// Funciones del modal
function abrirModalCalificaciones(idClase) {
  // Verificar permisos antes de abrir el modal
  const tienePermisos = <?php echo ($permisos['subir'] || $permisos['modificar']) ? 'true' : 'false'; ?>;
  
  if (!tienePermisos) {
    alert('No tiene permisos para gestionar calificaciones');
    return;
  }

  // Mostrar loading en el modal
  document.getElementById('modalBody').innerHTML = `
    <div style="text-align: center; padding: 40px;">
      <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1565c0;"></i>
      <p style="margin-top: 15px; color: #666;">Cargando calificaciones...</p>
    </div>
  `;
  
  document.getElementById('modalTitulo').textContent = `Calificaciones - Clase #${idClase}`;
  document.getElementById('modalCalificaciones').style.display = 'block';
  document.body.style.overflow = 'hidden';

  // Pasar permisos al cargar calificaciones
  fetch(`cargar_calificaciones.php?id_clase=${idClase}&permiso_subir=<?php echo $permisos['subir'] ? 1 : 0; ?>&permiso_modificar=<?php echo $permisos['modificar'] ? 1 : 0; ?>`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Error en la respuesta del servidor');
      }
      return response.text();
    })
    .then(html => {
      document.getElementById('modalBody').innerHTML = html;
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('modalBody').innerHTML = `
        <div style="text-align: center; padding: 40px; color: #dc3545;">
          <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
          <p style="margin-top: 15px;">Error al cargar las calificaciones</p>
          <button onclick="cerrarModalCalificaciones()" style="margin-top: 10px; padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Cerrar
          </button>
        </div>
      `;
    });
}

function cerrarModalCalificaciones() {
  document.getElementById('modalCalificaciones').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalCalificaciones').addEventListener('click', function(e) {
  if (e.target === this) {
    cerrarModalCalificaciones();
  }
});

// Prevenir que el modal se cierre al hacer clic dentro
document.querySelector('.modal-contenedor').addEventListener('click', function(e) {
  e.stopPropagation();
});
</script>

</body>
</html>