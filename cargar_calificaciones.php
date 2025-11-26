<?php
session_start();
include "conexion.php";

if ($_SESSION['rol'] != '2') { 
    exit("Acceso denegado");
}

$id_clase = $_GET['id_clase'] ?? null;
$permiso_subir = $_GET['permiso_subir'] ?? 0;
$permiso_modificar = $_GET['permiso_modificar'] ?? 0;

if (!$id_clase) {
    exit("ID de clase no especificado");
}

// Obtener información de la clase
$sql_info = "
SELECT m.id_materia, m.nombre AS materia, m.unidades, c.activo
FROM clase c
INNER JOIN materia m ON c.id_materia = m.id_materia
WHERE c.id_clase = $id_clase
";
$info = $conexion->query($sql_info)->fetch_assoc();

if (!$info) {
    exit("Clase no encontrada");
}

$id_materia = $info['id_materia'];
$materia = $info['materia'];
$unidades = $info['unidades'];
$activo = $info['activo'];

$estado_texto = $activo ? "CURSO ACTIVO" : "CURSO CERRADO";
$estado_clase = $activo ? "estado-activo" : "estado-cerrado";

// OBTENER PERMISOS ACTUALES DESDE LA BASE DE DATOS (IDs específicos)
$sql_permisos = "SELECT id_accion, activo FROM acciones WHERE id_accion IN (1, 2)";
$res_permisos = $conexion->query($sql_permisos);
$permisos_db = [
    'modificar' => false,  // ID 1
    'subir' => false       // ID 2
];

if ($res_permisos && $res_permisos->num_rows > 0) {
    while ($permiso = $res_permisos->fetch_assoc()) {
        if ($permiso['id_accion'] == 1) {
            $permisos_db['modificar'] = (bool)$permiso['activo'];
        } elseif ($permiso['id_accion'] == 2) {
            $permisos_db['subir'] = (bool)$permiso['activo'];
        }
    }
}

// SOBREESCRIBIR CON LOS PERMISOS ACTUALES DE LA BD (por seguridad)
$permiso_subir = $permisos_db['subir'];
$permiso_modificar = $permisos_db['modificar'];

// Verificar si hay calificaciones existentes
$sql_calificaciones_existentes = "
SELECT COUNT(*) as total 
FROM calificacion_clase cc
INNER JOIN asignacion a ON cc.id_asignacion = a.id_asignacion
WHERE a.id_clase = $id_clase
";
$res_existentes = $conexion->query($sql_calificaciones_existentes);
$total_calificaciones = $res_existentes->fetch_assoc()['total'];

// Determinar qué acciones están permitidas
$puede_subir = $permiso_subir && $activo;
$puede_modificar = $permiso_modificar && $activo;
$puede_guardar = ($puede_subir || $puede_modificar) && $activo;
?>
<style>
/* ESTILOS PARA EL BOTÓN DE GUARDAR CALIFICACIONES */
.btn-guardar {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: block;
    margin: 20px auto 0;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-guardar:hover {
    background: linear-gradient(135deg, #218838, #1e9e8a);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.btn-guardar:active {
    transform: translateY(0);
    box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
}

.btn-guardar:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-guardar:disabled:hover {
    background: #6c757d;
    transform: none;
    box-shadow: none;
}

/* Efecto de carga en el botón */
.btn-guardar .fa-spinner {
    margin-right: 8px;
}

/* Efecto de onda al hacer clic */
.btn-guardar::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn-guardar:focus:not(:active)::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(20, 20);
        opacity: 0;
    }
}

/* ESTILOS PARA INPUTS DESHABILITADOS */
.input-deshabilitado {
    background-color: #e9ecef !important;
    cursor: not-allowed !important;
    opacity: 0.7;
    border-color: #ced4da !important;
}

.input-deshabilitado:focus {
    border-color: #ced4da !important;
    box-shadow: none !important;
}

