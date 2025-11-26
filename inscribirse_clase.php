<?php
ob_start(); 
include "header.php";
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 1) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';

// Procesar inscripción
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_clase'])) {
    $codigo_clase = intval($_POST['codigo_clase']);
    
    // Verificar que la clase existe y tiene cupo
    $query_clase = $conexion->prepare("
        SELECT 
            c.id_clase,
            m.nombre as materia_nombre,
            CONCAT(prof.nombre, ' ', prof.apellidos) as profesor_nombre,
            c.capacidad,
            (SELECT COUNT(*) FROM asignacion WHERE id_clase = c.id_clase) as alumnos_inscritos,
            car.nombre as carrera_nombre
        FROM clase c
        INNER JOIN materia m ON c.id_materia = m.id_materia
        INNER JOIN profesor p ON c.id_profesor = p.id_profesor
        INNER JOIN usuario prof ON p.id_usuario = prof.id_usuario
        INNER JOIN carrera car ON m.id_carrera = car.id_carrera
        WHERE c.id_clase = ? AND c.activo = 1
    ");
    $query_clase->bind_param("i", $codigo_clase);
    $query_clase->execute();
    $clase = $query_clase->get_result()->fetch_assoc();
    
    if ($clase) {
        // Verificar cupo
        if ($clase['alumnos_inscritos'] < $clase['capacidad']) {
            // Obtener id_alumno
            $query_alumno = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
            $query_alumno->bind_param("i", $id_usuario);
            $query_alumno->execute();
            $alumno = $query_alumno->get_result()->fetch_assoc();
            
            if ($alumno) {
                // Verificar que no esté ya inscrito
                $query_verificar = $conexion->prepare("SELECT id_asignacion FROM asignacion WHERE id_clase = ? AND id_alumno = ?");
                $query_verificar->bind_param("ii", $codigo_clase, $alumno['id_alumno']);
                $query_verificar->execute();
                
                if ($query_verificar->get_result()->num_rows == 0) {
                    // Inscribir al alumno
                    $query_inscribir = $conexion->prepare("INSERT INTO asignacion (id_clase, id_alumno, oportunidad) VALUES (?, ?, 'Ordinario')");
                    if ($query_inscribir->bind_param("ii", $codigo_clase, $alumno['id_alumno']) && $query_inscribir->execute()) {
                        $mensaje = "<div class='alert alert-success'>✅ Te has inscrito exitosamente a la clase: <strong>{$clase['materia_nombre']}</strong></div>";
                    } else {
                        $mensaje = "<div class='alert alert-error'>❌ Error al inscribirse en la clase</div>";
                    }
                } else {
                    $mensaje = "<div class='alert alert-warning'>⚠️ Ya estás inscrito en esta clase</div>";
                }
            }
        } else {
            $mensaje = "<div class='alert alert-error'>❌ La clase está llena. No hay cupos disponibles.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-error'>❌ Código de clase inválido o la clase no existe</div>";
    }
}
?>

<style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
}

body {
  background: #f4f6f8;
  font-family: "Poppins", sans-serif;
}

.content {
  padding: 40px 5%;
  max-width: 600px;
  margin: auto;
}

.header-inscripcion {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 30px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.header-inscripcion h1 {
  margin: 0 0 15px 0;
  font-size: 2.2em;
}

.form-container {
  background: white;
  padding: 40px;
  border-radius: 20px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  font-weight: 600;
  color: #333;
  font-size: 1.1em;
}

.form-control {
  width: 100%;
  padding: 15px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 1.1em;
  text-align: center;
  letter-spacing: 2px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primario);
  box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
}

.btn {
  padding: 15px 30px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  font-size: 1.1em;
  width: 100%;
}

.btn-primary {
  background: var(--color-primario);
  color: white;
}

.btn-primary:hover {
  background: var(--color-secundario);
  transform: translateY(-2px);
}

.alert {
  padding: 15px;
  border-radius: 10px;
  margin-bottom: 20px;
  text-align: center;
}

.alert-success {
  background: #e3f2fd;
  color: #1565c0;
  border: 1px solid #bbdefb;
}

.alert-error {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

.alert-warning {
  background: #fff3e0;
  color: #f57c00;
  border: 1px solid #ffe0b2;
}

.info-box {
  background: #e3f2fd;
  padding: 20px;
  border-radius: 10px;
  margin-top: 20px;
  border-left: 4px solid var(--color-primario);
}

.info-box h3 {
  margin: 0 0 10px 0;
  color: var(--color-primario);
}

.how-to {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.step {
  text-align: center;
  padding: 15px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.step-number {
  background: var(--color-primario);
  color: white;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
  font-weight: bold;
}
</style>
<!-- HEADER -->
<div class="header-inscripcion">
  <h1>Inscribirse a Clase</h1>
  <p>Ingresa el código de la clase para unirte</p>
</div>

<main class="content">
  <?php echo $mensaje; ?>
  
  <div class="form-container">
    <form method="POST">
      <div class="form-group">
        <label for="codigo_clase">Código de la Clase</label>
        <input type="number" id="codigo_clase" name="codigo_clase" class="form-control" required placeholder="Ej: 12345" min="1">
      </div>
      
      <button type="submit" class="btn btn-primary">
        Inscribirse a la Clase
      </button>
    </form>
    
    <div class="info-box">
      <h3>¿Cómo obtener el código?</h3>
      <div class="how-to">
        <div class="step">
          <div class="step-number">1</div>
          <div>Solicita el código al profesor</div>
        </div>
        <div class="step">
          <div class="step-number">2</div>
          <div>Ingresa el código aquí</div>
        </div>
        <div class="step">
          <div class="step-number">3</div>
          <div>¡Listo! Estarás inscrito</div>
        </div>
      </div>
    </div>
  </div>
  
  <div style="text-align: center; margin-top: 20px;">
    <a href="clases.php" class="btn" style="background: #6c757d; color: white; width: auto;">
      ← Volver a Mis Clases
    </a>
  </div>
</main>

<?php include "footer.php"; ?>