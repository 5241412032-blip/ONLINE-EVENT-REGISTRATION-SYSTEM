<?php
// student/my_registrations.php — View & Cancel Registrations
require_once '../includes/auth_check.php';
$user = requireRole('student');
$db   = getDB();

$filter = $_GET['filter'] ?? 'all';

$where  = ['r.user_id=?'];
$params = [$user['id']];
if ($filter === 'upcoming')  { $where[] = "r.status='registered' AND e.event_date >= CURDATE()"; }
if ($filter === 'past')      { $where[] = "r.status='registered' AND e.event_date < CURDATE()"; }
if ($filter === 'cancelled') { $where[] = "r.status='cancelled'"; }

$stmt = $db->prepare("
    SELECT r.*, e.title AS event_title, e.event_date, e.event_time, e.venue,
           e.category, e.status AS event_status, e.banner_color, e.description
    FROM registrations r JOIN events e ON r.event_id=e.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.event_date ASC
");
$stmt->execute($params);
$regs = $stmt->fetchAll();

$counts = $db->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN r.status='registered' AND e.event_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming,
      SUM(CASE WHEN r.status='registered' AND e.event_date < CURDATE()  THEN 1 ELSE 0 END) AS past,
      SUM(CASE WHEN r.status='cancelled'                                  THEN 1 ELSE 0 END) AS cancelled
    FROM registrations r JOIN events e ON r.event_id=e.id
    WHERE r.user_id=?
");
$counts->execute([$user['id']]);
$counts = $counts->fetch();

$pageTitle  = 'My Registrations';
$activeMenu = 'myreg';
include '../includes/student_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb">Student Portal</div>
    <h1 class="page-title">My Registrations</h1>
    <p class="page-subtitle">Track all your event registrations in one place.</p>
  </div>
  <a href="events.php" class="btn btn-primary">➕ Register for More</a>
</div>

<!-- Filter Tabs -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.5rem;background:rgba(255,255,255,0.04);border-radius:var(--radius-full);padding:0.25rem;width:fit-content;">
  <?php
  $filters = [
    'all'       => ['label' => 'All',      'count' => $counts['total']],
    'upcoming'  => ['label' => 'Upcoming', 'count' => $counts['upcoming']],
    'past'      => ['label' => 'Past',     'count' => $counts['past']],
    'cancelled' => ['label' => 'Cancelled','count' => $counts['cancelled']],
  ];
  foreach ($filters as $key => $f): ?>
  <a href="my_registrations.php?filter=<?= $key ?>"
    class="btn btn-sm <?= $filter===$key?'btn-primary':'btn-ghost' ?>"
    style="border-radius:var(--radius-full);">
    <?= $f['label'] ?> <span style="opacity:0.7;font-size:0.75rem;">(<?= $f['count'] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($regs)): ?>
<div class="empty-state">
  <div class="empty-icon">📋</div>
  <div class="empty-title">No registrations found</div>
  <div class="empty-desc"><a href="events.php">Browse events</a> and register to get started.</div>
</div>
<?php else: ?>
<div class="events-grid">
  <?php foreach ($regs as $reg):
    $upcoming  = strtotime($reg['event_date']) >= strtotime(date('Y-m-d'));
    $isCancelled = $reg['status'] === 'cancelled';
    $color     = htmlspecialchars($reg['banner_color'] ?? '#6c63ff');
  ?>
  <div class="event-card" id="reg-card-<?= $reg['id'] ?>">
    <div class="event-card-banner" style="background:<?= $isCancelled ? 'var(--clr-red)' : $color ?>;"></div>
    <div class="event-card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
        <span class="event-card-category">🏷 <?= htmlspecialchars($reg['category']) ?></span>
        <span class="badge badge-<?= $isCancelled?'cancelled':($upcoming?'registered':'completed') ?>">
          <?= $isCancelled ? 'Cancelled' : ($upcoming ? 'Registered' : 'Past') ?>
        </span>
      </div>
      <h3 class="event-card-title"><?= htmlspecialchars($reg['event_title']) ?></h3>
      <p class="event-card-desc"><?= htmlspecialchars(substr($reg['description']??'',0,100)) ?>...</p>
      <div class="event-card-meta">
        <span class="event-meta-item">📅 <?= date('d M Y', strtotime($reg['event_date'])) ?></span>
        <?php if ($reg['event_time']): ?>
        <span class="event-meta-item">⏰ <?= date('g:i A', strtotime($reg['event_time'])) ?></span>
        <?php endif; ?>
        <span class="event-meta-item">📍 <?= htmlspecialchars($reg['venue']) ?></span>
        <span class="event-meta-item" style="color:var(--text-muted);">Registered: <?= date('d M Y', strtotime($reg['registered_at'])) ?></span>
      </div>

      <div class="event-card-footer">
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
          <?php if (!$isCancelled): ?>
          <a href="download_pdf.php?event_id=<?= $reg['event_id'] ?>" class="btn btn-primary btn-sm">
            📄 Download Ticket
          </a>
          <?php if ($upcoming): ?>
          <button onclick="cancelRegistration(<?= $reg['id'] ?>, this)"
            class="btn btn-danger btn-sm" id="cancel-btn-<?= $reg['id'] ?>">
            ✕ Cancel
          </button>
          <?php endif; ?>
          <?php else: ?>
          <span style="font-size:0.8rem;color:var(--text-muted);">
            Cancelled on <?= $reg['cancelled_at'] ? date('d M Y', strtotime($reg['cancelled_at'])) : '—' ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/student_layout_end.php'; ?>
