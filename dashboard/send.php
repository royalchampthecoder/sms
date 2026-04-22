<?php
include "auth_check.php";
include "../config.php";

if (!isset($_POST['phone']) || !isset($_POST['message'])) {
    die("Invalid");
}

$phone = $_POST['phone'];
$message = $_POST['message'];

$stmt = $conn->prepare("INSERT INTO messages (phone, message) VALUES (?, ?)");
$stmt->bind_param("ss", $phone, $message);
$stmt->execute();

header("Location: /sms/dashboard");
?>