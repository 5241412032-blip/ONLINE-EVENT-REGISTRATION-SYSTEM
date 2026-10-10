<?php
// admin/event_delete.php — Delete Event Handler
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session FIRST before anything else
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Absolute redirect base
$adminBase = '/admin';

// Auth check
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'admin') {
    header('Location: /login.php?error=Please+log+in');
    exit;
}

// Get event ID from POST
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No event ID supplied.'];
    header('Location: ' . $adminBase . '/events.php');
    exit;
}

$db = getDB();

try {
    // Verify event exists
    $stmt = $db->prepare("SELECT id, title FROM events WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $event = $stmt->fetch();

    if (!$event) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Event not found (may have already been deleted).'];
        header('Location: ' . $adminBase . '/events.php');
        exit;
    }

    // Transaction: delete registrations → nullify reports → delete event
    $db->beginTransaction();
    $db->prepare("DELETE FROM registrations WHERE event_id = ?")->execute([$id]);
    $db->prepare("UPDATE reports SET event_id = NULL WHERE event_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
    $db->commit();

    $_SESSION['flash'] = ['type' => 'success', 'message' => '✅ Event "' . htmlspecialchars($event['title']) . '" was deleted successfully.'];
    header('Location: ' . $adminBase . '/events.php');
    exit;

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    // Show error visibly so we can debug
    http_response_code(500);
    echo '<h2 style="color:red;font-family:monospace;">Deletion Error</h2>';
    echo '<pre style="background:#fee;padding:1em;border:1px solid red;">' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p><a href="/admin/events.php">← Back to Events</a></p>';
    exit;
}
