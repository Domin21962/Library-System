<?php
// Roles are stored in the database. Username text never grants elevated privileges.
function getUserRole($username, ?PDO $pdo = null) {
    $pdo = $pdo ?: ($GLOBALS['pdo'] ?? null);
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $role = $stmt->fetchColumn();
            if (in_array($role, ['Admin', 'Teacher', 'Student'], true)) return $role;
        } catch (Throwable $e) {
            // Older installations may not have the role column yet; use legacy prefixes below.
        }
    }
    // Fail closed: if the role column/migration is unavailable, users are treated as Student.
    // Never infer Admin or Teacher privileges from a username prefix.
    return "Student";
}
// Usernames no longer need an AM./TC./SD. prefix.
// Role elevation must be handled by a protected administrator workflow, never by user input.
function isValidUsername($username) {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._ -]{2,99}$/', $username) === 1;
}

/** Build a default avatar from the user's name without showing legacy role prefixes. */
function defaultProfileInitials(string $username): string {
    // Legacy account prefixes such as SD. and TC. identify roles, not name initials.
    $displayName = preg_replace('/^(?:SD|TC)(?:[._-]|\s)+/i', '', trim($username)) ?? trim($username);
    $parts = preg_split('/[^a-zA-Z0-9]+/', trim($displayName), -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials !== '' ? $initials : 'U';
}

// Simple flash-message style redirect (keeps pages() thin, like the JOptionPane dialogs)
function redirect($url) {
    header("Location: $url");
    exit;
}

function requireLogin() {
    if (empty($_SESSION['username']) || !isValidUsername((string)$_SESSION['username'])) {
        auditLog($GLOBALS['pdo'] ?? null, 'unauthorized_access');
        redirect('login.php');
    }
    $expectedRole = getUserRole((string)$_SESSION['username']);
    if (($_SESSION['role'] ?? '') !== $expectedRole) {
        session_regenerate_id(true);
        $_SESSION['role'] = $expectedRole;
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



/** Ensure user profile picture support exists without requiring a manual SQL import. */
function ensureUserProfileColumn(PDO $pdo): bool {
    static $ready = false;
    if ($ready) return true;
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER email");
        }
        $ready = true;
        return true;
    } catch (Throwable $e) {
        error_log('Doms Library profile column setup failed: ' . $e->getMessage());
        return false;
    }
}

function profilePictureUrl(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '' || !preg_match('#^uploads/profiles/[A-Za-z0-9._-]+$#', $path)) return null;
    return $path;
}

function removeProfilePictureFile(?string $path): void {
    $safePath = profilePictureUrl($path);
    if (!$safePath) return;
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);
    if (is_file($absolute)) @unlink($absolute);
}

/** Ensure the real-time admin notification table exists and upgrade legacy schemas. */
function ensureAdminNotificationsTable(PDO $pdo): bool {
    static $ready = false;
    if ($ready) return true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(20) NOT NULL DEFAULT 'borrow',
            username VARCHAR(100) NOT NULL,
            book_id INT NOT NULL DEFAULT 0,
            book_title VARCHAR(255) NOT NULL DEFAULT '',
            message VARCHAR(500) NOT NULL DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_notifications_id (id),
            INDEX idx_admin_notifications_created (created_at),
            INDEX idx_admin_notifications_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $columns = $pdo->query("SHOW COLUMNS FROM admin_notifications")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('TYPE', $columns, true) && !in_array('event_type', $columns, true)) {
            $pdo->exec("ALTER TABLE admin_notifications CHANGE COLUMN `TYPE` event_type VARCHAR(20) NOT NULL DEFAULT 'borrow'");
            $columns = $pdo->query("SHOW COLUMNS FROM admin_notifications")->fetchAll(PDO::FETCH_COLUMN);
        }
        $required = [
            'event_type' => "ALTER TABLE admin_notifications ADD COLUMN event_type VARCHAR(20) NOT NULL DEFAULT 'borrow' AFTER id",
            'username' => "ALTER TABLE admin_notifications ADD COLUMN username VARCHAR(100) NOT NULL DEFAULT '' AFTER event_type",
            'book_id' => "ALTER TABLE admin_notifications ADD COLUMN book_id INT NOT NULL DEFAULT 0 AFTER username",
            'book_title' => "ALTER TABLE admin_notifications ADD COLUMN book_title VARCHAR(255) NOT NULL DEFAULT '' AFTER book_id",
            'message' => "ALTER TABLE admin_notifications ADD COLUMN message VARCHAR(500) NOT NULL DEFAULT '' AFTER book_title",
            'is_read' => "ALTER TABLE admin_notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER message",
            'created_at' => "ALTER TABLE admin_notifications ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_read"
        ];
        foreach ($required as $column => $alterSql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($alterSql);
                $columns[] = $column;
            }
        }
        $ready = true;
        return true;
    } catch (Throwable $e) {
        error_log('Doms Library admin notification table setup failed: ' . $e->getMessage());
        return false;
    }
}

function createAdminNotification(PDO $pdo, string $eventType, string $username, int $bookId, string $bookTitle): bool {
    if (!ensureAdminNotificationsTable($pdo) || !in_array($eventType, ['borrow', 'return'], true)) return false;
    $message = $eventType === 'borrow' ? "$username borrowed \"$bookTitle\"." : "$username returned \"$bookTitle\".";
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (event_type, username, book_id, book_title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$eventType, $username, $bookId, $bookTitle, $message]);
        auditLog($pdo, 'notification_created', $username, ['event_type' => $eventType, 'book_id' => $bookId]);
        return true;
    } catch (Throwable $e) {
        error_log('Doms Library admin notification failed: ' . $e->getMessage());
        return false;
    }
}


function ensureRecoveryTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_reset_user (user_id),
        INDEX idx_password_reset_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function recoveryBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . ($host ?: 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

function maskEmailAddress(string $email): string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'your registered email';
    [$local, $domain] = explode('@', $email, 2);
    $first = function_exists('mb_substr') ? mb_substr($local, 0, 1) : substr($local, 0, 1);
    return htmlspecialchars($first, ENT_QUOTES, 'UTF-8') . '***@' . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
}
