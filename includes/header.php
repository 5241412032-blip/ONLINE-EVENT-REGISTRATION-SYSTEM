<?php
// includes/header.php — Shared HTML head + navbar (public pages)
// Usage: $pageTitle = 'My Page'; require 'includes/header.php';
$pageTitle  = $pageTitle ?? 'EventMS';
$user       = currentUser();
$activePage = $activePage ?? '';

// Determine relative path to root
$depth = substr_count(str_replace('\\','/',$_SERVER['PHP_SELF']), '/') - 1;
$root  = str_repeat('../', $depth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | EventMS</title>
  <meta name="description" content="Online Event Management System – discover, register, and manage university events effortlessly.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $root ?>assets/css/style.css">
</head>
<body>
<div class="page-wrapper">

<!-- Navbar -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="<?= $root ?>index.php" class="navbar-brand">
      <span class="brand-icon">🎯</span>
      EventMS
    </a>

    <ul class="navbar-nav" id="navMenu">
      <li><a href="<?= $root ?>index.php"          class="nav-link <?= $activePage==='home'?'active':'' ?>">Home</a></li>
      <?php if ($user && $user['role']==='student'): ?>
        <li><a href="<?= $root ?>student/events.php"           class="nav-link <?= $activePage==='events'?'active':'' ?>">Events</a></li>
        <li><a href="<?= $root ?>student/my_registrations.php" class="nav-link <?= $activePage==='myreg'?'active':'' ?>">My Registrations</a></li>
      <?php elseif ($user && $user['role']==='admin'): ?>
        <li><a href="<?= $root ?>admin/dashboard.php"     class="nav-link <?= $activePage==='dashboard'?'active':'' ?>">Dashboard</a></li>
        <li><a href="<?= $root ?>admin/events.php"        class="nav-link <?= $activePage==='events'?'active':'' ?>">Events</a></li>
        <li><a href="<?= $root ?>admin/reports.php"       class="nav-link <?= $activePage==='reports'?'active':'' ?>">Reports</a></li>
      <?php endif; ?>
    </ul>

    <div class="nav-user">
      <?php if ($user): ?>
        <div class="nav-avatar" title="<?= htmlspecialchars($user['name']) ?>">
          <?= strtoupper(substr($user['name'],0,1)) ?>
        </div>
        <span class="nav-link fs-sm fw-600 d-none d-sm-flex"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></span>
        <a href="<?= $root ?>logout.php" class="btn btn-outline btn-sm">Logout</a>
      <?php else: ?>
        <a href="<?= $root ?>login.php"    class="btn btn-ghost btn-sm">Login</a>
        <a href="<?= $root ?>register.php" class="btn btn-primary btn-sm">Register</a>
      <?php endif; ?>
      <button class="nav-toggle" data-sidebar-toggle aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>
<!-- End Navbar -->
