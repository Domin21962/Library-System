<?php
// Catalog page (home for Users and Admins): browse or search books, see availability stats and genre chips.
// Access: any logged-in user; Librarians are redirected to librarian.php.
// Inputs (GET): keyword = search text (title, author or genre); empty shows the whole catalog.
// Tables: book and borrow_return (read only).

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();


// ===== Request setup =====
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Librarians monitor students instead of browsing/searching the catalog
if ($role === 'Librarian') {
    redirect('librarian/librarian.php');
}


// ===== Data loading =====
// Every book query includes a live availability flag (currently borrowed = no return_date yet)
$availabilitySelect = "b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content, b.gutenberg_id,
    (SELECT br.username FROM borrow_return br WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by";

// Search text from the query string; blank means browse all
$keyword = trim($_GET['keyword'] ?? '');
$searched = $keyword !== '';

if ($searched) {
    // Equivalent of BookController.searchBooks()
    // Wildcard pattern; values are bound as parameters, never concatenated
    $like = "%$keyword%";
    // Match title, author or genre; ORDER BY ranks title matches first, then author, then genre
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
// Counts for the stats strip
$totalBooks = $pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$borrowedNow = $pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL")->fetchColumn();
$availableNow = $totalBooks - $borrowedNow;
// Distinct genres for the chips
$genres = $pdo->query("SELECT DISTINCT genre FROM book ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);


// ===== Page setup / header =====
$pageTitle = "WebShelf - Main";
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and role-aware nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <?php if ($role === 'Admin'): ?>
            <a href="<?= url('admin/admin.php') ?>">⚙️ Admin Panel</a>
        <?php elseif ($role === 'Librarian'): ?>
            <a href="<?= url('librarian/librarian.php') ?>">🧑‍🏫 Monitor Users</a>
        <?php endif; ?>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="borrow_return.php">Borrow &amp; Return</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1200">
    <h1 class="page-title">Find a book</h1>

    <!-- Stats cards: total, available, borrowed -->
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

    <!-- Search form (GET) -->
    <form method="get" class="search-bar">
                <?= csrf_field() ?>
        <input type="text" name="keyword" placeholder="Search by title, author, or genre..." value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit">Search</button>
    </form>

    <!-- Genre quick-filter chips (links that search by genre) -->
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

    <!-- Book cards, or an empty-state message -->
    <?php if (empty($books)): ?>
        <div class="empty-state">
            <?php if ($searched): ?>
                No books found for "<?= htmlspecialchars($keyword) ?>".
            <?php else: ?>
                The catalog is empty right now.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Grid of book cards; each card links to the reader page -->
        <div class="book-grid">
        <?php foreach ($books as $book): ?>
            <!-- A book is available when no one has an open loan -->
            <?php $available = empty($book['borrowed_by']); ?>
            <a class="book-card" href="book.php?id=<?= (int)$book['book_id'] ?>" style="border-top: 3px solid <?= genreColor($book['genre']) ?>;">
                <div class="book-card-top">
                    <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
                    <span class="badge-status <?= $available ? 'badge-available' : 'badge-borrowed' ?>">
                        <?= $available ? '● Available' : '● Borrowed' ?>
                    </span>
                </div>
                <?= bookCover($book) ?>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
