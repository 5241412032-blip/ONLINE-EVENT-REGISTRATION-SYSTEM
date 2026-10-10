<?php
// admin/dashboard.php — Admin Dashboard
require_once '../includes/auth_check.php';
$user = requireRole('admin');

$db = getDB();

// Fetch stats
$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM events)                               AS total_events,
        (SELECT COUNT(*) FROM events WHERE status='upcoming')       AS upcoming_events,
        (SELECT COUNT(*) FROM users  WHERE role='student')          AS total_students,
        (SELECT COUNT(*) FROM registrations WHERE status='registered') AS total_registrations
")->fetch();

// Recent events
$recentEvents = $db->query("
    SELECT e.*, u.name AS creator_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status='registered') AS reg_count
    FROM events e JOIN users u ON e.created_by=u.id
    ORDER BY e.created_at DESC LIMIT 5
")->fetchAll();

// Recent registrations
$recentRegs = $db->query("
    SELECT r.*, u.name AS student_name, u.email AS student_email,
           e.title AS event_title, e.event_date
    FROM registrations r
    JOIN users u  ON r.user_id  = u.id
    JOIN events e ON r.event_id = e.id
    WHERE r.status='registered'
    ORDER BY r.registered_at DESC LIMIT 8
")->fetchAll();

// Chart data: registrations per event (top 5)
$chartData = $db->query("
    SELECT e.title, COUNT(r.id) AS cnt
    FROM events e
    LEFT JOIN registrations r ON r.event_id=e.id AND r.status='registered'
    GROUP BY e.id ORDER BY cnt DESC LIMIT 5
")->fetchAll();

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
$extraHead  = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
include '../includes/admin_layout.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <div class="breadcrumb"><a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span> Dashboard</div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>! Here's what's happening today.</p>
  </div>
  <a href="event_add.php" class="btn btn-primary">➕ New Event</a>
</div>

<!-- Stats -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(108,99,255,0.15);">📅</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $stats['total_events'] ?>"><?= $stats['total_events'] ?></div>
      <div class="stat-label">Total Events</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,107,157,0.15);">🔥</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $stats['upcoming_events'] ?>"><?= $stats['upcoming_events'] ?></div>
      <div class="stat-label">Upcoming Events</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,230,118,0.15);">🎓</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $stats['total_students'] ?>"><?= $stats['total_students'] ?></div>
      <div class="stat-label">Students</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,171,64,0.15);">✅</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $stats['total_registrations'] ?>"><?= $stats['total_registrations'] ?></div>
      <div class="stat-label">Registrations</div>
    </div>
  </div>
</div>

<!-- Chart + Recent Events -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="dash-grid">
  <!-- Chart -->
  <div class="card">
    <div class="card-header">
      <h4 style="color:var(--text-primary);">📊 Registrations by Event</h4>
    </div>
    <div class="card-body">
      <canvas id="regChart" height="200"></canvas>
    </div>
  </div>

  <!-- Recent Events -->
  <div class="card">
    <div class="card-header">
      <h4 style="color:var(--text-primary);">📅 Recent Events</h4>
      <a href="events.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php foreach ($recentEvents as $ev): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;border-bottom:1px solid var(--border);">
        <div>
          <div style="font-weight:600;font-size:0.875rem;color:var(--text-primary);margin-bottom:0.2rem;">
            <?= htmlspecialchars($ev['title']) ?>
          </div>
          <div style="font-size:0.75rem;color:var(--text-muted);">
            <?= date('d M Y', strtotime($ev['event_date'])) ?> · <?= $ev['reg_count'] ?> registered
          </div>
        </div>
        <span class="badge badge-<?= htmlspecialchars($ev['status']) ?>"><?= htmlspecialchars($ev['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Recent Registrations -->
<div class="table-wrapper">
  <div class="card-header">
    <h4 style="color:var(--text-primary);">✅ Recent Registrations</h4>
    <a href="registrations.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Event</th>
          <th>Event Date</th>
          <th>Registered At</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRegs as $i => $reg): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="td-bold"><?= htmlspecialchars($reg['student_name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($reg['student_email']) ?></div>
          </td>
          <td><?= htmlspecialchars($reg['event_title']) ?></td>
          <td><?= date('d M Y', strtotime($reg['event_date'])) ?></td>
          <td><?= date('d M Y, g:i A', strtotime($reg['registered_at'])) ?></td>
          <td><span class="badge badge-<?= $reg['status'] ?>"><?= $reg['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentRegs)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">No registrations yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$chartLabels = json_encode(array_column($chartData, 'title'));
$chartCounts = json_encode(array_column($chartData, 'cnt'));
$inlineScript = "
  document.addEventListener('DOMContentLoaded', () => {
    drawBarChart('regChart', {$chartLabels}, {$chartCounts}, 'Registrations');
  });
";
include '../includes/admin_layout_end.php';
?>
