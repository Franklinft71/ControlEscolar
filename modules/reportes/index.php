<?php
$page_name = 'reportes';
$page_title = 'Reportes Avanzados';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

$pdo = db_connect();

// Filtros comunes
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Inicio de mes por defecto
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$grado_id = isset($_GET['grado_id']) ? intval($_GET['grado_id']) : 0;

// 1. Datos para Reporte de Asistencia
$sql_asistencia = "
    SELECT e.id, e.nombre, e.apellido, e.cedula_escolar, g.nombre as grado, s.nombre as seccion, 
           COUNT(a.id) as total_asistencias, 
           SUM(CASE WHEN a.tipo = 'entrada' THEN 1 ELSE 0 END) as entradas, 
           SUM(CASE WHEN a.tipo = 'salida' THEN 1 ELSE 0 END) as salidas,
           SUM(CASE WHEN a.retardo = 1 THEN 1 ELSE 0 END) as retardos
    FROM estudiantes e 
    LEFT JOIN asistencia a ON e.id = a.estudiante_id AND DATE(a.fecha_hora) BETWEEN ? AND ?
    LEFT JOIN grados g ON e.grado_id = g.id
    LEFT JOIN secciones s ON e.seccion_id = s.id
    WHERE 1=1
";
if ($grado_id) $sql_asistencia .= " AND e.grado_id = $grado_id";
$sql_asistencia .= " GROUP BY e.id ORDER BY e.apellido, e.nombre";

$stmt = $pdo->prepare($sql_asistencia);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$reporte_asistencia = $stmt->fetchAll();

