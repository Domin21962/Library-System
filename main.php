<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Every book query includes a live availability flag (currently borrowed = no return_date yet)
$availabilitySelect = "b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content,
    (SELECT br.username FROM borrow_return br WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by";

$keyword = trim($_GET['keyword'] ?? '');
$searched = $keyword !== '';

if ($searched) {
    // Equivalent of BookController.searchBooks()
    $like = "%$keyword%";
    $sql = "SELECT $availabilitySelect
            FROM book b
            WHERE b.title LIKE ? OR b.author LIKE ? OR b.genre LIKE ?
            ORDER BY CASE
                WHEN b.title LIKE ? THEN 1
                WHEN b.author LIKE ? THEN 2
                WHEN b.genre LIKE ? THEN 3
                ELSE 4 END";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no direct matches, recommend books from the same genre
    if (count($books) === 0) {
        $genreSql = "SELECT $availabilitySelect FROM book b WHERE b.genre LIKE ? LIMIT 5";
        $stmt = $pdo->prepare($genreSql);
        $stmt->execute([$like]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Nothing searched yet - browse the whole catalog instead of showing an empty page
    $stmt = $pdo->query("SELECT $availabilitySelect FROM book b ORDER BY b.title");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats strip + genre quick-filter chips
$totalBooks = $pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$borrowedNow = $pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL")->fetchColumn();
$availableNow = $totalBooks - $borrowedNow;
$genres = $pdo->query("SELECT DISTINCT genre FROM book ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Library System - Main";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Library System <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars($role) ?></span></div>
    <nav>
        <?php if ($role === 'Admin'): ?>
            <a href="admin.php">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="student_info.php">Information</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1200px;margin:0 auto;">
    <h1 class="page-title">Find a book</h1>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$totalBooks ?></div>
            <div class="stat-label">Total Books</div>
        </div>
        <div class="stat-card stat-available">
            <div class="stat-value"><?= (int)$availableNow ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card stat-borrowed">
            <div class="stat-value"><?= (int)$borrowedNow ?></div>
            <div class="stat-label">Borrowed</div>
        </div>
    </div>

    <form method="get" class="search-bar">
        <input type="text" name="keyword" placeholder="Search by title, author, or genre..." value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($genres)): ?>
    <div class="chip-row">
        <a href="main.php" class="chip <?= !$searched ? 'chip-active' : '' ?>">All</a>
        <?php foreach ($genres as $g): ?>
            <a href="main.php?keyword=<?= urlencode($g) ?>" class="chip <?= (strcasecmp($keyword, $g) === 0) ? 'chip-active' : '' ?>">
                <?= genreIcon($g) ?> <?= htmlspecialchars($g) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($books)): ?>
        <div class="empty-state">
            <?php if ($searched): ?>
                No books found for "<?= htmlspecialchars($keyword) ?>".
            <?php else: ?>
                The catalog is empty right now.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="book-grid">
        <?php foreach ($books as $book): ?>
            <?php $available = empty($book['borrowed_by']); ?>
            <a class="book-card" href="book.php?id=<?= (int)$book['book_id'] ?>" style="border-top: 3px solid <?= genreColor($book['genre']) ?>;">
                <div class="book-card-top">
                    <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
                    <span class="badge-status <?= $available ? 'badge-available' : 'badge-borrowed' ?>">
                        <?= $available ? '● Available' : '● Borrowed' ?>
                    </span>
                </div>
                <div class="genre-icon"><?= genreIcon($book['genre']) ?></div>
                <h3><?= htmlspecialchars($book['title']) ?></h3>
                <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
                <div class="meta">
                    <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
                    <span class="tag"><?= (int)$book['year'] ?></span>
                </div>
                <div class="read-cta">📖 Click to read</div>
                <?php if (!$available): ?>
                    <div class="borrowed-note">Currently with <?= htmlspecialchars($book['borrowed_by']) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
