<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 2) {
  header("Location: login.php");
  exit();
}

// Verificar que se viene de agregar_alumnos.php
if (!isset($_GET['id_clase']) || empty($_GET['id_clase'])) {
  header("Location: clases.php");
  exit();
}

$id_clase = intval($_GET['id_clase']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alumnos Agregados - SICENET</title>
<style>
body {
  margin: 0;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #2e7d32, #43a047);
  color: white;
  overflow: hidden;
  position: relative;
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

.contenedor {
  position: relative;
  z-index: 2;
  text-align: center;
  animation: aparecer 1s ease-out forwards;
  padding: 40px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(15px);
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.2);
}

@keyframes aparecer {
  from { 
    opacity: 0; 
    transform: translateY(30px) scale(0.9); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1); 
  }
}

.icono-exito {
  font-size: 5em;
  margin-bottom: 20px;
  display: block;
  animation: latido 2s ease-in-out infinite;
  filter: drop-shadow(0 0 10px rgba(255,255,255,0.5));
}

@keyframes latido {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

h1 {
  font-size: 2.5em;
  letter-spacing: 2px;
  text-shadow: 0 0 10px rgba(255,255,255,0.4);
  margin: 0 0 10px 0;
  background: linear-gradient(45deg, #ffffff, #e8f5e8);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

h2 {
  font-size: 1.2em;
  font-weight: 400;
  margin-top: 10px;
  color: rgba(255,255,255,0.9);
  line-height: 1.5;
}

.barra {
  width: 220px;
  height: 8px;
  background: rgba(255,255,255,0.2);
  border-radius: 4px;
  overflow: hidden;
  margin: 30px auto 0;
  position: relative;
}

.barra::before {
  content: "";
  position: absolute;
  top: 0; left: 0;
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, #ffffff, #c8e6c9);
  border-radius: 4px;
  animation: carga 2.5s ease-in-out forwards;
  box-shadow: 0 0 10px rgba(255,255,255,0.5);
}

@keyframes carga {
  0% { width: 0%; }
  50% { width: 60%; }
  100% { width: 100%; }
}

.contador {
  font-size: 4em;
  font-weight: 700;
  margin: 20px 0;
  background: linear-gradient(45deg, #ffffff, #a5d6a7);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: contar 2s ease-out forwards;
}

@keyframes contar {
  from { 
    opacity: 0;
    transform: scale(0.5) rotate(-10deg);
  }
  to { 
    opacity: 1;
    transform: scale(1) rotate(0deg);
  }
}

.detalles {
  background: rgba(255,255,255,0.1);
  padding: 15px;
  border-radius: 10px;
  margin: 20px 0;
  border-left: 4px solid rgba(255,255,255,0.3);
}

.detalle-item {
  display: flex;
  justify-content: space-between;
  margin: 8px 0;
  font-size: 0.95em;
}

.botones {
  margin-top: 25px;
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  font-size: 1em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: rgba(255,255,255,0.9);
  color: #2e7d32;
}

.btn-primary:hover {
  background: white;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-secondary {
  background: rgba(255,255,255,0.2);
  color: white;
  border: 1px solid rgba(255,255,255,0.3);
}

.btn-secondary:hover {
  background: rgba(255,255,255,0.3);
  transform: translateY(-2px);
}

.confetti {
  position: absolute;
  width: 10px;
  height: 10px;
  background: #fff;
  border-radius: 50%;
  animation: confetti-fall 5s linear forwards;
  z-index: 3;
}

@keyframes confetti-fall {
  0% {
    transform: translateY(-100px) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(100vh) rotate(360deg);
    opacity: 0;
  }
}

/* Responsive */
@media (max-width: 768px) {
  .contenedor {
    padding: 30px 20px;
    margin: 20px;
  }
  
  h1 {
    font-size: 2em;
  }
  
  .contador {
    font-size: 3em;
  }
  
  .botones {
    flex-direction: column;
    align-items: center;
  }
  
  .btn {
    width: 100%;
    max-width: 250px;
    justify-content: center;
  }
}
</style>
</head>
<body>
  <!-- Confetti animation -->
  <div id="confetti-container"></div>

  <div class="contenedor">
    <div class="icono-exito">✅</div>
    
    <h1>¡ALUMNOS AGREGADOS!</h1>
    
    <div class="contador" id="contador">0</div>
    
    <div class="detalles">
      <div class="detalle-item">
        <span>Clase:</span>
        <span><strong><?php echo htmlspecialchars($_GET['materia'] ?? 'Clase'); ?></strong></span>
      </div>
      <div class="detalle-item">
        <span>Alumnos agregados:</span>
        <span><strong id="total-alumnos"><?php echo $_GET['total'] ?? '0'; ?></strong></span>
      </div>
      <div class="detalle-item">
        <span>Periodo:</span>
        <span><strong><?php echo htmlspecialchars($_GET['periodo'] ?? date('Y-m')); ?></strong></span>
      </div>
    </div>
    
    <h2>Los alumnos han sido asignados exitosamente a la clase</h2>
    
    <div class="barra"></div>
    
    <div class="botones">
      <a href="detalle_clase.php?id=<?php echo $id_clase; ?>" class="btn btn-primary">
        Ver Clase
      </a>
      <a href="agregar_alumnos.php?id_clase=<?php echo $id_clase; ?>" class="btn btn-secondary">
        Agregar Más Alumnos
      </a>
    </div>
  </div>

  <script>
    // Animación del contador
    const contador = document.getElementById('contador');
    const totalAlumnos = document.getElementById('total-alumnos').textContent;
    let count = 0;
    const duration = 2000; // 2 segundos
    const increment = totalAlumnos / (duration / 50);

    const timer = setInterval(() => {
      count += increment;
      if (count >= totalAlumnos) {
        count = totalAlumnos;
        clearInterval(timer);
      }
      contador.textContent = Math.floor(count);
    }, 50);

    // Confetti animation
    function crearConfetti() {
      const confettiContainer = document.getElementById('confetti-container');
      const colores = ['#ffffff', '#c8e6c9', '#a5d6a7', '#81c784', '#66bb6a'];
      
      for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.background = colores[Math.floor(Math.random() * colores.length)];
        confetti.style.animationDelay = Math.random() * 2 + 's';
        confetti.style.width = (Math.random() * 10 + 5) + 'px';
        confetti.style.height = confetti.style.width;
        confettiContainer.appendChild(confetti);
      }
    }

    // Iniciar animaciones
    crearConfetti();

    // Redirección automática después de 8 segundos
    setTimeout(() => {
      window.location.href = "detalle_clase.php?id=<?php echo $id_clase; ?>";
    }, 8000);
  </script>
</body>
</html>