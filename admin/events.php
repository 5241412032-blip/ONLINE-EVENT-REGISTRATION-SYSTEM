<?php
// admin/events.php — Manage Events List
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

// Search & filter
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search)   { $where[] = "(e.title LIKE ? OR e.venue LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status)   { $where[] = "e.status=?";   $params[] = $status; }
if ($category) { $where[] = "e.category=?"; $params[] = $category; }

$sql = "SELECT e.*, u.name AS creator_name,
          (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status='registered') AS reg_count
        FROM events e JOIN users u ON e.created_by=u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.event_date DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Categories for filter
$categories = $db->query("SELECT DISTINCT category FROM events ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Manage Events';
$activeMenu = 'events';
include '../includes/admin_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb"><a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span> Events</div>
    <h1 class="page-title">Manage Events</h1>
    <p class="page-subtitle"><?= count($events) ?> event(s) found</p>
  </div>
  <a href="event_add.php" class="btn btn-primary">➕ Add New Event</a>
</div>

<!-- Filter Bar -->
<form method="GET" action="events.php" class="filter-bar">
  <div class="search-box">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" placeholder="Search events…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="status" class="filter-select" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <?php foreach (['upcoming','ongoing','completed','cancelled'] as $s): ?>
    <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" class="filter-select" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach ($categories as $cat): ?>
    <option value="<?= htmlspecialchars($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if ($search||$status||$category): ?>
  <a href="events.php" class="btn btn-ghost btn-sm">✕ Clear</a>
  <?php endif; ?>
</form>

<!-- Events Table -->
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Event</th>
          <th>Date & Time</th>
          <th>Venue</th>
          <th>Category</th>
          <th>Capacity</th>
          <th>Registered</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $i => $ev): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="td-bold" style="max-width:200px;"><?= htmlspecialchars($ev['title']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">by <?= htmlspecialchars($ev['creator_name']) ?></div>
          </td>
          <td>
            <div style="font-weight:500;color:var(--text-primary);"><?= date('d M Y', strtotime($ev['event_date'])) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= $ev['event_time'] ? date('g:i A', strtotime($ev['event_time'])) : '—' ?></div>
          </td>
          <td style="max-width:140px;color:var(--text-secondary);"><?= htmlspecialchars($ev['venue']) ?></td>
          <td><span class="badge badge-upcoming" style="font-size:0.7rem;"><?= htmlspecialchars($ev['category']) ?></span></td>
          <td><?= $ev['capacity'] ?></td>
          <td>
            <span style="color:<?= $ev['reg_count']>=$ev['capacity']?'var(--clr-red)':'var(--clr-green)' ?>;font-weight:600;">
              <?= $ev['reg_count'] ?>
            </span>
          </td>
          <td><span class="badge badge-<?= $ev['status'] ?>"><?= htmlspecialchars($ev['status']) ?></span></td>
          <td>
            <div class="td-actions">
              <a href="event_edit.php?id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit">✏️</a>
              <a href="registrations.php?event_id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Registrations">👥</a>
              <form method="POST" action="event_delete.php" style="display:inline;margin:0;"
                    onsubmit="return confirm('Delete event &quot;<?= htmlspecialchars($ev['title'], ENT_QUOTES) ?>&quot;?\nThis will remove all registrations. This cannot be undone.');">
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm btn-icon" title="Delete" style="color:var(--clr-red);">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
        <tr>
          <td colspan="9">
            <div class="empty-state">
              <div class="empty-icon">📅</div>
              <div class="empty-title">No events found</div>
              <div class="empty-desc">Try different filters or <a href="event_add.php">add a new event</a>.</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delete modal removed — using inline form + browser confirm per row -->

<?php include '../includes/admin_layout_end.php'; ?>
