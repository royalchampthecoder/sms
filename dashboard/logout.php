<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

if (is_logged_in()) {
    $user = current_user();
    log_activity("auth", "logout", ["username" => $user["username"] ?? ""], (int) ($user["id"] ?? 0));
}

logout_user();
flash_set("success", "You have been signed out.");
redirect_to("login.php");
