<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$error = '';
$success = '';

// Always load the real record from the database (fixes the old bug where
// email never made it into the session - now we just read it straight from users)
$stmt = $pdo->prepare("SELECT email, password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEmail = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $currentPassword !== $user['password']) {
        $error = "Current password is incorrect.";
    } elseif ($newEmail === '') {
        $error = "Email can't be empty.";
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = "New password and confirmation don't match.";
    } else {
        $passwordToSave = $newPassword !== '' ? $newPassword : $user['password'];

        $update = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE username = ?");
        $update->execute([$newEmail, $passwordToSave, $username]);

        $user['email'] = $newEmail;
        $user['password'] = $passwordToSave;
        $success = $newPassword !== '' ? "Info and password updated." : "Info updated.";
    }
}

$pageTitle = "My Info";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Library System <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="reader-wrap" style="max-width:520px;">
    <p style="margin:0 0 16px;"><a href="main.php">← Back to catalog</a></p>

    <div class="card" style="max-width:none;text-align:center;">
        <div class="profile-avatar"><?= strtoupper(substr($username, 3, 1) ?: 'U') ?></div>
        <h2 style="margin-bottom:2px;"><?= htmlspecialchars($username) ?></h2>
        <div class="subtitle" style="margin-bottom:24px;"><?= htmlspecialchars($role) ?> account</div>

        <form method="post" style="text-align:left;">
            <label>Email</label>
            <input type="text" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <div class="profile-divider">Change password <span>(optional)</span></div>

            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Leave blank to keep current password">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat new password">

            <div class="profile-divider">Confirm it's you</div>

            <label>Current Password</label>
            <input type="password" name="current_password" required placeholder="Required to save any change">

            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <button type="submit" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
