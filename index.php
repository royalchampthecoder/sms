<?php
// Root index.php - Redirect to dashboard
session_start();

// If user is logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    header("Location: /sms/dashboard");
    exit;
}

// If not logged in, redirect to login
header("Location: /sms/dashboard/login");
exit;
?>
