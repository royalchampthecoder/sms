<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /sms/dashboard/login");
    exit;
}
?>