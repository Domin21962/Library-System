<?php
// Admin only: catalog management table with how many users saved each book, delete and edit links.
// Inputs: POST action=delete_book, book_id, _csrf.
// Tables: book (delete), shelf (read only; its rows are removed automatically with the book).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Reject bad CSRF tokens on POST, then require an admin session
enforceCsrfOnPost();
requireAdmin();


// ===== State =====
// Logged-in admin name (shown in the top bar) and flash message
$username = $_SESSION['username'];
$notice = '';


// ===== POST handling: delete a book =====
// The browser asks for confirmation first (confirm-delete form below)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_book') {
    // Cast to int so only a number reaches the query
    $bookId = (int)($_POST['book_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
    $stmt->execute([$bookId]);
    // rowCount() is 0 when no such book existed
    $notice = $stmt->rowCount() > 0 ? "Book #$bookId removed." : "Book not found.";
}


// ===== Data loading =====
// All books A-Z; saved_by = how many users have the book on their shelf
$books = $pdo->query(
    "SELECT b.book_id, b.title, b.author, b.genre, b.year,
     (SELECT COUNT(*) FROM shelf s WHERE s.book_id = b.book_id) AS saved_by
     FROM book b ORDER BY b.title"
)->fetchAll(PDO::FETCH_ASSOC);


// ===== Page setup / header =====
$pageTitle = "Manage Books - WebShelf";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Top bar: brand and admin nav -->
<div class="topbar admin-topbar">
    <div class="brand"><img class="brand-logo" width="34" height="34" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="admin.php">← Dashboard</a>
        <a href="<?= url('user/main.php') ?>">📚 View Catalog</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-1200">
    <div class="page-header">
        <h1 class="page-title">📖 Manage Books</h1>
        <a href="admin.php" class="btn secondary">← Back to Dashboard</a>
    </div>

    <!-- Result of the last delete -->
    <?php if ($notice): ?><div class="success mb-16"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="admin-section">
        <div class="sub">Add new titles, edit their details, or remove ones no longer in circulation.</div>
        <a href="add_book.php" class="btn admin-btn">➕ Add a Book</a>

        <!-- Books table -->
        <table class="mt-18">
            <tr><th>ID</th><th>Title</th><th>Author</th><th>Genre</th><th>Status</th><th></th></tr>
            <?php foreach ($books as $b): ?>
            <tr>
                <td>#<?= (int)$b['book_id'] ?></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td><?= htmlspecialchars($b['author']) ?></td>
                <td><?= htmlspecialchars($b['genre']) ?></td>
                <td>
                    <!-- How many users have this book on their shelf -->
                    <span class="badge-status badge-available">Saved by <?= (int)$b['saved_by'] ?></span>
                </td>
                <td>
                    <!-- Delete form: data-message is the confirmation prompt shown before submitting; includes CSRF token -->
                    <form method="post" class="confirm-delete d-inline" data-message="Remove &quot;<?= htmlspecialchars(addslashes($b['title'])) ?>&quot; from the catalog?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_book">
                        <input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                    <!-- Edit link (separate page) -->
                    <a href="edit_book.php?id=<?= (int)$b['book_id'] ?>" class="row-btn link-btn">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
