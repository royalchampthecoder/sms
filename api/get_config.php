<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

$input = api_input();
$device = require_device_auth($input);
$device = update_device_heartbeat($device, ["source" => "get_config"]);

api_response([
    "success" => true,
    "config" => get_device_runtime_config($device),
    "device" => [
        "device_id" => $device["device_id"],
        "device_name" => $device["device_name"],
        "status" => $device["status"],
        "is_active" => (bool) $device["is_active"],
        "daily_limit" => (int) $device["daily_limit"],
        "sent_today" => (int) $device["sms_sent_today"],
    ],
]);
