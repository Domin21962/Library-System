<?php
// Book reader page: shows one book's details and full text, lets the user save it to My Shelf, and remembers where they stopped reading.
// Access: any logged-in user (Admin/Librarian/User).
// Inputs (GET): id = numeric book id (anything else goes back to the catalog).
// Tables: book, shelf and reading_progress (read only here; progress is written by user/progress.php, the shelf by user/shelf.php).

// ===== Includes and access check =====
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();


// ===== Request setup =====
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Validate the id: digits only, otherwise back to the catalog
$bookId = $_GET['id'] ?? '';
if (!ctype_digit((string)$bookId)) {
    redirect('user/main.php');
}


// ===== Data loading =====
$stmt = $pdo->prepare(
    "SELECT b.book_id, b.title, b.author, b.year, b.genre, b.publisher, b.book_content,
     b.gutenberg_id, b.full_text
     FROM book b WHERE b.book_id = ?"
);
$stmt->execute([(int)$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
// Unknown book id: back to the catalog

if (!$book) {
    redirect('user/main.php');
}


// ===== Display values =====
// Defensive defaults - keeps the page working even if a column is briefly missing
// (e.g. before sql/add_gutenberg_ids.sql has been run)
$book += [
    'title' => '', 'author' => '', 'year' => null, 'genre' => '',
    'publisher' => '', 'book_content' => '', 'gutenberg_id' => null,
    'full_text' => '',
];

// Is it on this user's shelf, and how far did they read last time?
$userId = currentUserId($pdo);
$stmt = $pdo->prepare("SELECT 1 FROM shelf WHERE user_id = ? AND book_id = ?");
$stmt->execute([$userId, (int)$book['book_id']]);
$onShelf = (bool)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT percent FROM reading_progress WHERE user_id = ? AND book_id = ?");
$stmt->execute([$userId, (int)$book['book_id']]);
$savedPercent = (int)$stmt->fetchColumn();

// full_text is populated by tools/import_texts.php (downloads public-domain texts
// from Project Gutenberg). If it hasn't been run yet, this will just be empty.
$fullText = trim((string)($book['full_text'] ?? ''));
$gutenbergId = $book['gutenberg_id'] ?? null;


// ===== Page setup / header =====
$pageTitle = $book['title'];
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== HTML output ===== -->
<!-- Top bar: brand, user badge and nav -->
<div class="topbar">
    <div class="brand"><img class="brand-logo" width="34" height="34" src="<?= APP_BASE ?>/assets/img/logo.png" alt="WebShelf logo"> WebShelf <span class="badge"><?= htmlspecialchars($username) ?> · <?= htmlspecialchars(($role === 'User' ? userCode($username) : $role)) ?></span></div>
    <nav>
        <a href="main.php">Catalog</a>
        <a href="shelf.php">My Shelf</a>
        <a href="profile.php">Profile</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</div>

<!-- Reader: book header card and full text -->
<div class="reader-wrap">
    <p class="m-0-0-16"><a href="main.php">← Back to catalog</a></p>

    <!-- Book header: id, status badge, title, author and tags -->
    <div class="reader-head" style="border-top:3px solid <?= genreColor($book['genre']) ?>;">
        <div class="book-card-top">
            <div class="book-id">Book #<?= (int)$book['book_id'] ?></div>
            <?php if ($onShelf): ?><span class="badge-status badge-available">● On my shelf</span><?php endif; ?>
        </div>
        <?= bookCover($book) ?>
        <h1><?= htmlspecialchars($book['title']) ?></h1>
        <div class="author">by <?= htmlspecialchars($book['author']) ?></div>
        <div class="meta">
            <span class="tag"><?= htmlspecialchars($book['genre']) ?></span>
            <span class="tag"><?= (int)$book['year'] ?></span>
            <span class="tag"><?= htmlspecialchars($book['publisher']) ?></span>
        </div>
        <!-- Short synopsis (if any) -->
        <?php if (!empty($book['book_content'])): ?>
            <div class="reader-synopsis"><?= htmlspecialchars($book['book_content']) ?></div>
        <?php endif; ?>
        <!-- Save to / remove from My Shelf (POST + CSRF), and the saved reading progress -->
        <div class="shelf-actions">
            <form method="post" action="shelf.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $onShelf ? 'remove' : 'add' ?>">
                <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
                <input type="hidden" name="return" value="book">
                <button type="submit" class="shelf-btn"><?= $onShelf ? '✓ On my shelf (remove)' : '＋ Add to My Shelf' ?></button>
            </form>
            <?php if ($savedPercent > 0): ?><span class="progress-label"><?= $savedPercent >= 100 ? 'Finished' : $savedPercent . '% read' ?></span><?php endif; ?>
        </div>
    </div>

    <!-- Full text, or a hint on how to import it -->
    <?php if ($fullText !== ''): ?>
        <!-- Page-by-page reader: the text is split into pages in the browser (see script below) -->
        <div class="pager-resume" id="pg-resume" hidden></div>
        <div class="reader-body reader-paged" id="pager-text" aria-live="polite"></div>
        <div class="pager" id="pager">
            <button type="button" id="pg-prev">← Previous</button>
            <span class="pager-info">Page <input type="number" id="pg-input" min="1" value="1" aria-label="Go to page"> of <span id="pg-total">…</span></span>
            <button type="button" id="pg-next">Next →</button>
        </div>
        <noscript><div class="reader-note">Turn on JavaScript to read the book page by page.</div></noscript>
    <?php else: ?>
        <div class="reader-note">
            <strong>Full text not imported yet.</strong><br><br>
            This book's complete text hasn't been loaded into the database. Run the importer
            once to download it:
            <br><br>
            Open a terminal in your project folder and run:<br>
            <code class="text-accent">php tools/import_texts.php</code>
            <br><br>
            It pulls the complete public-domain texts from Project Gutenberg straight into
            your <code>book.full_text</code> column. After it finishes, reload this page and
            the whole book will appear here.
            <?php if ($gutenbergId): ?>
                <br><br>
                You can also read it directly at
                <a href="https://www.gutenberg.org/ebooks/<?= (int)$gutenbergId ?>" target="_blank" rel="noopener">Project Gutenberg</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($fullText !== ''): ?>
<!-- The book text, safely encoded for the page-by-page reader -->
<script type="application/json" id="book-text"><?= json_encode($fullText, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) ?></script>
<script>
// Page-by-page reader: splits the text into pages, turns them with buttons / arrow keys / swipes,
// and saves the reading position (as a percentage) so the user can continue where they left off.
(function () {
    var box = document.getElementById('pager-text');
    if (!box) return;
    var bookId = <?= (int)$book['book_id'] ?>;
    var token = <?= json_encode(csrfToken()) ?>;
    var saved = <?= (int)$savedPercent ?>;           // last saved position, 0-100
    var text = JSON.parse(document.getElementById('book-text').textContent);

    // ----- Split the text into pages (about 230 words each, never breaking a paragraph unless it is huge) -----
    var TARGET = 230, MAX = 330;
    function words(t) { return t.split(/\s+/).length; }

    // Turn the raw text into paragraphs. Hard-wrapped prose lines are joined; poems and headings keep their line breaks.
    function paragraphs(raw) {
        var out = [];
        raw.replace(/\r\n?/g, '\n').split(/\n[ \t]*\n+/).forEach(function (chunk) {
            var t = chunk.replace(/^\n+/, '').replace(/\s+$/, '');
            if (!t.trim()) return;
            var lines = t.split('\n');
            if (lines.length > 1 && t.length / lines.length <= 40) {
                out.push({ t: t, keep: true });                       // short lines: poem, list, heading block
            } else {
                out.push({ t: t.replace(/\s*\n\s*/g, ' '), keep: false });
            }
        });
        return out;
    }

    // A paragraph longer than MAX words is cut into pieces near TARGET words (by line or by sentence).
    function splitBig(it) {
        // Sentences are found by marking the whitespace after . ! ? and splitting there, so no text can be lost
        var units = it.keep ? it.t.split('\n') : it.t.replace(/([.!?]["”’')\]]*)\s+/g, '$1\u0001').split('\u0001');
        var parts = [], cur = [], n = 0;
        units.forEach(function (u) {
            var w = words(u);
            if (w > MAX) {                                            // one giant sentence: cut by words
                if (cur.length) { parts.push(cur.join(it.keep ? '\n' : ' ')); cur = []; n = 0; }
                var ws = u.split(/\s+/);
                for (var i = 0; i < ws.length; i += TARGET) parts.push(ws.slice(i, i + TARGET).join(' '));
                return;
            }
            if (n > 0 && n + w > TARGET) { parts.push(cur.join(it.keep ? '\n' : ' ')); cur = []; n = 0; }
            cur.push(u); n += w;
        });
        if (cur.length) parts.push(cur.join(it.keep ? '\n' : ' '));
        return parts.map(function (t) { return { t: t.replace(/\s+$/, ''), keep: it.keep }; });
    }

    var pages = [], cur = [], count = 0;
    function flush() { if (cur.length) pages.push(cur); cur = []; count = 0; }
    paragraphs(text).forEach(function (it) {
        var w = words(it.t);
        (w > MAX ? splitBig(it) : [it]).forEach(function (piece) {
            var pw = words(piece.t);
            if (count > 0 && count + pw > TARGET + 60) flush();
            cur.push(piece); count += pw;
            if (count >= TARGET) flush();
        });
    });
    flush();
    var total = pages.length;
    if (!total) return;

    // ----- Where to start: the saved position, otherwise page 1 -----
    var key = 'webshelf_page_' + bookId;
    function pageFromPercent(p) { return Math.max(1, Math.min(total, Math.round(p * total / 100))); }
    var page = 1;
    if (saved > 0 && saved < 100) {
        page = pageFromPercent(saved);
        // Same device: use the exact page that was open (the saved percentage is only approximate)
        try {
            var mem = JSON.parse(localStorage.getItem(key) || 'null');
            if (mem && mem.pct === saved && mem.n === total && mem.p >= 1 && mem.p <= total) page = mem.p;
        } catch (e) {}
    }
    var resumed = page > 1;
    var last = saved, timer = null;

    // ----- Draw one page -----
    var info = document.getElementById('pg-input'), totalEl = document.getElementById('pg-total');
    var prev = document.getElementById('pg-prev'), next = document.getElementById('pg-next');
    totalEl.textContent = total; info.max = total;
    function render() {
        box.textContent = '';
        pages[page - 1].forEach(function (it) {
            var p = document.createElement('p');
            if (it.keep) p.className = 'keep-lines';
            p.textContent = it.t;                                     // textContent: book text is never treated as HTML
            box.appendChild(p);
        });
        info.value = page;
        prev.disabled = page <= 1;
        next.disabled = page >= total;
    }

    // ----- Save the position (percentage in the database, exact page on this device) -----
    function percent() { return Math.min(100, Math.max(1, Math.ceil(page * 100 / total))); }
    function save() {
        var pct = percent();
        if (pct === last) return;
        last = pct;
        try { localStorage.setItem(key, JSON.stringify({ p: page, pct: pct, n: total })); } catch (e) {}
        var data = new FormData();
        data.append('_csrf', token); data.append('book_id', bookId); data.append('percent', pct);
        // sendBeacon survives page close; fetch is the fallback
        if (!(navigator.sendBeacon && navigator.sendBeacon('progress.php', data))) {
            fetch('progress.php', { method: 'POST', body: data, credentials: 'same-origin', keepalive: true });
        }
    }

    // ----- Turning pages -----
    function go(n) {
        n = Math.max(1, Math.min(total, parseInt(n, 10) || page));
        if (n === page) { info.value = page; return; }
        page = n;
        render();
        var top = box.getBoundingClientRect().top;
        if (top < 0) window.scrollTo(0, top + window.scrollY - 12);   // bring the new page into view
        clearTimeout(timer); timer = setTimeout(save, 600);
    }
    prev.addEventListener('click', function () { go(page - 1); });
    next.addEventListener('click', function () { go(page + 1); });
    info.addEventListener('change', function () { go(info.value); });
    document.addEventListener('keydown', function (e) {
        var tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key === 'ArrowRight') go(page + 1);
        else if (e.key === 'ArrowLeft') go(page - 1);
    });
    // Swipe left / right on touch screens
    var sx = 0, sy = 0;
    box.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    box.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > 60 && Math.abs(dy) < 45) go(page + (dx < 0 ? 1 : -1));
    }, { passive: true });
    window.addEventListener('pagehide', save);

    render();
    if (resumed) {
        var note = document.getElementById('pg-resume');
        note.textContent = 'Continuing from page ' + page + ' of ' + total + ' where you left off.';
        note.hidden = false;
        window.scrollTo(0, Math.max(0, box.getBoundingClientRect().top + window.scrollY - 70));
    }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>