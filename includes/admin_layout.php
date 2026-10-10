<?php
// includes/admin_layout.php — Shared admin sidebar layout wrapper
// Usage: include at top of every admin page AFTER setting $pageTitle, $activeMenu, $user
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> | EventMS Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <?php if(!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="app-layout">

  <!-- Sidebar Overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🎯</div>
      <span class="sidebar-logo-text">EventMS</span>
    </div>
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
      <div class="sidebar-user-info">
        <div class="user-name"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></div>
        <div class="user-role"><span class="badge badge-admin">Admin</span></div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-section-title">Main</div>
      <a href="dashboard.php"     class="sidebar-link <?= $activeMenu==='dashboard'?'active':'' ?>">
        <span class="link-icon">📊</span> Dashboard
      </a>
      <a href="events.php"        class="sidebar-link <?= $activeMenu==='events'?'active':'' ?>">
        <span class="link-icon">📅</span> Events
      </a>
      <a href="event_add.php"     class="sidebar-link <?= $activeMenu==='event_add'?'active':'' ?>">
        <span class="link-icon">➕</span> Add Event
      </a>
      <a href="registrations.php" class="sidebar-link <?= $activeMenu==='registrations'?'active':'' ?>">
        <span class="link-icon">✅</span> Registrations
      </a>
      <a href="reports.php"       class="sidebar-link <?= $activeMenu==='reports'?'active':'' ?>">
        <span class="link-icon">📄</span> Reports
      </a>

      <div class="sidebar-section-title" style="margin-top:1rem;">System</div>
      <a href="../index.php" class="sidebar-link">
        <span class="link-icon">🏠</span> Public Site
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="../logout.php" class="sidebar-logout">
        <span>🚪</span> Logout
      </a>
    </div>
  </aside>

  <!-- Content -->
  <main class="content-area" id="contentArea">
    <!-- Mobile top bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
      <button class="btn btn-ghost btn-icon" data-sidebar-toggle id="mobileSidebarBtn" style="display:none;">☰</button>
    </div>

    <?php
    $flash = getFlash();
    if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['message']) ?>
      <button class="alert-close" onclick="this.parentElement.remove()">✕</button>
    </div>
    <?php endif; ?>
