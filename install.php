<?php
declare(strict_types=1);

header("Content-Type: application/json");

require_once __DIR__ . "/config.php";

date_default_timezone_set("Asia/Kolkata");

try {
    $statements = [
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
        <<<SQL
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(120) NOT NULL UNIQUE,
            device_name VARCHAR(120) NOT NULL,
            api_key VARCHAR(128) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM('online', 'offline', 'disconnected') NOT NULL DEFAULT 'offline',
            last_ping_at DATETIME NULL,
            last_message_at DATETIME NULL,
            last_reset_on DATE NULL,
            daily_limit INT NOT NULL DEFAULT 95,
            sms_sent_today INT NOT NULL DEFAULT 0,
            sms_success_today INT NOT NULL DEFAULT 0,
            sms_failed_today INT NOT NULL DEFAULT 0,
            sim_slot_preference ENUM('auto', 'sim1', 'sim2') NOT NULL DEFAULT 'auto',
            priority INT NOT NULL DEFAULT 100,
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_devices_status (is_active, status, priority),
            INDEX idx_devices_ping (last_ping_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
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
        <<<SQL
        CREATE TABLE IF NOT EXISTS custom_gateways (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            endpoint_url VARCHAR(255) NOT NULL,
            http_method ENUM('POST', 'GET') NOT NULL DEFAULT 'POST',
            headers_json LONGTEXT NULL,
            body_template LONGTEXT NULL,
            phone_param VARCHAR(100) NOT NULL DEFAULT 'phone',
            message_param VARCHAR(100) NOT NULL DEFAULT 'message',
            extra_params_json LONGTEXT NULL,
            success_keyword VARCHAR(120) NOT NULL DEFAULT 'success',
            status ENUM('active', 'inactive', 'disconnected', 'expired') NOT NULL DEFAULT 'inactive',
            valid_until DATETIME NULL,
            priority INT NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_custom_gateways_status (status, valid_until, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL UNIQUE,
            setting_value LONGTEXT NULL,
            setting_type ENUM('string', 'int', 'bool', 'json') NOT NULL DEFAULT 'string',
            description VARCHAR(255) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS user_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_device (user_id, device_id),
            CONSTRAINT fk_user_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_devices_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            message_text TEXT NOT NULL,
            route_preference ENUM('auto', 'device', 'msg91', 'custom_api') NOT NULL DEFAULT 'auto',
            language_code VARCHAR(10) NOT NULL DEFAULT 'en',
            scheduled_at DATETIME NULL,
            total_contacts INT NOT NULL DEFAULT 0,
            sent_count INT NOT NULL DEFAULT 0,
            failed_count INT NOT NULL DEFAULT 0,
            status ENUM('draft', 'queued', 'processing', 'completed', 'cancelled', 'scheduled') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_campaigns_user_status (user_id, status),
            CONSTRAINT fk_campaigns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS message_queue (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_id INT NULL,
            campaign_id INT NULL,
            route_preference ENUM('auto', 'device', 'msg91', 'custom_api') NOT NULL DEFAULT 'auto',
            route_used ENUM('device', 'msg91', 'custom_api') NULL,
            device_id INT NULL,
            custom_gateway_id INT NULL,
            phone VARCHAR(20) NOT NULL,
            contact_name VARCHAR(120) NULL,
            language_code VARCHAR(10) NOT NULL DEFAULT 'en',
            message_text TEXT NOT NULL,
            rendered_message TEXT NULL,
            status ENUM('pending', 'scheduled', 'queued_for_device', 'processing', 'sent', 'failed', 'retry_wait', 'cancelled') NOT NULL DEFAULT 'pending',
            retry_count INT NOT NULL DEFAULT 0,
            max_retry INT NOT NULL DEFAULT 2,
            error_message TEXT NULL,
            external_reference VARCHAR(120) NULL,
            msg91_response_id VARCHAR(120) NULL,
            scheduled_at DATETIME NULL,
            next_retry_at DATETIME NULL,
            locked_at DATETIME NULL,
            device_fetched_at DATETIME NULL,
            sent_at DATETIME NULL,
            processed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_message_status_retry (status, scheduled_at, next_retry_at, created_at),
            INDEX idx_message_user_created (user_id, created_at),
            INDEX idx_message_device_status (device_id, status),
            INDEX idx_message_campaign (campaign_id),
            CONSTRAINT fk_message_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_api FOREIGN KEY (api_id) REFERENCES apis(id) ON DELETE SET NULL,
            CONSTRAINT fk_message_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
            CONSTRAINT fk_message_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
            CONSTRAINT fk_message_custom_gateway FOREIGN KEY (custom_gateway_id) REFERENCES custom_gateways(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS campaign_contacts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            name VARCHAR(120) NULL,
            phone VARCHAR(20) NOT NULL,
            language_code VARCHAR(10) NOT NULL DEFAULT 'en',
            queue_id BIGINT NULL,
            status ENUM('pending', 'queued', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_campaign_contacts_campaign (campaign_id, status),
            CONSTRAINT fk_campaign_contacts_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            CONSTRAINT fk_campaign_contacts_queue FOREIGN KEY (queue_id) REFERENCES message_queue(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS message_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT NOT NULL,
            attempt_no INT NOT NULL,
            route ENUM('device', 'msg91', 'custom_api') NOT NULL,
            device_id INT NULL,
            custom_gateway_id INT NULL,
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            INDEX idx_attempts_message (message_id, attempt_no),
            CONSTRAINT fk_message_attempts_message FOREIGN KEY (message_id) REFERENCES message_queue(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_attempts_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
            CONSTRAINT fk_message_attempts_gateway FOREIGN KEY (custom_gateway_id) REFERENCES custom_gateways(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL UNIQUE,
            reason VARCHAR(255) NULL,
            added_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_blacklist_user FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            api_id INT NULL,
            device_id INT NULL,
            log_type ENUM('auth', 'message', 'device', 'api', 'system', 'admin', 'campaign') NOT NULL DEFAULT 'system',
            action VARCHAR(120) NOT NULL,
            details LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logs_type_created (log_type, created_at),
            INDEX idx_logs_user_created (user_id, created_at),
            CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_logs_api FOREIGN KEY (api_id) REFERENCES apis(id) ON DELETE SET NULL,
            CONSTRAINT fk_logs_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
    ];

    foreach ($statements as $sql) {
        $conn->query($sql);
    }

    $defaultSettings = [
        ["system_timezone", "Asia/Kolkata", "string", "Application timezone"],
        ["sms_delay_seconds", "5", "int", "Delay the device should keep between messages"],
        ["sim_slot_preference", "auto", "string", "Global SIM slot preference"],
        ["retry_limit", "3", "int", "Maximum retry attempts per message"],
        ["retry_delay_seconds", "60", "int", "Delay before retrying a failed message"],
        ["rate_limit_per_minute", "30", "int", "Messages per minute per dashboard user"],
        ["api_rate_limit_per_minute", "60", "int", "Requests per minute per API key"],
        ["daily_limit_per_user", "1000", "int", "Daily queue limit per user"],
        ["device_offline_after_minutes", "2", "int", "Heartbeat threshold for offline status"],
        ["device_disconnect_after_minutes", "10", "int", "Heartbeat threshold for disconnected status"],
        ["device_ack_timeout_minutes", "3", "int", "Timeout before retrying a device assigned message"],
        ["cron_batch_size", "25", "int", "Number of messages to process per worker cycle"],
        ["device_counter_last_reset", date("Y-m-d"), "string", "Last IST date when device counters were reset"],
        ["default_signature", "", "string", "Default signature appended to outgoing SMS"],
        ["quiet_hours_enabled", "false", "bool", "Restrict sending during quiet hours"],
        ["quiet_hours_start", "22:00", "string", "Quiet hours start time"],
        ["quiet_hours_end", "06:00", "string", "Quiet hours end time"],
        ["msg91_enabled", "false", "bool", "Enable MSG91 as a delivery route"],
        ["msg91_api_mode", "legacy", "string", "legacy or flow"],
        ["msg91_base_url", "https://api.msg91.com", "string", "Base URL for legacy MSG91 calls"],
        ["msg91_auth_key", "", "string", "MSG91 auth key"],
        ["msg91_sender_id", "", "string", "MSG91 sender id"],
        ["msg91_route", "4", "string", "MSG91 route"],
        ["msg91_country", "91", "string", "MSG91 country code"],
        ["msg91_dlt_template_id", "", "string", "Legacy DLT template id"],
        ["msg91_flow_id", "", "string", "Legacy flow id"],
        ["msg91_template_id", "", "string", "Current MSG91 template id"],
        ["msg91_headers_json", "{}", "json", "Additional MSG91 headers JSON"],
        ["msg91_short_url", "0", "string", "Flow short_url option"],
        ["msg91_short_url_expiry", "", "string", "Flow short_url_expiry option"],
        ["msg91_realtime_response", "1", "string", "Flow realTimeResponse option"],
        ["allow_unicode", "true", "bool", "Allow unicode SMS payloads"],
        ["cron_secret", bin2hex(random_bytes(16)), "string", "Optional secret for secured worker trigger"],
    ];

    $settingStmt = $conn->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            description = VALUES(description)"
    );

    foreach ($defaultSettings as $row) {
        [$key, $value, $type, $description] = $row;
        $settingStmt->bind_param("ssss", $key, $value, $type, $description);
        $settingStmt->execute();
    }
    $settingStmt->close();

    $adminUsername = "admin";
    $adminEmail = "admin@smsgateway.local";
    $adminName = "System Administrator";
    $adminPasswordHash = password_hash("admin123", PASSWORD_DEFAULT);

    $existingAdmin = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $existingAdmin->bind_param("s", $adminUsername);
    $existingAdmin->execute();
    $adminResult = $existingAdmin->get_result();

    if ($adminResult->num_rows === 0) {
        $insertAdmin = $conn->prepare(
            "INSERT INTO users (role, full_name, username, email, password_hash, status, device_access, force_password_reset)
             VALUES ('admin', ?, ?, ?, ?, 'active', 'all', 1)"
        );
        $insertAdmin->bind_param("ssss", $adminName, $adminUsername, $adminEmail, $adminPasswordHash);
        $insertAdmin->execute();
        $insertAdmin->close();
    }
    $existingAdmin->close();

    echo json_encode([
        "success" => true,
        "message" => "SMS Gateway SaaS schema installed successfully.",
        "default_admin" => [
            "username" => "admin",
            "password" => "admin123",
        ],
    ], JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $exception->getMessage(),
    ], JSON_PRETTY_PRINT);
}
