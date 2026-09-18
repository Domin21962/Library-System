<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookIdRaw = $_POST['book_id'] ?? '';

    if (!ctype_digit($bookIdRaw)) {
        $error = "Invalid book ID.";
    } else {
        // Equivalent of BookController.removeBook()
        $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
        $ok = $stmt->execute([(int)$bookIdRaw]);

        if ($ok && $stmt->rowCount() > 0) {
            $success = "Book removed successfully!";
        } else {
            $error = "Error removing book.";
        }
    }
}

$pageTitle = "Remove Book";
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="card">
        <h2>Remove a book</h2>
        <div class="subtitle">Admin only — enter the book ID to delete</div>
        <form method="post">
            <label>Book ID</label>
            <input type="number" name="book_id" required>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <button type="submit" class="danger" style="width:100%;">Remove</button>
        </form>
        <p class="footer-link"><a href="admin_books.php">← Back to Manage Books</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
