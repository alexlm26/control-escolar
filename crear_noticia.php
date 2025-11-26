<?php
session_start();
include "../conexion.php";

if ($_SESSION['rol'] != '3') { 
    header("Location: ../index.php");
    exit;
}

// Obtener el id_usuario actual
$id_usuario = $_SESSION['id_usuario'];

if ($_POST) {
    $titulo = trim($_POST['titulo']);
    $info = trim($_POST['info']);
    $publicacion = $_POST['publicacion'];
    
    // Validar longitud de los campos
    if (strlen($titulo) > 100) {
        header("Location: ../index.php&error=El título no puede exceder los 100 caracteres");
        exit;
    }
    
    if (strlen($info) > 1200) {
        header("Location: ../index.php&error=La información no puede exceder los 1200 caracteres");
        exit;
    }
    
    // Procesar imagen
    $imagen = 'default.png';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $extensiones_permitidas = array('jpg', 'jpeg', 'png', 'gif');
        
        if (in_array(strtolower($extension), $extensiones_permitidas)) {
            $nombre_imagen = uniqid() . '.' . $extension;
            $carpeta_destino = "../img/articulo/"; // Cambiado a ../img/articulo/
            $ruta_destino = $carpeta_destino . $nombre_imagen;
            
            // Crear la carpeta si no existe
            if (!is_dir($carpeta_destino)) {
                if (!mkdir($carpeta_destino, 0755, true)) {
                    error_log("No se pudo crear la carpeta: " . $carpeta_destino);
                }
            }
            
            // Verificar permisos de escritura
            if (is_dir($carpeta_destino) && is_writable($carpeta_destino)) {
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                    $imagen = $nombre_imagen;
                } else {
                    error_log("Error moviendo archivo: " . $_FILES['imagen']['tmp_name'] . " -> " . $ruta_destino);
                }
            } else {
                error_log("Carpeta no tiene permisos de escritura: " . $carpeta_destino);
            }
        }
    }
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Insertar la nueva noticia
        $sql_noticia = "INSERT INTO noticias (titulo, info, publicacion, imagen, id_usuario, visitas, likes) 
                       VALUES ('$titulo', '$info', '$publicacion', '$imagen', $id_usuario, 0, 0)";
        
        if (!$conexion->query($sql_noticia)) {
            throw new Exception("Error al crear noticia: " . $conexion->error);
        }
        
        $conexion->commit();
        
        $mensaje = "Noticia '$titulo' creada correctamente";
        header("Location: ../index.php?seccion=noticias&mensaje=" . urlencode($mensaje));
        exit;
        
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: ../index.php?seccion=noticias&error=" . urlencode($e->getMessage()));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Noticia</title>
    <style>
        body {
            font-family: "Poppins", "Segoe UI", sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
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
        .alert-success {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #1565c0;
        }
        .alert-error {
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
        .titulo-info {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #e65100;
        }
        .info-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #2e7d32;
        }
        .imagen-info {
            background: #f3e5f5;
            border: 1px solid #9c27b0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #7b1fa2;
        }
        .fecha-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #1565c0;
        }
        .contador {
            text-align: right;
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
        .contador.alerta {
            color: #c62828;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .file-input {
            padding: 8px;
        }
        .preview-imagen {
            max-width: 200px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 8px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nueva Noticia</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>La noticia se publicará con tu usuario como autor y estará disponible para todos los estudiantes</p>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Título de la Noticia:</label>
                <input type="text" name="titulo" class="form-control" placeholder="Ej: Convocatoria para becas 2025" required maxlength="100">
                <div class="contador" id="contador-titulo">0/100 caracteres</div>
                <div class="titulo-info">
                    El título debe ser claro y descriptivo (máximo 100 caracteres)
                </div>
            </div>
            
            <div class="form-group">
                <label>Información de la Noticia:</label>
                <textarea name="info" class="form-control" placeholder="Escribe aquí el contenido completo de la noticia..." required maxlength="1200"></textarea>
                <div class="contador" id="contador-info">0/1200 caracteres</div>
                <div class="info-info">
                    Información detallada de la noticia (máximo 1200 caracteres)
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha y Hora de Publicación:</label>
                    <input type="datetime-local" name="publicacion" class="form-control" required>
                    <div class="fecha-info">
                        La noticia se mostrará a partir de esta fecha y hora
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Imagen (Opcional):</label>
                    <input type="file" name="imagen" class="form-control file-input" accept="image/*">
                    <img id="preview" class="preview-imagen" src="#" alt="Vista previa">
                    <div class="imagen-info">
                        Formatos permitidos: JPG, PNG, GIF. Si no seleccionas imagen, se usará una por defecto.
                    </div>
                </div>
            </div>
            
            <div class="acciones">
                <button type="submit" class="btn btn-primary">Publicar Noticia</button>
                <a href="../index.php?seccion=noticias" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        // Contadores de caracteres
        document.addEventListener('DOMContentLoaded', function() {
            const tituloInput = document.querySelector('input[name="titulo"]');
            const infoTextarea = document.querySelector('textarea[name="info"]');
            const contadorTitulo = document.getElementById('contador-titulo');
            const contadorInfo = document.getElementById('contador-info');
            const imagenInput = document.querySelector('input[name="imagen"]');
            const preview = document.getElementById('preview');
            
            // Actualizar contador del título
            tituloInput.addEventListener('input', function() {
                const longitud = this.value.length;
                contadorTitulo.textContent = longitud + '/100 caracteres';
                if (longitud > 90) {
                    contadorTitulo.classList.add('alerta');
                } else {
                    contadorTitulo.classList.remove('alerta');
                }
            });
            
            // Actualizar contador de la información
            infoTextarea.addEventListener('input', function() {
                const longitud = this.value.length;
                contadorInfo.textContent = longitud + '/1200 caracteres';
                if (longitud > 1100) {
                    contadorInfo.classList.add('alerta');
                } else {
                    contadorInfo.classList.remove('alerta');
                }
            });
            
            // Vista previa de imagen
            imagenInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            });
            
            // Establecer fecha y hora actual por defecto
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.querySelector('input[name="publicacion"]').value = now.toISOString().slice(0, 16);
            
            // Validación antes de enviar
            document.querySelector('form').addEventListener('submit', function(e) {
                const titulo = tituloInput.value.trim();
                const info = infoTextarea.value.trim();
                
                if (titulo.length < 5) {
                    e.preventDefault();
                    alert('El título debe tener al menos 5 caracteres');
                    tituloInput.focus();
                    return;
                }
                
                if (info.length < 10) {
                    e.preventDefault();
                    alert('La información debe tener al menos 10 caracteres');
                    infoTextarea.focus();
                    return;
                }
            });
        });
    </script>
</body>
</html>