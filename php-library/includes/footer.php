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
    document.querySelectorAll('.topbar').forEach(function (bar) {
        var nav = bar.querySelector('nav');
        if (!nav || bar.querySelector('.menu-toggle')) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'menu-toggle';
        button.setAttribute('aria-label', 'Open navigation menu');
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = '&#9776;';
        bar.insertBefore(button, nav);
        button.addEventListener('click', function () {
            var open = bar.classList.toggle('nav-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            button.innerHTML = open ? '&times;' : '&#9776;';
        });
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                bar.classList.remove('nav-open');
                button.setAttribute('aria-expanded', 'false');
                button.innerHTML = '&#9776;';
            }
        });
    });
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

    // Real-time admin borrow/return notifications using Server-Sent Events (SSE).
    // This runs only for logged-in admin pages and reconnects automatically.
    <?php if (!empty($_SESSION['username']) && (($_SESSION['role'] ?? '') === 'Admin')): ?>
    (function () {
        var adminBars = document.querySelectorAll('.admin-topbar');
        if (!adminBars.length || typeof EventSource === 'undefined') return;

        var notifyItems = [];
        var unread = 0;
        var maxItems = 12;

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function relativeTime(dateString) {
            var d = new Date(String(dateString).replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateString;
            var seconds = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
            if (seconds < 10) return 'just now';
            if (seconds < 60) return seconds + 's ago';
            var minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + 'm ago';
            var hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + 'h ago';
            return d.toLocaleString();
        }

        function renderItems(panel) {
            var list = panel.querySelector('.admin-notify-list');
            if (!notifyItems.length) {
                list.innerHTML = '<div class=\"admin-notify-empty\">No new activity yet.</div>';
                return;
            }
            list.innerHTML = notifyItems.map(function (item) {
                var action = item.event_type === 'return' ? '↩ Book returned' : '📚 Book borrowed';
                return '<div class=\"admin-notify-item ' + esc(item.event_type) + '\">' +
                    '<div class=\"notify-title\">' + action + '</div>' +
                    '<div class=\"notify-meta\">' + esc(item.username) + ' · ' + esc(item.book_title) + '<br>' + esc(relativeTime(item.created_at)) + '</div>' +
                    '</div>';
            }).join('');
        }

        function showToast(item) {
            var old = document.querySelector('.admin-activity-toast');
            if (old) old.remove();
            var toast = document.createElement('div');
            toast.className = 'admin-activity-toast';
            toast.innerHTML = '<strong>' + (item.event_type === 'return' ? '↩ Book returned' : '📚 Book borrowed') + '</strong>' +
                '<span>' + esc(item.username) + ' · ' + esc(item.book_title) + '</span>';
            document.body.appendChild(toast);
            setTimeout(function () { if (toast.parentNode) toast.remove(); }, 6000);
        }

        function maybeSystemNotification(item) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            try {
                new Notification(item.event_type === 'return' ? 'Doms Library — Book Returned' : 'Doms Library — Book Borrowed', {
                    body: item.username + ' · ' + item.book_title
                });
            } catch (e) {}
        }

        adminBars.forEach(function (bar) {
            if (bar.querySelector('.admin-notify-wrap')) return;
            var wrap = document.createElement('div');
            wrap.className = 'admin-notify-wrap';
            wrap.innerHTML =
                '<button type=\"button\" class=\"admin-notify-btn\" aria-label=\"Open activity notifications\" aria-expanded=\"false\">🔔<span class=\"admin-notify-count\">0</span></button>' +
                '<div class=\"admin-notify-panel\" role=\"status\">' +
                    '<div class=\"admin-notify-head\">' +
                        '<div><strong>Library Activity</strong><div class=\"admin-notify-status\">Live borrow/return updates</div></div>' +
                        '<button type=\"button\" class=\"admin-notify-enable\">Enable desktop notifications</button>' +
                    '</div>' +
                    '<div class=\"admin-notify-list\"><div class=\"admin-notify-empty\">No new activity yet.</div></div>' +
                '</div>';
            var nav = bar.querySelector('nav');
            bar.insertBefore(wrap, nav || null);

            var btn = wrap.querySelector('.admin-notify-btn');
            var panel = wrap.querySelector('.admin-notify-panel');
            var count = wrap.querySelector('.admin-notify-count');
            var enable = wrap.querySelector('.admin-notify-enable');

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = panel.classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    unread = 0;
                    count.classList.remove('show');
                    count.textContent = '0';
                    renderItems(panel);
                }
            });

            enable.addEventListener('click', function () {
                if (!('Notification' in window)) {
                    enable.textContent = 'Browser notifications unavailable';
                    return;
                }
                Notification.requestPermission().then(function (permission) {
                    enable.textContent = permission === 'granted' ? 'Desktop notifications enabled' : 'Notifications blocked';
                });
            });

            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) {
                    panel.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });

        function receive(item) {
            notifyItems.unshift(item);
            notifyItems = notifyItems.slice(0, maxItems);
            unread++;
            document.querySelectorAll('.admin-notify-wrap').forEach(function (wrap) {
                var count = wrap.querySelector('.admin-notify-count');
                var panel = wrap.querySelector('.admin-notify-panel');
                count.textContent = unread > 99 ? '99+' : String(unread);
                count.classList.add('show');
                renderItems(panel);
            });
            showToast(item);
            maybeSystemNotification(item);
        }

        var source = new EventSource('admin_notifications_stream.php');
        source.addEventListener('library_activity', function (event) {
            try { receive(JSON.parse(event.data)); } catch (e) {}
        });
        source.onerror = function () {
            document.querySelectorAll('.admin-notify-status').forEach(function (el) {
                el.textContent = 'Reconnecting to live updates…';
            });
        };
        source.onopen = function () {
            document.querySelectorAll('.admin-notify-status').forEach(function (el) {
                el.textContent = 'Live borrow/return updates';
            });
        };
    })();
    <?php endif; ?>
})();
</script>
</body>
</html>
