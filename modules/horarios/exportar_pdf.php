<?php
require_once '../../includes/db.php';
$pdo = db_connect();

$seccion_id = $_GET['seccion_id'] ?? 0;
$grado_id = $_GET['grado_id'] ?? 0;

if (!$seccion_id || !$grado_id) {
    die("Faltan parámetros necesarios.");
}

// Obtener nombres de grado y sección
$stmt = $pdo->prepare("SELECT g.nombre as grado, s.nombre as seccion 
                       FROM grados g, secciones s 
                       WHERE g.id = ? AND s.id = ?");
$stmt->execute([$grado_id, $seccion_id]);
$info = $stmt->fetch();

// Obtener bloques horarios
$bloques = $pdo->query("SELECT * FROM bloques_horarios ORDER BY turno, orden")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];

// Obtener horario actual
$horario = [];
$stmt = $pdo->prepare("
    SELECT h.*, m.nombre as materia, m.color, d.nombre as docente_n, d.apellido as docente_a, a.nombre as aula
    FROM horarios_clases h
    JOIN materias m ON h.materia_id = m.id
    JOIN docentes d ON h.docente_id = d.id
    JOIN aulas a ON h.aula_id = a.id
    WHERE h.seccion_id = ? AND h.grado_id = ?
");
$stmt->execute([$seccion_id, $grado_id]);
while ($row = $stmt->fetch()) {
    $key = $row['dia_semana'] . '|' . $row['bloque_id'];
    $horario[$key] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horario - <?php echo $info['grado'] . ' ' . $info['seccion']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-print { border-bottom: 2px solid #0d6efd; margin-bottom: 30px; padding-bottom: 10px; }
        .table-horario { border: 1px solid #dee2e6; table-layout: fixed; }
        .table-horario th { background: #f8f9fa; text-align: center; font-size: 0.85rem; text-transform: uppercase; padding: 12px 5px; }
        .table-horario td { height: 100px; vertical-align: top; padding: 5px; font-size: 0.75rem; border: 1px solid #dee2e6; }
        .bloque-info { font-weight: bold; color: #666; font-size: 0.7rem; border-right: 2px solid #eee; width: 100px !important; }
        .clase-card { 
            height: 100%; 
            border-radius: 6px; 
            padding: 8px; 
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
        }
        .materia-name { font-weight: 800; font-size: 0.85rem; margin-bottom: 4px; line-height: 1.1; }
        .docente-name { font-size: 0.7rem; opacity: 0.9; margin-bottom: 2px; }
        .aula-name { font-style: italic; font-size: 0.7rem; font-weight: bold; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .table-horario td { height: 90px; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container py-4">
        <div class="no-print mb-4 text-center">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Se ha abierto el diálogo de impresión. Seleccione <b>"Guardar como PDF"</b> en su navegador.
                <button class="btn btn-sm btn-secondary ms-3" onclick="window.close()">Cerrar Ventana</button>
            </div>
        </div>

        <div class="header-print d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-primary mb-0">CONTROL ESCOLAR</h2>
                <p class="text-muted mb-0">Planificación Académica Semanal</p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-0"><?php echo $info['grado']; ?></h4>
                <span class="badge bg-primary fs-6">Sección: <?php echo $info['seccion']; ?></span>
            </div>
        </div>

        <table class="table table-horario">
            <thead>
                <tr>
                    <th style="width: 100px;">Bloque</th>
                    <?php foreach ($dias as $d): ?>
                        <th><?php echo $d; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bloques as $b): ?>
                    <tr>
                        <td class="bloque-info">
                            <div class="text-primary"><?php echo $b['nombre']; ?></div>
                            <div class="text-muted small"><?php echo date('H:i', strtotime($b['hora_inicio'])) . ' - ' . date('H:i', strtotime($b['hora_fin'])); ?></div>
                        </td>
                        <?php foreach ($dias as $d): 
                            $key = $d . '|' . $b['id'];
                            $c = $horario[$key] ?? null;
                        ?>
                            <td>
                                <?php if ($c): ?>
                                    <div class="clase-card" style="background-color: <?php echo $c['color']; ?>;">
                                        <div class="materia-name"><?php echo $c['materia']; ?></div>
                                        <div class="docente-name"><?php echo $c['docente_a'] . ', ' . $c['docente_n']; ?></div>
                                        <div class="aula-name"><i class="fas fa-door-open me-1"></i><?php echo $c['aula']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-5 d-flex justify-content-between text-muted small">
            <div>Generado el: <?php echo date('d/m/Y H:i'); ?></div>
            <div>ControlEscolar - Sistema de Gestión Académica</div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-kit-code.js" crossorigin="anonymous"></script>
</body>
</html>
