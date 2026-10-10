<?php
// ============================================================
// init_db.php — Database Schema Initializer & Seeder
// ============================================================
require_once __DIR__ . '/config/db.php';

$outputMessages = [];
$errorMessages  = [];
$success        = false;

try {
    $db = getDB();
    $outputMessages[] = " Connected to MySQL database (" . DB_NAME . "@" . DB_HOST . ")";

    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found at: {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $tablesCreated = 0;
    $seedsRun = 0;

    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;

        // Skip CREATE DATABASE and USE statements to preserve existing cloud DB names
        if (preg_match('/^\s*CREATE\s+DATABASE/i', $stmt) || preg_match('/^\s*USE\s+/i', $stmt)) {
            continue;
        }

        try {
            $db->exec($stmt);
            if (preg_match('/^\s*CREATE\s+TABLE/i', $stmt)) {
                $tablesCreated++;
            } elseif (preg_match('/^\s*INSERT\s+/i', $stmt)) {
                $seedsRun++;
            }
        } catch (PDOException $ex) {
            // Ignore benign duplicate key or table exists errors
            if ($ex->getCode() == '42S01' || $ex->getCode() == '23000') {
                continue;
            }
            $errorMessages[] = "Statement warning: " . $ex->getMessage();
        }
    }

    // Verify tables
    $checkUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $checkEvents = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $checkRegs = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

    $outputMessages[] = " Verified tables: `users` ({$checkUsers} rows), `events` ({$checkEvents} rows), `registrations` ({$checkRegs} rows).";
    $success = true;

} catch (Throwable $e) {
    $errorMessages[] = $e->getMessage();
}

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "========================================\n";
    echo " EventMS Database Initializer\n";
    echo "========================================\n";
    foreach ($outputMessages as $m) echo "[OK] " . $m . "\n";
    foreach ($errorMessages as $m) echo "[ERR] " . $m . "\n";
    if ($success) {
        echo "\nDatabase initialized successfully!\n";
        echo "Admin: admin@eventms.com / Admin@123\n";
        echo "Student: aarav@student.com / Student@123\n";
    }
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Initialization | EventMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0b0f19;
      --card: #151c2e;
      --border: #232f48;
      --text: #f8fafc;
      --muted: #94a3b8;
      --primary: #6366f1;
      --primary-hover: #4f46e5;
      --success: #10b981;
      --danger: #ef4444;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      max-width: 640px;
      width: 100%;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 36px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    .icon { font-size: 40px; margin-bottom: 12px; }
    h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 26px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 24px; }
    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .badge-danger  { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    .log-box {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px;
      font-family: monospace;
      font-size: 13px;
      margin-bottom: 24px;
      line-height: 1.6;
    }
    .log-ok  { color: #34d399; }
    .log-err { color: #f87171; }
    .creds {
      background: rgba(99, 102, 241, 0.08);
      border: 1px solid rgba(99, 102, 241, 0.25);
      border-radius: 10px;
      padding: 16px;
      margin-bottom: 24px;
    }
    .creds h3 { font-size: 14px; color: #a5b4fc; margin-bottom: 8px; font-weight: 600; }
    .creds ul { list-style: none; font-size: 13px; line-height: 1.8; color: #e2e8f0; }
    .creds code { background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; }
    .actions { display: flex; gap: 12px; }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 20px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: 0.2s;
      border: none;
    }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-outline:hover { background: rgba(255,255,255,0.05); }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon"><?= $success ? '🎉' : '⚠️' ?></div>
    <h1>Database Initialization</h1>
    <p class="subtitle">Online Event Management System</p>

    <?php if ($success): ?>
      <span class="status-badge badge-success">✓ Ready for Production</span>
    <?php else: ?>
      <span class="status-badge badge-danger">✗ Setup Required</span>
    <?php endif; ?>

    <div class="log-box">
      <?php foreach ($outputMessages as $msg): ?>
        <div class="log-ok">✓ <?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errorMessages as $msg): ?>
        <div class="log-err">✗ <?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($success): ?>
      <div class="creds">
        <h3>Default Credentials for Testing:</h3>
        <ul>
          <li><strong>Admin:</strong> <code>admin@eventms.com</code> &nbsp;/&nbsp; Password: <code>Admin@123</code></li>
          <li><strong>Student:</strong> <code>aarav@student.com</code> &nbsp;/&nbsp; Password: <code>Student@123</code></li>
        </ul>
      </div>

      <div class="actions">
        <a href="login.php" class="btn btn-primary">Go to Login Page →</a>
        <a href="index.php" class="btn btn-outline">Visit Homepage</a>
      </div>
    <?php else: ?>
      <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-primary">Retry Initialization</a>
        <a href="index.php" class="btn btn-outline">Back to Home</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
