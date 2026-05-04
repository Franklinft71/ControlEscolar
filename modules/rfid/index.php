<?php
require_once '../../includes/db.php';
$pdo = db_connect();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = sanitize($_POST['rfid_uid']);
    $stmt = $pdo->prepare("
        SELECT e.*, g.nombre as grado, s.nombre as seccion, h.hora_entrada as horario_entrada 
        FROM estudiantes e 
        LEFT JOIN grados g ON e.grado_id = g.id
        LEFT JOIN secciones s ON e.seccion_id = s.id
        LEFT JOIN turnos h ON e.turno_id = h.id
        WHERE e.rfid_uid = ?
    ");
    $stmt->execute([$rfid_uid]);
    $estudiante = $stmt->fetch();
    if ($estudiante) {
        // Si el estudiante está retirado o inactivo, informar pero no necesariamente bloquear el log si el usuario quiere ver quién intentó pasar
        // Pero si está suspendido, es importante mostrar el motivo (si es posible)
        
        if ($estudiante['estatus'] === 'inactivo') {
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Estudiante Inactivo']);
             exit;
        }
        $stmt = $pdo->prepare("SELECT tipo, fecha_hora FROM asistencia WHERE estudiante_id = ? AND DATE(fecha_hora) = CURDATE() ORDER BY fecha_hora DESC LIMIT 1");
        $stmt->execute([$estudiante['id']]);
        $ultima = $stmt->fetch();
        $tipo = ($ultima && $ultima['tipo'] === 'entrada') ? 'salida' : 'entrada';
        
        // Calcular retardo (sólo para entradas)
        $con_retardo = false;
        if ($tipo === 'entrada' && !empty($estudiante['horario_entrada'])) {
            $hora_actual = date('H:i:s');
            
            // El estudiante puede llegar "A tiempo" desde 1 hora antes hasta 5 minutos después
            $inicio_valido = date('H:i:s', strtotime($estudiante['horario_entrada'] . ' - 60 minutes'));
            $fin_valido = date('H:i:s', strtotime($estudiante['horario_entrada'] . ' + 5 minutes'));
            
            // Si llega ANTES de que abran (muy temprano en la madrugada) o DESPUÉS de la prórroga, es retardo (o fuera de horario)
            if ($hora_actual < $inicio_valido || $hora_actual > $fin_valido) {
                $con_retardo = true;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO asistencia (estudiante_id, tipo, retardo, fecha_hora, rfid_uid, metodo, confirmacion_whatsapp) VALUES (?, ?, ?, NOW(), ?, 'rfid', 'pendiente')");
        $stmt->execute([$estudiante['id'], $tipo, $con_retardo ? 1 : 0, $rfid_uid]);
        $asistencia_id = $pdo->lastInsertId();
        
        // Encolar Notificación de WhatsApp
        if (!empty($estudiante['telefono_representante'])) {
            $hora_txt = date('h:i A');
            $estado_txt = ($tipo === 'entrada') ? 'ENTRADA' : 'SALIDA';
            $msj = "🏫 *Control Escolar*\n\nHola " . trim($estudiante['nombre_representante']) . ", le informamos que su representado *" . $estudiante['nombre'] . " " . $estudiante['apellido'] . "* ha registrado su *$estado_txt* el día de hoy a las $hora_txt.\n";
            if ($con_retardo) $msj .= "⚠️ _Nota: Llegada con retardo._\n";
            
            $stmtMsg = $pdo->prepare("INSERT INTO notificaciones_log (estudiante_id, telefono, tipo, mensaje, estado) VALUES (?, ?, 'whatsapp', ?, 'pendiente')");
            $stmtMsg->execute([$estudiante['id'], $estudiante['telefono_representante'], $msj]);
        }
        
        $data = [
            'success' => true,
            'estudiante' => [
                'nombre' => $estudiante['nombre'],
                'apellido' => $estudiante['apellido'],
                'cedula' => $estudiante['cedula_escolar'],
                'grado_seccion' => ($estudiante['grado'] ?? '') . ' ' . ($estudiante['seccion'] ?? ''),
                'foto' => $estudiante['foto'] ?? ''
            ],
            'tipo' => $tipo,
            'retardo' => $con_retardo,
            'estatus' => $estudiante['estatus'],
            'asistencia_id' => $pdo->lastInsertId()
        ];
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tarjeta no registrada']);
    exit;
}
$page_name = 'rfid';
$hide_sidebar = true; // Ocultar el menú lateral en esta pantalla
require_once '../../includes/header.php';
?>
<style>
.badge-suspendido { background-color: #ffc107; color: #000; }
.badge-retirado { background-color: #6c757d; color: #fff; }
.badge-activo { background-color: #198754; color: #fff; }
.badge-inactivo { background-color: #dc3545; color: #fff; }
.badge-entrada { background-color: #198754; color: #fff; }
.badge-salida { background-color: #ffc107; color: #000; }
</style>
<?php
$pdo = db_connect();
$stmt = $pdo->query("SELECT a.*, e.nombre, e.apellido, e.estatus FROM asistencia a JOIN estudiantes e ON a.estudiante_id = e.id WHERE DATE(a.fecha_hora) = CURDATE() ORDER BY a.fecha_hora DESC LIMIT 20");
$registros_hoy = $stmt->fetchAll();
?>
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2><i class="fas fa-wifi me-2 text-primary"></i>Escáner RFID</h2>
    </div>
    <div class="col-12 col-md-6 text-md-end mt-3 mt-md-0">
        <a href="../../index.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-rfid me-2"></i>Area de Escaneo</h5></div>
            <div class="card-body text-center py-5">
                <div class="scanner-box d-inline-block px-5 py-4">
                    <div class="scanner-icon"><i class="fas fa-wifi"></i></div>
                    <h3>ESCANEANDO...</h3>
                    <div class="rfid-display" id="rfidDisplay">- - - - - - - - - -</div>
                </div>
                <input type="text" id="rfidInput" class="form-control mt-3" placeholder="UID de tarjeta (auto)" autocomplete="off" autofocus>
                <div id="resultadoScan" class="mt-4 d-none"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-list me-2"></i>Registros de Hoy</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Estudiante</th><th>Estatus</th><th>Tipo</th><th>Hora</th></tr></thead>
                    <tbody id="tablaRegistros">
                        <?php if (empty($registros_hoy)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sin registros hoy</td></tr>
                        <?php else: foreach ($registros_hoy as $r): ?>
                        <tr>
                            <td><?php echo $r['nombre'].' '.$r['apellido']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $r['estatus']; ?> px-2 py-1"><?php echo ucfirst($r['estatus']); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $r['tipo']; ?>"><?php echo ucfirst($r['tipo']); ?></span>
                                <?php if ($r['tipo'] === 'entrada'): ?>
                                    <?php if ($r['retardo']): ?>
                                        <span class="badge bg-danger ms-1"><i class="fas fa-clock"></i> Retardo</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-75 ms-1"><i class="fas fa-check"></i> A tiempo</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('h:i A', strtotime($r['fecha_hora'])); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('rfidInput');
    const display = document.getElementById('rfidDisplay');
    const resultado = document.getElementById('resultadoScan');
    let buffer = '';
    let lastKeyTime = 0;
    const TIMEOUT_MS = 50;
    input.focus();
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const finalUid = buffer.length >= 8 ? buffer : input.value.trim();
            if (finalUid.length >= 8) {
                procesarUID(finalUid);
            }
            buffer = '';
            input.value = ''; // Limpiar cajón
            return;
        }
        const now = Date.now();
        if (now - lastKeyTime > TIMEOUT_MS) buffer = '';
        lastKeyTime = now;
        if (/^[A-Fa-f0-9]$/.test(e.key)) {
            buffer += e.key.toUpperCase();
            display.textContent = buffer.padEnd(12, '-').substring(0, 12);
        }
    });
    input.addEventListener('blur', function() { setTimeout(() => input.focus(), 50); });
    async function procesarUID(uid) {
        try {
            const data = await App.ajax('index.php', 'POST', { rfid_uid: uid });
            if (data.success) {
                // 1. Mostrar Modal SweetAlert de 3 Segundos
                const fotoUrl = data.estudiante.foto ? '../../assets/uploads/estudiantes/' + data.estudiante.foto : 'https://ui-avatars.com/api/?name=' + data.estudiante.nombre + '+' + data.estudiante.apellido + '&size=150&background=random';
                
                let htmlModal = '<div class="text-center">';
                htmlModal += '<img src="' + fotoUrl + '" class="rounded-circle mb-3 border border-4 border-' + (data.tipo === 'entrada' ? 'success' : 'warning') + '" style="width:150px; height:150px; object-fit:cover; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">';
                htmlModal += '<h2 class="fw-bold text-dark mb-0">' + data.estudiante.nombre + ' ' + data.estudiante.apellido + '</h2>';
                htmlModal += '<p class="text-muted fs-5 mb-2">C.I: ' + data.estudiante.cedula + '</p>';
                htmlModal += '<div class="badge bg-secondary fs-6 mb-4 px-3 py-2">' + (data.estudiante.grado_seccion || 'Sin Grado') + '</div>';
                
                htmlModal += '<div class="alert alert-' + (data.tipo === 'entrada' ? 'success' : 'warning') + ' d-inline-block px-5 py-3 rounded-pill">';
                htmlModal += '<h3 class="mb-0 fw-bold"><i class="fas fa-' + (data.tipo === 'entrada' ? 'sign-in-alt' : 'sign-out-alt') + ' me-2"></i>' + data.tipo.toUpperCase() + '</h3>';
                htmlModal += '</div>';
                
                if (data.retardo) {
                    htmlModal += '<div class="mt-3"><span class="badge bg-danger fs-5 px-4 py-2 border border-2 border-white shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>LLEGADA CON RETARDO</span></div>';
                }

                if (data.estatus !== 'activo') {
                    const colorEstatus = data.estatus === 'suspendido' ? 'danger' : 'secondary';
                    const msgEstatus = data.estatus === 'suspendido' ? 'SUSPENDIDO - PASAR POR ADMINISTRACIÓN' : 'ESTATUS: ' + data.estatus.toUpperCase();
                    htmlModal += '<div class="mt-3"><div class="alert alert-' + colorEstatus + ' fw-bold border-2 shadow-sm mb-0 py-2"><i class="fas fa-exclamation-circle me-2"></i>' + msgEstatus + '</div></div>';
                }

                htmlModal += '</div>';

                Swal.fire({
                    html: htmlModal,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    backdrop: `rgba(0,0,10,0.7)`
                });

                // 2. Limpiar Input
                input.value = '';
                
                // 3. Actualizar la tabla dinámicamente
                const tbody = document.getElementById('tablaRegistros');
                if (tbody.innerHTML.includes('Sin registros hoy')) tbody.innerHTML = '';
                
                const tipoText = data.tipo.charAt(0).toUpperCase() + data.tipo.slice(1);
                const timeStr = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                
                let tipoBadge = '<span class="badge badge-' + data.tipo + '">' + tipoText + '</span>';
                if (data.tipo === 'entrada') {
                    if (data.retardo) {
                        tipoBadge += ' <span class="badge bg-danger ms-1"><i class="fas fa-clock"></i> Retardo</span>';
                    } else {
                        tipoBadge += ' <span class="badge bg-success bg-opacity-75 ms-1"><i class="fas fa-check"></i> A tiempo</span>';
                    }
                }
                
                const colorClass = data.tipo === 'entrada' ? 'table-success' : 'table-warning';
                const estatusBadge = '<span class="badge badge-' + data.estatus + '">' + data.estatus.charAt(0).toUpperCase() + data.estatus.slice(1) + '</span>';
                
                const rowHtml = '<tr class="' + colorClass + '"><td>' + data.estudiante.nombre + ' ' + data.estudiante.apellido + '</td><td>' + estatusBadge + '</td><td>' + tipoBadge + '</td><td>' + timeStr + '</td></tr>';
                
                tbody.insertAdjacentHTML('afterbegin', rowHtml);
                
                setTimeout(() => {
                    if (tbody.firstElementChild) tbody.firstElementChild.classList.remove('table-success', 'table-warning');
                }, 2000);
                
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Tarjeta no encontrada', timer: 2000 });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message, timer: 2000 });
        }
    }
});
</script>
<?php require_once '../../includes/footer.php';