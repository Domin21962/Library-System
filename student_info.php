<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
ensureUserProfileColumn($pdo);
enforceCsrfOnPost();
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
$stmt = $pdo->prepare("SELECT id, username, email, password, profile_picture FROM users WHERE username = ?");
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

function storePasswordCode(PDO $pdo, string $username, string $passwordHash, int $expiryMinutes): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $insert = $pdo->prepare(
        "INSERT INTO password_reset_codes (username, code, new_password, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    );
    $insert->execute([$username, $code, $passwordHash, $expiryMinutes]);
    return $code;
}

function sendPasswordCode(PDO $pdo, string $username, string $email, string $newPassword, int $expiryMinutes): void {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $code = storePasswordCode($pdo, $username, $passwordHash, $expiryMinutes);
    $html = buildPasswordCodeEmail($username, $code, $expiryMinutes);
    sendLibraryEmail($email, "Your Doms Library password change code", $html, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // --- Profile picture ---
    if ($form === 'upload_picture' && $requiresCodeConfirm) {
        $currentPath = profilePictureUrl($user['profile_picture'] ?? null);
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) $infoError = 'Choose an image to upload.';
        elseif ((int)$_FILES['profile_picture']['size'] > 3 * 1024 * 1024) $infoError = 'The image must be 3 MB or smaller.';
        else {
            $tmp = (string)$_FILES['profile_picture']['tmp_name']; $imageInfo = @getimagesize($tmp); $mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
            if (!$imageInfo || !isset($allowed[$mime])) $infoError = 'Upload a valid JPG, PNG, GIF, or WEBP image.';
            else {
                $directory = __DIR__ . '/uploads/profiles';
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) $infoError = 'The profile image folder could not be created.';
                else {
                    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime]; $relativePath = 'uploads/profiles/' . $filename;
                    if (!move_uploaded_file($tmp, $directory . DIRECTORY_SEPARATOR . $filename)) $infoError = 'The image could not be saved. Check folder permissions.';
                    else { $pdo->prepare('UPDATE users SET profile_picture = ? WHERE username = ?')->execute([$relativePath, $username]); removeProfilePictureFile($currentPath); $user['profile_picture'] = $relativePath; $infoSuccess = 'Profile picture updated.'; }
                }
            }
        }
    } elseif ($form === 'remove_picture' && $requiresCodeConfirm) {
        $currentPath = profilePictureUrl($user['profile_picture'] ?? null); $pdo->prepare('UPDATE users SET profile_picture = NULL WHERE username = ?')->execute([$username]); removeProfilePictureFile($currentPath); $user['profile_picture'] = null; $infoSuccess = 'Profile picture removed.';
    }

    // --- Account info (email) - saved immediately, same as before ---
    elseif ($form === 'update_info') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password'])) {
            $infoError = "Current password is incorrect.";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
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

        if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password'])) {
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
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update->execute([$newPasswordHash, $username]);
                $user['password'] = $newPasswordHash;
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
            $newCode = storePasswordCode($pdo, $username, (string)$pending['new_password'], $CODE_EXPIRY_MINUTES);
            $html = buildPasswordCodeEmail($username, $newCode, $CODE_EXPIRY_MINUTES);
            sendLibraryEmail($user['email'], "Your Doms Library password change code", $html, true);
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
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="reader-wrap" style="max-width:520px;">
    <p style="margin:0 0 16px;"><a href="main.php">← Back to catalog</a></p>

    <div class="card" style="max-width:none;text-align:center;margin-bottom:20px;">
        <h2 style="margin-bottom:4px;">My Profile & Information</h2>
        <div class="subtitle">Manage your account details and profile picture</div>
        <?php
        $picture = profilePictureUrl($user['profile_picture'] ?? null);
        // Default avatar: ignore legacy SD./TC. role prefixes when building initials.
        $initial = defaultProfileInitials((string)$username);
        ?>
        <?php if ($requiresCodeConfirm && $picture): ?><img class="profile-avatar profile-avatar-image" src="<?= htmlspecialchars($picture) ?>" alt="Profile picture"><?php else: ?><div class="profile-avatar"><?= htmlspecialchars($initial) ?></div><?php endif; ?>
        <h3 style="margin:0 0 4px;"><?= htmlspecialchars($username) ?></h3>
        <div class="subtitle" style="margin-bottom:20px;"><?= htmlspecialchars($role) ?> account</div>
        <?php if ($requiresCodeConfirm): ?>
        <form method="post" enctype="multipart/form-data" style="text-align:left;margin-bottom:12px;">
            <?= csrf_field() ?><input type="hidden" name="form" value="upload_picture">
            <label for="profile_picture">Profile picture</label><input id="profile_picture" type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <div style="color:var(--muted);font-size:12px;margin-top:8px;">JPG, PNG, GIF, or WEBP. Maximum 3 MB.</div>
            <button type="submit" style="width:100%;">Upload / Change Picture</button>
        </form>
        <?php if ($picture): ?><form method="post" style="margin-bottom:20px;"><?= csrf_field() ?><input type="hidden" name="form" value="remove_picture"><button type="submit" class="secondary" style="width:100%;margin-top:0;">Remove Picture</button></form><?php endif; ?>
        <?php endif; ?>
        <form method="post" style="text-align:left;">
            <?= csrf_field() ?><input type="hidden" name="form" value="update_info"><label>Email</label><input type="text" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><label>Current Password</label><input type="password" name="current_password" required placeholder="Required to save changes">
            <?php if ($infoError): ?><div class="error"><?= htmlspecialchars($infoError) ?></div><?php endif; ?><?php if ($infoSuccess): ?><div class="success"><?= htmlspecialchars($infoSuccess) ?></div><?php endif; ?><button type="submit" style="width:100%;">Save Information</button>
        </form>
    </div>

    <div class="card" style="max-width:none;text-align:center;">
        <button type="button" class="secondary" onclick="document.getElementById('password-panel').hidden = !document.getElementById('password-panel').hidden;" style="width:auto;margin:0 auto 8px;padding:8px 14px;font-size:13px;">🔒 Change Password</button><div id="password-panel" hidden>
        <div class="subtitle" style="margin-bottom:20px;">
            <?= $requiresCodeConfirm
                ? "We'll email you a confirmation code before this takes effect."
                : "Admin accounts change immediately, no confirmation code needed." ?>
        </div>

        <?php if ($requiresCodeConfirm && $pendingCode): ?>
            <!-- Step 2: enter the code that was emailed -->
            <form method="post" style="text-align:left;">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="confirm_code">
                <label>Confirmation Code</label>
                <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="6-digit code" required autofocus>

                <?php if ($pwError): ?><div class="error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
                <?php if ($pwSuccess): ?><div class="success"><?= htmlspecialchars($pwSuccess) ?></div><?php endif; ?>

                <button type="submit" style="width:100%;">Confirm & Change Password</button>
            </form>
            <form method="post" style="display:flex;gap:10px;margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="resend_code">
                <button type="submit" class="secondary" style="width:100%;margin-top:0;">Resend Code</button>
            </form>
            <form method="post" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="cancel_code">
                <button type="submit" class="secondary" style="width:100%;margin-top:0;">Cancel</button>
            </form>
        <?php else: ?>
            <!-- Step 1: request the change -->
            <form method="post" style="text-align:left;">
                <?= csrf_field() ?>
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
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
