<?php
// Serves a real book cover from a local cache (downloaded once, then reused).
// Access: logged-in users. Input (GET): book = book_id (preferred) or id = gutenberg_id (legacy links).
// Lookup order: Open Library (real edition cover, searched by title + author) -> Project Gutenberg cover.
// Tables: book (read). Files: assets/covers/auto/<book_id>.jpg (cache), <book_id>.miss (retry marker).
// If nothing is found this sends 404 and the page shows the generated title cover instead.
// Debug: add &debug=1 to see each step as plain text (e.g. cover.php?book=36&debug=1).

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
// Release the session lock so many covers can download in parallel
session_write_close();
set_time_limit(90);

$debug = isset($_GET['debug']);
$steps = [];
function coverLog(string $m): void { global $steps; $steps[] = $m; }

// ----- Find the book -----
if (isset($_GET['book'])) {
    $stmt = $pdo->prepare("SELECT book_id, title, author, gutenberg_id FROM book WHERE book_id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['book']]);
} else {
    $stmt = $pdo->prepare("SELECT book_id, title, author, gutenberg_id FROM book WHERE gutenberg_id = ? LIMIT 1");
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
}
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) { http_response_code(404); exit; }

$bookId = (int)$book['book_id'];
$dir    = __DIR__ . '/../assets/covers/auto';
$file   = "$dir/$bookId.jpg";
$miss   = "$dir/$bookId.miss";   // contains the time until which we should not retry

/** GET a URL. Returns [body|null, httpCode, error]. Code 0 = network problem (timeout, SSL, DNS). */
function coverHttpGet(string $url, int $timeout = 20): array {
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'WebShelf/1.0 (school project; cover cache)'];
    if (!ini_get('curl.cainfo') && !ini_get('openssl.cafile')) {
        foreach (['C:/laragon/etc/ssl/cacert.pem', 'C:/laragon/bin/php/cacert.pem'] as $ca) {
            if (is_file($ca)) { $opts[CURLOPT_CAINFO] = $ca; break; }
        }
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = $body === false ? curl_error($ch) : '';
    curl_close($ch);
    if ($body === false) error_log("WebShelf cover: $err ($url)");
    coverLog("GET $url -> HTTP $code" . ($err ? " ($err)" : ''));
    return [$body === false ? null : $body, $code, $err];
}

/** GET with one retry when the failure looks temporary (network error, rate limit, 5xx). */
function coverHttpGetRetry(string $url, bool &$temporary): array {
    $r = coverHttpGet($url);
    if ($r[1] === 0 || $r[1] === 429 || $r[1] >= 500) {
        usleep(700000);
        $r = coverHttpGet($url);
        if ($r[1] === 0 || $r[1] === 429 || $r[1] >= 500) $temporary = true;
    }
    return $r;
}

/** True when $body is a real, reasonably sized image (rejects 1px placeholders). */
function coverIsImage(?string $body): bool {
    if ($body === null || strlen($body) < 1500) return false;
    $info = @getimagesizefromstring($body);
    return $info !== false && $info[0] >= 60 && $info[1] >= 80;
}

if (!is_file($file)) {
    // A recent miss: don't hammer the remote sites yet
    if (is_file($miss) && (int)@file_get_contents($miss) > time() && !$debug) { http_response_code(404); exit; }

    $body = null;
    $temporary = false;   // true when a failure was only a timeout / rate limit, so we retry soon

    // 1) Open Library: search by title + author, take the first result that has a cover
    $q = http_build_query(['title' => $book['title'], 'author' => (string)$book['author'], 'limit' => 5, 'fields' => 'cover_i']);
    [$json] = coverHttpGetRetry("https://openlibrary.org/search.json?$q", $temporary);
    if ($json !== null) {
        $data = json_decode($json, true);
        $tried = 0;
        foreach (($data['docs'] ?? []) as $doc) {
            if (empty($doc['cover_i']) || $tried++ >= 3) continue;
            [$img, $code] = coverHttpGetRetry('https://covers.openlibrary.org/b/id/' . (int)$doc['cover_i'] . '-M.jpg?default=false', $temporary);
            if (coverIsImage($img)) { $body = $img; coverLog('Open Library cover accepted'); break; }
        }
    }

    // 2) Project Gutenberg cover
    if ($body === null && !empty($book['gutenberg_id'])) {
        $gid = (int)$book['gutenberg_id'];
        [$img] = coverHttpGetRetry("https://www.gutenberg.org/cache/epub/$gid/pg$gid.cover.medium.jpg", $temporary);
        if (coverIsImage($img)) { $body = $img; coverLog('Gutenberg cover accepted'); }
    } elseif ($body === null) {
        coverLog('No gutenberg_id for this book');
    }

    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if ($body === null) {
        // Temporary trouble: retry in 2 minutes. Genuinely no cover found: retry in 1 day.
        @file_put_contents($miss, (string)(time() + ($temporary ? 120 : 86400)));
        if ($debug) { header('Content-Type: text/plain; charset=utf-8'); echo "NO COVER (" . ($temporary ? 'temporary failure, retries in 2 min' : 'not found, retries in 1 day') . ")\n\n" . implode("\n", $steps); exit; }
        http_response_code(404);
        exit;
    }
    file_put_contents($file, $body);
    @unlink($miss);
}

if ($debug) { header('Content-Type: text/plain; charset=utf-8'); echo "COVER CACHED OK: $file\n\n" . implode("\n", $steps); exit; }
$info = @getimagesize($file);
header('Content-Type: ' . ($info['mime'] ?? 'image/jpeg'));
header('Cache-Control: public, max-age=604800');
readfile($file);
