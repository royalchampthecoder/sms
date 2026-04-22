<?php
/**
 * SMS Gateway Queue Worker
 * Processes pending SMS messages from the queue
 * Run this script every minute via cron job
 *
 * Cron command: * * * * * php /path/to/dashboard/worker.php
 */

// Include configuration and functions
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// Set execution time limit
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);

// Log worker start
logActivity('worker_started', 'Queue worker started processing messages');

// Get messages to process (limit to prevent timeout)
$messages = getMessagesForProcessing(50); // Process up to 50 messages per run

$processed = 0;
$successful = 0;
$failed = 0;

foreach ($messages as $message) {
    $processed++;

    // Update message status to processing
    updateMessageStatus($message['id'], 'processing');

    // Determine route and send SMS
    $result = false;
    $routeUsed = $message['route'];
    $deviceId = null;
    $apiId = null;

    switch ($message['route']) {
        case 'device':
            $result = sendViaDevice($message);
            if ($result && isset($result['device_id'])) {
                $deviceId = $result['device_id'];
            }
            break;

        case 'msg91':
            $result = sendViaMsg91($message['phone'], $message['message']);
            break;

        case 'api':
            $result = sendViaCustomApi($message['phone'], $message['message'], $message['api_id']);
            if ($result && isset($result['api_id'])) {
                $apiId = $result['api_id'];
            }
            break;

        default:
            // Auto-route: try device first, then MSG91, then custom API
            $result = sendViaDevice($message);
            if (!$result) {
                $result = sendViaMsg91($message['phone'], $message['message']);
                $routeUsed = 'msg91';
            }
            if (!$result) {
                $result = sendViaCustomApi($message['phone'], $message['message'], null);
                $routeUsed = 'api';
            }
            break;
    }

    // Update message status based on result
    if ($result && isset($result['success']) && $result['success']) {
        updateMessageStatus($message['id'], 'sent', null, $deviceId, $apiId);
        $successful++;

        // Log successful send
        logActivity('message_sent', "Message ID {$message['id']} sent via {$routeUsed}", $message['uid']);
    } else {
        $errorMessage = isset($result['error']) ? $result['error'] : 'Unknown error';

        // Check retry count
        $newRetryCount = $message['retry_count'] + 1;
        $maxRetries = getSetting('retry_limit', 2);

        if ($newRetryCount < $maxRetries) {
            // Reset to pending for retry
            $conn->query("UPDATE message_queue SET status = 'pending', retry_count = $newRetryCount WHERE id = {$message['id']}");
        } else {
            // Mark as failed
            updateMessageStatus($message['id'], 'failed', $errorMessage, $deviceId, $apiId);
            $failed++;

            // Log failed message
            logActivity('message_failed', "Message ID {$message['id']} failed after {$maxRetries} retries: {$errorMessage}", $message['uid']);
        }
    }
}

// Reset daily counters if it's a new day
resetDailyCountersIfNeeded();

// Log worker completion
logActivity('worker_completed', "Processed: {$processed}, Successful: {$successful}, Failed: {$failed}");

echo "Worker completed. Processed: {$processed}, Successful: {$successful}, Failed: {$failed}\n";

/**
 * Send SMS via Android device
 */
