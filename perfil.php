<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

include "conexion.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - SICENET</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- FontAwesome sin CORS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <!-- HEADER (ya incluye el menú y sus scripts) -->
   <!-- <?php include "header.php"; ?> -->
	<div style="padding: 20px;">


    <!-- CONTENIDO -->
    <div style="padding: 20px;">
        <h1>Mi Perfil</h1>
        <p>Página de perfil del usuario</p>
    </div>

    <!-- Módulos adicionales -->
    <?php include "modulo_chat.php"; ?>
    <?php include "footer.php"; ?>

    <!-- Script opcional SOLO para debug, sin redeclarar variables -->
    <script>
    console.log("perfil.php cargado correctamente, sin duplicar scripts.");
    </script>

</body>
</html>
