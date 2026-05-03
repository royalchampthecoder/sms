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

    $pdo = db();

    // ===============================
    // 🔹 STEP 1: AUTH
    // ===============================

    if (!empty($input['device_id'])) {
        // Existing device
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
        // First-time connection
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE api_key = ? LIMIT 1");
        $stmt->execute([$input['api_key']]);

        $device = $stmt->fetch();

        if (!$device) {
            api_response([
                "success" => false,
                "error" => "Invalid API key"
            ], 401);
        }

        // 🔹 Save device_name (FIRST TIME)
        if (!empty($input['device_name'])) {
            $stmt = $pdo->prepare("
                UPDATE devices 
                SET device_name = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                trim($input['device_name']),
                $device['id']
            ]);

            $device['device_name'] = trim($input['device_name']);
        }

        // 🔹 Save device_id (FIRST TIME)
        if (!empty($input['device_id'])) {
            $stmt = $pdo->prepare("
                UPDATE devices 
                SET device_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $input['device_id'],
                $device['id']
            ]);

            $device['device_id'] = $input['device_id'];
        }
    }

    // ===============================
    // 🔹 STEP 2: HEARTBEAT UPDATE
    // ===============================

    $updatedDevice = update_device_heartbeat($device, [
        "battery"     => $input["battery"] ?? null,
        "network"     => $input["network"] ?? null,
        "app_version" => $input["app_version"] ?? null,
        "device_meta" => $input["device_meta"] ?? null,
    ]);

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