<!DOCTYPE html>
<html lang="es">
</body>

<footer class="modern-footer">
    <div class="footer-container">
        <!-- SECCIÓN DE ESTADÍSTICAS -->
        <div class="footer-stats">
            <div class="stat-item">
                <span class="stat-number">
                    <?php 
                    include "conexion.php";
                    $query = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE rol = 1");
                    echo $query->fetch_assoc()['total'] ?? '0';
                    ?>
                </span>
                <span class="stat-label">Alumnos</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number">
                    <?php 
                    $query = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE rol = 2");
                    echo $query->fetch_assoc()['total'] ?? '0';
                    ?>
                </span>
                <span class="stat-label">Maestros</span>
            </div>
            
            <div class="stat-item">
                <span class="stat-number">
                    <?php 
                    $query = $conexion->query("SELECT COUNT(*) as total FROM clase");
                    echo $query->fetch_assoc()['total'] ?? '0';
                    ?>
                </span>
                <span class="stat-label">Asignaturas</span>
            </div>
        </div>

        <!-- LÍNEA SEPARADORA -->
        <div class="footer-divider"></div>

        <!-- DERECHOS DE AUTOR -->
        <div class="footer-copyright">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Control Escolar. Todos los derechos reservados.</p>
            <p>&copy; <?php echo date('Y'); ?> HECHO POR ALEXANDER LOPEZ MORA </p>
        </div>
    </div>

    <!-- ELEMENTOS DECORATIVOS -->
    <div class="footer-decoration">
        <div class="decoration-circle"></div>
        <div class="decoration-circle"></div>
        <div class="decoration-circle"></div>
    </div>
</footer>

<style>
/* ESTILOS MODERNOS EN AZUL */
.modern-footer {
    background: linear-gradient(135deg, #1565c0 0%, #1976d2 50%, #2196f3 100%);
    color: white;
    position: relative;
    overflow: hidden;
    margin-top: 80px;
    padding: 50px 0 30px;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    box-shadow: 0 -5px 30px rgba(21, 101, 192, 0.3);
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    position: relative;
    z-index: 2;
    text-align: center;
}

/* ESTADÍSTICAS */
.footer-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-5px);
}

.stat-number {
    display: block;
    font-size: 2.5em;
    font-weight: 800;
    color: #bbdefb;
    margin-bottom: 8px;
    text-shadow: 0 4px 8px rgba(0,0,0,0.2);
    background: linear-gradient(45deg, #bbdefb, #90caf9);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 0.9em;
    color: #e3f2fd;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 600;
}

/* DIVIDER */
.footer-divider {
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    margin: 30px auto;
    max-width: 400px;
}

/* COPYRIGHT */
.footer-copyright p {
    color: #bbdefb;
    font-size: 1em;
    margin: 0;
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* ELEMENTOS DECORATIVOS */
.footer-decoration {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

.decoration-circle {
    position: absolute;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(187, 222, 251, 0.15) 0%, transparent 70%);
    animation: float 8s ease-in-out infinite;
}

.decoration-circle:nth-child(1) {
    width: 120px;
    height: 120px;
    top: 20px;
    left: 10%;
    animation-delay: 0s;
}

.decoration-circle:nth-child(2) {
    width: 80px;
    height: 80px;
    bottom: 40px;
    right: 15%;
    animation-delay: 2.5s;
}

.decoration-circle:nth-child(3) {
    width: 60px;
    height: 60px;
    top: 60%;
    left: 20%;
    animation-delay: 5s;
}

@keyframes float {
    0%, 100% { 
        transform: translateY(0px) rotate(0deg); 
    }
    50% { 
        transform: translateY(-15px) rotate(180deg); 
    }
}

/* EFECTO DE BRILLO */
.modern-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    animation: shine 3s ease-in-out infinite;
}

@keyframes shine {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .modern-footer {
        padding: 40px 0 25px;
        margin-top: 60px;
    }
    
    .footer-stats {
        gap: 40px;
    }
    
    .stat-number {
        font-size: 2em;
    }
    
    .stat-label {
        font-size: 0.8em;
        letter-spacing: 1px;
    }
}

@media (max-width: 480px) {
    .footer-stats {
        gap: 30px;
        flex-direction: column;
    }
    
    .stat-item {
        margin-bottom: 10px;
    }
    
    .footer-divider {
        margin: 20px auto;
        max-width: 200px;
    }
    
    .footer-copyright p {
        font-size: 0.9em;
    }
}

/* EFECTO HOVER MEJORADO */
.stat-item {
    position: relative;
    padding: 15px 25px;
    border-radius: 15px;
    transition: all 0.3s ease;
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.05);
    border-radius: 15px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-item:hover::before {
    opacity: 1;
}

/* ANIMACIÓN DE ENTRADA */
.footer-stats {
    animation: slideUp 0.8s ease-out;
}

.footer-copyright {
    animation: fadeIn 1s ease-out 0.3s both;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>

</html>