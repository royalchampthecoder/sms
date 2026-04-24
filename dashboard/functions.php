<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set("Asia/Kolkata");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . "/../config.php";

const APP_TIMEZONE = "Asia/Kolkata";
const STORAGE_PATH = __DIR__ . "/../storage";
const STORAGE_UPLOAD_PATH = STORAGE_PATH . "/uploads";
const STORAGE_TMP_PATH = STORAGE_PATH . "/tmp";

function db(): mysqli
{
    global $conn;
    return $conn;
}

function ensure_storage_paths(): void
{
    foreach ([STORAGE_PATH, STORAGE_UPLOAD_PATH, STORAGE_TMP_PATH] as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

ensure_storage_paths();

function client_ip(): string
{
    return $_SERVER["REMOTE_ADDR"] ?? "";
}

function request_user_agent(): string
{
    return substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 255);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirect_to(string $path): never
{
    header("Location: {$path}");
    exit;
}

function current_url_basename(): string
{
    return basename(parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH) ?: "");
}

function db_param_type(mixed $value): string
{
    return match (true) {
        is_int($value), is_bool($value) => "i",
        is_float($value) => "d",
        default => "s",
    };
}

function db_bind_params(mysqli_stmt $statement, array $params): void
{
    if ($params === []) {
        return;
    }

    $types = "";
    $normalized = [];

    foreach ($params as $param) {
        $types .= db_param_type($param);
        $normalized[] = is_bool($param) ? (int) $param : $param;
    }

    $refs = [];
    foreach ($normalized as $index => $value) {
        $refs[$index] = &$normalized[$index];
    }

    $statement->bind_param($types, ...$refs);
}

function db_execute(string $sql, array $params = []): mysqli_stmt
{
    $statement = db()->prepare($sql);
    db_bind_params($statement, $params);
    $statement->execute();
    return $statement;
}

function db_fetch_one(string $sql, array $params = []): ?array
{
    $statement = db_execute($sql, $params);
    $result = $statement->get_result();
    $row = $result->fetch_assoc() ?: null;
    $statement->close();
    return $row;
}

function db_fetch_all(string $sql, array $params = []): array
{
    $statement = db_execute($sql, $params);
    $result = $statement->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $rows;
}

function db_insert(string $sql, array $params = []): int
{
    $statement = db_execute($sql, $params);
    $id = db()->insert_id;
    $statement->close();
    return $id;
}

function db_run(string $sql, array $params = []): int
{
    $statement = db_execute($sql, $params);
    $affected = $statement->affected_rows;
    $statement->close();
    return $affected;
}

function now_ist(): DateTimeImmutable
{
    return new DateTimeImmutable("now", new DateTimeZone(APP_TIMEZONE));
}

function flash_set(string $type, string $message): void
{
    $_SESSION["flash"] = ["type" => $type, "message" => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST["csrf_token"] ?? "";
    if (!hash_equals($_SESSION["csrf_token"] ?? "", (string) $token)) {
        http_response_code(419);
        exit("Invalid CSRF token.");
    }
}

function current_user(): ?array
{
    $userId = (int) ($_SESSION["user_id"] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    return db_fetch_one("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user["role"] === "admin";
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash_set("warning", "Please sign in to continue.");
        redirect_to("login.php");
    }

    if ($user["status"] !== "active") {
        logout_user();
        flash_set("danger", "Your account is inactive.");
        redirect_to("login.php");
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user["role"] !== "admin") {
        http_response_code(403);
        exit("Admin access required.");
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["user_name"] = $user["full_name"];
    $_SESSION["user_role"] = $user["role"];
    db_run("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user["id"]]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function authenticate_user(string $username, string $password): ?array
{
    $user = db_fetch_one("SELECT * FROM users WHERE username = ? LIMIT 1", [trim($username)]);
    if ($user === null) {
        return null;
    }

    if (!password_verify($password, $user["password_hash"])) {
        return null;
    }

    return $user;
}

function log_activity(string $logType, string $action, array|string|null $details = null, ?int $userId = null, ?int $apiId = null, ?int $deviceId = null): void
{
    $payload = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($details ?? "");
    db_run(
        "INSERT INTO activity_logs (user_id, api_id, device_id, log_type, action, details, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $apiId,
            $deviceId,
            $logType,
            $action,
            $payload,
            client_ip(),
            request_user_agent(),
        ]
    );
}

function app_setting(string $key, mixed $default = null): mixed
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $row = db_fetch_one("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1", [$key]);
    if ($row === null) {
        return $default;
    }

    $value = $row["setting_value"];
    $typed = match ($row["setting_type"]) {
        "int" => (int) $value,
        "bool" => in_array(strtolower((string) $value), ["1", "true", "yes", "on"], true),
        "json" => json_decode((string) $value, true) ?? $default,
        default => $value,
    };

    $cache[$key] = $typed;
    return $typed;
}

function save_setting(string $key, mixed $value, string $type = "string", string $description = ""): void
{
    $stored = match ($type) {
        "bool" => $value ? "true" : "false",
        "json" => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        default => (string) $value,
    };

    db_run(
        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            description = VALUES(description)",
        [$key, $stored, $type, $description]
    );
}

function refresh_setting_cache(): void
{
    // Settings are cached per request. A redirect follows every settings update,
    // so no in-request invalidation is required in the current app flow.
}

function generate_secure_key(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

function normalize_phone(string $phone): ?string
{
    $digits = preg_replace("/\D+/", "", $phone);
    if ($digits === null || $digits === "") {
        return null;
    }

    if (strlen($digits) === 10) {
        $digits = "91" . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === "0") {
        $digits = "91" . substr($digits, 1);
    } elseif (str_starts_with($digits, "0091")) {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) < 10 || strlen($digits) > 15) {
        return null;
    }

    return $digits;
}

function display_phone(string $phone): string
{
    $phone = normalize_phone($phone) ?? $phone;
    return "+" . ltrim($phone, "+");
}

function split_phone_input(string $input): array
{
    $raw = preg_split("/[\s,;]+/", trim($input)) ?: [];
    $phones = [];
    foreach ($raw as $item) {
        $normalized = normalize_phone($item);
        if ($normalized !== null) {
            $phones[] = $normalized;
        }
    }

    return array_values(array_unique($phones));
}

function message_is_unicode(string $message): bool
{
    return !mb_check_encoding($message, "ASCII");
}

function apply_signature(string $message): string
{
    $signature = trim((string) app_setting("default_signature", ""));
    if ($signature === "") {
        return $message;
    }

    return rtrim($message) . PHP_EOL . $signature;
}

function is_phone_blacklisted(string $phone): bool
{
    return db_fetch_one("SELECT id FROM blacklist WHERE phone = ? LIMIT 1", [$phone]) !== null;
}

function user_can_use_device(int $userId, int $deviceId): bool
{
    $user = db_fetch_one("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
    if ($user === null) {
        return false;
    }

    if ($user["role"] === "admin" || $user["device_access"] === "all") {
        return true;
    }

    return db_fetch_one(
        "SELECT id FROM user_devices WHERE user_id = ? AND device_id = ? LIMIT 1",
        [$userId, $deviceId]
    ) !== null;
}

function quiet_hours_enabled(): bool
{
    return (bool) app_setting("quiet_hours_enabled", false);
}

function is_quiet_hours_now(?DateTimeImmutable $at = null): bool
{
    if (!quiet_hours_enabled()) {
        return false;
    }

    $at ??= now_ist();
    $start = trim((string) app_setting("quiet_hours_start", "22:00"));
    $end = trim((string) app_setting("quiet_hours_end", "06:00"));

    $today = $at->format("Y-m-d");
    $startTime = new DateTimeImmutable($today . " " . $start, new DateTimeZone(APP_TIMEZONE));
    $endTime = new DateTimeImmutable($today . " " . $end, new DateTimeZone(APP_TIMEZONE));

    if ($endTime <= $startTime) {
        return $at >= $startTime || $at < $endTime;
    }

    return $at >= $startTime && $at < $endTime;
}

function next_allowed_send_time(?DateTimeImmutable $from = null): DateTimeImmutable
{
    $from ??= now_ist();
    if (!quiet_hours_enabled()) {
        return $from;
    }

    $start = trim((string) app_setting("quiet_hours_start", "22:00"));
    $end = trim((string) app_setting("quiet_hours_end", "06:00"));
    $today = $from->format("Y-m-d");

    $startTime = new DateTimeImmutable($today . " " . $start, new DateTimeZone(APP_TIMEZONE));
    $endTime = new DateTimeImmutable($today . " " . $end, new DateTimeZone(APP_TIMEZONE));

    if ($endTime <= $startTime) {
        if ($from >= $startTime) {
            return $endTime->modify("+1 day");
        }

        return $endTime;
    }

    if ($from >= $startTime && $from < $endTime) {
        return $endTime;
    }

    return $from;
}

function rate_limit_count_for_user(int $userId): int
{
    $row = db_fetch_one(
        "SELECT COUNT(*) AS total
         FROM message_queue
         WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
        [$userId]
    );

    return (int) ($row["total"] ?? 0);
}

function api_request_allowed(int $apiId, int $limitPerMinute): bool
{
    $row = db_fetch_one(
        "SELECT COUNT(*) AS total
         FROM activity_logs
         WHERE api_id = ? AND log_type = 'api' AND action = 'api.request'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
        [$apiId]
    );

    return ((int) ($row["total"] ?? 0)) < $limitPerMinute;
}

function daily_limit_reached(int $userId): bool
{
    $limit = (int) app_setting("daily_limit_per_user", 1000);
    $row = db_fetch_one(
        "SELECT COUNT(*) AS total
         FROM message_queue
         WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$userId]
    );

    return ((int) ($row["total"] ?? 0)) >= $limit;
}

function list_users(): array
{
    return db_fetch_all(
        "SELECT u.*,
                (SELECT COUNT(*) FROM user_devices ud WHERE ud.user_id = u.id) AS mapped_devices
         FROM users u
         ORDER BY u.created_at DESC"
    );
}

function get_user(int $userId): ?array
{
    return db_fetch_one("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
}

function create_user_account(array $data): int
{
    $passwordHash = password_hash((string) $data["password"], PASSWORD_DEFAULT);
    $id = db_insert(
        "INSERT INTO users (role, full_name, username, email, password_hash, status, device_access, messages_per_minute, force_password_reset)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $data["role"] ?? "user",
            trim((string) $data["full_name"]),
            trim((string) $data["username"]),
            trim((string) ($data["email"] ?? "")) ?: null,
            $passwordHash,
            $data["status"] ?? "active",
            $data["device_access"] ?? "selected",
            (int) ($data["messages_per_minute"] ?? 30),
            !empty($data["force_password_reset"]),
        ]
    );

    log_activity("admin", "user.created", ["user_id" => $id], (int) (current_user()["id"] ?? 0));
    return $id;
}

function update_user_account(int $userId, array $data): void
{
    db_run(
        "UPDATE users
         SET role = ?, full_name = ?, username = ?, email = ?, status = ?, device_access = ?, messages_per_minute = ?, force_password_reset = ?
         WHERE id = ?",
        [
            $data["role"] ?? "user",
            trim((string) $data["full_name"]),
            trim((string) $data["username"]),
            trim((string) ($data["email"] ?? "")) ?: null,
            $data["status"] ?? "active",
            $data["device_access"] ?? "selected",
            (int) ($data["messages_per_minute"] ?? 30),
            !empty($data["force_password_reset"]),
            $userId,
        ]
    );

    log_activity("admin", "user.updated", ["user_id" => $userId], (int) (current_user()["id"] ?? 0));
}

function set_user_password(int $userId, string $password, bool $forceReset = false): void
{
    db_run(
        "UPDATE users SET password_hash = ?, force_password_reset = ? WHERE id = ?",
        [password_hash($password, PASSWORD_DEFAULT), $forceReset, $userId]
    );

    log_activity("admin", "user.password_reset", ["user_id" => $userId], (int) (current_user()["id"] ?? 0));
}

function delete_user_account(int $userId): void
{
    db_run("DELETE FROM users WHERE id = ? AND role <> 'admin'", [$userId]);
    log_activity("admin", "user.deleted", ["user_id" => $userId], (int) (current_user()["id"] ?? 0));
}

function sync_user_devices(int $userId, string $deviceAccess, array $deviceIds): void
{
    db_run("UPDATE users SET device_access = ? WHERE id = ?", [$deviceAccess, $userId]);
    db_run("DELETE FROM user_devices WHERE user_id = ?", [$userId]);

    if ($deviceAccess === "selected") {
        foreach ($deviceIds as $deviceId) {
            db_run("INSERT INTO user_devices (user_id, device_id) VALUES (?, ?)", [$userId, (int) $deviceId]);
        }
    }

    log_activity("admin", "user.devices_synced", ["user_id" => $userId, "mode" => $deviceAccess], (int) (current_user()["id"] ?? 0));
}

function user_device_ids(int $userId): array
{
    $rows = db_fetch_all("SELECT device_id FROM user_devices WHERE user_id = ?", [$userId]);
    return array_map(static fn(array $row): int => (int) $row["device_id"], $rows);
}

function list_devices(?int $viewerUserId = null): array
{
    if ($viewerUserId === null || is_admin()) {
        return db_fetch_all(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM message_queue mq WHERE mq.device_id = d.id AND DATE(mq.created_at) = CURDATE()) AS queued_today
             FROM devices d
             ORDER BY d.priority ASC, d.created_at DESC"
        );
    }

    $user = get_user($viewerUserId);
    if ($user === null) {
        return [];
    }

    if ($user["device_access"] === "all") {
        return db_fetch_all("SELECT * FROM devices ORDER BY priority ASC, created_at DESC");
    }

    return db_fetch_all(
        "SELECT d.*
         FROM devices d
         INNER JOIN user_devices ud ON ud.device_id = d.id
         WHERE ud.user_id = ?
         ORDER BY d.priority ASC, d.created_at DESC",
        [$viewerUserId]
    );
}

function create_device_record(array $data): int
{
    $deviceId = trim((string) $data["device_id"]);
    if ($deviceId === "") {
        $deviceId = "DEV-" . strtoupper(bin2hex(random_bytes(6)));
    }

    $apiKey = trim((string) ($data["api_key"] ?? ""));
    if ($apiKey === "") {
        $apiKey = generate_secure_key();
    }

    $id = db_insert(
        "INSERT INTO devices (device_id, device_name, api_key, is_active, status, daily_limit, sim_slot_preference, priority)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $deviceId,
            trim((string) $data["device_name"]),
            $apiKey,
            !empty($data["is_active"]),
            $data["status"] ?? "offline",
            (int) ($data["daily_limit"] ?? 95),
            $data["sim_slot_preference"] ?? "auto",
            (int) ($data["priority"] ?? 100),
        ]
    );

    log_activity("device", "device.created", ["device_id" => $id], (int) (current_user()["id"] ?? 0), null, $id);
    return $id;
}

function update_device_record(int $deviceDbId, array $data): void
{
    db_run(
        "UPDATE devices
         SET device_name = ?, is_active = ?, daily_limit = ?, sim_slot_preference = ?, priority = ?, status = ?
         WHERE id = ?",
        [
            trim((string) $data["device_name"]),
            !empty($data["is_active"]),
            (int) ($data["daily_limit"] ?? 95),
            $data["sim_slot_preference"] ?? "auto",
            (int) ($data["priority"] ?? 100),
            $data["status"] ?? "offline",
            $deviceDbId,
        ]
    );

    log_activity("device", "device.updated", ["device_id" => $deviceDbId], (int) (current_user()["id"] ?? 0), null, $deviceDbId);
}

function rotate_device_key(int $deviceDbId): string
{
    $key = generate_secure_key();
    db_run("UPDATE devices SET api_key = ? WHERE id = ?", [$key, $deviceDbId]);
    return $key;
}

function delete_device_record(int $deviceDbId): void
{
    db_run("DELETE FROM devices WHERE id = ?", [$deviceDbId]);
    log_activity("device", "device.deleted", ["device_id" => $deviceDbId], (int) (current_user()["id"] ?? 0), null, $deviceDbId);
}

function validate_device_credentials(string $deviceId, string $apiKey): ?array
{
    return db_fetch_one(
        "SELECT * FROM devices WHERE device_id = ? AND api_key = ? LIMIT 1",
        [trim($deviceId), trim($apiKey)]
    );
}

function update_device_heartbeat(array $device, array $metadata = []): array
{
    reset_daily_device_counters_if_needed();

    db_run(
        "UPDATE devices
         SET status = 'online',
             last_ping_at = NOW(),
             metadata_json = ?
         WHERE id = ?",
        [json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $device["id"]]
    );

    log_activity("device", "device.ping", $metadata, null, null, (int) $device["id"]);
    return db_fetch_one("SELECT * FROM devices WHERE id = ? LIMIT 1", [$device["id"]]) ?? $device;
}

function refresh_device_presence(): void
{
    $offlineAfter = (int) app_setting("device_offline_after_minutes", 2);
    $disconnectAfter = (int) app_setting("device_disconnect_after_minutes", 10);

    db()->query(
        "UPDATE devices
         SET status = 'online'
         WHERE is_active = 1
           AND last_ping_at IS NOT NULL
           AND last_ping_at >= DATE_SUB(NOW(), INTERVAL {$offlineAfter} MINUTE)"
    );

    db()->query(
        "UPDATE devices
         SET status = 'offline'
         WHERE is_active = 1
           AND last_ping_at IS NOT NULL
           AND last_ping_at < DATE_SUB(NOW(), INTERVAL {$offlineAfter} MINUTE)
           AND last_ping_at >= DATE_SUB(NOW(), INTERVAL {$disconnectAfter} MINUTE)"
    );

    db()->query(
        "UPDATE devices
         SET status = 'disconnected'
         WHERE last_ping_at IS NULL
            OR last_ping_at < DATE_SUB(NOW(), INTERVAL {$disconnectAfter} MINUTE)"
    );
}

function reset_daily_device_counters_if_needed(): void
{
    $today = now_ist()->format("Y-m-d");
    $lastReset = (string) app_setting("device_counter_last_reset", "");

    if ($lastReset === $today) {
        return;
    }

    db()->query(
        "UPDATE devices
         SET sms_sent_today = 0,
             sms_success_today = 0,
             sms_failed_today = 0,
             last_reset_on = CURDATE()"
    );

    save_setting("device_counter_last_reset", $today, "string", "Last IST date when device counters were reset");
}

function active_devices_exist(): bool
{
    $row = db_fetch_one(
        "SELECT COUNT(*) AS total
         FROM devices
         WHERE is_active = 1 AND status = 'online' AND sms_sent_today < daily_limit"
    );

    return ((int) ($row["total"] ?? 0)) > 0;
}

function available_devices_for_user(int $userId): array
{
    $user = get_user($userId);
    if ($user === null) {
        return [];
    }

    if ($user["role"] === "admin" || $user["device_access"] === "all") {
        return db_fetch_all(
            "SELECT *
             FROM devices
             WHERE is_active = 1
               AND status = 'online'
               AND sms_sent_today < daily_limit
             ORDER BY priority ASC, sms_sent_today ASC, last_ping_at DESC"
        );
    }

    return db_fetch_all(
        "SELECT d.*
         FROM devices d
         INNER JOIN user_devices ud ON ud.device_id = d.id
         WHERE ud.user_id = ?
           AND d.is_active = 1
           AND d.status = 'online'
           AND d.sms_sent_today < d.daily_limit
         ORDER BY d.priority ASC, d.sms_sent_today ASC, d.last_ping_at DESC",
        [$userId]
    );
}

function list_api_clients(): array
{
    return db_fetch_all(
        "SELECT a.*, u.username
         FROM apis a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC"
    );
}

function get_api_client_by_key(string $apiKey): ?array
{
    return db_fetch_one(
        "SELECT *
         FROM apis
         WHERE api_key = ? AND status = 'active' AND valid_until >= NOW()
         LIMIT 1",
        [$apiKey]
    );
}

function create_api_client(array $data): int
{
    $id = db_insert(
        "INSERT INTO apis (user_id, name, api_key, valid_until, status, rate_limit_per_minute)
         VALUES (?, ?, ?, ?, ?, ?)",
        [
            $data["user_id"] ?: null,
            trim((string) $data["name"]),
            trim((string) ($data["api_key"] ?? generate_secure_key())),
            $data["valid_until"],
            $data["status"] ?? "active",
            (int) ($data["rate_limit_per_minute"] ?? 60),
        ]
    );

    log_activity("api", "api_client.created", ["api_id" => $id], (int) (current_user()["id"] ?? 0), $id);
    return $id;
}

function update_api_client(int $apiId, array $data): void
{
    db_run(
        "UPDATE apis
         SET user_id = ?, name = ?, valid_until = ?, status = ?, rate_limit_per_minute = ?
         WHERE id = ?",
        [
            $data["user_id"] ?: null,
            trim((string) $data["name"]),
            $data["valid_until"],
            $data["status"] ?? "active",
            (int) ($data["rate_limit_per_minute"] ?? 60),
            $apiId,
        ]
    );

    log_activity("api", "api_client.updated", ["api_id" => $apiId], (int) (current_user()["id"] ?? 0), $apiId);
}

function delete_api_client(int $apiId): void
{
    db_run("DELETE FROM apis WHERE id = ?", [$apiId]);
    log_activity("api", "api_client.deleted", ["api_id" => $apiId], (int) (current_user()["id"] ?? 0), $apiId);
}

function list_custom_gateways(): array
{
    return db_fetch_all("SELECT * FROM custom_gateways ORDER BY priority ASC, created_at DESC");
}

function get_custom_gateway(int $gatewayId): ?array
{
    return db_fetch_one("SELECT * FROM custom_gateways WHERE id = ? LIMIT 1", [$gatewayId]);
}

function active_custom_gateways(): array
{
    return db_fetch_all(
        "SELECT *
         FROM custom_gateways
         WHERE status = 'active'
           AND (valid_until IS NULL OR valid_until >= NOW())
         ORDER BY priority ASC, created_at ASC"
    );
}

function save_custom_gateway(array $data, ?int $gatewayId = null): int
{
    $payload = [
        trim((string) $data["name"]),
        trim((string) $data["endpoint_url"]),
        $data["http_method"] ?? "POST",
        trim((string) ($data["headers_json"] ?? "{}")),
        trim((string) ($data["body_template"] ?? "")) ?: null,
        trim((string) ($data["phone_param"] ?? "phone")),
        trim((string) ($data["message_param"] ?? "message")),
        trim((string) ($data["extra_params_json"] ?? "{}")),
        trim((string) ($data["success_keyword"] ?? "success")),
        $data["status"] ?? "inactive",
        trim((string) ($data["valid_until"] ?? "")) ?: null,
        (int) ($data["priority"] ?? 100),
    ];

    if ($gatewayId === null) {
        $gatewayId = db_insert(
            "INSERT INTO custom_gateways
             (name, endpoint_url, http_method, headers_json, body_template, phone_param, message_param, extra_params_json, success_keyword, status, valid_until, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $payload
        );

        log_activity("api", "custom_gateway.created", ["gateway_id" => $gatewayId], (int) (current_user()["id"] ?? 0));
        return $gatewayId;
    }

    $payload[] = $gatewayId;
    db_run(
        "UPDATE custom_gateways
         SET name = ?, endpoint_url = ?, http_method = ?, headers_json = ?, body_template = ?, phone_param = ?, message_param = ?, extra_params_json = ?, success_keyword = ?, status = ?, valid_until = ?, priority = ?
         WHERE id = ?",
        $payload
    );

    log_activity("api", "custom_gateway.updated", ["gateway_id" => $gatewayId], (int) (current_user()["id"] ?? 0));
    return $gatewayId;
}

function delete_custom_gateway(int $gatewayId): void
{
    db_run("DELETE FROM custom_gateways WHERE id = ?", [$gatewayId]);
    log_activity("api", "custom_gateway.deleted", ["gateway_id" => $gatewayId], (int) (current_user()["id"] ?? 0));
}

function expire_routing_records(): void
{
    db()->query("UPDATE apis SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");
    db()->query("UPDATE custom_gateways SET status = 'expired' WHERE status = 'active' AND valid_until IS NOT NULL AND valid_until < NOW()");
}

function msg91_configuration_valid(?string &$reason = null): bool
{
    if (!(bool) app_setting("msg91_enabled", false)) {
        $reason = "MSG91 is disabled.";
        return false;
    }

    $authKey = trim((string) app_setting("msg91_auth_key", ""));
    $mode = trim((string) app_setting("msg91_api_mode", "legacy"));

    if ($authKey === "") {
        $reason = "MSG91 auth key is missing.";
        return false;
    }

    if ($mode === "flow") {
        $templateId = trim((string) app_setting("msg91_template_id", ""));
        $flowId = trim((string) app_setting("msg91_flow_id", ""));

        if ($templateId === "" && $flowId === "") {
            $reason = "MSG91 flow mode requires template_id or flow_id.";
            return false;
        }
        return true;
    }

    $senderId = trim((string) app_setting("msg91_sender_id", ""));
    $route = trim((string) app_setting("msg91_route", ""));
    if ($senderId === "" || $route === "") {
        $reason = "MSG91 sender id and route are required for legacy mode.";
        return false;
    }

    return true;
}

function routing_fallback_available_without_msg91(): bool
{
    if (active_devices_exist()) {
        return true;
    }

    return active_custom_gateways() !== [];
}

function queue_message(int $userId, string $phone, string $messageText, array $options = []): array
{
    $phone = normalize_phone($phone) ?? "";
    if ($phone === "") {
        return ["success" => false, "error" => "Invalid phone number."];
    }

    if (is_phone_blacklisted($phone)) {
        return ["success" => false, "error" => "Phone number is blacklisted."];
    }

    if (daily_limit_reached($userId)) {
        return ["success" => false, "error" => "Daily limit reached for this user."];
    }

    if (rate_limit_count_for_user($userId) >= (int) app_setting("rate_limit_per_minute", 30)) {
        return ["success" => false, "error" => "Rate limit exceeded. Please slow down."];
    }

    $status = "pending";
    $scheduledAt = trim((string) ($options["scheduled_at"] ?? ""));
    if ($scheduledAt !== "") {
        $status = "scheduled";
    }

    $id = db_insert(
        "INSERT INTO message_queue
         (user_id, api_id, campaign_id, route_preference, phone, contact_name, language_code, message_text, rendered_message, status, retry_count, max_retry, scheduled_at, next_retry_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)",
        [
            $userId,
            $options["api_id"] ?? null,
            $options["campaign_id"] ?? null,
            $options["route_preference"] ?? "auto",
            $phone,
            trim((string) ($options["contact_name"] ?? "")) ?: null,
            trim((string) ($options["language_code"] ?? "en")) ?: "en",
            trim($messageText),
            apply_signature(trim($messageText)),
            $status,
            (int) ($options["max_retry"] ?? app_setting("retry_limit", 3)),
            $scheduledAt ?: null,
            $scheduledAt ?: null,
        ]
    );

    log_activity("message", "message.queued", ["message_id" => $id], $userId, $options["api_id"] ?? null);
    return ["success" => true, "id" => $id];
}

function queue_bulk_messages(int $userId, array $phones, string $messageText, array $options = []): array
{
    $results = ["queued" => [], "errors" => []];
    foreach ($phones as $phone) {
        $response = queue_message($userId, (string) $phone, $messageText, $options);
        if ($response["success"]) {
            $results["queued"][] = $response["id"];
        } else {
            $results["errors"][] = display_phone((string) $phone) . ": " . $response["error"];
        }
    }

    return $results;
}

function queue_campaign_from_contacts(int $userId, string $name, string $messageText, array $contacts, array $options = []): array
{
    $scheduledAt = trim((string) ($options["scheduled_at"] ?? ""));
    $campaignId = db_insert(
        "INSERT INTO campaigns (user_id, name, message_text, route_preference, language_code, scheduled_at, total_contacts, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            trim($name),
            trim($messageText),
            $options["route_preference"] ?? "auto",
            $options["language_code"] ?? "en",
            $scheduledAt ?: null,
            count($contacts),
            $scheduledAt === "" ? "queued" : "scheduled",
        ]
    );

    $queued = 0;
    $failed = 0;

    foreach ($contacts as $contact) {
        $phone = normalize_phone((string) ($contact["phone"] ?? ""));
        if ($phone === null) {
            $failed++;
            continue;
        }

        $queue = queue_message($userId, $phone, $messageText, [
            "campaign_id" => $campaignId,
            "route_preference" => $options["route_preference"] ?? "auto",
            "language_code" => $contact["language_code"] ?? ($options["language_code"] ?? "en"),
            "contact_name" => $contact["name"] ?? "",
            "scheduled_at" => $scheduledAt,
        ]);

        $status = $queue["success"] ? "queued" : "failed";
        $queuedId = $queue["success"] ? $queue["id"] : null;
        db_run(
            "INSERT INTO campaign_contacts (campaign_id, name, phone, language_code, queue_id, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $campaignId,
                trim((string) ($contact["name"] ?? "")) ?: null,
                $phone,
                $contact["language_code"] ?? ($options["language_code"] ?? "en"),
                $queuedId,
                $status,
                $queue["success"] ? null : $queue["error"],
            ]
        );

        if ($queue["success"]) {
            $queued++;
        } else {
            $failed++;
        }
    }

    db_run(
        "UPDATE campaigns SET sent_count = ?, failed_count = ?, status = ? WHERE id = ?",
        [$queued, $failed, $scheduledAt === "" ? "queued" : "scheduled", $campaignId]
    );

    log_activity("campaign", "campaign.created", ["campaign_id" => $campaignId], $userId);

    return ["campaign_id" => $campaignId, "queued" => $queued, "failed" => $failed];
}

function list_messages(array $filters = [], ?int $viewerUserId = null): array
{
    $where = [];
    $params = [];

    if ($viewerUserId !== null && !is_admin()) {
        $where[] = "mq.user_id = ?";
        $params[] = $viewerUserId;
    } elseif (!empty($filters["user_id"])) {
        $where[] = "mq.user_id = ?";
        $params[] = (int) $filters["user_id"];
    }

    if (!empty($filters["status"])) {
        $where[] = "mq.status = ?";
        $params[] = $filters["status"];
    }

    if (!empty($filters["route_used"])) {
        $where[] = "mq.route_used = ?";
        $params[] = $filters["route_used"];
    }

    if (!empty($filters["search"])) {
        $where[] = "(mq.phone LIKE ? OR mq.message_text LIKE ? OR mq.error_message LIKE ?)";
        $like = "%" . trim((string) $filters["search"]) . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT mq.*,
                   u.username,
                   d.device_name,
                   a.name AS api_name,
                   cg.name AS gateway_name
            FROM message_queue mq
            INNER JOIN users u ON u.id = mq.user_id
            LEFT JOIN devices d ON d.id = mq.device_id
            LEFT JOIN apis a ON a.id = mq.api_id
            LEFT JOIN custom_gateways cg ON cg.id = mq.custom_gateway_id";

    if ($where !== []) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY mq.created_at DESC LIMIT 500";

    return db_fetch_all($sql, $params);
}

function get_message(int $messageId, ?int $viewerUserId = null): ?array
{
    $message = db_fetch_one(
        "SELECT mq.*,
                u.username,
                d.device_name,
                a.name AS api_name,
                cg.name AS gateway_name
         FROM message_queue mq
         INNER JOIN users u ON u.id = mq.user_id
         LEFT JOIN devices d ON d.id = mq.device_id
         LEFT JOIN apis a ON a.id = mq.api_id
         LEFT JOIN custom_gateways cg ON cg.id = mq.custom_gateway_id
         WHERE mq.id = ?
         LIMIT 1",
        [$messageId]
    );

    if ($message === null) {
        return null;
    }

    if ($viewerUserId !== null && !is_admin() && (int) $message["user_id"] !== $viewerUserId) {
        return null;
    }

    $message["attempts"] = db_fetch_all(
        "SELECT ma.*, d.device_name, cg.name AS gateway_name
         FROM message_attempts ma
         LEFT JOIN devices d ON d.id = ma.device_id
         LEFT JOIN custom_gateways cg ON cg.id = ma.custom_gateway_id
         WHERE ma.message_id = ?
         ORDER BY ma.created_at DESC",
        [$messageId]
    );

    return $message;
}

function record_message_attempt(int $messageId, int $attemptNo, string $route, mixed $requestPayload, mixed $responsePayload, string $status, ?string $errorMessage = null, ?int $deviceId = null, ?int $gatewayId = null): void
{
    db_run(
        "INSERT INTO message_attempts
         (message_id, attempt_no, route, device_id, custom_gateway_id, request_payload, response_payload, status, error_message, created_at, completed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [
            $messageId,
            $attemptNo,
            $route,
            $deviceId,
            $gatewayId,
            is_string($requestPayload) ? $requestPayload : json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_string($responsePayload) ? $responsePayload : json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $errorMessage,
        ]
    );
}

function mark_message_sent(int $messageId, string $routeUsed, ?int $deviceId = null, ?int $gatewayId = null, ?string $externalReference = null, ?string $msg91ResponseId = null): void
{
    db_run(
        "UPDATE message_queue
         SET status = 'sent',
             route_used = ?,
             device_id = ?,
             custom_gateway_id = ?,
             external_reference = ?,
             msg91_response_id = ?,
             error_message = NULL,
             sent_at = NOW(),
             processed_at = NOW(),
             updated_at = NOW()
         WHERE id = ?",
        [$routeUsed, $deviceId, $gatewayId, $externalReference, $msg91ResponseId, $messageId]
    );

    if ($deviceId !== null) {
        db_run(
            "UPDATE devices
             SET sms_sent_today = sms_sent_today + 1,
                 sms_success_today = sms_success_today + 1,
                 last_message_at = NOW()
             WHERE id = ?",
            [$deviceId]
        );
    }
}

function schedule_retry_or_fail(array $message, ?string $routeUsed, string $errorMessage, ?int $deviceId = null, ?int $gatewayId = null): string
{
    $nextRetry = now_ist()->modify("+" . (int) app_setting("retry_delay_seconds", 60) . " seconds")->format("Y-m-d H:i:s");
    $retryCount = (int) $message["retry_count"] + 1;
    $maxRetry = (int) $message["max_retry"];
    $finalStatus = $retryCount >= $maxRetry ? "failed" : "retry_wait";

    db_run(
        "UPDATE message_queue
         SET status = ?,
             route_used = ?,
             device_id = ?,
             custom_gateway_id = ?,
             retry_count = ?,
             error_message = ?,
             next_retry_at = ?,
             processed_at = CASE WHEN ? = 'failed' THEN NOW() ELSE processed_at END,
             updated_at = NOW()
         WHERE id = ?",
        [
            $finalStatus,
            $routeUsed,
            $deviceId,
            $gatewayId,
            $retryCount,
            $errorMessage,
            $finalStatus === "retry_wait" ? $nextRetry : null,
            $finalStatus,
            $message["id"],
        ]
    );

    if ($deviceId !== null) {
        db_run(
            "UPDATE devices
             SET sms_sent_today = sms_sent_today + 1,
                 sms_failed_today = sms_failed_today + 1,
                 last_message_at = NOW()
             WHERE id = ?",
            [$deviceId]
        );
    }

    return $finalStatus;
}

function lock_processable_messages(int $limit): array
{
    $limit = max(1, $limit);
    return db_fetch_all(
        "SELECT *
         FROM message_queue
         WHERE (status = 'pending')
            OR (status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW())
            OR (status = 'retry_wait' AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())
         ORDER BY COALESCE(scheduled_at, next_retry_at, created_at) ASC
         LIMIT {$limit}"
    );
}

function assign_message_to_device(array $message, array $device): void
{
    db_run(
        "UPDATE message_queue
         SET status = 'queued_for_device',
             route_used = 'device',
             device_id = ?,
             error_message = NULL,
             locked_at = NOW(),
             updated_at = NOW()
         WHERE id = ?",
        [$device["id"], $message["id"]]
    );

    record_message_attempt(
        (int) $message["id"],
        (int) $message["retry_count"] + 1,
        "device",
        ["device_id" => $device["device_id"], "message_id" => $message["id"]],
        ["status" => "queued_for_device"],
        "queued",
        null,
        (int) $device["id"]
    );
}

function custom_headers_from_json(string $json): array
{
    $headers = json_decode($json, true);
    if (!is_array($headers)) {
        return [];
    }

    $flat = [];
    foreach ($headers as $key => $value) {
        if ($value === null || $value === "") {
            continue;
        }
        $flat[] = $key . ": " . $value;
    }

    return $flat;
}

function send_via_msg91(array $message): array
{
    $reason = null;
    if (!msg91_configuration_valid($reason)) {
        return ["success" => false, "error" => $reason];
    }

    $mode = trim((string) app_setting("msg91_api_mode", "legacy"));
    $authKey = trim((string) app_setting("msg91_auth_key", ""));
    $rendered = $message["rendered_message"] ?: apply_signature($message["message_text"]);
    $unicode = message_is_unicode((string) $rendered) ? 1 : 0;

    if ($mode === "flow") {
        $url = "https://control.msg91.com/api/v5/flow";
        $payload = [
            "recipients" => [
                [
                    "mobiles" => $message["phone"],
                    "MESSAGE" => $rendered,
                ],
            ],
            "unicode" => $unicode,
            "realTimeResponse" => (string) app_setting("msg91_realtime_response", "1"),
        ];

        $templateId = trim((string) app_setting("msg91_template_id", ""));
        $flowId = trim((string) app_setting("msg91_flow_id", ""));
        $senderId = trim((string) app_setting("msg91_sender_id", ""));
        $route = trim((string) app_setting("msg91_route", ""));
        $shortUrl = trim((string) app_setting("msg91_short_url", "0"));
        $shortUrlExpiry = trim((string) app_setting("msg91_short_url_expiry", ""));

        if ($templateId !== "") {
            $payload["template_id"] = $templateId;
        }
        if ($flowId !== "") {
            $payload["flow_id"] = $flowId;
        }
        if ($senderId !== "") {
            $payload["sender"] = $senderId;
        }
        if ($route !== "") {
            $payload["route"] = $route;
        }
        if ($shortUrl !== "") {
            $payload["short_url"] = $shortUrl;
        }
        if ($shortUrlExpiry !== "") {
            $payload["short_url_expiry"] = $shortUrlExpiry;
        }
        if (!empty($message["scheduled_at"])) {
            $payload["schtime"] = $message["scheduled_at"];
        }

        $headers = array_merge(
            [
                "Accept: application/json",
                "Content-Type: application/json",
                "authkey: {$authKey}",
            ],
            custom_headers_from_json((string) app_setting("msg91_headers_json", "{}"))
        );

        $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $url = rtrim((string) app_setting("msg91_base_url", "https://api.msg91.com"), "/") . "/api/v2/sendsms";
        $payload = [
            "mobiles" => $message["phone"],
            "message" => $rendered,
            "sender" => trim((string) app_setting("msg91_sender_id", "")),
            "route" => trim((string) app_setting("msg91_route", "4")),
            "country" => trim((string) app_setting("msg91_country", "91")),
            "unicode" => (string) $unicode,
        ];

        $dltTemplateId = trim((string) app_setting("msg91_dlt_template_id", ""));
        if ($dltTemplateId !== "") {
            $payload["DLT_TE_ID"] = $dltTemplateId;
        }

        if (!empty($message["scheduled_at"])) {
            $payload["schtime"] = $message["scheduled_at"];
        }

        $headers = array_merge(
            [
                "Accept: application/json",
                "authkey: {$authKey}",
            ],
            custom_headers_from_json((string) app_setting("msg91_headers_json", "{}"))
        );

        $requestBody = $payload;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== "") {
        return ["success" => false, "error" => $curlError, "response" => null, "http_code" => $httpCode];
    }

    $decoded = json_decode((string) $responseBody, true);
    $isSuccess = $httpCode >= 200 && $httpCode < 300;
    $externalId = null;

    if (is_array($decoded)) {
        $type = strtolower((string) ($decoded["type"] ?? ""));
        if ($type !== "") {
            $isSuccess = $isSuccess && $type === "success";
        }
        $externalId = (string) ($decoded["message"] ?? $decoded["request_id"] ?? "");
    } else {
        $isSuccess = $isSuccess && stripos((string) $responseBody, "success") !== false;
    }

    return [
        "success" => $isSuccess,
        "error" => $isSuccess ? null : (is_array($decoded) ? json_encode($decoded) : (string) $responseBody),
        "response" => $decoded ?: $responseBody,
        "external_id" => $externalId,
        "http_code" => $httpCode,
        "request" => $payload,
    ];
}

function template_replace(string $template, array $vars): string
{
    $result = $template;
    foreach ($vars as $key => $value) {
        $result = str_replace("{{" . $key . "}}", (string) $value, $result);
    }
    return $result;
}

function send_via_custom_gateway(array $gateway, array $message): array
{
    $headers = custom_headers_from_json((string) ($gateway["headers_json"] ?? "{}"));
    $extraParams = json_decode((string) ($gateway["extra_params_json"] ?? "{}"), true);
    if (!is_array($extraParams)) {
        $extraParams = [];
    }

    $vars = [
        "phone" => $message["phone"],
        "message" => $message["rendered_message"] ?: apply_signature($message["message_text"]),
        "message_json" => json_encode($message["rendered_message"] ?: apply_signature($message["message_text"]), JSON_UNESCAPED_UNICODE),
        "name" => $message["contact_name"] ?? "",
        "message_id" => $message["id"],
    ];

    $url = (string) $gateway["endpoint_url"];
    $method = strtoupper((string) $gateway["http_method"]);
    $bodyTemplate = trim((string) ($gateway["body_template"] ?? ""));

    $payload = $extraParams;
    $payload[(string) $gateway["phone_param"]] = $vars["phone"];
    $payload[(string) $gateway["message_param"]] = $vars["message"];

    $ch = curl_init();
    if ($method === "GET") {
        $url .= (str_contains($url, "?") ? "&" : "?") . http_build_query($payload);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);

        if ($bodyTemplate !== "") {
            $resolvedTemplate = template_replace($bodyTemplate, $vars);
            $decoded = json_decode($resolvedTemplate, true);
            if (is_array($decoded)) {
                $headers[] = "Content-Type: application/json";
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $payload = $decoded;
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $resolvedTemplate);
                $payload = $resolvedTemplate;
            }
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== "") {
        return ["success" => false, "error" => $curlError, "response" => null, "request" => $payload, "http_code" => $httpCode];
    }

    $successKeyword = trim((string) ($gateway["success_keyword"] ?? "success"));
    $success = $httpCode >= 200 && $httpCode < 300;
    if ($successKeyword !== "") {
        $success = $success && stripos((string) $responseBody, $successKeyword) !== false;
    }

    return [
        "success" => $success,
        "error" => $success ? null : (string) $responseBody,
        "response" => $responseBody,
        "request" => $payload,
        "http_code" => $httpCode,
    ];
}

function requeue_stale_device_jobs(): void
{
    $timeoutMinutes = (int) app_setting("device_ack_timeout_minutes", 3);
    $stale = db_fetch_all(
        "SELECT *
         FROM message_queue
         WHERE route_used = 'device'
           AND status IN ('queued_for_device', 'processing')
           AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$timeoutMinutes]
    );

    foreach ($stale as $message) {
        schedule_retry_or_fail($message, "device", "Device acknowledgement timeout.", (int) ($message["device_id"] ?: 0) ?: null);
        log_activity("system", "worker.device_timeout", ["message_id" => $message["id"]]);
    }
}

function choose_route_candidates(array $message): array
{
    $preference = $message["route_preference"] ?? "auto";
    return match ($preference) {
        "device" => ["device", "msg91", "custom_api"],
        "msg91" => ["msg91", "device", "custom_api"],
        "custom_api" => ["custom_api", "device", "msg91"],
        default => ["device", "msg91", "custom_api"],
    };
}

function run_queue_worker(int $limit = 25): array
{
    reset_daily_device_counters_if_needed();
    refresh_device_presence();
    expire_routing_records();
    requeue_stale_device_jobs();

    $summary = [
        "fetched" => 0,
        "queued_for_device" => 0,
        "sent_msg91" => 0,
        "sent_custom_api" => 0,
        "rescheduled" => 0,
        "failed" => 0,
    ];

    $messages = lock_processable_messages($limit);
    $summary["fetched"] = count($messages);

    foreach ($messages as $message) {
        if (is_quiet_hours_now()) {
            $next = next_allowed_send_time()->format("Y-m-d H:i:s");
            db_run(
                "UPDATE message_queue SET status = 'scheduled', scheduled_at = ?, updated_at = NOW() WHERE id = ?",
                [$next, $message["id"]]
            );
            $summary["rescheduled"]++;
            continue;
        }

        $sent = false;
        foreach (choose_route_candidates($message) as $route) {
            if ($route === "device") {
                $devices = available_devices_for_user((int) $message["user_id"]);
                if ($devices !== []) {
                    assign_message_to_device($message, $devices[0]);
                    $summary["queued_for_device"]++;
                    $sent = true;
                    break;
                }
                continue;
            }

            if ($route === "msg91") {
                $response = send_via_msg91($message);
                record_message_attempt((int) $message["id"], (int) $message["retry_count"] + 1, "msg91", $response["request"] ?? [], $response["response"] ?? null, $response["success"] ? "sent" : "failed", $response["error"] ?? null);
                if ($response["success"]) {
                    mark_message_sent((int) $message["id"], "msg91", null, null, $response["external_id"] ?? null, $response["external_id"] ?? null);
                    $summary["sent_msg91"]++;
                    $sent = true;
                    break;
                }
                continue;
            }

            if ($route === "custom_api") {
                $gateways = active_custom_gateways();
                if ($gateways === []) {
                    continue;
                }

                $gateway = $gateways[0];
                $response = send_via_custom_gateway($gateway, $message);
                record_message_attempt((int) $message["id"], (int) $message["retry_count"] + 1, "custom_api", $response["request"] ?? [], $response["response"] ?? null, $response["success"] ? "sent" : "failed", $response["error"] ?? null, null, (int) $gateway["id"]);

                if ($response["success"]) {
                    mark_message_sent((int) $message["id"], "custom_api", null, (int) $gateway["id"]);
                    $summary["sent_custom_api"]++;
                    $sent = true;
                    break;
                }
            }
        }

        if (!$sent) {
            $newStatus = schedule_retry_or_fail($message, $message["route_used"] ?: null, "No delivery route was available.");
            if ($newStatus === "failed") {
                $summary["failed"]++;
            } else {
                $summary["rescheduled"]++;
            }
        }
    }

    log_activity("system", "worker.run", $summary);
    return $summary;
}

function device_pending_count(int $deviceDbId): int
{
    $row = db_fetch_one(
        "SELECT COUNT(*) AS total
         FROM message_queue
         WHERE device_id = ? AND status = 'queued_for_device'",
        [$deviceDbId]
    );

    return (int) ($row["total"] ?? 0);
}

function get_device_runtime_config(array $device): array
{
    return [
        "sms_delay_seconds" => (int) app_setting("sms_delay_seconds", 5),
        "retry_limit" => (int) app_setting("retry_limit", 3),
        "sim_slot_preference" => $device["sim_slot_preference"] !== "auto"
            ? $device["sim_slot_preference"]
            : app_setting("sim_slot_preference", "auto"),
        "quiet_hours_enabled" => quiet_hours_enabled(),
        "quiet_hours_start" => app_setting("quiet_hours_start", "22:00"),
        "quiet_hours_end" => app_setting("quiet_hours_end", "06:00"),
        "signature" => app_setting("default_signature", ""),
    ];
}

function fetch_next_device_message(array $device): ?array
{
    $message = db_fetch_one(
        "SELECT *
         FROM message_queue
         WHERE device_id = ? AND status = 'queued_for_device'
         ORDER BY created_at ASC
         LIMIT 1",
        [$device["id"]]
    );

    if ($message === null) {
        return null;
    }

    db_run(
        "UPDATE message_queue
         SET status = 'processing', device_fetched_at = NOW(), updated_at = NOW()
         WHERE id = ?",
        [$message["id"]]
    );

    return db_fetch_one("SELECT * FROM message_queue WHERE id = ? LIMIT 1", [$message["id"]]);
}

function update_device_message_status(array $device, int $messageId, string $status, ?string $note = null, ?string $externalReference = null): array
{
    $message = db_fetch_one(
        "SELECT * FROM message_queue WHERE id = ? AND device_id = ? LIMIT 1",
        [$messageId, $device["id"]]
    );

    if ($message === null) {
        return ["success" => false, "error" => "Message not found for this device."];
    }

    if ($status === "sent") {
        record_message_attempt($messageId, (int) $message["retry_count"] + 1, "device", ["device_id" => $device["device_id"]], ["status" => "sent", "note" => $note], "sent", null, (int) $device["id"]);
        mark_message_sent($messageId, "device", (int) $device["id"], null, $externalReference);
        log_activity("device", "message.sent", ["message_id" => $messageId], null, null, (int) $device["id"]);
        return ["success" => true];
    }

    record_message_attempt($messageId, (int) $message["retry_count"] + 1, "device", ["device_id" => $device["device_id"]], ["status" => "failed", "note" => $note], "failed", $note, (int) $device["id"]);
    $newStatus = schedule_retry_or_fail($message, "device", $note ?: "Device reported failure.", (int) $device["id"]);
    log_activity("device", "message.failed", ["message_id" => $messageId, "status" => $newStatus], null, null, (int) $device["id"]);
    return ["success" => true, "final_status" => $newStatus];
}

function parse_contacts_csv(string $path): array
{
    $handle = fopen($path, "r");
    if ($handle === false) {
        return [];
    }

    $header = fgetcsv($handle);
    if (!is_array($header)) {
        fclose($handle);
        return [];
    }

    $normalizedHeader = array_map(static fn(string $item): string => strtolower(trim($item)), $header);
    $nameIndex = array_search("name", $normalizedHeader, true);
    $phoneIndex = array_search("phone", $normalizedHeader, true);

    if ($phoneIndex === false) {
        $phoneIndex = array_search("mobile", $normalizedHeader, true);
    }

    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        $phone = $phoneIndex !== false && isset($data[$phoneIndex]) ? normalize_phone((string) $data[$phoneIndex]) : null;
        if ($phone === null) {
            continue;
        }

        $rows[] = [
            "name" => $nameIndex !== false && isset($data[$nameIndex]) ? trim((string) $data[$nameIndex]) : "",
            "phone" => $phone,
            "language_code" => "en",
        ];
    }

    fclose($handle);
    return $rows;
}

