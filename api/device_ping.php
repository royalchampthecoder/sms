<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response(["success" => false, "error" => "Use POST for this endpoint."], 405);
}

$input = api_input();
$device = require_device_auth($input);
$updatedDevice = update_device_heartbeat($device, [
    "battery" => $input["battery"] ?? null,
    "network" => $input["network"] ?? null,
    "app_version" => $input["app_version"] ?? null,
    "device_meta" => $input["device_meta"] ?? null,
]);

api_response([
    "success" => true,
    "device" => [
        "device_id" => $updatedDevice["device_id"],
        "device_name" => $updatedDevice["device_name"],
        "status" => $updatedDevice["status"],
        "is_active" => (bool) $updatedDevice["is_active"],
        "daily_limit" => (int) $updatedDevice["daily_limit"],
        "sent_today" => (int) $updatedDevice["sms_sent_today"],
        "success_today" => (int) $updatedDevice["sms_success_today"],
        "failed_today" => (int) $updatedDevice["sms_failed_today"],
        "pending_assigned" => device_pending_count((int) $updatedDevice["id"]),
    ],
    "config" => get_device_runtime_config($updatedDevice),
]);
