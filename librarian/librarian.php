<?php
// Librarian only: table of user accounts with their shelf and reading counts.
// Inputs: none.
// Tables: users, shelf, reading_progress (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Only librarian sessions may view this page
requireLibrarian();


// ===== Data loading =====
$username = $_SESSION['username'];

// Students only - no credentials selected at all, just enough to identify the account
// on_shelf = books saved to the shelf; in_progress = started but not finished.
// User accounts are those with role = 'User' (same rule as the history page)
$students = $pdo->query(
    "SELECT username,
     (SELECT COUNT(*) FROM shelf s WHERE s.user_id = users.id) AS on_shelf,
     (SELECT COUNT(*) FROM reading_progress rp WHERE rp.user_id = users.id AND rp.percent BETWEEN 1 AND 99) AS in_progress
     FROM users WHERE role = 'User' ORDER BY username"
)->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Monitor Users - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and librarian nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · Librarian</span></div>
    <nav>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1000">
    <h1 class="page-title">🧑‍🏫 Monitor Users</h1>

    <div class="admin-section">
        <div class="sub">
            Reading activity for every user account. Click a name to see their shelf
            and reading progress — account credentials aren't shown here.
        </div>

        <!-- Users table (or empty-state message) -->
        <?php if (empty($students)): ?>
            <div class="empty-state">No user accounts yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Books on Shelf</th><th>In Progress</th><th></th></tr>
            <?php foreach ($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><?= (int)$s['on_shelf'] ?></td>
                <td><?= (int)$s['in_progress'] ?></td>
                <!-- Link to that user's history; username is URL-encoded -->
                <td><a href="librarian_user_history.php?username=<?= urlencode($s['username']) ?>" class="btn secondary btn-small">View History →</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
