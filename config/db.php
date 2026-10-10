<?php
// ============================================================
// config/db.php — PDO Database Connection & Configuration
// ============================================================

// Suppress deprecation notices so headers/JSON responses remain clean
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
if (getenv('VERCEL') || !empty($_SERVER['VERCEL'])) {
    ini_set('display_errors', '0');
}

// Support database configuration via Environment Variables (Vercel, Railway, PlanetScale, TiDB, etc.)
$dbUrl   = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$envHost = getenv('DB_HOST');
$envPort = getenv('DB_PORT') ?: '3306';
$envName = getenv('DB_NAME');
$envUser = getenv('DB_USER');
$envPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : getenv('DB_PASSWORD');

if ($dbUrl) {
    $parsed = parse_url($dbUrl);
    if (!empty($parsed['host'])) $envHost = $parsed['host'];
    if (!empty($parsed['port'])) $envPort = (string)$parsed['port'];
    if (!empty($parsed['path'])) $envName = ltrim($parsed['path'], '/');
    if (!empty($parsed['user'])) $envUser = urldecode($parsed['user']);
    if (isset($parsed['pass']))  $envPass = urldecode($parsed['pass']);
}

define('DB_HOST', $envHost ?: 'localhost');
define('DB_PORT', $envPort ?: '3306');
define('DB_NAME', $envName ?: 'event_management_db');
define('DB_USER', $envUser ?: 'root');
define('DB_PASS', $envPass !== false && $envPass !== null ? $envPass : '$100*@East');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('APP_NAME',    'EventMS');
define('APP_VERSION', '1.0.0');

// Session lifetime (seconds) — 2 hours
define('SESSION_LIFETIME', 7200);

// Determine base path dynamically
if (getenv('VERCEL') || !empty($_SERVER['VERCEL'])) {
    define('BASE_PATH', '');
} else {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    define('BASE_PATH', rtrim(str_replace($docRoot, '', $scriptDir), '/'));
}

// Determine APP_URL dynamically
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$proto = $isHttps ? 'https://' : 'http://';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_URL', getenv('APP_URL') ?: ($proto . $httpHost . BASE_PATH));

// Encrypted Cookie Session Handler for serverless environments (Vercel)
class EncryptedCookieSessionHandler implements SessionHandlerInterface {
    private string $key;
    private string $cookieName = 'EVENTMS_SESS';

    public function __construct(?string $secret = null) {
        $secret = $secret ?: (getenv('SESSION_SECRET') ?: 'eventms_secret_2026_vercel_session_salt');
        $this->key = hash('sha256', $secret, true);
    }