/* ESTILOS PARA LA TABLA DE CALIFICACIONES */
.tabla-calificaciones {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.tabla-calificaciones th {
    background: linear-gradient(135deg, #1565c0, #1976d2);
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 0.85rem;
    border: none;
}

.tabla-calificaciones td {
    padding: 10px 8px;
    text-align: center;
    border-bottom: 1px solid #eee;
    font-size: 0.85rem;
}

.tabla-calificaciones tr:hover {
    background-color: #f8f9fa;
}

.input-calificacion {
    width: 70px;
    padding: 8px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    text-align: center;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    font-weight: 500;
}

.input-calificacion:focus {
    outline: none;
    border-color: #1565c0;
    box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
    background-color: #f8fbff;
}

.input-calificacion:disabled {
    background-color: #f8f9fa;
    color: #6c757d;
    border-color: #dee2e6;
}

/* ESTILOS PARA MENSAJES INFORMATIVOS */
.info-materia {
    background: linear-gradient(135deg, #e3f2fd, #f0f7ff);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #1565c0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.info-materia h4 {
    margin: 0 0 10px 0;
    color: #1565c0;
    font-weight: 600;
}

.info-materia p {
    margin: 5px 0;
    color: #555;
}

.estado-activo {
    color: #28a745;
    font-weight: 600;
    background: #d4edda;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.estado-cerrado {
    color: #dc3545;
    font-weight: 600;
    background: #f8d7da;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.mensaje-curso-cerrado {
    background: #f8d7da;
    color: #721c24;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    margin: 20px 0;
    border-left: 4px solid #dc3545;
}

.mensaje-curso-cerrado h4 {
    margin: 0 0 10px 0;
    color: #721c24;
}

.mensaje-curso-cerrado i {
    color: #dc3545;
}

/* NOTIFICACIONES TEMPORALES */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.notification.success {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.notification.error {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .btn-guardar {
        width: 100%;
        margin: 20px 0 0 0;
    }
    
    .tabla-calificaciones {
        font-size: 0.8rem;
    }
    
    .input-calificacion {
        width: 60px;
        padding: 6px;
    }
    
    .info-materia {
        padding: 15px;
    }
}
</style>

<div class="info-materia">
    <h4><?php echo htmlspecialchars($materia); ?> (<?php echo $unidades; ?> UNIDADES)</h4>
    <p><strong>Estado:</strong> <span class="<?php echo $estado_clase; ?>"><?php echo $estado_texto; ?></span></p>
    <p><strong>ID Clase:</strong> <?php echo $id_clase; ?></p>
    
    <!-- Indicador de permisos dentro del modal -->
    <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #1565c0;">
        <strong>Permisos activos:</strong>
        <?php if ($puede_subir): ?>
            <span style="color: #28a745; margin-left: 10px;"><i class="fas fa-check-circle"></i> Subir calificaciones</span>
        <?php endif; ?>
        <?php if ($puede_modificar): ?>
            <span style="color: #28a745; margin-left: 10px;"><i class="fas fa-check-circle"></i> Modificar calificaciones</span>
        <?php endif; ?>
        <?php if (!$puede_subir && !$puede_modificar): ?>
            <span style="color: #dc3545; margin-left: 10px;"><i class="fas fa-times-circle"></i> Sin permisos de calificación</span>
        <?php endif; ?>
        
    </div>
</div>

<?php if ($activo && ($puede_subir || $puede_modificar)): ?>
<form method="POST" action="guardar_calificaciones.php" id="formCalificaciones">
    <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
    <input type="hidden" name="permiso_subir" value="<?php echo $permiso_subir; ?>">
    <input type="hidden" name="permiso_modificar" value="<?php echo $permiso_modificar; ?>">
    
    <!-- Mensaje informativo según el tipo de permiso -->
    <?php if ($total_calificaciones > 0 && !$puede_modificar && $puede_subir): ?>
    <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Nota:</strong> Ya existen calificaciones registradas. Solo puede agregar nuevas calificaciones en unidades vacías.
    </div>
    <?php elseif ($total_calificaciones > 0 && !$puede_modificar): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #dc3545;">
        <i class="fas fa-ban"></i>
        <strong>Restricción:</strong> No tiene permisos para modificar calificaciones existentes (ID 1 inactivo).
    </div>
    <?php elseif ($total_calificaciones == 0 && !$puede_subir): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #dc3545;">
        <i class="fas fa-ban"></i>
        <strong>Restricción:</strong> No tiene permisos para subir nuevas calificaciones (ID 2 inactivo).
    </div>
    <?php endif; ?>
    
    <div style="overflow-x: auto;">
        <table class="tabla-calificaciones">
            <thead>
                <tr>
                    <th>ALUMNO</th>
                    <?php for ($i=1; $i<=$unidades; $i++): ?>
                        <th>U<?php echo $i; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_alumnos = "
                SELECT asig.id_asignacion, u.nombre, u.apellidos
                FROM asignacion asig
                INNER JOIN alumno a ON asig.id_alumno = a.id_alumno
                INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                WHERE asig.id_clase = $id_clase
                ORDER BY u.apellidos, u.nombre
                ";
                $res_alumnos = $conexion->query($sql_alumnos);

                if ($res_alumnos->num_rows > 0):
                    while ($al = $res_alumnos->fetch_assoc()):
                        $id_asignacion = $al['id_asignacion'];
                        $sql_cal = "SELECT unidad, calificacion FROM calificacion_clase WHERE id_asignacion = $id_asignacion";
                        $res_cal = $conexion->query($sql_cal);
                        $cal = [];
                        while ($c = $res_cal->fetch_assoc()) { 
                            $cal[$c['unidad']] = $c['calificacion']; 
                        }
                ?>
                <tr>
                    <td style="text-align: left; font-weight: 500;">
                        <?php echo htmlspecialchars($al['apellidos'] . ' ' . $al['nombre']); ?>
                    </td>
                    <?php for ($i=1; $i<=$unidades; $i++): 
                        $val = isset($cal[$i]) ? $cal[$i] : "";
                        $input_disabled = false;
                        $title = "";
                        
                        // Determinar si el input debe estar deshabilitado
                        if (isset($cal[$i]) && $cal[$i] !== "" && !$puede_modificar) {
                            $input_disabled = true;
                            $title = "No tiene permisos para modificar calificaciones existentes (ID 1)";
                        } elseif (!isset($cal[$i]) && !$puede_subir) {
                            $input_disabled = true;
                            $title = "No tiene permisos para agregar nuevas calificaciones (ID 2)";
                        }
                    ?>
                    <td>
                        <input type="number" 
                               class="input-calificacion <?php echo $input_disabled ? 'input-deshabilitado' : ''; ?>" 
                               name="calificaciones[<?php echo $id_asignacion; ?>][<?php echo $i; ?>]" 
                               step="0.01" 
                               min="0" 
                               max="100" 
                               value="<?php echo $val; ?>" 
                               placeholder="0.00"
                               <?php echo $input_disabled ? 'disabled' : ''; ?>
                               title="<?php echo $title; ?>">
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="<?php echo $unidades + 1; ?>" style="text-align: center; padding: 20px; color: #6c757d;">
                        No hay alumnos asignados a esta clase.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($puede_guardar): ?>
    <button type="submit" class="btn-guardar">
        <i class="fas fa-save"></i> 
        <?php echo $total_calificaciones > 0 ? 'ACTUALIZAR CALIFICACIONES' : 'GUARDAR CALIFICACIONES'; ?>
    </button>
    <?php else: ?>
    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 6px; margin-top: 20px;">
        <i class="fas fa-lock" style="font-size: 2rem; color: #6c757d; margin-bottom: 10px;"></i>
        <p style="color: #6c757d; margin: 0;">No tiene permisos para guardar calificaciones en este momento.</p>
    </div>
    <?php endif; ?>
</form>

<style>
.input-deshabilitado {
    background-color: #e9ecef;
    cursor: not-allowed;
    opacity: 0.7;
}

.input-deshabilitado:focus {
    border-color: #ddd;
    box-shadow: none;
}
</style>

<script>
// Manejar el envío del formulario con AJAX para mejor UX
document.getElementById('formCalificaciones')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('.btn-guardar');
    const originalText = submitBtn.innerHTML;
    
    // Mostrar loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> GUARDANDO...';
    submitBtn.disabled = true;
    
    fetch('guardar_calificaciones.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('éxito') || data.includes('correctamente')) {
            // Mostrar mensaje de éxito
            showNotification('Calificaciones guardadas correctamente', 'success');
            setTimeout(() => {
                window.location.href = 'profesor.php?exito=calificaciones';
            }, 1500);
        } else {
            throw new Error('Error al guardar: ' + data);
        }
    })
    .catch(error => {
        showNotification('Error al guardar las calificaciones', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        ${type === 'success' ? 'background: #28a745;' : 'background: #dc3545;'}
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Agregar validación adicional antes de enviar
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCalificaciones');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Verificar que al menos un campo esté lleno
            const inputs = form.querySelectorAll('input[type="number"]:not(:disabled)');
            let alMenosUnoLleno = false;
            
            inputs.forEach(input => {
                if (input.value && input.value !== '') {
                    alMenosUnoLleno = true;
                }
            });
            
            if (!alMenosUnoLleno) {
                e.preventDefault();
                showNotification('Debe ingresar al menos una calificación', 'error');
                return false;
            }
        });
    }
});
</script>

<?php elseif (!$activo): ?>
<div class="mensaje-curso-cerrado">
    <i class="fas fa-lock" style="font-size: 2rem; margin-bottom: 10px;"></i>
    <h4>Curso Cerrado</h4>
    <p>Este curso ya está cerrado y no se pueden modificar las calificaciones.</p>
</div>
<?php else: ?>
<div class="mensaje-curso-cerrado">
    <i class="fas fa-ban" style="font-size: 2rem; margin-bottom: 10px;"></i>
    <h4>Permisos Insuficientes</h4>
    <p>No tiene permisos para gestionar calificaciones en esta clase.</p>
</div>
<?php endif; ?>