function sendViaDevice($message) {
    global $conn;

    // Get available devices for this user
    $userDevices = getUserDevices($message['uid']);
    if (empty($userDevices)) {
        return ['success' => false, 'error' => 'No devices assigned to user'];
    }

    // Filter online devices that haven't reached daily limit
    $availableDevices = array_filter($userDevices, function($device) {
        return $device['status'] === 'online' &&
               $device['is_active'] &&
               $device['sms_sent_today'] < $device['daily_limit'];
    });

    if (empty($availableDevices)) {
        return ['success' => false, 'error' => 'No available devices'];
    }

    // Sort by current usage (least used first)
    usort($availableDevices, function($a, $b) {
        return $a['sms_sent_today'] <=> $b['sms_sent_today'];
    });

    $selectedDevice = $availableDevices[0];

    // Check if device is still online (ping within last 5 minutes)
    $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    if ($selectedDevice['last_ping'] < $fiveMinutesAgo) {
        // Mark device as offline
        $conn->query("UPDATE devices SET status = 'offline' WHERE id = {$selectedDevice['id']}");
        return ['success' => false, 'error' => 'Device went offline'];
    }

    // Check spam protection
    if (getSetting('spam_protection_enabled', true)) {
        $rateLimit = getSetting('rate_limit_per_minute', 10);
        if (!checkRateLimit($message['uid'], $rateLimit)) {
            return ['success' => false, 'error' => 'Rate limit exceeded'];
        }
    }

    // Check night sending restriction
    if (getSetting('night_sending_enabled', false) === false) {
        $currentHour = (int)date('H');
        $nightStart = getSetting('night_start_hour', 22);
        $nightEnd = getSetting('night_end_hour', 6);

        if (($currentHour >= $nightStart || $currentHour < $nightEnd)) {
            return ['success' => false, 'error' => 'Night sending disabled'];
        }
    }

    // Check blacklist
    if (isPhoneBlacklisted($message['phone'])) {
        return ['success' => false, 'error' => 'Phone number blacklisted'];
    }

    // Add signature if configured
    $fullMessage = $message['message'];
    $signature = getSetting('default_signature');
    if (!empty($signature)) {
        $fullMessage .= "\n\n" . $signature;
    }

    // In a real implementation, you would send the SMS to the device via your API
    // For now, we'll simulate success/failure

    // Simulate API call to device
    $deviceApiUrl = "http://your-device-api.com/send"; // Replace with actual device API
    $postData = [
        'device_id' => $selectedDevice['device_id'],
        'api_key' => $selectedDevice['api_key'],
        'phone' => $message['phone'],
        'message' => $fullMessage,
        'sim_slot' => getSetting('sim_slot', 0)
    ];

    // For demonstration, we'll randomly succeed/fail
    // In production, make actual HTTP request to device
    $success = (rand(1, 10) > 2); // 80% success rate

    if ($success) {
        // Update device last ping
        updateDevicePing($selectedDevice['device_id']);

        return [
            'success' => true,
            'device_id' => $selectedDevice['id'],
            'route' => 'device'
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Device send failed',
            'device_id' => $selectedDevice['id']
        ];
    }
}

/**
 * Send SMS via Custom API
 */
function sendViaCustomApi($phone, $message, $apiId = null) {
    global $conn;

    // Get active APIs
    $apis = getActiveApis();
    if (empty($apis)) {
        return ['success' => false, 'error' => 'No active custom APIs'];
    }

    // If specific API requested, check if it's available
    if ($apiId) {
        $api = array_filter($apis, function($a) use ($apiId) {
            return $a['id'] == $apiId;
        });
        if (empty($api)) {
            return ['success' => false, 'error' => 'Requested API not available'];
        }
        $apis = array_values($api);
    }

    // Try each API until one succeeds
    foreach ($apis as $api) {
        // In a real implementation, you would make HTTP request to the custom API
        // For now, simulate success/failure

        $success = (rand(1, 10) > 3); // 70% success rate

        if ($success) {
            return [
                'success' => true,
                'api_id' => $api['id'],
                'route' => 'api'
            ];
        }
    }

    return ['success' => false, 'error' => 'All custom APIs failed'];
}

/**
 * Reset daily counters if needed
 */
function resetDailyCountersIfNeeded() {
    $lastReset = getSetting('last_counter_reset', date('Y-m-d'));

    if ($lastReset !== date('Y-m-d')) {
        resetDailyCounters();
        updateSetting('last_counter_reset', date('Y-m-d'));
        logActivity('counters_reset', 'Daily SMS counters reset');
    }
}
?>