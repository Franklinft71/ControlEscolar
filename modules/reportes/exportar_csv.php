<?php
require_once '../../includes/db.php';
$pdo = db_connect();

$tipo_reporte = $_GET['reporte'] ?? 'asistencia';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_' . $tipo_reporte . '_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

// Añadir BOM para compatibilidad con Excel y UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($tipo_reporte === 'asistencia') {
    fputcsv($output, ['Cédula Escolar', 'Alumno', 'Grado', 'Sección', 'Entradas', 'Salidas', 'Total Registros']);
    
    $stmt = $pdo->prepare("
        SELECT e.cedula_escolar, CONCAT(e.apellido, ', ', e.nombre) as alumno, g.nombre as grado, s.nombre as seccion, 
               SUM(CASE WHEN a.tipo = 'entrada' THEN 1 ELSE 0 END) as entradas, 
               SUM(CASE WHEN a.tipo = 'salida' THEN 1 ELSE 0 END) as salidas,
               COUNT(a.id) as total
        FROM estudiantes e 
        LEFT JOIN asistencia a ON e.id = a.estudiante_id AND DATE(a.fecha_hora) BETWEEN ? AND ?
        LEFT JOIN grados g ON e.grado_id = g.id
        LEFT JOIN secciones s ON e.seccion_id = s.id
        WHERE e.estatus = 'activo'
        GROUP BY e.id 
        ORDER BY e.apellido, e.nombre
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} 
elseif ($tipo_reporte === 'cobranza') {
    fputcsv($output, ['Fecha', 'Alumno', 'Concepto', 'Mes/Año', 'Monto', 'Moneda', 'Método', 'Referencia']);
    
    $stmt = $pdo->prepare("
        SELECT p.fecha_pago, CONCAT(e.apellido, ', ', e.nombre) as alumno, p.concepto, 
               CONCAT(p.mes_pagado, '/', p.anio_pagado) as periodo, p.monto, p.moneda, p.metodo_pago, p.referencia
        FROM pagos p
        JOIN estudiantes e ON p.estudiante_id = e.id
        WHERE p.fecha_pago BETWEEN ? AND ?
        ORDER BY p.fecha_pago DESC
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
