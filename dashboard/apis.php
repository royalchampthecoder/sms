<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

$authUser = require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $action = (string) ($_POST["action"] ?? "");

        if ($action === "create_api_client") {
            create_api_client([
                "user_id" => (int) ($_POST["user_id"] ?? 0),
                "name" => $_POST["name"] ?? "",
                "valid_until" => normalize_datetime_input($_POST["valid_until"] ?? "") ?? now_ist()->modify("+30 days")->format("Y-m-d H:i:s"),
                "status" => $_POST["status"] ?? "active",
                "rate_limit_per_minute" => (int) ($_POST["rate_limit_per_minute"] ?? 60),
            ]);
            flash_set("success", "API client created.");
        }

        if ($action === "update_api_client") {
            update_api_client((int) $_POST["api_id"], [
                "user_id" => (int) ($_POST["user_id"] ?? 0),
                "name" => $_POST["name"] ?? "",
                "valid_until" => normalize_datetime_input($_POST["valid_until"] ?? "") ?? now_ist()->modify("+30 days")->format("Y-m-d H:i:s"),
                "status" => $_POST["status"] ?? "active",
                "rate_limit_per_minute" => (int) ($_POST["rate_limit_per_minute"] ?? 60),
            ]);
            flash_set("success", "API client updated.");
        }

        if ($action === "delete_api_client") {
            delete_api_client((int) $_POST["api_id"]);
            flash_set("success", "API client deleted.");
        }

        if ($action === "save_gateway") {
            $gatewayId = (int) ($_POST["gateway_id"] ?? 0);
            save_custom_gateway([
                "name" => $_POST["name"] ?? "",
                "endpoint_url" => $_POST["endpoint_url"] ?? "",
                "http_method" => $_POST["http_method"] ?? "POST",
                "headers_json" => $_POST["headers_json"] ?? "{}",
                "body_template" => $_POST["body_template"] ?? "",
                "phone_param" => $_POST["phone_param"] ?? "phone",
                "message_param" => $_POST["message_param"] ?? "message",
                "extra_params_json" => $_POST["extra_params_json"] ?? "{}",
                "success_keyword" => $_POST["success_keyword"] ?? "success",
                "status" => $_POST["status"] ?? "inactive",
                "valid_until" => normalize_datetime_input($_POST["valid_until"] ?? ""),
                "priority" => (int) ($_POST["priority"] ?? 100),
            ], $gatewayId > 0 ? $gatewayId : null);
            flash_set("success", "Custom gateway saved.");
        }

        if ($action === "delete_gateway") {
            delete_custom_gateway((int) $_POST["gateway_id"]);
            flash_set("success", "Custom gateway deleted.");
        }
    } catch (Throwable $exception) {
        flash_set("danger", $exception->getMessage());
    }

    redirect_to("apis.php");
}

$users = list_users();
$apiClients = list_api_clients();
$gateways = list_custom_gateways();

$pageTitle = "API Management";
$activePage = "apis.php";

