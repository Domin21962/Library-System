<?php
// Admin only: JSON polling endpoint for new library-activity notifications.
// Inputs: GET since_id (return rows newer than this id), latest=1 (only report the newest id).
// Tables: admin_notifications (read only).
// Returns JSON; never redirects, because fetch() must receive JSON.

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Response headers =====
// JSON output, and no caching so each poll gets fresh data
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');


// ===== Access check =====
// Not requireAdmin(): that redirects, and a JSON client needs a 401 instead
if (empty($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'Admin')) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Admin session required.']);
    exit;
}


// ===== Poll logic =====
try {
    // Create the table if missing; fail if that is impossible
    if (!ensureAdminNotificationsTable($pdo)) {
        throw new RuntimeException('Notification table is unavailable.');
    }

    // Newest notification id overall
    $latestId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM admin_notifications')->fetchColumn();
    // Client's last seen id (negative values clamped to 0)
    $sinceId = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : 0;

    // latest=1: client only wants a starting point, so return no items
    if (isset($_GET['latest']) && $_GET['latest'] === '1') {
        echo json_encode(['ok' => true, 'latest_id' => $latestId, 'items' => []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Up to 50 notifications newer than since_id, oldest first
    $stmt = $pdo->prepare(
        "SELECT id, event_type, username, book_id, book_title, message, created_at
         FROM admin_notifications WHERE id > ? ORDER BY id ASC LIMIT 50"
    );
    $stmt->execute([$sinceId]);

    echo json_encode([
        'ok' => true,
        'latest_id' => $latestId,
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Log the real error server-side, show only a generic message to the client
    error_log('WebShelf notification poll error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Notification service temporarily unavailable.']);
}
