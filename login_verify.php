<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
enforceCsrfOnPost();

$error = '';
$username = (string)($_SESSION['pending_login_username'] ?? '');
$pendingId = (int)($_SESSION['pending_login_id'] ?? 0);
if ($username === '' || $pendingId <= 0) {
    redirect('login.php');
}

// Load the account details shown on the confirmation screen.
// The profile picture is optional, so the default initials avatar is used when no picture exists.
ensureUserProfileColumn($pdo);
$userStmt = $pdo->prepare('SELECT username, email, profile_picture FROM users WHERE username = ? LIMIT 1');
$userStmt->execute([$username]);
$pendingUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$displayUsername = (string)($pendingUser['username'] ?? $username);
$displayEmail = (string)($pendingUser['email'] ?? '');
$profilePicture = profilePictureUrl($pendingUser['profile_picture'] ?? null);
$profileInitials = defaultProfileInitials($displayUsername);

function maskConfirmationEmail(string $email): string {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'your registered email';
    }

    [$localPart, $domain] = explode('@', $email, 2);
    $firstCharacter = function_exists('mb_substr')
        ? mb_substr($localPart, 0, 1)
        : substr($localPart, 0, 1);

    return htmlspecialchars($firstCharacter, ENT_QUOTES, 'UTF-8')
        . '***@'
        . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
}

$maskedEmail = maskConfirmationEmail($displayEmail);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string)($_POST['code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Enter the 6-digit code from your email.';
    } else {
        $result = verifyLoginConfirmation($pdo, $pendingId, $username, $code);
        if (!$result['ok']) {
            $error = match ($result['reason']) {
                'expired' => 'This code has expired. Please log in again.',
                'locked' => 'Too many incorrect codes. Please log in again.',
                default => 'Incorrect confirmation code.'
            };
            auditLog($pdo, 'login_confirmation_failed', $username, ['reason' => $result['reason']]);
        } else {
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            $_SESSION['role'] = getUserRole($username, $pdo);
            unset($_SESSION['pending_login_id'], $_SESSION['pending_login_username'], $_SESSION['pending_login_role']);
            auditLog($pdo, 'login_success', $username, ['email_confirmed' => true]);
            if ($_SESSION['role'] === 'Admin') redirect('admin.php');
            if ($_SESSION['role'] === 'Teacher') redirect('teacher.php');
            redirect('main.php');
        }
    }
}

$pageTitle = 'Confirm Login - Doms Library';
include __DIR__ . '/includes/header.php';
?>
<style>
    .login-confirm-profile {
        text-align: center;
        margin: 22px 0 20px;
        padding: 18px 14px;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--panel-2);
    }
    .login-confirm-avatar {
        width: 82px;
        height: 82px;
        margin: 0 auto 12px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: #fff;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        border: 2px solid var(--border);
        font-size: 30px;
        font-weight: 700;
    }
    .login-confirm-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .login-confirm-name {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .login-confirm-email {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 13px;
        overflow-wrap: anywhere;
    }
    .login-confirm-question {
        margin: 0 0 8px;
        font-weight: 700;
    }
</style>
<div class="auth-split">
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup"><span class="logo-mark">📚</span> Doms Library</div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">One more step to protect your library account.</div>
    </div>
    <div class="auth-form-panel">
        <div class="card">
            <h2>Is this you?</h2>
            <div class="subtitle">Check your account details before entering the confirmation code.</div>

            <div class="login-confirm-profile" aria-label="Account confirmation details">
                <div class="login-confirm-avatar">
                    <?php if ($profilePicture): ?>
                        <img src="<?= htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8') ?>" alt="Profile picture">
                    <?php else: ?>
                        <?= htmlspecialchars($profileInitials, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <p class="login-confirm-name"><?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="login-confirm-email">Email: <?= $maskedEmail ?></p>
            </div>

            <p class="login-confirm-question">Is this your account?</p>
            <div class="subtitle">We sent a 6-digit confirmation code to your registered email. Enter it below to finish signing in.</div>
            <form method="post">
                <?= csrf_field() ?>
                <label>Email confirmation code</label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <button type="submit" style="width:100%;">Confirm and Login</button>
            </form>
            <p class="footer-link"><a href="logout.php">Cancel and return to login</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
