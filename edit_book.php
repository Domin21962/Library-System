<?php
// Admin only: edit an existing book (the Update step of the book CRUD).
// Inputs: GET id = book to edit; POST book_id, title, author, year, genre, publisher, content, _csrf.
// Tables: book (read and update); writes an audit log entry.

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Access check =====
// Reject POSTs without a valid CSRF token, then require an Admin session
enforceCsrfOnPost();
requireAdmin();


// ===== Data loading =====
// Book id comes from the URL on first load and from a hidden field on save; cast to int
$bookId = (int)($_GET['id'] ?? $_POST['book_id'] ?? 0);
// Load the current values to pre-fill the form
$stmt = $pdo->prepare("SELECT book_id, title, author, year, genre, publisher, book_content FROM book WHERE book_id = ?");
$stmt->execute([$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
// Unknown id: go back to the book list
if (!$book) redirect('admin_books.php');


// ===== POST handling (save changes) =====
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read and trim submitted fields
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $yearRaw = $_POST['year'] ?? '';
    $genre = trim($_POST['genre'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $content = $_POST['content'] ?? '';

    // Same validation rules as add_book.php
    if ($title === '' || strlen($title) > 255 || $author === '' || strlen($author) > 255 || strlen($genre) > 100 || strlen($publisher) > 255 || strlen($content) > 100000) {
        $error = 'Please check the length and contents of the book fields.';
    } elseif (!ctype_digit($yearRaw) || (int)$yearRaw < 0 || (int)$yearRaw > ((int)date('Y') + 1)) {
        $error = 'Please enter a valid year.';
    } else {
        // Parameterized UPDATE: values are bound, never concatenated
        $upd = $pdo->prepare("UPDATE book SET title = ?, author = ?, year = ?, genre = ?, publisher = ?, book_content = ? WHERE book_id = ?");
        $upd->execute([$title, $author, (int)$yearRaw, $genre, $publisher, $content, $bookId]);
        // Record who changed which book
        auditLog($pdo, 'book_updated', (string)$_SESSION['username'], ['book_id' => $bookId]);
        $success = 'Book updated.';
        // Re-fill the form with the saved values
        $book = ['book_id' => $bookId, 'title' => $title, 'author' => $author, 'year' => $yearRaw, 'genre' => $genre, 'publisher' => $publisher, 'book_content' => $content];
    }
}


// ===== Page setup / header =====
$pageTitle = "Edit Book";
include __DIR__ . '/includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Edit form card -->
<div class="auth-wrapper">
    <div class="card max-480">
        <h2>Edit book #<?= (int)$book['book_id'] ?></h2>
        <div class="subtitle">Admin only</div>
        <form method="post">
        <!-- CSRF token and hidden book id are sent with the form -->
            <?= csrf_field() ?>
            <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
            <!-- Book fields, pre-filled and HTML-escaped -->
            <label>Title</label>
            <input type="text" name="title" required value="<?= htmlspecialchars($book['title']) ?>">
            <label>Author</label>
            <input type="text" name="author" required value="<?= htmlspecialchars($book['author']) ?>">
            <label>Year</label>
            <input type="number" name="year" required value="<?= htmlspecialchars((string)$book['year']) ?>">
            <label>Genre</label>
            <input type="text" name="genre" required value="<?= htmlspecialchars($book['genre']) ?>">
            <label>Publisher</label>
            <input type="text" name="publisher" required value="<?= htmlspecialchars($book['publisher']) ?>">
            <label>Content</label>
            <textarea name="content"><?= htmlspecialchars((string)$book['book_content']) ?></textarea>
            <!-- Validation error / success message -->
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <button class="w-full" type="submit">Save changes</button>
        </form>
        <!-- Back link -->
        <p class="footer-link"><a href="admin_books.php">← Back to Manage Books</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
