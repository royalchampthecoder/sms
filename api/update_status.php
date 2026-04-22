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

$data = json_decode(file_get_contents("php://input"), true);

$messageId = (int)($data['message_id'] ?? 0);
$phone = trim($data['phone'] ?? '');
$status = trim($data['status'] ?? '');
$note = trim($data['note'] ?? '');
$deviceId = trim($data['device_id'] ?? '');
$apiKey = trim($data['api_key'] ?? '');

if (!$messageId || $phone === '' || $status === '' || $deviceId === '' || $apiKey === '') {
    jsonResponse(["success" => false, "message" => "Missing required fields"], 400);
}

validateDevice($conn, $deviceId, $apiKey);

if (!in_array($status, ['sent', 'failed'])) {
    jsonResponse(["success" => false, "message" => "Invalid status"], 400);
}

if ($status === 'sent') {
    $stmt = $conn->prepare("
        UPDATE messages
        SET status='sent', delivered_at=NOW(), device_id=?
        WHERE id=?
    ");
    $stmt->bind_param("si", $deviceId, $messageId);
    $stmt->execute();

    $stmt = $conn->prepare("
        INSERT INTO delivery_reports (message_id, phone, device_id, status, note)
        VALUES (?, ?, ?, 'sent', ?)
    ");
    $stmt->bind_param("isss", $messageId, $phone, $deviceId, $note);
    $stmt->execute();

    jsonResponse(["success" => true, "message" => "Status updated to sent"]);
}

$stmt = $conn->prepare("
    UPDATE messages
    SET retry_count = retry_count + 1,
        last_attempt_at = NOW(),
        device_id = ?,
        status = CASE
            WHEN retry_count + 1 >= max_retry THEN 'failed'
            ELSE 'pending'
        END
    WHERE id = ?
");
$stmt->bind_param("si", $deviceId, $messageId);
$stmt->execute();

$stmt = $conn->prepare("
    INSERT INTO delivery_reports (message_id, phone, device_id, status, note)
    VALUES (?, ?, ?, 'failed', ?)
");
$stmt->bind_param("isss", $messageId, $phone, $deviceId, $note);
$stmt->execute();

jsonResponse(["success" => true, "message" => "Failure recorded"]);
?>