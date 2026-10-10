<?php
// student/download_pdf.php — Generate & Download PDF Ticket
require_once '../includes/auth_check.php';
$user = requireRole('student');
$db   = getDB();

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) { header('Location: my_registrations.php'); exit; }

// Fetch registration & event
$stmt = $db->prepare("
    SELECT r.*, e.title, e.description, e.event_date, e.event_time, e.venue,
           e.category, e.capacity, e.banner_color,
           u.name AS student_name, u.email AS student_email,
           u.department, u.year, u.phone
    FROM registrations r
    JOIN events e ON r.event_id=e.id
    JOIN users  u ON r.user_id=u.id
    WHERE r.user_id=? AND r.event_id=? AND r.status='registered'
    LIMIT 1
");
$stmt->execute([$user['id'], $eventId]);
$data = $stmt->fetch();

if (!$data) {
    setFlash('danger', 'No active registration found for this event.');
    header('Location: my_registrations.php');
    exit;
}

// Generate PDF using TCPDF if available, otherwise output styled HTML for printing
$vendorPath = __DIR__ . '/../vendor/autoload.php';
$useTcpdf   = file_exists($vendorPath);

if ($useTcpdf) {
    require_once $vendorPath;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('EventMS');
    $pdf->SetAuthor('EventMS');
    $pdf->SetTitle('Event Ticket - ' . $data['title']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    $color   = ltrim($data['banner_color'] ?? '#6c63ff', '#');
    $r = hexdec(substr($color,0,2));
    $g = hexdec(substr($color,2,2));
    $b = hexdec(substr($color,4,2));

    $html = '
    <style>
      .ticket-header { background-color: #' . $color . '; color: #fff; padding: 20px; border-radius: 8px; }
      .ticket-body   { padding: 20px; border: 2px solid #' . $color . '; border-radius: 8px; margin-top: 10px; }
      .label         { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
      .value         { font-size: 14px; font-weight: bold; margin-bottom: 10px; }
      .qr-area       { text-align: center; border: 2px dashed #ccc; padding: 15px; border-radius: 8px; margin-top: 15px; }
      .divider       { border-top: 1px dashed #ccc; margin: 15px 0; }
    </style>
    <div class="ticket-header">
      <h1 style="font-size:22px;margin:0;color:#fff;">🎯 EventMS — Event Ticket</h1>
      <p style="margin:5px 0 0;font-size:12px;color:rgba(255,255,255,0.8);">Official Registration Confirmation</p>
    </div>
    <div class="ticket-body">
      <h2 style="font-size:18px;color:#' . $color . ';">' . htmlspecialchars($data['title']) . '</h2>
      <div class="divider"></div>
      <table width="100%" cellpadding="4">
        <tr>
          <td width="50%">
            <span class="label">Student Name</span><br>
            <span class="value">' . htmlspecialchars($data['student_name']) . '</span>
          </td>
          <td width="50%">
            <span class="label">Email</span><br>
            <span class="value">' . htmlspecialchars($data['student_email']) . '</span>
          </td>
        </tr>
        <tr>
          <td>
            <span class="label">Department</span><br>
            <span class="value">' . htmlspecialchars($data['department'] ?? '—') . '</span>
          </td>
          <td>
            <span class="label">Year</span><br>
            <span class="value">' . ($data['year'] ? 'Year ' . $data['year'] : '—') . '</span>
          </td>
        </tr>
      </table>
      <div class="divider"></div>
      <table width="100%" cellpadding="4">
        <tr>
          <td width="50%">
            <span class="label">Event Date</span><br>
            <span class="value">' . date('d F Y', strtotime($data['event_date'])) . '</span>
          </td>
          <td width="50%">
            <span class="label">Event Time</span><br>
            <span class="value">' . ($data['event_time'] ? date('g:i A', strtotime($data['event_time'])) : 'TBD') . '</span>
          </td>
        </tr>
        <tr>
          <td>
            <span class="label">Venue</span><br>
            <span class="value">' . htmlspecialchars($data['venue']) . '</span>
          </td>
          <td>
            <span class="label">Category</span><br>
            <span class="value">' . htmlspecialchars($data['category']) . '</span>
          </td>
        </tr>
      </table>
      <div class="divider"></div>
      <table width="100%" cellpadding="4">
        <tr>
          <td>
            <span class="label">Registration ID</span><br>
            <span class="value">#REG-' . str_pad($data['id'],6,'0',STR_PAD_LEFT) . '</span>
          </td>
          <td>
            <span class="label">Registered On</span><br>
            <span class="value">' . date('d M Y, g:i A', strtotime($data['registered_at'])) . '</span>
          </td>
        </tr>
      </table>
      <div class="qr-area">
        <p style="font-size:11px;color:#666;margin:0;">✅ This ticket serves as your official registration confirmation.<br>Please carry this to the event venue for check-in.</p>
      </div>
      <p style="text-align:center;font-size:9px;color:#999;margin-top:15px;">Generated by EventMS · ' . date('d M Y') . '</p>
    </div>
    ';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('EventMS_Ticket_' . $data['id'] . '.pdf', 'D');
    exit;
}

// ── Fallback: HTML printable ticket ─────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Event Ticket — <?= htmlspecialchars($data['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #0a0b1a; color: #e8eaf6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .wrapper { max-width: 680px; width: 100%; }
    .actions { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.5rem; border: none; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg,#6c63ff,#00d4ff); color: #fff; }
    .btn-ghost { background: rgba(255,255,255,0.08); color: #9aa0c2; }
    .ticket { background: #fff; color: #111; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,0.6); }
    .ticket-header { background: <?= htmlspecialchars($data['banner_color']??'#6c63ff') ?>; padding: 2rem; color: #fff; }
    .ticket-header h1 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; margin-bottom: 0.25rem; }
    .ticket-header p { font-size: 0.85rem; opacity: 0.85; }
    .ticket-body { padding: 2rem; }
    .event-title { font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: <?= htmlspecialchars($data['banner_color']??'#6c63ff') ?>; margin-bottom: 0.5rem; }
    .event-desc  { font-size: 0.85rem; color: #555; margin-bottom: 1.5rem; line-height: 1.6; }
    .divider { border: none; border-top: 1px dashed #ddd; margin: 1.25rem 0; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .info-item .label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #999; font-weight: 700; margin-bottom: 0.25rem; }
    .info-item .value { font-size: 0.925rem; font-weight: 700; color: #111; }
    .reg-id { background: #f5f5f5; border-radius: 8px; padding: 1rem; text-align: center; margin: 1rem 0; }
    .reg-id .num { font-size: 1.4rem; font-weight: 800; color: <?= htmlspecialchars($data['banner_color']??'#6c63ff') ?>; font-family: monospace; letter-spacing: 2px; }
    .footer-note { background: #f9f9ff; border: 1px dashed #ddd; border-radius: 8px; padding: 1rem; text-align: center; font-size: 0.8rem; color: #666; margin-top: 1rem; }
    .logo-strip { background: #f5f5f5; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.78rem; color: #888; }
    .logo-text { font-family: 'Outfit'; font-weight: 800; font-size: 1.1rem; color: <?= htmlspecialchars($data['banner_color']??'#6c63ff') ?>; }
    @media print {
      body { background: #fff; padding: 0; }
      .actions { display: none; }
      .ticket { box-shadow: none; }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <!-- Action buttons (hidden on print) -->
  <div class="actions no-print">
    <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save as PDF</button>
    <a href="my_registrations.php" class="btn btn-ghost">← My Registrations</a>
  </div>

  <!-- Ticket -->
  <div class="ticket">
    <!-- Header -->
    <div class="ticket-header">
      <h1>🎯 EventMS — Official Ticket</h1>
      <p>Registration Confirmation · Keep this for check-in</p>
    </div>

    <!-- Body -->
    <div class="ticket-body">
      <div class="event-title"><?= htmlspecialchars($data['title']) ?></div>
      <p class="event-desc"><?= htmlspecialchars(substr($data['description']??'',0,200)) ?></p>

      <hr class="divider">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.08em;color:#999;font-weight:700;margin-bottom:0.75rem;">Student Information</div>
      <div class="info-grid">
        <div class="info-item">
          <div class="label">Student Name</div>
          <div class="value"><?= htmlspecialchars($data['student_name']) ?></div>
        </div>
        <div class="info-item">
          <div class="label">Email</div>
          <div class="value"><?= htmlspecialchars($data['student_email']) ?></div>
        </div>
        <div class="info-item">
          <div class="label">Department</div>
          <div class="value"><?= htmlspecialchars($data['department'] ?? '—') ?></div>
        </div>
        <div class="info-item">
          <div class="label">Year</div>
          <div class="value"><?= $data['year'] ? 'Year ' . $data['year'] : '—' ?></div>
        </div>
      </div>

      <hr class="divider">
      <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.08em;color:#999;font-weight:700;margin-bottom:0.75rem;">Event Details</div>
      <div class="info-grid">
        <div class="info-item">
          <div class="label">Event Date</div>
          <div class="value">📅 <?= date('d F Y', strtotime($data['event_date'])) ?></div>
        </div>
        <div class="info-item">
          <div class="label">Time</div>
          <div class="value">⏰ <?= $data['event_time'] ? date('g:i A', strtotime($data['event_time'])) : 'TBD' ?></div>
        </div>
        <div class="info-item">
          <div class="label">Venue</div>
          <div class="value">📍 <?= htmlspecialchars($data['venue']) ?></div>
        </div>
        <div class="info-item">
          <div class="label">Category</div>
          <div class="value">🏷 <?= htmlspecialchars($data['category']) ?></div>
        </div>
      </div>

      <hr class="divider">
      <div class="reg-id">
        <div style="font-size:0.72rem;color:#999;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.4rem;">Registration ID</div>
        <div class="num">#REG-<?= str_pad($data['id'],6,'0',STR_PAD_LEFT) ?></div>
        <div style="font-size:0.78rem;color:#888;margin-top:0.35rem;">
          Registered on <?= date('d M Y, g:i A', strtotime($data['registered_at'])) ?>
        </div>
      </div>

      <div class="footer-note">
        ✅ This ticket confirms your registration for the event.<br>
        Please present this at the venue entrance for check-in.<br>
        <strong>EventMS University Event Management System</strong>
      </div>
    </div>

    <!-- Logo Strip -->
    <div class="logo-strip">
      <span class="logo-text">🎯 EventMS</span>
      <span>Generated on <?= date('d M Y, g:i A') ?></span>
    </div>
  </div>
</div>
</body>
</html>
