<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $targetUser = $_POST['target_username'] ?? '';
    if ($targetUser === $username) {
        $error = "You can't delete your own account while logged in.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$targetUser]);
        $notice = $stmt->rowCount() > 0 ? "Account \"$targetUser\" removed." : "Account not found.";
    }
}

// Admin accounts are excluded from this list entirely
$users = $pdo->query("SELECT id, username, email FROM users WHERE username NOT LIKE 'AM.%' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Users - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar admin-topbar">
    <div class="brand">⚙️ Doms Library <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="admin.php">← Dashboard</a>
        <a href="main.php">📚 View Catalog</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1200px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">👥 Manage Users</h1>
        <a href="admin.php" class="btn secondary">← Back to Dashboard</a>
    </div>

    <?php if ($notice): ?><div class="success" style="margin-bottom:16px;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="admin-section">
        <div class="sub">Everyone registered in Doms Library. Role is determined by the username prefix.</div>
        <table>
            <tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th></th></tr>
            <?php foreach ($users as $u): ?>
            <?php $r = getUserRole($u['username']); ?>
            <tr>
                <td style="color:var(--muted);font-family:monospace;">USR-<?= str_pad((string)$u['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td>
                    <?php if ($r !== 'Admin'): ?>
                        <a href="admin_user_history.php?username=<?= urlencode($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($u['username']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="role-pill role-<?= strtolower($r) ?>"><?= htmlspecialchars($r) ?></span></td>
                <td>
                    <?php if ($u['username'] !== $username): ?>
                    <form method="post" class="confirm-delete" data-message="Delete the account &quot;<?= htmlspecialchars(addslashes($u['username'])) ?>&quot;? This can't be undone." style="display:inline;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:12px;">(you)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