function sample_csv_contents(): string
{
    return "name,phone\nJohn Doe,9876543210\nAsha Sharma,919876543211\n";
}

function dashboard_stats(?int $userId = null): array
{
    if ($userId === null || is_admin()) {
        $totals = db_fetch_one(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_users,
                (SELECT COUNT(*) FROM devices) AS total_devices,
                (SELECT COUNT(*) FROM devices WHERE status = 'online' AND is_active = 1) AS online_devices,
                (SELECT COUNT(*) FROM devices WHERE status <> 'online' OR is_active = 0) AS offline_devices,
                (SELECT COUNT(*) FROM apis WHERE status = 'active' AND valid_until >= NOW()) AS total_apis,
                (SELECT COUNT(*) FROM custom_gateways WHERE status = 'active' AND (valid_until IS NULL OR valid_until >= NOW())) AS total_custom_gateways,
                (SELECT COUNT(*) FROM message_queue WHERE DATE(created_at) = CURDATE() AND status = 'sent') AS sent_today,
                (SELECT COUNT(*) FROM message_queue WHERE DATE(created_at) = CURDATE() AND status = 'failed') AS failed_today"
        ) ?: [];

        $recentActivity = db_fetch_all("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
        $totals["recent_activity"] = $recentActivity;
        return $totals;
    }

    $totals = db_fetch_one(
        "SELECT
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND status = 'sent') AS total_sent,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND status = 'failed') AS total_failed,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND status IN ('pending', 'scheduled', 'retry_wait', 'queued_for_device', 'processing')) AS total_pending,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND DATE(created_at) = CURDATE()) AS queued_today,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND route_used = 'device') AS via_device,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND route_used = 'msg91') AS via_msg91,
            (SELECT COUNT(*) FROM message_queue WHERE user_id = ? AND route_used = 'custom_api') AS via_custom_api",
        [$userId, $userId, $userId, $userId, $userId, $userId, $userId]
    ) ?: [];

    $totals["devices"] = list_devices($userId);
    return $totals;
}

