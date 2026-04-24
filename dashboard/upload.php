<?php
declare(strict_types=1);

require_once __DIR__ . "/auth_check.php";

if (isset($_GET["sample"])) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"sample_contacts.csv\"");
    echo sample_csv_contents();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $action = (string) ($_POST["action"] ?? "");

        if ($action === "upload_csv") {
            if (!isset($_FILES["csv_file"]) || $_FILES["csv_file"]["error"] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Please upload a valid CSV file.");
            }

            $rows = parse_contacts_csv($_FILES["csv_file"]["tmp_name"]);
            if ($rows === []) {
                throw new RuntimeException("No valid contacts were found in the CSV.");
            }

            $_SESSION["csv_preview_rows"] = $rows;
            flash_set("success", count($rows) . " contact(s) loaded for preview.");
        }

        if ($action === "queue_preview") {
            $names = $_POST["row_name"] ?? [];
            $phones = $_POST["row_phone"] ?? [];
            $keep = $_POST["keep"] ?? [];
            $campaignName = trim((string) ($_POST["campaign_name"] ?? "CSV Campaign"));
            $messageText = trim((string) ($_POST["message_text"] ?? ""));
            $scheduledAt = trim((string) ($_POST["scheduled_at"] ?? ""));

            if ($messageText === "") {
                throw new RuntimeException("Message text is required.");
            }

            $contacts = [];
            foreach ($phones as $index => $phone) {
                if (!isset($keep[$index])) {
                    continue;
                }

                $normalized = normalize_phone((string) $phone);
                if ($normalized === null) {
                    continue;
                }

                $contacts[] = [
                    "name" => trim((string) ($names[$index] ?? "")),
                    "phone" => $normalized,
                    "language_code" => "en",
                ];
            }

            if ($contacts === []) {
                throw new RuntimeException("No rows were selected for queueing.");
            }

            if ($scheduledAt !== "") {
                $scheduledAt = (new DateTimeImmutable($scheduledAt, new DateTimeZone(APP_TIMEZONE)))->format("Y-m-d H:i:s");
            }

            $result = queue_campaign_from_contacts((int) $authUser["id"], $campaignName, $messageText, $contacts, [
                "scheduled_at" => $scheduledAt,
                "route_preference" => "auto",
            ]);

            unset($_SESSION["csv_preview_rows"]);
            flash_set("success", "Campaign created. Queued {$result["queued"]} message(s), skipped {$result["failed"]}.");
            redirect_to("messages.php");
        }
    } catch (Throwable $exception) {
        flash_set("danger", $exception->getMessage());
    }

    redirect_to("upload.php");
}

$previewRows = $_SESSION["csv_preview_rows"] ?? [];

$pageTitle = "Bulk Upload";
$activePage = "upload.php";
$pageActions = '<a href="upload.php?sample=1" class="btn btn-outline-secondary">Download Sample CSV</a>';

require __DIR__ . "/partials/header.php";
?>

<div class="glass-panel p-4 mb-4">
    <h2 class="h5 mb-3">Upload CSV</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="upload_csv">
        <div class="col-md-8">
            <label class="form-label fw-semibold">CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            <div class="form-text">Required columns: `name` and `phone`.</div>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Preview CSV</button>
        </div>
    </form>
</div>

<?php if ($previewRows !== []): ?>
    <div class="glass-panel p-4">
        <h2 class="h5 mb-3">Preview And Queue</h2>
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="queue_preview">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Campaign Name</label>
                <input type="text" name="campaign_name" class="form-control" value="CSV Campaign" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Schedule</label>
                <input type="datetime-local" name="scheduled_at" class="form-control">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-text">Uncheck rows you want to exclude before queueing.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Message</label>
                <textarea name="message_text" class="form-control" rows="5" required></textarea>
            </div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Keep</th>
                            <th>Name</th>
                            <th>Phone</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($previewRows as $index => $row): ?>
                            <tr>
                                <td><input type="checkbox" name="keep[<?= (int) $index ?>]" value="1" checked></td>
                                <td><input type="text" name="row_name[<?= (int) $index ?>]" class="form-control" value="<?= h($row["name"]) ?>"></td>
                                <td><input type="text" name="row_phone[<?= (int) $index ?>]" class="form-control" value="<?= h($row["phone"]) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Create Campaign And Queue</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . "/partials/footer.php"; ?>
