<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Only Teacher and Student accounts go through the email-confirmation flow
// for password changes. (Admin keeps the old immediate-change behavior.)
$requiresCodeConfirm = in_array($role, ['Teacher', 'Student'], true);

$CODE_EXPIRY_MINUTES = 10;
$MAX_CODE_ATTEMPTS = 5;

$infoError = '';
$infoSuccess = '';
$pwError = '';
$pwSuccess = '';

// Always load the real record from the database (fixes the old bug where
// email never made it into the session - now we just read it straight from users)
$stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

// Clear out any expired, unused codes for this account so they don't linger
$pdo->prepare("DELETE FROM password_reset_codes WHERE username = ? AND used = 0 AND expires_at < NOW()")
    ->execute([$username]);

function getPendingCode(PDO $pdo, string $username) {
    $stmt = $pdo->prepare(
        "SELECT * FROM password_reset_codes
         WHERE username = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function sendPasswordCode(PDO $pdo, string $username, string $email, string $newPassword, int $expiryMinutes): void {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $insert = $pdo->prepare(
        "INSERT INTO password_reset_codes (username, code, new_password, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    );
    $insert->execute([$username, $code, $newPassword, $expiryMinutes]);

    $html = buildPasswordCodeEmail($username, $code, $expiryMinutes);
    sendLibraryEmail($email, "Your Doms Library password change code", $html, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // --- Account info (email) - saved immediately, same as before ---
    if ($form === 'update_info') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if ($currentPassword === '' || $currentPassword !== $user['password']) {
            $infoError = "Current password is incorrect.";
        } elseif ($newEmail === '') {
            $infoError = "Email can't be empty.";
        } else {
            $update = $pdo->prepare("UPDATE users SET email = ? WHERE username = ?");
            $update->execute([$newEmail, $username]);
            $user['email'] = $newEmail;
            $infoSuccess = "Info updated.";
        }
    }

    // --- Password change ---
    elseif ($form === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $currentPassword !== $user['password']) {
            $pwError = "Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $pwError = "New password and confirmation don't match.";
        } elseif (empty($user['email'])) {
            $pwError = "There's no email on file for this account, so a confirmation code can't be sent. Add an email above first.";
        } else {
            if ($requiresCodeConfirm) {
                sendPasswordCode($pdo, $username, $user['email'], $newPassword, $CODE_EXPIRY_MINUTES);
                $pwSuccess = "We sent a 6-digit code to {$user['email']}. Enter it below to confirm the change.";
            } else {
                // Admin: unchanged immediate behavior
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                $update->execute([$newPassword, $username]);
                $user['password'] = $newPassword;
                $pwSuccess = "Password updated.";
            }
        }
    }

    // --- Confirm the emailed code ---
    elseif ($form === 'confirm_code') {
        $enteredCode = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
        $pending = getPendingCode($pdo, $username);

        if (!$pending) {
            $pwError = "That code has expired. Request a new one below.";
        } elseif ($pending['attempts'] >= $MAX_CODE_ATTEMPTS) {
            $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE id = ?")->execute([$pending['id']]);
            $pwError = "Too many incorrect attempts. Request a new code.";
        } elseif ($enteredCode !== $pending['code']) {
            $pdo->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$pending['id']]);
            $remaining = $MAX_CODE_ATTEMPTS - ($pending['attempts'] + 1);
            $pwError = "Incorrect code. " . max($remaining, 0) . " attempt(s) left.";
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")
                ->execute([$pending['new_password'], $username]);
            $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE id = ?")->execute([$pending['id']]);
            $user['password'] = $pending['new_password'];
            $pwSuccess = "Password changed successfully.";
        }
    }

    // --- Resend the code (reuses the same pending new password) ---
    elseif ($form === 'resend_code') {
        $pending = getPendingCode($pdo, $username);
        if ($pending) {
            $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE id = ?")->execute([$pending['id']]);
            sendPasswordCode($pdo, $username, $user['email'], $pending['new_password'], $CODE_EXPIRY_MINUTES);
            $pwSuccess = "We sent a new code to {$user['email']}.";
        }
    }

    // --- Cancel a pending code and go back to the request form ---
    elseif ($form === 'cancel_code') {
        $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE username = ? AND used = 0")->execute([$username]);
    }
}

