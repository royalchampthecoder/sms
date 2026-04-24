<?php
declare(strict_types=1);

$host = "localhost";
$db = "sms_gateway";
$user = "root";
$pass = "";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (Throwable $exception) {
    http_response_code(500);
    exit("Database connection failed. Please verify MySQL is running and the credentials in config.php are correct.");
}
