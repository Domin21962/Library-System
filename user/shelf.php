<?php
// My Shelf page: the books a user saved for later, plus a "Continue Reading" list of books they started.
// Access: any logged-in user (Admin/Librarian/User).
// Inputs (POST): action = add or remove, book_id, return = "book" to go back to the reader afterwards, _csrf.
// Tables: shelf (read/write), reading_progress, book and users (read only); also creates an admin notification when a book is added.

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
// Reject POSTs without a valid CSRF token
enforceCsrfOnPost();
requireLogin();


// ===== Request setup =====
$username = (string)$_SESSION['username'];
$role = $_SESSION['role'];
$userId = currentUserId($pdo);


// ===== POST handling: add or remove a book, then redirect back =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int)($_POST['book_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // The book must exist
    $titleStmt = $pdo->prepare("SELECT title FROM book WHERE book_id = ?");
    $titleStmt->execute([$bookId]);
    $title = $titleStmt->fetchColumn();

    if ($title !== false && $userId > 0) {
        if ($action === 'add') {
            // INSERT IGNORE: saving the same book twice is harmless (unique user + book)
            $stmt = $pdo->prepare("INSERT IGNORE INTO shelf (user_id, book_id) VALUES (?, ?)");
            $stmt->execute([$userId, $bookId]);
            if ($stmt->rowCount() > 0) {
                createAdminNotification($pdo, 'shelf', $username, $bookId, $title);
            }
        } elseif ($action === 'remove') {
            $pdo->prepare("DELETE FROM shelf WHERE user_id = ? AND book_id = ?")->execute([$userId, $bookId]);
        }
    }
    redirect(($_POST['return'] ?? '') === 'book' && $bookId > 0 ? 'user/book.php?id=' . $bookId : 'user/shelf.php');
}


// ===== Data loading =====
// Books the user started but has not finished, most recently read first
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.gutenberg_id, rp.percent
     FROM reading_progress rp JOIN book b ON b.book_id = rp.book_id
     WHERE rp.user_id = ? AND rp.percent BETWEEN 1 AND 99
     ORDER BY rp.updated_at DESC LIMIT 6"
);
$stmt->execute([$userId]);
$continue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Everything on the shelf, newest first, with progress if any
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.gutenberg_id, COALESCE(rp.percent, 0) AS percent
     FROM shelf s JOIN book b ON b.book_id = s.book_id
     LEFT JOIN reading_progress rp ON rp.user_id = s.user_id AND rp.book_id = s.book_id
     WHERE s.user_id = ? ORDER BY s.added_at DESC"
);
$stmt->execute([$userId]);
$shelf = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "My Shelf - WebShelf";
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and nav -->
<div class="topbar">
    <div class="brand"><img class="brand-logo" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<div class="content wrap-1200">
    <h1 class="page-title">My Shelf</h1>

    <!-- Continue Reading: books with saved progress -->
    <?php if (!empty($continue)): ?>
        <h2 class="section-heading">Continue Reading</h2>
        <div class="book-grid">
            <?php foreach ($continue as $book): ?>
                <a class="book-card" href="book.php?id=<?= (int)$book['book_id'] ?>" style="border-top: 3px solid <?= genreColor($book['genre']) ?>;">
                    <?= bookCover($book) ?>
                    <h3><?= htmlspecialchars($book['title']) ?></h3>
                    <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
                    <?= progressBar((int)$book['percent']) ?>
                    <div class="read-cta">▶ Resume</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- The saved collection -->
    <h2 class="section-heading">Saved books (<?= count($shelf) ?>)</h2>
    <?php if (empty($shelf)): ?>
        <div class="empty-state">
            Your shelf is empty. Open a book in the <a href="main.php">catalog</a> and choose "Add to My Shelf".
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php foreach ($shelf as $book): ?>
                <div class="shelf-item">
                    <a class="book-card" href="book.php?id=<?= (int)$book['book_id'] ?>" style="border-top: 3px solid <?= genreColor($book['genre']) ?>;">
                        <?= bookCover($book) ?>
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
                        <div class="meta">
                            <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
                            <span class="tag"><?= (int)$book['year'] ?></span>
                        </div>
                        <?= progressBar((int)$book['percent']) ?>
                    </a>
                    <!-- Remove from shelf (POST + CSRF) -->
                    <form method="post" class="shelf-remove">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
                        <button type="submit">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
