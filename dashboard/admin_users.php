<?php
require_once 'functions.php';
requireAdmin();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        switch ($action) {
            case 'create_user':
                $username = sanitize($_POST['username']);
                $password = $_POST['password'];
                $email = sanitize($_POST['email']);

                if (empty($username) || empty($password)) {
                    $error = 'Username and password are required';
                } else {
                    $result = createUser($username, $password, $email);
                    if ($result) {
                        $message = 'User created successfully';
                    } else {
                        $error = 'Failed to create user';
                    }
                }
                break;

            case 'update_user':
                $userId = (int)$_POST['user_id'];
                $data = [];

                if (!empty($_POST['username'])) $data['username'] = sanitize($_POST['username']);
                if (!empty($_POST['email'])) $data['email'] = sanitize($_POST['email']);
                if (!empty($_POST['password'])) $data['password'] = $_POST['password'];
                if (isset($_POST['status'])) $data['status'] = sanitize($_POST['status']);

                if (updateUser($userId, $data)) {
                    $message = 'User updated successfully';
                } else {
                    $error = 'Failed to update user';
                }
                break;

            case 'delete_user':
                $userId = (int)$_POST['user_id'];
                if ($userId !== $_SESSION['user_id']) { // Prevent self-deletion
                    if (deleteUser($userId)) {
                        $message = 'User deleted successfully';
                    } else {
                        $error = 'Failed to delete user';
                    }
                } else {
                    $error = 'Cannot delete your own account';
                }
                break;

            case 'assign_devices':
                $userId = (int)$_POST['user_id'];
                $deviceIds = isset($_POST['device_ids']) ? array_map('intval', $_POST['device_ids']) : [];

                if (assignDevicesToUser($userId, $deviceIds)) {
                    $message = 'Devices assigned successfully';
                } else {
                    $error = 'Failed to assign devices';
                }
                break;
        }
    }
}

$users = getAllUsers();
$devices = getAllDevices();
?>

<!DOCTYPE html>
<html>
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta content="SMS Gateway Admin - Users" name="author">
  <title>User Management - SMS Gateway</title>

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

  .modal-lg { max-width: 800px; }
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
          <a class="nav-link" href="admin.php">
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
          <a class="nav-link active" href="admin_users.php">
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
                <path d="M9 9h6" />
                <path d="M9 13h6" />
                <path d="M9 17h6" />
                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
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
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus me-2">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M12 5l0 14" />
                <path d="M5 12l14 0" />
              </svg>
              Add User
            </button>
          </div>
        </div>
      </div>

      <!-- Container -->
      <div class="custom-container">
        <!-- Heading -->
        <div class="mb-6">
          <h1 class="fs-4">User Management</h1>
          <p class="text-muted">Manage users and their device assignments</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php echo htmlspecialchars($error); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card card-lg">
          <div class="card-header border-bottom-0">
            <div>
              <h5 class="mb-0">Users (<?php echo count($users); ?>)</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Devices</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                  <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                      </span>
                    </td>
                    <td><?php echo formatDate($user['created_at']); ?></td>
                    <td>
                      <?php
                      $userDevices = getUserDevices($user['id']);
                      echo count($userDevices) . ' device' . (count($userDevices) !== 1 ? 's' : '');
                      ?>
                    </td>
                    <td>
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>', '<?php echo $user['status']; ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                          </svg>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#assignDevicesModal" onclick="assignDevices(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                            <path d="M11 4h2" />
                            <path d="M12 17v.01" />
                          </svg>
                        </button>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7l16 0" />
                            <path d="M10 11l0 6" />
                            <path d="M14 11l0 6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                          </svg>
                        </button>
                        <?php endif; ?>
                      </div>
                    </td>
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

  <!-- Create User Modal -->
  <div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_user">
            <div class="mb-3">
              <label class="form-label">Username *</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="username" id="edit_username">
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="edit_email">
            </div>
            <div class="mb-3">
              <label class="form-label">New Password (leave empty to keep current)</label>
              <input type="password" class="form-control" name="password">
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="edit_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Assign Devices Modal -->
  <div class="modal fade" id="assignDevicesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Devices to <span id="assign_username"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="assign_devices">
            <input type="hidden" name="user_id" id="assign_user_id">
            <div class="row">
              <?php foreach ($devices as $device): ?>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input device-checkbox" type="checkbox" name="device_ids[]" value="<?php echo $device['id']; ?>" id="device_<?php echo $device['id']; ?>">
                  <label class="form-check-label" for="device_<?php echo $device['id']; ?>">
                    <strong><?php echo htmlspecialchars($device['device_name']); ?></strong><br>
                    <small class="text-muted">ID: <?php echo htmlspecialchars($device['device_id']); ?> | Status: <?php echo ucfirst($device['status']); ?></small>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Assign Devices</button>
          </div>
        </form>
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
          <a class="nav-link" href="admin.php" data-bs-dismiss="offcanvas">
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
          <a class="nav-link active" href="admin_users.php" data-bs-dismiss="offcanvas">
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

  <script>
  function editUser(id, username, email, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_status').value = status;
  }

  function assignDevices(userId, username) {
    document.getElementById('assign_user_id').value = userId;
    document.getElementById('assign_username').textContent = username;

    // Reset checkboxes
    document.querySelectorAll('.device-checkbox').forEach(cb => cb.checked = false);

    // Load current assignments
    fetch(`admin_users.php?get_user_devices=${userId}`)
      .then(response => response.json())
      .then(data => {
        data.forEach(deviceId => {
          const checkbox = document.getElementById(`device_${deviceId}`);
          if (checkbox) checkbox.checked = true;
        });
      });
  }

  function deleteUser(id, username) {
    if (confirm(`Are you sure you want to delete user "${username}"?`)) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" value="${id}">
      `;
      document.body.appendChild(form);
      form.submit();
    }
  }

  // Handle AJAX request for user devices
  <?php if (isset($_GET['get_user_devices'])): ?>
  <?php
  $userDevices = getUserDevices((int)$_GET['get_user_devices']);
  $deviceIds = array_column($userDevices, 'id');
  header('Content-Type: application/json');
  echo json_encode($deviceIds);
  exit;
  ?>
  <?php endif; ?>
  </script>
</body>
</html>