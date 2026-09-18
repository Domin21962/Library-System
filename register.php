<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!isValidUsername($username)) {
        $error = "Username must follow the format: TC.Name, SD.Name, AM.Name";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match!";
    } else {
        // Equivalent of RegisterController.userExists()
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = "Username already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password]);
            $success = "Registration successful! You can now log in.";
        }
    }
}

$pageTitle = "Register - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup">
            <span class="logo-mark">📚</span> Doms Library
        </div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">
            Create an account to start browsing, borrowing, and keeping track of what you're reading.
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="card">
            <h2>Create an account</h2>
            <div class="subtitle">Username must start with SD. / TC. / AM.</div>
            <form method="post">
                <label>Username</label>
                <input type="text" name="username" placeholder="SD.Name / TC.Name / AM.Name" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                <label>Email</label>
                <input type="text" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <label>Password</label>
                <input type="password" name="password" required>
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <button type="submit" style="width:100%;">Register</button>
            </form>
            <p class="footer-link">
                Already have an account? <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
