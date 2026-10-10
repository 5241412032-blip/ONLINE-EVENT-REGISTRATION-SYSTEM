<?php
// admin/report_pdf.php — Printable / Downloadable PDF Report for Admin
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

$eventId = (int)($_GET['event_id'] ?? 0);

// ── Fetch report data ───────────────────────────────────────────────────────
if ($eventId) {
    // Single-event report
    $evStmt = $db->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $evStmt->execute([$eventId]);
    $event = $evStmt->fetch();

    if (!$event) {
        header('Location: reports.php?error=Event+not+found');
        exit;
    }

    $regStmt = $db->prepare("
        SELECT r.*, u.name AS student_name, u.email, u.department, u.year, u.phone
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        WHERE r.event_id = ?
        ORDER BY r.status ASC, r.registered_at ASC
    ");
    $regStmt->execute([$eventId]);
    $registrations = $regStmt->fetchAll();

    $registered = array_filter($registrations, fn($r) => $r['status'] === 'registered');
    $cancelled  = array_filter($registrations, fn($r) => $r['status'] === 'cancelled');
    $regCount   = count($registered);
    $canCount   = count($cancelled);
    $fillPct    = $event['capacity'] > 0 ? round($regCount / $event['capacity'] * 100, 1) : 0;

    // Log this report generation
    $db->prepare("INSERT INTO reports (event_id, generated_by, report_type) VALUES (?, ?, 'event_pdf')")
       ->execute([$eventId, $user['id']]);

    $reportTitle = 'Event Report: ' . $event['title'];
    $mode        = 'single';
} else {
    // All-events summary report
    $allStats = $db->query("
        SELECT e.*,
               COUNT(CASE WHEN r.status='registered' THEN 1 END) AS reg_count,
               COUNT(CASE WHEN r.status='cancelled'  THEN 1 END) AS can_count,
               ROUND(COUNT(CASE WHEN r.status='registered' THEN 1 END)*100.0/NULLIF(e.capacity,0),1) AS fill_pct
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        GROUP BY e.id
        ORDER BY e.event_date DESC
    ")->fetchAll();

    $totalEvents = count($allStats);
    $totalRegs   = array_sum(array_column($allStats, 'reg_count'));
    $totalCan    = array_sum(array_column($allStats, 'can_count'));

    // Log this report generation
    $db->prepare("INSERT INTO reports (event_id, generated_by, report_type) VALUES (NULL, ?, 'all_events_pdf')")
       ->execute([$user['id']]);

    $reportTitle = 'All Events Summary Report';
    $mode        = 'all';
}

$generatedAt  = date('d F Y, g:i A');
$generatedBy  = $user['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($reportTitle) ?> | EventMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ── Base ────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 14px; }
    body { font-family: 'Inter', sans-serif; background: #f4f6fb; color: #111827; }
    a    { color: #4f46e5; text-decoration: none; }

    /* ── Screen-only controls ────────────────── */
    .screen-bar {
      background: #0f1235;
      padding: 0.85rem 2rem;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
      position: sticky; top: 0; z-index: 100;
    }
    .screen-bar-brand { font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: #6c63ff; display: flex; align-items: center; gap: 0.5rem; }
    .screen-bar-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.25rem; border: none; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity 0.2s; }
    .btn:hover { opacity: 0.85; }
    .btn-primary { background: linear-gradient(135deg, #6c63ff, #00d4ff); color: #fff; }
    .btn-outline { background: transparent; border: 1px solid #6c63ff; color: #6c63ff; }
    .btn-back    { background: rgba(255,255,255,0.1); color: #ccc; }

    /* ── Report wrapper ──────────────────────── */
    .report-page {
      max-width: 900px; margin: 2rem auto; padding: 0 1rem 3rem;
    }

    /* ── Report card ─────────────────────────── */
    .report-card {
      background: #fff; border-radius: 16px; overflow: hidden;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    }

    /* ── Header banner ───────────────────────── */
    .report-header {
      background: linear-gradient(135deg, #1a1040 0%, #2d1b69 50%, #0f2d5a 100%);
      padding: 2.5rem 2.5rem 2rem;
      color: #fff;
    }
    .report-header-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .report-badge { background: rgba(108,99,255,0.3); border: 1px solid rgba(108,99,255,0.5); border-radius: 9999px; padding: 0.25rem 0.85rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #c4b5fd; margin-bottom: 0.75rem; display: inline-block; }
    .report-title { font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 0.5rem; }
    .report-meta { font-size: 0.78rem; color: rgba(255,255,255,0.6); display: flex; flex-wrap: wrap; gap: 1.25rem; margin-top: 1rem; }
    .report-meta span::before { margin-right: 0.3rem; }
    .report-logo { font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 800; color: #6c63ff; opacity: 0.9; white-space: nowrap; }

    /* ── Summary stat boxes ──────────────────── */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; padding: 1.5rem 2rem; border-bottom: 1px solid #e5e7eb; background: #fafbff; }
    .stat-box { text-align: center; padding: 1rem 0.5rem; border-radius: 10px; background: #fff; border: 1px solid #e5e7eb; }
    .stat-box .num { font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; line-height: 1; }
    .stat-box .lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 0.3rem; }
    .c-purple { color: #6c63ff; } .c-green { color: #059669; } .c-red { color: #dc2626; } .c-orange { color: #d97706; } .c-blue { color: #2563eb; }

    /* ── Section ─────────────────────────────── */
    .section { padding: 1.5rem 2rem; }
    .section + .section { border-top: 1px solid #f0f0f5; }
    .section-title { font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; color: #374151; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .section-title::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }

    /* ── Event info grid ─────────────────────── */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem 1.5rem; }
    .info-item .lbl { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em; color: #9ca3af; font-weight: 700; margin-bottom: 0.2rem; }
    .info-item .val { font-size: 0.875rem; font-weight: 600; color: #111827; }

    /* ── Table ───────────────────────────────── */
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .data-table thead tr { background: #f3f4f6; }
    .data-table th { padding: 0.65rem 0.85rem; text-align: left; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .data-table td { padding: 0.7rem 0.85rem; border-bottom: 1px solid #f0f0f5; color: #374151; vertical-align: middle; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: #fafbff; }
    .td-bold { font-weight: 600; color: #111827; }
    .badge { display: inline-flex; align-items: center; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 700; }
    .badge-reg { background: #d1fae5; color: #065f46; }
    .badge-can { background: #fee2e2; color: #991b1b; }
    .badge-up  { background: #ede9fe; color: #5b21b6; }
    .badge-com { background: #d1fae5; color: #065f46; }
    .badge-ong { background: #dbeafe; color: #1d4ed8; }

    /* ── Fill-rate bar ───────────────────────── */
    .fill-bar { display: flex; align-items: center; gap: 0.5rem; }
    .fill-track { flex: 1; height: 6px; background: #e5e7eb; border-radius: 9999px; min-width: 50px; }
    .fill-inner { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #6c63ff, #00d4ff); }
    .fill-inner.warn { background: linear-gradient(90deg, #f59e0b, #ef4444); }

    /* ── Footer ──────────────────────────────── */
    .report-footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 1.25rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; font-size: 0.75rem; color: #6b7280; }
    .report-footer strong { color: #374151; }

    /* ── Empty state ─────────────────────────── */
    .empty { text-align: center; padding: 2.5rem; color: #9ca3af; }
    .empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

    /* ── Print styles ────────────────────────── */
    @media print {
      body { background: #fff; }
      .screen-bar { display: none !important; }
      .report-page { margin: 0; padding: 0; max-width: 100%; }
      .report-card { box-shadow: none; border-radius: 0; }
      .data-table tbody tr:hover { background: transparent; }
    }
  </style>
</head>
<body>

<!-- ── Screen controls (hidden when printing) ── -->
<div class="screen-bar">
  <div class="screen-bar-brand">🎯 EventMS — Report Preview</div>
  <div class="screen-bar-actions">
    <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save as PDF</button>
    <a href="reports.php<?= $eventId ? '?event_id='.$eventId : '' ?>" class="btn btn-back">← Back to Reports</a>
  </div>
</div>

<div class="report-page">
  <div class="report-card">

    <!-- ── Report Header ── -->
    <div class="report-header">
      <div class="report-header-top">
        <div>
          <div class="report-badge">📄 Official EventMS Report</div>
          <h1 class="report-title"><?= htmlspecialchars($reportTitle) ?></h1>
          <div class="report-meta">
            <span>📅 Generated: <?= $generatedAt ?></span>
            <span>👤 By: <?= htmlspecialchars($generatedBy) ?></span>
            <?php if ($mode === 'single'): ?>
            <span>🏷 Category: <?= htmlspecialchars($event['category']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="report-logo">🎯 EventMS</div>
      </div>
    </div>

    <?php if ($mode === 'single'): ?>
    <!-- ════════════════════════════════════════
         SINGLE EVENT REPORT
         ════════════════════════════════════════ -->

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-box">
        <div class="num c-purple"><?= $regCount ?></div>
        <div class="lbl">Registered</div>
      </div>
      <div class="stat-box">
        <div class="num c-red"><?= $canCount ?></div>
        <div class="lbl">Cancelled</div>
      </div>
      <div class="stat-box">
        <div class="num c-blue"><?= $event['capacity'] ?></div>
        <div class="lbl">Capacity</div>
      </div>
      <div class="stat-box">
        <div class="num c-orange"><?= $fillPct ?>%</div>
        <div class="lbl">Fill Rate</div>
      </div>
    </div>

    <!-- Event Details -->
    <div class="section">
      <div class="section-title">📋 Event Information</div>
      <div class="info-grid">
        <div class="info-item"><div class="lbl">Event Title</div><div class="val"><?= htmlspecialchars($event['title']) ?></div></div>
        <div class="info-item"><div class="lbl">Date</div><div class="val">📅 <?= date('d F Y', strtotime($event['event_date'])) ?></div></div>
        <div class="info-item"><div class="lbl">Time</div><div class="val">⏰ <?= $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD' ?></div></div>
        <div class="info-item"><div class="lbl">Venue</div><div class="val">📍 <?= htmlspecialchars($event['venue']) ?></div></div>
        <div class="info-item"><div class="lbl">Category</div><div class="val">🏷 <?= htmlspecialchars($event['category']) ?></div></div>
        <div class="info-item"><div class="lbl">Status</div><div class="val"><span class="badge badge-up"><?= htmlspecialchars($event['status']) ?></span></div></div>
      </div>
    </div>

    <!-- Registered Students -->
    <div class="section">
      <div class="section-title">✅ Registered Students (<?= $regCount ?>)</div>
      <?php if (empty($registered)): ?>
      <div class="empty"><div class="empty-icon">😔</div>No registered students yet.</div>
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Year</th>
            <th>Phone</th>
            <th>Registered At</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($registered as $r): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td class="td-bold"><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
            <td><?= $r['year'] ? 'Year ' . $r['year'] : '—' ?></td>
            <td><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
            <td><?= date('d M Y, g:i A', strtotime($r['registered_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php if (!empty($cancelled)): ?>
    <!-- Cancelled -->
    <div class="section">
      <div class="section-title">❌ Cancelled Registrations (<?= $canCount ?>)</div>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Cancelled At</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($cancelled as $r): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td class="td-bold"><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
            <td><?= $r['cancelled_at'] ? date('d M Y, g:i A', strtotime($r['cancelled_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ════════════════════════════════════════
         ALL EVENTS SUMMARY REPORT
         ════════════════════════════════════════ -->

    <!-- Global Stats -->
    <div class="stats-row">
      <div class="stat-box">
        <div class="num c-purple"><?= $totalEvents ?></div>
        <div class="lbl">Total Events</div>
      </div>
      <div class="stat-box">
        <div class="num c-green"><?= $totalRegs ?></div>
        <div class="lbl">Total Registered</div>
      </div>
      <div class="stat-box">
        <div class="num c-red"><?= $totalCan ?></div>
        <div class="lbl">Total Cancelled</div>
      </div>
      <div class="stat-box">
        <div class="num c-orange"><?= $totalEvents > 0 ? round($totalRegs / $totalEvents, 1) : 0 ?></div>
        <div class="lbl">Avg Registrations</div>
      </div>
    </div>

    <!-- All Events Table -->
    <div class="section">
      <div class="section-title">📋 All Events Overview</div>
      <?php if (empty($allStats)): ?>
      <div class="empty"><div class="empty-icon">📅</div>No events found.</div>
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Event Title</th>
            <th>Date</th>
            <th>Venue</th>
            <th>Category</th>
            <th>Capacity</th>
            <th>Registered</th>
            <th>Cancelled</th>
            <th>Fill Rate</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allStats as $i => $s): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td class="td-bold" style="max-width:180px;"><?= htmlspecialchars($s['title']) ?></td>
            <td style="white-space:nowrap;"><?= date('d M Y', strtotime($s['event_date'])) ?></td>
            <td><?= htmlspecialchars($s['venue']) ?></td>
            <td><?= htmlspecialchars($s['category']) ?></td>
            <td><?= $s['capacity'] ?></td>
            <td style="font-weight:700;color:#059669;"><?= (int)$s['reg_count'] ?></td>
            <td style="font-weight:700;color:#dc2626;"><?= (int)$s['can_count'] ?></td>
            <td>
              <div class="fill-bar">
                <div class="fill-track">
                  <div class="fill-inner <?= ($s['fill_pct']??0)>=80?'warn':'' ?>"
                       style="width:<?= min(100,(int)($s['fill_pct']??0)) ?>%"></div>
                </div>
                <span style="font-size:0.72rem;color:#6b7280;white-space:nowrap;"><?= number_format($s['fill_pct']??0,1) ?>%</span>
              </div>
            </td>
            <td>
              <?php $sc = $s['status'];
                $bc = $sc==='upcoming'?'ede9fe/5b21b6':($sc==='ongoing'?'dbeafe/1d4ed8':($sc==='completed'?'d1fae5/065f46':'fee2e2/991b1b'));
                [$bg,$fc] = explode('/', $bc);
              ?>
              <span style="background:#<?= $bg ?>;color:#<?= $fc ?>;padding:0.18rem 0.55rem;border-radius:9999px;font-size:0.68rem;font-weight:700;">
                <?= ucfirst($sc) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Report Footer -->
    <div class="report-footer">
      <span>🎯 <strong>EventMS</strong> — Online Event Management System</span>
      <span>Report generated on <?= $generatedAt ?> by <strong><?= htmlspecialchars($generatedBy) ?></strong></span>
    </div>
  </div><!-- .report-card -->
</div><!-- .report-page -->

<script>
  // Auto-trigger print if ?print=1 is in URL
  if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
  }
</script>
</body>
</html>
