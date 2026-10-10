<?php
// register.php — Registration Page (Student & Admin)
require_once 'config/db.php';

$user = currentUser();
if ($user) {
    header('Location: ' . ($user['role']==='admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$error    = '';
$success  = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name'       => trim($_POST['name'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'role'       => in_array($_POST['role']??'', ['admin','student']) ? $_POST['role'] : 'student',
        'department' => trim($_POST['department'] ?? ''),
        'year'       => (int)($_POST['year'] ?? 0),
        'phone'      => trim($_POST['phone'] ?? ''),
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validation
    if (!$formData['name'] || !$formData['email'] || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db   = getDB();
            $chk  = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $chk->execute([$formData['email']]);
            if ($chk->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
                $ins  = $db->prepare("
                    INSERT INTO users (name,email,password,role,department,year,phone)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $ins->execute([
                    $formData['name'], $formData['email'], $hash,
                    $formData['role'], $formData['department'],
                    $formData['year'] ?: null, $formData['phone']
                ]);
                $success = 'Account created successfully! You can now log in.';
                $formData = [];
            }
        } catch (Exception $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}

$prefillRole = $_GET['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Register | EventMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
  <div style="position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0;">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-orb hero-orb-3"></div>
  </div>

  <div class="auth-page" style="position:relative;z-index:1;">
    <div style="width:100%;max-width:520px;">
      <div class="text-center mb-3">
        <a href="index.php" class="btn btn-ghost btn-sm">← Back to Home</a>
      </div>

      <div class="auth-card">
        <div class="auth-banner" style="background:var(--gradient-pink);"></div>
        <div class="auth-body">
          <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">🚀</div>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle" style="margin-bottom:0;">Join the EventMS community</p>
          </div>

          <?php if ($error): ?>
          <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
          <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?> <a href="login.php" style="color:var(--clr-green);font-weight:700;">Login →</a></div>
          <?php endif; ?>

          <!-- Role Tabs -->
          <div class="role-tabs" role="tablist">
            <button class="role-tab <?= ($formData['role']??$prefillRole)==='student'?'active':'' ?>"
              data-role="student" type="button">🎓 Student</button>
            <button class="role-tab <?= ($formData['role']??$prefillRole)==='admin'?'active':'' ?>"
              data-role="admin" type="button">🛡 Admin</button>
          </div>

          <form method="POST" action="register.php" id="registerForm" novalidate>
            <input type="hidden" name="role" id="roleInput"
              value="<?= htmlspecialchars($formData['role']??$prefillRole) ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <div class="input-wrapper">
                  <span class="input-icon">👤</span>
                  <input type="text" id="name" name="name" class="form-control"
                    placeholder="John Doe" required
                    value="<?= htmlspecialchars($formData['name']??'') ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="phone">Phone</label>
                <div class="input-wrapper">
                  <span class="input-icon">📱</span>
                  <input type="tel" id="phone" name="phone" class="form-control"
                    placeholder="9876543210"
                    value="<?= htmlspecialchars($formData['phone']??'') ?>">
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="reg_email">Email Address *</label>
              <div class="input-wrapper">
                <span class="input-icon">📧</span>
                <input type="email" id="reg_email" name="email" class="form-control"
                  placeholder="you@university.edu" required
                  value="<?= htmlspecialchars($formData['email']??'') ?>">
              </div>
            </div>

            <!-- Student-only fields -->
            <div class="role-fields" data-for-role="student" <?= ($formData['role']??$prefillRole)==='admin'?'style="display:none"':'' ?>>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="department">Department</label>
                  <div class="input-wrapper">
                    <span class="input-icon">🏫</span>
                    <input type="text" id="department" name="department" class="form-control"
                      placeholder="Computer Science"
                      value="<?= htmlspecialchars($formData['department']??'') ?>">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label" for="year">Year</label>
                  <select id="year" name="year" class="form-control">
                    <option value="">Select year</option>
                    <?php foreach ([1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($formData['year']??0)==$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Admin-only fields -->
            <div class="role-fields" data-for-role="admin" <?= ($formData['role']??$prefillRole)==='student'?'style="display:none"':'' ?>>
              <div class="form-group">
                <label class="form-label" for="admin_dept">Department / Team</label>
                <div class="input-wrapper">
                  <span class="input-icon">🏢</span>
                  <input type="text" id="admin_dept" name="department" class="form-control"
                    placeholder="Event Management Office"
                    value="<?= htmlspecialchars($formData['department']??'') ?>">
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="reg_password">Password *</label>
                <div class="input-wrapper">
                  <span class="input-icon">🔒</span>
                  <input type="password" id="reg_password" name="password" class="form-control"
                    placeholder="Min 6 characters" required>
                </div>
                <div class="form-hint">Strength: <span id="strengthMeter"></span></div>
              </div>
              <div class="form-group">
                <label class="form-label" for="password2">Confirm Password *</label>
                <div class="input-wrapper">
                  <span class="input-icon">🔒</span>
                  <input type="password" id="password2" name="password2" class="form-control"
                    placeholder="Repeat password" required>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="padding:0.85rem;margin-top:0.25rem;">
              Create Account →
            </button>
          </form>

          <div class="auth-footer">
            Already have an account?
            <a href="login.php" style="color:var(--clr-purple);font-weight:600;"> Sign in</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      initPasswordStrength('reg_password', 'strengthMeter');
    });
  </script>
</div>
</body>
</html>
