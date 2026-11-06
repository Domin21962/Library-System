<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireTeacher();

$teacherUsername = $_SESSION['username'];
$targetUsername = $_GET['username'] ?? '';

// Only allow viewing accounts that are actually students - no email/password ever selected
$userStmt = $pdo->prepare("SELECT username FROM users WHERE username = ? AND role = 'User'");
$userStmt->execute([$targetUsername]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    redirect('teacher.php');
}

// Note: email is deliberately NOT selected here - teachers see history only, not credentials
$history = $pdo->prepare(
    "SELECT b.title, br.borrow_date, br.due_date, br.return_date
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

$pageTitle = "History: {$targetUser['username']} - WebShelf";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($teacherUsername) ?> · Teacher</span></div>
    <nav>
        <a href="teacher.php">← Monitor Students</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content wrap-1000">
    <div class="page-header">
        <h1 class="page-title">📜 Borrow History: <?= htmlspecialchars($targetUser['username']) ?></h1>
        <a href="teacher.php" class="btn secondary">← Back to Monitor Students</a>
    </div>

    <div class="stats-row mb-24">
        <div class="stat-card">
            <div class="stat-value"><?= count($records) ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $currentlyBorrowed ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card <?= $overdueCount > 0 ? 'stat-danger' : '' ?>">
            <div class="stat-value <?= $overdueCount > 0 ? 'text-danger' : '' ?>"><?= $overdueCount ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <div class="admin-section">
        <div class="sub">Borrow activity only - account credentials aren't visible on this page.</div>

        <?php if (empty($records)): ?>
            <div class="empty-state">This student hasn't borrowed anything yet.</div>
        <?php else: ?>
        <table>
            <tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($records as $r): ?>
            <?php $overdue = !$r['return_date'] && !empty($r['due_date']) && strtotime($r['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
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