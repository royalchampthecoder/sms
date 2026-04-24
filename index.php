<?php
declare(strict_types=1);

require_once __DIR__ . "/dashboard/functions.php";

if (is_logged_in()) {
    redirect_to("dashboard/index.php");
}

redirect_to("dashboard/login.php");
