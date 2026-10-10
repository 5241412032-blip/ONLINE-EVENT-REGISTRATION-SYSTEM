<?php
// admin/event_add.php — Create New Event
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

$errors  = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'event_date'  => $_POST['event_date'] ?? '',
        'event_time'  => $_POST['event_time'] ?? '',
        'venue'       => trim($_POST['venue'] ?? ''),
        'capacity'    => (int)($_POST['capacity'] ?? 0),
        'category'    => trim($_POST['category'] ?? 'General'),
        'status'      => $_POST['status'] ?? 'upcoming',
        'banner_color'=> $_POST['banner_color'] ?? '#6c63ff',
    ];

    if (!$formData['title'])      $errors[] = 'Event title is required.';
    if (!$formData['event_date']) $errors[] = 'Event date is required.';
    if ($formData['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO events (title,description,event_date,event_time,venue,capacity,category,status,banner_color,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $formData['title'], $formData['description'], $formData['event_date'],
            $formData['event_time'] ?: null, $formData['venue'], $formData['capacity'],
            $formData['category'], $formData['status'], $formData['banner_color'], $user['id']
        ]);
        setFlash('success', "Event '{$formData['title']}' created successfully!");
        header('Location: events.php');
        exit;
    }
}

$categories = ['Technology','Cultural','Sports','Workshop','Academic','Social','Other'];
$statuses   = ['upcoming','ongoing','completed','cancelled'];
$colors     = ['#6c63ff','#ff6b9d','#00d4ff','#00e676','#ffab40','#ff5252'];

$pageTitle  = 'Add Event';
$activeMenu = 'event_add';
include '../includes/admin_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb">
      <a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span>
      <a href="events.php">Events</a><span class="breadcrumb-sep">›</span> Add Event
    </div>
    <h1 class="page-title">Create New Event</h1>
    <p class="page-subtitle">Fill in the details to publish a new event.</p>
  </div>
  <a href="events.php" class="btn btn-outline">← Back to Events</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <div>
    <strong>Please fix the following errors:</strong>
    <ul style="margin-top:0.5rem;padding-left:1.25rem;">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;">
  <!-- Main Form -->
  <div class="card">
    <div class="card-header"><h4 style="color:var(--text-primary);">Event Details</h4></div>
    <form method="POST" action="event_add.php" id="addEventForm" class="card-body">
      <div class="form-group">
        <label class="form-label" for="title">Event Title *</label>
        <input type="text" id="title" name="title" class="form-control"
          placeholder="e.g. Tech Symposium 2026" required
          value="<?= htmlspecialchars($formData['title'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" class="form-control" rows="4"
          placeholder="Describe the event, its highlights, and what attendees can expect…"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="event_date">Event Date *</label>
          <input type="date" id="event_date" name="event_date" class="form-control" required
            value="<?= htmlspecialchars($formData['event_date'] ?? '') ?>"
            min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="event_time">Event Time</label>
          <input type="time" id="event_time" name="event_time" class="form-control"
            value="<?= htmlspecialchars($formData['event_time'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="venue">Venue</label>
        <div class="input-wrapper">
          <span class="input-icon">📍</span>
          <input type="text" id="venue" name="venue" class="form-control"
            placeholder="Main Auditorium, Block A"
            value="<?= htmlspecialchars($formData['venue'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="capacity">Capacity *</label>
          <div class="input-wrapper">
            <span class="input-icon">👥</span>
            <input type="number" id="capacity" name="capacity" class="form-control"
              placeholder="100" min="1" required
              value="<?= htmlspecialchars($formData['capacity'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="category">Category</label>
          <select id="category" name="category" class="form-control">
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= ($formData['category']??'Technology')===$cat?'selected':'' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="status">Status</label>
          <select id="status" name="status" class="form-control">
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= ($formData['status']??'upcoming')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Banner Color</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.4rem;">
            <?php foreach ($colors as $clr): ?>
            <label style="cursor:pointer;">
              <input type="radio" name="banner_color" value="<?= $clr ?>"
                <?= ($formData['banner_color']??'#6c63ff')===$clr?'checked':'' ?>
                style="display:none;" class="color-radio">
              <span class="color-swatch" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:<?= $clr ?>;border:2px solid <?= ($formData['banner_color']??'#6c63ff')===$clr?'white':'transparent' ?>;cursor:pointer;transition:transform 0.2s;"></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:1rem;margin-top:0.5rem;">
        <button type="submit" class="btn btn-primary">✅ Create Event</button>
        <a href="events.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Preview Card -->
  <div>
    <div class="card" style="position:sticky;top:2rem;">
      <div class="card-header"><h4 style="color:var(--text-primary);">📋 Preview</h4></div>
      <div class="card-body" style="padding:0;">
        <div class="event-card" style="border:none;border-radius:0;">
          <div class="event-card-banner" id="previewBanner" style="background:#6c63ff;"></div>
          <div class="event-card-body">
            <span class="event-card-category" id="previewCat">🏷 Technology</span>
            <h3 class="event-card-title" id="previewTitle">Event Title</h3>
            <p class="event-card-desc" id="previewDesc" style="font-size:0.8rem;">Event description will appear here…</p>
            <div class="event-card-meta">
              <span class="event-meta-item" id="previewDate">📅 Date</span>
              <span class="event-meta-item" id="previewTime">⏰ Time</span>
              <span class="event-meta-item" id="previewVenue">📍 Venue</span>
            </div>
            <div style="font-size:0.8rem;color:var(--text-muted);padding-top:0.75rem;border-top:1px solid var(--border);">
              👥 Capacity: <strong id="previewCap" style="color:var(--text-primary);">—</strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
  // Live preview
  function sync(id, targetId, transform) {
    const el = document.getElementById(id);
    const t  = document.getElementById(targetId);
    if (el && t) el.addEventListener('input', () => { t.textContent = transform ? transform(el.value) : el.value; });
  }
  sync('title', 'previewTitle');
  sync('description', 'previewDesc', v => v.substring(0,100) + (v.length>100?'…':''));
  sync('venue', 'previewVenue', v => '📍 ' + (v||'Venue'));
  sync('capacity', 'previewCap');
  document.getElementById('event_date')?.addEventListener('input', e => {
    const d = new Date(e.target.value);
    document.getElementById('previewDate').textContent = '📅 ' + (isNaN(d)?'Date':d.toLocaleDateString('en-IN',{day:'numeric',month:'long',year:'numeric'}));
  });
  document.getElementById('event_time')?.addEventListener('input', e => {
    const [h,m] = e.target.value.split(':');
    const d = new Date(0,0,0,h,m);
    document.getElementById('previewTime').textContent = '⏰ ' + d.toLocaleTimeString('en-IN',{hour:'numeric',minute:'2-digit'});
  });
  document.getElementById('category')?.addEventListener('change', e => {
    document.getElementById('previewCat').textContent = '🏷 ' + e.target.value;
  });
  // Color swatches
  document.querySelectorAll('.color-radio').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.color-swatch').forEach(s => s.style.border='2px solid transparent');
      radio.nextElementSibling.style.border = '2px solid white';
      document.getElementById('previewBanner').style.background = radio.value;
    });
  });
JS;
include '../includes/admin_layout_end.php';
?>
