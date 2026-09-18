<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon">🗑️</div>
        <h3 id="confirmTitle">Are you sure?</h3>
        <p id="confirmMessage">This action can't be undone.</p>
        <div class="confirm-actions">
            <button type="button" class="btn secondary" id="confirmCancelBtn">Cancel</button>
            <button type="button" class="btn danger" id="confirmOkBtn">Delete</button>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('confirmOverlay');
    var msgEl = document.getElementById('confirmMessage');
    var okBtn = document.getElementById('confirmOkBtn');
    var cancelBtn = document.getElementById('confirmCancelBtn');
    var pendingForm = null;

    function openModal(message, form) {
        msgEl.textContent = message;
        pendingForm = form;
        overlay.classList.add('open');
    }
    function closeModal() {
        overlay.classList.remove('open');
        pendingForm = null;
    }

    okBtn.addEventListener('click', function () {
        var form = pendingForm;
        closeModal();
        if (form) form.submit();
    });
    cancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    document.querySelectorAll('form.confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            openModal(form.dataset.message || 'Are you sure?', form);
        });
    });
})();
</script>
</body>
</html>
