<?php
/**
 * Public landing page with live catalog stats; signed-in users are redirected.
 * Who: public (logged-in users are forwarded to admin.php or main.php).
 * Input: none.
 * Tables: book (counts, genres), users (count).
 */


// ===== Requires =====
// Public landing page. Signed-in users skip it and go straight to their home page.
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Redirect signed-in users =====
if (!empty($_SESSION['username'])) {
    redirect(($_SESSION['role'] ?? '') === 'Admin' ? 'admin/admin.php' : 'user/main.php');
}


// ===== Load stats for the page =====
// Live numbers from the database, so the page reflects the real catalog.
$totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$genres = $pdo->query("SELECT genre FROM book WHERE genre <> '' GROUP BY genre ORDER BY COUNT(*) DESC LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);


// ===== Page setup =====
$pageTitle = "Doms Library";
include __DIR__ . '/includes/header.php';
?>
<!-- ===== HTML output: landing page ===== -->
<!-- Top navigation bar -->
<div class="topbar">
    <div class="brand">📚 Doms Library</div>
    <nav>
        <a href="#features">Features</a>
        <a href="login.php">Log in</a>
        <a href="register.php">Register</a>
    </nav>
</div>

<div class="content wrap-1100">
    <!-- Hero card with call-to-action buttons -->
    <div class="card hero-card">
        <h1 class="page-title hero-title">Find it. Read it. Borrow it.</h1>
        <p class="sub hero-text">Doms Library is an online catalog where you can search books, read them on screen, and borrow and return them in a click.</p>
        <a href="login.php" class="btn">Log in</a>
        <a href="register.php" class="btn secondary">Create an account</a>
    </div>

    <!-- Stats: total books and members -->
    <div class="stats-row mb-28">
        <div class="stat-card"><div class="stat-value"><?= $totalBooks ?></div><div class="stat-label">Books in the catalog</div></div>
        <div class="stat-card"><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Registered members</div></div>
    </div>

    <!-- Feature list -->
    <h2 class="mb-14" id="features">What you can do</h2>
    <div class="dash-grid">
        <div class="dash-card"><div class="dash-icon">🔎</div><h3>Search and filter</h3><div class="dash-sub">By title, author or genre.</div></div>
        <div class="dash-card"><div class="dash-icon">📖</div><h3>Read online</h3><div class="dash-sub">Open a book and read its content.</div></div>
        <div class="dash-card"><div class="dash-icon">🔁</div><h3>Borrow and return</h3><div class="dash-sub">7-day loans with email receipts and reminders.</div></div>
        <div class="dash-card"><div class="dash-icon">🔐</div><h3>Secure sign-in</h3><div class="dash-sub">Password plus an emailed code, or a QR login.</div></div>
    </div>

    <!-- Popular genres line -->
    <?php if ($genres): ?>
    <p class="sub mt-24 text-center">Popular genres: <?= htmlspecialchars(implode(' · ', $genres)) ?></p>
    <?php endif; ?>
</div>
<!-- Shared page footer -->
<?php include __DIR__ . '/includes/footer.php'; ?>
