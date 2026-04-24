<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

$stats = dashboard_stats((int) $authUser["id"]);
$recentMessages = list_messages([], (int) $authUser["id"]);
$recentMessages = array_slice($recentMessages, 0, 8);

$pageTitle = "Dashboard";
$activePage = "index.php";
$pageActions = '<a href="send.php" class="btn btn-primary">New Message</a>';

require __DIR__ . "/partials/header.php";
?>

<?php if ($authUser["role"] === "admin"): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Total Users</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["total_users"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Total Devices</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["total_devices"] ?? 0) ?></div>
                <div class="mt-2 small text-secondary">Online <?= (int) ($stats["online_devices"] ?? 0) ?> / Offline <?= (int) ($stats["offline_devices"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Active API Keys</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["total_apis"] ?? 0) ?></div>
                <div class="mt-2 small text-secondary">Custom routes <?= (int) ($stats["total_custom_gateways"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Today</div>
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-3 fw-bold"><?= (int) ($stats["sent_today"] ?? 0) ?></div>
                        <div class="small text-secondary">Sent</div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-danger"><?= (int) ($stats["failed_today"] ?? 0) ?></div>
                        <div class="small text-secondary">Failed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-panel p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Recent Queue Activity</h2>
                    <a href="messages.php" class="btn btn-outline-primary btn-sm">Open Messages</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentMessages as $message): ?>
                            <tr>
                                <td>#<?= (int) $message["id"] ?></td>
                                <td><?= h($message["username"]) ?></td>
                                <td><?= h(display_phone($message["phone"])) ?></td>
                                <td><?= h(route_label($message["route_used"] ?: $message["route_preference"])) ?></td>
                                <td><span class="badge rounded-pill <?= badge_class_for_status($message["status"]) ?>"><?= h($message["status"]) ?></span></td>
                                <td><?= h(pretty_date($message["created_at"])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-panel p-4 h-100">
                <h2 class="h5 mb-3">Admin Activity</h2>
                <div class="d-grid gap-3">
                    <?php foreach (($stats["recent_activity"] ?? []) as $log): ?>
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="d-flex justify-content-between gap-3">
                                <strong><?= h($log["action"]) ?></strong>
                                <span class="small text-secondary"><?= h(pretty_date($log["created_at"])) ?></span>
                            </div>
                            <div class="small text-secondary mt-1"><?= h((string) ($log["details"] ?? "")) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Sent</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["total_sent"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Failed</div>
                <div class="display-6 fw-bold text-danger"><?= (int) ($stats["total_failed"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Pending</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["total_pending"] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100">
                <div class="muted mb-2">Queued Today</div>
                <div class="display-6 fw-bold"><?= (int) ($stats["queued_today"] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="glass-panel p-4 h-100">
                <h2 class="h5 mb-3">Route Usage</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="small text-secondary mb-1">Device</div>
                            <div class="fs-3 fw-bold"><?= (int) ($stats["via_device"] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="small text-secondary mb-1">MSG91</div>
                            <div class="fs-3 fw-bold"><?= (int) ($stats["via_msg91"] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="small text-secondary mb-1">Custom API</div>
                            <div class="fs-3 fw-bold"><?= (int) ($stats["via_custom_api"] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="glass-panel p-4 h-100">
                <h2 class="h5 mb-3">Assigned Devices</h2>
                <div class="d-grid gap-3">
                    <?php foreach (($stats["devices"] ?? []) as $device): ?>
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?= h($device["device_name"]) ?></div>
                                    <div class="small text-secondary"><?= h($device["device_id"]) ?></div>
                                </div>
                                <span class="badge rounded-pill <?= badge_class_for_status($device["status"]) ?>"><?= h($device["status"]) ?></span>
                            </div>
                            <div class="small text-secondary mt-2">
                                Today <?= (int) $device["sms_sent_today"] ?> / <?= (int) $device["daily_limit"] ?> messages
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/partials/footer.php"; ?>
