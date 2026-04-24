<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response(["success" => false, "error" => "Use POST for this endpoint."], 405);
}

$input = api_input();
$device = require_device_auth($input);
$status = strtolower(trim((string) ($input["status"] ?? "")));

if (!in_array($status, ["sent", "failed"], true)) {
    api_response(["success" => false, "error" => "status must be sent or failed."], 422);
}

$messageId = (int) ($input["message_id"] ?? 0);
if ($messageId <= 0) {
    api_response(["success" => false, "error" => "message_id is required."], 422);
}

$response = update_device_message_status(
    $device,
    $messageId,
    $status,
    trim((string) ($input["note"] ?? $input["error_message"] ?? "")) ?: null,
    trim((string) ($input["external_reference"] ?? "")) ?: null
);

if (!$response["success"]) {
    api_response($response, 422);
}

api_response([
    "success" => true,
    "message_id" => $messageId,
    "status" => $status,
    "final_status" => $response["final_status"] ?? $status,
]);
