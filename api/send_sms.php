<?php
declare(strict_types=1);

require_once __DIR__ . "/common.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    api_response(["success" => false, "error" => "Use POST for this endpoint."], 405);
}

$input = api_input();
$api = require_api_client($input);

$ownerId = (int) ($api["user_id"] ?? 0);
if ($ownerId <= 0) {
    $admin = db_fetch_one("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
    $ownerId = (int) ($admin["id"] ?? 0);
}

$owner = get_user($ownerId);
if ($owner === null || $owner["status"] !== "active") {
    api_response(["success" => false, "error" => "The API key is not linked to an active account."], 403);
}

$message = trim((string) ($input["message"] ?? $input["text"] ?? ""));
$route = (string) ($input["route"] ?? "auto");
$schedule = trim((string) ($input["schedule"] ?? $input["scheduled_at"] ?? ""));

if ($message === "") {
    api_response(["success" => false, "error" => "Message is required."], 422);
}

$allowedRoutes = ["auto", "device", "msg91", "custom_api"];
if (!in_array($route, $allowedRoutes, true)) {
    api_response(["success" => false, "error" => "Invalid route value."], 422);
}

$phones = [];
if (isset($input["phones"]) && is_array($input["phones"])) {
    foreach ($input["phones"] as $value) {
        $normalized = normalize_phone((string) $value);
        if ($normalized !== null) {
            $phones[] = $normalized;
        }
    }
} else {
    $single = $input["phone"] ?? $input["to"] ?? $input["number"] ?? "";
    $phones = split_phone_input((string) $single);
}

if ($phones === []) {
    api_response(["success" => false, "error" => "At least one valid phone number is required."], 422);
}

if ($schedule !== "") {
    try {
        $scheduleAt = new DateTimeImmutable($schedule, new DateTimeZone(APP_TIMEZONE));
        if ($scheduleAt <= now_ist()) {
            api_response(["success" => false, "error" => "Scheduled time must be in the future."], 422);
        }
        $schedule = $scheduleAt->format("Y-m-d H:i:s");
    } catch (Throwable) {
        api_response(["success" => false, "error" => "Invalid schedule date format."], 422);
    }
}

$result = queue_bulk_messages($ownerId, $phones, $message, [
    "api_id" => (int) $api["id"],
    "route_preference" => $route,
    "scheduled_at" => $schedule,
]);

api_response([
    "success" => $result["queued"] !== [],
    "queued_ids" => $result["queued"],
    "queued_count" => count($result["queued"]),
    "errors" => $result["errors"],
]);
