<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireTeacher();

$username = $_SESSION['username'];

// Students only - no credentials selected at all, just enough to identify the account
$students = $pdo->query(
    "SELECT username,
     (SELECT COUNT(*) FROM borrow_return br WHERE br.username = users.username) AS total_borrows,
     (SELECT COUNT(*) FROM borrow_return br WHERE br.username = users.username AND br.return_date IS NULL) AS active_borrows
     FROM users WHERE username LIKE 'SD.%' ORDER BY username"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Monitor Students - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Doms Library <span class="badge"><?= htmlspecialchars($username) ?> · Teacher</span></div>
    <nav>
        <a href="qr_login.php">QR Login</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1000px;margin:0 auto;">
    <h1 class="page-title">🧑‍🏫 Monitor Students</h1>

    <div class="admin-section">
        <div class="sub">
            Reading activity for every student account. Click a name to see their borrow
            history — account credentials aren't shown here.
        </div>

        <?php if (empty($students)): ?>
            <div class="empty-state">No student accounts yet.</div>
        <?php else: ?>
        <table>
            <tr><th>Student</th><th>Total Borrows</th><th>Currently Borrowed</th><th></th></tr>
            <?php foreach ($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><?= (int)$s['total_borrows'] ?></td>
                <td><?= (int)$s['active_borrows'] ?></td>
                <td><a href="teacher_student_history.php?username=<?= urlencode($s['username']) ?>" class="btn secondary" style="margin-top:0;padding:6px 14px;font-size:13px;">View History →</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
