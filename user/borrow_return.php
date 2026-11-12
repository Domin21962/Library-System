<?php
// Borrow & Return page: a logged-in user borrows or returns a book by ID and sees their own history.
// Access: any logged-in user (Admin/Librarian/User).
// Inputs (POST): book_id, action = borrow or return, _csrf. No GET inputs.
// Tables: borrow_return (read/write), book and users (read only); also creates admin notifications and sends receipt emails.

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
// Reject POSTs without a valid CSRF token
enforceCsrfOnPost();
require_once __DIR__ . '/../includes/mailer.php';
// Must be logged in (any role)
requireLogin();


// ===== Request setup =====
$message = '';
// Pre-filled from session, like BorrowReturn.java's emailField.setText(Session.getCurrentUsername())
$username = (string)$_SESSION['username'];
// Book ID typed into the form (kept as text so we can validate it)
$bookIdRaw = $_POST['book_id'] ?? '';

// Pre-fill email from the users table (real value, unlike Session's blank email bug)
$email = '';

// Read the account email from the users table (needed for the receipt)
if ($email === '') {
    $lookup = $pdo->prepare("SELECT email FROM users WHERE username = ?");
    $lookup->execute([$username]);
    $email = $lookup->fetchColumn() ?: '';
}


// ===== POST handling =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Validate input: book ID present and numeric, and a valid email on file
    if ($bookIdRaw === '') {
        $message = "Enter a book ID.";
    } elseif (!ctype_digit($bookIdRaw)) {
        $message = "Invalid book ID. Must be a number.";
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } else {
        $bookId = (int)$bookIdRaw;
        // Make sure the book exists before borrowing or returning it
        $bookExists = $pdo->prepare('SELECT title FROM book WHERE book_id = ?');
        $bookExists->execute([$bookId]);
        if (!$bookExists->fetchColumn()) {
            $message = 'Book not found.';
        } elseif ($action === 'borrow') {
            // Equivalent of BorrowReturn.borrowBook()
            // A book is out when any user has a loan with no return_date
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE book_id = ? AND return_date IS NULL");
            $check->execute([$bookId]);

            if ($check->fetch()) {
                $message = "This book is already borrowed.";
            } else {
                // New loan, due in 7 days; values are bound as parameters
                $insert = $pdo->prepare("INSERT INTO borrow_return (username, book_id, borrow_date, due_date) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))");
                $insert->execute([$username, $bookId]);
                // Id of the new loan, shown on the receipt
                $transactionId = $pdo->lastInsertId();

                // Send a styled HTML receipt right away (best-effort - doesn't block borrowing if it fails)
                // Title for the receipt email
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
                // Receipt email and admin notification are best-effort extras
                sendLibraryEmail($email, "Receipt: You've borrowed \"$title\"", $receiptHtml, true);
                createAdminNotification($pdo, 'borrow', $username, $bookId, $title);

                $message = "Book borrowed successfully.";
            }
        } elseif ($action === 'return') {
            // Equivalent of BorrowReturn.returnBook()
            // Only the borrower can return: needs an open loan for this user and book
            $check = $pdo->prepare("SELECT * FROM borrow_return WHERE username = ? AND book_id = ? AND return_date IS NULL");
            $check->execute([$username, $bookId]);

            if ($check->fetch()) {
                // Close the loan by stamping return_date
                $update = $pdo->prepare("UPDATE borrow_return SET return_date = NOW(), email = ? WHERE username = ? AND book_id = ? AND return_date IS NULL");
                $update->execute([$email, $username, $bookId]);

                // Fetch the just-closed loan to build the return receipt
                $recordStmt = $pdo->prepare(
                    "SELECT br.id, br.borrow_date, br.return_date, b.title
                     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
                     WHERE br.username = ? AND br.book_id = ? AND br.return_date IS NOT NULL
                     ORDER BY br.id DESC LIMIT 1"
                );
                $recordStmt->execute([$username, $bookId]);
                $record = $recordStmt->fetch(PDO::FETCH_ASSOC);

                // Track whether the receipt email went out
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
                    createAdminNotification($pdo, 'return', $username, $bookId, $record['title']);
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


// ===== Data loading =====
// Equivalent of BorrowReturn.updateRecordsDisplay()
$records = [];
if ($username !== '') {
    $stmt = $pdo->prepare(
        "SELECT b.title, u.email, br.borrow_date, br.due_date, br.return_date
         FROM borrow_return br
         JOIN book b ON br.book_id = b.book_id
         JOIN users u ON u.username = br.username
         WHERE br.username = ?
         ORDER BY br.borrow_date DESC"
    );
    $stmt->execute([$username]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ===== Page setup / header =====
$pageTitle = "Borrow & Return";
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand and nav -->
<div class="topbar">
    <div class="brand">📚 WebShelf</div>
    <nav>
        <a href="main.php">Main</a>
        <a href="<?= url('qr_login.php') ?>">QR Login</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Main content -->
<div class="content wrap-700">
    <h1 class="page-title">Borrow &amp; Return</h1>
    <p class="m-0-0-16"><a href="main.php">← Back to catalog</a></p>
    <!-- Borrow / return form card -->
    <div class="card card-wide">
        <form method="post">
                <?= csrf_field() ?>
            <label>Book ID</label>
            <input type="number" name="book_id" value="<?= htmlspecialchars($bookIdRaw) ?>" required>
            <label>Username</label>
            <!-- Username is shown for convenience; the server uses the session username -->
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
            <label>Email</label>
            <input type="text" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com" required>
            <!-- Result message -->
            <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <!-- Submit buttons: action value decides borrow or return -->
            <div class="row-gap-10">
                <button type="submit" name="action" value="borrow">Borrow</button>
                <button type="submit" name="action" value="return" class="secondary">Return</button>
            </div>
        </form>
    </div>

    <!-- Borrow history table (or empty-state message) -->
    <h2 class="page-title section-title-gap">Borrow History</h2>
    <?php if (empty($records)): ?>
        <div class="empty-state">No borrow history yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Title</th><th>Email</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
        <!-- One row per loan; overdue (unreturned and past due) rows are highlighted -->
        <?php foreach ($records as $r): ?>
        <?php
            $isOverdue = !$r['return_date'] && !empty($r['due_date']) && strtotime($r['due_date']) < time();
        ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['borrow_date']) ?></td>
            <td<?= $isOverdue ? ' class="text-overdue"' : '' ?>>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
