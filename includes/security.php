<?php
declare(strict_types=1);

// Central security bootstrap. Keep secrets out of the web root and source control.
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('DOMSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function securityClientIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function csrfToken(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(): bool {
    $provided = (string)($_POST['_csrf'] ?? '');
    return $provided !== '' && !empty($_SESSION['_csrf']) && hash_equals((string)$_SESSION['_csrf'], $provided);
}

function enforceCsrfOnPost(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
        http_response_code(419);
        exit('Security check failed. Please go back, refresh the page, and try again.');
    }
}

function auditLog(?PDO $pdo, string $action, ?string $username = null, array $metadata = []): void {
    if (!$pdo) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS security_audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NULL,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_security_audit_created (created_at),
            INDEX idx_security_audit_username (username),
            INDEX idx_security_audit_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->prepare('INSERT INTO security_audit_logs (username, action, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $action, securityClientIp(), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    } catch (Throwable $e) {
        error_log('Doms Library audit log failed: ' . $e->getMessage());
    }
}

function ensureLoginProtectionTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_login_attempt (username, ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function loginIsLocked(PDO $pdo, string $username, string $ip): bool {
    ensureLoginProtectionTable($pdo);
    $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE username = ? AND ip_address = ?');
    $stmt->execute([$username, $ip]);
    $locked = $stmt->fetchColumn();
    return $locked !== false && $locked !== null && strtotime((string)$locked) > time();
}

function recordFailedLogin(PDO $pdo, string $username, string $ip): void {
    ensureLoginProtectionTable($pdo);
    $stmt = $pdo->prepare('SELECT failed_attempts FROM login_attempts WHERE username = ? AND ip_address = ?');
    $stmt->execute([$username, $ip]);
    $count = (int)($stmt->fetchColumn() ?: 0) + 1;
    $lockMinutes = $count >= 5 ? min(60, 5 * (int)floor($count / 5)) : 0;
    $lockedUntil = $lockMinutes ? date('Y-m-d H:i:s', time() + $lockMinutes * 60) : null;
    $up = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, failed_attempts, locked_until, last_attempt_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE failed_attempts = VALUES(failed_attempts), locked_until = VALUES(locked_until), last_attempt_at = NOW()');
    $up->execute([$username, $ip, $count, $lockedUntil]);
}

function clearFailedLogins(PDO $pdo, string $username, string $ip): void {
    ensureLoginProtectionTable($pdo);
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_address = ?')->execute([$username, $ip]);
}


function ensureLoginConfirmationsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_confirmations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(190) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        used_at DATETIME NULL,
        INDEX idx_login_confirmations_username (username),
        INDEX idx_login_confirmations_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function createLoginConfirmation(PDO $pdo, string $username, string $email, int $expiryMinutes = 10): array {
    ensureLoginConfirmationsTable($pdo);
    $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE username = ? AND used_at IS NULL')->execute([$username]);
    $code = (string)random_int(100000, 999999);
    $stmt = $pdo->prepare('INSERT INTO login_confirmations (username, email, code_hash, expires_at, ip_address, user_agent) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)');
    $stmt->execute([
        $username,
        $email,
        password_hash($code, PASSWORD_DEFAULT),
        $expiryMinutes,
        securityClientIp(),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
    ]);
    return ['id' => (int)$pdo->lastInsertId(), 'code' => $code, 'expires_minutes' => $expiryMinutes];
}

function verifyLoginConfirmation(PDO $pdo, int $id, string $username, string $code): array {
    ensureLoginConfirmationsTable($pdo);
    $stmt = $pdo->prepare('SELECT * FROM login_confirmations WHERE id = ? AND username = ? AND used_at IS NULL LIMIT 1');
    $stmt->execute([$id, $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['ok' => false, 'reason' => 'invalid'];
    if (strtotime((string)$row['expires_at']) < time()) return ['ok' => false, 'reason' => 'expired'];
    if ((int)$row['attempts'] >= 5) return ['ok' => false, 'reason' => 'locked'];
    if (!password_verify($code, (string)$row['code_hash'])) {
        $pdo->prepare('UPDATE login_confirmations SET attempts = attempts + 1 WHERE id = ?')->execute([$id]);
        return ['ok' => false, 'reason' => 'invalid'];
    }
    $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE id = ?')->execute([$id]);
    return ['ok' => true, 'reason' => 'verified'];
}
