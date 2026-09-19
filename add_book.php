<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
enforceCsrfOnPost();
requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $yearRaw = $_POST['year'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $content = $_POST['content'] ?? '';

    if ($title === '' || strlen($title) > 255 || $author === '' || strlen($author) > 255 || strlen($genre) > 100 || strlen($publisher) > 255 || strlen($content) > 100000) {
        $error = 'Please check the length and contents of the book fields.';
    } elseif (!ctype_digit($yearRaw) || (int)$yearRaw < 0 || (int)$yearRaw > ((int)date('Y') + 1)) {
        $error = "Please enter a valid year.";
    } else {
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

$pageTitle = "Add Book";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="card" style="max-width:480px;">
        <h2>Add a book</h2>
        <div class="subtitle">Admin only</div>
        <form method="post">
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
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <button type="submit" style="width:100%;">Add Book</button>
        </form>
        <p class="footer-link"><a href="admin_books.php">← Back to Manage Books</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
