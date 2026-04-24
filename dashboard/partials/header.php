<?php
declare(strict_types=1);

require_once __DIR__ . "/../functions.php";

$authUser = $authUser ?? require_login();
$pageTitle = $pageTitle ?? "SMS Gateway";
$activePage = $activePage ?? current_url_basename();
$pageActions = $pageActions ?? "";
$flash = flash_get();
$navItems = bootstrap_nav_items($authUser);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | SMS Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --app-bg: #f3efe7;
            --panel: #ffffff;
            --ink: #1e2432;
            --muted: #6d7280;
            --line: rgba(21, 31, 52, 0.08);
            --primary: #0b6bcb;
            --primary-soft: rgba(11, 107, 203, 0.12);
            --accent: #ff9a3d;
            --success-soft: rgba(25, 135, 84, 0.12);
            --danger-soft: rgba(220, 53, 69, 0.12);
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(11, 107, 203, 0.16), transparent 28%),
                radial-gradient(circle at bottom right, rgba(255, 154, 61, 0.18), transparent 22%),
                var(--app-bg);
            color: var(--ink);
            font-family: "Manrope", sans-serif;
        }

        .brand-font {
            font-family: "Space Grotesk", sans-serif;
        }

        .app-sidebar {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(18px);
            border-right: 1px solid var(--line);
            min-height: 100vh;
        }

        .app-sidebar .nav-link {
            color: var(--ink);
            border-radius: 14px;
            padding: 0.8rem 1rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .app-sidebar .nav-link.active,
        .app-sidebar .nav-link:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(30, 36, 50, 0.07);
        }

        .stat-card {
            border: 0;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.82));
            box-shadow: 0 18px 40px rgba(30, 36, 50, 0.06);
        }

        .muted {
            color: var(--muted);
        }

        .table thead th {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom-width: 1px;
        }

        .table td, .table th {
            vertical-align: middle;
        }

        .badge-soft-primary { background: var(--primary-soft); color: var(--primary); }
        .badge-soft-success { background: var(--success-soft); color: #146c43; }
        .badge-soft-danger { background: var(--danger-soft); color: #b02a37; }
        .badge-soft-warning { background: rgba(255, 193, 7, 0.16); color: #9a6700; }
        .badge-soft-secondary { background: rgba(108, 117, 125, 0.12); color: #495057; }

        .content-wrap {
            min-height: 100vh;
        }

        .page-shell {
            padding: 1.5rem;
        }

        .section-title {
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.85rem;
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .page-shell {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-12 col-lg-3 col-xl-2 app-sidebar p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <div class="brand-font fw-bold fs-4">SMS Gateway</div>
                    <div class="muted small"><?= $authUser["role"] === "admin" ? "Admin Console" : "User Workspace" ?></div>
                </div>
                <span class="badge badge-soft-primary rounded-pill"><?= h(strtoupper($authUser["role"])) ?></span>
            </div>
            <nav class="nav flex-column">
                <?php foreach ($navItems as $item): ?>
                    <a class="nav-link <?= $activePage === $item["key"] ? "active" : "" ?>" href="<?= h($item["href"]) ?>">
                        <?= h($item["label"]) ?>
                    </a>
                <?php endforeach; ?>
                <a class="nav-link text-danger" href="logout.php">Logout</a>
            </nav>
        </aside>
        <div class="col-12 col-lg-9 col-xl-10 content-wrap">
            <div class="page-shell">
                <div class="glass-panel p-3 p-md-4 mb-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="section-title">Operations</div>
                            <h1 class="brand-font h3 mb-1"><?= h($pageTitle) ?></h1>
                            <div class="muted">Signed in as <?= h($authUser["full_name"]) ?> (<?= h($authUser["username"]) ?>)</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <?= $pageActions ?>
                        </div>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="alert alert-<?= h($flash["type"]) ?> alert-dismissible fade show" role="alert">
                        <?= h($flash["message"]) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
