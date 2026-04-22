<?php
require_once 'functions.php';
requireAdmin();

// Get statistics
$stats = getDashboardStats();
$users = getAllUsers();
$devices = getAllDevices();
$apis = getAllApis();

// Recent logs
$recentLogs = [];
$result = $conn->query("SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.uid = u.id ORDER BY l.created_at DESC LIMIT 10");
$recentLogs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Gateway Admin" name="author">
  <title>Admin Dashboard - SMS Gateway</title>

  <!-- Favicon icon-->
  <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon/favicon-32x32.png" />

  <!-- Color modes -->
  <script src="./assets/js/vendors/color-modes.js"></script>
  <script>
    if (localStorage.getItem('sidebarExpanded') === 'false') {
      document.documentElement.classList.add('collapsed');
      document.documentElement.classList.remove('expanded');
    } else {
      document.documentElement.classList.remove('collapsed');
      document.documentElement.classList.add('expanded');
    }
  </script>

  <!-- Libs CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@6.2.1/dist/simplebar.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css" />

  <!-- Theme CSS -->
  <link rel="stylesheet" href="./assets/css/theme.css" />

  <!-- Custom styles -->
  <style>
  .bg-gradient-mixed { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

  /* Sidebar collapse styles */
  #miniSidebar {
    transition: width 0.3s ease;
  }

  /* Hide text when collapsed */
  html.collapsed #miniSidebar .text {
    display: none !important;
  }

  html.expanded #miniSidebar .text {
    display: inline !important;
  }

  /* Active nav link styling */
  #miniSidebar .nav-link.active {
    background-color: rgba(102, 126, 234, 0.1);
    color: #667eea;
  }

  #miniSidebar .nav-link.active .nav-icon {
    color: #667eea;
  }

  /* Adjust custom container padding */
  .custom-container {
    padding: 1.5rem 1.5rem;
  }

  .stat-card {
    transition: transform 0.2s ease;
  }

  .stat-card:hover {
    transform: translateY(-2px);
  }
  </style>
</head>

