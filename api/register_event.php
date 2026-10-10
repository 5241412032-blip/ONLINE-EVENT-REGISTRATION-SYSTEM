<?php
// api/register_event.php — AJAX: Register student for an event
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}

$user = currentUser();
if (!$user || $user['role'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'Please log in as a student.']); exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$eventId = (int)($input['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success'=>false,'message'=>'Invalid event.']); exit;
}

try {
    $db = getDB();

    // Get event
    $ev = $db->prepare("SELECT * FROM events WHERE id=? LIMIT 1");
    $ev->execute([$eventId]);
    $event = $ev->fetch();

    if (!$event) {
        echo json_encode(['success'=>false,'message'=>'Event not found.']); exit;
    }
    if ($event['status'] !== 'upcoming') {
        echo json_encode(['success'=>false,'message'=>'Registration is closed for this event.']); exit;
    }
    if (strtotime($event['event_date']) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success'=>false,'message'=>'This event has already passed.']); exit;
    }

    // Check existing registration
    $chk = $db->prepare("SELECT id,status FROM registrations WHERE user_id=? AND event_id=? LIMIT 1");
    $chk->execute([$user['id'],$eventId]);
    $existing = $chk->fetch();

    if ($existing) {
        if ($existing['status'] === 'registered') {
            echo json_encode(['success'=>false,'message'=>'You are already registered for this event.']); exit;
        }
        // Re-register (was cancelled)
        $upd = $db->prepare("UPDATE registrations SET status='registered', cancelled_at=NULL, registered_at=NOW() WHERE id=?");
        $upd->execute([$existing['id']]);
    } else {
        // Check capacity
        $cnt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status='registered'");
        $cnt->execute([$eventId]);
        $regCount = (int)$cnt->fetchColumn();

        if ($regCount >= $event['capacity']) {
            echo json_encode(['success'=>false,'message'=>'Sorry, this event is fully booked.']); exit;
        }
        // Insert registration
        $ins = $db->prepare("INSERT INTO registrations (user_id,event_id,status) VALUES (?,?,'registered')");
        $ins->execute([$user['id'],$eventId]);
    }

    // Get updated seat count
    $cnt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status='registered'");
    $cnt->execute([$eventId]);
    $newCount  = (int)$cnt->fetchColumn();
    $seatsLeft = max(0,$event['capacity']-$newCount);

    echo json_encode([
        'success'    => true,
        'message'    => "🎉 You're registered for '{$event['title']}'!",
        'seats_left' => $seatsLeft . ' / ' . $event['capacity'],
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Registration failed. Please try again.']);
}
