<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $action = (string) ($_POST["action"] ?? "");

        if ($action === "change_password") {
            $current = (string) ($_POST["current_password"] ?? "");
            $new = (string) ($_POST["new_password"] ?? "");
            $confirm = (string) ($_POST["confirm_password"] ?? "");

            if (!password_verify($current, $authUser["password_hash"])) {
                throw new RuntimeException("Current password is incorrect.");
            }
            if ($new === "" || $new !== $confirm) {
                throw new RuntimeException("New passwords do not match.");
            }

            set_user_password((int) $authUser["id"], $new, false);
            flash_set("success", "Password updated successfully.");
        }

        if ($authUser["role"] === "admin" && $action === "save_system_settings") {
            $msg91Enabled = !empty($_POST["msg91_enabled"]);
            if ($msg91Enabled) {
                $mode = (string) ($_POST["msg91_api_mode"] ?? "legacy");
                if (trim((string) ($_POST["msg91_auth_key"] ?? "")) === "") {
                    throw new RuntimeException("MSG91 auth key is required when MSG91 is enabled.");
                }
                if ($mode === "legacy") {
                    if (trim((string) ($_POST["msg91_sender_id"] ?? "")) === "" || trim((string) ($_POST["msg91_route"] ?? "")) === "") {
                        throw new RuntimeException("MSG91 sender ID and route are required in legacy mode.");
                    }
                }
                if ($mode === "flow" && trim((string) ($_POST["msg91_template_id"] ?? $_POST["msg91_flow_id"] ?? "")) === "") {
                    throw new RuntimeException("Flow mode requires template_id or flow_id.");
                }
            } elseif (!routing_fallback_available_without_msg91()) {
                throw new RuntimeException("MSG91 cannot be disabled while no online device or custom gateway is available.");
            }

            $settings = [
                ["sms_delay_seconds", (int) ($_POST["sms_delay_seconds"] ?? 5), "int"],
                ["sim_slot_preference", $_POST["sim_slot_preference"] ?? "auto", "string"],
                ["retry_limit", (int) ($_POST["retry_limit"] ?? 3), "int"],
                ["retry_delay_seconds", (int) ($_POST["retry_delay_seconds"] ?? 60), "int"],
                ["rate_limit_per_minute", (int) ($_POST["rate_limit_per_minute"] ?? 30), "int"],
                ["daily_limit_per_user", (int) ($_POST["daily_limit_per_user"] ?? 1000), "int"],
                ["device_offline_after_minutes", (int) ($_POST["device_offline_after_minutes"] ?? 2), "int"],
                ["device_disconnect_after_minutes", (int) ($_POST["device_disconnect_after_minutes"] ?? 10), "int"],
                ["device_ack_timeout_minutes", (int) ($_POST["device_ack_timeout_minutes"] ?? 3), "int"],
                ["default_signature", $_POST["default_signature"] ?? "", "string"],
                ["quiet_hours_enabled", !empty($_POST["quiet_hours_enabled"]), "bool"],
                ["quiet_hours_start", $_POST["quiet_hours_start"] ?? "22:00", "string"],
                ["quiet_hours_end", $_POST["quiet_hours_end"] ?? "06:00", "string"],
                ["msg91_enabled", $msg91Enabled, "bool"],
                ["msg91_api_mode", $_POST["msg91_api_mode"] ?? "legacy", "string"],
                ["msg91_base_url", $_POST["msg91_base_url"] ?? "https://api.msg91.com", "string"],
                ["msg91_auth_key", $_POST["msg91_auth_key"] ?? "", "string"],
                ["msg91_sender_id", $_POST["msg91_sender_id"] ?? "", "string"],
                ["msg91_route", $_POST["msg91_route"] ?? "4", "string"],
                ["msg91_country", $_POST["msg91_country"] ?? "91", "string"],
                ["msg91_dlt_template_id", $_POST["msg91_dlt_template_id"] ?? "", "string"],
                ["msg91_flow_id", $_POST["msg91_flow_id"] ?? "", "string"],
                ["msg91_template_id", $_POST["msg91_template_id"] ?? "", "string"],
                ["msg91_headers_json", json_decode((string) ($_POST["msg91_headers_json"] ?? "{}"), true) ?? [], "json"],
                ["msg91_short_url", $_POST["msg91_short_url"] ?? "0", "string"],
                ["msg91_short_url_expiry", $_POST["msg91_short_url_expiry"] ?? "", "string"],
                ["msg91_realtime_response", $_POST["msg91_realtime_response"] ?? "1", "string"],
            ];

            foreach ($settings as [$key, $value, $type]) {
                save_setting($key, $value, $type);
            }

            flash_set("success", "System settings updated.");
        }

        if ($authUser["role"] === "admin" && $action === "add_blacklist") {
            $phone = normalize_phone((string) ($_POST["phone"] ?? ""));
            if ($phone === null) {
                throw new RuntimeException("Invalid phone number.");
            }

            db_run(
                "INSERT INTO blacklist (phone, reason, added_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), added_by = VALUES(added_by)",
                [$phone, trim((string) ($_POST["reason"] ?? "")), $authUser["id"]]
            );
            flash_set("success", "Phone added to blacklist.");
        }

        if ($authUser["role"] === "admin" && $action === "remove_blacklist") {
            db_run("DELETE FROM blacklist WHERE id = ?", [(int) $_POST["blacklist_id"]]);
            flash_set("success", "Blacklist entry removed.");
        }
    } catch (Throwable $exception) {
        flash_set("danger", $exception->getMessage());
    }

    redirect_to("settings.php");
}

