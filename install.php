<?php
include "config.php";
$conn->set_charset("utf8mb4");

try {
    // Users table with enhanced fields
    $conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        api_key VARCHAR(64) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");

    // Insert default admin user with password_hash
    $adminExists = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if ($adminExists->num_rows == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, email, status) VALUES ('admin', '$hashedPassword', 'admin@smsgateway.com', 'active')");
    }

    // Devices table with enhanced fields
    $conn->query("
    CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(100) UNIQUE NOT NULL,
        device_name VARCHAR(100) NOT NULL,
        api_key VARCHAR(100) UNIQUE NOT NULL,
        last_ping DATETIME NULL,
        status ENUM('online', 'offline', 'disconnected') DEFAULT 'offline',
        is_active BOOLEAN DEFAULT TRUE,
        daily_limit INT DEFAULT 95,
        sms_sent_today INT DEFAULT 0,
        sms_success_today INT DEFAULT 0,
        sms_failed_today INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");

    // User-Device assignment table
    $conn->query("
    CREATE TABLE IF NOT EXISTS user_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        device_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_device (user_id, device_id)
    ) ENGINE=InnoDB
    ");

    // Message queue table (replaces old messages table)
    $conn->query("
    CREATE TABLE IF NOT EXISTS message_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid INT NOT NULL,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        route ENUM('device', 'msg91', 'api') DEFAULT 'device',
        device_id INT NULL,
        api_id INT NULL,
        status ENUM('pending', 'processing', 'sent', 'failed', 'scheduled') DEFAULT 'pending',
        retry_count INT DEFAULT 0,
        max_retry INT DEFAULT 2,
        error_message TEXT NULL,
        scheduled_for DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        processed_at DATETIME NULL,
        FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
        FOREIGN KEY (api_id) REFERENCES apis(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");

    // Legacy messages table (for backward compatibility)
    $conn->query("
    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        device_id VARCHAR(100) NULL,
        retry_count INT NOT NULL DEFAULT 0,
        max_retry INT NOT NULL DEFAULT 2,
        last_attempt_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivered_at DATETIME NULL
    ) ENGINE=InnoDB
    ");

    // Settings table
    $conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'int', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");

    // Insert default settings
    $defaultSettings = [
        ['sms_delay', '5', 'int', 'Delay between SMS in seconds'],
        ['sim_slot', '0', 'int', 'Default SIM slot (0=auto, 1=SIM1, 2=SIM2)'],
        ['retry_limit', '2', 'int', 'Maximum retry attempts'],
        ['default_signature', '', 'string', 'Default SMS signature to append'],
        ['msg91_enabled', 'false', 'boolean', 'Enable MSG91 API'],
        ['msg91_api_key', '', 'string', 'MSG91 API Key'],
        ['msg91_sender_id', '', 'string', 'MSG91 Sender ID'],
        ['msg91_route', '4', 'string', 'MSG91 Route'],
        ['msg91_dlt_template', '', 'string', 'MSG91 DLT Template ID'],
        ['msg91_headers', '{}', 'json', 'MSG91 Custom Headers (JSON)'],
        ['spam_protection_enabled', 'true', 'boolean', 'Enable spam protection'],
        ['rate_limit_per_minute', '10', 'int', 'Messages per minute limit'],
        ['night_sending_enabled', 'false', 'boolean', 'Allow sending during night hours'],
        ['night_start_hour', '22', 'int', 'Night start hour (24h format)'],
        ['night_end_hour', '6', 'int', 'Night end hour (24h format)']
    ];

    foreach ($defaultSettings as $setting) {
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES ('$setting[0]', '$setting[1]', '$setting[2]', '$setting[3]')");
    }

    // APIs table for custom APIs
    $conn->query("
    CREATE TABLE IF NOT EXISTS apis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        api_key VARCHAR(100) UNIQUE NOT NULL,
        status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
        valid_till DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    ");

    // Campaigns table for bulk uploads
    $conn->query("
    CREATE TABLE IF NOT EXISTS campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        uid INT NOT NULL,
        message TEXT NOT NULL,
        route ENUM('device', 'msg91', 'api') DEFAULT 'device',
        scheduled_for DATETIME NULL,
        total_contacts INT DEFAULT 0,
        sent_count INT DEFAULT 0,
        failed_count INT DEFAULT 0,
        status ENUM('draft', 'processing', 'completed', 'cancelled', 'scheduled') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
    ");

    // Campaign contacts table
    $conn->query("
    CREATE TABLE IF NOT EXISTS campaign_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        name VARCHAR(100),
        phone VARCHAR(20) NOT NULL,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        message_id INT NULL,
        sent_at DATETIME NULL,
        error_message TEXT NULL,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (message_id) REFERENCES message_queue(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");

    // Blacklist table
    $conn->query("
    CREATE TABLE IF NOT EXISTS blacklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) UNIQUE NOT NULL,
        reason TEXT,
        added_by INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
    ");

    // Logs table for activity tracking
    $conn->query("
    CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uid) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");

    // API logs table
    $conn->query("
    CREATE TABLE IF NOT EXISTS api_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_id INT NULL,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        request_data TEXT,
        response_data TEXT,
        status_code INT,
        response_time FLOAT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (api_id) REFERENCES apis(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");

    // Delivery reports table (enhanced)
    $conn->query("
    CREATE TABLE IF NOT EXISTS delivery_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        phone VARCHAR(20) NULL,
        device_id INT NULL,
        api_id INT NULL,
        status VARCHAR(20),
        delivery_id VARCHAR(100) NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES message_queue(id) ON DELETE CASCADE,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
        FOREIGN KEY (api_id) REFERENCES apis(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
    ");

    // Legacy config table (for backward compatibility)
    $conn->query("
    CREATE TABLE IF NOT EXISTS config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(100) UNIQUE,
        sms_delay INT DEFAULT 5,
        sim_slot INT DEFAULT 0,
        retry_limit INT DEFAULT 2
    ) ENGINE=InnoDB
    ");

    // Create indexes for better performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_message_queue_status ON message_queue(status)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_message_queue_uid ON message_queue(uid)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_message_queue_created ON message_queue(created_at)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_devices_status ON devices(status)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_campaigns_uid ON campaigns(uid)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_logs_created ON logs(created_at)");

    response([
        "success" => true,
        "message" => "Complete SMS Gateway SaaS database schema created successfully"
    ]);
} catch (Exception $e) {
    response([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

function response($data) {
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}
?>
}
?>