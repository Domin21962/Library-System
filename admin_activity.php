<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];

$activity = $pdo->query(
    "SELECT br.username, b.title, br.borrow_date, br.due_date, br.return_date
     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
     ORDER BY br.id DESC LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Recent Activity - Doms Library";
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
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <h1 class="page-title" style="margin-bottom:0;">🕒 Recent Activity</h1>
        <a href="admin.php" class="btn secondary" style="margin-top:0;">← Back to Dashboard</a>
    </div>

    <div class="admin-section">
        <div class="sub">The latest borrow and return transactions, across every user (most recent 50).</div>
        <?php if (empty($activity)): ?>
            <div class="empty-state">No activity yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($activity as $a): ?>
            <?php $overdue = !$a['return_date'] && !empty($a['due_date']) && strtotime($a['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['borrow_date']) ?></td>
                <td style="<?= $overdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
                    <?= !empty($a['due_date']) ? htmlspecialchars($a['due_date']) : '—' ?><?= $overdue ? ' (overdue)' : '' ?>
                </td>
                <td><?= $a['return_date'] ? htmlspecialchars($a['return_date']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
