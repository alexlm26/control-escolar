<?php
ob_start();
include "conexion.php";
if (session_status() == PHP_SESSION_NONE) {
    include 'header.php';
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// CONSULTAR CHATS
$queryChats = $conexion->prepare("
    SELECT c.id_chat,
           u.id_usuario,
           u.nombre,
           u.apellidos,
           u.foto, 
           (SELECT mensaje FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) AS ultimo_mensaje,
           (SELECT COUNT(*) FROM mensajes WHERE id_chat=c.id_chat AND id_usuario_envia!=? AND leido=0) AS sin_leer
    FROM chats c
    JOIN usuario u ON (u.id_usuario = IF(c.usuario1 = ?, c.usuario2, c.usuario1))
    WHERE c.usuario1=? OR c.usuario2=?
    ORDER BY (SELECT fecha_envio FROM mensajes WHERE id_chat=c.id_chat ORDER BY fecha_envio DESC LIMIT 1) DESC
");
$queryChats->bind_param("iiii",$id_usuario,$id_usuario,$id_usuario,$id_usuario);
$queryChats->execute();
$resultChats = $queryChats->get_result();
?>

<style>
.content { padding:40px;font-family:"Segoe UI",sans-serif;background:#f8f9fa; }
h2 { color:#2e7d32; }
h3 { margin-top:40px;color:#1b5e20; }
.tarjeta-buzon { display:flex; gap:20px; margin-bottom:30px; }
.tarjeta { background:#e8f5e9;border:1px solid #c8e6c9;border-radius:10px;padding:20px;flex:1; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.chat-container { flex:1; display:flex; flex-direction:column; background:white; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.1); padding:10px; height:400px; }
.chat-mensajes { flex:1; overflow-y:auto; padding:10px; border-bottom:1px solid #ccc; }
.chat-enviar { display:flex; flex-direction:column; margin-top:10px; }
.chat-enviar textarea { margin-bottom:5px; padding:8px; border-radius:5px; border:1px solid #ccc; width:100%; }
.chat-enviar button { background:#2e7d32; color:white; padding:10px; border:none; border-radius:5px; cursor:pointer; }
.mensaje { margin-bottom:8px; padding:6px; border-radius:5px; background:#e0e0e0; }
.mensaje span.fechaMensaje { display:block; font-size:0.7em; color:#555; margin-top:2px; }
.mensaje.mio { background:#81c784; color:white; align-self:flex-end; }
.chat-item {
    background: #f0fdf4;
    border: 1px solid #c8e6c9;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.chat-item:hover {
    background: #c8e6c9;
    transform: scale(1.02);
    cursor: pointer;
}
.chat-item.activo {
    background: #81c784;
    color: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}
.chat-item img { transition: transform 0.2s ease; }
.chat-item:hover img { transform: scale(1.1); }
                /* ======== BANNER DE BIENVENIDA ======== */
.banner-bienvenida {
  background: linear-gradient(135deg, #2e7d32, #43a047);
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
      MENSAJERIA!
    </h1>
    <p class="animar-subtitulo">
      CONTACTA CON TUS COMPAÑEROS Y PROFESORES DE FORMA RAPIDA Y SEGURA!
    </p>
  </div>
</section>

<div class="tarjeta-buzon">
    <!-- TARJETA DE CHATS -->
    <div class="tarjeta">
        <h3>CHATS</h3>
        <div id="listaChats">
            <?php while($chat = $resultChats->fetch_assoc()): ?>
                <div class="chat-item" data-chat="<?php echo $chat['id_chat']; ?>">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="img/usuarios/<?php echo $chat['foto'] ?: 'default.jpg'; ?>" 
                             alt="Foto" 
                             style="width:45px;height:45px;border-radius:50%;object-fit:cover;border:2px solid #2e7d32;">
                        <div style="flex:1;">
                            <b><?php echo $chat['nombre'].' '.$chat['apellidos']; ?></b>
                            <?php if($chat['sin_leer']>0): ?>
                                <span style="color:red;">(<?php echo $chat['sin_leer']; ?> nuevos)</span>
                            <?php endif; ?>
                            <div style="font-size:12px;color:#555;"><?php echo $chat['ultimo_mensaje']; ?></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <button id="nuevoChatBtn" style="margin-top:10px;padding:8px;background:#2e7d32;color:white;border:none;border-radius:5px;">Nuevo Chat</button>
    </div>

    <div class="chat-container" id="chatContainer" style="display:none; position:relative;">
        <button id="cerrarChatBtn" style="position:absolute; top:10px; right:10px; background:#c62828; color:white; border:none; border-radius:5px; padding:5px 10px; cursor:pointer;">Cerrar</button>
        <div class="chat-mensajes" id="chatMensajes"></div>
        <div class="chat-enviar">
            <textarea id="mensaje" placeholder="Escribe tu mensaje"></textarea>
            <button onclick="enviarMensaje()">Enviar</button>
        </div>
    </div>
</div>

<!-- MODAL NUEVO CHAT -->
<div id="modalNuevoChat" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:1000;">
    <div style="background:white;padding:25px;border-radius:12px;min-width:320px;position:relative;box-shadow:0 4px 10px rgba(0,0,0,0.3);">
        <button id="cerrarModalNuevoChat" style="position:absolute;top:10px;right:10px;background:#c62828;color:white;border:none;border-radius:5px;padding:5px 10px;cursor:pointer;">X</button>
        <h3 style="color:#2e7d32;margin-bottom:15px;">💬 INICIAR NUEVO CHAT</h3>
        <form id="formNuevoChat">
            <label for="usuarioDestino">Correo o Clave del Usuario:</label>
            <input type="text" name="usuario_destino" id="usuarioDestino" placeholder="Ejemplo: clave123 o usuario@correo.com" required style="width:100%;padding:8px;margin:10px 0;border:1px solid #ccc;border-radius:5px;">
            <button type="submit" style="background:#2e7d32;color:white;padding:10px;border:none;border-radius:5px;width:100%;cursor:pointer;">CREAR CHAT</button>
        </form>
    </div>
</div>

<script>
let chatActivo = null;

function agregarEventosChats() {
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', function(){
            chatActivo = this.dataset.chat;
            document.getElementById('chatContainer').style.display = 'flex';
            cargarMensajes();
        });
    });
}

function cargarMensajes(){
    if(!chatActivo) return;
    fetch('cargar_mensajes_chat.php?id_chat=' + chatActivo)
    .then(res => res.json())
    .then(data => {
        let chat = document.getElementById('chatMensajes');
        chat.innerHTML = '';
        data.forEach(msg => {
            let div = document.createElement('div');
            div.className = 'mensaje' + (msg.id_usuario_envia == <?php echo $id_usuario; ?> ? ' mio' : '');
            div.innerHTML = `<div class="contenidoMensaje"><strong>${msg.nombre}</strong>: ${msg.mensaje}<span class="fechaMensaje">${msg.fecha_envio}</span></div>`;
            chat.appendChild(div);
        });
        chat.scrollTop = chat.scrollHeight;
    });
}

function cargarListaChats(){
    fetch('cargar_chats.php')
    .then(res => res.json())
    .then(data => {
        let lista = document.getElementById('listaChats');
        lista.innerHTML = '';
        data.forEach(chat => {
            let div = document.createElement('div');
            div.className = 'chat-item';
            div.dataset.chat = chat.id_chat;
            div.innerHTML = `
                <div style="display:flex;align-items:center;gap:10px;">
                    <img src="img/usuarios/${chat.foto ? chat.foto : 'default.jpg'}" 
                         alt="Foto" 
                         style="width:45px;height:45px;border-radius:50%;object-fit:cover;border:2px solid #2e7d32;">
                    <div style="flex:1;">
                        <b>${chat.nombre} ${chat.apellidos}</b>
                        ${chat.sin_leer > 0 ? `<span style="color:red;">(${chat.sin_leer} nuevos)</span>` : ''}
                        <div style="font-size:12px;color:#555;">${chat.ultimo_mensaje ?? ''}</div>
                    </div>
                </div>
            `;
            div.addEventListener('click', function(){
                chatActivo = chat.id_chat;
                document.getElementById('chatContainer').style.display = 'flex';
                cargarMensajes();
            });
            lista.appendChild(div);
        });
    });
}

function enviarMensaje(){
    if(!chatActivo) return alert('Selecciona un chat primero');
    let mensaje = document.getElementById('mensaje').value.trim();
    if(!mensaje) return alert('Escribe un mensaje');
    let formData = new FormData();
    formData.append('id_chat', chatActivo);
    formData.append('mensaje', mensaje);
    fetch('enviar_mensaje_chat.php', { method:'POST', body: formData })
    .then(() => {
        document.getElementById('mensaje').value = '';
        cargarMensajes();
        cargarListaChats();
    });
}

document.getElementById('cerrarChatBtn').addEventListener('click', () => {
    document.getElementById('chatContainer').style.display = 'none';
    chatActivo = null;
});

document.getElementById('nuevoChatBtn').addEventListener('click', () => {
    document.getElementById('modalNuevoChat').style.display = 'flex';
});

document.getElementById('cerrarModalNuevoChat').addEventListener('click', () => {
    document.getElementById('modalNuevoChat').style.display = 'none';
});

document.getElementById('formNuevoChat').addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch('nuevo_chat.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.error) return alert("❌ " + data.error);
        chatActivo = data.id_chat;
        document.getElementById('modalNuevoChat').style.display = 'none';
        document.getElementById('chatContainer').style.display = 'flex';
        cargarMensajes();
        cargarListaChats();
    })
    .catch(() => alert('❌ Error al crear chat'));
});

cargarListaChats();
agregarEventosChats();
setInterval(cargarMensajes, 5000);
setInterval(cargarListaChats, 5000);
</script>
