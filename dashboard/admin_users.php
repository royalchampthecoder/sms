<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

$authUser = require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $action = (string) ($_POST["action"] ?? "");

        if ($action === "create_user") {
            $userId = create_user_account([
                "role" => $_POST["role"] ?? "user",
                "full_name" => $_POST["full_name"] ?? "",
                "username" => $_POST["username"] ?? "",
                "email" => $_POST["email"] ?? "",
                "password" => $_POST["password"] ?? "",
                "status" => $_POST["status"] ?? "active",
                "device_access" => $_POST["device_access"] ?? "selected",
                "messages_per_minute" => (int) ($_POST["messages_per_minute"] ?? 30),
                "force_password_reset" => !empty($_POST["force_password_reset"]),
            ]);
            sync_user_devices($userId, (string) ($_POST["device_access"] ?? "selected"), array_map("intval", $_POST["device_ids"] ?? []));
            flash_set("success", "User created successfully.");
        }

        if ($action === "update_user") {
            $userId = (int) $_POST["user_id"];
            update_user_account($userId, [
                "role" => $_POST["role"] ?? "user",
                "full_name" => $_POST["full_name"] ?? "",
                "username" => $_POST["username"] ?? "",
                "email" => $_POST["email"] ?? "",
                "status" => $_POST["status"] ?? "active",
                "device_access" => $_POST["device_access"] ?? "selected",
                "messages_per_minute" => (int) ($_POST["messages_per_minute"] ?? 30),
                "force_password_reset" => !empty($_POST["force_password_reset"]),
            ]);
            sync_user_devices($userId, (string) ($_POST["device_access"] ?? "selected"), array_map("intval", $_POST["device_ids"] ?? []));
            flash_set("success", "User updated successfully.");
        }

        if ($action === "reset_password") {
            set_user_password((int) $_POST["user_id"], (string) $_POST["new_password"], true);
            flash_set("success", "Password reset completed.");
        }

        if ($action === "delete_user") {
            delete_user_account((int) $_POST["user_id"]);
            flash_set("success", "User deleted.");
        }
    } catch (Throwable $exception) {
        flash_set("danger", $exception->getMessage());
    }

    redirect_to("admin_users.php");
}

$users = list_users();
$devices = list_devices();

$pageTitle = "Users";
$activePage = "admin_users.php";

require __DIR__ . "/partials/header.php";
?>

<div class="glass-panel p-4 mb-4">
    <h2 class="h5 mb-3">Create User</h2>
    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create_user">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Password</label>
            <input type="text" name="password" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Role</label>
            <select name="role" class="form-select">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Device Access</label>
            <select name="device_access" class="form-select">
                <option value="selected">Selected Devices</option>
                <option value="all">All Devices</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Messages / Minute</label>
            <input type="number" name="messages_per_minute" class="form-control" value="30" min="1">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Assign Devices</label>
            <select name="device_ids[]" class="form-select" multiple size="4">
                <?php foreach ($devices as $device): ?>
                    <option value="<?= (int) $device["id"] ?>"><?= h($device["device_name"]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="force_password_reset" id="force_password_reset" checked>
                <label class="form-check-label" for="force_password_reset">Force password change on first login</label>
            </div>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Create User</button>
        </div>
    </form>
</div>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Status</th>
                <th>Role</th>
                <th>Access</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $selectedDeviceIds = user_device_ids((int) $user["id"]); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= h($user["full_name"]) ?></div>
                        <div class="small text-secondary"><?= h($user["email"] ?: "No email") ?></div>
                    </td>
                    <td><?= h($user["username"]) ?></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($user["status"]) ?>"><?= h($user["status"]) ?></span></td>
                    <td><?= h($user["role"]) ?></td>
                    <td><?= h($user["device_access"]) ?></td>
                    <td><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#user-<?= (int) $user["id"] ?>">Manage</button></td>
                </tr>
                <tr class="collapse" id="user-<?= (int) $user["id"] ?>">
                    <td colspan="6">
                        <div class="border rounded-4 p-3 bg-white">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= (int) $user["id"] ?>">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Full Name</label>
                                    <input class="form-control" name="full_name" value="<?= h($user["full_name"]) ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Username</label>
                                    <input class="form-control" name="username" value="<?= h($user["username"]) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input class="form-control" name="email" value="<?= h($user["email"]) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Role</label>
                                    <select name="role" class="form-select">
                                        <option value="user" <?= $user["role"] === "user" ? "selected" : "" ?>>User</option>
                                        <option value="admin" <?= $user["role"] === "admin" ? "selected" : "" ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= $user["status"] === "active" ? "selected" : "" ?>>Active</option>
                                        <option value="inactive" <?= $user["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Device Access</label>
                                    <select name="device_access" class="form-select">
                                        <option value="selected" <?= $user["device_access"] === "selected" ? "selected" : "" ?>>Selected</option>
                                        <option value="all" <?= $user["device_access"] === "all" ? "selected" : "" ?>>All</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Messages / Minute</label>
                                    <input class="form-control" name="messages_per_minute" type="number" value="<?= (int) $user["messages_per_minute"] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Assigned Devices</label>
                                    <select name="device_ids[]" class="form-select" multiple size="4">
                                        <?php foreach ($devices as $device): ?>
                                            <option value="<?= (int) $device["id"] ?>" <?= in_array((int) $device["id"], $selectedDeviceIds, true) ? "selected" : "" ?>>
                                                <?= h($device["device_name"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="force_password_reset" id="reset-<?= (int) $user["id"] ?>" <?= (bool) $user["force_password_reset"] ? "checked" : "" ?>>
                                        <label class="form-check-label" for="reset-<?= (int) $user["id"] ?>">Force password reset</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Save User</button>
                                </div>
                            </form>
                            <div class="d-flex gap-2 flex-wrap">
                            <form method="post" class="d-inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $user["id"] ?>">
                                <input type="hidden" name="new_password" value="ChangeMe123!">
                                <button class="btn btn-outline-secondary" type="submit">Reset Password</button>
                            </form>
                            <?php if ((int) $user["id"] !== (int) $authUser["id"]): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int) $user["id"] ?>">
                                    <button class="btn btn-outline-danger" type="submit">Delete</button>
                                </form>
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
