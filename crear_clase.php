<?php
session_start();
include "conexion.php";

if($_SESSION['rol'] != '2'){ 
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

$sql_prof = "
SELECT p.id_profesor, u.id_carrera 
FROM profesor p 
INNER JOIN usuario u ON p.id_usuario = u.id_usuario
WHERE p.id_usuario = $id_usuario
";
$res_prof = $conexion->query($sql_prof);
if($res_prof->num_rows == 0) die("ERROR PROFESOR NO ENCONTRADO");

$prof = $res_prof->fetch_assoc();
$id_profesor = $prof['id_profesor'];
$id_carrera = $prof['id_carrera'];

// === CREAR CLASE ===
if(isset($_POST['crear_clase'])){
    $id_materia = $_POST['id_materia'];
    $periodo = $_POST['periodo'];
    $id_salon = $_POST['id_salon'];
    $hora_inicio = intval($_POST['hora']);
    $dias = isset($_POST['dias']) ? $_POST['dias'] : [];

    $conexion->query("
        INSERT INTO clase(id_profesor, id_materia, id_salon, periodo, activo)
        VALUES($id_profesor, $id_materia, $id_salon, '$periodo', 1)
    ");
    $id_clase = $conexion->insert_id;

    foreach($dias as $dia){
        $conexion->query("
            INSERT INTO horarios_clase(id_clase, dia, hora)
            VALUES($id_clase, $dia, $hora_inicio)
        ");
    }

    $_SESSION['id_clase_creada'] = $id_clase;
    $_SESSION['id_materia_creada'] = $id_materia;
    $_SESSION['periodo_creado'] = $periodo;

    header("Location: animacion_crear_clase.php");
exit;

}

// === ASIGNAR ALUMNOS ===
if(isset($_POST['asignar_alumnos'])){
    $id_clase = $_SESSION['id_clase_creada'];
    $id_materia = $_SESSION['id_materia_creada'];
    $periodo = $_SESSION['periodo_creado'];
    $asignados = 0;

    foreach($_POST['alumnos'] as $id_alumno){
        $sql_cursadas = "
            SELECT COUNT(*) AS reprobadas 
            FROM materia_cursada 
            WHERE id_alumno = $id_alumno 
            AND id_materia = $id_materia
            AND aprobado = 0
        ";
        $res_c = $conexion->query($sql_cursadas);
        $c = $res_c->fetch_assoc();
        $veces = intval($c['reprobadas']);

        if($veces == 0) $tipo = 'Ordinario';
        elseif($veces == 1) $tipo = 'Recurse';
        elseif($veces == 2) $tipo = 'Especial';
        else $tipo = 'Global';

        $semestre = $_POST['semestre_'.$id_alumno];
        $conexion->query("
            INSERT INTO asignacion(id_clase, id_alumno, oportunidad, semestre)
            VALUES($id_clase, $id_alumno, '$tipo', $semestre)
        ");
        $asignados++;
    }

    unset($_SESSION['id_clase_creada'], $_SESSION['id_materia_creada'], $_SESSION['periodo_creado']);
    echo "<script>alert('✅ ALUMNOS ASIGNADOS CORRECTAMENTE ($asignados)');window.location='profesor.php';</script>";
    exit;
}

// === DATOS PARA SELECTS ===
$materias = $conexion->query("SELECT id_materia, nombre FROM materia ORDER BY nombre ASC");
$salones = $conexion->query("SELECT id_salon, nombre FROM salon ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CREAR CLASE - SICENET</title>
<style>
/* ======== FONDO ANIMADO ======== */
body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #2e7d32, #43a047);
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 40px 10px;
  position: relative;
  overflow-x: hidden;
}

body::after {
  content: "";
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: radial-gradient(circle at center, rgba(255,255,255,0.15), transparent 60%);
  animation: moverLuz 6s linear infinite;
  z-index: 1;
}

@keyframes moverLuz {
  0% { transform: translateX(-100%); }
  50% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}

/* ======== TARJETA CENTRAL ======== */
.content {
  position: relative;
  z-index: 2;
  width: 90%;
  max-width: 850px;
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(15px);
  border-radius: 15px;
  padding: 35px 25px;
  color: white;
  box-shadow: 0 8px 25px rgba(0,0,0,0.2);
  animation: aparecer 0.8s ease-out forwards;
}

@keyframes aparecer {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

h2 {
  text-align: center;
  margin-bottom: 25px;
  font-size: 1.9em;
  letter-spacing: 2px;
  text-shadow: 0 0 10px rgba(255,255,255,0.4);
}

/* ======== FORMULARIOS Y TABLAS ======== */
form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

label {
  font-weight: 600;
}

select, input[type="text"], input[type="number"] {
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-size: 1em;
  background: rgba(255,255,255,0.9);
  outline: none;
}

select:focus, input:focus {
  background: white;
  box-shadow: 0 0 10px rgba(255,255,255,0.4);
}

.dias label {
  margin-right: 12px;
  font-weight: 400;
}

button {
  padding: 12px;
  background: #1b5e20;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1em;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s, transform 0.2s;
}

button:hover {
  background: #2e7d32;
  transform: translateY(-2px);
}

/* ======== TABLA DE ALUMNOS ======== */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
  background: rgba(255,255,255,0.9);
  color: #333;
  border-radius: 10px;
  overflow: hidden;
}

th, td {
  border: 1px solid rgba(0,0,0,0.1);
  padding: 10px;
  text-align: center;
}

th {
  background: #1b5e20;
  color: white;
}

tr:nth-child(even) {
  background: #f4f6f8;
}

/* ======== RESPONSIVE ======== */
@media (max-width: 768px) {
  .content {
    padding: 25px 20px;
  }
  table {
    font-size: 0.9em;
  }
}
</style>
</head>
<body>

<main class="content">
<h2>CREAR NUEVA CLASE</h2>

<?php if(!isset($_GET['alumnos'])){ ?>
<form method="POST">
    <label>MATERIA:</label>
    <select name="id_materia" required>
        <option value="">-- SELECCIONA MATERIA --</option>
        <?php while($m = $materias->fetch_assoc()){ ?>
            <option value="<?= $m['id_materia'] ?>"><?= $m['nombre'] ?></option>
        <?php } ?>
    </select>

    <label>SALÓN:</label>
    <select name="id_salon" required>
        <option value="">-- SELECCIONA SALÓN --</option>
        <?php while($s = $salones->fetch_assoc()){ ?>
            <option value="<?= $s['id_salon'] ?>"><?= $s['nombre'] ?></option>
        <?php } ?>
    </select>

    <label>PERIODO:</label>
    <input type="text" name="periodo" placeholder="EJEMPLO 2025-1" required>

    <label>HORA (SOLO NÚMERO):</label>
    <input type="number" name="hora" min="1" max="24" required>

    <label>DÍAS:</label>
    <div class="dias">
        <label><input type="checkbox" name="dias[]" value="1"> LUNES</label>
        <label><input type="checkbox" name="dias[]" value="2"> MARTES</label>
        <label><input type="checkbox" name="dias[]" value="3"> MIÉRCOLES</label>
        <label><input type="checkbox" name="dias[]" value="4"> JUEVES</label>
        <label><input type="checkbox" name="dias[]" value="5"> VIERNES</label>
        <label><input type="checkbox" name="dias[]" value="6"> SÁBADO</label>
    </div>

    <button type="submit" name="crear_clase">CREAR CLASE</button>
</form>

<?php } else { 
$id_materia = $_SESSION['id_materia_creada'];
$sql_alumnos = "
SELECT DISTINCT a.id_alumno, u.nombre, u.apellidos, a.semestre
FROM alumno a
INNER JOIN usuario u ON a.id_usuario = u.id_usuario
LEFT JOIN materia_cursada mc ON mc.id_alumno = a.id_alumno AND mc.id_materia = $id_materia
WHERE u.id_carrera = $id_carrera
AND (
    a.id_alumno NOT IN (
        SELECT id_alumno FROM asignacion WHERE id_clase IN (
            SELECT id_clase FROM clase WHERE id_materia = $id_materia
        )
    )
    OR (mc.aprobado = 0 OR mc.aprobado IS NULL)
)
";

$res_alumnos = $conexion->query($sql_alumnos);
?>
<form method="POST">
  <table>
    <tr><th>SELECCIONAR</th><th>NOMBRE</th><th>SEMESTRE</th><th>OPORTUNIDAD</th></tr>
    <?php while($al = $res_alumnos->fetch_assoc()){ 
        $sql_c = "
            SELECT COUNT(*) AS reprobadas 
            FROM materia_cursada 
            WHERE id_alumno = {$al['id_alumno']} 
            AND id_materia = $id_materia
            AND aprobado = 0
        ";
        $r = $conexion->query($sql_c);
        $c = $r->fetch_assoc();
        $veces = intval($c['reprobadas']);
        if($veces == 0) $tipo = 'Ordinario';
        elseif($veces == 1) $tipo = 'Recurse';
        elseif($veces == 2) $tipo = 'Especial';
        else $tipo = 'Global';
    ?>
      <tr>
        <td><input type="checkbox" name="alumnos[]" value="<?= $al['id_alumno'] ?>"></td>
        <td><?= $al['nombre']." ".$al['apellidos']?></td>
        <td><?= $al['semestre'] ?><input type="hidden" name="semestre_<?= $al['id_alumno'] ?>" value="<?= $al['semestre'] ?>"></td>
        <td><?= $tipo ?></td>
      </tr>
    <?php } ?>
  </table>
  <button type="submit" name="asignar_alumnos">ASIGNAR SELECCIONADOS</button>
</form>
<?php } ?>
</main>
</body>
</html>
