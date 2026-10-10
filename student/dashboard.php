<?php
// student/dashboard.php — Student Dashboard
require_once '../includes/auth_check.php';
$user = requireRole('student');
$db   = getDB();

// Student's registration stats
$myStats = $db->prepare("
    SELECT
        COUNT(*) AS total_reg,
        COUNT(CASE WHEN r.status='registered' AND e.event_date >= CURDATE() THEN 1 END) AS upcoming,
        COUNT(CASE WHEN r.status='registered' AND e.event_date < CURDATE()  THEN 1 END) AS past,
        COUNT(CASE WHEN r.status='cancelled'                                 THEN 1 END) AS cancelled
    FROM registrations r JOIN events e ON r.event_id=e.id
    WHERE r.user_id=?
");
$myStats->execute([$user['id']]);
$myStats = $myStats->fetch();

// My upcoming events
$myUpcoming = $db->prepare("
    SELECT e.*, r.id AS reg_id, r.registered_at
    FROM registrations r JOIN events e ON r.event_id=e.id
    WHERE r.user_id=? AND r.status='registered' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC LIMIT 3
");
$myUpcoming->execute([$user['id']]);
$myUpcoming = $myUpcoming->fetchAll();

// Suggested events (not yet registered, upcoming)
$suggested = $db->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM registrations rr WHERE rr.event_id=e.id AND rr.status='registered') AS reg_count
    FROM events e
    WHERE e.status='upcoming' AND e.event_date >= CURDATE()
      AND e.id NOT IN (SELECT event_id FROM registrations WHERE user_id=? AND status='registered')
    ORDER BY e.event_date ASC LIMIT 3
");
$suggested->execute([$user['id']]);
$suggested = $suggested->fetchAll();

$pageTitle  = 'My Dashboard';
$activeMenu = 'dashboard';
include '../includes/student_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb">Student Portal</div>
    <h1 class="page-title">Welcome, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>! 👋</h1>
    <p class="page-subtitle">Here's an overview of your event activity.</p>
  </div>
  <a href="events.php" class="btn btn-primary">🔍 Browse Events</a>
</div>

<!-- Stats -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(108,99,255,0.15);">📋</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $myStats['total_reg'] ?>"><?= $myStats['total_reg'] ?></div>
      <div class="stat-label">Total Registrations</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,212,255,0.15);">🔜</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $myStats['upcoming'] ?>"><?= $myStats['upcoming'] ?></div>
      <div class="stat-label">Upcoming Events</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,230,118,0.15);">✅</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $myStats['past'] ?>"><?= $myStats['past'] ?></div>
      <div class="stat-label">Attended</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,82,82,0.15);">❌</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $myStats['cancelled'] ?>"><?= $myStats['cancelled'] ?></div>
      <div class="stat-label">Cancelled</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="dash-grid">
  <!-- My Upcoming -->
  <div class="card">
    <div class="card-header">
      <h4 style="color:var(--text-primary);">🔜 My Upcoming Events</h4>
      <a href="my_registrations.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (empty($myUpcoming)): ?>
    <div class="empty-state" style="padding:2rem;">
      <div class="empty-icon">📅</div>
      <div class="empty-title">No upcoming events</div>
      <div class="empty-desc"><a href="events.php">Browse events</a> to register.</div>
    </div>
    <?php else: ?>
    <?php foreach ($myUpcoming as $ev): ?>
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:1rem;">
      <div style="width:44px;height:44px;border-radius:var(--radius-md);background:<?= htmlspecialchars($ev['banner_color']??'#6c63ff') ?>;display:grid;place-items:center;font-size:1.2rem;flex-shrink:0;">📅</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;color:var(--text-primary);font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ev['title']) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?> · <?= htmlspecialchars($ev['venue']) ?></div>
      </div>
      <a href="download_pdf.php?event_id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Download PDF">📄</a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Suggested Events -->
  <div class="card">
    <div class="card-header">
      <h4 style="color:var(--text-primary);">✨ Suggested Events</h4>
      <a href="events.php" class="btn btn-ghost btn-sm">See All</a>
    </div>
    <?php if (empty($suggested)): ?>
    <div class="empty-state" style="padding:2rem;">
      <div class="empty-icon">🎉</div>
      <div class="empty-title">You're all caught up!</div>
      <div class="empty-desc">You've registered for all available events.</div>
    </div>
    <?php else: ?>
    <?php foreach ($suggested as $ev):
      $seatsLeft = max(0,$ev['capacity']-$ev['reg_count']);
      $full = $seatsLeft === 0;
    ?>
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:1rem;">
      <div style="width:44px;height:44px;border-radius:var(--radius-md);background:<?= htmlspecialchars($ev['banner_color']??'#6c63ff') ?>;display:grid;place-items:center;font-size:1.2rem;flex-shrink:0;">🎯</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;color:var(--text-primary);font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ev['title']) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);"><?= date('d M Y', strtotime($ev['event_date'])) ?> · <?= $seatsLeft ?> seats left</div>
      </div>
      <?php if (!$full): ?>
      <button onclick="registerEvent(<?= $ev['id'] ?>, this)" class="btn btn-primary btn-sm" data-event-id="<?= $ev['id'] ?>">Register</button>
      <?php else: ?>
      <span class="badge badge-cancelled">Full</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Profile Card -->
<div class="card mt-3">
  <div class="card-header"><h4 style="color:var(--text-primary);">👤 My Profile</h4></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;font-size:0.875rem;">
      <div><span class="text-muted">Name:</span><br><strong><?= htmlspecialchars($user['name']) ?></strong></div>
      <div><span class="text-muted">Email:</span><br><strong><?= htmlspecialchars($user['email']) ?></strong></div>
      <div><span class="text-muted">Department:</span><br><strong><?= htmlspecialchars($user['department'] ?? '—') ?></strong></div>
      <div><span class="text-muted">Role:</span><br><span class="badge badge-student">Student</span></div>
    </div>
  </div>
</div>

<?php include '../includes/student_layout_end.php'; ?>