$blacklist = $authUser["role"] === "admin" ? db_fetch_all("SELECT b.*, u.username FROM blacklist b LEFT JOIN users u ON u.id = b.added_by ORDER BY b.created_at DESC") : [];

$pageTitle = "Settings";
$activePage = "settings.php";

require __DIR__ . "/partials/header.php";
?>

<?php if ($authUser["role"] === "admin"): ?>
    <div class="glass-panel p-4 mb-4">
        <h2 class="h5 mb-3">System Settings</h2>
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save_system_settings">
            <div class="col-md-3">
                <label class="form-label fw-semibold">SMS Delay (sec)</label>
                <input type="number" name="sms_delay_seconds" class="form-control" value="<?= (int) app_setting("sms_delay_seconds", 5) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Retry Limit</label>
                <input type="number" name="retry_limit" class="form-control" value="<?= (int) app_setting("retry_limit", 3) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Retry Delay (sec)</label>
                <input type="number" name="retry_delay_seconds" class="form-control" value="<?= (int) app_setting("retry_delay_seconds", 60) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">SIM Preference</label>
                <select name="sim_slot_preference" class="form-select">
                    <?php foreach (["auto" => "Automatic", "sim1" => "SIM1", "sim2" => "SIM2"] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= app_setting("sim_slot_preference", "auto") === $value ? "selected" : "" ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">User Rate / Min</label>
                <input type="number" name="rate_limit_per_minute" class="form-control" value="<?= (int) app_setting("rate_limit_per_minute", 30) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Daily Limit / User</label>
                <input type="number" name="daily_limit_per_user" class="form-control" value="<?= (int) app_setting("daily_limit_per_user", 1000) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Offline After (min)</label>
                <input type="number" name="device_offline_after_minutes" class="form-control" value="<?= (int) app_setting("device_offline_after_minutes", 2) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Disconnect After (min)</label>
                <input type="number" name="device_disconnect_after_minutes" class="form-control" value="<?= (int) app_setting("device_disconnect_after_minutes", 10) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Ack Timeout (min)</label>
                <input type="number" name="device_ack_timeout_minutes" class="form-control" value="<?= (int) app_setting("device_ack_timeout_minutes", 3) ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label fw-semibold">Default Signature</label>
                <input type="text" name="default_signature" class="form-control" value="<?= h((string) app_setting("default_signature", "")) ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4 pt-2">
                    <input class="form-check-input" type="checkbox" name="quiet_hours_enabled" id="quiet_hours_enabled" <?= app_setting("quiet_hours_enabled", false) ? "checked" : "" ?>>
                    <label class="form-check-label" for="quiet_hours_enabled">Enable quiet hours</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Quiet Start</label>
                <input type="time" name="quiet_hours_start" class="form-control" value="<?= h((string) app_setting("quiet_hours_start", "22:00")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Quiet End</label>
                <input type="time" name="quiet_hours_end" class="form-control" value="<?= h((string) app_setting("quiet_hours_end", "06:00")) ?>">
            </div>

            <div class="col-12"><hr></div>
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">MSG91 Configuration</h3>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="msg91_enabled" id="msg91_enabled" <?= app_setting("msg91_enabled", false) ? "checked" : "" ?>>
                    <label class="form-check-label" for="msg91_enabled">Enable MSG91</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Mode</label>
                <select name="msg91_api_mode" class="form-select">
                    <option value="legacy" <?= app_setting("msg91_api_mode", "legacy") === "legacy" ? "selected" : "" ?>>Legacy</option>
                    <option value="flow" <?= app_setting("msg91_api_mode", "legacy") === "flow" ? "selected" : "" ?>>Flow</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Base URL</label>
                <input type="text" name="msg91_base_url" class="form-control" value="<?= h((string) app_setting("msg91_base_url", "https://api.msg91.com")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Auth Key</label>
                <input type="text" name="msg91_auth_key" class="form-control" value="<?= h((string) app_setting("msg91_auth_key", "")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Sender ID</label>
                <input type="text" name="msg91_sender_id" class="form-control" value="<?= h((string) app_setting("msg91_sender_id", "")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Route</label>
                <input type="text" name="msg91_route" class="form-control" value="<?= h((string) app_setting("msg91_route", "4")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Country</label>
                <input type="text" name="msg91_country" class="form-control" value="<?= h((string) app_setting("msg91_country", "91")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">DLT Template ID</label>
                <input type="text" name="msg91_dlt_template_id" class="form-control" value="<?= h((string) app_setting("msg91_dlt_template_id", "")) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Flow ID</label>
                <input type="text" name="msg91_flow_id" class="form-control" value="<?= h((string) app_setting("msg91_flow_id", "")) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Template ID</label>
                <input type="text" name="msg91_template_id" class="form-control" value="<?= h((string) app_setting("msg91_template_id", "")) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Short URL Flag</label>
                <input type="text" name="msg91_short_url" class="form-control" value="<?= h((string) app_setting("msg91_short_url", "0")) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Short URL Expiry</label>
                <input type="text" name="msg91_short_url_expiry" class="form-control" value="<?= h((string) app_setting("msg91_short_url_expiry", "")) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Real Time Response</label>
                <input type="text" name="msg91_realtime_response" class="form-control" value="<?= h((string) app_setting("msg91_realtime_response", "1")) ?>">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Extra Headers JSON</label>
                <textarea name="msg91_headers_json" class="form-control" rows="3"><?= h(json_encode(app_setting("msg91_headers_json", []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Save System Settings</button>
            </div>
        </form>
    </div>

    <div class="glass-panel p-4 mt-4">
        <h2 class="h5 mb-3">Blacklist Numbers</h2>
        <form method="post" class="row g-3 mb-4">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_blacklist">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Reason</label>
                <input type="text" name="reason" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline-danger w-100" type="submit">Add</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Phone</th>
                    <th>Reason</th>
                    <th>Added By</th>
                    <th>Created</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($blacklist as $row): ?>
                    <tr>
                        <td><?= h(display_phone($row["phone"])) ?></td>
                        <td><?= h($row["reason"]) ?></td>
                        <td><?= h($row["username"] ?: "system") ?></td>
                        <td><?= h(pretty_date($row["created_at"])) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Remove this number from blacklist?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="remove_blacklist">
                                <input type="hidden" name="blacklist_id" value="<?= (int) $row["id"] ?>">
                                <button class="btn btn-outline-secondary btn-sm" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="glass-panel p-4 mt-4">
    <h2 class="h5 mb-3">Password</h2>
    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Update Password</button>
        </div>
    </form>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
