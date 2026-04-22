<?php 
include "auth_check.php"; 
include "../config.php";

// ===== STATS =====
$total = $conn->query("SELECT COUNT(*) as c FROM messages")->fetch_assoc()['c'];
$sent = $conn->query("SELECT COUNT(*) as c FROM messages WHERE status='sent'")->fetch_assoc()['c'];
$failed = $conn->query("SELECT COUNT(*) as c FROM messages WHERE status='failed'")->fetch_assoc()['c'];
$pending = $total - $sent - $failed;

// ===== SEND SMS =====
$msg = "";
if ($_POST) {
    $phones = explode(",", $_POST['phone']);
    $message = $conn->real_escape_string($_POST['message']);

    foreach ($phones as $p) {
        $p = trim($p);
        if (!empty($p)) {
            $conn->query("INSERT INTO messages (phone, message) VALUES ('$p', '$message')");
        }
    }

    $msg = "SMS queued successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Dashboard" name="author">
  <title>SMS Dashboard</title>
  
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
  
  <!-- Custom styles for SMS -->
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
  </style>
</head>

<body>
  <!-- Vertical Sidebar -->
  <div>
    <!-- Sidebar -->
    <div id="miniSidebar">
      <div class="brand-logo">
        <a class="d-none d-md-flex align-items-center gap-2" href="./">
          <span class="fw-bold fs-4 site-logo-text">SMS Dashboard</span>
        </a>
      </div>
      <ul class="navbar-nav flex-column">
        <!-- Nav item -->
        <li class="nav-item">
          <a class="nav-link active" href="./">
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
          <a class="nav-link" href="devices.php">
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
          <a class="nav-link" href="messages.php">
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
          <a class="nav-link" href="settings.php">
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
          <a class="nav-link" href="upload.php">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                <path d="M7 9l5 -5l5 5" />
                <path d="M12 4v12" />
              </svg></span>
            <span class="text">Bulk Upload</span>
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
          <div class="navbar-nav ms-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendSmsModal">Send SMS</button>
          </div>
        </div>
      </div>
      
      <!-- Container -->
      <div class="custom-container">
        <!-- Row -->
        <div class="row mb-4 g-6">
          <div class="col-xl-8 col-lg-6">
            <div class="bg-gradient-mixed p-8 py-10 rounded-3 p-lg-7">
              <h1 class="fs-3">👋 Welcome to SMS Dashboard</h1>
              <p class="mb-0">Monitor your SMS gateway, track message status,</p>
              <p>manage devices, and send bulk messages.</p>
              <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#sendSmsModal">Send SMS</button>
            </div>
          </div>
          <div class="col-xl-4 col-lg-6">
            <div class="card card-lg">
              <div class="card-body">
                <div class="mb-4">
                  <h5 class="mb-1">Quick Stats</h5>
                </div>
                <div class="row">
                  <div class="col-12">
                    <div class="d-flex justify-content-between mb-2">
                      <span>Total Messages</span>
                      <span class="fw-bold"><?php echo $total; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                      <span>Sent</span>
                      <span class="text-success"><?php echo $sent; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                      <span>Failed</span>
                      <span class="text-danger"><?php echo $failed; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                      <span>Pending</span>
                      <span class="text-warning"><?php echo $pending; ?></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row row-cols-1 row-cols-xl-3 row-cols-md-3 mb-4 g-6">
          <div class="col">
            <div class="card card-lg">
              <div class="card-body d-flex flex-column gap-8">
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-shape icon-lg rounded-circle bg-warning-darker text-warning-lighter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M8 9h8" />
                      <path d="M8 13h6" />
                      <path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 4v-4h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                    </svg>
                  </div>
                  <div>Total Messages</div>
                </div>
                <div class="d-flex justify-content-between align-items-center lh-1">
                  <div class="fs-3 fw-bold"><?php echo $total; ?></div>
                  <div class="text-success small">
                    <span>Active</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card card-lg">
              <div class="card-body d-flex flex-column gap-8">
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-shape icon-lg rounded-circle bg-success-darker text-success-lighter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M5 12l5 5l10 -10" />
                    </svg>
                  </div>
                  <div>Sent</div>
                </div>
                <div class="d-flex justify-content-between align-items-center lh-1">
                  <div class="fs-3 fw-bold"><?php echo $sent; ?></div>
                  <div class="text-success small">
                    <span><?php echo $total > 0 ? round($sent/$total*100, 1) : 0; ?>%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card card-lg">
              <div class="card-body d-flex flex-column gap-8">
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-shape icon-lg rounded-circle bg-danger-darker text-danger-lighter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M18 6l-12 12" />
                      <path d="M6 6l12 12" />
                    </svg>
                  </div>
                  <div>Failed</div>
                </div>
                <div class="d-flex justify-content-between align-items-center lh-1">
                  <div class="fs-3 fw-bold"><?php echo $failed; ?></div>
                  <div class="text-danger small">
                    <span><?php echo $total > 0 ? round($failed/$total*100, 1) : 0; ?>%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Chart -->
        <div class="row g-6 mb-4">
          <div class="col-xl-8 col-12">
            <div class="card card-lg">
              <div class="card-body d-flex flex-column gap-5">
                <div class="mb-4">
                  <h5 class="mb-0">Message Status Distribution</h5>
                </div>
                <div id="statusChart"></div>
              </div>
            </div>
          </div>
          <div class="col-xl-4 col-12">
            <div class="card card-lg">
              <div class="card-body">
                <h5 class="mb-6">Status Breakdown</h5>
                <div id="statusPie"></div>
                <div class="d-flex flex-column gap-2 mt-4">
                  <div>
                    <div class="d-flex justify-content-between align-items-center">
                      <span>Sent</span>
                      <span><?php echo $sent; ?> (<?php echo $total > 0 ? round($sent/$total*100, 1) : 0; ?>%)</span>
                    </div>
                    <div class="progress mt-1" style="height: 6px">
                      <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $total > 0 ? $sent/$total*100 : 0; ?>%" aria-valuenow="<?php echo $sent; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"></div>
                    </div>
                  </div>
                  <div>
                    <div class="d-flex justify-content-between align-items-center">
                      <span>Failed</span>
                      <span><?php echo $failed; ?> (<?php echo $total > 0 ? round($failed/$total*100, 1) : 0; ?>%)</span>
                    </div>
                    <div class="progress mt-1" style="height: 6px">
                      <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $total > 0 ? $failed/$total*100 : 0; ?>%" aria-valuenow="<?php echo $failed; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"></div>
                    </div>
                  </div>
                  <div>
                    <div class="d-flex justify-content-between align-items-center">
                      <span>Pending</span>
                      <span><?php echo $pending; ?> (<?php echo $total > 0 ? round($pending/$total*100, 1) : 0; ?>%)</span>
                    </div>
                    <div class="progress mt-1" style="height: 6px">
                      <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $total > 0 ? $pending/$total*100 : 0; ?>%" aria-valuenow="<?php echo $pending; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Recent Messages Table -->
        <div class="row g-6 mb-4">
          <div class="col-12">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">Recent Messages</h5>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table text-nowrap mb-0 table-centered table-hover">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Phone</th>
                      <th>Message</th>
                      <th>Status</th>
                      <th>Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $recent = $conn->query("SELECT * FROM messages ORDER BY id DESC LIMIT 10");
                    while($row = $recent->fetch_assoc()) {
                      $statusClass = $row['status'] == 'sent' ? 'success' : ($row['status'] == 'failed' ? 'danger' : 'warning');
                      echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['phone']}</td>
                        <td>" . substr($row['message'], 0, 50) . "...</td>
                        <td><span class='badge text-{$statusClass}-emphasis bg-{$statusClass}-subtle'>{$row['status']}</span></td>
                        <td>{$row['timestamp']}</td>
                      </tr>";
                    }
                    ?>
                  </tbody>
                </table>
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
      <h5 class="offcanvas-title" id="offcanvasExampleLabel">SMS Dashboard</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
      <ul class="navbar-nav flex-column">
        <li class="nav-item">
          <a class="nav-link" href="./" data-bs-dismiss="offcanvas">
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
          <a class="nav-link" href="devices.php" data-bs-dismiss="offcanvas">
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
          <a class="nav-link" href="messages.php" data-bs-dismiss="offcanvas">
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
          <a class="nav-link" href="settings.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c.593 .36 1.269 .36 1.862 0z" />
                <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
              </svg></span>
            <span class="text">Settings</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="upload.php" data-bs-dismiss="offcanvas">
            <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                <path d="M7 9l5 -5l5 5" />
                <path d="M12 4v12" />
              </svg></span>
            <span class="text">Bulk Upload</span>
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

  <!-- Send SMS Modal -->
  <div class="modal fade" id="sendSmsModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Send SMS</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if($msg) { ?>
          <div class="alert alert-success"><?php echo $msg; ?></div>
          <?php } ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Phone Numbers (comma separated)</label>
              <input class="form-control" name="phone" placeholder="1234567890,0987654321" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4" required></textarea>
            </div>
            <button class="btn btn-primary w-100">Send SMS</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
  <script src="./assets/js/vendors/sidebarnav.js"></script>
  <script src="./assets/js/vendors/chart.js"></script>
  
  <script>
    // Status Chart
    var options = {
      series: [{
        name: 'Messages',
        data: [<?php echo $sent; ?>, <?php echo $failed; ?>, <?php echo $pending; ?>]
      }],
      chart: {
        type: 'bar',
        height: 300
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: '55%',
          endingShape: 'rounded'
        },
      },
      dataLabels: {
        enabled: false
      },
      xaxis: {
        categories: ['Sent', 'Failed', 'Pending'],
      },
      colors: ['#00a76f', '#ff3e1d', '#ffab00']
    };
    var chart = new ApexCharts(document.querySelector("#statusChart"), options);
    chart.render();

    // Status Pie
    var pieOptions = {
      series: [<?php echo $sent; ?>, <?php echo $failed; ?>, <?php echo $pending; ?>],
      chart: {
        type: 'pie',
        height: 200
      },
      labels: ['Sent', 'Failed', 'Pending'],
      colors: ['#00a76f', '#ff3e1d', '#ffab00']
    };
    var pieChart = new ApexCharts(document.querySelector("#statusPie"), pieOptions);
    pieChart.render();
  </script>
</body>

</html>