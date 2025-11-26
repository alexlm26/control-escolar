let chatActivo = null;

// Abrir chat
document.querySelectorAll('.chat-item').forEach(item => {
    item.addEventListener('click', function(){
        chatActivo = this.dataset.chat;
        document.getElementById('chatContainer').style.display = 'flex';
        cargarMensajes();
    });
});

// Cerrar chat
document.getElementById('cerrarChatBtn').addEventListener('click', function(){
    document.getElementById('chatContainer').style.display = 'none';
    chatActivo = null;
    document.getElementById('chatMensajes').innerHTML = '';
});

// Cargar mensajes
function cargarMensajes(){
    if(!chatActivo) return;
    fetch('cargar_mensajes_chat.php?id_chat=' + chatActivo)
    .then(res => res.json())
    .then(data => {
        let chat = document.getElementById('chatMensajes');
        chat.innerHTML = '';
        data.forEach(msg => {
            let div = document.createElement('div');
            div.className = 'mensaje' + (msg.id_usuario_envia == <?php echo $_SESSION['id_usuario']; ?> ? ' mio' : '');
            div.innerHTML = msg.nombre + ': ' + msg.mensaje + ' <span class="fechaMensaje">' + msg.fecha_envio + '</span>';
            chat.appendChild(div);
        });
        chat.scrollTop = chat.scrollHeight;
    });
}

// Enviar mensaje
function enviarMensaje(){
    if(!chatActivo) return alert('Selecciona un chat primero');
    let mensaje = document.getElementById('mensaje').value;
    if(!mensaje) return alert('Escribe un mensaje');
    let formData = new FormData();
    formData.append('id_chat', chatActivo);
    formData.append('mensaje', mensaje);

    fetch('enviar_mensaje_chat.php', { method:'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        document.getElementById('mensaje').value = '';
        cargarMensajes();
    });
}

// Actualizar cada 5s
setInterval(cargarMensajes, 5000);

// Abrir modal Nuevo Chat
document.getElementById('nuevoChatBtn').addEventListener('click', function(){
    document.getElementById('modalNuevoChat').style.display = 'flex';
});

// Cerrar modal
document.getElementById('cerrarModalNuevoChat').addEventListener('click', function(){
    document.getElementById('modalNuevoChat').style.display = 'none';
});

// Crear chat
document.getElementById('formNuevoChat').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch('nuevo_chat.php', { method:'POST', body: formData })
    .then(res => res.text())
    .then(chatId => {
        document.getElementById('modalNuevoChat').style.display = 'none';
        chatActivo = chatId;
        cargarMensajes();
        location.reload(); // Refresca lista de chats
    });
});
