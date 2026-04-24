<?php
declare(strict_types=1);

require_once __DIR__ . "/functions.php";

if (is_logged_in()) {
    redirect_to("index.php");
}

$flash = flash_get();
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim((string) ($_POST["username"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");

    if ($username === "" || $password === "") {
        $error = "Username and password are required.";
    } else {
        $user = authenticate_user($username, $password);
        if ($user === null || $user["status"] !== "active") {
            $error = "Invalid login credentials.";
            log_activity("auth", "login.failed", ["username" => $username]);
        } else {
            login_user($user);
            log_activity("auth", "login.success", ["username" => $username], (int) $user["id"]);
            if ((bool) $user["force_password_reset"]) {
                flash_set("warning", "Please change your password before continuing regular work.");
            }
            redirect_to("index.php");
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SMS Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top left, rgba(11, 107, 203, 0.24), transparent 30%),
                radial-gradient(circle at bottom right, rgba(255, 154, 61, 0.22), transparent 24%),
                #f3efe7;
            font-family: "Manrope", sans-serif;
            color: #1e2432;
        }

        .login-card {
            width: min(460px, calc(100vw - 2rem));
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(18, 26, 42, 0.08);
            border-radius: 28px;
            box-shadow: 0 30px 70px rgba(18, 26, 42, 0.1);
            backdrop-filter: blur(18px);
        }

        .brand-font {
            font-family: "Space Grotesk", sans-serif;
        }
    </style>
</head>
<body>
    <div class="login-card p-4 p-md-5">
        <div class="mb-4">
            <div class="brand-font fs-2 fw-bold">SMS Gateway</div>
            <p class="text-secondary mb-0">Secure sign in for your SaaS SMS control panel.</p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= h($flash["type"]) ?>"><?= h($flash["message"]) ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="d-grid gap-3">
            <div>
                <label class="form-label fw-semibold">Username</label>
                <input type="text" name="username" class="form-control form-control-lg" autocomplete="username" required>
            </div>
            <div>
                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
        </form>

        <div class="mt-4 small text-secondary">
            Default installer admin: <strong>admin</strong> / <strong>admin123</strong>
        </div>
    </div>
</body>
</html>
