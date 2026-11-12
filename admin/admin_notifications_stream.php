<?php
// Admin only: Server-Sent Events (SSE) stream that pushes new library-activity notifications.
// Inputs: Last-Event-ID header or GET last_id (resume point).
// Tables: admin_notifications (read only).
// Runs up to 5 minutes, then ends; the browser's EventSource reconnects automatically.

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check and setup =====
requireAdmin();
ensureAdminNotificationsTable($pdo);


// ===== Stream setup =====
// Release the session lock before holding the SSE connection open.
session_write_close();

// Allow a long-running script; keep running until we notice the client left
set_time_limit(0);
ignore_user_abort(true);

// SSE headers: event-stream type, no caching, and no proxy buffering
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');

// Turn off output buffering so events are sent immediately
while (ob_get_level() > 0) {
    @ob_end_flush();
}


// ===== Resume point =====
// Browser reconnects send Last-Event-ID; the first connection may pass last_id
$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
if ($lastId <= 0 && isset($_GET['last_id'])) {
    $lastId = (int)$_GET['last_id'];
}

// On a brand-new dashboard tab, start from the current end so old notifications
// are not replayed. Browser reconnects send Last-Event-ID automatically.
if ($lastId <= 0) {
    $lastId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM admin_notifications')->fetchColumn();
}


// ===== Streaming loop =====
// Remember the start time so the loop can end after 5 minutes
$startedAt = time();

// Stop when the client disconnects
while (!connection_aborted()) {
    try {
        // Next batch (max 20) of notifications after the last one sent
        $stmt = $pdo->prepare(
            "SELECT id, event_type, username, book_id, book_title, message, created_at
             FROM admin_notifications
             WHERE id > ?
             ORDER BY id ASC
             LIMIT 20"
        );
        $stmt->execute([$lastId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send each row as an SSE message (id, event name, JSON data)
        foreach ($rows as $row) {
            $lastId = (int)$row['id'];
            echo 'id: ' . $lastId . "\n";
            echo "event: library_activity\n";
            echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }

        // Keep the connection alive even when there is no activity.
        // A line starting with ':' is an SSE comment; it is ignored by the client
        echo ': heartbeat ' . time() . "\n\n";

        // Push the output to the client now
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    } catch (Throwable $e) {
        // Log details server-side; tell the client with a generic error event, then stop
        error_log('WebShelf SSE error: ' . $e->getMessage());
        echo "event: error\n";
        echo 'data: ' . json_encode(['message' => 'Notification stream temporarily unavailable.']) . "\n\n";
        flush();
        break;
    }

    // Avoid keeping a stale Apache/PHP worker forever. EventSource will reconnect.
    if (time() - $startedAt >= 300) {
        break;
    }

    // Poll the database once per second
    sleep(1);
}
