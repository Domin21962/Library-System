<?php
// Admin dashboard: summary counters and links to the admin tools.
// Who: Admin only.
// Inputs: none (session user).
// Tables: book, users, shelf, reading_progress (read only).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Only logged-in Admins may continue
requireAdmin();


// ===== Data loading =====
$username = $_SESSION['username'];

// Counters for the stat cards and dashboard links
$totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
// Books saved to shelves (all users) and books currently being read (started, not finished)
$shelved = (int)$pdo->query("SELECT COUNT(*) FROM shelf")->fetchColumn();
$reading = (int)$pdo->query("SELECT COUNT(*) FROM reading_progress WHERE percent BETWEEN 1 AND 99")->fetchColumn();


// ===== Page setup / header =====
$pageTitle = "Admin Panel - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar" data-admin-dashboard="1">
    <div class="brand">⚙️ WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="<?= url('user/main.php') ?>">📚 View Catalog</a>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1200">
    <h1 class="page-title">Dashboard</h1>

    <!-- Stat cards -->
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
            <div class="stat-value"><?= $shelved ?></div>
            <div class="stat-label">Books on Shelves</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $reading ?></div>
            <div class="stat-label">Being Read</div>
        </div>
    </div>

    <!-- Dashboard link cards -->
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
            <div class="dash-sub">The latest shelf saves and reading progress, across every user.</div>
            <div class="dash-count"><?= $shelved ?> saved · <?= $reading ?> being read →</div>
        </a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
