<?php
// Admin only: borrow history and stats for one non-admin user.
// Inputs: GET username.
// Tables: users, borrow_return, book (read only).

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Access check =====
requireAdmin();


// ===== Input =====
// Admin name for the top bar; target account from the query string
$adminUsername = $_SESSION['username'];
$targetUsername = $_GET['username'] ?? '';


// ===== Data loading =====
// Confirm the target account exists
$userStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ?");
$userStmt->execute([$targetUsername]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    redirect('admin_users.php');
}

// Look up the role so admin accounts can be excluded
$targetRole = getUserRole($targetUser['username']);

// Admin accounts' history isn't shown here - bounce back to the list
if ($targetRole === 'Admin') {
    redirect('admin_users.php');
}

// All transactions for this user, newest first (joins book title and user email)
$history = $pdo->prepare(
    "SELECT b.title, u.email, br.borrow_date, br.due_date, br.return_date
     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
     JOIN users u ON u.username = br.username
     WHERE br.username = ?
     ORDER BY br.id DESC"
);
$history->execute([$targetUsername]);
$records = $history->fetchAll(PDO::FETCH_ASSOC);


// Count unreturned books, and how many of those are past due
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


// ===== Page setup / header =====
$pageTitle = "History: {$targetUser['username']} - WebShelf";
include __DIR__ . '/includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand">⚙️ WebShelf <span class="badge">Admin · <?= htmlspecialchars($adminUsername) ?></span></div>
    <nav>
        <a href="admin_users.php">← Manage Users</a>
        <a href="admin.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1000">
    <div class="page-header">
        <!-- Page title with role pill and padded user ID -->
        <h1 class="page-title">
            📜 Borrow History: <?= htmlspecialchars($targetUser['username']) ?>
            <span class="role-pill role-<?= strtolower($targetRole) ?> ml-8-middle"><?= htmlspecialchars($targetRole) ?></span>
            <span class="mono-muted-inline">USR-<?= str_pad((string)$targetUser['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </h1>
        <a href="admin_users.php" class="btn secondary">← Back to Manage Users</a>
    </div>

    <!-- Stat cards: total, currently borrowed, overdue -->
    <div class="stats-row mb-24">
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= count($records) ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $currentlyBorrowed ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card admin-stat <?= $overdueCount > 0 ? 'stat-danger' : '' ?>">
            <div class="stat-value <?= $overdueCount > 0 ? 'text-danger' : '' ?>"><?= $overdueCount ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <!-- History table (or empty-state message) -->
    <div class="admin-section">
        <div class="sub">Email on file: <?= htmlspecialchars($targetUser['email']) ?></div>

        <?php if (empty($records)): ?>
            <div class="empty-state">This account hasn't borrowed anything yet.</div>
        <?php else: ?>
        <table>
            <tr><th>Book</th><th>Email Used</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($records as $r): ?>
            <!-- Overdue = unreturned and past its due date -->
            <?php $overdue = !$r['return_date'] && !empty($r['due_date']) && strtotime($r['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['borrow_date']) ?></td>
                <td class="<?= $overdue ? 'text-overdue' : '' ?>">
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
