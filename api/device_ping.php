<?php
/**
 * Device Ping Endpoint
 * Handles device status updates and pings
 */

// Include configuration and functions
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../dashboard/functions.php';

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get API key from header or parameter
$apiKey = $_SERVER['HTTP_X_API_KEY'] ??
          $_SERVER['HTTP_AUTHORIZATION'] ??
          $_GET['api_key'] ??
          $_POST['api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'API key required'
    ]);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate device ID
$deviceId = $input['device_id'] ?? '';
if (empty($deviceId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Device ID is required'
    ]);
    exit();
}

// Validate device and API key
$device = validateDeviceApiKey($deviceId, $apiKey);
if (!$device) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid device ID or API key'
    ]);
    exit();
}

// Update device ping
$result = updateDevicePing($deviceId);

if ($result) {
    // Get device stats
    $stats = getDeviceStats($device['id']);

    // Log ping
    logActivity('device_ping', "Device {$deviceId} pinged successfully");

    // Return success with device info
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'device_id' => $deviceId,
        'status' => 'online',
        'last_ping' => date('Y-m-d H:i:s'),
        'stats' => [
            'sent_today' => $stats['sent_today'],
            'sent_total' => $stats['sent_total'],
            'daily_limit' => $device['daily_limit'],
            'remaining_today' => $device['daily_limit'] - $stats['sent_today']
        ],
        'settings' => [
            'sim_slot' => getSetting('sim_slot', 0),
            'sms_delay' => getSetting('sms_delay_seconds', 30),
            'night_sending' => getSetting('night_sending_enabled', false)
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update device ping'
    ]);
}
?>