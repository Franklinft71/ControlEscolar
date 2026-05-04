<?php
/**
 * Motor de Envíos de WhatsApp (Cron Job)
 * 
 * Este script revisa la tabla `notificaciones_log` en busca de mensajes en estado
 * 'pendiente' y utiliza la API Oficial de Meta (Facebook Cloud API) para enviarlos
 * por WhatsApp de forma secuencial.
 * 
 * Se recomienda configurar este script en un CronJob del servidor (Linux) o
 * Tareas Programadas (Windows) para que se ejecute cada minuto:
 * * * * * * php /ruta/absoluta/a/ControlEscolar/api/cron_whatsapp.php
 */

// Ignorar el límite de tiempo de PHP si hay muchos mensajes en cola
set_time_limit(0);
// No mostrar errores HTML en consola
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/db.php';
$pdo = db_connect();

// 1. Obtener los parámetros de configuración
$stmt_conf = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('whatsapp_token', 'whatsapp_phone_id', 'notificaciones_activas')");
$config = [];
while ($row = $stmt_conf->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

// Validar si las notificaciones están encendidas globalmente
if (($config['notificaciones_activas'] ?? '0') !== '1') {
    die(json_encode(['status' => 'apagado', 'message' => 'Las notificaciones de WhatsApp están desactivadas en el sistema.']));
}

$token = trim($config['whatsapp_token'] ?? '');
$phone_id = trim($config['whatsapp_phone_id'] ?? '');

if (empty($token) || empty($phone_id)) {
    die(json_encode(['status' => 'error', 'message' => 'Falta el Token o el Phone ID de Meta en la configuración.']));
}

// 2. Buscar lote de mensajes pendientes (Procesamos de 20 en 20 para no saturar la API)
$stmt = $pdo->query("SELECT id, telefono, mensaje FROM notificaciones_log WHERE estado = 'pendiente' AND tipo = 'whatsapp' LIMIT 20");
$pendientes = $stmt->fetchAll();

if (empty($pendientes)) {
    die(json_encode(['status' => 'completado', 'message' => 'No hay mensajes pendientes en la cola.']));
}

// Endpoint de la Cloud API de Meta
$url = "https://graph.facebook.com/v18.0/" . $phone_id . "/messages";

$enviados = 0;
$fallidos = 0;

// 3. Procesar cada mensaje de la cola
foreach ($pendientes as $msg) {
    
    // Formatear el teléfono: Meta requiere solo números, incluyendo el código de país pero SIN el símbolo '+'.
    // Ejemplo: 584141234567 o 525512345678
    $telefono_formateado = preg_replace('/[^0-9]/', '', $msg['telefono']);
    
    // Payload JSON con la estructura oficial de WhatsApp Cloud API
    /* 
       NOTA IMPORTANTE: Meta exige que el primer mensaje que se le envía a un usuario 
       (antes de que este responda) debe ser utilizando un "Template" (Plantilla pre-aprobada).
       Para simplificar, este script envía un mensaje tipo "texto libre", el cual funciona 
       perfectamente si el representante ya te escribió previamente (abriendo ventana de 24h) 
       o si estás usando un teléfono de pruebas configurado en Meta Developers.
    */
    $payload = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $telefono_formateado,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $msg['mensaje']
        ]
    ];
    
    // Iniciar petición HTTP (cURL)
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    
    // Ejecutar petición
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Evaluar la respuesta de los servidores de Facebook
    if ($httpcode >= 200 && $httpcode < 300) {
        $estado = 'enviado';
        $enviados++;
    } else {
        $estado = 'fallido';
        $fallidos++;
    }
    
    // 4. Actualizar el estado en la base de datos para no repetirlo
    $update = $pdo->prepare("UPDATE notificaciones_log SET estado = ?, respuesta_api = ? WHERE id = ?");
    $update->execute([$estado, $response, $msg['id']]);
}

// Imprimir resultado (Ideal para revisar los logs del CronJob)
echo json_encode([
    'status' => 'completado',
    'procesados' => count($pendientes),
    'enviados' => $enviados,
    'fallidos' => $fallidos
]);
?>
