<?php
// api/cancel_event.php — AJAX: Cancel a student registration
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}

$user = currentUser();
if (!$user || $user['role'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'Please log in as a student.']); exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$regId  = (int)($input['registration_id'] ?? 0);

if (!$regId) {
    echo json_encode(['success'=>false,'message'=>'Invalid registration.']); exit;
}

try {
    $db = getDB();

    // Get registration — must belong to current user
    $stmt = $db->prepare("
        SELECT r.*, e.event_date, e.title
        FROM registrations r JOIN events e ON r.event_id=e.id
        WHERE r.id=? AND r.user_id=? AND r.status='registered' LIMIT 1
    ");
    $stmt->execute([$regId, $user['id']]);
    $reg = $stmt->fetch();

    if (!$reg) {
        echo json_encode(['success'=>false,'message'=>'Registration not found.']); exit;
    }
    if (strtotime($reg['event_date']) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success'=>false,'message'=>'Cannot cancel a past event.']); exit;
    }

    // Cancel
    $upd = $db->prepare("UPDATE registrations SET status='cancelled', cancelled_at=NOW() WHERE id=?");
    $upd->execute([$regId]);

    echo json_encode([
        'success' => true,
        'message' => "Registration cancelled for '{$reg['title']}'.",
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Cancellation failed. Please try again.']);
}
