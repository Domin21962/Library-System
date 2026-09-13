<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$books = [];
$keyword = trim($_GET['keyword'] ?? '');
$searched = $keyword !== '';

if ($searched) {
    // Equivalent of BookController.searchBooks()
    $like = "%$keyword%";
    $sql = "SELECT book_id, title, author, year, genre, publisher, book_content
            FROM book
            WHERE title LIKE ? OR author LIKE ? OR genre LIKE ?
            ORDER BY CASE
                WHEN title LIKE ? THEN 1
                WHEN author LIKE ? THEN 2
                WHEN genre LIKE ? THEN 3
                ELSE 4 END";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no direct matches, recommend books from the same genre
    if (count($books) === 0) {
        $genreSql = "SELECT book_id, title, author, year, genre, publisher, book_content
                     FROM book WHERE genre LIKE ? LIMIT 5";
        $stmt = $pdo->prepare($genreSql);
        $stmt->execute([$like]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = "Library System - Main";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Library System <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <a href="student_info.php">Information</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="layout">
<?php if ($role === 'Admin'): ?>
<div class="sidebar">
    <a href="add_book.php">➕ Add Book</a>
    <a href="remove_book.php">🗑️ Remove Book</a>
</div>
<?php endif; ?>

<div class="content" style="max-width:1000px;">
    <h1 class="page-title">Find a book</h1>
    <form method="get" class="search-bar">
        <input type="text" name="keyword" placeholder="Search by title, author, or genre..." value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit">Search</button>
    </form>

    <?php if ($searched): ?>
        <?php if (empty($books)): ?>
            <div class="empty-state">No books found for "<?= htmlspecialchars($keyword) ?>".</div>
        <?php else: ?>
            <div class="book-grid">
            <?php foreach ($books as $book): ?>
                <div class="book-card">
                    <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
                    <h3><?= htmlspecialchars($book['title']) ?></h3>
                    <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
                    <div class="meta">
                        <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
                        <span class="tag"><?= (int)$book['year'] ?></span>
                    </div>
                    <div class="content-area"><?= htmlspecialchars($book['book_content']) ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">Search above to browse the catalog.</div>
    <?php endif; ?>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
