// ============================================================
// assets/js/main.js — Global JavaScript
// Online Event Management System
// ============================================================

'use strict';

/**
 * APP_ROOT is set by each layout template before this script loads.
 * e.g. window.APP_ROOT = '../'  (from /student/ or /admin/ pages)
 *      window.APP_ROOT = ''     (from root-level pages)
 * Falls back to '' (root) if not set.
 */
const _apiBase = (typeof window.APP_ROOT !== 'undefined' ? window.APP_ROOT : '') + 'api/';

/* ── Toast Notifications ──────────────────────────────────── */
const Toast = (() => {
  let container = null;
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  function show(message, type = 'info', duration = 4000) {
    const c = getContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <span class="toast-msg">${message}</span>
    `;
    c.appendChild(toast);
    setTimeout(() => {
      toast.classList.add('removing');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  return { show, success: m => show(m,'success'), error: m => show(m,'error'), info: m => show(m,'info'), warning: m => show(m,'warning') };
})();

/* ── Modal ────────────────────────────────────────────────── */
const Modal = (() => {
  function open(id)  { document.getElementById(id)?.classList.add('open'); document.body.style.overflow='hidden'; }
  function close(id) { document.getElementById(id)?.classList.remove('open'); document.body.style.overflow=''; }
  function closeAll(){ document.querySelectorAll('.modal-overlay.open').forEach(m=>{ m.classList.remove('open'); document.body.style.overflow=''; }); }

  document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) closeAll();
    const closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) close(closeBtn.dataset.modalClose);
    const openBtn = e.target.closest('[data-modal-open]');
    if (openBtn) open(openBtn.dataset.modalOpen);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

  return { open, close, closeAll };
})();

/* ── Alert Auto-Close ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert').forEach(alert => {
    const closeBtn = alert.querySelector('.alert-close');
    if (closeBtn) closeBtn.addEventListener('click', () => alert.remove());
    // Auto-dismiss after 6 seconds
    setTimeout(() => { if (alert.isConnected) alert.style.opacity = '0'; setTimeout(() => alert.remove(), 300); }, 6000);
  });
});

/* ── Sidebar Toggle (Mobile) ──────────────────────────────── */
(function() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggles  = document.querySelectorAll('[data-sidebar-toggle]');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('active'); document.body.style.overflow = ''; }

  toggles.forEach(btn => btn.addEventListener('click', () => sidebar?.classList.contains('open') ? closeSidebar() : openSidebar()));
  overlay?.addEventListener('click', closeSidebar);
})();

/* ── Search / Filter ──────────────────────────────────────── */
function initSearch(inputId, targetSelector, fields) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    document.querySelectorAll(targetSelector).forEach(el => {
      const text = fields.map(f => (el.dataset[f] || '')).join(' ').toLowerCase();
      el.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
  });
}

/* ── Event Registration (AJAX) ────────────────────────────── */
function registerEvent(eventId, btn) {
  if (!eventId) return;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner"></span>';
  btn.disabled = true;

  fetch(_apiBase + 'register_event.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ event_id: eventId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      Toast.success(data.message || 'Successfully registered!');
      btn.innerHTML = '✅ Registered';
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-green');
      btn.onclick = null;
      // Update seat count if element exists
      const seatEl = document.querySelector(`[data-seat-count="${eventId}"]`);
      if (seatEl && data.seats_left !== undefined) seatEl.textContent = data.seats_left + ' seats left';
    } else {
      Toast.error(data.message || 'Registration failed.');
      btn.innerHTML = original;
      btn.disabled = false;
    }
  })
  .catch(() => {
    Toast.error('Network error. Please try again.');
    btn.innerHTML = original;
    btn.disabled = false;
  });
}

/* ── Event Cancellation (AJAX) ────────────────────────────── */
function cancelRegistration(regId, btn) {
  if (!confirm('Are you sure you want to cancel this registration?')) return;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner"></span>';
  btn.disabled = true;

  fetch(_apiBase + 'cancel_event.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ registration_id: regId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      Toast.success(data.message || 'Registration cancelled.');
      const row = btn.closest('tr') || btn.closest('.event-card');
      if (row) {
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
      }
    } else {
      Toast.error(data.message || 'Cancellation failed.');
      btn.innerHTML = original;
      btn.disabled = false;
    }
  })
  .catch(() => {
    Toast.error('Network error. Please try again.');
    btn.innerHTML = original;
    btn.disabled = false;
  });
}

/* ── Delete Event Confirmation ────────────────────────────── */
function confirmDeleteEvent(eventId, eventName) {
  const modal = document.getElementById('deleteModal');
  if (!modal) {
    // Fallback: direct confirmation without modal
    if (confirm(`Delete event "${eventName}"? This cannot be undone.`)) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'event_delete.php';
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'id'; inp.value = eventId;
      form.appendChild(inp);
      document.body.appendChild(form);
      form.submit();
    }
    return;
  }
  // Set BOTH the hidden input value AND the form action URL for reliability
  const hiddenId = document.getElementById('deleteEventId');
  if (hiddenId) hiddenId.value = eventId;
  const deleteForm = document.getElementById('deleteForm');
  if (deleteForm) deleteForm.action = 'event_delete.php?id=' + eventId;
  document.getElementById('deleteEventName').textContent = eventName;
  Modal.open('deleteModal');
}

/* ── Form Validation ──────────────────────────────────────── */
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return true;
  let valid = true;
  form.querySelectorAll('[required]').forEach(field => {
    const err = field.parentElement.querySelector('.form-error');
    if (!field.value.trim()) {
      field.style.borderColor = 'var(--clr-red)';
      if (err) err.textContent = 'This field is required.';
      valid = false;
    } else {
      field.style.borderColor = '';
      if (err) err.textContent = '';
    }
  });
  return valid;
}

/* ── Password Strength Meter ──────────────────────────────── */
function initPasswordStrength(inputId, meterId) {
  const input = document.getElementById(inputId);
  const meter = document.getElementById(meterId);
  if (!input || !meter) return;

  input.addEventListener('input', () => {
    const v = input.value;
    let strength = 0;
    if (v.length >= 8)         strength++;
    if (/[A-Z]/.test(v))       strength++;
    if (/[a-z]/.test(v))       strength++;
    if (/\d/.test(v))          strength++;
    if (/[@$!%*#?&]/.test(v)) strength++;
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const colors = ['', 'var(--clr-red)', 'var(--clr-orange)', 'var(--clr-orange)', 'var(--clr-green)', 'var(--clr-green)'];
    meter.textContent = labels[strength] || '';
    meter.style.color = colors[strength] || '';
  });
}

/* ── Charts (simple bar chart for reports) ────────────────── */
function drawBarChart(canvasId, labels, data, label = 'Registrations') {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !window.Chart) return;
  new window.Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label,
        data,
        backgroundColor: 'rgba(108,99,255,0.7)',
        borderColor: '#6c63ff',
        borderWidth: 2,
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { labels: { color: '#9aa0c2' } }
      },
      scales: {
        x: { ticks: { color: '#9aa0c2' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { ticks: { color: '#9aa0c2', precision: 0 }, grid: { color: 'rgba(255,255,255,0.05)' } }
      }
    }
  });
}

/* ── Animate Numbers (counter effect) ────────────────────── */
function animateNumber(el, target, duration = 1200) {
  let start = 0;
  const step = (timestamp) => {
    if (!start) start = timestamp;
    const progress = Math.min((timestamp - start) / duration, 1);
    el.textContent = Math.floor(progress * target);
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target;
  };
  requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseInt(el.dataset.counter, 10);
    if (!isNaN(target)) animateNumber(el, target);
  });

  // Role-tab switching on register/login page
  document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const role = tab.dataset.role;
      const roleInput = document.getElementById('roleInput');
      if (roleInput) roleInput.value = role;
      document.querySelectorAll('.role-fields').forEach(f => f.classList.toggle('d-none', f.dataset.forRole !== role));
    });
  });
});

/* ── Filter Events by Category ───────────────────────────── */
function filterByCategory(category) {
  document.querySelectorAll('.event-card[data-category]').forEach(card => {
    const show = !category || card.dataset.category === category;
    card.style.display = show ? '' : 'none';
  });
  document.querySelectorAll('.cat-filter-btn').forEach(btn => {
    btn.classList.toggle('btn-primary', btn.dataset.cat === (category || ''));
    btn.classList.toggle('btn-outline', btn.dataset.cat !== (category || ''));
  });
}

/* ── Utility: Format Date ─────────────────────────────────── */
function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-IN', { day:'numeric', month:'long', year:'numeric' });
}
