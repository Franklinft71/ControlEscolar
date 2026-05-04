<?php
$page_name = 'configuracion';
$page_title = 'Configuración del Sistema';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

$pdo = db_connect();

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_config') {
    $configs = $_POST['config'] ?? [];
    foreach ($configs as $clave => $valor) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
        $stmt->execute([sanitize($valor), $clave]);
    }
    $_SESSION['message'] = 'Configuración guardada exitosamente';
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Cargar config actual
$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
$config_data = [];
while ($row = $stmt->fetch()) {
    $config_data[$row['clave']] = $row['valor'];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-12">
        <h2 class="fw-bold"><i class="fas fa-sliders-h me-3 text-primary"></i>Ajustes del Sistema</h2>
        <p class="text-muted">Administra las variables globales y credenciales API de ControlEscolar.</p>
    </div>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-12 col-xl-3 mb-4">
        <!-- Menú vertical -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="list-group list-group-flush" id="configTabs" role="tablist">
                <a class="list-group-item list-group-item-action active fw-bold py-3 border-bottom-0" data-bs-toggle="list" href="#tab-general" role="tab"><i class="fas fa-building me-3 text-secondary"></i>Institución</a>
                <a class="list-group-item list-group-item-action fw-bold py-3 border-bottom-0" data-bs-toggle="list" href="#tab-horarios" role="tab"><i class="fas fa-clock me-3 text-secondary"></i>Horarios Base</a>
                <a class="list-group-item list-group-item-action fw-bold py-3 border-bottom-0" data-bs-toggle="list" href="#tab-whatsapp" role="tab"><i class="fab fa-whatsapp me-3 text-secondary"></i>WhatsApp API</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-9">
        <div class="card shadow-sm border-0 rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="guardar_config">
                <div class="card-body p-5">
                    <div class="tab-content">

                        <!-- General -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <h4 class="mb-4 fw-bold text-dark">Datos Generales</h4>
                            <div class="mb-4">
                                <label class="form-label text-muted fw-bold text-uppercase small">Nombre de la Institución</label>
                                <input type="text" name="config[nombre_institucion]" class="form-control form-control-lg bg-light border-0" value="<?php echo htmlspecialchars($config_data['nombre_institucion'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted fw-bold text-uppercase small">Período Escolar Actual</label>
                                <input type="text" name="config[periodo_escolar_actual]" class="form-control bg-light border-0" placeholder="Ej: 2025-2026" value="<?php echo htmlspecialchars($config_data['periodo_escolar_actual'] ?? ''); ?>">
                                <small class="text-muted">Este valor se usará por defecto en las nuevas inscripciones.</small>
                            </div>

                            <div class="p-4 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-25 mb-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="fw-bold mb-1 text-primary"><i class="fas fa-sitemap me-2"></i>Estructura Académica</h5>
                                        <p class="text-muted small mb-0">Gestiona Niveles, Grados, Secciones y Horarios específicos.</p>
                                    </div>
                                    <a href="estructura.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">Gestionar</a>
                                </div>
                            </div>

                            <hr class="my-4 text-muted">
                            <h5 class="fw-bold mb-3">Preferencias de Interfaz</h5>
                            <div class="row g-4">
                                <div class="col-12 col-md-6">
                                    <label class="form-label text-muted fw-bold text-uppercase small">Sonido de Entrada (Escáner)</label>
                                    <select name="config[sonido_entrada]" class="form-select bg-light border-0 py-2">
                                        <option value="1" <?php echo ($config_data['sonido_entrada'] ?? '') == '1' ? 'selected' : ''; ?>>🔊 Activado</option>
                                        <option value="0" <?php echo ($config_data['sonido_entrada'] ?? '') == '0' ? 'selected' : ''; ?>>🔇 Desactivado</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label text-muted fw-bold text-uppercase small">Sonido de Salida (Escáner)</label>
                                    <select name="config[sonido_salida]" class="form-select bg-light border-0 py-2">
                                        <option value="1" <?php echo ($config_data['sonido_salida'] ?? '') == '1' ? 'selected' : ''; ?>>🔊 Activado</option>
                                        <option value="0" <?php echo ($config_data['sonido_salida'] ?? '') == '0' ? 'selected' : ''; ?>>🔇 Desactivado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Horarios -->
                        <div class="tab-pane fade" id="tab-horarios" role="tabpanel">
                            <h4 class="mb-4 fw-bold text-dark">Horarios Globales de Acceso</h4>
                            <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                                <i class="fas fa-info-circle fs-4 me-3"></i>
                                <div>Define los límites extremos en los que el sistema escolar opera. Esto sirve como protección adicional.</div>
                            </div>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-4 border rounded-4 bg-light">
                                        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-sun me-2"></i>Turno Mañana</h6>
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Apertura de Puertas</label>
                                            <input type="time" name="config[horario_entrada_inicio]" class="form-control" value="<?php echo htmlspecialchars($config_data['horario_entrada_inicio'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label class="form-label text-muted fw-bold small">Cierre de Puertas</label>
                                            <input type="time" name="config[horario_entrada_fin]" class="form-control" value="<?php echo htmlspecialchars($config_data['horario_entrada_fin'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-4 border rounded-4 bg-light">
                                        <h6 class="fw-bold text-warning mb-3"><i class="fas fa-moon me-2"></i>Turno Tarde</h6>
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Apertura de Puertas</label>
                                            <input type="time" name="config[horario_salida_inicio]" class="form-control" value="<?php echo htmlspecialchars($config_data['horario_salida_inicio'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label class="form-label text-muted fw-bold small">Cierre de Puertas</label>
                                            <input type="time" name="config[horario_salida_fin]" class="form-control" value="<?php echo htmlspecialchars($config_data['horario_salida_fin'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WhatsApp -->
                        <div class="tab-pane fade" id="tab-whatsapp" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                                <h4 class="fw-bold mb-0 text-dark"><i class="fab fa-whatsapp text-success me-2"></i>Notificaciones de WhatsApp</h4>
                                <div class="form-check form-switch fs-4">
                                    <input class="form-check-input cursor-pointer" type="checkbox" role="switch" name="config[notificaciones_activas]" value="1" <?php echo ($config_data['notificaciones_activas'] ?? '') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label ms-2 fw-bold text-muted fs-6" style="padding-top: 5px;">Motor de Envíos Activo</label>
                                    <input type="hidden" name="config[notificaciones_activas]" value="0" <?php echo ($config_data['notificaciones_activas'] ?? '') == '1' ? 'disabled' : ''; ?> id="hidden_notif">
                                </div>
                            </div>

                            <!-- SECCIÓN 1: MOTOR LOCAL (BOT) -->
                            <div class="card border-0 bg-light rounded-4 mb-5">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="fw-bold mb-1"><i class="fas fa-robot me-2 text-primary"></i>Motor Local</h5>
                                            <p class="text-muted small">Usa tu propio teléfono vinculado como si fuera WhatsApp Web.</p>
                                        </div>
                                        <div id="bot-status-badge">
                                            <span class="badge bg-secondary px-3 py-2">VERIFICANDO...</span>
                                        </div>
                                    </div>

                                    <div id="bot-qr-container" class="text-center py-4 bg-white rounded-4 shadow-sm mb-3 d-none">
                                        <p class="fw-bold text-primary mb-3">¡Escanea este código con tu celular!</p>
                                        <img id="bot-qr-image" src="" class="img-fluid" style="max-width: 250px;">
                                        <p class="text-muted small mt-3 px-4">Ve a WhatsApp > Dispositivos Vinculados > Vincular un dispositivo.</p>
                                    </div>

                                    <div id="bot-connected-msg" class="alert alert-success border-0 shadow-sm d-none mb-0">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle fs-3 me-3"></i>
                                            <div>
                                                <h6 class="fw-bold mb-0">¡Bot Conectado y Listo!</h6>
                                                <small>El sistema enviará los mensajes automáticamente mientras el programa `bot.js` esté corriendo.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="bot-disconnected-msg" class="alert alert-warning border-0 shadow-sm d-none mb-0">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle fs-3 me-3"></i>
                                            <div>
                                                <h6 class="fw-bold mb-0">Servicio de Fondo Apagado</h6>
                                                <small>Debes ejecutar `node bot.js` en tu terminal para activar el envío de mensajes.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: META CLOUD API -->
                            <div class="p-3 border rounded-4 opacity-50" style="background: #f8f9fa;">
                                <h6 class="fw-bold text-muted mb-3 small"><i class="fas fa-server me-2"></i>OPCIÓN B: Meta Cloud API (Empresarial)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted fw-bold small">Phone Number ID</label>
                                        <input type="text" name="config[whatsapp_phone_id]" class="form-control form-control-sm" placeholder="Opcional" value="<?php echo htmlspecialchars($config_data['whatsapp_phone_id'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted fw-bold small">Access Token</label>
                                        <input type="password" name="config[whatsapp_token]" class="form-control form-control-sm" placeholder="Opcional" value="<?php echo htmlspecialchars($config_data['whatsapp_token'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light p-4 text-end border-0 rounded-bottom-4">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm px-5 fw-bold rounded-pill"><i class="fas fa-save me-2"></i>Aplicar Configuración</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Manejar el hidden input para el checkbox de switch
    document.querySelector('input[type="checkbox"][role="switch"]').addEventListener('change', function() {
        document.getElementById('hidden_notif').disabled = this.checked;
    });

    // Manejo visual de las tabs del menú vertical
    document.addEventListener('DOMContentLoaded', function() {
        const tabLinks = document.querySelectorAll('#configTabs .list-group-item');
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                tabLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
    // Monitoreo del Bot de WhatsApp
    function checkBotStatus() {
        fetch('http://localhost:3001/status')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('bot-status-badge');
                const qrContainer = document.getElementById('bot-qr-container');
                const connectedMsg = document.getElementById('bot-connected-msg');
                const disconnectedMsg = document.getElementById('bot-disconnected-msg');

                // Reset
                qrContainer.classList.add('d-none');
                connectedMsg.classList.add('d-none');
                disconnectedMsg.classList.add('d-none');

                if (data.status === 'INICIANDO') {
                    badge.innerHTML = '<span class="badge bg-info px-3 py-2 text-dark"><i class="fas fa-spinner fa-spin me-2"></i>INICIANDO...</span>';
                } else if (data.status === 'ESPERANDO_QR') {
                    badge.innerHTML = '<span class="badge bg-warning px-3 py-2 text-dark"><i class="fas fa-qrcode me-2"></i>ESPERANDO ESCANEO</span>';
                    qrContainer.classList.remove('d-none');
                    fetch('http://localhost:3001/qr')
                        .then(r => r.json())
                        .then(qrData => {
                            if (qrData.qr) document.getElementById('bot-qr-image').src = qrData.qr;
                        });
                } else if (data.status === 'CONECTADO') {
                    badge.innerHTML = '<span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-2"></i>CONECTADO</span>';
                    connectedMsg.classList.remove('d-none');
                } else {
                    badge.innerHTML = '<span class="badge bg-danger px-3 py-2">DESCONECTADO</span>';
                    disconnectedMsg.classList.remove('d-none');
                }
            })
            .catch(err => {
                document.getElementById('bot-status-badge').innerHTML = '<span class="badge bg-danger px-3 py-2">APAGADO</span>';
                document.getElementById('bot-disconnected-msg').classList.remove('d-none');
                document.getElementById('bot-qr-container').classList.add('d-none');
            });
    }

    // Iniciar monitoreo
    setInterval(checkBotStatus, 3000);
    checkBotStatus();
</script>

<?php require_once '../../includes/footer.php'; ?>