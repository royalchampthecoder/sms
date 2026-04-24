<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response(["success" => false, "error" => "Use POST for this endpoint."], 405);
}

$input = api_input();
$device = require_device_auth($input);
$device = update_device_heartbeat($device, ["source" => "get_messages"]);

$message = fetch_next_device_message($device);

if ($message === null) {
    api_response([
        "success" => true,
        "message" => null,
        "config" => get_device_runtime_config($device),
    ]);
}

api_response([
    "success" => true,
    "message" => [
        "id" => (int) $message["id"],
        "phone" => $message["phone"],
        "display_phone" => display_phone($message["phone"]),
        "message" => $message["rendered_message"] ?: $message["message_text"],
        "language_code" => $message["language_code"],
        "retry_count" => (int) $message["retry_count"],
        "max_retry" => (int) $message["max_retry"],
    ],
    "config" => get_device_runtime_config($device),
]);
