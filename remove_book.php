<?php
// Admin only: form to delete a book by its numeric ID.
// Inputs: POST book_id, _csrf.
// Tables: book (delete).

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';


// ===== Access check =====
// Reject any POST with a bad CSRF token, then require an admin session
enforceCsrfOnPost();
requireAdmin();


// ===== State =====
// Messages shown in the form
$error = '';
$success = '';


// ===== POST handling: delete the book =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookIdRaw = $_POST['book_id'] ?? '';

    // Only a plain number is accepted as an ID
    if (!ctype_digit($bookIdRaw)) {
        $error = "Invalid book ID.";
    } else {
        // Prepared delete; rowCount() is 0 when no book has that ID
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


// ===== Page setup / header =====
$pageTitle = "Remove Book";
include __DIR__ . '/includes/header.php';
?>


<!-- ===== HTML output ===== -->
<!-- Centered card layout -->
<div class="auth-wrapper">
    <div class="card">
        <h2>Remove a book</h2>
        <div class="subtitle">Admin only — enter the book ID to delete</div>
        <!-- Remove-book form (posts to this same page) -->
        <form method="post">
                <!-- Hidden CSRF token, checked by enforceCsrfOnPost() -->
                <?= csrf_field() ?>
            <label>Book ID</label>
            <input type="number" name="book_id" required>
            <!-- Error / success messages (escaped) -->
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <button type="submit" class="danger w-full">Remove</button>
        </form>
        <p class="footer-link"><a href="admin_books.php">← Back to Manage Books</a></p>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
