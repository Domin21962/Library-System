<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        // Equivalent of LoginController.loginUser() - matches Java's plaintext comparison
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Equivalent of LoginController.getEmailByUsername() - fetched but (as in the
            // original) never actually saved into the session, so Student Info's email
            // field will appear blank, matching the original Session.java bug.
            $stmt2 = $pdo->prepare("SELECT email FROM users WHERE username = ?");
            $stmt2->execute([$username]);
            $emailRow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $email = $emailRow['email'] ?? '';

            $_SESSION['username'] = $username;
            $_SESSION['role'] = getUserRole($username);

            $role = $_SESSION['role'];
            if ($role === 'Admin') {
                redirect('admin.php');
            } elseif ($role === 'Teacher') {
                redirect('teacher.php');
            } else {
                redirect('main.php');
            }
        } else {
            $error = "Invalid username or password!";
        }
    }
}

$pageTitle = "Login - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <div class="auth-illustration-panel">
        <div class="auth-brand-lockup">
            <span class="logo-mark">📚</span> Doms Library
        </div>
        <?php include __DIR__ . '/includes/library_illustration.svg'; ?>
        <div class="auth-tagline">
            Borrow, read, and return books all in one place — your shelf, wherever you are.
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="card">
            <h2>Welcome back</h2>
            <div class="subtitle">Log in to your Doms Library account</div>
            <form method="post">
                <label>Username</label>
                <input type="text" name="username" placeholder="e.g. SD.Juan" required>
                <label>Password</label>
                <input type="password" name="password" required>
                <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <button type="submit" style="width:100%;">Login</button>
            </form>
            <a class="btn secondary" href="qr_login.php" style="width:100%;text-align:center;margin-top:10px;">▣ Login with QR Code</a>
            <p class="footer-link">
                No account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
