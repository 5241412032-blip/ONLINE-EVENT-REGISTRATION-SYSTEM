<?php
// includes/auth_check.php — Role-based access guard
// Usage: require_once '../includes/auth_check.php'; requireRole('admin');

require_once __DIR__ . '/../config/db.php';

function requireRole(string $role): array {
    $user = currentUser();
    if (!$user) {
        $base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
        header("Location: {$base}login.php?error=Please+log+in+to+continue");
        exit;
    }
    if ($user['role'] !== $role) {
        $base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
        header("Location: {$base}login.php?error=Access+denied");
        exit;
    }
    return $user;
}
