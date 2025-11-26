<?php
ob_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol_nombre = 'Desconocido';
$usuario_estado = 'Desconocido';

// DETERMINAR ROL
if(isset($_SESSION['rol'])){
    switch($_SESSION['rol']){
        case 1:
            $rol_nombre = 'Alumno';
            break;
        case 2:
            $rol_nombre = 'Profesor';
            break;
        case 3:
            $rol_nombre = 'Coordinador';
            break;
    }
}

// PROCESAR CAMBIO DE CONTRASEÑA
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'];
    $nueva_password = $_POST['nueva_password'];
    $confirmar_password = $_POST['confirmar_password'];
    
    // Validar que las contraseñas coincidan
    if ($nueva_password !== $confirmar_password) {
        $mensaje = 'Las contraseñas nuevas no coinciden';
        $tipo_mensaje = 'error';
    } else {
        // Obtener la contraseña actual del usuario
        $stmt = $conexion->prepare("SELECT contraseña FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        
       if ($usuario) {
    // Verificar contraseña actual (con bcrypt)
    if (password_verify($password_actual, $usuario['contraseña'])) {
        // Actualizar contraseña (con bcrypt)
        $nueva_password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);
        $stmt = $conexion->prepare("UPDATE usuario SET contraseña = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $nueva_password_hash, $id_usuario);
        
        if ($stmt->execute()) {
            $mensaje = 'Contraseña cambiada exitosamente';
            $tipo_mensaje = 'exito';
        } else {
            $mensaje = 'Error al cambiar la contraseña';
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'La contraseña actual es incorrecta';
        $tipo_mensaje = 'error';
    }
} else {
            $mensaje = 'Usuario no encontrado';
            $tipo_mensaje = 'error';
        }
    }
}

// CONSULTAR INFO SEGÚN ROL
$usuarioInfo = [];
$semaforos = ['regularidad' => '', 'asistencia' => '', 'progreso' => ''];

if($rol_nombre == 'Alumno'){
    // Información básica del alumno
    $stmt = $conexion->prepare("
        SELECT u.nombre, u.apellidos, u.clave, u.foto, a.semestre, a.especialidad, 
               c.nombre AS carrera, a.estado, a.id_alumno
        FROM alumno a
        JOIN usuario u ON a.id_usuario = u.id_usuario
        JOIN carrera c ON u.id_carrera = c.id_carrera
        WHERE u.id_usuario = ?
    ");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuarioInfo = $result->fetch_assoc();
    
    // CALCULAR SEMÁFOROS SOLO PARA ALUMNOS
    if ($usuarioInfo) {
        $id_alumno = $usuarioInfo['id_alumno'];
        $semestre_actual = $usuarioInfo['semestre'];
        
        // SEMÁFORO 1: Regularidad académica
        $stmt = $conexion->prepare("
            SELECT COUNT(*) as materias_reprobadas
            FROM materia_cursada mc
            JOIN materia m ON mc.id_materia = m.id_materia
            WHERE mc.id_alumno = ? 
            AND mc.aprobado = 0
            AND mc.id_materia IN (
                SELECT id_materia FROM materia 
                WHERE id_carrera = (SELECT id_carrera FROM usuario WHERE id_usuario = ?)
            )
        ");
        $stmt->bind_param("ii", $id_alumno, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $materias_reprobadas = $result->fetch_assoc()['materias_reprobadas'] ?? 0;
        
        if ($materias_reprobadas == 0) {
            $semaforos['regularidad'] = 'verde';
        } elseif ($materias_reprobadas <= 4) {
            $semaforos['regularidad'] = 'amarillo';
        } else {
            $semaforos['regularidad'] = 'rojo';
        }
        
        // SEMÁFORO 2: Asistencia (últimos 30 días)
        $stmt = $conexion->prepare("
            SELECT COUNT(*) as faltas
            FROM asistencia 
            WHERE id_alumno = ? 
            AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND estado_asistencia IN ('ausente', 'tardanza')
        ");
        $stmt->bind_param("i", $id_alumno);
        $stmt->execute();
        $result = $stmt->get_result();
        $faltas_30_dias = $result->fetch_assoc()['faltas'] ?? 0;
        
        if ($faltas_30_dias <= 3) {
            $semaforos['asistencia'] = 'verde';
        } elseif ($faltas_30_dias <= 7) {
            $semaforos['asistencia'] = 'amarillo';
        } else {
            $semaforos['asistencia'] = 'rojo';
        }
        
        // SEMÁFORO 3: Progreso en la carrera
        $max_semestres = 12;
        $materias_por_semestre = 8;
        $materias_totales = $max_semestres * $materias_por_semestre;
        
        // Calcular materias aprobadas
        $stmt = $conexion->prepare("
            SELECT COUNT(*) as materias_aprobadas
            FROM materia_cursada 
            WHERE id_alumno = ? AND aprobado = 1
        ");
        $stmt->bind_param("i", $id_alumno);
        $stmt->execute();
        $result = $stmt->get_result();
        $materias_aprobadas = $result->fetch_assoc()['materias_aprobadas'] ?? 0;
        
        // Calcular progreso esperado vs real
        $semestres_completados = $semestre_actual - 1;
        $materias_esperadas = $semestres_completados * $materias_por_semestre;
        
        if ($materias_esperadas > 0) {
            $progreso_real = ($materias_aprobadas / $materias_esperadas) * 100;
        } else {
            $progreso_real = 100;
        }
        
        // Considerar faltas como factor negativo
        $factor_faltas = max(0, 100 - ($faltas_30_dias * 5)); // Cada falta reduce 5%
        $puntaje_final = ($progreso_real + $factor_faltas) / 2;
        
        if ($puntaje_final >= 80) {
            $semaforos['progreso'] = 'verde';
        } elseif ($puntaje_final >= 60) {
            $semaforos['progreso'] = 'amarillo';
        } else {
            $semaforos['progreso'] = 'rojo';
        }
        
    }
    
} elseif($rol_nombre == 'Profesor'){
    $stmt = $conexion->prepare("
        SELECT u.nombre, u.apellidos, u.clave, u.foto, p.estado, 
               CONCAT(co.nombre,' ',co.apellidos) AS coordinador
        FROM profesor p
        JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN usuario co ON p.id_coordinador = co.id_usuario
        WHERE u.id_usuario = ?
    ");
} elseif($rol_nombre == 'Coordinador'){
    $stmt = $conexion->prepare("
        SELECT u.nombre, u.apellidos, u.clave, u.foto, co.estado, c.nombre AS carrera
        FROM coordinador co
        JOIN usuario u ON co.id_usuario = u.id_usuario
        LEFT JOIN carrera c ON co.id_carrera = c.id_carrera
        WHERE u.id_usuario = ?
    ");
}

if($rol_nombre != 'Alumno') {
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuarioInfo = $result->fetch_assoc();
}

if(isset($usuarioInfo['estado']))
{
    switch($usuarioInfo['estado'])
    {
        case 1: 
            $usuarioInfo['estado'] = 'ACTIVO';
            break;
        case 2: 
            $usuarioInfo['estado'] = 'ADEUDO';
            break;
        case 3: 
            $usuarioInfo['estado'] = 'BAJA TEMPORAL';
            break;
        case 4: 
            $usuarioInfo['estado'] = 'BAJA PERMANENTE';
            break;
    }
}

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

/* Estilos para los semáforos */
.semaforos-container {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
    justify-content: center;
}

.semaforo {
    flex: 1;
    min-width: 120px;
    text-align: center;
    padding: 15px;
    border-radius: 10px;
    background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.semaforo:hover {
    transform: translateY(-5px);
}

.semaforo.verde {
    border-left: 5px solid #28a745;
    background: linear-gradient(135deg, #f8fff9, #e8f5e8);
}

.semaforo.amarillo {
    border-left: 5px solid #ffc107;
    background: linear-gradient(135deg, #fffbf0, #fff3cd);
}

.semaforo.rojo {
    border-left: 5px solid #dc3545;
    background: linear-gradient(135deg, #fff5f5, #ffe6e6);
}

.semaforo-icono {
    font-size: 2rem;
    margin-bottom: 10px;
}

.semaforo-titulo {
    font-weight: bold;
    font-size: 0.9rem;
    margin-bottom: 5px;
    color: #333;
}

.semaforo-estado {
    font-size: 0.8rem;
    color: #666;
}

.semaforo-descripcion {
    font-size: 0.75rem;
    color: #888;
    margin-top: 5px;
}

/* Botón para abrir modal de contraseña */
.btn-cambiar-password {
    background: #1565c0;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 10px;
    cursor: pointer;
}

.btn-cambiar-password:hover {
    background: #0d47a1;
    transform: translateY(-2px);
}

/* Modal de contraseña */
.modal-password {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    backdrop-filter: blur(5px);
}

.modal-password-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    width: 90%;
    max-width: 450px;
    padding: 25px;
    animation: modalEntrada 0.3s ease-out;
}

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

.modal-password-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.modal-password-header h3 {
    margin: 0;
    color: #1565c0;
    font-size: 1.4rem;
}

.cerrar-modal-password {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 5px;
    transition: color 0.3s;
}

.cerrar-modal-password:hover {
    color: #1565c0;
}

/* Estilos para el formulario de contraseña */
.form-password .form-group {
    margin-bottom: 20px;
}

.form-password label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.form-password .form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 12px;
    width: 100%;
    transition: all 0.3s;
    font-size: 1rem;
}

.form-password .form-control:focus {
    border-color: #1565c0;
    box-shadow: 0 0 0 0.2rem rgba(21, 101, 192, 0.25);
    outline: none;
}

.btn-password {
    background: #1565c0;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    width: 100%;
    cursor: pointer;
    font-size: 1rem;
}

.btn-password:hover {
    background: #0d47a1;
    transform: translateY(-2px);
}

.mensaje-exito {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
}

.mensaje-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
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
    
    .semaforos-container {
        flex-direction: column;
    }
    
    .semaforo {
        min-width: 100%;
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
    
    .modal-password-content {
        width: 95%;
        padding: 20px;
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
    max-height: 300px;
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
        /* Estilos para los semáforos ultra compactos */
.semaforos-container {
    display: flex;
    gap: 8px;
    margin: 15px 0;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: stretch;
}

.semaforo {
    flex: 1;
    min-width: 0;
    text-align: center;
    padding: 8px 4px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 70px;
    border: 1px solid #e0e0e0;
}

.semaforo:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.15);
}

.semaforo.verde {
    border-top: 3px solid #28a745;
    background: linear-gradient(135deg, #f8fff9, #e8f5e8);
}

.semaforo.amarillo {
    border-top: 3px solid #ffc107;
    background: linear-gradient(135deg, #fffbf0, #fff3cd);
}

.semaforo.rojo {
    border-top: 3px solid #dc3545;
    background: linear-gradient(135deg, #fff5f5, #ffe6e6);
}

.semaforo-icono {
    font-size: 1.3rem;
    margin-bottom: 3px;
    line-height: 1;
}

.semaforo-titulo {
    font-weight: 700;
    font-size: 0.7rem;
    margin-bottom: 2px;
    color: #333;
    line-height: 1.1;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.semaforo-estado {
    font-size: 0.65rem;
    color: #666;
    font-weight: 600;
    line-height: 1.1;
}

/* Para móviles - más compactos pero manteniendo fila */
@media (max-width: 768px) {
    .semaforos-container {
        gap: 6px;
        margin: 12px 0;
        flex-direction: row; /* Mantener en fila */
    }
    
    .semaforo {
        padding: 6px 3px;
        height: 65px;
        min-width: 0;
    }
    
    .semaforo-icono {
        font-size: 1.1rem;
        margin-bottom: 2px;
    }
    
    .semaforo-titulo {
        font-size: 0.65rem;
    }
    
    .semaforo-estado {
        font-size: 0.6rem;
    }
}

/* Para pantallas muy pequeñas */
@media (max-width: 480px) {
    .semaforos-container {
        gap: 4px;
    }
    
    .semaforo {
        padding: 5px 2px;
        height: 60px;
    }
    
    .semaforo-icono {
        font-size: 1rem;
    }
    
    .semaforo-titulo {
        font-size: 0.6rem;
    }
    
    .semaforo-estado {
        font-size: 0.55rem;
    }
}

/* Para pantallas grandes - un poco más espaciados */
@media (min-width: 1200px) {
    .semaforos-container {
        gap: 12px;
    }
    
    .semaforo {
        padding: 10px 6px;
        height: 75px;
    }
    
    .semaforo-icono {
        font-size: 1.5rem;
    }
    
    .semaforo-titulo {
        font-size: 0.75rem;
    }
    
    .semaforo-estado {
        font-size: 0.7rem;
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
        <!-- TARJETA DE USUARIO -->
        <div class="tarjeta">
            <div class="d-flex align-items-center flex-column flex-md-row">
                <img src="img/usuarios/<?php echo htmlspecialchars($usuarioInfo['foto'] ?? 'usuario.png'); ?>" 
                     alt="Foto" class="img-fluid rounded-circle border border-primary me-md-3 mb-3 mb-md-0"
                     style="width:100px;height:100px;object-fit:cover;">
                <div class="text-center text-md-start">
                    <h2 class="mb-2"><?php echo htmlspecialchars($usuarioInfo['apellidos'].' '.$usuarioInfo['nombre'] . ' - ' .$rol_nombre); ?></h2>
                    <p class="mb-1"><b>CLAVE:</b> <?php echo htmlspecialchars($usuarioInfo['clave']); ?></p>
                    <?php if($rol_nombre == 'Alumno'): ?>
                        <p class="mb-1"><b>SEMESTRE:</b> <?php echo htmlspecialchars($usuarioInfo['semestre']); ?></p>
                        <p class="mb-1"><b>CARRERA:</b> <?php echo htmlspecialchars($usuarioInfo['carrera']); ?></p>
                        <p class="mb-1"><b>ESPECIALIDAD:</b> <?php echo htmlspecialchars($usuarioInfo['especialidad']); ?></p>
                        <p class="mb-1"><b>ESTADO:</b> <?php echo htmlspecialchars($usuarioInfo['estado']); ?></p>
                    <?php elseif($rol_nombre == 'Profesor'): ?>
                        <p class="mb-1"><b>COORDINADOR:</b> <?php echo htmlspecialchars($usuarioInfo['coordinador'] ?? 'No asignado'); ?></p>
                        <p class="mb-1"><b>ESTADO:</b> <?php echo htmlspecialchars($usuarioInfo['estado']); ?></p>
                            <button type="button" class="btn btn-warning mt-2" onclick="verPortafolioProfesor()">
        <i class="fas fa-folder-open me-1"></i> Ver Mi Portafolio
    </button>
                    <?php elseif($rol_nombre == 'Coordinador'): ?>
                        <p class="mb-1"><b>CARRERA:</b> <?php echo htmlspecialchars($usuarioInfo['carrera'] ?? 'No asignada'); ?></p>
                        <p class="mb-1"><b>ESTADO:</b> <?php echo htmlspecialchars($usuarioInfo['estado']); ?></p>
                    <?php endif; ?>
                    <form id="formFoto" enctype="multipart/form-data" method="POST" action="cambiar_foto.php">
                        <input type="hidden" name="id_usuario" value="<?php echo $id_usuario; ?>">
                        <input type="file" name="foto" id="inputFoto" class="d-none" accept="image/*">
                        <button type="button" onclick="document.getElementById('inputFoto').click();" 
                                class="btn btn-primary btn-sm mt-2">Cambiar Foto</button>
                    </form>
                    
                    <!-- Botón para abrir modal de contraseña -->
                    <button type="button" class="btn-cambiar-password" onclick="abrirModalPassword()">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                </div>
            </div>

            <!-- SEMÁFOROS SOLO PARA ALUMNOS -->
<?php if($rol_nombre == 'Alumno'): ?>
<div class="semaforos-container">
    <!-- Semáforo 1: Regularidad -->
    <div class="semaforo <?php echo $semaforos['regularidad']; ?>">
        <div class="semaforo-icono">
            <?php 
            if($semaforos['regularidad'] == 'verde') echo '';
            elseif($semaforos['regularidad'] == 'amarillo') echo '';
            else echo '';
            ?>
        </div>
        <div class="semaforo-titulo">REGULARIDAD</div>
        <div class="semaforo-estado">
            <?php 
            if($semaforos['regularidad'] == 'verde') echo 'REGULAR';
            elseif($semaforos['regularidad'] == 'amarillo') echo 'IRREGULAR';
            else echo 'REZAGADO';
            ?>
        </div>
    </div>

    <!-- Semáforo 2: Asistencia -->
    <div class="semaforo <?php echo $semaforos['asistencia']; ?>">
        <div class="semaforo-icono">
            <?php 
            if($semaforos['asistencia'] == 'verde') echo '';
            elseif($semaforos['asistencia'] == 'amarillo') echo '';
            else echo '';
            ?>
        </div>
        <div class="semaforo-titulo">ASISTENCIA</div>
        <div class="semaforo-estado">
            <?php 
            if($semaforos['asistencia'] == 'verde') echo 'BUENA';
            elseif($semaforos['asistencia'] == 'amarillo') echo 'RIESGO';
            else echo 'PELIGRO';
            ?>
        </div>
    </div>

    <!-- Semáforo 3: Progreso -->
    <div class="semaforo <?php echo $semaforos['progreso']; ?>">
        <div class="semaforo-icono">
            <?php 
            if($semaforos['progreso'] == 'verde') echo '';
            elseif($semaforos['progreso'] == 'amarillo') echo '';
            else echo '';
            ?>
        </div>
        <div class="semaforo-titulo">PROGRESO</div>
        <div class="semaforo-estado">
            <?php 
            if($semaforos['progreso'] == 'verde') echo 'ÓPTIMO';
            elseif($semaforos['progreso'] == 'amarillo') echo 'REGULAR';
            else echo 'BAJO';
            ?>
        </div>
    </div>
</div>
<?php endif; ?>
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
<!-- Modal para Portafolio del Profesor -->
<div class="modal fade" id="modalPortafolioProfesor" tabindex="-1" aria-labelledby="modalPortafolioProfesorLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalPortafolioProfesorLabel">
                    <i class="fas fa-folder-open me-2"></i>Mi Portafolio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoPortafolioProfesor">
                <!-- El contenido se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>
        <!-- MODAL PARA AGREGAR DOCUMENTO AL PORTAFOLIO -->
<div class="modal fade" id="modalAgregarDocumento" tabindex="-1" aria-labelledby="modalAgregarDocumentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalAgregarDocumentoLabel">Agregar Documento al Portafolio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAgregarDocumento" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="id_profesor_documento" name="id_profesor">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_documento" class="form-label">Tipo de Documento *</label>
                                <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="certificado_universitario">Certificado Universitario</option>
                                    <option value="preparatoria">Preparatoria/Bachillerato</option>
                                    <option value="curso">Curso</option>
                                    <option value="diploma">Diploma</option>
                                    <option value="otro">Otro Documento</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de Emisión</label>
                                <input type="date" class="form-control" id="fecha_emision" name="fecha_emision">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre_documento" class="form-label">Nombre del Documento *</label>
                        <input type="text" class="form-control" id="nombre_documento" name="nombre_documento" required 
                               placeholder="Ej: Certificado de Maestría en Informática">
                    </div>
                    
                    <div class="mb-3">
                        <label for="institucion" class="form-label">Institución</label>
                        <input type="text" class="form-control" id="institucion" name="institucion" 
                               placeholder="Ej: Universidad Nacional Autónoma de México">
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" 
                                  placeholder="Descripción adicional del documento..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="archivo_documento" class="form-label">Archivo del Documento *</label>
                        <input type="file" class="form-control" id="archivo_documento" name="archivo_documento" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">
                            Formatos permitidos: PDF, Word, JPG, PNG. Tamaño máximo: 10MB
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Guardar Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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

<!-- Modal para cambiar contraseña -->
<div class="modal-password" id="modalPassword">
    <div class="modal-password-content">
        <div class="modal-password-header">
            <h3><i class="fas fa-key"></i> Cambiar Contraseña</h3>
            <button class="cerrar-modal-password" onclick="cerrarModalPassword()">&times;</button>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $tipo_mensaje === 'exito' ? 'mensaje-exito' : 'mensaje-error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-password">
            <div class="form-group">
                <label for="password_actual">Contraseña Actual:</label>
                <input type="password" class="form-control" id="password_actual" name="password_actual" required>
            </div>
            
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña:</label>
                <input type="password" class="form-control" id="nueva_password" name="nueva_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirmar_password">Confirmar Nueva Contraseña:</label>
                <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
            </div>
            
            <button type="submit" name="cambiar_password" class="btn-password">
                Cambiar Contraseña
            </button>
        </form>
    </div>
</div>

<script>
let chatActivo = null;
// Variables globales para el portafolio
var profesorActual = {
    id: null,
    nombre: null
};

// Función para ver el portafolio del profesor
function verPortafolioProfesor() {
    // Obtener el ID del profesor desde PHP
    <?php
    if($rol_nombre == 'Profesor') {
        // Necesitamos obtener el id_profesor del usuario actual
        $stmt_profesor = $conexion->prepare("SELECT id_profesor FROM profesor WHERE id_usuario = ?");
        $stmt_profesor->bind_param("i", $id_usuario);
        $stmt_profesor->execute();
        $result_profesor = $stmt_profesor->get_result();
        $profesor_data = $result_profesor->fetch_assoc();
        $id_profesor_actual = $profesor_data['id_profesor'] ?? 0;
    } else {
        $id_profesor_actual = 0;
    }
    ?>
    
    const idProfesor = <?php echo $id_profesor_actual; ?>;
    const nombreProfesor = "<?php echo htmlspecialchars($usuarioInfo['nombre'] . ' ' . $usuarioInfo['apellidos']); ?>";
    
    if (idProfesor <= 0) {
        alert('Error: No se pudo identificar al profesor');
        return;
    }
    
    cargarPortafolioProfesor(idProfesor, nombreProfesor);
}

// Función para cargar el portafolio
function cargarPortafolioProfesor(idProfesor, nombreProfesor) {
    profesorActual.id = idProfesor;
    profesorActual.nombre = nombreProfesor;
    
    document.getElementById('contenidoPortafolioProfesor').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    document.getElementById('modalPortafolioProfesorLabel').textContent = 'Portafolio de ' + nombreProfesor;
    
    var modal = new bootstrap.Modal(document.getElementById('modalPortafolioProfesor'));
    modal.show();
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'acciones/obtener_portafolio_profesor.php?id_profesor=' + idProfesor, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('contenidoPortafolioProfesor').innerHTML = xhr.responseText;
        } else {
            document.getElementById('contenidoPortafolioProfesor').innerHTML = '<div class="alert alert-danger">Error al cargar el portafolio</div>';
        }
    };
    xhr.send();
}

// Función para abrir modal de agregar documento
function agregarDocumentoPortafolio(idProfesor) {
    document.getElementById('id_profesor_documento').value = idProfesor;
    document.getElementById('formAgregarDocumento').reset();
    
    // Cerrar modal del portafolio primero
    var modalPortafolio = bootstrap.Modal.getInstance(document.getElementById('modalPortafolioProfesor'));
    if (modalPortafolio) {
        modalPortafolio.hide();
    }
    
    // Abrir modal de agregar después de un breve delay
    setTimeout(function() {
        var modalAgregar = new bootstrap.Modal(document.getElementById('modalAgregarDocumento'));
        modalAgregar.show();
    }, 500);
}

// Función para eliminar documento
function eliminarDocumento(idPortafolio, nombreDocumento) {
    if (confirm('¿Estás seguro de que deseas eliminar el documento "' + nombreDocumento + '"? Esta acción no se puede deshacer.')) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'acciones/eliminar_documento_portafolio.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('Documento eliminado correctamente');
                    // Recargar el portafolio
                    if (profesorActual.id && profesorActual.nombre) {
                        cargarPortafolioProfesor(profesorActual.id, profesorActual.nombre);
                    }
                } else {
                    alert('Error al eliminar el documento: ' + response.message);
                }
            }
        };
        xhr.send('id_portafolio=' + idPortafolio);
    }
}

// Manejar envío del formulario de documento
document.getElementById('formAgregarDocumento').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'acciones/guardar_documento_portafolio.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('Documento guardado correctamente');
                
                // Cerrar modal de agregar
                var modalAgregar = bootstrap.Modal.getInstance(document.getElementById('modalAgregarDocumento'));
                if (modalAgregar) {
                    modalAgregar.hide();
                }
                
                // Volver al portafolio
                setTimeout(function() {
                    if (profesorActual.id && profesorActual.nombre) {
                        cargarPortafolioProfesor(profesorActual.id, profesorActual.nombre);
                    }
                }, 500);
                
            } else {
                alert('Error al guardar el documento: ' + response.message);
            }
        }
    };
    xhr.send(formData);
});
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

// Funciones para el modal de contraseña
function abrirModalPassword() {
    document.getElementById('modalPassword').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalPassword() {
    document.getElementById('modalPassword').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalPassword').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalPassword();
    }
});

document.getElementById('inputFoto').addEventListener('change', function(){
    document.getElementById('formFoto').submit();
});

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
    
    // Ocultar chat container inicialmente
    document.getElementById('chatContainer').classList.remove('activo');
});
</script>