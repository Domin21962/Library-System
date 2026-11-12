<?php
/*
 * Security helpers: session bootstrap, security headers, CSRF, audit log,
 * login lockout and email confirmation codes. Included by every page.
 * Access: public include (no page output). Inputs: $_SESSION, $_POST['_csrf'], $_SERVER.
 * Tables: security_audit_logs, login_attempts, login_confirmations.
 */
declare(strict_types=1);


// ===== HTTPS detection =====


/** True when the request came over HTTPS (directly or via a proxy header). */
function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}


// ===== App base URL (links work from the root and from the admin/, teacher/ and user/ folders) =====


/** URL path of the project root, for example "/php-library" (empty when the project is the web root). */
define('APP_BASE', preg_replace('#/(admin|teacher|user)$#', '', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/')));


/** Build a link from the project root: url('user/main.php') gives /php-library/user/main.php. */
function url(string $path): string {
    return APP_BASE . '/' . ltrim($path, '/');
}


// ===== Session and security-header bootstrap (runs on include) =====

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

// Send hardening headers (anti-sniffing, anti-clickjacking, referrer limits, HSTS on HTTPS)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}


// ===== Client info =====


/** Client IP for audit logs and lockouts, capped to the 45-char column size. */
function securityClientIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}


// ===== CSRF protection =====


/** Get (or create) the per-session CSRF token used to prove a POST came from our own forms. */
function csrfToken(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}


/** Hidden form input carrying the CSRF token; echo it inside every POST form. */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}


/** Check the posted token against the session token using a timing-safe comparison. */
function verifyCsrfToken(): bool {
    $provided = (string)($_POST['_csrf'] ?? '');
    return $provided !== '' && !empty($_SESSION['_csrf']) && hash_equals((string)$_SESSION['_csrf'], $provided);
}


/** Stop any POST request with a bad CSRF token (HTTP 419). */
function enforceCsrfOnPost(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
        http_response_code(419);
        exit('Security check failed. Please go back, refresh the page, and try again.');
    }
}


// ===== Audit log =====


/** Record a security event (who, what, IP, browser) in security_audit_logs; never throws. */
function auditLog(?PDO $pdo, string $action, ?string $username = null, array $metadata = []): void {
    if (!$pdo) return;
    try {
        // Create the audit table on first use
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
        // Write one audit row
        $stmt = $pdo->prepare('INSERT INTO security_audit_logs (username, action, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $action, securityClientIp(), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    } catch (Throwable $e) {
        error_log('WebShelf audit log failed: ' . $e->getMessage());
    }
}


// ===== Login lockout (brute-force protection) =====


/** Create the login_attempts table if missing (one row per username + IP). */
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


/** True if this username from this IP is currently locked out. */
function loginIsLocked(PDO $pdo, string $username, string $ip): bool {
    ensureLoginProtectionTable($pdo);
    // Read the lock expiry for this username + IP
    $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE username = ? AND ip_address = ?');
    $stmt->execute([$username, $ip]);
    $locked = $stmt->fetchColumn();
    return $locked !== false && $locked !== null && strtotime((string)$locked) > time();
}


/** Count a failed login; from 5 failures lock for 5 min per 5 failures, capped at 60. */
function recordFailedLogin(PDO $pdo, string $username, string $ip): void {
    ensureLoginProtectionTable($pdo);
    // Read the current failure count
    $stmt = $pdo->prepare('SELECT failed_attempts FROM login_attempts WHERE username = ? AND ip_address = ?');
    $stmt->execute([$username, $ip]);
    $count = (int)($stmt->fetchColumn() ?: 0) + 1;
    $lockMinutes = $count >= 5 ? min(60, 5 * (int)floor($count / 5)) : 0;
    $lockedUntil = $lockMinutes ? date('Y-m-d H:i:s', time() + $lockMinutes * 60) : null;
    // Insert or update the counter and lock time
    $up = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, failed_attempts, locked_until, last_attempt_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE failed_attempts = VALUES(failed_attempts), locked_until = VALUES(locked_until), last_attempt_at = NOW()');
    $up->execute([$username, $ip, $count, $lockedUntil]);
}


/** Reset the failure counter after a successful login. */
function clearFailedLogins(PDO $pdo, string $username, string $ip): void {
    ensureLoginProtectionTable($pdo);
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_address = ?')->execute([$username, $ip]);
}


// ===== Email login confirmation codes (second step) =====


/** Create the login_confirmations table if missing (stores hashed one-time codes). */
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


/** Issue a new 6-digit one-time code; only its hash is stored, older codes are invalidated. */
function createLoginConfirmation(PDO $pdo, string $username, string $email, int $expiryMinutes = 10): array {
    ensureLoginConfirmationsTable($pdo);
    // Invalidate any earlier unused codes for this user
    $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE username = ? AND used_at IS NULL')->execute([$username]);
    $code = (string)random_int(100000, 999999);
    // Store the hashed code with its expiry
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


/** Check a submitted code: must be unused, unexpired, under 5 wrong tries; marks it used on success (one-time). */
function verifyLoginConfirmation(PDO $pdo, int $id, string $username, string $code): array {
    ensureLoginConfirmationsTable($pdo);
    // Load the unused confirmation row
    $stmt = $pdo->prepare('SELECT * FROM login_confirmations WHERE id = ? AND username = ? AND used_at IS NULL LIMIT 1');
    $stmt->execute([$id, $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['ok' => false, 'reason' => 'invalid'];
    if (strtotime((string)$row['expires_at']) < time()) return ['ok' => false, 'reason' => 'expired'];
    if ((int)$row['attempts'] >= 5) return ['ok' => false, 'reason' => 'locked'];
    if (!password_verify($code, (string)$row['code_hash'])) {
        // Wrong code: count the attempt
        $pdo->prepare('UPDATE login_confirmations SET attempts = attempts + 1 WHERE id = ?')->execute([$id]);
        return ['ok' => false, 'reason' => 'invalid'];
    }
    // Correct code: burn it so it cannot be reused
    $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE id = ?')->execute([$id]);
    return ['ok' => true, 'reason' => 'verified'];
}
