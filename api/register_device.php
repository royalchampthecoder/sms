<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "../config.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['device_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "device_id required"
    ]);
    exit;
}

$device_id = trim($data['device_id']);

$stmt = $conn->prepare("SELECT api_key FROM devices WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        "success" => true,
        "api_key" => $row['api_key']
    ]);
    exit;
}

$api_key = bin2hex(random_bytes(16));

$stmt = $conn->prepare("INSERT INTO devices (device_id, api_key, status) VALUES (?, ?, 'offline')");
$stmt->bind_param("ss", $device_id, $api_key);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "api_key" => $api_key
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Failed to register device"
    ]);
}

exit;
?>