<?php
// Admin only: list of all accounts with role, link to history, and delete button.
// Inputs: POST action=delete_user | set_role | add_account, target_username, role, username, email, password, _csrf.
// Tables: users (delete, update role, insert, read), shelf and reading_progress (via admin_user_history link only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Reject bad CSRF tokens on POST, then require an admin session
enforceCsrfOnPost();
requireAdmin();


// ===== State =====
// Current admin name and flash messages
$username = $_SESSION['username'];
$notice = '';
$error = '';


// ===== POST handling: delete an account =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $targetUser = $_POST['target_username'] ?? '';
    // Safety check: an admin cannot delete the account they are logged in with
    if ($targetUser === $username) {
        $error = "You can't delete your own account while logged in.";
    } else {
        // Prepared delete by username
        // Admin accounts can never be deleted from this page
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ? AND role <> 'Admin'");
        $stmt->execute([$targetUser]);
        $notice = $stmt->rowCount() > 0 ? "Account \"$targetUser\" removed." : "Account not found.";
    }
}


// ===== POST handling: change a role (User <-> Librarian) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_role') {
    $targetUser = $_POST['target_username'] ?? '';
    $newRole = $_POST['role'] ?? '';
    if ($targetUser === $username) {
        $error = "You can't change your own role.";
    } elseif (!in_array($newRole, ['User', 'Librarian'], true)) {
        $error = "Invalid role.";
    } else {
        // Admin accounts are never touched from this page
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE username = ? AND role <> 'Admin'");
        $stmt->execute([$newRole, $targetUser]);
        $notice = "\"$targetUser\" is now a $newRole.";
        auditLog($pdo, 'role_changed', $username, ['target' => $targetUser, 'role' => $newRole]);
    }
}


// ===== POST handling: create an account (User or Librarian) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_account') {
    $newName = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPass = $_POST['password'] ?? '';
    $newRole = $_POST['role'] ?? 'User';
    if (!isValidUsername($newName)) {
        $error = "Username must be 3-100 characters and may contain letters, numbers, spaces, dots, underscores, or hyphens.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 190) {
        $error = "Please enter a valid email address.";
    } elseif ($newPass === '' || strlen($newPass) > 255) {
        $error = "Password is required (max 255 characters).";
    } elseif (!in_array($newRole, ['User', 'Librarian'], true)) {
        $error = "Invalid role.";
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$newName]);
        if ($stmt->fetch()) {
            $error = "Username already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newName, $newEmail, password_hash($newPass, PASSWORD_DEFAULT), $newRole]);
            auditLog($pdo, 'account_created_by_admin', $username, ['target' => $newName, 'role' => $newRole]);
            $notice = "$newRole account \"$newName\" created.";
        }
    }
}


// ===== Data loading =====
// Admin accounts are excluded from this list entirely
$users = $pdo->query("SELECT id, username, email FROM users WHERE role <> 'Admin' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Manage Users - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand"><img class="brand-logo" width="34" height="34" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="admin.php">← Dashboard</a>
        <a href="<?= url('user/main.php') ?>">📚 View Catalog</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1200">
    <div class="page-header">
        <h1 class="page-title">👥 Manage Users</h1>
        <a href="admin.php" class="btn secondary">← Back to Dashboard</a>
    </div>

    <!-- Flash messages -->
    <?php if ($notice): ?><div class="success mb-16"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error mb-16"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Create an account -->
    <div class="admin-section">
        <h2 class="section-heading">Add account</h2>
        <form method="post" class="add-account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_account">
            <input type="text" name="username" placeholder="Username" required maxlength="100">
            <input type="email" name="email" placeholder="Email" required maxlength="190">
            <input type="password" name="password" placeholder="Password" required maxlength="255" autocomplete="new-password">
            <select name="role"><option>User</option><option>Librarian</option></select>
            <button type="submit">Create</button>
        </form>
    </div>

    <div class="admin-section">
        <div class="sub">Everyone registered in WebShelf. Change a role with the dropdown; self-registered accounts start as User.</div>
        <!-- Users table -->
        <table>
            <tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th></th></tr>
            <?php foreach ($users as $u): ?>
            <!-- Role is derived from the username/role by getUserRole() -->
            <?php $r = getUserRole($u['username']); ?>
            <tr>
                <td class="mono-muted">USR-<?= str_pad((string)$u['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td>
                    <!-- Username links to shelf and reading history (not for admins) -->
                    <?php if ($r !== 'Admin'): ?>
                        <a href="admin_user_history.php?username=<?= urlencode($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($u['username']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php if ($u['username'] !== $username): ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                        <select name="role" onchange="this.form.submit()">
                            <?php foreach (['User', 'Librarian'] as $opt): ?>
                            <option<?= $r === $opt ? ' selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <span class="role-pill role-<?= strtolower($r) ?>"><?= htmlspecialchars($r) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Delete form with confirmation prompt and CSRF token; hidden for your own row -->
                    <?php if ($u['username'] !== $username): ?>
                    <form method="post" class="confirm-delete d-inline" data-message="Delete the account &quot;<?= htmlspecialchars(addslashes($u['username'])) ?>&quot;? This can't be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                    <?php else: ?>
                        <span class="note-small">(you)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
