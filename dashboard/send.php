<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    $message = trim((string) ($_POST["message_text"] ?? ""));
    $phones = split_phone_input((string) ($_POST["phones"] ?? ""));
    $schedule = trim((string) ($_POST["scheduled_at"] ?? ""));
    $languageCode = trim((string) ($_POST["language_code"] ?? "en")) ?: "en";

    if ($message === "") {
        flash_set("danger", "Message cannot be empty.");
    } elseif ($phones === []) {
        flash_set("danger", "Enter at least one valid phone number.");
    } else {
        if ($schedule !== "") {
            try {
                $schedule = (new DateTimeImmutable($schedule, new DateTimeZone(APP_TIMEZONE)))->format("Y-m-d H:i:s");
            } catch (Throwable) {
                $schedule = "";
            }
        }

        $result = queue_bulk_messages((int) $authUser["id"], $phones, $message, [
            "scheduled_at" => $schedule,
            "language_code" => $languageCode,
            "route_preference" => "auto",
        ]);

        $summary = count($result["queued"]) . " message(s) queued.";
        if ($result["errors"] !== []) {
            $summary .= " " . count($result["errors"]) . " message(s) were skipped.";
        }

        flash_set($result["queued"] !== [] ? "success" : "danger", $summary);
        $_SESSION["send_errors"] = $result["errors"];
        redirect_to("send.php");
    }
}

$pageTitle = "Send SMS";
$activePage = "send.php";
$previewErrors = $_SESSION["send_errors"] ?? [];
unset($_SESSION["send_errors"]);

require __DIR__ . "/partials/header.php";
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-panel p-4">
            <h2 class="h5 mb-3">Compose Message</h2>
            <form method="post" class="d-grid gap-3">
                <?= csrf_input() ?>
                <div>
                    <label class="form-label fw-semibold">Phone Numbers</label>
                    <textarea name="phones" class="form-control" rows="6" placeholder="Enter one number per line, or separate with comma / space" required></textarea>
                    <div class="form-text">Indian 10-digit numbers are auto-normalized to `91XXXXXXXXXX`.</div>
                </div>
                <div>
                    <label class="form-label fw-semibold">Message</label>
                    <textarea name="message_text" class="form-control" rows="7" maxlength="1000" required></textarea>
                    <div class="form-text">The default signature from settings is appended when the queue worker sends the message.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Language Code</label>
                        <input type="text" name="language_code" class="form-control" value="en">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Queue Messages</button>
                    <a href="upload.php" class="btn btn-outline-secondary">Open Bulk Upload</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass-panel p-4 mb-4">
            <h2 class="h5 mb-3">Routing Priority</h2>
            <ol class="mb-0">
                <li>Active online device assigned to the user</li>
                <li>MSG91 if enabled and fully configured</li>
                <li>Active custom gateway</li>
                <li>Retry and then mark failed</li>
            </ol>
        </div>
        <div class="glass-panel p-4">
            <h2 class="h5 mb-3">Last Queue Issues</h2>
            <?php if ($previewErrors === []): ?>
                <div class="text-secondary">No skipped rows in the last request.</div>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($previewErrors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
