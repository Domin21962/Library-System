<?php
// Admin only: shelf and reading progress for one non-admin user.
// Inputs: GET username.
// Tables: users, shelf, reading_progress, book (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


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
    redirect('admin/admin_users.php');
}

// Look up the role so admin accounts can be excluded
$targetRole = getUserRole($targetUser['username']);

// Admin accounts' history isn't shown here - bounce back to the list
if ($targetRole === 'Admin') {
    redirect('admin/admin_users.php');
}

// Books this user saved or started reading, most recent activity first
$userId = (int)$targetUser['id'];
$history = $pdo->prepare(
    "SELECT b.title, s.added_at, COALESCE(rp.percent, 0) AS percent, rp.updated_at
     FROM book b
     LEFT JOIN shelf s ON s.book_id = b.book_id AND s.user_id = ?
     LEFT JOIN reading_progress rp ON rp.book_id = b.book_id AND rp.user_id = ?
     WHERE s.id IS NOT NULL OR rp.id IS NOT NULL
     ORDER BY COALESCE(rp.updated_at, s.added_at) DESC"
);
$history->execute([$userId, $userId]);
$records = $history->fetchAll(PDO::FETCH_ASSOC);

// Counts for the stat cards
$onShelf = count(array_filter($records, fn($r) => !empty($r['added_at'])));
$inProgress = count(array_filter($records, fn($r) => $r['percent'] > 0 && $r['percent'] < 100));
$finished = count(array_filter($records, fn($r) => $r['percent'] >= 100));


// ===== Page setup / header =====
$pageTitle = "History: {$targetUser['username']} - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand"><img class="brand-logo" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge">Admin · <?= htmlspecialchars($adminUsername) ?></span></div>
    <nav>
        <a href="admin_users.php">← Manage Users</a>
        <a href="admin.php">Dashboard</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1000">
    <div class="page-header">
        <!-- Page title with role pill and padded user ID -->
        <h1 class="page-title">
            📜 Shelf &amp; Reading: <?= htmlspecialchars($targetUser['username']) ?>
            <span class="role-pill role-<?= strtolower($targetRole) ?> ml-8-middle"><?= htmlspecialchars($targetRole) ?></span>
            <span class="mono-muted-inline">USR-<?= str_pad((string)$targetUser['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </h1>
        <a href="admin_users.php" class="btn secondary">← Back to Manage Users</a>
    </div>

    <!-- Stat cards: shelf, in progress, finished -->
    <div class="stats-row mb-24">
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $onShelf ?></div>
            <div class="stat-label">On Shelf</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $inProgress ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $finished ?></div>
            <div class="stat-label">Finished</div>
        </div>
    </div>

    <!-- Shelf and reading table (or empty-state message) -->
    <div class="admin-section">
        <div class="sub">Email on file: <?= htmlspecialchars($targetUser['email']) ?></div>

        <?php if (empty($records)): ?>
            <div class="empty-state">This account hasn't saved or started any book yet.</div>
        <?php else: ?>
        <table>
            <tr><th>Book</th><th>Saved to shelf</th><th>Progress</th><th>Last read</th></tr>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= !empty($r['added_at']) ? htmlspecialchars($r['added_at']) : '—' ?></td>
                <td><?= $r['percent'] > 0 ? progressBar((int)$r['percent']) : '—' ?></td>
                <td><?= !empty($r['updated_at']) ? htmlspecialchars($r['updated_at']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
