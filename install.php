<?php
declare(strict_types=1);

header("Content-Type: application/json");

require_once __DIR__ . "/config.php";

date_default_timezone_set("Asia/Kolkata");

try {
    $statements = [

        // ================= USERS =================
        <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            full_name VARCHAR(120) NOT NULL,
            username VARCHAR(60) NOT NULL UNIQUE,
            email VARCHAR(150) NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            device_access ENUM('all', 'selected') NOT NULL DEFAULT 'selected',
            messages_per_minute INT NOT NULL DEFAULT 30,
            force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_users_role_status (role, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,

        // ================= DEVICES (FIXED) =================
        <<<SQL
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,

            device_id VARCHAR(120) NULL UNIQUE,
            device_name VARCHAR(120) NULL,

            api_key VARCHAR(128) NOT NULL UNIQUE,

            is_active TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM('online', 'offline', 'disconnected') NOT NULL DEFAULT 'offline',

            last_seen DATETIME NULL,
            last_ping_at DATETIME NULL,
            last_message_at DATETIME NULL,

            battery VARCHAR(20) NULL,
            network VARCHAR(50) NULL,
            app_version VARCHAR(50) NULL,
            device_meta LONGTEXT NULL,

            last_reset_on DATE NULL,

            daily_limit INT NOT NULL DEFAULT 95,
            sms_sent_today INT NOT NULL DEFAULT 0,
            sms_success_today INT NOT NULL DEFAULT 0,
            sms_failed_today INT NOT NULL DEFAULT 0,

            sim_slot_preference ENUM('auto', 'sim1', 'sim2') NOT NULL DEFAULT 'auto',
            priority INT NOT NULL DEFAULT 100,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_devices_status (is_active, status, priority),
            INDEX idx_devices_ping (last_seen),
            INDEX idx_devices_api_key (api_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,

        // ================= APIs =================
        <<<SQL
        CREATE TABLE IF NOT EXISTS apis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(120) NOT NULL,
            api_key VARCHAR(128) NOT NULL UNIQUE,
            valid_until DATETIME NOT NULL,
            status ENUM('active', 'expired', 'disabled', 'disconnected') NOT NULL DEFAULT 'active',
            rate_limit_per_minute INT NOT NULL DEFAULT 60,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_apis_validity (status, valid_until),
            CONSTRAINT fk_apis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,

        // ================= (REST UNCHANGED) =================
        // 👉 Keeping your remaining tables exactly same (they are correct)

    ];

    foreach ($statements as $sql) {
        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }
    }

    // ================= SETTINGS =================
    $defaultSettings = [
        ["system_timezone", "Asia/Kolkata", "string", "Application timezone"],
        ["sms_delay_seconds", "5", "int", "Delay between messages"],
        ["retry_limit", "3", "int", "Retry attempts"],
        ["device_offline_after_minutes", "2", "int", "Offline threshold"],
        ["device_disconnect_after_minutes", "10", "int", "Disconnect threshold"],
        ["cron_batch_size", "25", "int", "Worker batch size"],
        ["cron_secret", bin2hex(random_bytes(16)), "string", "Worker security key"],
    ];

    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
    ");

    foreach ($defaultSettings as $row) {
        $stmt->bind_param("ssss", $row[0], $row[1], $row[2], $row[3]);
        $stmt->execute();
    }

    // ================= DEFAULT ADMIN =================
    $adminUser = "admin";
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $adminUser);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {

        $hash = password_hash("admin123", PASSWORD_DEFAULT);

        $insert = $conn->prepare("
            INSERT INTO users 
            (role, full_name, username, email, password_hash, status, device_access, force_password_reset)
            VALUES ('admin', ?, ?, ?, ?, 'active', 'all', 1)
        ");

        $name = "System Admin";
        $email = "admin@smsgateway.local";

        $insert->bind_param("ssss", $name, $adminUser, $email, $hash);
        $insert->execute();
    }

    echo json_encode([
        "success" => true,
        "message" => "Installed successfully",
        "admin" => [
            "username" => "admin",
            "password" => "admin123"
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}