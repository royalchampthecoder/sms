<?php
include "../config.php";
include "auth.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['device_id']) || empty(trim($data['device_id']))) {
    echo json_encode([
        "success" => false,
        "error" => "device_id required"
    ]);
    exit;
}

if (!function_exists('validate')) {
    echo json_encode([
        "success" => false,
        "error" => "validate() function not found in auth.php"
    ]);
    exit;
}

validate($data);

$device_id = trim($data['device_id']);

$stmt = $conn->prepare("SELECT sms_delay, sim_slot FROM config WHERE device_id=? LIMIT 1");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "error" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $device_id);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "error" => "Execute failed: " . $stmt->error
    ]);
    exit;
}

$res = $stmt->get_result();

if ($res === false) {
    echo json_encode([
        "success" => false,
        "error" => "get_result() failed. mysqlnd may not be enabled."
    ]);
    exit;
}

if ($res->num_rows == 0) {
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO config (device_id, sms_delay, sim_slot) VALUES (?, 5, 0)");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "error" => "Prepare insert failed: " . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("s", $device_id);

    if (!$stmt->execute()) {
        echo json_encode([
            "success" => false,
            "error" => "Insert failed: " . $stmt->error
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "sms_delay" => 5,
        "sim_slot" => 0
    ]);
    exit;
}

$row = $res->fetch_assoc();

echo json_encode([
    "success" => true,
    "sms_delay" => (int)$row['sms_delay'],
    "sim_slot" => (int)$row['sim_slot']
]);

$stmt->close();
exit;
?>