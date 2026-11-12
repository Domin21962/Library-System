<?php
// My Info page: view and edit the account email, profile picture and password.
// Access: any logged-in user. Librarians and Users confirm password changes with an emailed code and may set a picture; Admins change immediately.
// Inputs (POST): form = upload_picture, remove_picture, update_info, change_password, confirm_code, resend_code or cancel_code, plus that form's fields and _csrf.
// Tables: users (read/update), password_reset_codes (read/write); uploads go to uploads/profiles.

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
ensureUserProfileColumn($pdo);
// Reject POSTs without a valid CSRF token
enforceCsrfOnPost();
require_once __DIR__ . '/../includes/mailer.php';
// Must be logged in
requireLogin();


// ===== Request setup =====
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Only Librarian and User accounts go through the email-confirmation flow
// for password changes. (Admin keeps the old immediate-change behavior.)
$requiresCodeConfirm = in_array($role, ['Librarian', 'User'], true);

// Emailed codes last 10 minutes and allow 5 wrong guesses
$CODE_EXPIRY_MINUTES = 10;
$MAX_CODE_ATTEMPTS = 5;

// Messages shown under the info form and the password form
$infoError = '';
$infoSuccess = '';
$pwError = '';
$pwSuccess = '';


// ===== Data loading =====
// Always load the real record from the database (fixes the old bug where
// email never made it into the session - now we just read it straight from users)
$stmt = $pdo->prepare("SELECT id, username, email, password, profile_picture FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Account no longer exists: end the session
if (!$user) {
    redirect('logout.php');
}

// Clear out any expired, unused codes for this account so they don't linger
// Parameterized DELETE of this user's expired codes
$pdo->prepare("DELETE FROM password_reset_codes WHERE username = ? AND used = 0 AND expires_at < NOW()")
    ->execute([$username]);


/** Return the newest unused, unexpired password-change code row for a user, or null. */
function getPendingCode(PDO $pdo, string $username) {
    $stmt = $pdo->prepare(
        "SELECT * FROM password_reset_codes
         WHERE username = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}


/** Save a new 6-digit code with the already-hashed new password; returns the code. */
function storePasswordCode(PDO $pdo, string $username, string $passwordHash, int $expiryMinutes): string {
    // Random 6-digit code (cryptographically secure)
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $insert = $pdo->prepare(
        "INSERT INTO password_reset_codes (username, code, new_password, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    );
    $insert->execute([$username, $code, $passwordHash, $expiryMinutes]);
    return $code;
}


/** Hash the new password, store a code for it and email the code to the user. */
function sendPasswordCode(PDO $pdo, string $username, string $email, string $newPassword, int $expiryMinutes): void {
    // Only the hash is stored; the plain password is never saved
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $code = storePasswordCode($pdo, $username, $passwordHash, $expiryMinutes);
    $html = buildPasswordCodeEmail($username, $code, $expiryMinutes);
    sendLibraryEmail($email, "Your WebShelf password change code", $html, true);
}


// ===== POST handling =====
// Each form posts a hidden 'form' field that selects the branch below
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // --- Profile picture ---
    if ($form === 'upload_picture' && $requiresCodeConfirm) {
        $currentPath = profilePictureUrl($user['profile_picture'] ?? null);
        // Upload checks: a file was sent without error, and it is at most 3 MB
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) $infoError = 'Choose an image to upload.';
        elseif ((int)$_FILES['profile_picture']['size'] > 3 * 1024 * 1024) $infoError = 'The image must be 3 MB or smaller.';
        else {
            // Detect the real image type from the file contents (not the client-supplied name)
            $tmp = (string)$_FILES['profile_picture']['tmp_name']; $imageInfo = @getimagesize($tmp); $mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
            // Allow-list of image types and the extension we save them with
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
            if (!$imageInfo || !isset($allowed[$mime])) $infoError = 'Upload a valid JPG, PNG, GIF, or WEBP image.';
            else {
                // Create the upload folder if missing
                $directory = __DIR__ . '/../uploads/profiles';
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) $infoError = 'The profile image folder could not be created.';
                else {
                    // Random file name, so users cannot overwrite or guess other files
                    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime]; $relativePath = 'uploads/profiles/' . $filename;
                    // Move the file, save its path on the user, then delete the old picture
                    if (!move_uploaded_file($tmp, $directory . DIRECTORY_SEPARATOR . $filename)) $infoError = 'The image could not be saved. Check folder permissions.';
                    else { $pdo->prepare('UPDATE users SET profile_picture = ? WHERE username = ?')->execute([$relativePath, $username]); removeProfilePictureFile($currentPath); $user['profile_picture'] = $relativePath; $infoSuccess = 'Profile picture updated.'; }
                }
            }
        }
    } elseif ($form === 'remove_picture' && $requiresCodeConfirm) {
        // Clear the stored path and delete the old picture file
        $currentPath = profilePictureUrl($user['profile_picture'] ?? null); $pdo->prepare('UPDATE users SET profile_picture = NULL WHERE username = ?')->execute([$username]); removeProfilePictureFile($currentPath); $user['profile_picture'] = null; $infoSuccess = 'Profile picture removed.';
    }

    // --- Account info (email) - saved immediately, same as before ---
    elseif ($form === 'update_info') {
        // Changing the email needs the current password
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password'])) {
            $infoError = "Current password is incorrect.";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $infoError = "Email can't be empty.";
        } else {
            // Parameterized UPDATE: values are bound, never concatenated
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
        // The current password is verified against the stored hash first
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password'])) {
            $pwError = "Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $pwError = "New password and confirmation don't match.";
        } elseif (empty($user['email'])) {
            $pwError = "There's no email on file for this account, so a confirmation code can't be sent. Add an email above first.";
        } else {
            // Librarian/User: email a code; the password only changes after the code is confirmed
            if ($requiresCodeConfirm) {
                sendPasswordCode($pdo, $username, $user['email'], $newPassword, $CODE_EXPIRY_MINUTES);
                $pwSuccess = "We sent a 6-digit code to {$user['email']}. Enter it below to confirm the change.";
            } else {
                // Admin: unchanged immediate behavior
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                // Hash with PASSWORD_DEFAULT (bcrypt/argon2) before storing
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update->execute([$newPasswordHash, $username]);
                $user['password'] = $newPasswordHash;
                $pwSuccess = "Password updated.";
            }
        }
    }

    // --- Confirm the emailed code ---
    elseif ($form === 'confirm_code') {
        // Keep digits only, then compare with the pending code
        $enteredCode = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
        $pending = getPendingCode($pdo, $username);

        if (!$pending) {
            $pwError = "That code has expired. Request a new one below.";
        } elseif ($pending['attempts'] >= $MAX_CODE_ATTEMPTS) {
            $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE id = ?")->execute([$pending['id']]);
            $pwError = "Too many incorrect attempts. Request a new code.";
        } elseif ($enteredCode !== $pending['code']) {
            // Wrong code: count the attempt (limit enforced above)
            $pdo->prepare("UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$pending['id']]);
            $remaining = $MAX_CODE_ATTEMPTS - ($pending['attempts'] + 1);
            $pwError = "Incorrect code. " . max($remaining, 0) . " attempt(s) left.";
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")
                // Code matched: apply the stored password hash (the code is then marked used below)
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
            sendLibraryEmail($user['email'], "Your WebShelf password change code", $html, true);
            $pwSuccess = "We sent a new code to {$user['email']}.";
        }
    }

    // --- Cancel a pending code and go back to the request form ---
    elseif ($form === 'cancel_code') {
        $pdo->prepare("UPDATE password_reset_codes SET used = 1 WHERE username = ? AND used = 0")->execute([$username]);
    }
}