<body>
  <!-- Vertical Sidebar -->
  <div>
    <!-- Sidebar -->
    <div id="miniSidebar">
      <div class="brand-logo">
        <a class="d-none d-md-flex align-items-center gap-2" href="admin.php">
          <span class="fw-bold fs-4 site-logo-text">SMS Admin</span>
        </a>
      </div>
      <ul class="navbar-nav flex-column">
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link active" href="admin.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-dashboard">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 4h6v8h-6z" />
                <path d="M4 16h6v4h-6z" />
                <path d="M14 12h6v8h-6z" />
                <path d="M14 4h6v4h-6z" />
              </svg></span>
            <span class="text">Dashboard</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_users.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
              </svg></span>
            <span class="text">Users</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_devices.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                <path d="M11 4h2" />
                <path d="M12 17v.01" />
              </svg></span>
            <span class="text">Devices</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_apis.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-api">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 13h5" />
                <path d="M12 16v-8h3a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-3" />
                <path d="M20 8v8" />
                <path d="M9 16v-5.5a2.5 2.5 0 0 1 5 0v5.5" />
              </svg></span>
            <span class="text">APIs</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_messages.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M8 9h8" />
                <path d="M8 13h6" />
                <path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 4v-4h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
              </svg></span>
            <span class="text">Messages</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_settings.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c.593 .36 1.269 .36 1.862 0z" />
                <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
              </svg></span>
            <span class="text">Settings</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link" href="admin_logs.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                <path d="M9 9h6" />
                <path d="M9 13h6" />
                <path d="M9 17h6" />
              </svg></span>
            <span class="text">Logs</span>
          </a>
        </li>
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-logout">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                <path d="M9 12h12l-3 -3" />
                <path d="M18 15l3 -3" />
              </svg></span>
            <span class="text">Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- Main Content -->
    <div id="content" class="position-relative h-100">
      <!-- Topbar -->
      <div class="navbar-glass navbar navbar-expand-lg px-0 px-lg-4">
        <div class="container-fluid px-lg-0">
          <div class="d-flex align-items-center gap-4">
            <!-- Collapse -->
            <div class="d-block d-lg-none">
              <a class="text-inherit" data-bs-toggle="offcanvas" href="#offcanvasExample" role="button" aria-controls="offcanvasExample">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-menu-2">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                  <path d="M4 6l16 0" />
                  <path d="M4 12l16 0" />
                  <path d="M4 18l16 0" />
                </svg>
              </a>
            </div>
            <div class="d-none d-lg-block">
              <a class="sidebar-toggle d-flex texttooltip p-3" href="javascript:void(0)" data-template="collapseMessage">
                <span class="collapse-mini">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-bar-left text-secondary">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M4 12l10 0" />
                    <path d="M4 12l4 4" />
                    <path d="M4 12l4 -4" />
                    <path d="M20 4l0 16" />
                  </svg>
                </span>
                <span class="collapse-expanded">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-bar-right text-secondary">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M20 12l-10 0" />
                    <path d="M20 12l-4 4" />
                    <path d="M20 12l-4 -4" />
                    <path d="M4 4l0 16" />
                  </svg>
                  <div id="collapseMessage" class="d-none">
                    <span class="small">Collapse</span>
                  </div>
                </span>
              </a>
            </div>
          </div>
          <div class="ms-auto d-flex align-items-center gap-3">
            <div class="dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center py-0 px-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar avatar-sm">
                  <span class="avatar-initial rounded-circle bg-primary text-white">A</span>
                </div>
                <div class="ms-2 d-none d-lg-block">
                  <div class="fw-medium text-sm"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                  <div class="text-muted text-xs">Administrator</div>
                </div>
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="admin_settings.php">Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Container -->
      <div class="custom-container">
        <!-- Heading -->
        <div class="mb-6">
          <h1 class="fs-4">Admin Dashboard</h1>
          <p class="text-muted">Overview of SMS Gateway system</p>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-6">
          <div class="col-md-3">
            <div class="card card-lg stat-card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-md bg-primary-subtle text-primary rounded-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                        <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
                      </svg>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h4 class="mb-0"><?php echo count($users); ?></h4>
                    <p class="text-muted mb-0">Total Users</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card card-lg stat-card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-md bg-success-subtle text-success rounded-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                        <path d="M11 4h2" />
                        <path d="M12 17v.01" />
                      </svg>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h4 class="mb-0"><?php echo $stats['devices']['online_devices'] ?? 0; ?>/<?php echo $stats['devices']['total_devices'] ?? 0; ?></h4>
                    <p class="text-muted mb-0">Online Devices</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card card-lg stat-card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-md bg-info-subtle text-info rounded-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-api">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 13h5" />
                        <path d="M12 16v-8h3a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-3" />
                        <path d="M20 8v8" />
                        <path d="M9 16v-5.5a2.5 2.5 0 0 1 5 0v5.5" />
                      </svg>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h4 class="mb-0"><?php echo $stats['apis']['total_apis'] ?? 0; ?></h4>
                    <p class="text-muted mb-0">Active APIs</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card card-lg stat-card">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-md bg-warning-subtle text-warning rounded-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M8 9h8" />
                        <path d="M8 13h6" />
                        <path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 4v-4h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                      </svg>
                    </div>
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h4 class="mb-0"><?php echo $stats['today']['today_sent'] ?? 0; ?></h4>
                    <p class="text-muted mb-0">SMS Sent Today</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Message Stats -->
        <div class="row g-4 mb-6">
          <div class="col-lg-8">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">Message Statistics</h5>
                </div>
              </div>
              <div class="card-body">
                <div class="row g-4">
                  <div class="col-md-3">
                    <div class="text-center">
                      <div class="fs-2 fw-bold text-primary"><?php echo $stats['messages']['total'] ?? 0; ?></div>
                      <div class="text-muted">Total</div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center">
                      <div class="fs-2 fw-bold text-success"><?php echo $stats['messages']['sent'] ?? 0; ?></div>
                      <div class="text-muted">Sent</div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center">
                      <div class="fs-2 fw-bold text-warning"><?php echo $stats['messages']['pending'] ?? 0; ?></div>
                      <div class="text-muted">Pending</div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-center">
                      <div class="fs-2 fw-bold text-danger"><?php echo $stats['messages']['failed'] ?? 0; ?></div>
                      <div class="text-muted">Failed</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">System Status</h5>
                </div>
              </div>
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-sm bg-success-subtle text-success rounded-3 me-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M5 12l5 5l10 -10" />
                      </svg>
                    </div>
                  </div>
                  <div>
                    <div class="fw-medium">Database</div>
                    <div class="text-muted text-sm">Connected</div>
                  </div>
                </div>

                <div class="d-flex align-items-center mb-3">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-sm bg-<?php echo getSetting('msg91_enabled') ? 'success' : 'secondary'; ?>-subtle text-<?php echo getSetting('msg91_enabled') ? 'success' : 'secondary'; ?> rounded-3 me-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-api">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 13h5" />
                        <path d="M12 16v-8h3a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-3" />
                        <path d="M20 8v8" />
                        <path d="M9 16v-5.5a2.5 2.5 0 0 1 5 0v5.5" />
                      </svg>
                    </div>
                  </div>
                  <div>
                    <div class="fw-medium">MSG91 API</div>
                    <div class="text-muted text-sm"><?php echo getSetting('msg91_enabled') ? 'Enabled' : 'Disabled'; ?></div>
                  </div>
                </div>

                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0">
                    <div class="icon-shape icon-sm bg-info-subtle text-info rounded-3 me-3">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                        <path d="M12 7l0 5l3 3" />
                      </svg>
                    </div>
                  </div>
                  <div>
                    <div class="fw-medium">Last Update</div>
                    <div class="text-muted text-sm"><?php echo date('H:i:s'); ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4">
          <div class="col-lg-12">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">Recent Activity</h5>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($recentLogs as $log): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars(substr($log['details'] ?? '', 0, 50)); ?></td>
                        <td><?php echo formatDate($log['created_at']); ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile Offcanvas Menu -->
  <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="offcanvasExampleLabel">SMS Admin</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
      <ul class="navbar-nav flex-column">
        <li class="nav-item">
          <a class="nav-link active" href="admin.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-dashboard">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 4h6v8h-6z" />
                <path d="M4 16h6v4h-6z" />
                <path d="M14 12h6v8h-6z" />
                <path d="M14 4h6v4h-6z" />
              </svg></span>
            <span class="text">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_users.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
              </svg></span>
            <span class="text">Users</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_devices.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                <path d="M11 4h2" />
                <path d="M12 17v.01" />
              </svg></span>
            <span class="text">Devices</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_apis.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-api">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 13h5" />
                <path d="M12 16v-8h3a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-3" />
                <path d="M20 8v8" />
                <path d="M9 16v-5.5a2.5 2.5 0 0 1 5 0v5.5" />
              </svg></span>
            <span class="text">APIs</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_messages.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M8 9h8" />
                <path d="M8 13h6" />
                <path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 4v-4h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
              </svg></span>
            <span class="text">Messages</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_settings.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c.593 .36 1.269 .36 1.862 0z" />
                <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
              </svg></span>
            <span class="text">Settings</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_logs.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M9 9h6" />
                <path d="M9 13h6" />
                <path d="M9 17h6" />
                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
              </svg></span>
            <span class="text">Logs</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-logout">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                <path d="M9 12h12l-3 -3" />
                <path d="M18 15l3 -3" />
              </svg></span>
            <span class="text">Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./assets/js/vendors/sidebarnav.js"></script>
</body>
</html>