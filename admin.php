<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];
$notice = '';
$error = '';

// Inline actions: delete a book or delete a user, handled right here so the
// admin never has to leave the dashboard for routine cleanup.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_book') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
        $stmt->execute([$bookId]);
        $notice = $stmt->rowCount() > 0 ? "Book #$bookId removed." : "Book not found.";
    } elseif ($action === 'delete_user') {
        $targetUser = $_POST['target_username'] ?? '';
        if ($targetUser === $username) {
            $error = "You can't delete your own account while logged in.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
            $stmt->execute([$targetUser]);
            $notice = $stmt->rowCount() > 0 ? "Account \"$targetUser\" removed." : "Account not found.";
        }
    }
}

// Stats
$totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM book")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$borrowedNow = (int)$pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL")->fetchColumn();
$overdueNow = (int)$pdo->query("SELECT COUNT(*) FROM borrow_return WHERE return_date IS NULL AND due_date IS NOT NULL AND due_date < NOW()")->fetchColumn();

// Book list (for management table)
$books = $pdo->query(
    "SELECT b.book_id, b.title, b.author, b.genre, b.year,
     (SELECT br.username FROM borrow_return br WHERE br.book_id = b.book_id AND br.return_date IS NULL LIMIT 1) AS borrowed_by
     FROM book b ORDER BY b.title"
)->fetchAll(PDO::FETCH_ASSOC);

// User list
$users = $pdo->query("SELECT username, email FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Recent activity across everyone (not just the admin's own)
$activity = $pdo->query(
    "SELECT br.username, b.title, br.borrow_date, br.due_date, br.return_date
     FROM borrow_return br JOIN book b ON br.book_id = b.book_id
     ORDER BY br.id DESC LIMIT 12"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Panel";
include __DIR__ . '/includes/header.php';
?>
<style>
    /* Admin gets its own accent so it's visually distinct from the student view */
    :root { --admin-accent: #f59e0b; --admin-accent-2: #f97316; }
    .admin-topbar { background: #1a1512; border-bottom: 1px solid #3a2a17; }
    .admin-topbar .brand { color: #ffd9a0; }
    .admin-topbar .badge { background: #2a1f14; border-color: #4a3620; color: #f0b968; }
    .admin-topbar nav a:hover { color: var(--admin-accent); }
    .admin-btn { background: linear-gradient(135deg, var(--admin-accent), var(--admin-accent-2)) !important; }
    .admin-stat { border-top: 3px solid var(--admin-accent) !important; }
    .admin-section {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 24px;
    }
    .admin-section h2 { margin: 0 0 4px; font-size: 16px; }
    .admin-section .sub { color: var(--muted); font-size: 13px; margin-bottom: 16px; }
    .role-pill {
        display: inline-block; padding: 2px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 700; letter-spacing: 0.02em;
    }
    .role-admin { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
    .role-teacher { background: rgba(91,140,255,0.15); color: #7ba3ff; border: 1px solid rgba(91,140,255,0.3); }
    .role-student { background: rgba(52,211,153,0.15); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
    .row-btn {
        padding: 5px 10px; font-size: 12px; margin-top: 0;
        background: var(--panel-2); border: 1px solid var(--border); border-radius: 6px;
        color: var(--text); cursor: pointer;
    }
    .row-btn:hover { border-color: var(--danger); color: var(--danger); }
</style>

<div class="topbar admin-topbar">
    <div class="brand">⚙️ Admin Panel <span class="badge"><?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="main.php">📚 View Catalog</a>
        <a href="student_info.php">Information</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content" style="max-width:1200px;margin:0 auto;">
    <h1 class="page-title">Dashboard</h1>

    <?php if ($notice): ?><div class="success" style="margin-bottom:16px;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="stats-row" style="margin-bottom:28px;">
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $totalBooks ?></div>
            <div class="stat-label">Total Books</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="stat-card admin-stat">
            <div class="stat-value"><?= $borrowedNow ?></div>
            <div class="stat-label">Currently Borrowed</div>
        </div>
        <div class="stat-card admin-stat" style="<?= $overdueNow > 0 ? 'border-top-color:var(--danger) !important;' : '' ?>">
            <div class="stat-value" style="<?= $overdueNow > 0 ? 'color:var(--danger);' : '' ?>"><?= $overdueNow ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <div class="admin-section">
        <h2>📖 Manage Books</h2>
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
                    <form method="post" onsubmit="return confirm('Remove &quot;<?= htmlspecialchars(addslashes($b['title'])) ?>&quot;?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_book">
                        <input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="admin-section">
        <h2>👥 Manage Users</h2>
        <div class="sub">Everyone registered in the system. Role is determined by the username prefix.</div>
        <table>
            <tr><th>Username</th><th>Email</th><th>Role</th><th></th></tr>
            <?php foreach ($users as $u): ?>
            <?php $r = getUserRole($u['username']); ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="role-pill role-<?= strtolower($r) ?>"><?= htmlspecialchars($r) ?></span></td>
                <td>
                    <?php if ($u['username'] !== $username): ?>
                    <form method="post" onsubmit="return confirm('Delete the account &quot;<?= htmlspecialchars(addslashes($u['username'])) ?>&quot;?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                        <button type="submit" class="row-btn">Delete</button>
                    </form>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:12px;">(you)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="admin-section">
        <h2>🕒 Recent Activity</h2>
        <div class="sub">The latest borrow and return transactions, across every user.</div>
        <?php if (empty($activity)): ?>
            <div class="empty-state">No activity yet.</div>
        <?php else: ?>
        <table>
            <tr><th>User</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
            <?php foreach ($activity as $a): ?>
            <?php $overdue = !$a['return_date'] && !empty($a['due_date']) && strtotime($a['due_date']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['borrow_date']) ?></td>
                <td style="<?= $overdue ? 'color:var(--danger);font-weight:600;' : '' ?>">
                    <?= !empty($a['due_date']) ? htmlspecialchars($a['due_date']) : '—' ?><?= $overdue ? ' (overdue)' : '' ?>
                </td>
                <td><?= $a['return_date'] ? htmlspecialchars($a['return_date']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
