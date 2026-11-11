<?php
/**
 * Forgot password: emails a one-time reset link to a registered address.
 * Who: public.
 * Input: POST email, csrf token.
 * Tables: users (read), password_reset_tokens (update, insert), audit log via helper.
 */


// ===== Requires & CSRF check =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
// Reject any POST with an invalid CSRF token
enforceCsrfOnPost();


// ===== Handle form submit =====
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            // Make sure the reset-token table exists
            ensureRecoveryTables($pdo);
            // Find the account that owns this email
            $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? ORDER BY id LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // Only send when SMTP is enabled and fully configured
            $mailConfig = require __DIR__ . '/config/mail.php';
            if ($user && !empty($mailConfig['enabled']) && !empty($mailConfig['configured'])) {
                // Random token; only its SHA-256 hash is stored in the DB
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                // Invalidate any older unused tokens for this user
                $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int)$user['id']]);
                // Store the new token hash, valid for 15 minutes
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                $stmt->execute([(int)$user['id'], $tokenHash]);
                // Link the user clicks; contains the raw token
                $resetUrl = recoveryBaseUrl() . '/reset_password.php?token=' . urlencode($rawToken);
                sendLibraryEmail($user['email'], 'WebShelf: Reset your password', buildPasswordResetEmail($user['username'], $resetUrl), true);
                auditLog($pdo, 'password_recovery_requested', $user['username']);
            }
        } catch (Throwable $e) {
            error_log('WebShelf password recovery failed: ' . $e->getMessage());
        }
        // Same message whether or not the email exists (prevents account enumeration)
        $success = 'If an account is registered with that email, a password reset link will be sent shortly.';
    }
}


// ===== Page setup =====
$pageTitle = 'Forgot Password - WebShelf';
include __DIR__ . '/includes/header.php';
?>
<!-- ===== HTML output: forgot-password card ===== -->
<div class="auth-wrapper"><div class="card">
    <h2>Forgot password?</h2>
    <div class="subtitle">Enter your registered email to receive a secure password reset link.</div>
    <!-- Email form (includes CSRF field) -->
    <form method="post">
        <?= csrf_field() ?>
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <button class="w-full" type="submit">Send reset link</button>
    </form>
    <p class="footer-link"><a href="login.php">Back to login</a></p>
</div></div>
<!-- Shared page footer -->
<?php include __DIR__ . '/includes/footer.php'; ?>
