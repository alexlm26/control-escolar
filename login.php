<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="logo.png">
  <title>LOGIN - CONTROL ESCOLAR</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ======== VARIABLES Y RESET ======== */
    :root {
      --color-primario: #1565c0;
      --color-secundario: #1976d2;
      --color-terciario: #2196f3;
      --color-texto: #ffffff;
      --color-sombra: rgba(0, 0, 0, 0.3);
      --border-radius: 20px;
      --transition: all 0.3s ease;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    html {
      font-size: 16px;
    }
    
    /* ======== FONDO CON EFECTO DE PANEO ======== */
    body {
      min-height: 100vh;
      overflow-x: hidden;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1565c0, #1976d2);
      padding: 20px;
    }
    
    .fondo-paneo {
      position: fixed;
      top: -10%;
      left: -10%;
      width: 120%;
      height: 120%;
      background-image: url('SnapInsta.to_539169901_17905804554219160_1844483839502990770_n.jpg');
      background-size: cover;
      background-position: center;
      animation: pan 30s infinite linear;
      filter: brightness(0.7);
      z-index: -2;
    }
    
    @keyframes pan {
      0% { transform: translate(0, 0); }
      25% { transform: translate(-2%, -1%); }
      50% { transform: translate(-4%, 0); }
      75% { transform: translate(-2%, 1%); }
      100% { transform: translate(0, 0); }
    }
    
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(21, 101, 192, 0.7);
      z-index: -1;
    }
    
    /* ======== LOGO TECNM ======== */
    .logo-tecnm {
      position: fixed;
      top: 20px;
      left: 20px;
      width: 60px;
      height: 60px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 15px var(--color-sombra);
      z-index: 10;
    }
    
    .logo-tecnm img {
      width: 80%;
      height: 80%;
      object-fit: contain;
    }
    
    /* ======== CONTENEDOR PRINCIPAL ======== */
    .contenedor-login {
      display: flex;
      width: 100%;
      max-width: 1100px;
      min-height: 600px;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(15px);
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: 0 15px 35px var(--color-sombra);
      animation: aparecer 1.2s ease-out forwards;
    }
    
    @keyframes aparecer {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* ======== SECCIÓN IZQUIERDA ======== */
    .seccion-izquierda {
      flex: 1;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      color: var(--color-texto);
    }
    
    .texto-institucional {
      margin-bottom: 40px;
    }
    
    .texto-institucional h1 {
      font-size: 2.8rem;
      margin-bottom: 10px;
      font-weight: 700;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      line-height: 1.2;
    }
    
    .texto-institucional h2 {
      font-size: 1.5rem;
      font-weight: 400;
      margin-bottom: 5px;
      opacity: 0.9;
    }
    
    .texto-institucional p {
      font-size: 1.1rem;
      opacity: 0.8;
      max-width: 500px;
      line-height: 1.6;
    }
    
    /* ======== FORMULARIO ======== */
    .formulario-login {
      width: 100%;
      max-width: 400px;
    }
    
    .grupo-input {
      position: relative;
      margin-bottom: 25px;
    }
    
    .grupo-input input {
      width: 100%;
      padding: 15px 20px;
      border: none;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.85);
      font-size: 1rem;
      outline: none;
      transition: var(--transition);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .grupo-input input:focus {
      background: white;
      box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }
    
    .grupo-input i {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--color-primario);
    }
    
    .btn-ingresar {
      width: 100%;
      padding: 16px;
      background: var(--color-primario);
      color: white;
      border: none;
      border-radius: 50px;
      font-size: 1.2rem;
      font-weight: 600;
      letter-spacing: 1px;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      margin-top: 10px;
    }
    
    .btn-ingresar:hover {
      background: var(--color-secundario);
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
    
    .btn-ingresar:active {
      transform: translateY(0);
    }
    
    /* ======== SECCIÓN DERECHA ======== */
    .seccion-derecha {
      flex: 1;
      background: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    
    .logo-itsur {
      width: 200px;
      height: 200px;
      margin-bottom: 30px;
      animation: flotar 4s ease-in-out infinite;
    }
    
    @keyframes flotar {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    .logo-itsur img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    
    .bienvenida {
      text-align: center;
      color: var(--color-primario);
    }
    
    .bienvenida h3 {
      font-size: 2rem;
      margin-bottom: 10px;
    }
    
    .bienvenida p {
      font-size: 1.1rem;
      opacity: 0.8;
    }
    
    /* ======== ESTILOS PARA ERRORES EN LOGIN ======== */
    .grupo-input.error input {
      background: rgba(255, 230, 230, 0.9);
      border: 2px solid #dc3545;
      box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2), 0 4px 15px rgba(0, 0, 0, 0.1);
      animation: pulse-error 0.5s ease-in-out;
    }
    
    .grupo-input.error i {
      color: #dc3545;
      animation: icon-shake 0.5s ease-in-out;
    }
    
    @keyframes pulse-error {
      0% { transform: scale(1); }
      50% { transform: scale(1.02); }
      100% { transform: scale(1); }
    }
    
    @keyframes icon-shake {
      0%, 100% { transform: translateY(-50%); }
      25% { transform: translateY(-50%) rotate(10deg); }
      75% { transform: translateY(-50%) rotate(-10deg); }
    }
    
    .mensaje-error {
      background: linear-gradient(135deg, #dc3545, #c82333);
      color: white;
      padding: 15px;
      border-radius: 12px;
      margin-bottom: 25px;
      text-align: center;
      animation: slide-in-error 0.5s ease-out;
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
      border-left: 5px solid #ff6b7a;
    }
    
    @keyframes slide-in-error {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .mensaje-error i {
      margin-right: 8px;
    }
    
    /* ======== SECCIÓN NOSOTROS DESLIZABLE ======== */
    .nosotros-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: var(--color-primario);
      color: white;
      border: none;
      border-radius: 50px;
      padding: 12px 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      z-index: 100;
      transition: var(--transition);
    }
    
    .nosotros-toggle:hover {
      background: var(--color-secundario);
      transform: translateY(-3px);
    }
    
    .nosotros-toggle i {
      transition: transform 0.3s ease;
    }
    
    .nosotros-toggle.active i {
      transform: rotate(180deg);
    }
    
    .seccion-nosotros {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 0;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      overflow: hidden;
      transition: height 0.5s ease;
      z-index: 90;
      box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.2);
    }
    
    .seccion-nosotros.abierto {
      height: 70vh;
    }
    
    .contenido-nosotros {
      padding: 30px;
      max-width: 1200px;
      margin: 0 auto;
      height: 100%;
      overflow-y: auto;
    }
    
    .titulo-nosotros {
      color: var(--color-primario);
      text-align: center;
      margin-bottom: 25px;
      font-size: 2.2rem;
    }
    
    .grid-nosotros {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
    }
    
    .tarjeta-nosotros {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: var(--transition);
    }
    
    .tarjeta-nosotros:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    
    .tarjeta-nosotros h3 {
      color: var(--color-primario);
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .tarjeta-nosotros p {
      color: #555;
      line-height: 1.6;
    }
    
    .cerrar-nosotros {
      position: absolute;
      top: 15px;
      right: 20px;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--color-primario);
      cursor: pointer;
      transition: var(--transition);
    }
    
    .cerrar-nosotros:hover {
      transform: scale(1.2);
    }
    
    /* ======== RESPONSIVE - TABLET ======== */
    @media (max-width: 1024px) {
      .contenedor-login {
        max-width: 900px;
        min-height: 550px;
      }
      
      .seccion-izquierda, .seccion-derecha {
        padding: 30px;
      }
      
      .texto-institucional h1 {
        font-size: 2.4rem;
      }
      
      .logo-itsur {
        width: 180px;
        height: 180px;
      }
    }
    
    /* ======== RESPONSIVE - TABLET PEQUEÑA ======== */
    @media (max-width: 768px) {
      body {
        padding: 15px;
      }
      
      .contenedor-login {
        flex-direction: column;
        min-height: auto;
        max-width: 600px;
      }
      
      .seccion-izquierda {
        order: 2;
        padding: 30px 25px;
      }
      
      .seccion-derecha {
        order: 1;
        padding: 30px 25px;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
      }
      
      .logo-itsur {
        width: 120px;
        height: 120px;
        margin-bottom: 20px;
      }
      
      .texto-institucional {
        margin-bottom: 30px;
        text-align: center;
      }
      
      .texto-institucional h1 {
        font-size: 2.2rem;
      }
      
      .texto-institucional h2 {
        font-size: 1.3rem;
      }
      
      .bienvenida h3 {
        font-size: 1.8rem;
      }
      
      .formulario-login {
        margin: 0 auto;
      }
      
      .logo-tecnm {
        width: 50px;
        height: 50px;
        top: 15px;
        left: 15px;
      }
      
      .seccion-nosotros.abierto {
        height: 80vh;
      }
      
      .grid-nosotros {
        grid-template-columns: 1fr;
      }
    }
    
    /* ======== RESPONSIVE - MÓVIL ======== */
    @media (max-width: 480px) {
      body {
        padding: 10px;
      }
      
      .contenedor-login {
        border-radius: 15px;
      }
      
      .seccion-izquierda, .seccion-derecha {
        padding: 25px 20px;
      }
      
      .texto-institucional h1 {
        font-size: 1.8rem;
      }
      
      .texto-institucional h2 {
        font-size: 1.1rem;
      }
      
      .texto-institucional p {
        font-size: 1rem;
      }
      
      .logo-itsur {
        width: 100px;
        height: 100px;
        margin-bottom: 15px;
      }
      
      .bienvenida h3 {
        font-size: 1.5rem;
      }
      
      .bienvenida p {
        font-size: 1rem;
      }
      
      .grupo-input input {
        padding: 12px 16px;
        font-size: 0.9rem;
      }
      
      .btn-ingresar {
        padding: 14px;
        font-size: 1.1rem;
      }
      
      .logo-tecnm {
        width: 45px;
        height: 45px;
        top: 10px;
        left: 10px;
      }
      
      .nosotros-toggle {
        bottom: 15px;
        right: 15px;
        padding: 10px 16px;
        font-size: 0.9rem;
      }
      
      .seccion-nosotros.abierto {
        height: 85vh;
      }
      
      .contenido-nosotros {
        padding: 20px;
      }
      
      .titulo-nosotros {
        font-size: 1.8rem;
      }
    }
    
    /* ======== RESPONSIVE - MÓVIL PEQUEÑO ======== */
    @media (max-width: 360px) {
      .seccion-izquierda, .seccion-derecha {
        padding: 20px 15px;
      }
      
      .texto-institucional h1 {
        font-size: 1.6rem;
      }
      
      .texto-institucional h2 {
        font-size: 1rem;
      }
      
      .grupo-input {
        margin-bottom: 20px;
      }
      
      .grupo-input input {
        padding: 10px 14px;
      }
      
      .btn-ingresar {
        padding: 12px;
        font-size: 1rem;
      }
      
      .logo-itsur {
        width: 80px;
        height: 80px;
      }
    }
    
    /* ======== ORIENTACIÓN HORIZONTAL EN MÓVIL ======== */
    @media (max-height: 600px) and (orientation: landscape) {
      body {
        padding: 10px;
        align-items: flex-start;
      }
      
      .contenedor-login {
        min-height: auto;
        margin-top: 20px;
        margin-bottom: 20px;
      }
      
      .seccion-izquierda, .seccion-derecha {
        padding: 20px;
      }
      
      .texto-institucional {
        margin-bottom: 20px;
      }
      
      .logo-itsur {
        width: 80px;
        height: 80px;
        margin-bottom: 15px;
      }
      
      .seccion-nosotros.abierto {
        height: 90vh;
      }
    }
  </style>
</head>

<body>
  <!-- Fondo con efecto de paneo -->
  <div class="fondo-paneo"></div>
  <div class="overlay"></div>
  
  <!-- Logo TECNM en la esquina superior izquierda -->
  <div class="logo-tecnm">
    <img src="https://www.tecnm.mx/images/tecnm_virtual/tecnm.png" alt="TECNM">
  </div>
  
  <!-- Contenedor principal del login -->
  <div class="contenedor-login">
    <!-- Sección izquierda con formulario -->
    <div class="seccion-izquierda">
      <div class="texto-institucional">
        <h1>SICENET</h1>
        <h2>CONTROL ESCOLAR</h2>
        <p>Plataforma institucional de control escolar</p>
      </div>
      
      <?php if (isset($_SESSION['login_error'])): ?>
        <div class="mensaje-error">
          <i class="fas fa-exclamation-triangle"></i>
          <?php 
            echo $_SESSION['login_error']; 
            unset($_SESSION['login_error']);
          ?>
        </div>
      <?php endif; ?>
      
      <form class="formulario-login" action="validar_login.php" method="POST">
        <div class="grupo-input <?php echo isset($_SESSION['login_error']) ? 'error' : ''; ?>">
          <input type="text" name="usuario" placeholder="Clave o correo" required 
                 value="<?php echo isset($_SESSION['usuario_intentado']) ? htmlspecialchars($_SESSION['usuario_intentado']) : ''; ?>">
          <i class="fas fa-user"></i>
        </div>
        
        <div class="grupo-input <?php echo isset($_SESSION['login_error']) ? 'error' : ''; ?>">
          <input type="password" name="contraseña" placeholder="Contraseña" required>
          <i class="fas fa-lock"></i>
        </div>
        
        <button type="submit" class="btn-ingresar">INGRESAR</button>
      </form>
    </div>
    
    <!-- Sección derecha con logo ITSUR -->
    <div class="seccion-derecha">
      <div class="logo-itsur">
        <img src="logo1.png" alt="ITSUR">
      </div>
      
      <div class="bienvenida">
        <h3>Bienvenido</h3>
        <p>Instituto Tecnológico Superior del Sur de Guanajuato</p>
      </div>
    </div>
  </div>
  
  <!-- Botón para abrir sección Nosotros -->
  <button class="nosotros-toggle" id="nosotrosToggle">
    <i class="fas fa-chevron-up"></i> Nosotros
  </button>
  
  <!-- Sección Nosotros deslizante -->
  <div class="seccion-nosotros" id="seccionNosotros">
    <button class="cerrar-nosotros" id="cerrarNosotros">
      <i class="fas fa-times"></i>
    </button>
    <div class="contenido-nosotros">
      <h2 class="titulo-nosotros">Conoce más sobre nosotros</h2>
      <div class="grid-nosotros">
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-university"></i> Nuestra Institución</h3>
          <p>El Instituto Tecnológico Superior del Sur de Guanajuato es una institución de educación superior comprometida con la formación de profesionales de excelencia, capaces de contribuir al desarrollo regional y nacional.</p>
        </div>
        
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-graduation-cap"></i> Misión</h3>
          <p>Formar profesionales competentes, con valores éticos y responsabilidad social, mediante programas educativos de calidad que respondan a las necesidades del entorno global.</p>
        </div>
        
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-eye"></i> Visión</h3>
          <p>Ser reconocidos como una institución de educación superior de excelencia, líder en la generación y aplicación del conocimiento, con impacto positivo en el desarrollo sustentable de la región.</p>
        </div>
        
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-star"></i> Valores</h3>
          <p>Compromiso, Excelencia, Integridad, Responsabilidad Social, Innovación y Trabajo en equipo son los valores que guían nuestra labor educativa diaria.</p>
        </div>
        
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-book"></i> Programas Educativos</h3>
          <p>Ofrecemos una amplia gama de programas educativos en ingenierías, licenciaturas y posgrados, diseñados para formar profesionales altamente competitivos.</p>
        </div>
        
        <div class="tarjeta-nosotros">
          <h3><i class="fas fa-users"></i> Comunidad</h3>
          <p>Formamos parte de una comunidad educativa integrada por estudiantes, docentes, personal administrativo y egresados, todos comprometidos con el crecimiento institucional.</p>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Efecto adicional para limpiar el error al empezar a escribir
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.grupo-input input');
      const grupos = document.querySelectorAll('.grupo-input');
      
      inputs.forEach((input, index) => {
        input.addEventListener('input', function() {
          // Remover clase de error cuando el usuario empiece a escribir
          if (grupos[index].classList.contains('error')) {
            grupos[index].classList.remove('error');
          }
        });
        
        // Efecto focus mejorado para inputs con error
        input.addEventListener('focus', function() {
          grupos[index].classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
          grupos[index].classList.remove('focused');
        });
      });
      
      // Limpiar session storage al cargar la página
      if (performance.navigation.type === 1) {
        // Página recargada
        fetch('limpiar_session.php').catch(err => console.log('Error al limpiar session'));
      }
      
      // Control de la sección Nosotros
      const nosotrosToggle = document.getElementById('nosotrosToggle');
      const seccionNosotros = document.getElementById('seccionNosotros');
      const cerrarNosotros = document.getElementById('cerrarNosotros');
      
      nosotrosToggle.addEventListener('click', function() {
        seccionNosotros.classList.toggle('abierto');
        nosotrosToggle.classList.toggle('active');
      });
      
      cerrarNosotros.addEventListener('click', function() {
        seccionNosotros.classList.remove('abierto');
        nosotrosToggle.classList.remove('active');
      });
      
      // Cerrar sección Nosotros al hacer clic fuera
      document.addEventListener('click', function(event) {
        if (!seccionNosotros.contains(event.target) && 
            !nosotrosToggle.contains(event.target) && 
            seccionNosotros.classList.contains('abierto')) {
          seccionNosotros.classList.remove('abierto');
          nosotrosToggle.classList.remove('active');
        }
      });
    });
  </script>
</body>
</html>