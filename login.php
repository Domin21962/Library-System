<?php
/**
 * Login page: checks username/password and starts a session or an emailed-code confirmation.
 * Who: public (anyone not logged in).
 * Input: POST username, password, csrf token.
 * Tables: users (read, legacy password rehash), login attempts + audit log via helpers.
 */


// ===== Requires & CSRF check =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
// Reject any POST with an invalid CSRF token
enforceCsrfOnPost();


// ===== Handle login form submit =====
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read and trim submitted credentials
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        // Lockout: refuse if too many recent failures for this user/IP
        $ip = securityClientIp();
        if (loginIsLocked($pdo, $username, $ip)) {
            $error = "Too many failed attempts. Please try again later.";
        } else {
            // Look up the account by username
            $stmt = $pdo->prepare("SELECT username, email, password AS password FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // Never access a missing password key; this prevents PHP warnings and fails closed.
            $storedPassword = ($user && array_key_exists('password', $user)) ? (string)$user['password'] : '';
            $valid = ($storedPassword !== '') && password_verify($password, $storedPassword);
            // One-time migration for legacy plaintext passwords. Remove plaintext storage by hashing immediately.
            if (!$valid && $user && $storedPassword !== '' && !password_get_info($storedPassword)['algo'] && hash_equals($storedPassword, $password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")->execute([$newHash, $username]);
                $storedPassword = $newHash;
                $user['password'] = $newHash;
                $valid = true;
                auditLog($pdo, 'legacy_password_migrated', $username);
            }

        // --- Credentials correct: next step depends on role ---
        if ($valid) {
            $role = getUserRole($username, $pdo);

            // Administrators bypass the email confirmation step by design.
            if ($role === 'Admin') {
                $stmt2 = $pdo->prepare("SELECT email FROM users WHERE username = ?");
                $stmt2->execute([$username]);
                $emailRow = $stmt2->fetch(PDO::FETCH_ASSOC);
                // New session id after login (prevents session fixation)
                session_regenerate_id(true);
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $emailRow['email'] ?? '';
                clearFailedLogins($pdo, $username, $ip);
                auditLog($pdo, 'login_success_admin_without_email_confirmation', $username);
                redirect('admin.php');
            }

            // Teachers and students must complete the email confirmation step.
            $stmt2 = $pdo->prepare("SELECT email FROM users WHERE username = ?");
            $stmt2->execute([$username]);
            $emailRow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $email = $emailRow['email'] ?? '';
            // Email confirmation needs working SMTP settings
            $mailConfig = require __DIR__ . '/config/mail.php';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "This account has no valid email address. Please contact an administrator.";
            } elseif (empty($mailConfig['enabled'])) {
                $error = "Email confirmation is required for teachers and students. Enable SMTP in config/local.php by setting mail_enabled to true.";
                auditLog($pdo, 'login_confirmation_blocked_mail_disabled', $username);
            } elseif (empty($mailConfig['configured'])) {
                $error = "SMTP is enabled but incomplete. Check host, port, encryption, username, password, and from_email in config/local.php.";
                auditLog($pdo, 'login_confirmation_blocked_mail_incomplete', $username);
            } else {
                try {
                    // Create a 10-minute emailed code and send it
                    $pending = createLoginConfirmation($pdo, $username, $email, 10);
                    $subject = 'WebShelf: Is this you?';
                    $body = buildLoginConfirmationEmail($username, $pending['code'], $pending['expires_minutes']);
                    if (!sendLibraryEmail($email, $subject, $body, true)) {
                        $error = "We could not send the confirmation email. Check config/local.php SMTP settings.";
                        auditLog($pdo, 'login_confirmation_email_failed', $username);
                    } else {
                        // Park the half-finished login until the code is verified
                        $_SESSION['pending_login_id'] = $pending['id'];
                        $_SESSION['pending_login_username'] = $username;
                        $_SESSION['pending_login_role'] = $role;
                        clearFailedLogins($pdo, $username, $ip);
                        auditLog($pdo, 'login_confirmation_sent', $username);
                        redirect('login_verify.php');
                    }
                } catch (Throwable $e) {
                    error_log('WebShelf login confirmation failed: ' . $e->getMessage());
                    $error = "Unable to start email confirmation. Please try again.";
                }
            }
        } else {
            // --- Wrong credentials: count the failure for lockout and log it ---
            recordFailedLogin($pdo, $username, $ip);
            auditLog($pdo, 'login_failed', $username);
            $error = "Invalid username or password!";
        }
        }
    }
}


// ===== Page setup =====
$pageTitle = "Login - WebShelf";
include __DIR__ . '/includes/header.php';
?>
<!-- ===== HTML output: login page (illustration + form) ===== -->
<div class="auth-split">
    <!-- Left panel: brand, illustration, tagline -->
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup">
            <span class="logo-mark">📚</span> WebShelf
        </div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">
            Borrow, read, and return books all in one place — your shelf, wherever you are.
        </div>
    </div>
    <!-- Right panel: login card -->
    <div class="auth-form-panel">
        <div class="card">
            <h2>Welcome back</h2>
            <div class="subtitle">Log in to your WebShelf account</div>
            <!-- Login form (includes CSRF field) -->
            <form method="post">
                <?= csrf_field() ?>
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
                <label>Password</label>
                <input type="password" name="password" required>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <button class="w-full" type="submit">Login</button>
            </form>
            <!-- Alternative: QR code login -->
            <a class="btn secondary w-full text-center mt-10" href="qr_login.php">▣ Login with QR Code</a>
            <!-- Recovery links -->
            <p class="footer-link mb-8">
                <a href="forgot_password.php">Forgot password?</a> · <a href="forgot_username.php">Forgot username?</a>
            </p>
            <p class="footer-link">
                No account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</div>
<!-- Shared page footer -->
<?php include __DIR__ . '/includes/footer.php'; ?>
