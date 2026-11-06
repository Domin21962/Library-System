<?php
// Admin only: read-only view of security_audit_logs (logins, lockouts, changes), with filters.
// Inputs (GET): user = username contains, action = exact action name.
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$username = $_SESSION['username'];
$userFilter = trim($_GET['user'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');

// Filter values are bound as parameters, never concatenated into the SQL.
$where = [];
$params = [];
if ($userFilter !== '') { $where[] = 'username LIKE ?'; $params[] = '%' . $userFilter . '%'; }
if ($actionFilter !== '') { $where[] = 'action = ?'; $params[] = $actionFilter; }

$stmt = $pdo->prepare("SELECT created_at, username, action, ip_address, metadata FROM security_audit_logs"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$actions = $pdo->query("SELECT DISTINCT action FROM security_audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Audit Logs - Doms Library";
include __DIR__ . '/includes/header.php';
?>
<div class="topbar admin-topbar">
    <div class="brand">⚙️ Doms Library <span class="badge">Admin · <?= htmlspecialchars($username) ?></span></div>
    <nav>
        <a href="admin.php">← Dashboard</a>
        <a href="main.php">📚 View Catalog</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="content wrap-1200">
    <div class="page-header">
        <h1 class="page-title">🛡️ Audit Logs</h1>
        <a href="admin.php" class="btn secondary">← Back to Dashboard</a>
    </div>

    <div class="admin-section">
        <div class="sub">The latest 200 recorded events. Filter by user or action.</div>
        <form class="filter-row" method="get">
            <input type="text" name="user" placeholder="Username contains…" value="<?= htmlspecialchars($userFilter) ?>">
            <select name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $a === $actionFilter ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
            <a href="admin_audit.php" class="btn secondary">Reset</a>
        </form>
        <?php if (empty($logs)): ?>
            <div class="empty-state">No matching events.</div>
        <?php else: ?>
        <table>
            <tr><th>Time</th><th>User</th><th>Action</th><th>IP</th><th>Details</th></tr>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['created_at']) ?></td>
                <td><?= htmlspecialchars((string)$l['username']) ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= htmlspecialchars((string)$l['ip_address']) ?></td>
                <td><?= htmlspecialchars((string)$l['metadata']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
