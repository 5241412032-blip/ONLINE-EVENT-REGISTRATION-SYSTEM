<?php
// admin/event_edit.php — Edit Existing Event
require_once '../includes/auth_check.php';
$user = requireRole('admin');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: events.php'); exit; }

$event = $db->prepare("SELECT * FROM events WHERE id=? LIMIT 1");
$event->execute([$id]);
$ev = $event->fetch();
if (!$ev) { setFlash('danger','Event not found.'); header('Location: events.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
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

    if (!$data['title'])      $errors[] = 'Event title is required.';
    if (!$data['event_date']) $errors[] = 'Event date is required.';
    if ($data['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';

    // Check if reducing capacity below current registrations
    $regCount = $db->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status='registered'");
    $regCount->execute([$id]);
    $regCount = (int)$regCount->fetchColumn();
    if ($data['capacity'] < $regCount) {
        $errors[] = "Capacity cannot be less than current registrations ($regCount).";
    }

    if (empty($errors)) {
        $upd = $db->prepare("
            UPDATE events SET title=?,description=?,event_date=?,event_time=?,venue=?,
            capacity=?,category=?,status=?,banner_color=? WHERE id=?
        ");
        $upd->execute([
            $data['title'],$data['description'],$data['event_date'],
            $data['event_time']?:null,$data['venue'],$data['capacity'],
            $data['category'],$data['status'],$data['banner_color'],$id
        ]);
        setFlash('success', "Event updated successfully!");
        header('Location: events.php');
        exit;
    }
    $ev = array_merge($ev, $data); // Keep edited values on error
}

$categories = ['Technology','Cultural','Sports','Workshop','Academic','Social','Other'];
$statuses   = ['upcoming','ongoing','completed','cancelled'];
$colors     = ['#6c63ff','#ff6b9d','#00d4ff','#00e676','#ffab40','#ff5252'];

$pageTitle  = 'Edit Event';
$activeMenu = 'events';
include '../includes/admin_layout.php';
?>

<div class="page-header">
  <div>
    <div class="breadcrumb">
      <a href="dashboard.php">Admin</a><span class="breadcrumb-sep">›</span>
      <a href="events.php">Events</a><span class="breadcrumb-sep">›</span> Edit
    </div>
    <h1 class="page-title">Edit Event</h1>
    <p class="page-subtitle">Update the details for "<?= htmlspecialchars($ev['title']) ?>"</p>
  </div>
  <a href="events.php" class="btn btn-outline">← Back to Events</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul style="padding-left:1.25rem;margin:0;">
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h4 style="color:var(--text-primary);">Event Details</h4></div>
  <form method="POST" action="event_edit.php?id=<?= $id ?>" class="card-body">

    <div class="form-group">
      <label class="form-label" for="title">Event Title *</label>
      <input type="text" id="title" name="title" class="form-control" required
        value="<?= htmlspecialchars($ev['title']) ?>">
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Description</label>
      <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="event_date">Event Date *</label>
        <input type="date" id="event_date" name="event_date" class="form-control" required
          value="<?= htmlspecialchars($ev['event_date']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="event_time">Event Time</label>
        <input type="time" id="event_time" name="event_time" class="form-control"
          value="<?= htmlspecialchars(substr($ev['event_time']??'',0,5)) ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="venue">Venue</label>
      <div class="input-wrapper">
        <span class="input-icon">📍</span>
        <input type="text" id="venue" name="venue" class="form-control"
          value="<?= htmlspecialchars($ev['venue'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="capacity">Capacity *</label>
        <div class="input-wrapper">
          <span class="input-icon">👥</span>
          <input type="number" id="capacity" name="capacity" class="form-control" min="1" required
            value="<?= htmlspecialchars($ev['capacity']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="category">Category</label>
        <select id="category" name="category" class="form-control">
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat ?>" <?= $ev['category']===$cat?'selected':'' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-control">
          <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $ev['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Banner Color</label>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.4rem;">
          <?php foreach ($colors as $clr): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="banner_color" value="<?= $clr ?>"
              <?= ($ev['banner_color']??'#6c63ff')===$clr?'checked':'' ?>
              style="display:none;" class="color-radio">
            <span style="display:inline-block;width:28px;height:28px;border-radius:50%;background:<?= $clr ?>;border:2px solid <?= ($ev['banner_color']??'#6c63ff')===$clr?'white':'transparent' ?>;cursor:pointer;transition:transform 0.2s;"></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:1rem;margin-top:0.5rem;">
      <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      <a href="events.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>

<?php
$inlineScript = <<<JS
  document.querySelectorAll('.color-radio').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('label span[style*="border-radius:50%"]').forEach(s => s.style.border='2px solid transparent');
      radio.nextElementSibling.style.border = '2px solid white';
    });
  });
JS;
include '../includes/admin_layout_end.php';
?>
