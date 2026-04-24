<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

if (PHP_SAPI === "cli") {
    $limit = 25;
    foreach ($argv as $argument) {
        if (str_starts_with($argument, "--limit=")) {
            $limit = max(1, (int) substr($argument, 8));
        }
    }

    echo json_encode(run_queue_worker($limit), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$authUser = require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $limit = max(1, (int) ($_POST["limit"] ?? 25));
    $_SESSION["worker_last_run"] = run_queue_worker($limit);
    flash_set("success", "Queue worker executed successfully.");
    redirect_to("worker.php");
}

$lastRun = $_SESSION["worker_last_run"] ?? null;

$pageTitle = "Queue Worker";
$activePage = "worker.php";

require __DIR__ . "/partials/header.php";
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="glass-panel p-4 h-100">
            <h2 class="h5 mb-3">Run Worker Now</h2>
            <form method="post" class="row g-3">
                <?= csrf_input() ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Batch Size</label>
                    <input type="number" name="limit" class="form-control" value="<?= (int) app_setting("cron_batch_size", 25) ?>" min="1" max="500">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Process Queue</button>
                </div>
            </form>

            <?php if ($lastRun !== null): ?>
                <div class="mt-4">
                    <h3 class="h6">Last Manual Run</h3>
                    <pre class="bg-light p-3 rounded-4 mb-0"><?= h(json_encode($lastRun, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass-panel p-4 h-100">
            <h2 class="h5 mb-3">Cron Command</h2>
            <p class="text-secondary">Run this every minute on the server:</p>
            <pre class="bg-light p-3 rounded-4">* * * * * php <?= h(realpath(__FILE__) ?: __FILE__) ?> --limit=25</pre>
            <p class="text-secondary mb-0">
                The worker resets device counters at midnight IST, refreshes heartbeat status, retries stale assignments,
                then applies the route order: device, MSG91, custom API.
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
