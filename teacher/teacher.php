<?php
// Teacher only: table of student accounts with their borrow counts.
// Inputs: none.
// Tables: users, borrow_return (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Only teacher sessions may view this page
requireTeacher();


// ===== Data loading =====
$username = $_SESSION['username'];

// Students only - no credentials selected at all, just enough to identify the account
// total_borrows = every transaction; active_borrows = not yet returned.
// Student accounts are those whose username starts with 'SD.'
$students = $pdo->query(
    "SELECT username,
     (SELECT COUNT(*) FROM borrow_return br WHERE br.username = users.username) AS total_borrows,
     (SELECT COUNT(*) FROM borrow_return br WHERE br.username = users.username AND br.return_date IS NULL) AS active_borrows
     FROM users WHERE username LIKE 'SD.%' ORDER BY username"
)->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Monitor Students - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and teacher nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · Teacher</span></div>
    <nav>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1000">
    <h1 class="page-title">🧑‍🏫 Monitor Students</h1>

    <div class="admin-section">
        <div class="sub">
            Reading activity for every student account. Click a name to see their borrow
            history — account credentials aren't shown here.
        </div>

        <!-- Students table (or empty-state message) -->
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
                <!-- Link to that student's history; username is URL-encoded -->
                <td><a href="teacher_student_history.php?username=<?= urlencode($s['username']) ?>" class="btn secondary btn-small">View History →</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
