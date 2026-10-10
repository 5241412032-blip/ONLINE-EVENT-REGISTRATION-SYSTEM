<?php
// includes/footer.php — Shared footer + scripts
?>
<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-inner">
      <span class="footer-brand">🎯 EventMS</span>
      <span class="footer-copy">© <?= date('Y') ?> Online Event Management System. All rights reserved.</span>
    </div>
  </div>
</footer>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script src="<?= $root ?? '' ?>assets/js/main.js"></script>
<?php if (!empty($extraScripts)): foreach($extraScripts as $s): ?>
  <script src="<?= htmlspecialchars($s) ?>"></script>
<?php endforeach; endif; ?>
<?php if (!empty($inlineScript)): ?>
  <script><?= $inlineScript ?></script>
<?php endif; ?>
</div><!-- .page-wrapper -->
</body>
</html>
