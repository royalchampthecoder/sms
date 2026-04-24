<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response(["success" => false, "error" => "Use POST for this endpoint."], 405);
}

$input = api_input();
$device = require_device_auth($input);

if (!empty($input["device_name"])) {
    db_run("UPDATE devices SET device_name = ? WHERE id = ?", [trim((string) $input["device_name"]), $device["id"]]);
}

$device = update_device_heartbeat($device, ["source" => "register_device"]);

api_response([
    "success" => true,
    "message" => "Device verified successfully.",
    "device" => [
        "device_id" => $device["device_id"],
        "device_name" => $device["device_name"],
        "status" => $device["status"],
        "api_key" => $device["api_key"],
    ],
    "config" => get_device_runtime_config($device),
]);
