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
        $sent = false;
        try {
            $stmt = $pdo->prepare('SELECT username FROM users WHERE email = ? ORDER BY username');
            $stmt->execute([$email]);
            $usernames = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'username');
            $mailConfig = require __DIR__ . '/config/mail.php';
            if ($usernames && !empty($mailConfig['enabled']) && !empty($mailConfig['configured'])) {
                $sent = sendLibraryEmail($email, 'Doms Library: Username reminder', buildUsernameRecoveryEmail($usernames), true);
                auditLog($pdo, 'username_recovery_email_sent', null, ['matched_accounts' => count($usernames)]);
            }
        } catch (Throwable $e) {
            error_log('Doms Library username recovery failed: ' . $e->getMessage());
        }
        $success = 'If an account is registered with that email, a username reminder will be sent shortly.';
    }
}
$pageTitle = 'Forgot Username - Doms Library';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper"><div class="card">
    <h2>Forgot username?</h2>
    <div class="subtitle">Enter the email address connected to your library account.</div>
    <form method="post">
        <?= csrf_field() ?>
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <button type="submit" style="width:100%;">Send username reminder</button>
    </form>
    <p class="footer-link"><a href="login.php">Back to login</a></p>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
