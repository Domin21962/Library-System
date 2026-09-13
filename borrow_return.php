<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$message = '';
// Pre-filled from session, like BorrowReturn.java's emailField.setText(Session.getCurrentUsername())
$username = $_POST['username'] ?? $_SESSION['username'];
$bookIdRaw = $_POST['book_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($bookIdRaw === '') {
        $message = "Enter a book ID.";
    } elseif (!ctype_digit($bookIdRaw)) {
        $message = "Invalid book ID. Must be a number.";
    } else {
        $bookId = (int)$bookIdRaw;

        if ($action === 'borrow') {
            // Equivalent of BorrowReturn.borrowBook()
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE book_id = ? AND return_date IS NULL");
            $check->execute([$bookId]);

            if ($check->fetch()) {
                $message = "This book is already borrowed.";
            } else {
                $insert = $pdo->prepare("INSERT INTO borrow_return (username, book_id, borrow_date) VALUES (?, ?, NOW())");
                $insert->execute([$username, $bookId]);
                $message = "Book borrowed successfully.";
            }
        } elseif ($action === 'return') {
            // Equivalent of BorrowReturn.returnBook()
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE username = ? AND book_id = ? AND return_date IS NULL");
            $check->execute([$username, $bookId]);

            if ($check->fetch()) {
                $update = $pdo->prepare("UPDATE borrow_return SET return_date = NOW() WHERE username = ? AND book_id = ? AND return_date IS NULL");
                $update->execute([$username, $bookId]);
                $message = "Book returned successfully.";
            } else {
                $message = "You haven't borrowed this book or already returned it.";
            }
        }
    }
}

// Equivalent of BorrowReturn.updateRecordsDisplay()
$records = [];
if ($username !== '') {
    $stmt = $pdo->prepare(
        "SELECT b.title, br.borrow_date, br.return_date
         FROM borrow_return br
         JOIN book b ON br.book_id = b.book_id
         WHERE br.username = ?
         ORDER BY br.borrow_date DESC"
    );
    $stmt->execute([$username]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "Borrow & Return";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div class="brand">📚 Library System</div>
    <nav>
        <a href="main.php">Main</a>
        <a href="student_info.php">Information</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:700px;margin:0 auto;">
    <h1 class="page-title">Borrow &amp; Return</h1>

    <div class="card" style="max-width:none;padding:24px;">
        <form method="post">
            <label>Book ID</label>
            <input type="number" name="book_id" value="<?= htmlspecialchars($bookIdRaw) ?>" required>
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
            <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div style="display:flex;gap:10px;">
                <button type="submit" name="action" value="borrow">Borrow</button>
                <button type="submit" name="action" value="return" class="secondary">Return</button>
            </div>
        </form>
    </div>

    <h2 class="page-title" style="margin-top:30px;font-size:16px;">Borrow History</h2>
    <?php if (empty($records)): ?>
        <div class="empty-state">No borrow history yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Title</th><th>Borrowed</th><th>Returned</th></tr>
        <?php foreach ($records as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['borrow_date']) ?></td>
            <td><?= $r['return_date'] ? htmlspecialchars($r['return_date']) : 'Not yet returned' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <p class="footer-link"><a href="main.php">← Back to Main</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
