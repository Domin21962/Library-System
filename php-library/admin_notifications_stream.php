<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireAdmin();
ensureAdminNotificationsTable($pdo);

// Release the session lock before holding the SSE connection open.
session_write_close();

set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    @ob_end_flush();
}

$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
if ($lastId <= 0 && isset($_GET['last_id'])) {
    $lastId = (int)$_GET['last_id'];
}

// On a brand-new dashboard tab, start from the current end so old notifications
// are not replayed. Browser reconnects send Last-Event-ID automatically.
if ($lastId <= 0) {
    $lastId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM admin_notifications')->fetchColumn();
}

$startedAt = time();

while (!connection_aborted()) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, event_type, username, book_id, book_title, message, created_at
             FROM admin_notifications
             WHERE id > ?
             ORDER BY id ASC
             LIMIT 20"
        );
        $stmt->execute([$lastId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $lastId = (int)$row['id'];
            echo 'id: ' . $lastId . "\n";
            echo "event: library_activity\n";
            echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }

        // Keep the connection alive even when there is no activity.
        echo ': heartbeat ' . time() . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    } catch (Throwable $e) {
        error_log('Doms Library SSE error: ' . $e->getMessage());
        echo "event: error\n";
        echo 'data: ' . json_encode(['message' => 'Notification stream temporarily unavailable.']) . "\n\n";
        flush();
        break;
    }

    // Avoid keeping a stale Apache/PHP worker forever. EventSource will reconnect.
    if (time() - $startedAt >= 300) {
        break;
    }

    sleep(1);
}
