<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

$input = api_input();

if (($input["type"] ?? "") === "device") {
    $device = require_device_auth($input);
    api_response([
        "success" => true,
        "type" => "device",
        "device" => [
            "id" => $device["id"],
            "device_id" => $device["device_id"],
            "device_name" => $device["device_name"],
            "status" => $device["status"],
            "is_active" => (bool) $device["is_active"],
        ],
    ]);
}

$api = require_api_client($input);
api_response([
    "success" => true,
    "type" => "api",
    "api" => [
        "id" => $api["id"],
        "name" => $api["name"],
        "valid_until" => $api["valid_until"],
        "status" => $api["status"],
    ],
]);
