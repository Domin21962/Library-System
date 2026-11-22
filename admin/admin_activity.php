<?php
// Admin only: the latest shelf saves and reading progress.
// Inputs: none.
// Tables: shelf, reading_progress, users, book (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
requireAdmin();


// ===== Data loading =====
$username = $_SESSION['username'];

// Latest 25 shelf saves and 25 reading updates across all users, newest first
$saved = $pdo->query(
    "SELECT u.username, b.title, s.added_at
     FROM shelf s JOIN users u ON u.id = s.user_id JOIN book b ON b.book_id = s.book_id
     ORDER BY s.added_at DESC, s.id DESC LIMIT 25"
)->fetchAll(PDO::FETCH_ASSOC);
$reads = $pdo->query(
    "SELECT u.username, b.title, rp.percent, rp.updated_at
     FROM reading_progress rp JOIN users u ON u.id = rp.user_id JOIN book b ON b.book_id = rp.book_id
     ORDER BY rp.updated_at DESC, rp.id DESC LIMIT 25"
)->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Recent Activity - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand"><img class="brand-logo" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
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

    <!-- Recently saved to a shelf -->
    <div class="admin-section">
        <div class="sub">Recently saved to a shelf (latest 25).</div>
        <?php if (empty($saved)): ?>
            <div class="empty-state">Nothing saved yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Book</th><th>Saved</th></tr>
            <?php foreach ($saved as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['added_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <!-- Recent reading progress -->
    <div class="admin-section">
        <div class="sub">Recent reading progress (latest 25).</div>
        <?php if (empty($reads)): ?>
            <div class="empty-state">Nobody has started reading yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Book</th><th>Progress</th><th>Last read</th></tr>
            <?php foreach ($reads as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= progressBar((int)$a['percent']) ?: '—' ?></td>
                <td><?= htmlspecialchars($a['updated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
