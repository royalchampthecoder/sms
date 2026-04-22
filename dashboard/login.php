<?php
session_start();
include "../config.php";

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    header("Location: /sms/dashboard");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $u = trim($_POST['username']);
    $p = md5(trim($_POST['password'])); // keeping MD5 as per your DB

    if (!empty($u) && !empty($p)) {

        $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $u, $p);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {

            $user = $res->fetch_assoc();

            $_SESSION['user'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];

            header("Location: /sms/dashboard");
            exit;

        } else {
            $error = "Invalid username or password";
        }

        $stmt->close();

    } else {
        $error = "Please fill all fields";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="SMS Dashboard" name="author">
    <title>Login - SMS Dashboard</title>
    
    <!-- Favicon icon-->
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon/favicon-32x32.png" />
    
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
      body {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      }
      
      .login-container {
        width: 100%;
        max-width: 420px;
        padding: 1rem;
      }
      
      .login-card {
        background: #ffffff;
        border-radius: 0.5rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        padding: 2rem;
      }
      
      .login-header {
        text-align: center;
        margin-bottom: 2rem;
      }
      
      .login-header .logo-text {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 0.5rem;
      }
      
      .login-header .logo-subtitle {
        font-size: 0.875rem;
        color: #6c757d;
      }
      
      .form-group {
        margin-bottom: 1.5rem;
      }
      
      .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #212529;
        margin-bottom: 0.5rem;
      }
      
      .form-group input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #e0e0e0;
        border-radius: 0.375rem;
        font-size: 0.9375rem;
        transition: all 0.2s ease;
      }
      
      .form-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      }
      
      .btn-login {
        width: 100%;
        padding: 0.75rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.375rem;
        font-size: 0.9375rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }
      
      .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
      }
      
      .btn-login:active {
        transform: translateY(0);
      }
      
      .alert-error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
      }
      
      .login-footer {
        text-align: center;
        margin-top: 1.5rem;
        font-size: 0.875rem;
        color: #6c757d;
      }
    </style>
</head>

<body>
  <div class="login-container">
    <div class="login-card">
      <!-- Header -->
      <div class="login-header">
        <div class="logo-text">SMS Dashboard</div>
        <div class="logo-subtitle">Sign in to your account</div>
      </div>

      <!-- Error Alert -->
      <?php if (!empty($error)): ?>
        <div class="alert-error">
          <strong>Error!</strong> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST">
        <div class="form-group">
          <label for="username">Username</label>
          <input 
            type="text"
            id="username"
            name="username"
            placeholder="Enter your username"
            required
            autofocus
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input 
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          />
        </div>

        <button type="submit" class="btn-login">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3H9a6 6 0 0 0 0 12h.5"></path>
            <path d="M20.303 18.657a2.997 2.997 0 0 0-2.965-2.909 3 3 0 0 0 .133-5.746"></path>
            <path d="M9 15l6-6"></path>
          </svg>
          Sign In
        </button>
      </form>

      <!-- Footer -->
      <div class="login-footer">
        <p>© 2026 SMS Dashboard. All rights reserved.</p>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>