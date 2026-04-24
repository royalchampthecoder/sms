<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

$filters = [
    "status" => trim((string) ($_GET["status"] ?? "")),
    "route_used" => trim((string) ($_GET["route_used"] ?? "")),
    "search" => trim((string) ($_GET["search"] ?? "")),
];

if ($authUser["role"] === "admin") {
    $filters["user_id"] = (int) ($_GET["user_id"] ?? 0);
}

$messages = list_messages($filters, (int) $authUser["id"]);
$users = $authUser["role"] === "admin" ? list_users() : [];

$pageTitle = "Messages";
$activePage = "messages.php";

require __DIR__ . "/partials/header.php";
?>

<div class="glass-panel p-4 mb-4">
    <form class="row g-3">
        <?php if ($authUser["role"] === "admin"): ?>
            <div class="col-md-3">
                <label class="form-label fw-semibold">User</label>
                <select name="user_id" class="form-select">
                    <option value="">All users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user["id"] ?>" <?= ((int) ($filters["user_id"] ?? 0) === (int) $user["id"]) ? "selected" : "" ?>>
                            <?= h($user["username"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (["pending", "scheduled", "queued_for_device", "processing", "sent", "failed", "retry_wait"] as $status): ?>
                    <option value="<?= h($status) ?>" <?= $filters["status"] === $status ? "selected" : "" ?>><?= h($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Route</label>
            <select name="route_used" class="form-select">
                <option value="">All routes</option>
                <?php foreach (["device", "msg91", "custom_api"] as $route): ?>
                    <option value="<?= h($route) ?>" <?= $filters["route_used"] === $route ? "selected" : "" ?>><?= h(route_label($route)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" value="<?= h($filters["search"]) ?>" placeholder="Phone, error, or message text">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Apply Filters</button>
            <a class="btn btn-outline-secondary" href="messages.php">Reset</a>
        </div>
    </form>
</div>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>ID</th>
                <?php if ($authUser["role"] === "admin"): ?><th>User</th><?php endif; ?>
                <th>Phone</th>
                <th>Route</th>
                <th>Status</th>
                <th>Retry</th>
                <th>Created</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr>
                    <td>#<?= (int) $message["id"] ?></td>
                    <?php if ($authUser["role"] === "admin"): ?><td><?= h($message["username"]) ?></td><?php endif; ?>
                    <td><?= h(display_phone($message["phone"])) ?></td>
                    <td><?= h(route_label($message["route_used"] ?: $message["route_preference"])) ?></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($message["status"]) ?>"><?= h($message["status"]) ?></span></td>
                    <td><?= (int) $message["retry_count"] ?> / <?= (int) $message["max_retry"] ?></td>
                    <td><?= h(pretty_date($message["created_at"])) ?></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#msg-<?= (int) $message["id"] ?>">
                            View
                        </button>
                    </td>
                </tr>
                <tr class="collapse" id="msg-<?= (int) $message["id"] ?>">
                    <td colspan="<?= $authUser["role"] === "admin" ? 8 : 7 ?>">
                        <div class="border rounded-4 p-3 bg-white">
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <div class="small text-secondary mb-1">Message</div>
                                    <div><?= nl2br(h($message["message_text"])) ?></div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="small text-secondary mb-1">Delivery Info</div>
                                    <div>Device: <?= h($message["device_name"] ?: "—") ?></div>
                                    <div>API Key: <?= h($message["api_name"] ?: "—") ?></div>
                                    <div>Custom Gateway: <?= h($message["gateway_name"] ?: "—") ?></div>
                                    <div>Scheduled: <?= h(pretty_date($message["scheduled_at"])) ?></div>
                                    <div>Sent: <?= h(pretty_date($message["sent_at"])) ?></div>
                                </div>
                                <?php if (!empty($message["error_message"])): ?>
                                    <div class="col-12">
                                        <div class="alert alert-danger mb-0"><?= h($message["error_message"]) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
