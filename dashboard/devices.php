<?php
include "auth_check.php";
include "../config.php";
include "functions.php";

// Handle device registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register_device') {
        $device_name = sanitize($_POST['device_name']);
        $api_key = generateApiKey();

        if (empty($device_name)) {
            $error = 'Device name is required';
        } else {
            // First register the device
            $device_id = 'DEV_' . time() . '_' . rand(1000, 9999);
            $stmt = $conn->prepare("INSERT INTO devices (device_id, device_name, api_key) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $device_id, $device_name, $api_key);
            if ($stmt->execute()) {
                $device_db_id = $conn->insert_id;
                // Then assign it to the user
                $stmt2 = $conn->prepare("INSERT INTO user_devices (user_id, device_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $_SESSION['user_id'], $device_db_id);
                if ($stmt2->execute()) {
                    $message = 'Device registered successfully';
                } else {
                    $error = 'Device registered but failed to assign to user';
                }
            } else {
                $error = 'Failed to register device';
            }
        }
    } elseif ($_POST['action'] === 'update_device') {
        $device_id = (int)$_POST['device_id'];
        $device_name = sanitize($_POST['device_name']);
        $status = sanitize($_POST['status']);

        if (empty($device_name)) {
            $error = 'Device name is required';
        } else {
            $stmt = $conn->prepare("UPDATE devices SET device_name = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssi", $device_name, $status, $device_id);
            if ($stmt->execute()) {
                $message = 'Device updated successfully';
            } else {
                $error = 'Failed to update device';
            }
        }
    } elseif ($_POST['action'] === 'delete_device') {
        $device_id = (int)$_POST['device_id'];

        // First remove from user_devices table
        $stmt = $conn->prepare("DELETE FROM user_devices WHERE device_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $device_id, $_SESSION['user_id']);
        $stmt->execute();

        // Then delete the device
        $stmt = $conn->prepare("DELETE FROM devices WHERE id = ?");
        $stmt->bind_param("i", $device_id);
        if ($stmt->execute()) {
            $message = 'Device deleted successfully';
        } else {
            $error = 'Failed to delete device';
        }
    }
}

// Get user's devices
$devices = $conn->prepare("
    SELECT d.*,
           COUNT(mq.id) as total_messages,
           COUNT(CASE WHEN mq.status = 'sent' THEN 1 END) as sent_messages,
           COUNT(CASE WHEN mq.status = 'failed' THEN 1 END) as failed_messages,
           MAX(mq.created_at) as last_message
    FROM devices d
    INNER JOIN user_devices ud ON d.id = ud.device_id
    LEFT JOIN message_queue mq ON d.id = mq.device_id
    WHERE ud.user_id = ?
    GROUP BY d.id
    ORDER BY d.created_at DESC
");
$devices->bind_param("i", $_SESSION['user_id']);
$devices->execute();
$devices = $devices->get_result();

// Get device statistics
$stats = $conn->prepare("
    SELECT
        COUNT(*) as total_devices,
        COUNT(CASE WHEN d.status = 'online' THEN 1 END) as online_devices,
        COUNT(CASE WHEN d.is_active = 1 THEN 1 END) as active_devices,
        COUNT(CASE WHEN d.status = 'offline' THEN 1 END) as inactive_devices
    FROM devices d
    INNER JOIN user_devices ud ON d.id = ud.device_id
    WHERE ud.user_id = ?
");
$stats->bind_param("i", $_SESSION['user_id']);
$stats->execute();
$stats = $stats->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Dashboard" name="author">
  <title>Devices - SMS Dashboard</title>

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

  /* Status badges */
  .badge-active { background-color: #28a745; color: #fff; }
  .badge-inactive { background-color: #6c757d; color: #fff; }
  .badge-online { background-color: #28a745; color: #fff; }
  .badge-offline { background-color: #dc3545; color: #fff; }

  /* Device card */
  .device-card {
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .device-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  /* API key display */
  .api-key-container {
    position: relative;
  }
  .api-key-text {
    font-family: monospace;
    font-size: 0.875rem;
    word-break: break-all;
  }
  .copy-btn {
    position: absolute;
    top: 0;
    right: 0;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
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
          <a class="nav-link" href="./">
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
          <a class="nav-link active" href="devices.php">
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
              <a class="sidebar-toggle d-flex texttooltip p-3" href="javascript:void(0)" data-template="collapseDevice">
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
                  <div id="collapseDevice" class="d-none">
                    <span class="small">Collapse</span>
                  </div>
                </span>
              </a>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerDeviceModal">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus me-1">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M12 5l0 14" />
                <path d="M5 12l14 0" />
              </svg>
              Register Device
            </button>
          </div>
        </div>
      </div>

      <!-- Container -->
      <div class="custom-container">
        <!-- Heading -->
        <div class="mb-4">
          <h1 class="fs-4">Devices</h1>
          <p class="text-muted">Manage your SMS sending devices</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($message)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check me-2">
              <path stroke="none" d="M0 0h24v24H0z" fill="none" />
              <path d="M5 12l5 5l10 -10" />
            </svg>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle me-2">
              <path stroke="none" d="M0 0h24v24H0z" fill="none" />
              <path d="M10.24 3.957l-8.422 14.06a1.989 1.989 0 0 0 1.7 2.983h16.845a1.989 1.989 0 0 0 1.7 -2.983l-8.423 -14.06a1.989 1.989 0 0 0 -3.4 0z" />
              <path d="M12 9v4" />
              <path d="M12 17h.01" />
            </svg>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="card card-lg">
              <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                  <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile text-primary">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                      <path d="M11 4h2" />
                      <path d="M12 17v.01" />
                    </svg>
                  </div>
                </div>
                <h3 class="fs-2 mb-1"><?php echo $stats['total_devices']; ?></h3>
                <p class="text-muted mb-0">Total Devices</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-lg">
              <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                  <div class="bg-success bg-opacity-10 rounded-circle p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wifi text-success">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M12 18l.01 0" />
                      <path d="M9.172 15.172a4 4 0 0 1 5.656 0" />
                      <path d="M6.343 12.343a8 8 0 0 1 11.314 0" />
                      <path d="M3.515 9.515c4.686 -4.687 12.284 -4.687 17 0" />
                    </svg>
                  </div>
                </div>
                <h3 class="fs-2 mb-1"><?php echo $stats['online_devices']; ?></h3>
                <p class="text-muted mb-0">Online</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-lg">
              <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                  <div class="bg-info bg-opacity-10 rounded-circle p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-info">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                      <path d="M9 12l2 2l4 -4" />
                    </svg>
                  </div>
                </div>
                <h3 class="fs-2 mb-1"><?php echo $stats['active_devices']; ?></h3>
                <p class="text-muted mb-0">Active</p>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-lg">
              <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-3">
                  <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-x text-warning">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                      <path d="M10 10l4 4m0 -4l-4 4" />
                    </svg>
                  </div>
                </div>
                <h3 class="fs-2 mb-1"><?php echo $stats['inactive_devices']; ?></h3>
                <p class="text-muted mb-0">Inactive</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Devices Grid -->
        <div class="row g-3">
          <?php if ($devices->num_rows > 0): ?>
            <?php while ($device = $devices->fetch_assoc()): ?>
              <div class="col-lg-6 col-xl-4">
                <div class="card device-card h-100">
                  <div class="card-header border-bottom-0">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile text-primary">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                            <path d="M11 4h2" />
                            <path d="M12 17v.01" />
                          </svg>
                        </div>
                        <div>
                          <h6 class="mb-0"><?php echo htmlspecialchars($device['device_name']); ?></h6>
                          <small class="text-muted">ID: <?php echo htmlspecialchars($device['device_id']); ?></small>
                        </div>
                      </div>
                      <div class="d-flex gap-2">
                        <span class="badge badge-<?php echo $device['is_active'] ? 'active' : 'inactive'; ?>">
                          <?php echo $device['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <span class="badge badge-<?php echo (strtotime($device['last_ping']) > strtotime('-5 minutes')) ? 'online' : 'offline'; ?>">
                          <?php echo (strtotime($device['last_ping']) > strtotime('-5 minutes')) ? 'Online' : 'Offline'; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <!-- API Key -->
                    <div class="mb-3">
                      <label class="form-label small fw-semibold">API Key</label>
                      <div class="api-key-container">
                        <code class="api-key-text form-control-plaintext p-2 bg-light rounded"><?php echo htmlspecialchars($device['api_key']); ?></code>
                        <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($device['api_key']); ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-copy">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M8 8m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                            <path d="M12 2m-2 0a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2v0a2 2 0 0 1 2 -2" />
                          </svg>
                        </button>
                      </div>
                    </div>

                    <!-- Statistics -->
                    <div class="row g-3 mb-3">
                      <div class="col-4 text-center">
                        <div class="fs-4 fw-bold text-primary"><?php echo $device['total_messages']; ?></div>
                        <small class="text-muted">Total</small>
                      </div>
                      <div class="col-4 text-center">
                        <div class="fs-4 fw-bold text-success"><?php echo $device['sent_messages']; ?></div>
                        <small class="text-muted">Sent</small>
                      </div>
                      <div class="col-4 text-center">
                        <div class="fs-4 fw-bold text-danger"><?php echo $device['failed_messages']; ?></div>
                        <small class="text-muted">Failed</small>
                      </div>
                    </div>

                    <!-- Last Activity -->
                    <div class="mb-3">
                      <small class="text-muted">
                        Last Message: <?php echo $device['last_message'] ? formatDate($device['last_message']) : 'Never'; ?>
                      </small>
                    </div>
                  </div>
                  <div class="card-footer border-top-0">
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDeviceModal" onclick="editDevice(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['device_name']); ?>', '<?php echo $device['status']; ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-1">
                          <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                          <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                          <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                          <path d="M16 5l3 3" />
                        </svg>
                        Edit
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDeviceModal" onclick="deleteDevice(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['device_name']); ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash me-1">
                          <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                          <path d="M4 7l16 0" />
                          <path d="M10 11l0 6" />
                          <path d="M14 11l0 6" />
                          <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                          <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="card">
                <div class="card-body text-center py-5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile-x mb-3 text-muted">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M13 21h-5a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8" />
                    <path d="M11 4h2" />
                    <path d="M12 17v.01" />
                    <path d="M22 22l-5 -5" />
                    <path d="M17 22l5 -5" />
                  </svg>
                  <h5 class="text-muted">No devices registered</h5>
                  <p class="text-muted mb-4">Register your first device to start sending SMS messages</p>
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerDeviceModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus me-1">
                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                      <path d="M12 5l0 14" />
                      <path d="M5 12l14 0" />
                    </svg>
                    Register Device
                  </button>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Register Device Modal -->
  <div class="modal fade" id="registerDeviceModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Register New Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="register_device">
            <div class="mb-3">
              <label class="form-label">Device Name *</label>
              <input type="text" class="form-control" name="device_name" required placeholder="e.g., My Android Phone">
              <small class="form-text text-muted">Give your device a descriptive name</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Register Device</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Device Modal -->
  <div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="update_device">
            <input type="hidden" name="device_id" id="edit_device_id">
            <div class="mb-3">
              <label class="form-label">Device Name *</label>
              <input type="text" class="form-control" name="device_name" id="edit_device_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="edit_device_status">
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="disconnected">Disconnected</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Device</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Device Modal -->
  <div class="modal fade" id="deleteDeviceModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete the device "<strong id="delete_device_name"></strong>"?</p>
          <p class="text-danger small">This action cannot be undone. All message history associated with this device will be lost.</p>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="delete_device">
          <input type="hidden" name="device_id" id="delete_device_id">
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete Device</button>
          </div>
        </form>
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
          <a class="nav-link active" href="devices.php" data-bs-dismiss="offcanvas">
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./assets/js/vendors/sidebarnav.js"></script>
  <script>
    function editDevice(id, name, status) {
      document.getElementById('edit_device_id').value = id;
      document.getElementById('edit_device_name').value = name;
      document.getElementById('edit_device_status').value = status;
    }

    function deleteDevice(id, name) {
      document.getElementById('delete_device_id').value = id;
      document.getElementById('delete_device_name').textContent = name;
    }

    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        const btn = event.target.closest('.copy-btn');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10"/></svg>';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');

        setTimeout(() => {
          btn.innerHTML = originalHtml;
          btn.classList.remove('btn-success');
          btn.classList.add('btn-outline-secondary');
        }, 2000);
      });
    }
  </script>
</body>
</html><?php 
include "auth_check.php"; 
include "../config.php";
?>

<!DOCTYPE html>
<html>
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Dashboard" name="author">
  <title>Devices - SMS Dashboard</title>
  
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
          <a class="nav-link" href="./">
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
          <a class="nav-link active" href="devices.php">
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
        <!-- Heading -->
        <div class="mb-6">
          <h1 class="fs-4">Devices</h1>
          <p class="text-muted">Manage connected SMS gateway devices</p>
        </div>

        <!-- Devices Table -->
        <div class="row g-6">
          <div class="col-12">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">All Devices</h5>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table text-nowrap mb-0 table-centered table-hover">
                  <thead>
                    <tr>
                      <th>Device ID</th>
                      <th>API Key</th>
                      <th>Status</th>
                      <th>Last Ping</th>
                      <th>Messages Sent</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $devices = $conn->query("SELECT d.*, COUNT(m.id) as msg_count FROM devices d LEFT JOIN messages m ON d.id = m.device_id GROUP BY d.id ORDER BY d.id DESC");
                    if ($devices->num_rows > 0) {
                      while($row = $devices->fetch_assoc()) {
                        $statusClass = $row['status'] == 'active' ? 'success' : 'danger';
                        echo "<tr>
                          <td><strong>{$row['id']}</strong></td>
                          <td><code>{$row['api_key']}</code></td>
                          <td><span class='badge text-{$statusClass}-emphasis bg-{$statusClass}-subtle'>{$row['status']}</span></td>
                          <td>{$row['last_ping']}</td>
                          <td>{$row['msg_count']}</td>
                        </tr>";
                      }
                    } else {
                      echo "<tr><td colspan='5' class='text-center text-muted py-4'>No devices found</td></tr>";
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
  <script src="./assets/js/vendors/sidebarnav.js"></script>
</body>

</html>