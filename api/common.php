<?php
declare(strict_types=1);

require_once __DIR__ . "/../dashboard/functions.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

function api_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_input(): array
{
    $raw = file_get_contents("php://input");
    $decoded = json_decode($raw ?: "{}", true);
    if (is_array($decoded) && $decoded !== []) {
        return $decoded;
    }

    return $_POST ?: $_GET;
}

function api_key_from_request(array $input = []): string
{
    $authHeader = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
    if (str_starts_with(strtolower($authHeader), "bearer ")) {
        return trim(substr($authHeader, 7));
    }

    return trim(
        (string) (
            $_SERVER["HTTP_X_API_KEY"]
            ?? $input["api_key"]
            ?? $_GET["api_key"]
            ?? ""
        )
    );
}

function require_api_client(array $input = []): array
{
    $apiKey = api_key_from_request($input);
    if ($apiKey === "") {
        api_response(["success" => false, "error" => "API key is required."], 401);
    }

    $api = get_api_client_by_key($apiKey);
    if ($api === null) {
        api_response(["success" => false, "error" => "Invalid or expired API key."], 401);
    }

    if (!api_request_allowed((int) $api["id"], (int) $api["rate_limit_per_minute"])) {
        api_response(["success" => false, "error" => "API rate limit exceeded."], 429);
    }

    db_run("UPDATE apis SET last_used_at = NOW() WHERE id = ?", [$api["id"]]);
    log_activity("api", "api.request", ["endpoint" => basename($_SERVER["SCRIPT_NAME"] ?? "")], null, (int) $api["id"]);

    return $api;
}

function require_device_auth(array $input = []): array
{
    $deviceId = trim((string) ($input["device_id"] ?? ""));
    $apiKey = trim((string) ($input["api_key"] ?? api_key_from_request($input)));

    if ($deviceId === "" || $apiKey === "") {
        api_response(["success" => false, "error" => "device_id and api_key are required."], 401);
    }

    $device = validate_device_credentials($deviceId, $apiKey);
    if ($device === null) {
        api_response(["success" => false, "error" => "Invalid device credentials."], 401);
    }

    if (!(bool) $device["is_active"]) {
        api_response(["success" => false, "error" => "Device is inactive."], 403);
    }

    return $device;
}
