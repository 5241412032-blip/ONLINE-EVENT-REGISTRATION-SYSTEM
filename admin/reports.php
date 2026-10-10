<?php
// admin/reports.php — Generate Event Reports
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

$eventId    = (int)($_GET['event_id'] ?? 0);
$reportType = $_GET['type'] ?? 'all';

// Log this report generation
if ($_GET['generate'] ?? false) {
    $logStmt = $db->prepare("INSERT INTO reports (event_id,generated_by,report_type) VALUES (?,?,?)");
    $logStmt->execute([$eventId ?: null, $user['id'], $eventId ? 'event_summary' : 'all_events']);
}

// Fetch events for dropdown
$allEvents = $db->query("SELECT id,title,event_date,status FROM events ORDER BY event_date DESC")->fetchAll();

// Aggregate stats for all-events report
$allStats = $db->query("
    SELECT
        e.id, e.title, e.event_date, e.venue, e.category, e.status, e.capacity,
        COUNT(CASE WHEN r.status='registered' THEN 1 END)  AS registered_count,
        COUNT(CASE WHEN r.status='cancelled'  THEN 1 END)  AS cancelled_count,
        ROUND(COUNT(CASE WHEN r.status='registered' THEN 1 END)*100.0/NULLIF(e.capacity,0),1) AS fill_pct
    FROM events e
    LEFT JOIN registrations r ON r.event_id=e.id
    GROUP BY e.id
    ORDER BY e.event_date DESC
")->fetchAll();

// Single event detail
$currentEvent   = null;
$eventStudents  = [];
if ($eventId) {
    $s = $db->prepare("SELECT * FROM events WHERE id=?"); $s->execute([$eventId]); $currentEvent = $s->fetch();
    $es = $db->prepare("
        SELECT r.*, u.name AS student_name, u.email, u.department, u.year, u.phone
        FROM registrations r JOIN users u ON r.user_id=u.id
        WHERE r.event_id=? ORDER BY r.registered_at ASC
    ");
    $es->execute([$eventId]);
    $eventStudents = $es->fetchAll();
}

// Report history
$reportHistory = $db->query("
    SELECT rp.*, u.name AS admin_name,
           e.title AS event_title
    FROM reports rp
    JOIN users u ON rp.generated_by=u.id
    LEFT JOIN events e ON rp.event_id=e.id
    ORDER BY rp.generated_at DESC LIMIT 10
")->fetchAll();

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
$pageTitle  = 'Reports';
$activeMenu = 'reports';
include '../includes/admin_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb"><a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span> Reports</div>
    <h1 class="page-title">📄 Reports</h1>
    <p class="page-subtitle">Generate, view, and download event reports.</p>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="report_pdf.php" target="_blank" class="btn btn-primary">⬇️ Download All Events PDF</a>
    <?php if ($currentEvent): ?>
    <a href="report_pdf.php?event_id=<?= $eventId ?>" target="_blank" class="btn btn-green">⬇️ Download Event PDF</a>
    <?php endif; ?>
    <a href="reports.php?generate=1&type=all" class="btn btn-outline btn-sm">📊 Log Report</a>
  </div>
</div>

<!-- Filter -->
<div class="filter-bar" style="margin-bottom:1.5rem;">
  <form method="GET" action="reports.php" style="display:flex;gap:0.75rem;flex-wrap:wrap;width:100%;">
    <select name="event_id" class="filter-select" style="flex:1;min-width:200px;" onchange="this.form.submit()">
      <option value="">📊 All Events Overview</option>
      <?php foreach ($allEvents as $ev): ?>
      <option value="<?= $ev['id'] ?>" <?= $eventId===$ev['id']?'selected':'' ?>>
        <?= htmlspecialchars($ev['title']) ?> (<?= date('M Y',strtotime($ev['event_date'])) ?>)
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">View Report</button>
  </form>
</div>

<?php if ($currentEvent): ?>
<!-- ── Single Event Report ───────────────────────────────── -->
<div class="card mb-4">
  <div class="card-header">
    <h4 style="color:var(--text-primary);">📋 Event Report: <?= htmlspecialchars($currentEvent['title']) ?></h4>
    <div style="display:flex;gap:0.5rem;">
      <a href="report_pdf.php?event_id=<?= $eventId ?>" target="_blank" class="btn btn-primary btn-sm">⬇️ Download PDF</a>
      <a href="report_pdf.php?event_id=<?= $eventId ?>&print=1" target="_blank" class="btn btn-outline btn-sm">🖨 Print</a>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
      <?php
      $regCount = count(array_filter($eventStudents, fn($r)=>$r['status']==='registered'));
      $canCount = count(array_filter($eventStudents, fn($r)=>$r['status']==='cancelled'));
      $fillPct  = $currentEvent['capacity']>0 ? round($regCount/$currentEvent['capacity']*100) : 0;
      ?>
      <div style="background:rgba(108,99,255,0.1);border:1px solid rgba(108,99,255,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--clr-purple);"><?= $regCount ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);">Registered</div>
      </div>
      <div style="background:rgba(255,82,82,0.1);border:1px solid rgba(255,82,82,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--clr-red);"><?= $canCount ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);">Cancelled</div>
      </div>
      <div style="background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--clr-green);"><?= $currentEvent['capacity'] ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);">Total Capacity</div>
      </div>
      <div style="background:rgba(255,171,64,0.1);border:1px solid rgba(255,171,64,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--clr-orange);"><?= $fillPct ?>%</div>
        <div style="font-size:0.78rem;color:var(--text-muted);">Fill Rate</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;font-size:0.875rem;">
      <div><span class="text-muted">📅 Date:</span> <strong><?= date('d M Y', strtotime($currentEvent['event_date'])) ?></strong></div>
      <div><span class="text-muted">⏰ Time:</span> <strong><?= $currentEvent['event_time'] ? date('g:i A', strtotime($currentEvent['event_time'])) : 'TBD' ?></strong></div>
      <div><span class="text-muted">📍 Venue:</span> <strong><?= htmlspecialchars($currentEvent['venue']) ?></strong></div>
      <div><span class="text-muted">🏷 Category:</span> <strong><?= htmlspecialchars($currentEvent['category']) ?></strong></div>
    </div>
  </div>
