<?php
$page_name = 'cobranza';
$page_title = 'Gestión de Cobranza';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

$pdo = db_connect();

// Procesar Registro de Pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_pago') {
    $estudiante_id = intval($_POST['estudiante_id']);
    $monto = floatval($_POST['monto']);
    $moneda = sanitize($_POST['moneda']);
    $metodo = sanitize($_POST['metodo_pago']);
    $referencia = sanitize($_POST['referencia']);
    $concepto = sanitize($_POST['concepto']);
    $mes = intval($_POST['mes_pagado']);
    $anio = intval($_POST['anio_pagado']);
    $fecha = $_POST['fecha_pago'];
    $usuario_id = $_SESSION['usuario_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO pagos (estudiante_id, monto, moneda, metodo_pago, referencia, concepto, mes_pagado, anio_pagado, fecha_pago, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$estudiante_id, $monto, $moneda, $metodo, $referencia, $concepto, $mes, $anio, $fecha, $usuario_id]);
        
        $_SESSION['message'] = "Pago registrado exitosamente.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al registrar pago: " . $e->getMessage();
    }
    header("Location: index.php");
    exit;
}

// Obtener estadísticas rápidas
$mes_actual = date('m');
$anio_actual = date('Y');

// Recaudación del mes (en USD para el ejemplo)
$stmt = $pdo->prepare("SELECT SUM(monto) as total FROM pagos WHERE mes_pagado = ? AND anio_pagado = ? AND moneda = 'USD'");
$stmt->execute([$mes_actual, $anio_actual]);
$total_mes_usd = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pagos WHERE DATE(fecha_pago) = CURDATE()");
$stmt->execute();
$pagos_hoy = $stmt->fetch()['count'] ?? 0;

// Listado de pagos recientes
$stmt = $pdo->query("
    SELECT p.*, e.nombre, e.apellido, u.nombre as registrado_por 
    FROM pagos p 
    JOIN estudiantes e ON p.estudiante_id = e.id 
    LEFT JOIN usuarios u ON p.usuario_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 50
");
$pagos = $stmt->fetchAll();

// Listado de estudiantes para el select
$stmt = $pdo->query("SELECT id, nombre, apellido, cedula_escolar FROM estudiantes WHERE estatus != 'retirado' ORDER BY apellido ASC");
$estudiantes_lista = $stmt->fetchAll();

$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Cobranza y Pagos</h2>
        <p class="text-muted">Administración de ingresos y mensualidades</p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button class="btn btn-success shadow-sm px-4 py-2 fw-bold" onclick="abrirModalPago()">
            <i class="fas fa-plus me-2"></i>Registrar Pago
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4">
        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Metrics Cards -->
<div class="row g-4 mb-5">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold">Recaudación Mes (USD)</h6>
                        <h2 class="mb-0 fw-bold">$<?php echo number_format($total_mes_usd, 2); ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold">Pagos Hoy</h6>
                        <h2 class="mb-0 fw-bold"><?php echo $pagos_hoy; ?></h2>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-receipt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark p-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-dark-50 text-uppercase small fw-bold">Próximos Vencimientos</h6>
                        <h2 class="mb-0 fw-bold">Manual</h2>
                    </div>
                    <div class="bg-dark bg-opacity-10 rounded-circle p-3">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table Section -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white p-4 border-0">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0 fw-bold">Historial de Pagos Recientes</h5>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="buscadorPagos" class="form-control bg-light border-0" placeholder="Buscar por alumno o referencia...">
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Alumno</th>
                    <th>Concepto / Mes</th>
                    <th>Monto</th>
                    <th>Método / Ref</th>
                    <th>Fecha</th>
                    <th class="pe-4 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaPagos">
                <?php if (empty($pagos)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No se han registrado pagos aún.</td></tr>
                <?php else: foreach ($pagos as $p): ?>
                    <tr class="pago-row">
                        <td class="ps-4">
                            <div class="fw-bold"><?php echo $p['apellido'] . ', ' . $p['nombre']; ?></div>
                            <small class="text-muted">Ref: <?php echo $p['referencia'] ?: 'N/A'; ?></small>
                        </td>
                        <td>
                            <div class="badge bg-info bg-opacity-10 text-info px-3"><?php echo $p['concepto']; ?></div>
                            <div class="small mt-1"><?php echo date("F", mktime(0, 0, 0, $p['mes_pagado'], 10)) . ' ' . $p['anio_pagado']; ?></div>
                        </td>
                        <td class="fw-bold text-dark">
                            <?php echo $p['moneda'] === 'USD' ? '$' : 'Bs.'; ?> <?php echo number_format($p['monto'], 2); ?>
                        </td>
                        <td>
                            <div class="small"><?php echo $p['metodo_pago']; ?></div>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></div>
                            <small class="text-muted"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
                        </td>
                        <td class="pe-4 text-end">
                            <button class="btn btn-sm btn-light rounded-circle" title="Ver Recibo"><i class="fas fa-file-invoice"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Registro de Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-cash-register me-2 text-success"></i>Registrar Nuevo Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="registrar_pago">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Seleccionar Estudiante</label>
                            <select name="estudiante_id" id="selectEstudiante" class="form-select bg-light p-3" required>
                                <option value="">- Buscar Estudiante -</option>
                                <?php foreach ($estudiantes_lista as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"><?php echo $e['apellido'] . ', ' . $e['nombre'] . ' (' . $e['cedula_escolar'] . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Concepto</label>
                            <select name="concepto" class="form-select bg-light" required>
                                <option value="Mensualidad">Mensualidad</option>
                                <option value="Inscripción">Inscripción</option>
                                <option value="Seguro">Seguro Escolar</option>
                                <option value="Materiales">Materiales</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Mes</label>
                            <select name="mes_pagado" class="form-select bg-light">
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $mes_actual ? 'selected' : ''; ?>>
                                        <?php echo date("F", mktime(0, 0, 0, $i, 10)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-uppercase">Año</label>
                            <input type="number" name="anio_pagado" class="form-control bg-light" value="<?php echo $anio_actual; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Monto</label>
                            <input type="number" step="0.01" name="monto" class="form-control bg-light" placeholder="0.00" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Moneda</label>
                            <select name="moneda" class="form-select bg-light">
                                <option value="USD">Dólares (USD)</option>
                                <option value="BS">Bolívares (BS)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Fecha de Pago</label>
                            <input type="date" name="fecha_pago" class="form-control bg-light" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Método de Pago</label>
                            <select name="metodo_pago" class="form-select bg-light" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia Bancaria</option>
                                <option value="Pago Móvil">Pago Móvil</option>
                                <option value="Zelle">Zelle / Otros</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Referencia / Observaciones</label>
                            <input type="text" name="referencia" class="form-control bg-light" placeholder="Nro Operación">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-5 shadow-sm fw-bold">Guardar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let modalPago;

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalPago');
    document.body.appendChild(modalEl);
    modalPago = new bootstrap.Modal(modalEl);
    
    // Inicializar Select2 al abrir el modal
    $(modalEl).on('shown.bs.modal', function () {
        $('#selectEstudiante').select2({
            theme: 'bootstrap-5',
            dropdownParent: $(modalEl),
            placeholder: 'Buscar por nombre o cédula...',
            width: '100%'
        });
    });

    // Buscador en tiempo real para la tabla
    document.getElementById('buscadorPagos').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('.pago-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
});

function abrirModalPago() {
    modalPago.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
