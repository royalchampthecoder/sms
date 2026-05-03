<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

/**
 * ===============================
 * DEVICE PING API (HEARTBEAT)
 * ===============================
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response([
        "success" => false,
        "error" => "Only POST method allowed"
    ], 405);
}

$input = api_input();

if (empty($input['api_key'])) {
    api_response([
        "success" => false,
        "error" => "api_key is required"
    ], 400);
}

try {

    $conn = db(); // ✅ mysqli

    // ===============================
    // 🔹 STEP 1: AUTH
    // ===============================

    if (!empty($input['device_id'])) {

        $device = require_device_auth($input);

        if (!$device) {
            api_response([
                "success" => false,
                "error" => "Invalid device"
            ], 401);
        }

        if ((int)$device['is_active'] === 0) {
            api_response([
                "success" => false,
                "error" => "Device inactive",
                "code" => "DEVICE_INACTIVE"
            ]);
        }

    } else {

        // 🔹 First-time connection (mysqli version)
        $stmt = $conn->prepare("SELECT * FROM devices WHERE api_key = ? LIMIT 1");

        if (!$stmt) {
            api_response([
                "success" => false,
                "error" => "DB prepare failed"
            ], 500);
        }

        $stmt->bind_param("s", $input['api_key']);
        $stmt->execute();

        $result = $stmt->get_result();
        $device = $result->fetch_assoc();

        if (!$device) {
            api_response([
                "success" => false,
                "error" => "Invalid API key"
            ], 401);
        }

        // 🔹 Save device_name
        if (!empty($input['device_name'])) {
            $name = trim($input['device_name']);

            $stmt = $conn->prepare("
                UPDATE devices 
                SET device_name = ? 
                WHERE id = ?
            ");

            $stmt->bind_param("si", $name, $device['id']);
            $stmt->execute();

            $device['device_name'] = $name;
        }

        // 🔹 Save device_id
        if (!empty($input['device_id'])) {
            $deviceId = $input['device_id'];

            $stmt = $conn->prepare("
                UPDATE devices 
                SET device_id = ? 
                WHERE id = ?
            ");

            $stmt->bind_param("si", $deviceId, $device['id']);
            $stmt->execute();

            $device['device_id'] = $deviceId;
        }
    }

    // ===============================
    // 🔹 STEP 2: HEARTBEAT
    // ===============================

    $updatedDevice = update_device_heartbeat($device, [
        "battery"     => $input["battery"] ?? null,
        "network"     => $input["network"] ?? null,
        "app_version" => $input["app_version"] ?? null,
        "device_meta" => $input["device_meta"] ?? null,
    ]);

    if (!$updatedDevice) {
        api_response([
            "success" => false,
            "error" => "Failed to update device"
        ], 500);
    }

    $deviceName = $updatedDevice["device_name"] ?? "Unnamed Device";

    // ===============================
    // 🔹 RESPONSE
    // ===============================

    api_response([
        "success" => true,
        "device" => [
            "device_id"        => $updatedDevice["device_id"] ?? null,
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
    ]);

} catch (Throwable $e) {

    api_response([
        "success" => false,
        "error"   => $e->getMessage()
    ], 500);
}