    #[\ReturnTypeWillChange]
    public function open(string $path, string $name): bool {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close(): bool {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read(string $id): string|false {
        if (!isset($_COOKIE[$this->cookieName])) {
            return '';
        }
        $raw = base64_decode($_COOKIE[$this->cookieName]);
        if (!$raw || strlen($raw) < 48) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $hmac = substr($raw, 16, 32);
        $ciphertext = substr($raw, 48);
        $expectedHmac = hash_hmac('sha256', $iv . $ciphertext, $this->key, true);
        if (!hash_equals($expectedHmac, $hmac)) {
            return '';
        }
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    #[\ReturnTypeWillChange]
    public function write(string $id, string $data): bool {
        if (headers_sent()) {
            return true;
        }
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $this->key, true);
        $payload = base64_encode($iv . $hmac . $ciphertext);
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie($this->cookieName, $payload, [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function destroy(string $id): bool {
        if (!headers_sent()) {
            setcookie($this->cookieName, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true
            ]);
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|false {
        return 0;
    }
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || !empty($_SERVER['VERCEL']) || getenv('COOKIE_SESSIONS') === 'true') {
        $handler = new EncryptedCookieSessionHandler();
        session_set_save_handler($handler, true);
    } else {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        // Cloud MySQL SSL handling (TiDB, Aiven, PlanetScale, etc.)
        $isCloud = (getenv('DB_SSL') === 'true') || 
                   (getenv('VERCEL') && DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1') ||
                   (DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1');

        if ($isCloud) {
            $sslCaKey     = defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
            $sslVerifyKey = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;

            $localCa = __DIR__ . '/cacert.pem';
            if (file_exists($localCa)) {
                $options[$sslCaKey] = $localCa;
            } elseif (file_exists('/etc/pki/tls/certs/ca-bundle.crt')) {
                $options[$sslCaKey] = '/etc/pki/tls/certs/ca-bundle.crt';
            } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
                $options[$sslCaKey] = '/etc/ssl/certs/ca-certificates.crt';
            }
            $options[$sslVerifyKey] = false;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $isHtml = !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/html');
            if ($isHtml) {
                renderDbErrorPage($e->getMessage());
                exit;
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Please check your MySQL settings or Vercel Environment Variables.',
                    'error'   => $e->getMessage()
                ]);
                exit;
            }
        }
    }
    return $pdo;
}

function renderDbErrorPage(string $errorMsg): void {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Database Setup Required | EventMS</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
      <style>
        :root {
          --bg: #0b0f19;
          --card: #151c2e;
          --border: #232f48;
          --text: #f8fafc;
          --muted: #94a3b8;
          --primary: #6366f1;
          --primary-hover: #4f46e5;
          --danger: #ef4444;
          --danger-bg: rgba(239, 68, 68, 0.1);
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
        .container {
          max-width: 640px;
          width: 100%;
          background: var(--card);
          border: 1px solid var(--border);
          border-radius: 16px;
          padding: 36px;
          box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .icon {
          font-size: 44px;
          margin-bottom: 16px;
        }
        h1 {
          font-family: 'Outfit', sans-serif;
          font-size: 26px;
          font-weight: 700;
          margin-bottom: 10px;
        }
        p {
          color: var(--muted);
          font-size: 15px;
          line-height: 1.6;
          margin-bottom: 20px;
        }
        .error-box {
          background: var(--danger-bg);
          border: 1px solid rgba(239, 68, 68, 0.25);
          color: #fca5a5;
          padding: 14px 18px;
          border-radius: 10px;
          font-size: 13px;
          font-family: monospace;
          word-break: break-all;
          margin-bottom: 24px;
        }
        .steps {
          background: rgba(255, 255, 255, 0.03);
          border: 1px solid var(--border);
          border-radius: 12px;
          padding: 20px;
          margin-bottom: 24px;
        }
        .steps h3 {
          font-size: 14px;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: #a5b4fc;
          margin-bottom: 12px;
        }
        .steps ol {
          padding-left: 20px;
          color: var(--muted);
          font-size: 14px;
          line-height: 1.7;
        }
        .steps code {
          background: rgba(255, 255, 255, 0.08);
          padding: 2px 6px;
          border-radius: 4px;
          color: #e2e8f0;
          font-family: monospace;
          font-size: 13px;
        }
        .btn-group {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;
        }
        .btn {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 11px 22px;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 600;
          text-decoration: none;
          cursor: pointer;
          transition: 0.2s ease;
          border: none;
        }
        .btn-primary {
          background: var(--primary);
          color: #fff;
        }
        .btn-primary:hover {
          background: var(--primary-hover);
        }
        .btn-outline {
          background: transparent;
          border: 1px solid var(--border);
          color: var(--text);
        }
        .btn-outline:hover {
          background: rgba(255,255,255,0.05);
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="icon">🔌</div>
        <h1>Database Connection Required</h1>
        <p>EventMS requires an external MySQL database to function on Vercel Serverless.</p>
        
        <div class="error-box">
          <strong>Error:</strong> <?= htmlspecialchars($errorMsg) ?>
        </div>

        <div class="steps">
          <h3>How to configure on Vercel:</h3>
          <ol>
            <li>Create a free MySQL database on <strong>Aiven</strong>, <strong>TiDB Cloud</strong>, or <strong>Railway</strong>.</li>
            <li>In your Vercel Dashboard, go to <strong>Project Settings → Environment Variables</strong>.</li>
            <li>Add <code>DATABASE_URL</code> (or <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>, <code>DB_PORT</code>).</li>
            <li>Visit <code>/init_db.php</code> to initialize your tables and seed data in 1 click!</li>
          </ol>
        </div>

        <div class="btn-group">
          <a href="/init_db.php" class="btn btn-primary">Run Database Setup (/init_db.php)</a>
          <a href="javascript:location.reload()" class="btn btn-outline">Retry Connection</a>
        </div>
      </div>
    </body>
    </html>
    <?php
}

// Helper: Get logged-in user
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// Helper: Require login; redirect if not
function requireLogin(string $role = ''): array {
    $user = currentUser();
    if (!$user) {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
    if ($role && $user['role'] !== $role) {
        header('Location: ' . BASE_PATH . '/login.php?error=unauthorized');
        exit;
    }
    return $user;
}

// Helper: Safe HTML output
function e(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Helper: Flash messages via session
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
}
function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
