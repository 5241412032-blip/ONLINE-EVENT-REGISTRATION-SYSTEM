<?php
// includes/admin_layout_end.php — Closes the admin layout
?>
  </main><!-- .content-area -->
</div><!-- .app-layout -->

<div class="toast-container" id="toastContainer"></div>

<script>
  // Show mobile sidebar button on small screens
  (function() {
    const btn = document.getElementById('mobileSidebarBtn');
    if (btn && window.innerWidth <= 1024) btn.style.display = 'flex';
    window.addEventListener('resize', () => {
      if (btn) btn.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
    });
  })();
</script>
<script src="../assets/js/main.js"></script>
<?php if (!empty($extraScripts)) foreach($extraScripts as $s): ?>
  <script src="<?= htmlspecialchars($s) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($inlineScript)): ?>
  <script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
