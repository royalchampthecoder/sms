<?php
declare(strict_types=1);

/**
 * ===============================
 * COMMON CORE FILE (MYSQLI VERSION)
 * ===============================
 * - Uses config.php ($conn)
 * - JSON helpers
 * - Auth helpers
 * - Shared utilities
 */

require_once __DIR__ . "/config.php";

// ===============================
// 🔹 DATABASE INSTANCE
// ===============================
function db(): mysqli {
    global $conn;

    if (!$conn || $conn->connect_error) {
        api_response([
            "success" => false,
            "error" => "Database connection failed"
        ], 500);
    }

    return $conn;
}

// ===============================
// 🔹 JSON INPUT
// ===============================
function api_input(): array {
    $raw = file_get_contents("php://input");

    if (!$raw) return [];

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

// ===============================
// 🔹 JSON RESPONSE
// ===============================
function api_response(array $data, int $status = 200): void {
    http_response_code($status);
    header("Content-Type: application/json");

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===============================
// 🔹 DEVICE AUTH (SAFE)
// ===============================
function require_device_auth(array $input): ?array {
    $conn = db();

    if (empty($input['device_id']) || empty($input['api_key'])) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT * FROM devices 
        WHERE device_id = ? AND api_key = ?
        LIMIT 1
    ");

    if (!$stmt) return null;

    $stmt->bind_param("ss", $input['device_id'], $input['api_key']);
    $stmt->execute();

    $result = $stmt->get_result();
    $device = $result->fetch_assoc();

    return $device ?: null;
}

// ===============================
// 🔹 UPDATE HEARTBEAT
// ===============================
function update_device_heartbeat(array $device, array $data): array {
    $conn = db();

    $stmt = $conn->prepare("
        UPDATE devices SET
            last_seen = NOW(),
            battery = IFNULL(?, battery),
            network = IFNULL(?, network),
            app_version = IFNULL(?, app_version),
            device_meta = IFNULL(?, device_meta)
        WHERE id = ?
    ");

    $battery     = $data['battery'];
    $network     = $data['network'];
    $appVersion  = $data['app_version'];
    $deviceMeta  = $data['device_meta'];
    $id          = $device['id'];

    $stmt->bind_param("ssssi", $battery, $network, $appVersion, $deviceMeta, $id);
    $stmt->execute();

    // 🔹 Return updated row
    $stmt = $conn->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// ===============================
// 🔹 PENDING SMS COUNT
// ===============================
function device_pending_count(int $deviceId): int {
    $conn = db();

    $status = "pending";

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM sms_queue 
        WHERE device_id = ? AND status = ?
    ");

    $stmt->bind_param("is", $deviceId, $status);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    return (int) ($result['total'] ?? 0);
}

// ===============================
// 🔹 DEVICE RUNTIME CONFIG
// ===============================
function get_device_runtime_config(array $device): array {
    return [
        "poll_interval" => 5,
        "max_batch"     => 10,
        "retry_limit"   => 3,
    ];
}