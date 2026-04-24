<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $authUser["role"] === "admin") {
    verify_csrf();

    try {
        $action = (string) ($_POST["action"] ?? "");

        if ($action === "create_device") {
            create_device_record([
                "device_id" => $_POST["device_id"] ?? "",
                "device_name" => $_POST["device_name"] ?? "",
                "daily_limit" => (int) ($_POST["daily_limit"] ?? 95),
                "sim_slot_preference" => $_POST["sim_slot_preference"] ?? "auto",
                "priority" => (int) ($_POST["priority"] ?? 100),
                "is_active" => !empty($_POST["is_active"]),
                "status" => "offline",
            ]);
            flash_set("success", "Device registered successfully.");
        }

        if ($action === "update_device") {
            update_device_record((int) $_POST["device_db_id"], [
                "device_name" => $_POST["device_name"] ?? "",
                "daily_limit" => (int) ($_POST["daily_limit"] ?? 95),
                "sim_slot_preference" => $_POST["sim_slot_preference"] ?? "auto",
                "priority" => (int) ($_POST["priority"] ?? 100),
                "is_active" => !empty($_POST["is_active"]),
                "status" => $_POST["status"] ?? "offline",
            ]);
            flash_set("success", "Device updated.");
        }

        if ($action === "rotate_key") {
            $newKey = rotate_device_key((int) $_POST["device_db_id"]);
            flash_set("success", "Device API key rotated. New key: {$newKey}");
        }

        if ($action === "delete_device") {
            delete_device_record((int) $_POST["device_db_id"]);
            flash_set("success", "Device deleted.");
        }
    } catch (Throwable $exception) {
        flash_set("danger", $exception->getMessage());
    }

    redirect_to("devices.php");
}

$devices = list_devices((int) $authUser["id"]);
$pageTitle = "Devices";
$activePage = "devices.php";
$pageActions = $authUser["role"] === "admin" ? '<a href="#device-form" class="btn btn-primary">Register Device</a>' : "";

require __DIR__ . "/partials/header.php";
?>

<?php if ($authUser["role"] === "admin"): ?>
    <div class="glass-panel p-4 mb-4" id="device-form">
        <h2 class="h5 mb-3">Register Device</h2>
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_device">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Device ID</label>
                <input type="text" name="device_id" class="form-control" placeholder="Optional auto-generate">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Device Name</label>
                <input type="text" name="device_name" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Daily Limit</label>
                <input type="number" name="daily_limit" class="form-control" value="95" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">SIM Slot</label>
                <select name="sim_slot_preference" class="form-select">
                    <option value="auto">Automatic</option>
                    <option value="sim1">SIM1</option>
                    <option value="sim2">SIM2</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Priority</label>
                <input type="number" name="priority" class="form-control" value="100">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Active and available for routing</label>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Create Device</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Device ID</th>
                <th>Status</th>
                <th>Today</th>
                <th>Last Ping</th>
                <?php if ($authUser["role"] === "admin"): ?><th>API Key</th><th>Actions</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $device): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= h($device["device_name"]) ?></div>
                        <div class="small text-secondary">Priority <?= (int) $device["priority"] ?> | <?= h($device["sim_slot_preference"]) ?></div>
                    </td>
                    <td><?= h($device["device_id"]) ?></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($device["status"]) ?>"><?= h($device["status"]) ?></span></td>
                    <td><?= (int) $device["sms_sent_today"] ?> / <?= (int) $device["daily_limit"] ?></td>
                    <td><?= h(pretty_date($device["last_ping_at"])) ?></td>
                    <?php if ($authUser["role"] === "admin"): ?>
                        <td><code><?= h($device["api_key"]) ?></code></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#device-<?= (int) $device["id"] ?>">Manage</button>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php if ($authUser["role"] === "admin"): ?>
                    <tr class="collapse" id="device-<?= (int) $device["id"] ?>">
                        <td colspan="7">
                            <div class="border rounded-4 p-3 bg-white">
                                <form method="post" class="row g-3">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_device">
                                    <input type="hidden" name="device_db_id" value="<?= (int) $device["id"] ?>">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Device Name</label>
                                        <input class="form-control" name="device_name" value="<?= h($device["device_name"]) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold">Daily Limit</label>
                                        <input class="form-control" type="number" name="daily_limit" value="<?= (int) $device["daily_limit"] ?>" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold">SIM Slot</label>
                                        <select name="sim_slot_preference" class="form-select">
                                            <?php foreach (["auto" => "Automatic", "sim1" => "SIM1", "sim2" => "SIM2"] as $value => $label): ?>
                                                <option value="<?= h($value) ?>" <?= $device["sim_slot_preference"] === $value ? "selected" : "" ?>><?= h($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold">Priority</label>
                                        <input class="form-control" type="number" name="priority" value="<?= (int) $device["priority"] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Status</label>
                                        <select name="status" class="form-select">
                                            <?php foreach (["online", "offline", "disconnected"] as $status): ?>
                                                <option value="<?= h($status) ?>" <?= $device["status"] === $status ? "selected" : "" ?>><?= h($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="active-<?= (int) $device["id"] ?>" <?= (bool) $device["is_active"] ? "checked" : "" ?>>
                                            <label class="form-check-label" for="active-<?= (int) $device["id"] ?>">Device enabled</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary" type="submit">Save Changes</button>
                                    </div>
                                </form>
                                <div class="d-flex gap-2 flex-wrap">
                                <form method="post" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="rotate_key">
                                    <input type="hidden" name="device_db_id" value="<?= (int) $device["id"] ?>">
                                    <button class="btn btn-outline-secondary" type="submit">Rotate API Key</button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this device?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete_device">
                                    <input type="hidden" name="device_db_id" value="<?= (int) $device["id"] ?>">
                                    <button class="btn btn-outline-danger" type="submit">Delete</button>
                                </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
