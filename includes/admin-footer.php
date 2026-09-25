    </main>
</div>

<div class="admin-modal" id="adminModal" aria-hidden="true">
    <div class="admin-modal-card" role="dialog" aria-modal="true" aria-label="Details">
        <button class="a-icon-btn modal-close" id="modalClose" aria-label="Close"><?= icon('close', 18) ?></button>
        <div class="admin-modal-body" id="modalBody"></div>
    </div>
</div>

<script>
window.PIE = window.PIE || {};
window.PIE.admin = true;
</script>
<script src="<?= asset('assets/js/admin.js') ?>" defer></script>
</body>
</html>