require __DIR__ . "/partials/header.php";
?>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="glass-panel p-4 h-100">
            <h2 class="h5 mb-3">Create External API Key</h2>
            <form method="post" class="row g-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create_api_client">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Name</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Linked User</label>
                    <select name="user_id" class="form-select">
                        <option value="0">Default admin account</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user["id"] ?>"><?= h($user["username"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Valid Until</label>
                    <input type="datetime-local" name="valid_until" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rate Limit / Min</label>
                    <input type="number" name="rate_limit_per_minute" class="form-control" value="60" min="1">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create API Key</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass-panel p-4 h-100">
            <h2 class="h5 mb-3">Create Custom Delivery Gateway</h2>
            <form method="post" class="row g-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_gateway">
                <input type="hidden" name="gateway_id" value="0">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Name</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Endpoint URL</label>
                    <input name="endpoint_url" class="form-control" placeholder="https://example.com/send" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Method</label>
                    <select name="http_method" class="form-select">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
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
                    <label class="form-label fw-semibold">Priority</label>
                    <input type="number" name="priority" class="form-control" value="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Valid Until</label>
                    <input type="datetime-local" name="valid_until" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Param</label>
                    <input name="phone_param" class="form-control" value="phone">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Message Param</label>
                    <input name="message_param" class="form-control" value="message">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Headers JSON</label>
                    <textarea name="headers_json" class="form-control" rows="2">{}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Extra Params JSON</label>
                    <textarea name="extra_params_json" class="form-control" rows="2">{}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Body Template</label>
                    <textarea name="body_template" class="form-control" rows="4" placeholder='Optional. Use placeholders like {{"phone"}} and {{"message"}}'></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Success Keyword</label>
                    <input name="success_keyword" class="form-control" value="success">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save Gateway</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="glass-panel p-4 mb-4">
    <h2 class="h5 mb-3">External API Keys</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Owner</th>
                <th>Key</th>
                <th>Status</th>
                <th>Valid Until</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($apiClients as $api): ?>
                <tr>
                    <td><?= h($api["name"]) ?></td>
                    <td><?= h($api["username"] ?: "admin") ?></td>
                    <td><code><?= h($api["api_key"]) ?></code></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($api["status"]) ?>"><?= h($api["status"]) ?></span></td>
                    <td><?= h(pretty_date($api["valid_until"])) ?></td>
                    <td><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#api-<?= (int) $api["id"] ?>">Manage</button></td>
                </tr>
                <tr class="collapse" id="api-<?= (int) $api["id"] ?>">
                    <td colspan="6">
                        <div class="border rounded-4 p-3 bg-white">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_api_client">
                                <input type="hidden" name="api_id" value="<?= (int) $api["id"] ?>">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Name</label>
                                    <input class="form-control" name="name" value="<?= h($api["name"]) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">User</label>
                                    <select name="user_id" class="form-select">
                                        <option value="0">Default admin account</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= (int) $user["id"] ?>" <?= (int) ($api["user_id"] ?? 0) === (int) $user["id"] ? "selected" : "" ?>><?= h($user["username"]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <?php foreach (["active", "disabled", "disconnected", "expired"] as $status): ?>
                                            <option value="<?= h($status) ?>" <?= $api["status"] === $status ? "selected" : "" ?>><?= h($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Rate / Min</label>
                                    <input class="form-control" type="number" name="rate_limit_per_minute" value="<?= (int) $api["rate_limit_per_minute"] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Valid Until</label>
                                    <input class="form-control" type="datetime-local" name="valid_until" value="<?= h(str_replace(' ', 'T', substr((string) $api["valid_until"], 0, 16))) ?>">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Save API Key</button>
                                </div>
                            </form>
                            <div class="d-flex gap-2 flex-wrap">
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this API key?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete_api_client">
                                <input type="hidden" name="api_id" value="<?= (int) $api["id"] ?>">
                                <button class="btn btn-outline-danger" type="submit">Delete</button>
                            </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="glass-panel p-4">
    <h2 class="h5 mb-3">Custom Delivery Gateways</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Endpoint</th>
                <th>Method</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($gateways as $gateway): ?>
                <tr>
                    <td><?= h($gateway["name"]) ?></td>
                    <td><?= h($gateway["endpoint_url"]) ?></td>
                    <td><?= h($gateway["http_method"]) ?></td>
                    <td><span class="badge rounded-pill <?= badge_class_for_status($gateway["status"]) ?>"><?= h($gateway["status"]) ?></span></td>
                    <td><?= (int) $gateway["priority"] ?></td>
                    <td><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gateway-<?= (int) $gateway["id"] ?>">Manage</button></td>
                </tr>
                <tr class="collapse" id="gateway-<?= (int) $gateway["id"] ?>">
                    <td colspan="6">
                        <div class="border rounded-4 p-3 bg-white">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="save_gateway">
                                <input type="hidden" name="gateway_id" value="<?= (int) $gateway["id"] ?>">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Name</label>
                                    <input class="form-control" name="name" value="<?= h($gateway["name"]) ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Endpoint URL</label>
                                    <input class="form-control" name="endpoint_url" value="<?= h($gateway["endpoint_url"]) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Method</label>
                                    <select name="http_method" class="form-select">
                                        <option value="POST" <?= $gateway["http_method"] === "POST" ? "selected" : "" ?>>POST</option>
                                        <option value="GET" <?= $gateway["http_method"] === "GET" ? "selected" : "" ?>>GET</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <?php foreach (["active", "inactive", "disconnected", "expired"] as $status): ?>
                                            <option value="<?= h($status) ?>" <?= $gateway["status"] === $status ? "selected" : "" ?>><?= h($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Priority</label>
                                    <input class="form-control" type="number" name="priority" value="<?= (int) $gateway["priority"] ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Valid Until</label>
                                    <input class="form-control" type="datetime-local" name="valid_until" value="<?= h($gateway["valid_until"] ? str_replace(' ', 'T', substr((string) $gateway["valid_until"], 0, 16)) : "") ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Success Keyword</label>
                                    <input class="form-control" name="success_keyword" value="<?= h($gateway["success_keyword"]) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone Param</label>
                                    <input class="form-control" name="phone_param" value="<?= h($gateway["phone_param"]) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Message Param</label>
                                    <input class="form-control" name="message_param" value="<?= h($gateway["message_param"]) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Headers JSON</label>
                                    <textarea class="form-control" name="headers_json" rows="2"><?= h($gateway["headers_json"]) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Extra Params JSON</label>
                                    <textarea class="form-control" name="extra_params_json" rows="2"><?= h($gateway["extra_params_json"]) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Body Template</label>
                                    <textarea class="form-control" name="body_template" rows="4"><?= h($gateway["body_template"]) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Save Gateway</button>
                                </div>
                            </form>
                            <div class="d-flex gap-2 flex-wrap">
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this gateway?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete_gateway">
                                <input type="hidden" name="gateway_id" value="<?= (int) $gateway["id"] ?>">
                                <button class="btn btn-outline-danger" type="submit">Delete</button>
                            </form>
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
