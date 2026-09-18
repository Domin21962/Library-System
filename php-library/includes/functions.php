<?php
// Equivalent of the role logic in models/User.java and MainForm.getUserRole()
function getUserRole($username) {
    if (str_starts_with($username, "AM.")) return "Admin";
    if (str_starts_with($username, "TC.")) return "Teacher";
    if (str_starts_with($username, "SD.")) return "Student";
    return "Unknown";
}

// Equivalent of RegisterForm.isValidUsername()
function isValidUsername($username) {
    return preg_match('/^(TC|SD|AM)\.[a-zA-Z]+$/', $username) === 1;
}

// Simple flash-message style redirect (keeps pages() thin, like the JOptionPane dialogs)
function redirect($url) {
    header("Location: $url");
    exit;
}

function requireLogin() {
    if (empty($_SESSION['username'])) {
        redirect('login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        redirect('main.php');
    }
}

function requireTeacher() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'Teacher') {
        redirect('main.php');
    }
}

// Small touches to make the catalog feel less flat - a genre -> icon/color mapping.
function genreIcon($genre) {
    $map = [
        'fantasy' => '🐉', 'science fiction' => '🚀', 'romance' => '💌',
        'dystopian' => '🏙️', 'classic' => '🏛️', 'mystery' => '🔍',
        'computer science' => '💻', 'science' => '🔬', 'biography' => '📖',
        'history' => '🗺️', 'adventure' => '🧭', 'horror' => '🕯️',
        'poetry' => '🖋️', 'thriller' => '🔪', 'comedy' => '🎭',
    ];
    $key = strtolower(trim($genre));
    return $map[$key] ?? '📘';
}

function genreColor($genre) {
    $palette = ['#5b8cff', '#7c6bff', '#34d399', '#f59e0b', '#f87171', '#22d3ee', '#e879f9', '#a3e635'];
    $index = crc32(strtolower(trim($genre))) % count($palette);
    return $palette[$index];
}

/** Ensure the real-time admin notification table exists. */
function ensureAdminNotificationsTable(PDO $pdo): bool {
    static $ready = false;
    if ($ready) return true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type ENUM('borrow','return') NOT NULL,
            username VARCHAR(100) NOT NULL,
            book_id INT NOT NULL,
            book_title VARCHAR(255) NOT NULL,
            message VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_notifications_id (id),
            INDEX idx_admin_notifications_created (created_at),
            INDEX idx_admin_notifications_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
        return true;
    } catch (Throwable $e) {
        error_log('Doms Library admin notification table setup failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a real-time admin notification for a successful borrow/return.
 * The transaction itself should remain successful even if notifications are unavailable.
 */
function createAdminNotification(PDO $pdo, string $eventType, string $username, int $bookId, string $bookTitle): bool {
    if (!ensureAdminNotificationsTable($pdo)) return false;
    if (!in_array($eventType, ['borrow', 'return'], true)) {
        return false;
    }

    $message = $eventType === 'borrow'
        ? $username . ' borrowed "' . $bookTitle . '".'
        : $username . ' returned "' . $bookTitle . '".';

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admin_notifications (event_type, username, book_id, book_title, message, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$eventType, $username, $bookId, $bookTitle, $message]);
        return true;
    } catch (Throwable $e) {
        error_log('Doms Library admin notification failed: ' . $e->getMessage());
        return false;
    }
}
