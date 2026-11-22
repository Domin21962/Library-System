<?php
// Saves how far the user has read in a book (called in the background by the reader page).
// Access: any logged-in user. Inputs (POST): book_id, percent (0-100), _csrf. Output: no page, only an HTTP status.
// Tables: reading_progress (write), users (read).

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
enforceCsrfOnPost();
requireLogin();


// ===== Validate input =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$bookId = (int)($_POST['book_id'] ?? 0);
$percent = max(0, min(100, (int)($_POST['percent'] ?? 0)));
$userId = currentUserId($pdo);
if ($bookId < 1 || $userId < 1) {
    http_response_code(400);
    exit;
}


// ===== Save (one row per user and book) =====
try {
    $stmt = $pdo->prepare(
        "INSERT INTO reading_progress (user_id, book_id, percent) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE percent = VALUES(percent), updated_at = NOW()"
    );
    $stmt->execute([$userId, $bookId, $percent]);
    http_response_code(204);
} catch (PDOException $e) {
    // Foreign key error: the book does not exist
    http_response_code(404);
}