$pendingCode = $requiresCodeConfirm ? getPendingCode($pdo, $username) : null;

$pageTitle = "My Info";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Doms Library <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="qr_login.php">QR Login</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="reader-wrap" style="max-width:520px;">
    <p style="margin:0 0 16px;"><a href="main.php">← Back to catalog</a></p>

    <div class="card" style="max-width:none;text-align:center;margin-bottom:20px;">
        <div class="profile-avatar"><?= strtoupper(substr($username, 3, 1) ?: 'U') ?></div>
        <h2 style="margin-bottom:2px;"><?= htmlspecialchars($username) ?></h2>
        <div class="subtitle" style="margin-bottom:4px;"><?= htmlspecialchars($role) ?> account</div>
        <div style="color:var(--muted);font-family:monospace;font-size:12px;margin-bottom:24px;">USR-<?= str_pad((string)$user['id'], 5, '0', STR_PAD_LEFT) ?></div>

        <form method="post" style="text-align:left;">
            <input type="hidden" name="form" value="update_info">
            <label>Email</label>
            <input type="text" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <label>Current Password</label>
            <input type="password" name="current_password" required placeholder="Required to save changes">

            <?php if ($infoError): ?><div class="error"><?= htmlspecialchars($infoError) ?></div><?php endif; ?>
            <?php if ($infoSuccess): ?><div class="success"><?= htmlspecialchars($infoSuccess) ?></div><?php endif; ?>

            <button type="submit" style="width:100%;">Save Info</button>
        </form>
    </div>

    <div class="card" style="max-width:none;text-align:center;">
        <h2 style="margin-bottom:2px;font-size:18px;">🔒 Change Password</h2>
        <div class="subtitle" style="margin-bottom:20px;">
            <?= $requiresCodeConfirm
                ? "We'll email you a confirmation code before this takes effect."
                : "Admin accounts change immediately, no confirmation code needed." ?>
        </div>

        <?php if ($requiresCodeConfirm && $pendingCode): ?>
            <!-- Step 2: enter the code that was emailed -->
            <form method="post" style="text-align:left;">
                <input type="hidden" name="form" value="confirm_code">
                <label>Confirmation Code</label>
                <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="6-digit code" required autofocus>

                <?php if ($pwError): ?><div class="error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
                <?php if ($pwSuccess): ?><div class="success"><?= htmlspecialchars($pwSuccess) ?></div><?php endif; ?>

                <button type="submit" style="width:100%;">Confirm & Change Password</button>
            </form>
            <form method="post" style="display:flex;gap:10px;margin-top:10px;">
                <input type="hidden" name="form" value="resend_code">
                <button type="submit" class="secondary" style="width:100%;margin-top:0;">Resend Code</button>
            </form>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="form" value="cancel_code">
                <button type="submit" class="secondary" style="width:100%;margin-top:0;">Cancel</button>
            </form>
        <?php else: ?>
            <!-- Step 1: request the change -->
            <form method="post" style="text-align:left;">
                <input type="hidden" name="form" value="change_password">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Enter a new password">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Repeat new password">
                <label>Current Password</label>
                <input type="password" name="current_password" required placeholder="Required to confirm it's you">

                <?php if ($pwError): ?><div class="error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
                <?php if ($pwSuccess && !$pendingCode): ?><div class="success"><?= htmlspecialchars($pwSuccess) ?></div><?php endif; ?>

                <button type="submit" style="width:100%;"><?= $requiresCodeConfirm ? 'Send Confirmation Code' : 'Change Password' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
