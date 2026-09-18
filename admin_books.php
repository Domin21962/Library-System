<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_book') {
    $bookId = (int)($_POST['book_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $notice = $stmt->rowCount() > 0 ? "Book #$bookId removed." : "Book not found.";
}

$books = $pdo->query(
    "SELECT b.book_id, b.title, b.author, b.genre, b.year,
     (SELECT br.username FROM borrow_return br WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by
     FROM book b ORDER BY b.title"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Books - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar admin-topbar">
    <div class="brand">⚙️ Doms Library <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="admin.php">← Dashboard</a>
        <a href="main.php">📚 View Catalog</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <h1 class="page-title" style="margin-bottom:0;">📖 Manage Books</h1>
        <a href="admin.php" class="btn secondary" style="margin-top:0;">← Back to Dashboard</a>
    </div>

    <?php if ($notice): ?><div class="success" style="margin-bottom:16px;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="admin-section">
        <div class="sub">Add new titles, or remove ones no longer in circulation.</div>
        <a href="add_book.php" class="btn admin-btn">➕ Add a Book</a>

        <table style="margin-top:18px;">
            <tr><th>ID</th><th>Title</th><th>Author</th><th>Genre</th><th>Status</th><th></th></tr>
            <?php foreach ($books as $b): ?>
            <tr>
                <td>#<?= (int)$b['book_id'] ?></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td><?= htmlspecialchars($b['author']) ?></td>
                <td><?= htmlspecialchars($b['genre']) ?></td>
                <td>
                    <?php if ($b['borrowed_by']): ?>
                        <span class="badge-status badge-borrowed">Borrowed by <?= htmlspecialchars($b['borrowed_by']) ?></span>
                    <?php else: ?>
                        <span class="badge-status badge-available">Available</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" class="confirm-delete" data-message="Remove &quot;<?= htmlspecialchars(addslashes($b['title'])) ?>&quot; from the catalog?" style="display:inline;">
                        <input type="hidden" name="action" value="delete_book">
                        <input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
