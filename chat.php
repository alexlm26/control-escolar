<?php
ob_start();
include "conexion.php";
include "header.php";
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
.content { 
    padding:20px;
    font-family:"Segoe UI",sans-serif;
    background:#f8f9fa; 
    min-height:100vh;
}

/* Estilos responsivos */
.tarjeta-buzon { 
    display:flex; 
    gap:20px; 
    margin-bottom:30px;
    flex-wrap: wrap;
}

.tarjeta { 
    flex:1; 
    min-width:300px;
    display:flex; 
    flex-direction:column; 
    background:white; 
    border-radius:10px; 
    box-shadow:0 2px 6px rgba(0,0,0,0.1); 
    padding:15px; 
    height:auto;
    margin-bottom:20px;
}

.chat-container { 
    flex:2; 
    min-width:350px;
    display:flex; 
    flex-direction:column; 
    background:white; 
    border-radius:10px; 
    box-shadow:0 2px 6px rgba(0,0,0,0.1); 
    padding:15px; 
    height:500px;
    margin-bottom:20px;
}

/* Para móviles */
@media (max-width: 768px) {
    .content {
        padding:10px;
    }
    
    .tarjeta-buzon {
        gap:10px;
        margin-bottom:15px;
    }
    
    .tarjeta, .chat-container {
        min-width:100%;
        margin-bottom:15px;
    }
    
    .chat-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        height: 80%;
        z-index: 50;
        display: none;
    }
    
    .chat-container.activo {
        display: flex;
    }
}

.chat-mensajes { 
    flex:1; 
    overflow-y:auto; 
    padding:10px; 
    border-bottom:1px solid #ccc; 
    max-height:400px;
}

.chat-enviar { 
    display:flex; 
    flex-direction:column; 
    margin-top:10px; 
    gap:8px;
}

.chat-enviar textarea { 
    margin-bottom:5px; 
    padding:8px; 
    border-radius:5px; 
    border:1px solid #ccc; 
    width:100%;
    resize:vertical;
    min-height:60px;
}

.chat-enviar button { 
    background:#1565c0; 
    color:white; 
    padding:10px; 
    border:none; 
    border-radius:5px; 
    cursor:pointer;
    transition:background 0.3s;
}

.chat-enviar button:hover {
    background:#0d47a1;
}

.mensaje { 
    margin-bottom:8px; 
    padding:8px 12px; 
    border-radius:8px; 
    background:#e0e0e0; 
    word-wrap:break-word;
    max-width:80%;
}

.mensaje span.fechaMensaje { 
    display:block; 
    font-size:0.7em; 
    color:#555; 
    margin-top:2px; 
}

.mensaje.mio { 
    background:#64b5f6; 
    color:white; 
    margin-left:auto;
    margin-right:0;
}

.chat-item {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    cursor: pointer;
}

.chat-item:hover {
    background: #bbdefb;
    transform: translateY(-2px);
}

.chat-item.activo {
    background: #64b5f6;
    color: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}

.chat-item img {
    transition: transform 0.2s ease;
    width:45px;
    height:45px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid #1565c0;
}

.chat-item:hover img {
    transform: scale(1.05);
}

#listaChats {
    flex: 1;
    overflow-y: auto;
    max-height: 600px;
    padding-right: 5px;
}

/* Scroll personalizado */
#listaChats::-webkit-scrollbar,
.chat-mensajes::-webkit-scrollbar {
    width: 6px;
}

#listaChats::-webkit-scrollbar-thumb,
.chat-mensajes::-webkit-scrollbar-thumb {
    background-color: #64b5f6;
    border-radius: 4px;
}

#listaChats::-webkit-scrollbar-thumb:hover,
.chat-mensajes::-webkit-scrollbar-thumb:hover {
    background-color: #42a5f5;
}

/* Botón móvil para nuevo chat */
.btn-movil {
    display: none;
}

@media (max-width: 768px) {
    .btn-movil {
        display: block;
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #1565c0;
        color: white;
        border: none;
        font-size: 24px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        z-index: 999;
    }
    
    .btn-desktop {
        display: none;
    }
}

