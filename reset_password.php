<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
enforceCsrfOnPost();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$user = null;
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $error = 'This password reset link is invalid.';
} else {
    ensureRecoveryTables($pdo);
    $stmt = $pdo->prepare('SELECT pr.id, u.id AS user_id, u.username FROM password_reset_tokens pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) $error = 'This password reset link is invalid or expired.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($password !== $confirm) $error = 'Passwords do not match.';
    else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, (int)$user['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        auditLog($pdo, 'password_reset_completed', $user['username']);
        $success = 'Your password has been changed. You may now log in.';
        $user = null;
    }
}
$pageTitle = 'Reset Password - Doms Library';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper"><div class="card">
    <h2>Reset password</h2>
    <div class="subtitle">Create a new password for your Doms Library account.</div>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><p class="footer-link"><a href="login.php">Return to login</a></p>
    <?php elseif ($user): ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <label for="password">New password</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
        <label for="confirm_password">Confirm new password</label><input id="confirm_password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <button type="submit" style="width:100%;">Change password</button>
    </form>
    <?php else: ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (!$success): ?><p class="footer-link"><a href="login.php">Back to login</a></p><?php endif; ?>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
