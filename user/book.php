<?php
// Book reader page: shows one book's details and full text, lets the user save it to My Shelf, and remembers where they stopped reading.
// Access: any logged-in user (Admin/Librarian/User).
// Inputs (GET): id = numeric book id (anything else goes back to the catalog).
// Tables: book, shelf and reading_progress (read only here; progress is written by user/progress.php, the shelf by user/shelf.php).

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();


// ===== Request setup =====
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Validate the id: digits only, otherwise back to the catalog
$bookId = $_GET['id'] ?? '';
if (!ctype_digit((string)$bookId)) {
    redirect('user/main.php');
}


// ===== Data loading =====
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content,
     b.gutenberg_id, b.full_text
     FROM book b WHERE b.book_id = ?"
);
$stmt->execute([(int)$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
// Unknown book id: back to the catalog

if (!$book) {
    redirect('user/main.php');
}


// ===== Display values =====
// Defensive defaults - keeps the page working even if a column is briefly missing
// (e.g. before sql/add_gutenberg_ids.sql has been run)
$book += [
    'title' => '', 'author' => '', 'year' => null, 'genre' => '',
    'publisher' => '', 'book_content' => '', 'gutenberg_id' => null,
    'full_text' => '',
];

// Is it on this user's shelf, and how far did they read last time?
$userId = currentUserId($pdo);
$stmt = $pdo->prepare("SELECT 1 FROM shelf WHERE user_id = ? AND book_id = ?");
$stmt->execute([$userId, (int)$book['book_id']]);
$onShelf = (bool)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT percent FROM reading_progress WHERE user_id = ? AND book_id = ?");
$stmt->execute([$userId, (int)$book['book_id']]);
$savedPercent = (int)$stmt->fetchColumn();

// full_text is populated by tools/import_texts.php (downloads public-domain texts
// from Project Gutenberg). If it hasn't been run yet, this will just be empty.
$fullText = trim((string)($book['full_text'] ?? ''));
$gutenbergId = $book['gutenberg_id'] ?? null;


// ===== Page setup / header =====
$pageTitle = $book['title'];
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and nav -->
<div class="topbar">
    <div class="brand"><img class="brand-logo" width="34" height="34" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="shelf.php">My Shelf</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Reader: book header card and full text -->
<div class="reader-wrap">
    <p class="m-0-0-16"><a href="main.php">← Back to catalog</a></p>

    <!-- Book header: id, status badge, title, author and tags -->
    <div class="reader-head" style="border-top:3px solid <?= genreColor($book['genre']) ?>;">
        <div class="book-card-top">
            <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
            <?php if ($onShelf): ?><span class="badge-status badge-available">● On my shelf</span><?php endif; ?>
        </div>
        <?= bookCover($book) ?>
        <h1><?= htmlspecialchars($book['title']) ?></h1>
        <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
        <div class="meta">
            <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
            <span class="tag"><?= (int)$book['year'] ?></span>
            <span class="tag"><?= htmlspecialchars($book['publisher']) ?></span>
        </div>
        <!-- Short synopsis (if any) -->
        <?php if (!empty($book['book_content'])): ?>
            <div class="reader-synopsis"><?= htmlspecialchars($book['book_content']) ?></div>
        <?php endif; ?>
        <!-- Save to / remove from My Shelf (POST + CSRF), and the saved reading progress -->
        <div class="shelf-actions">
            <form method="post" action="shelf.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $onShelf ? 'remove' : 'add' ?>">
                <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
                <input type="hidden" name="return" value="book">
                <button type="submit" class="shelf-btn"><?= $onShelf ? '✓ On my shelf (remove)' : '＋ Add to My Shelf' ?></button>
            </form>
            <?php if ($savedPercent > 0): ?><span class="progress-label"><?= $savedPercent >= 100 ? 'Finished' : $savedPercent . '% read' ?></span><?php endif; ?>
        </div>
    </div>

    <!-- Full text, or a hint on how to import it -->
    <?php if ($fullText !== ''): ?>
        <div class="reader-body"><?= htmlspecialchars($fullText) ?></div>
    <?php else: ?>
        <div class="reader-note">
            <strong>Full text not imported yet.</strong><br><br>
            This book's complete text hasn't been loaded into the database. Run the importer
            once to download it:
            <br><br>
            Open a terminal in your project folder and run:<br>
            <code class="text-accent">php tools/import_texts.php</code>
            <br><br>
            It pulls the complete public-domain texts from Project Gutenberg straight into
            your <code>book.full_text</code> column. After it finishes, reload this page and
            the whole book will appear here.
            <?php if ($gutenbergId): ?>
                <br><br>
                You can also read it directly at
                <a href="https://www.gutenberg.org/ebooks/<?= (int)$gutenbergId ?>" target="_blank" rel="noopener">Project Gutenberg</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($fullText !== ''): ?>
<script>
// Reading progress: restore the saved position, then save how far the user has read (debounced).
(function () {
    var body = document.querySelector('.reader-body');
    if (!body) return;
    var bookId = <?= (int)$book['book_id'] ?>;
    var token = <?= json_encode(csrfToken()) ?>;
    var saved = <?= (int)$savedPercent ?>;
    var last = saved, timer = null;
    // Position = fraction of the text above the bottom edge of the screen
    function top() { return body.getBoundingClientRect().top + window.scrollY; }
    function percent() {
        var p = (window.scrollY + window.innerHeight - top()) / body.offsetHeight * 100;
        return Math.max(0, Math.min(100, Math.round(p)));
    }
    function save() {
        var p = percent();
        if (p < 1 || p === last) return;
        last = p;
        var data = new FormData();
        data.append('_csrf', token); data.append('book_id', bookId); data.append('percent', p);
        // sendBeacon survives page close; fetch is the fallback
        if (!(navigator.sendBeacon && navigator.sendBeacon('progress.php', data))) {
            fetch('progress.php', { method: 'POST', body: data, credentials: 'same-origin', keepalive: true });
        }
    }
    if (saved > 0 && saved < 100) {
        window.scrollTo(0, Math.max(0, top() + body.offsetHeight * saved / 100 - window.innerHeight));
    }
    window.addEventListener('scroll', function () { clearTimeout(timer); timer = setTimeout(save, 800); });
    window.addEventListener('pagehide', save);
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