h2 { color:#1565c0; font-size:1.5rem; }
h3 { margin-top:20px; color:#0d47a1; font-size:1.3rem; }

/* Modal responsivo */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

@media (max-width: 576px) {
    .modal-dialog {
        margin: 10px;
    }
}

/* Modales en móvil - misma altura que modal de contraseña */
@media (max-width: 768px) {
    /* Modal de chat activo */
    .chat-container.activo {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        height: 80%;
        z-index: 1000;
        display: flex;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: modalEntrada 0.3s ease-out;
        background: white;
    }
    
    /* Modal nuevo chat */
    #modalNuevoChat .modal-dialog {
        margin: 0;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        max-width: 450px;
    }
    
    #modalNuevoChat .modal-content {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: modalEntrada 0.3s ease-out;
    }
    
    /* Fondo oscuro consistente */
    .modal-backdrop,
    .chat-container.activo::before {
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
    }
    
    /* Header del chat como modal de contraseña */
    .chat-container .d-flex.justify-content-between {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .chat-container h4 {
        margin: 0;
        color: #1565c0;
        font-size: 1.4rem;
    }
    
    #cerrarChatBtn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #666;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s;
    }
    
    #cerrarChatBtn:hover {
        color: #1565c0;
    }
}

/* Animación consistente */
@keyframes modalEntrada {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}
</style>

<div class="content">
    <div class="tarjeta-buzon">
        <!-- TARJETA DE CHATS -->
        <div class="tarjeta">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">CHATS</h3>
                <button id="nuevoChatBtn" class="btn btn-primary btn-desktop">Nuevo Chat</button>
            </div>
            <div id="listaChats">
                <?php while($chat = $resultChats->fetch_assoc()): ?>
                    <div class="chat-item" data-chat="<?php echo $chat['id_chat']; ?>">
                        <div class="d-flex align-items-center gap-3">
                            <img src="img/usuarios/<?php echo htmlspecialchars($chat['foto'] ?: 'default.jpg'); ?>" 
                                 alt="Foto">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center">
                                    <b><?php echo htmlspecialchars($chat['nombre'].' '.$chat['apellidos']); ?></b>
                                    <?php if($chat['sin_leer']>0): ?>
                                        <span class="badge bg-danger ms-2"><?php echo $chat['sin_leer']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small text-truncate">
                                    <?php echo htmlspecialchars($chat['ultimo_mensaje'] ?? 'Sin mensajes'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- CONTENEDOR DE CHAT -->
        <div class="chat-container" id="chatContainer">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 id="chatTitulo" class="mb-0">Conversación</h4>
                <button id="cerrarChatBtn" class="btn btn-danger btn-sm">Cerrar</button>
            </div>
            <div class="chat-mensajes" id="chatMensajes"></div>
            <div class="chat-enviar">
                <textarea id="mensaje" placeholder="Escribe tu mensaje..." rows="3"></textarea>
                <button onclick="enviarMensaje()" class="btn btn-primary">Enviar</button>
            </div>
        </div>
    </div>
</div>

<!-- Botón flotante para móviles -->
<button id="nuevoChatBtnMovil" class="btn btn-primary btn-movil">+</button>

<!-- Modal para nuevo chat -->
<div class="modal fade" id="modalNuevoChat" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">💬 INICIAR NUEVO CHAT</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoChat">
                    <div class="mb-3">
                        <label for="usuarioDestino" class="form-label">Correo o Clave del Usuario:</label>
                        <input type="text" name="usuario_destino" id="usuarioDestino" 
                            class="form-control"
                            placeholder="Ejemplo: clave123 o usuario@correo.com"
                            required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">CREAR CHAT</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let chatActivo = null;

// Función para escapar HTML - SEGURIDAD MEJORADA
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function agregarEventosChats() {
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', function(){
            chatActivo = this.dataset.chat;
            document.getElementById('chatContainer').classList.add('activo');
            cargarMensajes();
        });
    });
}

