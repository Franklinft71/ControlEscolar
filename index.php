<?php
$page_name = 'dashboard';
$page_title = 'Panel de Control';
require_once 'includes/db.php';
require_once 'includes/header.php';

$pdo = db_connect();

// 1. Estadísticas de hoy
$stmt = $pdo->query("SELECT tipo, COUNT(*) as total FROM asistencia WHERE DATE(fecha_hora) = CURDATE() GROUP BY tipo");
$stats_today = ['entrada' => 0, 'salida' => 0];
while ($row = $stmt->fetch()) {
    $stats_today[$row['tipo']] = $row['total'];
}

// 2. Total estudiantes activos y suspendidos
$total_activos = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE estatus = 'activo'")->fetchColumn();
$total_suspendidos = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE estatus = 'suspendido'")->fetchColumn();

// 3. Recaudación del mes (USD)
$mes_actual = date('m');
$anio_actual = date('Y');
$stmt = $pdo->prepare("SELECT SUM(monto) as total FROM pagos WHERE mes_pagado = ? AND anio_pagado = ? AND moneda = 'USD'");
$stmt->execute([$mes_actual, $anio_actual]);
$recaudacion_mes = $stmt->fetchColumn() ?: 0;

// 4. Actividad reciente (Últimos 8 movimientos)
$stmt = $pdo->query("
    SELECT a.*, e.nombre, e.apellido, e.foto 
    FROM asistencia a 
    JOIN estudiantes e ON a.estudiante_id = e.id 
    ORDER BY a.fecha_hora DESC 
    LIMIT 8
");
$actividad = $stmt->fetchAll();

// 5. Datos para Gráfico de Estatus
$stmt = $pdo->query("SELECT estatus, COUNT(*) as total FROM estudiantes GROUP BY estatus");
$estatus_data = $stmt->fetchAll();

// 6. Datos para Gráfico de Asistencia (Últimos 7 días)
$stmt = $pdo->query("
    SELECT DATE(fecha_hora) as fecha, COUNT(*) as total 
    FROM asistencia 
    WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_hora)
    ORDER BY fecha ASC
");
$tendencia_asistencia = $stmt->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Bienvenido, Administrador</h2>
        <p class="text-muted">Resumen operativo de la institución para hoy, <?php echo date('d/m/Y'); ?></p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <div class="btn-group shadow-sm rounded-pill overflow-hidden">
            <a href="modules/rfid/index.php" class="btn btn-primary px-4 fw-bold">
                <i class="fas fa-wifi me-2"></i>Escáner
            </a>
            <a href="modules/estudiantes/index.php?add=1" class="btn btn-dark px-4 fw-bold">
                <i class="fas fa-user-plus me-2"></i>Nuevo Alumno
            </a>
        </div>
    </div>
</div>

<!-- Metrics Row -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 h-100 bg-white">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 text-primary">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $total_activos; ?></h3>
                    <small class="text-muted text-uppercase fw-bold smaller">Activos</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 h-100 bg-white">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 text-success">
                    <i class="fas fa-sign-in-alt fa-2x"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $stats_today['entrada']; ?></h3>
                    <small class="text-muted text-uppercase fw-bold smaller">Entradas Hoy</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 h-100 bg-white">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3 text-warning">
                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0">$<?php echo number_format($recaudacion_mes, 0); ?></h3>
                    <small class="text-muted text-uppercase fw-bold smaller">Ingresos Mes</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 h-100 bg-white">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3 text-danger">
                    <i class="fas fa-user-slash fa-2x"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $total_suspendidos; ?></h3>
                    <small class="text-muted text-uppercase fw-bold smaller">Suspendidos</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Charts Column -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-white p-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-area me-2 text-primary"></i>Tendencia de Asistencia</h5>
                <small class="text-muted">Últimos 7 días</small>
            </div>
            <div class="card-body p-4">
                <canvas id="chartDashboard" height="150"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Status Distribution Column -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-white p-4 border-0">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-pie me-2 text-info"></i>Distribución de Alumnos</h5>
            </div>
            <div class="card-body p-4 pt-0">
                <canvas id="chartEstatus" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Activity -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="card-header bg-white p-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-secondary"></i>Actividad Reciente en Puerta</h5>
                <a href="modules/reportes/index.php" class="btn btn-sm btn-light px-3 rounded-pill fw-bold">Ver Todo</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Estudiante</th>
                            <th>Evento</th>
                            <th>Hora</th>
                            <th class="pe-4">Método</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($actividad)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Esperando lecturas RFID...</td></tr>
                        <?php else: foreach ($actividad as $a): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light me-3 d-flex align-items-center justify-content-center overflow-hidden" style="width: 35px; height: 35px;">
                                        <?php if($a['foto']): ?>
                                            <img src="assets/uploads/estudiantes/<?php echo $a['foto']; ?>" class="w-100 h-100" style="object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-user text-muted small"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="fw-bold"><?php echo $a['apellido'] . ', ' . $a['nombre']; ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $a['tipo'] == 'entrada' ? 'success' : 'warning'; ?> bg-opacity-10 text-<?php echo $a['tipo'] == 'entrada' ? 'success' : ($a['tipo'] == 'salida' ? 'warning' : 'dark'); ?> px-3">
                                    <?php echo ucfirst($a['tipo']); ?>
                                </span>
                            </td>
                            <td><i class="far fa-clock text-muted me-1"></i> <?php echo date('h:i A', strtotime($a['fecha_hora'])); ?></td>
                            <td class="pe-4">
                                <small class="text-muted text-uppercase fw-bold smaller"><?php echo $a['metodo']; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions Panel -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white p-4 h-100">
            <h5 class="fw-bold mb-4">Accesos Rápidos</h5>
            <div class="d-grid gap-3">
                <a href="modules/cobranza/index.php" class="btn btn-outline-light border-secondary text-start p-3 rounded-3 d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-cash-register text-success"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Cobrar Mensualidad</div>
                        <small class="text-white-50">Registrar nuevo ingreso</small>
                    </div>
                </a>
                <a href="modules/configuracion/estructura.php" class="btn btn-outline-light border-secondary text-start p-3 rounded-3 d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fas fa-sitemap text-primary"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Configurar Grados</div>
                        <small class="text-white-50">Mantenimiento escolar</small>
                    </div>
                </a>
                <a href="modules/configuracion/index.php#tab-whatsapp" class="btn btn-outline-light border-secondary text-start p-3 rounded-3 d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="fab fa-whatsapp text-success"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Estatus del Bot</div>
                        <small class="text-white-50">Vincular dispositivo</small>
                    </div>
                </a>
            </div>
            
            <div class="mt-auto pt-4 border-top border-white border-opacity-10 text-center">
                <p class="mb-0 text-white-50 smaller fw-bold text-uppercase">ControlEscolar v1.1.0</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Tendencia (Línea)
    new Chart(document.getElementById('chartDashboard'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($d){ return date('d/m', strtotime($d['fecha'])); }, $tendencia_asistencia)); ?>,
            datasets: [{
                label: 'Movimientos Diarios',
                data: <?php echo json_encode(array_column($tendencia_asistencia, 'total')); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Gráfico de Distribución (Dona)
    new Chart(document.getElementById('chartEstatus'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map('ucfirst', array_column($estatus_data, 'estatus'))); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($estatus_data, 'total')); ?>,
                backgroundColor: ['#0d6efd', '#6c757d', '#dc3545', '#ffc107', '#198754'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>