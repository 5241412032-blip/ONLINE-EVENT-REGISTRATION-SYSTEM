<?php
// index.php — Landing Page
require_once 'config/db.php';

$pageTitle  = 'Home';
$activePage = 'home';
$user       = currentUser();

// Redirect logged-in users to their dashboard
if ($user) {
    header('Location: ' . ($user['role']==='admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

// Fetch upcoming events for preview (limit 6)
try {
    $db = getDB();
    $events = $db->query("
        SELECT e.*, u.name AS creator_name,
               (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status='registered') AS reg_count
        FROM events e JOIN users u ON e.created_by=u.id
        WHERE e.status='upcoming' AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC LIMIT 6
    ")->fetchAll();

    $stats = $db->query("
        SELECT
          (SELECT COUNT(*) FROM events WHERE status='upcoming') AS total_events,
          (SELECT COUNT(*) FROM users WHERE role='student') AS total_students,
          (SELECT COUNT(*) FROM registrations WHERE status='registered') AS total_registrations
    ")->fetch();
} catch (Exception $e) {
    $events = [];
    $stats  = ['total_events'=>0,'total_students'=>0,'total_registrations'=>0];
}

$root = '';
require 'includes/header.php';
?>

<!-- ── Hero Section ──────────────────────────────────────── -->
<section class="hero">
  <div class="hero-bg">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-orb hero-orb-3"></div>
  </div>
  <div class="container">
    <div class="hero-content">
      <div class="hero-badge">🎯 &nbsp;University Event Platform</div>
      <h1 class="hero-title">
        Discover & Join<br>
        <span class="text-gradient">Amazing Events</span>
      </h1>
      <p class="hero-desc">
        Your all-in-one platform to explore, register, and manage university events.
        From tech symposiums to cultural fests — never miss what matters.
      </p>
      <div class="hero-actions">
        <a href="register.php" class="btn btn-primary btn-lg">Get Started Free →</a>
        <a href="login.php"    class="btn btn-outline btn-lg">Login</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="number" data-counter="<?= $stats['total_events'] ?>"><?= $stats['total_events'] ?></div>
          <div class="label">Live Events</div>
        </div>
        <div class="hero-stat">
          <div class="number" data-counter="<?= $stats['total_students'] ?>"><?= $stats['total_students'] ?></div>
          <div class="label">Students</div>
        </div>
        <div class="hero-stat">
          <div class="number" data-counter="<?= $stats['total_registrations'] ?>"><?= $stats['total_registrations'] ?></div>
          <div class="label">Registrations</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Features Section ───────────────────────────────────── -->
<section class="section" style="padding-top:3rem;">
  <div class="container">
    <div class="section-header">
      <div class="section-tag">✨ Features</div>
      <h2 class="section-title">Everything you need</h2>
      <p class="section-desc">Powerful tools for students and administrators to manage events seamlessly.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;">
      <?php
      $features = [
        ['🔐','Secure Auth',     'Role-based login for Admins and Students with encrypted passwords.'],
        ['📅','Event Management','Admins create, edit, and delete events with full control.'],
        ['✅','Easy Registration','Students browse and register for events in one click.'],
        ['📄','PDF Tickets',     'Download a beautifully styled PDF confirmation for every event.'],
        ['📊','Reports',         'Admins generate detailed reports on registrations and events.'],
        ['🔔','Real-time Updates','Live seat counts and instant status updates via AJAX.'],
      ];
      foreach ($features as [$icon,$title,$desc]): ?>
      <div class="card" style="padding:1.75rem;text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:1rem;"><?= $icon ?></div>
        <h4 style="margin-bottom:0.5rem;color:var(--text-primary);"><?= $title ?></h4>
        <p style="font-size:0.875rem;"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Upcoming Events Preview ────────────────────────────── -->
<?php if (!empty($events)): ?>
<section class="section" style="padding-top:2rem;">
  <div class="container">
    <div class="section-header">
      <div class="section-tag">📅 Upcoming</div>
      <h2 class="section-title">Events happening soon</h2>
      <p class="section-desc">Log in to register and secure your spot before seats fill up.</p>
    </div>

    <div class="events-grid">
      <?php foreach ($events as $ev):
        $seatsLeft  = max(0, $ev['capacity'] - $ev['reg_count']);
        $pct        = $ev['capacity'] > 0 ? round(($ev['reg_count'] / $ev['capacity']) * 100) : 0;
        $fillClass  = $pct >= 90 ? 'full' : ($pct >= 60 ? 'warn' : '');
        $color      = htmlspecialchars($ev['banner_color'] ?? '#6c63ff');
      ?>
      <div class="event-card" data-category="<?= htmlspecialchars($ev['category']) ?>">
        <div class="event-card-banner" style="background:<?= $color ?>;"></div>
        <div class="event-card-body">
          <span class="event-card-category">🏷 <?= htmlspecialchars($ev['category']) ?></span>
          <h3 class="event-card-title"><?= htmlspecialchars($ev['title']) ?></h3>
          <p class="event-card-desc"><?= htmlspecialchars(substr($ev['description'],0,110)) ?>...</p>
          <div class="event-card-meta">
            <span class="event-meta-item">📅 <?= date('d M Y', strtotime($ev['event_date'])) ?></span>
            <span class="event-meta-item">⏰ <?= $ev['event_time'] ? date('g:i A', strtotime($ev['event_time'])) : 'TBD' ?></span>
            <span class="event-meta-item">📍 <?= htmlspecialchars($ev['venue']) ?></span>
          </div>
          <div class="event-card-footer">
            <div class="capacity-bar">
              <div class="capacity-label"><?= $seatsLeft ?> / <?= $ev['capacity'] ?> seats left</div>
              <div class="capacity-track"><div class="capacity-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div></div>
            </div>
            <a href="login.php" class="btn btn-primary btn-sm">Register</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-4">
      <a href="register.php" class="btn btn-primary btn-lg">Join Now to See All Events</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── CTA Section ────────────────────────────────────────── -->
<section class="section" style="padding-bottom:5rem;">
  <div class="container">
    <div class="card" style="background:linear-gradient(135deg,rgba(108,99,255,0.2) 0%,rgba(0,212,255,0.1) 100%);border-color:rgba(108,99,255,0.3);padding:3rem;text-align:center;">
      <h2 style="margin-bottom:1rem;">Ready to get started?</h2>
      <p style="margin-bottom:2rem;max-width:500px;margin-left:auto;margin-right:auto;">
        Join thousands of students and administrators already using EventMS to discover and manage amazing events.
      </p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <a href="register.php?role=student" class="btn btn-primary btn-lg">Register as Student</a>
        <a href="register.php?role=admin"   class="btn btn-outline btn-lg">Admin Sign Up</a>
      </div>
    </div>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
