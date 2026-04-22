<?php
include "auth_check.php";
include "../config.php";

$success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device = $_POST['device_id'];
    $delay = (int)$_POST['sms_delay'];
    $sim = (int)$_POST['sim_slot'];
    $retry = (int)$_POST['retry_limit'];

    $stmt = $conn->prepare("
        REPLACE INTO config (device_id, sms_delay, sim_slot, retry_limit)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("siii", $device, $delay, $sim, $retry);
    $stmt->execute();
    $success = "Settings saved successfully";
}
?>
<!DOCTYPE html>
<html>
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Dashboard" name="author">
  <title>Settings - SMS Dashboard</title>
  
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
          <a class="nav-link active" href="settings.php">
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
        </div>
      </div>
      
      <!-- Container -->
      <div class="custom-container">
        <!-- Heading -->
        <div class="mb-6">
          <h1 class="fs-4">Settings</h1>
          <p class="text-muted">Configure device settings and preferences</p>
        </div>

        <!-- Settings Form -->
        <div class="row">
          <div class="col-lg-6">
            <div class="card card-lg">
              <div class="card-header border-bottom-0">
                <div>
                  <h5 class="mb-0">Device Configuration</h5>
                </div>
              </div>
              <div class="card-body">
                <?php if(!empty($success)) { ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php } ?>
                <form method="post">
                  <div class="mb-3">
                    <label class="form-label">Device ID</label>
                    <select name="device_id" class="form-control" required>
                      <option value="">Select Device</option>
                      <?php
                      $devices = $conn->query("SELECT id FROM devices");
                      while ($d = $devices->fetch_assoc()) {
                        echo "<option value='{$d['id']}'>{$d['id']}</option>";
                      }
                      ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">SMS Delay (seconds)</label>
                    <input class="form-control" name="sms_delay" type="number" placeholder="5" value="5" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">SIM Slot</label>
                    <select name="sim_slot" class="form-control">
                      <option value="0">SIM 1</option>
                      <option value="1">SIM 2</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Retry Limit</label>
                    <input class="form-control" name="retry_limit" type="number" placeholder="2" value="2" required>
                  </div>

                  <button class="btn btn-primary w-100">Save Settings</button>
                </form>
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./assets/js/vendors/sidebarnav.js"></script>
</body>
</html>