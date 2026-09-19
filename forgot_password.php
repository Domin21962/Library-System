<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
enforceCsrfOnPost();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            ensureRecoveryTables($pdo);
            $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? ORDER BY id LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $mailConfig = require __DIR__ . '/config/mail.php';
            if ($user && !empty($mailConfig['enabled']) && !empty($mailConfig['configured'])) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int)$user['id']]);
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                $stmt->execute([(int)$user['id'], $tokenHash]);
                $resetUrl = recoveryBaseUrl() . '/reset_password.php?token=' . urlencode($rawToken);
                sendLibraryEmail($user['email'], 'Doms Library: Reset your password', buildPasswordResetEmail($user['username'], $resetUrl), true);
                auditLog($pdo, 'password_recovery_requested', $user['username']);
            }
        } catch (Throwable $e) {
            error_log('Doms Library password recovery failed: ' . $e->getMessage());
        }
        $success = 'If an account is registered with that email, a password reset link will be sent shortly.';
    }
}
$pageTitle = 'Forgot Password - Doms Library';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper"><div class="card">
    <h2>Forgot password?</h2>
    <div class="subtitle">Enter your registered email to receive a secure password reset link.</div>
    <form method="post">
        <?= csrf_field() ?>
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <button type="submit" style="width:100%;">Send reset link</button>
    </form>
    <p class="footer-link"><a href="login.php">Back to login</a></p>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
