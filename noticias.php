<?php
ob_start(); 
include "conexion.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$rol_nombre = 'Desconocido';
if(isset($_SESSION['rol'])){
    switch($_SESSION['rol']){
        case 1: $rol_nombre = 'Alumno'; break;
        case 2: $rol_nombre = 'Profesor'; break;
        case 3: $rol_nombre = 'Coordinador'; break;
    }
}

$id_usuario = $_SESSION['id_usuario'];

$query = $conexion->query("
    SELECT n.*, 
           u.nombre AS nombre_usuario, 
           u.apellidos AS apellidos_usuario, 
           u.rol AS rol_usuario,
           IF(l.id_usuario IS NULL, 0, 1) AS dio_like
    FROM noticias n
    JOIN usuario u ON n.id_usuario = u.id_usuario
    LEFT JOIN likes_usuarios l 
           ON l.id_noticia = n.id_noticia AND l.id_usuario = $id_usuario
    ORDER BY n.publicacion DESC
    LIMIT 12
");

$noticias = $query->fetch_all(MYSQLI_ASSOC);
?>
<style>
:root {
  --color-primario: #1565c0;
  --color-secundario: #1976d2;
  --color-fondo: #f4f6f8;
  --color-texto: #333;
  --color-blanco: #fff;
  --sombra-suave: 0 4px 10px rgba(0,0,0,0.1);
  --sombra-hover: 0 8px 18px rgba(0,0,0,0.15);
  --radio-borde: 14px;
}

body {
  background: var(--color-fondo);
  font-family: "Poppins", "Segoe UI", sans-serif;
  color: var(--color-texto);
}

.content {
  padding: 40px 5%;
  max-width: 1000px;
  margin: auto;
}

h2 {
  color: var(--color-primario);
  margin-bottom: 15px;
  text-align: center;
  font-weight: 600;
  letter-spacing: 1px;
}

.grid-noticias {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

/* TARJETA */
.tarjeta-noticia {
  display: flex;
  align-items: flex-start;
  background: var(--color-blanco);
  border-radius: var(--radio-borde);
  overflow: hidden;
  box-shadow: var(--sombra-suave);
  transition: all 0.3s ease;
  cursor: pointer;
}

.tarjeta-noticia:hover {
  transform: translateY(-5px);
  box-shadow: var(--sombra-hover);
}

/* IMAGEN */
.tarjeta-noticia img {
  width: 35%;
  height: 200px;
  object-fit: cover;
  border-right: 4px solid var(--color-primario);
  transition: transform 0.4s ease;
}

.tarjeta-noticia:hover img {
  transform: scale(1.05);
}

/* INFORMACIÓN */
.tarjeta-info {
  flex: 1;
  padding: 20px 25px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.tarjeta-info h3 {
  font-size: 1.3em;
  font-weight: 700;
  color: var(--color-primario);
  margin-bottom: 10px;
}

.tarjeta-info p.descripcion {
  color: #555;
  font-size: 0.95em;
  line-height: 1.5;
  margin-bottom: 15px;
}

/* CONTADOR */
.tarjeta-info .contador-visitas {
  align-self: flex-end;
  background: rgba(0,0,0,0.6);
  color: #fff;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.85em;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .tarjeta-noticia {
    flex-direction: column;
  }

  .tarjeta-noticia img {
    width: 100%;
    height: 220px;
    border-right: none;
    border-bottom: 4px solid var(--color-primario);
  }

  .tarjeta-info {
    width: 100%;
    padding: 15px;
  }
}
        /* ======== BANNER DE BIENVENIDA ======== */
.banner-bienvenida {
  background: linear-gradient(135deg, #1565c0, #1976d2);
  color: white;
  padding: 60px 20px;
  text-align: center;
  overflow: hidden;
  position: relative;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.banner-bienvenida::after {
  content: "";
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: radial-gradient(circle at center, rgba(255,255,255,0.15), transparent 60%);
  animation: moverLuz 5s linear infinite;
}

@keyframes moverLuz {
  0% { transform: translateX(-100%); }
  50% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}

.banner-texto {
  position: relative;
  z-index: 2;
  max-width: 900px;
  margin: 0 auto;
}

.banner-bienvenida h1 {
  font-size: 2.4em;
  font-weight: 700;
  letter-spacing: 2px;
  margin-bottom: 15px;
  opacity: 0;
  transform: translateY(-30px);
  animation: aparecerTitulo 1s ease-out forwards;
}

.banner-bienvenida p {
  font-size: 1.1em;
  font-weight: 400;
  opacity: 0;
  transform: translateY(30px);
  animation: aparecerSubtitulo 1.5s ease-out forwards;
  animation-delay: 0.5s;
}

@keyframes aparecerTitulo {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes aparecerSubtitulo {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .banner-bienvenida {
    padding: 40px 15px;
  }
  .banner-bienvenida h1 {
    font-size: 1.8em;
  }
  .banner-bienvenida p {
    font-size: 1em;
  }
}

</style>
<!-- BANNER DE BIENVENIDA -->
<section class="banner-bienvenida">
  <div class="banner-texto">
    <h1 class="animar-titulo">
      BIENVENIDO <?php echo strtoupper($rol_nombre .' '. $_SESSION['nombre']); ?> !
    </h1>
    <p class="animar-subtitulo">
      ESTE ES EL PANEL PRINCIPAL DEL CONTROL ESCOLAR, LAS NOTICIAS MÁS RECIENTES DEL PLANTEL SE PUBLICAN AQUÍ!
    </p>
  </div>
</section>

<main class="content">
  <h2 style='  color: var(--color-primario);
  margin-bottom: 15px;
  text-align: center;
  font-weight: 600;
  letter-spacing: 1px;'>NOTICIAS RECIENTES DEL PLANTEL</h2>

  <div class="grid-noticias">
    <?php foreach($noticias as $noticia): ?>
    <div class="tarjeta-noticia" 
     data-id="<?php echo $noticia['id_noticia']; ?>"
     data-titulo="<?php echo htmlspecialchars($noticia['titulo']); ?>" 
     data-imagen="img/articulo/<?php echo $noticia['imagen']; ?>" 
     data-info="<?php echo htmlspecialchars($noticia['info']); ?>"
     data-fecha="<?php echo date('d/m/Y H:i', strtotime($noticia['publicacion'])); ?>"
     data-nombre="<?php echo htmlspecialchars($noticia['nombre_usuario']); ?>"
     data-apellidos="<?php echo htmlspecialchars($noticia['apellidos_usuario']); ?>"
     data-rol="<?php echo $noticia['rol_usuario']; ?>"
     data-visitas="<?php echo $noticia['visitas']; ?>"
     data-likes="<?php echo $noticia['likes']; ?>"
     data-dio-like="<?php echo $noticia['dio_like']; ?>">

  <!-- IMAGEN -->
  <img src="img/articulo/<?php echo $noticia['imagen']; ?>" alt="<?php echo $noticia['titulo']; ?>">

  <!-- INFO -->
  <div class="tarjeta-info">
    <h3><?php echo htmlspecialchars($noticia['titulo']); ?></h3>
    <p class="descripcion">
      <?php 
        // Muestra los primeros 200 caracteres del contenido
        $texto = strip_tags($noticia['info']);
        echo mb_strimwidth($texto, 0, 200, "...");
      ?>
    </p>
    <div class="contador-visitas">
      👁 <?php echo $noticia['visitas']; ?> • ❤️ <?php echo $noticia['likes']; ?>
    </div>
  </div>
</div>



    <?php endforeach; ?>
  </div>
</main>

<!-- MODAL -->
<div id="modalNoticia" class="modal">
  <div class="modal-content">
    <button id="cerrarModal" class="btn-cerrar">X</button>
    <h2 id="modalTitulo" style='  color: var(--color-primario);
  margin-bottom: 15px;
  text-align: center;
  font-weight: 600;
  letter-spacing: 1px;'></h2>
    <img id="modalImagen" src="">
    <p id="modalInfo"></p>
    <div class="acciones">
      <button id="btnLike" class="btn-like">❤️ ME GUSTA (<span id="likeCount"></span>)</button>
    </div>
    <p id="modalFooter"></p>
  </div>
</div>

<style>
.content { padding:40px;font-family:"Segoe UI",sans-serif;background:#f8f9fa; }
h2 { color:#2e7d32; margin-bottom:20px; text-align:center; }
p { text-align:center; margin-bottom:30px; }
.overlay { position:absolute; bottom:0; left:0; width:100%; background:rgba(46,125,50,0.8); color:white; padding:15px; transform:translateY(100%); transition:transform 0.3s; }
.overlay h3 { margin:0; font-size:1.1em; font-weight:bold; }.tarjeta-noticia:not(:hover) .contador-visitas { display:block; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1000; }
.modal-content { background:white; border-radius:12px; padding:20px; max-width:700px; width:90%; max-height:90%; overflow:auto; position:relative; }
.btn-cerrar { position:absolute; top:10px; right:10px; background:#c62828; color:white; border:none; border-radius:5px; padding:5px 10px; cursor:pointer; }
.btn-like { background:#2e7d32; color:white; border:none; border-radius:8px; padding:8px 15px; cursor:pointer; }
.btn-like:hover { background:#43a047; }
.modal-content img { width:100%; border-radius:12px; margin-bottom:15px; }
.modal-content p { text-align:left; margin-bottom:15px; }
.acciones { text-align:center; margin:15px 0; }
</style>

<script>
let idActual = null;
let idUsuario = <?php echo json_encode($id_usuario); ?>;

document.querySelectorAll('.tarjeta-noticia').forEach(card => {
  card.addEventListener('click', async () => {
    const id = card.dataset.id;
    idActual = id;

    fetch('actualizar_visitas.php', { method: 'POST', body: new URLSearchParams({ id }) });

    try {
      const resp = await fetch(`get_noticia_status.php?id=${id}&id_usuario=${idUsuario}`);
      const json = await resp.json();

      const btn = document.getElementById('btnLike');
      document.getElementById('likeCount').textContent = json.likes;

      if (json.dio_like == 1) {
        btn.disabled = true;
        btn.style.background = '#888';
        btn.textContent = `❤️ YA TE GUSTA (${json.likes})`;
      } else {
        btn.disabled = false;
        btn.style.background = '#2e7d32';
        btn.textContent = `❤️ ME GUSTA (${json.likes})`;
      }
    } catch (e) {
      console.error('Error al obtener estado de noticia', e);
    }

    document.getElementById('modalTitulo').textContent = card.dataset.titulo;
    document.getElementById('modalImagen').src = card.dataset.imagen;
    document.getElementById('modalInfo').textContent = card.dataset.info;
    document.getElementById('modalFooter').textContent =
      `Publicado el ${card.dataset.fecha} por ${card.dataset.nombre} ${card.dataset.apellidos} (Rol: ${card.dataset.rol})`;

    document.getElementById('modalNoticia').style.display = 'flex';
        // ACTUALIZAR BOTÓN SEGÚN LIKE INDIVIDUAL
    const btn = document.getElementById('btnLike');
    if (card.dataset.dioLike == '1') {
      btn.disabled = true;
      btn.style.background = '#888';
      btn.textContent = '❤️ YA TE GUSTA';
    } else {
      btn.disabled = false;
      btn.style.background = '#2e7d32';
      btn.textContent = `❤️ ME GUSTA (${card.dataset.likes})`;
    }

  });
});


document.getElementById('cerrarModal').addEventListener('click', () => {
  document.getElementById('modalNoticia').style.display = 'none';
});

// LIKE
document.getElementById('btnLike').addEventListener('click', () => {
  if (!idActual) return;
  fetch('actualizar_likes.php', {
    method: 'POST',
    body: new URLSearchParams({ id: idActual, id_usuario: idUsuario })
  })
  .then(res => res.text())
  .then(resp => {
    const btn = document.getElementById('btnLike');
    const card = document.querySelector(`.tarjeta-noticia[data-id='${idActual}']`);
    let likeCount = document.getElementById('likeCount');

    if (resp.trim() === 'liked') {
      let nuevo = parseInt(likeCount.textContent) + 1;
      likeCount.textContent = nuevo;
      btn.disabled = true;
      btn.style.background = '#888';
      btn.textContent = '❤️ YA TE GUSTA';
      if (card) {
        card.dataset.dioLike = '1';
        card.dataset.likes = nuevo;
      }
    } else if (resp.trim() === 'ya_liked') {
      btn.disabled = true;
      btn.style.background = '#888';
      btn.textContent = '❤️ YA TE GUSTA';
      if (card) card.dataset.dioLike = '1';
    } else if (resp.trim() === 'sin_sesion') {
      alert('DEBES INICIAR SESIÓN PARA DAR LIKE 💬');
    }
  })
  .catch(err => console.error('Error al actualizar like', err));
});
</script>
<?php include 'footer.php' ?>