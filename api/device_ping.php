<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

/**
 * ===============================
 * DEVICE PING API (HEARTBEAT)
 * ===============================
 * - First time: API key only
 * - Later: API key + device_id
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

// 🔹 API key is REQUIRED
if (empty($input['api_key'])) {
    api_response([
        "success" => false,
        "error" => "api_key is required"
    ], 400);
}

try {

    // ===============================
    // 🔹 STEP 1: AUTHENTICATE DEVICE
    // ===============================

    if (!empty($input['device_id'])) {
        // ✅ Normal flow (device already connected)
        $device = require_device_auth($input);
    } else {
        // ✅ First-time connection (NO device_id)
        $pdo = db();

        $stmt = $pdo->prepare("SELECT * FROM devices WHERE api_key = ? LIMIT 1");
        $stmt->execute([$input['api_key']]);

       $device = $stmt->fetch();

        if (!$device) {
            api_response([
                "success" => false,
                "error" => "Invalid API key"
            ], 401);
        }
    }

    // ===============================
    // 🔹 STEP 2: UPDATE HEARTBEAT
    // ===============================

    $updatedDevice = update_device_heartbeat($device, [
        "battery"     => $input["battery"] ?? null,
        "network"     => $input["network"] ?? null,
        "app_version" => $input["app_version"] ?? null,
        "device_meta" => $input["device_meta"] ?? null,
    ]);

    // 🔹 Safe fallback
    $deviceName = $updatedDevice["device_name"] ?? "Unnamed Device";

    // ===============================
    // 🔹 RESPONSE
    // ===============================
    api_response([
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
    ]);

} catch (Exception $e) {

    api_response([
        "success" => false,
        "error"   => $e->getMessage()
    ], 500);
}