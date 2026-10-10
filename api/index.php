<?php
// ============================================================
// api/index.php — Serverless Entrypoint & Fast Router for Vercel
// ============================================================

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

// Project root
$rootDir = realpath(__DIR__ . '/..');

// Normalize path
$path = '/' . ltrim($path, '/');

// Default landing page
if ($path === '/' || $path === '') {
    $targetRel = '/index.php';
} else {
    $targetRel = $path;
}

// Check target path
$targetPath = realpath($rootDir . $targetRel);

// If not directly found, check if appending .php helps (e.g. /login -> /login.php)
if (!$targetPath || !is_file($targetPath)) {
    $withPhp = realpath($rootDir . $targetRel . '.php');
    if ($withPhp && is_file($withPhp)) {
        $targetPath = $withPhp;
        $targetRel .= '.php';
    }
}

// Security: Prevent directory traversal outside root directory
if ($targetPath && (!str_starts_with($targetPath, $rootDir) || str_contains($targetRel, '..'))) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// Serve static assets if routed through here
if ($targetPath && is_file($targetPath) && preg_match('/\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|eot|pdf)$/i', $targetPath)) {
    $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
    ];
    $contentType = $mimes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');
    readfile($targetPath);
    exit;
}

// Execute PHP target script
if ($targetPath && is_file($targetPath) && str_ends_with($targetPath, '.php')) {
    // Emulate standard environment
    $_SERVER['SCRIPT_FILENAME'] = $targetPath;
    $_SERVER['SCRIPT_NAME']     = $targetRel;
    $_SERVER['PHP_SELF']        = $targetRel;
    $_SERVER['DOCUMENT_ROOT']   = $rootDir;

    // Change working directory to target file directory
    chdir(dirname($targetPath));

    require $targetPath;
    exit;
}

// 404 Fallback
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found | EventMS</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0b0f19; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
    .box { text-align: center; max-width: 480px; padding: 32px; background: #151c2e; border: 1px solid #232f48; border-radius: 12px; }
    h1 { font-size: 48px; margin: 0 0 8px; color: #6366f1; }
    p { color: #94a3b8; line-height: 1.5; }
    a { display: inline-block; margin-top: 16px; color: #fff; background: #6366f1; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; }
  </style>
</head>
<body>
  <div class="box">
    <h1>404</h1>
    <p>The requested page <code><?= htmlspecialchars($path) ?></code> was not found.</p>
    <a href="/">Return to Home</a>
  </div>
</body>
</html>
