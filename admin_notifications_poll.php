<?php
// JSON-only polling endpoint. Never redirect here: fetch() must receive JSON.
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['username']) || (($_SESSION['role'] ?? '') !== 'Admin')) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Admin session required.']);
    exit;
}

try {
    if (!ensureAdminNotificationsTable($pdo)) {
        throw new RuntimeException('Notification table is unavailable.');
    }

    $latestId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM admin_notifications')->fetchColumn();
    $sinceId = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : 0;

    if (isset($_GET['latest']) && $_GET['latest'] === '1') {
        echo json_encode(['ok' => true, 'latest_id' => $latestId, 'items' => []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

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
    error_log('Doms Library notification poll error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Notification service temporarily unavailable.']);
}
