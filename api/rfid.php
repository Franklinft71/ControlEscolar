<?php
/**
 * API Endpoint para hardware RFID
 * 
 * Este archivo recibe las peticiones HTTP (GET o POST) desde dispositivos 
 * físicos como NodeMCU (ESP8266) o ESP32 equipados con un lector RFID (ej. RC522).
 */

// Permitir peticiones externas (útil si el hardware usa una red distinta)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Validar que no sea una petición OPTIONS (Pre-flight de CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';
$pdo = db_connect();

// Aceptar el UID tanto por GET (?uid=XYZ) como por POST
$uid = $_REQUEST['uid'] ?? '';
$uid = sanitize($uid);

if (empty($uid)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false, 
        'message' => 'Error: Falta el parámetro UID en la petición'
    ]);
    exit;
}

try {
    // 1. Buscar al estudiante asociado a este UID
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, estatus, nombre_representante, telefono_representante 
        FROM estudiantes 
        WHERE rfid_uid = ? 
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    $estudiante = $stmt->fetch();

    if (!$estudiante) {
        http_response_code(404); // Not Found
        echo json_encode([
            'success' => false, 
            'message' => 'Tarjeta no reconocida (UID: ' . $uid . ')'
        ]);
        exit;
    }

    if ($estudiante['estatus'] !== 'activo') {
        http_response_code(403); // Forbidden
        echo json_encode([
            'success' => false, 
            'message' => 'Acceso denegado: Estudiante inactivo o retirado'
        ]);
        exit;
    }

    // 2. Determinar si está entrando o saliendo
    // Buscamos el último movimiento registrado hoy para este estudiante
    $stmt = $pdo->prepare("
        SELECT tipo, fecha_hora 
        FROM asistencia 
        WHERE estudiante_id = ? AND DATE(fecha_hora) = CURDATE() 
        ORDER BY fecha_hora DESC 
        LIMIT 1
    ");
    $stmt->execute([$estudiante['id']]);
    $ultima_asistencia = $stmt->fetch();

    // Lógica: Si no hay registros hoy o el último fue una 'salida', entonces la nueva acción es 'entrada'
    $tipo_asistencia = (!$ultima_asistencia || $ultima_asistencia['tipo'] === 'salida') ? 'entrada' : 'salida';

    // Opcional: Prevenir "rebotes" (que pase la tarjeta 2 veces seguidas en menos de 1 minuto)
    if ($ultima_asistencia) {
        $ultima_vez = strtotime($ultima_asistencia['fecha_hora']);
        $ahora = time();
        if (($ahora - $ultima_vez) < 60) {
            http_response_code(429); // Too Many Requests
            echo json_encode([
                'success' => false, 
                'message' => 'Por favor espere un momento antes de volver a pasar la tarjeta'
            ]);
            exit;
        }
    }

    // 3. Registrar la Asistencia en la Base de Datos
    $stmt = $pdo->prepare("
        INSERT INTO asistencia (estudiante_id, tipo, fecha_hora, rfid_uid, metodo) 
        VALUES (?, ?, NOW(), ?, 'rfid')
    ");
    $stmt->execute([$estudiante['id'], $tipo_asistencia, $uid]);
    $asistencia_id = $pdo->lastInsertId();

    // 4. (Preparación para WhatsApp) Verificar si la configuración permite enviar mensajes
    $stmt_conf = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'notificaciones_activas'");
    $notificar = $stmt_conf->fetchColumn();

    if ($notificar == '1' && !empty($estudiante['telefono_representante'])) {
        // Dejamos el registro "pendiente" en la tabla notificaciones_log
        // Un script secundario (o esta misma API después) se encargará de enviarlo por cURL
        $mensaje = "Hola " . $estudiante['nombre_representante'] . ". Le informamos que " . 
                   $estudiante['nombre'] . " " . $estudiante['apellido'] . 
                   " acaba de registrar su " . strtoupper($tipo_asistencia) . " al instituto a las " . date('h:i A') . ".";
                   
        $stmt_log = $pdo->prepare("
            INSERT INTO notificaciones_log (estudiante_id, telefono, tipo, mensaje, estado) 
            VALUES (?, ?, 'whatsapp', ?, 'pendiente')
        ");
        $stmt_log->execute([$estudiante['id'], $estudiante['telefono_representante'], $mensaje]);
    }

    // 5. Responder al Hardware con Éxito (El ESP32 leerá este JSON o encenderá un LED verde)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => ucfirst($tipo_asistencia) . ' registrada',
        'data' => [
            'estudiante' => $estudiante['nombre'] . ' ' . $estudiante['apellido'],
            'tipo' => $tipo_asistencia,
            'hora' => date('H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno en la base de datos'
    ]);
}
?>
