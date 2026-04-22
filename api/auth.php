<?php
include "../config.php";

function validate($data) {
    global $conn;

    header("Content-Type: application/json");

    if (!$data || !isset($data['device_id']) || !isset($data['api_key'])) {
        echo json_encode([
            "success" => false,
            "error" => "Invalid request"
        ]);
        exit;
    }

    $device_id = trim($data['device_id']);
    $api_key   = trim($data['api_key']);

    $stmt = $conn->prepare("SELECT id FROM devices WHERE device_id = ? AND api_key = ?");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "error" => "Prepare failed: " . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("ss", $device_id, $api_key);

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
        echo json_encode([
            "success" => false,
            "error" => "Unauthorized"
        ]);
        exit;
    }

    $stmt->close();
}
?>