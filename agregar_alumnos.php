<?php
session_start();
include "conexion.php";

if($_SESSION['rol'] != '2'){ 
    header("Location: index.php");
    exit;
}

// ======== VALIDAR ID DE CLASE ========
if(!isset($_GET['id_clase']) || empty($_GET['id_clase'])){
    die("ERROR: CLASE NO ESPECIFICADA");
}

$id_clase = intval($_GET['id_clase']);

// ======== DATOS DE CLASE Y PROFESOR ========
$sql_clase = "
SELECT c.id_clase, c.id_materia, c.periodo, c.id_profesor, u.id_carrera, m.nombre AS materia
FROM clase c
INNER JOIN profesor p ON c.id_profesor = p.id_profesor
INNER JOIN usuario u ON p.id_usuario = u.id_usuario
INNER JOIN materia m ON c.id_materia = m.id_materia
WHERE c.id_clase = $id_clase
";
$res = $conexion->query($sql_clase);
if($res->num_rows == 0) die("ERROR: CLASE NO ENCONTRADA");

$clase = $res->fetch_assoc();
$id_materia = $clase['id_materia'];
$id_carrera = $clase['id_carrera'];
$materia = $clase['materia'];
$periodo = $clase['periodo'];

// ======== AGREGAR ALUMNOS ========
if(isset($_POST['asignar_alumnos'])){
    $asignados = 0;

    foreach($_POST['alumnos'] as $id_alumno){
        // tipo de oportunidad según reprobadas
        $sql_c = "
            SELECT COUNT(*) AS reprobadas 
            FROM materia_cursada 
            WHERE id_alumno = $id_alumno 
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

        $semestre = $_POST['semestre_'.$id_alumno];

        $conexion->query("
            INSERT INTO asignacion(id_clase, id_alumno, oportunidad, semestre)
            VALUES($id_clase, $id_alumno, '$tipo', $semestre)
        ");
        $asignados++;
    }
$_SESSION['alumnos_agregados'] = $asignados;
header("Location: alumnos_agregados.php?id_clase=$id_clase&total=$asignados&materia=" . urlencode($materia) . "&periodo=" . urlencode($periodo));
exit;
}

// ======== LISTAR ALUMNOS DISPONIBLES ========
$sql_alumnos = "
SELECT DISTINCT a.id_alumno, u.nombre, u.apellidos, a.semestre
FROM alumno a
INNER JOIN usuario u ON a.id_usuario = u.id_usuario
LEFT JOIN materia_cursada mc ON mc.id_alumno = a.id_alumno AND mc.id_materia = $id_materia
WHERE u.id_carrera = $id_carrera
AND a.id_alumno NOT IN (
    SELECT id_alumno FROM asignacion WHERE id_clase = $id_clase
)
";

$res_alumnos = $conexion->query($sql_alumnos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>AGREGAR ALUMNOS A CLASE - SICENET</title>
<style>
  
    /* ======== BANNER DE FONDO ANIMADO ======== */
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #2e7d32, #43a047);
      overflow: hidden;
      position: relative;
    }

    body::after {
      content: "";
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: radial-gradient(circle at center, rgba(255,255,255,0.15), transparent 60%);
      animation: moverLuz 5s linear infinite;
      z-index: 1;
    }

    @keyframes moverLuz {
      0% { transform: translateX(-100%); }
      50% { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }

    /* ======== CONTENEDOR DEL LOGIN ======== */
    .login-container {
      position: relative;
      z-index: 2;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(15px);
      padding: 50px 40px;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      text-align: center;
      width: 100%;
      max-width: 380px;
      color: white;
      animation: aparecer 1s ease-out forwards;
    }

    @keyframes aparecer {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ======== LOGO Y TITULO ======== */
    .login-logo {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      box-shadow: 0 0 15px rgba(255,255,255,0.3);
      animation: flotar 4s ease-in-out infinite;
    }

    @keyframes flotar {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }

    .login-container h1 {
      font-size: 2em;
      margin-bottom: 5px;
      font-weight: 700;
      letter-spacing: 2px;
      color: #ffffff;
      text-shadow: 0 0 10px rgba(255,255,255,0.5);
    }

    .login-container h2 {
      margin-bottom: 25px;
      font-size: 1.3em;
      letter-spacing: 1px;
      font-weight: 400;
      color: rgba(255,255,255,0.9);
    }

    .login-container form {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .login-container input {
      padding: 12px;
      border: none;
      border-radius: 8px;
      outline: none;
      background: rgba(255, 255, 255, 0.8);
      font-size: 1em;
      transition: 0.3s;
    }

    .login-container input:focus {
      background: white;
      box-shadow: 0 0 10px rgba(255,255,255,0.5);
    }

    .login-container button {
      padding: 12px;
      background: #1b5e20;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 1px;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
    }

    .login-container button:hover {
      background: #2e7d32;
      transform: translateY(-2px);
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
      .login-container {
        padding: 40px 25px;
      }
      .login-container h1 {
        font-size: 1.6em;
      }
    }


.content {
  width: 90%;
  max-width: 850px;
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(15px);
  border-radius: 15px;
  padding: 35px 25px;
  color: white;
  box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}
h2 {
  text-align: center;
  margin-bottom: 25px;
  font-size: 1.8em;
  text-shadow: 0 0 10px rgba(255,255,255,0.4);
}
table {
  width: 100%;
  border-collapse: collapse;
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
  background: #1565c0;
  color: white;
}
tr:nth-child(even) { background: #f4f6f8; }
button {
  margin-top: 15px;
  padding: 12px;
  background: #0d47a1;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1em;
  cursor: pointer;
  transition: background 0.3s;
}
button:hover { background: #1976d2; }
</style>
</head>
<body>
<main class="content">
<h2>AGREGAR ALUMNOS A LA CLASE DE <?= strtoupper($materia) ?> (<?= $periodo ?>)</h2>

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

  <?php if($res_alumnos->num_rows > 0){ ?>
  <button type="submit" name="asignar_alumnos">AGREGAR SELECCIONADOS</button>
  <?php } else { ?>
  <p style="text-align:center;color:#fff;margin-top:15px;">NO HAY ALUMNOS DISPONIBLES PARA AGREGAR</p>
  <?php } ?>
</form>

</main>
</body>
</html>