function bootstrap_nav_items(array $user): array
{
    $items = [
        ["href" => "index.php", "label" => "Dashboard", "key" => "index.php"],
        ["href" => "send.php", "label" => "Send SMS", "key" => "send.php"],
        ["href" => "messages.php", "label" => "Messages", "key" => "messages.php"],
        ["href" => "upload.php", "label" => "Bulk Upload", "key" => "upload.php"],
        ["href" => "devices.php", "label" => "Devices", "key" => "devices.php"],
    ];

    if ($user["role"] === "admin") {
        $items[] = ["href" => "admin_users.php", "label" => "Users", "key" => "admin_users.php"];
        $items[] = ["href" => "apis.php", "label" => "APIs", "key" => "apis.php"];
        $items[] = ["href" => "settings.php", "label" => "Settings", "key" => "settings.php"];
        $items[] = ["href" => "worker.php", "label" => "Worker", "key" => "worker.php"];
        $items[] = ["href" => "logs.php", "label" => "Logs", "key" => "logs.php"];
    } else {
        $items[] = ["href" => "settings.php", "label" => "Settings", "key" => "settings.php"];
    }

    return $items;
}

function badge_class_for_status(string $status): string
{
    return match ($status) {
        "active", "online", "sent", "completed" => "badge-soft-success",
        "failed", "inactive", "disconnected", "expired" => "badge-soft-danger",
        "pending", "scheduled", "queued", "queued_for_device", "processing", "retry_wait" => "badge-soft-warning",
        default => "badge-soft-secondary",
    };
}

function route_label(?string $route): string
{
    return match ($route) {
        "msg91" => "MSG91",
        "custom_api" => "Custom API",
        "device" => "Device",
        default => "Auto",
    };
}

function pretty_date(?string $date): string
{
    if ($date === null || $date === "" || $date === "0000-00-00 00:00:00") {
        return "—";
    }

    try {
        return (new DateTimeImmutable($date, new DateTimeZone(APP_TIMEZONE)))->format("d M Y, h:i A");
    } catch (Throwable) {
        return $date;
    }
}

function normalize_datetime_input(?string $input): ?string
{
    $input = trim((string) $input);
    if ($input === "") {
        return null;
    }

    try {
        return (new DateTimeImmutable($input, new DateTimeZone(APP_TIMEZONE)))->format("Y-m-d H:i:s");
    } catch (Throwable) {
        return null;
    }
}