// 2. Datos para Reporte de Cobranza
$stmt_cobranza = $pdo->prepare("
    SELECT p.*, e.nombre, e.apellido 
    FROM pagos p 
    JOIN estudiantes e ON p.estudiante_id = e.id 
    WHERE p.fecha_pago BETWEEN ? AND ? 
    ORDER BY p.fecha_pago DESC
");
$stmt_cobranza->execute([$fecha_inicio, $fecha_fin]);
$reporte_cobranza = $stmt_cobranza->fetchAll();

// 3. Totales para Gráficos
// Asistencia por día
$stmt_grafico_asistencia = $pdo->prepare("
    SELECT DATE(fecha_hora) as fecha, COUNT(*) as total 
    FROM asistencia 
    WHERE DATE(fecha_hora) BETWEEN ? AND ? 
    GROUP BY DATE(fecha_hora) 
    ORDER BY fecha
");
$stmt_grafico_asistencia->execute([$fecha_inicio, $fecha_fin]);
$datos_grafico_asistencia = $stmt_grafico_asistencia->fetchAll();

// Cobranza por día (USD)
$stmt_grafico_cobranza = $pdo->prepare("
    SELECT fecha_pago as fecha, SUM(monto) as total 
    FROM pagos 
    WHERE fecha_pago BETWEEN ? AND ? AND moneda = 'USD'
    GROUP BY fecha_pago 
    ORDER BY fecha
");
$stmt_grafico_cobranza->execute([$fecha_inicio, $fecha_fin]);
$datos_grafico_cobranza = $stmt_grafico_cobranza->fetchAll();

// Grados para el filtro
$grados_filtro = $pdo->query("SELECT * FROM grados ORDER BY nombre")->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Reportes y Estadísticas</h2>
        <p class="text-muted">Análisis de asistencia y flujo de caja</p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <div class="btn-group shadow-sm">
            <a href="exportar_csv.php?reporte=asistencia&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>" class="btn btn-outline-success fw-bold">
                <i class="fas fa-file-csv me-1"></i> CSV Asistencia
            </a>
            <a href="exportar_csv.php?reporte=cobranza&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>" class="btn btn-outline-primary fw-bold">
                <i class="fas fa-file-csv me-1"></i> CSV Pagos
            </a>
            <button class="btn btn-dark fw-bold" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- Filtros Globales -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control bg-light border-0" value="<?php echo $fecha_inicio; ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control bg-light border-0" value="<?php echo $fecha_fin; ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Filtrar por Grado</label>
                <select name="grado_id" class="form-select bg-light border-0">
                    <option value="0">Todos los Grados</option>
                    <?php foreach ($grados_filtro as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>>
                            <?php echo $g['nombre']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                    <i class="fas fa-sync-alt me-2"></i>Actualizar Reportes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-pills mb-4 gap-2" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="pill" data-bs-target="#tab-asistencia" type="button">
            <i class="fas fa-user-check me-2"></i>Asistencia Estudiantil
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="pill" data-bs-target="#tab-cobranza" type="button">
            <i class="fas fa-wallet me-2"></i>Control de Cobranza
        </button>
    </li>
</ul>

<div class="tab-content">
    
    <!-- REPORTE ASISTENCIA -->
    <div class="tab-pane fade show active" id="tab-asistencia">
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white p-4 border-0">
                        <h5 class="mb-0 fw-bold">Tendencia de Asistencia</h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="chartAsistencia" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 h-100">
                    <h5 class="fw-bold mb-4">Resumen del Periodo</h5>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-25 pb-2">
                        <span>Total Registros</span>
                        <span class="fw-bold fs-5"><?php echo array_sum(array_column($reporte_asistencia, 'total_asistencias')); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-25 pb-2">
                        <span>Total Entradas</span>
                        <span class="fw-bold fs-5 text-info"><?php echo array_sum(array_column($reporte_asistencia, 'entradas')); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-25 pb-2">
                        <span>Total Salidas</span>
                        <span class="fw-bold fs-5 text-warning"><?php echo array_sum(array_column($reporte_asistencia, 'salidas')); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Retardos</span>
                        <span class="fw-bold fs-5 text-danger"><?php echo array_sum(array_column($reporte_asistencia, 'retardos')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Alumno</th>
                            <th>Grado / Sección</th>
                            <th class="text-center">Entradas</th>
                            <th class="text-center">Salidas</th>
                            <th class="text-center">Retardos</th>
                            <th class="text-center pe-4">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_asistencia as $r): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $r['apellido'] . ', ' . $r['nombre']; ?></div>
                                <small class="text-muted"><?php echo $r['cedula_escolar']; ?></small>
                            </td>
                            <td><?php echo ($r['grado'] ?? '-') . ' "' . ($r['seccion'] ?? '-') . '"'; ?></td>
                            <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success px-3"><?php echo $r['entradas']; ?></span></td>
                            <td class="text-center"><span class="badge bg-warning bg-opacity-10 text-warning px-3"><?php echo $r['salidas']; ?></span></td>
                            <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger px-3"><?php echo $r['retardos']; ?></span></td>
                            <td class="text-center fw-bold pe-4"><?php echo $r['total_asistencias']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORTE COBRANZA -->
    <div class="tab-pane fade" id="tab-cobranza">
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white p-4 border-0">
                        <h5 class="mb-0 fw-bold">Flujo de Caja Mensual (USD)</h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="chartCobranza" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-4 h-100">
                    <h5 class="fw-bold mb-4">Ingresos Totales</h5>
                    <?php 
                        $total_usd = 0; $total_bs = 0;
                        foreach($reporte_cobranza as $p) {
                            if($p['moneda'] == 'USD') $total_usd += $p['monto']; else $total_bs += $p['monto'];
                        }
                    ?>
                    <div class="mb-4">
                        <h6 class="text-white-50 small text-uppercase">Total Dólares</h6>
                        <h2 class="fw-bold mb-0">$ <?php echo number_format($total_usd, 2); ?></h2>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-white-50 small text-uppercase">Total Bolívares</h6>
                        <h3 class="fw-bold mb-0">Bs. <?php echo number_format($total_bs, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th>Alumno</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th class="pe-4">Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reporte_cobranza)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay registros financieros en este periodo.</td></tr>
                        <?php else: foreach ($reporte_cobranza as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></div>
                            </td>
                            <td><?php echo $p['apellido'] . ', ' . $p['nombre']; ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3"><?php echo $p['concepto']; ?></span>
                                <small class="d-block text-muted mt-1"><?php echo date("F", mktime(0, 0, 0, $p['mes_pagado'], 10)) . ' ' . $p['anio_pagado']; ?></small>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo $p['moneda'] === 'USD' ? '$' : 'Bs.'; ?> <?php echo number_format($p['monto'], 2); ?>
                            </td>
                            <td class="pe-4 small text-muted"><?php echo $p['referencia'] ?: 'Efectivo'; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart Asistencia
    new Chart(document.getElementById('chartAsistencia'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($datos_grafico_asistencia, 'fecha')); ?>,
            datasets: [{
                label: 'Registros diarios',
                data: <?php echo json_encode(array_column($datos_grafico_asistencia, 'total')); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    // Chart Cobranza
    new Chart(document.getElementById('chartCobranza'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($datos_grafico_cobranza, 'fecha')); ?>,
            datasets: [{
                label: 'Ingresos USD',
                data: <?php echo json_encode(array_column($datos_grafico_cobranza, 'total')); ?>,
                backgroundColor: '#198754',
                borderRadius: 8
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
});
</script>

<style>
@media print {
    .btn-group, form, .nav-pills { display: none !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    .flex-grow-1 { margin-left: 0 !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>