document.getElementById('cerrarChatBtn').addEventListener('click', function(){
    document.getElementById('chatContainer').classList.remove('activo');
    chatActivo = null;
});

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
            
            // USANDO textContent PARA SEGURIDAD - EL HTML SE MUESTRA COMO TEXTO
            const contenidoMensaje = document.createElement('div');
            contenidoMensaje.className = 'contenidoMensaje';
            
            const strong = document.createElement('strong');
            strong.textContent = msg.nombre + ': ';
            
            const textoMensaje = document.createTextNode(msg.mensaje);
            
            const fecha = document.createElement('span');
            fecha.className = 'fechaMensaje';
            fecha.textContent = msg.fecha_envio;
            
            contenidoMensaje.appendChild(strong);
            contenidoMensaje.appendChild(textoMensaje);
            contenidoMensaje.appendChild(fecha);
            
            div.appendChild(contenidoMensaje);
            chat.appendChild(div);
        });
        
        chat.scrollTop = chat.scrollHeight;
    })
    .catch(error => console.error('Error cargando mensajes:', error));
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
            
            const img = document.createElement('img');
            img.src = 'img/usuarios/' + (chat.foto ? escapeHtml(chat.foto) : 'default.jpg');
            img.alt = 'Foto';
            
            const contenido = document.createElement('div');
            contenido.className = 'd-flex align-items-center gap-3';
            
            const info = document.createElement('div');
            info.className = 'flex-grow-1';
            
            const nombreLinea = document.createElement('div');
            nombreLinea.className = 'd-flex align-items-center';
            
            const nombre = document.createElement('b');
            nombre.textContent = escapeHtml(chat.nombre) + ' ' + escapeHtml(chat.apellidos);
            
            nombreLinea.appendChild(nombre);
            
            if(chat.sin_leer > 0) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger ms-2';
                badge.textContent = chat.sin_leer;
                nombreLinea.appendChild(badge);
            }
            
            const ultimoMsg = document.createElement('div');
            ultimoMsg.className = 'text-muted small text-truncate';
            ultimoMsg.textContent = chat.ultimo_mensaje ? escapeHtml(chat.ultimo_mensaje) : 'Sin mensajes';
            
            info.appendChild(nombreLinea);
            info.appendChild(ultimoMsg);
            
            contenido.appendChild(img);
            contenido.appendChild(info);
            div.appendChild(contenido);
            
            div.addEventListener('click', function(){
                chatActivo = chat.id_chat;
                document.getElementById('chatContainer').classList.add('activo');
                cargarMensajes();
            });
            
            lista.appendChild(div);
        });
    })
    .catch(error => console.error('Error cargando chats:', error));
}

function enviarMensaje(){
    if(!chatActivo) {
        alert('Selecciona un chat primero');
        return;
    }
    
    let mensajeInput = document.getElementById('mensaje');
    let mensaje = mensajeInput.value.trim();
    
    if(!mensaje) {
        alert('Escribe un mensaje');
        return;
    }
    
    let formData = new FormData();
    formData.append('id_chat', chatActivo);
    formData.append('mensaje', mensaje);
    
    fetch('enviar_mensaje_chat.php', { 
        method:'POST', 
        body: formData 
    })
    .then(res => res.text())
    .then(() => {
        mensajeInput.value = '';
        cargarMensajes();
        cargarListaChats();
    })
    .catch(error => console.error('Error enviando mensaje:', error));
}

// NUEVO CHAT
document.getElementById('nuevoChatBtn').addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('modalNuevoChat')).show();
});

document.getElementById('nuevoChatBtnMovil').addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('modalNuevoChat')).show();
});

document.getElementById('formNuevoChat').addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    fetch('nuevo_chat.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert("❌ " + data.error);
            return;
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevoChat'));
        modal.hide();
        
        chatActivo = data.id_chat;
        document.getElementById('chatContainer').classList.add('activo');
        cargarMensajes();
        cargarListaChats();
        
        e.target.reset();
    })
    .catch(() => alert('❌ Error al crear chat'));
});

// ACTUALIZACIONES AUTOMÁTICAS
setInterval(() => {
    if(chatActivo) cargarMensajes();
    cargarListaChats();
}, 5000);

// ENVIAR MENSAJE CON ENTER
document.getElementById('mensaje').addEventListener('keypress', function(e) {
    if(e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        enviarMensaje();
    }
});

// Función para cerrar el chat al hacer clic fuera en móvil
function setupCerrarChatClickOutside() {
    document.addEventListener('click', function(e) {
        const chatContainer = document.getElementById('chatContainer');
        const listaChats = document.getElementById('listaChats');
        const nuevoChatBtn = document.getElementById('nuevoChatBtn');
        const nuevoChatBtnMovil = document.getElementById('nuevoChatBtnMovil');
        
        // Solo aplicar en móvil y cuando el chat esté activo
        if (window.innerWidth <= 768 && chatContainer.classList.contains('activo')) {
            // Verificar si el clic fue fuera del chat y fuera de los elementos que abren chats
            if (!chatContainer.contains(e.target) && 
                !listaChats.contains(e.target) && 
                e.target !== nuevoChatBtn && 
                e.target !== nuevoChatBtnMovil &&
                !nuevoChatBtn.contains(e.target) &&
                !nuevoChatBtnMovil.contains(e.target)) {
                
                chatContainer.classList.remove('activo');
                chatActivo = null;
            }
        }
    });
}

// Prevenir que los clics dentro del chat cierren el contenedor
document.getElementById('chatContainer').addEventListener('click', function(e) {
    e.stopPropagation();
});

// CARGA INICIAL
document.addEventListener('DOMContentLoaded', function() {
    cargarListaChats();
    agregarEventosChats();
    setupCerrarChatClickOutside();
    
    // Ocultar chat container inicialmente
    document.getElementById('chatContainer').classList.remove('activo');
});
</script>