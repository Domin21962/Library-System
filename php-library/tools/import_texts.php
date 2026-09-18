<?php
/**
 * import_texts.php
 *
 * Downloads the COMPLETE text of each public-domain book in the catalog from
 * Project Gutenberg and stores it in book.full_text.
 *
 * Every title in this catalog was first published before ~1930, so copyright has
 * expired and the full texts are free to download, store, and read.
 *
 * Run once from your project folder:
 *     php tools/import_texts.php
 *
 * Re-running is safe: books that already have text are skipped unless you pass --force.
 *     php tools/import_texts.php --force
 */

require_once __DIR__ . '/../config/db.php';

$force = in_array('--force', $argv ?? [], true);

// Make sure the columns exist (safe to run repeatedly)
function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "  + added column $table.$column\n";
    }
}

echo "Checking schema...\n";
ensureColumn($pdo, 'book', 'gutenberg_id', 'INT NULL');
ensureColumn($pdo, 'book', 'full_text', 'LONGTEXT NULL');

/**
 * Fetch a Gutenberg plain-text file. Gutenberg stores files under a few different
 * naming conventions depending on the book's age, so try each in turn.
 */
function fetchGutenbergText(int $id): ?string {
    $candidates = [
        "https://www.gutenberg.org/cache/epub/$id/pg$id.txt",
        "https://www.gutenberg.org/files/$id/$id-0.txt",
        "https://www.gutenberg.org/files/$id/$id.txt",
        "https://www.gutenberg.org/ebooks/$id.txt.utf-8",
    ];

    foreach ($candidates as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'LibrarySystem/1.0 (school project; public-domain text import)',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $code === 200 && strlen($body) > 5000) {
            return $body;
        }
    }
    return null;
}

/**
 * Strip Gutenberg's license header/footer so only the actual book text remains.
 */
function stripGutenbergBoilerplate(string $text): string {
    $startMarkers = [
        '*** START OF THE PROJECT GUTENBERG EBOOK',
        '*** START OF THIS PROJECT GUTENBERG EBOOK',
        '***START OF THE PROJECT GUTENBERG EBOOK',
    ];
    $endMarkers = [
        '*** END OF THE PROJECT GUTENBERG EBOOK',
        '*** END OF THIS PROJECT GUTENBERG EBOOK',
        '***END OF THE PROJECT GUTENBERG EBOOK',
    ];

    foreach ($startMarkers as $marker) {
        $pos = stripos($text, $marker);
        if ($pos !== false) {
            $lineEnd = strpos($text, "\n", $pos);
            if ($lineEnd !== false) {
                $text = substr($text, $lineEnd + 1);
            }
            break;
        }
    }

    foreach ($endMarkers as $marker) {
        $pos = stripos($text, $marker);
        if ($pos !== false) {
            $text = substr($text, 0, $pos);
            break;
        }
    }

    return trim($text);
}

// Books that have a Gutenberg ID recorded
$sql = "SELECT book_id, title, gutenberg_id, full_text FROM book
        WHERE gutenberg_id IS NOT NULL ORDER BY title";
$books = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$books) {
    echo "\nNo books have a gutenberg_id set.\n";
    echo "Run sql/add_gutenberg_ids.sql first, then re-run this script.\n";
    exit(1);
}

$update = $pdo->prepare("UPDATE book SET full_text = ? WHERE book_id = ?");

$done = 0;
$skipped = 0;
$failed = [];

echo "\nImporting " . count($books) . " books...\n\n";

foreach ($books as $b) {
    $title = $b['title'];

    if (!$force && trim((string)$b['full_text']) !== '') {
        echo "  = $title (already imported)\n";
        $skipped++;
        continue;
    }

    echo "  . $title ... ";
    $raw = fetchGutenbergText((int)$b['gutenberg_id']);

    if ($raw === null) {
        echo "FAILED\n";
        $failed[] = $title;
        continue;
    }

    $clean = stripGutenbergBoilerplate($raw);

    // Normalise encoding so MySQL stores it cleanly
    if (!mb_check_encoding($clean, 'UTF-8')) {
        $clean = mb_convert_encoding($clean, 'UTF-8', 'ISO-8859-1');
    }

    $update->execute([$clean, $b['book_id']]);
    echo number_format(strlen($clean)) . " chars OK\n";
    $done++;

    // Be polite to Gutenberg's servers
    sleep(1);
}

echo "\n----------------------------------------\n";
echo "Imported: $done\n";
echo "Skipped:  $skipped\n";
if ($failed) {
    echo "Failed:   " . count($failed) . "\n";
    foreach ($failed as $f) {
        echo "    - $f\n";
    }
    echo "\n(Failures are usually a temporary network issue - just re-run the script.)\n";
}
echo "\nDone. Open a book in the app to read it.\n";
