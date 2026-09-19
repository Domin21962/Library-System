<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
enforceCsrfOnPost();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        $ip = securityClientIp();
        if (loginIsLocked($pdo, $username, $ip)) {
            $error = "Too many failed attempts. Please try again later.";
        } else {
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

        if ($valid) {
            $role = getUserRole($username, $pdo);

            // Administrators bypass the email confirmation step by design.
            if ($role === 'Admin') {
                $stmt2 = $pdo->prepare("SELECT email FROM users WHERE username = ?");
                $stmt2->execute([$username]);
                $emailRow = $stmt2->fetch(PDO::FETCH_ASSOC);
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
                    $pending = createLoginConfirmation($pdo, $username, $email, 10);
                    $subject = 'Doms Library: Is this you?';
                    $body = buildLoginConfirmationEmail($username, $pending['code'], $pending['expires_minutes']);
                    if (!sendLibraryEmail($email, $subject, $body, true)) {
                        $error = "We could not send the confirmation email. Check config/local.php SMTP settings.";
                        auditLog($pdo, 'login_confirmation_email_failed', $username);
                    } else {
                        $_SESSION['pending_login_id'] = $pending['id'];
                        $_SESSION['pending_login_username'] = $username;
                        $_SESSION['pending_login_role'] = $role;
                        clearFailedLogins($pdo, $username, $ip);
                        auditLog($pdo, 'login_confirmation_sent', $username);
                        redirect('login_verify.php');
                    }
                } catch (Throwable $e) {
                    error_log('Doms Library login confirmation failed: ' . $e->getMessage());
                    $error = "Unable to start email confirmation. Please try again.";
                }
            }
        } else {
            recordFailedLogin($pdo, $username, $ip);
            auditLog($pdo, 'login_failed', $username);
            $error = "Invalid username or password!";
        }
        }
    }
}

$pageTitle = "Login - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup">
            <span class="logo-mark">📚</span> Doms Library
        </div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">
            Borrow, read, and return books all in one place — your shelf, wherever you are.
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="card">
            <h2>Welcome back</h2>
            <div class="subtitle">Log in to your Doms Library account</div>
            <form method="post">
                <?= csrf_field() ?>
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
                <label>Password</label>
                <input type="password" name="password" required>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <button type="submit" style="width:100%;">Login</button>
            </form>
            <a class="btn secondary" href="qr_login.php" style="width:100%;text-align:center;margin-top:10px;">▣ Login with QR Code</a>
            <p class="footer-link" style="margin-bottom:8px;">
                <a href="forgot_password.php">Forgot password?</a> · <a href="forgot_username.php">Forgot username?</a>
            </p>
            <p class="footer-link">
                No account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
