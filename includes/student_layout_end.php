<?php
// includes/student_layout_end.php
?>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
  (function() {
    const btn = document.getElementById('mobileSidebarBtn');
    if (btn && window.innerWidth <= 1024) btn.style.display = 'flex';
    window.addEventListener('resize', () => { if (btn) btn.style.display = window.innerWidth<=1024?'flex':'none'; });
  })();
</script>
<script src="../assets/js/main.js"></script>
<?php if (!empty($inlineScript)): ?><script><?= $inlineScript ?></script><?php endif; ?>
</body>
</html>
