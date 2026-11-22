<?php
// Catalog page (home for Users and Admins): browse or search books, see shelf stats, Continue Reading and genre chips.
// Access: any logged-in user; Librarians are redirected to librarian.php.
// Inputs (GET): keyword = search text (title, author or genre); empty shows the whole catalog.
// Tables: book, shelf and reading_progress (read only).

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
// Columns every catalog card needs
$availabilitySelect = "b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content, b.gutenberg_id";

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
// This user's shelf (set of book ids) and reading progress (book id => percent)
$userId = currentUserId($pdo);
$stmt = $pdo->prepare("SELECT book_id FROM shelf WHERE user_id = ?");
$stmt->execute([$userId]);
$shelfIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $pdo->prepare("SELECT book_id, percent FROM reading_progress WHERE user_id = ?");
$stmt->execute([$userId]);
$progress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
// Continue Reading strip: up to 3 started, unfinished books, newest first
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.genre, b.gutenberg_id, rp.percent
     FROM reading_progress rp JOIN book b ON b.book_id = rp.book_id
     WHERE rp.user_id = ? AND rp.percent BETWEEN 1 AND 99 ORDER BY rp.updated_at DESC LIMIT 3"
);
$stmt->execute([$userId]);
$continue = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Distinct genres for the chips
$genres = $pdo->query("SELECT DISTINCT genre FROM book ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);


// ===== Page setup / header =====
$pageTitle = "WebShelf - Main";
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and role-aware nav -->
<div class="topbar">
    <div class="brand"><img class="brand-logo" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <?php if ($role === 'Admin'): ?>
            <a href="<?= url('admin/admin.php') ?>">⚙️ Admin Panel</a>
        <?php elseif ($role === 'Librarian'): ?>
            <a href="<?= url('librarian/librarian.php') ?>">🧑‍🏫 Monitor Users</a>
        <?php endif; ?>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="shelf.php">My Shelf</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1200">
    <h1 class="page-title">Find a book</h1>

    <!-- Stats cards: catalog size, this user's shelf and books in progress -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$totalBooks ?></div>
            <div class="stat-label">Total Books</div>
        </div>
        <div class="stat-card stat-available">
            <div class="stat-value"><?= count($shelfIds) ?></div>
            <div class="stat-label">On My Shelf</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($continue) ?></div>
            <div class="stat-label">Continue Reading</div>
        </div>
    </div>

    <!-- Continue Reading: books the user stopped in the middle of -->
    <?php if (!empty($continue)): ?>
        <h2 class="section-heading">Continue Reading</h2>
        <div class="book-grid">
            <?php foreach ($continue as $c): ?>
                <a class="book-card" href="book.php?id=<?= (int)$c['book_id'] ?>" style="border-top: 3px solid <?= genreColor($c['genre']) ?>;">
                    <?= bookCover($c) ?>
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <div class="author">by <?= htmlspecialchars($c['author']) ?></div>
                    <?= progressBar((int)$c['percent']) ?>
                    <div class="read-cta">▶ Resume</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
            <?php $onShelf = isset($shelfIds[$book['book_id']]); ?>
            <a class="book-card" href="book.php?id=<?= (int)$book['book_id'] ?>" style="border-top: 3px solid <?= genreColor($book['genre']) ?>;">
                <div class="book-card-top">
                    <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
                    <?php if ($onShelf): ?>
                        <span class="badge-status badge-available">● On my shelf</span>
                    <?php endif; ?>
                </div>
                <?= bookCover($book) ?>
                <h3><?= htmlspecialchars($book['title']) ?></h3>
                <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
                <div class="meta">
                    <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
                    <span class="tag"><?= (int)$book['year'] ?></span>
                </div>
                <div class="read-cta">📖 Click to read</div>
                <?= progressBar((int)($progress[$book['book_id']] ?? 0)) ?>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
