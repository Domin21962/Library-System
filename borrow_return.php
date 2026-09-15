<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
requireLogin();

$message = '';
// Pre-filled from session, like BorrowReturn.java's emailField.setText(Session.getCurrentUsername())
$username = $_POST['username'] ?? $_SESSION['username'];
$bookIdRaw = $_POST['book_id'] ?? '';

// Pre-fill email from the users table (real value, unlike Session's blank email bug)
$email = $_POST['email'] ?? '';
if ($email === '') {
    $lookup = $pdo->prepare("SELECT email FROM users WHERE username = ?");
    $lookup->execute([$username]);
    $email = $lookup->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($bookIdRaw === '') {
        $message = "Enter a book ID.";
    } elseif (!ctype_digit($bookIdRaw)) {
        $message = "Invalid book ID. Must be a number.";
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } else {
        $bookId = (int)$bookIdRaw;

        if ($action === 'borrow') {
            // Equivalent of BorrowReturn.borrowBook()
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE book_id = ? AND return_date IS NULL");
            $check->execute([$bookId]);

            if ($check->fetch()) {
                $message = "This book is already borrowed.";
            } else {
                $insert = $pdo->prepare("INSERT INTO borrow_return (username, email, book_id, borrow_date, due_date) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))");
                $insert->execute([$username, $email, $bookId]);
                $transactionId = $pdo->lastInsertId();

                // Send a styled HTML receipt right away (best-effort - doesn't block borrowing if it fails)
                $bookTitle = $pdo->prepare("SELECT title FROM book WHERE book_id = ?");
                $bookTitle->execute([$bookId]);
                $title = $bookTitle->fetchColumn() ?: 'your book';

                $receiptHtml = buildReceiptEmail('borrow', [
                    'transaction_id' => $transactionId,
                    'title'          => $title,
                    'book_id'        => $bookId,
                    'username'       => $username,
                    'email'          => $email,
                    'borrow_date'    => date('F j, Y g:i A'),
                    'due_date'       => date('F j, Y', strtotime('+7 days')),
                ]);
                sendLibraryEmail($email, "Receipt: You've borrowed \"$title\"", $receiptHtml, true);

                $message = "Book borrowed successfully.";
            }
        } elseif ($action === 'return') {
            // Equivalent of BorrowReturn.returnBook()
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE username = ? AND book_id = ? AND return_date IS NULL");
            $check->execute([$username, $bookId]);

            if ($check->fetch()) {
                $update = $pdo->prepare("UPDATE borrow_return SET return_date = NOW(), email = ? WHERE username = ? AND book_id = ? AND return_date IS NULL");
                $update->execute([$email, $username, $bookId]);

                $recordStmt = $pdo->prepare(
                    "SELECT br.id, br.borrow_date, br.return_date, b.title
                     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
                     WHERE br.username = ? AND br.book_id = ? AND br.return_date IS NOT NULL
                     ORDER BY br.id DESC LIMIT 1"
                );
                $recordStmt->execute([$username, $bookId]);
                $record = $recordStmt->fetch(PDO::FETCH_ASSOC);

                $emailSent = false;
                if ($record) {
                    $receiptHtml = buildReceiptEmail('return', [
                        'transaction_id' => $record['id'],
                        'title'          => $record['title'],
                        'book_id'        => $bookId,
                        'username'       => $username,
                        'email'          => $email,
                        'borrow_date'    => date('F j, Y g:i A', strtotime($record['borrow_date'])),
                        'return_date'    => date('F j, Y g:i A', strtotime($record['return_date'])),
                    ]);
                    $emailSent = sendLibraryEmail($email, "Receipt: You've returned \"{$record['title']}\"", $receiptHtml, true);
                }

                $message = "Book returned successfully.";
                if (!$emailSent) {
                    $message .= " (Note: the receipt email failed to send - check config/mail.php and the PHP error log.)";
                }
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
        "SELECT b.title, br.email, br.borrow_date, br.due_date, br.return_date
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
            <label>Email</label>
            <input type="text" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com" required>
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
        <tr><th>Title</th><th>Email</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
        <?php foreach ($records as $r): ?>
        <?php
            $isOverdue = !$r['return_date'] && !empty($r['due_date']) && strtotime($r['due_date']) < time();
        ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['borrow_date']) ?></td>
            <td<?= $isOverdue ? ' style="color:var(--danger);font-weight:600;"' : '' ?>>
                <?= !empty($r['due_date']) ? htmlspecialchars($r['due_date']) : '—' ?>
                <?= $isOverdue ? ' (overdue)' : '' ?>
            </td>
            <td><?= $r['return_date'] ? htmlspecialchars($r['return_date']) : 'Not yet returned' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <p class="footer-link"><a href="main.php">← Back to Main</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
