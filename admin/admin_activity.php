<?php
// Admin only: table of the 50 most recent borrow/return transactions.
// Inputs: none.
// Tables: borrow_return, book (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
requireAdmin();


// ===== Data loading =====
$username = $_SESSION['username'];

// Latest 50 transactions across all users, newest first
$activity = $pdo->query(
    "SELECT br.username, b.title, br.borrow_date, br.due_date, br.return_date
     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
     ORDER BY br.id DESC LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Recent Activity - WebShelf";
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
        <h1 class="page-title">🕒 Recent Activity</h1>
        <a href="admin.php" class="btn secondary">← Back to Dashboard</a>
    </div>

    <div class="admin-section">
        <div class="sub">The latest borrow and return transactions, across every user (most recent 50).</div>
        <!-- Activity table (or empty-state message) -->
        <?php if (empty($activity)): ?>
            <div class="empty-state">No activity yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($activity as $a): ?>
            <!-- Row is overdue when unreturned and past its due date -->
            <?php $overdue = !$a['return_date'] && !empty($a['due_date']) && strtotime($a['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['borrow_date']) ?></td>
                <td class="<?= $overdue ? 'text-overdue' : '' ?>">
                    <?= !empty($a['due_date']) ? htmlspecialchars($a['due_date']) : '—' ?><?= $overdue ? ' (overdue)' : '' ?>
                </td>
                <td><?= $a['return_date'] ? htmlspecialchars($a['return_date']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
