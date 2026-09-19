<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
enforceCsrfOnPost();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!isValidUsername($username)) {
        $error = "Username must be 3-100 characters and may contain letters, numbers, spaces, dots, underscores, or hyphens.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($username) > 100 || strlen($password) > 255) {
        $error = "Input is too long.";
    } elseif ($password === '') {
        $error = "Password cannot be empty.";
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
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$username, $email, $passwordHash]);
            auditLog($pdo, 'account_registered', $username);
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
            <div class="subtitle">Choose any unique username. New accounts are registered as Student accounts. Account roles are assigned by an administrator.</div>
            <form method="post">
                <?= csrf_field() ?>
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
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
