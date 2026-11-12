<?php
// Login step 2: the user enters the 6-digit code emailed to them to finish signing in.
// Access: public, but only usable after login.php has stored a pending login in the session.
// Inputs (POST): code, _csrf. No GET inputs.
// Tables: users (read); login confirmation codes via verifyLoginConfirmation(); audit log (write).

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Access check =====
// Reject POSTs without a valid CSRF token
enforceCsrfOnPost();


// ===== Pending login state =====
$error = '';
// Set by login.php after the password was accepted but before the code is confirmed
$username = (string)($_SESSION['pending_login_username'] ?? '');
$pendingId = (int)($_SESSION['pending_login_id'] ?? 0);
// No pending login in this session: send the user back to the login form
if ($username === '' || $pendingId <= 0) {
    redirect('login.php');
}


// ===== Data loading =====
// Load the account details shown on the confirmation screen.
// The profile picture is optional, so the default initials avatar is used when no picture exists.
// Make sure users.profile_picture exists before selecting it
ensureUserProfileColumn($pdo);
$userStmt = $pdo->prepare('SELECT username, email, profile_picture FROM users WHERE username = ? LIMIT 1');
$userStmt->execute([$username]);
$pendingUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$displayUsername = (string)($pendingUser['username'] ?? $username);
$displayEmail = (string)($pendingUser['email'] ?? '');
// Avatar URL (or null) and fallback initials for the confirmation card
$profilePicture = profilePictureUrl($pendingUser['profile_picture'] ?? null);
$profileInitials = defaultProfileInitials($displayUsername);


/** Hide most of an email address for display, e.g. j***@example.com. */
function maskConfirmationEmail(string $email): string {
    $email = trim($email);
    // Not a valid address: show a generic label instead
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'your registered email';
    }

    [$localPart, $domain] = explode('@', $email, 2);
    // Keep only the first character of the local part (multibyte safe when mbstring is available)
    $firstCharacter = function_exists('mb_substr')
        ? mb_substr($localPart, 0, 1)
        : substr($localPart, 0, 1);

    // Escape both parts because the result is printed as raw HTML later
    return htmlspecialchars($firstCharacter, ENT_QUOTES, 'UTF-8')
        . '***@'
        . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
}


// Masked address shown on the page
$maskedEmail = maskConfirmationEmail($displayEmail);


// ===== POST handling (verify the code) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Code must be exactly 6 digits before we even check it
    $code = trim((string)($_POST['code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Enter the 6-digit code from your email.';
    } else {
        // Checks the code against the stored one (expiry and attempt limit handled inside)
        $result = verifyLoginConfirmation($pdo, $pendingId, $username, $code);
        if (!$result['ok']) {
            // Map the failure reason to a user-facing message
            $error = match ($result['reason']) {
                'expired' => 'This code has expired. Please log in again.',
                'locked' => 'Too many incorrect codes. Please log in again.',
                default => 'Incorrect confirmation code.'
            };
            // Log the failed attempt (reason only, never the code)
            auditLog($pdo, 'login_confirmation_failed', $username, ['reason' => $result['reason']]);
        } else {
            // Success: new session id prevents session fixation
            session_regenerate_id(true);
            // Log the user in and remember their role
            $_SESSION['username'] = $username;
            $_SESSION['role'] = getUserRole($username, $pdo);
            // Drop the pending-login markers now that login is complete
            unset($_SESSION['pending_login_id'], $_SESSION['pending_login_username'], $_SESSION['pending_login_role']);
            // Record the successful, email-confirmed login
            auditLog($pdo, 'login_success', $username, ['email_confirmed' => true]);
            // Send each role to its home page
            if ($_SESSION['role'] === 'Admin') redirect('admin/admin.php');
            if ($_SESSION['role'] === 'Librarian') redirect('librarian/librarian.php');
            redirect('user/main.php');
        }
    }
}


// ===== Page setup / header =====
$pageTitle = 'Confirm Login - WebShelf';
include __DIR__ . '/includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Split layout: illustration on the left, form on the right -->
<div class="auth-split">
    <!-- Brand, illustration and tagline -->
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup"><span class="logo-mark">📚</span> WebShelf</div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">One more step to protect your library account.</div>
    </div>
    <!-- Confirmation card -->
    <div class="auth-form-panel">
        <div class="card">
            <h2>Is this you?</h2>
            <div class="subtitle">Check your account details before entering the confirmation code.</div>

            <!-- Account summary: avatar (or initials), username and masked email -->
            <div class="login-confirm-profile" aria-label="Account confirmation details">
                <div class="login-confirm-avatar">
                    <?php if ($profilePicture): ?>
                        <img src="<?= htmlspecialchars(url($profilePicture), ENT_QUOTES, 'UTF-8') ?>" alt="Profile picture">
                    <?php else: ?>
                        <?= htmlspecialchars($profileInitials, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <p class="login-confirm-name"><?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="login-confirm-email">Email: <?= $maskedEmail ?></p>
            </div>

            <p class="login-confirm-question">Is this your account?</p>
            <div class="subtitle">We sent a 6-digit confirmation code to your registered email. Enter it below to finish signing in.</div>
            <!-- Code entry form (CSRF token included) -->
            <form method="post">
                <?= csrf_field() ?>
                <label>Email confirmation code</label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <button class="w-full" type="submit">Confirm and Login</button>
            </form>
            <!-- Cancel: logout.php clears the pending login -->
            <p class="footer-link"><a href="logout.php">Cancel and return to login</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
