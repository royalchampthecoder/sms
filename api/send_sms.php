<?php
/**
 * SMS Gateway API Endpoint
 * Handles external API requests for sending SMS
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

// Validate API key
$user = validateApiKey($apiKey);
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid API key'
    ]);
    exit();
}

// Check if user is active
if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Account is inactive'
    ]);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required parameters
$phone = $input['phone'] ?? $input['to'] ?? $input['number'] ?? '';
$message = $input['message'] ?? $input['text'] ?? $input['msg'] ?? '';
$route = $input['route'] ?? 'auto'; // auto, device, msg91, api
$schedule = $input['schedule'] ?? null; // Optional: YYYY-MM-DD HH:MM:SS

if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Phone number and message are required'
    ]);
    exit();
}

// Validate phone number format
if (!validatePhone($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid phone number format'
    ]);
    exit();
}

// Check message length
if (strlen($message) > 160) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Message too long. Maximum 160 characters allowed.'
    ]);
    exit();
}

// Check rate limiting
$rateLimit = getSetting('api_rate_limit_per_minute', 60);
if (!checkApiRateLimit($user['id'], $rateLimit)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded. Please try again later.'
    ]);
    exit();
}

// Check if user has reached daily limit
$userStats = getUserStats($user['id']);
$dailyLimit = getSetting('daily_limit_per_user', 1000);

if ($userStats['sent_today'] >= $dailyLimit) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Daily SMS limit reached'
    ]);
    exit();
}

// Validate route
$validRoutes = ['auto', 'device', 'msg91', 'api'];
if (!in_array($route, $validRoutes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid route. Valid routes: ' . implode(', ', $validRoutes)
    ]);
    exit();
}

// Validate schedule time if provided
if ($schedule) {
    $scheduleTime = strtotime($schedule);
    if (!$scheduleTime || $scheduleTime <= time()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid schedule time. Must be a future date/time.'
        ]);
        exit();
    }
}

// Check spam protection
if (getSetting('spam_protection_enabled', true)) {
    if (isPhoneBlacklisted($phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Phone number is blacklisted'
        ]);
        exit();
    }
}

// Queue the message
$messageId = queueMessage($user['id'], $phone, $message, $route, $schedule);

// Log API request
logActivity('api_request', "SMS queued via API - ID: {$messageId}", $user['id']);

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message_id' => $messageId,
    'status' => $schedule ? 'scheduled' : 'queued',
    'phone' => $phone,
    'route' => $route,
    'scheduled_for' => $schedule,
    'remaining_daily' => $dailyLimit - $userStats['sent_today'] - 1
]);
?>