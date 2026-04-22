<?php
include "../config.php";

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateDevice($conn, $deviceId, $apiKey) {
    $stmt = $conn->prepare("SELECT id FROM devices WHERE device_id=? AND api_key=? LIMIT 1");
    $stmt->bind_param("ss", $deviceId, $apiKey);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        jsonResponse(["success" => false, "message" => "Invalid device"], 401);
    }
}

function getDeviceConfig($conn, $deviceId) {
    $stmt = $conn->prepare("SELECT sms_delay, sim_slot, retry_limit FROM config WHERE device_id=? LIMIT 1");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }

    $stmt = $conn->prepare("INSERT INTO config (device_id, sms_delay, sim_slot, retry_limit) VALUES (?, 5, 0, 2)");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();

    return [
        "sms_delay" => 5,
        "sim_slot" => 0,
        "retry_limit" => 2
    ];
}

$data = json_decode(file_get_contents("php://input"), true);

$deviceId = trim($data['device_id'] ?? '');
$apiKey = trim($data['api_key'] ?? '');

if ($deviceId === '' || $apiKey === '') {
    jsonResponse(["success" => false, "message" => "device_id and api_key required"], 400);
}

validateDevice($conn, $deviceId, $apiKey);
$config = getDeviceConfig($conn, $deviceId);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT id, phone, message, retry_count, max_retry
        FROM messages
        WHERE status='pending'
          AND retry_count < max_retry
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $conn->commit();
        jsonResponse([
            "success" => true,
            "message" => null,
            "config" => [
                "sms_delay" => (int)$config['sms_delay'],
                "sim_slot" => (int)$config['sim_slot'],
                "retry_limit" => (int)$config['retry_limit']
            ]
        ]);
    }

    $msg = $res->fetch_assoc();

    $stmt = $conn->prepare("
        UPDATE messages
        SET status='processing', device_id=?, last_attempt_at=NOW()
        WHERE id=? AND status='pending'
    ");
    $stmt->bind_param("si", $deviceId, $msg['id']);
    $stmt->execute();

    $conn->commit();

    jsonResponse([
        "success" => true,
        "message" => [
            "id" => (int)$msg['id'],
            "phone" => $msg['phone'],
            "message" => $msg['message'],
            "retry_count" => (int)$msg['retry_count'],
            "max_retry" => (int)$msg['max_retry']
        ],
        "config" => [
            "sms_delay" => (int)$config['sms_delay'],
            "sim_slot" => (int)$config['sim_slot'],
            "retry_limit" => (int)$config['retry_limit']
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse([
        "success" => false,
        "message" => $e->getMessage()
    ], 500);
}
?>