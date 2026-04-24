<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

$authUser = require_admin();
$logs = db_fetch_all(
    "SELECT l.*, u.username, a.name AS api_name, d.device_name
     FROM activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN apis a ON a.id = l.api_id
     LEFT JOIN devices d ON d.id = l.device_id
     ORDER BY l.created_at DESC
     LIMIT 500"
);

$pageTitle = "Logs";
$activePage = "logs.php";

require __DIR__ . "/partials/header.php";
?>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>When</th>
                <th>Type</th>
                <th>Action</th>
                <th>User</th>
                <th>API</th>
                <th>Device</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= h(pretty_date($log["created_at"])) ?></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($log["log_type"]) ?>"><?= h($log["log_type"]) ?></span></td>
                    <td><?= h($log["action"]) ?></td>
                    <td><?= h($log["username"] ?: "—") ?></td>
                    <td><?= h($log["api_name"] ?: "—") ?></td>
                    <td><?= h($log["device_name"] ?: "—") ?></td>
                    <td><small><?= h((string) $log["details"]) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
