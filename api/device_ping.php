<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

/**
 * ===============================
 * DEVICE PING API (HEARTBEAT)
 * ===============================
 * - Authenticates device
 * - Updates last seen / heartbeat
 * - Returns device info + runtime config
 */

// 🔹 Allow only POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response([
        "success" => false,
        "error" => "Only POST method allowed"
    ], 405);
}

// 🔹 Get JSON input
$input = api_input();

// 🔹 Validate required fields
if (empty($input['device_id']) || empty($input['api_key'])) {
    api_response([
        "success" => false,
        "error" => "device_id and api_key are required"
    ], 400);
}

try {

    // 🔹 Authenticate device
    $device = require_device_auth($input);

    if (!$device) {
        api_response([
            "success" => false,
            "error" => "Unauthorized device"
        ], 401);
    }

    // 🔹 Update heartbeat + optional metadata
    $updatedDevice = update_device_heartbeat($device, [
        "battery"     => $input["battery"] ?? null,
        "network"     => $input["network"] ?? null,
        "app_version" => $input["app_version"] ?? null,
        "device_meta" => $input["device_meta"] ?? null,
    ]);

    // 🔹 Safety fallback (avoid null issues)
    $deviceName = $updatedDevice["device_name"] ?? "Unnamed Device";

    // 🔹 Build response
    $response = [
        "success" => true,

        "device" => [
            "device_id"        => $updatedDevice["device_id"],
            "device_name"      => $deviceName,
            "status"           => $updatedDevice["status"] ?? "unknown",
            "is_active"        => (bool) ($updatedDevice["is_active"] ?? false),
            "daily_limit"      => (int) ($updatedDevice["daily_limit"] ?? 0),
            "sent_today"       => (int) ($updatedDevice["sms_sent_today"] ?? 0),
            "success_today"    => (int) ($updatedDevice["sms_success_today"] ?? 0),
            "failed_today"     => (int) ($updatedDevice["sms_failed_today"] ?? 0),
            "pending_assigned" => device_pending_count((int) $updatedDevice["id"]),
        ],

        "config" => get_device_runtime_config($updatedDevice),
    ];

    api_response($response);

} catch (Exception $e) {

    // 🔴 Handle auth / runtime errors
    api_response([
        "success" => false,
        "error"   => $e->getMessage()
    ], 500);
}