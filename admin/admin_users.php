<?php
// Admin only: list of all accounts with role, link to history, and delete button.
// Inputs: POST action=delete_user, target_username, _csrf.
// Tables: users (delete, read), borrow_return (via admin_user_history link only).

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
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$targetUser]);
        $notice = $stmt->rowCount() > 0 ? "Account \"$targetUser\" removed." : "Account not found.";
    }
}


// ===== Data loading =====
// Admin accounts are excluded from this list entirely
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Manage Users - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand">⚙️ WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
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

    <div class="admin-section">
        <div class="sub">Everyone registered in WebShelf. Legacy prefixes are supported; new accounts default to Student.</div>
        <!-- Users table -->
        <table>
            <tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th></th></tr>
            <?php foreach ($users as $u): ?>
            <!-- Role is derived from the username/role by getUserRole() -->
            <?php $r = getUserRole($u['username']); ?>
            <tr>
                <td class="mono-muted">USR-<?= str_pad((string)$u['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td>
                    <!-- Username links to borrow history (not for admins) -->
                    <?php if ($r !== 'Admin'): ?>
                        <a href="admin_user_history.php?username=<?= urlencode($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($u['username']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="role-pill role-<?= strtolower($r) ?>"><?= htmlspecialchars($r) ?></span></td>
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
