<?php
// Book reader page: shows one book's details, availability and its full text.
// Access: any logged-in user (Admin/Teacher/User).
// Inputs (GET): id = numeric book id (anything else goes back to the catalog).
// Tables: book and borrow_return (read only).

// ===== Includes and access check =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();


// ===== Request setup =====
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Validate the id: digits only, otherwise back to the catalog
$bookId = $_GET['id'] ?? '';
if (!ctype_digit((string)$bookId)) {
    redirect('main.php');
}


// ===== Data loading =====
// Book row plus who currently has it (an open loan has no return_date)
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content,
     b.gutenberg_id, b.full_text,
     (SELECT br.username FROM borrow_return br
      WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by
     FROM book b WHERE b.book_id = ?"
);
$stmt->execute([(int)$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
// Unknown book id: back to the catalog

if (!$book) {
    redirect('main.php');
}


// ===== Display values =====
// Defensive defaults - keeps the page working even if a column is briefly missing
// (e.g. before sql/add_gutenberg_ids.sql has been run)
$book += [
    'title' => '', 'author' => '', 'year' => null, 'genre' => '',
    'publisher' => '', 'book_content' => '', 'gutenberg_id' => null,
    'full_text' => '', 'borrowed_by' => null,
];

// Available when nobody has an open loan on it
$available = empty($book['borrowed_by']);

// full_text is populated by tools/import_texts.php (downloads public-domain texts
// from Project Gutenberg). If it hasn't been run yet, this will just be empty.
$fullText = trim((string)($book['full_text'] ?? ''));
$gutenbergId = $book['gutenberg_id'] ?? null;


// ===== Page setup / header =====
$pageTitle = $book['title'];
include __DIR__ . '/includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<!-- Reader: book header card and full text -->
<div class="reader-wrap">
    <p class="m-0-0-16"><a href="main.php">← Back to catalog</a></p>

    <!-- Book header: id, status badge, title, author and tags -->
    <div class="reader-head" style="border-top:3px solid <?= genreColor($book['genre']) ?>;">
        <div class="book-card-top">
            <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
            <span class="badge-status <?= $available ? 'badge-available' : 'badge-borrowed' ?>">
                <?= $available ? '● Available' : '● Borrowed' ?>
            </span>
        </div>
        <div class="genre-icon"><?= genreIcon($book['genre']) ?></div>
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
        <!-- Shown only while the book is on loan -->
        <?php if (!$available): ?>
            <div class="borrowed-note">Currently with <?= htmlspecialchars($book['borrowed_by']) ?></div>
        <?php endif; ?>
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
<?php include __DIR__ . '/includes/footer.php'; ?>
