/* ===========================================================================
   THE PIE TECHNOLOGIES — admin dashboard interactions
   =========================================================================== */
(function () {
  'use strict';

  /* --------------------------- sidebar (mobile) -------------------------- */
  var sidebarToggle = document.getElementById('sidebarToggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
  }

  /* ------------------------------ modals --------------------------------- */
  var modal = document.getElementById('adminModal');
  var modalBody = document.getElementById('modalBody');
  var modalClose = document.getElementById('modalClose');

  function openModal(sourceId) {
    var tpl = document.getElementById(sourceId);
    if (!modal || !modalBody || !tpl) return;
    modalBody.innerHTML = tpl.innerHTML;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    bindModalForms();
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  if (modalClose) modalClose.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-modal]');
    if (trigger) {
      e.preventDefault();
      openModal(trigger.getAttribute('data-modal'));
    }
  });

  /* modal inner forms submit normally; keep modal scroll reset */
  function bindModalForms() {
    var forms = modalBody ? modalBody.querySelectorAll('form[data-modal-form]') : [];
    forms.forEach(function (form) {
      form.addEventListener('submit', function () { closeModal(); });
    });
  }

  /* --------------------------- confirm actions --------------------------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!window.confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  /* ------------------------- bulk selection (tables) --------------------- */
  var selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('input.row-check').forEach(function (cb) {
        cb.checked = selectAll.checked;
      });
      updateBulkBar();
    });
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('row-check')) updateBulkBar();
    });
  }
  function updateBulkBar() {
    var bar = document.getElementById('bulkBar');
    if (!bar) return;
    var count = document.querySelectorAll('input.row-check:checked').length;
    bar.style.display = count > 0 ? 'flex' : 'none';
    var label = bar.querySelector('[data-bulk-count]');
    if (label) label.textContent = count + ' selected';
  }

  /* --------------------- auto-submit filter controls --------------------- */
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = el.closest('form');
      if (form) form.submit();
    });
  });

  /* ------------------------------ tabs ----------------------------------- */
  var tabs = document.querySelectorAll('.a-tab');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function (e) {
      e.preventDefault();
      var target = tab.getAttribute('data-tab');
      tabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      document.querySelectorAll('.a-tabpanel').forEach(function (panel) {
        panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
      });
      if (history.replaceState) history.replaceState(null, '', '#' + target);
    });
  });
  if (location.hash) {
    var hashTab = document.querySelector('.a-tab[data-tab="' + location.hash.slice(1) + '"]');
    if (hashTab) hashTab.click();
  }

  /* ------------------------ password show / hide ------------------------- */
  document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
    });
  });

  /* ---------------------- AJAX action buttons ---------------------------- */
  document.querySelectorAll('[data-ajax-action]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var box = document.getElementById(btn.getAttribute('data-result'));
      var payload = new FormData();
      payload.append('action', btn.getAttribute('data-ajax-action'));
      (btn.getAttribute('data-fields') || '').split(',').forEach(function (name) {
        name = name.trim();
        if (!name) return;
        var field = document.getElementById(name);
        if (field) payload.append(name, field.value);
      });
      var token = document.querySelector('input[name="csrf_token"]');
      if (token) payload.append('csrf_token', token.value);
      btn.disabled = true;
      fetch('actions.php', { method: 'POST', body: payload })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          btn.disabled = false;
          if (box) {
            box.className = 'inline-test show ' + (json.success ? 'ok' : 'err');
            box.textContent = json.message || (json.success ? 'OK' : 'Failed');
            if (json.reply) box.textContent += ' — ' + json.reply;
          }
        })
        .catch(function () {
          btn.disabled = false;
          if (box) {
            box.className = 'inline-test show err';
            box.textContent = 'Request failed — check the server connection.';
          }
        });
    });
  });

  /* --------------------- drag & drop reorder (team) ---------------------- */
  document.querySelectorAll('[data-sortable]').forEach(function (list) {
    if (!window.Sortable) return;
    new window.Sortable(list, {
      animation: 180,
      handle: '.grip',
      ghostClass: 'dragging',
      onEnd: function () {
        var ids = [];
        list.querySelectorAll('[data-id]').forEach(function (row) {
          ids.push(row.getAttribute('data-id'));
        });
        var payload = new FormData();
        payload.append('action', 'reorder');
        payload.append('entity', list.getAttribute('data-sortable'));
        payload.append('order', ids.join(','));
        var token = document.querySelector('input[name="csrf_token"]');
        if (token) payload.append('csrf_token', token.value);
        fetch('actions.php', { method: 'POST', body: payload }).then(function (r) { return r.json(); }).then(function (json) {
          if (json && json.success) {
            var note = document.getElementById('sortNote');
            if (note) { note.textContent = 'Order saved.'; setTimeout(function () { note.textContent = ''; }, 2500); }
          }
        });
      }
    });
  });

  /* ------------------------------ charts --------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    var canvases = document.querySelectorAll('canvas[data-chart]');
    if (!canvases.length || !window.Chart) return;
    Chart.defaults.color = '#9a9aa7';
    Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    canvases.forEach(function (canvas) {
      var cfg;
      try { cfg = JSON.parse(canvas.getAttribute('data-chart')); } catch (e) { return; }
      cfg.options = cfg.options || {};
      cfg.options.responsive = true;
      cfg.options.maintainAspectRatio = false;
      if (cfg.type === 'line') {
        (cfg.data.datasets || []).forEach(function (ds) {
          ds.borderColor = ds.borderColor || '#7c3aed';
          ds.backgroundColor = ds.backgroundColor || 'rgba(124,58,237,.18)';
          ds.fill = true;
          ds.tension = 0.4;
        });
      }
      if (cfg.type === 'bar') {
        (cfg.data.datasets || []).forEach(function (ds) {
          ds.backgroundColor = ds.backgroundColor || 'rgba(124,58,237,.65)';
          ds.borderRadius = 6;
        });
      }
      new Chart(canvas, cfg);
    });
  });

  /* ------------------------------ TinyMCE -------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    var editor = document.getElementById('postContent');
    if (editor && window.tinymce) {
      window.tinymce.init({
        selector: '#postContent',
        height: 480,
        menubar: false,
        skin: 'oxide-dark',
        content_css: 'dark',
        plugins: 'lists link image code table autolink',
        toolbar: 'undo redo | blocks | bold italic underline | link image table | bullist numlist | code',
        branding: false,
        promotion: false
      });
    }
  });
})();
