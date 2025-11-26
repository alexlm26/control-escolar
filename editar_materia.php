<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

$id_materia = isset($_GET['id_materia']) ? intval($_GET['id_materia']) : 0;
if (!$id_materia) {
    header("Location: ../coordinador.php?seccion=materias&error=sin_materia");
    exit;
}

// Obtener información de la materia
$sql_materia = "SELECT m.*, c.nombre as carrera_nombre 
                FROM materia m 
                INNER JOIN carrera c ON m.id_carrera = c.id_carrera 
                WHERE m.id_materia = ?";
$stmt = $conexion->prepare($sql_materia);
$stmt->bind_param("i", $id_materia);
$stmt->execute();
$materia = $stmt->get_result()->fetch_assoc();

if (!$materia) {
    header("Location: ../coordinador.php?seccion=materias&error=materia_no_encontrada");
    exit;
}

// Obtener lista de materias para prerrequisitos (excluyendo la actual)
$sql_materias = "SELECT id_materia, nombre FROM materia 
                 WHERE id_carrera = ? AND id_materia != ? 
                 ORDER BY nombre";
$stmt_materias = $conexion->prepare($sql_materias);
$stmt_materias->bind_param("ii", $materia['id_carrera'], $id_materia);
$stmt_materias->execute();
$materias_disponibles = $stmt_materias->get_result();

if ($_POST) {
    $nombre = $_POST['nombre'];
    $creditos = $_POST['creditos'];
    $unidades = $_POST['unidades'];
    $semestre_sugerido = $_POST['semestre_sugerido'];
    $id_prerrequisito = $_POST['id_prerrequisito'] ?: NULL;

    // Verificar si ya existe otra materia con el mismo nombre en la misma carrera
    $sql_verificar = "SELECT id_materia FROM materia 
                      WHERE nombre = ? AND id_carrera = ? AND id_materia != ?";
    $stmt_verificar = $conexion->prepare($sql_verificar);
    $stmt_verificar->bind_param("sii", $nombre, $materia['id_carrera'], $id_materia);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();

    if ($result_verificar->num_rows > 0) {
        $error = "Ya existe una materia con el nombre '$nombre' en esta carrera";
    } else {
        // Actualizar la materia
        $sql_update = "UPDATE materia SET 
                      nombre = ?, creditos = ?, unidades = ?, 
                      semestre_sugerido = ?, id_prerrequisito = ?
                      WHERE id_materia = ?";
        $stmt_update = $conexion->prepare($sql_update);
        $stmt_update->bind_param(
            "siiiii", 
            $nombre, $creditos, $unidades, 
            $semestre_sugerido, $id_prerrequisito, $id_materia
        );

        if ($stmt_update->execute()) {
            $_SESSION['success'] = "Materia actualizada correctamente";
            header("Location: ../coordinador.php?seccion=materias");
            exit;
        } else {
            $error = "Error al actualizar la materia: " . $conexion->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Materia</title>
    <style>
        body {
            font-family: "Poppins", "Segoe UI", sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1565c0;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #1565c0;
        }
        .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
            background: white;
        }
        .form-select:focus {
            outline: none;
            border-color: #1565c0;
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
        }
        .btn-primary {
            background: #1565c0;
            color: white;
        }
        .btn-primary:hover {
            background: #1976d2;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 0;
            color: #1565c0;
            font-weight: 600;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .prerrequisito-info {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #e65100;
        }
        .semestre-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Editar Materia</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>📚 Editando materia de: <?php echo htmlspecialchars($materia['carrera_nombre']); ?></p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Nombre de la Materia:</label>
                <input type="text" name="nombre" class="form-control" 
                       value="<?php echo htmlspecialchars($materia['nombre']); ?>" 
                       placeholder="Ej: Redes de Computadoras 1" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Créditos:</label>
                    <input type="number" name="creditos" class="form-control" 
                           min="1" max="10" value="<?php echo $materia['creditos']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Unidades:</label>
                    <input type="number" name="unidades" class="form-control" 
                           min="1" max="15" value="<?php echo $materia['unidades']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Semestre Sugerido:</label>
                    <select name="semestre_sugerido" class="form-select" required>
                        <option value="">-- Seleccionar semestre --</option>
                        <?php for($i = 1; $i <= 9; $i++): ?>
                            <option value="<?php echo $i; ?>" 
                                <?php echo $materia['semestre_sugerido'] == $i ? 'selected' : ''; ?>>
                                Semestre <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <div class="semestre-info">
                        📅 Semestre recomendado para cursar esta materia
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Prerrequisito (Opcional):</label>
                <select name="id_prerrequisito" class="form-select">
                    <option value="">-- Sin prerrequisito --</option>
                    <?php while($materia_disponible = $materias_disponibles->fetch_assoc()): ?>
                        <option value="<?php echo $materia_disponible['id_materia']; ?>" 
                            <?php echo $materia['id_prerrequisito'] == $materia_disponible['id_materia'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($materia_disponible['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="prerrequisito-info">
                    💡 Si seleccionas un prerrequisito, los alumnos deberán aprobar esa materia antes de poder inscribirse a esta.
                </div>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Actualizar Materia</button>
                <a href="../coordinador.php?seccion=materias" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>