</div>

<!-- Registered Students -->
<div class="table-wrapper">
  <div class="card-header">
    <h4 style="color:var(--text-primary);">👥 Registered Students</h4>
    <span style="font-size:0.85rem;color:var(--text-muted);"><?= $regCount ?> students</span>
  </div>
  <div class="table-scroll">
    <table>
      <thead><tr><th>#</th><th>Student Name</th><th>Email</th><th>Department</th><th>Year</th><th>Phone</th><th>Registered At</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($eventStudents as $i => $st): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td class="td-bold"><?= htmlspecialchars($st['student_name']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($st['email']) ?></td>
          <td><?= htmlspecialchars($st['department'] ?? '—') ?></td>
          <td><?= $st['year'] ? 'Year ' . $st['year'] : '—' ?></td>
          <td><?= htmlspecialchars($st['phone'] ?? '—') ?></td>
          <td><?= date('d M Y, g:i A', strtotime($st['registered_at'])) ?></td>
          <td><span class="badge badge-<?= $st['status'] ?>"><?= $st['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($eventStudents)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">No registrations for this event.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ── All Events Overview ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="dash-grid">
  <div class="card">
    <div class="card-header"><h4 style="color:var(--text-primary);">📊 Registrations per Event</h4></div>
    <div class="card-body"><canvas id="allEventsChart" height="220"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h4 style="color:var(--text-primary);">🍕 Status Distribution</h4></div>
    <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
  </div>
</div>

<div class="table-wrapper mb-4">
  <div class="card-header">
    <h4 style="color:var(--text-primary);">📋 All Events Summary</h4>
  </div>
  <div class="table-scroll">
    <table>
      <thead><tr><th>#</th><th>Event</th><th>Date</th><th>Category</th><th>Capacity</th><th>Registered</th><th>Fill Rate</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($allStats as $i => $s): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td class="td-bold"><?= htmlspecialchars($s['title']) ?></td>
          <td><?= date('d M Y', strtotime($s['event_date'])) ?></td>
          <td><?= htmlspecialchars($s['category']) ?></td>
          <td><?= $s['capacity'] ?></td>
          <td><span style="color:var(--clr-green);font-weight:700;"><?= $s['registered_count'] ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <div style="flex:1;height:6px;background:rgba(255,255,255,0.1);border-radius:9999px;min-width:60px;">
                <div style="width:<?= min(100,$s['fill_pct']??0) ?>%;height:100%;background:var(--gradient-main);border-radius:9999px;"></div>
              </div>
              <span style="font-size:0.78rem;color:var(--text-muted);"><?= $s['fill_pct']??0 ?>%</span>
            </div>
          </td>
          <td><span class="badge badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
          <td>
            <div style="display:flex;gap:0.4rem;">
              <a href="reports.php?event_id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">View</a>
              <a href="report_pdf.php?event_id=<?= $s['id'] ?>" target="_blank" class="btn btn-primary btn-sm" title="Download PDF">⬇️ PDF</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Report History -->
<div class="table-wrapper">
  <div class="card-header"><h4 style="color:var(--text-primary);">🕒 Report Generation History</h4></div>
  <div class="table-scroll">
    <table>
      <thead><tr><th>#</th><th>Report Type</th><th>Event</th><th>Generated By</th><th>Generated At</th></tr></thead>
      <tbody>
        <?php foreach ($reportHistory as $i => $rh): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td><span class="badge badge-upcoming"><?= htmlspecialchars($rh['report_type']) ?></span></td>
          <td><?= $rh['event_title'] ? htmlspecialchars($rh['event_title']) : '<em style="color:var(--text-muted)">All Events</em>' ?></td>
          <td><?= htmlspecialchars($rh['admin_name']) ?></td>
          <td><?= date('d M Y, g:i A', strtotime($rh['generated_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($reportHistory)): ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:1.5rem;">No reports generated yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$chartLabels = json_encode(array_column($allStats, 'title'));
$chartCounts = json_encode(array_map(fn($s)=>(int)$s['registered_count'], $allStats));
$statusLabels = json_encode(['Upcoming','Ongoing','Completed','Cancelled']);
$statusData   = json_encode([
    count(array_filter($allStats, fn($s)=>$s['status']==='upcoming')),
    count(array_filter($allStats, fn($s)=>$s['status']==='ongoing')),
    count(array_filter($allStats, fn($s)=>$s['status']==='completed')),
    count(array_filter($allStats, fn($s)=>$s['status']==='cancelled')),
]);
$inlineScript = "
  document.addEventListener('DOMContentLoaded', () => {
    const chartEl = document.getElementById('allEventsChart');
    if (chartEl) drawBarChart('allEventsChart', {$chartLabels}, {$chartCounts}, 'Registrations');
    const statusEl = document.getElementById('statusChart');
    if (statusEl && window.Chart) {
      new Chart(statusEl.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: {$statusLabels},
          datasets: [{ data: {$statusData}, backgroundColor: ['#6c63ff','#00d4ff','#00e676','#ff5252'], borderWidth: 0 }]
        },
        options: { responsive:true, plugins: { legend: { labels: { color:'#9aa0c2' } } } }
      });
    }
  });
";
$extraScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'];
include '../includes/admin_layout_end.php';
?>
