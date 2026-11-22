<?php
// Librarian only: shelf and reading progress for one user.
// Inputs: GET username.
// Tables: users, shelf, reading_progress, book (read only). Emails and passwords are never selected.

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
requireLibrarian();


// ===== Input =====
// Librarian name for the top bar; target student from the query string
$librarianUsername = $_SESSION['username'];
$targetUsername = $_GET['username'] ?? '';


// ===== Data loading =====
// Only allow viewing accounts that are actually students - no email/password ever selected
$userStmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND role = 'User'");
$userStmt->execute([$targetUsername]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Unknown user or not a student: back to the list
if (!$targetUser) {
    redirect('librarian/librarian.php');
}

// Note: email is deliberately NOT selected here - librarians see history only, not credentials
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
<!-- Top bar: brand and librarian nav -->
<div class="topbar">
    <div class="brand"><img class="brand-logo" width="34" height="34" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge"><?= htmlspecialchars($librarianUsername) ?> · Librarian</span></div>
    <nav>
        <a href="librarian.php">← Monitor Users</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1000">
    <div class="page-header">
        <h1 class="page-title">📜 Shelf &amp; Reading: <?= htmlspecialchars($targetUser['username']) ?></h1>
        <a href="librarian.php" class="btn secondary">← Back to Monitor Users</a>
    </div>

    <!-- Stat cards: shelf, in progress, finished -->
    <div class="stats-row mb-24">
        <div class="stat-card">
            <div class="stat-value"><?= $onShelf ?></div>
            <div class="stat-label">On Shelf</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $inProgress ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $finished ?></div>
            <div class="stat-label">Finished</div>
        </div>
    </div>

    <!-- Shelf and reading table (or empty-state message) -->
    <div class="admin-section">
        <div class="sub">Shelf and reading activity only - account credentials aren't visible on this page.</div>

        <?php if (empty($records)): ?>
            <div class="empty-state">This user hasn't saved or started any book yet.</div>
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