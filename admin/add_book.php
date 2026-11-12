<?php
// Admin only: form to add a new book to the catalog.
// Inputs: POST title, author, year, genre, publisher, content, _csrf.
// Tables: book (insert).

// ===== Includes =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


// ===== Access check =====
// Reject any POST with a bad CSRF token, then require an admin session
enforceCsrfOnPost();
requireAdmin();


// ===== State =====
// Messages shown in the form
$error = '';
$success = '';


// ===== POST handling: validate and insert =====
// Only runs when the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $yearRaw = $_POST['year'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $content = $_POST['content'] ?? '';

    // Server-side length limits; title and author are required
    if ($title === '' || strlen($title) > 255 || $author === '' || strlen($author) > 255 || strlen($genre) > 100 || strlen($publisher) > 255 || strlen($content) > 100000) {
        $error = 'Please check the length and contents of the book fields.';
    // Year must be digits only and no later than next year
    } elseif (!ctype_digit($yearRaw) || (int)$yearRaw < 0 || (int)$yearRaw > ((int)date('Y') + 1)) {
        $error = "Please enter a valid year.";
    } else {
        // Prepared statement: values are bound, never concatenated into SQL
        // Equivalent of BookController.addBook()
        $stmt = $pdo->prepare("INSERT INTO book (title, author, year, genre, publisher, book_content) VALUES (?, ?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$title, $author, (int)$yearRaw, $genre, $publisher, $content]);

        if ($ok) {
            $success = "Book added successfully!";
        } else {
            $error = "Error adding book.";
        }
    }
}


// ===== Page setup / header =====
$pageTitle = "Add Book";
include __DIR__ . '/../includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Centered card layout -->
<div class="auth-wrapper">
    <div class="card max-480">
        <h2>Add a book</h2>
        <div class="subtitle">Admin only</div>
        <!-- Add-book form (posts to this same page) -->
        <form method="post">
                <!-- Hidden CSRF token, checked by enforceCsrfOnPost() -->
                <?= csrf_field() ?>
            <label>Title</label>
            <input type="text" name="title" required>
            <label>Author</label>
            <input type="text" name="author" required>
            <label>Year</label>
            <input type="number" name="year" required>
            <label>Genre</label>
            <input type="text" name="genre" required>
            <label>Publisher</label>
            <input type="text" name="publisher" required>
            <label>Content</label>
            <textarea name="content"></textarea>
            <!-- Error / success messages (escaped) -->
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <button class="w-full" type="submit">Add Book</button>
        </form>
        <p class="footer-link"><a href="admin_books.php">← Back to Manage Books</a></p>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
