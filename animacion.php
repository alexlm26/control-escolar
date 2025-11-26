<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenido a SICENET</title>
    <style>
    body {
      margin: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #1565c0, #1976d2);
      position: relative;
      color: white;
    }

    /* Luz animada del fondo */
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

    .contenedor {
      position: relative;
      z-index: 2;
      text-align: center;
      animation: aparecer 1s ease-out forwards;
    }

    @keyframes aparecer {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .logo {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 20px;
      animation: flotar 3s ease-in-out infinite;
      box-shadow: 0 0 25px rgba(255,255,255,0.3);
    }

    @keyframes flotar {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    h1 {
      font-size: 2.5em;
      letter-spacing: 3px;
      text-shadow: 0 0 10px rgba(255,255,255,0.4);
      margin: 0;
    }

    h2 {
      font-size: 1.2em;
      font-weight: 400;
      margin-top: 10px;
      color: rgba(255,255,255,0.9);
    }

    .barra {
      width: 180px;
      height: 6px;
      background: rgba(255,255,255,0.2);
      border-radius: 3px;
      overflow: hidden;
      margin: 25px auto 0;
      position: relative;
    }

    .barra::before {
      content: "";
      position: absolute;
      top: 0; left: 0;
      width: 0%;
      height: 100%;
      background: white;
      border-radius: 3px;
      animation: carga 2.5s ease-in-out forwards;
    }

    @keyframes carga {
      to { width: 100%; }
    }
  </style>
</head>
<body>
  <div class="contenedor">
    <img src="img/articulo/default.png" class="logo" alt="Logo SICENET">
    <h1>Bienvenido</h1>
    <h2><?php echo strtoupper($_SESSION['nombre'] . " " . $_SESSION['apellidos']); ?></h2>
    <div class="barra"></div>
  </div>

  <script>
    // Espera 2.7 segundos y redirige al index
    setTimeout(() => {
      window.location.href = "index.php";
    }, 2700);
  </script>
</body>
</html>
