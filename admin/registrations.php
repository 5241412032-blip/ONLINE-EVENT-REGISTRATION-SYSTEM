<?php
// admin/registrations.php — View All Registrations
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

$eventId = (int)($_GET['event_id'] ?? 0);
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');

// Build query
$where  = ['1=1'];
$params = [];
if ($eventId) { $where[] = 'r.event_id=?';  $params[] = $eventId; }
if ($status)  { $where[] = 'r.status=?';    $params[] = $status; }
if ($search)  { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR e.title LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT r.*, u.name AS student_name, u.email AS student_email,
           u.department, u.year, u.phone,
           e.title AS event_title, e.event_date, e.venue, e.category
    FROM registrations r
    JOIN users u  ON r.user_id  = u.id
    JOIN events e ON r.event_id = e.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.registered_at DESC
");
$stmt->execute($params);
$regs = $stmt->fetchAll();

// Events for filter dropdown
$events = $db->query("SELECT id, title FROM events ORDER BY event_date DESC")->fetchAll();

// Current event for context
$currentEvent = null;
if ($eventId) {
    $s = $db->prepare("SELECT * FROM events WHERE id=?"); $s->execute([$eventId]); $currentEvent=$s->fetch();
}

// Stats for current filter
$total      = count($regs);
$registered = count(array_filter($regs, fn($r)=>$r['status']==='registered'));
$cancelled  = count(array_filter($regs, fn($r)=>$r['status']==='cancelled'));

$pageTitle  = 'Registrations';
$activeMenu = 'registrations';
include '../includes/admin_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb"><a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span> Registrations</div>
    <h1 class="page-title">
      <?= $currentEvent ? htmlspecialchars($currentEvent['title']) . ' — Registrations' : 'All Registrations' ?>
    </h1>
    <p class="page-subtitle"><?= $total ?> total · <?= $registered ?> registered · <?= $cancelled ?> cancelled</p>
  </div>
  <?php if ($currentEvent): ?>
  <a href="registrations.php" class="btn btn-outline">← All Registrations</a>
  <?php endif; ?>
</div>

<!-- Quick stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(108,99,255,0.15);">📋</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $total ?>"><?= $total ?></div>
      <div class="stat-label">Total Records</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,230,118,0.15);">✅</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $registered ?>"><?= $registered ?></div>
      <div class="stat-label">Registered</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,82,82,0.15);">❌</div>
    <div class="stat-info">
      <div class="stat-number" data-counter="<?= $cancelled ?>"><?= $cancelled ?></div>
      <div class="stat-label">Cancelled</div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="registrations.php" class="filter-bar">
  <div class="search-box">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" placeholder="Search student or event…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="event_id" class="filter-select" onchange="this.form.submit()">
    <option value="">All Events</option>
    <?php foreach ($events as $ev): ?>
    <option value="<?= $ev['id'] ?>" <?= $eventId===$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['title']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="filter-select" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <option value="registered" <?= $status==='registered'?'selected':'' ?>>Registered</option>
    <option value="cancelled"  <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if ($search||$eventId||$status): ?>
  <a href="registrations.php" class="btn btn-ghost btn-sm">✕ Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Department</th>
          <th>Event</th>
          <th>Event Date</th>
          <th>Registered At</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($regs as $i => $r): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="td-bold"><?= htmlspecialchars($r['student_name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($r['student_email']) ?></div>
            <?php if ($r['phone']): ?>
            <div style="font-size:0.72rem;color:var(--text-muted);">📱 <?= htmlspecialchars($r['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);">
            <?= htmlspecialchars($r['department'] ?? '—') ?>
            <?php if ($r['year']): ?><br><span style="font-size:0.75rem;color:var(--text-muted);">Year <?= $r['year'] ?></span><?php endif; ?>
          </td>
          <td>
            <div style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($r['event_title']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">📍 <?= htmlspecialchars($r['venue']) ?></div>
          </td>
          <td><?= date('d M Y', strtotime($r['event_date'])) ?></td>
          <td><?= date('d M Y, g:i A', strtotime($r['registered_at'])) ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($regs)): ?>
        <tr><td colspan="7">
          <div class="empty-state"><div class="empty-icon">📋</div>
          <div class="empty-title">No registrations found</div></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/admin_layout_end.php'; ?>
