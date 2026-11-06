<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];

$totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$borrowedNow = (int)$pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL")->fetchColumn();
$overdueNow = (int)$pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL AND due_date IS NOT NULL AND due_date < NOW()")->fetchColumn();
$recentCount = (int)$pdo->query("SELECT COUNT(*) FROM borrow_return")->fetchColumn();

$pageTitle = "Admin Panel - WebShelf";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar admin-topbar" data-admin-dashboard="1">
    <div class="brand">⚙️ WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="main.php">📚 View Catalog</a>
        <a href="qr_login.php">QR Login</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content wrap-1200">
    <h1 class="page-title">Dashboard</h1>

    <div class="stats-row mb-28">
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $totalBooks ?></div>
            <div class="stat-label">Total Books</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $borrowedNow ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card admin-stat <?= $overdueNow > 0 ? 'stat-danger' : '' ?>">
            <div class="stat-value <?= $overdueNow > 0 ? 'text-danger' : '' ?>"><?= $overdueNow ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <div class="dash-grid">
        <a href="admin_books.php" class="dash-card">
            <div class="dash-icon">📖</div>
            <h3>Manage Books</h3>
            <div class="dash-sub">Add, edit or remove titles in the catalog.</div>
            <div class="dash-count"><?= $totalBooks ?> book<?= $totalBooks === 1 ? '' : 's' ?> in the catalog →</div>
        </a>

        <a href="admin_users.php" class="dash-card">
            <div class="dash-icon">👥</div>
            <h3>Manage Users</h3>
            <div class="dash-sub">View every registered account and remove ones you no longer need.</div>
            <div class="dash-count"><?= $totalUsers ?> registered user<?= $totalUsers === 1 ? '' : 's' ?> →</div>
        </a>

        <a href="admin_audit.php" class="dash-card">
            <div class="dash-icon">🛡️</div>
            <h3>Audit Logs</h3>
            <div class="dash-sub">Sign-ins, lockouts and other security events, with filters.</div>
            <div class="dash-count">View the log →</div>
        </a>

        <a href="admin_activity.php" class="dash-card">
            <div class="dash-icon">🕒</div>
            <h3>Recent Activity</h3>
            <div class="dash-sub">The latest borrow and return transactions, across every user.</div>
            <div class="dash-count <?= $overdueNow > 0 ? 'danger' : '' ?>">
                <?= $recentCount ?> transaction<?= $recentCount === 1 ? '' : 's' ?> logged<?= $overdueNow > 0 ? " · $overdueNow overdue" : '' ?> →
            </div>
        </a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
