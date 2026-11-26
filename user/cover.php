<?php
// Serves a Project Gutenberg cover from a local cache (downloaded once, then reused).
// Access: logged-in users. Input (GET): id = gutenberg_id of a book in the catalog.
// Tables: book (read). Files: assets/covers/gutenberg/<id>.jpg (cache).

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT 1 FROM book WHERE gutenberg_id = ? LIMIT 1");
$stmt->execute([$id]);
if ($id < 1 || !$stmt->fetchColumn()) { http_response_code(404); exit; }

$dir = __DIR__ . '/../assets/covers/gutenberg';
$file = "$dir/$id.jpg";
if (!is_file($file)) {
    $ch = curl_init("https://www.gutenberg.org/cache/epub/$id/pg$id.cover.medium.jpg");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'LibrarySystem/1.0 (school project; cover cache)']);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body !== false && $code === 200 && strlen($body) > 1000) {
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($file, $body);
    } else {
        // Download failed: send the browser to Gutenberg directly (the page's onerror shows a title cover if that fails too)
        header('Location: https://www.gutenberg.org/cache/epub/' . $id . '/pg' . $id . '.cover.medium.jpg');
        exit;
    }
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=604800');
readfile($file);
