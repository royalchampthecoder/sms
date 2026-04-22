<?php
/**
 * SMS Gateway SaaS - Core Functions
 * Version: 1.0.0
 * Author: SMS Gateway System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config.php';

/**
 * Security Functions
 */

// Sanitize input data
function sanitize($data) {
    global $conn;
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return $conn->real_escape_string(trim($data));
}

// Validate phone number (Indian format)
function validatePhone($phone) {
    $phone = preg_replace('/\D+/', '', $phone);

    if (strlen($phone) === 10) {
        return '+91' . $phone;
    }

    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        return '+' . $phone;
    }

    if (strlen($phone) === 13 && substr($phone, 0, 3) === '+91') {
        return $phone;
    }

    return false;
}

// Generate secure API key
function generateApiKey($length = 32) {
    return bin2hex(random_bytes($length));
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user']);
}

// Check if user is admin
function isAdmin() {
    // All users are admin - no role checking needed
    return true;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Require admin (all users are admin)
function requireAdmin() {
    requireLogin();
    // All users are admin - no additional checks needed
}

// Log activity
function logActivity($action, $details = '', $uid = null) {
    global $conn;

    if ($uid === null && isLoggedIn()) {
        $uid = $_SESSION['user_id'];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("INSERT INTO logs (uid, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $uid, $action, $details, $ip, $userAgent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Settings Functions
 */

// Get setting value
function getSetting($key, $default = null) {
    global $conn;

    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $value = $row['setting_value'];

        switch ($row['setting_type']) {
            case 'int':
                return (int) $value;
            case 'boolean':
                return $value === 'true';
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    return $default;
}

// Update setting
function updateSetting($key, $value) {
    global $conn;

    // Convert value based on type
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
        $type = 'boolean';
    } elseif (is_int($value)) {
        $type = 'int';
    } elseif (is_array($value)) {
        $value = json_encode($value);
        $type = 'json';
    } else {
        $type = 'string';
    }

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)");
    $stmt->bind_param("sss", $key, $value, $type);
    $stmt->execute();
    $stmt->close();
}

/**
 * User Management Functions
 */

// Get user by ID
function getUser($userId) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Get all users
function getAllUsers() {
    global $conn;

    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Create user
function createUser($username, $password, $email = '') {
    global $conn;

    $hashedPassword = hashPassword($password);

    $stmt = $conn->prepare("INSERT INTO users (username, password, email, status) VALUES (?, ?, ?, 'active')");
    $stmt->bind_param("sss", $username, $hashedPassword, $email);

    if ($stmt->execute()) {
        logActivity('user_created', "Created user: $username", $_SESSION['user_id'] ?? null);
        return $conn->insert_id;
    }

    return false;
}

// Update user
function updateUser($userId, $data) {
    global $conn;

    $updates = [];
    $types = '';
    $params = [];

    if (isset($data['username'])) {
        $updates[] = "username = ?";
        $types .= 's';
        $params[] = $data['username'];
    }

    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $types .= 's';
        $params[] = $data['email'];
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password = ?";
        $types .= 's';
        $params[] = hashPassword($data['password']);
    }

    if (isset($data['role'])) {
        $updates[] = "role = ?";
        $types .= 's';
        $params[] = $data['role'];
    }

    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $types .= 's';
        $params[] = $data['status'];
    }

    if (empty($updates)) {
        return false;
    }

    $types .= 'i';
    $params[] = $userId;

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        logActivity('user_updated', "Updated user ID: $userId", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

// Delete user
function deleteUser($userId) {
    global $conn;

    $user = getUser($userId);
    if (!$user) return false;

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        logActivity('user_deleted', "Deleted user: {$user['username']}", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

/**
 * Device Management Functions
 */

// Get all devices
function getAllDevices() {
    global $conn;

    $result = $conn->query("SELECT * FROM devices ORDER BY created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get device by ID
function getDevice($deviceId) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM devices WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Get device by device_id
function getDeviceByDeviceId($deviceId) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM devices WHERE device_id = ? LIMIT 1");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Register device
function registerDevice($deviceId, $deviceName, $apiKey) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO devices (device_id, device_name, api_key) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $deviceId, $deviceName, $apiKey);

    if ($stmt->execute()) {
        logActivity('device_registered', "Registered device: $deviceName ($deviceId)", $_SESSION['user_id'] ?? null);
        return $conn->insert_id;
    }

    return false;
}

// Update device
function updateDevice($deviceId, $data) {
    global $conn;

    $updates = [];
    $types = '';
    $params = [];

    if (isset($data['device_name'])) {
        $updates[] = "device_name = ?";
        $types .= 's';
        $params[] = $data['device_name'];
    }

    if (isset($data['is_active'])) {
        $updates[] = "is_active = ?";
        $types .= 'i';
        $params[] = $data['is_active'] ? 1 : 0;
    }

    if (isset($data['daily_limit'])) {
        $updates[] = "daily_limit = ?";
        $types .= 'i';
        $params[] = $data['daily_limit'];
    }

    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $types .= 's';
        $params[] = $data['status'];
    }

    if (empty($updates)) {
        return false;
    }

    $types .= 'i';
    $params[] = $deviceId;

    $sql = "UPDATE devices SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        logActivity('device_updated', "Updated device ID: $deviceId", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

// Delete device
function deleteDevice($deviceId) {
    global $conn;

    $device = getDevice($deviceId);
    if (!$device) return false;

    $stmt = $conn->prepare("DELETE FROM devices WHERE id = ?");
    $stmt->bind_param("i", $deviceId);

    if ($stmt->execute()) {
        logActivity('device_deleted', "Deleted device: {$device['device_name']}", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

// Reset daily SMS counters
function resetDailyCounters() {
    global $conn;

    $conn->query("UPDATE devices SET sms_sent_today = 0, sms_success_today = 0, sms_failed_today = 0");
}

// Update device ping
function updateDevicePing($deviceId) {
    global $conn;

    $stmt = $conn->prepare("UPDATE devices SET last_ping = NOW(), status = 'online' WHERE device_id = ?");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
}

/**
 * User-Device Assignment Functions
 */

// Assign devices to user
function assignDevicesToUser($userId, $deviceIds) {
    global $conn;

    // Remove existing assignments
    $stmt = $conn->prepare("DELETE FROM user_devices WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Add new assignments
    if (!empty($deviceIds)) {
        $stmt = $conn->prepare("INSERT INTO user_devices (user_id, device_id) VALUES (?, ?)");
        foreach ($deviceIds as $deviceId) {
            $stmt->bind_param("ii", $userId, $deviceId);
            $stmt->execute();
        }
    }

    logActivity('devices_assigned', "Assigned devices to user ID: $userId", $_SESSION['user_id'] ?? null);
    return true;
}

// Get user's assigned devices
function getUserDevices($userId) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT d.* FROM devices d
        INNER JOIN user_devices ud ON d.id = ud.device_id
        WHERE ud.user_id = ? AND d.is_active = 1
        ORDER BY d.device_name
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Message Queue Functions
 */

// Add message to queue
function queueMessage($uid, $phone, $message, $route = 'device', $schedule = null) {
    global $conn;

    // Check if phone is blacklisted
    if (isPhoneBlacklisted($phone)) {
        return ['success' => false, 'message' => 'Phone number is blacklisted'];
    }

    // Validate phone
    $phone = validatePhone($phone);
    if (!$phone) {
        return ['success' => false, 'message' => 'Invalid phone number'];
    }

    // Set status based on schedule
    $status = $schedule ? 'scheduled' : 'pending';

    $stmt = $conn->prepare("INSERT INTO message_queue (uid, phone, message, route, status, scheduled_for) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $uid, $phone, $message, $route, $status, $schedule);

    if ($stmt->execute()) {
        $messageId = $conn->insert_id;
        logActivity('message_queued', "Queued message ID: $messageId", $uid);
        return ['success' => true, 'message_id' => $messageId];
    }

    return ['success' => false, 'message' => 'Failed to queue message'];
}

// Get messages for processing
function getMessagesForProcessing($limit = 10) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT * FROM message_queue
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Update message status
function updateMessageStatus($messageId, $status, $errorMessage = null, $deviceId = null, $apiId = null) {
    global $conn;

    $stmt = $conn->prepare("
        UPDATE message_queue
        SET status = ?, error_message = ?, device_id = ?, api_id = ?,
            processed_at = NOW(),
            sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END
        WHERE id = ?
    ");
    $stmt->bind_param("ssiiis", $status, $errorMessage, $deviceId, $apiId, $status, $messageId);
    $stmt->execute();

    // Update device counters if device was used
    if ($deviceId && in_array($status, ['sent', 'failed'])) {
        $counter = $status === 'sent' ? 'sms_success_today' : 'sms_failed_today';
        $conn->query("UPDATE devices SET $counter = $counter + 1, sms_sent_today = sms_sent_today + 1 WHERE id = $deviceId");
    }
}

// Get user's messages
function getUserMessages($userId, $limit = 100, $offset = 0) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT mq.*, d.device_name, a.name as api_name
        FROM message_queue mq
        LEFT JOIN devices d ON mq.device_id = d.id
        LEFT JOIN apis a ON mq.api_id = a.id
        WHERE mq.uid = ?
        ORDER BY mq.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * API Management Functions
 */

// Get all APIs
function getAllApis() {
    global $conn;

    $result = $conn->query("SELECT * FROM apis ORDER BY created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Create API
function createApi($name, $validTill) {
    global $conn;

    $apiKey = generateApiKey();

    $stmt = $conn->prepare("INSERT INTO apis (name, api_key, valid_till) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $apiKey, $validTill);

    if ($stmt->execute()) {
        $apiId = $conn->insert_id;
        logActivity('api_created', "Created API: $name", $_SESSION['user_id'] ?? null);
        return ['success' => true, 'api_id' => $apiId, 'api_key' => $apiKey];
    }

    return ['success' => false, 'message' => 'Failed to create API'];
}

// Update API
function updateApi($apiId, $data) {
    global $conn;

    $updates = [];
    $types = '';
    $params = [];

    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $types .= 's';
        $params[] = $data['name'];
    }

    if (isset($data['valid_till'])) {
        $updates[] = "valid_till = ?";
        $types .= 's';
        $params[] = $data['valid_till'];
    }

    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $types .= 's';
        $params[] = $data['status'];
    }

    if (empty($updates)) {
        return false;
    }

    $types .= 'i';
    $params[] = $apiId;

    $sql = "UPDATE apis SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        logActivity('api_updated', "Updated API ID: $apiId", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

// Delete API
function deleteApi($apiId) {
    global $conn;

    $stmt = $conn->prepare("DELETE FROM apis WHERE id = ?");
    $stmt->bind_param("i", $apiId);

    if ($stmt->execute()) {
        logActivity('api_deleted', "Deleted API ID: $apiId", $_SESSION['user_id'] ?? null);
        return true;
    }

    return false;
}

// Validate API key
function validateApiKey($apiKey) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT * FROM apis
        WHERE api_key = ? AND status = 'active' AND valid_till >= CURDATE()
        LIMIT 1
    ");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Campaign Functions
 */

/**
 * CSV Processing Functions
 */

// Parse CSV file and return contacts array
function parseCSV($filePath) {
    $contacts = [];
    $handle = fopen($filePath, 'r');

    if (!$handle) {
        return $contacts;
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return $contacts;
    }

    // Convert headers to lowercase for case-insensitive matching
    $header = array_map('strtolower', $header);

    // Find name and phone columns
    $nameIndex = array_search('name', $header);
    $phoneIndex = array_search('phone', $header);

    // If not found, try common variations
    if ($nameIndex === false) {
        $nameIndex = array_search('contact_name', $header) !== false ? array_search('contact_name', $header) : null;
    }
    if ($phoneIndex === false) {
        $phoneIndex = array_search('mobile', $header) !== false ? array_search('mobile', $header) :
                     (array_search('number', $header) !== false ? array_search('number', $header) : null);
    }

    // Read data rows
    while (($row = fgetcsv($handle)) !== false) {
        $name = $nameIndex !== false && isset($row[$nameIndex]) ? trim($row[$nameIndex]) : '';
        $phone = $phoneIndex !== false && isset($row[$phoneIndex]) ? trim($row[$phoneIndex]) : '';

        // Validate phone number
        if (!empty($phone)) {
            $phone = validatePhone($phone);
            if ($phone) {
                $contacts[] = [
                    'name' => $name ?: 'Unknown',
                    'phone' => $phone
                ];
            }
        }
    }

    fclose($handle);
    return $contacts;
}

/**
 * Campaign Functions
 */

// Create campaign
function createCampaign($name, $uid, $contacts) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO campaigns (name, uid, total_contacts) VALUES (?, ?, ?)");
    $totalContacts = count($contacts);
    $stmt->bind_param("sii", $name, $uid, $totalContacts);

    if ($stmt->execute()) {
        $campaignId = $conn->insert_id;

        // Add contacts
        $stmt2 = $conn->prepare("INSERT INTO campaign_contacts (campaign_id, name, phone) VALUES (?, ?, ?)");
        foreach ($contacts as $contact) {
            $stmt2->bind_param("iss", $campaignId, $contact['name'], $contact['phone']);
            $stmt2->execute();
        }

        logActivity('campaign_created', "Created campaign: $name", $uid);
        return $campaignId;
    }

    return false;
}

// Create campaign with message and settings
function createCampaignWithMessage($uid, $name, $message, $route, $schedule, $contacts) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO campaigns (name, uid, message, route, scheduled_for, total_contacts, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $status = $schedule ? 'scheduled' : 'draft';
    $totalContacts = count($contacts);
    $stmt->bind_param("sisssis", $name, $uid, $message, $route, $schedule, $totalContacts, $status);

    if ($stmt->execute()) {
        $campaignId = $conn->insert_id;

        // Add contacts
        $stmt2 = $conn->prepare("INSERT INTO campaign_contacts (campaign_id, name, phone) VALUES (?, ?, ?)");
        foreach ($contacts as $contact) {
            $stmt2->bind_param("iss", $campaignId, $contact['name'], $contact['phone']);
            $stmt2->execute();
        }

        logActivity('campaign_created', "Created campaign: $name with " . count($contacts) . " contacts", $uid);
        return $campaignId;
    }

    return false;
}

// Add contact to campaign
function addContactToCampaign($campaignId, $name, $phone) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO campaign_contacts (campaign_id, name, phone) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $campaignId, $name, $phone);

    return $stmt->execute();
}

// Start campaign - queue all messages
function startCampaign($campaignId) {
    global $conn;

    // Get campaign details
    $stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();

    if (!$campaign) {
        return ['success' => false, 'error' => 'Campaign not found'];
    }

    // Check if campaign is already completed
    if ($campaign['status'] === 'completed') {
        return ['success' => false, 'error' => 'Campaign already completed'];
    }

    // Get campaign contacts
    $stmt = $conn->prepare("SELECT * FROM campaign_contacts WHERE campaign_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($contacts)) {
        return ['success' => false, 'error' => 'No pending contacts found'];
    }

    // Update campaign status to processing
    $stmt = $conn->prepare("UPDATE campaigns SET status = 'processing' WHERE id = ?");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();

    // Queue messages for each contact
    $queued = 0;
    $failed = 0;

    foreach ($contacts as $contact) {
        $result = queueMessage($campaign['uid'], $contact['phone'], $campaign['message'], $campaign['route'], $campaign['scheduled_for']);

        if ($result['success']) {
            // Update contact status to sent
            $stmt = $conn->prepare("UPDATE campaign_contacts SET status = 'sent' WHERE id = ?");
            $stmt->bind_param("i", $contact['id']);
            $stmt->execute();
            $queued++;
        } else {
            // Update contact status to failed
            $stmt = $conn->prepare("UPDATE campaign_contacts SET status = 'failed' WHERE id = ?");
            $stmt->bind_param("i", $contact['id']);
            $stmt->execute();
            $failed++;
        }
    }

    // Update campaign statistics
    $stmt = $conn->prepare("UPDATE campaigns SET sent_count = sent_count + ?, failed_count = failed_count + ?, status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $queued, $failed, $campaignId);
    $stmt->execute();

    logActivity('campaign_started', "Started campaign: {$campaign['name']} - Queued: $queued, Failed: $failed", $campaign['uid']);

    return [
        'success' => true,
        'message' => "Campaign started successfully. Queued: $queued, Failed: $failed",
        'queued' => $queued,
        'failed' => $failed
    ];
}

// Get user's campaigns
function getUserCampaigns($userId) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM campaigns WHERE uid = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Blacklist Functions
 */

// Check if phone is blacklisted
function isPhoneBlacklisted($phone) {
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM blacklist WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

// Add to blacklist
function addToBlacklist($phone, $reason = '') {
    global $conn;

    $uid = $_SESSION['user_id'] ?? null;

    $stmt = $conn->prepare("INSERT INTO blacklist (phone, reason, added_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $phone, $reason, $uid);

    if ($stmt->execute()) {
        logActivity('blacklist_added', "Added to blacklist: $phone", $uid);
        return true;
    }

    return false;
}

/**
 * Statistics Functions
 */

// Get dashboard stats
function getDashboardStats($userId = null) {
    global $conn;

    $stats = [];

    // Message stats
    if ($userId) {
        $stmt = $conn->prepare("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM message_queue WHERE uid = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM message_queue");
    }
    $stats['messages'] = $result->fetch_assoc();

    // Device stats
    $result = $conn->query("SELECT
        COUNT(*) as total_devices,
        SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_devices,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_devices
        FROM devices");

    $stats['devices'] = $result->fetch_assoc();

    // API stats
    $result = $conn->query("SELECT COUNT(*) as total_apis FROM apis WHERE status = 'active'");
    $stats['apis'] = $result->fetch_assoc();

    // Today's SMS count
    if ($userId) {
        $stmt = $conn->prepare("SELECT COUNT(*) as today_sent FROM message_queue WHERE DATE(created_at) = CURDATE() AND status = 'sent' AND uid = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT COUNT(*) as today_sent FROM message_queue WHERE DATE(created_at) = CURDATE() AND status = 'sent'");
    }
    $stats['today'] = $result->fetch_assoc();

    return $stats;
}

/**
 * SMS Sending Logic
 */

// Get available routes for sending
function getAvailableRoutes() {
    $routes = [];

    // Check devices
    $devices = getAvailableDevices();
    if (!empty($devices)) {
        $routes[] = 'device';
    }

    // Check MSG91
    if (getSetting('msg91_enabled', false) &&
        !empty(getSetting('msg91_api_key')) &&
        !empty(getSetting('msg91_sender_id'))) {
        $routes[] = 'msg91';
    }

    // Check custom APIs
    $apis = getActiveApis();
    if (!empty($apis)) {
        $routes[] = 'api';
    }

    return $routes;
}

// Get available devices
function getAvailableDevices() {
    global $conn;

    $result = $conn->query("
        SELECT * FROM devices
        WHERE is_active = 1
        AND status = 'online'
        AND sms_sent_today < daily_limit
        ORDER BY sms_sent_today ASC
    ");

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get active APIs
function getActiveApis() {
    global $conn;

    $result = $conn->query("
        SELECT * FROM apis
        WHERE status = 'active'
        AND valid_till >= CURDATE()
    ");

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Send SMS via MSG91
function sendViaMsg91($phone, $message) {
    $apiKey = getSetting('msg91_api_key');
    $senderId = getSetting('msg91_sender_id');
    $route = getSetting('msg91_route', '4');
    $dltTemplate = getSetting('msg91_dlt_template');

    if (empty($apiKey) || empty($senderId)) {
        return ['success' => false, 'error' => 'MSG91 not configured'];
    }

    // Add signature if configured
    $signature = getSetting('default_signature');
    if (!empty($signature)) {
        $message .= "\n\n" . $signature;
    }

    $url = "https://api.msg91.com/api/sendhttp.php";
    $params = [
        'authkey' => $apiKey,
        'mobiles' => $phone,
        'message' => urlencode($message),
        'sender' => $senderId,
        'route' => $route,
        'country' => '91'
    ];

    if (!empty($dltTemplate)) {
        $params['DLT_TE_ID'] = $dltTemplate;
    }

    $query = http_build_query($params);
    $fullUrl = $url . '?' . $query;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Log API call
    logApiCall(null, 'MSG91', 'GET', $params, $response, $httpCode);

    if ($httpCode == 200 && strpos($response, 'success') !== false) {
        return ['success' => true, 'response' => $response];
    }

    return ['success' => false, 'error' => $response];
}

// Send SMS via Custom API
function sendViaCustomApi($phone, $message, $api) {
    // This would need to be implemented based on the specific API requirements
    // For now, return a placeholder
    return ['success' => false, 'error' => 'Custom API sending not implemented'];
}

// Log API call
function logApiCall($apiId, $endpoint, $method, $requestData, $responseData, $statusCode) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO api_logs (api_id, endpoint, method, request_data, response_data, status_code) VALUES (?, ?, ?, ?, ?, ?)");
    $requestJson = json_encode($requestData);
    $responseJson = json_encode($responseData);
    $stmt->bind_param("issssi", $apiId, $endpoint, $method, $requestJson, $responseJson, $statusCode);
    $stmt->execute();
    $stmt->close();
}

/**
 * Rate Limiting Functions
 */

// Check rate limit
function checkRateLimit($userId, $limit = null) {
    global $conn;

    if ($limit === null) {
        $limit = getSetting('rate_limit_per_minute', 10);
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM message_queue
        WHERE uid = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'] < $limit;
}

/**
 * Utility Functions
 */

// Format date
function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}

// Get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'bg-warning text-dark',
        'processing' => 'bg-info',
        'sent' => 'bg-success',
        'failed' => 'bg-danger',
        'active' => 'bg-success',
        'inactive' => 'bg-secondary',
        'online' => 'bg-success',
        'offline' => 'bg-secondary',
        'disconnected' => 'bg-danger'
    ];

    return $classes[$status] ?? 'bg-secondary';
}

// Generate CSV
function generateCsv($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

/**
 * Device API Functions
 */

// Validate device API key and return device info
function validateDeviceApiKey($deviceId, $apiKey) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM devices WHERE device_id = ? AND api_key = ? LIMIT 1");
    $stmt->bind_param("ss", $deviceId, $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Get device statistics
function getDeviceStats($deviceId) {
    global $conn;

    // Get today's sent messages
    $stmt = $conn->prepare("
        SELECT
            COUNT(CASE WHEN DATE(created_at) = CURDATE() AND status = 'sent' THEN 1 END) as sent_today,
            COUNT(*) as sent_total
        FROM message_queue
        WHERE device_id = ?
    ");
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();

    return $stats;
}

/**
 * API Rate Limiting Functions
 */

// Check API rate limit for user
function checkApiRateLimit($userId, $limitPerMinute = 60) {
    global $conn;

    $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));

    $stmt = $conn->prepare("
        SELECT COUNT(*) as requests
        FROM activity_log
        WHERE user_id = ? AND action = 'api_request' AND created_at > ?
    ");
    $stmt->bind_param("is", $userId, $oneMinuteAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    return $data['requests'] < $limitPerMinute;
}

/**
 * User Statistics Functions
 */

// Get user statistics
function getUserStats($userId) {
    global $conn;

    // Get today's sent messages
    $stmt = $conn->prepare("
        SELECT
            COUNT(CASE WHEN DATE(created_at) = CURDATE() AND status = 'sent' THEN 1 END) as sent_today,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_total,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_total
        FROM message_queue
        WHERE uid = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();

    return $stats;
}
?>