// A pending code means the page shows the 'enter your code' step
$pendingCode = $requiresCodeConfirm ? getPendingCode($pdo, $username) : null;


// ===== Page setup / header =====
$pageTitle = "My Info";
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="reader-wrap max-520">
    <p class="m-0-0-16"><a href="main.php">← Back to catalog</a></p>

    <!-- Profile card: avatar, picture upload and account info form -->
    <div class="card card-wide-center mb-20">
        <h2 class="mb-4">My Profile & Information</h2>
        <div class="subtitle">Manage your account details and profile picture</div>
        <?php
        $picture = profilePictureUrl($user['profile_picture'] ?? null);
        // Default avatar: ignore legacy SD./TC. role prefixes when building initials.
        $initial = defaultProfileInitials((string)$username);
        ?>
        <!-- Avatar: uploaded picture or initials -->
        <?php if ($requiresCodeConfirm && $picture): ?><img class="profile-avatar profile-avatar-image" src="<?= htmlspecialchars(url($picture)) ?>" alt="Profile picture"><?php else: ?><div class="profile-avatar"><?= htmlspecialchars($initial) ?></div><?php endif; ?>
        <h3 class="m-0-0-4"><?= htmlspecialchars($username) ?></h3>
        <div class="subtitle mb-20"><?= htmlspecialchars($role) ?> account</div>
        <?php if ($requiresCodeConfirm): ?>
        <!-- Picture upload form (Librarian/User only) -->
        <form class="text-left mb-12" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="form" value="upload_picture">
            <label for="profile_picture">Profile picture</label><input id="profile_picture" type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <div class="note-small mt-8">JPG, PNG, GIF, or WEBP. Maximum 3 MB.</div>
            <button class="w-full" type="submit">Upload / Change Picture</button>
        </form>
        <?php if ($picture): ?><form class="mb-20" method="post"><?= csrf_field() ?><input type="hidden" name="form" value="remove_picture"><button type="submit" class="secondary w-full mt-0">Remove Picture</button></form><?php endif; ?>
        <?php endif; ?>
        <form class="text-left" method="post">
            <!-- Account info form (email) - needs the current password -->
            <?= csrf_field() ?><input type="hidden" name="form" value="update_info"><label>Email</label><input type="text" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><label>Current Password</label><input type="password" name="current_password" required placeholder="Required to save changes">
            <?php if ($infoError): ?><div class="error"><?= htmlspecialchars($infoError) ?></div><?php endif; ?><?php if ($infoSuccess): ?><div class="success"><?= htmlspecialchars($infoSuccess) ?></div><?php endif; ?><button class="w-full" type="submit">Save Information</button>
        </form>
    </div>

    <!-- Password card: button toggles the panel below -->
    <div class="card card-wide-center">
        <button type="button" class="secondary btn-small-center" onclick="document.getElementById('password-panel').hidden = !document.getElementById('password-panel').hidden;">🔒 Change Password</button><div id="password-panel" hidden>
        <div class="subtitle mb-20">
            <?= $requiresCodeConfirm
                ? "We'll email you a confirmation code before this takes effect."
                : "Admin accounts change immediately, no confirmation code needed." ?>
        </div>

        <!-- Shows step 2 (enter code) when a code is pending, else step 1 (request change) -->
        <?php if ($requiresCodeConfirm && $pendingCode): ?>
            <!-- Step 2: enter the code that was emailed -->
            <form class="text-left" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="confirm_code">
                <label>Confirmation Code</label>
                <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="6-digit code" required autofocus>

                <?php if ($pwError): ?><div class="error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
                <?php if ($pwSuccess): ?><div class="success"><?= htmlspecialchars($pwSuccess) ?></div><?php endif; ?>

                <button class="w-full" type="submit">Confirm & Change Password</button>
            </form>
            <form class="row-gap-10 mt-10" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="resend_code">
                <button type="submit" class="secondary w-full mt-0">Resend Code</button>
            </form>
            <form class="mt-10" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="cancel_code">
                <button type="submit" class="secondary w-full mt-0">Cancel</button>
            </form>
        <?php else: ?>
            <!-- Step 1: request the change -->
            <form class="text-left" method="post">
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

                <button class="w-full" type="submit"><?= $requiresCodeConfirm ? 'Send Confirmation Code' : 'Change Password' ?></button>
            </form>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>