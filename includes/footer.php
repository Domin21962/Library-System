<!-- Shared page footer, included at the bottom of every page. Access: include only, no inputs, no tables. -->
<!-- Delete-confirmation modal, opened by the script below for forms marked confirm-delete -->
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
<!-- Behaviour script: mobile menu, delete confirmation and admin live notifications -->
<script>
/* Everything runs inside one function so no variables leak into the page */
(function () {
    /* Mobile menu: add a hamburger button to every top bar and toggle its nav open and closed */
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
    /* Delete-confirmation modal elements and the form waiting for approval */
    var overlay = document.getElementById('confirmOverlay');
    var msgEl = document.getElementById('confirmMessage');
    var okBtn = document.getElementById('confirmOkBtn');
    var cancelBtn = document.getElementById('confirmCancelBtn');
    var pendingForm = null;

    /* Show the modal with a message and remember which form to submit */
    function openModal(message, form) {
        msgEl.textContent = message;
        pendingForm = form;
        overlay.classList.add('open');
    }
    /* Hide the modal and forget the pending form */
    function closeModal() {
        overlay.classList.remove('open');
        pendingForm = null;
    }

    /* OK submits the remembered form; Cancel or a click on the backdrop just closes the modal */
    okBtn.addEventListener('click', function () {
        var form = pendingForm;
        closeModal();
        if (form) form.submit();
    });
    cancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    /* Intercept submit on delete forms and ask for confirmation first */
    document.querySelectorAll('form.confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            openModal(form.dataset.message || 'Are you sure?', form);
        });
    });

    // Real-time admin borrow/return notifications.
    // Uses lightweight 1-second polling because it is reliable with Laragon/Apache
    // even when PHP output buffering prevents SSE from flushing immediately.
    /* The notification bell below is only sent to logged-in Admins */
    <?php if (!empty($_SESSION['username']) && (($_SESSION['role'] ?? '') === 'Admin')): ?>
    (function () {
        /* Admin notifications: state kept by this block (items shown, unread count, last seen id, polling timer) */
        var adminBars = document.querySelectorAll('.admin-topbar');
        if (!adminBars.length) return;

        var notifyItems = [];
        var unread = 0;
        var maxItems = 12;
        var lastId = 0;
        var pollTimer = null;
        var stopped = false;

        /* Escape text before putting it into innerHTML */
        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /* Turn a timestamp into text like 5m ago */
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

        /* Fill the notification panel with the latest items */
        function renderItems(panel) {
            var list = panel.querySelector('.admin-notify-list');
            if (!notifyItems.length) {
                list.innerHTML = '<div class="admin-notify-empty">No new activity yet.</div>';
                return;
            }
            list.innerHTML = notifyItems.map(function (item) {
                var action = item.event_type === 'return' ? '↩ Book returned' : '📚 Book borrowed';
                return '<div class="admin-notify-item ' + esc(item.event_type) + '">' +
                    '<div class="notify-title">' + action + '</div>' +
                    '<div class="notify-meta">' + esc(item.username) + ' · ' + esc(item.book_title) + '<br>' + esc(relativeTime(item.created_at)) + '</div>' +
                    '</div>';
            }).join('');
        }

        /* Update the status line in every admin notification panel */
        function setStatus(text) {
            document.querySelectorAll('.admin-notify-status').forEach(function (el) { el.textContent = text; });
        }

        /* Pop up a short-lived toast for a new event */
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

        /* Show a desktop notification when the browser has permission */
        function maybeSystemNotification(item) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            try {
                new Notification(item.event_type === 'return' ? 'WebShelf — Book Returned' : 'WebShelf — Book Borrowed', {
                    body: item.username + ' · ' + item.book_title,
                    tag: 'doms-library-' + item.id
                });
            } catch (e) {}
        }

        /* Handle one new event: remember it, bump the unread badge, refresh panels, show the toast */
        function receive(item) {
            var id = Number(item.id || 0);
            if (id > lastId) lastId = id;
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

        /* Build the bell button and dropdown panel inside each admin top bar */
        adminBars.forEach(function (bar) {
            if (bar.querySelector('.admin-notify-wrap')) return;
            var wrap = document.createElement('div');
            wrap.className = 'admin-notify-wrap';
            wrap.innerHTML =
                '<button type="button" class="admin-notify-btn" aria-label="Open activity notifications" aria-expanded="false">🔔<span class="admin-notify-count">0</span></button>' +
                '<div class="admin-notify-panel" role="status">' +
                    '<div class="admin-notify-head">' +
                        '<div><strong>Library Activity</strong><div class="admin-notify-status">Connecting…</div></div>' +
                        '<button type="button" class="admin-notify-enable">Enable desktop notifications</button>' +
                    '</div>' +
                    '<div class="admin-notify-list"><div class="admin-notify-empty">No new activity yet.</div></div>' +
                '</div>';
            var nav = bar.querySelector('nav');
            bar.insertBefore(wrap, nav || null);

            var btn = wrap.querySelector('.admin-notify-btn');
            var panel = wrap.querySelector('.admin-notify-panel');
            var count = wrap.querySelector('.admin-notify-count');
            var enable = wrap.querySelector('.admin-notify-enable');

            /* Bell click: toggle the panel and clear the unread count when opened */
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

            /* Ask the browser for desktop notification permission */
            enable.addEventListener('click', function () {
                if (!('Notification' in window)) {
                    enable.textContent = 'Browser notifications unavailable';
                    return;
                }
                Notification.requestPermission().then(function (permission) {
                    enable.textContent = permission === 'granted' ? 'Desktop notifications enabled' : 'Notifications blocked';
                }).catch(function () {
                    enable.textContent = 'Notifications blocked';
                });
            });

            /* Click outside the panel closes it */
            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) {
                    panel.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });

        /* Ask the server for events newer than lastId, then schedule the next check one second later */
        function poll() {
            if (stopped) return;
            var url = 'admin_notifications_poll.php?since_id=' + encodeURIComponent(lastId) + '&_=' + Date.now();
            fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) throw new Error('Invalid notification response');
                    setStatus('Live borrow/return updates');
                    (data.items || []).forEach(receive);
                })
                .catch(function (error) {
                    setStatus('Reconnecting… ' + (error && error.message ? error.message : 'request failed'));
                })
                .finally(function () {
                    if (!stopped) pollTimer = setTimeout(poll, 1000);
                });
        }

        // Establish a starting point so old notifications don't appear as new.
        /* Fetch the newest existing id first, then start polling */
        fetch('admin_notifications_poll.php?latest=1&_=' + Date.now(), {
            cache: 'no-store', credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            if (!data || !data.ok) throw new Error('Invalid notification response');
            lastId = Number(data.latest_id || 0);
            setStatus('Live borrow/return updates');
            poll();
        })
        .catch(function () {
            setStatus('Notification service unavailable');
            poll();
        });

        /* Stop polling when leaving the page */
        window.addEventListener('beforeunload', function () {
            stopped = true;
            if (pollTimer) clearTimeout(pollTimer);
        });
    })();
    /* End of admin-only block */
    <?php endif; ?>
})();
</script>
<!-- End of footer script -->
</body>
</html>
