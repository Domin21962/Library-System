<?php
/**
 * Landing page: public front door of WebShelf (what it is, with Log in and Register buttons).
 * Who: public. Logged-in users are sent straight to their own home page.
 * Input: none. Tables: book (read only, for the catalog count; the page works without it).
 */

// ===== Requires =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// ===== Already logged in: skip the landing page =====
if (!empty($_SESSION['username']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Admin') redirect('admin/admin.php');
    if ($_SESSION['role'] === 'Librarian') redirect('librarian/librarian.php');
    redirect('user/main.php');
}

// ===== Catalog count (optional; hidden if the query fails) =====
$bookCount = 0;
try {
    $bookCount = (int)$pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
} catch (Throwable $e) {
    $bookCount = 0;
}

// ===== Page setup =====
$pageTitle = "WebShelf - Read online and keep your place";
$extraCss = ['landing.css'];
include __DIR__ . '/includes/header.php';
?>
<!-- ===== Top bar ===== -->
<header class="lp-top">
    <a class="lp-brand" href="<?= url('index.php') ?>">
        <img width="36" height="36" src="<?= APP_BASE ?>/assets/img/logo.png" alt="">
        <span>WebShelf</span>
    </a>
    <nav class="lp-nav" aria-label="Account">
        <a href="<?= url('login.php') ?>">Log in</a>
        <a class="lp-nav-cta" href="<?= url('register.php') ?>">Register</a>
    </nav>
</header>

<main>
    <!-- ===== Hero: headline + a shelf of genre spines ===== -->
    <section class="lp-hero">
        <div class="lp-hero-copy">
            <h1>Pick up reading right where you stopped.</h1>
            <p class="lp-lede">
                WebShelf is a library you read in your browser. Search the catalog, save books to your own shelf,
                and come back to the same page next time.
            </p>
            <div class="lp-actions">
                <a class="btn" href="<?= url('register.php') ?>">Create an account</a>
                <a class="btn secondary" href="<?= url('login.php') ?>">Log in</a>
            </div>
            <?php if ($bookCount > 0): ?>
                <p class="lp-count"><?= number_format($bookCount) ?> book<?= $bookCount === 1 ? '' : 's' ?> in the catalog right now.</p>
            <?php endif; ?>
        </div>

        <div class="lp-shelf" aria-hidden="true">
            <div class="lp-books">
                <span class="lp-spine" style="--h:84%;--c:#7a2e2a;--i:0"><b>Mystery</b></span>
                <span class="lp-spine" style="--h:68%;--c:#3f6b6b;--i:1"><b>Fantasy</b></span>
                <span class="lp-spine" style="--h:92%;--c:#b8893b;--i:2;--t:#3a2a1c"><b>Classic</b></span>
                <span class="lp-spine" style="--h:74%;--c:#5b3a4e;--i:3"><b>Romance</b></span>
                <span class="lp-spine" style="--h:60%;--c:#2f3e5c;--i:4"><b>Poetry</b></span>
                <span class="lp-spine" style="--h:88%;--c:#5b6b3a;--i:5"><b>Adventure</b></span>
                <span class="lp-spine" style="--h:72%;--c:#8b5e3c;--i:6"><b>Philosophy</b></span>
            </div>
            <div class="lp-plank"></div>
        </div>
    </section>

    <!-- ===== What you can do ===== -->
    <section class="lp-features" aria-labelledby="lp-features-title">
        <h2 id="lp-features-title">What you can do here</h2>
        <ul>
            <li>
                <h3>Find a book fast</h3>
                <p>Search by title, author or genre, or tap a genre to browse it.</p>
            </li>
            <li>
                <h3>Keep a personal shelf</h3>
                <p>Save the books you want to read and find them again in one place.</p>
            </li>
            <li>
                <h3>Continue where you left off</h3>
                <p>Your reading progress is saved, so your next session starts on the page you stopped at.</p>
            </li>
            <li>
                <h3>Sign in your way</h3>
                <p>Log in with a username and password, or scan a QR code. Each login is confirmed by email.</p>
            </li>
        </ul>
    </section>

    <!-- ===== Closing call to action ===== -->
    <section class="lp-final">
        <h2>Ready to start your shelf?</h2>
        <div class="lp-actions">
            <a class="btn" href="<?= url('register.php') ?>">Create an account</a>
            <a class="btn secondary" href="<?= url('login.php') ?>">I already have one</a>
        </div>
    </section>
</main>

<footer class="lp-foot">WebShelf</footer>
<?php include __DIR__ . '/includes/footer.php'; ?>
