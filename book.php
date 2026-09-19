<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$bookId = $_GET['id'] ?? '';
if (!ctype_digit((string)$bookId)) {
    redirect('main.php');
}

$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content,
     b.gutenberg_id, b.full_text,
     (SELECT br.username FROM borrow_return br
      WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by
     FROM book b WHERE b.book_id = ?"
);
$stmt->execute([(int)$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    redirect('main.php');
}

// Defensive defaults - keeps the page working even if a column is briefly missing
// (e.g. before sql/add_gutenberg_ids.sql has been run)
$book += [
    'title' => '', 'author' => '', 'year' => null, 'genre' => '',
    'publisher' => '', 'book_content' => '', 'gutenberg_id' => null,
    'full_text' => '', 'borrowed_by' => null,
];

$available = empty($book['borrowed_by']);

// full_text is populated by tools/import_texts.php (downloads public-domain texts
// from Project Gutenberg). If it hasn't been run yet, this will just be empty.
$fullText = trim((string)($book['full_text'] ?? ''));
$gutenbergId = $book['gutenberg_id'] ?? null;

$pageTitle = $book['title'];
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Doms Library <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="reader-wrap">
    <p style="margin:0 0 16px;"><a href="main.php">← Back to catalog</a></p>

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
        <?php if (!empty($book['book_content'])): ?>
            <div class="reader-synopsis"><?= htmlspecialchars($book['book_content']) ?></div>
        <?php endif; ?>
        <?php if (!$available): ?>
            <div class="borrowed-note">Currently with <?= htmlspecialchars($book['borrowed_by']) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($fullText !== ''): ?>
        <div class="reader-body"><?= htmlspecialchars($fullText) ?></div>
    <?php else: ?>
        <div class="reader-note">
            <strong>Full text not imported yet.</strong><br><br>
            This book's complete text hasn't been loaded into the database. Run the importer
            once to download it:
            <br><br>
            Open a terminal in your project folder and run:<br>
            <code style="color:var(--accent);">php tools/import_texts.php</code>
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
