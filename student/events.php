<?php
// student/events.php — Browse & Register Events
require_once '../includes/auth_check.php';
$user = requireRole('student');
$db   = getDB();

$search   = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? 'upcoming';

$where  = ['e.event_date >= CURDATE()'];
$params = [];
if ($search)   { $where[] = "(e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category) { $where[] = "e.category=?"; $params[] = $category; }
if ($status)   { $where[] = "e.status=?";   $params[] = $status; } else { $where[] = "e.status='upcoming'"; }

$sql = "
    SELECT e.*,
           (SELECT COUNT(*) FROM registrations rr WHERE rr.event_id=e.id AND rr.status='registered') AS reg_count,
           (SELECT id FROM registrations WHERE user_id=? AND event_id=e.id AND status='registered' LIMIT 1) AS my_reg_id
    FROM events e
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.event_date ASC
";
array_unshift($params, $user['id']);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$categories = $db->query("SELECT DISTINCT category FROM events ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Browse Events';
$activeMenu = 'events';
include '../includes/student_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb">Student Portal</div>
    <h1 class="page-title">Browse Events</h1>
    <p class="page-subtitle"><?= count($events) ?> event(s) available</p>
  </div>
</div>

<!-- Category Filter Pills -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;">
  <button onclick="filterByCategory('')" class="btn btn-primary btn-sm cat-filter-btn" data-cat="">All</button>
  <?php foreach ($categories as $cat): ?>
  <button onclick="filterByCategory('<?= htmlspecialchars($cat) ?>')"
    class="btn btn-outline btn-sm cat-filter-btn" data-cat="<?= htmlspecialchars($cat) ?>">
    <?= htmlspecialchars($cat) ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- Search Bar -->
<form method="GET" action="events.php" class="filter-bar">
  <div class="search-box" style="flex:1;">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" placeholder="Search events by name, venue, or description…"
      value="<?= htmlspecialchars($search) ?>">
  </div>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($search): ?><a href="events.php" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif; ?>
</form>

<!-- Events Grid -->
<?php if (empty($events)): ?>
<div class="empty-state">
  <div class="empty-icon">📅</div>
  <div class="empty-title">No events found</div>
  <div class="empty-desc">Try a different search or check back later for new events.</div>
</div>
<?php else: ?>
<div class="events-grid" id="eventsGrid">
  <?php foreach ($events as $ev):
    $seatsLeft = max(0,$ev['capacity']-$ev['reg_count']);
    $pct       = $ev['capacity']>0 ? round($ev['reg_count']/$ev['capacity']*100) : 0;
    $fillClass = $pct>=90?'full':($pct>=60?'warn':'');
    $full      = $seatsLeft === 0;
    $isReg     = !empty($ev['my_reg_id']);
    $color     = htmlspecialchars($ev['banner_color']??'#6c63ff');
  ?>
  <div class="event-card" data-category="<?= htmlspecialchars($ev['category']) ?>"
       data-title="<?= htmlspecialchars($ev['title']) ?>" data-venue="<?= htmlspecialchars($ev['venue']) ?>">
    <div class="event-card-banner" style="background:<?= $color ?>;"></div>
    <div class="event-card-body">
      <span class="event-card-category">🏷 <?= htmlspecialchars($ev['category']) ?></span>
      <h3 class="event-card-title"><?= htmlspecialchars($ev['title']) ?></h3>
      <p class="event-card-desc"><?= htmlspecialchars(substr($ev['description']??'',0,120)) ?><?= strlen($ev['description']??'')>120?'…':'' ?></p>

      <div class="event-card-meta">
        <span class="event-meta-item">📅 <?= date('d M Y', strtotime($ev['event_date'])) ?></span>
        <span class="event-meta-item">⏰ <?= $ev['event_time'] ? date('g:i A', strtotime($ev['event_time'])) : 'TBD' ?></span>
        <span class="event-meta-item">📍 <?= htmlspecialchars($ev['venue']) ?></span>
        <span class="event-meta-item">👥 <?= $ev['capacity'] ?> seats</span>
      </div>

      <div class="event-card-footer">
        <div class="capacity-bar">
          <div class="capacity-label" data-seat-count="<?= $ev['id'] ?>">
            <?= $seatsLeft ?> / <?= $ev['capacity'] ?> seats left
          </div>
          <div class="capacity-track">
            <div class="capacity-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>

        <?php if ($isReg): ?>
          <!-- Already registered -->
          <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
            <span class="badge badge-registered badge-dot">Registered</span>
            <a href="download_pdf.php?event_id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">📄 PDF</a>
          </div>
        <?php elseif ($full): ?>
          <span class="badge badge-cancelled">Full</span>
        <?php else: ?>
          <button onclick="registerEvent(<?= $ev['id'] ?>, this)"
            class="btn btn-primary btn-sm" id="reg-btn-<?= $ev['id'] ?>">
            ✅ Register
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/student_layout_end.php'; ?>
