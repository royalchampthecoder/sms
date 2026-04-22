<?php
include "../config.php";

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function formatIndianPhone($phone) {
    $phone = trim($phone);
    $phone = preg_replace('/\D+/', '', $phone);

    if (strlen($phone) === 10) {
        return '+91' . $phone;
    }

    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        return '+' . $phone;
    }

    if (strlen($phone) === 13 && substr($phone, 0, 3) === '+91') {
        return $phone;
    }

    return false;
}

function validateDevice($conn, $deviceId, $apiKey) {
    $stmt = $conn->prepare("SELECT * FROM devices WHERE device_id = ? AND api_key = ? LIMIT 1");
    $stmt->bind_param("ss", $deviceId, $apiKey);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        jsonResponse(["success" => false, "message" => "Unauthorized device"], 401);
    }

    $conn->query("UPDATE devices SET status='online', last_ping=NOW() WHERE device_id='" . $conn->real_escape_string($deviceId) . "'");
}

function getDeviceConfig($conn, $deviceId) {
    $stmt = $conn->prepare("SELECT sms_delay, sim_slot, retry_limit FROM config WHERE device_id = ? LIMIT 1");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }

    return [
        "sms_delay" => 5,
        "sim_slot" => 0,
        "retry_limit" => 2
    ];
}
?>