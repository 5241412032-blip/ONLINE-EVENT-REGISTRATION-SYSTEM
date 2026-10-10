<?php
// login.php — Login Page
require_once 'config/db.php';

$user = currentUser();
if ($user) {
    header('Location: ' . ($user['role']==='admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$error  = '';
$prefill_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $prefill_email = $email;

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row  = $stmt->fetch();

            if ($row && password_verify($password, $row['password'])) {
                $_SESSION['user'] = [
                    'id'         => $row['id'],
                    'name'       => $row['name'],
                    'email'      => $row['email'],
                    'role'       => $row['role'],
                    'department' => $row['department'],
                ];
                session_regenerate_id(true);
                header('Location: ' . ($row['role']==='admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

$urlError = $_GET['error'] ?? '';
$pageTitle = 'Login';
$root = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login | EventMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
  <!-- Floating orbs -->
  <div style="position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0;">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
  </div>

  <div class="auth-page" style="position:relative;z-index:1;">
    <div style="width:100%;max-width:480px;">

      <!-- Back to home -->
      <div class="text-center mb-3">
        <a href="index.php" class="btn btn-ghost btn-sm">← Back to Home</a>
      </div>

      <div class="auth-card">
        <div class="auth-banner"></div>
        <div class="auth-body">
          <div style="text-align:center;margin-bottom:1.75rem;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">🎯</div>
            <h1 class="auth-title text-center">Welcome back</h1>
            <p class="auth-subtitle text-center" style="margin-bottom:0;">Sign in to your EventMS account</p>
          </div>

          <?php if ($error): ?>
          <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($urlError): ?>
          <div class="alert alert-warning">⚠️ <?= htmlspecialchars($urlError) ?></div>
          <?php endif; ?>

          <form method="POST" action="login.php" id="loginForm" novalidate>
            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <div class="input-wrapper">
                <span class="input-icon">📧</span>
                <input type="email" id="email" name="email" class="form-control"
                  placeholder="you@university.edu" required
                  value="<?= htmlspecialchars($prefill_email) ?>" autocomplete="email">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Password</label>
              <div class="input-wrapper">
                <span class="input-icon">🔒</span>
                <input type="password" id="password" name="password" class="form-control"
                  placeholder="Your password" required autocomplete="current-password">
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:0.5rem;padding:0.85rem;">
              Sign In →
            </button>
          </form>

          <div class="divider-text">or</div>

          <div class="auth-footer">
            Don't have an account?
            <a href="register.php" style="color:var(--clr-purple);font-weight:600;"> Register now</a>
          </div>

          <!-- Demo credentials hint -->
          <div class="card" style="margin-top:1.5rem;padding:1rem;background:rgba(108,99,255,0.08);border-color:rgba(108,99,255,0.2);">
            <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;">🔑 <strong style="color:var(--text-secondary);">Demo Credentials</strong></p>
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0.2rem 0;">Admin: <code style="color:var(--clr-cyan);">admin@eventms.com</code> / <code style="color:var(--clr-cyan);">Admin@123</code></p>
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0.2rem 0;">Student: <code style="color:var(--clr-green);">aarav@student.com</code> / <code style="color:var(--clr-green);">Admin@123</code></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</div>
</body>
</html>
