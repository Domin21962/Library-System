<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$username = $_SESSION['username'];
// Note: matches the original app's behavior - Session.setCurrentEmail() was
// never actually called anywhere in the Java code, so this field is always blank.
$email = $_SESSION['email'] ?? '';

$pageTitle = "Student Info";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="card">
        <h2>My Info</h2>
        <div class="subtitle">Account details</div>
        <label>Username</label>
        <input type="text" value="<?= htmlspecialchars($username) ?>" readonly>
        <label>Email</label>
        <input type="text" value="<?= htmlspecialchars($email) ?>" readonly>
        <label>Password</label>
        <input type="password" value="********" readonly>
        <p style="margin-top:20px;"><a href="main.php" class="btn secondary">← Back</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
