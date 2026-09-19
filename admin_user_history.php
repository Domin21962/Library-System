<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$adminUsername = $_SESSION['username'];
$targetUsername = $_GET['username'] ?? '';

// Confirm the target account exists
$userStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ?");
$userStmt->execute([$targetUsername]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    redirect('admin_users.php');
}

$targetRole = getUserRole($targetUser['username']);

// Admin accounts' history isn't shown here - bounce back to the list
if ($targetRole === 'Admin') {
    redirect('admin_users.php');
}

$history = $pdo->prepare(
    "SELECT b.title, br.email, br.borrow_date, br.due_date, br.return_date
     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
     WHERE br.username = ?
     ORDER BY br.id DESC"
);
$history->execute([$targetUsername]);
$records = $history->fetchAll(PDO::FETCH_ASSOC);

$currentlyBorrowed = 0;
$overdueCount = 0;
foreach ($records as $r) {
    if (!$r['return_date']) {
        $currentlyBorrowed++;
        if (!empty($r['due_date']) && strtotime($r['due_date']) < time()) {
            $overdueCount++;
        }
    }
}

$pageTitle = "History: {$targetUser['username']} - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar admin-topbar">
    <div class="brand">⚙️ Doms Library <span class="badge">Admin · <?= htmlspecialchars($adminUsername) ?></span></div>
    <nav>
        <a href="admin_users.php">← Manage Users</a>
        <a href="admin.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1000px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">
            📜 Borrow History: <?= htmlspecialchars($targetUser['username']) ?>
            <span class="role-pill role-<?= strtolower($targetRole) ?>" style="margin-left:8px;vertical-align:middle;"><?= htmlspecialchars($targetRole) ?></span>
            <span style="color:var(--muted);font-family:monospace;font-size:13px;font-weight:400;margin-left:8px;">USR-<?= str_pad((string)$targetUser['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </h1>
        <a href="admin_users.php" class="btn secondary">← Back to Manage Users</a>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= count($records) ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $currentlyBorrowed ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card admin-stat" style="<?= $overdueCount > 0 ? 'border-top-color:var(--danger) !important;' : '' ?>">
            <div class="stat-value" style="<?= $overdueCount > 0 ? 'color:var(--danger);' : '' ?>"><?= $overdueCount ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <div class="admin-section">
        <div class="sub">Email on file: <?= htmlspecialchars($targetUser['email']) ?></div>

        <?php if (empty($records)): ?>
            <div class="empty-state">This account hasn't borrowed anything yet.</div>
        <?php else: ?>
        <table>
            <tr><th>Book</th><th>Email Used</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($records as $r): ?>
            <?php $overdue = !$r['return_date'] && !empty($r['due_date']) && strtotime($r['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['borrow_date']) ?></td>
                <td style="<?= $overdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
                    <?= !empty($r['due_date']) ? htmlspecialchars($r['due_date']) : '—' ?><?= $overdue ? ' (overdue)' : '' ?>
                </td>
                <td><?= $r['return_date'] ? htmlspecialchars($r['return_date']) : '<span class="badge-status badge-borrowed">Not returned</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
